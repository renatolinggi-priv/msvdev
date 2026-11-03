
<?php
// PDFReports.php - Alle PDF-Report-Klassen für das Wanderpreis-Modul

require_once 'PDFGenerator.php';

/**
 * Wanderpreise Jahres-Report
 * Zeigt alle Wanderpreise mit aktuellen Gewinnern und Statistiken
 */
class WanderpreiseJahresReport extends PDFGenerator {

    /** Gravur-Aufträge (Schnitzerei, Akura): Abgabetermin gross, etwas mehr Zeilenabstand zum Abhaken */
    const GRAVUR_CSS = '
        .rl-frist { font-size: 12pt; font-weight: bold; margin: 0 0 5mm 0; }
        .table { font-size: 10.5pt; }
        .table td { padding-top: 2.2mm; padding-bottom: 2.2mm; }
        .table td:last-child { text-align: left; }
        .rl-adresse { line-height: 1.45; margin: 0; }
    ';

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
        
        // Ranglisten-Layout (Logo links, Titel, Fusszeile mit Seitenzahl)
        $untertitel = 'Jahresbericht' . ($this->hersteller_filter ? ' · ' . $this->hersteller_filter : '');
        $html = $this->ranglisteHeader('Wanderpreise ' . $this->selectedYear, $untertitel, $this->getCustomStyles());

        // Alle Wanderpreise mit Gewinnerdaten holen
        $wanderpreiseData = $this->getWanderpreiseOverview();
        
        if (empty($wanderpreiseData)) {
            $html .= '<p>Keine Wanderpreise für das Jahr ' . $this->selectedYear . ' gefunden.</p>';
        } else {
            $html .= $this->createWanderpreiseTable($wanderpreiseData);
        }
        
        // Hinweis falls Filter aktiv
        if ($this->hersteller_filter) {
            $html .= '<p class="rl-hinweis">Diese Aufstellung zeigt nur Wanderpreise von ' . htmlspecialchars($this->hersteller_filter) . '.</p>';
        }

        // Legende
        $html .= '<p class="rl-hinweis"><strong>Seine Gewinne:</strong> Siege des aktuellen Gewinners für diesen Wanderpreis, von so vielen, bis er ihm definitiv gehört. '
               . '<strong>Gesamt:</strong> verschiedene Personen und Jahre mit Gewinner. '
               . '<strong>Definitiv:</strong> Der Wanderpreis gehört dem Gewinner.</p>';
        
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
        
        // HTML erstellen (Ranglisten-Layout, Abgabetermin gleich unter dem Kopf)
        $html = $this->ranglisteHeader('Gravur-Auftrag ' . $this->selectedYear, 'Schnitzerei Heinz Schild · Wanderpreise MSV Wilen', self::GRAVUR_CSS);
        $html .= '<p class="rl-frist">Bitte gravieren bis ' . htmlspecialchars($datum) . '</p>';

