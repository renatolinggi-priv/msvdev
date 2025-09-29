<?php
include '../config.php';

// Jahr-Parameter verarbeiten
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validierung des Jahres
if ($year < 2020 || $year > date('Y')) {
    $year = date('Y');
}

// Versuche verschiedene Jahr-Spalten-Namen zu verwenden
$yearColumns = ['Jahr', 'jahr', 'year', 'Year'];
$yearColumn = null;

// Prüfe, welche Jahr-Spalte existiert
foreach ($yearColumns as $col) {
    $testSql = "SHOW COLUMNS FROM endstich_jung LIKE '$col'";
    $testResult = $conn->query($testSql);
    if ($testResult && $testResult->num_rows > 0) {
        $yearColumn = $col;
        break;
    }
}

// SQL mit oder ohne Jahr-Filter je nach verfügbaren Spalten
if ($yearColumn) {
    $sql = "
    SELECT
      m.id AS JungschuetzeID,
      m.Name,
      m.Vorname,
      g.GSchuss1,
      g.GSchuss2,
      g.GSchuss3,
      z.ZSchuss1,
      z.ZSchuss2,
      z.ZSchuss3,
      z.ZSchuss4,
      z.ZSchuss5,
      z.ZSchuss6,
      z.Ansage,
      GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3) AS max_glueck,
      COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS Endstich_Summe,
      COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6 ), 0) AS Schwini_Summe1,
      COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6 ), 0) AS Schwini_Summe2,
      COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) AS Kunst_Summe
    FROM
      jungschuetzen m
    LEFT JOIN endstich_jung e ON m.id = e.JungschuetzeID AND e.$yearColumn = ?
    LEFT JOIN schwini_jung s ON m.id = s.JungschuetzeID AND s.$yearColumn = ?
    LEFT JOIN kunst_jung k ON m.id = k.JungschuetzeID AND k.$yearColumn = ?
    LEFT JOIN glueck_jung g ON m.id = g.JungschuetzeID AND g.$yearColumn = ?
    LEFT JOIN zabig_jung z ON m.id = z.JungschuetzeID AND z.$yearColumn = ?
    GROUP BY
      m.id, m.Vorname, m.Name
    ORDER BY m.Name, m.Vorname;
    ";
} else {
    // Fallback ohne Jahr-Filter, wenn keine Jahr-Spalte existiert
    $sql = "
    SELECT
      m.id AS JungschuetzeID,
      m.Name,
      m.Vorname,
      g.GSchuss1,
      g.GSchuss2,
      g.GSchuss3,
      z.ZSchuss1,
      z.ZSchuss2,
      z.ZSchuss3,
      z.ZSchuss4,
      z.ZSchuss5,
      z.ZSchuss6,
      z.Ansage,
      GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3) AS max_glueck,
      COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS Endstich_Summe,
      COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6 ), 0) AS Schwini_Summe1,
      COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6 ), 0) AS Schwini_Summe2,
      COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) AS Kunst_Summe
    FROM
      jungschuetzen m
    LEFT JOIN endstich_jung e ON m.id = e.JungschuetzeID
    LEFT JOIN schwini_jung s ON m.id = s.JungschuetzeID
    LEFT JOIN kunst_jung k ON m.id = k.JungschuetzeID
    LEFT JOIN glueck_jung g ON m.id = g.JungschuetzeID
    LEFT JOIN zabig_jung z ON m.id = z.JungschuetzeID
    GROUP BY
      m.id, m.Vorname, m.Name
    ORDER BY m.Name, m.Vorname;
    ";
}

function calculatePoints($value)
{
    if ($value >= 91) return 10;
    if ($value >= 81) return 9;
    if ($value >= 71) return 8;
    if ($value >= 61) return 7;
    if ($value >= 51) return 6;
    if ($value >= 41) return 5;
    if ($value >= 31) return 4;
    if ($value >= 21) return 3;
    if ($value >= 11) return 2;
    if ($value >= 1) return 1;
    return 0;
}

// Prepared Statement verwenden für Sicherheit
if ($yearColumn) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiii", $year, $year, $year, $year, $year);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Ohne Jahr-Filter wenn keine Jahr-Spalte vorhanden
    $result = $conn->query($sql);
}

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($row['Schwini_Summe1'] <= $row['Schwini_Summe2']) {
            $schwini_hoeher = $row['Schwini_Summe2'];
            $schwini_tiefer = $row['Schwini_Summe1'];
        } else {
            $schwini_hoeher = $row['Schwini_Summe1'];
            $schwini_tiefer = $row['Schwini_Summe2'];
        }

        $i = 1;
        $ZResult = 0;
        while ($i <= 6) {
            $ZResult += calculatePoints($row['ZSchuss' . $i]);
            $i++;
        }

        echo "<tr>";
        echo "<td><a href='#' class='edit-btn' data-id='" . $row['JungschuetzeID'] . "'>"  . htmlspecialchars($row['Name']) . " " . htmlspecialchars($row['Vorname']) . "</a></td>";
        echo "<td>" . $row['Endstich_Summe'] . "</td>";
        echo "<td>" . $schwini_hoeher . ", " . $schwini_tiefer . "</td>";
        echo "<td>" . $ZResult . "</td>";
        echo "<td>
        <button class='btn btn-outline-primary edit-btn' data-id='" . $row['JungschuetzeID'] . "'>Bearbeiten</button>
        <button class='btn btn-outline-danger delete-btn' data-id='" . $row['JungschuetzeID'] . "'>Löschen</button>
        </td>";
        echo "</tr>";
    }
} else {
    if ($yearColumn) {
        echo "<tr><td colspan='8'>Keine Jungschützen für das Jahr $year gefunden</td></tr>";
    } else {
        echo "<tr><td colspan='8'>Keine Jungschützen gefunden (Jahr-Filter nicht verfügbar)</td></tr>";
    }
}

if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
