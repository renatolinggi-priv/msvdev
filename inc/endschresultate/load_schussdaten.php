<?php
require_once __DIR__ . '/../debug_log.inc.php';
// load_schussdaten.php
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)

$mitgliedID = intval($_GET['mitgliedID']);
$jahr = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($conn->connect_error) {
    error_log("DB Connection failed: " . $conn->connect_error);
    die(json_encode(['error' => 'Connection failed']));
}

$response = [];

// NEU: Ermittle welche Stiche für diesen Schützen gelöst wurden
$geloesteStiche = [];

// Prüfe ob Mitglied oder Gast (Mitglieder haben positive IDs)
if ($mitgliedID > 0) {
    $sql_geloest = "SELECT DISTINCT sd.code 
                    FROM endstich_selection es
                    INNER JOIN endstich_definition sd ON es.stich_id = sd.id
                    WHERE es.mitglied_id = ? AND es.jahr = ? AND sd.active = 1";
    $stmt = $conn->prepare($sql_geloest);
    $stmt->bind_param("ii", $mitgliedID, $jahr);
} else {
    // Für Gäste (negative IDs werden als Gast-ID verwendet)
    $gastID = abs($mitgliedID);
    $sql_geloest = "SELECT DISTINCT sd.code 
                    FROM endstich_selection es
                    INNER JOIN endstich_definition sd ON es.stich_id = sd.id
                    WHERE es.gast_id = ? AND es.jahr = ? AND sd.active = 1";
    $stmt = $conn->prepare($sql_geloest);
    $stmt->bind_param("ii", $gastID, $jahr);
}

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die(json_encode(['error' => 'Query preparation failed']));
}

$stmt->execute();
$result_geloest = $stmt->get_result();

while ($row = $result_geloest->fetch_assoc()) {
    $geloesteStiche[] = $row['code'];
}
$stmt->close();

// DEBUG-Logging
msv_debug_log('endschresultate', "=== LOAD_SCHUSSDATEN DEBUG ===");
msv_debug_log('endschresultate', "MitgliedID: $mitgliedID, Jahr: $jahr");
msv_debug_log('endschresultate', "Gelöste Stiche: " . implode(', ', $geloesteStiche));

// Gelöste Stiche zur Response hinzufügen
$response['geloesteStiche'] = $geloesteStiche;

// Daten aus der Tabelle endstich laden (wenn gelöst)
if (in_array('END', $geloesteStiche)) {
    msv_debug_log('endschresultate', "Lade Endstich-Daten...");
    $sql = "SELECT Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, Schuss6, Schuss7, Schuss8, Schuss9, Schuss10, Tiefschuss, AbsendenAnmeldung 
            FROM endstich 
            WHERE MitgliedID = ? AND Jahr = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $mitgliedID, $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        foreach ($row as $key => $value) {
            $response[$key] = $value;
        }
        msv_debug_log('endschresultate', "Endstich-Daten geladen: " . $result->num_rows . " Zeilen");
    } else {
        msv_debug_log('endschresultate', "Keine Endstich-Daten gefunden");
    }
    $stmt->close();
}

// Daten aus der Tabelle schwini laden (wenn P1 oder P2 gelöst)
if (in_array('SCHWINI_P1', $geloesteStiche) || in_array('SCHWINI_P2', $geloesteStiche)) {
    msv_debug_log('endschresultate', "Lade Schwini-Daten...");
    $sql = "SELECT P1Schuss1, P1Schuss2, P1Schuss3, P1Schuss4, P1Schuss5, P1Schuss6, P2Schuss1, P2Schuss2, P2Schuss3, P2Schuss4, P2Schuss5, P2Schuss6 
            FROM schwini 
            WHERE MitgliedID = ? AND Jahr = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $mitgliedID, $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        foreach ($row as $key => $value) {
            $response[$key] = $value;
        }
        msv_debug_log('endschresultate', "Schwini-Daten geladen");
    } else {
        msv_debug_log('endschresultate', "Keine Schwini-Daten gefunden");
    }
    $stmt->close();
}

