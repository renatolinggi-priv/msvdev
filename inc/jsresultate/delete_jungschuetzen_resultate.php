<?php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die('Ungültige Anfrage');
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "DELETE FROM jungschuetzen_resultate";
if ($conn->query($sql) === TRUE) {
    echo "Alle aktuellen Resultate erfolgreich gelöscht";
} else {
    echo "Fehler beim Löschen der aktuellen Resultate: " . $conn->error;
}

$conn->close();
?>
