<?php
// inc/jsk_verwaltung/anfragen.php – Betreuungs-Anfragen fuer die JSK-Verwaltung (Tab «Anfragen»).
//   GET  action=list                     -> kommende + vergangene Anfragen, Betreuer-Auswahl, Statistik, Leiter-Stand
//   POST action=assign  id, user_id      -> Betreuer manuell zuteilen / umteilen
//   POST action=release id               -> Betreuung freigeben (wieder offen)
//   POST action=cancel  id               -> Anfrage stornieren (abgesagt)
// JSON, PDO. Guard + CSRF gemaess CLAUDE.md (adminApiGuard / csrf_require).

require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../chat.inc.php';

header('Content-Type: application/json; charset=utf-8');
$db   = getDB();
$meId = (int) ($_SESSION['user_id'] ?? 0);

function anfOk(array $extra = []): void { echo json_encode(['success' => true] + $extra, JSON_UNESCAPED_UNICODE); exit; }
function anfErr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------- GET: list
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hatTermin  = jskDbHatSpalte($db, 'jsk_betreuung_anfragen', 'termin_id');
    $terminSel  = $hatTermin ? 'wt.name AS termin_name' : 'NULL AS termin_name';
    $terminJoin = $hatTermin ? 'LEFT JOIN wichtige_termine wt ON wt.ID = a.termin_id' : '';

    $rows = $db->query(
        "SELECT a.id, a.datum, a.zeit, a.bemerkung, a.status, a.betreut_von_user_id, a.betreut_am, a.erstellt_am,
                TRIM(CONCAT(j.Vorname, ' ', j.Name)) AS js_name, j.id AS jungschuetze_id,
                bu.full_name AS betreuer_name, $terminSel,
                (SELECT u.id FROM users u WHERE u.jungschuetze_id = j.id AND u.status = 'approved' LIMIT 1) AS js_user_id
           FROM jsk_betreuung_anfragen a
           JOIN jungschuetzen j ON j.id = a.jungschuetze_id
           LEFT JOIN users bu ON bu.id = a.betreut_von_user_id
           $terminJoin
          WHERE a.datum >= (CURDATE() - INTERVAL 120 DAY)
          ORDER BY (a.datum >= CURDATE()) DESC,
                   CASE WHEN a.datum >= CURDATE() THEN a.datum END ASC,
                   CASE WHEN a.datum <  CURDATE() THEN a.datum END DESC, a.id DESC"
    )->fetchAll();
    $anfragen = [];
    foreach ($rows as $r) {
        $anfragen[] = [
            'id'          => (int) $r['id'],
            'datum'       => $r['datum'],
            'datum_de'    => date('d.m.Y', strtotime($r['datum'])),
            'zeit'        => $r['zeit'],
            'bemerkung'   => $r['bemerkung'],
            'status'      => $r['status'],
            'js_name'     => $r['js_name'],
            'hat_login'   => !empty($r['js_user_id']),
            'betreuer_id' => $r['betreut_von_user_id'] !== null ? (int) $r['betreut_von_user_id'] : null,
            'betreuer'    => $r['betreuer_name'],
            'termin'      => $r['termin_name'],
            'vergangen'   => (strtotime($r['datum']) < strtotime('today')),
            'erstellt_de' => date('d.m.', strtotime($r['erstellt_am'])),
        ];
    }

    // Betreuer-Auswahl: alle freigegebenen Mitglieder-Logins, aktivierte zuerst
    $betreuer = [];
    foreach ($db->query(
        "SELECT u.id, u.full_name, COALESCE(p.jsk_betreuung, 0) AS aktiv
           FROM users u LEFT JOIN benachrichtigung_prefs p ON p.user_id = u.id
          WHERE u.status = 'approved' AND u.role IN ('mitglied','vorstand','admin')
          ORDER BY COALESCE(p.jsk_betreuung, 0) DESC, u.full_name ASC"
    )->fetchAll() as $b) {
        $betreuer[] = ['id' => (int) $b['id'], 'name' => (string) $b['full_name'], 'aktiv' => ((int) $b['aktiv'] === 1)];
    }

    // Statistik laufendes Jahr: Betreuungen pro Mitglied (vergeben + erledigt mit Betreuer)
    $stats = [];
    foreach ($db->query(
        "SELECT bu.full_name, COUNT(*) AS n,
                SUM(a.status = 'erledigt') AS erledigt, SUM(a.status = 'vergeben') AS offen
           FROM jsk_betreuung_anfragen a JOIN users bu ON bu.id = a.betreut_von_user_id
          WHERE YEAR(a.datum) = YEAR(CURDATE()) AND a.status IN ('vergeben','erledigt')
          GROUP BY bu.id, bu.full_name ORDER BY n DESC, bu.full_name ASC"
    )->fetchAll() as $s) {
        $stats[] = ['name' => (string) $s['full_name'], 'n' => (int) $s['n'], 'erledigt' => (int) $s['erledigt'], 'offen' => (int) $s['offen']];
    }
    $ohneBetreuer = (int) $db->query(
        "SELECT COUNT(*) FROM jsk_betreuung_anfragen
          WHERE YEAR(datum) = YEAR(CURDATE()) AND status = 'erledigt' AND betreut_von_user_id IS NULL"
    )->fetchColumn();

    // Leiter-Stand: Flag gesetzt, aber ohne (freigegebenes) Login = Chat/Eskalation laufen ins Leere
    $leiterMit = []; $leiterOhne = [];
    foreach ($db->query(
        "SELECT TRIM(CONCAT(m.Vorname, ' ', m.Name)) AS n, u.id AS uid, u.status
           FROM mitglieder m LEFT JOIN users u ON u.mitglied_id = m.ID
          WHERE m.ist_jsk_leiter = 1 ORDER BY m.Name, m.Vorname"
    )->fetchAll() as $l) {
        if (!empty($l['uid']) && $l['status'] === 'approved') $leiterMit[] = $l['n']; else $leiterOhne[] = $l['n'];
    }

    anfOk([
        'anfragen' => $anfragen, 'betreuer' => $betreuer, 'stats' => $stats, 'ohne_betreuer' => $ohneBetreuer,
        'leiter_mit' => $leiterMit, 'leiter_ohne' => $leiterOhne,
        'einsicht' => jskChatLeiterEinsichtAktiv($db),
        'aktive_betreuer' => count(jskBetreuerUserIds($db)),
    ]);
}

