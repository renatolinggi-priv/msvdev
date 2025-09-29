<?php
/**
 * generate_wanderpreise_jahresreport.php
 * Zentrale Datei für alle Wanderpreise-Reports
 * Nutzt die Klassen aus PDFReports.php
 */

require_once '../dbconnect.inc.php';
require_once 'PDFReports.php';

// Datenbankverbindung herstellen
$conn = get_db_connection();
if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// Parameter auslesen
$reportType = $_GET['type'] ?? 'jahresreport';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$hersteller = $_GET['hersteller'] ?? null;

// Hersteller validieren (nur die beiden erlaubten Werte)
$allowed_hersteller = ['Schnitzerei Heinz Schild', 'Akura Einsiedeln'];
if ($hersteller && !in_array($hersteller, $allowed_hersteller)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Ungültiger Hersteller angegeben']);
    exit;
}

try {
    switch ($reportType) {
        case 'jahresreport':
            // Spezialbehandlung für Akura Gravur-Auftrag
            if ($hersteller === 'Akura Einsiedeln') {
                $report = new AkuraGravurReport($conn, $year);
            } else {
                // Standard Jahresreport (alle oder gefiltert nach Hersteller)
                $report = new WanderpreiseJahresReport($conn, $year, $hersteller);
            }
            $report->generate();
            break;
            
        case 'historie':
            // Historie für einzelnen Wanderpreis
            $wanderpreis_id = $_GET['wanderpreis_id'] ?? null;
            if (!$wanderpreis_id) {
                throw new Exception('Wanderpreis-ID ist erforderlich für den Historie-Report');
            }
            $report = new WanderpreisHistorieReport($conn, $wanderpreis_id, $year);
            $report->generate();
            break;
            
        case 'gewinner':
            // Alle Gewinner eines Jahres
            $report = new WanderpreisReport($conn, $year);
            $report->generate();
            break;
            
        case 'top3':
            // Top 3 Schützen Report
            $report = new Top3SchuetzenReport($conn, $year);
            $report->generate();
            break;
            
        default:
            throw new Exception('Unbekannter Report-Typ: ' . $reportType);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>