// Daten aus der Tabelle Kunst laden (wenn gelöst)
if (in_array('KUNST', $geloesteStiche)) {
    msv_debug_log('endschresultate', "Lade Kunst-Daten...");
    $sql = "SELECT KSchuss1, KSchuss2, KSchuss3, KSchuss4, KSchuss5 
            FROM kunst 
            WHERE MitgliedID = ? AND Jahr = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $mitgliedID, $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        foreach ($row as $key => $value) {
            $response[$key] = $value;
        }
        msv_debug_log('endschresultate', "Kunst-Daten geladen");
    } else {
        msv_debug_log('endschresultate', "Keine Kunst-Daten gefunden");
    }
    $stmt->close();
}

// Daten aus der Tabelle Glueck laden (wenn gelöst)
if (in_array('GLUECK', $geloesteStiche)) {
    msv_debug_log('endschresultate', "Lade Glück-Daten...");
    $sql = "SELECT GSchuss1, GSchuss2, GSchuss3 
            FROM glueck 
            WHERE MitgliedID = ? AND Jahr = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $mitgliedID, $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        foreach ($row as $key => $value) {
            $response[$key] = $value;
        }
        msv_debug_log('endschresultate', "Glück-Daten geladen");
    } else {
        msv_debug_log('endschresultate', "Keine Glück-Daten gefunden");
    }
    $stmt->close();
}

// Daten aus der Tabelle Zabig laden (wenn ZABIG oder DIFF gelöst)
// DIFF (Differenzler) nutzt das Ansage-Feld aus der zabig-Tabelle
if (in_array('ZABIG', $geloesteStiche) || in_array('DIFF', $geloesteStiche)) {
    msv_debug_log('endschresultate', "Lade Zabig-Daten...");
    $sql = "SELECT ZSchuss1, ZSchuss2, ZSchuss3, ZSchuss4, ZSchuss5, ZSchuss6, Ansage 
            FROM zabig 
            WHERE MitgliedID = ? AND Jahr = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $mitgliedID, $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Wenn nur DIFF gelöst ist (nicht ZABIG), dann nur Ansage laden
        if (in_array('DIFF', $geloesteStiche) && !in_array('ZABIG', $geloesteStiche)) {
            $response['Ansage'] = $row['Ansage'];
            msv_debug_log('endschresultate', "Nur Ansage-Daten geladen (DIFF ohne ZABIG)");
        } else {
            // Vollständige Zabig-Daten laden
            foreach ($row as $key => $value) {
                $response[$key] = $value;
            }
            msv_debug_log('endschresultate', "Zabig-Daten geladen");
        }
    } else {
        msv_debug_log('endschresultate', "Keine Zabig-Daten gefunden");
    }
    $stmt->close();
}

// Daten aus der Tabelle endresultate_partner laden (Sie und Er - wenn gelöst)
if (in_array('SIEUNDER', $geloesteStiche)) {
    msv_debug_log('endschresultate', "Lade Sie und Er-Daten...");
    $sql = "SELECT SieErSchuss6, SieErSchuss7, SieErSchuss8, SieErSchuss9, SieErSchuss10 
            FROM endresultate_partner 
            WHERE MitgliedID = ? AND Jahr = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $mitgliedID, $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        foreach ($row as $key => $value) {
            $response[$key] = $value;
        }
        msv_debug_log('endschresultate', "Sie und Er-Daten geladen");
    } else {
        msv_debug_log('endschresultate', "Keine Sie und Er-Daten gefunden");
    }
    $stmt->close();
}

msv_debug_log('endschresultate', "Response Keys: " . implode(', ', array_keys($response)));
msv_debug_log('endschresultate', "=== END DEBUG ===");

echo json_encode($response);

$conn->close();
?>
