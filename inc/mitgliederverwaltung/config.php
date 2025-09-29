<?php

$servername = "bdebbd4.mysql.db.internal";
$username = "bdebbd4_msvjm";
$password = "xx*97ubWcy+HnLWyf6PW";
$dbname = "bdebbd4_msvjm";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
