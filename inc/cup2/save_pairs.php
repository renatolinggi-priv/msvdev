<?php
/**
 * save_pairs.php - Optimierte Version
 * Speichert Paarungen für Runden 1, 2 und Finale
 * Mit verbesserter Fehlerbehandlung und Prepared Statements
 */

include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

// Header für JSON-Response
header('Content-Type: application/json');

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// Logging für Debugging
error_log("Empfangene Daten: " . print_r($_POST, true));

// Daten dekodieren und validieren
$pairs = isset($_POST['pairs']) ? $_POST['pairs'] : [];
if (!is_array($pairs)) {
    $pairs = json_decode($pairs, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die(json_encode(['success' => false, 'message' => 'Ungültige JSON-Daten: ' . json_last_error_msg()]));
    }
}

if (!is_array($pairs) || empty($pairs)) {
    die(json_encode(['success' => false, 'message' => 'Keine Paarungsdaten erhalten']));
}

// Weitere Parameter validieren
$year  = isset($_POST['year'])  ? (int)$_POST['year']  : date('Y');
$round = isset($_POST['round']) ? (int)$_POST['round'] : 0;

if ($round < 1 || $round > 3) {
    die(json_encode(['success' => false, 'message' => 'Ungültige Runde: ' . $round]));
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
        $stmt_check = $conn->prepare("SELECT ID FROM cupPairs WHERE Participant1 = ? AND Participant2 = ? AND Round = ? AND Year = ?");
        if (!$stmt_check) {
            throw new Exception("Prepare Statement fehlgeschlagen: " . $conn->error);
        }

        // Separate Statements für 2er und 3er (damit NULL korrekt gespeichert wird)
        $stmt_insert_2 = $conn->prepare(
            "INSERT INTO cupPairs (Participant1, Participant2, Participant3, Result1, LowShot1, Result2, LowShot2, Result3, LowShot3, Round, Year)
             VALUES (?, ?, NULL, ?, NULL, ?, NULL, NULL, NULL, ?, ?)"
        );
        $stmt_insert_3 = $conn->prepare(
            "INSERT INTO cupPairs (Participant1, Participant2, Participant3, Advancers, Result1, LowShot1, Result2, LowShot2, Result3, LowShot3, Round, Year)
             VALUES (?, ?, ?, ?, ?, NULL, ?, NULL, ?, NULL, ?, ?)"
        );
        $stmt_update_2 = $conn->prepare(
            "UPDATE cupPairs SET Result1 = ?, Result2 = ?, Participant3 = NULL, Advancers = NULL, Result3 = NULL, LowShot1 = NULL, LowShot2 = NULL, LowShot3 = NULL
             WHERE Participant1 = ? AND Participant2 = ? AND Round = ? AND Year = ?"
        );
        $stmt_update_3 = $conn->prepare(
            "UPDATE cupPairs SET Result1 = ?, Result2 = ?, Result3 = ?, Participant3 = ?, Advancers = ?, LowShot1 = NULL, LowShot2 = NULL, LowShot3 = NULL
             WHERE Participant1 = ? AND Participant2 = ? AND Round = ? AND Year = ?"
        );

        foreach ($pairs as $index => $pair) {
            // ======= LEERE PAARUNGEN ÜBERSPRINGEN =======
            $validIds = array_filter(array_slice($pair, 0, 3), function($v){
                return is_numeric($v) && $v > 0;
            });
            if (count($validIds) < 2) {
                continue;
            }

            // Bestimme ob es eine 2er oder 3er Paarung ist
            $is_three_pair = count($pair) > 6;

            // Teilnehmer IDs extrahieren
            $p1 = (int)$pair[0];
            $p2 = (int)$pair[1];
            $p3 = $is_three_pair && isset($pair[2]) && (int)$pair[2] > 0 ? (int)$pair[2] : null;

            // Validierung der Teilnehmer IDs
            if ($p1 <= 0 || $p2 <= 0 || ($is_three_pair && ($p3 === null || $p3 <= 0))) {
                $errors[] = "Ungültige Teilnehmer-IDs für Paarung " . ($index + 1);
                continue;
            }

            // Ergebnisse extrahieren
            if ($is_three_pair) {
                $r1 = isset($pair[3]) && is_numeric($pair[3]) ? (int)$pair[3] : null;
                $r2 = isset($pair[4]) && is_numeric($pair[4]) ? (int)$pair[4] : null;
                $r3 = isset($pair[5]) && is_numeric($pair[5]) ? (int)$pair[5] : null;
                // Anzahl Weiterkommende (Index 9, nach den 3 LowShots) - nur 1 oder 2
                $advancers = (isset($pair[9]) && (int)$pair[9] === 1) ? 1 : 2;
            } else {
                $r1 = isset($pair[2]) && is_numeric($pair[2]) ? (int)$pair[2] : null;
                $r2 = isset($pair[3]) && is_numeric($pair[3]) ? (int)$pair[3] : null;
                $r3 = null;
            }

            // Prüfen ob Paarung bereits existiert
            $stmt_check->bind_param("iiii", $p1, $p2, $round, $year);
            $stmt_check->execute();
            $exists = $stmt_check->get_result()->num_rows > 0;

            $ok = false;
            if ($is_three_pair) {
                if ($exists) {
                    $stmt_update_3->bind_param("iiiiiiiii", $r1, $r2, $r3, $p3, $advancers, $p1, $p2, $round, $year);
                    $ok = $stmt_update_3->execute();
                } else {
                    $stmt_insert_3->bind_param("iiiiiiiii", $p1, $p2, $p3, $advancers, $r1, $r2, $r3, $round, $year);
                    $ok = $stmt_insert_3->execute();
                }
            } else {
                if ($exists) {
                    $stmt_update_2->bind_param("iiiiii", $r1, $r2, $p1, $p2, $round, $year);
                    $ok = $stmt_update_2->execute();
                } else {
                    $stmt_insert_2->bind_param("iiiiii", $p1, $p2, $r1, $r2, $round, $year);
                    $ok = $stmt_insert_2->execute();
                }
            }

            if ($ok) {
                $exists ? $updated_count++ : $inserted_count++;
                $success_count++;
            } else {
                $stmt_err = $is_three_pair
                    ? ($exists ? $stmt_update_3->error : $stmt_insert_3->error)
                    : ($exists ? $stmt_update_2->error : $stmt_insert_2->error);
                $errors[] = "Fehler für Paarung $p1 vs $p2: " . $stmt_err;
            }
        }

        $stmt_check->close();
        $stmt_insert_2->close();
        $stmt_insert_3->close();
        $stmt_update_2->close();
        $stmt_update_3->close();
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