        $html .= '<table class="table">';
        $html .= '<thead><tr><th style="width: 48%;">Wanderpreis</th><th>Gravur</th></tr></thead><tbody>';

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $count++;
            if ($row['gewinner_name'] && $row['jahr']) {
                $info_text = $row['jahr'] . ' ' . htmlspecialchars($row['gewinner_name']);
            } else {
                $info_text = '<span class="muted">noch kein Gewinner für ' . $this->selectedYear . '</span>';
            }
            $html .= '<tr><td><strong>' . htmlspecialchars($row['bezeichnung']) . '</strong></td><td>' . $info_text . '</td></tr>';
        }

        if ($count == 0) {
            $html .= '<tr><td colspan="2" class="muted">Keine Wanderpreise von Schnitzerei Heinz Schild gefunden.</td></tr>';
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
                WHERE Bezeichnung LIKE '%Absenden%' 
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
                CONCAT(TRIM(m_aktuell.Vorname), ' ', TRIM(m_aktuell.Name)) as aktueller_gewinner_name,
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
                        <th>Wanderpreis</th>
                        <th style="width: 24mm;">Hersteller</th>
                        <th style="width: 28mm;">Gewinner ' . $this->selectedYear . '</th>
                        <th class="z" style="width: 12mm;">Rang/Res.</th>
                        <th class="z" style="width: 16mm;">Seine Gewinne</th>
                        <th class="z" style="width: 20mm;">Gesamt</th>
                        <th style="width: 20mm;">Status</th>
                    </tr>
                  </thead>
                  <tbody>';

        foreach ($data as $row) {
            $html .= '<tr>';

            // Wanderpreis Name + Beschreibung + Anschaffung
            $html .= '<td><strong>' . htmlspecialchars($row['bezeichnung']) . '</strong>';
            $zusatz = [];
            if (!empty($row['beschreibung'])) {
                $zusatz[] = htmlspecialchars($row['beschreibung']);
            }
            $zusatz[] = 'seit ' . htmlspecialchars((string)$row['beschaffung_datum']);
            $html .= '<br><span class="muted">' . implode(' · ', $zusatz) . '</span></td>';

            // Hersteller
            $html .= '<td>' . (!empty($row['hersteller']) ? htmlspecialchars($row['hersteller']) : '<span class="muted">nicht angegeben</span>') . '</td>';

            // Aktueller Gewinner
            $html .= '<td>' . (!empty($row['aktueller_gewinner_name'])
                ? htmlspecialchars($row['aktueller_gewinner_name'])
                : '<span class="muted">kein Gewinner</span>') . '</td>';

            // Rang/Resultat
            if (!empty($row['aktueller_rang'])) {
                $rangResultat = htmlspecialchars($row['aktueller_rang']);
            } elseif (!empty($row['aktuelles_resultat'])) {
                $rangResultat = htmlspecialchars($row['aktuelles_resultat']);
            } else {
                $rangResultat = '–';
            }
            $html .= '<td class="z">' . $rangResultat . '</td>';

            // Anzahl Gewinne des aktuellen Gewinners (von min_anzahl_gewinne)
            $gewinne = '–';
            if (!empty($row['aktuelle_anzahl_gewinne'])) {
                $gewinne = $row['aktuelle_anzahl_gewinne'] . '×';
                if ($row['min_anzahl_gewinne'] > 0) {
                    $gewinne .= ' von ' . $row['min_anzahl_gewinne'];
                }
            }
            $html .= '<td class="z">' . $gewinne . '</td>';

            // Gesamtstatistiken: Personen, Jahre, Zeitraum
            $html .= '<td class="z">' . $row['anzahl_verschiedene_gewinner'] . ' Pers. · ' . $row['anzahl_gewinne_total'] . ' J.';
            if (!empty($row['erstes_jahr']) && !empty($row['letztes_jahr'])) {
                $zeitraum = $row['erstes_jahr'] == $row['letztes_jahr']
                    ? $row['erstes_jahr']
                    : $row['erstes_jahr'] . '–' . $row['letztes_jahr'];
                $html .= '<br><span class="muted">' . $zeitraum . '</span>';
            }
            $html .= '</td>';

            // Status
            $html .= '<td>';
            if (!empty($row['aktueller_gewinner_id'])) {
                if ($row['ist_definitiv']) {
                    $html .= '<strong>Definitiv</strong>';
                } else {
                    $html .= 'Wandert noch';
                    if ($row['min_anzahl_gewinne'] > 0 && $row['aktuelle_anzahl_gewinne'] > 0) {
                        $html .= '<br><span class="muted">' . $row['aktuelle_anzahl_gewinne'] . '/' . $row['min_anzahl_gewinne'] . '</span>';
                    }
                }
            } else {
                $html .= '–';
            }
            $html .= '</td>';

            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Layout-Ergänzungen zum Ranglisten-Stil: Zellen oben ausgerichtet (mehrzeilig), Zusatzzeilen klein
     */
    private function getCustomStyles() {
        return '
            .table { font-size: 9pt; }
            .table td { vertical-align: top; padding-top: 1.6mm; padding-bottom: 1.6mm; }
            .table th { vertical-align: bottom; }
            .table .muted { font-size: 7.5pt; }
            .table td:last-child { text-align: left; }
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
        
        $html = $this->ranglisteHeader($wanderpreisInfo['bezeichnung'], 'Wanderpreis · Gewinner-Historie', $this->getCustomStyles());

        // Wanderpreis-Details
        $html .= $this->createWanderpreisInfoBox($wanderpreisInfo);

        // Historie-Tabelle
        $historieData = $this->getWanderpreisHistorie();
        if (!empty($historieData)) {
            $html .= '<h2 class="rl-abschnitt">Gewinner</h2>';
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
        // Steckbrief als schlichte zweispaltige Liste (Begriff | Wert)
        $zeilen = [];
        if (!empty($info['beschreibung'])) {
            $zeilen['Beschreibung'] = htmlspecialchars($info['beschreibung']);
        }
        $zeilen['Anschaffung'] = htmlspecialchars((string)$info['beschaffung_datum']);
        if ($info['min_anzahl_gewinne'] > 0) {
            $zeilen['Definitiv nach'] = (int)$info['min_anzahl_gewinne'] . ' Gewinnen';
        }
        if ($info['auto_verknuepfung']) {
            $zeilen['Automatische Verknüpfung'] = 'Ja' . (!empty($info['verknuepfung_regel'])
                ? ' (Regel: ' . htmlspecialchars($info['verknuepfung_regel']) . ')' : '');
        }

        $html = '<table class="rl-steckbrief">';
        foreach ($zeilen as $begriff => $wert) {
            $html .= '<tr><td class="rl-begriff">' . $begriff . '</td><td>' . $wert . '</td></tr>';
        }
        return $html . '</table>';
    }
    
    private function createHistorieTable($data) {
        $html = '<table class="table">';
        $html .= '<thead>
                    <tr>
                        <th style="width: 14mm;">Jahr</th>
                        <th>Gewinner</th>
                        <th class="z" style="width: 24mm;">Rang/Resultat</th>
                        <th class="z" style="width: 18mm;">Gewinne</th>
                        <th style="width: 22mm;">Status</th>
                        <th>Bemerkung</th>
                    </tr>
                  </thead>
                  <tbody>';

        foreach ($data as $row) {
            if (!empty($row['rang'])) {
                $rangResultat = htmlspecialchars($row['rang']);
            } elseif (!empty($row['resultat'])) {
                $rangResultat = htmlspecialchars($row['resultat']);
            } else {
                $rangResultat = '–';
            }
            $html .= '<tr>'
                . '<td>' . $row['jahr'] . '</td>'
                . '<td>' . htmlspecialchars((string)$row['gewinner_name']) . '</td>'
                . '<td class="z">' . $rangResultat . '</td>'
                . '<td class="z">' . $row['anzahl_gewinne'] . '×</td>'
                . '<td>' . ($row['ist_definitiv'] ? '<strong>Definitiv</strong>' : 'Wandernd') . '</td>'
                . '<td>' . (!empty($row['bemerkung']) ? htmlspecialchars($row['bemerkung']) : '–') . '</td>'
                . '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Layout-Ergänzungen: Steckbrief (Begriff | Wert) ohne Rahmen, Status-Spalte links
     */
    private function getCustomStyles() {
        return '
            table.rl-steckbrief { border-collapse: collapse; margin: 0 0 2mm 0; }
            table.rl-steckbrief td { border: none; padding: 0.8mm 4mm 0.8mm 0; vertical-align: top; text-align: left; }
            td.rl-begriff { font-weight: bold; }
            .table td:last-child { text-align: left; }
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

        $html = $this->ranglisteHeader('Wanderpreis-Gewinner ' . $this->selectedYear, 'Wanderpreise', $this->getCustomStyles());

        // Tabelle, gruppiert nach Wanderpreis (Gruppentitel, eingerückte Gewinner)
        if (!empty($data)) {
            $html .= '<table class="table">';
            $html .= '<thead><tr>
                        <th>Gewinner</th>
                        <th class="z" style="width: 26mm;">Rang/Resultat</th>
                        <th class="z" style="width: 26mm;">Anzahl Gewinne</th>
                        <th style="width: 36mm;">Status</th>
                      </tr></thead><tbody>';

            $current_wanderpreis = '';
            foreach ($data as $row) {
                if ($current_wanderpreis !== $row['wanderpreis_bezeichnung']) {
                    $html .= '<tr class="rl-gruppe"><td colspan="4">' . htmlspecialchars($row['wanderpreis_bezeichnung']) . '</td></tr>';
                    $current_wanderpreis = $row['wanderpreis_bezeichnung'];
                }

                $rangResultat = !empty($row['rang']) ? $row['rang'] : $row['resultat'];
                $html .= '<tr>'
                    . '<td class="rl-einzug">' . htmlspecialchars((string)$row['gewinner_name']) . '</td>'
                    . '<td class="z">' . htmlspecialchars((string)$rangResultat) . '</td>'
                    . '<td class="z">' . $row['anzahl_gewinne'] . '×</td>'
                    . '<td>' . ($row['ist_definitiv'] ? '<strong>Definitiv</strong>' : 'Noch nicht definitiv') . '</td>'
                    . '</tr>';
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
     * Layout-Ergänzung: Status-Spalte links (Theme richtet die letzte Spalte rechts aus)
     */
    private function getCustomStyles() {
        return '
            .table td:last-child { text-align: left; }
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
                WHERE Bezeichnung LIKE '%Absenden%' 
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
        
        // HTML erstellen (Ranglisten-Layout, Abgabetermin gleich unter dem Kopf)
        $html = $this->ranglisteHeader('Gravur-Auftrag ' . $this->selectedYear, 'Akura Einsiedeln · Wanderpreise MSV Wilen', WanderpreiseJahresReport::GRAVUR_CSS);
        $html .= '<p class="rl-frist">Bitte gravieren bis Freitag, ' . htmlspecialchars($this->gravurDatum) . '</p>';

        $html .= '<table class="table">';
        $html .= '<thead><tr><th style="width: 48%;">Wanderpreis</th><th>Gravur</th></tr></thead><tbody>';

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $count++;
            if ($row['gewinner_name'] && $row['jahr']) {
                $gravur_text = $row['jahr'] . ' ' . htmlspecialchars($row['gewinner_name']);
            } else {
                $gravur_text = '<span class="muted">noch kein Gewinner für ' . $this->selectedYear . '</span>';
            }
            $html .= '<tr><td><strong>' . htmlspecialchars($row['bezeichnung']) . '</strong></td><td>' . $gravur_text . '</td></tr>';
        }

        if ($count == 0) {
            $html .= '<tr><td colspan="2" class="muted">Keine Wanderpreise von Akura Einsiedeln gefunden.</td></tr>';
        }

        $html .= '</tbody></table>';

        // Rechnungsadresse
        $html .= '<h2 class="rl-abschnitt">Rechnung senden an</h2>';
        $html .= '<p class="rl-adresse"><strong>Schober Marco</strong><br>Sihlboden 2<br>8847 Egg<br>Tel. 079 519 11 88</p>';
        
        $html .= $this->createHTMLFooter();
        
        $stmt->close();
        
        // PDF generieren
        $pdfPath = $this->generatePDF($html, 'Akura_Gravur_' . $this->selectedYear);
        $this->outputDownloadLink($pdfPath);
    }
}

/**
 * Mitglieder-Info Report
 * Schlanke Version des Jahresreports nur mit den wichtigsten Infos für Mitglieder
 */
class MitgliederInfoReport extends PDFGenerator {
    
    public function __construct($conn, $year = null) {
        parent::__construct($conn, $year);
    }
    
    /**
     * Generiert den Mitglieder-Info Report
     */
    public function generate() {
        $html = $this->ranglisteHeader('Wanderpreisgewinner ' . $this->selectedYear, 'Wanderpreise · Information für Mitglieder', $this->getCustomStyles());
        
        // Alle Wanderpreise mit Gewinnerdaten holen
        $wanderpreiseData = $this->getWanderpreiseOverview();
        
        if (empty($wanderpreiseData)) {
            $html .= '<p>Keine Wanderpreise für das Jahr ' . $this->selectedYear . ' gefunden.</p>';
        } else {
            $html .= $this->createWanderpreiseTable($wanderpreiseData);
        }
        
        // Hinweis für Mitglieder mit Endstich-Datum
        $html .= $this->createMitgliederInfoBox();
        
        $html .= $this->createHTMLFooter();
        
        // PDF generieren
        $filename = 'Wanderpreise_Info_' . $this->selectedYear;
        $pdfPath = $this->generatePDF($html, $filename);
        $this->outputDownloadLink($pdfPath);
    }
    
    /**
     * Holt alle Wanderpreise mit Gewinner-Informationen (vereinfacht)
     */
    private function getWanderpreiseOverview() {
        $sql = "
            SELECT 
                w.id,
                w.bezeichnung,
                w.beschreibung,
                w.beschaffung_datum,
                
                -- Aktueller Gewinner des angegebenen Jahres
                wg_aktuell.gewinner_id as aktueller_gewinner_id,
                CONCAT(TRIM(m_aktuell.Vorname), ' ', TRIM(m_aktuell.Name)) as aktueller_gewinner_name,
                wg_aktuell.rang as aktueller_rang,
                wg_aktuell.resultat as aktuelles_resultat,
                wg_aktuell.ist_definitiv
                
            FROM wanderpreise w
            LEFT JOIN wanderpreise_gewinner wg_aktuell ON (
                w.id = wg_aktuell.wanderpreis_id 
                AND wg_aktuell.jahr = ?
            )
            LEFT JOIN mitglieder m_aktuell ON wg_aktuell.gewinner_id = m_aktuell.ID
            ORDER BY w.bezeichnung ASC
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $this->selectedYear);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    /**
     * Erstellt die Info-Box mit dem Endstich-Datum aus der JMDefinition
     */
    private function createMitgliederInfoBox() {
        $html = '<div class="rl-info">';

        try {
            // Hole das Endstich-Datum aus JMDefinition
            $year = date('Y');
            $sql = "SELECT Schiesstage FROM JMDefinition WHERE Bezeichnung = 'Endstich' AND year = ? LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param('i', $year);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if (!$result) {
                // Fallback wenn Query fehlschlägt
                $html .= '<p>Diese Übersicht zeigt alle Wanderpreise mit den aktuellen Gewinnern des Jahres ' . $this->selectedYear . '.</p>';
                $html .= '<p>Bei Fragen wenden Sie sich bitte an den Vorstand.</p>';
                $html .= '</div>';
                return $html;
            }
            
            $endstich_data = $result->fetch_assoc();
            
            if ($endstich_data && !empty($endstich_data['Schiesstage'])) {
                // Parse das Datum aus dem Schiesstage-Feld
                $schiesstage = $endstich_data['Schiesstage'];
                $endstich_datum = "";
                
                // Versuche das Datum zu extrahieren
                if (preg_match('/(\d{1,2})\. (\w+)/', $schiesstage, $matches)) {
                    $endstich_datum = $matches[1] . '. ' . $matches[2] . ' ' .$year;
                } else {
                    $endstich_datum = "zum Endstich";
                }
                
                $html .= '<p><strong>Bitte gebt die Wanderpreise bis spätestens am ' . htmlspecialchars($endstich_datum) . ' an Roger Cavelti oder Renato Linggi für das Gravieren zurück.</strong></p>';
            } else {
                // Fallback falls kein Endstich gefunden wurde
                $html .= '<p>Diese Übersicht zeigt alle Wanderpreise mit den aktuellen Gewinnern des Jahres ' . $this->selectedYear . '.</p>';
                $html .= '<p>Bei Fragen wenden Sie sich bitte an den Vorstand.</p>';
            }
        } catch (Exception $e) {
            // Bei Fehler zeige Standard-Text
            $html .= '<p>Diese Übersicht zeigt alle Wanderpreise mit den aktuellen Gewinnern des Jahres ' . $this->selectedYear . '.</p>';
            $html .= '<p>Bei Fragen wenden Sie sich bitte an den Vorstand.</p>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Erstellt die HTML-Tabelle für die Wanderpreise-Übersicht (vereinfacht)
     */
    private function createWanderpreiseTable($data) {
        $html = '<table class="table">';
        $html .= '<thead>
                    <tr>
                        <th style="width: 35%;">Wanderpreis</th>
                        <th style="width: 35%;">Beschreibung</th>
                        <th style="width: 30%;">Gewinner ' . $this->selectedYear . '</th>
                    </tr>
                  </thead>
                  <tbody>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            
            // Wanderpreis Name
            $html .= '<td>';
            $html .= '<strong>' . htmlspecialchars($row['bezeichnung']) . '</strong>';
            if ($row['ist_definitiv']) {
                $html .= '<br>Definitiv gewonnen';
            }
            $html .= '</td>';
            
            // Beschreibung
            $html .= '<td>';
            if (!empty($row['beschreibung'])) {
                $beschreibung = trim($row['beschreibung']);
                // Wenn die Beschreibung nur aus einer Zahl besteht, zeige stattdessen einen Strich
                if (preg_match('/^\d+$/', $beschreibung)) {
                    $html .= '<span class="muted">–</span>';
                } else {
                    $html .= htmlspecialchars($beschreibung, ENT_QUOTES, 'UTF-8');
                }
            } else {
                $html .= '<span class="muted">–</span>';
            }
            $html .= '</td>';
            
            // Aktueller Gewinner - NUR NAME, kein Rang/Resultat
            $html .= '<td>';
            if (!empty($row['aktueller_gewinner_name'])) {
                // Name korrekt formatieren und HTML-Entities vermeiden
                $gewinner_name = html_entity_decode($row['aktueller_gewinner_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $html .= '<strong>' . htmlspecialchars($gewinner_name, ENT_QUOTES, 'UTF-8') . '</strong>';
                // Rang und Resultat NICHT mehr anzeigen
            } else {
                $html .= '<span class="muted">kein Gewinner eingetragen</span>';
            }
            $html .= '</td>';
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Gibt das benutzerdefinierte CSS für den Mitglieder-Info Stil zurück
     */
    private function getCustomStyles() {
        return '
            .table { font-size: 10pt; }
            .table td { vertical-align: top; padding-top: 1.8mm; padding-bottom: 1.8mm; }
            .table th { vertical-align: bottom; }
            .table td:last-child { text-align: left; }
            .rl-info { margin-top: 6mm; font-size: 10pt; }
            .rl-info p { margin: 0 0 1.5mm 0; }
        ';
    }
}

/**
 * Top 3 Schützen Report
 * Zeigt die Top 3 Schützen für jede Kategorie (Kat. A und Kat. B) basierend auf dem bereitgestellten SQL
 */
class Top3SchuetzenReport extends PDFGenerator {
    public function generate() {
        // Ranglisten-Layout; Preisliste etwas grösser (wird beim Einkauf abgehakt)
        $customStyles = '
            .table { font-size: 11pt; }
            .table td { padding-top: 2mm; padding-bottom: 2mm; }
            .table td:last-child { text-align: left; }
        ';
        $html = $this->ranglisteHeader('JM-Preise ' . $this->selectedYear, 'Jahresmeisterschaft · Top 3 je Kategorie', $customStyles);

        // Hole die Top 3 Schützen für Kat. A
        $html .= '<h2 class="rl-abschnitt">Kategorie A</h2>';
        $topA = $this->getTopSchuetzen('Kat. A');
        $html .= $this->createTopSchuetzenTable($topA);

        // Hole die Top 3 Schützen für Kat. B
        $html .= '<h2 class="rl-abschnitt">Kategorie B</h2>';
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
        $year = intval($this->selectedYear);
        
        $sql = "
            -- Haupt-Query mit CTEs
            WITH
            -- Wettbewerbe laden
            definitions AS (
                SELECT ID, Bezeichnung, Maxpunkte, Streicher, Reihenfolge
                FROM JMDefinition
                WHERE year = ? AND Erweitert = 0 AND Info = 0
            ),

            -- Mitglieder laden
            members AS (
                SELECT m.ID, m.Vorname, m.Name, w.Bezeichnung as Waffe
                FROM mitglieder m
                JOIN Waffen w ON w.ID = m.WaffenID
                WHERE m.Status = 1
                AND w.Kategorie = ?
            ),

            -- Normale Resultate sammeln
            normal_results AS (
                SELECT
                    jr.mitgliederID,
                    jr.jmdefinitionID,
                    CASE
                        WHEN jd.Bezeichnung IN ('Einzelwettschiessen', 'Obligatorisch', 'Feldschiessen') THEN jr.Punkte
                        WHEN jd.Maxpunkte > 0 AND jd.Maxpunkte < 100 THEN ROUND((jr.Punkte * 100.0) / jd.Maxpunkte, 2)
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
                        WHEN jd.Maxpunkte > 0 AND jd.Maxpunkte < 100 THEN ROUND(((COALESCE(e.Schuss1,0) + COALESCE(e.Schuss2,0) + COALESCE(e.Schuss3,0) +
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
                AND e.Jahr = ?
                AND e.MitgliedID IN (SELECT ID FROM members)
            ),

            -- Kantonalstich Resultate (Bester)
            kanti_results AS (
                SELECT
                    k.MitgliedID as mitgliederID,
                    jd.ID as jmdefinitionID,
                    CASE
                        WHEN jd.Maxpunkte > 0 AND jd.Maxpunkte < 100 THEN ROUND((GREATEST(
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
                AND k.Jahr = ?
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
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('isii', $year, $category, $year, $year);
        $stmt->execute();
        $result = $stmt->get_result();

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
                    <th class="r" style="width: 14mm;">Rang</th>
                    <th>Waffe</th>
                  </tr></thead><tbody>';

        if (empty($schuetzen)) {
            $html .= '<tr><td colspan="2" class="muted">Keine Schützen gefunden.</td></tr>';
        } else {
            foreach ($schuetzen as $schuetze) {
                $html .= pdf_rangliste_zeile((int)$schuetze['Rang']);
                $html .= '<td class="rang">' . $schuetze['Rang'] . '.</td>';
                
                // Add "(Gross)" for first rank, "(Klein)" for others
                $waffe_text = htmlspecialchars($schuetze['Waffe']);
                if ($schuetze['Rang'] == 1) {
                    $waffe_text .= ' (Gross)';
                } else {
                    $waffe_text .= ' (Klein)';
                }
                
                $html .= '<td>' . $waffe_text . '</td>';
                $html .= '</tr>';
            }
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
}
?>
