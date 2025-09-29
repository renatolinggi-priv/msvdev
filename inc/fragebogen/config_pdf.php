<?php
// config_pdf.php

function imgToBase64($imgPath)
{
    $imageData = base64_encode(file_get_contents($imgPath));
    $src = 'data:' . mime_content_type($imgPath) . ';base64,' . $imageData;
    return $src;
}

$logoBase64 = imgToBase64('dat/MSVWilen_Logo.jpg');

// HTML-Header (CSS usw.)
// Keine feste Spaltenbreite, kein table-layout: fixed
$header = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Allgemeine Basis-Styles */
        body {
            font-family: Arial, sans-serif;
            font-size: 8px; /* etwas kleiner als 9px, damit es in PDF nicht zu breit wird */
        }
        .container {
            margin: 5px;
            padding: 1px;
        }
        h2 {
            text-align: left;
            margin-bottom: 20px;
        }
        .header {
            display: flex;
            align-items: center;
        }
        .header img {
            max-width: 100px;
            margin-right: 20px;
        }

        /* Tabelle so wie in load_jm.php */
        .table {
            width: 100%;
            border-collapse: collapse; 
            /* KEIN table-layout: fixed, damit wir dem HTML-Verhalten in load_jm ähneln */
        }

        .table th, .table td {
            vertical-align: middle;
            padding: 2px; /* wenig Padding für kompaktere Darstellung */
            border: 1px solid #000;
            font-family: Arial, sans-serif;
            font-size: 8px;
        }

        .bold {
            font-weight: bold;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 6px;
            padding: 10px 0;
            border-top: 1px solid #000;
        }

        td.zwischenzeile {
            font-size: 3px;
        }
        th.vertical-bottom {
            writing-mode: horizontal-tb;
            transform: none;
            vertical-align: bottom;
            border: 1px solid #000;
        }
        th.vertical-top {
            writing-mode: horizontal-tb;
            transform: none;
            vertical-align: top;
            border: 1px solid #000;
        }

        .small-input {
            width: 60px;
        }

        .page-break {
            page-break-after: always;
        }

        /* Rotierte Spaltenüberschriften (wie in load_jm.php) */
        .vertical-header {
            /* Dompdf unterstützt writing-mode kaum, 
               also Rotation wie in load_jm. */
            transform: rotate(270deg);
            transform-origin: left top 0;
            white-space: nowrap;
            text-align: left;
        }
    </style>';

// Footer schließt body + html
$footer = '</body>
</html>';
