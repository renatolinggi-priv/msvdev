<?php
/**
 * cron/check_benachrichtigungen.php - taeglicher Reminder-Lauf (Web Push)
 * Vorlage: benachrichtigungs-konzept.md (Abschnitt 4)
 *
 * Findet faellige Objekte je Thema (Lead-Time-Fenster), bestimmt die Empfaenger,
 * schickt pro Empfaenger genau einen Push und setzt den Idempotenz-Marker NUR bei
 * Erfolg (Retry-by-default bei transienten Fehlern).
 *
 * Aufruf:
 *   CLI : php cron/check_benachrichtigungen.php
 *   HTTP: .../cron/check_benachrichtigungen.php?key=<cron_trigger_key>
 *
 * Intervall: taeglich (z.B. 08:00). Lead-Times sind in der settings-Tabelle
 * konfigurierbar (push_lead_einsaetze / _jm / _umfragen / _termine).
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/push_helper.php'; // getDB(), sendePushAnBenutzer(), pushGetSetting(), pushLeadTime()

$cli = (PHP_SAPI === 'cli');

// --- Absicherung: HTTP nur mit geheimem Token (timing-sicher) ---------------
if (!$cli) {
    header('Content-Type: application/json; charset=utf-8');
    $erwartet = (string) (pushGetSetting('cron_trigger_key') ?? '');
    $gesendet = (string) ($_GET['key'] ?? '');
    if ($erwartet === '' || !hash_equals($erwartet, $gesendet)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

$db = getDB();

// =====================  Hilfsfunktionen  ====================================

// Maximales Lade-Fenster (Tage). Deckt die groesste erlaubte Vorlaufzeit ab;
// die tatsaechliche Filterung erfolgt pro Benutzer ueber dessen lead-Wert.
const PUSH_MAX_WINDOW = 30;

/** Approved Benutzer mit aktivem Haupt- und Themen-Schalter (fehlende Zeile = an).
 *  'lead' = persoenliche Vorlaufzeit in Tagen (null = globaler Standard verwenden). */
function eligibleUsers(PDO $db, string $topicCol): array {
    // $topicCol stammt aus festem Whitelist-Set -> Interpolation unkritisch.
    // push_aktiv steuert nur den Push-Versand (in benachrichtigungZustellen), NICHT die
    // In-App-Glocke -> hier bewusst kein push_aktiv-Filter.
    $sql = "SELECT u.id, u.role, u.mitglied_id, p.lead_tage
            FROM users u
            LEFT JOIN benachrichtigung_prefs p ON p.user_id = u.id
            WHERE u.status = 'approved'
              AND COALESCE(p.$topicCol, 1) = 1";
    $out = [];
    foreach ($db->query($sql) as $r) {
        $out[(int) $r['id']] = [
            'role'        => $r['role'],
            'mitglied_id' => $r['mitglied_id'] !== null ? (int) $r['mitglied_id'] : null,
            'lead'        => $r['lead_tage'] !== null ? (int) $r['lead_tage'] : null,
        ];
    }
    return $out;
}

function hatGeraete(PDO $db, int $userId): bool {
    $s = $db->prepare('SELECT 1 FROM push_abos WHERE benutzer_id = ? LIMIT 1');
    $s->execute([$userId]);
    return (bool) $s->fetchColumn();
}

