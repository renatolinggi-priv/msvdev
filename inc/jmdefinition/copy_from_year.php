<?php
// copy_from_year.php – Übernimmt ausgewählte Anlässe (mit bereits verschobenem Datum
// und angepasstem Namen) ins Zieljahr. Fügt JMDefinition ein und befüllt JMSchiesstage
// direkt aus dem (kanonischen) Schiesstage-Text.
include '../config.php';

// CSRF-Schutz (Muster wie save_jmdefinition.php)
require_once __DIR__ . '/../session_config.inc.php';
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}

$targetYear = isset($_POST['target_year']) ? intval($_POST['target_year']) : 0;
$events = isset($_POST['events']) && is_array($_POST['events']) ? $_POST['events'] : [];

if ($targetYear < 2000 || $targetYear > 2100 || empty($events)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültige Daten']));
}

$conn->begin_transaction();
try {
    $res = $conn->query("SELECT MAX(Reihenfolge) AS m FROM JMDefinition WHERE year = " . $targetYear);
    $rowMax = $res ? $res->fetch_assoc() : null;
    $reihenfolge = (int)($rowMax['m'] ?? 0);

    $stmt = $conn->prepare("
        INSERT INTO JMDefinition (Reihenfolge, Bezeichnung, Maxpunkte, Streicher, Erweitert, Schiesstage, Info, Gruppe, hidden, year, Adresse, Zuschlag)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
    }
    $stmtSch = $conn->prepare("INSERT INTO JMSchiesstage (jm_id, schiesstag, start_time, end_time, year) VALUES (?, ?, ?, ?, ?)");
    if (!$stmtSch) {
        throw new Exception('Prepare (Schiesstage) fehlgeschlagen: ' . $conn->error);
    }
    $monthsMap = ['Januar'=>1,'Februar'=>2,'März'=>3,'April'=>4,'Mai'=>5,'Juni'=>6,'Juli'=>7,'August'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Dezember'=>12];

    $count = 0;
    foreach ($events as $ev) {
        $reihenfolge++;
        $bezeichnung = trim((string)($ev['bezeichnung'] ?? ''));
        if ($bezeichnung === '') { continue; }
        $maxpunkte = intval($ev['maxpunkte'] ?? 0);
        $streicher = !empty($ev['streicher']) ? 1 : 0;
        $erweitert = !empty($ev['erweitert']) ? 1 : 0;
        $schiesstage = trim((string)($ev['schiesstage'] ?? ''));
        $info = !empty($ev['info']) ? 1 : 0;
        $gruppe = !empty($ev['gruppe']) ? 1 : 0;
        $hidden = 0;
        $adresse = trim((string)($ev['adresse'] ?? ''));
        $zuschlag = intval($ev['zuschlag'] ?? 0);

        $stmt->bind_param(
            "isiiisiiiisi",
            $reihenfolge, $bezeichnung, $maxpunkte, $streicher, $erweitert,
            $schiesstage, $info, $gruppe, $hidden, $targetYear, $adresse, $zuschlag
        );
        if (!$stmt->execute()) {
            throw new Exception('Insert fehlgeschlagen: ' . $stmt->error);
        }
        $jmId = $conn->insert_id;
        $count++;

        // JMSchiesstage aus kanonischem Text befüllen ("Wochentag TT. Monat JJJJ HH:MM – HH:MM Uhr, ...")
        foreach (preg_split('/\r?\n/', $schiesstage) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (!preg_match('/^\S+\s+(\d{1,2})\.\s+(\S+)\s+(\d{4})\s+(.*)$/u', $line, $mm)) continue;
            $mon = $monthsMap[$mm[2]] ?? 0;
            if (!$mon) continue;
            $dateStr = sprintf('%04d-%02d-%02d', (int)$mm[3], $mon, (int)$mm[1]);
            if (preg_match_all('/(\d{1,2})[:.](\d{2})\s*[–-]\s*(\d{1,2})[:.](\d{2})/u', $mm[4], $tm, PREG_SET_ORDER)) {
                foreach ($tm as $t) {
                    $st = sprintf('%02d:%02d:00', (int)$t[1], (int)$t[2]);
                    $et = sprintf('%02d:%02d:00', (int)$t[3], (int)$t[4]);
                    $stmtSch->bind_param('isssi', $jmId, $dateStr, $st, $et, $targetYear);
                    if (!$stmtSch->execute()) {
                        throw new Exception('Schiesstage-Insert fehlgeschlagen: ' . $stmtSch->error);
                    }
                }
            }
        }
    }
    $stmt->close();
    $stmtSch->close();
    $conn->commit();

    echo json_encode(['success' => true, 'count' => $count]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}

$conn->close();
