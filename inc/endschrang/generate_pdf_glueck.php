<?php
// generate_pdf_glueck.php
if (!defined('DB_HOST')) {
    include '../config.php';
}
require_once 'PDFReports.php';
$report = new GlueckReport($conn, $_GET['year'] ?? null);
$report->generate();
?>