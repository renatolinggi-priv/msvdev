<?php
/**
 * inc/einsatzplanung/einteilung.inc.php – automatische Einteilung als Vorschlag (ohne DB, lokal testbar).
 *
 * Eingabe: Plan (ep_plan_laden) und Verfügbarkeiten (ep_verfuegbarkeit_laden): «Person kann an Termin T
 * in Rolle R». Offene Positionen = Slots ohne feste Person in Funktionen mit Anfrage-Rolle (nicht 'OK').
 *
 * Greedy mit Bewertung, deterministisch:
 *   - Positionen in Reihenfolge der Knappheit (wenigste Kandidaten zuerst).
 *   - Kandidat mit höchster Punktzahl:
 *       Fairness   – Verein mit grösstem Rückstand zum gleichen Anteil der Schicht
 *                    (Ziel = Positionen der Schicht ÷ Vereine, die in dieser Schicht überhaupt Personen haben)
 *       Belastung  – wenige bisherige Einsätze im Verhältnis zu den angebotenen Schichten
 *       Kontinuität– Bonus, wenn die Person in einer anderen Schicht schon dieselbe Funktion hat (Option)
 *       Flexibilität – Personen mit wenigen Alternativen zuerst, flexible bleiben in Reserve
 *     Gleichstand: Name.
 *   - Eine Person höchstens eine Position pro Schicht; Verfügbarkeit (Rolle UND Schicht) ist Pflicht.
 *
 * Ausgabe: vorschlaege, offen (unbesetzbare Positionen mit Grund), ungenutzt (verfügbare Personen ohne
 * Einsatz), tabelle (Termin × Verein: ist/ziel), warnungen.
 */
require_once __DIR__ . '/plan_helpers.inc.php';

