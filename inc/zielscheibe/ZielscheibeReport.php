<?php
// ZielscheibeReport.php - Zielscheiben als PDF

require_once 'PDFGenerator.php';
require_once 'ZielscheibeGeneratorImagick.php';
require_once 'ZielscheibeGeneratorKeiler.php';

/**
 * Zielscheibe Report - Generiert PDF mit Zielscheibe
 */
class ZielscheibeReport extends PDFGenerator {
    private $alleStiche; // Array von Stichen
    private $schuetzenName;
    
    public function __construct($conn, $year = null, $alleStiche = [], $schuetzenName = null) {
        parent::__construct($conn, $year);
        
        error_log("=== ZielscheibeReport Konstruktor ===");
        error_log("alleStiche (Input): " . print_r($alleStiche, true));
        error_log("Jahr: " . ($year ?? 'NULL'));
        error_log("Sch\u00fctzenName: " . ($schuetzenName ?? 'NULL'));
        
        // Wenn altes Format (treffer Array), konvertiere zu neuem Format
        if (!empty($alleStiche) && !isset($alleStiche[0]['programmNummer'])) {
            error_log("ALTES FORMAT erkannt - konvertiere");
            // Altes Format: einfaches treffer Array
            $this->alleStiche = [[
                'programmNummer' => null,
                'stichName' => null,
                'schuesse' => $alleStiche
            ]];
        } else {
            error_log("NEUES FORMAT - direkt \u00fcbernehmen");
            $this->alleStiche = $alleStiche;
        }
        
        error_log("this->alleStiche (nach Konstruktor): " . print_r($this->alleStiche, true));
        
        $this->schuetzenName = $schuetzenName;
    }
    