// ---------------------------------------------------------------- POST
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);
if ($id <= 0) anfErr('Keine gültige Anfrage.');

$vorher = jskAnfrageInfo($db, $id);
if (!$vorher) anfErr('Anfrage nicht gefunden.', 404);

$meinName = '';
try { $q = $db->prepare('SELECT full_name FROM users WHERE id = ?'); $q->execute([$meId]); $meinName = (string) ($q->fetchColumn() ?: ''); } catch (Throwable $e) {}
$vonLeitung = ' (durch ' . ($meinName !== '' ? $meinName : 'die Leitung') . ')';

if ($action === 'assign') {
    $uid = (int) ($_POST['user_id'] ?? 0);
    if ($uid <= 0) anfErr('Bitte einen Betreuer wählen.');
    $chk = $db->prepare("SELECT full_name FROM users WHERE id = ? AND status = 'approved' AND role IN ('mitglied','vorstand','admin')");
    $chk->execute([$uid]);
    $neuerName = (string) ($chk->fetchColumn() ?: '');
    if ($neuerName === '') anfErr('Betreuer nicht gefunden oder nicht freigegeben.', 404);
    if ($vorher['status'] === 'abgesagt' || $vorher['status'] === 'erledigt') anfErr('Diese Anfrage ist abgeschlossen.');
    if (strtotime($vorher['datum']) < strtotime('today')) anfErr('Der Termin liegt in der Vergangenheit.');

    $db->prepare("UPDATE jsk_betreuung_anfragen SET status = 'vergeben', betreut_von_user_id = ?, betreut_am = NOW() WHERE id = ?")
       ->execute([$uid, $id]);

    $alt = (int) ($vorher['betreut_von_user_id'] ?? 0);
    if ($vorher['js_user_id'] > 0) {
        chatEnsureMatchConversation($db, $vorher['js_user_id'], $uid);
        jskBenachrichtigen($vorher['js_user_id'], 'Betreuer zugeteilt',
            $neuerName . ' betreut dich am ' . $vorher['datum_de'] . $vonLeitung . '.', 'portal/jsk_dashboard.php', 'jsk_betreuung', 'jsk-anfrage-' . $id);
    }
    jskBenachrichtigen($uid, 'Betreuung zugeteilt',
        'Dir wurde die Betreuung von ' . $vorher['js_name'] . ' am ' . $vorher['datum_de'] . ' zugeteilt' . $vonLeitung . '.',
        'portal/jsk_betreuung.php', 'jsk_betreuung', 'jsk-anfrage-' . $id);
    if ($alt > 0 && $alt !== $uid) {
        jskBenachrichtigen($alt, 'Betreuung umgeteilt',
            'Die Betreuung von ' . $vorher['js_name'] . ' am ' . $vorher['datum_de'] . ' übernimmt neu ' . $neuerName . $vonLeitung . '.',
            'portal/jsk_betreuung.php', 'jsk_betreuung', 'jsk-anfrage-' . $id);
    }
    anfOk(['message' => $neuerName . ' zugeteilt – alle Beteiligten wurden informiert.']);
}

