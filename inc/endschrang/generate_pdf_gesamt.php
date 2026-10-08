<?php
// generate_pdf_gesamt.php
if (!defined('DB_HOST')) {
    include '../config.php';
    require_once __DIR__ . '/../admin_api_guard.inc.php';
    adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
}
require_once 'ComplexReports.php';
$report = new GesamtRanglisteReport($conn, $_GET['year'] ?? null);
$report->generate();
?>