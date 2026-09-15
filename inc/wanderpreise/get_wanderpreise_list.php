<?php
// get_wanderpreise_list.php - Dropdown-Liste aller Wanderpreise
require_once 'wanderpreise_config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('html');
require_once '../dbconnect.inc.php';

try {
    // SQL Query für alle Wanderpreise
    $sql = "SELECT
                id,
                bezeichnung,
                min_anzahl_gewinne,
                (SELECT COUNT(*)
                 FROM wanderpreise_gewinner wg
                 WHERE wg.wanderpreis_id = w.id) as anzahl_gewinner
            FROM wanderpreise w
            ORDER BY bezeichnung";
    
    $result = $conn->query($sql);
    
    $options = '';
    
    if ($result && $result->num_rows > 0) {
        // Flache Liste: die Tabelle wanderpreise hat keine Spalte "kategorie"; die frühere
        // optgroup-Gruppierung las ein nicht vorhandenes Feld (leere Gruppenlabels + PHP-Warnungen).
        while ($row = $result->fetch_assoc()) {
            $zusatz_info = '';
            if ($row['anzahl_gewinner'] > 0) {
                $zusatz_info = ' (' . $row['anzahl_gewinner'] . ' Gewinner)';
            }

            $options .= '<option value="' . (int)$row['id'] . '"'
                      . ' data-min-gewinne="' . (int)$row['min_anzahl_gewinne'] . '"'
                      . ' data-anzahl-gewinner="' . (int)$row['anzahl_gewinner'] . '">';
            $options .= htmlspecialchars($row['bezeichnung']) . $zusatz_info;
            $options .= '</option>';
        }
    } else {
        $options = '<option value="">Keine aktiven Wanderpreise vorhanden</option>';
    }
    
    echo $options;
    
} catch (Exception $e) {
    echo '<option value="">Fehler beim Laden der Wanderpreise</option>';
    wanderpreise_debug('Error in get_wanderpreise_list', ['error' => $e->getMessage()]);
}
?>