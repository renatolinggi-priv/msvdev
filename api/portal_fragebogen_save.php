<?php
// api/portal_fragebogen_save.php - Speichert Fragebogen-Antworten (Portal, Session-Auth)
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Ungültige Anfrage', 405);
}

requireLogin();

// CSRF-Token prüfen
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.');
}

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
if (!$mitglied_id) {
    json_error('Kein Mitglied mit diesem Konto verknüpft.');
}

// Eingaben validieren
$year       = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
$waffenID   = isset($_POST['waffenID']) ? (int)$_POST['waffenID'] : 0;
$mannschaft = $_POST['mannschaft'] ?? '';
$gruppen    = $_POST['gruppen'] ?? '';
$erweitert  = isset($_POST['erweitert']) && is_array($_POST['erweitert']) ? $_POST['erweitert'] : [];

$allowedParticipation = ['teil', 'nicht', 'evtl'];
if (!in_array($mannschaft, $allowedParticipation, true)) {
    json_error('Ungültiger Wert für Mannschaftsmeisterschaft.');
}
if (!in_array($gruppen, $allowedParticipation, true)) {
    json_error('Ungültiger Wert für Gruppenmeisterschaft.');
}

$db = getDB();

// Waffe validieren
$stmtW = $db->prepare("SELECT ID FROM Waffen WHERE ID = ?");
$stmtW->execute([$waffenID]);
if (!$stmtW->fetch()) {
    json_error('Ungültige Waffe ausgewählt.');
}

// Mitglied prüfen
$stmtM = $db->prepare("SELECT ID FROM mitglieder WHERE ID = ?");
$stmtM->execute([$mitglied_id]);
if (!$stmtM->fetch()) {
    json_error('Mitglied nicht gefunden (ID: ' . (int)$mitglied_id . ').');
}

// Speichern (Transaction)
try {
    $db->beginTransaction();

    // Upsert mitglieder_fragebogen
    $stmtCheck = $db->prepare("SELECT ID FROM mitglieder_fragebogen WHERE mitgliedID = ? AND jahr = ? LIMIT 1");
    $stmtCheck->execute([$mitglied_id, $year]);
    $existingRow = $stmtCheck->fetch();

    if ($existingRow) {
        $fid = (int)$existingRow['ID'];
        $stmtUpd = $db->prepare("UPDATE mitglieder_fragebogen SET waffenID = ?, mannschaft = ?, gruppen = ? WHERE ID = ?");
        $stmtUpd->execute([$waffenID, $mannschaft, $gruppen, $fid]);
    } else {
        $stmtIns = $db->prepare("INSERT INTO mitglieder_fragebogen (mitgliedID, jahr, waffenID, mannschaft, gruppen) VALUES (?, ?, ?, ?, ?)");
        $stmtIns->execute([$mitglied_id, $year, $waffenID, $mannschaft, $gruppen]);
        $fid = (int)$db->lastInsertId();
    }

    // Mitglieder-Tabelle: WaffenID aktualisieren
    $stmtMW = $db->prepare("UPDATE mitglieder SET WaffenID = ? WHERE ID = ?");
    $stmtMW->execute([$waffenID, $mitglied_id]);

    // Erweiterte Antworten upserten
    foreach ($erweitert as $defID => $antwort) {
        $defID  = (int)$defID;
        $antwort = ($antwort === 'ja') ? 'ja' : 'nein';

        $stmtEC = $db->prepare("SELECT ID FROM mitglieder_fragebogen_erweitert WHERE fragebogenID = ? AND jmdefinitionID = ? LIMIT 1");
        $stmtEC->execute([$fid, $defID]);
        $existingExt = $stmtEC->fetch();

        if ($existingExt) {
            $stmtEU = $db->prepare("UPDATE mitglieder_fragebogen_erweitert SET antwort = ? WHERE ID = ?");
            $stmtEU->execute([$antwort, (int)$existingExt['ID']]);
        } else {
            $stmtEI = $db->prepare("INSERT INTO mitglieder_fragebogen_erweitert (fragebogenID, jmdefinitionID, antwort) VALUES (?, ?, ?)");
            $stmtEI->execute([$fid, $defID, $antwort]);
        }
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Fragebogen erfolgreich gespeichert!']);

} catch (Exception $e) {
    $db->rollBack();
    json_error('Fehler beim Speichern: ' . $e->getMessage(), 500);
}
