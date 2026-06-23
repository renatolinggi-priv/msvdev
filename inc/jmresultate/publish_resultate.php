<?php
/**
 * publish_resultate.php — Veröffentlicht Changelog-Einträge für JM-Resultate eines Jahres
 * POST: year, csrf_token
 */
include '../config.php';
require_once __DIR__ . '/../changelog_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$year = intval($_POST['year'] ?? date('Y'));

// Alle unveröffentlichten JM-Resultate-Einträge freigeben
$count = publishJmChangelog($year);

// Zusätzlich einen sichtbaren Sammel-Eintrag erstellen
logChangelog('resultate', 'aktualisiert', "JM-Resultate $year veröffentlicht", [
    'tabelle' => 'jmresultate',
    'jahr' => $year,
    'sichtbar' => 1
]);

echo json_encode([
    'success' => true,
    'message' => "JM-Resultate $year veröffentlicht ($count Einträge)",
    'published' => $count
]);
