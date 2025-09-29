<?php
// generate_pdf_partner.php
if (!defined('DB_HOST')) {
    include '../config.php';
}
require_once 'PDFReports.php';
$report = new PartnerRankingReport($conn, $_GET['year'] ?? null);
$report->generate();
?>