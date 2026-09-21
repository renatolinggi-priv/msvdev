<?php
/**
 * dashboard_widgets.inc.php
 *
 * Zusatzblöcke der Startseite (inc/home.php), ergänzend zu den saisonalen
 * Kacheln aus dashboard_phasen.inc.php:
 *
 *   msvDashAufgaben()     "Das wartet auf dich" - offene Punkte quer durch die App
 *   msvDashTermine()      naechste Daten aus JM-Anlaessen und wichtigen Terminen
 *   msvDashJubilaeen()    runde Geburtstage und Vereinsjubilaeen
 *
 * Grundsatz wie bei den Kacheln: alles kommt aus vorhandenen Daten, es gibt
 * keine neue Tabelle und nichts einzustellen. Jede Abfrage ist rein lesend und
 * scheitert still (leeres Ergebnis), wenn eine Tabelle fehlt - die Startseite
 * darf nie wegen eines Zusatzblocks kaputtgehen.
 */

require_once __DIR__ . '/dashboard_phasen.inc.php';

/** Positionen eines Einsatzplans gelten als offen, wenn weder Mitglied noch Name gesetzt ist. */
const MSV_DASH_EINSATZ_FRIST = 42;   // Tage: ab wann offene Positionen gemeldet werden
const MSV_DASH_GEBURTSTAG_FRIST = 90; // Tage: Vorlauf fuer anstehende Geburtstage

/**
 * Fuehrt eine lesende Abfrage aus und liefert alle Zeilen.
 * Bei Fehlern (fehlende Tabelle o.ae.) ein leeres Array statt einer Exception.
 */
function msvDashRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    try {
        $stmt = @$conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!@$stmt->execute()) {
            $stmt->close();
            return [];
        }
        $res  = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

/** Erste Spalte der ersten Zeile als int. */
function msvDashOne(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $rows = msvDashRows($conn, $sql, $types, $params);
    if (!$rows) {
        return 0;
    }
    return (int)reset($rows[0]);
}

/**
 * Einsatzplaene mit Terminen ab $heute, inklusive Belegungszahlen.
 * Basis fuer zwei Aufgaben: "fertig, aber nicht freigegeben" und "Positionen offen".
 */
function msvDashEinsatzPlaene(mysqli $conn, string $heute): array
{
    return msvDashRows($conn,
        "SELECT p.id, p.titel, p.status,
                MIN(t.datum) AS von,
                (SELECT COUNT(*) FROM einsatz_plan_slots s WHERE s.plan_id = p.id) AS slots,
                (SELECT COUNT(*) FROM einsatz_plan_slots s
                   WHERE s.plan_id = p.id
                     AND s.mitglied_id IS NULL
                     AND (s.name_text IS NULL OR s.name_text = '')) AS offen
         FROM einsatz_plaene p
         JOIN einsatz_plan_termine t ON t.plan_id = p.id
         WHERE t.datum IS NOT NULL
         GROUP BY p.id, p.titel, p.status
         HAVING von >= ?
         ORDER BY von ASC",
        's', [$heute]);
}

/**
 * Baut die Liste offener Punkte.
 *
 * @param array $pending Ergebnis von msvDashboardAusstehend()
 * @return array Zeilen: icon, text, link, badge
 */
