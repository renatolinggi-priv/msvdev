<?php
// api/changelog_seen.php
// Markiert die aktuell neuste, fuer den Benutzer sichtbare changelog.json-Version
// als "gesehen". Wird vom globalen "Was ist neu"-Modal (portal_footer.php) beim
// Klick auf "Verstanden" aufgerufen. Kein Body-Parameter noetig (= bis jetzt gesehen).

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/changelog_portal.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand', 'mitglied']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Methode nicht erlaubt.', 405);
}

if (!validateCsrfRequest()) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    json_error('Nicht eingeloggt.', 401);
}

// Neuste sichtbare Version serverseitig bestimmen (Client nicht vertrauen).
$changelog = getPortalChangelog(isVorstand());
$newest    = portalChangelogNewest($changelog);

if ($newest === null) {
    // Kein Changelog vorhanden -> nichts zu merken, aber kein Fehler.
    echo json_encode(['success' => true]);
    exit;
}

$db   = getDB();
$stmt = $db->prepare('UPDATE users SET changelog_seen_version = ? WHERE id = ?');
$stmt->execute([$newest, $userId]);

echo json_encode(['success' => true, 'version' => $newest]);
