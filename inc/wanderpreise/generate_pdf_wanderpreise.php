<?php
// generate_pdf_wanderpreise.php
require_once '../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once 'PDFReports.php';

$conn = get_db_connection();
if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

$report = new WanderpreisReport($conn, $_GET['year'] ?? date('Y'));
$report->generate();
?>