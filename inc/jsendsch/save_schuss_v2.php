<?php
include '../config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug-Header
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'debug' => []];

try {
    // Empfange Daten
    $jungschuetzeID = isset($_POST['jungschuetzeID']) ? intval($_POST['jungschuetzeID']) : 0;
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    
    $response['debug'][] = "JungschuetzeID: $jungschuetzeID, Year: $year";
    
    if ($jungschuetzeID == 0) {
        throw new Exception("Ungültige JungschuetzeID");
    }
    
    // Prüfe ob Jungschütze in endstich_gaeste existiert
    $checkStmt = $conn->prepare("SELECT id, name FROM endstich_gaeste WHERE id = ?");
    $checkStmt->bind_param("i", $jungschuetzeID);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows == 0) {
        throw new Exception("Jungschütze nicht in endstich_gaeste gefunden");
    }
    
    $js = $checkResult->fetch_assoc();
    $response['debug'][] = "Jungschütze gefunden: " . $js['name'];
    
    // Deaktiviere Foreign Key Checks temporär
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    $response['debug'][] = "Foreign Key Checks deaktiviert";
    
    // Speichere Endstich
    $endstichFields = ['Schuss1', 'Schuss2', 'Schuss3', 'Schuss4', 'Schuss5', 
                       'Schuss6', 'Schuss7', 'Schuss8', 'Schuss9', 'Schuss10', 
                       'Tiefschuss', 'AbsendenAnmeldung'];
    
    // Check ob bereits vorhanden
    $checkEndstich = $conn->prepare("SELECT ID FROM endstich_jung WHERE JungschuetzeID = ? AND Jahr = ?");
    $checkEndstich->bind_param("ii", $jungschuetzeID, $year);
    $checkEndstich->execute();
    $endstichResult = $checkEndstich->get_result();
    
    if ($endstichResult->num_rows > 0) {
        // UPDATE
        $updateParts = [];
        $values = [];
        $types = "";
        
        foreach ($endstichFields as $field) {
            $updateParts[] = "$field = ?";
            if ($field === 'AbsendenAnmeldung') {
                $values[] = isset($_POST[$field]) ? $_POST[$field] : '';
                $types .= "s";
            } else {
                $values[] = isset($_POST[$field]) ? intval($_POST[$field]) : 0;
                $types .= "i";
            }
        }
        
        $sql = "UPDATE endstich_jung SET " . implode(", ", $updateParts) . 
               " WHERE JungschuetzeID = ? AND Jahr = ?";
        $values[] = $jungschuetzeID;
        $values[] = $year;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        
        $response['debug'][] = "Endstich UPDATE: " . ($result ? "OK" : $stmt->error);
    } else {
        // INSERT
        $fieldList = implode(", ", $endstichFields);
        $placeholders = implode(", ", array_fill(0, count($endstichFields), "?"));
        
        $sql = "INSERT INTO endstich_jung (JungschuetzeID, Jahr, $fieldList) VALUES (?, ?, $placeholders)";
        
        $values = [$jungschuetzeID, $year];
        $types = "ii";
        
        foreach ($endstichFields as $field) {
            if ($field === 'AbsendenAnmeldung') {
                $values[] = isset($_POST[$field]) ? $_POST[$field] : '';
                $types .= "s";
            } else {
                $values[] = isset($_POST[$field]) ? intval($_POST[$field]) : 0;
                $types .= "i";
            }
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        
        $response['debug'][] = "Endstich INSERT: " . ($result ? "OK" : $stmt->error);
    }
    
    // Speichere Schwini (nur eine Passe mit 6 Schüssen)
    // Check ob bereits vorhanden
    $checkSchwini = $conn->prepare("SELECT ID FROM schwini_jung WHERE JungschuetzeID = ? AND Jahr = ?");
    $checkSchwini->bind_param("ii", $jungschuetzeID, $year);
    $checkSchwini->execute();
    $schwiniResult = $checkSchwini->get_result();
    
    if ($schwiniResult->num_rows > 0) {
        // UPDATE
        $sql = "UPDATE schwini_jung SET 
                P1Schuss1 = ?, P1Schuss2 = ?, P1Schuss3 = ?, 
                P1Schuss4 = ?, P1Schuss5 = ?, P1Schuss6 = ?,
                P2Schuss1 = 0, P2Schuss2 = 0, P2Schuss3 = 0,
                P2Schuss4 = 0, P2Schuss5 = 0, P2Schuss6 = 0
                WHERE JungschuetzeID = ? AND Jahr = ?";
        
        $stmt = $conn->prepare($sql);
        $s1 = isset($_POST['SchwiniSchuss1']) ? intval($_POST['SchwiniSchuss1']) : 0;
        $s2 = isset($_POST['SchwiniSchuss2']) ? intval($_POST['SchwiniSchuss2']) : 0;
        $s3 = isset($_POST['SchwiniSchuss3']) ? intval($_POST['SchwiniSchuss3']) : 0;
        $s4 = isset($_POST['SchwiniSchuss4']) ? intval($_POST['SchwiniSchuss4']) : 0;
        $s5 = isset($_POST['SchwiniSchuss5']) ? intval($_POST['SchwiniSchuss5']) : 0;
        $s6 = isset($_POST['SchwiniSchuss6']) ? intval($_POST['SchwiniSchuss6']) : 0;
        
        $stmt->bind_param("iiiiiiii", $s1, $s2, $s3, $s4, $s5, $s6, $jungschuetzeID, $year);
        $result = $stmt->execute();
        
        $response['debug'][] = "Schwini UPDATE: " . ($result ? "OK" : $stmt->error);
    } else {
        // INSERT
        $sql = "INSERT INTO schwini_jung 
                (JungschuetzeID, Jahr, P1Schuss1, P1Schuss2, P1Schuss3, P1Schuss4, P1Schuss5, P1Schuss6,
                 P2Schuss1, P2Schuss2, P2Schuss3, P2Schuss4, P2Schuss5, P2Schuss6) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0)";
        
        $stmt = $conn->prepare($sql);
        $s1 = isset($_POST['SchwiniSchuss1']) ? intval($_POST['SchwiniSchuss1']) : 0;
        $s2 = isset($_POST['SchwiniSchuss2']) ? intval($_POST['SchwiniSchuss2']) : 0;
        $s3 = isset($_POST['SchwiniSchuss3']) ? intval($_POST['SchwiniSchuss3']) : 0;
        $s4 = isset($_POST['SchwiniSchuss4']) ? intval($_POST['SchwiniSchuss4']) : 0;
        $s5 = isset($_POST['SchwiniSchuss5']) ? intval($_POST['SchwiniSchuss5']) : 0;
        $s6 = isset($_POST['SchwiniSchuss6']) ? intval($_POST['SchwiniSchuss6']) : 0;
        
        $stmt->bind_param("iiiiiiii", $jungschuetzeID, $year, $s1, $s2, $s3, $s4, $s5, $s6);
        $result = $stmt->execute();
        
        $response['debug'][] = "Schwini INSERT: " . ($result ? "OK" : $stmt->error);
    }
    
    // Speichere Zabig
    $zabigFields = ['ZSchuss1', 'ZSchuss2', 'ZSchuss3', 'ZSchuss4', 'ZSchuss5', 'ZSchuss6'];
    
    // Check ob bereits vorhanden
    $checkZabig = $conn->prepare("SELECT ID FROM zabig_jung WHERE JungschuetzeID = ? AND Jahr = ?");
    $checkZabig->bind_param("ii", $jungschuetzeID, $year);
    $checkZabig->execute();
    $zabigResult = $checkZabig->get_result();
    
    if ($zabigResult->num_rows > 0) {
        // UPDATE
        $updateParts = [];
        $values = [];
        
        foreach ($zabigFields as $field) {
            $updateParts[] = "$field = ?";
            $values[] = isset($_POST[$field]) ? intval($_POST[$field]) : 0;
        }
        
        $sql = "UPDATE zabig_jung SET " . implode(", ", $updateParts) . 
               " WHERE JungschuetzeID = ? AND Jahr = ?";
        $values[] = $jungschuetzeID;
        $values[] = $year;
        
        $types = str_repeat("i", count($values));
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        
        $response['debug'][] = "Zabig UPDATE: " . ($result ? "OK" : $stmt->error);
    } else {
        // INSERT
        $fieldList = implode(", ", $zabigFields);
        $placeholders = implode(", ", array_fill(0, count($zabigFields), "?"));
        
        $sql = "INSERT INTO zabig_jung (JungschuetzeID, Jahr, $fieldList) VALUES (?, ?, $placeholders)";
        
        $values = [$jungschuetzeID, $year];
        foreach ($zabigFields as $field) {
            $values[] = isset($_POST[$field]) ? intval($_POST[$field]) : 0;
        }
        
        $types = str_repeat("i", count($values));
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        
        $response['debug'][] = "Zabig INSERT: " . ($result ? "OK" : $stmt->error);
    }
    
    // Aktiviere Foreign Key Checks wieder
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    $response['debug'][] = "Foreign Key Checks wieder aktiviert";
    
    $response['success'] = true;
    $response['message'] = "Schüsse erfolgreich gespeichert";
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['debug'][] = "Exception: " . $e->getMessage();
}

$conn->close();

// Sende JSON Response
echo json_encode($response);
?>
