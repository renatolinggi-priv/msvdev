<?php
// api/jsk_teilnahme.php – Teilnahme an JSK-Terminen.
//   set  (POST, Jungschütze): eigene Teilnahme für einen Termin setzen (1/0)
//   list (GET, Vorstand/Admin): Teilnehmerliste eines Termins
// JSON, CSRF (bei POST), PDO.

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['jungschuetze', 'mitglied', 'vorstand', 'admin']);

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ---- GET: Teilnehmerliste eines Termins (nur Vorstand/Admin) ----------------
if ($method === 'GET' && $action === 'list') {
    if (!isVorstand()) {
        json_error('Zugriff verweigert', 403);
    }
    $terminId = (int) ($_GET['termin_id'] ?? 0);
    if ($terminId <= 0) json_error('Kein gültiger Termin.');

    // Alle aktiven Jungschützen + ihr (optionaler) Teilnahme-Eintrag; fehlt er -> teilnehmend (Default)
    $stmt = $db->prepare(
        "SELECT j.id, j.Vorname, j.Name, t.teilnahme
           FROM jungschuetzen j
           LEFT JOIN jsk_termin_teilnahme t ON t.jungschuetze_id = j.id AND t.termin_id = ?
          WHERE j.Aktiv = 1
          ORDER BY j.Name ASC, j.Vorname ASC"
    );
    $stmt->execute([$terminId]);
    $teil = []; $nicht = [];
    foreach ($stmt->fetchAll() as $r) {
        $name = trim($r['Vorname'] . ' ' . $r['Name']);
        if ($r['teilnahme'] !== null && (int) $r['teilnahme'] === 0) {
            $nicht[] = $name;
        } else {
            $teil[] = $name;   // Default oder explizit 1
        }
    }
    echo json_encode(['success' => true, 'teil' => $teil, 'nicht' => $nicht]);
    exit;
}

// ---- POST: eigene Teilnahme setzen (Jungschütze) ----------------------------
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateCsrf($csrf)) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
}

if (($input['action'] ?? '') === 'set') {
    $jsId = (int) (getJungschuetzeId() ?? 0);
    if ($jsId <= 0) {
        json_error('Dein Konto ist keinem Jungschützen zugeordnet.', 403);
    }
    $terminId  = (int) ($input['termin_id'] ?? 0);
    $teilnahme = !empty($input['teilnahme']) ? 1 : 0;
    if ($terminId <= 0) json_error('Kein gültiger Termin.');

    // Termin muss existieren und für JSK geflaggt sein
    $chk = $db->prepare("SELECT 1 FROM wichtige_termine WHERE ID = ? AND fuer_jsk = 1 LIMIT 1");
    $chk->execute([$terminId]);
    if (!$chk->fetchColumn()) json_error('Termin nicht gefunden.', 404);

    $db->prepare(
        "INSERT INTO jsk_termin_teilnahme (termin_id, jungschuetze_id, teilnahme) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE teilnahme = VALUES(teilnahme)"
    )->execute([$terminId, $jsId, $teilnahme]);

    echo json_encode(['success' => true, 'teilnahme' => $teilnahme]);
    exit;
}

json_error('Unbekannte Aktion.');
