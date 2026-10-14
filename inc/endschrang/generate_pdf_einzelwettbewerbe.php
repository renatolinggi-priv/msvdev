<?php
// generate_pdf_einzelwettbewerbe.php: alle Einzelwettbewerbe (Endstich bis Differenzler) in einem PDF
if (!defined('DB_HOST')) {
    include '../config.php';
    require_once __DIR__ . '/../admin_api_guard.inc.php';
    adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
}
require_once 'PDFReports.php';
$report = new EinzelwettbewerbeReport($conn, $_GET['year'] ?? null);
$report->generate();
?>