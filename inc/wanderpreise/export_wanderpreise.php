<?php
// export_wanderpreise.php - CSV Export für Wanderpreise
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

// Datenbankverbindung herstellen
$conn = get_db_connection();
if (!$conn) {
    wanderpreise_debug('CSV Export Error', ['error' => 'Datenbankverbindung fehlgeschlagen']);
    die('Datenbankverbindung fehlgeschlagen');
}

// Jahr aus dem GET-Parameter holen oder aktuelles Jahr verwenden
$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : date('Y');

// Header für CSV-Datei setzen
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=wanderpreise_' . $jahr . '.csv');

// CSV-Output erstellen
$output = fopen('php://output', 'w');

// UTF-8 BOM hinzufügen (für Excel)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header-Zeile schreiben
fputcsv($output, [
    'Wanderpreis ID',
    'Bezeichnung',
    'Beschreibung',
    'Anschaffungsjahr',
    'Gewinner ID',
    'Gewinner Name',
    'Jahr',
    'Rang/Resultat',
    'Bemerkung',
    'Definitiv'
]);

// SQL-Abfrage für Wanderpreise und Gewinner des angegebenen Jahres
$sql = "SELECT 
            w.id as wanderpreis_id,
            w.bezeichnung,
            w.beschreibung,
            w.beschaffung_datum,
            wg.gewinner_id,
            CONCAT(m.Name, ' ', m.Vorname) as gewinner_name,
            wg.jahr,
            wg.rang,
            wg.resultat,
            wg.bemerkung,
            wg.ist_definitiv
        FROM wanderpreise w
        LEFT JOIN wanderpreise_gewinner wg ON w.id = wg.wanderpreis_id AND wg.jahr = ?
        LEFT JOIN mitglieder m ON wg.gewinner_id = m.ID
        ORDER BY w.bezeichnung";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    wanderpreise_debug('CSV Export SQL Error', ['error' => $conn->error]);
    die('Fehler beim Vorbereiten der SQL-Abfrage: ' . $conn->error);
}

$stmt->bind_param("i", $jahr);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['wanderpreis_id'],
            $row['bezeichnung'],
            $row['beschreibung'],
            $row['beschaffung_datum'],
            $row['gewinner_id'],
            $row['gewinner_name'],
            $row['jahr'],
            $row['rang'] . ($row['resultat'] ? ' (' . $row['resultat'] . ')' : ''),
            $row['bemerkung'],
            $row['ist_definitiv'] ? 'Ja' : 'Nein'
        ]);
    }
}

$stmt->close();
$conn->close();
fclose($output);
exit;
?>