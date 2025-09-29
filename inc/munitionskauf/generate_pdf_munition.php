<?php
// generate_pdf_munition.php - PDF-Report für Munitionsbestellungen

require_once '../wanderpreise/PDFGenerator.php';

/**
 * Munitionskauf Auswertungs-Report
 * Zeigt alle Munitionsbestellungen mit Kaufdatum für ein Jahr
 */
class MunitionskaufReport extends PDFGenerator {
    
    private $filter;
    private $filterText;
    
    public function __construct($conn, $year = null, $filter = 'year') {
        parent::__construct($conn, $year);
        $this->filter = $filter;
        
        // Set filter text for display
        switch ($filter) {
            case 'today':
                $this->filterText = 'Heute (' . date('d.m.Y') . ')';
                break;
            case 'week':
                $this->filterText = 'Diese Woche (KW ' . date('W') . ')';
                break;
            case 'month':
                $this->filterText = strftime('%B %Y');
                break;
            case 'year':
                $this->filterText = 'Ganzes Jahr ' . $year;
                break;
            default:
                $this->filterText = '';
        }
    }
    
    /**
     * Generiert den Munitionskauf-Report
     */
    public function generate() {
        $title = 'Munitionsverkauf ' . $this->selectedYear;
        if ($this->filter !== 'year') {
            $title .= ' - ' . $this->filterText;
        }
        
        // Custom CSS für den Report
        $customStyles = $this->getCustomStyles();
        
        // HTML Header
        $html = $this->createHTMLHeader($title, $customStyles, 10);
        $html .= '<h2>' . $title . '</h2>';
        
        // Hole Daten
        $bestellungen = $this->getBestellungen();
        $totals = $this->getTotals();
        $statistics = $this->getStatistics();
        
        if (empty($bestellungen)) {
            $html .= '<p>Keine Bestellungen für ' . $this->filterText . ' gefunden.</p>';
        } else {
            // Erstelle die Haupttabelle mit Kaufdatum in erster Spalte
            $html .= $this->createMainTable($bestellungen, $totals);
            
            // Statistik-Box
            //$html .= $this->createStatisticsBox($statistics);
        }
        
        $html .= $this->createHTMLFooter();
        
        // PDF generieren
        $filename = 'Munitionsverkauf_' . $this->selectedYear . '_' . $this->filter . '_' . date('Ymd');
        $pdfPath = $this->generatePDF($html, $filename, 'portrait');
        $this->outputDownloadLink($pdfPath);
    }
    
