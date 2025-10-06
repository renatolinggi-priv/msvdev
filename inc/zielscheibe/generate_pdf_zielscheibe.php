<?php
// generate_pdf_zielscheibe.php
if (!defined('DB_HOST')) {
    include '../config.php';
}

require_once 'ZielscheibeReport.php';

// DEBUG aktivieren
error_log("=== PDF Generation gestartet ===");
error_log("GET: " . print_r($_GET, true));
error_log("POST: " . print_r($_POST, true));

// Schussdaten aus POST oder GET holen
$alleStiche = [];
$schuetzenName = isset($_GET['name']) ? $_GET['name'] : null;

// JSON aus POST Body lesen
$postData = file_get_contents('php://input');
error_log("POST Body: " . $postData);
if (!empty($postData)) {
    $jsonData = json_decode($postData, true);
    if (isset($jsonData['alleStiche']) && is_array($jsonData['alleStiche'])) {
        $alleStiche = $jsonData['alleStiche'];
    } else if (isset($jsonData['treffer']) && is_array($jsonData['treffer'])) {
        // Fallback: Altes Format mit treffer
        $alleStiche = [[
            'programmNummer' => isset($_GET['programm']) ? $_GET['programm'] : null,
            'stichName' => null,
            'schuesse' => $jsonData['treffer']
        ]];
    }
}

// Fallback: Normale POST Daten
if (empty($alleStiche) && isset($_POST['alleStiche']) && is_array($_POST['alleStiche'])) {
    $alleStiche = $_POST['alleStiche'];
}

// Fallback: GET Parameter
if (empty($alleStiche) && isset($_GET['alleStiche'])) {
    $alleStiche = json_decode($_GET['alleStiche'], true);
}

// DEBUG: Zeige was wir haben
error_log("alleStiche nach Parsing: " . print_r($alleStiche, true));
error_log("Anzahl Stiche: " . count($alleStiche));
if (!empty($alleStiche)) {
    foreach ($alleStiche as $idx => $stich) {
        error_log("Stich $idx: " . (isset($stich['stichName']) ? $stich['stichName'] : 'KEIN NAME'));
        error_log("  - Anzahl Sch\u00fcsse: " . (isset($stich['schuesse']) ? count($stich['schuesse']) : '0'));
    }
}

// Report erstellen und generieren
$report = new ZielscheibeReport($conn, $_GET['year'] ?? null, $alleStiche, $schuetzenName);
$report->generate();
?>
