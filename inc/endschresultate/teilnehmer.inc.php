<?php
/**
 * Endschiessen: Teilnehmer der Resultat-Tabellen (seit Migration 094).
 *
 * endstich, schwini, kunst, glueck, zabig und endstich_sieunder führen eine Person entweder über
 * MitgliedID (mitglieder) oder über GastID (endstich_gaeste: Gäste und Jungschützen aus «Stiche lösen»,
 * Jungschütze = Gast mit Geburtsdatum). Erfassung, Ranglisten und Absendenbuch holen die Personen hier,
 * damit Name, Typ, Waffe und Kranzlimite überall gleich bestimmt werden.
 *
 * Wer wo erscheint (Entscheid 12.10.2026):
 *   - Einzelstiche (Endstich, Schwini, Kunst, Glück, Zabig, Differenzler): Mitglieder und Gäste.
 *   - Jungschützen nur, wo sie schon vorher standen: Endstich, Schwini, Zabig (PDF) und ihre eigene Liste.
 *   - Gesamtrangliste: Gäste nur in Kat. B und nur, wenn sie alle gewerteten Stiche geschossen haben.
 *   - Kranz für Gäste und Jungschützen nach der Limite ihrer Waffe. Wanderpreise bleiben Mitgliedern
 *     vorbehalten: «WP» erhält das bestplatzierte Mitglied (endschWpVergeben).
 */

const ENDSCH_TYPEN_ALLE        = ['mitglied', 'gast', 'js'];
const ENDSCH_TYPEN_MITGLIED_GAST = ['mitglied', 'gast'];
const ENDSCH_RESULTAT_TABELLEN = ['endstich', 'schwini', 'kunst', 'glueck', 'zabig', 'endstich_sieunder'];

/**
 * Teilnehmer-Schlüssel der Erfassung: «m123» (Mitglied) oder «g45» (Gast/Jungschütze).
 * Rückgabe ['spalte' => 'MitgliedID'|'GastID', 'id' => int, 'schluessel' => string] oder null.
 */
function endschTeilnehmer(?string $schluessel): ?array
{
    if (!preg_match('/^([mg])(\d+)$/', trim((string)$schluessel), $t) || (int)$t[2] <= 0) {
        return null;
    }
    return [
        'spalte'     => $t[1] === 'm' ? 'MitgliedID' : 'GastID',
        'id'         => (int)$t[2],
        'schluessel' => $t[1] . (int)$t[2],
    ];
}

/** Schlüssel aus MitgliedID/GastID bilden (eine der beiden ist gesetzt). */
function endschSchluessel($mitgliedId, $gastId): string
{
    return $mitgliedId ? 'm' . (int)$mitgliedId : 'g' . (int)$gastId;
}

/** SQL-Bedingung (auf die Aliase x = Resultat-Tabelle, tg = endstich_gaeste) für die gewünschten Typen. */
function endschTypBedingung(array $typen): string
{
    $bed = [];
    if (in_array('mitglied', $typen, true)) $bed[] = 'x.MitgliedID IS NOT NULL';
    if (in_array('gast', $typen, true))     $bed[] = '(x.GastID IS NOT NULL AND tg.geburtsdatum IS NULL)';
    if (in_array('js', $typen, true))       $bed[] = '(x.GastID IS NOT NULL AND tg.geburtsdatum IS NOT NULL)';
    return $bed ? '(' . implode(' OR ', $bed) . ')' : '0';
}

/**
 * Abgeleitete Tabelle für FROM (… AS q): alle Spalten der Resultat-Tabelle eines Jahres plus
 *   TeilnehmerTyp ('mitglied'|'gast'|'js'), Schluessel, Name, Vorname, Geburtsdatum, Kategorie,
 *   Kranz_Endstich, Kranz_Kunst.
 * Geburtsdatum: Mitglieder wie gespeichert, Gäste ohne Datum 2100-01-01 (bei Gleichstand hinten).
 * Waffe: Mitglied aus den Stammdaten, Gast aus der Lösung des Jahres, sonst aus dem Gast.
 * Erwartet genau einen Parameter: das Jahr.
 */
