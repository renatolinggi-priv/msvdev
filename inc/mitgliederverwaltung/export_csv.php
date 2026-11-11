<?php
// export_csv.php – alle Mitglieder als CSV (Semikolon, UTF-8 mit BOM). Zugleich die Vorlage für den Import:
// import_csv.php liest die Spalten über ihre Namen, die Reihenfolge ist frei. «Waffe» steht nur zur Lesbarkeit
// neben der WaffenID (beim Import zählt die WaffenID, wenn beide da sind).
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('plain');
require_once __DIR__ . '/mitglied.inc.php';

$mitMitgliedschaft = mvHatMitgliedschaft($conn);
$result = $conn->query("SELECT m.*, w.Bezeichnung AS waffe FROM mitglieder m LEFT JOIN Waffen w ON w.ID = m.WaffenID ORDER BY m.Name, m.Vorname");
if (!$result) {
    msvFehler('CSV-Export', $conn->error);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Der Export ist fehlgeschlagen. Bitte nochmals versuchen.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=mitglieder_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF"); // BOM für Excel

$kopf = ['ID', 'Anrede', 'Vorname', 'Name', 'Geburtsdatum', 'WaffenID', 'Waffe', 'Status'];
if ($mitMitgliedschaft) $kopf[] = 'Mitgliedsart';
$kopf = array_merge($kopf, ['Ehrenmitglied', 'Vereinsaufnahme']);
if ($mitMitgliedschaft) $kopf[] = 'Austrittsjahr';
$kopf = array_merge($kopf, ['Verstorben', 'Strasse', 'PLZ', 'Ort', 'Email', 'Telefon', 'Mobile', 'Kommunikation', 'Notizen']);
fputcsv($output, $kopf, ';', '"', '');

while ($row = $result->fetch_assoc()) {
    $z = [$row['ID'], $row['Anrede'] ?? '', $row['Vorname'], $row['Name'], $row['Geburtsdatum'],
          $row['WaffenID'], $row['waffe'] ?? '', $row['Status']];
    if ($mitMitgliedschaft) $z[] = $row['Mitgliedsart'] ?? 'Aktiv';
    $z = array_merge($z, [$row['Ehrenmitglied'], $row['Vereinsaufnahme'] ?? '']);
    if ($mitMitgliedschaft) $z[] = $row['Austrittsjahr'] ?? '';
    $z = array_merge($z, [$row['Verstorben'] ?? 0, $row['Strasse'] ?? '', $row['PLZ'] ?? '', $row['Ort'] ?? '',
          $row['Email'] ?? '', $row['Telefon'] ?? '', $row['Mobile'] ?? '', $row['Kommunikation'] ?? '', $row['Notizen'] ?? '']);
    fputcsv($output, $z, ';', '"', '');
}

fclose($output);
$conn->close();
exit();
