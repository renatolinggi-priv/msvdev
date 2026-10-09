<?php
// inc_termine.php – Termine fürs Portal aus einer Hand (Vorschau «Vereinsfahne», Okt 2026).
// Führt JM-Anlässe (JMDefinition.Schiesstage, eine Zeile pro Schiesstag), Vereinstermine
// (wichtige_termine) und optional die eigenen Einsätze zu Einträgen pro Tag zusammen.
// Genutzt von dashboard_fahne.inc.php (nächster Tag/Wochenende), tag.php (Tagesplan) und
// termine.php (Liste nach Tag). Nur Funktionen, keine Ausgabe.
//
// Eintrag: ['datum' => 'Y-m-d', 'fenster' => [['08:00','12:00'], …], 'start' => '08:00' | '',
//           'name', 'ort', 'art' => 'jm' | 'schiessen' | 'info' | 'termin' | 'einsatz',
//           'quelle' => 'jm:ID' | 'wt:ID' | 'ez:ID', 'jsk' => bool, 'funktion' => string]

const PT_MONATE = ['januar' => 1, 'februar' => 2, 'märz' => 3, 'maerz' => 3, 'april' => 4, 'mai' => 5, 'juni' => 6,
                   'juli' => 7, 'august' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'dezember' => 12];
const PT_TAGE_KURZ = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
const PT_TAGE_LANG = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
const PT_MONAT_LANG = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const PT_MONAT_KURZ = ['', 'Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sept.', 'Okt.', 'Nov.', 'Dez.'];

/** Zeitfenster aus Text wie «08:00 - 12:00, 13:30 – 17:00 Uhr» oder «17.00 - 20.00» */
function ptZeitfenster(string $text): array {
    $f = [];
    if (preg_match_all('/(\d{1,2})[:.](\d{2})\s*(?:-|–|bis)\s*(\d{1,2})[:.](\d{2})/u', $text, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $f[] = [sprintf('%02d:%02d', $x[1], $x[2]), sprintf('%02d:%02d', $x[3], $x[4])];
        }
    } elseif (preg_match('/(\d{1,2})[:.](\d{2})/u', $text, $x)) {
        $f[] = [sprintf('%02d:%02d', $x[1], $x[2]), ''];
    }
    return $f;
}

/** Datum aus einer Schiesstag-Zeile («Samstag, 23. Mai 2026 …», «Freitag 12. Juni 17:00 …») */
function ptDatumAusZeile(string $zeile, int $jahrStandard): ?string {
    if (!preg_match('/(\d{1,2})\.\s*(januar|februar|märz|maerz|april|mai|juni|juli|august|september|oktober|november|dezember)(?:\s+(\d{4}))?/iu', $zeile, $m)) {
        return null;
    }
    $monat = PT_MONATE[mb_strtolower($m[2])] ?? null;
    if (!$monat) return null;
    $jahr = !empty($m[3]) ? (int) $m[3] : $jahrStandard;
    return checkdate($monat, (int) $m[1], $jahr) ? sprintf('%04d-%02d-%02d', $jahr, $monat, (int) $m[1]) : null;
}

/**
 * Alle Einträge der Jahre $jahrVon..$jahrBis. Mit $mitgliedId kommen die eigenen Einsätze dazu.
 */
