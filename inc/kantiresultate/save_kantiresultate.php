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
$jahr = isset($_POST['jahr']) ? $_POST['jahr'] : date('Y'); // Jahr wird aus der POST-Anfrage übernommen, falls nicht gesetzt, Standardwert ist das aktuelle Jahr

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $conn->connect_error]));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $passe = $_POST['passe'];

        foreach ($passe as $mitgliedID => $passen) {
            $resultateSql = "SELECT * FROM kantiresultate WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";
            $resultateResult = $conn->query($resultateSql);

            if ($resultateResult === FALSE) {
                throw new Exception("Fehler bei SELECT: " . $conn->error);
            }

            if ($resultateResult->num_rows > 0) {
                $updateSql = "UPDATE kantiresultate SET ";
                for ($i = 1; $i <= 5; $i++) {
                    // Speichere auch 0-Werte, aber nur wenn sie gesetzt sind
                    if (isset($passen[$i]) && $passen[$i] !== ''){
                        $updateSql .= "Passe$i = '" . $passen[$i] . "', ";
                    }
                }
                $updateSql = rtrim($updateSql, ', ');
                $updateSql .= " WHERE MitgliedID = $mitgliedID AND Jahr = $jahr";
                if ($conn->query($updateSql) === FALSE) {
                    throw new Exception("Fehler bei UPDATE: " . $conn->error);
                }
            } else {
                // Prüfe ob irgendeine Passe einen Wert hat (auch 0)
                $hasAnyValue = false;
                for ($i = 1; $i <= 5; $i++) {
                    if (isset($passen[$i]) && $passen[$i] !== '') {
                        $hasAnyValue = true;
                        break;
                    }
                }

                if($hasAnyValue){
                    $insertSql = "INSERT INTO kantiresultate (MitgliedID, Jahr, Passe1, Passe2, Passe3, Passe4, Passe5) VALUES ($mitgliedID, $jahr, ";
                    for ($i = 1; $i <= 5; $i++) {
                        $value = isset($passen[$i]) && $passen[$i] !== '' ? $passen[$i] : '0';
                        $insertSql .= "'" . $value . "', ";
                    }
                    $insertSql = rtrim($insertSql, ', ');
                    $insertSql .= ")";
                    if ($conn->query($insertSql) === FALSE) {
                        throw new Exception("Fehler bei INSERT: " . $conn->error);
                    }
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