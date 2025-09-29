<?php
// generate_pdf_zwischenrangliste.php
if (!defined('DB_HOST')) {
    include '../config.php';
}
require_once 'ComplexReports.php';
$report = new ZwischenRanglisteReport($conn, $_GET['year'] ?? null);
$report->generate();
?>