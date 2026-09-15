<?php
/**
 * pages/drucksteuerung/test_pdf.php — Testseite für den Testdruck eines Druckprofils
 *
 * GET: doc_type, label, printer, orientation (portrait|landscape), paper (A4), copies, duplex, color
 * Antwort: PDF inline (application/pdf), eine Seite im gewählten Format.
 *
 * Der Testdruck läuft damit über denselben Pfad wie die echten Dokumente (PDF → QZ Tray, Vektordruck),
 * statt über HTML, das QZ Tray in einer JavaFX-WebView rendert und unzuverlässig skaliert.
 * Die Seite zeigt einen Rahmen 10 mm innerhalb der Blattkante und ein Massband, damit Ausrichtung,
 * Papierformat und Skalierung am Ausdruck sofort erkennbar sind.
 */
require_once __DIR__ . '/../session_config.inc.php';
require_once __DIR__ . '/../../auth.php';

if (!isset($_SESSION['user_id']) && function_exists('restoreSessionFromToken')) {
    restoreSessionFromToken();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nicht eingeloggt';
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../pdf/pdf_orientation.inc.php';

$esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$orientation = pdfOrientationParam('portrait');
$paper       = strtoupper(trim((string)($_GET['paper'] ?? 'A4')));
if (!in_array($paper, ['A3', 'A4', 'A5', 'LETTER'], true)) $paper = 'A4';
$label   = trim((string)($_GET['label'] ?? $_GET['doc_type'] ?? 'Testdruck'));
$printer = trim((string)($_GET['printer'] ?? ''));
$copies  = max(1, (int)($_GET['copies'] ?? 1));
$duplex  = ['long-edge' => 'beidseitig, lange Seite', 'short-edge' => 'beidseitig, kurze Seite'][(string)($_GET['duplex'] ?? '')] ?? 'einseitig';
$color   = ['color' => 'Farbe', 'grayscale' => 'Graustufen'][(string)($_GET['color'] ?? '')] ?? 'Schwarzweiss';
$datum   = date('d.m.Y H:i');

$zeilen = '';
foreach ([
    'Dokumenttyp' => $label,
    'Drucker'     => $printer !== '' ? $printer : '–',
    'Format'      => $paper . ' ' . ($orientation === 'landscape' ? 'quer' : 'hoch'),
    'Duplex'      => $duplex,
    'Farbe'       => $color,
    'Kopien'      => (string)$copies,
    'Zeit'        => $datum,
] as $k => $v) {
    $zeilen .= '<tr><th>' . $esc($k) . '</th><td>' . $esc($v) . '</td></tr>';
}
// Massband 0–100 mm
$ticks = '';
for ($mm = 0; $mm <= 100; $mm += 10) {
    $ticks .= '<div class="tick" style="left:' . $mm . 'mm"><span>' . $mm . '</span></div>';
}
$farbfeld = $color === 'Schwarzweiss'
    ? ''
    : '<p class="hint">Farbtest: <span class="sw" style="background:#d62828"></span><span class="sw" style="background:#2a9d8f"></span><span class="sw" style="background:#e9c46a"></span> – erscheinen die Felder grau, druckt das Gerät nicht farbig.</p>';

$html = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><style>
@page { size: ' . $paper . ' ' . $orientation . '; margin: 0; }
body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #222; }
.frame { position: absolute; top: 10mm; left: 10mm; right: 10mm; bottom: 10mm; border: 0.6mm solid #444; }
.frame::after { content: "Rahmen 10 mm innerhalb der Blattkante"; position: absolute; right: 3mm; bottom: 1mm; font-size: 8px; color: #777; }
.inhalt { position: absolute; top: 22mm; left: 22mm; right: 22mm; }
h1 { font-size: 22px; margin: 0 0 4mm 0; }
table { border-collapse: collapse; font-size: 12px; margin-top: 4mm; }
th { text-align: left; padding: 1.5mm 6mm 1.5mm 0; color: #555; font-weight: normal; }
td { padding: 1.5mm 0; font-weight: bold; }
.ruler { position: relative; height: 10mm; margin-top: 10mm; border-bottom: 0.4mm solid #444; width: 100mm; }
.tick { position: absolute; bottom: 0; width: 0.3mm; height: 4mm; background: #444; }
.tick span { position: absolute; bottom: 5mm; left: -3mm; width: 6mm; text-align: center; font-size: 8px; color: #555; }
.hint { font-size: 10px; color: #555; margin-top: 8mm; }
.sw { display: inline-block; width: 10mm; height: 5mm; margin: 0 1mm; vertical-align: middle; }
</style></head><body>
<div class="frame"></div>
<div class="inhalt">
  <h1>Testdruck ' . $esc($label) . '</h1>
  <div style="font-size:11px;color:#555">MSV Wilen · Drucksteuerung · Profil-Test über QZ Tray</div>
  <table>' . $zeilen . '</table>
  <div class="ruler">' . $ticks . '</div>
  <p class="hint">Massband 100 mm: stimmt die Länge, wird ohne Skalierung gedruckt. Der Rahmen muss rundum gleich weit von der Blattkante liegen.</p>
  ' . $farbfeld . '
</div>
</body></html>';

$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'Helvetica');
$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper($paper, $orientation);
$dompdf->render();

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Testdruck.pdf"');
header('Cache-Control: no-store');
echo $dompdf->output();
