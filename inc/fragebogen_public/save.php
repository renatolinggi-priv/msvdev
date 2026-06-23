<?php
// save.php — Speichert Fragebogen-Antworten eines einzelnen Mitglieds (öffentlich, Token-geschützt)
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function verify_token($token, $secret) {
    $decoded = base64_decode($token, true);
    if ($decoded === false) return false;

    $parts = explode('|', $decoded);
    if (count($parts) !== 3) return false;

    $mitgliedId = (int)$parts[0];
    $expires    = (int)$parts[1];
    $hmac       = $parts[2];

    // Ablaufzeit prüfen
    if (time() > $expires) return false;

    // HMAC verifizieren
    $data = $mitgliedId . '|' . $expires;
    $expectedHmac = hash_hmac('sha256', $data, $secret);
    if (!hash_equals($expectedHmac, $hmac)) return false;

    return $mitgliedId;
}

// --- Sicherheitschecks ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Ungültige Anfrage', 405);
}

// CSRF-Token prüfen
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.');
}

// Honeypot prüfen
if (!empty($_POST['website'])) {
    json_error('Speichern fehlgeschlagen.');
}

// Verifizierungs-Token prüfen
$token = $_POST['verify_token'] ?? '';
if (empty($token)) {
    json_error('Kein Verifizierungs-Token vorhanden. Bitte zuerst verifizieren.');
}

$secret = defined('DB_PASS') ? DB_PASS : 'fallback-secret-key';
$mitgliedId = verify_token($token, $secret);
if ($mitgliedId === false) {
    json_error('Verifizierung abgelaufen. Bitte Seite neu laden und erneut verifizieren.');
}

// --- Eingaben validieren ---

$year      = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
$waffenID  = isset($_POST['waffenID']) ? (int)$_POST['waffenID'] : 0;
$mannschaft = $_POST['mannschaft'] ?? '';
$gruppen    = $_POST['gruppen'] ?? '';
$erweitert  = isset($_POST['erweitert']) && is_array($_POST['erweitert']) ? $_POST['erweitert'] : [];

// Werte validieren
$allowedParticipation = ['teil', 'nicht', 'evtl'];
if (!in_array($mannschaft, $allowedParticipation, true)) {
    json_error('Ungültiger Wert für Mannschaftsmeisterschaft.');
}
if (!in_array($gruppen, $allowedParticipation, true)) {
    json_error('Ungültiger Wert für Gruppenmeisterschaft.');
}

// Waffe validieren (0 = "Nehme nicht teil" ist erlaubt)
if ($waffenID !== 0) {
    $stmtW = $conn->prepare("SELECT ID FROM Waffen WHERE ID = ?");
    $stmtW->bind_param('i', $waffenID);
    $stmtW->execute();
    if ($stmtW->get_result()->num_rows === 0) {
        $stmtW->close();
        json_error('Ungültige Waffe ausgewählt.');
    }
    $stmtW->close();
}

// Mitglied existiert und ist aktiv
$stmtM = $conn->prepare("SELECT ID FROM mitglieder WHERE ID = ? AND Status = 1");
$stmtM->bind_param('i', $mitgliedId);
$stmtM->execute();
if ($stmtM->get_result()->num_rows === 0) {
    $stmtM->close();
    json_error('Mitglied nicht gefunden.');
}
$stmtM->close();

// --- Speichern (Transaction) ---

$conn->begin_transaction();

try {
    // 1) Upsert mitglieder_fragebogen
    $stmtCheck = $conn->prepare("SELECT ID FROM mitglieder_fragebogen WHERE mitgliedID = ? AND jahr = ? LIMIT 1");
    $stmtCheck->bind_param('ii', $mitgliedId, $year);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    $existingRow = $resCheck->fetch_assoc();
    $stmtCheck->close();

    if ($existingRow) {
        $fid = (int)$existingRow['ID'];
        $stmtUpd = $conn->prepare("UPDATE mitglieder_fragebogen SET waffenID = ?, mannschaft = ?, gruppen = ? WHERE ID = ?");
        $stmtUpd->bind_param('issi', $waffenID, $mannschaft, $gruppen, $fid);
        $stmtUpd->execute();
        $stmtUpd->close();
    } else {
        $stmtIns = $conn->prepare("INSERT INTO mitglieder_fragebogen (mitgliedID, jahr, waffenID, mannschaft, gruppen) VALUES (?, ?, ?, ?, ?)");
        $stmtIns->bind_param('iiiss', $mitgliedId, $year, $waffenID, $mannschaft, $gruppen);
        $stmtIns->execute();
        $fid = $conn->insert_id;
        $stmtIns->close();
    }

    // 2) Mitglieder-Tabelle: WaffenID aktualisieren (nicht bei "Nehme nicht teil")
    if ($waffenID !== 0) {
        $stmtMW = $conn->prepare("UPDATE mitglieder SET WaffenID = ? WHERE ID = ?");
        $stmtMW->bind_param('ii', $waffenID, $mitgliedId);
        $stmtMW->execute();
        $stmtMW->close();
    }

    // 3) Erweiterte Antworten upserten
    foreach ($erweitert as $defID => $antwort) {
        $defID = (int)$defID;
        $antwort = ($antwort === 'ja') ? 'ja' : 'nein';

        $stmtEC = $conn->prepare("SELECT ID FROM mitglieder_fragebogen_erweitert WHERE fragebogenID = ? AND jmdefinitionID = ? LIMIT 1");
        $stmtEC->bind_param('ii', $fid, $defID);
        $stmtEC->execute();
        $resEC = $stmtEC->get_result();
        $existingExt = $resEC->fetch_assoc();
        $stmtEC->close();

        if ($existingExt) {
            $eID = (int)$existingExt['ID'];
            $stmtEU = $conn->prepare("UPDATE mitglieder_fragebogen_erweitert SET antwort = ? WHERE ID = ?");
            $stmtEU->bind_param('si', $antwort, $eID);
            $stmtEU->execute();
            $stmtEU->close();
        } else {
            $stmtEI = $conn->prepare("INSERT INTO mitglieder_fragebogen_erweitert (fragebogenID, jmdefinitionID, antwort) VALUES (?, ?, ?)");
            $stmtEI->bind_param('iis', $fid, $defID, $antwort);
            $stmtEI->execute();
            $stmtEI->close();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Fragebogen erfolgreich gespeichert!']);

} catch (Exception $e) {
    $conn->rollback();
    json_error('Fehler beim Speichern: ' . $e->getMessage(), 500);
}
