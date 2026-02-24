<?php
// save_parameter.php – Speichert JM-Parameter (Anzahl Streicher) für ein Jahr
include '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

$year         = isset($_POST['year']) ? (int)$_POST['year'] : 0;
$excludeCount = isset($_POST['excludeCount']) ? (int)$_POST['excludeCount'] : 3;

if ($year < 2000 || $year > 2100) {
    echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr']);
    exit;
}
if ($excludeCount < 1 || $excludeCount > 9) {
    echo json_encode(['success' => false, 'message' => 'Anzahl Streicher muss zwischen 1 und 9 liegen']);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO Parameter (year, excludeCount) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE excludeCount = VALUES(excludeCount)"
);
$stmt->bind_param('ii', $year, $excludeCount);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Anzahl Streicher gespeichert']);
