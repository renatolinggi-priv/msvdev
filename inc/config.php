<?php
//config.php
// Basisverzeichnis der Anwendung definieren
define('BASE_PATH', dirname(__DIR__));

// Zentrale Session-Konfiguration (CSRF, Cross-Subdomain Cookies)
require_once __DIR__ . '/session_config.inc.php';
$config = require __DIR__ . '/../../msvjm_config.php';
$dbConf = $config['db'];
// Datenbankverbindungsinformationen
define('DB_HOST', $dbConf['host']);
define('DB_USER', $dbConf['user']);
define('DB_PASS', $dbConf['pass']);
define('DB_NAME', $dbConf['name']);

// Datenbankverbindung herstellen
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Verbindungsfehler: " . $conn->connect_error);
}
?>