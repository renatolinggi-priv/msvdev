<?php
/**
 * inc/einsatzplanung/plan_helpers.inc.php
 *
 * Gemeinsame Logik der Einsatzplanung (Admin-Seite, Endpunkte, Exporte, Portal-Ansicht).
 *
 * Modell (Migration 050):
 *   einsatz_plaene           Plan pro Anlass und Jahr (typ, layout, status, fusstext)
 *   einsatz_plan_termine     Spalten: Schiesstage bzw. Schichten
 *   einsatz_plan_funktionen  Zeilen: Funktionen (anzahl = Slots pro Termin) bzw. Einsatztypen (Chilbi)
 *   einsatz_plan_slots       eine Position = Termin × Funktion × pos; verein + mitglied_id oder name_text
 *
 * einsatz_zuweisungen (Lesetabelle für Portal/Tausch/Cron/ICS) wird per einsatzplanProjizieren()
 * aus den Slots gefüllt – Schlüssel ist slot_id. Der Tausch schreibt in api/einsatz_tausch.php
 * das Mitglied in den Slot zurück, damit der Slot immer den Endstand hält.
 */

const EP_VEREINE = ['msv' => 'MSV Wilen', 'freienbach' => 'SV Freienbach', 'wollerau' => 'SV Wollerau'];
const EP_TYPEN   = ['obligatorisch' => 'Obligatorisch', 'feldschiessen' => 'Feldschiessen', 'schlossturm' => 'Schlossturmschiessen', 'chilbi' => 'Wiler Chilbi', 'sonstiges' => 'Sonstiges'];
const EP_LAYOUTS = ['funktion_x_termin' => 'Funktionen × Termine', 'person_x_schicht' => 'Personen × Schichten'];
const EP_STATUS  = ['entwurf' => 'Entwurf', 'freigegeben' => 'Freigegeben', 'final' => 'Final'];
/** Anfrage-Rollen (Personalanfrage / Umfrage). 'OK' = vom OK fest besetzt, keine automatische Einteilung. */
const EP_ROLLEN  = ['Büro' => 'Büro', 'Schützenmeister' => 'Schützenmeister', 'Warner' => 'Warner', 'Türkontrolle' => 'Türkontrolle', 'Parkdienst' => 'Parkdienst', 'OK' => 'OK Schlossturm (Positionen zählen als OK)'];
const EP_QUELLEN = ['umfrage' => 'Umfrage', 'excel' => 'Excel', 'manuell' => 'manuell'];

const EP_FUSSTEXT_DEFAULT = "Bei Verhinderung bitte SELBST für Ersatz oder Abtausch sorgen!\n"
    . "Ich bitte Euch 30 Minuten vor Schiessbeginn zu erscheinen, damit der Schiessbetrieb pünktlich begonnen werden kann.\n"
    . "Herzlichen Dank";
const EP_FUSSTEXT_CHILBI = "Bei Verhinderung bitte selbst für Abtausch oder Ersatz sorgen!\n"
    . "Getränke sind nur während des persönlichen Arbeitseinsatzes gratis. Ausnahme: Eis-Shot schiessen muss bezahlt werden.\n"
    . "Wir bitten euch Alkohol nur in Massen zu konsumieren, es ist ein Arbeitseinsatz!";

const EP_FUSSTEXT_SCHLOSSTURM = "Wichtige Infos:\n"
    . "- Bei Verhinderung unbedingt selber im eigenen Verein für Ersatz / Abtausch sorgen\n"
    . "- Für alle Helfer, die am 1. Samstag den ganzen Tag eingeteilt sind, wird das Mittagessen organisiert und bezahlt\n"
    . "- Znüni / Zvieri inkl. Kaffee / Mineralwasser werden organisiert\n"
    . "- Es können keine kostenlosen Getränke vom Restaurant bezogen werden\n"
    . "Herzlichen Dank an alle Helferinnen und Helfer!";

const EP_WOCHENTAGE = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
const EP_WOCHENTAGE_KURZ = ['So.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.'];
const EP_MONATE = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

/** JSON-Antwort ausgeben und beenden. */
function ep_json(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

/** Standard-Layout für einen Plantyp. */
function ep_layout_fuer_typ(string $typ): string
{
    return $typ === 'chilbi' ? 'person_x_schicht' : 'funktion_x_termin';
}

/** Standard-Fusstext für einen Plantyp. */
function ep_fusstext_default(string $typ): string
{
    if ($typ === 'chilbi') return EP_FUSSTEXT_CHILBI;
    if ($typ === 'schlossturm') return EP_FUSSTEXT_SCHLOSSTURM;
    return EP_FUSSTEXT_DEFAULT;
}

// ---------------------------------------------------------------------------
//  Laden
// ---------------------------------------------------------------------------

/** Alle Mitglieder als Map ID => Zeile (Name, Vorname, Status, Verstorben). */
function ep_mitglieder_map(PDO $db): array
{
    $map = [];
    foreach ($db->query("SELECT ID, Name, Vorname, Status, Verstorben FROM mitglieder ORDER BY Name, Vorname") as $m) {
        $map[(int)$m['ID']] = $m;
    }
    return $map;
}

/** «von Euw Monika» → ['von Euw', 'Monika'] (letztes Wort = Vorname). */
function ep_name_split(string $name): array
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    if (count($parts) < 2) return [trim($name), ''];
    $vorname = array_pop($parts);
    return [implode(' ', $parts), $vorname];
}

/** Externe (MSV ohne Mitgliedschaft, Klartext) im Plan: [name => Anzahl Positionen], sortiert. */
function ep_externe_im_plan(array $plan): array
{
    $ext = [];
    foreach ($plan['slots'] as $s) {
        if (($s['verein'] ?? 'msv') !== 'msv' || !empty($s['mitglied_id'])) continue;
        $n = trim((string)($s['name_text'] ?? ''));
        if ($n !== '') $ext[$n] = ($ext[$n] ?? 0) + 1;
    }
    ksort($ext, SORT_NATURAL | SORT_FLAG_CASE);
    return $ext;
}

/** «Name Vorname» (Schreibweise wie in einsatz_zuweisungen.mitglied_name). */
function ep_name_vorname(array $m): string
{
    return trim(($m['Name'] ?? '') . ' ' . ($m['Vorname'] ?? ''));
}

/**
 * Planliste eines Jahres (oder alle Jahre bei null) mit Kennzahlen.
 * besetzt = Slots mit Mitglied oder Namen, offen = leere Slots.
 */
