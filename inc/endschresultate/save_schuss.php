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

$mitgliedID = intval($_POST['mitgliedID']);
$jahr = isset($_POST['jahr']) ? intval($_POST['jahr']) : intval(date('Y'));
$schussData = $_POST;

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $conn->connect_error]));
}

function saveSchuss($conn, $mitgliedID, $jahr, $schussData, $table, $fields) {
    // Überprüfen, ob Daten für dieses Mitglied und Jahr bereits existieren
    // $table und $fields stammen aus hardcodierten Arrays – sicher für direkte Verwendung
    $checkStmt = $conn->prepare("SELECT ID FROM $table WHERE MitgliedID = ? AND Jahr = ?");
    $checkStmt->bind_param("ii", $mitgliedID, $jahr);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    $fieldValues = array_map(function($field) use ($schussData) {
        return intval($schussData[$field] ?? 0);
    }, $fields);

    if ($checkResult->num_rows > 0) {
        // Update
        $row = $checkResult->fetch_assoc();
        $id = $row['ID'];
        $checkStmt->close();

        $setParts = implode(", ", array_map(function($field) { return "$field = ?"; }, $fields));
        $sql = "UPDATE $table SET $setParts WHERE ID = ? AND Jahr = ?";
        $stmt = $conn->prepare($sql);
        $types = str_repeat('i', count($fields)) . 'ii';
        $params = array_merge($fieldValues, [$id, $jahr]);
        $stmt->bind_param($types, ...$params);
    } else {
        // Insert
        $checkStmt->close();

        $columns = implode(", ", $fields);
        $placeholders = implode(", ", array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO $table (MitgliedID, Jahr, $columns) VALUES (?, ?, $placeholders)";
        $stmt = $conn->prepare($sql);
        $types = 'ii' . str_repeat('i', count($fields));
        $params = array_merge([$mitgliedID, $jahr], $fieldValues);
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute() === FALSE) {
        throw new Exception("Fehler beim Speichern: " . $stmt->error);
    }
    $stmt->close();
}

// Spezielle Funktion für endresultate_partner (Sie und Er)
// NEU: Nur speichern, wenn mindestens ein Wert > 0 vorhanden ist
function saveSieUnder($conn, $mitgliedID, $jahr, $schussData, $fields) {
    // Prüfen, ob mindestens ein Wert > 0 vorhanden ist
    $hasValues = false;
    foreach ($fields as $field) {
        if (isset($schussData[$field]) && intval($schussData[$field]) > 0) {
            $hasValues = true;
            break;
        }
    }
    
    // Wenn keine Werte vorhanden sind, nichts tun
    if (!$hasValues) {
        return;
    }
    
    // Überprüfen, ob Daten für dieses Mitglied und Jahr bereits existieren
    $checkStmt = $conn->prepare("SELECT ID FROM endresultate_partner WHERE MitgliedID = ? AND Jahr = ?");
    $checkStmt->bind_param("ii", $mitgliedID, $jahr);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    $fieldValues = array_map(function($field) use ($schussData) {
        return intval($schussData[$field] ?? 0);
    }, $fields);

    if ($checkResult->num_rows > 0) {
        // Update - nur Sie und Er Felder aktualisieren
        $row = $checkResult->fetch_assoc();
        $id = $row['ID'];
        $checkStmt->close();

        $setParts = implode(", ", array_map(function($field) { return "$field = ?"; }, $fields));
        $sql = "UPDATE endresultate_partner SET $setParts WHERE ID = ? AND Jahr = ?";
        $stmt = $conn->prepare($sql);
        $types = str_repeat('i', count($fields)) . 'ii';
        $params = array_merge($fieldValues, [$id, $jahr]);
        $stmt->bind_param($types, ...$params);
    } else {
        // Insert - mit Default PartnerName
        $checkStmt->close();

        $columns = implode(", ", $fields);
        $placeholders = implode(", ", array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO endresultate_partner (MitgliedID, Jahr, PartnerName, $columns) VALUES (?, ?, 'Partner', $placeholders)";
        $stmt = $conn->prepare($sql);
        $types = 'ii' . str_repeat('i', count($fields));
        $params = array_merge([$mitgliedID, $jahr], $fieldValues);
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute() === FALSE) {
        throw new Exception("Fehler bei Sie und Er: " . $stmt->error);
    }
    $stmt->close();
}

/**
 * Prüft ob mindestens ein Feld im Datensatz einen Wert > 0 hat
 */
function hasNonZeroValues($schussData, $fields) {
    foreach ($fields as $field) {
        if (isset($schussData[$field]) && intval($schussData[$field]) > 0) {
            return true;
        }
    }
    return false;
}

// Felder für die Tabellen
$endstichFields = ['Schuss1', 'Schuss2', 'Schuss3', 'Schuss4', 'Schuss5', 'Schuss6', 'Schuss7', 'Schuss8', 'Schuss9', 'Schuss10', 'Tiefschuss', 'AbsendenAnmeldung'];
$schwiniFields = ['P1Schuss1', 'P1Schuss2', 'P1Schuss3', 'P1Schuss4', 'P1Schuss5', 'P1Schuss6', 'P2Schuss1', 'P2Schuss2', 'P2Schuss3', 'P2Schuss4', 'P2Schuss5', 'P2Schuss6'];
$kunstFields = ['KSchuss1', 'KSchuss2', 'KSchuss3', 'KSchuss4', 'KSchuss5'];
$glueckFields = ['GSchuss1', 'GSchuss2', 'GSchuss3'];
$zabigFields = ['ZSchuss1', 'ZSchuss2', 'ZSchuss3', 'ZSchuss4', 'ZSchuss5', 'ZSchuss6', 'Ansage'];
$sieunderFields = ['SieErSchuss6', 'SieErSchuss7', 'SieErSchuss8', 'SieErSchuss9', 'SieErSchuss10'];

// Daten speichern – nur wenn mindestens ein Wert > 0 vorhanden ist (verhindert Phantom-Datensätze)
try {
    $endstichData = array_intersect_key($schussData, array_flip($endstichFields));
    if (hasNonZeroValues($schussData, $endstichFields) || !empty($schussData['AbsendenAnmeldung'] ?? '')) {
        saveSchuss($conn, $mitgliedID, $jahr, $endstichData, 'endstich', $endstichFields);
    }

    $schwiniData = array_intersect_key($schussData, array_flip($schwiniFields));
    if (hasNonZeroValues($schussData, $schwiniFields)) {
        saveSchuss($conn, $mitgliedID, $jahr, $schwiniData, 'schwini', $schwiniFields);
    }

    $kunstData = array_intersect_key($schussData, array_flip($kunstFields));
    if (hasNonZeroValues($schussData, $kunstFields)) {
        saveSchuss($conn, $mitgliedID, $jahr, $kunstData, 'kunst', $kunstFields);
    }

    $glueckData = array_intersect_key($schussData, array_flip($glueckFields));
    if (hasNonZeroValues($schussData, $glueckFields)) {
        saveSchuss($conn, $mitgliedID, $jahr, $glueckData, 'glueck', $glueckFields);
    }

    $zabigData = array_intersect_key($schussData, array_flip($zabigFields));
    if (hasNonZeroValues($schussData, $zabigFields) || !empty($schussData['Ansage'] ?? '')) {
        saveSchuss($conn, $mitgliedID, $jahr, $zabigData, 'zabig', $zabigFields);
    }

    // Sie und Er speichern – hat eigene Prüfung in saveSieUnder()
    saveSieUnder($conn, $mitgliedID, $jahr, array_intersect_key($schussData, array_flip($sieunderFields)), $sieunderFields);

    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Schüsse erfolgreich gespeichert']);
} catch (Exception $e) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
