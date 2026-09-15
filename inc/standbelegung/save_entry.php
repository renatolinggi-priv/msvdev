<?php
// save_entry.php – einzelnen Standbelegung-Eintrag anlegen oder aktualisieren (JSON-Body)
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/standbelegung_config.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true); // Token kommt als Header X-CSRF-TOKEN ($.ajaxSetup) – bei JSON-Body ist $_POST leer

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Keine Daten empfangen']));
}

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$id          = !empty($input['id']) ? (int)$input['id'] : null;
$datum       = trim((string)($input['datum'] ?? ''));
$bezeichnung = trim((string)($input['bezeichnung'] ?? ''));
$startZeit   = trim((string)($input['start_zeit'] ?? ''));
$endZeit     = trim((string)($input['end_zeit'] ?? ''));
$kategorie   = (string)($input['kategorie'] ?? 'Sonstiges');
$inKalender  = !empty($input['in_kalender']) ? 1 : 0;

if ($bezeichnung === '') fail('Bitte eine Bezeichnung angeben');
$d = DateTime::createFromFormat('Y-m-d', $datum);
if (!$d || $d->format('Y-m-d') !== $datum) fail('Ungültiges Datum (erwartet JJJJ-MM-TT)'); // vorher: strtotime('foo') -> Jahr 1970
if (!in_array($kategorie, SB_KATEGORIEN, true)) fail('Ungültige Kategorie');
foreach (['Von' => &$startZeit, 'Bis' => &$endZeit] as $label => &$zeit) {
    if ($zeit === '') { $zeit = null; continue; }
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $zeit, $m) || (int)$m[1] > 23 || (int)$m[2] > 59) fail("Ungültige Zeit ($label)");
    $zeit = sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
}
unset($zeit);
if ($startZeit && $endZeit && $endZeit < $startZeit) fail('Die Endzeit liegt vor der Startzeit');

$jahr      = (int)$d->format('Y');
$wochentag = ['SO', 'MO', 'DI', 'MI', 'DO', 'FR', 'SA'][(int)$d->format('w')];

try {
    if ($id) {
        $stmt = $conn->prepare("UPDATE Standbelegung
            SET Datum = ?, Wochentag = ?, Bezeichnung = ?, StartZeit = ?, EndZeit = ?, Kategorie = ?, InKalender = ?, Jahr = ?
            WHERE ID = ?");
        if (!$stmt) throw new Exception($conn->error);
        $stmt->bind_param('ssssssiii', $datum, $wochentag, $bezeichnung, $startZeit, $endZeit, $kategorie, $inKalender, $jahr, $id);
        if (!$stmt->execute()) {
            if ($conn->errno === 1062) fail('Ein Eintrag mit diesem Datum, dieser Bezeichnung und Startzeit existiert bereits', 409);
            throw new Exception($stmt->error);
        }
        $stmt->close();
        echo json_encode(['success' => true, 'id' => $id, 'wochentag' => $wochentag, 'jahr' => $jahr, 'action' => 'updated', 'message' => 'Eintrag aktualisiert']);
    } else {
        $stmt = $conn->prepare("INSERT INTO Standbelegung (Datum, Wochentag, Bezeichnung, StartZeit, EndZeit, Kategorie, InKalender, Jahr)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception($conn->error);
        $stmt->bind_param('ssssssii', $datum, $wochentag, $bezeichnung, $startZeit, $endZeit, $kategorie, $inKalender, $jahr);
        if (!$stmt->execute()) {
            if ($conn->errno === 1062) fail('Ein Eintrag mit diesem Datum, dieser Bezeichnung und Startzeit existiert bereits', 409);
            throw new Exception($stmt->error);
        }
        $newId = (int)$stmt->insert_id;
        $stmt->close();
        echo json_encode(['success' => true, 'id' => $newId, 'wochentag' => $wochentag, 'jahr' => $jahr, 'action' => 'inserted', 'message' => 'Eintrag hinzugefügt']);
    }
} catch (Throwable $e) {
    error_log('[standbelegung/save_entry] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Eintrag konnte nicht gespeichert werden']);
}
$conn->close();
