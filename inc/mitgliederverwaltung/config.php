<?php

// DB-Credentials aus zentraler Konfiguration
$config = require __DIR__ . '/../../config.php';
$dbConf = $config['db'];

$servername = $dbConf['host'];
$username = $dbConf['user'];
$password = $dbConf['pass'];
$dbname = $dbConf['name'];

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
