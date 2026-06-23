<?php
// update_sieger.php — Bestehenden Sieger-Eintrag aktualisieren
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
    $id = intval($_POST['id'] ?? 0);
    $wert = intval($_POST['wert'] ?? 0);
    $siegerdef = intval($_POST['siegerdef'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($id <= 0) {
        throw new Exception('Ungültige ID');
    }

    // Falls member_id mitgesendet wird, Name daraus ableiten
    $member_id = intval($_POST['member_id'] ?? 0);
    if ($member_id > 0) {
        $stmt = $conn->prepare("SELECT Vorname, Name FROM mitglieder WHERE ID = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $name = $row['Name'] . ' ' . $row['Vorname'];
        }
        $stmt->close();
    }

    $sql = "UPDATE sieger SET Name = ?, Wert = ?, siegerdef = ? WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siii", $name, $wert, $siegerdef, $id);

    if (!$stmt->execute()) {
        throw new Exception("Fehler beim Aktualisieren: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        // Prüfen ob Eintrag existiert
        $check = $conn->prepare("SELECT ID FROM sieger WHERE ID = ?");
        $check->bind_param("i", $id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            throw new Exception("Eintrag nicht gefunden");
        }
        $check->close();
    }

    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Sieger erfolgreich aktualisiert']);

} catch (Exception $e) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
