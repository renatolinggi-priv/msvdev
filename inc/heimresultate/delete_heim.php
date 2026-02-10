
<?php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die('Ungültige Anfrage');
}

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
