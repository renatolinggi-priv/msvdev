<?php
// save_gruppen.php
header('Content-Type: application/json');
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

$editId       = isset($_POST['editGroupId']) ? intval($_POST['editGroupId']) : 0;
$eventID      = isset($_POST['eventID'])     ? intval($_POST['eventID'])     : 0;
$jahr         = isset($_POST['jahr'])        ? intval($_POST['jahr'])        : date('Y');
$gruppenname  = isset($_POST['gruppenname']) ? trim($_POST['gruppenname'])   : '';
$mitglieder   = isset($_POST['mitglieder'])  ? $_POST['mitglieder']          : [];

// Validierung
if($gruppenname === '' || $eventID <= 0) {
  echo json_encode(['message' => 'Ungültige Daten.']);
  exit;
}

$conn->begin_transaction();
try {
    if($editId > 0) {
        /////////////////////////////////////////////
        // ============ UPDATE-FALL ============  //
        /////////////////////////////////////////////
        // 1) Alle Zeilen mit dieser GruppenUID löschen
        $stmtDel = $conn->prepare("DELETE FROM JMDefinition_Gruppen WHERE GruppenUID = ?");
        $stmtDel->bind_param("i", $editId);
        $stmtDel->execute();
        $stmtDel->close();

        // 2) Neu einfügen mit der selben UID
        $stmtInsert = $conn->prepare("
          INSERT INTO JMDefinition_Gruppen (GruppenUID, mitgliederID, JMDefinitionID, Gruppenname, Jahr)
          VALUES (?,?,?,?,?)
        ");

        foreach($mitglieder as $mitgliedID) {
          $mID = intval($mitgliedID);
          $stmtInsert->bind_param("iiisi", $editId, $mID, $eventID, $gruppenname, $jahr);
          $stmtInsert->execute();
        }
        $stmtInsert->close();

        echo json_encode(['success' => 'Gruppe aktualisiert.']);

    } else {
        /////////////////////////////////////////////
        // ============ INSERT-FALL ============  //
        /////////////////////////////////////////////
        // 1) Neue GruppenUID ermitteln
        //    (max + 1) - Achtung: in einer produktiven DB kann es Race Conditions geben;
        //    du könntest auch ein eigenes AUTOINCREMENT in einer Hilfstabelle nutzen.
        $sqlMax = "SELECT IFNULL(MAX(GruppenUID), 0) + 1 AS newUid FROM JMDefinition_Gruppen";
        $resMax = $conn->query($sqlMax);
        $rowMax = $resMax->fetch_assoc();
        $newUid = $rowMax['newUid'];

        // 2) Pro Mitglied -> Insert
        $stmt = $conn->prepare("
          INSERT INTO JMDefinition_Gruppen (GruppenUID, mitgliederID, JMDefinitionID, Gruppenname, Jahr)
          VALUES (?,?,?,?,?)
        ");

        foreach($mitglieder as $mitgliedID) {
            $mID = intval($mitgliedID);
            $stmt->bind_param("iiisi", $newUid, $mID, $eventID, $gruppenname, $jahr);
            $stmt->execute();
        }
        $stmt->close();

        echo json_encode(['success' => 'Gruppe wurde erfolgreich gespeichert.']);
    }

    $conn->commit();

} catch(Exception $e) {
    $conn->rollback();
    echo json_encode(['message' => 'Fehler beim Speichern: ' . $e->getMessage()]);
}

$conn->close();
