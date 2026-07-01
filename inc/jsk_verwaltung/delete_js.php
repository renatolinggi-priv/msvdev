<?php
// inc/jsk_verwaltung/delete_js.php
// Jungschuetzen loeschen. PDO, CSRF, Vorstand/Admin.
// Verknuepfte Betreuungs-Anfragen werden via FK (ON DELETE CASCADE) mitentfernt;
// ein evtl. verknuepftes users-Konto behaelt jungschuetze_id=NULL (ON DELETE SET NULL).

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    json_error('Keine gültige ID.');
}

try {
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM jungschuetzen WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Jungschütze gelöscht']);
} catch (Throwable $e) {
    error_log('delete_js: ' . $e->getMessage());
    json_error('Löschen fehlgeschlagen (evtl. noch verknüpfte Daten).', 500);
}