function ep_plan_liste(PDO $db, ?int $jahr): array
{
    $sql = "SELECT p.*,
                   (SELECT COUNT(*) FROM einsatz_plan_termine t WHERE t.plan_id = p.id) AS anz_termine,
                   (SELECT COUNT(*) FROM einsatz_plan_slots s WHERE s.plan_id = p.id) AS anz_slots,
                   (SELECT COUNT(*) FROM einsatz_plan_slots s WHERE s.plan_id = p.id
                        AND (s.mitglied_id IS NOT NULL OR (s.name_text IS NOT NULL AND s.name_text <> ''))) AS anz_besetzt
              FROM einsatz_plaene p";
    $params = [];
    if ($jahr !== null) { $sql .= " WHERE p.jahr = ?"; $params[] = $jahr; }
    $sql .= " ORDER BY p.jahr DESC, FIELD(p.typ,'obligatorisch','feldschiessen','schlossturm','chilbi','sonstiges'), p.titel";
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Plan mit Termine, Funktionen und Slots laden.
 * Rückgabe: Plan-Zeile + 'termine' (sortiert), 'funktionen' (sortiert), 'slots' (Liste),
 * 'slot_index' ["termin_id|funktion_id|pos" => slot]. null wenn nicht vorhanden.
 */
function ep_plan_laden(PDO $db, int $planId): ?array
{
    $st = $db->prepare("SELECT * FROM einsatz_plaene WHERE id = ?");
    $st->execute([$planId]);
    $plan = $st->fetch();
    if (!$plan) return null;

    $st = $db->prepare("SELECT * FROM einsatz_plan_termine WHERE plan_id = ? ORDER BY datum, zeit_von, sort, id");
    $st->execute([$planId]);
    $plan['termine'] = $st->fetchAll();

    $st = $db->prepare("SELECT * FROM einsatz_plan_funktionen WHERE plan_id = ? ORDER BY sort, id");
    $st->execute([$planId]);
    $plan['funktionen'] = $st->fetchAll();

    $st = $db->prepare("SELECT * FROM einsatz_plan_slots WHERE plan_id = ? ORDER BY termin_id, funktion_id, pos");
    $st->execute([$planId]);
    $plan['slots'] = $st->fetchAll();

    $plan['slot_index'] = [];
    $plan['zellen'] = [];        // "termin_id|funktion_id" => [slots nach pos]
    foreach ($plan['slots'] as $s) {
        $plan['slot_index'][$s['termin_id'] . '|' . $s['funktion_id'] . '|' . $s['pos']] = $s;
        $plan['zellen'][$s['termin_id'] . '|' . $s['funktion_id']][] = $s;
    }

    // Spalten späterer Migrationen tolerant ergänzen (052: info, 053: rolle/umfrage_id/vorschlag)
    foreach ($plan['termine'] as &$t) {
        if (!array_key_exists('info', $t)) $t['info'] = null;
        if (!array_key_exists('pauschale_std', $t)) $t['pauschale_std'] = null;   // Migration 058
    } unset($t);
    foreach ($plan['funktionen'] as &$f) { if (!array_key_exists('rolle', $f)) $f['rolle'] = null; } unset($f);
    if (!array_key_exists('umfrage_id', $plan)) $plan['umfrage_id'] = null;
    if (!array_key_exists('farbe', $plan)) $plan['farbe'] = null;   // Migration 055
    $slotDefaults = function (array $s): array {
        $s['vorschlag'] ??= 0;
        if (!array_key_exists('anwesend', $s)) $s['anwesend'] = null;
        $s['ok'] ??= 0;                                                          // Migration 058
        if (!array_key_exists('stunden_korrektur', $s)) $s['stunden_korrektur'] = null;
        return $s;
    };
    $plan['slots'] = array_map($slotDefaults, $plan['slots']);
    $plan['slot_index'] = array_map($slotDefaults, $plan['slot_index']);
    foreach ($plan['zellen'] as $k => $z) $plan['zellen'][$k] = array_map($slotDefaults, $z);
    $plan['anz_vorschlaege'] = count(array_filter($plan['slots'], fn($s) => (int)$s['vorschlag'] === 1));

    // Verfügbarkeiten (Migration 053; vor der Migration leer)
    try { $plan['verfuegbarkeit'] = ep_verfuegbarkeit_laden($db, $planId); } catch (Throwable $e) { $plan['verfuegbarkeit'] = []; }

    // Soll pro Termin × Funktion (Migration 051; vor der Migration leer)
    $plan['soll'] = [];
    try {
        $st = $db->prepare("SELECT termin_id, funktion_id, soll FROM einsatz_plan_soll WHERE plan_id = ?");
        $st->execute([$planId]);
        foreach ($st->fetchAll() as $r) $plan['soll'][$r['termin_id'] . '|' . $r['funktion_id']] = (int)$r['soll'];
    } catch (Throwable $e) { $plan['soll'] = []; }
    return $plan;
}

// ---------------------------------------------------------------------------
//  Verfügbarkeiten (Personalanfrage) und Vorschläge
// ---------------------------------------------------------------------------

/** Verfügbarkeiten eines Plans, JSON-Felder dekodiert, sortiert nach Verein und Name. */
function ep_verfuegbarkeit_laden(PDO $db, int $planId): array
{
    $st = $db->prepare("SELECT v.*, m.Name, m.Vorname FROM einsatz_plan_verfuegbarkeit v LEFT JOIN mitglieder m ON m.ID = v.mitglied_id
                         WHERE v.plan_id = ? ORDER BY FIELD(v.verein,'msv','freienbach','wollerau'), COALESCE(m.Name, v.name_text), m.Vorname");
    $st->execute([$planId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $r['rollen']     = array_values(array_filter((array)json_decode((string)$r['rollen'], true)));
        $r['termin_ids'] = array_values(array_map('intval', array_filter((array)json_decode((string)$r['termin_ids'], true), 'is_numeric')));
        $r['name']       = !empty($r['mitglied_id']) && $r['Name'] !== null ? trim($r['Name'] . ' ' . $r['Vorname']) : (string)$r['name_text'];
        $r['person_key'] = ep_person_key((int)$r['mitglied_id'], (string)$r['name_text']);
        $out[] = $r;
    }
    return $out;
}

/** Schlüssel einer Person: Mitglied per ID, Externe per Name. */
function ep_person_key(int $mitgliedId, string $nameText): string
{
    return $mitgliedId > 0 ? 'm' . $mitgliedId : 'n' . mb_strtolower(trim(preg_replace('/\s+/', ' ', $nameText)));
}

/** Vorbelegung der Anfrage-Rolle aus dem Funktionsnamen (Schlossturm-OK-Liste). */
function ep_rolle_vorbelegung(string $bezeichnung, ?array $okFunktionen = null): ?string
{
    if ($okFunktionen !== null && in_array(ep_funktion_norm($bezeichnung), $okFunktionen, true)) return 'OK';   // Definition «immer vom OK besetzt»
    $b = mb_strtolower(trim($bezeichnung));
    if (in_array($b, ['standblätter', 'kasse', 'munition', 'auszahlung', 'auszeichnungen'], true)) return 'Büro';
    foreach (['Schützenmeister', 'Warner', 'Türkontrolle', 'Parkdienst'] as $k) if (str_contains($b, mb_strtolower($k))) return $k;
    return null;
}

// ---------------------------------------------------------------------------
//  Funktionen, die immer vom OK besetzt sind (Definition über alle Jahre, settings.einsatzplan_ok_funktionen)
//  → Rolle 'OK' auf der Planfunktion; alle Positionen zählen als OK (ep_slot_ist_ok). Pflege im Dialog «OK-Mitglieder».
// ---------------------------------------------------------------------------

const EP_OK_FUNKTIONEN_KEY     = 'einsatzplan_ok_funktionen';
const EP_OK_FUNKTIONEN_DEFAULT = ['edv / anlage', 'schiessleitung'];   // Abrechnung 2025/2026: alle Positionen OK

/** Funktionsname normalisiert für Vergleiche: klein, Leerzeichen um «/» und «-» vereinheitlicht. */
function ep_funktion_norm(string $bezeichnung): string
{
    $b = mb_strtolower(trim($bezeichnung));
    $b = preg_replace('/\s*([\/–-])\s*/u', ' $1 ', $b);
    return trim(preg_replace('/\s+/u', ' ', $b));
}

/** Definierte OK-Funktionen (normalisierte Namen); Default, solange nichts gespeichert ist. */
function ep_ok_funktionen_laden(PDO $db): array
{
    try {
        $st = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $st->execute([EP_OK_FUNKTIONEN_KEY]);
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null) {
            $arr = json_decode((string)$v, true);
            if (is_array($arr)) return array_values(array_unique(array_map('ep_funktion_norm', array_filter($arr, 'is_string'))));
        }
    } catch (Throwable $e) {}
    return EP_OK_FUNKTIONEN_DEFAULT;
}

/** Definierte OK-Funktionen speichern (normalisiert, ohne Doppel). */
function ep_ok_funktionen_speichern(PDO $db, array $namen): void
{
    $namen = array_values(array_unique(array_filter(array_map(fn($n) => ep_funktion_norm((string)$n), $namen), fn($n) => $n !== '')));
    sort($namen);
    $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
       ->execute([EP_OK_FUNKTIONEN_KEY, json_encode($namen, JSON_UNESCAPED_UNICODE)]);
}

/**
 * Definition auf einen Schlossturm-Plan anwenden: Funktion in der Liste → Rolle 'OK'; Rolle 'OK' ohne Listeneintrag →
 * zurück auf die übrige Vorbelegung (Büro/Warner/… oder NULL). Rückgabe: [funktion_id => ist_ok].
 */
function ep_ok_funktionen_anwenden(PDO $db, int $planId): array
{
    $liste = ep_ok_funktionen_laden($db);
    $out = [];
    try {
        $st = $db->prepare("SELECT f.id, f.bezeichnung, f.rolle FROM einsatz_plan_funktionen f JOIN einsatz_plaene p ON p.id = f.plan_id WHERE f.plan_id = ? AND p.typ = 'schlossturm'");
        $st->execute([$planId]);
        $upd = $db->prepare("UPDATE einsatz_plan_funktionen SET rolle = ? WHERE id = ?");
        foreach ($st->fetchAll() as $f) {
            $soll = in_array(ep_funktion_norm((string)$f['bezeichnung']), $liste, true);
            $out[(int)$f['id']] = $soll;
            if ($soll && $f['rolle'] !== 'OK') $upd->execute(['OK', (int)$f['id']]);
            elseif (!$soll && $f['rolle'] === 'OK') $upd->execute([ep_rolle_vorbelegung((string)$f['bezeichnung']), (int)$f['id']]);
        }
    } catch (Throwable $e) { /* vor Migration 053 */ }
    return $out;
}

/** Optionstext («OK Schlossturm», «Schützenmeister ») → Rollenschlüssel aus EP_ROLLEN oder null. */
function ep_rolle_aus_text(string $text): ?string
{
    $t = mb_strtolower(trim($text));
    if ($t === '') return null;
    foreach (EP_ROLLEN as $key => $label) {
        if ($t === mb_strtolower($key) || $t === mb_strtolower($label)) return $key;
    }
    if (str_contains($t, 'ok')) return 'OK';
    if (str_contains($t, 'büro') || str_contains($t, 'buero')) return 'Büro';
    foreach (['Schützenmeister', 'Warner', 'Türkontrolle', 'Parkdienst'] as $k) if (str_contains($t, mb_strtolower($k))) return $k;
    return null;
}

/**
 * Text mit Datum (+ Startzeit) → Termin-ID des Plans, z.B. «Samstag, 18.04.2026, 08:00 - 12:00» oder
 * «Sonntag, 19.04.2026, 09:30 - 11.30». Ohne Zeit: eindeutiger Termin des Datums, sonst null.
 */
function ep_termin_aus_text(array $plan, string $text): ?int
{
    if (!preg_match('/(\d{1,2})[.,](\d{1,2})[.,](\d{4})/', $text, $m)) return null;
    $datum = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    $rest  = substr($text, strpos($text, $m[0]) + strlen($m[0]));
    $zeit  = preg_match('/(\d{1,2})[:.h](\d{2})/', $rest, $z) ? sprintf('%02d:%02d', $z[1], $z[2]) : null;
    $treffer = [];
    foreach ($plan['termine'] as $t) {
        if ($t['datum'] !== $datum) continue;
        if ($zeit === null || substr((string)$t['zeit_von'], 0, 5) === $zeit) $treffer[] = (int)$t['id'];
    }
    if (count($treffer) === 1) return $treffer[0];
    // Zeit passt nicht exakt (z.B. Tippfehler): einziger Termin des Tages gewinnt
    $tag = array_values(array_filter($plan['termine'], fn($t) => $t['datum'] === $datum));
    return count($tag) === 1 ? (int)$tag[0]['id'] : null;
}

/** Slot ist fest besetzt (kein Vorschlag)? */
function ep_slot_fix(array $slot): bool
{
    return ep_slot_besetzt($slot) && (int)($slot['vorschlag'] ?? 0) === 0;
}

// ---------------------------------------------------------------------------
//  OK-Mitglieder (Schlossturm, Helferabrechnung): drei Quellen
//    1. Funktion mit Rolle 'OK' → alle Positionen zählen als OK (EDV/Anlage, Schiessleitung)
//    2. Stammliste einsatz_ok_personen (Migration 060) → Person ist in jedem Plan OK (wird auf slots.ok übertragen)
//    3. slots.ok je Position (Migration 058) – Import «OK» statt «x», Panel, Dialog «OK-Mitglieder»
// ---------------------------------------------------------------------------

/** Zählt die Position als OK? (slots.ok oder Funktion mit Rolle 'OK') */
function ep_slot_ist_ok(array $slot, ?array $funktion = null): bool
{
    return (int)($slot['ok'] ?? 0) === 1 || ($funktion !== null && ($funktion['rolle'] ?? '') === 'OK');
}

// ---------------------------------------------------------------------------
//  Vereinszuordnung von Klartext-Personen (Partnervereine): keine Stammtabelle, sondern das, was das Tool schon weiss –
//  Positionen aller Pläne und Verfügbarkeiten. Dient der Plausibilisierung bei Import und Rückmeldung.
// ---------------------------------------------------------------------------

/** Bekannte Vereine je Klartextname: [name klein => [verein => Anzahl]] aus allen Plänen (ausser $ohnePlanId) und Verfügbarkeiten. */
function ep_personen_vereine(PDO $db, int $ohnePlanId = 0): array
{
    $map = [];
    $add = function (?string $name, ?string $verein) use (&$map) {
        $n = mb_strtolower(trim((string)$name));
        if ($n === '' || !$verein) return;
        $map[$n][$verein] = ($map[$n][$verein] ?? 0) + 1;
    };
    try {
        $st = $db->prepare("SELECT name_text, verein FROM einsatz_plan_slots WHERE mitglied_id IS NULL AND name_text IS NOT NULL AND name_text <> '' AND plan_id <> ?");
        $st->execute([$ohnePlanId]);
        foreach ($st as $r) $add($r['name_text'], $r['verein']);
    } catch (Throwable $e) {}
    try {
        foreach ($db->query("SELECT name_text, verein FROM einsatz_plan_verfuegbarkeit WHERE mitglied_id IS NULL AND name_text IS NOT NULL AND name_text <> ''") as $r) $add($r['name_text'], $r['verein']);
    } catch (Throwable $e) {}
    return $map;
}

/** Eindeutig dominanter Verein einer Person aus einer Zählung [verein => n] (null bei unbekannt oder Gleichstand). */
function ep_verein_bekannt(array $map, string $name): ?string
{
    $z = $map[mb_strtolower(trim($name))] ?? [];
    if (!$z) return null;
    arsort($z);
    $keys = array_keys($z);
    if (count($keys) > 1 && $z[$keys[0]] === $z[$keys[1]]) return null;
    return $keys[0];
}

/** Stammliste der OK-Personen: ['m' => [mitglied_id => bemerkung], 'n' => [name klein => bemerkung]]; leer vor Migration 060. */
function ep_ok_stamm_laden(PDO $db): array
{
    $out = ['m' => [], 'n' => []];
    try {
        foreach ($db->query("SELECT mitglied_id, name_text, bemerkung FROM einsatz_ok_personen") as $r) {
            if (!empty($r['mitglied_id'])) $out['m'][(int)$r['mitglied_id']] = (string)$r['bemerkung'];
            elseif (trim((string)$r['name_text']) !== '') $out['n'][mb_strtolower(trim((string)$r['name_text']))] = (string)$r['bemerkung'];
        }
    } catch (Throwable $e) { /* Migration 060 noch nicht ausgeführt */ }
    return $out;
}

/** Person in der Stammliste? */
function ep_ok_stamm_hat(array $stamm, int $mitgliedId, string $nameText): bool
{
    if ($mitgliedId > 0) return isset($stamm['m'][$mitgliedId]);
    $n = mb_strtolower(trim($nameText));
    return $n !== '' && isset($stamm['n'][$n]);
}

/**
 * Nach einem Personenwechsel auf Positionen: OK-Kennzeichen neu bewerten (Stammliste → 1, sonst 0), damit das
 * Kennzeichen der vorherigen Person nicht stehen bleibt. Für Rückmeldung, Tausch, Import-Korrektur, Drag & Drop.
 */
function ep_ok_nach_personenwechsel(PDO $db, array $slotIds): void
{
    $slotIds = array_values(array_filter(array_map('intval', $slotIds)));
    if (!$slotIds) return;
    try {
        $stamm = ep_ok_stamm_laden($db);
        $st = $db->prepare("SELECT id, mitglied_id, name_text FROM einsatz_plan_slots WHERE id IN (" . implode(',', $slotIds) . ")");
        $st->execute();
        $upd = $db->prepare("UPDATE einsatz_plan_slots SET ok = ? WHERE id = ?");
        foreach ($st->fetchAll() as $s) {
            $besetzt = !empty($s['mitglied_id']) || trim((string)$s['name_text']) !== '';
            $upd->execute([$besetzt && ep_ok_stamm_hat($stamm, (int)$s['mitglied_id'], (string)$s['name_text']) ? 1 : 0, (int)$s['id']]);
        }
    } catch (Throwable $e) { /* vor Migration 058 */ }
}

/** Stammliste auf einen Plan anwenden: slots.ok = 1 für alle Positionen von Stamm-Personen. Rückgabe: geänderte Slots. */
function ep_ok_stammliste_anwenden(PDO $db, int $planId): int
{
    $stamm = ep_ok_stamm_laden($db);
    if (!$stamm['m'] && !$stamm['n']) return 0;
    try {
        $st = $db->prepare("SELECT id, mitglied_id, name_text FROM einsatz_plan_slots WHERE plan_id = ? AND ok = 0 AND (mitglied_id IS NOT NULL OR (name_text IS NOT NULL AND name_text <> ''))");
        $st->execute([$planId]);
        $ids = [];
        foreach ($st->fetchAll() as $s) if (ep_ok_stamm_hat($stamm, (int)$s['mitglied_id'], (string)$s['name_text'])) $ids[] = (int)$s['id'];
        if ($ids) $db->prepare("UPDATE einsatz_plan_slots SET ok = 1 WHERE id IN (" . implode(',', $ids) . ")")->execute();
        return count($ids);
    } catch (Throwable $e) { return 0; }   // vor Migration 058
}

/** Plan-Kopie ohne Vorschlags-Personen (für Word/PDF/Excel/Portal/Projektion). */
function ep_plan_ohne_vorschlaege(array $plan): array
{
    $strip = function (array $s): array {
        if ((int)($s['vorschlag'] ?? 0) === 1) { $s['mitglied_id'] = null; $s['name_text'] = null; $s['vorschlag'] = 0; }
        return $s;
    };
    $plan['slots'] = array_map($strip, $plan['slots']);
    $plan['slot_index'] = array_map($strip, $plan['slot_index']);
    foreach ($plan['zellen'] as $k => $z) $plan['zellen'][$k] = array_map($strip, $z);
    return $plan;
}

/**
 * Besetzung eines Termins je Funktion: [['funktion' => f, 'ist' => n, 'soll' => n|null], ...]
 * Nur Funktionen mit Ist > 0 oder Soll > 0. Layout funktion_x_termin: Soll = anzahl.
 */
function ep_besetzung(array $plan, int $terminId): array
{
    $out = [];
    foreach ($plan['funktionen'] as $f) {
        $key = $terminId . '|' . $f['id'];
        $slots = $plan['zellen'][$key] ?? [];
        $ist = count(array_filter($slots, 'ep_slot_besetzt'));
        if ($plan['layout'] === 'funktion_x_termin') $soll = ep_anzahl_pos($plan, $terminId, $f);
        else $soll = $plan['soll'][$key] ?? null;
        if ($ist === 0 && !$soll) continue;
        $out[] = ['funktion' => $f, 'ist' => $ist, 'soll' => $soll];
    }
    return $out;
}

/** Kurzform der Besetzung eines Termins: «Bar 2/3 · Chef 1/1 · Aufstellen 5» */
function ep_besetzung_text(array $plan, int $terminId): string
{
    $teile = [];
    foreach (ep_besetzung($plan, $terminId) as $b) {
        $teile[] = $b['funktion']['bezeichnung'] . ' ' . $b['ist'] . ($b['soll'] !== null ? '/' . $b['soll'] : '');
    }
    return implode(' · ', $teile);
}

/** Termine nach Datum gruppieren: [datum => [termine]] (für «eine Tabelle pro Tag»). */
function ep_termine_pro_tag(array $termine): array
{
    $tage = [];
    foreach ($termine as $t) $tage[$t['datum']][] = $t;
    return $tage;
}

/**
 * Funktionen nach Gruppe zusammenfassen (Reihenfolge = sort). Jede Gruppe wird im Word eine
 * Tabellenzeile: ['gruppe' => 'Büro'|'', 'funktionen' => [...]].
 */
function ep_funktionen_gruppiert(array $funktionen): array
{
    $gruppen = [];
    $aktuell = null;
    foreach ($funktionen as $f) {
        $g = trim((string)($f['gruppe'] ?? ''));
        if ($aktuell === null || $aktuell['gruppe'] !== $g || $g === '') {
            if ($aktuell !== null) $gruppen[] = $aktuell;
            $aktuell = ['gruppe' => $g, 'funktionen' => []];
        }
        $aktuell['funktionen'][] = $f;
    }
    if ($aktuell !== null) $gruppen[] = $aktuell;
    return $gruppen;
}

// ---------------------------------------------------------------------------
//  Anzeige-Helfer
// ---------------------------------------------------------------------------

/** Slot hat einen Namen (Mitglied oder Klartext)? */
function ep_slot_besetzt(array $slot): bool
{
    return !empty($slot['mitglied_id']) || trim((string)($slot['name_text'] ?? '')) !== '';
}

/**
 * Text eines Slots für Word/PDF/Portal:
 *   Mitglied → «Name Vorname»; Klartext → name_text; Fremdverein ohne Namen → «SV Freienbach»;
 *   MSV ohne Namen → ''.
 */
function ep_slot_text(array $slot, array $mitglieder): string
{
    if (!empty($slot['mitglied_id']) && isset($mitglieder[(int)$slot['mitglied_id']])) {
        return ep_name_vorname($mitglieder[(int)$slot['mitglied_id']]);
    }
    $nt = trim((string)($slot['name_text'] ?? ''));
    if ($nt !== '') return $nt;
    $v = $slot['verein'] ?? 'msv';
    return $v === 'msv' ? '' : (EP_VEREINE[$v] ?? '');
}

/** Zeitangabe eines Termins: «18:00 – 20:00», nur Beginn, oder zeit_text (Chilbi «17:00-fertig»). */
function ep_zeit_text(array $t): string
{
    $zt = trim((string)($t['zeit_text'] ?? ''));
    if ($zt !== '') return $zt;
    $von = !empty($t['zeit_von']) ? substr($t['zeit_von'], 0, 5) : '';
    $bis = !empty($t['zeit_bis']) ? substr($t['zeit_bis'], 0, 5) : '';
    if ($von !== '' && $bis !== '') return $von . ' – ' . $bis;
    return $von;
}

/**
 * Abgerechnete Stunden einer Schicht (Helferabrechnung, Migration 058): pauschale_std, falls gesetzt,
 * sonst die Dauer aus zeit_von/zeit_bis (Mitternachtsüberlauf +24 h); 0 ohne Zeitangabe.
 * Einzige Rechenstelle – genutzt von Editor (Helferstunden-Box), Anwesenheits-Auswertung und Helferabrechnung.
 */
function ep_termin_stunden(array $t): float
{
    if (isset($t['pauschale_std']) && $t['pauschale_std'] !== '') return round((float)$t['pauschale_std'], 2);
    return ep_termin_dauer($t);
}

/** Schichtende bei offenen Chilbi-Angaben («17:00-fertig», «19:45-Ende»): fertig = 04:00 Uhr (Benutzerentscheid 21.09.2026). */
const EP_SCHICHTENDE_OFFEN = '04:00';

/**
 * Schichtdauer in Stunden aus zeit_von/zeit_bis; fehlen die Zeiten (Chilbi speichert nur zeit_text), wird der Text
 * gelesen: «16:00-19:00» → 3.0, «17:00-fertig»/«19:45-Ende» → bis EP_SCHICHTENDE_OFFEN (11.0 bzw. 8.25).
 * Mitternachtsüberlauf +24 h. 0 ohne verwertbare Angabe. Ohne Pauschale – für «Ist-Dauer» und als Basis von ep_termin_stunden().
 */
function ep_termin_dauer(array $t): float
{
    $von = !empty($t['zeit_von']) ? substr((string)$t['zeit_von'], 0, 5) : null;
    $bis = !empty($t['zeit_bis']) ? substr((string)$t['zeit_bis'], 0, 5) : null;
    if ($von === null || $bis === null) {
        $txt = trim((string)($t['zeit_text'] ?? ''));
        if (preg_match('/(\d{1,2})[:.](\d{2})\s*[-–]\s*(\d{1,2})[:.](\d{2})/u', $txt, $m)) {
            $von = sprintf('%02d:%02d', $m[1], $m[2]); $bis = sprintf('%02d:%02d', $m[3], $m[4]);
        } elseif (preg_match('/(\d{1,2})[:.](\d{2})\s*[-–]\s*(fertig|ende|schluss|open)/iu', $txt, $m)) {
            $von = sprintf('%02d:%02d', $m[1], $m[2]); $bis = EP_SCHICHTENDE_OFFEN;
        } else {
            return 0.0;
        }
    }
    $d = (strtotime($bis) - strtotime($von)) / 3600;
    if ($d < 0) $d += 24;
    return round($d, 2);
}

/** «Mittwoch 27. Mai» */
function ep_datum_lang(string $datum): string
{
    $ts = strtotime($datum);
    if ($ts === false) return $datum;
    return EP_WOCHENTAGE[(int)date('w', $ts)] . ' ' . (int)date('j', $ts) . '. ' . EP_MONATE[(int)date('n', $ts)];
}

/** «Mi. 18.11.2026» */
function ep_datum_kurz(string $datum): string
{
    $ts = strtotime($datum);
    if ($ts === false) return $datum;
    return EP_WOCHENTAGE_KURZ[(int)date('w', $ts)] . ' ' . date('d.m.Y', $ts);
}

/** Titelfarbe als 6-stelliges Hex ohne «#» (Word) – Standard D9D9D9. */
function ep_farbe_hex(?string $farbe, string $default = 'D9D9D9'): string
{
    $f = ltrim(trim((string)$farbe), '#');
    return preg_match('/^[0-9a-fA-F]{6}$/', $f) ? strtoupper($f) : $default;
}

/** Ist die Farbe dunkel (→ weisse Schrift)? */
function ep_farbe_dunkel(string $hex): bool
{
    $hex = ep_farbe_hex($hex);
    [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) < 150;
}

/** Dateiname-tauglicher Stamm: «Obligatorisch_2026» */
function ep_dateistamm(array $plan): string
{
    $s = preg_replace('/[^A-Za-z0-9_-]+/', '_', str_replace(['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue'], (string)$plan['titel']));
    return trim($s, '_') ?: 'Einsatzplan';
}

// ---------------------------------------------------------------------------
//  Struktur
// ---------------------------------------------------------------------------

/**
 * Layout funktion_x_termin: Anzahl Positionen einer Funktion an einem Termin.
 * Standard = funktion.anzahl; abweichend je Termin über einsatz_plan_soll (Schlossturm: Warner Sa 11, So 8 …).
 */
function ep_anzahl_pos(array $plan, int $terminId, array $f): int
{
    $soll = $plan['soll'][$terminId . '|' . $f['id']] ?? null;
    return max(1, $soll !== null && $soll > 0 ? (int)$soll : (int)$f['anzahl']);
}

/** Grösste Positionszahl einer Funktion über alle Termine (Zeilenzahl im Word / Raster). */
function ep_anzahl_max(array $plan, array $f): int
{
    $max = 1;
    foreach ($plan['termine'] as $t) $max = max($max, ep_anzahl_pos($plan, (int)$t['id'], $f));
    return $max;
}

/**
 * Layout funktion_x_termin: fehlende Slots anlegen (Termin × Funktion × pos 1..Anzahl je Termin).
 * Bestehende Slots bleiben unverändert (INSERT IGNORE auf uq_slot_pos).
 */
function ep_slots_sicherstellen(PDO $db, int $planId): int
{
    $plan = ep_plan_laden($db, $planId);
    if (!$plan || $plan['layout'] !== 'funktion_x_termin') return 0;
    $ins = $db->prepare("INSERT IGNORE INTO einsatz_plan_slots (plan_id, termin_id, funktion_id, pos, verein) VALUES (?, ?, ?, ?, 'msv')");
    $n = 0;
    foreach ($plan['termine'] as $t) {
        foreach ($plan['funktionen'] as $f) {
            for ($pos = 1; $pos <= ep_anzahl_pos($plan, (int)$t['id'], $f); $pos++) {
                if (isset($plan['slot_index'][$t['id'] . '|' . $f['id'] . '|' . $pos])) continue;
                $ins->execute([$planId, $t['id'], $f['id'], $pos]);
                $n += $ins->rowCount();
            }
        }
    }
    return $n;
}

/**
 * Slots einer Funktion oberhalb der neuen Anzahl löschen – nur leere. Liefert die Anzahl
 * besetzter Slots, die das Kürzen verhindern (0 = alles gelöscht).
 */
function ep_slots_kuerzen(PDO $db, int $planId, int $funktionId, int $anzahl): int
{
    $st = $db->prepare("SELECT COUNT(*) FROM einsatz_plan_slots WHERE plan_id = ? AND funktion_id = ? AND pos > ?
                          AND (mitglied_id IS NOT NULL OR (name_text IS NOT NULL AND name_text <> ''))");
    $st->execute([$planId, $funktionId, $anzahl]);
    $blockiert = (int)$st->fetchColumn();
    if ($blockiert > 0) return $blockiert;
    $db->prepare("DELETE FROM einsatz_plan_slots WHERE plan_id = ? AND funktion_id = ? AND pos > ?")
       ->execute([$planId, $funktionId, $anzahl]);
    return 0;
}

/**
 * Layout A: leere Slots einer Zelle (Termin × Funktion) oberhalb der neuen Anzahl löschen.
 * Rückgabe: Anzahl besetzter Slots, die das Kürzen verhindern (0 = ok).
 */
function ep_slots_kuerzen_zelle(PDO $db, int $planId, int $terminId, int $funktionId, int $anzahl): int
{
    $st = $db->prepare("SELECT COUNT(*) FROM einsatz_plan_slots WHERE plan_id = ? AND termin_id = ? AND funktion_id = ? AND pos > ?
                          AND (mitglied_id IS NOT NULL OR (name_text IS NOT NULL AND name_text <> ''))");
    $st->execute([$planId, $terminId, $funktionId, $anzahl]);
    $blockiert = (int)$st->fetchColumn();
    if ($blockiert > 0) return $blockiert;
    $db->prepare("DELETE FROM einsatz_plan_slots WHERE plan_id = ? AND termin_id = ? AND funktion_id = ? AND pos > ?")->execute([$planId, $terminId, $funktionId, $anzahl]);
    return 0;
}

/**
 * Schiesstage eines JM-Anlasses (JMDefinition.Bezeichnung passend zum Typ) als Termin-Vorschläge.
 * Rückgabe: [['datum' => 'Y-m-d', 'zeit_von' => 'HH:MM:SS', 'zeit_bis' => ...], ...]
 */
function ep_termine_aus_jm(PDO $db, string $typ, int $jahr): array
{
    $muster = EP_TYPEN[$typ] ?? '';
    if ($muster === '' || $typ === 'chilbi' || $typ === 'sonstiges') return [];
    $st = $db->prepare("SELECT s.schiesstag, s.start_time, s.end_time
                          FROM JMSchiesstage s
                          JOIN JMDefinition d ON d.ID = s.jm_id
                         WHERE d.year = ? AND d.Bezeichnung LIKE ?
                         ORDER BY s.schiesstag, s.start_time");
    $st->execute([$jahr, '%' . $muster . '%']);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[] = ['datum' => $r['schiesstag'], 'zeit_von' => $r['start_time'], 'zeit_bis' => $r['end_time']];
    }
    return $out;
}

/**
 * Datum um n Jahre verschieben, so dass der Wochentag erhalten bleibt (nächstliegender gleicher Wochentag):
 * Mi 27.05.2026 +1 Jahr → Mi 26.05.2027.
 */
function ep_datum_gleicher_wochentag(string $datum, int $jahrDiff): string
{
    $quelle = new DateTime($datum);
    $ziel = (clone $quelle)->modify(($jahrDiff >= 0 ? '+' : '') . $jahrDiff . ' year');
    $diff = ((int)$quelle->format('N') - (int)$ziel->format('N') + 7) % 7;   // Tage vorwärts bis gleicher Wochentag
    if ($diff > 3) $diff -= 7;                                                  // näher rückwärts
    return $ziel->modify(($diff >= 0 ? '+' : '') . $diff . ' day')->format('Y-m-d');
}

/**
 * Termine eines Quellplans in ein Zieljahr übernehmen (Datum auf gleichen Wochentag verschoben,
 * Zeiten/Info/Bezeichnung unverändert). Rückgabe: [quell_termin_id => neue_termin_id].
 */
function ep_termine_uebernehmen(PDO $db, array $quelle, int $neuId, int $zielJahr): array
{
    $ins = $db->prepare("INSERT INTO einsatz_plan_termine (plan_id, bezeichnung, datum, zeit_von, zeit_bis, zeit_text, info, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $map = [];
    $jahrDiff = $zielJahr - (int)$quelle['jahr'];
    foreach ($quelle['termine'] as $i => $t) {
        $datum = ep_termin_datum_vorschlag($quelle['typ'] ?? '', $t, $i, $zielJahr, $jahrDiff);
        $ins->execute([$neuId, $t['bezeichnung'], $datum, $t['zeit_von'], $t['zeit_bis'], $t['zeit_text'], $t['info'] ?? null, $t['sort']]);
        $map[(int)$t['id']] = (int)$db->lastInsertId();
        ep_termin_pauschale_kopieren($db, $t, $map[(int)$t['id']]);
    }
    return $map;
}

/** Pauschale (Migration 058) eines Quelltermins auf den neuen Termin übertragen – tolerant, falls Spalte fehlt. */
function ep_termin_pauschale_kopieren(PDO $db, array $quelle, int $neuTerminId): void
{
    if (!isset($quelle['pauschale_std']) || $quelle['pauschale_std'] === '') return;
    try { $db->prepare("UPDATE einsatz_plan_termine SET pauschale_std = ? WHERE id = ?")->execute([$quelle['pauschale_std'], $neuTerminId]); } catch (Throwable $e) {}
}

/**
 * Datumsvorschlag für einen übernommenen Termin. Standard: gleicher Wochentag im Zieljahr.
 * Regel Obligatorisch: das 3. Obligatorisch liegt immer auf dem letzten Mittwoch bzw. Freitag im August
 * (Wochentag wie im Vorjahr; ist er weder Mi noch Fr, gilt der letzte gleiche Wochentag im August).
 */
function ep_termin_datum_vorschlag(string $typ, array $termin, int $index, int $zielJahr, int $jahrDiff): string
{
    $quelle = new DateTime($termin['datum']);
    $istDrittes = $index === 2 || preg_match('/^3\s*\./', (string)$termin['bezeichnung']);
    if ($typ === 'obligatorisch' && $istDrittes && (int)$quelle->format('n') === 8) {
        // Regel: spätester Tag im August, der ein Mittwoch (3) oder Freitag (5) ist – der Wochentag darf
        // wechseln (Bundesprogramm endet am 31.08.): 2027 → Fr 27.08., 2028 → Mi 30.08., 2029 → Fr 31.08.
        $d = new DateTime(sprintf('%04d-08-31', $zielJahr));
        while (!in_array((int)$d->format('N'), [3, 5], true)) $d->modify('-1 day');
        return $d->format('Y-m-d');
    }
    return ep_datum_gleicher_wochentag($termin['datum'], $jahrDiff);
}

/** Termin-Bezeichnung für Obligatorisch: «1.Obligatorisch», «2.Obligatorisch» … sonst leer. */
function ep_termin_bezeichnung_auto(string $typ, int $index): string
{
    return $typ === 'obligatorisch' ? ($index + 1) . '.Obligatorisch' : '';
}

// ---------------------------------------------------------------------------
//  Kopie ins Folgejahr
// ---------------------------------------------------------------------------

/**
 * Plan kopieren (Vorlage = Endstand nach Tausch, da der Tausch den Slot zurückschreibt).
 * Termine: aus JM-Definition des Zieljahrs, falls gleich viele vorhanden – sonst Datum +1 Jahr.
 * Fremdvereins-Namen und Bemerkungen werden geleert, Verein und Mitglied bleiben.
 * Rückgabe: neue Plan-ID. Muss in einer Transaktion des Aufrufers laufen.
 */
function ep_plan_kopieren(PDO $db, array $quelle, int $zielJahr, string $titel, int $userId, bool $termineAusJm): int
{
    $db->prepare("INSERT INTO einsatz_plaene (jahr, typ, titel, layout, status, fusstext, vorlage_plan_id, erstellt_von)
                  VALUES (?, ?, ?, ?, 'entwurf', ?, ?, ?)")
       ->execute([$zielJahr, $quelle['typ'], $titel, $quelle['layout'], $quelle['fusstext'], $quelle['id'], $userId]);
    $neuId = (int)$db->lastInsertId();
    // Titelfarbe und Umfrage-Verknüpfung nicht zwingend vorhanden (Migrationen 053/055) → separat, tolerant
    try { $db->prepare("UPDATE einsatz_plaene SET farbe = ? WHERE id = ?")->execute([$quelle['farbe'] ?? null, $neuId]); } catch (Throwable $e) {}

    // Termine: aus der JM-Definition des Zieljahrs (wenn gleich viele), sonst auf den gleichen Wochentag verschoben
    $jmTermine = $termineAusJm ? ep_termine_aus_jm($db, $quelle['typ'], $zielJahr) : [];
    $nutzeJm = count($jmTermine) > 0 && count($jmTermine) === count($quelle['termine']);
    if ($nutzeJm) {
        $insT = $db->prepare("INSERT INTO einsatz_plan_termine (plan_id, bezeichnung, datum, zeit_von, zeit_bis, zeit_text, info, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $terminMap = [];
        foreach ($quelle['termine'] as $i => $t) {
            $insT->execute([$neuId, $t['bezeichnung'], $jmTermine[$i]['datum'], $jmTermine[$i]['zeit_von'], $jmTermine[$i]['zeit_bis'], $t['zeit_text'], $t['info'] ?? null, $t['sort']]);
            $terminMap[(int)$t['id']] = (int)$db->lastInsertId();
            ep_termin_pauschale_kopieren($db, $t, $terminMap[(int)$t['id']]);
        }
    } else {
        $terminMap = ep_termine_uebernehmen($db, $quelle, $neuId, $zielJahr);
    }

    // Funktionen (inkl. Anfrage-Rolle)
    $insF = $db->prepare("INSERT INTO einsatz_plan_funktionen (plan_id, gruppe, bezeichnung, rolle, anzahl, sort) VALUES (?, ?, ?, ?, ?, ?)");
    $funktionMap = [];
    foreach ($quelle['funktionen'] as $f) {
        $insF->execute([$neuId, $f['gruppe'], $f['bezeichnung'], $f['rolle'] ?? null, $f['anzahl'], $f['sort']]);
        $funktionMap[(int)$f['id']] = (int)$db->lastInsertId();
    }

    // Slots (Endstand; Vorschläge zählen nicht)
    $insS = $db->prepare("INSERT INTO einsatz_plan_slots (plan_id, termin_id, funktion_id, pos, verein, mitglied_id, name_text)
                          VALUES (?, ?, ?, ?, ?, ?, NULL)");
    foreach ($quelle['slots'] as $s) {
        $tid = $terminMap[(int)$s['termin_id']] ?? null;
        $fid = $funktionMap[(int)$s['funktion_id']] ?? null;
        if (!$tid || !$fid) continue;
        // Externe (MSV ohne Mitglied, nur Klartext) nicht übernehmen – ist im neuen Jahr offen
        $mid = (int)($s['vorschlag'] ?? 0) === 1 ? null : ($s['mitglied_id'] ?: null);
        $insS->execute([$neuId, $tid, $fid, $s['pos'], $s['verein'], $mid]);
        // OK-Kennzeichen (Migration 058) mitnehmen, nur wenn die Person übernommen wurde – tolerant, falls Spalte fehlt
        if ($mid && (int)($s['ok'] ?? 0) === 1) {
            try { $db->prepare("UPDATE einsatz_plan_slots SET ok = 1 WHERE id = ?")->execute([(int)$db->lastInsertId()]); } catch (Throwable $e) {}
        }
    }
    ep_ok_funktionen_anwenden($db, $neuId);   // Funktionen «immer OK» (settings), nur Schlossturm
    ep_ok_stammliste_anwenden($db, $neuId);   // dauerhafte OK-Personen (Migration 060)
    return $neuId;
}

// ---------------------------------------------------------------------------
//  Projektion nach einsatz_zuweisungen
// ---------------------------------------------------------------------------

/**
 * Plan in die Lesetabelle einsatz_zuweisungen schreiben (nur MSV-Slots mit Mitglied).
 * Upsert über slot_id; Zeilen dieses Plans, deren Slot nicht mehr MSV-besetzt ist, werden gelöscht.
 * Legacy-Zeilen (slot_id NULL) mit gleichem Typ, Jahr und Termin-Datum werden entfernt, damit
 * nach dem einmaligen Bootstrapping aus dem Dokument keine Doppel entstehen.
 * Rückgabe: ['upserts' => n, 'geloescht' => n, 'legacy' => n].
 */
function einsatzplanProjizieren(PDO $db, int $planId): array
{
    $plan = ep_plan_laden($db, $planId);
    if (!$plan) return ['upserts' => 0, 'geloescht' => 0, 'legacy' => 0];
    $plan = ep_plan_ohne_vorschlaege($plan);   // Vorschläge erreichen das Portal erst nach Übernahme
    $mitglieder = ep_mitglieder_map($db);
    $termine   = []; foreach ($plan['termine']   as $t) $termine[(int)$t['id']] = $t;
    $funktionen = []; foreach ($plan['funktionen'] as $f) $funktionen[(int)$f['id']] = $f;

    $up = $db->prepare("INSERT INTO einsatz_zuweisungen (typ, bezeichnung, event_datum, event_zeit, funktion, mitglied_name, mitglied_id, jahr, dokument_id, slot_id)
                        VALUES (:typ, :bez, :datum, :zeit, :funktion, :name, :mid, :jahr, NULL, :slot)
                        ON DUPLICATE KEY UPDATE typ = VALUES(typ), bezeichnung = VALUES(bezeichnung), event_datum = VALUES(event_datum),
                            event_zeit = VALUES(event_zeit), funktion = VALUES(funktion), mitglied_name = VALUES(mitglied_name),
                            mitglied_id = VALUES(mitglied_id), jahr = VALUES(jahr)");
    $aktiveSlotIds = [];
    $upserts = 0;
    foreach ($plan['slots'] as $s) {
        if (($s['verein'] ?? 'msv') !== 'msv' || empty($s['mitglied_id'])) continue;
        $t = $termine[(int)$s['termin_id']] ?? null;
        $f = $funktionen[(int)$s['funktion_id']] ?? null;
        $m = $mitglieder[(int)$s['mitglied_id']] ?? null;
        if (!$t || !$f || !$m) continue;
        $funktionText = trim((string)$f['gruppe']) !== '' ? $f['gruppe'] . ': ' . $f['bezeichnung'] : $f['bezeichnung'];
        $up->execute([
            ':typ'      => $plan['typ'],
            ':bez'      => trim((string)$t['bezeichnung']) !== '' ? $t['bezeichnung'] : $plan['titel'],
            ':datum'    => $t['datum'],
            ':zeit'     => ep_zeit_text($t) ?: null,
            ':funktion' => mb_substr($funktionText, 0, 100),
            ':name'     => mb_substr(ep_name_vorname($m), 0, 100),
            ':mid'      => (int)$s['mitglied_id'],
            ':jahr'     => (int)$plan['jahr'],
            ':slot'     => (int)$s['id'],
        ]);
        $aktiveSlotIds[] = (int)$s['id'];
        $upserts++;
    }

    // Zeilen dieses Plans ohne (noch) besetzten MSV-Slot entfernen
    $alleSlotIds = array_map(fn($s) => (int)$s['id'], $plan['slots']);
    $geloescht = 0;
    if ($alleSlotIds) {
        $ph = implode(',', array_fill(0, count($alleSlotIds), '?'));
        $params = $alleSlotIds;
        $sql = "DELETE FROM einsatz_zuweisungen WHERE slot_id IN ($ph)";
        if ($aktiveSlotIds) {
            $ph2 = implode(',', array_fill(0, count($aktiveSlotIds), '?'));
            $sql .= " AND slot_id NOT IN ($ph2)";
            $params = array_merge($params, $aktiveSlotIds);
        }
        $del = $db->prepare($sql);
        $del->execute($params);
        $geloescht = $del->rowCount();
    }

    // Legacy-Import-Zeilen desselben Anlasses (ohne slot_id) entfernen
    $legacy = 0;
    if ($plan['termine']) {
        $daten = array_values(array_unique(array_map(fn($t) => $t['datum'], $plan['termine'])));
        $typen = [$plan['typ']];
        if ($plan['typ'] === 'chilbi') $typen[] = 'einsatz';
        $phD = implode(',', array_fill(0, count($daten), '?'));
        $phT = implode(',', array_fill(0, count($typen), '?'));
        $del = $db->prepare("DELETE FROM einsatz_zuweisungen WHERE slot_id IS NULL AND jahr = ? AND typ IN ($phT) AND event_datum IN ($phD)");
        $del->execute(array_merge([(int)$plan['jahr']], $typen, $daten));
        $legacy = $del->rowCount();
    }

    return ['upserts' => $upserts, 'geloescht' => $geloescht, 'legacy' => $legacy];
}

/** Projektion nur, wenn der Plan bereits freigegeben ist (Entwürfe bleiben unsichtbar). */
function ep_projizieren_wenn_freigegeben(PDO $db, int $planId): void
{
    $st = $db->prepare("SELECT status FROM einsatz_plaene WHERE id = ?");
    $st->execute([$planId]);
    $status = $st->fetchColumn();
    if ($status && $status !== 'entwurf') einsatzplanProjizieren($db, $planId);
}

/**
 * Bestehende Legacy-Zeilen (Import) an die Slots eines Plans hängen: gleiche Person am gleichen
 * Datum. Eindeutige Treffer bekommen slot_id, damit offene Tausch-Anträge (Referenz auf die
 * Zeilen-ID) gültig bleiben und der Tausch den Slot findet. Rückgabe: Anzahl verknüpft.
 */
function ep_legacy_verknuepfen(PDO $db, int $planId): int
{
    $plan = ep_plan_laden($db, $planId);
    if (!$plan) return 0;
    $termine = []; foreach ($plan['termine'] as $t) $termine[(int)$t['id']] = $t;
    $sel = $db->prepare("SELECT id, event_zeit FROM einsatz_zuweisungen WHERE slot_id IS NULL AND jahr = ? AND event_datum = ? AND mitglied_id = ?");
    $upd = $db->prepare("UPDATE einsatz_zuweisungen SET slot_id = ? WHERE id = ? AND slot_id IS NULL");
    $n = 0;
    // Slots pro (Datum, Mitglied) sammeln
    $gruppen = [];
    foreach ($plan['slots'] as $s) {
        if (($s['verein'] ?? 'msv') !== 'msv' || empty($s['mitglied_id'])) continue;
        $t = $termine[(int)$s['termin_id']] ?? null;
        if (!$t) continue;
        $gruppen[$t['datum'] . '|' . $s['mitglied_id']][] = ['slot' => $s, 'termin' => $t];
    }
    foreach ($gruppen as $key => $kandidaten) {
        [$datum, $mid] = explode('|', $key);
        $sel->execute([(int)$plan['jahr'], $datum, (int)$mid]);
        $zeilen = $sel->fetchAll();
        if (!$zeilen) continue;
        if (count($zeilen) === 1 && count($kandidaten) === 1) {
            $upd->execute([(int)$kandidaten[0]['slot']['id'], (int)$zeilen[0]['id']]);
            $n += $upd->rowCount();
            continue;
        }
        // mehrere: über die Zeitangabe zuordnen
        foreach ($kandidaten as $k) {
            $zeit = ep_zeit_text($k['termin']);
            foreach ($zeilen as $idx => $z) {
                if (trim((string)$z['event_zeit']) === $zeit) {
                    $upd->execute([(int)$k['slot']['id'], (int)$z['id']]);
                    $n += $upd->rowCount();
                    unset($zeilen[$idx]);
                    break;
                }
            }
        }
    }
    return $n;
}

/**
 * Chilbi-Ansicht: Personen (Zeilen) aus den Slots ableiten, sortiert nach Name/Vorname.
 * Rückgabe: [mitglied_id => ['mitglied' => m, 'zellen' => [termin_id => [funktion, ...]]]]
 * Slots mit Klartext (ohne Mitglied) werden unter negativen Schlüsseln geführt.
 */
function ep_chilbi_personen(array $plan, array $mitglieder): array
{
    $funktionen = []; foreach ($plan['funktionen'] as $f) $funktionen[(int)$f['id']] = $f;
    $personen = [];
    $extern = 0;
    foreach ($plan['slots'] as $s) {
        if (!ep_slot_besetzt($s)) continue;
        if (!empty($s['mitglied_id'])) {
            $key = (int)$s['mitglied_id'];
            $m = $mitglieder[$key] ?? ['ID' => $key, 'Name' => '?', 'Vorname' => '', 'Status' => 0, 'Verstorben' => 0];
        } else {
            $extern--;
            $key = $extern;
            $parts = preg_split('/\s+/', trim((string)$s['name_text']), 2);
            $m = ['ID' => 0, 'Name' => $parts[0] ?? '', 'Vorname' => $parts[1] ?? '', 'Status' => 1, 'Verstorben' => 0, 'extern' => true];
        }
        if (!isset($personen[$key])) $personen[$key] = ['mitglied' => $m, 'zellen' => []];
        $f = $funktionen[(int)$s['funktion_id']] ?? null;
        if ($f) $personen[$key]['zellen'][(int)$s['termin_id']][] = $f;
    }
    uasort($personen, fn($a, $b) => strcasecmp(ep_name_vorname($a['mitglied']), ep_name_vorname($b['mitglied'])));
    return $personen;
}
