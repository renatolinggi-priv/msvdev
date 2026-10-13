<?php
/**
 * Sieger aus den Ranglisten ermitteln (Seite «Sieger der letzten Jahre», Knopf «Aus Ranglisten übernehmen»).
 *
 * Pro Auszeichnung (Tabelle siegerdef, Spalte Bezeichnung) eine Quelle, jeweils dieselbe Rangierung wie
 * die Rangliste:
 *   - Endschiessen-Stiche (Endstich, Schwini, Kunst, Glück, Zabigstich): Abfragen wie in
 *     endschrang/PDFReports.php, aber nur Mitglieder. Das entspricht dem «WP» der Rangliste
 *     (endschWpVergeben: bestplatziertes Mitglied); Gäste stehen in keiner Siegerliste.
 *   - Endschiessen Kat. A/B: Gesamtrangliste endschGesamtAbfrage(), bestplatziertes Mitglied.
 *   - Junioren: Sieger der Jungschützen-Wertung des Endschiessens (siegerJunioren); ohne Jungschützen
 *     «Keine Konkurrenz» ohne Wert, wie in der Liste seit 1997.
 *   - Jahresmeisterschaft, Heimmeisterschaft, Kantonalstich Kat. A/B: die Wanderpreis-Regel
 *     (wanderpreise_regeln) mit demselben Code, ausgeführt wie in wanderpreise/auto_zuordnung.php.
 * Die Kategorie kommt überall aus der heutigen Waffe des Mitglieds, wie in den Ranglisten.
 */

require_once __DIR__ . '/../endschresultate/teilnehmer.inc.php';
require_once __DIR__ . '/../wanderpreise/regel_builder.inc.php'; // wp_validate_regel_sql()

/** Wert so, wie er in sieger.Wert steht: ganze Zahl ohne Nachkommastellen, sonst zwei («263.90»). */
function siegerWertText($wert): string
{
    $f = round((float)$wert, 2);
    return abs($f - round($f)) < 0.005 ? (string)(int)round($f) : number_format($f, 2, '.', '');
}

/** Namen vergleichbar machen: «Judith von Euw» und «von Euw Judith» gelten als dieselbe Person. */
function siegerNameSchluessel(string $name): string
{
    $teile = preg_split('/\s+/u', mb_strtolower(trim($name)), -1, PREG_SPLIT_NO_EMPTY);
    sort($teile);
    return implode(' ', $teile);
}

/** Erste Zeile einer vorbereiteten Abfrage mit einem Jahr-Parameter je «?». */
function siegerErsteZeile(mysqli $conn, string $sql, int $jahr): ?array
{
    $st = $conn->prepare($sql);
    $typen = str_repeat('i', substr_count($sql, '?'));
    $st->bind_param($typen, ...array_fill(0, strlen($typen), $jahr));
    $st->execute();
    $zeile = $st->get_result()->fetch_assoc();
    $st->close();
    return $zeile ?: null;
}

/** Bestplatziertes Mitglied der Gesamtrangliste Endschiessen einer Kategorie. */
function siegerEndschGesamt(mysqli $conn, string $kat, int $jahr): ?array
{
    $q = endschGesamtAbfrage($kat, $jahr, true);
    $st = $conn->prepare($q['sql']);
    $st->bind_param($q['types'], ...$q['params']);
    $st->execute();
    $zeilen = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    $i = endschWpVergeben($zeilen);
    if ($i === null) {
        return null;
    }
    $z = $zeilen[$i];
    return ['name' => endschAnzeigename($z), 'wert' => $z['GesamtTotal']];
}

/**
 * Junioren: Sieger der Jungschützen-Wertung des Endschiessens, gleich gerechnet wie im Absendenbuch
 * (getJungschuetzenResultate): Endstich + Zabig-Punkte + beste Schwini-Passe; bei Gleichstand Endstich, dann das
 * höhere Alter. Jungschützen sind Gäste mit Geburtsdatum. Hat das Endschiessen stattgefunden, aber kein
 * Jungschütze geschossen: «Keine Konkurrenz» ohne Wert; ohne Endschiessen-Resultate gar nichts.
 */
