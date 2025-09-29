<?php
//save_jshelfer.php
header('Content-Type: application/json');
include '../config.php';
file_put_contents('debug_post.txt', print_r($_POST, true));

// POST-Daten auslesen
$wilen     = $_POST['helferWilen']    ?? [];
$wollerau  = $_POST['helferWollerau'] ?? [];

if (empty($wilen) && empty($wollerau)) {
    echo json_encode(['error' => 'Keine Daten übermittelt.']);
    exit;
}

$conn->begin_transaction();
try {
    // ================================
    // 1. Eventgebundene Einträge
    // ================================
    foreach ($wilen as $helferKey => $wilenStunden) {
        $wilenStunden = floatval($wilenStunden);
        $wollerauStunden = isset($wollerau[$helferKey]) ? floatval($wollerau[$helferKey]) : 0;

        if ($wilenStunden == 0 && $wollerauStunden == 0) {
            continue;
        }

        if (is_numeric($helferKey)) {
            // UPDATE nach helferID
            $stmtUpdate = $conn->prepare("UPDATE jungschuetzen_helfer SET helferWilen = ?, helferWollerau = ?, angeletAM = NOW() WHERE ID = ?");
            $stmtUpdate->bind_param("ddi", $wilenStunden, $wollerauStunden, $helferKey);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        } else {
            // INSERT für neuen Event-Eintrag
            $eventID = intval(str_replace('new_', '', $helferKey));

            // Event-ID prüfen
            $checkStmt = $conn->prepare("SELECT ID FROM wichtige_termine WHERE ID = ?");
            $checkStmt->bind_param("i", $eventID);
            $checkStmt->execute();
            $res = $checkStmt->get_result();
            $validEvent = $res->num_rows > 0;
            $checkStmt->close();

            if (!$validEvent) {
                continue;
            }

            $stmtInsert = $conn->prepare("INSERT INTO jungschuetzen_helfer (eventID, helferWilen, helferWollerau, angeletAM) VALUES (?, ?, ?, NOW())");
            $stmtInsert->bind_param("idd", $eventID, $wilenStunden, $wollerauStunden);
            $stmtInsert->execute();
            $stmtInsert->close();
        }
    }

    // ================================
    // 2. Freier Eintrag ohne eventID
    // ================================
    $freierTitel     = trim($_POST['freierTitel'] ?? '');
    $freierWilen     = floatval($_POST['freierWilen'] ?? 0);
    $freierWollerau  = floatval($_POST['freierWollerau'] ?? 0);
    file_put_contents('debug_freier.txt', "Titel: $freierTitel | Wilen: $freierWilen | Wollerau: $freierWollerau\n", FILE_APPEND);

    if ($freierTitel !== '' && ($freierWilen > 0 || $freierWollerau > 0)) {
        // Prüfen, ob bereits ein Eintrag mit diesem Titel existiert (case-insensitive)
        $stmtCheck = $conn->prepare("SELECT ID FROM jungschuetzen_helfer WHERE eventID IS NULL AND LOWER(freierTitel) = LOWER(?) LIMIT 1");
        $stmtCheck->bind_param("s", $freierTitel);
        $stmtCheck->execute();
        $result = $stmtCheck->get_result();
        $existing = $result->fetch_assoc();
        $stmtCheck->close();

        if ($existing) {
            // UPDATE bestehender freier Eintrag
            $stmtUpdate = $conn->prepare("UPDATE jungschuetzen_helfer SET helferWilen = ?, helferWollerau = ?, angeletAM = NOW() WHERE ID = ?");
            $stmtUpdate->bind_param("ddi", $freierWilen, $freierWollerau, $existing['ID']);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        } else {
            // INSERT neuer freier Eintrag
            $stmtInsert = $conn->prepare("INSERT INTO jungschuetzen_helfer (eventID, freierTitel, helferWilen, helferWollerau, angeletAM) VALUES (NULL, ?, ?, ?, NOW())");
            $stmtInsert->bind_param("sdd", $freierTitel, $freierWilen, $freierWollerau);
            $stmtInsert->execute();
            $stmtInsert->close();
        }
    }

    // Abschluss
    $conn->commit();
    echo json_encode(['success' => 'Helferstunden erfolgreich gespeichert.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Fehler beim Speichern: ' . $e->getMessage()]);
}

$conn->close();
