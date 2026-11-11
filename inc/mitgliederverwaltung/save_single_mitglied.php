<?php
// save_single_mitglied.php – ein Mitglied aus dem Panel speichern.
// Prüfung zentral in mitglied.inc.php (dieselbe wie Hinzufügen und Import). Ungültige Felder kommen
// als 422 mit fehler{feld: Text} zurück; die Seite markiert sie am Feld.
include '../config.php';
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

$id = (int)($_POST['id'] ?? 0);
$st = $conn->prepare('SELECT ID FROM mitglieder WHERE ID = ?');
$st->bind_param('i', $id);
$st->execute();
$gibtEs = $st->get_result()->num_rows > 0;
$st->close();
if (!$gibtEs) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Dieses Mitglied gibt es nicht mehr. Bitte die Seite neu laden.']));
}

$mitMitgliedschaft = mvHatMitgliedschaft($conn);
[$fehler, $sauber] = mvPruefen($_POST, mvWaffen($conn), $mitMitgliedschaft);
unset($fehler['id']);
if ($fehler) mvFehlerAntwort($fehler);

try {
    mvSchreiben($conn, $id, $sauber, false);
} catch (Throwable $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => msvFehler('Das Mitglied konnte nicht gespeichert werden. Bitte nochmals versuchen.', $e)]));
}

$conn->close();
// Vereinheitlichte Werte zurück (z.B. Telefon im Format +41 …), damit Zeile und Panel sie übernehmen
echo json_encode(['success' => true, 'message' => 'Gespeichert', 'werte' => [
    'telefon' => $sauber['telefon'], 'mobile' => $sauber['mobile'], 'email' => $sauber['email'],
    'ehrenmitglied' => $sauber['ehrenmitglied'], 'austrittsjahr' => $sauber['austrittsjahr'] ?? '',
]], JSON_UNESCAPED_UNICODE);
