<?php
// add_jmdefinition.php – legt einen neuen Anlass an und befuellt JMSchiesstage sofort
// (vorher erschienen die Termine erst nach einem Voll-Speichern im Kalender/Portal).
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/jmdefinition_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true);

$bezeichnung = trim((string)($_POST['bezeichnung'] ?? ''));
$adresse     = trim((string)($_POST['adresse'] ?? ''));
$maxpunkte   = (int)($_POST['maxpunkte'] ?? 0);
$streicher   = (int)($_POST['streicher'] ?? 0);
$erweitert   = (int)($_POST['erweitert'] ?? 0);
$info        = (int)($_POST['info'] ?? 0);
$gruppe      = (int)($_POST['gruppe'] ?? 0);
$zuschlag    = (int)($_POST['zuschlag'] ?? 0);
$schiesstage = trim((string)($_POST['schiesstage'] ?? ''));
$year        = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
$hidden      = 0;

if ($bezeichnung === '') {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Bitte eine Bezeichnung angeben']));
}
if ($year < 2000 || $year > 2100) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültiges Jahr']));
}

$conn->begin_transaction();
try {
    $stmtMax = $conn->prepare("SELECT COALESCE(MAX(Reihenfolge), 0) + 1 AS next FROM JMDefinition WHERE year = ?");
    $stmtMax->bind_param('i', $year);
    $stmtMax->execute();
    $neueReihenfolge = (int)$stmtMax->get_result()->fetch_assoc()['next'];
    $stmtMax->close();

    $stmtInsert = $conn->prepare("
        INSERT INTO JMDefinition (Reihenfolge, Bezeichnung, Maxpunkte, Streicher, Erweitert, Schiesstage, Info, Gruppe, hidden, year, Adresse, Zuschlag)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmtInsert) {
        throw new Exception('Prepare (Insert) fehlgeschlagen: ' . $conn->error);
    }
    $stmtInsert->bind_param('isiiisiiiisi', $neueReihenfolge, $bezeichnung, $maxpunkte, $streicher, $erweitert, $schiesstage, $info, $gruppe, $hidden, $year, $adresse, $zuschlag);
    if (!$stmtInsert->execute()) {
        throw new Exception('Anlass konnte nicht angelegt werden: ' . $stmtInsert->error);
    }
    $newId = (int)$conn->insert_id;
    $stmtInsert->close();

    $warnings = [];
    jm_schiesstage_replace($conn, $newId, $schiesstage, $year, $warnings);

    $conn->commit();

    $response = ['success' => true, 'message' => 'Anlass hinzugefügt', 'id' => $newId];
    if ($warnings) $response['warnings'] = array_keys($warnings);
    echo json_encode($response);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[add_jmdefinition] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}

$conn->close();
