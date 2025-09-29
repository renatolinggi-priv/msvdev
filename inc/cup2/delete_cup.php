

<?php
include '../config.php';

// Prüfen, ob die Verbindung erfolgreich ist
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$year = date("Y");
// Transaktion starten
$conn->begin_transaction();

try {
    $conn->query("DELETE FROM `cupPairs` WHERE `Year` = $year;");
    // Transaktion erfolgreich abschließen
    $conn->commit();
    json_encode(['status' => 'success', 'message' => 'Script ausgeführt']);

} catch (Exception $e) {
    // Bei einem Fehler Transaktion rückgängig machen
    $conn->rollback();
    echo "Fehler beim Leeren der Tabellen: " . $e->getMessage();
}
try {
    $conn->query("DELETE FROM `cupFinalResults` WHERE `Year` = $year;");
    // Transaktion erfolgreich abschließen
    $conn->commit();
    json_encode(['status' => 'success', 'message' => 'Script ausgeführt']);

} catch (Exception $e) {
    // Bei einem Fehler Transaktion rückgängig machen
    $conn->rollback();
    echo "Fehler beim Leeren der Tabellen: " . $e->getMessage();
}

// Schließen der Verbindung
$conn->close();
?>
