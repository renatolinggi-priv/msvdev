<?php
// copy_from_year.php – Übernimmt ausgewählte Anlässe (mit bereits verschobenem Datum
// und angepasstem Namen) ins Zieljahr. Fügt JMDefinition ein und befüllt JMSchiesstage
// direkt aus dem (kanonischen) Schiesstage-Text.
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

$targetYear = isset($_POST['target_year']) ? intval($_POST['target_year']) : 0;
$events = isset($_POST['events']) && is_array($_POST['events']) ? $_POST['events'] : [];

if ($targetYear < 2000 || $targetYear > 2100 || empty($events)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültige Daten']));
}

$conn->begin_transaction();
try {
    $res = $conn->query("SELECT MAX(Reihenfolge) AS m FROM JMDefinition WHERE year = " . $targetYear);
    $rowMax = $res ? $res->fetch_assoc() : null;
    $reihenfolge = (int)($rowMax['m'] ?? 0);

    $stmt = $conn->prepare("
        INSERT INTO JMDefinition (Reihenfolge, Bezeichnung, Maxpunkte, Streicher, Erweitert, Schiesstage, Info, Gruppe, hidden, year, Adresse, Zuschlag)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
    }
    $count = 0;
    $warnings = [];
    foreach ($events as $ev) {
        $reihenfolge++;
        $bezeichnung = trim((string)($ev['bezeichnung'] ?? ''));
        if ($bezeichnung === '') { continue; }
        $maxpunkte = intval($ev['maxpunkte'] ?? 0);
        $streicher = !empty($ev['streicher']) ? 1 : 0;
        $erweitert = !empty($ev['erweitert']) ? 1 : 0;
        $schiesstage = trim((string)($ev['schiesstage'] ?? ''));
        $info = !empty($ev['info']) ? 1 : 0;
        $gruppe = !empty($ev['gruppe']) ? 1 : 0;
        $hidden = 0;
        $adresse = trim((string)($ev['adresse'] ?? ''));
        $zuschlag = intval($ev['zuschlag'] ?? 0);

        $stmt->bind_param(
            "isiiisiiiisi",
            $reihenfolge, $bezeichnung, $maxpunkte, $streicher, $erweitert,
            $schiesstage, $info, $gruppe, $hidden, $targetYear, $adresse, $zuschlag
        );
        if (!$stmt->execute()) {
            throw new Exception('Insert fehlgeschlagen: ' . $stmt->error);
        }
        $jmId = $conn->insert_id;
        $count++;

        // JMSchiesstage aus dem (kanonischen) Schiesstage-Text befuellen – gemeinsamer Parser
        jm_schiesstage_replace($conn, $jmId, $schiesstage, $targetYear, $warnings);
    }
    $stmt->close();
    $conn->commit();

    $response = ['success' => true, 'count' => $count];
    if ($warnings) $response['warnings'] = array_keys($warnings);
    echo json_encode($response);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}

$conn->close();
