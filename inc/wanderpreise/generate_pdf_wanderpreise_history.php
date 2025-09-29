<?php
/**
 * generate_pdf_wanderpreise_history.php
 * Generiert den Wanderpreise Historie-Report für einen einzelnen Wanderpreis
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
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$wanderpreis_id = $_GET['wanderpreis_id'] ?? null;

if (!$wanderpreis_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Wanderpreis-ID ist erforderlich für den Historie-Report']);
    exit;
}

try {
    // Historie für einzelnen Wanderpreis
    $report = new WanderpreisHistorieReport($conn, $wanderpreis_id, $year);
    $report->generate();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>