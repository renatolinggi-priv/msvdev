<?php
// load_waffen_option.php - Lädt Waffen-Optionen für Dropdown
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('html');

$waffenSql = "SELECT id, bezeichnung FROM Waffen ORDER BY bezeichnung ASC";
$waffenResult = $conn->query($waffenSql);

if ($waffenResult && $waffenResult->num_rows > 0) {
    while($waffe = $waffenResult->fetch_assoc()) {
        echo '<option value="' . htmlspecialchars($waffe['id']) . '">' . htmlspecialchars($waffe['bezeichnung']) . '</option>';
    }
} else {
    echo '<option value="">Keine Waffen gefunden</option>';
}

$conn->close();
?>