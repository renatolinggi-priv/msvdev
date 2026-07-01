<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include '../config.php';
require_once __DIR__ . '/../csrf.inc.php';

csrf_require(true);
if (empty($_SESSION['user_id'])) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Nicht angemeldet']); exit; }

$freierTitel     = trim($_POST['freierTitel'] ?? '');
$freierWilen     = floatval($_POST['freierWilen'] ?? 0);
$freierWollerau  = floatval($_POST['freierWollerau'] ?? 0);

// Validierung
if ($freierTitel === '' || ($freierWilen == 0 && $freierWollerau == 0)) {
  echo json_encode(['message' => 'Bitte gültige Daten eingeben.']);
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

    echo json_encode(['success' => 'Freier Eintrag gespeichert.']);

} catch (Exception $e) {
    echo json_encode(['message' => 'Fehler beim Speichern: ' . $e->getMessage()]);
}

$conn->close();
