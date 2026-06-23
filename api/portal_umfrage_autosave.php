<?php
// api/portal_umfrage_autosave.php - Einzelne Antwort automatisch speichern
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/_umfrage_antwort.inc.php';

header('Content-Type: application/json; charset=utf-8');

// json_error() wird zentral in auth.php bereitgestellt

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Ungültige Anfrage', 405);
}

requireLogin();

// CSRF prüfen
if (!validateCsrfRequest()) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.');
}

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
if (!$mitglied_id) {
    json_error('Kein Mitglied mit diesem Konto verknüpft.');
}

$umfrage_id = intval($_POST['umfrage_id'] ?? 0);
$frage_id = intval($_POST['frage_id'] ?? 0);
$antwort_raw = $_POST['antwort'] ?? null;

if ($umfrage_id < 1 || $frage_id < 1) {
    json_error('Ungültige Parameter');
}

$db = getDB();

// Umfrage prüfen
$stmt = $db->prepare("SELECT id, status, zielgruppe FROM umfragen WHERE id = ?");
$stmt->execute([$umfrage_id]);
$umfrage = $stmt->fetch();

if (!$umfrage || $umfrage['status'] !== 'aktiv') {
    json_error('Diese Umfrage ist nicht mehr aktiv.');
}
// Zielgruppen-Check: Vorstand-interne Umfragen nur für Vorstand/Admin beantwortbar
if (($umfrage['zielgruppe'] ?? 'alle') === 'vorstand'
    && !in_array($_SESSION['user_role'] ?? 'mitglied', ['admin', 'vorstand'])) {
    json_error('Kein Zugriff auf diese Umfrage.', 403);
}

// Frage prüfen
$stmtF = $db->prepare("SELECT id, frage_typ FROM umfragen_fragen WHERE id = ? AND umfrage_id = ?");
$stmtF->execute([$frage_id, $umfrage_id]);
$frage = $stmtF->fetch();

if (!$frage) {
    json_error('Ungültige Frage');
}

// Wert aufbereiten
if ($frage['frage_typ'] === 'checkbox') {
    // Array zu JSON
    $val = is_array($antwort_raw) ? json_encode(array_values($antwort_raw), JSON_UNESCAPED_UNICODE) : '[]';
} else {
    $val = trim($antwort_raw ?? '');
}

try {
    if ($val === '' || ($frage['frage_typ'] === 'checkbox' && $val === '[]')) {
        // Leere Antwort: bestehende löschen
        deleteUmfrageAntwort($db, $frage_id, $mitglied_id);
    } else {
        upsertUmfrageAntwort($db, $umfrage_id, $frage_id, $mitglied_id, $val);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('portal_umfrage_autosave: ' . $e->getMessage());
    json_error('Fehler beim Speichern. Bitte versuche es erneut.', 500);
}
