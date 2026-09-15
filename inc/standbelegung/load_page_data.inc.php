<?php
/**
 * inc/standbelegung/load_page_data.inc.php – Seitendaten fuer standbelegung.php
 * Erwartet $conn (mysqli). Liefert: $currentYear, $existingEntries, $stats, $years, $artKeywords, $pdfInfos
 */
require_once __DIR__ . '/standbelegung_config.inc.php';

$currentYear = (int)date('Y');

$existingEntries = [];
$stats = [];
$res = $conn->query("SELECT ID, Datum, Wochentag, Bezeichnung, StartZeit, EndZeit, Kategorie, InKalender, Jahr
                     FROM Standbelegung ORDER BY Jahr DESC, Datum ASC, StartZeit ASC");
while ($res && ($row = $res->fetch_assoc())) {
    $row['ID'] = (int)$row['ID'];
    $row['Jahr'] = (int)$row['Jahr'];
    $row['InKalender'] = (int)$row['InKalender'];
    $existingEntries[] = $row;
    $jahr = $row['Jahr'];
    if (!isset($stats[$jahr])) {
        $stats[$jahr] = array_fill_keys(SB_KATEGORIEN, 0) + ['total' => 0, 'inKalender' => 0];
    }
    if (isset($stats[$jahr][$row['Kategorie']])) $stats[$jahr][$row['Kategorie']]++;
    $stats[$jahr]['total']++;
    if ($row['InKalender']) $stats[$jahr]['inKalender']++;
}

$years = array_keys($stats);
foreach ([$currentYear, $currentYear + 1] as $y) {
    if (!in_array($y, $years, true)) $years[] = $y;
}
sort($years);

$artKeywords = sb_load_keywords($conn);

// Gespeicherte Standbelegungs-PDFs (werden vom WordPress-Plugin ueber get_pdf.php ausgeliefert)
$pdfInfos = [];
foreach ($years as $y) {
    $p = __DIR__ . '/pdf/standbelegung_' . $y . '.pdf';
    if (file_exists($p)) {
        $pdfInfos[$y] = ['size' => round(filesize($p) / 1024, 1), 'date' => date('d.m.Y H:i', filemtime($p))];
    }
}
