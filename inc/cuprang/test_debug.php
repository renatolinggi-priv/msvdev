<?php
// TEST-DATEI zum Debuggen des PDF-Generators

// Fehlerausgabe aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Basis-Includes
include '../config.php';
require_once 'cup_repository.php';

// DB-Verbindung testen
if (!isset($conn) && function_exists('get_db_connection')) {
    $conn = get_db_connection();
}

if (!$conn) {
    die("FEHLER: Keine DB-Verbindung!");
}

echo "<h2>Test Cup PDF Debug</h2>";

// Jahr setzen
$selectedYear = 2025;
echo "<p>Jahr: $selectedYear</p>";

// Standcup-Daten laden
$standRaw = cup_fetch_standcup_final($conn, $selectedYear);
echo "<h3>Standcup Raw-Daten:</h3>";
echo "<pre>";
print_r($standRaw);
echo "</pre>";

// Namen prüfen
echo "<h3>Namen-Verarbeitung:</h3>";
foreach ($standRaw as $r) {
    echo "ParticipantName: " . ($r['ParticipantName'] ?? 'LEER') . "<br>";
    echo "ParticipantID: " . ($r['ParticipantID'] ?? 'NICHT VORHANDEN') . "<br>";
    echo "---<br>";
}

// Finale Daten
$finalRaw = cup_fetch_final_results($conn, $selectedYear);
echo "<h3>Finale Raw-Daten:</h3>";
echo "<pre>";
print_r($finalRaw);
echo "</pre>";

echo "<hr>";
echo "<p>Wenn du Namen siehst bei ParticipantName, dann sollte das PDF funktionieren!</p>";