function portalTermineLaden(PDO $db, int $jahrVon, int $jahrBis, ?int $mitgliedId = null): array {
    $e = [];
    try {
        $st = $db->prepare("SELECT ID, Bezeichnung, Schiesstage, Adresse, Info, Erweitert, year
                              FROM JMDefinition
                             WHERE hidden = 0 AND year BETWEEN ? AND ? AND LENGTH(Schiesstage) > 0");
        $st->execute([$jahrVon, $jahrBis]);
        foreach ($st as $r) {
            $art = ((int) $r['Info'] === 1) ? 'info' : (((int) $r['Erweitert'] === 1) ? 'schiessen' : 'jm');
            foreach (preg_split('/\R/u', (string) $r['Schiesstage']) as $zeile) {
                $zeile = trim($zeile);
                if ($zeile === '') continue;
                $datum = ptDatumAusZeile($zeile, (int) $r['year']);
                if ($datum === null) continue;
                $fenster = ptZeitfenster($zeile);
                $e[] = ['datum' => $datum, 'fenster' => $fenster, 'start' => $fenster[0][0] ?? '',
                        'name' => trim((string) $r['Bezeichnung']), 'ort' => trim((string) ($r['Adresse'] ?? '')),
                        'art' => $art, 'quelle' => 'jm:' . (int) $r['ID'], 'jsk' => false, 'funktion' => ''];
            }
        }
    } catch (Throwable $ex) { error_log('[inc_termine] JMDefinition: ' . $ex->getMessage()); }

    try {
        $st = $db->prepare("SELECT ID, name, `date`, `time`, fuer_jsk FROM wichtige_termine WHERE YEAR(`date`) BETWEEN ? AND ?");
        $st->execute([$jahrVon, $jahrBis]);
        foreach ($st as $r) {
            $fenster = ptZeitfenster((string) ($r['time'] ?? ''));
            $e[] = ['datum' => (string) $r['date'], 'fenster' => $fenster, 'start' => $fenster[0][0] ?? '',
                    'name' => trim((string) $r['name']), 'ort' => '', 'art' => 'termin',
                    'quelle' => 'wt:' . (int) $r['ID'], 'jsk' => !empty($r['fuer_jsk']), 'funktion' => ''];
        }
    } catch (Throwable $ex) { error_log('[inc_termine] wichtige_termine: ' . $ex->getMessage()); }

    if ($mitgliedId) {
        try {
            $st = $db->prepare("SELECT id, bezeichnung, event_datum, event_zeit, funktion FROM einsatz_zuweisungen
                                 WHERE mitglied_id = ? AND YEAR(event_datum) BETWEEN ? AND ?");
            $st->execute([$mitgliedId, $jahrVon, $jahrBis]);
            foreach ($st as $r) {
                $fenster = ptZeitfenster((string) ($r['event_zeit'] ?? ''));
                $e[] = ['datum' => (string) $r['event_datum'], 'fenster' => $fenster, 'start' => $fenster[0][0] ?? '',
                        'name' => trim((string) $r['bezeichnung']), 'ort' => '', 'art' => 'einsatz',
                        'quelle' => 'ez:' . (int) $r['id'], 'jsk' => false, 'funktion' => trim((string) ($r['funktion'] ?? ''))];
            }
        } catch (Throwable $ex) { error_log('[inc_termine] einsatz_zuweisungen: ' . $ex->getMessage()); }
    }

    usort($e, fn($a, $b) => [$a['datum'], $a['start'] === '' ? '99' : $a['start'], $a['name']]
                         <=> [$b['datum'], $b['start'] === '' ? '99' : $b['start'], $b['name']]);
    return $e;
}

/** Einträge nach Tag gruppiert: ['Y-m-d' => [Eintrag, …]] in Datumsreihenfolge */
function portalTermineNachTag(array $eintraege): array {
    $t = [];
    foreach ($eintraege as $x) $t[$x['datum']][] = $x;
    ksort($t);
    return $t;
}

/**
 * Nächster Tag mit einem Termin (ab heute), bei Freitag bis Sonntag das ganze Wochenende.
 * Einsätze stehen auf der Startseite in einer eigenen Zeile und zählen hier nicht.
 */
function portalNaechsterBlock(array $eintraege, string $heute): ?array {
    $erst = null;
    foreach ($eintraege as $x) {
        if ($x['datum'] >= $heute && $x['art'] !== 'einsatz') { $erst = $x; break; }
    }
    if (!$erst) return null;
    $von = $erst['datum'];
    $wt  = (int) date('N', strtotime($von));                       // 1 = Montag … 7 = Sonntag
    $bis = ($wt >= 5) ? date('Y-m-d', strtotime($von . ' +' . (7 - $wt) . ' days')) : $von;
    $block = array_values(array_filter($eintraege, fn($x) => $x['datum'] >= $von && $x['datum'] <= $bis && $x['art'] !== 'einsatz'));
    // Ein Wochenende endet am letzten Tag, an dem noch etwas ist
    $bis = max(array_column($block, 'datum'));
    return ['von' => $von, 'bis' => $bis, 'eintraege' => $block];
}

/** Titel, Zeitzeile und Unterzeile für einen Block (Startseite, Tagesplan) */
function portalBlockText(array $block): array {
    // Gleichnamige Vereinstermine an mehreren Tagen (z.B. Chilbi Sa und So) zählen einmal
    $schiessen = []; $termine = []; $infos = [];
    foreach ($block['eintraege'] as $x) {
        if (in_array($x['art'], ['jm', 'schiessen'], true)) $schiessen[$x['quelle']] = $x['name'];
        elseif ($x['art'] === 'termin') $termine[mb_strtolower($x['name'])] = $x['name'];
        elseif ($x['art'] === 'info') $infos[] = $x;
    }
    // Ohne Schiessen und Vereinstermin ist der Info-Anlass selbst das Thema (Absenden, GV)
    if (!$schiessen && !$termine && $infos) {
        $haupt = $infos[0];
        return ['titel' => $haupt['name'],
                'wann' => $block['von'] !== $block['bis'] ? ptTagKurz($block['von']) . ' bis ' . ptTagKurz($block['bis'], true) : ptFensterText($haupt['fenster']),
                'unter' => ($haupt['ort'] !== '' && !ptIstKoordinate($haupt['ort'])) ? $haupt['ort'] : '',
                'mehrtaegig' => $block['von'] !== $block['bis']];
    }
    $mehrtaegig = $block['von'] !== $block['bis'];
    $anzahl = count($schiessen);
    if ($anzahl >= 2) {
        $titel = $anzahl . ' Schiessen' . ($mehrtaegig ? ' am Wochenende' : '');
    } elseif ($anzahl === 1) {
        $titel = reset($schiessen);
    } elseif ($termine) {
        $titel = count($termine) === 1 ? reset($termine) : count($termine) . ' Termine';
    } else {
        $titel = $infos ? $infos[0]['name'] : 'Termin';
    }

    $teile = [];
    if ($mehrtaegig) {
        $teile[] = ptTagKurz($block['von']) . ' bis ' . ptTagKurz($block['bis'], true);
    } else {
        $haupt = null;
        foreach ($block['eintraege'] as $x) { if ($x['art'] !== 'info') { $haupt = $x; break; } }
        if ($haupt && $haupt['fenster'] && $anzahl <= 1) $teile[] = ptFensterText($haupt['fenster']);
    }
    foreach ($infos as $i) {
        $name = (mb_stripos($i['name'], 'mittagessen') !== false) ? 'Mittagessen' : $i['name'];
        $teile[] = trim($name . ' ' . ($mehrtaegig ? PT_TAGE_KURZ[(int) date('w', strtotime($i['datum']))] . ' ' : '') . $i['start']);
    }

    $unter = '';
    if ($anzahl >= 2) {
        $unter = implode(', ', array_map(fn($n) => ptKurzname($n), array_values($schiessen)));
    } elseif ($anzahl === 1) {
        foreach ($block['eintraege'] as $x) {
            if (in_array($x['art'], ['jm', 'schiessen'], true) && $x['ort'] !== '' && !ptIstKoordinate($x['ort'])) { $unter = $x['ort']; break; }
        }
    } elseif (count($termine) > 1) {
        $unter = implode(', ', array_values($termine));
    }
    return ['titel' => $titel, 'wann' => implode(' · ', $teile), 'unter' => $unter, 'mehrtaegig' => $mehrtaegig];
}

/** Name ohne Laufnummer und Jahr für Aufzählungen: «60. Gasterländer … 2026» → «Gasterländer …» */
function ptKurzname(string $name): string {
    return trim(preg_replace(['/^\d+\.\s*/u', '/\s+\d{4}$/u'], '', $name));
}

/** «09:00–11:30 und 13:00–16:00» */
function ptFensterText(array $fenster): string {
    // Ende «23:59» oder offen heisst «ab …» (z.B. Absenden ab 18:00)
    $t = array_map(fn($f) => ($f[1] !== '' && $f[1] !== '23:59') ? $f[0] . '–' . $f[1] : 'ab ' . $f[0], $fenster);
    return count($t) === 2 ? $t[0] . ' und ' . $t[1] : implode(', ', $t);
}

/** «Fr 24.» oder mit Monat «So 26. April» */
function ptTagKurz(string $ymd, bool $mitMonat = false): string {
    $ts = strtotime($ymd);
    return PT_TAGE_KURZ[(int) date('w', $ts)] . ' ' . (int) date('j', $ts) . '.' . ($mitMonat ? ' ' . PT_MONAT_LANG[(int) date('n', $ts)] : '');
}

/** «Samstag, 10. Oktober 2026» */
function ptTagLang(string $ymd, bool $mitJahr = true): string {
    $ts = strtotime($ymd);
    return PT_TAGE_LANG[(int) date('w', $ts)] . ', ' . (int) date('j', $ts) . '. ' . PT_MONAT_LANG[(int) date('n', $ts)] . ($mitJahr ? ' ' . date('Y', $ts) : '');
}

/** «heute» / «morgen» / '' */
function ptNaehe(string $ymd): string {
    if ($ymd === date('Y-m-d')) return 'heute';
    if ($ymd === date('Y-m-d', strtotime('+1 day'))) return 'morgen';
    return '';
}

function ptIstKoordinate(string $ort): bool {
    return (bool) preg_match('/^\s*-?\d{1,3}\.\d+\s*[,\/]\s*-?\d{1,3}\.\d+\s*$/', $ort);
}

/** Link auf die Karte (Adresse oder Koordinaten) */
function ptKartenLink(string $ort): string {
    $q = ptIstKoordinate($ort) ? preg_replace('/\s*[,\/]\s*/', ',', trim($ort)) : $ort;
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
}
