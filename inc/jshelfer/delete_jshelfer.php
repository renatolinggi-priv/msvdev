<?php
header('Content-Type: application/json');
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)

// CSRF-Schutz
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Kein Eintrag gewählt.']);
  exit;
}

try {
  $stmt = $conn->prepare("DELETE FROM jungschuetzen_helfer WHERE ID = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $geloescht = $stmt->affected_rows;
  $stmt->close();

  if ($geloescht < 1) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Der Eintrag existiert nicht mehr. Die Liste wird neu geladen.']);
    exit;
  }
  echo json_encode(['success' => true, 'message' => 'Eintrag gelöscht.']);
} catch (Exception $e) {
  error_log('delete_jshelfer: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Der Eintrag konnte nicht gelöscht werden. Bitte später nochmals versuchen.']);
}
