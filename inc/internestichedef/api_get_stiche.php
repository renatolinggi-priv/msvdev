<?php
// api_get_stiche.php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand), startet die Session

// CSRF prüfen
$input = json_decode(file_get_contents('php://input'), true);
if (empty($_SESSION['csrf_token']) || !isset($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$input['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Sitzung abgelaufen – bitte die Seite neu laden und nochmals versuchen.']);
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
        'message' => msvFehler('Die Daten konnten nicht geladen werden. Bitte die Seite neu laden.', $e)
    ]);
}
