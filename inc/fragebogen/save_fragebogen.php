<?php
// save_fragebogen.php – alle Antworten eines Jahres speichern (Upsert je Mitglied + erweiterte Fragen)
// Review 09.2026: Whitelist-Validierung der Werte; bestehende IDs werden einmal vorab geladen und die
// Statements einmal vorbereitet (vorher pro Mitglied und pro Frage je SELECT + UPDATE/INSERT).
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true);

$year = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
if ($year < 2000 || $year > 2100) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültiges Jahr']));
}
if (!isset($_POST['fragebogen']) || !is_array($_POST['fragebogen'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Keine Daten empfangen']));
}

$TEILNAHME = ['teil', 'nicht', 'evtl'];
$JANEIN    = ['ja', 'nein'];

$conn->begin_transaction();
try {
    // Bestehende Fragebogen-IDs des Jahres: [mitgliedID] => ID
    $existing = [];
    $stmt = $conn->prepare("SELECT ID, mitgliedID FROM mitglieder_fragebogen WHERE jahr = ?");
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $existing[(int)$row['mitgliedID']] = (int)$row['ID'];
    $stmt->close();

    // Bestehende erweiterte Antworten: [fragebogenID][jmdefinitionID] => ID
    $existingExt = [];
    $stmt = $conn->prepare("SELECT fe.ID, fe.fragebogenID, fe.jmdefinitionID
                            FROM mitglieder_fragebogen_erweitert fe
                            JOIN mitglieder_fragebogen fb ON fb.ID = fe.fragebogenID
                            WHERE fb.jahr = ?");
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $existingExt[(int)$row['fragebogenID']][(int)$row['jmdefinitionID']] = (int)$row['ID'];
    $stmt->close();

    $stmtUpd    = $conn->prepare("UPDATE mitglieder_fragebogen SET waffenID = ?, mannschaft = ?, gruppen = ? WHERE ID = ?");
    $stmtIns    = $conn->prepare("INSERT INTO mitglieder_fragebogen (mitgliedID, jahr, waffenID, mannschaft, gruppen) VALUES (?, ?, ?, ?, ?)");
    $stmtMember = $conn->prepare("UPDATE mitglieder SET WaffenID = ? WHERE ID = ?");
    $stmtUpd2   = $conn->prepare("UPDATE mitglieder_fragebogen_erweitert SET antwort = ? WHERE ID = ?");
    $stmtIns2   = $conn->prepare("INSERT INTO mitglieder_fragebogen_erweitert (fragebogenID, jmdefinitionID, antwort) VALUES (?, ?, ?)");
    if (!$stmtUpd || !$stmtIns || !$stmtMember || !$stmtUpd2 || !$stmtIns2) {
        throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
    }

    $gespeichert = 0;
    foreach ($_POST['fragebogen'] as $mid => $data) {
        $mid = (int)$mid;
        if ($mid < 1 || !is_array($data)) continue;

        $waffenID   = (int)($data['waffenID'] ?? 0);
        $mannschaft = (string)($data['mannschaft'] ?? 'nicht');
        $gruppen    = (string)($data['gruppen'] ?? 'nicht');
        if (!in_array($mannschaft, $TEILNAHME, true) || !in_array($gruppen, $TEILNAHME, true)) {
            throw new InvalidArgumentException("Ungültiger Teilnahme-Wert bei Mitglied $mid");
        }

        if (isset($existing[$mid])) {
            $fid = $existing[$mid];
            $stmtUpd->bind_param('issi', $waffenID, $mannschaft, $gruppen, $fid);
            if (!$stmtUpd->execute()) throw new Exception($stmtUpd->error);
        } else {
            $stmtIns->bind_param('iiiss', $mid, $year, $waffenID, $mannschaft, $gruppen);
            if (!$stmtIns->execute()) throw new Exception($stmtIns->error);
            $fid = (int)$conn->insert_id;
            $existing[$mid] = $fid;
        }
        $gespeichert++;

        // Stammdaten-Waffe mitziehen (nicht bei "Nehme nicht teil")
        if ($waffenID !== 0) {
            $stmtMember->bind_param('ii', $waffenID, $mid);
            if (!$stmtMember->execute()) throw new Exception($stmtMember->error);
        }

        // Erweiterte Fragen
        if (isset($data['erweitert']) && is_array($data['erweitert'])) {
            foreach ($data['erweitert'] as $defID => $ans) {
                $defID = (int)$defID;
                $ans   = (string)$ans;
                if ($defID < 1) continue;
                if (!in_array($ans, $JANEIN, true)) {
                    throw new InvalidArgumentException("Ungültige Antwort bei Mitglied $mid, Frage $defID");
                }
                if (isset($existingExt[$fid][$defID])) {
                    $eID = $existingExt[$fid][$defID];
                    $stmtUpd2->bind_param('si', $ans, $eID);
                    if (!$stmtUpd2->execute()) throw new Exception($stmtUpd2->error);
                } else {
                    $stmtIns2->bind_param('iis', $fid, $defID, $ans);
                    if (!$stmtIns2->execute()) throw new Exception($stmtIns2->error);
                    $existingExt[$fid][$defID] = (int)$conn->insert_id;
                }
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Daten gespeichert', 'count' => $gespeichert]);
} catch (InvalidArgumentException $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[save_fragebogen] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern']);
}
