<?php
/**
 * changelog_publish.php — Zentraler Endpoint zum Veröffentlichen von Changelog-Einträgen
 *
 * POST-Parameter:
 *   kategorie    - resultate | termine | definition | standbelegung
 *   tabelle      - kantiresultate | heimresultate | wichtige_termine | Standbelegung | JMDefinition
 *   jahr         - betroffenes Jahr (optional)
 *   beschreibung - Menschenlesbarer Text
 *   csrf_token   - CSRF-Schutz
 */
include 'config.php';
require_once __DIR__ . '/changelog_helper.php';

// CSRF-Schutz
require_once __DIR__ . '/session_config.inc.php';
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// Parameter validieren
$kategorie    = $_POST['kategorie'] ?? '';
$tabelle      = $_POST['tabelle'] ?? '';
$jahr         = !empty($_POST['jahr']) ? intval($_POST['jahr']) : null;
$beschreibung = trim($_POST['beschreibung'] ?? '');

$erlaubteKategorien = ['resultate', 'termine', 'definition', 'standbelegung'];
if (!in_array($kategorie, $erlaubteKategorien, true)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültige Kategorie']));
}

if (empty($beschreibung)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Beschreibung fehlt']));
}

// Changelog-Eintrag schreiben
$id = logChangelog($kategorie, 'aktualisiert', $beschreibung, [
    'tabelle' => $tabelle ?: null,
    'jahr'    => $jahr,
]);

if ($id) {
    echo json_encode(['success' => true, 'message' => 'Änderung veröffentlicht', 'id' => $id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Veröffentlichen']);
}
