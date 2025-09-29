
<?php
// PDFReports.php - Alle PDF-Report-Klassen für das Wanderpreis-Modul

require_once 'PDFGenerator.php';

/**
 * Wanderpreise Jahres-Report
 * Zeigt alle Wanderpreise mit aktuellen Gewinnern und Statistiken
 */
class WanderpreiseJahresReport extends PDFGenerator {
    
    private $hersteller_filter = null;
    
    public function __construct($conn, $year = null, $hersteller = null) {
        parent::__construct($conn, $year);
        $this->hersteller_filter = $hersteller;
    }
    
    /**
     * Generiert den kompletten Wanderpreise-Jahresbericht
     */
    public function generate() {
        $title = 'Wanderpreise Übersicht ' . $this->selectedYear;
        if ($this->hersteller_filter) {
            $title .= ' - ' . $this->hersteller_filter;
        }
        
        // Spezialbehandlung für Schnitzerei Heinz Schild (gleich wie Akura)
        if ($this->hersteller_filter === 'Schnitzerei Heinz Schild') {
            $this->generateSchnitzereiReport();
            return;
        }
        
        // Custom CSS für Akura-ähnlichen Stil
        $customStyles = $this->getCustomStyles();
        
        $html = $this->createHTMLHeader($title, $customStyles, 11);
        $html .= '<h1>' . $title . '</h1>';
        $html .= '<h2>MSV Wilen</h2>';
        
        // Alle Wanderpreise mit Gewinnerdaten holen
        $wanderpreiseData = $this->getWanderpreiseOverview();
        
        if (empty($wanderpreiseData)) {
            $html .= '<p>Keine Wanderpreise für das Jahr ' . $this->selectedYear . ' gefunden.</p>';
        } else {
            $html .= $this->createWanderpreiseTable($wanderpreiseData);
        }
        
        // Hinweis falls Filter aktiv
        if ($this->hersteller_filter) {
            $html .= '<div class="hinweis-box">';
            $html .= '<strong>Hinweis:</strong> Diese Aufstellung zeigt nur Wanderpreise vom Hersteller "' . htmlspecialchars($this->hersteller_filter) . '".';
            $html .= '</div>';
        }
        
        // Legende hinzufügen
        $html .= '<div class="legende-box">';
        $html .= '<p><strong>Legende:</strong></p>';
        $html .= '<p>• <strong>Seine Gewinne:</strong> Anzahl Siege des aktuellen Gewinners für diesen Wanderpreis</p>';
        $html .= '<p>• <strong>Gesamt Gewinner:</strong> Anzahl verschiedener Personen / Anzahl Jahre mit Gewinner</p>';
        $html .= '<p>• <strong>Definitiv:</strong> Wanderpreis gehört dem Gewinner nach X Siegen definitiv</p>';
        $html .= '</div>';
        
        $html .= $this->createHTMLFooter();
        
        // PDF generieren
        $filename = 'WanderpreiseJahresreport_' . $this->selectedYear;
        if ($this->hersteller_filter) {
            $filename .= '_' . str_replace(' ', '_', $this->hersteller_filter);
        }
        $pdfPath = $this->generatePDF($html, $filename);
        $this->outputDownloadLink($pdfPath);
    }
    
