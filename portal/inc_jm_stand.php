<?php
// inc_jm_stand.php – JM-Stand eines Mitglieds für ein Jahr: Anlässe mit Resultat, Hochrechnung,
// Streicher und Total. Gemeinsam genutzt von meine_jm.php und der Startseite (dashboard.php),
// damit beide gleich rechnen (Regeln analog jmrang/load_jm.php). Code unverändert aus meine_jm.php.
// Erwartet: $db (PDO), $mitglied_id (?int), $selected_year (int).
// Liefert u.a. $schiessen_list (mit PunkteNorm, _idx), $all_streicher_idxs, $streicher_idxs,
// $total_punkte, $geschossen_count, $total_events, $exclude_count, $available_years, $months_de
// und die Helfer portalNormalize(), jmIsTeilnahme(), jmIsVereinscup(), splitSchiessDatum(), jmParseDatum().
if (!isset($db) || !($db instanceof PDO)) { http_response_code(404); exit; }

// Hochrechnung auf 100: nur wenn Maxpunkte < 100 (analog zu jmrang/load_jm.php)
function portalNormalize(?int $punkte, int $maxpunkte, string $bezeichnung = ''): ?float {
    if ($punkte === null) return null;
    // Bonus-Events werden NICHT hochgerechnet (analog zu scalePoints in load_jm.php)
    if (in_array($bezeichnung, ['Einzelwettschiessen', 'Obligatorisch', 'Feldschiessen'])) {
        return (float)$punkte;
    }
    if ($maxpunkte > 0 && $maxpunkte < 100) {
        return round($punkte * 100 / $maxpunkte, 2);
    }
    return (float)$punkte;
}

// Teilnahme-Only-Anlass: Maxpunkte == 20 -> Teilnahme bedeutet immer die volle Punktzahl.
// Im Portal wird daher nur Ja/Nein angezeigt/erfasst, nie der konkrete Wert.
function jmIsTeilnahme(array $s): bool {
    return (int)($s['Maxpunkte'] ?? 0) === 20;
}

// Vereinscup: Das zaehlende Resultat wird ueber die Cup-Erfassung (inc/cup.php -> cupPairs)
// gefuehrt, nicht per Selbsteingabe. Es wird daher nur read-only angezeigt.
// Bewusst NICHT auf "Standcup ..."-Auswaertsschiessen matchen.
function jmIsVereinscup(array $s): bool {
    return (bool)preg_match('/Vereins[- ]?cup/i', (string)($s['Bezeichnung'] ?? ''));
}

// Verfuegbare Jahre laden
$years_stmt = $db->query("SELECT DISTINCT year FROM JMDefinition WHERE year IS NOT NULL ORDER BY year DESC");
$available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($available_years)) $available_years = [date('Y')];

// Anzahl Streicher aus Parameter-Tabelle laden (Fallback: 3)
$ep_stmt = $db->prepare("SELECT excludeCount FROM Parameter WHERE year = ?");
$ep_stmt->execute([$selected_year]);
$ep_row = $ep_stmt->fetch();
$exclude_count = $ep_row ? max(1, (int)$ep_row['excludeCount']) : 3;

