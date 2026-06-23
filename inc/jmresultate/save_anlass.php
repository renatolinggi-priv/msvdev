<?php
/**
 * Speichert Resultate für EINEN JM-Anlass.
 * POST-Daten:
 *   - jmdefinitionID: int
 *   - members: JSON-Array [{mitgliedID, punkte}] oder [{mitgliedID, punkte_runde1, punkte_runde2}]
 *   - csrf_token: string
 */
include '../config.php';
require_once __DIR__ . '/../changelog_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

header('Content-Type: application/json; charset=utf-8');

try {
    $jmdefID = intval($_POST['jmdefinitionID'] ?? 0);
    $membersJson = $_POST['members'] ?? '[]';
    $members = json_decode($membersJson, true);
    $isSektionsmeisterschaft = !empty($_POST['isSektionsmeisterschaft']);

    if ($jmdefID === 0 || !is_array($members)) {
        echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
        exit;
    }

    // Vorstand-User-ID fuer Freigabe-Audit (kann NULL sein, wenn Session fehlt).
    // Durch das Speichern bestaetigt der Vorstand implizit alle erfassten Resultate
    // dieses Anlasses -> status='freigegeben', auch Mitglied-Eingaben (status='entwurf').
    $vorstandUserId = $_SESSION['user_id'] ?? null;

    $conn->begin_transaction();

    foreach ($members as $m) {
        $mid = intval($m['mitgliedID']);

        if ($isSektionsmeisterschaft) {
            // Runde 1
            saveOrDeleteJMResultat($conn, $mid, $jmdefID, $m['punkte_runde1'] ?? '', 'runde 1', $vorstandUserId);
            // Runde 2
            saveOrDeleteJMResultat($conn, $mid, $jmdefID, $m['punkte_runde2'] ?? '', 'runde 2', $vorstandUserId);
        } else {
            // Normal
            saveOrDeleteJMResultat($conn, $mid, $jmdefID, $m['punkte'] ?? '', '', $vorstandUserId);
        }
    }

    $conn->commit();
    logChangelog('resultate', 'aktualisiert', "JM-Resultate aktualisiert", ['tabelle' => 'jmresultate', 'jahr' => date('Y'), 'sichtbar' => 0]);
    echo json_encode(['success' => true, 'message' => 'Anlass-Resultate gespeichert']);

} catch (Throwable $e) {
    if (isset($conn)) $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Hilfsfunktion: Einzelnes JM-Resultat speichern oder löschen.
 * Beim Speichern durch den Vorstand wird status='freigegeben' gesetzt (inkl. Audit-Felder),
 * damit Mitglied-Eingaben (status='entwurf') automatisch bestaetigt und gesperrt werden.
 */
function saveOrDeleteJMResultat($conn, $mitgliedID, $definitionID, $rawValue, $info, $vorstandUserId = null) {
    $val = trim((string)$rawValue);

    if ($info === '') {
        // Normal (Info = '' oder NULL)
        $whereInfo = "(Info = '' OR Info IS NULL)";
    } else {
        $whereInfo = "Info = '" . $conn->real_escape_string($info) . "'";
    }

    if ($val === '' || !is_numeric($val)) {
        // Löschen
        $conn->query("DELETE FROM jmresultate WHERE mitgliederID = $mitgliedID AND jmdefinitionID = $definitionID AND $whereInfo");
        return;
    }

    $punkte = intval($val);
    // SQL-Literal fuer freigegeben_von: echtes NULL statt 0, wenn keine Session-User-ID vorliegt
    $uidSql = ($vorstandUserId !== null) ? intval($vorstandUserId) : 'NULL';

    // UPDATE versuchen (Punkte + Freigabe durch Vorstand)
    $conn->query("UPDATE jmresultate
                     SET Punkte = $punkte,
                         status = 'freigegeben',
                         freigegeben_von = $uidSql,
                         freigegeben_am = NOW()
                   WHERE mitgliederID = $mitgliedID AND jmdefinitionID = $definitionID AND $whereInfo");

    // Prüfen ob Zeile existiert
    $result = $conn->query("SELECT 1 FROM jmresultate WHERE mitgliederID = $mitgliedID AND jmdefinitionID = $definitionID AND $whereInfo LIMIT 1");
    if ($result->num_rows === 0) {
        $infoVal = $conn->real_escape_string($info);
        $conn->query("INSERT INTO jmresultate (mitgliederID, jmdefinitionID, Punkte, Info, status, freigegeben_von, freigegeben_am)
                      VALUES ($mitgliedID, $definitionID, $punkte, '$infoVal', 'freigegeben', $uidSql, NOW())");
    }
}

$conn->close();
