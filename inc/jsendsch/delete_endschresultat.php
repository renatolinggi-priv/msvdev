<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../config.php';

$jungschuetzeID = isset($_POST['jungschuetzeID']) ? intval($_POST['jungschuetzeID']) : 0;

if ($jungschuetzeID == 0) {
    die("Ungültige JungschuetzeID");
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Tabellen, aus denen Daten gelöscht werden sollen
$tables = ['endstich_jung', 'schwini_jung', 'kunst_jung', 'glueck_jung', 'zabig_jung'];

foreach ($tables as $table) {
    $stmt = $conn->prepare("DELETE FROM $table WHERE JungschuetzeID = ?");
    $stmt->bind_param("i", $jungschuetzeID);
    $stmt->execute();
}

$conn->close();

echo "Resultate erfolgreich gelöscht";
?>
