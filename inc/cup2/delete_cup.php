
<?php
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)

// CSRF-Schutz
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

// Prüfen, ob die Verbindung erfolgreich ist
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$year = intval(date("Y"));
// Transaktion starten
$conn->begin_transaction();

try {
    $stmt = $conn->prepare("DELETE FROM `cupPairs` WHERE `Year` = ?");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $stmt->close();
    // Transaktion erfolgreich abschliessen
    $conn->commit();
    json_encode(['status' => 'success', 'message' => 'Script ausgeführt']);

} catch (Exception $e) {
    // Bei einem Fehler Transaktion rückgängig machen
    $conn->rollback();
    echo "Fehler beim Leeren der Tabellen: " . $e->getMessage();
}
try {
    $stmt = $conn->prepare("DELETE FROM `cupFinalResults` WHERE `Year` = ?");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $stmt->close();
    // Transaktion erfolgreich abschliessen
    $conn->commit();
    json_encode(['status' => 'success', 'message' => 'Script ausgeführt']);

} catch (Exception $e) {
    // Bei einem Fehler Transaktion rückgängig machen
    $conn->rollback();
    echo "Fehler beim Leeren der Tabellen: " . $e->getMessage();
}

// Schliessen der Verbindung
$conn->close();
?>
