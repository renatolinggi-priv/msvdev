<?php
// config_pdf.php — Fragebogen (nutzt zentrales PDF-Theme)

require_once __DIR__ . '/../pdf/pdf_theme.php';

// Zentrales Logo (pdf_theme.php), unabhaengig vom Arbeitsverzeichnis
$logoBase64 = pdf_logo_src();

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

// Footer schliesst body + html
$footer = '</body>
</html>';
