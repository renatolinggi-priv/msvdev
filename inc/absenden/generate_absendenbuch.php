<?php
/**
 * generate_absendenbuch.php — Absendenbuch (Resultatbüchlein) als Word-Dokument
 *
 * GET: year   Antwort: JSON {success, word_link: 'dat/…docx', display_name, hinweise: [Klartext …]}
 *             bzw. {success:false, leer:true, message} ohne Endstich-Resultate, {success:false, message} bei Fehlern
 * Die Befüllung der Vorlage liegt in absendenbuch_docx.inc.php (gemeinsam mit dem Broschüren-PDF).
 */
include '../config.php';                       // $conn + zentraler dat/-Cleanup
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/absendenbuch_docx.inc.php';

header('Content-Type: application/json; charset=utf-8');
$selectedYear = absendenbuchJahr();

try {
    if (!absendenbuchHatEndstich($conn, $selectedYear)) {
        msvAusgabeLeer('Für ' . $selectedYear . ' sind noch keine Endstich-Resultate erfasst. Ohne sie gibt es kein Absendenbuch.');
    }

    $hinweise = [];
    $templateProcessor = absendenbuchTemplateFuellen($selectedYear, $conn, $hinweise);

    // Dateiname mit Datum und Uhrzeit (Zeitstempel unmittelbar vor der Endung: zentraler dat/-Cleanup)
    $date = new DateTime();
    $filename = 'dat/Resultatbuechlein_' . $date->format('Y-m-d_H-i-s') . '.docx';
    $templateProcessor->saveAs(__DIR__ . '/' . $filename);

    // Stand fürs «Fürs Absenden» festhalten; scheitert das, ist das Buch trotzdem da
    try {
        absendenStandSpeichern($conn, $selectedYear, 'word', $filename);
    } catch (Throwable $e) {
        error_log('[absendenbuch] Stand nicht gespeichert: ' . $e->getMessage());
    }

    echo json_encode([
        'success'      => true,
        'word_link'    => $filename,
        'display_name' => basename($filename),
        'hinweise'     => $hinweise,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => msvFehler(
        'Das Absendenbuch konnte nicht erstellt werden. Bitte in ein paar Minuten nochmals versuchen und sonst dem Admin melden.', $e)],
        JSON_UNESCAPED_UNICODE);
}
$conn->close();
