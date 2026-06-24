<?php
// api/jsk_anfrage.php - Jungschuetze meldet einen Schiess-Termin an / sagt ab / listet eigene Anfragen.
// JSON, CSRF, Rolle jungschuetze (oder admin zum Testen).

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['jungschuetze', 'admin']);

$db     = getDB();
$jsId   = (int) (getJungschuetzeId() ?? 0);
$method = $_SERVER['REQUEST_METHOD'];

// ---- GET: eigene Anfragen auflisten -----------------------------------------
if ($method === 'GET' && ($_GET['action'] ?? '') === 'list') {
    if ($jsId <= 0) { echo json_encode(['success' => true, 'anfragen' => []]); exit; }
    $stmt = $db->prepare(
        "SELECT a.id, a.datum, a.zeit, a.bemerkung, a.status, a.betreut_am,
                bu.full_name AS betreuer_name
           FROM jsk_betreuung_anfragen a
           LEFT JOIN users bu ON bu.id = a.betreut_von_user_id
          WHERE a.jungschuetze_id = ?
          ORDER BY a.datum DESC, a.id DESC"
    );
    $stmt->execute([$jsId]);
    echo json_encode(['success' => true, 'anfragen' => $stmt->fetchAll()]);
    exit;
}

// ---- ab hier: schreibende Aktionen -> CSRF + Feature-Gate --------------------
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateCsrf($csrf)) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
}
if (!jskFeatureAktiv()) {
    json_error('Die Jungschützen-Betreuung ist derzeit deaktiviert.', 403);
}
if ($jsId <= 0) {
    json_error('Dein Konto ist keinem Jungschützen zugeordnet. Bitte den Jungschützenleiter kontaktieren.', 403);
}

$action = $input['action'] ?? '';

if ($action === 'create') {
    $datum     = trim((string) ($input['datum'] ?? ''));
    $zeit      = trim((string) ($input['zeit'] ?? ''));
    $bemerkung = trim((string) ($input['bemerkung'] ?? ''));

    // Datum validieren (Format + nicht in der Vergangenheit)
    $d = DateTime::createFromFormat('Y-m-d', $datum);
    if (!$d || $d->format('Y-m-d') !== $datum) {
        json_error('Bitte ein gültiges Datum wählen.');
    }
    $today = new DateTime('today');
    if ($d < $today) {
        json_error('Das Datum liegt in der Vergangenheit.');
    }
    if (mb_strlen($bemerkung) > 500) $bemerkung = mb_substr($bemerkung, 0, 500);
    if (mb_strlen($zeit) > 20) $zeit = mb_substr($zeit, 0, 20);

    // Dedupe: pro JSK + Datum nur eine offene/vergebene Anfrage
    $chk = $db->prepare("SELECT id FROM jsk_betreuung_anfragen WHERE jungschuetze_id = ? AND datum = ? AND status IN ('offen','vergeben') LIMIT 1");
    $chk->execute([$jsId, $datum]);
    if ($chk->fetchColumn()) {
        json_error('Für dieses Datum besteht bereits eine Anmeldung.');
    }

    $ins = $db->prepare("INSERT INTO jsk_betreuung_anfragen (jungschuetze_id, datum, zeit, bemerkung, status) VALUES (?, ?, ?, ?, 'offen')");
    $ins->execute([$jsId, $datum, ($zeit !== '' ? $zeit : null), ($bemerkung !== '' ? $bemerkung : null)]);
    $anfrageId = (int) $db->lastInsertId();

    // Name des Jungschuetzen fuer die Push-Nachricht
    $jsName = '';
    try {
        $n = $db->prepare('SELECT Vorname, Name FROM jungschuetzen WHERE id = ?');
        $n->execute([$jsId]);
        if ($row = $n->fetch()) $jsName = trim($row['Vorname'] . ' ' . $row['Name']);
    } catch (Throwable $e) { /* egal */ }

    $datumDe = $d->format('d.m.Y');
    jskNotifyBetreuer($db, 'Jungschütze sucht Begleitung',
        ($jsName !== '' ? $jsName : 'Ein Jungschütze') . ' möchte am ' . $datumDe . ' schiessen.');

    echo json_encode(['success' => true, 'message' => 'Termin angemeldet – Betreuer wurden benachrichtigt.', 'id' => $anfrageId]);
    exit;
}

if ($action === 'cancel') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) json_error('Keine gültige Anfrage.');

    // Nur eigene, noch nicht abgesagte/erledigte Anfrage
    $stmt = $db->prepare("SELECT a.id, a.datum, a.status, a.betreut_von_user_id, j.Vorname, j.Name
                            FROM jsk_betreuung_anfragen a
                            JOIN jungschuetzen j ON j.id = a.jungschuetze_id
                           WHERE a.id = ? AND a.jungschuetze_id = ?");
    $stmt->execute([$id, $jsId]);
    $anf = $stmt->fetch();
    if (!$anf) json_error('Anfrage nicht gefunden.', 404);
    if (in_array($anf['status'], ['abgesagt', 'erledigt'], true)) {
        json_error('Diese Anfrage kann nicht mehr abgesagt werden.');
    }

    $upd = $db->prepare("UPDATE jsk_betreuung_anfragen SET status = 'abgesagt' WHERE id = ? AND jungschuetze_id = ?");
    $upd->execute([$id, $jsId]);

    // War bereits vergeben -> betreuendes Mitglied informieren
    if ($anf['status'] === 'vergeben' && !empty($anf['betreut_von_user_id'])) {
        $datumDe = date('d.m.Y', strtotime($anf['datum']));
        $jsName = trim($anf['Vorname'] . ' ' . $anf['Name']);
        try {
            jskSendPush((int) $anf['betreut_von_user_id'], 'Termin abgesagt',
                $jsName . ' hat den Termin am ' . $datumDe . ' abgesagt.', 'portal/jsk_betreuung.php');
        } catch (Throwable $e) { /* Push best effort */ }
    }

    echo json_encode(['success' => true, 'message' => 'Anmeldung abgesagt.']);
    exit;
}

json_error('Unbekannte Aktion.');

// ---------------------------------------------------------------------------
// Push-Helfer (best effort – Fehler brechen die Aktion nie ab)
// ---------------------------------------------------------------------------
function jskSendPush(int $userId, string $titel, string $text, string $url): void {
    $helper = __DIR__ . '/../inc/push_helper.php';
    if (!file_exists($helper)) return;
    require_once $helper;
    if (function_exists('sendePushAnBenutzer')) {
        sendePushAnBenutzer($userId, $titel, $text, $url);
    }
}

function jskNotifyBetreuer(PDO $db, string $titel, string $text): void {
    try {
        $rows = $db->query(
            "SELECT u.id
               FROM users u
               JOIN benachrichtigung_prefs p ON p.user_id = u.id
              WHERE u.status = 'approved'
                AND u.role IN ('mitglied','vorstand','admin')
                AND p.jsk_betreuung = 1
                AND COALESCE(p.push_aktiv, 1) = 1"
        )->fetchAll();
        foreach ($rows as $r) {
            try { jskSendPush((int) $r['id'], $titel, $text, 'portal/jsk_betreuung.php'); }
            catch (Throwable $e) { /* einzelne Push-Fehler ignorieren */ }
        }
    } catch (Throwable $e) {
        error_log('jskNotifyBetreuer: ' . $e->getMessage());
    }
}