function bereitsGemeldet(PDO $db, int $userId, string $refTyp, int $refId, string $termin): bool {
    $s = $db->prepare('SELECT 1 FROM benachrichtigung_log
                       WHERE benutzer_id = ? AND ref_typ = ? AND ref_id = ? AND termin_datum = ? LIMIT 1');
    $s->execute([$userId, $refTyp, $refId, $termin]);
    return (bool) $s->fetchColumn();
}

function markiere(PDO $db, int $userId, string $kategorie, string $refTyp, int $refId, string $termin): void {
    $s = $db->prepare('INSERT IGNORE INTO benachrichtigung_log
                       (benutzer_id, kategorie, ref_typ, ref_id, termin_datum) VALUES (?,?,?,?,?)');
    $s->execute([$userId, $kategorie, $refTyp, $refId, $termin]);
}

/**
 * Stellt einem Benutzer ein Vorkommnis zu (idempotent).
 * In-App-Eintrag (Glocke) entsteht IMMER, Push nur bei push_aktiv (benachrichtigungZustellen).
 * Der Marker wird NACH der Zustellung gesetzt -> verhindert doppelte Inbox-Eintraege beim
 * naechsten Lauf. Nur bei hartem Fehler kein Marker (-> Retry).
 */
function zustellen(PDO $db, int $userId, string $kategorie, string $refTyp, int $refId,
                   string $termin, string $titel, string $text, string $url, array &$stats,
                   string $tag = ''): void {
    if (bereitsGemeldet($db, $userId, $refTyp, $refId, $termin)) { $stats['skipped']++; return; }

    try {
        $n = benachrichtigungZustellen($userId, $titel, $text, $url, $kategorie, $tag);
    } catch (\Throwable $e) {
        error_log('cron benachrichtigungen: Sendefehler user ' . $userId . ': ' . $e->getMessage());
        $stats['failed']++;
        return; // kein Marker -> Retry
    }

    markiere($db, $userId, $kategorie, $refTyp, $refId, $termin);
    if ($n > 0) { $stats['sent']++; } else { $stats['no_device']++; } // no_device = nur In-App (kein Push)
}

function fmtDatum(?string $ymd): string {
    $ymd = (string) $ymd;
    $t = DateTime::createFromFormat('Y-m-d', substr($ymd, 0, 10));
    return $t ? $t->format('d.m.Y') : $ymd;
}
function fmtZeit(?string $z): string {
    $z = trim((string) $z);
    if ($z === '') return '';
    if (preg_match('/^(\d{2}):(\d{2}):\d{2}$/', $z, $m)) return $m[1] . ':' . $m[2]; // TIME -> HH:MM
    return $z;
}

/**
 * Fasst alle Vorkommnisse EINES Tages zu einer Sammelmeldung zusammen.
 *
 * Gleiche Bezeichnung mit mehreren Startzeiten wird zu einer Zeile gebuendelt
 * ("SV Sattel (08:00, 13:30)") - damit erzeugt ein Anlass mit Vormittags- und
 * Nachmittagsdurchgang nur noch eine Meldung statt zwei. Bei genau einem Anlass
 * bleibt der bisherige Wortlaut erhalten.
 *
 * @param array   $items    Liste aus ['bez' => Bezeichnung, 'zeit' => 'HH:MM' oder '']
 * @param ?string $themaMz  Titel-Variante fuer die Sammelmeldung (null = $thema)
 * @param string  $wortMz   Zaehlwort im Text ("4 Anlaesse: ...")
 * @return array{0:string,1:string,2:int} Titel, Text, Idempotenz-Schluessel
 *         (= Anzahl gebuendelter Anlaesse; aendert sich, wenn ein Anlass nachtraeglich
 *         dazukommt -> dann wird der Tag genau einmal erneut gemeldet)
 */
function buendeln(string $thema, array $items, string $datum, ?string $themaMz = null, string $wortMz = 'Anlässe'): array {
    $gruppen = []; // Bezeichnung -> Startzeiten (Eingangsreihenfolge, ohne Duplikate)
    foreach ($items as $it) {
        $bez = trim((string) $it['bez']);
        if (!isset($gruppen[$bez])) $gruppen[$bez] = [];
        $zeit = (string) $it['zeit'];
        if ($zeit !== '' && !in_array($zeit, $gruppen[$bez], true)) $gruppen[$bez][] = $zeit;
    }
    $anzahl = count($gruppen);

    // Einzelner Anlass -> gewohnter Wortlaut (mehrere Zeiten mit " / " verbunden)
    if ($anzahl === 1) {
        $bez    = (string) array_key_first($gruppen);
        $zeiten = $gruppen[$bez];
        $text   = $bez . ' am ' . fmtDatum($datum);
        if ($zeiten) $text .= ' um ' . implode(' / ', $zeiten) . ' Uhr';
        return [$thema, $text, 1];
    }

    $teile = [];
    foreach ($gruppen as $bez => $zeiten) {
        $teile[] = $bez . ($zeiten ? ' (' . implode(', ', $zeiten) . ')' : '');
    }
    return [($themaMz ?? $thema) . ' – ' . fmtDatum($datum),
            $anzahl . ' ' . $wortMz . ': ' . implode(' · ', $teile),
            $anzahl];
}

$stats = ['sent' => 0, 'skipped' => 0, 'no_device' => 0, 'failed' => 0];
$details = [];

// =====================  1. Einsaetze (personalisiert)  =======================
try {
    $def = (int) pushLeadTime('push_lead_einsaetze', 3); // Standard, falls User keinen eigenen Wert hat
    $win = PUSH_MAX_WINDOW;
    // Pro Zeile gilt die persoenliche Vorlaufzeit (COALESCE lead_tage, Standard) -> HAVING-Filter.
    $sql = "SELECT ez.id, ez.bezeichnung, ez.funktion, ez.event_datum, ez.event_zeit, u.id AS user_id,
                   DATEDIFF(ez.event_datum, CURDATE()) AS tage_bis,
                   COALESCE(p.lead_tage, $def) AS lead
            FROM einsatz_zuweisungen ez
            JOIN users u ON u.mitglied_id = ez.mitglied_id AND u.status = 'approved'
            LEFT JOIN benachrichtigung_prefs p ON p.user_id = u.id
            WHERE ez.event_datum BETWEEN CURDATE() AND (CURDATE() + INTERVAL $win DAY)
              AND COALESCE(p.einsaetze, 1) = 1
            HAVING tage_bis <= lead";
    $n0 = $stats['sent'];
    foreach ($db->query($sql)->fetchAll() as $r) {
        $datum = (string) $r['event_datum'];
        $zeit  = fmtZeit($r['event_zeit']);
        $text  = $r['bezeichnung'] . ' am ' . fmtDatum($datum) . ($zeit !== '' ? ' (' . $zeit . ')' : '');
        if (!empty($r['funktion'])) $text .= ' – ' . $r['funktion'];
        zustellen($db, (int) $r['user_id'], 'einsaetze', 'einsatz', (int) $r['id'], $datum,
                  'Einsatz-Erinnerung', $text, 'portal/meine_einsaetze.php', $stats);
    }
    $details['einsaetze'] = $stats['sent'] - $n0;
} catch (\Throwable $e) {
    error_log('cron benachrichtigungen [einsaetze]: ' . $e->getMessage());
}

// ============  2. Jahresmeisterschaft (broadcast, pro Tag gebuendelt)  =======
// Ein Schiessanlass hat pro Startzeit eine eigene JMSchiesstage-Zeile, und mehrere
// Anlaesse koennen auf denselben Tag fallen. Darum wird NICHT je Zeile zugestellt,
// sondern erst gesammelt (je Benutzer und Tag) und dann eine Meldung verschickt.
try {
    $def = (int) pushLeadTime('push_lead_jm', 2);
    $win = PUSH_MAX_WINDOW;
    $users = eligibleUsers($db, 'jm');
    if ($users) {
        $sql = "SELECT js.ID AS id, js.schiesstag, js.start_time, jd.Bezeichnung,
                       DATEDIFF(js.schiesstag, CURDATE()) AS tage_bis
                FROM JMSchiesstage js
                JOIN JMDefinition jd ON jd.ID = js.jm_id
                WHERE js.schiesstag BETWEEN CURDATE() AND (CURDATE() + INTERVAL $win DAY)
                ORDER BY js.schiesstag, jd.Bezeichnung, js.start_time";
        $n0 = $stats['sent'];
        // Puffer [user][datum][] - je Benutzer, weil die Vorlaufzeit persoenlich ist
        // und jeder Benutzer daher eine andere Teilmenge der Tage sieht.
        $buf = [];
        foreach ($db->query($sql)->fetchAll() as $r) {
            $datum   = (string) $r['schiesstag'];
            $tageBis = (int) $r['tage_bis'];
            $item    = ['bez' => (string) $r['Bezeichnung'], 'zeit' => fmtZeit($r['start_time'])];
            foreach ($users as $uid => $info) {
                if ($tageBis > ($info['lead'] ?? $def)) continue; // ausserhalb persoenlicher Vorlaufzeit
                $buf[$uid][$datum][] = $item;
            }
        }
        foreach ($buf as $uid => $tage) {
            foreach ($tage as $datum => $items) {
                [$titel, $text, $key] = buendeln('Jahresmeisterschaft', $items, (string) $datum);
                zustellen($db, (int) $uid, 'jm', 'jm_tag', $key, (string) $datum,
                          $titel, $text, 'portal/meine_jm.php', $stats, 'jm-' . $datum);
            }
        }
        $details['jm'] = $stats['sent'] - $n0;
    }
} catch (\Throwable $e) {
    error_log('cron benachrichtigungen [jm]: ' . $e->getMessage());
}

// =====================  3. Umfrage-Fristen (broadcast)  ======================
try {
    $def = (int) pushLeadTime('push_lead_umfragen', 3);
    $win = PUSH_MAX_WINDOW;
    $users = eligibleUsers($db, 'umfragen');
    if ($users) {
        $sql = "SELECT id, titel, gueltig_bis, zielgruppe,
                       DATEDIFF(gueltig_bis, CURDATE()) AS tage_bis
                FROM umfragen
                WHERE status = 'aktiv' AND gueltig_bis IS NOT NULL
                  AND gueltig_bis BETWEEN CURDATE() AND (CURDATE() + INTERVAL $win DAY)";
        $n0 = $stats['sent'];
        $ansStmt = $db->prepare('SELECT DISTINCT mitglied_id FROM umfragen_antworten WHERE umfrage_id = ?');
        foreach ($db->query($sql)->fetchAll() as $r) {
            $umfrageId   = (int) $r['id'];
            $datum       = (string) $r['gueltig_bis'];
            $nurVorstand = ($r['zielgruppe'] === 'vorstand');
            $tageBis     = (int) $r['tage_bis'];
            $text = 'Bitte bis ' . fmtDatum($datum) . ' beantworten: ' . $r['titel'];

            // Wer hat bereits geantwortet?
            $ansStmt->execute([$umfrageId]);
            $beantwortet = array_map('intval', $ansStmt->fetchAll(PDO::FETCH_COLUMN));
            $beantwortet = array_flip($beantwortet);

            foreach ($users as $uid => $info) {
                if ($nurVorstand && !in_array($info['role'], ['vorstand', 'admin'], true)) continue;
                if ($info['mitglied_id'] !== null && isset($beantwortet[$info['mitglied_id']])) continue; // schon beantwortet
                if ($tageBis > ($info['lead'] ?? $def)) continue; // ausserhalb persoenlicher Vorlaufzeit
                zustellen($db, $uid, 'umfragen', 'umfrage', $umfrageId, $datum,
                          'Umfrage', $text, 'portal/mein_fragebogen.php', $stats);
            }
        }
        $details['umfragen'] = $stats['sent'] - $n0;
    }
} catch (\Throwable $e) {
    error_log('cron benachrichtigungen [umfragen]: ' . $e->getMessage());
}

// =====================  4. Vereinstermine & Training (broadcast)  ============
try {
    $def   = (int) pushLeadTime('push_lead_termine', 2);
    $win   = PUSH_MAX_WINDOW;
    $users = eligibleUsers($db, 'termine');
    if ($users) {
        $n0 = $stats['sent'];
        // 4a) Standbelegung (nur Kalender-relevante)
        $sql1 = "SELECT ID AS id, Datum AS datum, StartZeit AS zeit, Bezeichnung,
                        DATEDIFF(Datum, CURDATE()) AS tage_bis
                 FROM Standbelegung
                 WHERE InKalender = 1 AND Datum BETWEEN CURDATE() AND (CURDATE() + INTERVAL $win DAY)";
        // 4b) wichtige_termine (Training etc.)
        $sql2 = "SELECT ID AS id, date AS datum, time AS zeit, name AS Bezeichnung,
                        DATEDIFF(date, CURDATE()) AS tage_bis
                 FROM wichtige_termine
                 WHERE date BETWEEN CURDATE() AND (CURDATE() + INTERVAL $win DAY)";

        // Beide Quellen in EINEN Puffer [user][datum][] - ein Tag ergibt eine Meldung,
        // egal aus welcher Tabelle die Termine stammen.
        $buf = [];
        foreach ([$sql1, $sql2] as $sql) {
            foreach ($db->query($sql)->fetchAll() as $r) {
                $datum   = (string) $r['datum'];
                $tageBis = (int) $r['tage_bis'];
                $item    = ['bez' => (string) $r['Bezeichnung'], 'zeit' => fmtZeit($r['zeit'])];
                foreach ($users as $uid => $info) {
                    if ($tageBis > ($info['lead'] ?? $def)) continue; // ausserhalb persoenlicher Vorlaufzeit
                    $buf[$uid][$datum][] = $item;
                }
            }
        }
        foreach ($buf as $uid => $tage) {
            ksort($tage); // Quellen wurden nacheinander eingelesen -> Tage wieder chronologisch
            foreach ($tage as $datum => $items) {
                [$titel, $text, $key] = buendeln('Vereinstermin', $items, (string) $datum, 'Vereinstermine', 'Termine');
                zustellen($db, (int) $uid, 'termine', 'termin_tag', $key, (string) $datum,
                          $titel, $text, 'portal/dashboard.php', $stats, 'termin-' . $datum);
            }
        }
        $details['termine'] = $stats['sent'] - $n0;
    }
} catch (\Throwable $e) {
    error_log('cron benachrichtigungen [termine]: ' . $e->getMessage());
}

// =====================  Aufraeumen: alte Log-Eintraege  ======================
try {
    $db->exec('DELETE FROM benachrichtigung_log WHERE termin_datum < (CURDATE() - INTERVAL 90 DAY)');
} catch (\Throwable $e) {
    error_log('cron benachrichtigungen [purge]: ' . $e->getMessage());
}

// Aufraeumen: In-App-Glocke. Gelesene > 30 Tage, ungelesene > 90 Tage entfernen.
try {
    $db->exec("DELETE FROM benachrichtigungen_inbox
               WHERE (gelesen_am IS NOT NULL AND gelesen_am  < (NOW() - INTERVAL 30 DAY))
                  OR (gelesen_am IS NULL     AND erstellt_am < (NOW() - INTERVAL 90 DAY))");
} catch (\Throwable $e) {
    error_log('cron benachrichtigungen [purge inbox]: ' . $e->getMessage());
}

// =====================  Ausgabe  =============================================
$summary = sprintf(
    'Push-Reminder: %d gesendet, %d uebersprungen, %d ohne Geraet, %d fehlgeschlagen.',
    $stats['sent'], $stats['skipped'], $stats['no_device'], $stats['failed']
);

if ($cli) {
    echo $summary . PHP_EOL;
    exit(0);
}
echo json_encode(['success' => true, 'message' => $summary, 'stats' => $stats, 'details' => $details],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
