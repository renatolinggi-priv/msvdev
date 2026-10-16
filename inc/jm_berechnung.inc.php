<?php
/**
 * Gemeinsame JM-Rechnung (seit 15.10.2026): Werte, Hochrechnung, Streicher, Total und Rang.
 * Genutzt von der Admin-Rangliste (inc/jmrang/load_jm.php), dem PDF «Rangliste nach Rang»
 * (inc/jmrang/generate_pdf_jm.php) und dem Portal (portal/inc_jm_stand.php). Wer ein JM-Total
 * braucht, ruft jmWertung() auf und rechnet nicht selbst. Das Absendenbuch rechnet bewusst weiter
 * selbst (inc/absenden/functions.inc.php, Entscheid 15.10.2026) und ist der Massstab für den
 * Endstand: nach dem Endstich muss jmWertung() dieselben Totale liefern.
 *
 * Regeln:
 * - Ein Wert pro Mitglied und Anlass: das beste Resultat (Sektionsmeisterschaft: beste Runde).
 *   Endstich und Bester Kantonalstich kommen aus den Tabellen endstich bzw. kantiresultate.
 * - Hochrechnung auf 100 nur bei Maxpunkte unter 100 (nicht Obligatorisch, Feldschiessen,
 *   Einzelwettschiessen).
 * - Pflicht-Anlässe (Streicher=0) zählen immer.
 * - Ein Streicher-Anlass zählt für alle mit, sobald jemand ein Resultat hat. Wer fehlt, bekommt 0.
 *   Ein abgesagter oder noch nicht erfasster Anlass gibt also niemandem eine 0.
 * - Nach dem Endstich (Datum erreicht UND Resultate erfasst): die N schlechtesten Werte sind
 *   gestrichen (N = Parameter.excludeCount).
 * - Vorher (Zwischenstand): gestrichen werden nur verpasste Anlässe (Wert 0), höchstens N.
 * - Gleichstand: vorne, wer im Endstich mehr hat (Entscheid 14.10.2026).
 */

