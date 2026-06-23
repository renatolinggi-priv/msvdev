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

// Datei-spezifische Layout-Overrides (NACH dem Theme -> gewinnen in der Kaskade).
// Nur echtes Layout für die Querformat-Breittabelle, keine Farben.
$jmrangOverrides = '
    /* JM-Rangliste: Querformat mit vielen rotierten Spalten */
    body { font-size: 11px; }
    .logo { width: 90px; max-width: 90px; }
    .table th, .table td { padding: 5px 2px; }
    .result-col { font-size: 11px; }
    .vertical-header { font-size: 8px; line-height: 1.2; }
    @page { margin-bottom: 50px; }
';

// HTML-Header mit Theme-CSS + Overrides; Footer weiterhin via Dompdf page_script.
$header = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>' . pdf_theme_css() . $jmrangOverrides . '</style>
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script(\'
                $font = $fontMetrics->get_font("Arial", "normal");
                $size = 7;
                $pageCount = $pdf->get_page_count();
                $pageNumber = $pdf->get_page_number();

                // Footer auf jeder Seite
                $y = $pdf->get_height() - 25;

                // Linie (weiches Grau)
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