    public function generate() {
        if (empty($this->alleStiche)) {
            $this->outputError("Keine Stiche zum Anzeigen vorhanden");
            return;
        }
        
        // Prüfe ob mindestens ein Stich Schüsse enthält
        $hatSchuesse = false;
        foreach ($this->alleStiche as $stich) {
            if (!empty($stich['schuesse'])) {
                $hatSchuesse = true;
                break;
            }
        }
        
        if (!$hatSchuesse) {
            $this->outputError("Keine Schüsse in den Stichen vorhanden");
            return;
        }
        
        try {
            // HTML für PDF erstellen
            $html = $this->createCustomHTMLHeader($this->selectedYear, $this->getCustomStyles());
            
            $titel = 'Zielscheibe';
            if ($this->schuetzenName) {
                $titel .= ' - ' . htmlspecialchars($this->schuetzenName);
            }
            $titel .= ' ' . $this->selectedYear;
            
            $html .= '<h2>' . $titel . '</h2>';
            
            // Durch alle Stiche loopen
            foreach ($this->alleStiche as $stichIndex => $stich) {
                $programmNummer = $stich['programmNummer'];
                $treffer = $stich['schuesse'];
                
                if (empty($treffer)) {
                    continue; // Überspringe leere Stiche
                }
                
                // Stich-Name aus DB holen falls Programmnummer vorhanden
                $stichName = null;
                if ($programmNummer) {
                    $stichName = $this->getStichNameByProgrammNummer($programmNummer);
                    if (!$stichName && isset($stich['stichName'])) {
                        $stichName = $stich['stichName'];
                    }
                }
                
                // Prüfe ob Keiler-Scheibe verwendet werden soll
                $istKeilerStich = $this->istKeilerProgramm($programmNummer);
                
                error_log("Stich $stichIndex: Programm=$programmNummer, Name=$stichName, istKeiler=" . ($istKeilerStich ? 'JA' : 'NEIN'));
                
                // 🚀 Option 2+3: JPEG + Memory-Optimierung (keine Temp-Datei mehr!)
                if ($istKeilerStich) {
                    // Keiler-Scheibe generieren - QUADRATISCH 1200x1200
                    $keilerBildPfad = __DIR__ . '/keiler_scheibe.jpg';
                    
                    if (!file_exists($keilerBildPfad)) {
                        error_log("WARNUNG: Keiler-Bild nicht gefunden: " . $keilerBildPfad);
                        continue; // Überspringe wenn Keiler-Bild fehlt
                    }
                    
                    $generator = new ZielscheibeGeneratorKeiler(1200, 1200);
                    $generator->setzeSkalierungsfaktor(1.6);
                    $result = $generator->generiereZielscheibeBlob($treffer, $keilerBildPfad);
                    
                } else {
                    // Normale Ringscheibe generieren - QUADRATISCH 1200x1200
                    $generator = new ZielscheibeGeneratorImagick(1200, 1200);
                    $generator->setzeKoordinatenFaktor(1.1);
                    $result = $generator->generiereZielscheibeBlob($treffer, false);
                }
                
                if (!$result['success']) {
                    error_log("FEHLER: Zielscheiben-Generierung fehlgeschlagen für Stich " . $stichIndex);
                    continue; // Überspringe wenn Generierung fehlschlägt
                }
                
                // Direkt aus Memory - kein Temp-File mehr!
                $imageData = base64_encode($result['blob']);
                $bildBase64 = 'data:' . $result['mime'] . ';base64,' . $imageData;
                
                // Layout: TABELLE LINKS, BILD RECHTS nebeneinander
                $html .= '<table style="width: 100%; border: none; border-collapse: collapse; margin: 10px 0;"><tr>';
                
                // Linke Spalte: STICH-NAME + STATISTIK-TABELLE
                $html .= '<td style="width: 40%; vertical-align: top; border: none; padding-right: 15px;">';
                
                // Stich-Name über der Tabelle (mit Passe-Nummer falls vorhanden)
                if ($stichName) {
                    $vollstichName = htmlspecialchars($stichName);
                    
                    // Passe-Nummer anhängen falls vorhanden
                    if (isset($stich['passe']) && $stich['passe'] > 0) {
                        $vollstichName .= ' - ' . $stich['passe'] . '. Passe';
                    }
                    
                    $html .= '<h4 style="color: #007bff; margin: 0 0 8px 0; padding: 0; font-size: 13px; font-weight: bold; text-align: left;">' . $vollstichName . '</h4>';
                }
                
                $html .= $this->createStatistikTable($treffer);
                $html .= '</td>';
                
                // Rechte Spalte: BILD
                // Im PDF: Keiler-Scheibe rechteckig anzeigen (breiter), normale Scheibe quadratisch
                $bildStyle = $istKeilerStich 
                    ? 'width: 500px; height: auto;' // Rechteckig für Keiler im PDF
                    : 'width: 400px; height: 400px;'; // Quadratisch für normale Scheibe
                
                $html .= '<td style="width: 60%; vertical-align: top; border: none; text-align: center;">';
                $html .= '<img src="' . $bildBase64 . '" style="' . $bildStyle . '" alt="Zielscheibe">';
                $html .= '</td>';
                
                $html .= '</tr></table>';
                
                // Horizontale Trennlinie nach jedem Stich
                $html .= '<hr style="border: none; border-top: 2px solid #333; margin: 20px 0;">';
                
                // Kein Temp-File mehr zum Löschen! 🚀
            }
            
            $html .= $this->createHTMLFooter();
            
            // PDF generieren
            $filename = 'Zielscheibe';
            if ($this->schuetzenName) {
                $filename .= '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $this->schuetzenName);
            }
            
            $pdfPath = $this->generatePDF($html, $filename, 'portrait');
            $this->outputDownloadLink($pdfPath);
            
        } catch (Exception $e) {
            $this->outputError("Fehler beim Generieren des PDFs: " . $e->getMessage());
        }
    }
    
    /**
     * Prüft ob eine Programmnummer ein Keiler-Programm ist
     * Keiler-Programme: 526 = Schwinistich
     */
    private function istKeilerProgramm($programmNummer) {
        if (empty($programmNummer)) {
            return false;
        }
        
        // Programmnummer als String behandeln
        $progNr = trim((string)$programmNummer);
        
        // Liste der Keiler-Programme
        $keilerProgramme = ['526']; // Schwinistich
        
        return in_array($progNr, $keilerProgramme);
    }
    
