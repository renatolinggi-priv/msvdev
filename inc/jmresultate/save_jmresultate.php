<?php
include '../config.php';
require_once __DIR__ . '/../changelog_helper.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

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

    // Vorstand-User-ID fuer Freigabe-Audit (kann NULL sein wenn Session fehlt)
    $vorstandUserId = $_SESSION['user_id'] ?? null;

    // --- Prepared Statements (NORMAL: Info = '')
    // Vorstand-Save setzt status='freigegeben' und Audit-Felder fuer ALLE beruehrten Zeilen
    // (auch unveraenderte Werte gelten als bestaetigt durch das Speichern).
    $stmtUpdNorm = $conn->prepare("
        UPDATE jmresultate
           SET Punkte = ?, status = 'freigegeben',
               freigegeben_von = ?, freigegeben_am = NOW()
         WHERE mitgliederID = ? AND jmdefinitionID = ? AND (Info = '' OR Info IS NULL)
    ");
    $stmtInsNorm = $conn->prepare("
        INSERT INTO jmresultate (mitgliederID, jmdefinitionID, Punkte, Info, status, freigegeben_von, freigegeben_am)
        VALUES (?, ?, ?, '', 'freigegeben', ?, NOW())
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
           SET Punkte = ?, status = 'freigegeben',
               freigegeben_von = ?, freigegeben_am = NOW()
         WHERE mitgliederID = ? AND jmdefinitionID = ? AND Info = ?
    ");
    $stmtInsSSM = $conn->prepare("
        INSERT INTO jmresultate (mitgliederID, jmdefinitionID, Punkte, Info, status, freigegeben_von, freigegeben_am)
        VALUES (?, ?, ?, ?, 'freigegeben', ?, NOW())
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
    $saveOrDeleteNormal = function($mitgliedID, $definitionID, $raw) use ($stmtUpdNorm, $stmtInsNorm, $stmtDelNorm, $stmtExiNorm, $vorstandUserId) {
        $mitgliedID   = (int)$mitgliedID;
        $definitionID = (int)$definitionID;
        $s = trim((string)$raw);

        if ($s === '' || !is_numeric($s)) {
            $stmtDelNorm->bind_param('ii', $mitgliedID, $definitionID);
            $stmtDelNorm->execute();
            return;
        }

        $punkte = (int)$s;
        $uid    = $vorstandUserId !== null ? (int)$vorstandUserId : null;

        // UPDATE: Punkte, freigegeben_von, mitgliederID, jmdefinitionID
        $stmtUpdNorm->bind_param('iiii', $punkte, $uid, $mitgliedID, $definitionID);
        $stmtUpdNorm->execute();

        // Wenn UPDATE nichts geändert hat, prüfen ob Datensatz existiert
        $stmtExiNorm->bind_param('ii', $mitgliedID, $definitionID);
        $stmtExiNorm->execute();
        $exists = $stmtExiNorm->get_result()->fetch_row() !== null;

        // Falls es keinen Datensatz gibt, INSERT
        if (!$exists) {
            // INSERT: mitgliederID, jmdefinitionID, Punkte, freigegeben_von
            $stmtInsNorm->bind_param('iiii', $mitgliedID, $definitionID, $punkte, $uid);
            $stmtInsNorm->execute();
        }
    };

    $saveOrDeleteSSM = function($mitgliedID, $definitionID, $raw, $label) use ($stmtUpdSSM, $stmtInsSSM, $stmtDelSSM, $stmtExiSSM, $vorstandUserId) {
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
        $uid    = $vorstandUserId !== null ? (int)$vorstandUserId : null;

        // UPDATE: Punkte, freigegeben_von, mitgliederID, jmdefinitionID, Info
        $stmtUpdSSM->bind_param('iiiis', $punkte, $uid, $mitgliedID, $definitionID, $info);
        $stmtUpdSSM->execute();

        // Existenz prüfen
        $stmtExiSSM->bind_param('iis', $mitgliedID, $definitionID, $info);
        $stmtExiSSM->execute();
        $exists = $stmtExiSSM->get_result()->fetch_row() !== null;

        if (!$exists) {
            // INSERT: mitgliederID, jmdefinitionID, Punkte, Info, freigegeben_von
            $stmtInsSSM->bind_param('iiisi', $mitgliedID, $definitionID, $punkte, $info, $uid);
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
    logChangelog('resultate', 'aktualisiert', "JM-Resultate aktualisiert", ['tabelle' => 'jmresultate', 'sichtbar' => 0]);
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