    /**
     * Holt die Bestellungen basierend auf Filter
     */
    private function getBestellungen() {
        $jahr = (int)$this->selectedYear;
        
        // Build date filter
        $date_condition = '';
        $params = [$jahr];
        $types = 'i';
        $today = date('Y-m-d');
        
        switch ($this->filter) {
            case 'today':
                $date_condition = "AND mk.kauf_datum = ?";
                $params[] = $today;
                $types .= 's';
                break;
                
            case 'week':
                $week_start = date('Y-m-d', strtotime('monday this week'));
                $week_end = date('Y-m-d', strtotime('sunday this week'));
                $date_condition = "AND mk.kauf_datum BETWEEN ? AND ?";
                $params[] = $week_start;
                $params[] = $week_end;
                $types .= 'ss';
                break;
                
            case 'month':
                $month_start = date('Y-m-01');
                $month_end = date('Y-m-t');
                $date_condition = "AND mk.kauf_datum BETWEEN ? AND ?";
                $params[] = $month_start;
                $params[] = $month_end;
                $types .= 'ss';
                break;
                
            case 'year':
                // Jahr-Filter ist bereits in WHERE-Klausel
                break;
        }
        
        // Get bestellungen
        $sql = "SELECT mk.*, 
                COALESCE(CONCAT(m.Name, ' ', m.Vorname), mk.gast_name) as kaeufer_name
                FROM munitionskauf mk
                LEFT JOIN mitglieder m ON mk.mitglied_id = m.ID
                WHERE mk.jahr = ?
                $date_condition
                ORDER BY mk.kauf_datum DESC, mk.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        
        // Bind parameters dynamisch
        $bind_params = [$types];
        foreach ($params as $key => $value) {
            $bind_params[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $bestellungen = [];
        while ($row = $result->fetch_assoc()) {
            $bestellungen[] = $row;
        }
        
        return $bestellungen;
    }
    
    /**
     * Holt die Totals
     */
    private function getTotals() {
        $jahr = (int)$this->selectedYear;
        
        // Build date filter (gleich wie bei getBestellungen)
        $date_condition = '';
        $params = [$jahr];
        $types = 'i';
        $today = date('Y-m-d');
        
        switch ($this->filter) {
            case 'today':
                $date_condition = "AND kauf_datum = ?";
                $params[] = $today;
                $types .= 's';
                break;
                
            case 'week':
                $week_start = date('Y-m-d', strtotime('monday this week'));
                $week_end = date('Y-m-d', strtotime('sunday this week'));
                $date_condition = "AND kauf_datum BETWEEN ? AND ?";
                $params[] = $week_start;
                $params[] = $week_end;
                $types .= 'ss';
                break;
                
            case 'month':
                $month_start = date('Y-m-01');
                $month_end = date('Y-m-t');
                $date_condition = "AND kauf_datum BETWEEN ? AND ?";
                $params[] = $month_start;
                $params[] = $month_end;
                $types .= 'ss';
                break;
        }
        
        $sql = "SELECT 
                SUM(gp11_total) as gp11_total,
                SUM(gp90_total) as gp90_total,
                SUM(total_preis) as total_preis
                FROM munitionskauf
                WHERE jahr = ?
                $date_condition";
        
        $stmt = $this->conn->prepare($sql);
        
        $bind_params = [$types];
        foreach ($params as $key => $value) {
            $bind_params[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
        
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Holt die Statistiken
     */
    private function getStatistics() {
        $jahr = (int)$this->selectedYear;
        $stats = [];
        
        // Today
        $today = date('Y-m-d');
        $sql = "SELECT COALESCE(SUM(total_preis), 0) as total 
                FROM munitionskauf 
                WHERE kauf_datum = ? AND jahr = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('si', $today, $jahr);
        $stmt->execute();
        $stats['today'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // This week
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        $sql = "SELECT COALESCE(SUM(total_preis), 0) as total 
                FROM munitionskauf 
                WHERE kauf_datum BETWEEN ? AND ? AND jahr = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssi', $week_start, $week_end, $jahr);
        $stmt->execute();
        $stats['week'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // This month
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        $sql = "SELECT COALESCE(SUM(total_preis), 0) as total 
                FROM munitionskauf 
                WHERE kauf_datum BETWEEN ? AND ? AND jahr = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ssi', $month_start, $month_end, $jahr);
        $stmt->execute();
        $stats['month'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // Year total
        $sql = "SELECT COALESCE(SUM(total_preis), 0) as total 
                FROM munitionskauf 
                WHERE jahr = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $jahr);
        $stmt->execute();
        $stats['year'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // Top buyers
        $sql = "SELECT 
                COALESCE(CONCAT(m.Name, ' ', m.Vorname), mk.gast_name) as name,
                SUM(mk.total_preis) as total
                FROM munitionskauf mk
                LEFT JOIN mitglieder m ON mk.mitglied_id = m.ID
                WHERE mk.jahr = ?
                GROUP BY mk.mitglied_id, mk.gast_name, m.Name, m.Vorname
                ORDER BY total DESC
                LIMIT 5";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $jahr);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stats['top_buyers'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['top_buyers'][] = $row;
        }
        
        return $stats;
    }
    
    /**
     * Erstellt die Haupttabelle mit Kaufdatum in erster Spalte
     */
    private function createMainTable($bestellungen, $totals) {
        $html = '<table class="table main-table">';
        $html .= '<thead><tr>';
        $html .= '<th class="date-col">Datum</th>';
        $html .= '<th class="name-col">Käufer</th>';
        $html .= '<th class="anlass-col">Anlass</th>';
        $html .= '<th class="munition-col">GP11</th>';
        $html .= '<th class="munition-col">GP90</th>';
        $html .= '<th class="price-col">Preis CHF</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($bestellungen as $b) {
            $datum = date('d.m.Y', strtotime($b['kauf_datum']));
            $kaeufer = htmlspecialchars($b['kaeufer_name']);
            $anlass = htmlspecialchars($b['anlass'] ?: '-');
            $gp11 = $b['gp11_total'] ?: '-';
            $gp90 = $b['gp90_total'] ?: '-';
            $preis = number_format($b['total_preis'] / 100, 2, '.', '');
            
            $html .= '<tr>';
            $html .= '<td class="date-cell">' . $datum . '</td>';
            $html .= '<td class="name-cell">' . $kaeufer . '</td>';
            $html .= '<td class="anlass-cell">' . $anlass . '</td>';
            $html .= '<td class="munition-cell">' . $gp11 . '</td>';
            $html .= '<td class="munition-cell">' . $gp90 . '</td>';
            $html .= '<td class="price-cell">' . $preis . '</td>';
            $html .= '</tr>';
        }
        
        // Totals row
        $html .= '</tbody><tfoot>';
        $html .= '<tr class="totals-row">';
        $html .= '<td colspan="3" class="total-label"><strong>TOTAL</strong></td>';
        $html .= '<td class="munition-cell"><strong>' . ($totals['gp11_total'] ?: '0') . '</strong></td>';
        $html .= '<td class="munition-cell"><strong>' . ($totals['gp90_total'] ?: '0') . '</strong></td>';
        $html .= '<td class="price-cell"><strong>' . number_format(($totals['total_preis'] ?: 0) / 100, 2, '.', '') . '</strong></td>';
        $html .= '</tr>';
        $html .= '</tfoot></table>';
        
        return $html;
    }
    
    /**
     * Erstellt die Statistik-Box
     */
    private function createStatisticsBox($stats) {
        $html = '<div class="statistics-box">';
        $html .= '<h3>Statistiken</h3>';
        
        $html .= '<div class="stat-row">';
        $html .= '<div class="stat-item">';
        $html .= '<strong>Heute:</strong><br>';
        $html .= 'CHF ' . number_format($stats['today'] / 100, 2, '.', '\'');
        $html .= '</div>';
        
        $html .= '<div class="stat-item">';
        $html .= '<strong>Diese Woche:</strong><br>';
        $html .= 'CHF ' . number_format($stats['week'] / 100, 2, '.', '\'');
        $html .= '</div>';
        
        $html .= '<div class="stat-item">';
        $html .= '<strong>Diesen Monat:</strong><br>';
        $html .= 'CHF ' . number_format($stats['month'] / 100, 2, '.', '\'');
        $html .= '</div>';
        
        $html .= '<div class="stat-item highlight">';
        $html .= '<strong>Jahrestotal:</strong><br>';
        $html .= 'CHF ' . number_format($stats['year'] / 100, 2, '.', '\'');
        $html .= '</div>';
        $html .= '</div>';
        
        // Top Käufer
        if (!empty($stats['top_buyers'])) {
            $html .= '<h4>Top 5 Käufer (' . $this->selectedYear . ')</h4>';
            $html .= '<table class="stat-table">';
            $html .= '<thead><tr>';
            $html .= '<th style="width: 50px;">Rang</th>';
            $html .= '<th>Name</th>';
            $html .= '<th style="width: 100px;">Total CHF</th>';
            $html .= '</tr></thead><tbody>';
            
            $i = 1;
            foreach ($stats['top_buyers'] as $buyer) {
                $html .= '<tr>';
                $html .= '<td class="text-center">' . $i . '.</td>';
                $html .= '<td>' . htmlspecialchars($buyer['name']) . '</td>';
                $html .= '<td class="text-right">' . number_format($buyer['total'] / 100, 2, '.', '') . '</td>';
                $html .= '</tr>';
                $i++;
            }
            
            $html .= '</tbody></table>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Gibt das benutzerdefinierte CSS für den Report zurück
     */
    private function getCustomStyles() {
        return '
            @page {
                size: A4 portrait;
                margin: 15mm;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 10pt;
            }
            
            h1 {
                color: #003366;
                font-size: 20pt;
                margin: 15px 0;
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
                font-size: 14pt;
                margin: 20px 0 10px 0;
                border-bottom: 2px solid #003366;
                padding-bottom: 5px;
            }
            
            h4 {
                color: #003366;
                font-size: 12pt;
                margin: 15px 0 10px 0;
            }
            
            .main-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                font-size: 9pt;
            }
            
            .main-table th {
                background-color: #003366;
                color: white;
                padding: 8px 5px;
                text-align: left;
                font-weight: bold;
                border: 1px solid #003366;
            }
            
            .main-table td {
                padding: 6px 5px;
                border: 1px solid #ddd;
            }
            
            .main-table tbody tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            
            .date-col {
                width: 15%;
            }
            
            .date-cell {
                text-align: center;
                font-weight: 600;
            }
            
            .name-col {
                width: 25%;
            }
            
            .name-cell {
                font-weight: 600;
            }
            
            .anlass-col {
                width: 25%;
            }
            
            .anlass-cell {
                font-style: italic;
                color: #666;
            }
            
            .munition-col {
                width: 10%;
                text-align: center;
                background-color: #e8f4f8;
            }
            
            .munition-cell {
                text-align: center;
                background-color: #e8f4f8;
            }
            
            .price-col {
                width: 15%;
                text-align: right;
                background-color: #fff3cd;
            }
            
            .price-cell {
                text-align: right;
                padding-right: 10px !important;
                font-weight: 600;
                background-color: #fff3cd;
            }
            
            .totals-row {
                background-color: #f0f0f0 !important;
                font-weight: bold;
            }
            
            .totals-row td {
                padding: 10px 5px;
                border-top: 2px solid #003366;
            }
            
            .total-label {
                text-align: right;
                padding-right: 15px !important;
            }
            
            .statistics-box {
                margin-top: 30px;
                padding: 20px;
                background-color: #f8f9fa;
                border: 2px solid #dee2e6;
                border-radius: 5px;
                page-break-inside: avoid;
            }
            
            .stat-row {
                display: table;
                width: 100%;
                margin: 15px 0;
            }
            
            .stat-item {
                display: table-cell;
                padding: 10px;
                text-align: center;
                border-right: 1px solid #dee2e6;
            }
            
            .stat-item:last-child {
                border-right: none;
            }
            
            .stat-item.highlight {
                background-color: #fff3cd;
                font-size: 12pt;
            }
            
            .stat-table {
                width: 70%;
                border-collapse: collapse;
                margin: 10px 0;
            }
            
            .stat-table th {
                background-color: #e9ecef;
                padding: 8px;
                text-align: left;
                border: 1px solid #dee2e6;
                font-weight: bold;
            }
            
            .stat-table td {
                padding: 6px 8px;
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
        $filter = isset($_GET['filter']) ? $_GET['filter'] : 'year';
        
        $report = new MunitionskaufReport($conn, $year, $filter);
        $report->generate();
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
