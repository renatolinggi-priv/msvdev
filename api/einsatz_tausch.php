<?php
// api/einsatz_tausch.php - Einsatz-Tausch / Übernahme zwischen Mitgliedern
//
//   GET  ?action=partner_kandidaten              -> Mitglieder mit Portal-Konto (mögliche Übernehmer)
//   GET  ?action=partner_einsaetze&mitglied_id=X -> kommende Einsätze eines Mitglieds (gekoppelter Tausch)
//   POST action=create   -> A meldet Übernahme/Tausch (status 'offen') + Push an B
//   POST action=accept   -> B akzeptiert -> Zuordnung umschreiben + Push an A + Vorstand
//   POST action=decline  -> B lehnt ab -> Push an A
//   POST action=withdraw -> A zieht offenen Antrag zurück
//
// Ablauf: Bestätigung läuft zwischen A und B; der Vorstand wird nach dem
// Akzeptieren nur informiert (kein Gating). Siehe Migration 034.

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand', 'mitglied']);

$db  = getDB();
$me  = (int) (getMitgliedId() ?? 0);
$uid = (int) ($_SESSION['user_id'] ?? 0);

if ($me <= 0) {
    json_error('Dein Konto ist keinem Vereinsmitglied zugeordnet.', 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ===== GET: lesende Aktionen =================================================
if ($method === 'GET') {
    if ($action === 'partner_kandidaten') {
        // Nur Mitglieder mit freigegebenem Portal-Konto (sonst können sie nicht akzeptieren).
        $stmt = $db->prepare(
            "SELECT m.ID AS id, m.Name AS name, m.Vorname AS vorname
               FROM mitglieder m
               JOIN users u ON u.mitglied_id = m.ID AND u.status = 'approved'
              WHERE m.ID <> :me AND m.Status = 1 AND m.Verstorben = 0
              ORDER BY m.Name, m.Vorname"
        );
        $stmt->execute([':me' => $me]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'partner_einsaetze') {
        $b = (int) ($_GET['mitglied_id'] ?? 0);
        if ($b <= 0) json_error('Kein Mitglied angegeben.');
        $stmt = $db->prepare(
            "SELECT id, bezeichnung, event_datum, event_zeit, funktion
               FROM einsatz_zuweisungen
              WHERE mitglied_id = :b AND event_datum >= CURDATE()
              ORDER BY event_datum, bezeichnung"
        );
        $stmt->execute([':b' => $b]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    json_error('Unbekannte Aktion.');
}

// ===== ab hier: POST (verändernd) ============================================
if ($method !== 'POST') json_error('Methode nicht erlaubt.', 405);
if (!validateCsrfRequest()) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
}

// ---- create -----------------------------------------------------------------
if ($action === 'create') {
    $typ        = (($_POST['typ'] ?? 'uebernahme') === 'tausch') ? 'tausch' : 'uebernahme';
    $einsatzA   = (int) ($_POST['einsatz_a_id'] ?? 0);
    $anMitglied = (int) ($_POST['an_mitglied_id'] ?? 0);
    $einsatzB   = (int) ($_POST['einsatz_b_id'] ?? 0);
    $nachricht  = trim((string) ($_POST['nachricht'] ?? ''));
    if (mb_strlen($nachricht) > 500) $nachricht = mb_substr($nachricht, 0, 500);

    if ($einsatzA <= 0 || $anMitglied <= 0) json_error('Unvollständige Angaben.');
    if ($anMitglied === $me)                json_error('Du kannst nicht mit dir selbst tauschen.');

    // Einsatz A muss aktuell mir gehören und in der Zukunft liegen
    $a = ezGet($db, $einsatzA);
    if (!$a || (int) $a['mitglied_id'] !== $me) json_error('Dieser Einsatz gehört dir nicht (mehr).');
    if ($a['event_datum'] < date('Y-m-d'))      json_error('Dieser Einsatz liegt in der Vergangenheit.');

    // B muss ein freigegebenes Portal-Konto haben
    $bUserId = userIdVonMitglied($db, $anMitglied);
    if ($bUserId <= 0) json_error('Das gewählte Mitglied hat kein aktives Portal-Konto.');

    if ($typ === 'tausch') {
        if ($einsatzB <= 0) json_error('Bitte einen Gegen-Einsatz wählen.');
        $bEz = ezGet($db, $einsatzB);
        if (!$bEz || (int) $bEz['mitglied_id'] !== $anMitglied) json_error('Der gewählte Gegen-Einsatz gehört diesem Mitglied nicht.');
        if ($bEz['event_datum'] < date('Y-m-d'))                json_error('Der Gegen-Einsatz liegt in der Vergangenheit.');
    } else {
        $einsatzB = 0;
    }

    // Kein offener Antrag auf einen der beteiligten Einsätze
    $conflictIds = [$einsatzA];
    if ($einsatzB > 0) $conflictIds[] = $einsatzB;
    $ph = implode(',', array_fill(0, count($conflictIds), '?'));
    $chk = $db->prepare("SELECT id FROM einsatz_tausch WHERE status='offen' AND (einsatz_a_id IN ($ph) OR einsatz_b_id IN ($ph)) LIMIT 1");
    $chk->execute(array_merge($conflictIds, $conflictIds));
    if ($chk->fetchColumn()) json_error('Für einen der gewählten Einsätze besteht bereits eine offene Anfrage.');

    $ins = $db->prepare(
        "INSERT INTO einsatz_tausch (typ, einsatz_a_id, einsatz_b_id, von_mitglied_id, an_mitglied_id, nachricht, status, erstellt_von)
         VALUES (:typ, :a, :b, :von, :an, :nachricht, 'offen', :uid)"
    );
    $ins->execute([
        ':typ'       => $typ,
        ':a'         => $einsatzA,
        ':b'         => ($einsatzB > 0 ? $einsatzB : null),
        ':von'       => $me,
        ':an'        => $anMitglied,
        ':nachricht' => ($nachricht !== '' ? $nachricht : null),
        ':uid'       => $uid,
    ]);

    // Push an B (Übernehmer / Tauschpartner)
    $meName = nameVornameLesbar($db, $me);
    $aLabel = $a['bezeichnung'] . ' am ' . fmtDatumDe($a['event_datum']);
    if ($typ === 'tausch') {
        $titel = 'Tausch-Anfrage';
        $text  = $meName . ' möchte einen Einsatz mit dir tauschen (' . $aLabel . ').';
    } else {
        $titel = 'Einsatz-Übernahme angefragt';
        $text  = $meName . ' bittet dich, einen Einsatz zu übernehmen (' . $aLabel . ').';
    }
    tauschPush($db, $bUserId, $titel, $text, 'portal/meine_einsaetze.php');

    echo json_encode(['success' => true, 'message' => 'Anfrage gesendet.']);
    exit;
}

// ---- accept -----------------------------------------------------------------
if ($action === 'accept') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) json_error('Keine Anfrage angegeben.');

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT * FROM einsatz_tausch WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $t = $stmt->fetch();
        if (!$t)                                throw new RuntimeException('Anfrage nicht gefunden.');
        if ((int) $t['an_mitglied_id'] !== $me) throw new RuntimeException('Diese Anfrage ist nicht an dich gerichtet.');
        if ($t['status'] !== 'offen')           throw new RuntimeException('Diese Anfrage ist nicht mehr offen.');

        $von = (int) $t['von_mitglied_id'];                                 // A
        $an  = (int) $t['an_mitglied_id'];                                  // B (= me)
        $aId = (int) $t['einsatz_a_id'];
        $bId = $t['einsatz_b_id'] !== null ? (int) $t['einsatz_b_id'] : 0;

        // Einsatz A sperren + Eigentum erneut prüfen
        $ezA = ezLock($db, $aId);
        if (!$ezA || (int) $ezA['mitglied_id'] !== $von) {
            throw new RuntimeException('Der Einsatz wurde zwischenzeitlich geändert. Bitte neu anfragen.');
        }
        $ezB = null;
        if ($t['typ'] === 'tausch') {
            if ($bId <= 0) throw new RuntimeException('Gegen-Einsatz fehlt.');
            $ezB = ezLock($db, $bId);
            if (!$ezB || (int) $ezB['mitglied_id'] !== $an) {
                throw new RuntimeException('Dein Gegen-Einsatz wurde zwischenzeitlich geändert. Bitte neu anfragen.');
            }
        }

        // Umschreiben: A -> B
        $updEz = $db->prepare("UPDATE einsatz_zuweisungen SET mitglied_id = :mid, mitglied_name = :nm WHERE id = :id");
        $updEz->execute([':mid' => $an, ':nm' => nameVornameDb($db, $an), ':id' => $aId]);
        // bei Tausch: B -> A
        if ($ezB) {
            $updEz2 = $db->prepare("UPDATE einsatz_zuweisungen SET mitglied_id = :mid, mitglied_name = :nm WHERE id = :id");
            $updEz2->execute([':mid' => $von, ':nm' => nameVornameDb($db, $von), ':id' => $bId]);
        }

        // Antrag bestätigen
        $db->prepare("UPDATE einsatz_tausch SET status='bestaetigt', entschieden_von=:uid, entschieden_am=NOW() WHERE id=:id")
           ->execute([':uid' => $uid, ':id' => $id]);

        // Konkurrierende offene Anträge auf dieselben Einsätze entwerten
        $ids = [$aId];
        if ($bId > 0) $ids[] = $bId;
        $place  = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$id], $ids, $ids);
        $db->prepare(
            "UPDATE einsatz_tausch SET status='zurueckgezogen'
              WHERE status='offen' AND id <> ? AND (einsatz_a_id IN ($place) OR einsatz_b_id IN ($place))"
        )->execute($params);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        json_error($e->getMessage(), 409);
    }

    // === Benachrichtigungen (best effort, ausserhalb der Transaktion) =========
    $aUserId   = userIdVonMitglied($db, $von);
    $aName     = nameVornameLesbar($db, $von);
    $bName     = nameVornameLesbar($db, $an);
    $istTausch = ($ezB !== null);
    $aLabel    = $ezA['bezeichnung'] . ' am ' . fmtDatumDe($ezA['event_datum']);
    $bLabel    = $istTausch ? ($ezB['bezeichnung'] . ' am ' . fmtDatumDe($ezB['event_datum'])) : '';

    // an A: persönliche Bestätigung
    if ($aUserId > 0) {
        $text = $istTausch
            ? $bName . ' hat den Tausch bestätigt: ' . $aLabel . ' ↔ ' . $bLabel . '.'
            : $bName . ' übernimmt deinen Einsatz: ' . $aLabel . '.';
        tauschPush($db, $aUserId, 'Tausch bestätigt', $text, 'portal/meine_einsaetze.php');
    }

    // an Vorstand/Admin: Information (A und B nicht doppelt benachrichtigen)
    $vText = $istTausch
        ? 'Tausch: ' . $aName . ' ↔ ' . $bName . ' – ' . $aLabel . ' / ' . $bLabel . '.'
        : 'Übernahme: ' . $aName . ' → ' . $bName . ' – ' . $aLabel . '.';
    foreach (vorstandUserIds($db) as $vid) {
        if ($vid === $uid || $vid === $aUserId) continue;
        tauschPush($db, $vid, 'Einsatz-Tausch', $vText, 'portal/einsatzplaene.php');
    }

    echo json_encode(['success' => true, 'message' => $istTausch ? 'Tausch bestätigt.' : 'Übernahme bestätigt.']);
    exit;
}

