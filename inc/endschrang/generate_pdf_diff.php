<?php
// generate_pdf_diff.php
if (!defined('DB_HOST')) {
    include '../config.php';
}
require_once 'PDFReports.php';
$report = new DifferenzlerReport($conn, $_GET['year'] ?? null);
$report->generate();
?>