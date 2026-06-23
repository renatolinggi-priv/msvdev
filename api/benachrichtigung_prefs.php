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
    $stmt = $db->prepare('SELECT push_aktiv, einsaetze, jm, umfragen, termine, lead_tage FROM benachrichtigung_prefs WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    $prefs = [];
    foreach ($fields as $f) {
        // Fehlende Zeile -> Default 1 (alles an)
        $prefs[$f] = $row ? (((int) $row[$f]) === 1) : true;
    }
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

$vals = [];
foreach ($fields as $f) {
    $vals[$f] = !empty($input[$f]) ? 1 : 0;
}

// Vorlaufzeit (Tage). Fehlt/leer -> null (globale Standardwerte), sonst 0..30 geklemmt.
$lead = null;
if (array_key_exists('lead_tage', $input) && $input['lead_tage'] !== '' && $input['lead_tage'] !== null) {
    $lead = max(0, min(30, (int) $input['lead_tage']));
}

$stmt = $db->prepare(
    'INSERT INTO benachrichtigung_prefs (user_id, push_aktiv, einsaetze, jm, umfragen, termine, lead_tage)
     VALUES (:u, :push_aktiv, :einsaetze, :jm, :umfragen, :termine, :lead_tage)
     ON DUPLICATE KEY UPDATE push_aktiv = VALUES(push_aktiv), einsaetze = VALUES(einsaetze),
                             jm = VALUES(jm), umfragen = VALUES(umfragen), termine = VALUES(termine),
                             lead_tage = VALUES(lead_tage)'
);
$stmt->execute([
    ':u'          => $userId,
    ':push_aktiv' => $vals['push_aktiv'],
    ':einsaetze'  => $vals['einsaetze'],
    ':jm'         => $vals['jm'],
    ':umfragen'   => $vals['umfragen'],
    ':termine'    => $vals['termine'],
    ':lead_tage'  => $lead,
]);

echo json_encode(['success' => true, 'message' => 'Einstellungen gespeichert.']);
