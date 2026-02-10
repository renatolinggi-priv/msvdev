<?php
header('Content-Type: application/json');
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
  echo json_encode(['message' => 'Ungültige ID']);
  exit;
}

try {
  $stmt = $conn->prepare("DELETE FROM jungschuetzen_helfer WHERE ID = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();

  echo json_encode(['success' => 'Eintrag wurde gelöscht.']);
} catch (Exception $e) {
  echo json_encode(['message' => 'Fehler beim Löschen: ' . $e->getMessage()]);
}
