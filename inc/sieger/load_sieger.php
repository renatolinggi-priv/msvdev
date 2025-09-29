<?php
// load_sieger.php
require_once '../config.php';

// Jahr aus GET-Parameter holen
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

try {
    // Daten aus der Tabelle sieger für das ausgewählte Jahr abrufen
    $sql = "SELECT sieger.ID, sieger.Name, sieger.Wert, siegerdef.Bezeichnung as siegerdef, sieger.year 
            FROM sieger 
            JOIN siegerdef ON sieger.siegerdef = siegerdef.ID 
            WHERE sieger.year = ? 
            ORDER BY siegerdef.Bezeichnung, sieger.Wert DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<table class='table table-hover mb-0' id='siegerTable'>";
        echo "<thead>";
        echo "<tr>";
        echo "<th scope='col'>";
        echo "<i class='bi bi-person me-1'></i>Name";
        echo "</th>";
        echo "<th scope='col'>Kategorie</th>";
        echo "<th scope='col'>Wert</th>";
        echo "<th scope='col'>Jahr</th>";
        echo "<th scope='col' class='text-center'>Aktionen</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['siegerdef']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Wert']) . "</td>";
            echo "<td>" . htmlspecialchars($row['year']) . "</td>";
            echo "<td class='text-center'>";
            echo "<button class='btn btn-sm btn-outline-danger delete-sieger' data-id='" . $row['ID'] . "' title='Sieger löschen'>";
            echo "<i class='bi bi-trash'></i>";
            echo "</button>";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<div class='p-4 text-center text-muted'>";
        echo "<i class='bi bi-info-circle me-2'></i>";
        echo "Keine Sieger für das Jahr " . htmlspecialchars($selected_year) . " gefunden.";
        echo "</div>";
    }

    $stmt->close();
} catch (Exception $e) {
    echo "<div class='p-4 text-center text-danger'>";
    echo "<i class='bi bi-exclamation-triangle me-2'></i>";
    echo "Fehler beim Laden der Daten: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

$conn->close();
?>