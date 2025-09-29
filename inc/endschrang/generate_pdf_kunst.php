<?php
// generate_pdf_kunst.php
if (!defined('DB_HOST')) {
    include '../config.php';
}
require_once 'PDFReports.php';
$report = new KunstReport($conn, $_GET['year'] ?? null);
$report->generate();
?>