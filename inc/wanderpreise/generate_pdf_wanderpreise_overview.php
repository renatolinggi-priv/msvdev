<?php
/**
 * generate_pdf_wanderpreise_overview.php
 * Generiert den Wanderpreise Jahresbericht
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
$hersteller = $_GET['hersteller'] ?? null;

// Hersteller validieren (nur die beiden erlaubten Werte)
$allowed_hersteller = ['Schnitzerei Heinz Schild', 'Akura Einsiedeln'];
if ($hersteller && !in_array($hersteller, $allowed_hersteller)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Ungültiger Hersteller angegeben']);
    exit;
}

try {
    // Spezialbehandlung für Akura Gravur-Auftrag
    if ($hersteller === 'Akura Einsiedeln') {
        $report = new AkuraGravurReport($conn, $year);
    } else {
        // Standard Jahresreport (alle oder gefiltert nach Hersteller)
        $report = new WanderpreiseJahresReport($conn, $year, $hersteller);
    }
    $report->generate();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $conn->close();
}
?>