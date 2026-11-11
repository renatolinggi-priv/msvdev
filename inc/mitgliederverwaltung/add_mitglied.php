<?php
// add_mitglied.php – neues Mitglied anlegen. Prüfung zentral in mitglied.inc.php.
// 422 + fehler{feld}: ungültige Felder · 409 + fehler.id: Lizenznummer vergeben ·
// 409 + duplikat: gleicher Name, Vorname und Geburtsdatum existiert schon (mit trotzdem=1 erneut senden).
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/mitglied.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true);

$eingabe = $_POST;
$eingabe['status'] = 1;       // neu = Mitglied
$eingabe['verstorben'] = 0;
$eingabe['austrittsjahr'] = '';

[$fehler, $sauber] = mvPruefen($eingabe, mvWaffen($conn), mvHatMitgliedschaft($conn));
if ($fehler) mvFehlerAntwort($fehler);

$id = $sauber['id'];
$st = $conn->prepare('SELECT Vorname, Name FROM mitglieder WHERE ID = ?');
$st->bind_param('i', $id);
$st->execute();
$vergeben = $st->get_result()->fetch_assoc();
$st->close();
if ($vergeben) {
    mvFehlerAntwort(['id' => 'Die Lizenznummer ' . $id . ' gehört schon ' . trim($vergeben['Vorname'] . ' ' . $vergeben['Name']) . '.'], 409);
}

if (empty($_POST['trotzdem'])) {
    $dup = mvDuplikat($conn, $sauber['name'], $sauber['vorname'], $sauber['geburtsdatum']);
    if ($dup) {
        http_response_code(409);
        die(json_encode(['success' => false, 'duplikat' => [
            'id' => (int)$dup['ID'], 'name' => trim($dup['Vorname'] . ' ' . $dup['Name']),
        ], 'message' => 'Dieses Mitglied gibt es vielleicht schon.'], JSON_UNESCAPED_UNICODE));
    }
}

try {
    mvSchreiben($conn, $id, $sauber, true);
} catch (Throwable $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => msvFehler('Das Mitglied konnte nicht angelegt werden. Bitte nochmals versuchen.', $e)]));
}
$conn->close();

echo json_encode(['success' => true, 'message' => trim($sauber['vorname'] . ' ' . $sauber['name']) . ' hinzugefügt', 'id' => $id], JSON_UNESCAPED_UNICODE);
