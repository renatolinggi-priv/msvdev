<?php
/**
 * generate_pdf_akura_gravur.php
 * Generiert den Akura Gravur-Auftrag PDF-Report
 * Nutzt die AkuraGravurReport Klasse aus PDFReports.php
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

// Jahr aus Parameter oder aktuelles Jahr
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

try {
    // Report generieren mit der neuen Klasse
    $report = new AkuraGravurReport($conn, $year);
    $report->generate();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>