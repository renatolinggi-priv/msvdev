<?php
// add_jmdefinition.php
include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bezeichnung = isset($_POST['bezeichnung']) ? trim($_POST['bezeichnung']) : '';
    $adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : '';
    $maxpunkte = isset($_POST['maxpunkte']) ? intval($_POST['maxpunkte']) : 0;
    $streicher = intval($_POST['streicher'] ?? 0);
    $erweitert = intval($_POST['erweitert'] ?? 0);
    $info = intval($_POST['info'] ?? 0);
    $gruppe = intval($_POST['gruppe'] ?? 0);
    $zuschlag = isset($_POST['zuschlag']) ? intval($_POST['zuschlag']) : 0;
    $schiesstage = isset($_POST['schiesstage']) ? trim($_POST['schiesstage']) : ''; // Schiesstage hinzufügen
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $hidden = 0; // Standardwert für 'hidden'

    // Beginne eine Transaktion
    $conn->begin_transaction();

    try {
        // Abfrage der höchsten Reihenfolge für das aktuelle Jahr
        $result = $conn->query("SELECT MAX(Reihenfolge) AS maxReihenfolge FROM JMDefinition WHERE year = $year");
        if (!$result) {
            throw new Exception("Fehler beim Abrufen der höchsten Reihenfolge: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $maxReihenfolge = $row['maxReihenfolge'] ?? 0; // Wenn null, dann 0
        $neueReihenfolge = $maxReihenfolge + 1;

        // Füge den neuen Eintrag ein
        $stmtInsert = $conn->prepare("
        INSERT INTO JMDefinition (Reihenfolge, Bezeichnung, Maxpunkte, Streicher, Erweitert, Schiesstage, Info, Gruppe, hidden, year, Adresse, Zuschlag)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
        if (!$stmtInsert) {
            throw new Exception("Fehler beim Vorbereiten des Insert-Statements: " . $conn->error);
        }

        $stmtInsert->bind_param("isiiisiiisii", $neueReihenfolge, $bezeichnung, $maxpunkte, $streicher, $erweitert, $schiesstage, $info, $gruppe, $hidden, $year, $adresse, $zuschlag);
        if (!$stmtInsert->execute()) {
            throw new Exception("Fehler beim Einfügen des neuen Eintrags: " . $stmtInsert->error);
        }
        $stmtInsert->close();

        // Commit der Transaktion
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Eintrag erfolgreich hinzugefügt.']);
    } catch (Exception $e) {
        // Rollback bei einem Fehler
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }

    $conn->close();
}
