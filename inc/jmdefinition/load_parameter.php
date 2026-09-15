<?php
// load_parameter.php – Lädt JM-Parameter (Anzahl Streicher) für ein Jahr
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$stmt = $conn->prepare("SELECT excludeCount FROM Parameter WHERE year = ?");
$stmt->bind_param('i', $year);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'success'      => true,
    'excludeCount' => $row ? (int)$row['excludeCount'] : 3
]);
