<?php
// api/benachrichtigung_prefs.php - Themen-Schalter pro Benutzer
//   GET  -> aktuelle Schalter laden (fehlende Zeile = alles an)
//   POST -> Schalter speichern (Upsert, CSRF)

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand', 'mitglied']);

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$fields = ['push_aktiv', 'einsaetze', 'jm', 'umfragen', 'termine'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT push_aktiv, einsaetze, jm, umfragen, termine, fotos, jsk_betreuung, chat, einsatz_tausch, lead_tage FROM benachrichtigung_prefs WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    $prefs = [];
    foreach ($fields as $f) {
        // Fehlende Zeile -> Default 1 (alles an)
        $prefs[$f] = $row ? (((int) $row[$f]) === 1) : true;
    }
    // Foto-Galerie: Default AN (1)
    $prefs['fotos'] = $row ? ((int) ($row['fotos'] ?? 1) === 1) : true;
    // Jungschuetzen-Betreuung ist Opt-In -> Default AUS (0)
    $prefs['jsk_betreuung'] = ($row && (int) ($row['jsk_betreuung'] ?? 0) === 1);
    // Chat-Push: Default AN (1)
    $prefs['chat'] = $row ? ((int) ($row['chat'] ?? 1) === 1) : true;
    // Einsatz-Tausch: Default AN (1)
    $prefs['einsatz_tausch'] = $row ? ((int) ($row['einsatz_tausch'] ?? 1) === 1) : true;
    // Vorlaufzeit in Tagen (null = noch nicht angepasst -> globale Standardwerte)
    $prefs['lead_tage'] = ($row && $row['lead_tage'] !== null) ? (int) $row['lead_tage'] : null;

    echo json_encode(['success' => true, 'prefs' => $prefs]);
    exit;
}

// --- POST: speichern ---------------------------------------------------------
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateCsrf($csrf)) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.');
}

// Spalten dynamisch aufbauen: Basisfelder immer, optionale Flags nur wenn mitgesendet
// (damit andere Speicheraufrufe diese Werte nicht versehentlich zuruecksetzen).
// Alle Spaltennamen stammen aus festen Whitelists -> kein Injection-Risiko.
$cols = $fields;                       // push_aktiv, einsaetze, jm, umfragen, termine
$params = [];
foreach ($fields as $f) $params[$f] = !empty($input[$f]) ? 1 : 0;

if (array_key_exists('jsk_betreuung', $input))  { $cols[] = 'jsk_betreuung';  $params['jsk_betreuung']  = !empty($input['jsk_betreuung']) ? 1 : 0; }
if (array_key_exists('chat', $input))           { $cols[] = 'chat';           $params['chat']           = !empty($input['chat']) ? 1 : 0; }
if (array_key_exists('einsatz_tausch', $input)) { $cols[] = 'einsatz_tausch'; $params['einsatz_tausch'] = !empty($input['einsatz_tausch']) ? 1 : 0; }
if (array_key_exists('fotos', $input))          { $cols[] = 'fotos';          $params['fotos']          = !empty($input['fotos']) ? 1 : 0; }

// Vorlaufzeit (Tage). Fehlt/leer -> null (globale Standardwerte), sonst 0..30 geklemmt.
$lead = null;
if (array_key_exists('lead_tage', $input) && $input['lead_tage'] !== '' && $input['lead_tage'] !== null) {
    $lead = max(0, min(30, (int) $input['lead_tage']));
}
$cols[] = 'lead_tage';
$params['lead_tage'] = $lead;

$allCols = array_merge(['user_id'], $cols);
$colSql  = implode(', ', array_map(fn($c) => "`$c`", $allCols));
$phSql   = implode(', ', array_map(fn($c) => ":$c", $allCols));
$updSql  = implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", $cols));
$bind = [':user_id' => $userId];
foreach ($cols as $c) $bind[":$c"] = $params[$c];

$db->prepare("INSERT INTO benachrichtigung_prefs ($colSql) VALUES ($phSql) ON DUPLICATE KEY UPDATE $updSql")
   ->execute($bind);

echo json_encode(['success' => true, 'message' => 'Einstellungen gespeichert.']);
