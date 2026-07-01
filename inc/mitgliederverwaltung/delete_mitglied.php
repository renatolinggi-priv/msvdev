<?php
// mitglieder/delete_mitglied.php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../csrf.inc.php';

// CSRF check
csrf_require();

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