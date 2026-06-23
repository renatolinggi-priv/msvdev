<?php
// config_pdf.php — Endschiessen-Ranglisten (nutzt zentrales PDF-Theme)

require_once __DIR__ . '/../pdf/pdf_theme.php';

// Funktion zum Konvertieren eines Bildes in Base64 (von der Basisklasse genutzt)
function imgToBase64($imgPath) {
    if (!file_exists($imgPath)) {
        return '';
    }
    $imageData = base64_encode(file_get_contents($imgPath));
    $src = 'data:' . mime_content_type($imgPath) . ';base64,' . $imageData;
    return $src;
}

$header = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>' . pdf_theme_css() . '</style>';

$footer = '</div>
    ' . pdf_footer_html('MSV Wilen') . '
    </body>
    </html>';
