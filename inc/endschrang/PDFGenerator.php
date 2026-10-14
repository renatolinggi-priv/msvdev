<?php
// PDFGenerator.php - Zentrale Klasse für alle PDF-Generierungen

require_once '../vendor/autoload.php';

// Nur includen wenn noch nicht geladen
if (!defined('DB_HOST')) {
    include_once '../config.php';
}
include_once 'function_date.php';

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../pdf/pdf_theme.php';

class PDFGenerator {
    protected $conn;  // Changed from private to protected
    protected $selectedYear;  // Changed from private to protected
    protected $dompdf;  // Changed from private to protected
    protected $logoBase64;  // Changed from private to protected
    protected $useConfigPdf = false;

    // Leeres Jahr: Zeilen aller Abfragen über executeQuery/executePreparedQuery. Kam keine einzige Zeile,
    // liefert generatePDF() statt eines leeren PDFs einen Hinweis (msvAusgabeLeer). Berichte, die auch
    // direkt über $this->conn abfragen, setzen $leerPruefen = false (sonst würde fälschlich «leer» gemeldet).
    protected $zeilenGelesen = 0;
    protected $leerPruefen = true;

    // Sammelmodus (EinzelwettbewerbeReport): Ist das ein Array, schreibt generatePDF() keine Datei, sondern legt
    // das HTML des Berichts hier ab (Berichte ohne Zeilen gar nicht); outputDownloadLink() gibt nichts aus.
    public static $sammlung = null;

    // Layout-Overrides der Basisklasse (Farben/Tabellen/Footer kommen aus pdf_theme.php)
    private $defaultStyles = '
        body { font-size: 10px; }
        .fixed-width { width: 10%; }
        .name-width { width: 20%; }
        .total-width { width: 15%; }
    ';
    
    public function __construct($conn, $year = null) {
        ob_start(); // Ausgabepufferung starten
        
        $this->conn = $conn;
        $this->selectedYear = $year ?: date('Y');
        
        // Verbindung prüfen
        if (!$this->conn || $this->conn->connect_error) {
            $this->outputError("Datenbankverbindung fehlgeschlagen");
            exit;
        }
        
        // Prüfen ob config_pdf.php existiert
        if (file_exists('config_pdf.php')) {
            include_once 'config_pdf.php';
            $this->useConfigPdf = true;
        }
        
        // Logo konvertieren
        $logoPath = 'dat/MSVWilen_Logo.jpg';
        if (!file_exists($logoPath)) {
            $this->outputError("Logo-Datei nicht gefunden: $logoPath");
            exit;
        }
        
        // Verwende imgToBase64 aus config_pdf.php wenn vorhanden
        if (function_exists('imgToBase64')) {
            $this->logoBase64 = imgToBase64($logoPath);
        } else {
            $this->logoBase64 = $this->imgToBase64($logoPath);
        }
        
        // Dompdf initialisieren
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $this->dompdf = new Dompdf($options);
    }
    
    /**
     * Konvertiert ein Bild in Base64
     * Nur verwenden wenn imgToBase64 nicht aus config_pdf.php verfügbar ist
     */
    protected function imgToBase64($imgPath) {
        if (function_exists('imgToBase64')) {
            return imgToBase64($imgPath);
        }
        $imageData = base64_encode(file_get_contents($imgPath));
        $src = 'data:' . mime_content_type($imgPath) . ';base64,' . $imageData;
        return $src;
    }
    
    /**
     * Erstellt den HTML-Header mit Logo und Titel
     */
    protected function createHTMLHeader($title, $customStyles = '', $bodyFontSize = 10) {
        $styles = pdf_theme_css() . $this->defaultStyles . $customStyles;
        
        // Überschreibe die body font-size wenn angegeben
        if ($bodyFontSize != 10) {
            $styles = str_replace('font-size: 10px;', 'font-size: ' . $bodyFontSize . 'px;', $styles);
        }
        
        return '<!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>' . $styles . '</style>
            <title>' . $title . '</title>
        </head>
        <body>
        <div class="container">
            <div class="header">
                <img src="' . $this->logoBase64 . '" alt="Logo" style="width:120px; height:auto;">
            </div>';
    }
    
    // Ranglisten-Layout wie im Absendenbuch (ranglisteHeader): Fusszeile mit Seitenzahl nach dem Rendern
    protected $ranglisteLayout = false;
    protected $fussText = '';

    /**
     * HTML-Kopf einer Rangliste im Layout des Absendenbuchs: Titel links, Logo rechts.
     */
    protected function ranglisteHeader($titel, $untertitel = '') {
        $this->ranglisteLayout = true;
        $this->fussText = $titel;
        return '<!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <style>' . pdf_theme_css() . pdf_rangliste_css() . '</style>
            <title>' . htmlspecialchars($titel) . '</title>
        </head>
        <body>
        <div class="container">' . pdf_rangliste_kopf($titel, $untertitel);
    }

    /**
     * Zeilenanfang einer Rangliste mit Rangzelle: die ersten drei fett, vor Rang 4 etwas Abstand.
     */
    protected function zeileAnfang($rang) {
        return pdf_rangliste_zeile($rang) . '<td class="rang">' . $rang . '.</td>';
    }

    /**
     * Erstellt den HTML-Footer
     */
    protected function createHTMLFooter() {
        return '</div>
        <div class="footer">
            <hr>
            <p>' . getactdate() . '</p>
        </div>
        </body>
        </html>';
    }
    
