<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
            echo "Ungültiges AHV-Nummer Format für ID $id.";
            continue;
        }

        // Vorbereitetes Statement verwenden
        $stmt = $conn->prepare("UPDATE jungschuetzen SET AHVNummer=?, Name=?, Vorname=?, Geburtsdatum=?, Strasse=?, PLZ=?, Ort=?, KursNummer=? WHERE id=?");
        $stmt->bind_param("sssssssii", $ahvnummer, $name, $vorname, $geburtsdatum, $strasse, $plz, $ort, $kursnummer, $id);
        $stmt->execute();
        $stmt->close();
    }

    echo "Änderungen erfolgreich gespeichert";
}

$conn->close();
?>
