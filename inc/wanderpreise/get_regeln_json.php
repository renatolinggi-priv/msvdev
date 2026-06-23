<?php
// get_regeln_json.php - Liefert alle Regeln als JSON
require_once '../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');

$conn = get_db_connection();
if (!$conn) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "SELECT id, regel_code, regel_name, regel_beschreibung, sql_query, aktiv
            FROM wanderpreise_regeln
            ORDER BY regel_name";
    $result = $conn->query($sql);

    $regeln = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $regeln[] = $row;
        }
    }

    echo json_encode($regeln);
} catch (Exception $e) {
    echo json_encode([]);
}

$conn->close();
