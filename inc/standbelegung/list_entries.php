<?php
// list_entries.php – alle Standbelegungs-Eintraege als JSON (Client laedt nach dem Import neu statt location.reload)
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

header('Content-Type: application/json; charset=utf-8');

$entries = [];
$res = $conn->query("SELECT ID, Datum, Wochentag, Bezeichnung, StartZeit, EndZeit, Kategorie, InKalender, Jahr
                     FROM Standbelegung ORDER BY Jahr DESC, Datum ASC, StartZeit ASC");
while ($res && ($row = $res->fetch_assoc())) {
    $row['ID'] = (int)$row['ID'];
    $row['Jahr'] = (int)$row['Jahr'];
    $row['InKalender'] = (int)$row['InKalender'];
    $entries[] = $row;
}
$conn->close();

echo json_encode(['success' => true, 'entries' => $entries], JSON_UNESCAPED_UNICODE);