function msvDashAufgaben(mysqli $conn, string $heute, array $pending, bool $istAdmin): array
{
    $aufgaben = [];

    // --- Anlaesse ohne Resultate (Details, nicht nur eine Zahl) ---
    $zeige = array_slice($pending, 0, 4);
    foreach ($zeige as $p) {
        $aufgaben[] = [
            'icon'  => 'bi-clipboard-x',
            'text'  => $p['Bezeichnung'] . ' ohne Resultate',
            'link'  => 'jmresultate.php',
            'badge' => 'seit ' . msvDashDatum($p['letzter']),
        ];
    }
    if (count($pending) > count($zeige)) {
        $rest = count($pending) - count($zeige);
        $aufgaben[] = [
            'icon'  => 'bi-three-dots',
            'text'  => $rest . ' weitere Anlässe ohne Resultate',
            'link'  => 'jmresultate.php',
            'badge' => null,
        ];
    }

    // --- Umfragen, deren Frist abgelaufen ist, aber noch offen stehen ---
    $rows = msvDashRows($conn,
        "SELECT id, titel, gueltig_bis
         FROM umfragen
         WHERE status = 'aktiv' AND gueltig_bis IS NOT NULL AND gueltig_bis < ?
         ORDER BY gueltig_bis ASC",
        's', [$heute]);
    foreach ($rows as $u) {
        $aufgaben[] = [
            'icon'  => 'bi-ui-checks',
            'text'  => 'Umfrage «' . $u['titel'] . '» ist noch offen',
            'link'  => '../portal/mein_fragebogen.php',
            'badge' => 'Frist ' . msvDashDatum($u['gueltig_bis']),
        ];
    }

    // --- Einsatzplaene ---
    foreach (msvDashEinsatzPlaene($conn, $heute) as $p) {
        $slots = (int)$p['slots'];
        $offen = (int)$p['offen'];

        // Fertig besetzt, aber noch Entwurf: im Portal sieht ihn niemand.
        if ($p['status'] === 'entwurf' && $slots > 0 && $offen === 0) {
            $aufgaben[] = [
                'icon'  => 'bi-send-check',
                'text'  => $p['titel'] . ' ist vollständig besetzt, aber nicht freigegeben',
                'link'  => 'einsatzplanung.php',
                'badge' => msvDashRelativ($p['von'], $heute),
            ];
        }

        // Offene Positionen erst melden, wenn der Termin naeher rueckt -
        // sonst nörgeln die Plaene des naechsten Jahres das ganze Jahr.
        $frist = msvDashTage($heute, $p['von']);
        if ($offen > 0 && $frist !== null && $frist <= MSV_DASH_EINSATZ_FRIST) {
            $aufgaben[] = [
                'icon'  => 'bi-person-dash',
                'text'  => $p['titel'] . ': ' . $offen . ($offen === 1 ? ' Position offen' : ' Positionen offen'),
                'link'  => 'einsatzplanung.php',
                'badge' => msvDashRelativ($p['von'], $heute),
            ];
        }
    }

    // --- Offene Tauschanfragen ---
    $n = msvDashOne($conn, "SELECT COUNT(*) FROM einsatz_tausch WHERE status = 'offen'");
    if ($n > 0) {
        $aufgaben[] = [
            'icon'  => 'bi-arrow-left-right',
            'text'  => $n === 1 ? 'Eine Tauschanfrage ist offen' : $n . ' Tauschanfragen sind offen',
            'link'  => 'einsatzplanung.php',
            'badge' => null,
        ];
    }

    // --- Registrierungen, die auf Freigabe warten ---
    $n = msvDashOne($conn, "SELECT COUNT(*) FROM users WHERE status = 'pending'");
    if ($n > 0) {
        $aufgaben[] = [
            'icon'  => 'bi-person-plus',
            'text'  => $n === 1 ? 'Eine Registrierung wartet auf Freigabe' : $n . ' Registrierungen warten auf Freigabe',
            'link'  => 'benutzerverwaltung.php',
            'badge' => null,
        ];
    }

    // --- Fotos in der Moderation ---
    $n = msvDashOne($conn, "SELECT COUNT(*) FROM anlass_fotos WHERE status = 'pending'");
    if ($n > 0) {
        $aufgaben[] = [
            'icon'  => 'bi-images',
            'text'  => $n === 1 ? 'Ein Foto wartet auf Freigabe' : $n . ' Fotos warten auf Freigabe',
            'link'  => 'anlass_galerie_verwaltung.php',
            'badge' => null,
        ];
    }

    // --- Datenbank-Migrationen (nur Admin, nur diese Seite kann sie ausfuehren) ---
    if ($istAdmin) {
        $offen = msvDashOffeneMigrationen($conn);
        if ($offen > 0) {
            $aufgaben[] = [
                'icon'  => 'bi-database-exclamation',
                'text'  => $offen === 1 ? 'Eine Datenbank-Aktualisierung steht aus' : $offen . ' Datenbank-Aktualisierungen stehen aus',
                'link'  => '../admin/aktualisierung.php',
                'badge' => null,
            ];
        }
    }

    return $aufgaben;
}

/**
 * Zahl der Migrationsdateien, die die Datenbank noch nicht kennt.
 * Rein lesend - die Tracking-Tabelle wird bewusst nicht angelegt.
 */
function msvDashOffeneMigrationen(mysqli $conn): int
{
    $dir = dirname(__DIR__) . '/migrations';
    if (!is_dir($dir)) {
        return 0;
    }
    $dateien = glob($dir . '/*.sql') ?: [];
    if (!$dateien) {
        return 0;
    }
    $rows = msvDashRows($conn, "SELECT Dateiname FROM schema_migrationen");
    if (!$rows) {
        // Tabelle fehlt oder ist leer -> Baseline noch nicht gesetzt, nicht warnen.
        return 0;
    }
    $erledigt = array_flip(array_column($rows, 'Dateiname'));
    $offen = 0;
    foreach ($dateien as $datei) {
        if (!isset($erledigt[basename($datei)])) {
            $offen++;
        }
    }
    return $offen;
}

