<?php
// api/portal_jm_fragebogen_results.php - Auswertung JM-Fragebogen (Vorstand/Admin)
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/session_config.inc.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}
$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['admin', 'vorstand'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Zugriff verweigert']);
    exit;
}

$db = getDB();

// Jahr bestimmen (gleiche Logik wie portal_fragebogen_data.php)
$year = intval($_GET['year'] ?? 0);
if ($year < 1) {
    $year = (int)date('Y');
    $currentMonth = (int)date('m');
    if ($currentMonth >= 10) {
        $nextYear = $year + 1;
        $stmtNY = $db->prepare("SELECT COUNT(*) FROM JMDefinition WHERE year = ? AND Erweitert = 1");
        $stmtNY->execute([$nextYear]);
        if ((int)$stmtNY->fetchColumn() > 0) {
            $year = $nextYear;
        }
    }
}

try {

// Waffen laden (für Labels)
$waffen = [];
foreach ($db->query("SELECT ID, Bezeichnung FROM Waffen ORDER BY Bezeichnung")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $waffen[(int)$row['ID']] = $row['Bezeichnung'];
}

// Erweiterte Definitionen laden
$defs = [];
$stmtD = $db->prepare("SELECT ID, Bezeichnung FROM JMDefinition WHERE year = ? AND Erweitert = 1 ORDER BY Reihenfolge");
$stmtD->execute([$year]);
foreach ($stmtD->fetchAll(PDO::FETCH_ASSOC) as $rd) {
    $defs[(int)$rd['ID']] = $rd['Bezeichnung'];
}

// Alle Fragebogen-Antworten laden
$stmt = $db->prepare("
    SELECT mf.ID as fb_id, mf.mitgliedID, mf.waffenID, mf.mannschaft, mf.gruppen,
           m.Vorname, m.Name
    FROM mitglieder_fragebogen mf
    JOIN mitglieder m ON m.ID = mf.mitgliedID
    WHERE mf.jahr = ?
    ORDER BY m.Name, m.Vorname
");
$stmt->execute([$year]);
$antworten = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Erweiterte Antworten laden
$erweitert = [];
if (!empty($defs)) {
    $fbIds = array_column($antworten, 'fb_id');
    if (!empty($fbIds)) {
        $placeholders = implode(',', array_fill(0, count($fbIds), '?'));
        $stmtE = $db->prepare("SELECT fragebogenID, jmdefinitionID, antwort FROM mitglieder_fragebogen_erweitert WHERE fragebogenID IN ($placeholders)");
        $stmtE->execute($fbIds);
        foreach ($stmtE->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $erweitert[(int)$row['fragebogenID']][(int)$row['jmdefinitionID']] = $row['antwort'];
        }
    }
}

// Aktive Mitglieder
$mitglieder = $db->query("SELECT ID, Vorname, Name FROM mitglieder WHERE Status = 1 AND Verstorben = 0 ORDER BY Name, Vorname")->fetchAll(PDO::FETCH_ASSOC);
$total = count($mitglieder);

// Beantwortet-IDs
$beantwortetIds = [];
foreach ($antworten as $a) {
    $beantwortetIds[(int)$a['mitgliedID']] = $a['Vorname'] . ' ' . $a['Name'];
}

// Nicht beantwortet
$nicht_beantwortet = [];
foreach ($mitglieder as $m) {
    if (!isset($beantwortetIds[$m['ID']])) {
        $nicht_beantwortet[] = $m['Vorname'] . ' ' . $m['Name'];
    }
}

// Statistiken berechnen

// 1. Waffen-Verteilung
$waffen_counts = [];
foreach ($waffen as $wId => $wName) {
    $waffen_counts[$wName] = 0;
}
foreach ($antworten as $a) {
    $wName = $waffen[(int)$a['waffenID']] ?? 'Unbekannt';
    $waffen_counts[$wName] = ($waffen_counts[$wName] ?? 0) + 1;
}

// 2. Mannschaft (ZSMM)
$mannschaft_counts = ['teil' => 0, 'nicht' => 0, 'evtl' => 0];
foreach ($antworten as $a) {
    $val = $a['mannschaft'] ?: 'nicht';
    $mannschaft_counts[$val] = ($mannschaft_counts[$val] ?? 0) + 1;
}

// 3. Gruppen (GM)
$gruppen_counts = ['teil' => 0, 'nicht' => 0, 'evtl' => 0];
foreach ($antworten as $a) {
    $val = $a['gruppen'] ?: 'nicht';
    $gruppen_counts[$val] = ($gruppen_counts[$val] ?? 0) + 1;
}

// 4. Erweiterte Fragen
$erweitert_results = [];
foreach ($defs as $defId => $defName) {
    $counts = ['ja' => 0, 'nein' => 0];
    foreach ($antworten as $a) {
        $val = $erweitert[(int)$a['fb_id']][$defId] ?? 'nein';
        $counts[$val] = ($counts[$val] ?? 0) + 1;
    }
    $erweitert_results[] = [
        'bezeichnung' => $defName,
        'optionen' => $counts
    ];
}

// Labels für Teilnahme-Optionen
$teilnahme_labels = [
    'teil' => 'Ich nehme teil',
    'nicht' => 'Ich nehme nicht teil',
    'evtl' => 'Nur wenn Gruppe füllt'
];

// CSV Export
if (isset($_GET['format']) && $_GET['format'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="JM_Fragebogen_' . $year . '_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    $header = ['Mitglied', 'Waffe', 'ZSMM', 'GM'];
    foreach ($defs as $defName) {
        $header[] = $defName;
    }
    fputcsv($out, $header, ';');

    foreach ($antworten as $a) {
        $row = [
            $a['Vorname'] . ' ' . $a['Name'],
            $waffen[(int)$a['waffenID']] ?? 'Unbekannt',
            $teilnahme_labels[$a['mannschaft']] ?? $a['mannschaft'],
            $teilnahme_labels[$a['gruppen']] ?? $a['gruppen']
        ];
        foreach ($defs as $defId => $defName) {
            $row[] = $erweitert[(int)$a['fb_id']][$defId] ?? 'nein';
        }
        fputcsv($out, $row, ';');
    }

    // Nicht beantwortet
    foreach ($nicht_beantwortet as $name) {
        $row = [$name, '-', '-', '-'];
        foreach ($defs as $d) { $row[] = '-'; }
        fputcsv($out, $row, ';');
    }

    fclose($out);
    exit;
}

// Einzelantworten pro Mitglied
$details = [];
foreach ($antworten as $a) {
    $row = [
        'name' => $a['Vorname'] . ' ' . $a['Name'],
        'waffe' => $waffen[(int)$a['waffenID']] ?? 'Unbekannt',
        'mannschaft' => $a['mannschaft'] ?: 'nicht',
        'gruppen' => $a['gruppen'] ?: 'nicht',
        'erweitert' => []
    ];
    foreach ($defs as $defId => $defName) {
        $row['erweitert'][] = [
            'bezeichnung' => $defName,
            'antwort' => $erweitert[(int)$a['fb_id']][$defId] ?? 'nein'
        ];
    }
    $details[] = $row;
}

echo json_encode([
    'success' => true,
    'year' => $year,
    'total_mitglieder' => $total,
    'total_beantwortet' => count($beantwortetIds),
    'waffen' => $waffen_counts,
    'mannschaft' => $mannschaft_counts,
    'gruppen' => $gruppen_counts,
    'erweitert' => $erweitert_results,
    'teilnahme_labels' => $teilnahme_labels,
    'details' => $details,
    'beantwortet' => array_values(array_map(function($name) {
        return ['name' => $name];
    }, $beantwortetIds)),
    'nicht_beantwortet' => $nicht_beantwortet
]);

} catch (Exception $e) {
    error_log('portal_jm_fragebogen_results: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ein Fehler ist aufgetreten.']);
}
