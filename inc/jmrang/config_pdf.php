<?php
// config_pdf.php

function imgToBase64($imgPath)
{
    if (!file_exists($imgPath)) {
        return '';
    }
    $imageData = base64_encode(file_get_contents($imgPath));
    $src = 'data:' . mime_content_type($imgPath) . ';base64,' . $imageData;
    return $src;
}

// HTML-Header mit verbessertem CSS
$header = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Reset und Basis-Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
            padding-top: 20px;
            padding-bottom: 40px;
        }
        
        .container {
            margin: 0 15px 10px 15px;
            padding: 5px;
        }
        
        /* Header Styles */
        h1 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 16px;
            color: #2c3e50;
        }
        
        h2 {
            text-align: left;
            margin-bottom: 15px;
            font-size: 14px;
            color: #34495e;
        }
        
        /* PDF Header mit Logo */
        .pdf-header {
            display: flex;
            align-items: center;
            margin: 0 15px 20px 15px;
            padding-left: 5px;
            position: relative;
            min-height: 64px;
        }

        .logo-container {
            flex: 0 0 auto;
            margin-right: 20px;
        }

        .logo {
            width: 64px;
            height: auto;
        }

        .header-text {
            position: absolute;
            left: 0;
            right: 0;
            text-align: center;
            pointer-events: none;
        }

        .header-text h1 {
            margin: 0;
            font-size: 18px;
            color: #2c3e50;
        }

        .header-text .subtitle {
            margin: 5px 0 0 0;
            font-size: 10px;
            color: #666;
        }

        /* Tabellen-Styles */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
        }

        .table th, 
        .table td {
            vertical-align: middle;
            padding: 3px 4px;
            border: 1px solid #ddd;
            font-family: Arial, sans-serif;
            font-size: 8px;
        }
        
        /* Header-Zeile */
        .table thead {
            background-color: #f8f9fa;
        }
        
        .table th {
            font-weight: bold;
            text-align: left;
            background-color: #e9ecef;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table th:first-child {
            width: 40px;
            text-align: center;
        }
        
        .table th:last-child {
            width: 50px;
            text-align: right;
        }
        
        /* Body-Zeilen */
        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .table tbody tr:hover {
            background-color: #e9ecef;
        }
        
        /* Rang-Spalte */
        .table td:first-child {
            text-align: center;
            font-weight: bold;
        }
        
        /* Total-Spalte */
        .table td:last-child {
            text-align: right;
            font-weight: bold;
            background-color: #f8f9fa;
        }

        /* Resultat-Spalten */
        .result-col {
            text-align: center;
            font-size: 7px;
        }
        
        .result-col .value {
            font-weight: normal;
        }
        
        .result-col .no-data {
            color: #999;
        }

        /* Vertikale Header für Wettbewerbe */
        .vertical-header {
            writing-mode: horizontal-tb;
            transform: none;
            text-align: center;
            font-size: 6px;
            padding: 2px;
            height: auto;
            white-space: normal;
            line-height: 1.2;
        }
        
        /* Gestrichene Werte */
        .struck {
            color: #dc3545;
            text-decoration: line-through;
        }

        /* Seitenumbruch */
        .page-break {
            page-break-after: always;
            page-break-inside: avoid;
        }
        
        /* Footer-Bereich freihalten */
        @page {
            margin-bottom: 50px;
        }
        
        /* Zwischenzeile */
        td.zwischenzeile {
            font-size: 3px;
            height: 5px;
            background-color: #f0f0f0;
        }
        
        /* Responsive für kleine Formate */
        @media print {
            body {
                font-size: 7px;
            }
            
            .table th,
            .table td {
                padding: 2px 3px;
                font-size: 7px;
            }
            
            .vertical-header {
                font-size: 5px;
            }
            
            h1 {
                font-size: 14px;
            }
            
            h2 {
                font-size: 12px;
            }
        }
        
        /* Spezielle Rang-Highlights */
        .rank-1 {
            background-color: #ffd700 !important;
            color: #333;
        }
        
        .rank-2 {
            background-color: #c0c0c0 !important;
            color: #333;
        }
        
        .rank-3 {
            background-color: #cd7f32 !important;
            color: #333;
        }
        
        /* Info-Box */
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 9px;
        }
        
        .info-box strong {
            color: #1976D2;
        }
        
        /* Tabellen-Wrapper für besseres Layout */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }
        
        /* Kleine Anpassungen für bessere Lesbarkeit */
        .bold {
            font-weight: bold;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-muted {
            color: #6c757d;
        }
        
        /* Kompakte Darstellung für viele Spalten */
        .compact-table .table th,
        .compact-table .table td {
            padding: 1px 2px;
            font-size: 6px;
        }
        
        /* Legende */
        .legend {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            font-size: 7px;
        }
        
        .legend-item {
            margin-bottom: 3px;
            display: block;
        }
        
        .legend-item .struck {
            color: #dc3545;
            text-decoration: line-through;
            font-weight: bold;
        }
            /* Spezielle Rang-Highlights für erste 3 Plätze */
.rank-1 td {
    font-weight: bold !important;
    background-color: #ffd700 !important; /* Gold */
}

.rank-2 td {
    font-weight: bold !important;
    background-color: #c0c0c0 !important; /* Silber */
}

.rank-3 td {
    font-weight: bold !important;
    background-color: #cd7f32 !important; /* Bronze */
}
    </style>
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script(\'
                $font = $fontMetrics->get_font("Arial", "normal");
                $size = 7;
                $pageCount = $pdf->get_page_count();
                $pageNumber = $pdf->get_page_number();
                
                // Footer auf jeder Seite
                $y = $pdf->get_height() - 25;
                
                // Linie
                $pdf->line(15, $y - 5, $pdf->get_width() - 15, $y - 5, array(0.8, 0.8, 0.8), 0.5);
                
                // Linker Text
                $pdf->text(20, $y, "Erstellt am: ' . date('d.m.Y H:i') . ' Uhr", $font, $size);
                
                // Mittlerer Text
                $text = "MSV Wilen - Jahresmeisterschaft";
                $width = $fontMetrics->get_text_width($text, $font, $size);
                $pdf->text(($pdf->get_width() - $width) / 2, $y, $text, $font, $size);
                
                // Rechter Text
                $text = "Seite " . $pageNumber . " von " . $pageCount;
                $width = $fontMetrics->get_text_width($text, $font, $size);
                $pdf->text($pdf->get_width() - $width - 20, $y, $text, $font, $size);
            \');
        }
    </script>
    ';

// Footer - nur für HTML-Vorschau, nicht für PDF
$footer = '</body>
</html>';