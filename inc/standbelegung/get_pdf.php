<?php
// get_pdf.php - API-Endpoint für WordPress-Plugin
// Gibt den Pfad zum Standbelegung-PDF zurück
// 
// Verwendung im WordPress-Plugin:
// - API URL: https://jahresmeisterschaft.msvwilen.ch/inc/standbelegung/get_pdf.php
// - PDF Basis-URL: https://jahresmeisterschaft.msvwilen.ch/inc/standbelegung/
// - Parameter: year
// - Shortcode: [msv_standbelegung year="2025"]

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://www.msvwilen.ch');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Credentials: true');

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$pdfDir = __DIR__ . '/pdf';
$filename = 'standbelegung_' . $year . '.pdf';
$filepath = $pdfDir . '/' . $filename;

// Prüfe ob PDF existiert
if (!file_exists($filepath)) {
    // Versuche aktuelles Jahr
    $currentYear = date('Y');
    $fallbackFilename = 'standbelegung_' . $currentYear . '.pdf';
    $fallbackPath = $pdfDir . '/' . $fallbackFilename;
    
    if (file_exists($fallbackPath)) {
        $filepath = $fallbackPath;
        $filename = $fallbackFilename;
        $year = $currentYear;
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'PDF nicht gefunden',
            'requested_year' => $year
        ]);
        exit;
    }
}

// Relativer Link für WordPress-Plugin (pdf_base_url wird vom Plugin vorangestellt)
$pdfLink = 'pdf/' . $filename;

echo json_encode([
    'success' => true,
    'year' => $year,
    'pdf_link' => $pdfLink,
    'filename' => $filename,
    'size' => round(filesize($filepath) / 1024, 1),
    'modified' => date('Y-m-d H:i:s', filemtime($filepath))
]);
