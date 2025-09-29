<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Eingabedaten validieren
    $ahvnummer = trim($_POST['ahvnummer']);
    $name = trim($_POST['name']);
    $vorname = trim($_POST['vorname']);
    $geburtsdatum = trim($_POST['geburtsdatum']);
    $strasse = trim($_POST['strasse']);
    $plz = trim($_POST['plz']);
    $ort = trim($_POST['ort']);
    $kursnummer = intval($_POST['kursnummer']);

    // Überprüfen, ob alle Felder ausgefüllt sind
    if (empty($ahvnummer) || empty($name) || empty($vorname) || empty($geburtsdatum) || empty($strasse) || empty($plz) || empty($ort) || empty($kursnummer)) {
        echo json_encode(['status' => 'error', 'message' => 'Bitte füllen Sie alle Felder aus.']);
        exit;
    }

    // AHV-Nummer Format überprüfen (Optional)
    if (!preg_match('/^\d{3}\.\d{4}\.\d{4}\.\d{2}$/', $ahvnummer)) {
        echo json_encode(['status' => 'error', 'message' => 'Bitte geben Sie eine gültige AHV-Nummer im Format 756.XXXX.XXXX.XX ein.']);
        exit;
    }

    // Vorbereitetes Statement verwenden
    $stmt = $conn->prepare("INSERT INTO jungschuetzen (AHVNummer, Name, Vorname, Geburtsdatum, Strasse, PLZ, Ort, KursNummer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssi", $ahvnummer, $name, $vorname, $geburtsdatum, $strasse, $plz, $ort, $kursnummer);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Jungschütze erfolgreich hinzugefügt']);
    } else {
        if ($stmt->errno == 1062) {
            echo json_encode(['status' => 'error', 'message' => 'Ein Jungschütze mit dieser AHV-Nummer existiert bereits.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Fehler beim Hinzufügen: ' . $stmt->error]);
        }
    }

    $stmt->close();
    $conn->close();
}
?>
