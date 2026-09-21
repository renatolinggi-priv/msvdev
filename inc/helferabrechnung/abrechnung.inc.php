<?php
/**
 * inc/helferabrechnung/abrechnung.inc.php – Rechenlogik der Helferabrechnung (Migration 058).
 *
 * Einzige Stelle, an der Helferstunden für die Vereinsabrechnung gebildet werden. Seite, Endpunkte,
 * Excel und PDF rufen ausschliesslich diese Funktionen – nirgends eigene Summen.
 *
 * Modell (Vorlage: Excel «Helferstunden Schlossturmschiessen», PDF-Abrechnung 2025):
 *   Zuteilung  = fest besetzter Slot (ep_slot_fix): Stunden = stunden_korrektur ?? ep_termin_stunden(termin)
 *                zählt nicht bei anwesend = 0 («nicht da»); anwesend NULL (nicht erfasst) zählt (Plan gilt)
 *                zählt nicht bei ok = 1, ausser Schalter «OK-Einsätze mitzählen»
 *   Manuelle Zeilen (einsatz_abr_zeilen) nach Kategorie:
 *                einsatz      → Zeile «Einsätze» (Nachträge, z.B. vergessener Einsatz)
 *                vorarbeit    → Zeile «Vor- & Nacharbeiten»
 *                ok_funktion  → Zeile «OK-Funktionen» (Präsident, Kasse … je 50 Std.)
 *   Matrix je Verein: einsaetze | vorarbeiten | ok_funktionen | total | anteil
 *
 * Alle Funktionen sind frei von Ausgabe; ha_matrix()/ha_pro_person() sind rein (ohne DB) und damit
 * per CLI testbar (scratch/test_abrechnung.php).
 */
require_once __DIR__ . '/../einsatzplanung/plan_helpers.inc.php';

const HA_KATEGORIEN = ['einsatz' => 'Nachtrag Einsatz', 'vorarbeit' => 'Vor- & Nacharbeit', 'ok_funktion' => 'OK-Funktion'];
/** Vorschlagswert je OK-Funktion (Abrechnung 2025: Präsident, Kasse, Schützenmeister … je 50 h). */
const HA_OK_FUNKTION_STD = 50.0;
const HA_ZEILEN     = ['einsaetze' => 'Einsätze gemäss Einsatzliste', 'vorarbeiten' => 'Vor- & Nacharbeiten', 'ok_funktionen' => 'OK-Funktionen'];
/** Vorlagen-Tätigkeiten aus der Abrechnung 2025 (Tab «Manuelle Zeilen», Auswahlliste; Freitext bleibt möglich). */
const HA_TAETIGKEITEN = [
    'vorarbeit'   => ['Festführer zusammenstellen', 'Festführer Layout / PDF druckfertig', 'Munition bereitstellen', 'Büro einrichten / EDV / Definition Fest', 'Verpflegung', 'Vorschiessen (2x Mittwochabend)', 'Aufräumen Reklamen', 'Aufräumen Büro / EDV / Munition'],
    'ok_funktion' => ['OK – Präsident', 'OK – Werbung / Sponsoring', 'OK – Kasse', 'OK – Schützenmeister', 'OK – Anmeldung / Internet'],
    'einsatz'     => ['Nachtrag Einsatz'],
];

/** Alle Schlossturm-Pläne, neuester zuerst: [{id, jahr, titel, status}]. */
function ha_plaene(PDO $db): array
{
    return $db->query("SELECT id, jahr, titel, status FROM einsatz_plaene WHERE typ = 'schlossturm' ORDER BY jahr DESC, id DESC")->fetchAll();
}

/** Schlossturm-Plan wählen: plan_id hat Vorrang, sonst der neueste Plan des Jahres. null wenn keiner. */
function ha_plan_waehlen(PDO $db, int $jahr, int $planId = 0): ?array
{
    if ($planId > 0) {
        $st = $db->prepare("SELECT id FROM einsatz_plaene WHERE id = ? AND typ = 'schlossturm'");
        $st->execute([$planId]);
    } else {
        $st = $db->prepare("SELECT id FROM einsatz_plaene WHERE typ = 'schlossturm' AND jahr = ? ORDER BY status = 'entwurf', id DESC LIMIT 1");
        $st->execute([$jahr]);
    }
    $id = (int)$st->fetchColumn();
    return $id > 0 ? ep_plan_laden($db, $id) : null;
}

/**
 * Zuteilungen eines Plans mit berechneten Stunden – eine Zeile je fest besetztem Slot.
 * Felder: slot_id, termin_id, datum, zeit, termin_label, funktion, funktion_id, pos, person, verein, mitglied_id,
 *         ok, anwesend (null|0|1), korrektur (float|null), bemerkung, ansatz, stunden, zaehlt (bool), grund ('', 'nicht_da', 'ok')
 */
