<?php
// mitglieder/delete_mitglied.php
session_start();
require_once '../config.php';

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die('CSRF token validation failed');
}

if (isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    $sql = "DELETE FROM mitglieder WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
        echo "Mitglied gelöscht";
    } else {
        http_response_code(500);
        echo "Fehler: " . $conn->error;
    }
}

$conn->close();
?>