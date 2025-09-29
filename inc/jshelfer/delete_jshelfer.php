<?php
header('Content-Type: application/json');
include '../config.php';

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
  echo json_encode(['error' => 'Ungültige ID']);
  exit;
}

try {
  $stmt = $conn->prepare("DELETE FROM jungschuetzen_helfer WHERE ID = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();

  echo json_encode(['success' => 'Eintrag wurde gelöscht.']);
} catch (Exception $e) {
  echo json_encode(['error' => 'Fehler beim Löschen: ' . $e->getMessage()]);
}
