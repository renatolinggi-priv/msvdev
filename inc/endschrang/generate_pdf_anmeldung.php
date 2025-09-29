<?php
// generate_pdf_anmeldung.php
if (!defined('DB_HOST')) {
    include '../config.php';
}
require_once 'PDFReports.php';
$report = new AnmeldungReport($conn, $_GET['year'] ?? null);
$report->generate();
?>