function ha_zuteilungen(array $plan, array $mitglieder, bool $okMitzaehlen = false): array
{
    $termine = []; foreach ($plan['termine'] as $t) $termine[(int)$t['id']] = $t;
    $funktionen = []; foreach ($plan['funktionen'] as $f) $funktionen[(int)$f['id']] = $f;
    $out = [];
    foreach ($plan['slots'] as $s) {
        if (!ep_slot_fix($s)) continue;
        $t = $termine[(int)$s['termin_id']] ?? null;
        $f = $funktionen[(int)$s['funktion_id']] ?? null;
        if (!$t || !$f) continue;
        $ansatz    = ep_termin_stunden($t);
        $korrektur = ($s['stunden_korrektur'] ?? null) !== null && $s['stunden_korrektur'] !== '' ? round((float)$s['stunden_korrektur'], 2) : null;
        $anwesend  = $s['anwesend'] === null ? null : (int)$s['anwesend'];
        $ok        = ep_slot_ist_ok($s, $f);                 // Position oder Funktion mit Rolle 'OK'
        $okQuelle  = !$ok ? '' : (($f['rolle'] ?? '') === 'OK' ? 'funktion' : 'position');
        $grund = '';
        if ($anwesend === 0) $grund = 'nicht_da';
        elseif ($ok && !$okMitzaehlen) $grund = 'ok';
        $zaehlt = $grund === '';
        $out[] = [
            'slot_id'      => (int)$s['id'],
            'termin_id'    => (int)$t['id'],
            'datum'        => $t['datum'],
            'zeit'         => ep_zeit_text($t),
            'termin_label' => ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t),
            'funktion'     => trim(($f['gruppe'] ? $f['gruppe'] . ': ' : '') . $f['bezeichnung']),
            'funktion_id'  => (int)$f['id'],
            'pos'          => (int)$s['pos'],
            'person'       => ep_slot_text($s, $mitglieder),
            'person_key'   => ep_person_key((int)$s['mitglied_id'], (string)$s['name_text']),
            'verein'       => $s['verein'] ?? 'msv',
            'mitglied_id'  => (int)$s['mitglied_id'] ?: null,
            'ok'           => $ok,
            'ok_quelle'    => $okQuelle,
            'anwesend'     => $anwesend,
            'korrektur'    => $korrektur,
            'bemerkung'    => (string)($s['bemerkung'] ?? ''),
            'ansatz'       => $ansatz,
            'stunden'      => $zaehlt ? ($korrektur ?? $ansatz) : 0.0,
            'zaehlt'       => $zaehlt,
            'grund'        => $grund,
        ];
    }
    return $out;
}

/** Manuelle Zeilen eines Plans (einsatz_abr_zeilen) inkl. Personenname. Leer, wenn Migration 058 fehlt. */
function ha_manuell(PDO $db, int $planId): array
{
    try {
        $st = $db->prepare("SELECT z.*, m.Name, m.Vorname FROM einsatz_abr_zeilen z LEFT JOIN mitglieder m ON m.ID = z.mitglied_id
                            WHERE z.plan_id = ? ORDER BY FIELD(z.kategorie,'vorarbeit','ok_funktion','einsatz'), z.sort, z.id");
        $st->execute([$planId]);
    } catch (Throwable $e) { return []; }
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $r['person']     = !empty($r['mitglied_id']) && $r['Name'] !== null ? trim($r['Name'] . ' ' . $r['Vorname']) : (string)$r['name_text'];
        $r['person_key'] = ep_person_key((int)$r['mitglied_id'], (string)$r['name_text']);
        $r['stunden']    = round((float)$r['stunden'], 2);
        unset($r['Name'], $r['Vorname']);
        $out[] = $r;
    }
    return $out;
}

/**
 * Abrechnungsmatrix (rein).
 * Rückgabe: ['vereine' => [verein => [einsaetze, vorarbeiten, ok_funktionen, total, anteil, positionen]],
 *            'gesamt'  => [einsaetze, vorarbeiten, ok_funktionen, total, positionen],
 *            'termine' => [termin_id => ['label', 'datum', 'vereine' => [verein => ['stunden', 'positionen']], 'total' => [stunden, positionen]]],
 *            'nachtrag'=> [verein => stunden]  (manuelle Zeilen Kategorie einsatz, in 'einsaetze' enthalten)]
 */