// ---- decline ----------------------------------------------------------------
if ($action === 'decline') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) json_error('Keine Anfrage angegeben.');

    $stmt = $db->prepare("SELECT * FROM einsatz_tausch WHERE id = ?");
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    if (!$t)                                json_error('Anfrage nicht gefunden.', 404);
    if ((int) $t['an_mitglied_id'] !== $me) json_error('Diese Anfrage ist nicht an dich gerichtet.', 403);

    $upd = $db->prepare("UPDATE einsatz_tausch SET status='abgelehnt', entschieden_von=?, entschieden_am=NOW() WHERE id=? AND status='offen'");
    $upd->execute([$uid, $id]);
    if ($upd->rowCount() === 0) json_error('Diese Anfrage ist nicht mehr offen.', 409);

    // Push an A
    $aUserId = userIdVonMitglied($db, (int) $t['von_mitglied_id']);
    if ($aUserId > 0) {
        $ez    = ezGet($db, (int) $t['einsatz_a_id']);
        $label = $ez ? ($ez['bezeichnung'] . ' am ' . fmtDatumDe($ez['event_datum'])) : 'deinen Einsatz';
        tauschPush($db, $aUserId, 'Tausch abgelehnt',
            nameVornameLesbar($db, $me) . ' hat deine Anfrage (' . $label . ') abgelehnt.',
            'portal/meine_einsaetze.php');
    }

    echo json_encode(['success' => true, 'message' => 'Anfrage abgelehnt.']);
    exit;
}