function endschQuelle(string $tabelle, array $typen = ENDSCH_TYPEN_ALLE): string
{
    if (!in_array($tabelle, ENDSCH_RESULTAT_TABELLEN, true)) {
        throw new InvalidArgumentException('Unbekannte Resultat-Tabelle: ' . $tabelle);
    }
    $typBed = endschTypBedingung($typen);
    return "(
        SELECT x.*,
            CASE WHEN x.MitgliedID IS NOT NULL THEN 'mitglied'
                 WHEN tg.geburtsdatum IS NULL THEN 'gast' ELSE 'js' END AS TeilnehmerTyp,
            CONCAT(IF(x.MitgliedID IS NOT NULL, 'm', 'g'), COALESCE(x.MitgliedID, x.GastID)) AS Schluessel,
            COALESCE(CONVERT(tm.Name USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                     CONVERT(tg.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) AS Name,
            CONVERT(IF(x.MitgliedID IS NOT NULL, COALESCE(tm.Vorname, ''), '') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Vorname,
            IF(x.MitgliedID IS NOT NULL, tm.Geburtsdatum, COALESCE(tg.geburtsdatum, DATE('2100-01-01'))) AS Geburtsdatum,
            tw.Kategorie, tw.Kranz_Endstich, tw.Kranz_Kunst
        FROM `$tabelle` x
        LEFT JOIN mitglieder tm ON tm.ID = x.MitgliedID
        LEFT JOIN endstich_gaeste tg ON tg.id = x.GastID
        LEFT JOIN Waffen tw ON tw.ID = COALESCE(
            tm.WaffenID,
            (SELECT MAX(es.waffen_id) FROM endstich_selection es WHERE es.gast_id = x.GastID AND es.jahr = x.Jahr),
            tg.waffen_id)
        WHERE x.Jahr = ? AND $typBed
    )";
}

/**
 * Wanderpreis-Vermerk: Wanderpreise gehen nur an Mitglieder. Liefert den Index (0-basiert) der
 * Zeile, die «WP» erhält: das bestplatzierte Mitglied. null, wenn keines dabei ist.
 * $zeilen: Ranglisten-Zeilen in Rangfolge, mit Feld TeilnehmerTyp (fehlt es: Mitglied).
 */
function endschWpVergeben(array $zeilen): ?int
{
    foreach (array_values($zeilen) as $i => $z) {
        if (($z['TeilnehmerTyp'] ?? 'mitglied') === 'mitglied') {
            return $i;
        }
    }
    return null;
}

/** Anzeigename einer Ranglisten-Zeile (Gäste tragen den ganzen Namen in «Name»). */
function endschAnzeigename(array $z): string
{
    return trim(($z['Name'] ?? '') . ' ' . ($z['Vorname'] ?? ''));
}

/**
 * Quelle der Partner-Rangliste (Endstich plus beste Schwini-Passe): Gäste ohne Geburtsdatum sowie Mitglieder,
 * die unter «Resultate erfassen» als Partnerin bzw. Partner markiert sind (endstich_partnerrang, Migration 096).
 * Spalten: ID (Schlüssel), PartnerName, Endstich_Summe, Schwini_Passe1, Schwini_Passe2.
 * Erwartet zwei Parameter: Jahr (Gäste), Jahr (Mitglieder).
 */
function endschPartnerQuelle(): string
{
    $endstich = "(COALESCE(e.Schuss1, 0) + COALESCE(e.Schuss2, 0) + COALESCE(e.Schuss3, 0) + COALESCE(e.Schuss4, 0) + COALESCE(e.Schuss5, 0) +
                  COALESCE(e.Schuss6, 0) + COALESCE(e.Schuss7, 0) + COALESCE(e.Schuss8, 0) + COALESCE(e.Schuss9, 0) + COALESCE(e.Schuss10, 0))";
    $p1 = "(COALESCE(s.P1Schuss1, 0) + COALESCE(s.P1Schuss2, 0) + COALESCE(s.P1Schuss3, 0) + COALESCE(s.P1Schuss4, 0) + COALESCE(s.P1Schuss5, 0) + COALESCE(s.P1Schuss6, 0))";
    $p2 = "(COALESCE(s.P2Schuss1, 0) + COALESCE(s.P2Schuss2, 0) + COALESCE(s.P2Schuss3, 0) + COALESCE(s.P2Schuss4, 0) + COALESCE(s.P2Schuss5, 0) + COALESCE(s.P2Schuss6, 0))";
    return "
        SELECT CONCAT('g', g.id) AS ID,
               CONVERT(g.name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS PartnerName,
               $endstich AS Endstich_Summe, $p1 AS Schwini_Passe1, $p2 AS Schwini_Passe2
        FROM endstich_gaeste g
        LEFT JOIN endstich e ON e.GastID = g.id AND e.Jahr = g.jahr
        LEFT JOIN schwini s ON s.GastID = g.id AND s.Jahr = g.jahr
        WHERE g.jahr = ? AND g.geburtsdatum IS NULL
        UNION ALL
        SELECT CONCAT('m', m.ID),
               CONVERT(CONCAT(m.Name, ' ', m.Vorname) USING utf8mb4) COLLATE utf8mb4_unicode_ci,
               $endstich, $p1, $p2
        FROM endstich_partnerrang pr
        JOIN mitglieder m ON m.ID = pr.MitgliedID
        LEFT JOIN endstich e ON e.MitgliedID = m.ID AND e.Jahr = pr.Jahr
        LEFT JOIN schwini s ON s.MitgliedID = m.ID AND s.Jahr = pr.Jahr
        WHERE pr.Jahr = ?";
}

/** Sie-und-Er-Wertung: Summe der eindeutigen Werte (jeder Wert zählt einmal, 0 und leer zählen nicht). */
function endschSieUndErTotal(array $schuesse): int
{
    $eindeutig = [];
    foreach ($schuesse as $s) {
        if ($s !== null && $s !== '' && (int)$s > 0) {
            $eindeutig[(int)$s] = (int)$s;
        }
    }
    return array_sum($eindeutig);
}

/**
 * Sie-und-Er-Paare eines Jahres aus endstich_sieunder.
 * Jede Person hat eine Zeile mit fünf Schüssen und dem Verweis auf ihre Partnerin bzw. ihren Partner.
 * Ein Paar erscheint einmal: zuerst genannt wird das Mitglied (bei zwei Mitgliedern bzw. zwei Gästen
 * die alphabetisch erste Person). Personen ohne verknüpfte Partnerin erscheinen allein (PartnerName «–»).
 * Rückgabe im bisherigen Format der Rangliste: Name, Vorname, PartnerName, SieErSchuss1–5 (Partner/in),
 * SieErSchuss6–10 (erstgenannte Person), SpecialTotal; nur Paare mit mindestens einem Schuss.
 */
function endschSieUndErPaare(mysqli $conn, int $jahr): array
{
    $sql = "SELECT q.Schluessel, q.TeilnehmerTyp, q.Name, q.Vorname,
                   q.Schuss1, q.Schuss2, q.Schuss3, q.Schuss4, q.Schuss5,
                   CASE WHEN q.PartnerMitgliedID IS NOT NULL THEN CONCAT('m', q.PartnerMitgliedID)
                        WHEN q.PartnerGastID IS NOT NULL THEN CONCAT('g', q.PartnerGastID) END AS PartnerSchluessel,
                   COALESCE(CONVERT(CONCAT(pm.Name, ' ', pm.Vorname) USING utf8mb4) COLLATE utf8mb4_unicode_ci,
                            CONVERT(pg.name USING utf8mb4) COLLATE utf8mb4_unicode_ci) AS PartnerAnzeige
            FROM " . endschQuelle('endstich_sieunder', ENDSCH_TYPEN_ALLE) . " q
            LEFT JOIN mitglieder pm ON pm.ID = q.PartnerMitgliedID
            LEFT JOIN endstich_gaeste pg ON pg.id = q.PartnerGastID
            ORDER BY q.Name, q.Vorname";
    $st = $conn->prepare($sql);
    if (!$st) {
        throw new RuntimeException('Sie und Er laden: ' . $conn->error);
    }
    $st->bind_param('i', $jahr);
    $st->execute();
    $personen = [];
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
        $personen[$p['Schluessel']] = $p;
    }
    $st->close();

    $schuesse = fn(?array $p) => $p ? [$p['Schuss1'], $p['Schuss2'], $p['Schuss3'], $p['Schuss4'], $p['Schuss5']] : [null, null, null, null, null];
    $paare = [];
    $erledigt = [];
    foreach ($personen as $k => $p) {
        if (isset($erledigt[$k])) {
            continue;
        }
        $erledigt[$k] = true;
        $pk = $p['PartnerSchluessel'];
        $partner = ($pk !== null && isset($personen[$pk]) && !isset($erledigt[$pk])) ? $personen[$pk] : null;
        if ($partner) {
            $erledigt[$pk] = true;
        }
        // Zuerst das Mitglied nennen
        $a = $p;
        $b = $partner;
        if ($b && $a['TeilnehmerTyp'] !== 'mitglied' && $b['TeilnehmerTyp'] === 'mitglied') {
            [$a, $b] = [$b, $a];
        }
        $partnerName = $b ? endschAnzeigename($b) : ($p['PartnerAnzeige'] ?? '');
        $zeile = [
            'ID'            => $a['Schluessel'],
            'TeilnehmerTyp' => $a['TeilnehmerTyp'],
            'Name'          => $a['Name'],
            'Vorname'       => $a['Vorname'],
            'PartnerName'   => $partnerName !== '' ? $partnerName : '–',
        ];
        $sb = $schuesse($b);
        $sa = $schuesse($a);
        for ($i = 0; $i < 5; $i++) {
            $zeile['SieErSchuss' . ($i + 1)] = $sb[$i];
            $zeile['SieErSchuss' . ($i + 6)] = $sa[$i];
        }
        $alle = array_merge($sb, $sa);
        if (!array_filter($alle, fn($s) => $s !== null && (int)$s > 0)) {
            continue;
        }
        $zeile['SpecialTotal'] = endschSieUndErTotal($alle);
        $paare[] = $zeile;
    }
    return $paare;
}
