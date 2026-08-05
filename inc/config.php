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

// Generierte Exportdateien begrenzen: laeuft NACH der Ausgabe und raeumt das dat/ des
// aktuellen Modul-Skripts auf (Details und Sicherheitsregeln: inc/dat_cleanup.inc.php).
// Zentral hier, weil praktisch jeder Generator unter inc/<modul>/ diese Datei einbindet --
// damit sind auch Module abgedeckt, die keinen eigenen Aufruf haben.
require_once __DIR__ . '/dat_cleanup.inc.php';
datAufraeumenNachAusgabe(5);
?>