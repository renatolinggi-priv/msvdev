<?php
// save_jungschuetzen_resultate.php

include '../config.php'; // Passen Sie den Pfad an, falls nötig

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

header('Content-Type: application/json; charset=utf-8');

// Hilfsfunktion zur Übergabe von Referenzen
function refValues($arr){
    // Für PHP 7.0 und höher
    if (strnatcmp(phpversion(),'5.3') >= 0) {
        $refs = [];
        foreach($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }
    return $arr;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

if (!isset($_POST['resultate']) || !is_array($_POST['resultate'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Keine Daten zum Speichern']));
}

try {
    foreach ($_POST['resultate'] as $jungschuetzeID => $data) {
        // Dynamische Spaltenverwaltung
        $fields = [];
        $values = [];
        $types = '';

        foreach ($data as $column => $value) {
            $fields[] = "$column = ?";
            $values[] = $value;
            // Annahme: Alle Felder sind Strings. Passen Sie dies bei Bedarf an.
            $types .= 's';
        }

        if (!empty($fields)) {
            // Prüfen, ob ein Eintrag existiert
            $checkSql = "SELECT COUNT(*) as count FROM jungschuetzen_resultate WHERE JungschuetzeID = ?";
            $stmtCheck = $conn->prepare($checkSql);
            if (!$stmtCheck) {
                throw new Exception("Fehler beim Vorbereiten der Abfrage: " . $conn->error);
            }
            $stmtCheck->bind_param("i", $jungschuetzeID);
            $stmtCheck->execute();
            $resultCheck = $stmtCheck->get_result();
            $count = $resultCheck->fetch_assoc()['count'];
            $stmtCheck->close();

            if ($count > 0) {
                // Update
                $updateSql = "UPDATE jungschuetzen_resultate SET " . implode(', ', $fields) . " WHERE JungschuetzeID = ?";
                $stmtUpdate = $conn->prepare($updateSql);
                if (!$stmtUpdate) {
                    throw new Exception("Fehler beim Vorbereiten des Update-Statements: " . $conn->error);
                }

                // Parameter für bind_param
                $bind_params = array_merge([$types . 'i'], $values, [$jungschuetzeID]);
                $bind_params = refValues($bind_params);

                // bind_param mit call_user_func_array
                call_user_func_array([$stmtUpdate, 'bind_param'], $bind_params);

                if (!$stmtUpdate->execute()) {
                    throw new Exception("Fehler beim Ausführen des Update-Statements: " . $stmtUpdate->error);
                }
                $stmtUpdate->close();
            } else {
                // Insert
                $insertFields = array_keys($data);
                $insertPlaceholders = implode(', ', array_fill(0, count($insertFields), '?'));
                $insertSql = "INSERT INTO jungschuetzen_resultate (JungschuetzeID, " . implode(', ', $insertFields) . ") VALUES (?, $insertPlaceholders)";
                $stmtInsert = $conn->prepare($insertSql);
                if (!$stmtInsert) {
                    throw new Exception("Fehler beim Vorbereiten des Insert-Statements: " . $conn->error);
                }

                // Parameter für bind_param
                $insert_types = 'i' . str_repeat('s', count($values)); // 'i' für JungschuetzeID und 's' für die restlichen Felder
                $bind_params = array_merge([$insert_types], [$jungschuetzeID], $values);
                $bind_params = refValues($bind_params);

                // bind_param mit call_user_func_array
                call_user_func_array([$stmtInsert, 'bind_param'], $bind_params);

                if (!$stmtInsert->execute()) {
                    throw new Exception("Fehler beim Ausführen des Insert-Statements: " . $stmtInsert->error);
                }
                $stmtInsert->close();
            }
        }
    }

    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Daten erfolgreich gespeichert']);
} catch (Exception $e) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
