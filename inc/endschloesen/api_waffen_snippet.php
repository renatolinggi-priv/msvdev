<?php
/**
 * Ergänzungen für endschloesen_api.php
 * Füge diese Cases zum Switch-Statement hinzu
 */

// ==========================================
// NEUE ACTION: list_waffen
// ==========================================
case 'list_waffen':
    $sql = "SELECT ID, Bezeichnung, Kategorie FROM Waffen ORDER BY Kategorie, Bezeichnung";
    $result = $conn->query($sql);
    
    $waffen = [];
    while ($row = $result->fetch_assoc()) {
        $waffen[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $waffen
    ]);
    break;

// ==========================================
// ANPASSUNG: save_selection
// ==========================================
// In der save_selection Action, beim Speichern des Gastes:

if (isset($data['gast_name'])) {
    $gast_name = trim($data['gast_name']);
    
    // Extrahiere Geburtsdatum wenn vorhanden
    $geburtsdatum = null;
    if (preg_match('/\((\d{2})\.(\d{2})\.(\d{4})\)/', $gast_name, $matches)) {
        $geburtsdatum = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }
    
    // Waffen-ID für Gast
    $waffen_id = isset($data['waffen_id']) ? intval($data['waffen_id']) : null;
    
    // Prüfe ob Gast schon existiert
    $stmt = $conn->prepare("SELECT id FROM endstich_gaeste WHERE name = ? AND jahr = ?");
    $stmt->bind_param("si", $gast_name, $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Gast existiert - Update mit Waffe
        $gast_id = $row['id'];
        if ($waffen_id) {
            $stmt_update = $conn->prepare("UPDATE endstich_gaeste SET waffen_id = ? WHERE id = ?");
            $stmt_update->bind_param("ii", $waffen_id, $gast_id);
            $stmt_update->execute();
        }
    } else {
        // Neuer Gast - INSERT mit Waffe
        $stmt_insert = $conn->prepare("INSERT INTO endstich_gaeste (name, jahr, geburtsdatum, waffen_id) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("sisi", $gast_name, $jahr, $geburtsdatum, $waffen_id);
        $stmt_insert->execute();
        $gast_id = $conn->insert_id;
    }
    
    $entity_id = $gast_id;
    $entity_typ = 'gast';
}

// ==========================================
// ANPASSUNG: get_year_details  
// ==========================================
// Im get_year_details, füge Munitions-Berechnung hinzu

include_once 'munitions_helper.php';

// Bei Mitgliedern:
if ($entry['mitglied_id']) {
    $waffen_id = getMitgliedWaffe($entry['mitglied_id'], $conn);
    if ($waffen_id) {
        $munition = berechneMunitionsVerbrauch($entry['stiche'], $waffen_id, $conn);
        $entry['munition_typ'] = getMunitionsTyp($waffen_id, $conn);
        $entry['munition_verbrauch'] = $munition;
    }
}

// Bei Gästen:
if ($entry['gast_id']) {
    $waffen_id = getGastWaffe($entry['gast_id'], $conn);
    if ($waffen_id) {
        $munition = berechneMunitionsVerbrauch($entry['stiche'], $waffen_id, $conn);
        $entry['munition_typ'] = getMunitionsTyp($waffen_id, $conn);
        $entry['munition_verbrauch'] = $munition;
    }
}

?>
