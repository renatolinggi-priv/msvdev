<?php
// get_mitglieder_list.php - Liste der aktiven Mitglieder für Dropdown
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

try {
    // SQL Query für aktive Mitglieder
    $sql = "SELECT
                m.ID,
                m.Name,
                m.Vorname,
                m.Status,
                w.Kategorie as waffenkategorie,
                COUNT(wg.id) as anzahl_wanderpreis_gewinne
            FROM mitglieder m
            LEFT JOIN Waffen w ON w.ID = m.WaffenID
            LEFT JOIN wanderpreise_gewinner wg ON wg.gewinner_id = m.ID
            WHERE m.Status = 1
              AND m.Verstorben = 0
            GROUP BY m.ID, m.Name, m.Vorname, m.Status, w.Kategorie
            ORDER BY m.Name, m.Vorname";
    
    $result = $conn->query($sql);
    
    $options = '';
    
    if ($result && $result->num_rows > 0) {
        $current_kategorie = '';
        
        while ($row = $result->fetch_assoc()) {
            $waffenkategorie = $row['waffenkategorie'] ?: 'Unbekannt';
            
            // Optgroup für neue Waffenkategorie
            if ($current_kategorie !== $waffenkategorie) {
                if ($current_kategorie !== '') {
                    $options .= '</optgroup>';
                }
                $options .= '<optgroup label="' . htmlspecialchars($waffenkategorie) . '">';
                $current_kategorie = $waffenkategorie;
            }
            
            // Zusätzliche Info für die Option
            $mitglied_name = $row['Name'] . ' ' . $row['Vorname'];
            $zusatz_info = '';
            if ($row['anzahl_wanderpreis_gewinne'] > 0) {
                $zusatz_info = ' (' . $row['anzahl_wanderpreis_gewinne'] . ' Wanderpreise)';
            }
            
            $options .= '<option value="' . $row['ID'] . '" 
                                data-name="' . htmlspecialchars($mitglied_name) . '"
                                data-kategorie="' . htmlspecialchars($waffenkategorie) . '"
                                data-gewinne="' . $row['anzahl_wanderpreis_gewinne'] . '">';
            $options .= htmlspecialchars($mitglied_name) . $zusatz_info;
            $options .= '</option>';
        }
        
        // Letztes optgroup schließen
        if ($current_kategorie !== '') {
            $options .= '</optgroup>';
        }
    } else {
        $options = '<option value="">Keine aktiven Mitglieder vorhanden</option>';
    }
    
    echo $options;
    
} catch (Exception $e) {
    echo '<option value="">Fehler beim Laden der Mitglieder</option>';
    wanderpreise_debug('Error in get_mitglieder_list', ['error' => $e->getMessage()]);
}
?>