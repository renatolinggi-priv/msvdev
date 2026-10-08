<?php
header('Content-Type: application/json');
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)

// CSRF-Schutz
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

$freierTitel     = trim($_POST['freierTitel'] ?? '');
$freierWilenRoh  = trim((string)($_POST['freierWilen'] ?? ''));
$freierWollRoh   = trim((string)($_POST['freierWollerau'] ?? ''));

// Validierung: Titel Pflicht (max. 255 Zeichen), Stunden 0–999, mindestens ein Wert über 0
$istStunde = function (string $v): bool {
    return $v === '' || (is_numeric($v) && (float)$v >= 0 && (float)$v <= 999);
};
if ($freierTitel === '') {
  http_response_code(422);
  echo json_encode(['success' => false, 'message' => 'Bitte eine Bezeichnung eingeben.']);
  exit;
}
if (mb_strlen($freierTitel) > 255) {
  http_response_code(422);
  echo json_encode(['success' => false, 'message' => 'Die Bezeichnung ist zu lang (höchstens 255 Zeichen).']);
  exit;
}
if (!$istStunde($freierWilenRoh) || !$istStunde($freierWollRoh)) {
  http_response_code(422);
  echo json_encode(['success' => false, 'message' => 'Stunden müssen eine Zahl zwischen 0 und 999 sein (z.B. 2.5).']);
  exit;
}
$freierWilen    = (float)$freierWilenRoh;
$freierWollerau = (float)$freierWollRoh;
if ($freierWilen == 0 && $freierWollerau == 0) {
  http_response_code(422);
  echo json_encode(['success' => false, 'message' => 'Bitte Stunden für Wilen oder Wollerau eingeben.']);
  exit;
}

try {
    $stmt = $conn->prepare("
        INSERT INTO jungschuetzen_helfer (eventID, freierTitel, helferWilen, helferWollerau, angeletAM)
        VALUES (NULL, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("sdd", $freierTitel, $freierWilen, $freierWollerau);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Freier Eintrag gespeichert.']);

} catch (Exception $e) {
    error_log('add_jshelferevent: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Der Eintrag konnte nicht gespeichert werden. Bitte nochmals versuchen.']);
}

$conn->close();
