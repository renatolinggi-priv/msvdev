<?php
// Zentrale Session-Konfiguration (inkl. Cross-Subdomain Cookie-Domain)
require_once __DIR__ . '/session_config.inc.php';

// Remember-Token löschen (iOS PWA Persistenz)
require_once __DIR__ . '/dbconnect.inc.php';
require_once __DIR__ . '/remember_me.inc.php';
clearRememberToken();

// Session-Variablen löschen
$_SESSION = array();

// Session zerstören
session_destroy();

// Weiterleitung zur Login-Seite mit Erfolgsmeldung
header("Location: ../login.php?logout=1");
exit();
