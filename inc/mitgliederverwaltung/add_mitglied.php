<?php
// add_mitglied.php – neues Mitglied anlegen (Prepared Statement, JSON-Antwort mit Statuscode)
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true);

function add_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$id           = (int)($_POST['id'] ?? 0);
$vorname      = trim((string)($_POST['vorname'] ?? ''));
$name         = trim((string)($_POST['name'] ?? ''));
$geburtsdatum = trim((string)($_POST['birthday'] ?? ''));
$waffenid     = (int)($_POST['waffenid'] ?? 0);
$status       = !empty($_POST['status']) ? 1 : 0;
$ehrenmitglied = !empty($_POST['ehrenmitglied']) ? 1 : 0;
$strasse      = trim((string)($_POST['strasse'] ?? ''));
$plz          = trim((string)($_POST['plz'] ?? ''));
$ort          = trim((string)($_POST['ort'] ?? ''));
$email        = trim((string)($_POST['email'] ?? ''));
$telefon      = trim((string)($_POST['telefon'] ?? ''));
$mobile       = trim((string)($_POST['mobile'] ?? ''));
$notizen      = trim((string)($_POST['notizen'] ?? ''));
$anrede       = trim((string)($_POST['anrede'] ?? ''));
$kommunikation = trim((string)($_POST['kommunikation'] ?? ''));
$vereinsaufnahme = !empty($_POST['vereinsaufnahme']) ? (int)$_POST['vereinsaufnahme'] : null;

if ($id < 1)                 add_fail('Bitte eine gültige Lizenznummer angeben');
if ($vorname === '' || $name === '') add_fail('Name und Vorname sind Pflichtfelder');
if ($waffenid < 1)           add_fail('Bitte eine Waffe wählen');
$d = DateTime::createFromFormat('Y-m-d', $geburtsdatum);
if (!$d || $d->format('Y-m-d') !== $geburtsdatum) add_fail('Ungültiges Geburtsdatum (erwartet JJJJ-MM-TT)');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) add_fail('Ungültige E-Mail-Adresse');
if ($anrede !== '' && !in_array($anrede, ['Herr', 'Frau'], true)) add_fail('Ungültige Anrede');
if ($kommunikation !== '' && !in_array($kommunikation, ['Briefpost', 'Whatsapp', 'Beides'], true)) add_fail('Ungültiger Kommunikationsweg');
if ($vereinsaufnahme !== null && ($vereinsaufnahme < 1900 || $vereinsaufnahme > 2099)) add_fail('Ungültiges Jahr der Vereinsaufnahme');

// Duplikat vorab lesbar melden statt SQL-Fehler
$chk = $conn->prepare("SELECT ID FROM mitglieder WHERE ID = ?");
$chk->bind_param('i', $id);
$chk->execute();
$exists = $chk->get_result()->num_rows > 0;
$chk->close();
if ($exists) add_fail("Die Lizenznummer $id ist bereits vergeben", 409);

$anredeDb = $anrede !== '' ? $anrede : null;
$kommDb   = $kommunikation !== '' ? $kommunikation : null;

$stmt = $conn->prepare("INSERT INTO mitglieder
    (ID, Anrede, Vorname, Name, Geburtsdatum, WaffenID, Status, Ehrenmitglied, Strasse, PLZ, Ort, Email, Telefon, Mobile, Notizen, Verstorben, Vereinsaufnahme, Kommunikation)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
if (!$stmt) add_fail('Datenbankfehler', 500);
$stmt->bind_param('issssiiisssssssis',
    $id, $anredeDb, $vorname, $name, $geburtsdatum, $waffenid, $status, $ehrenmitglied,
    $strasse, $plz, $ort, $email, $telefon, $mobile, $notizen, $vereinsaufnahme, $kommDb);

if (!$stmt->execute()) {
    error_log('[add_mitglied] ' . $stmt->error);
    add_fail('Mitglied konnte nicht gespeichert werden', 500);
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'message' => 'Mitglied hinzugefügt', 'id' => $id]);
