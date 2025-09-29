<?php
// generate_pdf_zabig.php
if (!defined('DB_HOST')) {
    include '../config.php';
}
require_once 'PDFReports.php';
$report = new ZabigReport($conn, $_GET['year'] ?? null);
$report->generate();
?>