<?php
/**
 * Helper-Funktionen für Munitionsberechnung basierend auf Waffe
 * 
 * Munitionsregel:
 * - Stgw90 → GP90 (5.6mm)
 * - Alle anderen → GP11 (7.5mm)
 */

/**
 * Gibt den Munitionstyp für eine Waffe zurück
 * @param int $waffen_id Die ID der Waffe
 * @param mysqli $conn Datenbankverbindung
 * @return string 'GP11' oder 'GP90'
 */
function getMunitionsTyp($waffen_id, $conn) {
    $sql = "SELECT Bezeichnung FROM Waffen WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $waffen_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $bezeichnung = $row['Bezeichnung'];
        // Nur Stgw90 verwendet GP90 (5.6mm)
        // Alle anderen verwenden GP11 (7.5mm)
        return (stripos($bezeichnung, 'Stgw90') !== false) ? 'GP90' : 'GP11';
    }
    
    // Default: GP11
    return 'GP11';
}

/**
 * Berechnet Munitionsverbrauch für Mitglied oder Gast
 * @param array $stiche Array von Stich-IDs
 * @param int $waffen_id Die ID der Waffe
 * @param mysqli $conn Datenbankverbindung
 * @return array ['GP11' => int, 'GP90' => int, 'total' => int]
 */
function berechneMunitionsVerbrauch($stiche, $waffen_id, $conn) {
    $munitionstyp = getMunitionsTyp($waffen_id, $conn);
    
    // Hole Schussanzahl pro Stich
    $total_schuesse = 0;
    if (!empty($stiche)) {
        $placeholders = implode(',', array_fill(0, count($stiche), '?'));
        $sql = "SELECT SUM(shots) as total FROM endstich_definitionen WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        
        $types = str_repeat('i', count($stiche));
        $stmt->bind_param($types, ...$stiche);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $total_schuesse = intval($row['total']);
        }
    }
    
    // Verteile auf GP11 oder GP90
    $verbrauch = [
        'GP11' => 0,
        'GP90' => 0,
        'total' => $total_schuesse
    ];
    
    $verbrauch[$munitionstyp] = $total_schuesse;
    
    return $verbrauch;
}

/**
 * Lade alle Waffen für Dropdown
 * @param mysqli $conn Datenbankverbindung
 * @return array Array von Waffen
 */
function ladeAlleWaffen($conn) {
    $sql = "SELECT ID, Bezeichnung, Kategorie FROM Waffen ORDER BY Kategorie, Bezeichnung";
    $result = $conn->query($sql);
    
    $waffen = [];
    while ($row = $result->fetch_assoc()) {
        $waffen[] = $row;
    }
    
    return $waffen;
}

/**
 * Hole Waffe für Mitglied
 * @param int $mitglied_id
 * @param mysqli $conn
 * @return int|null Waffen-ID oder null
 */
function getMitgliedWaffe($mitglied_id, $conn) {
    $sql = "SELECT WaffenID FROM mitglieder WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $mitglied_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return intval($row['WaffenID']);
    }
    
    return null;
}

/**
 * Hole Waffe für Gast
 * @param int $gast_id
 * @param mysqli $conn
 * @return int|null Waffen-ID oder null
 */
function getGastWaffe($gast_id, $conn) {
    $sql = "SELECT waffen_id FROM endstich_gaeste WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $gast_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['waffen_id'] ? intval($row['waffen_id']) : null;
    }
    
    return null;
}
?>
