<?php
/**
 * inc/sektionrang/generate_pdf.php — PDF «Sektionsmeisterschaft <Jahr>»:
 * Runde 1 und Runde 2 nebeneinander, je mit Schnitt-Block (Regel der Sektionsabrechnungen).
 * GET: year. Antwort: JSON {pdf_link} relativ zu inc/.
 */
use Dompdf\Dompdf;
use Dompdf\Options;

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require '../vendor/autoload.php';
    include '../config.php';
    require_once __DIR__ . '/../admin_api_guard.inc.php';
    adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
    require_once __DIR__ . '/../pdf/pdf_theme.php';
    require_once __DIR__ . '/functions.inc.php';

    if ($conn->connect_error) {
        throw new Exception('Verbindung fehlgeschlagen: ' . $conn->connect_error);
    }

    $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    $currentYear  = (int)date('Y');
    if ($selectedYear < 2000 || $selectedYear > $currentYear + 1) {
        $selectedYear = $currentYear;
    }

    $daten = sektionrangDaten($conn, $selectedYear);
    $conn->close();
    $n1   = count($daten['runde1']);
    $n2   = count($daten['runde2']);
    $ziel = max($n1, $n2);

    // Leeres Jahr: kein leeres PDF (und kein leeres Blatt im Direktdruck), sondern ein Hinweis
    if (!$n1 && !$n2) {
        msvAusgabeLeer('Für ' . $selectedYear . ' sind noch keine Sektionsresultate erfasst.');
    }

    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    // Schnitt-Block (Regel der Sektionsabrechnungen) unterhalb einer Runde
    $schnittBox = function (?array $s): string {
        if ($s === null) {
            return '';
        }
        $row = fn(string $l, string $v, string $cls = '') =>
            '<tr class="' . $cls . '"><td class="sl">' . $l . '</td><td class="sv">' . $v . '</td></tr>';
        return '<table class="schnitt"><tbody>'
             . $row('Teilnehmer', (string)$s['teilnehmer'])
             . $row('Pflichtteilnehmer', (string)$s['verwendete'])
             . $row('Durchschnitt', number_format($s['durchschnitt'], 2))
             . $row('Beteiligungszuschlag', $s['zuschlag'] . ' %')
             . $row('Endergebnis', number_format($s['endergebnis'], 3), 'final')
             . '</tbody></table>';
    };

    // $pad = Leerzeilen, damit beide Runden gleich lang sind (Schnitt-Blöcke auf gleicher Höhe)
    $tabelleRunde = function (array $liste, string $titel, ?array $schnitt, int $pad) use ($h, $schnittBox): string {
        $html = '<h2>' . $h($titel) . '</h2>';
        if (!$liste) {
            return $html . '<p class="leer">Keine Resultate.</p>';
        }
        $html .= '<table class="table"><thead><tr>'
               . '<th>Name</th><th class="r">Resultat</th>'
               . '</tr></thead><tbody>';
        foreach ($liste as $e) {
            $html .= '<tr><td class="name">' . $h($e['name']) . '</td>'
                   . '<td class="r pkt">' . $e['punkte'] . '</td></tr>';
        }
        for ($i = 0; $i < $pad; $i++) {
            $html .= '<tr><td class="name">&nbsp;</td><td class="pkt"></td></tr>';
        }
        return $html . '</tbody></table>' . $schnittBox($schnitt);
    };

    // Runde 1 und 2 nebeneinander; Schnitt-Block unter jeder Runde
    $layout = '
    .zweispaltig { width: 100%; border-collapse: collapse; }
    .zweispaltig > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; border: 0; background: none; }
    .zweispaltig > tbody > tr > td.links { padding-right: 5mm; }
    .zweispaltig > tbody > tr > td.rechts { padding-left: 5mm; }
    .zweispaltig h2 { margin-top: 0; }
    .block { page-break-inside: avoid; }
    .table td.pkt { width: 18mm; }
    .leer { font-style: italic; margin: 0 0 4mm 0; }
    .schnitt { width: 100%; margin-top: 2mm; border-collapse: collapse; }
    .schnitt td { padding: 0.8mm 1.4mm; border: 0; background: none; }
    .schnitt td.sv { text-align: right; width: 22mm; }
    .schnitt tr.final td { border-top: 0.8pt solid #000; font-weight: bold; padding-top: 1.4mm; }
    ';
    $html = pdf_rangliste_anfang('Sektionsmeisterschaft ' . $selectedYear, 'Rangliste', $layout) . '
<table class="zweispaltig"><tbody><tr>
    <td class="links"><div class="block">' . $tabelleRunde($daten['runde1'], 'Runde 1', $daten['schnitt1'], $n1 ? $ziel - $n1 : 0) . '</div></td>
    <td class="rechts"><div class="block">' . $tabelleRunde($daten['runde2'], 'Runde 2', $daten['schnitt2'], $n2 ? $ziel - $n2 : 0) . '</div></td>
</tr></tbody></table>
</body></html>';

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    require_once __DIR__ . '/../pdf/pdf_orientation.inc.php';
    $dompdf->setPaper('A4', pdfOrientationParam('portrait')); // Ausrichtung aus dem Druckprofil, Default hoch
    $dompdf->render();
    pdf_seitenfuss($dompdf, 'Sektionsmeisterschaft ' . $selectedYear);

    $datDir = __DIR__ . '/dat';
    if (!is_dir($datDir) && !mkdir($datDir, 0755, true)) {
        throw new Exception('Konnte Verzeichnis nicht erstellen');
    }

    $fileName = 'RanglisteSektionsmeisterschaft_' . $selectedYear . '_' . date('Y-m-d_H-i-s') . '.pdf';
    if (!file_put_contents($datDir . '/' . $fileName, $dompdf->output())) {
        throw new Exception('Konnte PDF nicht speichern');
    }

    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['pdf_link' => 'sektionrang/dat/' . $fileName]);
} catch (Throwable $e) {
    ob_end_clean();
    error_log('Sektionsmeisterschaft-PDF Fehler: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['pdf_link' => null, 'error' => msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e)]);
}
