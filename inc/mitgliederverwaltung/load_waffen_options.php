<?php
//load_waffen_option.php
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('html');
$waffenSql = "SELECT id, bezeichnung FROM Waffen";
$waffenResult = $conn->query($waffenSql);

if ($waffenResult->num_rows > 0) {
    while($waffe = $waffenResult->fetch_assoc()) {
        echo "<option value='" . $waffe['id'] . "'>" . $waffe['bezeichnung'] . "</option>";
    }
}

$conn->close();
?>
