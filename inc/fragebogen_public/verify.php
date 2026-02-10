<?php
// verify.php — Verifiziert Mitglied via Geburtsdatum, gibt Formular-Daten + Token zurück
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

// --- Hilfsfunktionen ---

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function generate_verification_token($mitgliedId, $secret) {
    $expires = time() + 1800; // 30 Minuten
    $data = $mitgliedId . '|' . $expires;
    $hmac = hash_hmac('sha256', $data, $secret);
    return base64_encode($data . '|' . $hmac);
}

// --- Sicherheitschecks ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Ungültige Anfrage', 405);
}

// CSRF-Token prüfen
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.');
}

// Honeypot prüfen
if (!empty($_POST['website'])) {
    json_error('Verifizierung fehlgeschlagen.');
}

// Rate Limiting (Session-basiert)
if (!isset($_SESSION['verify_attempts'])) {
    $_SESSION['verify_attempts'] = 0;
    $_SESSION['verify_first_attempt'] = time();
}

// Nach 15 Minuten zurücksetzen
if (time() - $_SESSION['verify_first_attempt'] > 900) {
    $_SESSION['verify_attempts'] = 0;
    $_SESSION['verify_first_attempt'] = time();
}

if ($_SESSION['verify_attempts'] >= 5) {
    json_error('Zu viele Fehlversuche. Bitte versuche es in 15 Minuten erneut.', 429);
}

// reCAPTCHA v3 prüfen
$recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
if (!empty($recaptchaResponse)) {
    $recaptchaSecret = $config['recaptcha']['secret_key'] ?? '';
    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify?secret='
        . urlencode($recaptchaSecret) . '&response=' . urlencode($recaptchaResponse);
    $recaptchaResult = json_decode(file_get_contents($verifyUrl), true);
    if (!$recaptchaResult['success'] || ($recaptchaResult['score'] ?? 0) < 0.3) {
        json_error('Sicherheitsprüfung fehlgeschlagen. Bitte versuche es erneut.');
    }
}

// --- Eingaben validieren ---

$mitgliedId = isset($_POST['mitglied_id']) ? (int)$_POST['mitglied_id'] : 0;
$geburtsdatum = $_POST['geburtsdatum'] ?? '';

if ($mitgliedId <= 0 || empty($geburtsdatum)) {
    json_error('Bitte Mitglied und Geburtsdatum angeben.');
}

// Datumsformat validieren (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $geburtsdatum)) {
    json_error('Ungültiges Datumsformat.');
}

// --- Geburtsdatum verifizieren ---

$stmt = $conn->prepare("SELECT ID, Geburtsdatum FROM mitglieder WHERE ID = ? AND Status = 1");
$stmt->bind_param('i', $mitgliedId);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();
$stmt->close();

if (!$member || $member['Geburtsdatum'] !== $geburtsdatum) {
    $_SESSION['verify_attempts']++;
    $remaining = 5 - $_SESSION['verify_attempts'];
    $msg = 'Verifizierung fehlgeschlagen.';
    if ($remaining > 0 && $remaining <= 2) {
        $msg .= " Noch $remaining Versuch(e) möglich.";
    }
    json_error($msg);
}

// Erfolg — Versuche zurücksetzen
$_SESSION['verify_attempts'] = 0;

// --- Verifizierungs-Token generieren ---

$secret = defined('DB_PASS') ? DB_PASS : 'fallback-secret-key';
$token = generate_verification_token($mitgliedId, $secret);

// --- Formular-Daten laden ---

$year = date('Y');
// Ab Oktober: auch Folgejahr berücksichtigen (gleich wie jmdefinition.php)
$currentMonth = (int)date('m');
if ($currentMonth >= 10) {
    $nextYear = $year + 1;
    // Prüfe ob es Definitionen für nächstes Jahr gibt
    $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM JMDefinition WHERE year = ? AND Erweitert = 1");
    $checkStmt->bind_param('i', $nextYear);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    if ($checkResult['cnt'] > 0) {
        $year = $nextYear;
    }
}

// Waffen laden
$waffen = [];
$res = $conn->query("SELECT ID, Bezeichnung FROM Waffen ORDER BY Bezeichnung");
while ($row = $res->fetch_assoc()) {
    $waffen[] = ['id' => (int)$row['ID'], 'bezeichnung' => $row['Bezeichnung']];
}

// Aktuelle Waffe des Mitglieds
$stmtW = $conn->prepare("SELECT WaffenID FROM mitglieder WHERE ID = ?");
$stmtW->bind_param('i', $mitgliedId);
$stmtW->execute();
$memberWaffe = $stmtW->get_result()->fetch_assoc();
$stmtW->close();
$defaultWaffenID = $memberWaffe ? (int)$memberWaffe['WaffenID'] : 0;

// Erweiterte Definitionen laden (Erweitert = 1)
$defs = [];
$stmtD = $conn->prepare("SELECT ID, Bezeichnung FROM JMDefinition WHERE year = ? AND Erweitert = 1 ORDER BY Reihenfolge");
$stmtD->bind_param('i', $year);
$stmtD->execute();
$resD = $stmtD->get_result();
while ($rd = $resD->fetch_assoc()) {
    $defs[] = ['id' => (int)$rd['ID'], 'bezeichnung' => $rd['Bezeichnung']];
}
$stmtD->close();

// Bestehende Fragebogen-Antworten laden
$existing = ['waffenID' => $defaultWaffenID, 'mannschaft' => '', 'gruppen' => '', 'erweitert' => []];

$stmtFB = $conn->prepare("SELECT ID, waffenID, mannschaft, gruppen FROM mitglieder_fragebogen WHERE mitgliedID = ? AND jahr = ? LIMIT 1");
$stmtFB->bind_param('ii', $mitgliedId, $year);
$stmtFB->execute();
$resFB = $stmtFB->get_result();
$fbRow = $resFB->fetch_assoc();
$stmtFB->close();

if ($fbRow) {
    $existing['waffenID'] = (int)$fbRow['waffenID'];
    $existing['mannschaft'] = $fbRow['mannschaft'];
    $existing['gruppen'] = $fbRow['gruppen'];

    // Erweiterte Antworten
    $fbId = (int)$fbRow['ID'];
    $stmtExt = $conn->prepare("SELECT jmdefinitionID, antwort FROM mitglieder_fragebogen_erweitert WHERE fragebogenID = ?");
    $stmtExt->bind_param('i', $fbId);
    $stmtExt->execute();
    $resExt = $stmtExt->get_result();
    while ($extRow = $resExt->fetch_assoc()) {
        $existing['erweitert'][(int)$extRow['jmdefinitionID']] = $extRow['antwort'];
    }
    $stmtExt->close();
}

echo json_encode([
    'success'  => true,
    'token'    => $token,
    'year'     => $year,
    'waffen'   => $waffen,
    'defs'     => $defs,
    'existing' => $existing
]);
