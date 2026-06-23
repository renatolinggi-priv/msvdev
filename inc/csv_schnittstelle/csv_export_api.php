<?php
/**
 * CSV-Schnittstelle API — shooters.csv Export + Settings
 *
 * GET  ?action=get_settings    → Einstellungen laden
 * GET  ?action=get_status      → Letzter Export-Status (Anzahl, Aufschluesselung)
 * POST action=save_settings    → Einstellungen speichern
 * POST action=export_shooters  → shooters.csv generieren
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../session_config.inc.php';
require_once __DIR__ . '/../../auth.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}

if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'vorstand'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

$db = getDB();

// === GET ===
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_settings':
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('csv_export_aktiv', 'csv_pfad_shooters')");
            $stmt->execute();
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            echo json_encode(['success' => true, 'data' => $settings]);
            exit;

        case 'get_status':
            $jahr = (int)date('Y');
            echo json_encode(['success' => true, 'data' => getExportStatus($db, $jahr)]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
            exit;
    }
}

// === POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ungueltiges CSRF-Token']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'save_settings':
            $keys = ['csv_export_aktiv', 'csv_pfad_shooters'];
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            foreach ($keys as $k) {
                if (isset($input[$k])) {
                    $stmt->execute([$k, $input[$k]]);
                }
            }
            echo json_encode(['success' => true, 'message' => 'Einstellungen gespeichert']);
            exit;

        case 'export_shooters':
            $jahr = (int)date('Y');
            $result = generateShootersCsv($db, $jahr);
            echo json_encode($result);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
            exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);
exit;

// ============================================================
//  shooters.csv generieren
// ============================================================

function generateShootersCsv(PDO $db, int $jahr): array {
    // Pfad aus Settings lesen
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'csv_pfad_shooters'");
    $stmt->execute();
    $pfad = (string)$stmt->fetchColumn();

    if (empty($pfad)) {
        return ['success' => false, 'message' => 'Pfad nicht konfiguriert'];
    }

    $dir = dirname($pfad);
    if (!is_dir($dir)) {
        return ['success' => false, 'message' => 'Verzeichnis existiert nicht: ' . $dir];
    }

    // Mitglieder mit mindestens einem Stich im aktuellen Jahr
    $sql = "
        SELECT DISTINCT
            m.ID AS mnr,
            m.Name AS nachname,
            m.Vorname AS vorname,
            m.Geburtsdatum AS geburtsdatum,
            'mitglied' AS typ
        FROM endstich_selection es
        JOIN mitglieder m ON es.mitglied_id = m.ID
        WHERE es.jahr = :jahr1 AND es.mitglied_id IS NOT NULL

        UNION

        SELECT DISTINCT
            g.mitgliedernr AS mnr,
            g.name AS nachname,
            '' AS vorname,
            g.geburtsdatum AS geburtsdatum,
            CASE WHEN g.geburtsdatum IS NOT NULL THEN 'js' ELSE 'gast' END AS typ
        FROM endstich_selection es
        JOIN endstich_gaeste g ON es.gast_id = g.id
        WHERE es.jahr = :jahr2 AND es.gast_id IS NOT NULL

        ORDER BY mnr
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(['jahr1' => $jahr, 'jahr2' => $jahr]);
    $rows = $stmt->fetchAll();

    $lines = [];
    $counts = ['mitglied' => 0, 'gast' => 0, 'js' => 0];

    foreach ($rows as $row) {
        $counts[$row['typ']]++;

        // Name: Bei Mitgliedern "Nachname Vorname", bei Gaesten/JS steht alles in nachname
        $name = !empty($row['vorname'])
            ? $row['nachname'] . ' ' . $row['vorname']
            : $row['nachname'];

        // Jahrgang 2-stellig
        $jahrgang = '';
        if (!empty($row['geburtsdatum'])) {
            $year = (int)substr($row['geburtsdatum'], 0, 4);
            $jahrgang = str_pad($year % 100, 2, '0', STR_PAD_LEFT);
        }

        $lines[] = implode(';', [
            $row['mnr'],
            $name,
            '', '',
            $jahrgang,
        ]);
    }

    $content = implode("\r\n", $lines) . ($lines ? "\r\n" : '');

    // UTF-8 → ISO-8859-1 (Schiessanlage erwartet ISO)
    $content = mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');

    // Nur schreiben wenn sich Inhalt geaendert hat
    $existing = file_exists($pfad) ? file_get_contents($pfad) : '';
    if ($content === $existing) {
        return [
            'success' => true,
            'message' => 'Keine Aenderungen — Datei unveraendert',
            'unchanged' => true,
            'counts' => $counts,
            'total' => count($lines),
        ];
    }

    $written = file_put_contents($pfad, $content);
    if ($written === false) {
        return ['success' => false, 'message' => 'Datei konnte nicht geschrieben werden: ' . $pfad];
    }

    return [
        'success' => true,
        'message' => count($lines) . ' Schuetzen exportiert',
        'counts' => $counts,
        'total' => count($lines),
    ];
}

// ============================================================
//  Export-Status (Vorschau ohne zu schreiben)
// ============================================================

function getExportStatus(PDO $db, int $jahr): array {
    // Mitglieder zaehlen
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT es.mitglied_id)
        FROM endstich_selection es
        WHERE es.jahr = ? AND es.mitglied_id IS NOT NULL
    ");
    $stmt->execute([$jahr]);
    $mitglieder = (int)$stmt->fetchColumn();

    // Gaeste zaehlen (ohne Geburtsdatum)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT es.gast_id)
        FROM endstich_selection es
        JOIN endstich_gaeste g ON es.gast_id = g.id
        WHERE es.jahr = ? AND es.gast_id IS NOT NULL AND g.geburtsdatum IS NULL
    ");
    $stmt->execute([$jahr]);
    $gaeste = (int)$stmt->fetchColumn();

    // JS zaehlen (mit Geburtsdatum)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT es.gast_id)
        FROM endstich_selection es
        JOIN endstich_gaeste g ON es.gast_id = g.id
        WHERE es.jahr = ? AND es.gast_id IS NOT NULL AND g.geburtsdatum IS NOT NULL
    ");
    $stmt->execute([$jahr]);
    $js = (int)$stmt->fetchColumn();

    // Pfad-Status
    $stmtP = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'csv_pfad_shooters'");
    $stmtP->execute();
    $pfad = (string)$stmtP->fetchColumn();
    $fileExists = !empty($pfad) && file_exists($pfad);
    $fileMtime = $fileExists ? date('d.m.Y H:i:s', filemtime($pfad)) : null;

    return [
        'jahr' => $jahr,
        'mitglieder' => $mitglieder,
        'gaeste' => $gaeste,
        'js' => $js,
        'total' => $mitglieder + $gaeste + $js,
        'file_exists' => $fileExists,
        'file_mtime' => $fileMtime,
    ];
}
