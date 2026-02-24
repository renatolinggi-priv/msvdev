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
        error_log("DB connection failed: " . $conn->connect_error);
        die("Datenbankverbindung fehlgeschlagen. Bitte später erneut versuchen.");
    }
    $conn->set_charset($dbConf['charset'] ?? 'utf8mb4');
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

    // Fallback: neue Verbindung aufbauen â€“ nimm die gleichen Credentials wie oben
    $servername = $GLOBALS['servername'] ?? null;
    $username   = $GLOBALS['username']   ?? null;
    $password   = $GLOBALS['password']   ?? null;
    $dbname     = $GLOBALS['dbname']     ?? null;
    $mysqli = new mysqli($servername, $username, $password, $dbname);
    if ($mysqli->connect_error) {
        error_log("DB connection failed: " . $mysqli->connect_error);
        throw new Exception("Datenbankverbindung fehlgeschlagen.");
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

// PDO-Verbindung fuer neuen Code (Mitgliederportal etc.)
function getDB() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    global $dbConf;
    $dsn = 'mysql:host=' . $dbConf['host'] . ';dbname=' . $dbConf['name'] . ';charset=' . ($dbConf['charset'] ?? 'utf8mb4');
    try {
        $pdo = new PDO($dsn, $dbConf['user'], $dbConf['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log("PDO connection failed: " . $e->getMessage());
        die("Datenbankverbindung fehlgeschlagen. Bitte später erneut versuchen.");
    }
    return $pdo;
}

// Globale Datenbankverbindung für bestehende Dateien
    $servername = $dbConf['host'];
    $username = $dbConf['user'];
    $password = $dbConf['pass'];
    $dbname = $dbConf['name'];
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    die("Datenbankverbindung fehlgeschlagen. Bitte später erneut versuchen.");
}
$conn->set_charset($dbConf['charset'] ?? 'utf8mb4');

?>
