<?php
// get_regeln_json.php - Liefert alle Regeln als JSON
require_once '../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
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

    // Welche Wanderpreise eine Regel verwenden (verknuepfung_regel = Regel-Code, alte Einträge: Regel-ID).
    // In PHP zugeordnet, damit unterschiedliche Kollationen der beiden Tabellen nie die Regelliste kosten.
    $verwendet = [];
    try {
        $wp = $conn->query("SELECT bezeichnung, verknuepfung_regel, auto_verknuepfung FROM wanderpreise
                            WHERE verknuepfung_regel IS NOT NULL AND verknuepfung_regel <> '' ORDER BY bezeichnung");
        while ($wp && ($w = $wp->fetch_assoc())) {
            $verwendet[(string)$w['verknuepfung_regel']][] = ['name' => $w['bezeichnung'], 'auto' => (int)$w['auto_verknuepfung'] === 1];
        }
    } catch (Throwable $e) {
        error_log('get_regeln_json: Verwendung nicht ermittelt: ' . $e->getMessage());
    }

    $regeln = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Defaults, damit das Frontend sich auf die Felder verlassen kann
            if (!isset($row['regel_typ']))    $row['regel_typ'] = 'custom';
            if (!array_key_exists('regel_params', $row)) $row['regel_params'] = null;
            $row['verwendet_von'] = array_merge($verwendet[(string)$row['regel_code']] ?? [], $verwendet[(string)$row['id']] ?? []);
            $regeln[] = $row;
        }
    }

    echo json_encode($regeln);
} catch (Exception $e) {
    echo json_encode([]);
}

$conn->close();
