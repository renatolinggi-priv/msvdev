<?php
// load_sieger.php — Kategorie-Karten
require_once '../config.php';

$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

try {
    $sql = "SELECT sieger.ID, sieger.Name, sieger.Wert,
                   COALESCE(siegerdef.Bezeichnung, '–') as siegerdef,
                   sieger.siegerdef as siegerdef_id,
                   sieger.year
            FROM sieger
            LEFT JOIN siegerdef ON sieger.siegerdef = siegerdef.ID
            WHERE sieger.year = ?
            ORDER BY siegerdef.Bezeichnung, sieger.Wert DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Daten nach Kategorie gruppieren
        $grouped = [];
        while ($row = $result->fetch_assoc()) {
            $cat = $row['siegerdef'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $row;
        }

        // Kategorien in übergeordnete Gruppen einteilen
        // Jahresmeisterschaft: JM + Kantonalstich (Kanti) + Heimmeisterschaft (Heim)
        // Endschiessen: alles Übrige (Endstich, Schwini, Kunst, Glück, Zabig, Endschiessen A/B)
        $superGroups = ['Jahresmeisterschaft' => [], 'Endschiessen' => []];
        foreach ($grouped as $category => $entries) {
            $group = 'Endschiessen';
            foreach (['Jahresmeisterschaft', 'Kantonalstich', 'Heim'] as $kw) {
                if (stripos($category, $kw) !== false) { $group = 'Jahresmeisterschaft'; break; }
            }
            $superGroups[$group][$category] = $entries;
        }

        $groupIcons = ['Jahresmeisterschaft' => 'bi-trophy', 'Endschiessen' => 'bi-bullseye'];

        foreach ($superGroups as $groupName => $cats) {
            if (empty($cats)) continue;
            $icon = $groupIcons[$groupName] ?? 'bi-collection';
            echo "<div class='sieger-group'>";
            echo "<h5 class='sieger-group-title'><i class='bi {$icon}'></i>" . htmlspecialchars($groupName) . "</h5>";
            echo "<div class='desktop-cards-container'>";

            foreach ($cats as $category => $entries) {
                $catSafe = htmlspecialchars($category);
                echo "<div class='cat-card' data-category='{$catSafe}'>";
                echo "<div class='cat-card-head'>";
                echo "<div class='cat-icon' data-cat-icon></div>";
                echo "<h6 data-cat-label>{$catSafe}</h6>";
                echo "</div>";
                echo "<div class='cat-card-body'>";

                foreach ($entries as $entry) {
                    $nameSafe = htmlspecialchars($entry['Name']);
                    $wertSafe = htmlspecialchars($entry['Wert']);
                    $id = intval($entry['ID']);

                    $siegerdefId = intval($entry['siegerdef_id'] ?? 0);
                    echo "<div class='winner-row' data-id='{$id}' data-name='{$nameSafe}' data-wert='{$wertSafe}' data-siegerdef='{$siegerdefId}'>";
                    echo "<div class='winner-name'>{$nameSafe}</div>";
                    echo "<div class='winner-score'>{$wertSafe}</div>";
                    echo "<div class='winner-action'>";
                    echo "<button class='btn btn-outline-danger btn-sm delete-sieger' data-id='{$id}' data-tooltip='Löschen'>";
                    echo "<i class='bi bi-trash'></i>";
                    echo "</button>";
                    echo "</div>";
                    echo "</div>";
                }

                echo "</div>"; // cat-card-body
                echo "</div>"; // cat-card
            }

            echo "</div>"; // desktop-cards-container
            echo "</div>"; // sieger-group
        }

    } else {
        echo "<div class='empty-state'>";
        echo "<i class='bi bi-trophy'></i>";
        echo "<p>Keine Sieger für das Jahr " . htmlspecialchars($selected_year) . " erfasst.</p>";
        echo "</div>";
    }

    $stmt->close();
} catch (Exception $e) {
    echo "<div class='text-center py-4 text-danger'>";
    echo "<i class='bi bi-exclamation-triangle me-2'></i>";
    echo "Fehler beim Laden: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

$conn->close();
?>
