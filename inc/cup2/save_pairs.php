<?php
/**
 * save_pairs.php - Optimierte Version
 * Speichert Paarungen für Runden 1, 2 und Finale
 * Mit verbesserter Fehlerbehandlung und Prepared Statements
 */

include '../config.php';

// Header für JSON-Response
header('Content-Type: application/json');

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

// Logging für Debugging
error_log("Empfangene Daten: " . print_r($_POST, true));

// Daten dekodieren und validieren
$pairs = isset($_POST['pairs']) ? $_POST['pairs'] : [];
if (!is_array($pairs)) {
    $pairs = json_decode($pairs, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die(json_encode(['success' => false, 'error' => 'Ungültige JSON-Daten: ' . json_last_error_msg()]));
    }
}

if (!is_array($pairs) || empty($pairs)) {
    die(json_encode(['success' => false, 'error' => 'Keine Paarungsdaten erhalten']));
}

// Weitere Parameter validieren
$year  = isset($_POST['year'])  ? (int)$_POST['year']  : date('Y');
$round = isset($_POST['round']) ? (int)$_POST['round'] : 0;

if ($round < 1 || $round > 3) {
    die(json_encode(['success' => false, 'error' => 'Ungültige Runde: ' . $round]));
}

// Variablen für Tracking
$errors         = [];
$success_count  = 0;
$updated_count  = 0;
$inserted_count = 0;

// Transaktion starten für Datenkonsistenz
$conn->begin_transaction();

