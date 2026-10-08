<?php
//save_jshelfer.php
header('Content-Type: application/json');
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)

// CSRF-Schutz
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

// POST-Daten auslesen
$wilen     = $_POST['helferWilen']    ?? [];
$wollerau  = $_POST['helferWollerau'] ?? [];

if (!is_array($wilen) || !is_array($wollerau) || (empty($wilen) && empty($wollerau))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Es gibt nichts zu speichern.']);
    exit;
}

// Stunden: leer = 0, sonst eine Zahl von 0 bis 999 (Schritt 0.5 gibt das Formular vor)
$stundenOk = function ($v): bool {
    $v = trim((string)$v);
    return $v === '' || (is_numeric($v) && (float)$v >= 0 && (float)$v <= 999);
};
foreach ([$wilen, $wollerau, [$_POST['freierWilen'] ?? '', $_POST['freierWollerau'] ?? '']] as $werte) {
    foreach ($werte as $v) {
        if (!$stundenOk($v)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Stunden müssen eine Zahl zwischen 0 und 999 sein (z.B. 2.5). Nichts wurde gespeichert.']);
            exit;
        }
    }
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
    echo json_encode(['success' => true, 'message' => 'Helferstunden gespeichert.']);

} catch (Exception $e) {
    $conn->rollback();
    error_log('save_jshelfer: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Die Helferstunden konnten nicht gespeichert werden; es wurde nichts geändert. Deine Eingaben sind noch da, bitte nochmals speichern.']);
}

$conn->close();
