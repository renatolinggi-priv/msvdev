<?php
// api/portal_umfragen_list.php - Aktive Umfragen für eingeloggtes Mitglied
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');
requireLogin();

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
if (!$mitglied_id) {
    echo json_encode(['success' => true, 'umfragen' => [], 'count' => 0]);
    exit;
}

$db = getDB();

try {
    // Nur count zurückgeben (für Dashboard-Badge)
    if (isset($_GET['count_only'])) {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT u.id) FROM umfragen u
            WHERE u.status = 'aktiv'
              AND (u.zielgruppe = 'alle' OR (u.zielgruppe = 'vorstand' AND ? IN ('admin','vorstand')))
              AND (u.gueltig_bis IS NULL OR u.gueltig_bis >= CURDATE())
              AND u.id NOT IN (
                  SELECT DISTINCT ua.umfrage_id FROM umfragen_antworten ua WHERE ua.mitglied_id = ?
              )
        ");
        $role = $_SESSION['user_role'] ?? 'mitglied';
        $stmt->execute([$role, $mitglied_id]);
        echo json_encode(['success' => true, 'count' => (int)$stmt->fetchColumn()]);
        exit;
    }

    // Alle aktiven + geschlossene (mit eigenen Antworten) + Entwürfe (nur Admin/Vorstand)
    $role = $_SESSION['user_role'] ?? 'mitglied';
    $ist_vorstand = in_array($role, ['admin', 'vorstand']);
    $stmt = $db->prepare("
        SELECT u.id, u.titel, u.beschreibung, u.gueltig_bis, u.status, u.kategorie,
            (SELECT COUNT(*) FROM umfragen_antworten ua WHERE ua.umfrage_id = u.id AND ua.mitglied_id = ?) > 0 AS beantwortet
        FROM umfragen u
        WHERE (
            (u.status = 'aktiv' AND (u.gueltig_bis IS NULL OR u.gueltig_bis >= CURDATE()))
            OR (u.status = 'aktiv' AND u.gueltig_bis < CURDATE() AND ? IN ('admin','vorstand'))
            OR (u.status = 'geschlossen' AND u.id IN (SELECT DISTINCT ua2.umfrage_id FROM umfragen_antworten ua2 WHERE ua2.mitglied_id = ?))
            OR (u.status = 'entwurf' AND ? IN ('admin','vorstand'))
        )
        AND (u.zielgruppe = 'alle' OR (u.zielgruppe = 'vorstand' AND ? IN ('admin','vorstand')))
        ORDER BY
            CASE u.status WHEN 'entwurf' THEN 0 WHEN 'aktiv' THEN 1 ELSE 2 END,
            CASE WHEN u.status = 'aktiv' AND (SELECT COUNT(*) FROM umfragen_antworten ua3 WHERE ua3.umfrage_id = u.id AND ua3.mitglied_id = ?) = 0 THEN 0 ELSE 1 END,
            u.erstellt_am DESC
    ");
    $stmt->execute([$mitglied_id, $role, $mitglied_id, $role, $role, $mitglied_id]);
    $umfragen = $stmt->fetchAll();

    foreach ($umfragen as &$u) {
        $u['beantwortet'] = (bool)$u['beantwortet'];
    }
    unset($u);

    echo json_encode(['success' => true, 'umfragen' => $umfragen]);
} catch (Exception $e) {
    // Tabelle existiert evtl. noch nicht
    echo json_encode(['success' => true, 'umfragen' => [], 'count' => 0]);
}
