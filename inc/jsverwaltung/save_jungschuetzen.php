<?php
require_once '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        foreach ($_POST['ahvnummer'] as $id => $ahvnummer) {
            $id = intval($id);
            $ahvnummer = $conn->real_escape_string(trim($ahvnummer));
            $name = $conn->real_escape_string(trim($_POST['name'][$id]));
            $vorname = $conn->real_escape_string(trim($_POST['vorname'][$id]));
            $geburtsdatum = $conn->real_escape_string(trim($_POST['geburtsdatum'][$id]));
            $strasse = $conn->real_escape_string(trim($_POST['strasse'][$id]));
            $plz = $conn->real_escape_string(trim($_POST['plz'][$id]));
            $ort = $conn->real_escape_string(trim($_POST['ort'][$id]));
            $kursnummer = intval($_POST['kursnummer'][$id]);

            // AHV-Nummer Format überprüfen (Optional)
            if (!preg_match('/^\d{3}\.\d{4}\.\d{4}\.\d{2}$/', $ahvnummer)) {
                throw new Exception("Ungültiges AHV-Nummer Format für ID $id");
            }

            // Vorbereitetes Statement verwenden
            $stmt = $conn->prepare("UPDATE jungschuetzen SET AHVNummer=?, Name=?, Vorname=?, Geburtsdatum=?, Strasse=?, PLZ=?, Ort=?, KursNummer=? WHERE id=?");
            if (!$stmt) {
                throw new Exception("Fehler beim Vorbereiten des Statements: " . $conn->error);
            }
            $stmt->bind_param("sssssssii", $ahvnummer, $name, $vorname, $geburtsdatum, $strasse, $plz, $ort, $kursnummer, $id);
            if (!$stmt->execute()) {
                throw new Exception("Fehler beim Speichern für ID $id: " . $stmt->error);
            }
            $stmt->close();
        }

        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Änderungen erfolgreich gespeichert']);
    } catch (Exception $e) {
        $conn->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    $conn->close();
}
