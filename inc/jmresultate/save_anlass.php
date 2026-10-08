<?php
/**
 * Speichert Resultate für EINEN JM-Anlass.
 * POST-Daten:
 *   - jmdefinitionID: int
 *   - members: JSON-Array [{mitgliedID, punkte}] oder [{mitgliedID, punkte_runde1, punkte_runde2}]
 *   - csrf_token: string
 */
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/../changelog_helper.php';

require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

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

    // Jahr des Anlasses, nicht das Kalenderjahr: Änderungsprotokoll und «Veröffentlichen» zählen pro Jahr
    $stJahr = $conn->prepare('SELECT year, Maxpunkte FROM JMDefinition WHERE ID = ?');
    $stJahr->bind_param('i', $jmdefID);
    $stJahr->execute();
    $defRow = $stJahr->get_result()->fetch_assoc() ?: [];
    $jahrAnlass = (int) ($defRow['year'] ?? 0) ?: (int) date('Y');
    $maxPunkte = (int) ($defRow['Maxpunkte'] ?? 0);
    $stJahr->close();

    // Punkte prüfen, bevor etwas geschrieben wird: keine Zahl, negativ, nicht ganz oder über den Max-Punkten
    // → nichts speichern. Früher wurde «keine Zahl» still als Löschen behandelt und Kommastellen abgeschnitten.
    require_once __DIR__ . '/../eingabe_pruefen.inc.php';
    $felder = $isSektionsmeisterschaft ? ['punkte_runde1' => 'Runde 1', 'punkte_runde2' => 'Runde 2'] : ['punkte' => 'Punkte'];
    $fehler = [];
    foreach ($members as $m) {
        if (!is_array($m)) continue;
        $regeln = [];
        foreach ($felder as $f => $label) {
            $regeln[$f] = [0, $maxPunkte > 0 ? $maxPunkte : 100000, 0, 'Mitglied Nr. ' . (int)($m['mitgliedID'] ?? 0) . ', ' . $label];
        }
        $fehler = array_merge($fehler, msvPruefeZahlen($m, $regeln));
    }
    if ($fehler) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Ungültige Eingabe – ' . implode('; ', array_slice($fehler, 0, 5))], JSON_UNESCAPED_UNICODE);
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
    logChangelog('resultate', 'aktualisiert', "JM-Resultate aktualisiert", ['tabelle' => 'jmresultate', 'jahr' => $jahrAnlass, 'sichtbar' => 0]);
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
    $val = str_replace(',', '.', trim((string)$rawValue));

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
