<?php
// load_sieger.php
require_once '../config.php';

// Jahr aus GET-Parameter holen
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

try {
    // Daten aus der Tabelle sieger für das ausgewählte Jahr abrufen
    $sql = "SELECT sieger.ID, sieger.Name, sieger.Wert, COALESCE(siegerdef.Bezeichnung, '–') as siegerdef, sieger.year
            FROM sieger
            LEFT JOIN siegerdef ON sieger.siegerdef = siegerdef.ID
            WHERE sieger.year = ?
            ORDER BY siegerdef.Bezeichnung, sieger.Wert DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();

    // Äußere Card mit Header
    echo "<div class='sieger-list-card'>";
    echo "<div class='sieger-header'>";
    echo "<i class='bi bi-trophy me-2'></i>";
    echo "Sieger des Jahres " . htmlspecialchars($selected_year);
    echo "</div>";

    if ($result->num_rows > 0) {
        // Desktop: Tabelle
        echo "<div class='desktop-table-container'>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-hover' id='siegerTable'>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>Name</th>";
        echo "<th>Auszeichnung</th>";
        echo "<th>Resultat</th>";
        echo "<th>Jahr</th>";
        echo "<th>Aktionen</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($row['Name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($row['siegerdef']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($row['Wert']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($row['year']) . "</td>";
            echo "<td>";
            echo "<button class='btn btn-outline-danger btn-icon delete-sieger' data-id='" . $row['ID'] . "' title='Sieger löschen'>";
            echo "<i class='bi bi-trash'></i>";
            echo "</button>";
            echo "</td>";
            echo "</tr>";
        }

        echo "</tbody>";
        echo "</table>";
        echo "</div>"; // Ende table-responsive
        echo "</div>"; // Ende desktop-table-container

        // Mobile: Cards
        echo "<div class='mobile-cards-container' id='mobileSiegerCards'>";
        echo "<div class='mobile-search'>";
        echo "<div class='position-relative'>";
        echo "<i class='bi bi-search search-icon'></i>";
        echo "<input type='text' class='form-control' placeholder='Suchen...' oninput='filterMobileSieger(this)'>";
        echo "</div>";
        echo "</div>";
        echo "<div class='mobile-cards-scroll'>";
        echo "<!-- Cards werden per JavaScript generiert -->";
        echo "</div>";
        echo "</div>";
    } else {
        echo "<div class='p-4 text-center text-muted'>";
        echo "<i class='bi bi-info-circle me-2'></i>";
        echo "Keine Sieger für das Jahr " . htmlspecialchars($selected_year) . " gefunden.";
        echo "</div>";
    }

    echo "</div>"; // Ende sieger-list-card

    $stmt->close();
} catch (Exception $e) {
    echo "<div class='sieger-list-card'>";
    echo "<div class='sieger-header'>";
    echo "<i class='bi bi-trophy me-2'></i>";
    echo "Sieger Liste";
    echo "</div>";
    echo "<div class='p-4 text-center text-danger'>";
    echo "<i class='bi bi-exclamation-triangle me-2'></i>";
    echo "Fehler beim Laden der Daten: " . htmlspecialchars($e->getMessage());
    echo "</div>";
    echo "</div>";
}

$conn->close();
?>