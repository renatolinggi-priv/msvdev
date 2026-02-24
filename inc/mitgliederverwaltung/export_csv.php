<?php
// export_csv.php
require_once 'config.php';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=mitglieder_export_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV headers
fputcsv($output, [
    'ID',
    'Vorname',
    'Name',
    'Geburtsdatum',
    'WaffenID',
    'Status',
    'Ehrenmitglied',
    'Strasse',
    'PLZ',
    'Ort',
    'Email',
    'Telefon',
    'Mobile',
    'Notizen',
    'Verstorben'
], ';');

// Fetch data
$sql = "SELECT * FROM mitglieder ORDER BY name, vorname";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['ID'],
            $row['Vorname'],
            $row['Name'],
            $row['Geburtsdatum'],
            $row['WaffenID'],
            $row['Status'],
            $row['Ehrenmitglied'],
            $row['Strasse'] ?? '',
            $row['PLZ'] ?? '',
            $row['Ort'] ?? '',
            $row['Email'] ?? '',
            $row['Telefon'] ?? '',
            $row['Mobile'] ?? '',
            $row['Notizen'] ?? '',
            $row['Verstorben'] ?? 0
        ], ';');
    }
}

fclose($output);
$conn->close();
exit();
?>