<?php
// api/portal_umfrage_save.php - Antworten für eine Umfrage speichern
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
if ($umfrage_id < 1) {
    json_error('Ungültige Umfrage-ID');
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

// Fragen laden (für Validierung)
$stmtF = $db->prepare("SELECT id, frage_text, frage_typ, pflichtfeld, optionen FROM umfragen_fragen WHERE umfrage_id = ? ORDER BY reihenfolge");
$stmtF->execute([$umfrage_id]);
$fragen = $stmtF->fetchAll();

// Antworten aus POST extrahieren: antworten[frage_id] = wert
$antworten = $_POST['antworten'] ?? [];
if (!is_array($antworten)) {
    json_error('Ungültige Antwortdaten');
}

// Pflichtfelder prüfen
foreach ($fragen as $f) {
    if ($f['pflichtfeld']) {
        $antwort = $antworten[$f['id']] ?? null;
        if ($f['frage_typ'] === 'checkbox') {
            // Checkbox: Array muss mindestens einen Wert haben
            if (!is_array($antwort) || empty($antwort)) {
                json_error('Bitte beantworte: "' . $f['frage_text'] . '"');
            }
        } else {
            if ($antwort === null || trim($antwort) === '') {
                json_error('Bitte beantworte: "' . $f['frage_text'] . '"');
            }
        }
    }
}

// Speichern
try {
    $db->beginTransaction();

    foreach ($fragen as $f) {
        $frage_id = (int)$f['id'];
        $raw = $antworten[$frage_id] ?? null;

        // Wert aufbereiten
        if ($f['frage_typ'] === 'checkbox') {
            // Array zu JSON
            $val = is_array($raw) ? json_encode(array_values($raw), JSON_UNESCAPED_UNICODE) : '[]';
        } elseif ($f['frage_typ'] === 'text') {
            $val = trim($raw ?? '');
        } else {
            // radio, dropdown: Einzelwert
            $val = trim($raw ?? '');
        }

        // Nur speichern wenn Wert vorhanden (oder Pflichtfeld, was oben schon geprüft wurde)
        if ($val === '' && !$f['pflichtfeld']) {
            // Optionales Feld ohne Antwort: bestehende Antwort löschen falls vorhanden
            deleteUmfrageAntwort($db, $frage_id, $mitglied_id);
            continue;
        }

        upsertUmfrageAntwort($db, $umfrage_id, $frage_id, $mitglied_id, $val);
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Antworten gespeichert!']);

} catch (Exception $e) {
    $db->rollBack();
    error_log('portal_umfrage_save: ' . $e->getMessage());
    json_error('Fehler beim Speichern. Bitte versuche es erneut.', 500);
}
