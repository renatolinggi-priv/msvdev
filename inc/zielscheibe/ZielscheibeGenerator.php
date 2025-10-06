<?php
// ZielscheibeReport.php - Zielscheiben als PDF

require_once 'PDFGenerator.php';
require_once 'ZielscheibeGeneratorImagick.php';

/**
 * Zielscheibe Report - Generiert PDF mit Zielscheibe
 */
class ZielscheibeReport extends PDFGenerator {
    private $treffer;
    private $schuetzenName;
    
    public function __construct($conn, $year = null, $treffer = [], $schuetzenName = null) {
        parent::__construct($conn, $year);
        $this->treffer = $treffer;
        $this->schuetzenName = $schuetzenName;
    }
    
    public function generate() {
        // Zielscheibe als PNG generieren
        $tempBildDatei = sys_get_temp_dir() . '/zielscheibe_temp_' . time() . '.png';
        
        try {
            $generator = new ZielscheibeGeneratorImagick(1200, 1200);
            $generator->setzeKoordinatenFaktor(1.1);
            $erfolg = $generator->generiereZielscheibe($this->treffer, $tempBildDatei);
            
            if (!$erfolg || !file_exists($tempBildDatei)) {
                $this->outputError("Zielscheibe konnte nicht generiert werden");
                return;
            }
            
            // Bild in Base64 konvertieren (nutzt die Methode aus PDFGenerator)
            $imageData = base64_encode(file_get_contents($tempBildDatei));
            $bildBase64 = 'data:' . mime_content_type($tempBildDatei) . ';base64,' . $imageData;
            
            // HTML für PDF erstellen
            $html = $this->createHTMLHeader('Zielscheibe ' . $this->selectedYear, $this->getCustomStyles());
            
            $titel = 'Zielscheibe';
            if ($this->schuetzenName) {
                $titel .= ' - ' . htmlspecialchars($this->schuetzenName);
            }
            $titel .= ' ' . $this->selectedYear;
            
            $html .= '<h2>' . $titel . '</h2>';
            $html .= '<div style="text-align: center; margin: 20px 0;">';
            $html .= '<img src="' . $bildBase64 . '" style="max-width: 100%; height: auto;" alt="Zielscheibe">';
            $html .= '</div>';
            
            // Statistik-Tabelle
            if (!empty($this->treffer)) {
                $html .= $this->createStatistikTable();
            }
            
            $html .= $this->createHTMLFooter();
            
            // PDF generieren
            $filename = 'Zielscheibe';
            if ($this->schuetzenName) {
                $filename .= '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $this->schuetzenName);
            }
            
            $pdfPath = $this->generatePDF($html, $filename);
            
            // Temporäre Bilddatei löschen
            if (file_exists($tempBildDatei)) {
                unlink($tempBildDatei);
            }
            
            $this->outputDownloadLink($pdfPath);
            
        } catch (Exception $e) {
            if (file_exists($tempBildDatei)) {
                unlink($tempBildDatei);
            }
            $this->outputError("Fehler beim Generieren des PDFs: " . $e->getMessage());
        }
    }
    
    private function getCustomStyles() {
        return '
            h2 {
                text-align: center;
                color: #333;
                margin-bottom: 30px;
            }
            .stats-table {
                width: 60%;
                margin: 30px auto;
                border-collapse: collapse;
            }
            .stats-table th,
            .stats-table td {
                padding: 8px 12px;
                border: 1px solid #ddd;
                text-align: center;
            }
            .stats-table th {
                background-color: #f8f9fa;
                font-weight: bold;
            }
            .stats-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .total-row {
                font-weight: bold;
                background-color: #e8f4f8 !important;
            }
        ';
    }
    
    private function createStatistikTable() {
        $html = '<table class="stats-table">';
        $html .= '<thead><tr>';
        $html .= '<th>Schuss Nr.</th>';
        $html .= '<th>Wertung</th>';
        $html .= '<th>100er</th>';
        $html .= '<th>X (mm)</th>';
        $html .= '<th>Y (mm)</th>';
        $html .= '</tr></thead><tbody>';
        
        $totalWertung = 0;
        $max100er = 0;
        
        foreach ($this->treffer as $treffer) {
            $nr = isset($treffer['schuss_nr']) ? $treffer['schuss_nr'] : '?';
            $wert = isset($treffer['wert']) ? $treffer['wert'] : 0;
            $hunderter = isset($treffer['hunderter']) ? $treffer['hunderter'] : 0;
            $x = isset($treffer['x']) ? number_format($treffer['x'], 2) : '-';
            $y = isset($treffer['y']) ? number_format($treffer['y'], 2) : '-';
            
            $totalWertung += $wert;
            if ($hunderter > $max100er) {
                $max100er = $hunderter;
            }
            
            $html .= '<tr>';
            $html .= '<td>' . $nr . '</td>';
            $html .= '<td>' . $wert . '</td>';
            $html .= '<td>' . $hunderter . '</td>';
            $html .= '<td>' . $x . '</td>';
            $html .= '<td>' . $y . '</td>';
            $html .= '</tr>';
        }
        
        // Total-Zeile
        $html .= '<tr class="total-row">';
        $html .= '<td>Total</td>';
        $html .= '<td>' . $totalWertung . '</td>';
        $html .= '<td>' . $max100er . '</td>';
        $html .= '<td colspan="2">-</td>';
        $html .= '</tr>';
        
        $html .= '</tbody></table>';
        
        return $html;
    }
}
?>