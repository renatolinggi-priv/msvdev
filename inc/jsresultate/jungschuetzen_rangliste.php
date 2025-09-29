<?php
include 'dbconnect.inc.php';
include 'header.inc.php';
?>

<div class="container-fluid">
    <h5>Rangliste Jungschützen</h5>
</div>
<div class="container-fluid">&nbsp;</div>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Rang</th>
            <th>Name</th>
            <!-- Weitere Spalten entsprechend Ihren Anforderungen -->
        </tr>
    </thead>
    <tbody>
        <?php
        // Ergebnisse laden und sortieren
        $sql = "SELECT js.Name, js.Vorname, jr.*
                FROM jungschuetzen js
                LEFT JOIN jungschuetzen_resultate jr ON js.ID = jr.JungschuetzeID
                ORDER BY jr.Punkte DESC"; // Passen Sie die Sortierung an

        $result = $conn->query($sql);
        $rang = 1;
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $rang . '</td>';
            echo '<td>' . htmlspecialchars($row['Name']) . ' ' . htmlspecialchars($row['Vorname']) . '</td>';
            // Weitere Spalten entsprechend Ihren Anforderungen
            echo '</tr>';
            $rang++;
        }
        ?>
    </tbody>
</table>

<?php
include 'footer.inc.php';
?>
