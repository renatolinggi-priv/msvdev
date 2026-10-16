<?php
/**
 * save_pairs.php – Paarungen einer Runde (1 oder 2) speichern.
 *
 * POST pairs (JSON), year, round. Seit 1.68.31 ein Objekt je Paarung:
 *   {id: ID|null, p: [Teilnehmer1, Teilnehmer2, (Teilnehmer3)], r: [Resultat …], adv: 1|2, mw: ID|−ID|null}
 *   id   – gespeicherte Paarung: wird über ihre ID aktualisiert, auch wenn Teilnehmer getauscht wurden
 *          (vorher erkannte der Server eine Paarung an Teilnehmer 1 + 2; ein Tausch legte eine zweite an)
 *   adv  – Dreier: wie viele weiterkommen
 *   mw   – manuell gewählter Gewinner bei Gleichstand (negativ = ausgeschieden beim Dreier-Gleichstand)
 * Das alte Listenformat [id1, id2, (id3), r1, r2, (r3), ls…, (adv)] wird weiter angenommen.
 *
 * Antwort: {success, message, details}; bei Fehlern success:false mit HTTP 207 (Teilerfolg) oder 400.
 */

include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)

// CSRF-Schutz
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$pairs = isset($_POST['pairs']) ? $_POST['pairs'] : [];
if (!is_array($pairs)) {
    $pairs = json_decode($pairs, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Die Paarungen kamen unvollständig an. Bitte die Seite neu laden und nochmals speichern.']));
    }
}

if (!is_array($pairs) || empty($pairs)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Keine Paarungen zum Speichern.']));
}

$year  = isset($_POST['year'])  ? (int)$_POST['year']  : (int)date('Y');
$round = isset($_POST['round']) ? (int)$_POST['round'] : 0;

if ($round < 1 || $round > 3) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Unbekannte Runde. Bitte die Seite neu laden.']));
}

/** Eine Paarung aus dem neuen oder alten Format lesen. */
function cupPaarungLesen($pair): ?array
{
    if (!is_array($pair)) return null;
    $zahl = function ($v) { return (isset($v) && is_numeric($v)) ? (int)$v : null; };

    if (isset($pair['p'])) {
        $p = array_values(array_filter(array_map('intval', (array)$pair['p']), function ($v) { return $v > 0; }));
        $r = array_values((array)($pair['r'] ?? []));
        $mw = $zahl($pair['mw'] ?? null);
        return [
            'id'  => $zahl($pair['id'] ?? null),
            'p'   => $p,
            'r'   => [$zahl($r[0] ?? null), $zahl($r[1] ?? null), $zahl($r[2] ?? null)],
            'adv' => ((int)($pair['adv'] ?? 2) === 1) ? 1 : 2,
            'mw'  => $mw ?: null,
            'mwGesendet' => true,
        ];
    }

    // Altes Listenformat
    $ids = array_values(array_filter(array_slice($pair, 0, 3), function ($v) { return is_numeric($v) && $v > 0; }));
    $drei = count($pair) > 6;
    if ($drei) {
        return ['id' => null, 'p' => array_map('intval', array_slice($pair, 0, 3)),
                'r' => [$zahl($pair[3] ?? null), $zahl($pair[4] ?? null), $zahl($pair[5] ?? null)],
                'adv' => ((int)($pair[9] ?? 2) === 1) ? 1 : 2, 'mw' => null, 'mwGesendet' => false];
    }
    return ['id' => null, 'p' => array_map('intval', array_slice($ids, 0, 2)),
            'r' => [$zahl($pair[2] ?? null), $zahl($pair[3] ?? null), null],
            'adv' => 2, 'mw' => null, 'mwGesendet' => false];
}

$errors         = [];
$success_count  = 0;
$updated_count  = 0;
$inserted_count = 0;

$conn->begin_transaction();