function ep_einteilung_berechnen(array $plan, array $verfuegbarkeit, array $opt = []): array
{
    $kontinuitaet = $opt['kontinuitaet'] ?? true;
    $vereinBeachten = $opt['verein_beachten'] ?? false;   // Verein einer leeren Fremdvereins-Position vorgeben

    $termine = []; foreach ($plan['termine'] as $t) $termine[(int)$t['id']] = $t;
    $funktionen = []; foreach ($plan['funktionen'] as $f) $funktionen[(int)$f['id']] = $f;
    $vereine = array_keys(EP_VEREINE);

    // Personen aus den Verfügbarkeiten
    $personen = [];
    foreach ($verfuegbarkeit as $v) {
        $key = $v['person_key'] ?? ep_person_key((int)$v['mitglied_id'], (string)$v['name_text']);
        $tids = array_values(array_filter(array_map('intval', $v['termin_ids']), fn($id) => isset($termine[$id])));
        $rollen = array_values(array_filter($v['rollen'], fn($r) => isset(EP_ROLLEN[$r]) && $r !== 'OK'));
        $personen[$key] = [
            'key' => $key, 'verein' => $v['verein'], 'mitglied_id' => (int)$v['mitglied_id'] ?: null, 'name_text' => (string)$v['name_text'],
            'name' => $v['name'] ?? (string)$v['name_text'], 'rollen' => $rollen, 'termine' => $tids,
            'angeboten' => count($tids), 'einsaetze' => 0, 'pro_termin' => [], 'funktionen' => [],
        ];
    }

    // Feste Besetzung: zählt für Fairness, Belastung, Kontinuität und blockiert die Schicht
    $ist = [];   // termin_id => verein => n
    foreach ($termine as $tid => $t) foreach ($vereine as $v) $ist[$tid][$v] = 0;
    $offen = [];
    foreach ($plan['slots'] as $s) {
        $tid = (int)$s['termin_id']; $fid = (int)$s['funktion_id'];
        if (!isset($termine[$tid]) || !isset($funktionen[$fid])) continue;
        $f = $funktionen[$fid];
        if (ep_slot_fix($s)) {
            $ist[$tid][$s['verein']]++;
            $pk = ep_person_key((int)$s['mitglied_id'], (string)$s['name_text']);
            if (isset($personen[$pk])) {
                $personen[$pk]['einsaetze']++;
                $personen[$pk]['pro_termin'][$tid] = $fid;
                $personen[$pk]['funktionen'][$fid] = true;
            } else {
                // Person ohne Verfügbarkeitsmeldung: trotzdem pro Schicht blockieren
                $personen[$pk] = ['key' => $pk, 'verein' => $s['verein'], 'mitglied_id' => (int)$s['mitglied_id'] ?: null, 'name_text' => (string)$s['name_text'],
                                  'name' => (string)$s['name_text'], 'rollen' => [], 'termine' => [], 'angeboten' => 0, 'einsaetze' => 1, 'pro_termin' => [$tid => $fid], 'funktionen' => [$fid => true], 'ohne_meldung' => true];
            }
            continue;
        }
        $rolle = $f['rolle'] ?? null;
        if ($rolle === null || $rolle === '' || $rolle === 'OK') continue;   // nicht automatisch einteilen
        $offen[] = ['slot_id' => (int)$s['id'], 'termin_id' => $tid, 'funktion_id' => $fid, 'pos' => (int)$s['pos'], 'rolle' => $rolle,
                    'verein_vorgabe' => $vereinBeachten && ($s['verein'] ?? 'msv') !== 'msv' ? $s['verein'] : null];
    }

    // Positionen je Schicht (alle Slots) und Vereine mit Personal je Schicht → Ziel je Verein
    $posProTermin = [];
    foreach ($plan['slots'] as $s) { $tid = (int)$s['termin_id']; if (isset($termine[$tid])) $posProTermin[$tid] = ($posProTermin[$tid] ?? 0) + 1; }
    $ziel = [];
    foreach ($termine as $tid => $t) {
        $aktiv = [];
        foreach ($vereine as $v) if ($ist[$tid][$v] > 0) $aktiv[$v] = true;
        foreach ($personen as $p) if (in_array($tid, $p['termine'], true) && $p['rollen']) $aktiv[$p['verein']] = true;
        $n = max(1, count($aktiv));
        foreach ($vereine as $v) $ziel[$tid][$v] = isset($aktiv[$v]) ? ($posProTermin[$tid] ?? 0) / $n : 0;
    }

    $kandidaten = function (array $pos) use (&$personen): array {
        $out = [];
        foreach ($personen as $p) {
            if (!in_array($pos['rolle'], $p['rollen'], true)) continue;
            if (!in_array($pos['termin_id'], $p['termine'], true)) continue;
            if (isset($p['pro_termin'][$pos['termin_id']])) continue;
            if ($pos['verein_vorgabe'] !== null && $p['verein'] !== $pos['verein_vorgabe']) continue;
            $out[] = $p['key'];
        }
        return $out;
    };

    $vorschlaege = []; $unbesetzt = [];
    while ($offen) {
        // knappste Position zuerst
        $best = null; $bestKand = null;
        foreach ($offen as $i => $pos) {
            $k = $kandidaten($pos);
            if ($best === null || count($k) < count($bestKand) || (count($k) === count($bestKand) && $pos['slot_id'] < $offen[$best]['slot_id'])) { $best = $i; $bestKand = $k; }
        }
        $pos = $offen[$best];
        unset($offen[$best]); $offen = array_values($offen);
        if (!$bestKand) {
            $t = $termine[$pos['termin_id']]; $f = $funktionen[$pos['funktion_id']];
            $unbesetzt[] = ['slot_id' => $pos['slot_id'], 'termin_id' => $pos['termin_id'], 'funktion_id' => $pos['funktion_id'], 'pos' => $pos['pos'],
                            'termin' => ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t), 'funktion' => $f['bezeichnung'],
                            'grund' => 'keine verfügbare Person mit Rolle «' . $pos['rolle'] . '» in dieser Schicht'];
            continue;
        }
        // bewerten
        $tid = $pos['termin_id'];
        $scores = [];
        foreach ($bestKand as $key) {
            $p = $personen[$key];
            $defizit = $ziel[$tid][$p['verein']] - $ist[$tid][$p['verein']];              // Fairness (kann negativ sein)
            $last    = 1 - ($p['einsaetze'] / max(1, $p['angeboten']));                     // Belastung: 1 = noch nie eingeteilt
            $konti   = $kontinuitaet && isset($p['funktionen'][$pos['funktion_id']]) ? 1 : 0;
            $flex    = 1 / max(1, count($p['rollen']) * max(1, $p['angeboten'] - $p['einsaetze']));   // wenig Alternativen → hoch
            $scores[$key] = 10 * $defizit + 5 * $last + 3 * $konti + 2 * $flex;
        }
        uksort($scores, function ($a, $b) use ($scores, $personen) {
            if (abs($scores[$a] - $scores[$b]) > 1e-9) return $scores[$b] <=> $scores[$a];
            return strcasecmp($personen[$a]['name'], $personen[$b]['name']);
        });
        $key = array_key_first($scores);
        $p = &$personen[$key];
        $p['einsaetze']++; $p['pro_termin'][$tid] = $pos['funktion_id']; $p['funktionen'][$pos['funktion_id']] = true;
        $ist[$tid][$p['verein']]++;
        $t = $termine[$tid]; $f = $funktionen[$pos['funktion_id']];
        $grund = [];
        if ($ziel[$tid][$p['verein']] - $ist[$tid][$p['verein']] >= 0) $grund[] = EP_VEREINE[$p['verein']] . ' im Rückstand';
        if ($p['einsaetze'] === 1) $grund[] = 'erster Einsatz';
        if ($kontinuitaet && count(array_filter($p['pro_termin'], fn($fid) => $fid === $pos['funktion_id'])) > 1) $grund[] = 'gleiche Funktion wie in anderer Schicht';
        $vorschlaege[] = ['slot_id' => $pos['slot_id'], 'termin_id' => $tid, 'funktion_id' => $pos['funktion_id'], 'pos' => $pos['pos'],
                          'verein' => $p['verein'], 'mitglied_id' => $p['mitglied_id'], 'name_text' => $p['mitglied_id'] ? null : $p['name_text'],
                          'person' => $p['name'], 'termin' => ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t), 'funktion' => $f['bezeichnung'],
                          'grund' => implode(', ', $grund)];
        unset($p);
    }

    // Tabelle Termin × Verein und ungenutzte Personen
    $tabelle = [];
    foreach ($termine as $tid => $t) {
        $tabelle[$tid] = ['termin' => ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t), 'positionen' => $posProTermin[$tid] ?? 0, 'vereine' => []];
        foreach ($vereine as $v) $tabelle[$tid]['vereine'][$v] = ['ist' => $ist[$tid][$v], 'ziel' => round($ziel[$tid][$v], 1)];
    }
    $ungenutzt = []; $gemeldet = [];
    foreach ($personen as $p) {
        if (!empty($p['ohne_meldung']) || $p['angeboten'] === 0 || !$p['rollen']) continue;
        if ($p['einsaetze'] === 0) $ungenutzt[] = ['person' => $p['name'], 'verein' => $p['verein'], 'rollen' => $p['rollen'], 'angeboten' => $p['angeboten']];
        // Alle gemeldeten Personen mit angeboten/eingeteilt (feste + vorgeschlagene Positionen) – Übersicht im Dialog
        $gemeldet[] = ['person' => $p['name'], 'verein' => $p['verein'], 'rollen' => $p['rollen'], 'angeboten' => $p['angeboten'], 'eingeteilt' => $p['einsaetze'],
                       'mitglied_id' => $p['mitglied_id'], 'name_text' => $p['name_text']];
    }
    usort($gemeldet, fn($a, $b) => [$a['eingeteilt'] > 0 ? 1 : 0, -($a['angeboten'] - $a['eingeteilt']), $a['verein'], $a['person']] <=> [$b['eingeteilt'] > 0 ? 1 : 0, -($b['angeboten'] - $b['eingeteilt']), $b['verein'], $b['person']]);
    $warnungen = [];
    foreach ($personen as $p) if (!empty($p['ohne_meldung']) && ($p['mitglied_id'] || $p['name_text'] !== '')) { /* still ok */ }
    if (!$verfuegbarkeit) $warnungen[] = 'Keine Verfügbarkeiten erfasst – nichts einzuteilen.';

    return ['vorschlaege' => $vorschlaege, 'offen' => $unbesetzt, 'ungenutzt' => $ungenutzt, 'gemeldet' => $gemeldet, 'tabelle' => $tabelle, 'warnungen' => $warnungen];
}
