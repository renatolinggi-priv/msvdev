<?php
// endschrang/absenden_bereit.php – Kennzahlen für «Absenden vorbereiten» auf endschrang.php (nur lesen).
// Offene Stiche und Partnerinnen zählt die Seite aus den Ladern der Erfassung (eine Quelle der Wahrheit);
// hier nur die Wanderpreise: angeschafft bis zum Jahr, nicht in einem früheren Jahr definitiv gewonnen,
// und ob für das Jahr ein Gewinner eingetragen ist.
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

header('Content-Type: application/json; charset=utf-8');

$jahr = (int)($_GET['year'] ?? 0);
if ($jahr < 2000 || $jahr > (int)date('Y') + 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $st = $conn->prepare(
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(EXISTS(SELECT 1 FROM wanderpreise_gewinner g WHERE g.wanderpreis_id = w.id AND g.jahr = ?)), 0) AS vergeben
           FROM wanderpreise w
          WHERE w.beschaffung_datum <= ?
            AND NOT EXISTS (SELECT 1 FROM wanderpreise_gewinner d
                             WHERE d.wanderpreis_id = w.id AND d.ist_definitiv = 1 AND d.jahr < ?)');
    $st->bind_param('iii', $jahr, $jahr, $jahr);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    echo json_encode(['success' => true, 'jahr' => $jahr,
                      'wanderpreise' => ['total' => (int)$r['total'], 'vergeben' => (int)$r['vergeben']]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[absenden_bereit] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Die Wanderpreise konnten nicht geprüft werden.'], JSON_UNESCAPED_UNICODE);
}
