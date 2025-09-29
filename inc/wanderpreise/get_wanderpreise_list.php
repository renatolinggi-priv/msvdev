<?php
// get_wanderpreise_list.php - Dropdown-Liste aller Wanderpreise
require_once 'wanderpreise_config.php';
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
        $current_kategorie = '';
        
        while ($row = $result->fetch_assoc()) {
            // Optgroup für neue Kategorie
            if ($current_kategorie !== $row['kategorie']) {
                if ($current_kategorie !== '') {
                    $options .= '</optgroup>';
                }
                $options .= '<optgroup label="' . htmlspecialchars($row['kategorie']) . '">';
                $current_kategorie = $row['kategorie'];
            }
            
            // Zusätzliche Info für die Option
            $zusatz_info = '';
            if ($row['anzahl_gewinner'] > 0) {
                $zusatz_info = ' (' . $row['anzahl_gewinner'] . ' Gewinner)';
            }
            
            $options .= '<option value="' . $row['id'] . '" 
                                data-kategorie="' . htmlspecialchars($row['kategorie']) . '"
                                data-min-gewinne="' . $row['min_anzahl_gewinne'] . '"
                                data-anzahl-gewinner="' . $row['anzahl_gewinner'] . '">';
            $options .= htmlspecialchars($row['bezeichnung']) . $zusatz_info;
            $options .= '</option>';
        }
        
        // Letztes optgroup schließen
        if ($current_kategorie !== '') {
            $options .= '</optgroup>';
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