// ---- withdraw ---------------------------------------------------------------
if ($action === 'withdraw') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) json_error('Keine Anfrage angegeben.');

    // Daten vorab lesen (für Push an B), dann nur eigene offene Anfrage zurückziehen
    $stmt = $db->prepare("SELECT an_mitglied_id FROM einsatz_tausch WHERE id = ? AND von_mitglied_id = ? AND status = 'offen'");
    $stmt->execute([$id, $me]);
    $t = $stmt->fetch();

    $upd = $db->prepare("UPDATE einsatz_tausch SET status='zurueckgezogen' WHERE id=? AND von_mitglied_id=? AND status='offen'");
    $upd->execute([$id, $me]);
    if ($upd->rowCount() === 0) json_error('Diese Anfrage kann nicht zurückgezogen werden.', 409);

    if ($t) {
        $bUserId = userIdVonMitglied($db, (int) $t['an_mitglied_id']);
        if ($bUserId > 0) {
            tauschPush($db, $bUserId, 'Anfrage zurückgezogen',
                nameVornameLesbar($db, $me) . ' hat eine Tausch-Anfrage zurückgezogen.',
                'portal/meine_einsaetze.php');
        }
    }

    echo json_encode(['success' => true, 'message' => 'Anfrage zurückgezogen.']);
    exit;
}