try {
    if ($round === 3) {
        // ========== FINALE SPEICHERN ==========
        $stmt_check  = $conn->prepare("SELECT ID FROM cupFinalResults WHERE ParticipantID = ? AND Year = ?");
        $stmt_insert = $conn->prepare("INSERT INTO cupFinalResults (ParticipantID, Result, LowShot, Year) VALUES (?, ?, ?, ?)");
        $stmt_update = $conn->prepare("UPDATE cupFinalResults SET Result = ?, LowShot = ? WHERE ParticipantID = ? AND Year = ?");
        if (!$stmt_check || !$stmt_insert || !$stmt_update) {
            throw new Exception("Prepare Statement fehlgeschlagen: " . $conn->error);
        }

        foreach ($pairs as $index => $pair) {
            // Validierung der Eingabedaten
            if (!is_array($pair) || count($pair) < 3) {
                $errors[] = "Ungültige Daten für Eintrag " . ($index + 1);
                continue;
            }

            $participant_id = (int)$pair[0];
            $result         = isset($pair[1]) && is_numeric($pair[1]) ? (int)$pair[1] : null;
            $lowshot        = isset($pair[2]) && is_numeric($pair[2]) ? (int)$pair[2] : null;

            // Prüfen ob Teilnehmer existiert
            if ($participant_id <= 0) {
                $errors[] = "Ungültige Teilnehmer-ID für Eintrag " . ($index + 1);
                continue;
            }

            // Prüfen ob bereits vorhanden
            $stmt_check->bind_param("ii", $participant_id, $year);
            $stmt_check->execute();
            $exists = $stmt_check->get_result()->num_rows > 0;

            if ($exists) {
                // Update
                $stmt_update->bind_param("iiii", $result, $lowshot, $participant_id, $year);
                if ($stmt_update->execute()) {
                    $updated_count++;
                    $success_count++;
                } else {
                    $errors[] = "Update fehlgeschlagen für Teilnehmer ID $participant_id: " . $stmt_update->error;
                }
            } else {
                // Insert
                $stmt_insert->bind_param("iiii", $participant_id, $result, $lowshot, $year);
                if ($stmt_insert->execute()) {
                    $inserted_count++;
                    $success_count++;
                } else {
                    $errors[] = "Insert fehlgeschlagen für Teilnehmer ID $participant_id: " . $stmt_insert->error;
                }
            }
        }

        $stmt_check->close();
        $stmt_insert->close();
        $stmt_update->close();

    } else {
        // ========== RUNDEN 1 UND 2 SPEICHERN ==========
        $stmt_check  = $conn->prepare("SELECT ID FROM cupPairs WHERE Participant1 = ? AND Participant2 = ? AND Round = ? AND Year = ?");
        $stmt_insert = $conn->prepare("INSERT INTO cupPairs (Participant1, Participant2, Participant3, Result1, LowShot1, Result2, LowShot2, Result3, LowShot3, Round, Year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_update = $conn->prepare("UPDATE cupPairs SET Result1 = ?, LowShot1 = ?, Result2 = ?, LowShot2 = ?, Result3 = ?, LowShot3 = ?, Participant3 = ? WHERE Participant1 = ? AND Participant2 = ? AND Round = ? AND Year = ?");
        if (!$stmt_check || !$stmt_insert || !$stmt_update) {
            throw new Exception("Prepare Statement fehlgeschlagen: " . $conn->error);
        }

        foreach ($pairs as $index => $pair) {
            // ======= LEERE PAARUNGEN ÜBERSPRINGEN =======
            // Erst prüfen, ob es mindestens zwei gültige Teilnehmer-IDs > 0 gibt
            $validIds = array_filter(array_slice($pair, 0, 3), function($v){
                return is_numeric($v) && $v > 0;
            });
            if (count($validIds) < 2) {
                // kein echtes 2er- oder 3er-Paar → skip
                continue;
            }

            // Bestimme ob es eine 2er oder 3er Paarung ist
            $is_three_pair = count($pair) > 6;

            // Teilnehmer IDs extrahieren
            $p1 = (int)$pair[0];
            $p2 = (int)$pair[1];
            $p3 = $is_three_pair && isset($pair[2]) ? (int)$pair[2] : null;

            // Validierung der Teilnehmer IDs
            if ($p1 <= 0 || $p2 <= 0 || ($is_three_pair && $p3 <= 0)) {
                $errors[] = "Ungültige Teilnehmer-IDs für Paarung " . ($index + 1);
                continue;
            }

            // Ergebnisse extrahieren basierend auf Paarungstyp
            if ($is_three_pair) {
                // 3er Paarung
                $r1  = isset($pair[3]) && is_numeric($pair[3]) ? (int)$pair[3] : null;
                $ls1 = isset($pair[6]) && is_numeric($pair[6]) ? (int)$pair[6] : null;
                $r2  = isset($pair[4]) && is_numeric($pair[4]) ? (int)$pair[4] : null;
                $ls2 = isset($pair[7]) && is_numeric($pair[7]) ? (int)$pair[7] : null;
                $r3  = isset($pair[5]) && is_numeric($pair[5]) ? (int)$pair[5] : null;
                $ls3 = isset($pair[8]) && is_numeric($pair[8]) ? (int)$pair[8] : null;
            } else {
                // 2er Paarung
                $r1  = isset($pair[2]) && is_numeric($pair[2]) ? (int)$pair[2] : null;
                $ls1 = isset($pair[4]) && is_numeric($pair[4]) ? (int)$pair[4] : null;
                $r2  = isset($pair[3]) && is_numeric($pair[3]) ? (int)$pair[3] : null;
                $ls2 = isset($pair[5]) && is_numeric($pair[5]) ? (int)$pair[5] : null;
                $r3  = null;
                $ls3 = null;
            }

            // Prüfen ob Paarung bereits existiert
            $stmt_check->bind_param("iiii", $p1, $p2, $round, $year);
            $stmt_check->execute();
            $exists = $stmt_check->get_result()->num_rows > 0;

            if ($exists) {
                // Update
                $stmt_update->bind_param(
                    "iiiiiiiiiii",
                    $r1, $ls1, $r2, $ls2, $r3, $ls3, $p3,
                    $p1, $p2, $round, $year
                );
                if ($stmt_update->execute()) {
                    $updated_count++;
                    $success_count++;
                } else {
                    $errors[] = "Update fehlgeschlagen für Paarung $p1 vs $p2: " . $stmt_update->error;
                }
            } else {
                // Insert
                $stmt_insert->bind_param(
                    "iiiiiiiiiii",
                    $p1, $p2, $p3,
                    $r1, $ls1, $r2, $ls2, $r3, $ls3,
                    $round, $year
                );
                if ($stmt_insert->execute()) {
                    $inserted_count++;
                    $success_count++;
                } else {
                    $errors[] = "Insert fehlgeschlagen für Paarung $p1 vs $p2: " . $stmt_insert->error;
                }
            }
        }

        $stmt_check->close();
        $stmt_insert->close();
        $stmt_update->close();
    }

    // Transaktion abschließen
    $conn->commit();

    // Response erstellen
    $response = [
        'success' => empty($errors),
        'message' => "$success_count Einträge erfolgreich verarbeitet",
        'details' => [
            'total'    => count($pairs),
            'saved'    => $success_count,
            'inserted' => $inserted_count,
            'updated'  => $updated_count,
            'errors'   => count($errors)
        ]
    ];
    if (!empty($errors)) {
        $response['errors'] = $errors;
        http_response_code($success_count > 0 ? 207 : 400);
    }
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Kritischer Fehler in save_pairs.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Datenbankfehler: ' . $e->getMessage(),
        'details' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}

$conn->close();