function ha_matrix(array $zuteilungen, array $manuell, array $termine = []): array
{
    $leer = fn() => ['einsaetze' => 0.0, 'vorarbeiten' => 0.0, 'ok_funktionen' => 0.0, 'total' => 0.0, 'anteil' => 0.0, 'positionen' => 0];
    $v = []; foreach (EP_VEREINE as $k => $_) $v[$k] = $leer();
    $tm = []; $nachtrag = [];
    foreach ($termine as $t) {
        $tm[(int)$t['id']] = ['label' => ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t), 'datum' => $t['datum'],
                              'vereine' => array_fill_keys(array_keys(EP_VEREINE), ['stunden' => 0.0, 'positionen' => 0]), 'total' => ['stunden' => 0.0, 'positionen' => 0]];
    }
    foreach ($zuteilungen as $z) {
        if (!isset($v[$z['verein']]) || !$z['zaehlt']) continue;
        $v[$z['verein']]['einsaetze'] += $z['stunden'];
        $v[$z['verein']]['positionen']++;
        $tid = (int)$z['termin_id'];
        if (!isset($tm[$tid])) $tm[$tid] = ['label' => $z['termin_label'], 'datum' => $z['datum'], 'vereine' => array_fill_keys(array_keys(EP_VEREINE), ['stunden' => 0.0, 'positionen' => 0]), 'total' => ['stunden' => 0.0, 'positionen' => 0]];
        $tm[$tid]['vereine'][$z['verein']]['stunden'] += $z['stunden'];
        $tm[$tid]['vereine'][$z['verein']]['positionen']++;
        $tm[$tid]['total']['stunden'] += $z['stunden'];
        $tm[$tid]['total']['positionen']++;
    }
    foreach ($manuell as $m) {
        if (!isset($v[$m['verein']])) continue;
        $std = (float)$m['stunden'];
        if ($m['kategorie'] === 'einsatz')          { $v[$m['verein']]['einsaetze'] += $std; $nachtrag[$m['verein']] = ($nachtrag[$m['verein']] ?? 0.0) + $std; }
        elseif ($m['kategorie'] === 'ok_funktion')  $v[$m['verein']]['ok_funktionen'] += $std;
        else                                        $v[$m['verein']]['vorarbeiten'] += $std;
    }
    $g = $leer(); unset($g['anteil']);
    foreach ($v as $k => $z) {
        $v[$k]['total'] = round($z['einsaetze'] + $z['vorarbeiten'] + $z['ok_funktionen'], 2);
        foreach (['einsaetze', 'vorarbeiten', 'ok_funktionen', 'total', 'positionen'] as $f) $g[$f] += $v[$k][$f];
    }
    foreach ($v as $k => $z) {
        $v[$k]['anteil'] = $g['total'] > 0 ? $z['total'] / $g['total'] : 0.0;
        foreach (['einsaetze', 'vorarbeiten', 'ok_funktionen'] as $f) $v[$k][$f] = round($v[$k][$f], 2);
    }
    foreach (['einsaetze', 'vorarbeiten', 'ok_funktionen', 'total'] as $f) $g[$f] = round($g[$f], 2);
    uasort($tm, fn($a, $b) => strcmp($a['datum'], $b['datum']));
    return ['vereine' => $v, 'gesamt' => $g, 'termine' => $tm, 'nachtrag' => $nachtrag];
}

/** Stunden je Person (rein): Zuteilungen + manuelle Zeilen, sortiert nach Stunden absteigend. */
function ha_pro_person(array $zuteilungen, array $manuell): array
{
    $out = [];
    $neu = fn($name, $verein, $mid) => ['name' => $name, 'verein' => $verein, 'mitglied_id' => $mid, 'positionen' => 0, 'einsaetze' => 0.0, 'manuell' => 0.0, 'stunden' => 0.0];
    foreach ($zuteilungen as $z) {
        $k = $z['person_key'];
        $out[$k] ??= $neu($z['person'], $z['verein'], $z['mitglied_id']);
        $out[$k]['positionen']++;
        $out[$k]['einsaetze'] += $z['stunden'];
        $out[$k]['stunden']   += $z['stunden'];
    }
    foreach ($manuell as $m) {
        if (trim((string)$m['person']) === '') continue;
        $k = $m['person_key'];
        $out[$k] ??= $neu($m['person'], $m['verein'], (int)$m['mitglied_id'] ?: null);
        $out[$k]['manuell'] += (float)$m['stunden'];
        $out[$k]['stunden'] += (float)$m['stunden'];
    }
    foreach ($out as &$p) foreach (['einsaetze', 'manuell', 'stunden'] as $f) $p[$f] = round($p[$f], 2); unset($p);
    uasort($out, fn($a, $b) => [$b['stunden'], $a['verein'], $a['name']] <=> [$a['stunden'], $b['verein'], $b['name']]);
    return array_values($out);
}

