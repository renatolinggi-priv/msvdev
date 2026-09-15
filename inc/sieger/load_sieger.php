<?php
// load_sieger.php — Kategorie-Karten eines Jahres (HTML-Fragment fuer sieger.php)
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('html');

header('Content-Type: text/html; charset=utf-8');

$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

try {
    $stmt = $conn->prepare("SELECT sieger.ID, sieger.Name, sieger.Wert,
                   COALESCE(siegerdef.Bezeichnung, '–') AS siegerdef,
                   sieger.siegerdef AS siegerdef_id,
                   sieger.year
            FROM sieger
            LEFT JOIN siegerdef ON sieger.siegerdef = siegerdef.ID
            WHERE sieger.year = ?
            ORDER BY siegerdef.Bezeichnung, sieger.Wert DESC");
    $stmt->bind_param('i', $selected_year);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "<div class='empty-state'><i class='bi bi-trophy'></i><p>Keine Sieger für " . $selected_year . " gefunden</p></div>";
    } else {
        // Nach Kategorie gruppieren
        $grouped = [];
        while ($row = $result->fetch_assoc()) {
            $grouped[$row['siegerdef']][] = $row;
        }

        // Kategorien in uebergeordnete Gruppen einteilen:
        // Jahresmeisterschaft = JM + Kantonalstich + Heimmeisterschaft, Endschiessen = alles Uebrige
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
                $catSafe = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
                echo "<div class='cat-card' data-category='{$catSafe}'>";
                echo "<div class='cat-card-head'><div class='cat-icon' data-cat-icon></div><h6 data-cat-label>{$catSafe}</h6></div>";
                echo "<div class='cat-card-body'>";

                foreach ($entries as $entry) {
                    $nameSafe    = htmlspecialchars($entry['Name'], ENT_QUOTES, 'UTF-8');
                    $wertSafe    = htmlspecialchars((string)$entry['Wert'], ENT_QUOTES, 'UTF-8');
                    $id          = (int)$entry['ID'];
                    $siegerdefId = (int)($entry['siegerdef_id'] ?? 0);
                    $year        = (int)$entry['year'];
                    // Zeile ist per Klick/Enter bearbeitbar (role/tabindex), Aktionen sind sichtbar
                    echo "<div class='winner-row' role='button' tabindex='0' aria-label='{$nameSafe} bearbeiten'"
                       . " data-id='{$id}' data-name='{$nameSafe}' data-wert='{$wertSafe}' data-siegerdef='{$siegerdefId}' data-year='{$year}'>";
                    echo "<div class='winner-name'>{$nameSafe}</div>";
                    echo "<div class='winner-score'>{$wertSafe}</div>";
                    echo "<div class='winner-action'>";
                    echo "<button type='button' class='btn btn-outline-primary btn-sm edit-sieger' data-tooltip='Bearbeiten' tabindex='-1'><i class='bi bi-pencil'></i></button>";
                    echo "<button type='button' class='btn btn-outline-danger btn-sm delete-sieger' data-id='{$id}' data-tooltip='Löschen'><i class='bi bi-trash'></i></button>";
                    echo "</div></div>";
                }

                echo "</div></div>"; // cat-card-body, cat-card
            }
            echo "</div></div>"; // desktop-cards-container, sieger-group
        }
    }
    $stmt->close();
} catch (Throwable $e) {
    error_log('[load_sieger] ' . $e->getMessage());
    echo "<div class='text-center py-4 text-danger'><i class='bi bi-exclamation-triangle me-2'></i>Fehler beim Laden der Sieger</div>";
}

$conn->close();