    /**
     * Generiert den Schnitzerei-Report im Akura-Stil
     */
    private function generateSchnitzereiReport() {
        // Berechne das Datum (eine Woche früher als Absenden)
        $datum = $this->calculateSchnitzereiDatum();
        
        // Hole alle Wanderpreise von Schnitzerei Heinz Schild mit Gewinnern für das aktuelle Jahr
        $sql = "SELECT 
                    w.id,
                    w.bezeichnung,
                    wg.jahr,
                    CONCAT(m.Name, ' ', m.Vorname) as gewinner_name
                FROM wanderpreise w
                LEFT JOIN wanderpreise_gewinner wg ON w.id = wg.wanderpreis_id AND wg.jahr = ?
                LEFT JOIN mitglieder m ON wg.gewinner_id = m.ID
                WHERE w.hersteller = 'Schnitzerei Heinz Schild'
                ORDER BY w.bezeichnung ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->selectedYear);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Custom CSS für Akura-ähnlichen Stil
        $customStyles = '
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #003366;
                padding-bottom: 15px;
            }
            h1 {
                color: #003366;
                font-size: 18pt;
                margin: 10px 0;
                font-weight: bold;
            }
            h2 {
                color: #003366;
                font-size: 16pt;
                margin: 5px 0;
                text-align: center;
            }
            .table th {
                background-color: #003366;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: bold;
            }
            .table td {
                padding: 8px 10px;
                border-bottom: 1px solid #ddd;
            }
            .table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .wanderpreis-name {
                font-weight: bold;
                color: #003366;
            }
            .gravur-info {
                color: #666;
            }
            .no-winner {
                color: #999;
                font-style: italic;
            }
        ';
        
        // HTML erstellen
        $html = $this->createHTMLHeader('Schnitzerei Wanderpreise bis: ' . $datum, $customStyles, 11);
        
        $html .= '<h1>Schnitzerei Wanderpreise bis: ' . $datum . '</h1>';
        $html .= '<h2>MSV Wilen</h2>';
        
        $html .= '<table class="table">';
        $html .= '<thead><tr>';
        $html .= '<th style="width: 40%;">Wanderpreis</th>';
        $html .= '<th style="width: 60%;">Information</th>';
        $html .= '</tr></thead><tbody>';
        
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $count++;
            $info_text = '';
            
            if ($row['gewinner_name'] && $row['jahr']) {
                $info_text = '<span class="gravur-info">' . 
                              $row['jahr'] . ' ' . htmlspecialchars($row['gewinner_name']);
            } else {
                $info_text = '<span class="no-winner">Noch kein Gewinner für ' . $this->selectedYear . '</span>';
            }
            
            $html .= '<tr>';
            $html .= '<td class="wanderpreis-name">' . htmlspecialchars($row['bezeichnung']) . '</td>';
            $html .= '<td>' . $info_text . '</td>';
            $html .= '</tr>';
        }
        
        if ($count == 0) {
            $html .= '<tr>';
            $html .= '<td colspan="2" style="text-align: center; padding: 20px; color: #999;">';
            $html .= 'Keine Wanderpreise von Schnitzerei Heinz Schild gefunden';
            $html .= '</td></tr>';
        }
        
        $html .= '</tbody></table>';
        
        $html .= $this->createHTMLFooter();
        
        $stmt->close();
        
        // PDF generieren
        $pdfPath = $this->generatePDF($html, 'Schnitzerei_Wanderpreise_' . $this->selectedYear);
        $this->outputDownloadLink($pdfPath);
    }
    
    /**
     * Berechnet das Datum für Schnitzerei (eine Woche früher als Absenden)
     */
    private function calculateSchnitzereiDatum() {
        // Standard-Datum falls nicht gefunden
        $datum = "15. November " . $this->selectedYear;
        
        // Hole das Absenden-Datum aus JMDefinition
        $sql = "SELECT Schiesstage FROM JMDefinition 
                WHERE Bezeichnung = 'Absenden' 
                AND year = ?
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->selectedYear);
        $stmt->execute();
        $result = $stmt->get_result();
        $absenden_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($absenden_data && $absenden_data['Schiesstage']) {
            // Parse das Datum aus dem Schiesstage-Feld
            if (preg_match('/(\d{1,2})\.\s*(\w+)/', $absenden_data['Schiesstage'], $matches)) {
                $tag = intval($matches[1]);
                $monat = $matches[2];
                
                $monate = [
                    'Januar' => 1, 'Februar' => 2, 'März' => 3, 'April' => 4,
                    'Mai' => 5, 'Juni' => 6, 'Juli' => 7, 'August' => 8,
                    'September' => 9, 'Oktober' => 10, 'November' => 11, 'Dezember' => 12
                ];
                
                if (isset($monate[$monat])) {
                    $timestamp = mktime(0, 0, 0, $monate[$monat], $tag, $this->selectedYear);
                    // Eine Woche früher
                    $timestamp = $timestamp - (7 * 24 * 60 * 60);
                    $datum = date('j', $timestamp) . '. ' . $monat . ' ' . $this->selectedYear;
                }
            }
        }
        
        return $datum;
    }
    
    /**
     * Holt alle Wanderpreise mit Gewinner-Informationen
     */
    private function getWanderpreiseOverview() {
        $sql = "
            SELECT 
                w.id,
                w.bezeichnung,
                w.beschreibung,
                w.beschaffung_datum,
                w.min_anzahl_gewinne,
                w.hersteller,
                
                -- Aktueller Gewinner des angegebenen Jahres
                wg_aktuell.gewinner_id as aktueller_gewinner_id,
                CONCAT(m_aktuell.Vorname, ' ', m_aktuell.Name) as aktueller_gewinner_name,
                wg_aktuell.rang as aktueller_rang,
                wg_aktuell.resultat as aktuelles_resultat,
                wg_aktuell.ist_definitiv,
                wg_aktuell.anzahl_gewinne as aktuelle_anzahl_gewinne,
                
                -- Gesamtstatistiken für diesen Wanderpreis
                COUNT(DISTINCT wg_alle.gewinner_id) as anzahl_verschiedene_gewinner,
                COUNT(wg_alle.id) as anzahl_gewinne_total,
                MIN(wg_alle.jahr) as erstes_jahr,
                MAX(wg_alle.jahr) as letztes_jahr
                
            FROM wanderpreise w
            LEFT JOIN wanderpreise_gewinner wg_aktuell ON (
                w.id = wg_aktuell.wanderpreis_id 
                AND wg_aktuell.jahr = ?
            )
            LEFT JOIN mitglieder m_aktuell ON wg_aktuell.gewinner_id = m_aktuell.ID
            LEFT JOIN wanderpreise_gewinner wg_alle ON w.id = wg_alle.wanderpreis_id";
            
        // Hersteller-Filter hinzufügen wenn gesetzt
        if ($this->hersteller_filter) {
            $sql .= " WHERE w.hersteller = ?";
        }
            
        $sql .= "
            GROUP BY w.id, wg_aktuell.id, m_aktuell.ID
            ORDER BY w.bezeichnung ASC
        ";
        
        $stmt = $this->conn->prepare($sql);
        if ($this->hersteller_filter) {
            $stmt->bind_param('is', $this->selectedYear, $this->hersteller_filter);
        } else {
            $stmt->bind_param('i', $this->selectedYear);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    /**
     * Erstellt die HTML-Tabelle für die Wanderpreise-Übersicht
     */
    private function createWanderpreiseTable($data) {
        $html = '<table class="table">';
        $html .= '<thead>
                    <tr>
                        <th style="width: 20%;">Wanderpreis</th>
                        <th style="width: 15%;">Hersteller</th>
                        <th style="width: 20%;">Aktueller Gewinner ' . $this->selectedYear . '</th>
                        <th style="width: 15%;">Rang/Resultat</th>
                        <th style="width: 10%;">Seine Gewinne</th>
                        <th style="width: 10%;">Gesamt Gewinner</th>
                        <th style="width: 10%;">Status</th>
                    </tr>
                  </thead>
                  <tbody>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            
            // Wanderpreis Name + Beschreibung
            $html .= '<td class="wanderpreis-name">';
            $html .= '<strong>' . htmlspecialchars($row['bezeichnung']) . '</strong>';
            if (!empty($row['beschreibung'])) {
                $html .= '<br><small>' . htmlspecialchars($row['beschreibung']) . '</small>';
            }
            $html .= '<br><small>Seit ' . $row['beschaffung_datum'] . '</small>';
            $html .= '</td>';
            
            // Hersteller
            $html .= '<td>';
            if (!empty($row['hersteller'])) {
                $html .= '<strong>' . htmlspecialchars($row['hersteller']) . '</strong>';
            } else {
                $html .= '<em>Nicht angegeben</em>';
            }
            $html .= '</td>';
            
            // Aktueller Gewinner
            $html .= '<td>';
            if (!empty($row['aktueller_gewinner_name'])) {
                $html .= htmlspecialchars($row['aktueller_gewinner_name']);
            } else {
                $html .= '<em>Kein Gewinner ' . $this->selectedYear . '</em>';
            }
            $html .= '</td>';
            
            // Rang/Resultat
            $html .= '<td class="text-center">';
            if (!empty($row['aktueller_rang'])) {
                $html .= htmlspecialchars($row['aktueller_rang']);
            } elseif (!empty($row['aktuelles_resultat'])) {
                $html .= htmlspecialchars($row['aktuelles_resultat']);
            } else {
                $html .= '-';
            }
            $html .= '</td>';
            
            // Anzahl Gewinne des aktuellen Gewinners
            $html .= '<td class="text-center">';
            if (!empty($row['aktuelle_anzahl_gewinne'])) {
                $html .= $row['aktuelle_anzahl_gewinne'] . 'x';
                
                // Zeige min_anzahl_gewinne für Kontext
                if ($row['min_anzahl_gewinne'] > 0) {
                    $html .= '<br><small>von ' . $row['min_anzahl_gewinne'] . '</small>';
                }
            } else {
                $html .= '-';
            }
            $html .= '</td>';
            
            // Gesamtstatistiken
            $html .= '<td class="text-center">';
            $html .= $row['anzahl_verschiedene_gewinner'] . ' Pers.';
            $html .= '<br><small>' . $row['anzahl_gewinne_total'] . ' Jahre</small>';
            
            // Zeitraum wenn verfügbar
            if (!empty($row['erstes_jahr']) && !empty($row['letztes_jahr'])) {
                if ($row['erstes_jahr'] == $row['letztes_jahr']) {
                    $html .= '<br><small>(' . $row['erstes_jahr'] . ')</small>';
                } else {
                    $html .= '<br><small>(' . $row['erstes_jahr'] . '-' . $row['letztes_jahr'] . ')</small>';
                }
            }
            $html .= '</td>';
            
            // Status
            $html .= '<td class="text-center">';
            if (!empty($row['aktueller_gewinner_id'])) {
                if ($row['ist_definitiv']) {
                    $html .= '<strong class="status-definitiv">Definitiv</strong>';
                } else {
                    $html .= '<span class="status-wandernd">Wandert noch</span>';
                    
                    // Zeige Fortschritt
                    if ($row['min_anzahl_gewinne'] > 0 && $row['aktuelle_anzahl_gewinne'] > 0) {
                        $progress = $row['aktuelle_anzahl_gewinne'] . '/' . $row['min_anzahl_gewinne'];
                        $html .= '<br><small>(' . $progress . ')</small>';
                    }
                }
            } else {
                $html .= '-';
            }
            $html .= '</td>';
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Gibt das benutzerdefinierte CSS für den Akura-ähnlichen Stil zurück
     */
    private function getCustomStyles() {
        return '
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #003366;
                padding-bottom: 15px;
            }
            h1 {
                color: #003366;
                font-size: 18pt;
                margin: 10px 0;
                font-weight: bold;
            }
            h2 {
                color: #003366;
                font-size: 16pt;
                margin: 5px 0;
                text-align: center;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .table th {
                background-color: #003366;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: bold;
            }
            .table td {
                padding: 8px 10px;
                border-bottom: 1px solid #ddd;
            }
            .table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .wanderpreis-name {
                font-weight: bold;
                color: #003366;
            }
            .text-center {
                text-align: center;
            }
            .status-definitiv {
                color: green;
                font-weight: bold;
            }
            .status-wandernd {
                color: orange;
                font-weight: bold;
            }
            .hinweis-box {
                margin-top: 15px;
                padding: 10px;
                background-color: #e7f3ff;
                border-left: 4px solid #2196F3;
                font-size: 10px;
            }
            .legende-box {
                margin-top: 15px;
                padding: 10px;
                background-color: #f0f0f0;
                border-left: 4px solid #003366;
                font-size: 9px;
            }
            .legende-box p {
                margin: 2px 0;
            }
        ';
    }
}

/**
 * Detaillierter Wanderpreis-Verlaufs-Report
 * Zeigt die Historie einzelner Wanderpreise
 */
class WanderpreisHistorieReport extends PDFGenerator {
    
    private $wanderpreis_id;
    
    public function __construct($conn, $wanderpreis_id, $year = null) {
        parent::__construct($conn, $year);
        $this->wanderpreis_id = $wanderpreis_id;
    }
    
    public function generate() {
        // Wanderpreis-Grunddaten holen
        $wanderpreisInfo = $this->getWanderpreisInfo();
        if (!$wanderpreisInfo) {
            $this->outputError('Wanderpreis nicht gefunden');
            return;
        }
        
        // Custom CSS für Akura-ähnlichen Stil
        $customStyles = $this->getCustomStyles();
        
        $html = $this->createHTMLHeader('Wanderpreis Historie: ' . $wanderpreisInfo['bezeichnung'], $customStyles, 11);
        $html .= '<h1>Wanderpreis Historie: ' . htmlspecialchars($wanderpreisInfo['bezeichnung']) . '</h1>';
        $html .= '<h2>MSV Wilen</h2>';
        
        // Wanderpreis-Details
        $html .= $this->createWanderpreisInfoBox($wanderpreisInfo);
        
        // Historie-Tabelle
        $historieData = $this->getWanderpreisHistorie();
        if (!empty($historieData)) {
            $html .= '<h3>Gewinner-Historie</h3>';
            $html .= $this->createHistorieTable($historieData);
        } else {
            $html .= '<p>Keine Gewinner-Historie gefunden.</p>';
        }
        
        $html .= $this->createHTMLFooter();
        
        // PDF generieren
        $filename = 'Wanderpreis_Historie_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $wanderpreisInfo['bezeichnung']);
        $pdfPath = $this->generatePDF($html, $filename);
        $this->outputDownloadLink($pdfPath);
    }
    
    private function getWanderpreisInfo() {
        $sql = "SELECT * FROM wanderpreise WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->wanderpreis_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    private function getWanderpreisHistorie() {
        $sql = "
            SELECT 
                wg.jahr,
                wg.rang,
                wg.resultat,
                wg.bemerkung,
                wg.ist_definitiv,
                wg.anzahl_gewinne,
                CONCAT(m.Vorname, ' ', m.Name) as gewinner_name,
                m.Geburtsdatum
            FROM wanderpreise_gewinner wg
            LEFT JOIN mitglieder m ON wg.gewinner_id = m.ID
            WHERE wg.wanderpreis_id = ?
            ORDER BY wg.jahr DESC
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->wanderpreis_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    private function createWanderpreisInfoBox($info) {
        $html = '<div class="wanderpreis-info-box">';
        $html .= '<h4>' . htmlspecialchars($info['bezeichnung']) . '</h4>';
        
        if (!empty($info['beschreibung'])) {
            $html .= '<p><strong>Beschreibung:</strong> ' . htmlspecialchars($info['beschreibung']) . '</p>';
        }
        
        $html .= '<p><strong>Anschaffung:</strong> ' . $info['beschaffung_datum'] . '</p>';
        
        if ($info['min_anzahl_gewinne'] > 0) {
            $html .= '<p><strong>Definitiv nach:</strong> ' . $info['min_anzahl_gewinne'] . ' Gewinnen</p>';
        }
        
        if ($info['auto_verknuepfung']) {
            $html .= '<p><strong>Automatische Verknüpfung:</strong> Ja';
            if (!empty($info['verknuepfung_regel'])) {
                $html .= ' (Regel: ' . htmlspecialchars($info['verknuepfung_regel']) . ')';
            }
            $html .= '</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function createHistorieTable($data) {
        $html = '<table class="table">';
        $html .= '<thead>
                    <tr>
                        <th>Jahr</th>
                        <th>Gewinner</th>
                        <th>Rang/Resultat</th>
                        <th>Gewinne</th>
                        <th>Status</th>
                        <th>Bemerkung</th>
                    </tr>
                  </thead>
                  <tbody>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            
            // Jahr
            $html .= '<td class="text-center">' . $row['jahr'] . '</td>';
            
            // Gewinner
            $html .= '<td>' . htmlspecialchars($row['gewinner_name']) . '</td>';
            
            // Rang/Resultat
            $html .= '<td class="text-center">';
            if (!empty($row['rang'])) {
                $html .= htmlspecialchars($row['rang']);
            } elseif (!empty($row['resultat'])) {
                $html .= htmlspecialchars($row['resultat']);
            } else {
                $html .= '-';
            }
            $html .= '</td>';
            
            // Anzahl Gewinne
            $html .= '<td class="text-center">' . $row['anzahl_gewinne'] . 'x</td>';
            
            // Status
            $html .= '<td class="text-center">';
            if ($row['ist_definitiv']) {
                $html .= '<strong class="status-definitiv">Definitiv</strong>';
            } else {
                $html .= '<span class="status-wandernd">Wandernd</span>';
            }
            $html .= '</td>';
            
            // Bemerkung
            $html .= '<td>';
            if (!empty($row['bemerkung'])) {
                $html .= htmlspecialchars($row['bemerkung']);
            } else {
                $html .= '-';
            }
            $html .= '</td>';
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Gibt das benutzerdefinierte CSS für den Akura-ähnlichen Stil zurück
     */
    private function getCustomStyles() {
        return '
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #003366;
                padding-bottom: 15px;
            }
            h1 {
                color: #003366;
                font-size: 18pt;
                margin: 10px 0;
                font-weight: bold;
            }
            h2 {
                color: #003366;
                font-size: 16pt;
                margin: 5px 0;
                text-align: center;
            }
            h3 {
                color: #003366;
                font-size: 14pt;
                margin: 15px 0 5px 0;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .table th {
                background-color: #003366;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: bold;
            }
            .table td {
                padding: 8px 10px;
                border-bottom: 1px solid #ddd;
            }
            .table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .wanderpreis-info-box {
                background-color: #f8f9fa;
                padding: 15px;
                border: 1px solid #dee2e6;
                margin: 15px 0;
            }
            .wanderpreis-info-box h4 {
                color: #003366;
                margin-top: 0;
            }
            .text-center {
                text-align: center;
            }
            .status-definitiv {
                color: green;
                font-weight: bold;
            }
            .status-wandernd {
                color: orange;
                font-weight: bold;
            }
        ';
    }
}

/**
 * Wanderpreis Gewinner Report
 */
class WanderpreisReport extends PDFGenerator {
    public function generate() {
        // SQL Query to fetch hiking prize winners for the selected year
        $sql = "SELECT 
                    wg.id,
                    wg.wanderpreis_id,
                    wg.gewinner_id,
                    wg.jahr,
                    wg.rang,
                    wg.resultat,
                    wg.bemerkung,
                    wg.ist_definitiv,
                    wg.anzahl_gewinne,
                    w.bezeichnung as wanderpreis_bezeichnung,
                    CONCAT(m.Name, ' ', m.Vorname) as gewinner_name,
                    m.Geburtsdatum
                FROM wanderpreise_gewinner wg
                INNER JOIN wanderpreise w ON wg.wanderpreis_id = w.id
                INNER JOIN mitglieder m ON wg.gewinner_id = m.ID
                WHERE wg.jahr = {$this->selectedYear}
                ORDER BY w.bezeichnung, wg.rang";

        $data = $this->executeQuery($sql);

        // Custom CSS für Akura-ähnlichen Stil
        $customStyles = $this->getCustomStyles();
        
        // Create HTML header
        $html = $this->createHTMLHeader('Wanderpreis Gewinner ' . $this->selectedYear, $customStyles, 11);
        $html .= '<h1>Wanderpreis Gewinner ' . $this->selectedYear . '</h1>';
        $html .= '<h2>MSV Wilen</h2>';

        // Create table for hiking prize winners
        if (!empty($data)) {
            $html .= '<table class="table">';
            $html .= '<thead><tr>
                        <th>Wanderpreis</th>
                        <th>Gewinner</th>
                        <th>Rang/Resultat</th>
                        <th>Anzahl Gewinne</th>
                        <th>Status</th>
                      </tr></thead><tbody>';

            $current_wanderpreis = '';
            foreach ($data as $row) {
                // Group by hiking prize
                if ($current_wanderpreis !== $row['wanderpreis_bezeichnung']) {
                    if ($current_wanderpreis !== '') {
                        $html .= '<tr><td colspan="5"></td></tr>'; // Add spacing between prizes
                    }
                    $html .= '<tr><td colspan="5" class="wanderpreis-name"><strong>' . htmlspecialchars($row['wanderpreis_bezeichnung']) . '</strong></td></tr>';
                    $current_wanderpreis = $row['wanderpreis_bezeichnung'];
                }

                $html .= '<tr>';
                $html .= '<td></td>'; // Empty cell for grouping
                $html .= '<td>' . htmlspecialchars($row['gewinner_name']) . '</td>';
                
                if (!empty($row['rang'])) {
                    $html .= '<td>' . htmlspecialchars($row['rang']) . '</td>';
                } else {
                    $html .= '<td>' . htmlspecialchars($row['resultat']) . '</td>';
                }
                
                $html .= '<td class="text-center">' . $row['anzahl_gewinne'] . 'x</td>';
                
                if ($row['ist_definitiv']) {
                    $html .= '<td class="status-definitiv">Definitiv</td>';
                } else {
                    $html .= '<td class="status-wandernd">Noch nicht definitiv</td>';
                }
                
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        } else {
            $html .= '<p>Keine Wanderpreis Gewinner für das Jahr ' . $this->selectedYear . ' gefunden.</p>';
        }

        $html .= $this->createHTMLFooter();

        // Generate PDF
        $pdfPath = $this->generatePDF($html, 'WanderpreisGewinner');
        $this->outputDownloadLink($pdfPath);
    }
    
    /**
     * Gibt das benutzerdefinierte CSS für den Akura-ähnlichen Stil zurück
     */
    private function getCustomStyles() {
        return '
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #003366;
                padding-bottom: 15px;
            }
            h1 {
                color: #003366;
                font-size: 18pt;
                margin: 10px 0;
                font-weight: bold;
            }
            h2 {
                color: #003366;
                font-size: 16pt;
                margin: 5px 0;
                text-align: center;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .table th {
                background-color: #003366;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: bold;
            }
            .table td {
                padding: 8px 10px;
                border-bottom: 1px solid #ddd;
            }
            .table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .wanderpreis-name {
                font-weight: bold;
                color: #003366;
            }
            .text-center {
                text-align: center;
            }
            .status-definitiv {
                color: green;
                font-weight: bold;
            }
            .status-wandernd {
                color: orange;
                font-weight: bold;
            }
        ';
    }
}

/**
 * Akura Gravur-Auftrag Report
 * Generiert einen PDF-Report für die Gravur-Aufträge von Akura Einsiedeln
 */
class AkuraGravurReport extends PDFGenerator {
    private $gravurDatum;
    
    public function __construct($conn, $year = null) {
        parent::__construct($conn, $year);
        $this->calculateGravurDatum();
    }
    
    /**
     * Berechnet das Gravur-Datum (Freitag vor dem Absenden-Termin)
     */
    private function calculateGravurDatum() {
        // Standard-Datum falls nicht gefunden
        $this->gravurDatum = "15. November " . $this->selectedYear;
        
        // Hole das Absenden-Datum aus JMDefinition
        $sql = "SELECT Schiesstage FROM JMDefinition 
                WHERE Bezeichnung = 'Absenden' 
                AND year = ?
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->selectedYear);
        $stmt->execute();
        $result = $stmt->get_result();
        $absenden_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($absenden_data && $absenden_data['Schiesstage']) {
            // Parse das Datum aus dem Schiesstage-Feld
            if (preg_match('/(\d{1,2})\.\s*(\w+)/', $absenden_data['Schiesstage'], $matches)) {
                $tag = intval($matches[1]);
                $monat = $matches[2];
                
                $monate = [
                    'Januar' => 1, 'Februar' => 2, 'März' => 3, 'April' => 4,
                    'Mai' => 5, 'Juni' => 6, 'Juli' => 7, 'August' => 8,
                    'September' => 9, 'Oktober' => 10, 'November' => 11, 'Dezember' => 12
                ];
                
                if (isset($monate[$monat])) {
                    $datum = mktime(0, 0, 0, $monate[$monat], $tag, $this->selectedYear);
                    $wochentag = date('w', $datum);
                    
                    // Berechne den vorherigen Freitag
                    if ($wochentag == 6) { // Samstag
                        $freitag = $datum - 86400; // Ein Tag zurück
                    } elseif ($wochentag == 0) { // Sonntag
                        $freitag = $datum - 2 * 86400; // Zwei Tage zurück
                    } else {
                        // Andere Wochentage - zum vorherigen Freitag zurück
                        $tage_zurueck = ($wochentag + 2) % 7;
                        if ($tage_zurueck == 0) $tage_zurueck = 7;
                        $freitag = $datum - ($tage_zurueck * 86400);
                    }
                    
                    $this->gravurDatum = date('j', $freitag) . '. ' . $monat . ' ' . $this->selectedYear;
                }
            }
        }
    }
    
    public function generate() {
        // Hole alle Wanderpreise von Akura Einsiedeln mit Gewinnern für das aktuelle Jahr
        $sql = "SELECT 
                    w.id,
                    w.bezeichnung,
                    wg.jahr,
                    CONCAT(m.Name, ' ', m.Vorname) as gewinner_name
                FROM wanderpreise w
                LEFT JOIN wanderpreise_gewinner wg ON w.id = wg.wanderpreis_id AND wg.jahr = ?
                LEFT JOIN mitglieder m ON wg.gewinner_id = m.ID
                WHERE w.hersteller = 'Akura Einsiedeln'
                ORDER BY w.bezeichnung ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->selectedYear);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Custom CSS für Akura-Report
        $customStyles = '
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #003366;
                padding-bottom: 15px;
            }
            h1 {
                color: #003366;
                font-size: 18pt;
                margin: 10px 0;
                font-weight: bold;
            }
            h2 {
                color: #003366;
                font-size: 16pt;
                margin: 5px 0;
                text-align: center;
            }
            .table th {
                background-color: #003366;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: bold;
            }
            .table td {
                padding: 8px 10px;
                border-bottom: 1px solid #ddd;
            }
            .table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .wanderpreis-name {
                font-weight: bold;
                color: #003366;
            }
            .gravur-info {
                color: #666;
            }
            .no-winner {
                color: #999;
                font-style: italic;
            }
            .rechnung-box {
                background-color: #f0f0f0;
                padding: 15px;
                border-left: 4px solid #003366;
                margin-top: 30px;
            }
            .rechnung-titel {
                font-weight: bold;
                font-size: 12pt;
                color: #003366;
                margin-bottom: 10px;
            }
            .rechnung-adresse {
                line-height: 1.4;
            }
        ';
        
        // HTML erstellen
        $html = $this->createHTMLHeader('Akura Gravur-Auftrag ' . $this->selectedYear, $customStyles, 11);
        
        $html .= '<h1>Wanderpreise gravieren bis: Freitag, ' . $this->gravurDatum . '</h1>';
        $html .= '<h2>MSV Wilen</h2>';
        
        $html .= '<table class="table">';
        $html .= '<thead><tr>';
        $html .= '<th style="width: 40%;">Wanderpreis</th>';
        $html .= '<th style="width: 60%;">Gravur-Information</th>';
        $html .= '</tr></thead><tbody>';
        
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $count++;
            $gravur_text = '';
            
            if ($row['gewinner_name'] && $row['jahr']) {
                $gravur_text = '<span class="gravur-info">gravieren: </span>' . 
                              $row['jahr'] . ' ' . htmlspecialchars($row['gewinner_name']);
            } else {
                $gravur_text = '<span class="no-winner">Noch kein Gewinner für ' . $this->selectedYear . '</span>';
            }
            
            $html .= '<tr>';
            $html .= '<td class="wanderpreis-name">' . htmlspecialchars($row['bezeichnung']) . '</td>';
            $html .= '<td>' . $gravur_text . '</td>';
            $html .= '</tr>';
        }
        
        if ($count == 0) {
            $html .= '<tr>';
            $html .= '<td colspan="2" style="text-align: center; padding: 20px; color: #999;">';
            $html .= 'Keine Wanderpreise von Akura Einsiedeln gefunden';
            $html .= '</td></tr>';
        }
        
        $html .= '</tbody></table>';
        
        // Rechnung-Box
        $html .= '<div class="rechnung-box">';
        $html .= '<div class="rechnung-titel">Rechnung senden an:</div>';
        $html .= '<div class="rechnung-adresse">';
        $html .= '<strong>Schober Marco</strong><br>';
        $html .= 'Sihlboden 2<br>';
        $html .= '8847 Egg<br>';
        $html .= 'Tel. 079 519 11 88';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= $this->createHTMLFooter();
        
        $stmt->close();
        
        // PDF generieren
        $pdfPath = $this->generatePDF($html, 'Akura_Gravur_' . $this->selectedYear);
        $this->outputDownloadLink($pdfPath);
    }
}

/**
 * Top 3 Schützen Report
 * Zeigt die Top 3 Schützen für jede Kategorie (Kat. A und Kat. B) basierend auf dem bereitgestellten SQL
 */
class Top3SchuetzenReport extends PDFGenerator {
    public function generate() {
        // Custom CSS für Akura-ähnlichen Stil
        $customStyles = '
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #003366;
                padding-bottom: 15px;
            }
            h1 {
                color: #003366;
                font-size: 18pt;
                margin: 10px 0;
                font-weight: bold;
            }
            h2 {
                color: #003366;
                font-size: 16pt;
                margin: 5px 0;
                text-align: center;
            }
            .table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .table th {
                background-color: #003366;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: bold;
            }
            .table td {
                padding: 8px 10px;
                border-bottom: 1px solid #ddd;
            }
            .table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .category-header {
                background-color: #003366;
                color: white;
                padding: 10px;
                text-align: center;
                font-weight: bold;
                margin: 20px 0 10px 0;
            }
            .rank-number {
                font-weight: bold;
                font-size: 14pt;
                text-align: center;
            }
        ';
        
        // Create HTML header
        $html = $this->createHTMLHeader('Top 3 Schützen ' . $this->selectedYear, $customStyles, 11);
        $html .= '<h1>Top 3 Schützen ' . $this->selectedYear . '</h1>';
        $html .= '<h2>MSV Wilen</h2>';
        
        // Hole die Top 3 Schützen für Kat. A
        $html .= '<div class="category-header">Kategorie A</div>';
        $topA = $this->getTopSchuetzen('Kat. A');
        $html .= $this->createTopSchuetzenTable($topA);
        
        // Hole die Top 3 Schützen für Kat. B
        $html .= '<div class="category-header">Kategorie B</div>';
        $topB = $this->getTopSchuetzen('Kat. B');
        $html .= $this->createTopSchuetzenTable($topB);
        
        $html .= $this->createHTMLFooter();
        
        // Generate PDF
        $pdfPath = $this->generatePDF($html, 'Top3Schuetzen_' . $this->selectedYear);
        $this->outputDownloadLink($pdfPath);
    }
    
    /**
     * Holt die Top 3 Schützen für eine Kategorie basierend auf dem bereitgestellten SQL
     */
    private function getTopSchuetzen($category) {
        // Query basierend auf dem bereitgestellten SQL
        // Da die SQL-Query zu komplex für prepared statements ist, führen wir sie direkt aus
        // und ersetzen die Parameter manuell
        
        // Für die members CTE brauchen wir eine spezielle Behandlung
        // Da wir nicht einfach ? Platzhalter verwenden können, ersetzen wir sie direkt
        $year = intval($this->selectedYear);
        
        $sql = "
            -- Haupt-Query mit CTEs
            WITH
            -- Wettbewerbe laden
            definitions AS (
                SELECT ID, Bezeichnung, Maxpunkte, Streicher, Reihenfolge
                FROM JMDefinition
                WHERE year = $year AND Erweitert = 0 AND Info = 0
            ),

            -- Mitglieder laden
            members AS (
                SELECT m.ID, m.Vorname, m.Name, w.Bezeichnung as Waffe
                FROM mitglieder m
                JOIN Waffen w ON w.ID = m.WaffenID
                WHERE m.Status = 1
                AND w.Kategorie = '" . $this->conn->real_escape_string($category) . "'
            ),

            -- Normale Resultate sammeln
            normal_results AS (
                SELECT
                    jr.mitgliederID,
                    jr.jmdefinitionID,
                    CASE
                        WHEN jd.Bezeichnung IN ('Einzelwettschiessen', 'Obligatorisch', 'Feldschiessen') THEN jr.Punkte
                        WHEN jd.Maxpunkte > 0 THEN ROUND((jr.Punkte * 100.0) / jd.Maxpunkte, 2)
                        ELSE jr.Punkte
                    END AS scaled_points,
                    jd.Streicher AS streicher_flag
                FROM jmresultate jr
                JOIN definitions jd ON jd.ID = jr.jmdefinitionID
                WHERE jr.mitgliederID IN (SELECT ID FROM members)
            ),

            -- Endstich Resultate
            endstich_results AS (
                SELECT
                    e.MitgliedID as mitgliederID,
                    jd.ID as jmdefinitionID,
                    CASE
                        WHEN jd.Maxpunkte > 0 THEN ROUND(((COALESCE(e.Schuss1,0) + COALESCE(e.Schuss2,0) + COALESCE(e.Schuss3,0) +
                            COALESCE(e.Schuss4,0) + COALESCE(e.Schuss5,0) + COALESCE(e.Schuss6,0) +
                            COALESCE(e.Schuss7,0) + COALESCE(e.Schuss8,0) + COALESCE(e.Schuss9,0) +
                            COALESCE(e.Schuss10,0)) * 100.0) / jd.Maxpunkte, 2)
                        ELSE (COALESCE(e.Schuss1,0) + COALESCE(e.Schuss2,0) + COALESCE(e.Schuss3,0) +
                            COALESCE(e.Schuss4,0) + COALESCE(e.Schuss5,0) + COALESCE(e.Schuss6,0) +
                            COALESCE(e.Schuss7,0) + COALESCE(e.Schuss8,0) + COALESCE(e.Schuss9,0) +
                            COALESCE(e.Schuss10,0))
                    END AS scaled_points,
                    jd.Streicher AS streicher_flag
                FROM endstich e
                CROSS JOIN definitions jd
                WHERE jd.Bezeichnung = 'Endstich'
                AND e.Jahr = $year
                AND e.MitgliedID IN (SELECT ID FROM members)
            ),

            -- Kantonalstich Resultate (Bester)
            kanti_results AS (
                SELECT
                    k.MitgliedID as mitgliederID,
                    jd.ID as jmdefinitionID,
                    CASE
                        WHEN jd.Maxpunkte > 0 THEN ROUND((GREATEST(
                            COALESCE(k.Passe1,0), COALESCE(k.Passe2,0), COALESCE(k.Passe3,0),
                            COALESCE(k.Passe4,0), COALESCE(k.Passe5,0)
                        ) * 100.0) / jd.Maxpunkte, 2)
                        ELSE GREATEST(
                            COALESCE(k.Passe1,0), COALESCE(k.Passe2,0), COALESCE(k.Passe3,0),
                            COALESCE(k.Passe4,0), COALESCE(k.Passe5,0)
                        )
                    END AS scaled_points,
                    jd.Streicher AS streicher_flag
                FROM kantiresultate k
                CROSS JOIN definitions jd
                WHERE jd.Bezeichnung = 'Bester Kantonalstich'
                AND k.Jahr = $year
                AND k.MitgliedID IN (SELECT ID FROM members)
            ),

            -- Alle Resultate zusammenführen
            all_results AS (
                SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM normal_results
                UNION ALL
                SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM endstich_results
                UNION ALL
                SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM kanti_results
            ),

            -- Aktive Streicher-Wettbewerbe ermitteln
            active_streicher AS (
                SELECT DISTINCT jmdefinitionID
                FROM all_results
                WHERE streicher_flag = 1
            ),

            -- Nicht-Teilnahmen als 0-Punkte hinzufügen
            results_with_zeros AS (
                SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM all_results
                UNION ALL
                SELECT
                    m.ID as mitgliederID,
                    as_id.jmdefinitionID,
                    0 as scaled_points,
                    1 as streicher_flag
                FROM members m
                CROSS JOIN active_streicher as_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM all_results ar
                    WHERE ar.mitgliederID = m.ID
                    AND ar.jmdefinitionID = as_id.jmdefinitionID
                )
            ),

            -- Sektionsmeisterschaft filtern
            sm_filtered AS (
                SELECT
                    r.mitgliederID,
                    r.jmdefinitionID,
                    CASE
                        WHEN jd.Bezeichnung = 'Sektionsmeisterschaft' THEN MAX(r.scaled_points)
                        ELSE r.scaled_points
                    END as scaled_points,
                    r.streicher_flag
                FROM results_with_zeros r
                JOIN definitions jd ON jd.ID = r.jmdefinitionID
                GROUP BY r.mitgliederID, r.jmdefinitionID,
                    CASE WHEN jd.Bezeichnung = 'Sektionsmeisterschaft' THEN 1 ELSE r.scaled_points END,
                    r.streicher_flag
            ),

            -- Streicher berechnen
            streicher_calc AS (
                SELECT
                    mitgliederID,
                    jmdefinitionID,
                    scaled_points,
                    streicher_flag,
                    CASE
                        WHEN streicher_flag = 1 THEN
                            ROW_NUMBER() OVER (PARTITION BY mitgliederID, streicher_flag ORDER BY scaled_points ASC, jmdefinitionID)
                        ELSE NULL
                    END as streicher_rank
                FROM sm_filtered
            ),

            -- Finale Berechnung
            final_scores AS (
                SELECT
                    m.ID,
                    m.Name,
                    m.Vorname,
                    m.Waffe,
                    SUM(CASE WHEN s.streicher_flag = 0 THEN s.scaled_points ELSE 0 END) as sumStreicher0,
                    SUM(CASE WHEN s.streicher_flag = 1 AND s.streicher_rank > 3 THEN s.scaled_points ELSE 0 END) as sumStreicher1,
                    SUM(CASE
                        WHEN s.streicher_flag = 0 THEN s.scaled_points
                        WHEN s.streicher_flag = 1 AND s.streicher_rank > 3 THEN s.scaled_points
                        ELSE 0
                    END) as sumTotal
                FROM members m
                LEFT JOIN streicher_calc s ON m.ID = s.mitgliederID
                GROUP BY m.ID, m.Name, m.Vorname, m.Waffe
            )

            -- Endergebnis mit Rangierung - nur die Top 3
            SELECT
                ID as gewinner_id,
                DENSE_RANK() OVER (ORDER BY sumTotal DESC) as Rang,
                Name,
                Vorname,
                Waffe,
                ROUND(sumStreicher0, 2) as Punkte_Fix,
                ROUND(sumStreicher1, 2) as Punkte_Streicher,
                ROUND(sumTotal, 2) as Total
            FROM final_scores
            ORDER BY sumTotal DESC, Name ASC
            LIMIT 3
        ";
        
        $result = $this->conn->query($sql);
        
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        
        return $data;
    }
    
    /**
     * Erstellt die Tabelle für die Top Schützen
     */
    private function createTopSchuetzenTable($schuetzen) {
        $html = '<table class="table">';
        $html .= '<thead><tr>
                    <th style="width: 10%;">Rang</th>
                    <th style="width: 90%;">Waffe</th>
                  </tr></thead><tbody>';
        
        if (empty($schuetzen)) {
            $html .= '<tr><td colspan="2" style="text-align: center;">Keine Schützen gefunden</td></tr>';
        } else {
            foreach ($schuetzen as $schuetze) {
                $html .= '<tr>';
                $html .= '<td class="rank-number">' . $schuetze['Rang'] . '.</td>';
                
                // Add "(Gross)" for first rank, "(Klein)" for others
                $waffe_text = htmlspecialchars($schuetze['Waffe']);
                if ($schuetze['Rang'] == 1) {
                    $waffe_text .= ' (Gross)';
                } else {
                    $waffe_text .= ' (Klein)';
                }
                
                $html .= '<td class="rank-number">' . $waffe_text . '</td>';
                $html .= '</tr>';
            }
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
}
?>
