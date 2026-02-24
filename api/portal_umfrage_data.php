<?php
// api/portal_umfrage_data.php - Einzelne Umfrage mit Fragen + eigene Antworten laden
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');
requireLogin();

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
if (!$mitglied_id) {
    echo json_encode(['success' => false, 'message' => 'Kein Mitglied verknüpft']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id < 1) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Umfrage-ID']);
    exit;
}

$db = getDB();

// Umfrage laden
$stmt = $db->prepare("SELECT id, titel, beschreibung, gueltig_bis, status, zielgruppe FROM umfragen WHERE id = ?");
$stmt->execute([$id]);
$umfrage = $stmt->fetch();

if (!$umfrage) {
    echo json_encode(['success' => false, 'message' => 'Umfrage nicht gefunden']);
    exit;
}

// Zugriffskontrolle
if ($umfrage['status'] === 'entwurf') {
    echo json_encode(['success' => false, 'message' => 'Diese Umfrage ist noch nicht veröffentlicht']);
    exit;
}
$role = $_SESSION['user_role'] ?? 'mitglied';
if ($umfrage['zielgruppe'] === 'vorstand' && !in_array($role, ['admin', 'vorstand'])) {
    echo json_encode(['success' => false, 'message' => 'Kein Zugriff auf diese Umfrage']);
    exit;
}

// Fragen laden
$stmtF = $db->prepare("SELECT id, frage_text, frage_typ, pflichtfeld, optionen FROM umfragen_fragen WHERE umfrage_id = ? ORDER BY reihenfolge");
$stmtF->execute([$id]);
$fragen = $stmtF->fetchAll();
foreach ($fragen as &$f) {
    $f['optionen'] = $f['optionen'] ? json_decode($f['optionen'], true) : [];
    $f['pflichtfeld'] = (bool)$f['pflichtfeld'];
}
unset($f);

// Eigene Antworten laden
$stmtA = $db->prepare("SELECT frage_id, antwort FROM umfragen_antworten WHERE umfrage_id = ? AND mitglied_id = ?");
$stmtA->execute([$id, $mitglied_id]);
$antworten = [];
foreach ($stmtA->fetchAll() as $a) {
    $antworten[$a['frage_id']] = $a['antwort'];
}

// Readonly wenn geschlossen
$readonly = ($umfrage['status'] === 'geschlossen');

echo json_encode([
    'success'   => true,
    'umfrage'   => $umfrage,
    'fragen'    => $fragen,
    'antworten' => (object)$antworten,
    'readonly'  => $readonly
]);
