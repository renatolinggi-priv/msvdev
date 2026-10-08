<?php
/**
 * count_unpublished.php — Zählt unveröffentlichte JM-Resultate-Changelog-Einträge
 * GET: year
 */
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/../changelog_helper.php';

header('Content-Type: application/json; charset=utf-8');

$year = intval($_GET['year'] ?? date('Y'));
$count = countUnpublishedJmChangelog($year);

echo json_encode(['success' => true, 'count' => $count]);