    /**
     * Erstellt eine Standard-Ranglisten-Tabelle
     */
    protected function createRankingTable($data, $columns, $showTop3 = true, $additionalClasses = '') {
        if (empty($data)) {
            return '<p>Keine Ergebnisse gefunden.</p>';
        }
        
        $html = '<table class="table ' . $additionalClasses . '">';
        $html .= '<thead><tr>';
        
        // Header erstellen
        foreach ($columns as $col) {
            $align = isset($col['align']) ? $col['align'] : 'left';
            $colspan = isset($col['colspan']) ? ' colspan="' . $col['colspan'] . '"' : '';
            $html .= '<th align="' . $align . '"' . $colspan . '>' . $col['label'] . '</th>';
        }
        
        $html .= '</tr></thead><tbody>';
        
        // Daten ausgeben
        $rang = 1;
        foreach ($data as $row) {
            $bold = ($showTop3 && $rang <= 3) ? 'class="bold"' : '';
            
            $html .= '<tr>';
            foreach ($columns as $col) {
                if ($col['field'] == 'rang') {
                    $html .= "<td align=\"left\" $bold>$rang.</td>";
                } else {
                    $value = isset($row[$col['field']]) ? $row[$col['field']] : '';
                    $align = isset($col['align']) ? $col['align'] : 'left';
                    $html .= "<td align=\"$align\" $bold>$value</td>";
                }
            }
            $html .= '</tr>';
            
            // Trennlinie nach Rang 3
            if ($showTop3 && $rang == 3) {
                $html .= '<tr>';
                foreach ($columns as $col) {
                    $html .= '<td align="left"></td>';
                }
                $html .= '</tr>';
            }
            
            $rang++;
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * Führt eine SQL-Abfrage aus und gibt das Ergebnis als Array zurück
     */
    protected function executeQuery($sql) {
        $result = $this->conn->query($sql);
        $data = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        $this->zeilenGelesen += count($data);
        return $data;
    }

    /**
     * Führt eine SQL-Abfrage mit Prepared Statement aus und gibt das Ergebnis als Array zurück
     */
    protected function executePreparedQuery($sql, $types, ...$params) {
        $stmt = $this->conn->prepare($sql);
        if ($types && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        $stmt->close();
        $this->zeilenGelesen += count($data);
        return $data;
    }
    
    /**
     * Generiert das PDF und speichert es
     */
    protected function generatePDF($html, $filename, $orientation = 'portrait') {
        if (is_array(self::$sammlung)) {
            ob_end_clean();
            if (!$this->leerPruefen || $this->zeilenGelesen > 0) {
                self::$sammlung[] = $html;
            }
            return null;
        }
        // Keine einzige Zeile gelesen: kein leeres PDF (und kein leeres Blatt im Direktdruck), sondern ein Hinweis
        if ($this->leerPruefen && $this->zeilenGelesen === 0 && function_exists('msvAusgabeLeer')) {
            msvAusgabeLeer('Für ' . $this->selectedYear . ' gibt es für diese Rangliste noch keine Resultate.');
        }
        // Ausrichtung aus dem Druckprofil (?orientation=portrait|landscape) übersteuert den Report-Default
        require_once __DIR__ . '/../pdf/pdf_orientation.inc.php';
        $orientation = pdfOrientationParam($orientation);
        if ($this->ranglisteLayout) {
            // fmtNoTrailingZero hängt an echte Nullen ein ZWNJ an; die PDF-Grundschrift kennt es nicht («0?»)
            $html = str_replace("\u{200C}", '', $html);
        }
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', $orientation);
        $this->dompdf->render();
        if ($this->ranglisteLayout) {
            pdf_seitenfuss($this->dompdf, $this->fussText);
        }

        $pdfOutput = $this->dompdf->output();
        $date = new DateTime();
        $pdfFilePath = 'dat/' . $filename . '_' . $date->format('Y-m-d_H-i-s') . '.pdf';
        
        file_put_contents($pdfFilePath, $pdfOutput);
        
        ob_end_clean();
        
        return $pdfFilePath;
    }
    
    /**
     * Gibt den Download-Link als JSON aus
     */
    protected function outputDownloadLink($pdfFilePath) {
        if (is_array(self::$sammlung)) {
            return;
        }
        header('Content-Type: application/json');
        echo json_encode(array('pdf_link' => $pdfFilePath));
    }
    
    /**
     * Gibt eine Fehlermeldung als JSON aus
     */
    protected function outputError($message) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(array('message' => $message));
    }
    
    /**
     * Hilfsmethode für Zabig-Punkte-Berechnung
     */
    protected function getZabigPoints($value) {
        if ($value >= 91) return 10;
        if ($value >= 81) return 9;
        if ($value >= 71) return 8;
        if ($value >= 61) return 7;
        if ($value >= 51) return 6;
        if ($value >= 41) return 5;
        if ($value >= 31) return 4;
        if ($value >= 21) return 3;
        if ($value >= 11) return 2;
        if ($value >= 1) return 1;
        return 0;
    }
    
    /**
     * Hilfsmethode für sortierte Schusswerte
     */
    protected function getSortedShots($shots) {
        $filtered = array_filter($shots, function($v) { return $v !== null; });
        rsort($filtered);
        return $filtered;
    }
}
?>