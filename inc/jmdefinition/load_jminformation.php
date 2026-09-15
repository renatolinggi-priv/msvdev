<?php
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
header('Content-Type: application/json; charset=utf-8');

// Zusatztext aus der Datenbank abrufen
$sql = "SELECT text FROM JMInformation ORDER BY created_at DESC LIMIT 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'text' => $row['text']]);
} else {
    echo json_encode(['success' => false, 'text' => '']);
}

$conn->close();
?>
