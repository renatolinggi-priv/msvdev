<?php
// Session starten falls noch nicht gestartet
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Session-Variablen löschen
$_SESSION = array();

// Session zerstören
session_destroy();

// Weiterleitung zur Login-Seite mit Erfolgsmeldung
header("Location: ../login.php?logout=1");
exit();
?>