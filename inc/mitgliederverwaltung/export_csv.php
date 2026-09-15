<?php
// export_csv.php – alle Mitglieder als CSV (Semikolon, UTF-8 mit BOM). Spalten = Import-Format.
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('plain');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=mitglieder_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM fuer Excel

fputcsv($output, [
    'ID', 'Anrede', 'Vorname', 'Name', 'Geburtsdatum', 'WaffenID', 'Status', 'Ehrenmitglied',
    'Strasse', 'PLZ', 'Ort', 'Email', 'Telefon', 'Mobile', 'Notizen', 'Verstorben',
    'Vereinsaufnahme', 'Kommunikation',
], ';');

$result = $conn->query("SELECT * FROM mitglieder ORDER BY Name, Vorname");
while ($result && ($row = $result->fetch_assoc())) {
    fputcsv($output, [
        $row['ID'],
        $row['Anrede'] ?? '',
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
        $row['Verstorben'] ?? 0,
        $row['Vereinsaufnahme'] ?? '',
        $row['Kommunikation'] ?? '',
    ], ';');
}

fclose($output);
$conn->close();
exit();
