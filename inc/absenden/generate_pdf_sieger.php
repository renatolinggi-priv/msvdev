<?php
/**
 * generate_pdf_sieger.php — Seite «Sieger <Jahr>» des Absendenbuchs als PDF im Ranglisten-Stil
 *
 * Letzter Teil der Sammelrangliste (endresultate.php, Knopf «Sammelrangliste»): die Sieger aller Wettkämpfe,
 * oben die beiden Jahresmeister. Die Sieger kommen aus derselben Rechnung wie im Absendenbuch: Die Vorlage wird
 * befüllt (absendenbuchTemplateFuellen, nur im Speicher, nichts wird gespeichert) und dabei merkt sich jeder
 * Abschnitt seinen Rang 1 (absendenSiegerMerken). Ohne Spalte «Seite», die Seitenzahlen gelten nur im Buch.
 *
 * GET: year   Antwort: JSON {pdf_link: 'absenden/dat/Sieger_<Jahr>_…pdf'} bzw. {success:false, leer:true, message}
 * Muss aus dem globalen Scope laufen: functions.inc.php liest $selectedYear per «global».
 */
include __DIR__ . '/../config.php';            // $conn + zentraler dat/-Cleanup
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/absendenbuch_docx.inc.php';
require_once __DIR__ . '/../pdf/pdf_theme.php';

header('Content-Type: application/json; charset=utf-8');
$selectedYear = absendenbuchJahr();

try {
    ob_start(); // Ausgaben der Abschnitte (Warnungen) nicht in die JSON-Antwort
    $hinweise = [];
    absendenbuchTemplateFuellen($selectedYear, $conn, $hinweise); // Vorlage nur im Speicher, wird verworfen
    ob_end_clean();
    $sieger = $GLOBALS['absendenSieger'] ?? [];
    if ($hinweise) {
        error_log('[sieger_pdf] Hinweise beim Befüllen: ' . implode(' | ', $hinweise));
    }

    // Reihenfolge und Bezeichnungen wie auf der Seite «Sieger <Jahr>» im Buch (absendenbuchSiegerSeiteXml)
    $reihe = [
        'Endstich' => 'Endstich', 'Schwinistich' => 'Schwinistich', 'Zabigstich' => 'Zabigstich',
        'Glueckstich' => 'Glückstich', 'Kunststich' => 'Kunststich',
        'EndschA' => 'Endschiessen Kat. A', 'EndschB' => 'Endschiessen Kat. B',
        'EndschJ' => 'Endschiessen Jungschützen', 'EndschP' => 'Endschiessen Partner',
        'JMA' => 'Jahresmeisterschaft Kat. A', 'JMB' => 'Jahresmeisterschaft Kat. B',
        'HeimA' => 'Heimmeisterschaft Kat. A', 'HeimB' => 'Heimmeisterschaft Kat. B',
        'KantiA' => 'Kantonalstich Kat. A', 'KantiB' => 'Kantonalstich Kat. B',
        'Cup' => 'Vereinscup (Final)', 'Standcup' => 'Standcup',
    ];
    $hat = fn(string $m): bool => !empty($sieger[$m]['name']);
    if (!array_filter(array_keys($reihe), $hat)) {
        msvAusgabeLeer('Für ' . $selectedYear . ' gibt es noch keine Sieger.');
    }

    $h = fn($s): string => htmlspecialchars(str_replace("\u{200C}", '', trim((string)$s)), ENT_QUOTES, 'UTF-8');
    $css = '
        .table .w1 { width: 42%; } .table .w2 { width: 42%; } .table .w3 { width: 16%; }
        .table.rl-jm td { font-size: 12pt; padding-top: 2mm; padding-bottom: 2mm; }
        .table.rl-jm { border-bottom: 0.8pt solid #000; margin-bottom: 7mm; }
        .table td { padding-top: 1.8mm; padding-bottom: 1.8mm; }';
    $zeile = fn(string $wettkampf, string $marke): string => '<tr><td class="w1">' . $h($wettkampf) . '</td><td class="w2 bold">'
        . $h($sieger[$marke]['name']) . '</td><td class="w3 r">' . $h($sieger[$marke]['resultat']) . '</td></tr>';

    $html = pdf_rangliste_anfang('Sieger ' . $selectedYear, 'Zusammenfassung fürs Absenden', $css);

    // Die zwei Jahresmeister zuerst und etwas grösser (Höhepunkt des Abends, wie im Buch)
    $mitBlock = $hat('JMA') || $hat('JMB');
    if ($mitBlock) {
        $html .= '<div class="rl-abschnitt">Jahresmeister</div><table class="table rl-jm"><tbody>';
        foreach (['JMA' => 'Kat. A (Standardgewehr)', 'JMB' => 'Kat. B (Ordonnanzwaffen)'] as $marke => $kat) {
            if ($hat($marke)) $html .= $zeile($kat, $marke);
        }
        $html .= '</tbody></table>';
    }

    $html .= '<table class="table"><thead><tr><th class="w1">Wettkampf</th><th class="w2">Sieger</th><th class="w3 r">Resultat</th></tr></thead><tbody>';
    foreach ($reihe as $marke => $bezeichnung) {
        if (!$hat($marke)) continue;
        if ($mitBlock && in_array($marke, ['JMA', 'JMB'], true)) continue; // stehen schon im Block «Jahresmeister»
        $html .= $zeile($bezeichnung, $marke);
    }
    $html .= '</tbody></table></body></html>';

    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    pdf_seitenfuss($dompdf, 'Sieger ' . $selectedYear);

    $datei = 'dat/Sieger_' . $selectedYear . '_' . date('Y-m-d_H-i-s') . '.pdf';
    if (file_put_contents(__DIR__ . '/' . $datei, $dompdf->output()) === false) {
        throw new RuntimeException('PDF nicht gespeichert: ' . $datei);
    }
    echo json_encode(['pdf_link' => 'absenden/' . $datei], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => msvFehler(
        'Die Siegerliste konnte nicht erstellt werden. Bitte nochmals versuchen und sonst dem Admin melden.', $e)],
        JSON_UNESCAPED_UNICODE);
}
