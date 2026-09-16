<?php
/**
 * inc/einsatzplanung/plan_import.inc.php – Plan aus einem Einsatzplan-Dokument (DOCX Obli/Feld,
 * XLSX Chilbi) aufbauen. Gemeinsame Logik für den Web-Endpunkt plan_from_dokument.php und für
 * CLI-/Wartungsläufe (z.B. einmaliges Bootstrapping des laufenden Jahres per SSH).
 *
 *   DOCX: inc/einsatzplan_parser/docx_parser.php mit $vereineBehalten = true → Platzhalter
 *         «SV Freienbach»/«SV Wollerau» werden Slots des jeweiligen Vereins.
 *   XLSX: inc/einsatzplan_parser/xlsx_parser.php → Personen × Schichten (Layout person_x_schicht).
 *
 * Danach werden bestehende Import-Zeilen in einsatz_zuweisungen per (Datum, Mitglied) an die Slots
 * gehängt (ep_legacy_verknuepfen), damit offene Tausch-Anträge gültig bleiben.
 */
require_once __DIR__ . '/plan_helpers.inc.php';
require_once __DIR__ . '/../einsatzplan_parser/name_matcher.php';

/** «18:00 – 20:00» / «18:00-20:00» → [von, bis] als TIME oder [null, null] */
function ep_zeit_split(string $zeit): array
{
    if (preg_match('/(\d{1,2})[:.](\d{2})\s*[–-]\s*(\d{1,2})[:.](\d{2})/u', $zeit, $m)) {
        return [sprintf('%02d:%02d:00', $m[1], $m[2]), sprintf('%02d:%02d:00', $m[3], $m[4])];
    }
    if (preg_match('/^(\d{1,2})[:.](\d{2})$/', trim($zeit), $m)) return [sprintf('%02d:%02d:00', $m[1], $m[2]), null];
    return [null, null];
}

/**
 * Plan aus Datei erzeugen.
 *
 * @param PDO    $db
 * @param string $pfad    lokaler Pfad der DOCX/XLSX-Datei
 * @param int    $userId  erstellt_von
 * @param array  $opt     'dokument_id' (vorstand_dokumente.id, optional), 'titel', 'typ' (EP_TYPEN-Schlüssel), 'jahr'
 * @return array ['plan_id' => int, 'titel' => string, 'typ' => string, 'layout' => string,
 *                'statistik' => ['termine','funktionen','slots','ohne_match' => [...], 'verknuepft']]
 * @throws InvalidArgumentException bei Benutzer-/Dateifehlern, Throwable bei DB-Fehlern (Transaktion wird zurückgerollt)
 */
