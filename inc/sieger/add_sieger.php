<?php
// add_sieger.php
require_once '../config.php';
require_once __DIR__ . '/../csrf.inc.php';

// CSRF Token prüfen
session_start();
csrf_require(true);

// Eingaben validieren
if (empty($_POST['member_id']) || empty($_POST['wert']) || empty($_POST['siegerdef']) || empty($_POST['year'])) {
    echo json_encode(['success' => false, 'message' => 'Alle Felder sind erforderlich']);
    exit;
}

$member_id = intval($_POST['member_id']);
$wert = intval($_POST['wert']);
$siegerdef = intval($_POST['siegerdef']);
$year = intval($_POST['year']);

try {
    // Mitglied-Name aus der Tabelle `mitglieder` holen
    $sql = "SELECT Vorname, Name FROM mitglieder WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $name = $row['Vorname'] . ' ' . $row['Name'];

        // Daten in die Tabelle `sieger` einfügen
        $sql = "INSERT INTO sieger (Name, Wert, siegerdef, year) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siii", $name, $wert, $siegerdef, $year);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Sieger erfolgreich hinzugefügt']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern: ' . $stmt->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Mitglied nicht gefunden']);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
}

$conn->close();
?>