json_error('Unbekannte Aktion.');

// ===== Helfer ================================================================

/** Einsatz-Zeile (ohne Lock). */
function ezGet(PDO $db, int $id): ?array {
    $stmt = $db->prepare("SELECT id, mitglied_id, bezeichnung, event_datum, event_zeit, funktion FROM einsatz_zuweisungen WHERE id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

/** Einsatz-Zeile mit Row-Lock (nur innerhalb einer Transaktion verwenden). */
function ezLock(PDO $db, int $id): ?array {
    $stmt = $db->prepare("SELECT id, mitglied_id, bezeichnung, event_datum, event_zeit, funktion FROM einsatz_zuweisungen WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

/** Freigegebene users.id zu einer mitglieder.ID (oder 0). */
function userIdVonMitglied(PDO $db, int $mid): int {
    if ($mid <= 0) return 0;
    $stmt = $db->prepare("SELECT id FROM users WHERE mitglied_id = ? AND status = 'approved' ORDER BY id LIMIT 1");
    $stmt->execute([$mid]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

/** "Vorname Name" – natürlich lesbar (für Push-Texte). */
function nameVornameLesbar(PDO $db, int $mid): string {
    $stmt = $db->prepare("SELECT Vorname, Name FROM mitglieder WHERE ID = ?");
    $stmt->execute([$mid]);
    $r = $stmt->fetch();
    return $r ? trim(($r['Vorname'] ?? '') . ' ' . ($r['Name'] ?? '')) : '';
}

/** "Name Vorname" – wie in der Einsatz-Verwaltung gespeichert/angezeigt. */
function nameVornameDb(PDO $db, int $mid): string {
    $stmt = $db->prepare("SELECT Vorname, Name FROM mitglieder WHERE ID = ?");
    $stmt->execute([$mid]);
    $r = $stmt->fetch();
    return $r ? trim(($r['Name'] ?? '') . ' ' . ($r['Vorname'] ?? '')) : '';
}

/** users.id aller freigegebenen Vorstands-/Admin-Konten. */
function vorstandUserIds(PDO $db): array {
    $rows = $db->query("SELECT id FROM users WHERE status = 'approved' AND role IN ('vorstand','admin')")->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', $rows);
}

/** Datum DD.MM.YYYY. */
function fmtDatumDe(string $d): string {
    $ts = strtotime($d);
    return $ts ? date('d.m.Y', $ts) : $d;
}

/**
 * Best-effort Push (bricht die Aktion nie ab). Respektiert
 * benachrichtigung_prefs.einsatz_tausch (Default 1) + push_aktiv (Default 1).
 */
function tauschPush(PDO $db, int $userId, string $titel, string $text, string $url): void {
    if ($userId <= 0) return;
    try {
        $st = $db->prepare("SELECT COALESCE(einsatz_tausch,1) AS et, COALESCE(push_aktiv,1) AS pa FROM benachrichtigung_prefs WHERE user_id = ?");
        $st->execute([$userId]);
        $p = $st->fetch();
        if ($p && ((int) $p['et'] !== 1 || (int) $p['pa'] !== 1)) return;

        $helper = __DIR__ . '/../inc/push_helper.php';
        if (!file_exists($helper)) return;
        require_once $helper;
        if (function_exists('benachrichtigungZustellen')) {
            benachrichtigungZustellen($userId, $titel, $text, $url, 'einsatz_tausch');
        }
    } catch (Throwable $e) {
        error_log('tauschPush: ' . $e->getMessage());
    }
}