    /**
     * Eigener Header mit kleinerem Logo
     */
    private function createCustomHTMLHeader($year, $customStyles) {
        $styles = $this->getParentDefaultStyles() . $customStyles;
        
        return '<!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>' . $styles . '</style>
            <title>Zielscheibe ' . $year . '</title>
        </head>
        <body>
        <div class="container">
            <div class="header">
                <img src="' . $this->logoBase64 . '" alt="Logo" style="width:60px; height:auto;">
            </div>';
    }
    
    /**
     * Hole die Standard-Styles von Parent
     */
    private function getParentDefaultStyles() {
        return '
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        .container {
            margin: 0;
            padding: 0;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            text-align: center;
            font-size: 9px;
            border-top: 1px;
            background-color: #ffffff;
        }
        .footer hr {
            border: none;
            border-top: 1px solid #000;
            margin: 0;
        }';
    }
    
    private function getCustomStyles() {
        return '
            @page {
                margin: 8mm 8mm;
            }
            h2 {
                text-align: center;
                color: #333;
                margin: 2px 0 5px 0;
                font-size: 11px;
            }
            .stats-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 9px;
            }
            .stats-table th,
            .stats-table td {
                padding: 3px 4px;
                border: 1px solid #ddd;
                text-align: center;
            }
            .stats-table th {
                background-color: #343a40;
                color: #fff;
                font-weight: bold;
                font-size: 9px;
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
    
    private function createStatistikTable($treffer) {
        $html = '<table class="stats-table">';
        $html .= '<thead><tr>';
        $html .= '<th>Schuss Nr.</th>';
        $html .= '<th>Wertung</th>';
        $html .= '<th>100er</th>';
        $html .= '</tr></thead><tbody>';
        
        $totalWertung = 0;
        $max100er = 0;
        
        foreach ($treffer as $schuss) {
            $nr = isset($schuss['schuss_nr']) ? $schuss['schuss_nr'] : '?';
            $wert = isset($schuss['wert']) ? $schuss['wert'] : 0;
            $hunderter = isset($schuss['hunderter']) ? $schuss['hunderter'] : 0;
            
            $totalWertung += $wert;
            if ($hunderter > $max100er) {
                $max100er = $hunderter;
            }
            
            $html .= '<tr>';
            $html .= '<td>' . $nr . '</td>';
            $html .= '<td>' . $wert . '</td>';
            $html .= '<td>' . $hunderter . '</td>';
            $html .= '</tr>';
        }
        
        // Total-Zeile
        $html .= '<tr class="total-row">';
        $html .= '<td>Total</td>';
        $html .= '<td>' . $totalWertung . '</td>';
        $html .= '<td>' . $max100er . '</td>';
        $html .= '</tr>';
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Holt den Stich-Namen aus der DB basierend auf der Programmnummer
     * Sucht in allen nummer1, nummer2, nummer3 Feldern
     */
    private function getStichNameByProgrammNummer($programmNummer) {
        if (empty($programmNummer) || !$this->conn) {
            error_log("DEBUG getStichName: programmNummer empty oder conn NULL");
            return null;
        }
        
        error_log("DEBUG getStichName: Suche nach programmNummer = " . $programmNummer);
        
        $stmt = $this->conn->prepare(
            "SELECT stich FROM interne_stichdefinition 
             WHERE nummer1 = ? OR nummer2 = ? OR nummer3 = ? 
             LIMIT 1"
        );
        
        if ($stmt) {
            $stmt->bind_param('sss', $programmNummer, $programmNummer, $programmNummer);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                error_log("DEBUG getStichName: Gefunden = " . $row['stich']);
                return $row['stich'];
            } else {
                error_log("DEBUG getStichName: Keine Zeile gefunden");
            }
            
            $stmt->close();
        } else {
            error_log("DEBUG getStichName: prepare() fehlgeschlagen");
        }
        
        return null;
    }
}
?>
