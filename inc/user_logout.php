<?php
// Zentrale Session-Konfiguration (inkl. Cross-Subdomain Cookie-Domain)
require_once __DIR__ . '/session_config.inc.php';

// CSRF-Schutz: Logout nur per POST mit gültigem Token zulassen.
// Verhindert Logout-CSRF (fremde Seite loggt den User ungefragt aus).
$csrf = $_POST['csrf_token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], (string)$csrf)) {
    header('Location: ../login.php');
    exit();
}

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
