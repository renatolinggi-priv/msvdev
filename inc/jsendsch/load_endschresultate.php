<?php
include '../config.php';

// Jahr-Parameter verarbeiten
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validierung des Jahres
if ($year < 2020 || $year > date('Y') + 1) {
    $year = date('Y');
}

// Neue SQL-Query - Vereinfacht: Nur Gäste mit Geburtsdatum
// Da wir JS über das Geburtsdatum identifizieren (10-20 Jahre alt)
$sql = "
SELECT
    g.id AS JungschuetzeID,
    g.name,
    g.geburtsdatum,
    COALESCE(g.vorname, SUBSTRING_INDEX(g.name, ' ', 1)) AS Vorname,
    COALESCE(g.nachname, SUBSTRING_INDEX(g.name, ' ', -1)) AS Nachname,

    COALESCE(e.Schuss1, 0)  AS E1,
    COALESCE(e.Schuss2, 0)  AS E2,
    COALESCE(e.Schuss3, 0)  AS E3,
    COALESCE(e.Schuss4, 0)  AS E4,
    COALESCE(e.Schuss5, 0)  AS E5,
    COALESCE(e.Schuss6, 0)  AS E6,
    COALESCE(e.Schuss7, 0)  AS E7,
    COALESCE(e.Schuss8, 0)  AS E8,
    COALESCE(e.Schuss9, 0)  AS E9,
    COALESCE(e.Schuss10, 0) AS E10,

    COALESCE(
      COALESCE(e.Schuss1,0)+COALESCE(e.Schuss2,0)+COALESCE(e.Schuss3,0)+COALESCE(e.Schuss4,0)+COALESCE(e.Schuss5,0)
     +COALESCE(e.Schuss6,0)+COALESCE(e.Schuss7,0)+COALESCE(e.Schuss8,0)+COALESCE(e.Schuss9,0)+COALESCE(e.Schuss10,0)
    ,0) AS Endstich_Summe,

    COALESCE(
      COALESCE(s.P1Schuss1,0)+COALESCE(s.P1Schuss2,0)+COALESCE(s.P1Schuss3,0)
     +COALESCE(s.P1Schuss4,0)+COALESCE(s.P1Schuss5,0)+COALESCE(s.P1Schuss6,0)
    ,0) AS Schwini_Summe,

    COALESCE(z.ZSchuss1, 0) AS ZSchuss1,
    COALESCE(z.ZSchuss2, 0) AS ZSchuss2,
    COALESCE(z.ZSchuss3, 0) AS ZSchuss3,
    COALESCE(z.ZSchuss4, 0) AS ZSchuss4,
    COALESCE(z.ZSchuss5, 0) AS ZSchuss5,
    COALESCE(z.ZSchuss6, 0) AS ZSchuss6

FROM endstich_gaeste g
LEFT JOIN endstich_jung e ON g.id = e.JungschuetzeID AND e.Jahr = ?
LEFT JOIN schwini_jung s  ON g.id = s.JungschuetzeID AND s.Jahr = ?
LEFT JOIN zabig_jung z    ON g.id = z.JungschuetzeID AND z.Jahr = ?
WHERE
    g.jahr = ?
    AND g.geburtsdatum IS NOT NULL
    AND TIMESTAMPDIFF(YEAR, g.geburtsdatum, CURDATE()) BETWEEN 10 AND 20
ORDER BY Nachname, Vorname;
";

// Debug-Ausgaben entfernen für Production
// error_log("=== JSENDSCHRESULTATE DEBUG ===");
// error_log("Year: " . $year);
// error_log("SQL (simplified): Selecting from endstich_gaeste WHERE jahr=" . $year . " AND geburtsdatum IS NOT NULL");

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
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "<tr><td colspan='5' class='text-danger'>Fehler bei der Datenbankabfrage: " . $conn->error . "</td></tr>";
    exit;
}

$stmt->bind_param("iiii", $year, $year, $year, $year);
$stmt->execute();
$result = $stmt->get_result();

// Debug-Ausgaben entfernen für Production
// error_log("Rows found: " . $result->num_rows);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Debug entfernt für Production
        // error_log("Processing: ID=" . $row['JungschuetzeID'] . ", Name=" . $row['name'] . ", Geburtsdatum=" . $row['geburtsdatum']);
        
        // Schwini - nur eine Passe
        $schwini_summe = $row['Schwini_Summe'];

        // Zabig Punkte berechnen
        $ZResult = 0;
        for ($i = 1; $i <= 6; $i++) {
            $ZResult += calculatePoints($row['ZSchuss' . $i]);
        }

        // Name formatieren
        $fullName = htmlspecialchars($row['Nachname']) . " " . htmlspecialchars($row['Vorname']);
        
        // Alter berechnen wenn Geburtsdatum vorhanden
        $alter = '';
        if ($row['geburtsdatum']) {
            $geb = new DateTime($row['geburtsdatum']);
            $heute = new DateTime();
            $diff = $heute->diff($geb);
            $alter = ' (' . $diff->y . ' J.)';
        }

        echo "<tr>";
        echo "<td style='text-align: left;'><strong>" . $fullName . "</strong>" . $alter . "</td>";
        echo "<td class='text-center'>" . ($row['Endstich_Summe'] > 0 ? $row['Endstich_Summe'] : '-') . "</td>";
        
        // Schwini anzeigen
        echo "<td class='text-center'>" . ($schwini_summe > 0 ? $schwini_summe : '-') . "</td>";
        
        echo "<td class='text-center'>" . ($ZResult > 0 ? $ZResult : '-') . "</td>";
        echo "<td class='text-center'>
    <div class='btn-group btn-group-sm action-group' role='group' aria-label='Aktionen'>
        <button type='button' 
                class='btn btn-outline-secondary btn-action-edit' 
                data-id='" . (int)$row['JungschuetzeID'] . "' 
                title='Resultate erfassen'>
            <i class='bi bi-pencil-square'></i>
        </button>
        <button type='button' 
                class='btn btn-outline-danger btn-action-delete' 
                data-id='" . (int)$row['JungschuetzeID'] . "' 
                title='Löschen'>
            <i class='bi bi-trash'></i>
        </button>
    </div>
</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5' class='text-center text-muted py-4'>";
    echo "<i class='bi bi-info-circle me-2'></i>";
    echo "Keine Jungschützen mit Geburtsdatum für das Jahr $year gefunden.<br>";
    echo "<small>Tipp: Erfassen Sie zuerst Jungschützen mit Geburtsdatum über 'JS-Endschiessen - Jungschützen erfassen'</small>";
    echo "</td></tr>";
}

$stmt->close();
$conn->close();
?>