function siegerJunioren(mysqli $conn, int $jahr): array
{
    $endstich = 'COALESCE(Schuss1,0)+COALESCE(Schuss2,0)+COALESCE(Schuss3,0)+COALESCE(Schuss4,0)+COALESCE(Schuss5,0)'
              . '+COALESCE(Schuss6,0)+COALESCE(Schuss7,0)+COALESCE(Schuss8,0)+COALESCE(Schuss9,0)+COALESCE(Schuss10,0)';
    $p1 = 'COALESCE(P1Schuss1,0)+COALESCE(P1Schuss2,0)+COALESCE(P1Schuss3,0)+COALESCE(P1Schuss4,0)+COALESCE(P1Schuss5,0)+COALESCE(P1Schuss6,0)';
    $p2 = 'COALESCE(P2Schuss1,0)+COALESCE(P2Schuss2,0)+COALESCE(P2Schuss3,0)+COALESCE(P2Schuss4,0)+COALESCE(P2Schuss5,0)+COALESCE(P2Schuss6,0)';
    $z = siegerErsteZeile($conn,
        "SELECT g.name, g.vorname, g.nachname, e.E + COALESCE(zb.Z, 0) + COALESCE(sw.S, 0) AS Total, e.E
           FROM endstich_gaeste g
           JOIN (SELECT GastID, SUM($endstich) AS E FROM endstich WHERE Jahr = ? AND GastID IS NOT NULL GROUP BY GastID) e
             ON e.GastID = g.id
           LEFT JOIN (SELECT z.GastID, MAX(" . endschZabigPunkteSql() . ") AS Z FROM zabig z
                       WHERE z.Jahr = ? AND z.GastID IS NOT NULL GROUP BY z.GastID) zb ON zb.GastID = g.id
           LEFT JOIN (SELECT GastID, MAX(GREATEST($p1, $p2)) AS S FROM schwini
                       WHERE Jahr = ? AND GastID IS NOT NULL GROUP BY GastID) sw ON sw.GastID = g.id
          WHERE g.geburtsdatum IS NOT NULL AND g.jahr = ? AND e.E > 0
          ORDER BY Total DESC, e.E DESC, g.geburtsdatum ASC
          LIMIT 1", $jahr);
    if ($z) {
        $name = trim((string)$z['nachname']) !== '' && trim((string)$z['vorname']) !== ''
            ? $z['nachname'] . ' ' . $z['vorname'] : (string)$z['name'];
        return ['name' => $name, 'wert' => $z['Total']];
    }
    // «Keine Konkurrenz» nur, wenn das Jahr schon im heutigen Teilnehmer-Modell erfasst ist (Gäste/Jungschützen aus
    // «Stiche lösen» in endstich_gaeste) und das Endschiessen Resultate hat. Ältere Jahre haben dort keine Daten
    // (z.B. 2024: Sieger nur in der alten Vorlage) – dann kein Vorschlag, damit nichts Erfasstes überschrieben wird.
    $erfasst = siegerErsteZeile($conn,
        "SELECT 1 FROM endstich_gaeste WHERE jahr = ? AND EXISTS (SELECT 1 FROM endstich WHERE Jahr = ? AND ($endstich) > 0) LIMIT 1", $jahr);
    return $erfasst ? ['name' => 'Keine Konkurrenz', 'wert' => ''] : [];
}

/**
 * Erste Zeile einer Wanderpreis-Regel (Platzhalter und Prüfung wie in auto_zuordnung.php).
 * $spalte: Spalte mit dem Resultat (die Standardregeln liefern keine Spalte «resultat»).
 */
function siegerAusRegel(mysqli $conn, string $code, string $spalte, int $jahr): array
{
    $st = $conn->prepare("SELECT sql_query FROM wanderpreise_regeln WHERE regel_code = ? AND aktiv = 1 LIMIT 1");
    $st->bind_param('s', $code);
    $st->execute();
    $sql = $st->get_result()->fetch_column();
    $st->close();
    if ($sql === false || $sql === null) {
        return ['hinweis' => "Wanderpreis-Regel «{$code}» fehlt oder ist inaktiv (Seite «Wanderpreis-Regeln»)."];
    }
    $sql = (string)$sql;
    if (($fehler = wp_validate_regel_sql($sql)) !== null) {
        return ['hinweis' => "Wanderpreis-Regel «{$code}» abgelehnt: {$fehler}."];
    }

    $kat = preg_match('/A$/', $code) ? 'Kat. A' : (preg_match('/B$/', $code) ? 'Kat. B' : '');
    $sql = str_replace(['{jahr}', '{wanderpreis_id}', '{kategorie}'], [(string)$jahr, '0', $kat], $sql);
    if (stripos($sql, 'SET @year') !== false || stripos($sql, 'SET @kategorie') !== false) {
        $sql = preg_replace('/\bSET\s+@year\s*=\s*[^;]+;?\s*/i', '', $sql);
        $sql = preg_replace('/\bSET\s+@kategorie\s*=\s*[^;]+;?\s*/i', '', $sql);
        $sql = str_replace(['@year', '@kategorie'], [(string)$jahr, "'" . $conn->real_escape_string($kat) . "'"], $sql);
    }

    $r = null;
    if (strpos($sql, ';') !== false) {
        $conn->multi_query($sql);
        do {
            if ($tmp = $conn->store_result()) {
                if ($r) $r->free();
                $r = $tmp; // letztes SELECT ist relevant
            }
        } while ($conn->more_results() && $conn->next_result());
    } else {
        $r = $conn->query($sql);
    }
    $zeile = $r instanceof mysqli_result ? $r->fetch_assoc() : null;
    if ($r instanceof mysqli_result) $r->free();
    if (!$zeile) {
        return [];
    }

    $id = (int)($zeile['gewinner_id'] ?? 0);
    $st = $conn->prepare("SELECT CONCAT(Name, ' ', Vorname) FROM mitglieder WHERE ID = ?");
    $st->bind_param('i', $id);
    $st->execute();
    $name = (string)$st->get_result()->fetch_column();
    $st->close();
    if ($name === '') {
        return ['hinweis' => "Wanderpreis-Regel «{$code}» liefert kein gültiges Mitglied."];
    }
    $wert = $zeile[$spalte] ?? $zeile['resultat'] ?? null;
    return ['name' => $name, 'wert' => $wert];
}

/**
 * Quellen je siegerdef.Bezeichnung: callable(mysqli, int $jahr): array
 *   ['name' => 'Name Vorname', 'wert' => Zahl] | [] (keine Resultate) | ['hinweis' => Text]
 */
function siegerQuellen(): array
{
    $stich = function (string $tabelle, string $wertSpalte, string $select, string $where, string $order): callable {
        return function (mysqli $conn, int $jahr) use ($tabelle, $wertSpalte, $select, $where, $order): array {
            $z = siegerErsteZeile($conn,
                "SELECT u.Name, u.Vorname, $select FROM " . endschQuelle($tabelle, ['mitglied']) . " u
                 WHERE $where ORDER BY $order LIMIT 1", $jahr);
            return $z ? ['name' => endschAnzeigename($z), 'wert' => $z[$wertSpalte]] : [];
        };
    };
    $endstichSumme = 'u.Schuss1 + u.Schuss2 + u.Schuss3 + u.Schuss4 + u.Schuss5 + u.Schuss6 + u.Schuss7 + u.Schuss8 + u.Schuss9 + u.Schuss10';
    $p1 = 'COALESCE((u.P1Schuss1 + u.P1Schuss2 + u.P1Schuss3 + u.P1Schuss4 + u.P1Schuss5 + u.P1Schuss6), 0)';
    $p2 = 'COALESCE((u.P2Schuss1 + u.P2Schuss2 + u.P2Schuss3 + u.P2Schuss4 + u.P2Schuss5 + u.P2Schuss6), 0)';
    $zabigRoh = 'u.ZSchuss1 + u.ZSchuss2 + u.ZSchuss3 + u.ZSchuss4 + u.ZSchuss5 + u.ZSchuss6';
    $regel = fn(string $code, string $spalte): callable
        => fn(mysqli $conn, int $jahr): array => siegerAusRegel($conn, $code, $spalte, $jahr);
    $gesamt = fn(string $kat): callable
        => fn(mysqli $conn, int $jahr): array => siegerEndschGesamt($conn, $kat, $jahr) ?? [];

    return [
        // Rangierung wie EndstichReport
        'Endstich' => $stich('endstich', 'Summe',
            "COALESCE($endstichSumme, 0) AS Summe,
             ((u.Schuss1 = 10) + (u.Schuss2 = 10) + (u.Schuss3 = 10) + (u.Schuss4 = 10) + (u.Schuss5 = 10) +
              (u.Schuss6 = 10) + (u.Schuss7 = 10) + (u.Schuss8 = 10) + (u.Schuss9 = 10) + (u.Schuss10 = 10)) AS Anzahl_10",
            "COALESCE($endstichSumme, 0) != 0",
            'Summe DESC, u.Tiefschuss DESC, Anzahl_10 DESC, u.Geburtsdatum ASC, u.Name ASC'),
        // wie SchwiniReport: bessere Passe, dann die andere
        'Schwini' => $stich('schwini', 'Hoechste',
            "GREATEST($p1, $p2) AS Hoechste, LEAST($p1, $p2) AS Tiefste",
            "$p1 != 0 OR $p2 != 0",
            'Hoechste DESC, Tiefste DESC, u.Geburtsdatum ASC, u.Name ASC'),
        // wie KunstReport
        'Kunst' => $stich('kunst', 'Summe',
            "COALESCE(u.KSchuss1 + u.KSchuss2 + u.KSchuss3 + u.KSchuss4 + u.KSchuss5, 0) AS Summe,
             GREATEST(COALESCE(u.KSchuss1, 0), COALESCE(u.KSchuss2, 0), COALESCE(u.KSchuss3, 0),
                      COALESCE(u.KSchuss4, 0), COALESCE(u.KSchuss5, 0)) AS TS",
            'u.KSchuss1 IS NOT NULL AND u.KSchuss1 != 0',
            'Summe DESC, TS DESC, u.Geburtsdatum ASC, u.Name ASC'),
        // wie GlueckReport
        'Glück' => $stich('glueck', 'MaxGlueck',
            "GREATEST(u.GSchuss1, u.GSchuss2, u.GSchuss3) AS MaxGlueck,
             LEAST(GREATEST(u.GSchuss1, u.GSchuss2), GREATEST(u.GSchuss1, u.GSchuss3), GREATEST(u.GSchuss2, u.GSchuss3)) AS Zweit,
             LEAST(u.GSchuss1, u.GSchuss2, u.GSchuss3) AS Dritt",
            'u.GSchuss1 != 0',
            'MaxGlueck DESC, Zweit DESC, Dritt DESC, u.Geburtsdatum ASC, u.Name ASC'),
        // wie ZabigReport: Punkte (0–10 je Schuss), dann bester Hunderterwert
        'Zabigstich' => $stich('zabig', 'Punkte',
            str_replace('z.ZSchuss', 'u.ZSchuss', endschZabigPunkteSql()) . " AS Punkte,
             GREATEST(COALESCE(u.ZSchuss1, 0), COALESCE(u.ZSchuss2, 0), COALESCE(u.ZSchuss3, 0),
                      COALESCE(u.ZSchuss4, 0), COALESCE(u.ZSchuss5, 0), COALESCE(u.ZSchuss6, 0)) AS TS",
            "COALESCE($zabigRoh, 0) != 0",
            'Punkte DESC, TS DESC, u.Geburtsdatum ASC, u.Name ASC'),
        'EndschiessenA'        => $gesamt('Kat. A'),
        'EndschiessenB'        => $gesamt('Kat. B'),
        'HeimmeisterschaftA'   => $regel('heimmeisterschaftA', 'HeimSumme'),
        'HeimmeisterschaftB'   => $regel('heimmeisterschaftB', 'HeimSumme'),
        'KantonalstichA'       => $regel('kantonalstichA', 'KantiSumme'),
        'KantonalstichB'       => $regel('kantonalstichB', 'KantiSumme'),
        'JahresmeisterschaftA' => $regel('jahresmeisterschaftA', 'Total'),
        'JahresmeisterschaftB' => $regel('jahresmeisterschaftB', 'Total'),
        'Junioren'             => fn(mysqli $conn, int $jahr): array => siegerJunioren($conn, $jahr),
    ];
}

/**
 * Vorschlag je Auszeichnung für ein Jahr, verglichen mit den erfassten Siegern.
 * Rückgabe: Liste in der Reihenfolge von siegerdef, je
 *   siegerdef_id, bezeichnung, vorschlag (['name','wert'] | null), hinweis (string),
 *   bisher (Liste ['id','name','wert']),
 *   status: 'neu' (noch keiner erfasst) | 'gleich' | 'wert' (gleiche Person, anderer Wert)
 *           | 'anders' (andere Person) | 'mehrere' (mehrere Einträge, nicht automatisch) | 'leer' (keine Resultate)
 */
function siegerVorschlaege(mysqli $conn, int $jahr): array
{
    $quellen = siegerQuellen();
    $bisher = [];
    $st = $conn->prepare("SELECT ID, Name, Wert, siegerdef FROM sieger WHERE year = ? ORDER BY ID");
    $st->bind_param('i', $jahr);
    $st->execute();
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $b) {
        $bisher[(int)$b['siegerdef']][] = ['id' => (int)$b['ID'], 'name' => (string)$b['Name'], 'wert' => (string)$b['Wert']];
    }
    $st->close();

    $liste = [];
    foreach ($conn->query("SELECT ID, Bezeichnung FROM siegerdef ORDER BY ID")->fetch_all(MYSQLI_ASSOC) as $def) {
        $id = (int)$def['ID'];
        $bez = (string)$def['Bezeichnung'];
        $eintrag = ['siegerdef_id' => $id, 'bezeichnung' => $bez, 'vorschlag' => null, 'hinweis' => '',
                    'bisher' => $bisher[$id] ?? [], 'status' => 'leer'];

        if (!isset($quellen[$bez])) {
            $eintrag['hinweis'] = 'Für diese Auszeichnung gibt es keine Rangliste; bitte von Hand erfassen.';
        } else {
            try {
                $r = $quellen[$bez]($conn, $jahr);
            } catch (Throwable $e) {
                error_log('[sieger_ranglisten] ' . $bez . ' ' . $jahr . ': ' . $e->getMessage());
                $r = ['hinweis' => 'Rangliste konnte nicht gelesen werden (Details im Fehlerprotokoll).'];
            }
            if (!empty($r['name'])) {
                // leerer Wert bleibt leer (Junioren «Keine Konkurrenz»), sonst wie in sieger.Wert
                $eintrag['vorschlag'] = ['name' => $r['name'], 'wert' => ($r['wert'] ?? null) === '' ? '' : siegerWertText($r['wert'] ?? 0)];
            } else {
                $eintrag['hinweis'] = $r['hinweis'] ?? 'Keine Resultate für ' . $jahr . '.';
            }
        }

        $v = $eintrag['vorschlag'];
        $b = $eintrag['bisher'];
        if ($v !== null) {
            if (!$b) {
                $eintrag['status'] = 'neu';
            } elseif (count($b) > 1) {
                $eintrag['status'] = 'mehrere';
            } elseif (siegerNameSchluessel($b[0]['name']) !== siegerNameSchluessel($v['name'])) {
                $eintrag['status'] = 'anders';
            } else {
                $eintrag['status'] = abs((float)$b[0]['wert'] - (float)$v['wert']) < 0.005 ? 'gleich' : 'wert';
            }
        }
        $liste[] = $eintrag;
    }
    return $liste;
}
