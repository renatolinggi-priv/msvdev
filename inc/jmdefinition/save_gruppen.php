<?php
// save_gruppen.php – Gruppe anlegen oder (per editGroupId = GruppenUID) komplett ersetzen.
// Eine Gruppe = mehrere Zeilen in JMDefinition_Gruppen mit derselben GruppenUID.
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

$editId      = (int)($_POST['editGroupId'] ?? 0);
$eventID     = (int)($_POST['eventID'] ?? 0);
$jahr        = (int)($_POST['jahr'] ?? date('Y'));
$gruppenname = trim((string)($_POST['gruppenname'] ?? ''));
$mitglieder  = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['mitglieder'] ?? [])), static fn($m) => $m > 0)));

if ($gruppenname === '' || $eventID < 1) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Bitte Gruppenname und Anlass angeben']));
}
if ($jahr < 2000 || $jahr > 2100) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültiges Jahr']));
}
if (!$mitglieder) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Bitte mindestens ein Mitglied zur Gruppe hinzufügen']));
}

$conn->begin_transaction();
try {
    // Doppelte Zuteilung verhindern: Mitglieder, die fuer diesen Anlass/Jahr schon in einer ANDEREN Gruppe sind
    $ph = implode(',', array_fill(0, count($mitglieder), '?'));
    $types = str_repeat('i', count($mitglieder)) . 'iii';
    $chk = $conn->prepare("SELECT DISTINCT g.mitgliederID, g.Gruppenname FROM JMDefinition_Gruppen g
                           WHERE g.mitgliederID IN ($ph) AND g.JMDefinitionID = ? AND g.Jahr = ? AND g.GruppenUID <> ?");
    if (!$chk) throw new Exception('Prepare (Check): ' . $conn->error);
    $chk->bind_param($types, ...array_merge($mitglieder, [$eventID, $jahr, $editId]));
    $chk->execute();
    $konflikte = $chk->get_result()->fetch_all(MYSQLI_ASSOC);
    $chk->close();
    if ($konflikte) {
        $conn->rollback();
        http_response_code(409);
        $namen = array_unique(array_map(static fn($k) => $k['Gruppenname'], $konflikte));
        die(json_encode(['success' => false, 'message' => count($konflikte) . ' Mitglied(er) sind bereits in einer anderen Gruppe (' . implode(', ', $namen) . ') eingeteilt']));
    }

    if ($editId > 0) {
        // Bestehende Gruppe vollstaendig ersetzen
        $stmtDel = $conn->prepare("DELETE FROM JMDefinition_Gruppen WHERE GruppenUID = ?");
        $stmtDel->bind_param('i', $editId);
        if (!$stmtDel->execute()) throw new Exception($stmtDel->error);
        $stmtDel->close();
        $uid = $editId;
    } else {
        // Neue GruppenUID: MAX+1 innerhalb der Transaktion gesperrt (FOR UPDATE), damit zwei gleichzeitige
        // Speichervorgaenge nicht dieselbe UID erhalten
        $res = $conn->query("SELECT IFNULL(MAX(GruppenUID), 0) + 1 AS newUid FROM JMDefinition_Gruppen FOR UPDATE");
        if (!$res) throw new Exception($conn->error);
        $uid = (int)$res->fetch_assoc()['newUid'];
    }

    $stmt = $conn->prepare("INSERT INTO JMDefinition_Gruppen (GruppenUID, mitgliederID, JMDefinitionID, Gruppenname, Jahr) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) throw new Exception('Prepare (Insert): ' . $conn->error);
    foreach ($mitglieder as $mID) {
        $stmt->bind_param('iiisi', $uid, $mID, $eventID, $gruppenname, $jahr);
        if (!$stmt->execute()) throw new Exception($stmt->error);
    }
    $stmt->close();

    $conn->commit();
    echo json_encode([
        'success'  => true,
        'message'  => $editId > 0 ? 'Gruppe aktualisiert' : 'Gruppe gespeichert',
        'groupId'  => $uid,
        'anzahl'   => count($mitglieder),
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[save_gruppen] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gruppe konnte nicht gespeichert werden']);
}
$conn->close();
