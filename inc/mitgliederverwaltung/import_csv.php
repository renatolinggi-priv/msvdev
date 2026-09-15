<?php
// import_csv.php – Mitglieder aus CSV (JSON-Array vom Client) anlegen/aktualisieren.
// Spalten wie export_csv.php; zusaetzliche Spalten Anrede/Kommunikation/Vereinsaufnahme sind optional
// (aeltere Exporte ohne diese Spalten bleiben importierbar). Prepared Statements, Fehler pro Zeile.
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true);

$csvData = json_decode((string)($_POST['csvData'] ?? ''), true);
if (!$csvData || !is_array($csvData)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Keine gültigen CSV-Daten empfangen']));
}

$imported = 0;
$updated  = 0;
$errors   = [];

$stmtCheck = $conn->prepare("SELECT ID FROM mitglieder WHERE ID = ?");
$stmtUpd = $conn->prepare("UPDATE mitglieder SET Anrede = ?, Vorname = ?, Name = ?, Geburtsdatum = ?, WaffenID = ?, Status = ?, Ehrenmitglied = ?,
        Strasse = ?, PLZ = ?, Ort = ?, Email = ?, Telefon = ?, Mobile = ?, Notizen = ?, Verstorben = ?, Vereinsaufnahme = ?, Kommunikation = ?
        WHERE ID = ?");
$stmtIns = $conn->prepare("INSERT INTO mitglieder (ID, Anrede, Vorname, Name, Geburtsdatum, WaffenID, Status, Ehrenmitglied,
        Strasse, PLZ, Ort, Email, Telefon, Mobile, Notizen, Verstorben, Vereinsaufnahme, Kommunikation)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmtCheck || !$stmtUpd || !$stmtIns) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Datenbankfehler (prepare)']));
}

$zeile = 1;
foreach ($csvData as $row) {
    $zeile++;
    if (!is_array($row)) { $errors[] = "Zeile $zeile: ungültiges Format"; continue; }

    $id           = (int)($row['ID'] ?? 0);
    $vorname      = trim((string)($row['Vorname'] ?? ''));
    $name         = trim((string)($row['Name'] ?? ''));
    $geburtsdatum = trim((string)($row['Geburtsdatum'] ?? ''));
    $waffenId     = (int)($row['WaffenID'] ?? 1);
    $status       = (int)!empty($row['Status']);
    $ehrenmitglied = (int)!empty($row['Ehrenmitglied']);
    $strasse      = trim((string)($row['Strasse'] ?? ''));
    $plz          = trim((string)($row['PLZ'] ?? ''));
    $ort          = trim((string)($row['Ort'] ?? ''));
    $email        = trim((string)($row['Email'] ?? ''));
    $telefon      = trim((string)($row['Telefon'] ?? ''));
    $mobile       = trim((string)($row['Mobile'] ?? ''));
    $notizen      = trim((string)($row['Notizen'] ?? ''));
    $verstorben   = (int)!empty($row['Verstorben']);
    $anrede       = trim((string)($row['Anrede'] ?? ''));
    $komm         = trim((string)($row['Kommunikation'] ?? ''));
    $vereinsaufnahme = !empty($row['Vereinsaufnahme']) ? (int)$row['Vereinsaufnahme'] : null;

    $bez = trim("$vorname $name") !== '' ? "$vorname $name" : "ID $id";
    if ($id < 1) { $errors[] = "Zeile $zeile ($bez): keine gültige ID"; continue; }
    if ($vorname === '' || $name === '') { $errors[] = "Zeile $zeile (ID $id): Name/Vorname fehlt"; continue; }
    $d = DateTime::createFromFormat('Y-m-d', $geburtsdatum);
    if (!$d || $d->format('Y-m-d') !== $geburtsdatum) { $errors[] = "Zeile $zeile ($bez): ungültiges Geburtsdatum «{$geburtsdatum}»"; continue; }
    if ($waffenId < 1) { $errors[] = "Zeile $zeile ($bez): ungültige WaffenID"; continue; }
    $anredeDb = in_array($anrede, ['Herr', 'Frau'], true) ? $anrede : null;
    $kommDb   = in_array($komm, ['Briefpost', 'Whatsapp', 'Beides'], true) ? $komm : null;
    if ($vereinsaufnahme !== null && ($vereinsaufnahme < 1900 || $vereinsaufnahme > 2099)) $vereinsaufnahme = null;

    $stmtCheck->bind_param('i', $id);
    $stmtCheck->execute();
    $exists = $stmtCheck->get_result()->num_rows > 0;

    if ($exists) {
        $stmtUpd->bind_param('ssssiiisssssssiisi',
            $anredeDb, $vorname, $name, $geburtsdatum, $waffenId, $status, $ehrenmitglied,
            $strasse, $plz, $ort, $email, $telefon, $mobile, $notizen, $verstorben, $vereinsaufnahme, $kommDb, $id);
        if ($stmtUpd->execute()) $updated++;
        else $errors[] = "Zeile $zeile ($bez): Update fehlgeschlagen – " . $stmtUpd->error;
    } else {
        $stmtIns->bind_param('issssiiisssssssiis',
            $id, $anredeDb, $vorname, $name, $geburtsdatum, $waffenId, $status, $ehrenmitglied,
            $strasse, $plz, $ort, $email, $telefon, $mobile, $notizen, $verstorben, $vereinsaufnahme, $kommDb);
        if ($stmtIns->execute()) $imported++;
        else $errors[] = "Zeile $zeile ($bez): Anlegen fehlgeschlagen – " . $stmtIns->error;
    }
}
$stmtCheck->close(); $stmtUpd->close(); $stmtIns->close();
$conn->close();

echo json_encode([
    'success'  => ($imported + $updated) > 0 || !$errors,
    'imported' => $imported,
    'updated'  => $updated,
    'errors'   => $errors,
    'message'  => "$imported neu, $updated aktualisiert" . ($errors ? ', ' . count($errors) . ' Fehler' : ''),
]);
