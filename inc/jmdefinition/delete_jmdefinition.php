<?php
// delete_jmdefinition.php – loescht einen Anlass samt Schiesstagen und Gruppen-Zuordnungen
// (keine FKs im Schema, darum explizit in einer Transaktion; vorher blieben Waisen zurueck).
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $conn->connect_error]));
}

$id = (int)($_POST['id'] ?? 0);
if ($id < 1) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültige ID']));
}

$conn->begin_transaction();
try {
    foreach ([
        "DELETE FROM JMSchiesstage WHERE jm_id = ?",
        "DELETE FROM JMDefinition_Gruppen WHERE JMDefinitionID = ?",
        "DELETE FROM JMDefinition WHERE ID = ?",
    ] as $i => $sql) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) throw new Exception('Löschen fehlgeschlagen: ' . $stmt->error);
        if ($i === 2 && $stmt->affected_rows === 0) {
            $stmt->close();
            throw new RuntimeException('Anlass nicht gefunden');
        }
        $stmt->close();
    }
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Anlass gelöscht']);
} catch (RuntimeException $e) {
    $conn->rollback();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[delete_jmdefinition] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen: ' . $e->getMessage()]);
}

$conn->close();
