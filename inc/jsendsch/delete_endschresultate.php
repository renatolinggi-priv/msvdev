<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Transaktion starten
$conn->begin_transaction();

try {
    // Daten aus den Jungschützen-Tabellen löschen
    $conn->query("TRUNCATE TABLE `endstich_jung`;");
    $conn->query("TRUNCATE TABLE `glueck_jung`;");
    $conn->query("TRUNCATE TABLE `kunst_jung`;");
    $conn->query("TRUNCATE TABLE `schwini_jung`;");
    $conn->query("TRUNCATE TABLE `zabig_jung`;");

    // Transaktion erfolgreich abschließen
    $conn->commit();
    echo "Tabellen erfolgreich geleert.";
} catch (Exception $e) {
    // Bei einem Fehler Transaktion rückgängig machen
    $conn->rollback();
    echo "Fehler beim Leeren der Tabellen: " . $e->getMessage();
}

// Schließen der Verbindung
$conn->close();
?>
