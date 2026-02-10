<?php
// save_mitglieder.php - ERWEITERTE VERSION
include 'config.php';

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
        $ids = $_POST['id'];
        $vornamen = $_POST['vorname'];
        $namen = $_POST['name'];
        $waffenids = $_POST['waffenid'];
        $status = isset($_POST['status']) ? $_POST['status'] : [];
        $ehrenmitglied = isset($_POST['ehrenmitglied']) ? $_POST['ehrenmitglied'] : [];
        $geburtsdatums = $_POST['geburtsdatum'];

        // NEUE FELDER
        $strassen = $_POST['strasse'] ?? [];
        $plzs = $_POST['plz'] ?? [];
        $orte = $_POST['ort'] ?? [];
        $emails = $_POST['email'] ?? [];
        $telefone = $_POST['telefon'] ?? [];

        foreach ($namen as $old_id => $name) {
            $new_id = $conn->real_escape_string($ids[$old_id]);
            $vorname = $conn->real_escape_string($vornamen[$old_id]);
            $name = $conn->real_escape_string($name);
            $birthday = $conn->real_escape_string($geburtsdatums[$old_id]);
            $waffenid = intval($waffenids[$old_id]);
            $isActive = isset($status[$old_id]) ? 1 : 0;
            $isEhrenmitglied = isset($ehrenmitglied[$old_id]) ? 1 : 0;

            // Neue Felder escapen
            $strasse = $conn->real_escape_string($strassen[$old_id] ?? '');
            $plz = $conn->real_escape_string($plzs[$old_id] ?? '');
            $ort = $conn->real_escape_string($orte[$old_id] ?? '');
            $email = $conn->real_escape_string($emails[$old_id] ?? '');
            $telefon = $conn->real_escape_string($telefone[$old_id] ?? '');

            // Update oder Insert je nach ID-Änderung
            if ($new_id != $old_id) {
                // Erst neuen Eintrag erstellen
                $sql = "INSERT INTO mitglieder (id, vorname, name, waffenid, status, Geburtsdatum, Ehrenmitglied,
                        Strasse, PLZ, Ort, Email, Telefon)
                        VALUES ('$new_id', '$vorname', '$name', '$waffenid', $isActive, '$birthday', '$isEhrenmitglied',
                        '$strasse', '$plz', '$ort', '$email', '$telefon')";

                if ($conn->query($sql) === TRUE) {
                    // Dann alten Eintrag löschen
                    if ($conn->query("DELETE FROM mitglieder WHERE id=$old_id") === FALSE) {
                        throw new Exception("Fehler beim Löschen des alten Eintrags für Mitglied ID $old_id: " . $conn->error);
                    }
                } else {
                    throw new Exception("Fehler beim Einfügen des neuen Eintrags für Mitglied ID $old_id: " . $conn->error);
                }
            } else {
                // Normales Update - KORRIGIERTE SPALTENNAMEN
                $sql = "UPDATE mitglieder SET
                        id='$new_id',
                        vorname='$vorname',
                        name='$name',
                        waffenid='$waffenid',
                        status=$isActive,
                        Geburtsdatum='$birthday',
                        Ehrenmitglied=$isEhrenmitglied,
                        Strasse='$strasse',
                        PLZ='$plz',
                        Ort='$ort',
                        Email='$email',
                        Telefon='$telefon'
                        WHERE id=$old_id";
            }

            if ($conn->query($sql) !== TRUE) {
                throw new Exception("Fehler beim Aktualisieren von Mitglied ID $old_id: " . $conn->error);
            }
        }

        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Alle Änderungen wurden erfolgreich gespeichert']);
    } catch (Exception $e) {
        $conn->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    $conn->close();
}
?>