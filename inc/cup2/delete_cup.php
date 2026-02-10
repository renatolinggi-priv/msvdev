
<?php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

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
    // Transaktion erfolgreich abschließen
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
