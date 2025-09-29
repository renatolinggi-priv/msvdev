<?php
// import_handler.php - Backend-Logik für CSV Import
session_start();
require_once '../dbconnect.inc.php';

// Datenbankverbindung herstellen (für diese Datei)
$servername = "bdebbd4.mysql.db.internal";
$username = "bdebbd4_msvjm";
$password = "xx*97ubWcy+HnLWyf6PW";
$dbname = "bdebbd4_msvjm";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Mitglied anhand Lizenznummer finden
// WICHTIG: Die ID in der mitglieder Tabelle IST die Lizenznummer!
if (isset($_GET['action']) && $_GET['action'] === 'find_member_by_license') {
    header('Content-Type: application/json');
    
    $license = trim($_GET['license']);
    error_log("Searching for member with license/ID: $license");
    
    try {
        // Die ID-Spalte IST die Lizenznummer!
        $sql = "SELECT ID, Name, Vorname FROM mitglieder WHERE ID = ?";
        $stmt = $conn->prepare($sql);
        $licenseInt = intval($license); // Konvertiere zu Integer da ID ein INT ist
        $stmt->bind_param("i", $licenseInt);
        $stmt->execute();
        $result = $stmt->get_result();
        
        error_log("Query executed for ID: $licenseInt, rows found: " . $result->num_rows);
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            error_log("Found member: " . $row['Name'] . ' ' . $row['Vorname'] . ' (ID: ' . $row['ID'] . ')');
            echo json_encode([
                'success' => true,
                'member_id' => $row['ID'],
                'member_name' => $row['Name'] . ' ' . $row['Vorname']
            ]);
        } else {
            error_log("No member found with ID: $licenseInt");
            echo json_encode(['success' => false, 'message' => 'Kein Mitglied gefunden', 'license_searched' => $license]);
        }
    } catch (Exception $e) {
        error_log("Error in find_member_by_license: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX Handler für den Import
if (isset($_POST['action']) && $_POST['action'] === 'import_results') {
    header('Content-Type: application/json');
    
    // CSRF-Token prüfen
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges CSRF-Token']);
        exit;
    }
    
    $mitgliedId = intval($_POST['mitglied_id']);
    $jahr = intval($_POST['jahr']);
    $selectedPrograms = json_decode($_POST['selected_programs'], true);
    
    // Debug logging
    error_log('Import request received:');
    error_log('Mitglied ID: ' . $mitgliedId);
    error_log('Jahr: ' . $jahr);
    error_log('Selected Programs: ' . print_r($selectedPrograms, true));
    
    try {
        // Prüfen ob bereits Daten für dieses Mitglied und Jahr existieren
        $checkSql = "SELECT ID, Passe1, Passe2, Passe3, Passe4, Passe5, Passe6, Passe7, Passe8 
                     FROM heimresultate 
                     WHERE MitgliedID = ? AND Jahr = ?";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param("ii", $mitgliedId, $jahr);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $passes = [
            'Passe1' => null, 'Passe2' => null, 'Passe3' => null, 'Passe4' => null,
            'Passe5' => null, 'Passe6' => null, 'Passe7' => null, 'Passe8' => null
        ];
        
        if ($result->num_rows > 0) {
            // Update existing record
            $row = $result->fetch_assoc();
            $recordId = $row['ID'];
            
            // Bestehende Werte laden
            foreach ($passes as $key => $value) {
                $passes[$key] = $row[$key];
            }
        } else {
            // Insert new record
            $insertSql = "INSERT INTO heimresultate (MitgliedID, Jahr) VALUES (?, ?)";
            $stmt = $conn->prepare($insertSql);
            $stmt->bind_param("ii", $mitgliedId, $jahr);
            $stmt->execute();
            $recordId = $conn->insert_id;
        }
        
        // Zuordnung der Programme zu den Passen
        foreach ($selectedPrograms as $program) {
            $programNumber = $program['number'];
            $total = intval($program['total']); // Sicherstellen dass es ein Integer ist
            $index = intval($program['index']);
            
            error_log("Processing program: Number=$programNumber, Total=$total, Index=$index");
            
            if ($programNumber === '133') {
                // 133er gehen in ungerade Passen: 1, 3, 5, 7
                $passeNr = ($index * 2) - 1;
                $passeField = 'Passe' . $passeNr;
                error_log("Program 133: Assigning total $total to $passeField");
            } else if ($programNumber === '134') {
                // 134er gehen in gerade Passen: 2, 4, 6, 8
                $passeNr = $index * 2;
                $passeField = 'Passe' . $passeNr;
                error_log("Program 134: Assigning total $total to $passeField");
            } else {
                continue;
            }
            
            if (isset($passes[$passeField])) {
                $passes[$passeField] = $total;
                error_log("Successfully assigned $total to $passeField");
            } else {
                error_log("ERROR: $passeField not found in passes array");
            }
        }
        
        error_log('Final passes array: ' . print_r($passes, true));
        
        // Update der Datenbank
        $updateSql = "UPDATE heimresultate SET 
                      Passe1 = ?, Passe2 = ?, Passe3 = ?, Passe4 = ?,
                      Passe5 = ?, Passe6 = ?, Passe7 = ?, Passe8 = ?
                      WHERE ID = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("iiiiiiiii", 
            $passes['Passe1'], $passes['Passe2'], $passes['Passe3'], $passes['Passe4'],
            $passes['Passe5'], $passes['Passe6'], $passes['Passe7'], $passes['Passe8'],
            $recordId
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Daten erfolgreich importiert',
                'record_id' => $recordId,
                'passes' => $passes
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern: ' . $conn->error]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit;
}

// Mitglieder laden
if (isset($_GET['action']) && $_GET['action'] === 'get_members') {
    header('Content-Type: application/json');
    
    try {
        $sql = "SELECT ID, Name, Vorname FROM mitglieder ORDER BY Name, Vorname";
        $result = $conn->query($sql);
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = [
                'id' => $row['ID'],
                'name' => $row['Name'] . ' ' . $row['Vorname']
            ];
        }
        
        echo json_encode(['success' => true, 'members' => $members]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit;
}

// Prüfe ob bereits Daten existieren
if (isset($_GET['action']) && $_GET['action'] === 'check_existing') {
    header('Content-Type: application/json');
    
    $mitgliedId = intval($_GET['mitglied_id']);
    $jahr = intval($_GET['jahr']);
    
    try {
        $sql = "SELECT Passe1, Passe2, Passe3, Passe4, Passe5, Passe6, Passe7, Passe8 
                FROM heimresultate 
                WHERE MitgliedID = ? AND Jahr = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $mitgliedId, $jahr);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $existingPasses = [];
            
            for ($i = 1; $i <= 8; $i++) {
                if (!empty($row['Passe' . $i])) {
                    $existingPasses[] = $i;
                }
            }
            
            echo json_encode([
                'success' => true,
                'exists' => true,
                'existing_passes' => $existingPasses,
                'data' => $row
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'exists' => false
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit;
}

// Verbindung schließen
$conn->close();
?>