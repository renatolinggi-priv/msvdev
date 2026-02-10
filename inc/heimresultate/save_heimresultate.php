<?php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$jahr = isset($_POST['jahr']) ? intval($_POST['jahr']) : intval(date('Y')); // Jahr wird aus der POST-Anfrage übernommen, falls nicht gesetzt, Standardwert ist das aktuelle Jahr

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $conn->connect_error]));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $passe = $_POST['passe'];

        foreach ($passe as $mitgliedID => $passen) {
            $mitgliedID = intval($mitgliedID);

            $checkStmt = $conn->prepare("SELECT * FROM heimresultate WHERE MitgliedID = ? AND Jahr = ?");
            if (!$checkStmt) {
                throw new Exception("Fehler beim Vorbereiten der SELECT-Abfrage: " . $conn->error);
            }
            $checkStmt->bind_param("ii", $mitgliedID, $jahr);
            if (!$checkStmt->execute()) {
                throw new Exception("Fehler beim Ausführen der SELECT-Abfrage: " . $checkStmt->error);
            }
            $resultateResult = $checkStmt->get_result();

            if ($resultateResult->num_rows > 0) {
                $checkStmt->close();

                // Dynamisch SET-Klausel aufbauen – nur gesetzte Felder
                $setParts = [];
                $values = [];
                $types = '';
                for ($i = 1; $i <= 8; $i++) {
                    // Speichere auch 0-Werte, aber nur wenn sie gesetzt sind
                    if (isset($passen[$i]) && $passen[$i] !== ''){
                        $setParts[] = "Passe$i = ?";
                        $values[] = $passen[$i];
                        $types .= 's';
                    }
                }

                if (!empty($setParts)) {
                    $updateSql = "UPDATE heimresultate SET " . implode(", ", $setParts) . " WHERE MitgliedID = ? AND Jahr = ?";
                    $types .= 'ii';
                    $values[] = $mitgliedID;
                    $values[] = $jahr;
                    $stmt = $conn->prepare($updateSql);
                    if (!$stmt) {
                        throw new Exception("Fehler beim Vorbereiten der UPDATE-Abfrage: " . $conn->error);
                    }
                    $stmt->bind_param($types, ...$values);
                    if (!$stmt->execute()) {
                        throw new Exception("Fehler beim Ausführen der UPDATE-Abfrage: " . $stmt->error);
                    }
                    $stmt->close();
                }
            } else {
                $checkStmt->close();

                // Prüfe ob irgendeine Passe einen Wert hat (auch 0)
                $hasAnyValue = false;
                for ($i = 1; $i <= 8; $i++) {
                    if (isset($passen[$i]) && $passen[$i] !== '') {
                        $hasAnyValue = true;
                        break;
                    }
                }

                if($hasAnyValue){
                    $insertSql = "INSERT INTO heimresultate (MitgliedID, Jahr, Passe1, Passe2, Passe3, Passe4, Passe5, Passe6, Passe7, Passe8) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insertSql);
                    if (!$stmt) {
                        throw new Exception("Fehler beim Vorbereiten der INSERT-Abfrage: " . $conn->error);
                    }
                    $insertValues = [$mitgliedID, $jahr];
                    for ($i = 1; $i <= 8; $i++) {
                        $insertValues[] = (isset($passen[$i]) && $passen[$i] !== '') ? $passen[$i] : '0';
                    }
                    $stmt->bind_param("iissssssss", ...$insertValues);
                    if (!$stmt->execute()) {
                        throw new Exception("Fehler beim Ausführen der INSERT-Abfrage: " . $stmt->error);
                    }
                    $stmt->close();
                }
            }
        }

        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Alle Ergebnisse wurden erfolgreich gespeichert']);
    } catch (Exception $e) {
        $conn->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    $conn->close();
}
?>
