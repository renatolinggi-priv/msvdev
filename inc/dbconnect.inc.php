<?php

//dbconnect.inc.php

// Verbindung zur Datenbank herstellen

$config = require __DIR__ . '/../../msvjm_config.php';
$dbConf = $config['db'];
function connect_db($Query){
    global $dbConf;
    $servername = $dbConf['host'];
    $username = $dbConf['user'];
    $password = $dbConf['pass'];
    $dbname = $dbConf['name'];

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $result = $conn->query($Query);
    $conn->close();
    return ($result);
}

// Persistente Verbindung für Prepared Statements
// ... dein bestehender Inhalt inkl. $conn = new mysqli(...);
// Liefert eine wiederverwendbare mysqli-Verbindung
function get_db_connection() {
    // Falls bereits global vorhanden & ok: nimm sie
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        if ($GLOBALS['conn']->ping()) return $GLOBALS['conn'];
    }
    // Fallback: neue Verbindung aufbauen – nimm die gleichen Credentials wie oben
    $servername = $GLOBALS['servername'] ?? null;
    $username   = $GLOBALS['username']   ?? null;
    $password   = $GLOBALS['password']   ?? null;
    $dbname     = $GLOBALS['dbname']     ?? null;

    $mysqli = new mysqli($servername, $username, $password, $dbname);
    if ($mysqli->connect_error) {
        throw new Exception("DB connect error: ".$mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
    // global setzen, damit andere Files dieselbe Connection nutzen
    $GLOBALS['conn'] = $mysqli;
    return $mysqli;
}



// Optionaler Alias wie oft üblich
function connect_db_mysqli() {
    return get_db_connection();
}

// Globale Datenbankverbindung für bestehende Dateien
    $servername = $dbConf['host'];
    $username = $dbConf['user'];
    $password = $dbConf['pass'];
    $dbname = $dbConf['name'];
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

