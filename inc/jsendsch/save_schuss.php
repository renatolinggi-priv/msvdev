<?php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Log alle empfangenen POST-Daten
error_log("save_schuss.php - POST data: " . json_encode($_POST));

$jungschuetzeID = isset($_POST['jungschuetzeID']) ? intval($_POST['jungschuetzeID']) : 0;
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

error_log("save_schuss.php - JungschuetzeID: $jungschuetzeID, Year: $year");

if ($jungschuetzeID == 0) {
    error_log("save_schuss.php - ERROR: Ungültige JungschuetzeID");
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültige JungschuetzeID']));
}

$schussData = $_POST;

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $conn->connect_error]));
}

function saveSchuss($conn, $jungschuetzeID, $year, $schussData, $table, $fields) {
    // Überprüfen, ob Daten vorhanden sind
    $checkSql = "SELECT ID FROM $table WHERE JungschuetzeID = ? AND Jahr = ?";
    $stmtCheck = $conn->prepare($checkSql);
    if (!$stmtCheck) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmtCheck->bind_param("ii", $jungschuetzeID, $year);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck && $resultCheck->num_rows > 0) {
        // Update - Erstelle SET-Klausel korrekt
        $updateParts = [];
        $values = [];
        $types = "";
        
        foreach ($fields as $field) {
            $updateParts[] = "$field = ?";
            // Spezialbehandlung für AbsendenAnmeldung (Text) vs. numerische Felder
            if ($field === 'AbsendenAnmeldung') {
                $values[] = isset($schussData[$field]) ? $schussData[$field] : '';
                $types .= "s";
            } else {
                $values[] = isset($schussData[$field]) ? intval($schussData[$field]) : 0;
                $types .= "i";
            }
        }
        
        $updateClause = implode(", ", $updateParts);
        $sql = "UPDATE $table SET $updateClause WHERE JungschuetzeID = ? AND Jahr = ?";
        
        // Füge JungschuetzeID und Jahr am Ende hinzu
        $values[] = $jungschuetzeID;
        $values[] = $year;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error . " SQL: " . $sql);
        }
        $stmt->bind_param($types, ...$values);
        
    } else {
        // Insert
        $fieldList = implode(", ", $fields);
        $placeholders = implode(", ", array_fill(0, count($fields), "?"));
        $sql = "INSERT INTO $table (JungschuetzeID, Jahr, $fieldList) VALUES (?, ?, $placeholders)";
        
        $values = [$jungschuetzeID, $year];
        $types = "ii";
        
        foreach ($fields as $field) {
            // Spezialbehandlung für AbsendenAnmeldung (Text) vs. numerische Felder
            if ($field === 'AbsendenAnmeldung') {
                $values[] = isset($schussData[$field]) ? $schussData[$field] : '';
                $types .= "s";
            } else {
                $values[] = isset($schussData[$field]) ? intval($schussData[$field]) : 0;
                $types .= "i";
            }
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error . " SQL: " . $sql);
        }
        $stmt->bind_param($types, ...$values);
    }

    if ($stmt->execute() === FALSE) {
        error_log("save_schuss.php - Execute failed in $table: " . $stmt->error);
        throw new Exception("Execute failed: " . $stmt->error);
    } else {
        error_log("save_schuss.php - Successfully saved to $table");
    }
    
    $stmtCheck->close();
    $stmt->close();
    return true;
}

// Felder für die Tabellen - NUR die benötigten, ohne Kunst, Glück und Ansage
$endstichFields = ['Schuss1', 'Schuss2', 'Schuss3', 'Schuss4', 'Schuss5', 'Schuss6', 'Schuss7', 'Schuss8', 'Schuss9', 'Schuss10', 'Tiefschuss', 'AbsendenAnmeldung'];
// Nur eine Schwini-Passe - wir verwenden P1 für die einzige Passe (6 Schüsse)
$schwiniFields = ['SchwiniSchuss1', 'SchwiniSchuss2', 'SchwiniSchuss3', 'SchwiniSchuss4', 'SchwiniSchuss5', 'SchwiniSchuss6'];
// Mapping für die Datenbank (speichern in P1Schuss Felder)
$schwiniMapping = [];
for ($i = 1; $i <= 6; $i++) {
    $schwiniMapping['P1Schuss' . $i] = isset($schussData['SchwiniSchuss' . $i]) ? intval($schussData['SchwiniSchuss' . $i]) : 0;
    $schwiniMapping['P2Schuss' . $i] = 0; // P2 auf 0 setzen
}
$schwiniFieldsDB = ['P1Schuss1', 'P1Schuss2', 'P1Schuss3', 'P1Schuss4', 'P1Schuss5', 'P1Schuss6', 'P2Schuss1', 'P2Schuss2', 'P2Schuss3', 'P2Schuss4', 'P2Schuss5', 'P2Schuss6'];
$zabigFields = ['ZSchuss1', 'ZSchuss2', 'ZSchuss3', 'ZSchuss4', 'ZSchuss5', 'ZSchuss6'];

// Nur die benötigten Tabellen speichern
try {
    saveSchuss($conn, $jungschuetzeID, $year, $schussData, 'endstich_jung', $endstichFields);
    // Schwini mit angepassten Daten speichern
    saveSchuss($conn, $jungschuetzeID, $year, $schwiniMapping, 'schwini_jung', $schwiniFieldsDB);
    saveSchuss($conn, $jungschuetzeID, $year, $schussData, 'zabig_jung', $zabigFields);
    // Kunst und Glück entfernt

    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Schüsse erfolgreich gespeichert']);
} catch (Exception $e) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
