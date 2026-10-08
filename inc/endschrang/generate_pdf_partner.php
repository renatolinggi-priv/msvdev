<?php
// generate_pdf_partner.php
if (!defined('DB_HOST')) {
    include '../config.php';
    require_once __DIR__ . '/../admin_api_guard.inc.php';
    adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
}
require_once 'PDFReports.php';
$report = new PartnerRankingReport($conn, $_GET['year'] ?? null);
$report->generate();
?>