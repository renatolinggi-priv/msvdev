<?php
include '../config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Mitglieder laden
$sql = "SELECT m.Name, m.Vorname, k.Passe1, k.Passe2, k.Passe3, k.Passe4, k.Passe5,
       (COALESCE(k.Passe1, 0) + COALESCE(k.Passe2, 0) + COALESCE(k.Passe3, 0) + COALESCE(k.Passe4, 0) + 
        COALESCE(k.Passe5, 0)) AS KantiSumme
FROM kantiresultate k
INNER JOIN mitglieder m ON m.ID = k.MitgliedID
INNER JOIN Waffen w ON w.ID = m.WaffenID 
WHERE w.Kategorie = 'Kat. B' and k.Passe1 > 0
ORDER BY KantiSumme DESC";

$result = $conn->query($sql);
// Ergebnisse prüfen und als Tabelle ausgeben
$i = 1;
if ($result->num_rows > 0) {

    foreach ($result as $row) {
        echo '<tr>';
        echo '<td>' . $i ."." .'</td>';
        echo '<td>' . $row["Name"] ." " .$row["Vorname"] . '</td>';
        echo '<td>' . $row["Passe1"] . '</td>';
        echo '<td>' . $row["Passe2"] . '</td>';
        echo '<td>' . $row["Passe3"] . '</td>';
        echo '<td>' . $row["Passe4"] . '</td>';
        echo '<td>' . $row["Passe5"] . '</td>';
        echo '<td>' . $row["KantiSumme"] . '</td>';
        echo '</tr>';
        $i++;
    }
} else {
    echo '<p>Keine Ergebnisse gefunden.</p>';
}

echo '</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>';

// Verbindung schließen
$conn->close();

exit();
?>