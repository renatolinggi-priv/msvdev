<?php
// config.php einbinden, um die Datenbankverbindung herzustellen
require_once '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

header('Content-Type: application/json; charset=utf-8');

try {
    // Werte aus dem Formular holen
    $member_id = $_POST['member_id'];
    $wert = (int)($_POST['wert'] ?? 0);
    $siegerdef = $_POST['siegerdef'];
    $year = $_POST['year'];

    // Validierung: Wert muss positiv sein (kein 0-Punkte-Sieger)
    if ($wert <= 0) {
        http_response_code(422);
        die(json_encode(['success' => false, 'message' => 'Wert muss grösser als 0 sein']));
    }

    // Mitglied-Name aus der Tabelle `mitglieder` holen
    $sql = "SELECT Vorname, Name FROM mitglieder WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $name = $row['Name'] . ' ' . $row['Vorname'];

        // Daten in die Tabelle `sieger` einfügen
        $sql = "INSERT INTO sieger (Name, Wert, siegerdef, year) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siii", $name, $wert, $siegerdef, $year);
        if (!$stmt->execute()) {
            throw new Exception("Fehler beim Speichern: " . $stmt->error);
        }

        $stmt->close();
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Neuer Sieger erfolgreich gespeichert']);
    } else {
        $stmt->close();
        $conn->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Mitglied nicht gefunden']);
    }
} catch (Exception $e) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
