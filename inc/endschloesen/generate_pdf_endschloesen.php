<?php
// generate_pdf_endschloesen.php - PDF-Report für Endschiessen Stiche und Munition

require_once '../wanderpreise/PDFGenerator.php';

/**
 * Endschiessen Auswertungs-Report
 * Zeigt alle erfassten Stiche und bestellte Munition für ein Jahr
 */
class EndschloesenReport extends PDFGenerator {
    
    public function __construct($conn, $year = null) {
        parent::__construct($conn, $year);
    }
    
    /**
     * Generiert den Endschiessen-Report
     */
    public function generate() {
        $title = 'Endschiessen Auswertung ' . $this->selectedYear;
        
        // Custom CSS für Querformat-Tabelle
        $customStyles = $this->getCustomStyles();
        
        // HTML Header mit angepasster Schriftgröße für bessere Lesbarkeit
        $html = $this->createHTMLHeader($title, $customStyles, 10);
        $html .= '<h1>' . $title . '</h1>';
        $html .= '<h2>MSV Wilen</h2>';
        
        // Hole Stich-Definitionen
        $stiche = $this->getStichDefinitionen();
        
        // Hole Daten für das Jahr
        $data = $this->getYearData();
        
        if (empty($data)) {
            $html .= '<p>Keine Daten für das Jahr ' . $this->selectedYear . ' erfasst.</p>';
        } else {
            // Erstelle die Haupttabelle
            $html .= $this->createMainTable($stiche, $data);
            
            // Legende
            $html .= '<div style="margin: 10px 0; font-size: 9pt;">';
            $html .= '<strong>Legende:</strong> ';
            $html .= '<span style="color: #28a745; font-weight: bold;">X</span> = Stich gelöst | ';
            $html .= '<span style="color: #007bff; font-weight: bold; background-color: #e7f3ff; padding: 2px 4px;">P</span> = Partner-Stich (Zabig mit Partner, CHF 10.00)';
            $html .= '</div>';
            
            // Statistik-Box
            $html .= $this->createStatisticsBox($stiche, $data);
        }
        
        $html .= $this->createHTMLFooter();
        
        // PDF generieren im Querformat
        $filename = 'Endschiessen_Auswertung_' . $this->selectedYear;
        $pdfPath = $this->generatePDF($html, $filename, 'landscape');
        $this->outputDownloadLink($pdfPath);
    }
    
