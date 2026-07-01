<?php
// get_regeln_json.php - Liefert alle Regeln als JSON
require_once '../dbconnect.inc.php';
require_once 'regel_builder.inc.php'; // wp_regeln_has_builder_columns()

header('Content-Type: application/json; charset=utf-8');

$conn = get_db_connection();
if (!$conn) {
    echo json_encode([]);
    exit;
}

try {
    // Builder-Spalten nur selektieren, wenn Migration 027 schon lief
    // (sonst wuerde die Query unter PHP 8.1+ eine Exception werfen).
    $cols = wp_regeln_has_builder_columns($conn)
        ? "id, regel_code, regel_name, regel_beschreibung, regel_typ, sql_query, regel_params, aktiv"
        : "id, regel_code, regel_name, regel_beschreibung, sql_query, aktiv";
    $result = $conn->query("SELECT $cols FROM wanderpreise_regeln ORDER BY regel_name");

    $regeln = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Defaults, damit das Frontend sich auf die Felder verlassen kann
            if (!isset($row['regel_typ']))    $row['regel_typ'] = 'custom';
            if (!array_key_exists('regel_params', $row)) $row['regel_params'] = null;
            $regeln[] = $row;
        }
    }

    echo json_encode($regeln);
} catch (Exception $e) {
    echo json_encode([]);
}

$conn->close();
