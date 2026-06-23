<?php
// config_pdf.php — Fragebogen (nutzt zentrales PDF-Theme)

require_once __DIR__ . '/../pdf/pdf_theme.php';

function imgToBase64($imgPath)
{
    if (!file_exists($imgPath)) {
        return '';
    }
    $imageData = base64_encode(file_get_contents($imgPath));
    $src = 'data:' . mime_content_type($imgPath) . ';base64,' . $imageData;
    return $src;
}

$logoBase64 = imgToBase64('dat/MSVWilen_Logo.jpg');

// Datei-spezifische Layout-Overrides (NACH dem Theme). Nur Layout, keine Farben:
// kompakte Breittabelle + rotierte Spaltenüberschriften wie in load_jm.php.
$fragebogenOverrides = '
    body { font-size: 8px; }
    .table th, .table td { padding: 2px; font-size: 8px; }
    td.zwischenzeile { font-size: 3px; }
    .small-input { width: 60px; }
    /* Rotierte Spaltenüberschriften (Dompdf unterstützt writing-mode kaum -> Rotation) */
    .vertical-header {
        transform: rotate(270deg);
        transform-origin: left top 0;
        white-space: nowrap;
        text-align: left;
    }
    th.vertical-bottom { transform: none; vertical-align: bottom; }
    th.vertical-top { transform: none; vertical-align: top; }
';

// HTML-Header (CSS usw.) — Spaltenbreiten frei, kein table-layout: fixed
$header = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>' . pdf_theme_css() . $fragebogenOverrides . '</style>';

// Footer schließt body + html
$footer = '</body>
</html>';
