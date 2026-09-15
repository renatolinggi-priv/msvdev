<?php
// save_jminformation.php – Zusatztext (JMInformation) speichern.
// Wird von jmdefinition.php nicht mehr separat aufgerufen (laeuft in save_jmdefinition.php mit),
// bleibt als eigenstaendiger Endpoint bestehen. Pflegt genau EINE Zeile.
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/jmdefinition_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['zusatztext'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Kein Zusatztext übermittelt']));
}
csrf_require(true);

try {
    jm_information_save($conn, trim((string)$_POST['zusatztext']));
    echo json_encode(['success' => true, 'message' => 'Zusatztext gespeichert']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
