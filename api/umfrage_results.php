<?php
// api/umfrage_results.php - Auswertung einer Umfrage (Vorstand/Admin)
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/session_config.inc.php';

// Auth manuell prüfen (JSON-freundlich, kein Redirect)
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

$id = intval($_GET['id'] ?? 0);
if ($id < 1) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Ungültige Umfrage-ID']);
    exit;
}

$db = getDB();

try {

// Umfrage laden
$stmt = $db->prepare("SELECT id, titel, status FROM umfragen WHERE id = ?");
$stmt->execute([$id]);
$umfrage = $stmt->fetch();
if (!$umfrage) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Umfrage nicht gefunden']);
    exit;
}

// Fragen laden
$stmtF = $db->prepare("SELECT id, frage_text, frage_typ, optionen FROM umfragen_fragen WHERE umfrage_id = ? ORDER BY reihenfolge");
$stmtF->execute([$id]);
$fragen = $stmtF->fetchAll();

// Alle Antworten laden (mit Fallback falls Tabelle fehlt)
$alle_antworten = [];
try {
    $stmtA = $db->prepare("
        SELECT ua.frage_id, ua.antwort, ua.mitglied_id, m.Vorname, m.Name
        FROM umfragen_antworten ua
        JOIN mitglieder m ON m.ID = ua.mitglied_id
        WHERE ua.umfrage_id = ?
        ORDER BY m.Name, m.Vorname
    ");
    $stmtA->execute([$id]);
    $alle_antworten = $stmtA->fetchAll();
} catch (Exception $e2) {
    // umfragen_antworten existiert evtl. noch nicht
}

// Antworten nach frage_id gruppieren
$antworten_by_frage = [];
$beantwortet_ids = [];
foreach ($alle_antworten as $a) {
    $antworten_by_frage[$a['frage_id']][] = $a;
    $beantwortet_ids[$a['mitglied_id']] = $a['Vorname'] . ' ' . $a['Name'];
}

// Aktive Mitglieder laden
$mitglieder = $db->query("SELECT ID, Vorname, Name FROM mitglieder WHERE Status = 1 AND Verstorben = 0 ORDER BY Name, Vorname")->fetchAll();
$total = count($mitglieder);

// Nicht-Beantworter
$nicht_beantwortet = [];
foreach ($mitglieder as $m) {
    if (!isset($beantwortet_ids[$m['ID']])) {
        $nicht_beantwortet[] = $m['Vorname'] . ' ' . $m['Name'];
    }
}

// CSV-Export
if (isset($_GET['format']) && $_GET['format'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Umfrage_' . $id . '_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    $out = fopen('php://output', 'w');

    // Header
    $header = ['Mitglied'];
    foreach ($fragen as $f) {
        $header[] = $f['frage_text'];
    }
    fputcsv($out, $header, ';');

    // Antworten pro Mitglied
    foreach ($mitglieder as $m) {
        $row = [$m['Vorname'] . ' ' . $m['Name']];
        foreach ($fragen as $f) {
            $val = '';
            foreach ($antworten_by_frage[$f['id']] ?? [] as $a) {
                if ($a['mitglied_id'] == $m['ID']) {
                    if ($f['frage_typ'] === 'checkbox') {
                        $decoded = json_decode($a['antwort'], true);
                        $val = is_array($decoded) ? implode(', ', $decoded) : $a['antwort'];
                    } else {
                        $val = $a['antwort'];
                    }
                    break;
                }
            }
            $row[] = $val;
        }
        fputcsv($out, $row, ';');
    }

    fclose($out);
    exit;
}

// JSON-Auswertung
header('Content-Type: application/json; charset=utf-8');

$ergebnisse = [];
foreach ($fragen as $f) {
    $optionen_raw = $f['optionen'] ? json_decode($f['optionen'], true) : [];
    $frage_antworten = $antworten_by_frage[$f['id']] ?? [];

    $result = [
        'frage_id'   => (int)$f['id'],
        'frage_text' => $f['frage_text'],
        'frage_typ'  => $f['frage_typ'],
        'total'      => count($frage_antworten),
    ];

    if (in_array($f['frage_typ'], ['radio', 'dropdown'])) {
        // Zählen pro Option
        $counts = [];
        foreach ($optionen_raw as $opt) {
            $counts[$opt] = 0;
        }
        foreach ($frage_antworten as $a) {
            $val = $a['antwort'];
            if (isset($counts[$val])) {
                $counts[$val]++;
            }
        }
        $result['optionen'] = $counts;
    } elseif ($f['frage_typ'] === 'checkbox') {
        // Zählen pro Option (JSON-Array decodieren)
        $counts = [];
        foreach ($optionen_raw as $opt) {
            $counts[$opt] = 0;
        }
        foreach ($frage_antworten as $a) {
            $decoded = json_decode($a['antwort'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $v) {
                    if (isset($counts[$v])) {
                        $counts[$v]++;
                    }
                }
            }
        }
        $result['optionen'] = $counts;
    } else {
        // Text: alle Antworten als Liste
        $result['texte'] = [];
        foreach ($frage_antworten as $a) {
            if (trim($a['antwort']) !== '') {
                $result['texte'][] = [
                    'mitglied_id' => (int)$a['mitglied_id'],
                    'mitglied'    => $a['Vorname'] . ' ' . $a['Name'],
                    'antwort'     => $a['antwort']
                ];
            }
        }
    }

    $ergebnisse[] = $result;
}

echo json_encode([
    'success'           => true,
    'umfrage'           => $umfrage,
    'total_mitglieder'  => $total,
    'total_beantwortet' => count($beantwortet_ids),
    'ergebnisse'        => $ergebnisse,
    'nicht_beantwortet' => $nicht_beantwortet,
    'beantwortet'       => array_map(function($name, $id) {
        return ['mitglied_id' => (int)$id, 'name' => $name];
    }, $beantwortet_ids, array_keys($beantwortet_ids))
]);

} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    error_log('umfrage_results: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ein Fehler ist aufgetreten.']);
}
