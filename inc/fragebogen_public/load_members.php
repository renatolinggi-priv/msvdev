<?php
// load_members.php — Gibt aktive Mitglieder für das Dropdown zurück (nur Name + ID)
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$stmt = $conn->prepare("SELECT ID, Name, Vorname FROM mitglieder WHERE Status = 1 ORDER BY Name, Vorname");
$stmt->execute();
$result = $stmt->get_result();

$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = [
        'id'   => (int)$row['ID'],
        'name' => $row['Name'] . ' ' . $row['Vorname']
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'members' => $members]);