/**
 * Naechste Daten aus den JM-Anlaessen (inkl. Info-Eintraegen wie GV und
 * Mittagessen) und aus den wichtigen Terminen, zusammengefuehrt und nach
 * Titel gruppiert, damit mehrtaegige Anlaesse eine Zeile bleiben.
 */
function msvDashTermine(mysqli $conn, string $heute, int $limit = 6): array
{
    return msvDashRows($conn,
        "SELECT titel, quelle, MIN(dat) AS von, MAX(dat) AS bis
         FROM (
            SELECT s.schiesstag AS dat, jd.Bezeichnung AS titel, 'Anlass' AS quelle
              FROM JMSchiesstage s
              JOIN JMDefinition jd ON jd.ID = s.jm_id
             WHERE jd.hidden = 0 AND s.schiesstag >= ?
            UNION ALL
            SELECT `date` AS dat, name AS titel, 'Termin' AS quelle
              FROM wichtige_termine
             WHERE `date` >= ?
         ) x
         GROUP BY titel, quelle, YEAR(dat)
         ORDER BY von ASC
         LIMIT ?",
        'ssi', [$heute, $heute, $limit]);
}

/**
 * Anstehende Geburtstage der naechsten 90 Tage (alle, runde mit Flag 'rund')
 * und Vereinsjubilaeen des Jahres.
 *
 * Gerechnet wird in PHP: der Mitgliederbestand ist klein, und ein
 * Datumsfenster ueber den Jahreswechsel laesst sich in SQL nur umstaendlich
 * formulieren.
 */
function msvDashJubilaeen(mysqli $conn, int $jahr, string $heute): array
{
    $rows = msvDashRows($conn,
        "SELECT Vorname, Name, Geburtsdatum, Vereinsaufnahme
         FROM mitglieder
         WHERE Status = 1 AND Verstorben = 0");

    $geburtstage = [];
    $jubilaeen   = [];

    try {
        $heuteDt = new DateTimeImmutable($heute . ' 00:00:00');
    } catch (Exception $e) {
        return ['geburtstage' => [], 'jubilaeen' => []];
    }
    $grenze = $heuteDt->modify('+' . MSV_DASH_GEBURTSTAG_FRIST . ' days');

    foreach ($rows as $r) {
        $person = trim(($r['Vorname'] ?? '') . ' ' . ($r['Name'] ?? ''));

        // Naechster Geburtstag im Fenster - alle, nicht nur runde.
        // Runde werden markiert, damit sie trotzdem auffallen.
        if (!empty($r['Geburtsdatum'])) {
            try {
                $geb = new DateTimeImmutable($r['Geburtsdatum']);
                $naechster = $geb->setDate((int)$heuteDt->format('Y'), (int)$geb->format('n'), (int)$geb->format('j'));
                if ($naechster < $heuteDt) {
                    $naechster = $naechster->modify('+1 year');
                }
                $alter = (int)$naechster->format('Y') - (int)$geb->format('Y');
                if ($alter > 0 && $naechster <= $grenze) {
                    $geburtstage[] = [
                        'person' => $person,
                        'datum'  => $naechster->format('Y-m-d'),
                        'alter'  => $alter,
                        'rund'   => ($alter % 5 === 0),
                    ];
                }
            } catch (Exception $e) {
                // unbrauchbares Datum still überspringen
            }
        }

        // Vereinsjubilaeen des laufenden Jahres (Vereinsaufnahme ist ein YEAR-Feld)
        $seit = (int)($r['Vereinsaufnahme'] ?? 0);
        if ($seit > 0) {
            $jahre = $jahr - $seit;
            if ($jahre > 0 && $jahre % 5 === 0) {
                $jubilaeen[] = ['person' => $person, 'jahre' => $jahre, 'seit' => $seit];
            }
        }
    }

    usort($geburtstage, fn($a, $b) => strcmp($a['datum'], $b['datum']));
    usort($jubilaeen, fn($a, $b) => $b['jahre'] <=> $a['jahre']);

    return ['geburtstage' => $geburtstage, 'jubilaeen' => $jubilaeen];
}
