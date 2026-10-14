<?php
// inc/cuprang/generate_cup_pdf.php
// VERSION 6.0 - Mit Tiefschuss (klein) und fettem Resultat

use Dompdf\Dompdf;
use Dompdf\Options;

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Includes
    require '../vendor/autoload.php';
    include '../config.php';
    require_once __DIR__ . '/../admin_api_guard.inc.php';
    adminApiGuard('json');
    require_once __DIR__ . '/../pdf/pdf_theme.php';
    require_once __DIR__ . '/../pdf/pdf_orientation.inc.php'; // ?orientation= aus dem Druckprofil (setPaper + @page)
    
    if (!isset($conn) && function_exists('get_db_connection')) {
        $conn = get_db_connection();
    }
    
    require_once __DIR__ . '/cup_repository.php';
    require_once __DIR__ . '/cup_logic.php';
    
    if (!isset($conn) || !$conn) {
        throw new Exception("Keine DB-Verbindung verfügbar.");
    }
    
    // Jahr
    $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
    
    // Daten laden
    $pairs = cup_fetch_pairs($conn, $selectedYear);
    $finalRaw = cup_fetch_final_results($conn, $selectedYear);
    $standRaw = cup_fetch_standcup_final($conn, $selectedYear);

    // Leeres Jahr: kein leeres PDF (und kein leeres Blatt im Direktdruck), sondern ein Hinweis
    if (empty($pairs) && empty($finalRaw) && empty($standRaw)) {
        msvAusgabeLeer('Für ' . $selectedYear . ' sind noch keine Cup-Paarungen erfasst.');
    }

    // Namen für Paarungen laden
    $allIds = [];
    foreach ($pairs as $p) {
        if (!empty($p['Participant1'])) $allIds[] = (int)$p['Participant1'];
        if (!empty($p['Participant2'])) $allIds[] = (int)$p['Participant2'];
        if (!empty($p['Participant3'])) $allIds[] = (int)$p['Participant3'];
    }
    $names = get_member_names_bulk($conn, array_unique($allIds));
    
    // Runden
    $rounds = array_values(array_unique(array_map(fn($r) => (int)$r['Round'], $pairs)));
    sort($rounds, SORT_ASC);
    
    // Finale Rangliste vorbereiten (MIT Tiefschuss!)
    $final = [];
    foreach ($finalRaw as $r) {
        if (empty($r['Teilnehmer']) && !empty($r['ParticipantID'])) {
            $r['Teilnehmer'] = get_member_name($conn, (int)$r['ParticipantID']);
        }
        if (!isset($r['Punkte']) && isset($r['Result'])) {
            $r['Punkte'] = $r['Result'];
        }
        if (!isset($r['Tiefschuss']) && isset($r['LowShot'])) {
            $r['Tiefschuss'] = $r['LowShot'];
        }
        $final[] = $r;
    }
    
    // Sortieren nach Punkte UND Tiefschuss
    usort($final, function($a, $b) {
        $pa = (int)($a['Punkte'] ?? 0);
        $pb = (int)($b['Punkte'] ?? 0);
        if ($pb !== $pa) return $pb <=> $pa;
        // Bei gleichen Punkten: höherer Tiefschuss gewinnt
        return ((int)($b['Tiefschuss'] ?? 0)) <=> ((int)($a['Tiefschuss'] ?? 0));
    });
    
    $rankedFinal = [];
    $rank = 0;
    $i = 0;
    $prev = null;
    foreach ($final as $r) {
        $i++;
        $key = $r['Punkte'] . '|' . $r['Tiefschuss'];
        if ($key !== $prev) {
            $rank = $i;
            $prev = $key;
        }
        $r['Rang'] = $rank;
        $rankedFinal[] = $r;
    }
    
    // Standcup vorbereiten
    $stand = [];
    foreach ($standRaw as $r) {
        if (!isset($r['Punkte']) && isset($r['Result'])) {
            $r['Punkte'] = $r['Result'];
        }
        if (!isset($r['club']) && isset($r['Club'])) {
            $r['club'] = $r['Club'];
        }
        if (empty($r['ParticipantName'])) {
            $r['ParticipantName'] = 'Unbekannt';
        }
        $stand[] = $r;
    }
    
    usort($stand, fn($a, $b) => ((int)($b['Punkte'] ?? 0)) <=> ((int)($a['Punkte'] ?? 0)));
    $i = 0;
    $rank = 0;
    $prev = null;
    foreach ($stand as &$r) {
        $i++;
        $punkte = (int)($r['Punkte'] ?? 0);
        if ($punkte !== $prev) {
            $rank = $i;
            $prev = $punkte;
        }
        $r['_rank'] = $rank;
    }
    unset($r);
    
    // HTML im Ranglisten-Layout (pdf_rangliste_anfang); Paarungen wie im Absendenbuch: Sieger fett,
    // feine Linie unter jeder Begegnung, Reihenfolge der Auslosung bleibt.
    $layout = '
    .columns { width: 100%; border-collapse: collapse; }
    .columns > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; border: 0; background: none; }
    .columns > tbody > tr > td.links { padding-right: 5mm; }
    .columns > tbody > tr > td.rechts { padding-left: 5mm; }
    .paarung { width: 100%; border-collapse: collapse; margin: 0; }
    .paarung td { padding: 0.9mm 1.4mm; border: 0; background: none; }
    .paarung tr.letzte td { border-bottom: 0.5pt solid #000; padding-bottom: 1.4mm; }
    .paarung tr.erste td { padding-top: 1.4mm; }
    .paarung td.pkt { text-align: right; width: 12mm; }
    .paarung td.ts { text-align: right; width: 10mm; color: #64748b; }
    .paarung .sieger { font-weight: bold; }
    .table td.pkt { width: 14mm; }
    .table td.ts { width: 12mm; color: #64748b; }
    .club { color: #64748b; font-size: 8pt; }
    .stand td { vertical-align: top; }
    ';

    // Eine Begegnung (2er oder 3er) als kleine Tabelle
    $paarungHtml = function (array $row) use ($names) {
        $teilnehmer = [];
        foreach ([1, 2, 3] as $k) {
            $pid = !empty($row['Participant' . $k]) ? (int)$row['Participant' . $k] : 0;
            if (!$pid && $k === 3) {
                continue;
            }
            $teilnehmer[$k] = [
                'name' => $pid ? ($names[$pid] ?? 'Mitglied #' . $pid) : '',
                'res'  => $row['Result' . $k] ?? '',
                'ts'   => $row['LowShot' . $k] ?? '',
            ];
        }
        if (isset($teilnehmer[3])) {
            $verlierer = cup_three_loser_index($row);
            $sieger = fn($k) => $verlierer !== null && $verlierer !== $k;
        } else {
            $gewinner = cup_winner_index($row);
            $sieger = fn($k) => $gewinner === $k;
        }
        $html = '<table class="paarung"><tbody>';
        $letzte = array_key_last($teilnehmer);
        foreach ($teilnehmer as $k => $t) {
            $kl = trim(($k === 1 ? 'erste' : '') . ($k === $letzte ? ' letzte' : '') . ($sieger($k) ? ' sieger' : ''));
            $html .= '<tr class="' . $kl . '">'
                   . '<td>' . htmlspecialchars($t['name']) . '</td>'
                   . '<td class="pkt">' . htmlspecialchars((string)$t['res']) . '</td>'
                   . '<td class="ts">' . ($t['ts'] !== '' && $t['ts'] !== null ? '(' . htmlspecialchars((string)$t['ts']) . ')' : '') . '</td>'
                   . '</tr>';
        }
        return $html . '</tbody></table>';
    };

    $rundenHtml = function (array $runden) use ($pairs, $paarungHtml) {
        $html = '';
        foreach ($runden as $rnd) {
            $html .= '<h2>Runde ' . $rnd . '</h2>';
            foreach (array_filter($pairs, fn($r) => (int)$r['Round'] === $rnd) as $row) {
                $html .= $paarungHtml($row);
            }
        }
        return $html;
    };

    $html = pdf_rangliste_anfang('Vereinscup ' . $selectedYear, 'MSV Wilen · Paarungen und Finals', $layout);

    // Paarungen in zwei Spalten
    if (!empty($pairs)) {
        $halfPoint = ceil(count($rounds) / 2);
        $html .= '<table class="columns"><tbody><tr>'
               . '<td class="links">' . $rundenHtml(array_slice($rounds, 0, $halfPoint)) . '</td>'
               . '<td class="rechts">' . $rundenHtml(array_slice($rounds, $halfPoint)) . '</td>'
               . '</tr></tbody></table>';
    }

    // Finals nebeneinander
    $finalHtml = '<h2>MSV Wilen Final</h2>';
    if (!empty($rankedFinal)) {
        $finalHtml .= '<table class="table"><thead><tr><th class="r">Rang</th><th>Name</th><th class="r">Punkte</th><th class="r">TS</th></tr></thead><tbody>';
        foreach ($rankedFinal as $r) {
            $finalHtml .= pdf_rangliste_zeile(min((int)$r['Rang'], 5)) // Final: nur fett, kein Abstand
                        . '<td class="rang">' . $r['Rang'] . '.</td>'
                        . '<td>' . htmlspecialchars($r['Teilnehmer'] ?? '') . '</td>'
                        . '<td class="r pkt">' . htmlspecialchars((string)($r['Punkte'] ?? '')) . '</td>'
                        . '<td class="r ts">' . htmlspecialchars((string)($r['Tiefschuss'] ?? '')) . '</td></tr>';
        }
        $finalHtml .= '</tbody></table>';
    }

    $standHtml = '<h2>Standcup Final</h2>';
    if (!empty($stand)) {
        $standHtml .= '<table class="table stand"><thead><tr><th class="r">Rang</th><th>Name</th><th class="r">Punkte</th></tr></thead><tbody>';
        foreach ($stand as $r) {
            $standHtml .= pdf_rangliste_zeile(min((int)$r['_rank'], 5))
                        . '<td class="rang">' . $r['_rank'] . '.</td>'
                        . '<td>' . htmlspecialchars($r['ParticipantName'] ?? 'Unbekannt')
                        . (!empty($r['club']) ? '<br><span class="club">' . htmlspecialchars($r['club']) . '</span>' : '') . '</td>'
                        . '<td class="r pkt">' . htmlspecialchars((string)($r['Punkte'] ?? '')) . '</td></tr>';
        }
        $standHtml .= '</tbody></table>';
    }

    $html .= '<table class="columns" style="margin-top: 4mm;"><tbody><tr>'
           . '<td class="links">' . $finalHtml . '</td>'
           . '<td class="rechts">' . $standHtml . '</td>'
           . '</tr></tbody></table>';

    $html .= '</body></html>';
    
    // PDF generieren
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', pdfOrientationParam('portrait')); // Ausrichtung aus dem Druckprofil, Default hoch (auch in @page oben)
    $dompdf->render();
    pdf_seitenfuss($dompdf, 'Vereinscup ' . $selectedYear);
    
    // Verzeichnis prüfen
    if (!is_dir('dat')) {
        if (!mkdir('dat', 0755, true)) {
            throw new Exception("Konnte Verzeichnis nicht erstellen");
        }
    }
    
    // PDF speichern
    $date = new DateTime();
    $pdfFilePath = 'dat/cup_' . $selectedYear . '_' . $date->format('Y-m-d_H-i-s') . '.pdf';
    
    if (!file_put_contents($pdfFilePath, $dompdf->output())) {
        throw new Exception("Konnte PDF nicht speichern");
    }
    
    // Ausgabepuffer leeren
    ob_end_clean();
    
    // JSON-Antwort
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'pdf_link' => "/inc/cuprang/" . $pdfFilePath
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("Cup PDF-Generator Fehler: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e),
        'pdf_link' => null
    ]);
}