    /**
     * Holt alle aktiven Stich-Definitionen
     */
    private function getStichDefinitionen() {
        $sql = "SELECT id, code, name, shots, price_cents, sort_order 
                FROM endstich_definition 
                WHERE active = 1 
                ORDER BY sort_order, name";
        
        $result = $this->conn->query($sql);
        
        $stiche = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $stiche[] = $row;
            }
        }
        
        return $stiche;
    }
    
    /**
     * Holt alle Daten für das Jahr
     */
    private function getYearData() {
        $jahr = (int)$this->selectedYear;
        
        // Hole alle Mitglieder die entweder Stiche ODER Munition haben
        $sql = "SELECT DISTINCT 
                m.ID as mitglied_id,
                CONCAT(m.Name, ' ', m.Vorname) as name,
                'mitglied' as typ
            FROM mitglieder m
            WHERE m.ID IN (
                SELECT DISTINCT mitglied_id FROM endstich_selection WHERE jahr = ? AND mitglied_id IS NOT NULL
                UNION
                SELECT DISTINCT mitglied_id FROM endstich_zusatz_schuss WHERE jahr = ? AND mitglied_id IS NOT NULL
            )
            ORDER BY m.Name, m.Vorname";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $jahr, $jahr);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $details = [];
        $mitglied_ids = [];
        
        while ($row = $result->fetch_assoc()) {
            $row['stiche'] = [];
            $row['partner_stiche'] = [];  // NEU: Array für Partner-Stiche
            $row['zusatz_schuesse'] = [];
            $row['total_shots'] = 0;
            $row['total_price'] = 0;
            $details[$row['mitglied_id']] = $row;
            $mitglied_ids[] = $row['mitglied_id'];
        }
        
        // Hole die Stiche für diese Mitglieder
        if (!empty($mitglied_ids)) {
            $placeholders = implode(',', array_fill(0, count($mitglied_ids), '?'));
            
            // Prüfe ob sie_und_er Spalte existiert
            $col_check = $this->conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'sie_und_er'");
            $has_sie_und_er = $col_check->num_rows > 0;
            
            // Stiche holen - mit sie_und_er Information wenn verfügbar
            if ($has_sie_und_er) {
                $sql = "SELECT 
                        es.mitglied_id,
                        es.stich_id,
                        es.sie_und_er,
                        ed.shots,
                        ed.price_cents,
                        ed.code
                    FROM endstich_selection es
                    JOIN endstich_definition ed ON es.stich_id = ed.id
                    WHERE es.mitglied_id IN ($placeholders) AND es.jahr = ?";
            } else {
                $sql = "SELECT 
                        es.mitglied_id,
                        es.stich_id,
                        0 as sie_und_er,
                        ed.shots,
                        ed.price_cents,
                        ed.code
                    FROM endstich_selection es
                    JOIN endstich_definition ed ON es.stich_id = ed.id
                    WHERE es.mitglied_id IN ($placeholders) AND es.jahr = ?";
            }
            
            $stmt = $this->conn->prepare($sql);
            $types = str_repeat('i', count($mitglied_ids)) . 'i';
            $params = array_merge($mitglied_ids, [$jahr]);
            
            $bind_params = [$types];
            foreach ($params as $key => $value) {
                $bind_params[] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_params);
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $mid = $row['mitglied_id'];
                if (isset($details[$mid])) {
                    $details[$mid]['stiche'][] = (int)$row['stich_id'];
                    $details[$mid]['total_shots'] += $row['shots'];
                    
                    // Berechne Preis basierend auf sie_und_er
                    $preis = $row['price_cents'];
                    if ($row['code'] === 'ZABIG' && $row['sie_und_er'] == 1) {
                        $preis = 1000; // CHF 10.00 für Partner
                        // Füge zu Partner-Stiche Array hinzu
                        $details[$mid]['partner_stiche'][] = (int)$row['stich_id'];
                    }
                    $details[$mid]['total_price'] += $preis;
                }
            }
            
            // Zusätzliche Schüsse holen
            $sql = "SELECT mitglied_id, typ, anzahl, preis_cents 
                    FROM endstich_zusatz_schuss 
                    WHERE mitglied_id IN ($placeholders) AND jahr = ?";
            
            $stmt = $this->conn->prepare($sql);
            
            // Wieder die gleichen Parameter verwenden
            $bind_params = [$types];
            foreach ($params as $key => $value) {
                $bind_params[] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_params);
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $mid = $row['mitglied_id'];
                if (isset($details[$mid])) {
                    $details[$mid]['zusatz_schuesse'][] = [
                        'typ' => $row['typ'],
                        'anzahl' => (int)$row['anzahl'],
                        'preis_cents' => (int)$row['preis_cents']
                    ];
                    // Addiere zum Gesamtpreis
                    $details[$mid]['total_price'] += $row['preis_cents'];
                }
            }
        }
        
        // Jetzt hole auch die Gäste
        $sql = "SELECT DISTINCT 
                g.id as gast_id,
                CONCAT(g.name, ' (Gast)') as name,
                'gast' as typ
            FROM endstich_gaeste g
            WHERE g.jahr = ?
            AND g.id IN (
                SELECT DISTINCT gast_id FROM endstich_selection WHERE jahr = ? AND gast_id IS NOT NULL
                UNION
                SELECT DISTINCT gast_id FROM endstich_zusatz_schuss WHERE jahr = ? AND gast_id IS NOT NULL
            )
            ORDER BY g.name";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iii", $jahr, $jahr, $jahr);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $gaeste = [];
        $gast_ids = [];
        
        while ($row = $result->fetch_assoc()) {
            $row['stiche'] = [];
            $row['partner_stiche'] = [];  // NEU: Auch für Gäste (bleibt aber leer)
            $row['zusatz_schuesse'] = [];
            $row['total_shots'] = 0;
            $row['total_price'] = 0;
            $gaeste[$row['gast_id']] = $row;
            $gast_ids[] = $row['gast_id'];
        }
        
        // Hole Stiche und Munition für Gäste
        if (!empty($gast_ids)) {
            $placeholders = implode(',', array_fill(0, count($gast_ids), '?'));
            
            // Stiche holen
            $sql = "SELECT 
                    es.gast_id,
                    es.stich_id,
                    ed.shots,
                    ed.price_cents
                FROM endstich_selection es
                JOIN endstich_definition ed ON es.stich_id = ed.id
                WHERE es.gast_id IN ($placeholders) AND es.jahr = ?";
            
            $stmt = $this->conn->prepare($sql);
            $types = str_repeat('i', count($gast_ids)) . 'i';
            $params = array_merge($gast_ids, [$jahr]);
            
            $bind_params = [$types];
            foreach ($params as $key => $value) {
                $bind_params[] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_params);
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $gid = $row['gast_id'];
                if (isset($gaeste[$gid])) {
                    $gaeste[$gid]['stiche'][] = (int)$row['stich_id'];
                    $gaeste[$gid]['total_shots'] += $row['shots'];
                    $gaeste[$gid]['total_price'] += $row['price_cents'];
                }
            }
            
            // Zusätzliche Schüsse holen
            $sql = "SELECT gast_id, typ, anzahl, preis_cents 
                    FROM endstich_zusatz_schuss 
                    WHERE gast_id IN ($placeholders) AND jahr = ?";
            
            $stmt = $this->conn->prepare($sql);
            
            // Wieder die gleichen Parameter verwenden
            $bind_params = [$types];
            foreach ($params as $key => $value) {
                $bind_params[] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_params);
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $gid = $row['gast_id'];
                if (isset($gaeste[$gid])) {
                    $gaeste[$gid]['zusatz_schuesse'][] = [
                        'typ' => $row['typ'],
                        'anzahl' => (int)$row['anzahl'],
                        'preis_cents' => (int)$row['preis_cents']
                    ];
                    // Addiere zum Gesamtpreis
                    $gaeste[$gid]['total_price'] += $row['preis_cents'];
                }
            }
        }
        
        // Kombiniere Mitglieder und Gäste - Mitglieder zuerst, dann Gäste
        $all_data = array_values($details);
        $all_data = array_merge($all_data, array_values($gaeste));
        
        // Konvertiere zurück zu indexed array
        return $all_data;
    }
    
    /**
     * Erstellt die Haupttabelle
     */
    private function createMainTable($stiche, $data) {
        $html = '<table class="table main-table">';
        $html .= '<thead><tr>';
        $html .= '<th class="name-col">Mitglied</th>';
        
        // Spalten für jeden Stich
        foreach ($stiche as $stich) {
            $html .= '<th class="stich-col" title="' . htmlspecialchars($stich['name']) . '">';
            $html .= htmlspecialchars($this->shortenStichName($stich['name']));
            $html .= '</th>';
        }
        
        // Munition und Total Spalten
        $html .= '<th class="munition-col">GP11</th>';
        $html .= '<th class="munition-col">GP90</th>';
        $html .= '<th class="total-col">Total CHF</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($data as $entry) {
            $html .= '<tr>';
            
            // Mitglied Name
            $html .= '<td class="name-cell">' . htmlspecialchars($entry['name']) . '</td>';
            
            // Stich-Checkmarks
            foreach ($stiche as $stich) {
                $stichId = (int)$stich['id'];
                if (in_array($stichId, $entry['stiche'])) {
                    // Prüfe ob es ein Partner-Stich ist (Zabig mit Partner)
                    $isPartner = isset($entry['partner_stiche']) && in_array($stichId, $entry['partner_stiche']);
                    
                    if ($isPartner && $stich['code'] === 'ZABIG') {
                        // Verwende P für Partner (Sie und Er)
                        $html .= '<td class="check-cell partner-cell" title="Partner"><strong>P</strong></td>';
                    } else {
                        // Verwende ein einfaches X als Markierung
                        $html .= '<td class="check-cell"><strong>X</strong></td>';
                    }
                } else {
                    $html .= '<td class="check-cell"></td>';
                }
            }
            
            // Munition (GP11 und GP90 getrennt)
            $gp11Total = 0;
            $gp90Total = 0;
            
            foreach ($entry['zusatz_schuesse'] as $z) {
                $anzahl = $z['anzahl'];
                if ($z['typ'] === 'GP11_60' || $z['typ'] === 'GP11_CUSTOM') {
                    $gp11Total += $anzahl;
                } else if ($z['typ'] === 'GP90_50' || $z['typ'] === 'GP90_CUSTOM') {
                    $gp90Total += $anzahl;
                }
            }
            
            $html .= '<td class="munition-cell">' . ($gp11Total > 0 ? $gp11Total : '-') . '</td>';
            $html .= '<td class="munition-cell">' . ($gp90Total > 0 ? $gp90Total : '-') . '</td>';
            
            // Total
            $html .= '<td class="total-cell">' . number_format($entry['total_price'] / 100, 2, '.', '') . '</td>';
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Erstellt die Statistik-Box
     */
    private function createStatisticsBox($stiche, $data) {
        // Berechne Statistiken
        $totalMitglieder = count($data);
        $totalEinnahmen = 0;
        $stichStatistik = [];
        $gp11Gesamt = 0;
        $gp90Gesamt = 0;
        
        // Initialisiere Stich-Statistik
        foreach ($stiche as $stich) {
            $stichStatistik[$stich['id']] = [
                'name' => $stich['name'],
                'count' => 0,
                'shots' => $stich['shots'],
                'price' => $stich['price_cents']
            ];
        }
        
        // Sammle Daten
        $partnerCount = 0; // Zähle Partner-Stiche separat
        
        foreach ($data as $entry) {
            $totalEinnahmen += $entry['total_price'];
            
            // Zähle Stiche
            foreach ($entry['stiche'] as $stichId) {
                if (isset($stichStatistik[$stichId])) {
                    // Prüfe ob es ein Partner-Stich ist
                    $isPartner = isset($entry['partner_stiche']) && in_array($stichId, $entry['partner_stiche']);
                    
                    if ($isPartner) {
                        $partnerCount++;
                    } else {
                        $stichStatistik[$stichId]['count']++;
                    }
                }
            }
            
            // Zähle Munition
            foreach ($entry['zusatz_schuesse'] as $z) {
                if ($z['typ'] === 'GP11_60' || $z['typ'] === 'GP11_CUSTOM') {
                    $gp11Gesamt += $z['anzahl'];
                } else if ($z['typ'] === 'GP90_50' || $z['typ'] === 'GP90_CUSTOM') {
                    $gp90Gesamt += $z['anzahl'];
                }
            }
        }
        
        $html = '<div class="statistics-box">';
        $html .= '<h3>Zusammenfassung</h3>';
        
        // Allgemeine Statistik
        $html .= '<div class="stat-row">';
        $html .= '<div class="stat-item">';
        $html .= '<strong>Anzahl Teilnehmer:</strong> ' . $totalMitglieder;
        $html .= '</div>';
        $html .= '<div class="stat-item">';
        $html .= '<strong>Gesamteinnahmen:</strong> CHF ' . number_format($totalEinnahmen / 100, 2, '.', '\'');
        $html .= '</div>';
        $html .= '</div>';
        
        // Stich-Statistik
        $html .= '<h4>Stiche</h4>';
        $html .= '<table class="stat-table">';
        $html .= '<thead><tr>';
        $html .= '<th>Stich</th>';
        $html .= '<th>Anzahl</th>';
        $html .= '<th>Schuss Total</th>';
        $html .= '<th>Einnahmen CHF</th>';
        $html .= '</tr></thead><tbody>';
        
        $gesamtSchuss = 0;
        foreach ($stichStatistik as $stichId => $stat) {
            if ($stat['count'] > 0 || ($stat['name'] === 'Zabig' && $partnerCount > 0)) {
                // Berechne normale Stiche
                $schussTotal = $stat['count'] * $stat['shots'];
                $einnahmen = $stat['count'] * $stat['price'];
                
                // Zeige Zabig mit Partner-Info
                if ($stat['name'] === 'Zabig' && $partnerCount > 0) {
                    // Zeile für normale Zabig
                    if ($stat['count'] > 0) {
                        $html .= '<tr>';
                        $html .= '<td>' . htmlspecialchars($stat['name']) . ' (Normal)</td>';
                        $html .= '<td class="text-center">' . $stat['count'] . '</td>';
                        $html .= '<td class="text-center">' . $schussTotal . '</td>';
                        $html .= '<td class="text-right">' . number_format($einnahmen / 100, 2, '.', '') . '</td>';
                        $html .= '</tr>';
                        $gesamtSchuss += $schussTotal;
                    }
                    
                    // Zeile für Partner-Zabig
                    $partnerSchuss = $partnerCount * $stat['shots'];
                    $partnerEinnahmen = $partnerCount * 1000; // CHF 10.00 pro Partner
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($stat['name']) . ' <span style="color: #007bff;">(Partner)</span></td>';
                    $html .= '<td class="text-center">' . $partnerCount . '</td>';
                    $html .= '<td class="text-center">' . $partnerSchuss . '</td>';
                    $html .= '<td class="text-right">' . number_format($partnerEinnahmen / 100, 2, '.', '') . '</td>';
                    $html .= '</tr>';
                    $gesamtSchuss += $partnerSchuss;
                } else if ($stat['count'] > 0) {
                    // Normale Stiche (nicht Zabig)
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($stat['name']) . '</td>';
                    $html .= '<td class="text-center">' . $stat['count'] . '</td>';
                    $html .= '<td class="text-center">' . $schussTotal . '</td>';
                    $html .= '<td class="text-right">' . number_format($einnahmen / 100, 2, '.', '') . '</td>';
                    $html .= '</tr>';
                    $gesamtSchuss += $schussTotal;
                }
            }
        }
        
        $html .= '</tbody></table>';
        
        // Munitions-Statistik
        if ($gp11Gesamt > 0 || $gp90Gesamt > 0) {
            $html .= '<h4>Bestellte Munition</h4>';
            $html .= '<div class="stat-row">';
            
            if ($gp11Gesamt > 0) {
                $html .= '<div class="stat-item">';
                $html .= '<strong>GP11:</strong> ' . $gp11Gesamt . ' Schuss (CHF ' . number_format($gp11Gesamt * 0.5, 2, '.', '') . ')';
                $html .= '</div>';
            }
            
            if ($gp90Gesamt > 0) {
                $html .= '<div class="stat-item">';
                $html .= '<strong>GP90:</strong> ' . $gp90Gesamt . ' Schuss (CHF ' . number_format($gp90Gesamt * 0.5, 2, '.', '') . ')';
                $html .= '</div>';
            }
            
            $html .= '<div class="stat-item">';
            $munitionGesamt = $gp11Gesamt + $gp90Gesamt;
            $html .= '<strong>Total Munition:</strong> ' . $munitionGesamt . ' Schuss';
            $html .= '</div>';
            
            $html .= '</div>';
        }
        
        // Gesamt-Schuss
        $html .= '<div class="stat-row highlight">';
        $html .= '<strong>Gesamt-Schuss (Stiche + Munition):</strong> ' . ($gesamtSchuss + $gp11Gesamt + $gp90Gesamt);
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Kürzt lange Stich-Namen für die Tabellen-Header
     */
    private function shortenStichName($name) {
        // Kürze bekannte lange Namen
        $replacements = [
            'Schwini Passe' => 'Schwini P',
            'Differenzler' => 'Diff.',
            'Sie und Er' => 'Sie+Er'
        ];
        
        foreach ($replacements as $search => $replace) {
            $name = str_replace($search, $replace, $name);
        }
        
        // Wenn immer noch zu lang, kürze auf max. 12 Zeichen
        if (strlen($name) > 12) {
            return substr($name, 0, 10) . '...';
        }
        
        return $name;
    }
    
    /**
     * Gibt das benutzerdefinierte CSS für den Report zurück
     */
    private function getCustomStyles() {
        return '
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 9pt;
            }
            
            h1 {
                color: #003366;
                font-size: 18pt;
                margin: 10px 0;
                text-align: center;
            }
            
            h2 {
                color: #003366;
                font-size: 14pt;
                margin: 5px 0 20px 0;
                text-align: center;
            }
            
            h3 {
                color: #003366;
                font-size: 12pt;
                margin: 15px 0 10px 0;
                border-bottom: 1px solid #003366;
                padding-bottom: 5px;
            }
            
            h4 {
                color: #003366;
                font-size: 10pt;
                margin: 10px 0 5px 0;
            }
            
            .main-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                font-size: 8pt;
            }
            
            .main-table th {
                background-color: #003366;
                color: white;
                padding: 5px 3px;
                text-align: center;
                font-weight: bold;
                border: 1px solid #003366;
            }
            
            .main-table td {
                padding: 3px;
                border: 1px solid #ddd;
            }
            
            .main-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            
            .name-col {
                width: 20%;
                text-align: left !important;
            }
            
            .name-cell {
                text-align: left;
                font-weight: bold;
                padding-left: 5px !important;
            }
            
            .stich-col {
                width: 6%;
                font-size: 7pt;
            }
            
            .check-cell {
                text-align: center;
                color: #28a745;
                font-weight: bold;
                font-size: 10pt;
            }
            
            .partner-cell {
                color: #007bff !important;
                background-color: #e7f3ff;
            }
            
            .munition-col {
                width: 5%;
                background-color: #e8f4f8;
            }
            
            .munition-cell {
                text-align: center;
                background-color: #e8f4f8;
            }
            
            .total-col {
                width: 8%;
                background-color: #fff3cd;
            }
            
            .total-cell {
                text-align: right;
                padding-right: 5px !important;
                font-weight: bold;
                background-color: #fff3cd;
            }
            
            .statistics-box {
                margin-top: 30px;
                padding: 15px;
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                page-break-inside: avoid;
            }
            
            .stat-row {
                display: table;
                width: 100%;
                margin: 10px 0;
            }
            
            .stat-item {
                display: table-cell;
                padding: 5px 15px;
            }
            
            .stat-row.highlight {
                background-color: #fff3cd;
                padding: 10px;
                margin-top: 15px;
                font-size: 11pt;
            }
            
            .stat-table {
                width: 60%;
                border-collapse: collapse;
                margin: 10px 0;
            }
            
            .stat-table th {
                background-color: #e9ecef;
                padding: 5px;
                text-align: left;
                border: 1px solid #dee2e6;
            }
            
            .stat-table td {
                padding: 5px;
                border: 1px solid #dee2e6;
            }
            
            .text-center {
                text-align: center;
            }
            
            .text-right {
                text-align: right;
            }
        ';
    }
}

// Handler für AJAX-Request
if (isset($_GET['action']) && $_GET['action'] === 'generate_pdf') {
    try {
        // Database connection
        require_once '../dbconnect.inc.php';
        
        if (!isset($conn)) {
            throw new Exception('Datenbankverbindung nicht verfügbar');
        }
        
        $year = isset($_GET['jahr']) ? (int)$_GET['jahr'] : date('Y');
        
        $report = new EndschloesenReport($conn, $year);
        $report->generate();
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
