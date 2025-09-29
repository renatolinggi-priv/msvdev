<?php
// api_get_stiche.php
header('Content-Type: application/json; charset=utf-8');

// Debug (nur in DEV aktivieren!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// CSRF prüfen
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF-Token']);
    exit;
}

require_once '../dbconnect.inc.php';

try {
    $rows = [];

    $sql = "SELECT stich, nummer1, nummer2, nummer3 FROM interne_stichdefinition ORDER BY stich ASC";
    $result = connect_db($sql);

    while ($row = $result->fetch_assoc()) {
        $rows[$row['stich']] = [
            'nummer1' => $row['nummer1'],
            'nummer2' => $row['nummer2'],
            'nummer3' => $row['nummer3']
        ];
    }

    echo json_encode(['success' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // Falls etwas schiefgeht: JSON-Fehlerantwort
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Laden der Daten: ' . $e->getMessage()
    ]);
}
