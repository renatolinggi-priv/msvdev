<?php
// generate_pdf_munition.php - PDF-Report für Munitionsbestellungen

require_once '../wanderpreise/PDFGenerator.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)

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
        // Ranglisten-Layout (Logo links, Titel, Fusszeile mit Seitenzahl); Zeitraum im Untertitel
        $untertitel = $this->filter === 'year' ? 'Ganzes Jahr' : $this->filterText;
        $html = $this->ranglisteHeader('Munitionsverkauf ' . $this->selectedYear, $untertitel, $this->getCustomStyles());
        
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
        $html = '<table class="table">';
        $html .= '<thead><tr>';
        $html .= '<th style="width: 24mm;">Datum</th>';
        $html .= '<th>Käufer</th>';
        $html .= '<th>Anlass</th>';
        $html .= '<th class="r" style="width: 18mm;">GP11</th>';
        $html .= '<th class="r" style="width: 18mm;">GP90</th>';
        $html .= '<th class="r" style="width: 24mm;">Preis CHF</th>';
        $html .= '</tr></thead><tbody>';

        $strich = '<span class="muted">–</span>';
        foreach ($bestellungen as $b) {
            $datum = date('d.m.Y', strtotime($b['kauf_datum']));
            $kaeufer = htmlspecialchars((string)$b['kaeufer_name']);
            $anlass = $b['anlass'] ? htmlspecialchars($b['anlass']) : $strich;
            $gp11 = $b['gp11_total'] ?: $strich;
            $gp90 = $b['gp90_total'] ?: $strich;
            $preis = number_format($b['total_preis'] / 100, 2, '.', '');

            $html .= '<tr>'
                . '<td>' . $datum . '</td>'
                . '<td>' . $kaeufer . '</td>'
                . '<td>' . $anlass . '</td>'
                . '<td class="r">' . $gp11 . '</td>'
                . '<td class="r">' . $gp90 . '</td>'
                . '<td class="r">' . $preis . '</td>'
                . '</tr>';
        }

        // Totalzeile mit Linie (wie die Abrechnungen im Endschiessen)
        $html .= '<tr class="rl-total">'
            . '<td colspan="3">Total</td>'
            . '<td class="r">' . ($totals['gp11_total'] ?: '0') . '</td>'
            . '<td class="r">' . ($totals['gp90_total'] ?: '0') . '</td>'
            . '<td class="r">' . number_format(($totals['total_preis'] ?: 0) / 100, 2, '.', '') . '</td>'
            . '</tr>';
        $html .= '</tbody></table>';
        
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
            .table { font-size: 10pt; }

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
        echo json_encode(['message' => msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e)]);
    }
}
?>