if (!function_exists('jmWertung')) {

/** Wird dieser Anlass auf 100 hochgerechnet? */
function jmIstHochgerechnet(array $def): bool
{
    if (in_array($def['Bezeichnung'], ['Einzelwettschiessen', 'Obligatorisch', 'Feldschiessen'], true)) return false;
    $max = (int)$def['Maxpunkte'];
    return $max > 0 && $max < 100;
}

/** Rohpunkte in die Wertung umrechnen (Hochrechnung siehe jmIstHochgerechnet). */
function jmHochrechnen(float $punkte, array $def): float
{
    return jmIstHochgerechnet($def) ? round($punkte * 100 / (int)$def['Maxpunkte'], 2) : $punkte;
}

/** Letztes Schiessdatum aus dem Schiesstage-Freitext ('YYYY-MM-DD'), falls JMSchiesstage leer ist. */
function jmLetztesDatumAusText(string $text, int $jahr): ?string
{
    $monate = ['Januar'=>1,'Februar'=>2,'März'=>3,'Maerz'=>3,'April'=>4,'Mai'=>5,'Juni'=>6,'Juli'=>7,
               'August'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Dezember'=>12];
    $letztes = null;
    foreach (preg_split('/\r\n|\r|\n/', $text) as $zeile) {
        foreach ($monate as $name => $nr) {
            if (preg_match('/(\d{1,2})\.\s*' . preg_quote($name, '/') . '(?:\s+(\d{4}))?/u', $zeile, $m)) {
                $y = (isset($m[2]) && $m[2] !== '') ? (int)$m[2] : $jahr;
                $d = sprintf('%04d-%02d-%02d', $y, $nr, (int)$m[1]);
                if ($letztes === null || $d > $letztes) $letztes = $d;
                break;
            }
        }
    }
    return $letztes;
}

/**
 * JM eines Jahres werten.
 *
 * $optionen:
 *   'kategorie'  => 'Kat. A' | 'Kat. B'  aktive Mitglieder dieser Kategorie
 *   'mitglied'   => int                   nur dieses Mitglied (Portal)
 *   'stichtag'   => 'YYYY-MM-DD'          Stand an diesem Tag (Standard: heute)
 *   ohne beides: alle aktiven Mitglieder
 *
 * Rückgabe:
 *   'definitionen'    Liste der Anlässe (Anzeige-Reihenfolge) mit 'letztesDatum', 'hochgerechnet', 'zaehlt'
 *   'anzahlStreicher' N
 *   'abgeschlossen'   true = Endstich durch, definitive Rangliste; false = Zwischenstand
 *   'endstichDatum'   'YYYY-MM-DD' oder null
 *   'mitglieder'      Liste in Ranglisten-Reihenfolge, je:
 *      'mitglied' [ID, Vorname, Name], 'rang' (int|null, null = ohne gewertetes Resultat),
 *      'werte' [defID => ['punkte', 'roh', 'gestrichen', 'verpasst']], 'sumPflicht', 'sumStreich',
 *      'total', 'endstich', 'hatResultat', 'anzGestrichen', 'anzVerpasst'
 */
function jmWertung(mysqli $conn, int $jahr, array $optionen = []): array
{
    $stichtag = $optionen['stichtag'] ?? date('Y-m-d');

    // ---- Anzahl Streicher ----
    $st = $conn->prepare('SELECT excludeCount FROM Parameter WHERE year = ?');
    $st->bind_param('i', $jahr);
    $st->execute();
    $p = $st->get_result()->fetch_assoc();
    $st->close();
    $anzahlStreicher = $p ? max(1, (int)$p['excludeCount']) : 3;

    // ---- Anlässe ----
    $st = $conn->prepare("SELECT ID, Bezeichnung, Maxpunkte, Streicher, Reihenfolge, Schiesstage
        FROM JMDefinition WHERE year = ? AND Erweitert = 0 AND Info = 0 AND hidden = 0
        ORDER BY CASE
            WHEN Bezeichnung = 'Obligatorisch' THEN 1
            WHEN Bezeichnung = 'Feldschiessen' THEN 2
            WHEN Bezeichnung LIKE '%Kantonalstich%' THEN 3
            WHEN Bezeichnung LIKE '%Sektionsmeisterschaft%' THEN 4
            ELSE 5 END, Reihenfolge");
    $st->bind_param('i', $jahr);
    $st->execute();
    $defs = [];
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $d) {
        $d['ID'] = (int)$d['ID'];
        $d['Streicher'] = (int)$d['Streicher'];
        $d['Maxpunkte'] = (int)$d['Maxpunkte'];
        $d['hochgerechnet'] = jmIstHochgerechnet($d);
        $d['letztesDatum'] = null;
        $d['zaehlt'] = false; // Streicher-Anlass mit mindestens einem Resultat
        $defs[$d['ID']] = $d;
    }
    $st->close();

    $leer = ['definitionen' => array_values($defs), 'anzahlStreicher' => $anzahlStreicher,
             'abgeschlossen' => false, 'endstichDatum' => null, 'mitglieder' => []];
    if (!$defs) return $leer;

    $endstichID = $kantiID = null;
    foreach ($defs as $id => $d) {
        if ($d['Bezeichnung'] === 'Endstich') $endstichID = $id;
        if ($d['Bezeichnung'] === 'Bester Kantonalstich') $kantiID = $id;
    }

    // ---- Letztes Schiessdatum: JMSchiesstage, sonst Freitext ----
    $st = $conn->prepare('SELECT jm_id, MAX(schiesstag) AS d FROM JMSchiesstage WHERE year = ? GROUP BY jm_id');
    $st->bind_param('i', $jahr);
    $st->execute();
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        if (isset($defs[(int)$r['jm_id']]) && !empty($r['d'])) $defs[(int)$r['jm_id']]['letztesDatum'] = $r['d'];
    }
    $st->close();
    foreach ($defs as $id => $d) {
        if ($d['letztesDatum'] === null && !empty($d['Schiesstage'])) {
            $defs[$id]['letztesDatum'] = jmLetztesDatumAusText((string)$d['Schiesstage'], $jahr);
        }
    }

    // ---- Mitglieder ----
    if (!empty($optionen['mitglied'])) {
        $st = $conn->prepare('SELECT ID, Vorname, Name FROM mitglieder WHERE ID = ?');
        $mid = (int)$optionen['mitglied'];
        $st->bind_param('i', $mid);
    } elseif (!empty($optionen['kategorie'])) {
        $st = $conn->prepare('SELECT m.ID, m.Vorname, m.Name FROM mitglieder m JOIN Waffen w ON w.ID = m.WaffenID
            WHERE w.Kategorie = ? AND m.Status = 1 ORDER BY m.Name, m.Vorname');
        $st->bind_param('s', $optionen['kategorie']);
    } else {
        $st = $conn->prepare('SELECT ID, Vorname, Name FROM mitglieder WHERE Status = 1 ORDER BY Name, Vorname');
    }
    $st->execute();
    $mitglieder = [];
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $m) {
        $m['ID'] = (int)$m['ID'];
        $mitglieder[$m['ID']] = $m;
    }
    $st->close();
    $leer['definitionen'] = array_values($defs);
    if (!$mitglieder) return $leer;

    // ---- Rohwerte: ein Wert pro Mitglied und Anlass (das beste Resultat) ----
    // Gezählt wird für ALLE Mitglieder, damit «hat jemand ein Resultat» nicht von der Kategorie abhängt.
    $roh = []; // mid => defID => Punkte
    $ids = implode(',', array_keys(array_filter($defs, fn($d) => $d['ID'] !== $endstichID && $d['ID'] !== $kantiID)));
    if ($ids !== '') {
        $res = $conn->query("SELECT mitgliederID AS mid, jmdefinitionID AS def, MAX(Punkte) AS p
            FROM jmresultate WHERE jmdefinitionID IN ($ids) GROUP BY mitgliederID, jmdefinitionID");
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $r) {
            $defs[(int)$r['def']]['zaehlt'] = true;
            $roh[(int)$r['mid']][(int)$r['def']] = (int)$r['p'];
        }
    }
    $sonderfall = function (?int $defID, string $sql) use ($conn, $jahr, &$roh, &$defs) {
        if (!$defID) return;
        $st = $conn->prepare($sql);
        $st->bind_param('i', $jahr);
        $st->execute();
        foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            if ((int)$r['p'] > 0) $defs[$defID]['zaehlt'] = true; // gelöste, aber nicht geschossene Zeilen zählen nicht
            $roh[(int)$r['mid']][$defID] = (int)$r['p'];
        }
        $st->close();
    };
    $sonderfall($endstichID, "SELECT MitgliedID AS mid, MAX(
            COALESCE(Schuss1,0)+COALESCE(Schuss2,0)+COALESCE(Schuss3,0)+COALESCE(Schuss4,0)+COALESCE(Schuss5,0)+
            COALESCE(Schuss6,0)+COALESCE(Schuss7,0)+COALESCE(Schuss8,0)+COALESCE(Schuss9,0)+COALESCE(Schuss10,0)) AS p
        FROM endstich WHERE Jahr = ? AND MitgliedID IS NOT NULL GROUP BY MitgliedID");
    $sonderfall($kantiID, "SELECT MitgliedID AS mid, MAX(GREATEST(
            COALESCE(Passe1,0),COALESCE(Passe2,0),COALESCE(Passe3,0),COALESCE(Passe4,0),COALESCE(Passe5,0))) AS p
        FROM kantiresultate WHERE Jahr = ? AND MitgliedID IS NOT NULL GROUP BY MitgliedID");
    foreach ($defs as $id => $d) {
        if ($d['Streicher'] !== 1) $defs[$id]['zaehlt'] = false; // «zaehlt» betrifft nur den Streicher-Pool
    }

    // ---- Endstich durch? Datum erreicht UND Resultate erfasst ----
    $endstichDatum = $endstichID ? $defs[$endstichID]['letztesDatum'] : null;
    if ($endstichID) {
        $endstichErfasst = false;
        foreach ($roh as $w) { if (($w[$endstichID] ?? 0) > 0) { $endstichErfasst = true; break; } }
        $abgeschlossen = $endstichErfasst && ($endstichDatum === null || $endstichDatum <= $stichtag);
    } else {
        $abgeschlossen = true; // Jahr ohne Endstich: Total wie bisher sofort
    }

    // Anlässe ohne Cup, SSM/Sektionsmeisterschaft, Obligatorisch, Feldschiessen = «gewertetes Resultat»
    $zaehltAlsResultat = [];
    foreach ($defs as $id => $d) {
        $b = (string)$d['Bezeichnung'];
        if ($b === 'Obligatorisch' || $b === 'Feldschiessen') continue;
        if (stripos($b, 'Sektionsmeisterschaft') !== false || stripos($b, 'SSM') === 0 || stripos($b, 'Cup') !== false) continue;
        $zaehltAlsResultat[$id] = true;
    }
    $reihe = array_flip(array_keys($defs)); // für eine feste Reihenfolge bei gleichen Werten

    // ---- Werten ----
    $liste = [];
    foreach ($mitglieder as $mid => $m) {
        $werte = [];
        $pool = [];
        $sumPflicht = 0.0;
        $hatResultat = false;
        foreach ($defs as $id => $d) {
            $r = $roh[$mid][$id] ?? null;
            if ($r === null && !($d['Streicher'] === 1 && $d['zaehlt'])) continue; // nicht geschossen, zählt nicht
            $verpasst = ($r === null || $r === 0);
            $punkte = $r === null ? 0.0 : jmHochrechnen((float)$r, $d);
            $werte[$id] = ['punkte' => $punkte, 'roh' => $r, 'gestrichen' => false, 'verpasst' => $verpasst];
            if (!$verpasst && isset($zaehltAlsResultat[$id])) $hatResultat = true;
            if ($d['Streicher'] === 1) $pool[] = $id;
            else $sumPflicht += $punkte;
        }

        // Streicher: nach dem Endstich die N schlechtesten, vorher nur verpasste (0), je höchstens N
        $kandidaten = $abgeschlossen ? $pool : array_values(array_filter($pool, fn($id) => $werte[$id]['verpasst']));
        usort($kandidaten, fn($a, $b) => [$werte[$a]['punkte'], !$werte[$a]['verpasst'], $reihe[$a]]
                                          <=> [$werte[$b]['punkte'], !$werte[$b]['verpasst'], $reihe[$b]]);
        $anzGestrichen = 0;
        foreach (array_slice($kandidaten, 0, $anzahlStreicher) as $id) {
            $werte[$id]['gestrichen'] = true;
            $anzGestrichen++;
        }
        $sumStreich = 0.0;
        $anzVerpasst = 0;
        foreach ($pool as $id) {
            if ($werte[$id]['verpasst']) $anzVerpasst++;
            if (!$werte[$id]['gestrichen']) $sumStreich += $werte[$id]['punkte'];
        }

        $liste[] = [
            'mitglied'      => $m,
            'werte'         => $werte,
            'sumPflicht'    => round($sumPflicht, 2),
            'sumStreich'    => round($sumStreich, 2),
            'total'         => round($sumPflicht + $sumStreich, 2),
            'endstich'      => ($endstichID && isset($werte[$endstichID])) ? $werte[$endstichID]['punkte'] : 0.0,
            'hatResultat'   => $hatResultat,
            'anzGestrichen' => $anzGestrichen,
            'anzVerpasst'   => $anzVerpasst,
            'rang'          => null,
        ];
    }

    // ---- Reihenfolge und Rang ----
    // Mit gewertetem Resultat nach Total, bei Gleichstand nach Endstich; danach alle ohne Resultat nach Name.
    usort($liste, function ($a, $b) {
        if ($a['hatResultat'] !== $b['hatResultat']) return $a['hatResultat'] ? -1 : 1;
        if ($a['hatResultat']) {
            $c = [$b['total'], $b['endstich']] <=> [$a['total'], $a['endstich']];
            if ($c !== 0) return $c;
        }
        return strnatcasecmp($a['mitglied']['Name'] . ' ' . $a['mitglied']['Vorname'],
                             $b['mitglied']['Name'] . ' ' . $b['mitglied']['Vorname']);
    });
    $pos = 0; $rang = 0; $vorher = null;
    foreach ($liste as &$e) {
        if (!$e['hatResultat']) continue;
        $pos++;
        $schluessel = [$e['total'], $e['endstich']];
        if ($schluessel !== $vorher) { $rang = $pos; $vorher = $schluessel; }
        $e['rang'] = $rang;
    }
    unset($e);

    return [
        'definitionen'    => array_values($defs),
        'anzahlStreicher' => $anzahlStreicher,
        'abgeschlossen'   => $abgeschlossen,
        'endstichDatum'   => $endstichDatum,
        'mitglieder'      => $liste,
    ];
}

} // function_exists