try {
    if ($round === 3) {
        // ========== FINALE (Altweg; die Seite speichert das Finale über save_finalresults.php) ==========
        $stmt_check  = $conn->prepare("SELECT ID FROM cupFinalResults WHERE ParticipantID = ? AND Year = ?");
        $stmt_insert = $conn->prepare("INSERT INTO cupFinalResults (ParticipantID, Result, LowShot, Year) VALUES (?, ?, ?, ?)");
        $stmt_update = $conn->prepare("UPDATE cupFinalResults SET Result = ?, LowShot = ? WHERE ParticipantID = ? AND Year = ?");
        if (!$stmt_check || !$stmt_insert || !$stmt_update) {
            throw new Exception("Prepare Statement fehlgeschlagen: " . $conn->error);
        }

        foreach ($pairs as $index => $pair) {
            if (!is_array($pair) || count($pair) < 3) {
                $errors[] = "Eintrag " . ($index + 1) . " ist unvollständig.";
                continue;
            }
            $participant_id = (int)$pair[0];
            $result         = isset($pair[1]) && is_numeric($pair[1]) ? (int)$pair[1] : null;
            $lowshot        = isset($pair[2]) && is_numeric($pair[2]) ? (int)$pair[2] : null;
            if ($participant_id <= 0) {
                $errors[] = "Eintrag " . ($index + 1) . " hat keinen Teilnehmer.";
                continue;
            }
            $stmt_check->bind_param("ii", $participant_id, $year);
            $stmt_check->execute();
            $exists = $stmt_check->get_result()->num_rows > 0;
            if ($exists) {
                $stmt_update->bind_param("iiii", $result, $lowshot, $participant_id, $year);
                $ok = $stmt_update->execute();
            } else {
                $stmt_insert->bind_param("iiii", $participant_id, $result, $lowshot, $year);
                $ok = $stmt_insert->execute();
            }
            if ($ok) { $exists ? $updated_count++ : $inserted_count++; $success_count++; }
            else { $errors[] = "Finalresultat " . ($index + 1) . " wurde nicht gespeichert."; }
        }
    } else {
        // ========== RUNDEN 1 UND 2 ==========
        $stmt_by_id = $conn->prepare("SELECT ID FROM cupPairs WHERE ID = ? AND Round = ? AND Year = ?");
        $stmt_check = $conn->prepare("SELECT ID FROM cupPairs WHERE Participant1 = ? AND Participant2 = ? AND Round = ? AND Year = ?");
        $stmt_insert = $conn->prepare(
            "INSERT INTO cupPairs (Participant1, Participant2, Participant3, Advancers, Result1, Result2, Result3,
                                   LowShot1, LowShot2, LowShot3, ManualWinner, ManualWinnerReason, Round, Year)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, ?, ?, ?, ?)"
        );
        // Teilnehmer, Resultate, «weiter» und manueller Gewinner über die ID der Paarung
        $stmt_update = $conn->prepare(
            "UPDATE cupPairs SET Participant1 = ?, Participant2 = ?, Participant3 = ?, Advancers = ?,
                                 Result1 = ?, Result2 = ?, Result3 = ?, LowShot1 = NULL, LowShot2 = NULL, LowShot3 = NULL,
                                 ManualWinner = ?, ManualWinnerReason = ?
             WHERE ID = ?"
        );
        // Altformat ohne manuellen Gewinner: ManualWinner bleibt, wie er ist
        $stmt_update_alt = $conn->prepare(
            "UPDATE cupPairs SET Participant3 = ?, Advancers = ?, Result1 = ?, Result2 = ?, Result3 = ?,
                                 LowShot1 = NULL, LowShot2 = NULL, LowShot3 = NULL
             WHERE ID = ?"
        );
        if (!$stmt_by_id || !$stmt_check || !$stmt_insert || !$stmt_update || !$stmt_update_alt) {
            throw new Exception("Prepare Statement fehlgeschlagen: " . $conn->error);
        }

        foreach ($pairs as $index => $roh) {
            $pair = cupPaarungLesen($roh);
            if (!$pair || count($pair['p']) < 2) {
                continue; // leere oder halb belegte Paarung: nichts zu speichern
            }
            $p  = $pair['p'];
            $p1 = $p[0];
            $p2 = $p[1];
            $p3 = $p[2] ?? null;
            $drei = $p3 !== null;
            $adv = $drei ? $pair['adv'] : null;
            [$r1, $r2, $r3] = $pair['r'];
            if (!$drei) $r3 = null;

            // Manueller Gewinner nur, wenn er (bzw. bei negativem Wert der Ausgeschiedene) in der Paarung steht
            $mw = $pair['mw'];
            if ($mw !== null && !in_array(abs($mw), $p, true)) $mw = null;
            $grund = $mw === null ? null : ($mw < 0 ? 'Dreier-Gleichstand - manuell' : 'Gleichstand - manuell');

            // Bestehende Paarung finden: zuerst über die ID, sonst (neue Karte, doppelt gespeichert) über Teilnehmer 1 + 2
            $id = null;
            if ($pair['id']) {
                $stmt_by_id->bind_param("iii", $pair['id'], $round, $year);
                $stmt_by_id->execute();
                $row = $stmt_by_id->get_result()->fetch_assoc();
                if (!$row) {
                    $errors[] = "Paarung " . ($index + 1) . " gibt es nicht mehr. Bitte die Seite neu laden.";
                    continue;
                }
                $id = (int)$row['ID'];
            } else {
                $stmt_check->bind_param("iiii", $p1, $p2, $round, $year);
                $stmt_check->execute();
                $row = $stmt_check->get_result()->fetch_assoc();
                if ($row) $id = (int)$row['ID'];
            }

            if ($id !== null && $pair['mwGesendet']) {
                $stmt_update->bind_param("iiiiiiiisi", $p1, $p2, $p3, $adv, $r1, $r2, $r3, $mw, $grund, $id);
                $ok = $stmt_update->execute();
                $err = $stmt_update->error;
            } elseif ($id !== null) {
                $stmt_update_alt->bind_param("iiiiii", $p3, $adv, $r1, $r2, $r3, $id);
                $ok = $stmt_update_alt->execute();
                $err = $stmt_update_alt->error;
            } else {
                $stmt_insert->bind_param("iiiiiiiisii", $p1, $p2, $p3, $adv, $r1, $r2, $r3, $mw, $grund, $round, $year);
                $ok = $stmt_insert->execute();
                $err = $stmt_insert->error;
            }

            if ($ok) {
                $id !== null ? $updated_count++ : $inserted_count++;
                $success_count++;
            } else {
                error_log("save_pairs.php: Paarung $p1/$p2 Runde $round $year: $err");
                $errors[] = "Paarung " . ($index + 1) . " wurde nicht gespeichert.";
            }
        }
    }

    $conn->commit();

    $response = [
        'success' => empty($errors),
        'message' => empty($errors)
            ? "$success_count Paarungen gespeichert"
            : implode(' ', $errors),
        'details' => [
            'total'    => count($pairs),
            'saved'    => $success_count,
            'inserted' => $inserted_count,
            'updated'  => $updated_count,
            'errors'   => count($errors)
        ]
    ];
    if (!empty($errors)) {
        http_response_code($success_count > 0 ? 207 : 400);
    }
    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => msvFehler('Speichern hat nicht geklappt. Bitte nochmals versuchen.', $e)
    ]);
}

$conn->close();
