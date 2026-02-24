<?php
// api/portal_umfrage_save.php - Antworten für eine Umfrage speichern
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Ungültige Anfrage', 405);
}

requireLogin();

// CSRF prüfen
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])
    || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
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
$stmt = $db->prepare("SELECT id, status FROM umfragen WHERE id = ?");
$stmt->execute([$umfrage_id]);
$umfrage = $stmt->fetch();

if (!$umfrage || $umfrage['status'] !== 'aktiv') {
    json_error('Diese Umfrage ist nicht mehr aktiv.');
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
            $db->prepare("DELETE FROM umfragen_antworten WHERE frage_id = ? AND mitglied_id = ?")->execute([$frage_id, $mitglied_id]);
            continue;
        }

        // Upsert
        $stmtCheck = $db->prepare("SELECT id FROM umfragen_antworten WHERE frage_id = ? AND mitglied_id = ? LIMIT 1");
        $stmtCheck->execute([$frage_id, $mitglied_id]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $db->prepare("UPDATE umfragen_antworten SET antwort = ?, beantwortet_am = NOW() WHERE id = ?")
                ->execute([$val, $existing['id']]);
        } else {
            $db->prepare("INSERT INTO umfragen_antworten (umfrage_id, frage_id, mitglied_id, antwort) VALUES (?, ?, ?, ?)")
                ->execute([$umfrage_id, $frage_id, $mitglied_id, $val]);
        }
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Antworten gespeichert!']);

} catch (Exception $e) {
    $db->rollBack();
    json_error('Fehler beim Speichern: ' . $e->getMessage(), 500);
}
