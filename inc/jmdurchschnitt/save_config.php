<?php
// save_config.php
// Speichert die "Anzahl zaehlende Resultate" fuer ein Jahr.
session_start();
include '../config.php';
require_once __DIR__ . '/config_helper.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// CSRF Token pruefen
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF Token']);
    exit;
}

$year = isset($_POST['year']) ? intval($_POST['year']) : 0;
$anzahl = isset($_POST['anzahl_zaehlende']) ? intval($_POST['anzahl_zaehlende']) : 0;

if ($year < 2000 || $year > 2100) {
    echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr']);
    exit;
}
if ($anzahl < 1 || $anzahl > 99) {
    echo json_encode(['success' => false, 'message' => 'Anzahl muss zwischen 1 und 99 liegen']);
    exit;
}

try {
    ensureDurchschnittConfigTable($conn);

    $sql = "INSERT INTO jmdurchschnitt_config (year, anzahl_zaehlende)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE anzahl_zaehlende = VALUES(anzahl_zaehlende)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $year, $anzahl);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success'          => true,
        'year'             => $year,
        'anzahl_zaehlende' => $anzahl,
        'message'          => 'Einstellung gespeichert',
    ]);
} catch (Exception $e) {
    error_log("Fehler in save_config.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern der Konfiguration']);
} finally {
    $conn->close();
}
?>
