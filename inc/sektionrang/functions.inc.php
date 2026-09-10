<?php
/**
 * inc/sektionrang/functions.inc.php — Datenaufbereitung Sektionsmeisterschaft-Rangliste.
 *
 * Quelle: jmresultate mit Info = 'runde 1' / 'runde 2' zur JMDefinition des Jahres,
 * deren Bezeichnung «Sektionsmeisterschaft» enthält (so erfasst das Anlass-Panel in
 * inc/jmresultate/save_anlass.php). Gemeinsam genutzt von load_sektionrang.php
 * (Bildschirm) und generate_pdf.php (PDF), damit beide dasselbe zeigen.
 *
 * Sortierung: Punkte absteigend, ohne Rangnummern und ohne Gesamtwertung
 * (Wunsch Benutzer 09.09.2026).
 *
 * Schnitt pro Runde: gleiche Regel wie die Sektionsabrechnungen
 * (inc/jmdurchschnitt/calculate_averages.php): nur aktive Mitglieder mit Punkten > 0,
 * die besten N Resultate zählen (N aus jmdurchschnitt_config, mindestens die Hälfte
 * der Teilnehmer), Durchschnitt = Summe zählende ÷ N, Endergebnis mit
 * Beteiligungszuschlag (JMDefinition.Zuschlag) auf die nicht zählenden Resultate.
 */

require_once __DIR__ . '/../jmdurchschnitt/config_helper.php';

/**
 * @return array{
 *   runde1: list<array>, runde2: list<array>, schnitt1: ?array, schnitt2: ?array,
 *   anzahl: int, anzahl_runde1: int, anzahl_runde2: int
 * }
 */
function sektionrangDaten(mysqli $conn, int $year): array
{
    // Beteiligungszuschlag der Sektionsmeisterschaft dieses Jahres (wie Sektionsabrechnungen)
    $zuschlag = 0;
    $stmt = $conn->prepare("SELECT Zuschlag FROM JMDefinition
                             WHERE year = ? AND Bezeichnung LIKE '%Sektionsmeisterschaft%'
                             ORDER BY ID LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $zuschlag = $r ? (int)$r['Zuschlag'] : 0;
        $stmt->close();
    }

    $sql = "SELECT m.ID AS mid, m.Name, m.Vorname, m.status, r.Info, r.Punkte
              FROM jmresultate r
              JOIN JMDefinition d ON d.ID = r.jmdefinitionID
              JOIN mitglieder   m ON m.ID = r.mitgliederID
             WHERE d.year = ?
               AND d.Bezeichnung LIKE '%Sektionsmeisterschaft%'
               AND r.Info IN ('runde 1', 'runde 2')
             ORDER BY m.Name, m.Vorname";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Abfrage fehlgeschlagen: ' . $conn->error);
    }
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();

    $schuetzen = [];
    while ($row = $res->fetch_assoc()) {
        $mid = (int)$row['mid'];
        if (!isset($schuetzen[$mid])) {
            $schuetzen[$mid] = [
                'mid'   => $mid,
                'name'  => trim($row['Name'] . ' ' . $row['Vorname']),
                'aktiv' => ((int)$row['status'] === 1),
                'r1'    => null,
                'r2'    => null,
            ];
        }
        $key = ($row['Info'] === 'runde 2') ? 'r2' : 'r1';
        $p   = (int)$row['Punkte'];
        // Sollte je Runde nur eine Zeile geben; falls doch doppelt, zählt der höhere Wert.
        if ($schuetzen[$mid][$key] === null || $p > $schuetzen[$mid][$key]) {
            $schuetzen[$mid][$key] = $p;
        }
    }
    $stmt->close();

    $runde1 = [];
    $runde2 = [];
    foreach ($schuetzen as $s) {
        if ($s['r1'] !== null) {
            $runde1[] = ['name' => $s['name'], 'punkte' => $s['r1'], 'aktiv' => $s['aktiv']];
        }
        if ($s['r2'] !== null) {
            $runde2[] = ['name' => $s['name'], 'punkte' => $s['r2'], 'aktiv' => $s['aktiv']];
        }
    }

    $runde1 = sektionrangSortieren($runde1);
    $runde2 = sektionrangSortieren($runde2);

    $anzahlZaehlende = getDurchschnittConfig($conn, $year)['anzahl_zaehlende'];

    return [
        'runde1'        => $runde1,
        'runde2'        => $runde2,
        'schnitt1'      => sektionrangSchnitt($runde1, $anzahlZaehlende, $zuschlag),
        'schnitt2'      => sektionrangSchnitt($runde2, $anzahlZaehlende, $zuschlag),
        'anzahl'        => count($schuetzen),
        'anzahl_runde1' => count($runde1),
        'anzahl_runde2' => count($runde2),
    ];
}

/**
 * Schnitt einer Runde nach der Regel der Sektionsabrechnungen.
 * $liste muss absteigend nach 'punkte' sortiert sein.
 *
 * @return ?array{teilnehmer:int, verwendete:int, anzahl_zaehlende:int, durchschnitt:float, zuschlag:int, endergebnis:float}
 */
function sektionrangSchnitt(array $liste, int $anzahlZaehlende, int $zuschlag): ?array
{
    $teilnehmer = array_values(array_filter($liste, fn($e) => $e['punkte'] > 0 && !empty($e['aktiv'])));
    $n = count($teilnehmer);
    if ($n === 0) {
        return null;
    }

    $verwendete    = calculateUsedResults($n, $anzahlZaehlende);
    $punkte        = array_column($teilnehmer, 'punkte');
    $summeZaehlend = array_sum(array_slice($punkte, 0, $verwendete));
    $summeRest     = array_sum(array_slice($punkte, $verwendete));

    return [
        'teilnehmer'       => $n,
        'verwendete'       => $verwendete,
        'anzahl_zaehlende' => $anzahlZaehlende,
        'durchschnitt'     => round($summeZaehlend / $verwendete, 2),
        'zuschlag'         => $zuschlag,
        'endergebnis'      => round(($summeZaehlend + ($zuschlag * $summeRest) / 100) / $verwendete, 3),
    ];
}

/**
 * Sortiert nach 'punkte' absteigend, dann Name.
 *
 * @param list<array> $liste
 * @return list<array>
 */
function sektionrangSortieren(array $liste): array
{
    usort($liste, function ($a, $b) {
        $cmp = $b['punkte'] <=> $a['punkte'];
        if ($cmp !== 0) return $cmp;
        return strcasecmp($a['name'], $b['name']);
    });

    return $liste;
}
