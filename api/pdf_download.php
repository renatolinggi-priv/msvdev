<?php
/**
 * PDF Download Wrapper
 * Liefert das generierte PDF direkt zum Download aus
 * 
 * Usage: pdf_download.php?year=2024
 */

// Fehlerbehandlung: nichts an den Browser ausgeben (wuerde das PDF zerstoeren), aber
// alles ins Error-Log schreiben. Vorher stand hier error_reporting(0) -- das hat auch
// das Logging abgeschaltet, weshalb der HTTP 500 dieses Endpoints unsichtbar blieb.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Parameter
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Pfad zur export_jmdefinition_pdf.php
$export_script = __DIR__ . '/../inc/jmdefinition/export_jmdefinition_pdf.php';

// Prüfen ob das Script existiert
if (!file_exists($export_script)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    die('PDF-Generator nicht gefunden.');
}

// Output Buffer starten um JSON zu fangen
ob_start();

// Export-Script ausführen. Es benutzt relative Pfade ('../vendor/autoload.php',
// '../config.php'), die vom Working Directory abhaengen -- ohne chdir() laufen die ins
// Leere und der Endpoint endet in einem Fatal (HTTP 500, leere Antwort).
$cwd_vorher = getcwd();
chdir(dirname($export_script));
$_GET['year'] = $year;
include $export_script;
if ($cwd_vorher !== false) {
    chdir($cwd_vorher);
}

// Output abfangen
$output = ob_get_clean();

// JSON parsen
$result = json_decode($output, true);

// Prüfen ob erfolgreich
if (!$result || !isset($result['success']) || !$result['success']) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    die('Fehler beim Generieren des PDFs: ' . ($result['message'] ?? 'Unbekannter Fehler'));
}

// PDF-Pfad aus JSON extrahieren
$pdf_link = $result['pdf_link'] ?? '';
if (empty($pdf_link)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    die('PDF-Link konnte nicht ermittelt werden.');
}

// Vollständigen Pfad zum PDF erstellen
// Der Link ist relativ: "jmdefinition/dat/Jahresprogramm_2025_2025-11-21_11-26-32.pdf"
$pdf_path = __DIR__ . '/../inc/' . $pdf_link;

// Prüfen ob PDF existiert
if (!file_exists($pdf_path)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    die('PDF-Datei nicht gefunden: ' . basename($pdf_path));
}

// PDF ausliefern
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Jahresprogramm_' . $year . '.pdf"');
header('Content-Length: ' . filesize($pdf_path));
header('Cache-Control: public, max-age=3600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// PDF ausgeben
readfile($pdf_path);
exit;
?>
