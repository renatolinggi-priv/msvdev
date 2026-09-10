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
               . '<th class="name">Name</th><th class="pkt">Resultat</th>'
               . '</tr></thead><tbody>';
        foreach ($liste as $e) {
            $html .= '<tr><td class="name">' . $h($e['name']) . '</td>'
                   . '<td class="pkt">' . $e['punkte'] . '</td></tr>';
        }
        for ($i = 0; $i < $pad; $i++) {
            $html .= '<tr class="pad"><td class="name">&nbsp;</td><td class="pkt"></td></tr>';
        }
        return $html . '</tbody></table>' . $schnittBox($schnitt);
    };

    $html = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">
<style>' . pdf_theme_css() . '
    /* Sektionsmeisterschaft: Layout-Overrides (Hochformat) */
    @page { margin: 1.5cm; }
    body { font-size: 11px; margin: 0; padding: 0; }
    .header { position: relative; margin-bottom: 20px; min-height: 80px; }
    /* .header .logo schlaegt die Theme-Regel .header img (Logo-Breite aus pdf_theme) */
    .header .logo { position: absolute; top: 0; left: 0; width: 80px; max-width: 80px; height: auto; margin: 0; }
    h1 { text-align: center; font-size: 20px; margin: 0; padding-top: 20px; color: #3b5998; }
    h2 { font-size: 14px; margin: 0 0 8px 0; border-bottom: 2px solid #cbd5e0; padding-bottom: 4px; }
    .zweispaltig { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .zweispaltig > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; border: 0; background: none; }
    .zweispaltig > tbody > tr > td.links { padding-right: 10px; }
    .zweispaltig > tbody > tr > td.rechts { padding-left: 10px; }
    .block { page-break-inside: avoid; }
    .table { width: 100%; }
    .table th, .table td { padding: 3px 4px; }
    .table th.name, .table td.name { text-align: left; }
    .table tr.pad td { background: none; }
    .pkt { text-align: center; width: 56px; }
    .total { font-weight: bold; }
    .leer { color: #718096; font-style: italic; margin: 0 0 12px 0; }
    .schnitt { width: 100%; margin-top: 8px; border-collapse: collapse; font-size: 10px; }
    .schnitt td { padding: 2px 4px; border: 0; border-bottom: 1px solid #e2e8f0; background: none; }
    .schnitt td.sl { color: #4a5568; }
    .schnitt td.sv { text-align: right; font-weight: bold; width: 70px; }
    .schnitt tr.final td { border-bottom: 0; border-top: 1px solid #a0aec0; }
    .schnitt tr.final td.sv { font-size: 12px; color: #276749; }
</style>
<title>Sektionsmeisterschaft ' . $selectedYear . '</title></head><body>
<div class="header">
    <img src="' . pdf_logo_src() . '" class="logo" alt="MSV Wilen Logo">
    <h1>Sektionsmeisterschaft ' . $selectedYear . '</h1>
</div>
<table class="zweispaltig"><tbody><tr>
    <td class="links"><div class="block">' . $tabelleRunde($daten['runde1'], 'Runde 1', $daten['schnitt1'], $n1 ? $ziel - $n1 : 0) . '</div></td>
    <td class="rechts"><div class="block">' . $tabelleRunde($daten['runde2'], 'Runde 2', $daten['schnitt2'], $n2 ? $ziel - $n2 : 0) . '</div></td>
</tr></tbody></table>
<div class="footer"><p>Generiert am ' . date('d.m.Y \u\m H:i') . ' Uhr</p></div>
</body></html>';

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

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
    echo json_encode(['pdf_link' => null, 'error' => $e->getMessage()]);
}
