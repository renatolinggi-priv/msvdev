<?php
include '../config.php';

if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Zuerst Kat. B Schützen zählen
$katb_sql = "SELECT COUNT(*) as katb_count 
             FROM mitglieder m
             JOIN Waffen w ON m.WaffenID = w.ID
             WHERE m.Status = 1 AND w.Kategorie = 'Kat. B'";
$katb_result = $conn->query($katb_sql);
$katb_count = $katb_result->fetch_assoc()['katb_count'];

// Basis-SQL für Teilnehmer
$sql = "
    SELECT m.ID, m.Vorname, m.Name, w.Kategorie
    FROM mitglieder m
    JOIN Waffen w ON m.WaffenID = w.ID
    WHERE m.Status = 1
    AND m.ID NOT IN (
        SELECT Participant1 FROM cupPairs WHERE Round = 1 AND Year = ?
        UNION
        SELECT Participant2 FROM cupPairs WHERE Round = 1 AND Year = ?
        UNION
        SELECT Participant3 FROM cupPairs WHERE Round = 1 AND Year = ? AND Participant3 IS NOT NULL
    )";

// Wenn nur ein Kat. B Schütze existiert, diesen auch ausschließen
if ($katb_count == 1) {
    $sql .= " AND w.Kategorie != 'Kat. B'";
}

$sql .= " ORDER BY m.Name ASC, m.Vorname ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $year, $year, $year);
$stmt->execute();
$result = $stmt->get_result();

$participants = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $participants[] = $row;
    }
}

echo json_encode($participants);
$conn->close();
?>