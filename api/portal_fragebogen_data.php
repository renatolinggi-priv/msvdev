<?php
// api/portal_fragebogen_data.php - Lädt Fragebogen-Daten für das Mitgliederportal (Session-Auth)
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
if (!$mitglied_id) {
    echo json_encode(['success' => false, 'message' => 'Kein Mitglied mit diesem Konto verknüpft.']);
    exit;
}

$db = getDB();

// Mitglied prüfen und aktuelle Waffe laden
$stmtM = $db->prepare("SELECT WaffenID FROM mitglieder WHERE ID = ?");
$stmtM->execute([$mitglied_id]);
$memberRow = $stmtM->fetch();
if (!$memberRow) {
    echo json_encode(['success' => false, 'message' => 'Mitglied nicht gefunden (ID: ' . (int)$mitglied_id . ').']);
    exit;
}
$defaultWaffenID = (int)($memberRow['WaffenID'] ?? 0);

// Jahr bestimmen (gleiche Logik wie fragebogen_public/verify.php)
$year = (int)date('Y');
$currentMonth = (int)date('m');
if ($currentMonth >= 10) {
    $nextYear = $year + 1;
    $stmtNY = $db->prepare("SELECT COUNT(*) FROM JMDefinition WHERE year = ? AND Erweitert = 1");
    $stmtNY->execute([$nextYear]);
    if ((int)$stmtNY->fetchColumn() > 0) {
        $year = $nextYear;
    }
}

// Waffen laden
$waffen = [];
foreach ($db->query("SELECT ID, Bezeichnung FROM Waffen ORDER BY Bezeichnung")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $waffen[] = ['id' => (int)$row['ID'], 'bezeichnung' => $row['Bezeichnung']];
}

// Erweiterte Definitionen laden (Anlasse mit Erweitert = 1)
$defs = [];
$stmtD = $db->prepare("SELECT ID, Bezeichnung FROM JMDefinition WHERE year = ? AND Erweitert = 1 ORDER BY Reihenfolge");
$stmtD->execute([$year]);
foreach ($stmtD->fetchAll(PDO::FETCH_ASSOC) as $rd) {
    $defs[] = ['id' => (int)$rd['ID'], 'bezeichnung' => $rd['Bezeichnung']];
}

// Bestehende Fragebogen-Antworten laden
$existing = ['waffenID' => $defaultWaffenID, 'mannschaft' => 'nicht', 'gruppen' => 'nicht', 'erweitert' => []];
$stmtFB = $db->prepare("SELECT ID, waffenID, mannschaft, gruppen FROM mitglieder_fragebogen WHERE mitgliedID = ? AND jahr = ? LIMIT 1");
$stmtFB->execute([$mitglied_id, $year]);
$fbRow = $stmtFB->fetch();
if ($fbRow) {
    $existing['waffenID'] = (int)$fbRow['waffenID'];
    $existing['mannschaft'] = $fbRow['mannschaft'];
    $existing['gruppen'] = $fbRow['gruppen'];

    $stmtExt = $db->prepare("SELECT jmdefinitionID, antwort FROM mitglieder_fragebogen_erweitert WHERE fragebogenID = ?");
    $stmtExt->execute([(int)$fbRow['ID']]);
    foreach ($stmtExt->fetchAll(PDO::FETCH_ASSOC) as $extRow) {
        $existing['erweitert'][(int)$extRow['jmdefinitionID']] = $extRow['antwort'];
    }
}

echo json_encode([
    'success'  => true,
    'year'     => $year,
    'waffen'   => $waffen,
    'defs'     => $defs,
    'existing' => $existing,
]);
