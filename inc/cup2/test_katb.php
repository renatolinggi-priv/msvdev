<?php
// test_katb.php - Lege diese Datei im cup2/ Ordner an
include '../config.php';

$year = date('Y');

echo "<h2>Kat. B Debug Test</h2>";

// 1. Wie viele aktive Kat. B Schützen gibt es?
$sql1 = "SELECT m.ID, m.Name, m.Vorname, w.Kategorie, m.Status
         FROM mitglieder m
         JOIN Waffen w ON m.WaffenID = w.ID
         WHERE m.Status = 1 AND w.Kategorie = 'Kat. B'";

$result1 = $conn->query($sql1);
echo "<h3>1. Aktive Kat. B Schützen:</h3>";
echo "<pre>";
while ($row = $result1->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";
echo "Anzahl: " . $result1->num_rows . "<br><br>";

// 2. Ist ein Kat. B Schütze bereits im Finale?
$sql2 = "SELECT cf.*, m.Name, m.Vorname, w.Kategorie
         FROM cupFinalResults cf
         JOIN mitglieder m ON cf.ParticipantID = m.ID
         JOIN Waffen w ON m.WaffenID = w.ID
         WHERE cf.Year = $year AND w.Kategorie = 'Kat. B'";

$result2 = $conn->query($sql2);
echo "<h3>2. Kat. B Schützen im Finale:</h3>";
echo "<pre>";
while ($row = $result2->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";
echo "Anzahl: " . $result2->num_rows . "<br><br>";

// 3. Teste die fetch_participants.php Logik
echo "<h3>3. Test fetch_participants.php:</h3>";
$katb_count_sql = "SELECT COUNT(*) as katb_count 
                   FROM mitglieder m
                   JOIN Waffen w ON m.WaffenID = w.ID
                   WHERE m.Status = 1 AND w.Kategorie = 'Kat. B'";
$katb_result = $conn->query($katb_count_sql);
$katb_count = $katb_result->fetch_assoc()['katb_count'];
echo "Kat. B Count: " . $katb_count . "<br>";

if ($katb_count == 1) {
    echo "→ Es gibt genau 1 Kat. B Schützen - sollte aus Teilnehmerliste ausgeschlossen werden<br>";
} else {
    echo "→ Es gibt $katb_count Kat. B Schützen - normale Behandlung<br>";
}

// 4. Teste die fetch_winners.php Logik
echo "<h3>4. Test fetch_winners.php Ausschluss:</h3>";
$test_sql = "SELECT m.ID, m.Name, m.Vorname, w.Kategorie,
             (w.Kategorie = 'Kat. B' AND (
                SELECT COUNT(*) 
                FROM mitglieder m2 
                JOIN Waffen w2 ON m2.WaffenID = w2.ID 
                WHERE m2.Status = 1 AND w2.Kategorie = 'Kat. B'
             ) = 1) as should_be_excluded
             FROM mitglieder m
             JOIN Waffen w ON m.WaffenID = w.ID
             WHERE m.Status = 1";

$result4 = $conn->query($test_sql);
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Name</th><th>Vorname</th><th>Kategorie</th><th>Sollte ausgeschlossen werden?</th></tr>";
while ($row = $result4->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['ID'] . "</td>";
    echo "<td>" . $row['Name'] . "</td>";
    echo "<td>" . $row['Vorname'] . "</td>";
    echo "<td>" . $row['Kategorie'] . "</td>";
    echo "<td>" . ($row['should_be_excluded'] ? 'JA' : 'NEIN') . "</td>";
    echo "</tr>";
}
echo "</table><br>";

// 5. Prüfe die Waffen-Tabelle
echo "<h3>5. Alle Waffen-Kategorien:</h3>";
$sql5 = "SELECT DISTINCT Kategorie, COUNT(*) as count FROM Waffen GROUP BY Kategorie";
$result5 = $conn->query($sql5);
echo "<pre>";
while ($row = $result5->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

// 6. AJAX-Test für check_katb_finalist.php
echo "<h3>6. Test check_katb_finalist.php:</h3>";
echo "<button onclick='testCheckKatB()'>Test AJAX Call</button>";
echo "<div id='ajax-result'></div>";

?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function testCheckKatB() {
    $.ajax({
        url: 'check_katb_finalist.php',
        method: 'GET',
        data: { year: <?php echo $year; ?> },
        dataType: 'json',
        success: function(response) {
            $('#ajax-result').html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
        },
        error: function(xhr, status, error) {
            $('#ajax-result').html('Error: ' + error + '<br>Response: ' + xhr.responseText);
        }
    });
}
</script>

<?php
$conn->close();
?>