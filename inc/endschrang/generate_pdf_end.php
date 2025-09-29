<?php
// generate_pdf_end.php
if (!defined('DB_HOST')) {
    include '../config.php';
}
require_once 'PDFReports.php';
$report = new EndstichReport($conn, $_GET['year'] ?? null);
$report->generate();
?>