// Alle JM-Schiessen des Jahres (keine Info/Erweitert-Events, nicht hidden)
// Gleiche Filter wie in jmrang/load_jm.php: Erweitert=0 AND Info=0
$jm_stmt = $db->prepare("
    SELECT jd.ID, jd.Bezeichnung, jd.Maxpunkte, jd.Streicher, jd.Reihenfolge,
           jd.Schiesstage, jd.Adresse, jd.Zuschlag, jd.Info,
           jr.Punkte, jr.status AS jr_status
    FROM JMDefinition jd
    LEFT JOIN jmresultate jr ON jr.jmdefinitionID = jd.ID AND jr.mitgliederID = ?
        AND (jr.Info = '' OR jr.Info IS NULL)
    WHERE jd.year = ?
      AND jd.hidden = 0
      AND jd.Info = 0
      AND jd.Erweitert = 0
    ORDER BY jd.Reihenfolge ASC
");
$jm_stmt->execute([$mitglied_id, $selected_year]);
$schiessen_list = $jm_stmt->fetchAll();

// Eindeutigen Index zuweisen (Sektionsmeisterschaft hat mehrere Zeilen mit gleicher defID)
foreach ($schiessen_list as $idx => &$s) {
    $s['_idx'] = $idx;
}
unset($s);

// Sonderfälle: Endstich und Bester Kantonalstich kommen aus eigenen Tabellen
// (nicht aus jmresultate) – gleiche Logik wie in jmrang/load_jm.php
$endstich_def_id = null;
$kanti_def_id    = null;
$sektion_def_id  = null;
$cup_def_ids     = [];   // Vereinscup -> Resultat aus cupPairs (inc/cup.php)
foreach ($schiessen_list as $s) {
    if ($s['Bezeichnung'] === 'Endstich')             $endstich_def_id = (int)$s['ID'];
    if ($s['Bezeichnung'] === 'Bester Kantonalstich')  $kanti_def_id    = (int)$s['ID'];
    if ($s['Bezeichnung'] === 'Sektionsmeisterschaft') $sektion_def_id  = (int)$s['ID'];
    if (jmIsVereinscup($s))                            $cup_def_ids[]   = (int)$s['ID'];
}
$cup_def_ids = array_values(array_unique($cup_def_ids));

if ($endstich_def_id && $mitglied_id) {
    $es = $db->prepare("
        SELECT (COALESCE(Schuss1,0)+COALESCE(Schuss2,0)+COALESCE(Schuss3,0)+
                COALESCE(Schuss4,0)+COALESCE(Schuss5,0)+COALESCE(Schuss6,0)+
                COALESCE(Schuss7,0)+COALESCE(Schuss8,0)+COALESCE(Schuss9,0)+
                COALESCE(Schuss10,0)) AS Punkte
        FROM endstich WHERE MitgliedID = ? AND Jahr = ?
    ");
    $es->execute([$mitglied_id, $selected_year]);
    $esrow = $es->fetch();
    $endstich_punkte = $esrow ? (int)$esrow['Punkte'] : null;
    foreach ($schiessen_list as &$s) {
        if ((int)$s['ID'] === $endstich_def_id) {
            $s['Punkte'] = $endstich_punkte;
        }
    }
    unset($s);
}

if ($kanti_def_id && $mitglied_id) {
    $ks = $db->prepare("
        SELECT GREATEST(
            COALESCE(Passe1,0),COALESCE(Passe2,0),COALESCE(Passe3,0),
            COALESCE(Passe4,0),COALESCE(Passe5,0)
        ) AS Punkte
        FROM kantiresultate WHERE MitgliedID = ? AND Jahr = ?
    ");
    $ks->execute([$mitglied_id, $selected_year]);
    $ksrow = $ks->fetch();
    $kanti_punkte = $ksrow ? (int)$ksrow['Punkte'] : null;
    foreach ($schiessen_list as &$s) {
        if ((int)$s['ID'] === $kanti_def_id) {
            $s['Punkte'] = $kanti_punkte;
        }
    }
    unset($s);
}

// Sektionsmeisterschaft: zaehlendes Resultat = hoechste Runde (Info='runde 1'/'runde 2'),
// analog zur Rangliste (load_jm.php: nur hoechster Wert zaehlt). Kommt aus jmresultate, das
// per LEFT JOIN (Info='') nicht greift -> separat laden, damit der Wert angezeigt wird.
if ($sektion_def_id && $mitglied_id) {
    $sm = $db->prepare("
        SELECT MAX(Punkte) AS Punkte
        FROM jmresultate
        WHERE mitgliederID = ? AND jmdefinitionID = ? AND Info IN ('runde 1','runde 2')
    ");
    $sm->execute([$mitglied_id, $sektion_def_id]);
    $smrow = $sm->fetch();
    $sektion_punkte = ($smrow && $smrow['Punkte'] !== null) ? (int)$smrow['Punkte'] : null;
    foreach ($schiessen_list as &$s) {
        if ((int)$s['ID'] === $sektion_def_id) {
            $s['Punkte'] = $sektion_punkte;
        }
    }
    unset($s);
}

// Vereinscup: zaehlendes Resultat = Punktzahl aus der 1. Cup-Runde (cupPairs.Round=1),
// analog zu Endstich/Kanti. Das Resultat stammt aus der Cup-Erfassung (inc/cup.php) und
// nicht aus einer Selbsteingabe. Ist das Mitglied in Runde 1 nicht (mit Resultat) erfasst,
// bleibt der bestehende jmresultate-Wert als Fallback erhalten.
// Hinweis (PDO ATTR_EMULATE_PREPARES=false): positionsbasierte Platzhalter, da mitglied_id
// mehrfach gebunden wird.
if ($cup_def_ids && $mitglied_id) {
    $cp = $db->prepare("
        SELECT CASE
                   WHEN Participant1 = ? THEN Result1
                   WHEN Participant2 = ? THEN Result2
                   WHEN Participant3 = ? THEN Result3
               END AS Punkte
        FROM cupPairs
        WHERE `Year` = ? AND `Round` = 1
          AND (Participant1 = ? OR Participant2 = ? OR Participant3 = ?)
        LIMIT 1
    ");
    $cp->execute([
        $mitglied_id, $mitglied_id, $mitglied_id,
        $selected_year,
        $mitglied_id, $mitglied_id, $mitglied_id,
    ]);
    $cprow = $cp->fetch();
    if ($cprow && $cprow['Punkte'] !== null) {
        $cup_punkte = (int)$cprow['Punkte'];
        foreach ($schiessen_list as &$s) {
            if (in_array((int)$s['ID'], $cup_def_ids, true)) {
                $s['Punkte'] = $cup_punkte;
            }
        }
        unset($s);
    }
}

// Normalisierte Punkte berechnen (Hochrechnung auf 100 wenn Maxpunkte < 100)
// Punkte=0 wird als "nicht teilgenommen" behandelt (DB speichert 0 statt NULL)
foreach ($schiessen_list as &$s) {
    $raw = $s['Punkte'];
    $punkte = ($raw !== null && (int)$raw > 0) ? (int)$raw : null;
    $s['PunkteNorm'] = portalNormalize($punkte, (int)($s['Maxpunkte'] ?? 100), $s['Bezeichnung'] ?? '');
}
unset($s);

// Streicher und Total: gemeinsame JM-Rechnung (inc/jm_berechnung.inc.php), gleich wie die
// Admin-Rangliste und das PDF. Vor dem Endstich sind nur verpasste Anlässe gestrichen.
require_once __DIR__ . '/../inc/jm_berechnung.inc.php';
$jm_conn = $conn ?? ($GLOBALS['conn'] ?? null);
$jm_eintrag = null;
$jm_abgeschlossen = false;
if ($mitglied_id && $jm_conn instanceof mysqli) {
    $jm_wertung = jmWertung($jm_conn, (int)$selected_year, ['mitglied' => (int)$mitglied_id]);
    $jm_eintrag = $jm_wertung['mitglieder'][0] ?? null;
    $jm_abgeschlossen = $jm_wertung['abgeschlossen'];
    $exclude_count = $jm_wertung['anzahlStreicher'];
}

// Sektionsmeisterschaft: bei mehreren Zeilen mit gleicher defID (LEFT JOIN erzeugt
// Duplikate) nur die beste zaehlen, schlechtere als Streicher markieren (via _idx)
$sektions_streicher_idxs = [];
$entries_by_defid = [];
foreach ($schiessen_list as $s) {
    $entries_by_defid[(int)$s['ID']][] = $s;
}
foreach ($entries_by_defid as $defid => $entries) {
    if (count($entries) <= 1) continue;
    // Absteigend sortieren (bester zuerst)
    usort($entries, fn($a, $b) => ($b['PunkteNorm'] ?? 0) <=> ($a['PunkteNorm'] ?? 0));
    for ($i = 1; $i < count($entries); $i++) {
        $sektions_streicher_idxs[] = $entries[$i]['_idx'];
    }
}

// Streicher laut jmWertung auf die Zeilen übertragen (via _idx, Sektions-Duplikate ausschliessen)
$streicher_idxs = [];
if ($jm_eintrag) {
    foreach ($schiessen_list as $s) {
        if (in_array($s['_idx'], $sektions_streicher_idxs)) continue;
        if (!empty($jm_eintrag['werte'][(int)$s['ID']]['gestrichen'])) $streicher_idxs[] = $s['_idx'];
    }
}

// Alle Streicher zusammenfassen (regulaere + Sektionsmeisterschaft-Duplikate)
$all_streicher_idxs = array_merge($streicher_idxs, $sektions_streicher_idxs);

// Schiessdatum in Datum- und Zeitanteil aufteilen
// "Samstag 18. April 2026 08:00 – 12:00 Uhr" → ['date'=>'Samstag 18. April 2026', 'time'=>'08:00 – 12:00 Uhr']
function splitSchiessDatum(string $line): array {
    $line = trim($line);
    // Zeit = ab dem ersten HH:MM oder HH.MM (Datum enthaelt nie eine Uhrzeit) — auch ohne Jahr.
    // Ein "DD." des Datums passt nicht, da danach ein Leerzeichen + Monatsname folgt (keine 2 Ziffern).
    if (preg_match('/^(.*?)\s*(\d{1,2}[:.]\d{2}.*)$/u', $line, $m)) {
        return ['date' => trim($m[1]), 'time' => trim($m[2])];
    }
    return ['date' => $line, 'time' => ''];
}

// Parst eine deutsche Datumszeile ("... 18. April 2026") zu 'Y-m-d' (oder null, wenn unklar).
function jmParseDatum(string $dateStr, array $months_de, int $fallbackYear): ?string {
    foreach ($months_de as $name => $num) {
        if (preg_match('/(\d{1,2})\.\s*' . preg_quote($name, '/') . '(?:\s+(\d{4}))?/u', $dateStr, $m)) {
            $year = (isset($m[2]) && $m[2] !== '') ? (int)$m[2] : $fallbackYear;
            return sprintf('%04d-%02d-%02d', $year, $num, (int)$m[1]);
        }
    }
    return null;
}

// Monats-Mapping fuer Datum-Erkennung
$months_de = ['Januar'=>1,'Februar'=>2,'März'=>3,'April'=>4,'Mai'=>5,'Juni'=>6,
              'Juli'=>7,'August'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Dezember'=>12];

// Zusammenfassung berechnen
$total_punkte = 0;
$geschossen_count = 0;
$streicher_used = count($streicher_idxs); // nur regulaere Streicher fuer Anzeige

foreach ($schiessen_list as $s) {
    if ($s['PunkteNorm'] !== null) {
        $geschossen_count++;
    }
}
if ($jm_eintrag) $total_punkte = $jm_eintrag['total'];
$total_events = count($schiessen_list);
