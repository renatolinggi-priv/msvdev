<?php
// get_config.php
// Liefert die "Anzahl zaehlende Resultate" fuer ein Jahr (inkl. Vorjahres-Fallback).
include '../config.php';
require_once __DIR__ . '/config_helper.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

try {
    $config = getDurchschnittConfig($conn, $year);
    echo json_encode([
        'success'          => true,
        'year'             => $year,
        'anzahl_zaehlende' => $config['anzahl_zaehlende'],
        'inherited'        => $config['inherited'],
        'source_year'      => $config['source_year'],
    ]);
} catch (Exception $e) {
    error_log("Fehler in get_config.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Laden der Konfiguration']);
} finally {
    $conn->close();
}
?>
