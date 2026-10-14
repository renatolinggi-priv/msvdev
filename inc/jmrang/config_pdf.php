<?php
// config_pdf.php — JM-Rangliste (nutzt zentrales PDF-Theme)

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

// Ranglisten-Layout wie im Absendenbuch (pdf_rangliste_css) plus das, was nur die breite JM-Tabelle braucht:
// kleinere Schrift und Polster für die vielen Anlass-Spalten, Zebra wie im Buch, Streicher grau hinterlegt.
$jmrangOverrides = '
    .jm { font-size: 8.5pt; }
    .jm th, .jm td { padding: 1mm 0.7mm; }
    .jm td.name { white-space: nowrap; padding-right: 2mm; }
    .jm td.result-col { text-align: right; }
    .jm td.total { text-align: right; padding-left: 2mm; }
    .jm tbody tr:nth-child(even) td { background: #f2f2f2; }
    .jm .struck, .rl-hinweis .struck { background: #d9d9d9; color: #000; text-decoration: line-through; padding: 0 0.5mm; }
    .jm-zusatz { font-size: 9pt; color: #64748b; font-weight: normal; }
';

// Dokumentanfang: Theme + Ranglisten-Layout + JM. Die Generatoren hängen <title>, </head><body> und den Kopf
// (pdf_rangliste_kopf) an und rufen nach dem Rendern pdf_seitenfuss() auf.
$header = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>' . pdf_theme_css() . pdf_rangliste_css() . $jmrangOverrides . '</style>
    ';

// Footer - nur für HTML-Vorschau, nicht für PDF
$footer = '</body>
</html>';