function ep_plan_aus_dokument(PDO $db, string $pfad, int $userId, array $opt = []): array
{
    if (!is_file($pfad)) throw new InvalidArgumentException('Datei nicht gefunden: ' . basename($pfad));
    $ext = strtolower(pathinfo($pfad, PATHINFO_EXTENSION));

    if ($ext === 'docx') {
        require_once __DIR__ . '/../einsatzplan_parser/docx_parser.php';
        $res = parseEinsatzplanDocx($pfad, true);
        $layout = 'funktion_x_termin';
    } elseif ($ext === 'xlsx') {
        require_once __DIR__ . '/../einsatzplan_parser/xlsx_parser.php';
        $res = parseEinsatzplanXlsx($pfad, true);
        // Chilbi-Excel = Personen × Schichten; Schlossturm-Excel (OK-Einsatzliste, drei Vereine) = Funktionen × Termine
        $layout = ($res['format'] ?? 'chilbi') === 'schlossturm' ? 'funktion_x_termin' : 'person_x_schicht';
    } else {
        throw new InvalidArgumentException('Nur DOCX (Obli/Feld) oder XLSX (Chilbi) können übernommen werden');
    }
    if (empty($res['success']) || empty($res['data'])) {
        throw new InvalidArgumentException($res['message'] ?? 'Dokument konnte nicht gelesen werden');
    }
    $daten = $res['data'];

    // Typ bestimmen: Vorgabe > Parser > Titel/Dateiname (die Titelzeile «Feldschiessen - Einsatzplan»
    // hat im Word nur eine Zelle und wird vom Parser übersprungen, daher der Rückfall)
    $typ = (string)($opt['typ'] ?? '');
    if (!isset(EP_TYPEN[$typ])) {
        $erk = $daten[0]['typ'] ?? '';
        $hinweis = mb_strtolower((string)($opt['titel'] ?? '') . ' ' . basename($pfad) . ' ' . ($daten[0]['bezeichnung'] ?? ''));
        if ($layout === 'person_x_schicht') $typ = 'chilbi';
        elseif (($res['format'] ?? '') === 'schlossturm' || str_contains($hinweis, 'schlossturm')) $typ = 'schlossturm';
        elseif ($erk === 'obligatorisch' || $erk === 'feldschiessen') $typ = $erk;
        elseif (str_contains($hinweis, 'feld')) $typ = 'feldschiessen';
        elseif (str_contains($hinweis, 'obli')) $typ = 'obligatorisch';
        elseif (str_contains($hinweis, 'chilbi')) $typ = 'chilbi';
        else $typ = 'sonstiges';
    }

    // Jahr und Titel
    $jahr = (int)($opt['jahr'] ?? 0);
    if ($jahr < 2000) $jahr = (int)substr($daten[0]['event_datum'], 0, 4);
    $titel = trim((string)($opt['titel'] ?? ''));
    if ($titel === '') $titel = EP_TYPEN[$typ] . ' ' . $jahr;
    $dokId = !empty($opt['dokument_id']) ? (int)$opt['dokument_id'] : null;

    // Gruppe/Bezeichnung aus «Büro: Anmeldung»
    $splitFunktion = function (string $roh) use ($layout): array {
        $roh = trim($roh); $gruppe = null; $bez = $roh;
        if ($layout === 'funktion_x_termin' && strpos($roh, ':') !== false) {
            [$g, $b] = array_map('trim', explode(':', $roh, 2));
            if ($g !== '' && $b !== '') { $gruppe = $g; $bez = $b; }
        }
        return [$gruppe, $bez];
    };

    // Termine: eindeutig nach Datum + Zeit (Reihenfolge des ersten Auftretens)
    $termine = [];
    foreach ($daten as $z) {
        $key = $z['event_datum'] . '|' . ($z['event_zeit'] ?? '');
        if (isset($termine[$key])) continue;
        [$von, $bis] = ep_zeit_split((string)($z['event_zeit'] ?? ''));
        $zeitText = ($von === null && trim((string)($z['event_zeit'] ?? '')) !== '') ? mb_substr(trim($z['event_zeit']), 0, 30) : null;
        if ($layout === 'person_x_schicht') { $zeitText = mb_substr(trim((string)($z['event_zeit'] ?? '')), 0, 30) ?: null; $von = $bis = null; }
        $bez = $layout === 'funktion_x_termin' ? trim((string)($z['bezeichnung'] ?? '')) : '';
        // Rückfall «29. Mai» aus dem Parser ist keine echte Bezeichnung; Schlossturm-Excel liefert den Titel → keine Bezeichnung
        if (preg_match('/^\d{1,2}\.\s*\p{L}+$/u', $bez) || ($res['format'] ?? '') === 'schlossturm') $bez = '';
        $termine[$key] = ['bezeichnung' => $bez !== '' ? $bez : null, 'datum' => $z['event_datum'], 'zeit_von' => $von, 'zeit_bis' => $bis, 'zeit_text' => $zeitText,
                          'info' => mb_substr(trim((string)($z['info'] ?? '')), 0, 100) ?: null];
    }
    uasort($termine, fn($a, $b) => strcmp($a['datum'] . ($a['zeit_von'] ?? $a['zeit_text'] ?? ''), $b['datum'] . ($b['zeit_von'] ?? $b['zeit_text'] ?? '')));

    // Funktionen: Reihenfolge des ersten Auftretens; anzahl = max. Zeilen pro Termin
    $funktionen = [];
    $zaehler = [];
    foreach ($daten as $z) {
        [$gruppe, $bez] = $splitFunktion((string)$z['funktion']);
        $fkey = mb_strtolower(($gruppe ?? '') . '|' . $bez);
        if (!isset($funktionen[$fkey])) $funktionen[$fkey] = ['gruppe' => $gruppe !== null ? mb_substr($gruppe, 0, 50) : null, 'bezeichnung' => mb_substr($bez, 0, 100), 'anzahl' => 1];
        $ck = $fkey . '#' . $z['event_datum'] . '|' . ($z['event_zeit'] ?? '');
        $zaehler[$ck] = ($zaehler[$ck] ?? 0) + 1;
        $funktionen[$fkey]['anzahl'] = max($funktionen[$fkey]['anzahl'], $zaehler[$ck]);
    }
    if ($layout === 'person_x_schicht') foreach ($funktionen as &$f) { $f['anzahl'] = 0; } unset($f);

    // Namen den Mitgliedern zuordnen (nur MSV-Einträge, in Reihenfolge)
    $msvEintraege = array_values(array_filter($daten, fn($z) => ($z['verein'] ?? 'msv') === 'msv'));
    $gematcht = matchMitglieder($db, $msvEintraege);
    $ohneMatch = [];
    foreach ($gematcht as $g) if (empty($g['mitglied_id'])) $ohneMatch[$g['mitglied_name']] = true;

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO einsatz_plaene (jahr, typ, titel, layout, status, fusstext, quelle_dokument_id, erstellt_von) VALUES (?, ?, ?, ?, 'entwurf', ?, ?, ?)")
           ->execute([$jahr, $typ, mb_substr($titel, 0, 100), $layout, ep_fusstext_default($typ), $dokId, $userId ?: null]);
        $planId = (int)$db->lastInsertId();

        $insT = $db->prepare("INSERT INTO einsatz_plan_termine (plan_id, bezeichnung, datum, zeit_von, zeit_bis, zeit_text, info, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $terminIds = []; $i = 0;
        foreach ($termine as $key => $t) {
            $insT->execute([$planId, $t['bezeichnung'], $t['datum'], $t['zeit_von'], $t['zeit_bis'], $t['zeit_text'], $t['info'] ?? null, $i++]);
            $terminIds[$key] = (int)$db->lastInsertId();
        }
        $insF = $db->prepare("INSERT INTO einsatz_plan_funktionen (plan_id, gruppe, bezeichnung, rolle, anzahl, sort) VALUES (?, ?, ?, ?, ?, ?)");
        $funktionIds = []; $i = 0;
        foreach ($funktionen as $key => $f) {
            $rolle = $typ === 'schlossturm' ? ep_rolle_vorbelegung($f['bezeichnung']) : null;
            $insF->execute([$planId, $f['gruppe'], $f['bezeichnung'], $rolle, $f['anzahl'], (++$i) * 10]);
            $funktionIds[$key] = (int)$db->lastInsertId();
        }

        // Slots in Dokument-Reihenfolge
        $insS = $db->prepare("INSERT INTO einsatz_plan_slots (plan_id, termin_id, funktion_id, pos, verein, mitglied_id, name_text) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $posZaehler = []; $slots = 0; $mi = 0;
        foreach ($daten as $z) {
            $tkey = $z['event_datum'] . '|' . ($z['event_zeit'] ?? '');
            [$gruppe, $bez] = $splitFunktion((string)$z['funktion']);
            $fkey = mb_strtolower(($gruppe ?? '') . '|' . $bez);
            $tid = $terminIds[$tkey] ?? null; $fid = $funktionIds[$fkey] ?? null;
            $verein = $z['verein'] ?? 'msv';
            $match = null;
            if ($verein === 'msv') $match = $gematcht[$mi++] ?? null;   // Index synchron zu $msvEintraege halten
            if (!$tid || !$fid) continue;
            $pk = $tid . '|' . $fid;
            $pos = ($posZaehler[$pk] ?? 0) + 1; $posZaehler[$pk] = $pos;

            $mid = null; $nameText = null;
            if ($verein === 'msv') {
                if ($match && !empty($match['mitglied_id'])) $mid = (int)$match['mitglied_id'];
                else $nameText = mb_substr(trim((string)$z['mitglied_name']), 0, 100);
            } else {
                // Fremdverein: echter Name (Schlossturm-OK-Liste) als Klartext, reiner Vereins-Platzhalter («SV Freienbach») bleibt leer
                $n = trim((string)$z['mitglied_name']);
                $istPlatzhalter = preg_match('/^(sv|msv)\s/i', $n) && preg_match('/freienbach|wollerau|wilen/i', $n);
                if ($n !== '' && !$istPlatzhalter) $nameText = mb_substr($n, 0, 100);
            }
            $insS->execute([$planId, $tid, $fid, $pos, $verein, $mid, $nameText]);
            $slots++;
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    if ($layout === 'funktion_x_termin') ep_slots_sicherstellen($db, $planId);
    $verknuepft = ep_legacy_verknuepfen($db, $planId);

    return [
        'plan_id' => $planId, 'titel' => $titel, 'typ' => $typ, 'layout' => $layout,
        'statistik' => ['termine' => count($termine), 'funktionen' => count($funktionen), 'slots' => $slots, 'ohne_match' => array_keys($ohneMatch), 'verknuepft' => $verknuepft],
    ];
}