/** Kennzahlen (rein): OK-Positionen/-Stunden, nicht da, offene Anwesenheit, Korrekturen, Bemerkungen. */
function ha_kennzahlen(array $zuteilungen, array $manuell): array
{
    $k = ['positionen' => count($zuteilungen), 'zaehlend' => 0, 'ok_positionen' => 0, 'ok_stunden' => 0.0, 'nicht_da' => 0, 'anwesenheit_offen' => 0, 'korrekturen' => 0, 'bemerkungen' => 0, 'manuell' => count($manuell)];
    foreach ($zuteilungen as $z) {
        if ($z['zaehlt']) $k['zaehlend']++;
        if ($z['ok']) { $k['ok_positionen']++; $k['ok_stunden'] += $z['korrektur'] ?? $z['ansatz']; }
        if ($z['anwesend'] === 0) $k['nicht_da']++;
        if ($z['anwesend'] === null) $k['anwesenheit_offen']++;
        if ($z['korrektur'] !== null) $k['korrekturen']++;
        if ($z['bemerkung'] !== '') $k['bemerkungen']++;
    }
    $k['ok_stunden'] = round($k['ok_stunden'], 2);
    return $k;
}

/** Warnhinweise (rein) für den Tab Abrechnung. */
function ha_warnungen(array $plan, array $zuteilungen, array $kennzahlen): array
{
    $w = [];
    $ohne = array_filter($plan['termine'], fn($t) => !isset($t['pauschale_std']) || $t['pauschale_std'] === '' || $t['pauschale_std'] === null);
    if ($ohne) $w[] = ['typ' => 'pauschale', 'text' => count($ohne) . ' Schicht(en) ohne Pauschale – es zählt die Schichtdauer aus den Zeiten. Pauschale im Tab «Ansätze» setzen.'];
    if ($kennzahlen['anwesenheit_offen'] > 0) $w[] = ['typ' => 'anwesenheit', 'text' => $kennzahlen['anwesenheit_offen'] . ' Position(en) ohne erfasste Anwesenheit – sie zählen gemäss Plan. Nur «nicht da» streicht Stunden.'];
    $unklar = array_filter($zuteilungen, fn($z) => stripos($z['bemerkung'], 'Verein unklar') !== false);
    if ($unklar) $w[] = ['typ' => 'verein', 'text' => count($unklar) . ' Position(en) mit Bemerkung «Verein unklar» (kein Kreuz im Original) – Verein im Einsatzplan-Editor prüfen.'];
    if ($plan['status'] === 'entwurf') $w[] = ['typ' => 'status', 'text' => 'Der Plan hat den Status «Entwurf».'];
    return $w;
}

/** Vorschlag für die Pauschale einer Schicht: Samstag 5.00, Sonntag 3.00, sonst Schichtdauer. */
function ha_pauschale_vorschlag(array $t): float
{
    $wt = (int)date('N', strtotime($t['datum']));
    if ($wt === 6) return 5.0;
    if ($wt === 7) return 3.0;
    return ep_termin_dauer($t);
}

/** Komplettes Bündel für Seite, Excel, PDF und JSON. */
function ha_abrechnung(PDO $db, array $plan, bool $okMitzaehlen = false): array
{
    $mitglieder  = ep_mitglieder_map($db);
    $zuteilungen = ha_zuteilungen($plan, $mitglieder, $okMitzaehlen);
    $manuell     = ha_manuell($db, (int)$plan['id']);
    $kennzahlen  = ha_kennzahlen($zuteilungen, $manuell);
    return [
        'plan'        => $plan,
        'ok'          => $okMitzaehlen,
        'zuteilungen' => $zuteilungen,
        'manuell'     => $manuell,
        'matrix'      => ha_matrix($zuteilungen, $manuell, $plan['termine']),
        'personen'    => ha_pro_person($zuteilungen, $manuell),
        'kennzahlen'  => $kennzahlen,
        'warnungen'   => ha_warnungen($plan, $zuteilungen, $kennzahlen),
        'mitglieder'  => $mitglieder,
    ];
}

/** Zahl «de-CH» mit 2 Nachkommastellen ohne Tausendertrennzeichen (Anzeige/PDF). */
function ha_fmt(float $n): string
{
    return number_format($n, 2, '.', '');
}

/** Datum «Sa. 18.04.2026» → «Samstag, 18.04.2026». */
function ha_datum_lang(string $datum): string
{
    $ts = strtotime($datum);
    return EP_WOCHENTAGE[(int)date('w', $ts)] . ', ' . date('d.m.Y', $ts);
}
