<?php
// config_pdf.php — Fragebogen (nutzt zentrales PDF-Theme)

require_once __DIR__ . '/../pdf/pdf_theme.php';

// Zentrales Logo (pdf_theme.php), unabhaengig vom Arbeitsverzeichnis
$logoBase64 = pdf_logo_src();

// Ergänzungen zum Ranglisten-Stil (pdf_rangliste_anfang in generate_pdf.php): kompakte Breittabelle
// im Querformat, leichte Zebrastreifen zum Lesen über viele Spalten. Nur Layout, keine Farben.
$fragebogenOverrides = '
    .table { font-size: 8pt; }
    .table th, .table td { padding: 0.9mm 1.2mm; vertical-align: top; }
    .table th { vertical-align: bottom; }
    .table td:last-child { text-align: left; }
';

// HTML-Header (CSS usw.) — Spaltenbreiten frei, kein table-layout: fixed
$header = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>' . pdf_theme_css() . $fragebogenOverrides . '</style>';

// Footer schliesst body + html
$footer = '</body>
</html>';
