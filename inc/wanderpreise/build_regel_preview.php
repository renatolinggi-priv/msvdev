<?php
// build_regel_preview.php - Erzeugt die SQL-Vorschau fuer eine gefuehrte Regel.
// Einzige Quelle der SQL-Generierung ist wp_build_regel_sql(); diese Vorschau
// wird im Panel angezeigt und kann anschliessend ueber test_regel_sql.php
// getestet werden. Kein Schreibzugriff.
require_once '../session_config.inc.php';
require_once '../dbconnect.inc.php';
require_once 'regel_builder.inc.php';
require_once __DIR__ . '/../csrf.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}
csrf_require(true);

$typ = trim($_POST['regel_typ'] ?? '');
$params = wp_params_from_post($_POST);

try {
    $sql = wp_build_regel_sql($typ, $params);
    echo json_encode(['success' => true, 'sql' => $sql]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
