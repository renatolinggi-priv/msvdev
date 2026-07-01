<?php
// add_mitglied.php - ERWEITERTE VERSION
require_once '../config.php';
require_once __DIR__ . '/../csrf.inc.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_require();
    if (empty($_SESSION['user_id'])) { http_response_code(403); exit('Nicht angemeldet'); }
    $id = $conn->real_escape_string($_POST['id']);
    $vorname = $conn->real_escape_string($_POST['vorname']);
    $name = $conn->real_escape_string($_POST['name']);
    $geburtsdatum = $conn->real_escape_string($_POST['birthday']);
    $waffenid = intval($_POST['waffenid']);
    $status = intval($_POST['status']);
    $ehrenmitglied = intval($_POST['ehrenmitglied']);
    
    // Neue Felder
    $strasse = $conn->real_escape_string($_POST['strasse'] ?? '');
    $plz = $conn->real_escape_string($_POST['plz'] ?? '');
    $ort = $conn->real_escape_string($_POST['ort'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $telefon = $conn->real_escape_string($_POST['telefon'] ?? '');
    $mobile = $conn->real_escape_string($_POST['mobile'] ?? '');
    $notizen = $conn->real_escape_string($_POST['notizen'] ?? '');
    $anrede = $conn->real_escape_string($_POST['anrede'] ?? '');
    $vereinsaufnahme = !empty($_POST['vereinsaufnahme']) ? intval($_POST['vereinsaufnahme']) : 'NULL';
    $kommunikation = $conn->real_escape_string($_POST['kommunikation'] ?? '');

    $anredeSQL = $anrede !== '' ? "'$anrede'" : "NULL";
    $kommSQL = $kommunikation !== '' ? "'$kommunikation'" : "NULL";

    $sql = "INSERT INTO mitglieder (ID, Anrede, Vorname, Name, Geburtsdatum, WaffenID, Status, Ehrenmitglied,
            Strasse, PLZ, Ort, Email, Telefon, Mobile, Notizen, Verstorben, Vereinsaufnahme, Kommunikation)
            VALUES ('$id', $anredeSQL, '$vorname', '$name', '$geburtsdatum', '$waffenid', '$status', '$ehrenmitglied',
            '$strasse', '$plz', '$ort', '$email', '$telefon', '$mobile', '$notizen', 0, $vereinsaufnahme, $kommSQL)";

    if ($conn->query($sql) === TRUE) {
        echo "Mitglied erfolgreich hinzugefügt";
    } else {
        echo "Fehler: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();
}
?>