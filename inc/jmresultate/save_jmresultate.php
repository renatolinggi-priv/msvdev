<?php
include '../config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// (Optional) Kurzzeitig für Debug lokal aktivieren
// ini_set('display_errors', 1); error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    if ($conn->connect_error) {
        throw new Exception("DB connection failed: " . $conn->connect_error);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $punkte       = $_POST['punkte']        ?? [];
    $punkteR1     = $_POST['punkte_runde1'] ?? [];
    $punkteR2     = $_POST['punkte_runde2'] ?? [];

    $conn->begin_transaction();

    // --- Prepared Statements (NORMAL: Info = '')
    $stmtUpdNorm = $conn->prepare("
        UPDATE jmresultate
           SET Punkte = ?
         WHERE mitgliederID = ? AND jmdefinitionID = ? AND (Info = '' OR Info IS NULL)
    ");
    $stmtInsNorm = $conn->prepare("
        INSERT INTO jmresultate (mitgliederID, jmdefinitionID, Punkte, Info)
        VALUES (?, ?, ?, '')
    ");
    $stmtDelNorm = $conn->prepare("
        DELETE FROM jmresultate
         WHERE mitgliederID = ? AND jmdefinitionID = ? AND (Info = '' OR Info IS NULL)
    ");
    $stmtExiNorm = $conn->prepare("
        SELECT 1 FROM jmresultate
         WHERE mitgliederID = ? AND jmdefinitionID = ? AND (Info = '' OR Info IS NULL)
         LIMIT 1
    ");

    // --- Prepared Statements (SSM: Info = 'runde 1' / 'runde 2')
    $stmtUpdSSM = $conn->prepare("
        UPDATE jmresultate
           SET Punkte = ?
         WHERE mitgliederID = ? AND jmdefinitionID = ? AND Info = ?
    ");
    $stmtInsSSM = $conn->prepare("
        INSERT INTO jmresultate (mitgliederID, jmdefinitionID, Punkte, Info)
        VALUES (?, ?, ?, ?)
    ");
    $stmtDelSSM = $conn->prepare("
        DELETE FROM jmresultate
         WHERE mitgliederID = ? AND jmdefinitionID = ? AND Info = ?
    ");
    $stmtExiSSM = $conn->prepare("
        SELECT 1 FROM jmresultate
         WHERE mitgliederID = ? AND jmdefinitionID = ? AND Info = ?
         LIMIT 1
    ");

    // Helpers
    $saveOrDeleteNormal = function($mitgliedID, $definitionID, $raw) use ($stmtUpdNorm, $stmtInsNorm, $stmtDelNorm, $stmtExiNorm) {
        $mitgliedID   = (int)$mitgliedID;
        $definitionID = (int)$definitionID;
        $s = trim((string)$raw);

        if ($s === '' || !is_numeric($s)) {
            $stmtDelNorm->bind_param('ii', $mitgliedID, $definitionID);
            $stmtDelNorm->execute();
            return;
        }

        $punkte = (int)$s;

        // UPDATE
        $stmtUpdNorm->bind_param('iii', $punkte, $mitgliedID, $definitionID);
        $stmtUpdNorm->execute();

        // Wenn UPDATE nichts geändert hat, prüfen ob Datensatz existiert
        $stmtExiNorm->bind_param('ii', $mitgliedID, $definitionID);
        $stmtExiNorm->execute();
        $exists = $stmtExiNorm->get_result()->fetch_row() !== null;

        // Falls es keinen Datensatz gibt, INSERT
        if (!$exists) {
            $stmtInsNorm->bind_param('iii', $mitgliedID, $definitionID, $punkte);
            $stmtInsNorm->execute();
        }
    };

    $saveOrDeleteSSM = function($mitgliedID, $definitionID, $raw, $label) use ($stmtUpdSSM, $stmtInsSSM, $stmtDelSSM, $stmtExiSSM) {
        $mitgliedID   = (int)$mitgliedID;
        $definitionID = (int)$definitionID;
        $info         = $label;
        $s = trim((string)$raw);

        if ($s === '' || !is_numeric($s)) {
            $stmtDelSSM->bind_param('iis', $mitgliedID, $definitionID, $info);
            $stmtDelSSM->execute();
            return;
        }

        $punkte = (int)$s;

        // UPDATE
        $stmtUpdSSM->bind_param('iiis', $punkte, $mitgliedID, $definitionID, $info);
        $stmtUpdSSM->execute();

        // Existenz prüfen
        $stmtExiSSM->bind_param('iis', $mitgliedID, $definitionID, $info);
        $stmtExiSSM->execute();
        $exists = $stmtExiSSM->get_result()->fetch_row() !== null;

        if (!$exists) {
            $stmtInsSSM->bind_param('iiis', $mitgliedID, $definitionID, $punkte, $info);
            $stmtInsSSM->execute();
        }
    };

    // Normale Disziplinen
    if (is_array($punkte)) {
        foreach ($punkte as $mid => $defs) {
            if (!is_array($defs)) continue;
            foreach ($defs as $did => $val) {
                $saveOrDeleteNormal($mid, $did, $val);
            }
        }
    }
    // Runde 1
    if (is_array($punkteR1)) {
        foreach ($punkteR1 as $mid => $defs) {
            if (!is_array($defs)) continue;
            foreach ($defs as $did => $val) {
                $saveOrDeleteSSM($mid, $did, $val, 'runde 1');
            }
        }
    }
    // Runde 2
    if (is_array($punkteR2)) {
        foreach ($punkteR2 as $mid => $defs) {
            if (!is_array($defs)) continue;
            foreach ($defs as $did => $val) {
                $saveOrDeleteSSM($mid, $did, $val, 'runde 2');
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Ergebnisse gespeichert / bereinigt']);
} catch (Throwable $e) {
    if ($conn && $conn->errno === 0) {
        // noop
    }
    if ($conn && $conn->errno !== 0) {
        // DB Fehlertext anhängen
        $extra = " (DB errno {$conn->errno})";
    } else {
        $extra = '';
    }
    if ($conn && $conn->errno) {
        $conn->rollback();
    }
    http_response_code(500);
    // ins PHP-Errorlog schreiben
    error_log('[save_jmresultate] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern' . $extra, 'detail' => $e->getMessage()]);
}
