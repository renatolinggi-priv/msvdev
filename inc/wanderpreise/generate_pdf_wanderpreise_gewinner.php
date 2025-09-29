<?php
/**
 * generate_pdf_wanderpreise_gewinner.php
 * Generiert den Wanderpreise Gewinner-Report
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

try {
    // Alle Gewinner eines Jahres
    $report = new WanderpreisReport($conn, $year);
    $report->generate();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>