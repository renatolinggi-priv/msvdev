<?php
// import_csv.php
session_start();
require_once 'config.php';
require_once __DIR__ . '/../csrf.inc.php';

// CSRF check
csrf_require(true);

$csvData = json_decode($_POST['csvData'], true);

if (!$csvData || !is_array($csvData)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid CSV data']));
}

$imported = 0;
$updated = 0;
$errors = [];

foreach ($csvData as $row) {
    // Daten vorbereiten
    $id = $conn->real_escape_string($row['ID'] ?? '');
    $vorname = $conn->real_escape_string($row['Vorname'] ?? '');
    $name = $conn->real_escape_string($row['Name'] ?? '');
    $geburtsdatum = $conn->real_escape_string($row['Geburtsdatum'] ?? '');
    $waffenId = intval($row['WaffenID'] ?? 1);
    $status = intval($row['Status'] ?? 0);
    $ehrenmitglied = intval($row['Ehrenmitglied'] ?? 0);
    $strasse = $conn->real_escape_string($row['Strasse'] ?? '');
    $plz = $conn->real_escape_string($row['PLZ'] ?? '');
    $ort = $conn->real_escape_string($row['Ort'] ?? '');
    $email = $conn->real_escape_string($row['Email'] ?? '');
    $telefon = $conn->real_escape_string($row['Telefon'] ?? '');
    $mobile = $conn->real_escape_string($row['Mobile'] ?? '');
    $notizen = $conn->real_escape_string($row['Notizen'] ?? '');
    $verstorben = intval($row['Verstorben'] ?? 0);
    
    // Check if member exists
    $checkSql = "SELECT id FROM mitglieder WHERE id = '$id'";
    $checkResult = $conn->query($checkSql);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        // Update existing
        $sql = "UPDATE mitglieder SET 
                vorname = '$vorname',
                name = '$name',
                Geburtsdatum = '$geburtsdatum',
                waffenid = $waffenId,
                status = $status,
                Ehrenmitglied = $ehrenmitglied,
                Strasse = '$strasse',
                PLZ = '$plz',
                Ort = '$ort',
                Email = '$email',
                Telefon = '$telefon',
                Mobile = '$mobile',
                Notizen = '$notizen',
                Verstorben = $verstorben
                WHERE id = '$id'";
        
        if ($conn->query($sql) === TRUE) {
            $updated++;
        } else {
            $errors[] = "Update-Fehler bei ID $id: " . $conn->error;
        }
    } else {
        // Insert new
        $sql = "INSERT INTO mitglieder (id, vorname, name, Geburtsdatum, waffenid, status, Ehrenmitglied,
                Strasse, PLZ, Ort, Email, Telefon, Mobile, Notizen, Verstorben)
                VALUES ('$id', '$vorname', '$name', '$geburtsdatum', $waffenId, $status, $ehrenmitglied,
                '$strasse', '$plz', '$ort', '$email', '$telefon', '$mobile', '$notizen', $verstorben)";
        
        if ($conn->query($sql) === TRUE) {
            $imported++;
        } else {
            $errors[] = "Insert-Fehler bei $vorname $name: " . $conn->error;
        }
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'imported' => $imported,
    'updated' => $updated,
    'errors' => $errors
]);
?>