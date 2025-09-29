<?php
// Beispiel: load_heimresultate.php
include '../config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$kat = isset($_GET['kat']) ? $_GET['kat'] : 'A'; // Standard: A

$kategorie = ($kat === 'A') ? 'Kat. A' : 'Kat. B';

$sql = "SELECT 
    m.Name, 
    m.Vorname, 
    h.Passe1, h.Passe2, h.Passe3, h.Passe4, h.Passe5, h.Passe6, h.Passe7, h.Passe8,
    (COALESCE(h.Passe1, 0) + COALESCE(h.Passe2, 0) + COALESCE(h.Passe3, 0) + COALESCE(h.Passe4, 0) + 
     COALESCE(h.Passe5, 0) + COALESCE(h.Passe6, 0) + COALESCE(h.Passe7, 0) + COALESCE(h.Passe8, 0)) AS HeimSumme
FROM heimresultate h
INNER JOIN mitglieder m ON m.ID = h.MitgliedID
INNER JOIN Waffen w ON w.ID = m.WaffenID 
WHERE w.Kategorie = '$kategorie'
  AND h.Jahr = $selectedYear
HAVING HeimSumme > 0
ORDER BY HeimSumme DESC";

$result = $conn->query($sql);

$i = 1;
if ($result && $result->num_rows > 0) {
    foreach ($result as $row) {
        echo '<tr>';
        echo '<td>' . $i . '.</td>';
        echo '<td>' . htmlspecialchars($row["Name"] . " " . $row["Vorname"]) . '</td>';
        echo '<td>' . $row["Passe1"] . '</td>';
        echo '<td>' . $row["Passe2"] . '</td>';
        echo '<td>' . $row["Passe3"] . '</td>';
        echo '<td>' . $row["Passe4"] . '</td>';
        echo '<td>' . $row["Passe5"] . '</td>';
        echo '<td>' . $row["Passe6"] . '</td>';
        echo '<td>' . $row["Passe7"] . '</td>';
        echo '<td>' . $row["Passe8"] . '</td>';
        echo '<td>' . $row["HeimSumme"] . '</td>';
        echo '</tr>';
        $i++;
    }
} else {
    echo '<tr><td colspan="11">Keine Ergebnisse gefunden.</td></tr>';
}

$conn->close();
exit();
?>