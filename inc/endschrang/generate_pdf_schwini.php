<?php
// generate_pdf_schwini.php
if (!defined('DB_HOST')) {
    include '../config.php';
}
require_once 'PDFReports.php';
$report = new SchwiniReport($conn, $_GET['year'] ?? null);
$report->generate();
?>