if ($action === 'release') {
    if ($vorher['status'] !== 'vergeben') anfErr('Diese Anfrage ist nicht vergeben.');
    $db->prepare("UPDATE jsk_betreuung_anfragen SET status = 'offen', betreut_von_user_id = NULL, betreut_am = NULL WHERE id = ?")->execute([$id]);
    $alt = (int) ($vorher['betreut_von_user_id'] ?? 0);
    if ($alt > 0) {
        jskBenachrichtigen($alt, 'Betreuung freigegeben',
            'Die Betreuung von ' . $vorher['js_name'] . ' am ' . $vorher['datum_de'] . ' wurde freigegeben' . $vonLeitung . '.',
            'portal/jsk_betreuung.php', 'jsk_betreuung', 'jsk-anfrage-' . $id);
    }
    if ($vorher['js_user_id'] > 0) {
        jskBenachrichtigen($vorher['js_user_id'], 'Betreuung wieder offen',
            'Dein Termin am ' . $vorher['datum_de'] . ' sucht wieder eine Begleitung' . $vonLeitung . '.', 'portal/jsk_dashboard.php', 'jsk_betreuung', 'jsk-anfrage-' . $id);
    }
    jskBenachrichtigeBetreuer($db, 'Jungschütze sucht wieder Begleitung',
        $vorher['js_name'] . ' braucht am ' . $vorher['datum_de'] . ' erneut eine Begleitung.', 'portal/jsk_betreuung.php', $alt, 'jsk-anfrage-' . $id);
    anfOk(['message' => 'Freigegeben – Betreuer wurden informiert.']);
}

if ($action === 'cancel') {
    if (in_array($vorher['status'], ['abgesagt', 'erledigt'], true)) anfErr('Diese Anfrage ist bereits abgeschlossen.');
    $db->prepare("UPDATE jsk_betreuung_anfragen SET status = 'abgesagt' WHERE id = ?")->execute([$id]);
    $alt = (int) ($vorher['betreut_von_user_id'] ?? 0);
    if ($vorher['js_user_id'] > 0) {
        jskBenachrichtigen($vorher['js_user_id'], 'Termin storniert',
            'Dein Schiesstermin am ' . $vorher['datum_de'] . ' wurde storniert' . $vonLeitung . '.', 'portal/jsk_dashboard.php', 'jsk_betreuung', 'jsk-anfrage-' . $id);
    }
    if ($alt > 0) {
        jskBenachrichtigen($alt, 'Termin storniert',
            'Der Termin mit ' . $vorher['js_name'] . ' am ' . $vorher['datum_de'] . ' wurde storniert' . $vonLeitung . '.', 'portal/jsk_betreuung.php', 'jsk_betreuung', 'jsk-anfrage-' . $id);
    }
    anfOk(['message' => 'Anfrage storniert.']);
}

anfErr('Unbekannte Aktion.');
