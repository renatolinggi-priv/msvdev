<?php
/**
 * Generiert ein Sammel-PDF mit den JM-Standblättern aller aktiven Mitglieder (Download und
 * «Alle drucken»). Seit 1.68.49 ein einziges Dompdf-Dokument (standblatt_pdf.inc.php), vorher
 * pro Mitglied ein Aufruf beim PDF-Dienst und Zusammenfügen mit FPDI.
 * GET-Parameter: jahr
 * Übersprungen wird niemand mehr; ohne gültige Lizenz fehlt nur der Lizenz-Barcode. Die Header
 * X-Skipped / X-Skipped-Names, die das Frontend noch auswertet, fallen darum weg.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('plain');
require_once __DIR__ . '/../pdf/pdf_theme.php';
require_once __DIR__ . '/standblatt_pdf.inc.php';

$jahr = (int)($_GET['jahr'] ?? date('Y'));

if (!isset($conn)) {
    http_response_code(500);
    echo 'Datenbankverbindung fehlt';
    exit;
}

$result = $conn->query("SELECT ID, Vorname, Name FROM mitglieder WHERE Status = 1 AND Verstorben = 0 ORDER BY Name, Vorname");
if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    echo 'Keine aktiven Mitglieder gefunden';
    exit;
}

try {
    $nummern = sb_pdf_stichnummern($conn);
    $waffen  = sb_pdf_waffen($conn);
    $personen = [];
    while ($m = $result->fetch_assoc()) {
        $personen[] = sb_pdf_person($m, $jahr, $nummern, $waffen);
    }
    $pdf = sb_pdf_rendern(sb_pdf_dokument($personen, $jahr));
    sb_pdf_senden($pdf, 'JM_Standblaetter_' . $jahr . '_alle.pdf');
} catch (Throwable $e) {
    error_log('[jmstandblatt_all_pdf] Fehler: ' . $e->getMessage());
    http_response_code(500);
    echo msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e);
}
exit;
