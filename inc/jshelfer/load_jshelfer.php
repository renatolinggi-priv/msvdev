<?php 
//load_jshelfer.php
header('Content-Type: application/json');
require_once '../config.php';

$jahr = date('Y');
$data = []; // Gesamtdaten-Array vorbereiten

// 1. Eventgebundene Helferstunden (zuerst laden)
$sql = "
  SELECT 
    h.ID AS helferID,
    e.ID AS eventID,
    e.name,
    e.date,
    h.helferWilen,
    h.helferWollerau,
    FALSE AS isCustom
  FROM wichtige_termine e
  LEFT JOIN jungschuetzen_helfer h ON e.ID = h.eventID
  WHERE e.name LIKE '%Jungschützenkurs%'
    AND YEAR(e.date) = ?
  ORDER BY e.date
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $jahr);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

// 2. Freie Helferstunden (eventID IS NULL)
$sqlCustom = "
  SELECT 
    ID AS helferID,
    NULL AS eventID,
    freierTitel AS name,
    NULL AS date,
    helferWilen,
    helferWollerau,
    TRUE AS isCustom
  FROM jungschuetzen_helfer
  WHERE eventID IS NULL
  ORDER BY angeletAM
";

$resCustom = $conn->query($sqlCustom);
while ($row = $resCustom->fetch_assoc()) {
    $data[] = $row; // anhängen statt ersetzen
}

// Rückgabe
echo json_encode($data);
