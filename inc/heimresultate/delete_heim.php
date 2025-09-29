

<?php
include '../config.php';

// Prüfen, ob die Verbindung erfolgreich ist
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$jahr = isset($_POST['jahr']) ? $_POST['jahr'] : date('Y'); // Jahr wird aus der POST-Anfrage übernommen, falls nicht gesetzt, Standardwert ist das aktuelle Jahr
// Transaktion starten
$conn->begin_transaction();

try {
    $conn->query("DELETE FROM `heimresultate` WHERE `Jahr` = $jahr;");
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
