<?php
// generate_pdf_sieer.php
if (!defined('DB_HOST')) {
    include '../config.php';
}
require_once 'PDFReports.php';
$report = new SieErReport($conn, $_GET['year'] ?? null);
$report->generate();
?>