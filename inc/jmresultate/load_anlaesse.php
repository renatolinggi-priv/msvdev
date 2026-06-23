<?php
/**
 * Lädt alle JM-Anlässe für ein Jahr mit Fortschritt.
 * GET: year
 * Rückgabe: JSON-Array
 */
include '../config.php';
require_once __DIR__ . '/anlaesse_data.php';
header('Content-Type: application/json; charset=utf-8');

$year = intval($_GET['year'] ?? date('Y'));

try {
    $data = getJmAnlaesse($conn, $year);
    echo json_encode([
        'success'      => true,
        'anlaesse'     => $data['anlaesse'],
        'totalMembers' => $data['totalMembers'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
