<?php
// api/dokument_delete.php - Dokument loeschen
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireRole(['admin', 'vorstand']);

header('Content-Type: application/json; charset=utf-8');

if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiges CSRF-Token']);
    exit;
}

$doc_id = intval($_POST['id'] ?? 0);
if ($doc_id < 1) {
    echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM vorstand_dokumente WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    echo json_encode(['success' => false, 'message' => 'Dokument nicht gefunden']);
    exit;
}

// Nur eigene Uploads oder Admin darf loeschen
if ($doc['hochgeladen_von'] != $_SESSION['user_id'] && ($_SESSION['user_role'] ?? '') != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

// Datei loeschen
if (file_exists($doc['dateipfad'])) {
    unlink($doc['dateipfad']);
}

$stmt = $db->prepare("DELETE FROM vorstand_dokumente WHERE id = ?");
$stmt->execute([$doc_id]);

echo json_encode(['success' => true, 'message' => 'Dokument gelöscht']);
