<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

// Jahr
$year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');

if (!isset($_POST['fragebogen']) || !is_array($_POST['fragebogen'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Keine Daten empfangen']));
}

$conn->begin_transaction();

try {
    foreach ($_POST['fragebogen'] as $mid => $data) {
        $mid = (int)$mid;
        $waffenID    = (int)$data['waffenID'];
        $mannschaft  = $data['mannschaft'];
        $gruppen     = $data['gruppen'];

        // 1) Upsert in Tabelle mitglieder_fragebogen
        //    Prüfen, ob bereits ein Eintrag existiert
        $stmtCheck = $conn->prepare("SELECT ID FROM mitglieder_fragebogen WHERE mitgliedID = ? AND jahr = ? LIMIT 1");
        $stmtCheck->bind_param("ii", $mid, $year);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        if ($resCheck && $resCheck->num_rows > 0) {
            $rowF = $resCheck->fetch_assoc();
            $fid  = (int)$rowF['ID'];
            $stmtCheck->close();
            // UPDATE in mitglieder_fragebogen
            $stmtUpd = $conn->prepare("UPDATE mitglieder_fragebogen SET waffenID = ?, mannschaft = ?, gruppen = ? WHERE ID = ?");
            $stmtUpd->bind_param("issi", $waffenID, $mannschaft, $gruppen, $fid);
            $stmtUpd->execute();
            $stmtUpd->close();
        } else {
            $stmtCheck->close();
            // INSERT in mitglieder_fragebogen
            $stmtIns = $conn->prepare("INSERT INTO mitglieder_fragebogen (mitgliedID, jahr, waffenID, mannschaft, gruppen) VALUES (?, ?, ?, ?, ?)");
            $stmtIns->bind_param("iiiss", $mid, $year, $waffenID, $mannschaft, $gruppen);
            $stmtIns->execute();
            $fid = $conn->insert_id;
            $stmtIns->close();
        }

        // *** Hier wird auch die Mitglieder-Tabelle aktualisiert ***
        $stmtMember = $conn->prepare("UPDATE mitglieder SET WaffenID = ? WHERE ID = ?");
        $stmtMember->bind_param("ii", $waffenID, $mid);
        $stmtMember->execute();
        $stmtMember->close();

        // 2) Erweitert – in Tabelle mitglieder_fragebogen_erweitert
        if (isset($data['erweitert']) && is_array($data['erweitert'])) {
            foreach ($data['erweitert'] as $defID => $ans) {
                $defID = (int)$defID;
                $ans   = (string)$ans; // 'ja' oder 'nein'

                // Upsert in mitglieder_fragebogen_erweitert
                $stmtCheck2 = $conn->prepare("SELECT ID FROM mitglieder_fragebogen_erweitert WHERE fragebogenID = ? AND jmdefinitionID = ? LIMIT 1");
                $stmtCheck2->bind_param("ii", $fid, $defID);
                $stmtCheck2->execute();
                $resC2 = $stmtCheck2->get_result();
                if ($resC2 && $resC2->num_rows > 0) {
                    $rowE = $resC2->fetch_assoc();
                    $eID  = (int)$rowE['ID'];
                    $stmtCheck2->close();
                    $stmtUpd2 = $conn->prepare("UPDATE mitglieder_fragebogen_erweitert SET antwort = ? WHERE ID = ?");
                    $stmtUpd2->bind_param("si", $ans, $eID);
                    $stmtUpd2->execute();
                    $stmtUpd2->close();
                } else {
                    $stmtCheck2->close();
                    $stmtIns2 = $conn->prepare("INSERT INTO mitglieder_fragebogen_erweitert (fragebogenID, jmdefinitionID, antwort) VALUES (?, ?, ?)");
                    $stmtIns2->bind_param("iis", $fid, $defID, $ans);
                    $stmtIns2->execute();
                    $stmtIns2->close();
                }
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Daten gespeichert']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>
