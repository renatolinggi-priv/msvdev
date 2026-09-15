<?php
// load_jmdefinition_gruppen.php – Anlaesse mit Gruppenwettkampf (JMDefinition.Gruppe = 1) eines Jahres
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

header('Content-Type: application/json; charset=utf-8');

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$stmt = $conn->prepare("SELECT ID, Bezeichnung FROM JMDefinition WHERE year = ? AND Gruppe = 1 ORDER BY Reihenfolge");
$stmt->bind_param('i', $year);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($events);
