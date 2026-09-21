<?php
/**
 * dashboard_phasen.inc.php
 *
 * Saisonale Steuerung der Startseiten-Kacheln (inc/home.php).
 *
 * Grundgedanke: Das Dashboard zeigt oben ("Jetzt aktuell") nur, was in diesem
 * Moment der Saison gebraucht wird. Alles andere rutscht in eine eingeklappte
 * Liste ("Weitere Bereiche") und bleibt damit erreichbar - es wird nichts
 * versteckt, nur einsortiert.
 *
 * Alle Termine kommen aus Daten, die es bereits gibt. Es braucht keine neue
 * Konfiguration:
 *
 *   - JMDefinition + JMSchiesstage  -> Endstich, Absenden, Vereinscup,
 *                                      Feldschiessen, erster Schiesstag der Saison
 *   - einsatz_plan_termine          -> naechster Arbeitseinsatz (Obli/Feld/Chilbi/Schlossturm)
 *   - cupFinalResults               -> Cup abgeschlossen?
 *   - endstich_selection/endstich   -> Fortschritt Stichausgabe bzw. Resultate
 *
 * WICHTIG - fail open: Fehlt ein Anker (z.B. weil der Endstich fuer das Jahr
 * noch nicht erfasst ist), bleibt die Kachel sichtbar und traegt den Hinweis
 * "kein Datum hinterlegt". Ein vergessener Eintrag in der JM-Definition darf
 * niemals eine Funktion unerreichbar machen.
 */

// ---------------------------------------------------------------------------
// Vorlauf / Nachlauf in Tagen. Bewusst als Konstanten - bei Bedarf spaeter
// nach settings umziehen.
// ---------------------------------------------------------------------------
const MSV_DASH_VORLAUF_STICHAUSGABE = 30;  // Stichverkauf startet 1 Monat vor dem Endstich
const MSV_DASH_NACHLAUF_STICHAUSGABE = 7;  // Nachzuegler nach dem Endstich
const MSV_DASH_VORLAUF_JM            = 7;  // JM-Erfassung kurz vor dem ersten Schiesstag
const MSV_DASH_NACHLAUF_RANGLISTE    = 7;  // Rangliste bleibt nach dem Absenden offen
const MSV_DASH_VORLAUF_EINSATZ       = 42; // Arbeitseinsaetze: 6 Wochen Vorlauf
const MSV_DASH_NACHLAUF_EINSATZ      = 7;

/**
 * Differenz in ganzen Tagen zwischen zwei Y-m-d-Daten ($bis - $von).
 * Liefert null, wenn ein Datum fehlt oder unbrauchbar ist.
 */
function msvDashTage(?string $von, ?string $bis): ?int
{
    if (!$von || !$bis) {
        return null;
    }
    try {
        $a = new DateTimeImmutable($von . ' 00:00:00');
        $b = new DateTimeImmutable($bis . ' 00:00:00');
    } catch (Exception $e) {
        return null;
    }
    return (int)$a->diff($b)->format('%r%a');
}

/** Verschiebt ein Y-m-d-Datum um $tage (negativ = zurueck). */
function msvDashPlus(?string $datum, int $tage): ?string
{
    if (!$datum) {
        return null;
    }
    try {
        $d = new DateTimeImmutable($datum . ' 00:00:00');
    } catch (Exception $e) {
        return null;
    }
    return $d->modify(($tage >= 0 ? '+' : '-') . abs($tage) . ' days')->format('Y-m-d');
}

/** 2026-10-10 -> 10.10.2026 */
function msvDashDatum(?string $datum): string
{
    if (!$datum) {
        return '';
    }
    try {
        return (new DateTimeImmutable($datum))->format('d.m.Y');
    } catch (Exception $e) {
        return $datum;
    }
}

/**
 * Datumsbereich kompakt: "10.10.2026", "21.–22.11.2026", "28.11.–06.12.2026"
 * oder, ueber den Jahreswechsel, "28.12.2026–03.01.2027".
 */
function msvDashDatumBereich(?string $von, ?string $bis): string
{
    if (!$von) {
        return '';
    }
    if (!$bis || $bis === $von) {
        return msvDashDatum($von);
    }
    try {
        $a = new DateTimeImmutable($von);
        $b = new DateTimeImmutable($bis);
    } catch (Exception $e) {
        return msvDashDatum($von) . '–' . msvDashDatum($bis);
    }
    if ($a->format('Y') !== $b->format('Y')) {
        return $a->format('d.m.Y') . '–' . $b->format('d.m.Y');
    }
    if ($a->format('m') !== $b->format('m')) {
        return $a->format('d.m.') . '–' . $b->format('d.m.Y');
    }
    return $a->format('d.') . '–' . $b->format('d.m.Y');
}

/**
 * "in 24 Tagen" / "heute" / "vor 3 Tagen" - relativ zu $heute.
 */
function msvDashRelativ(?string $datum, string $heute): string
{
    $t = msvDashTage($heute, $datum);
    if ($t === null) {
        return '';
    }
    if ($t === 0)  return 'heute';
    if ($t === 1)  return 'morgen';
    if ($t === -1) return 'gestern';
    return $t > 0 ? "in $t Tagen" : 'vor ' . abs($t) . ' Tagen';
}

/**
 * Sammelt die Saison-Anker eines Jahres.
 *
 * @return array{cup:?array,endstich:?array,absenden:?array,feld:?array,
 *               chilbi:?array,saisonstart:?string,einsatz:?array,
 *               folgejahr_start:?string,cup_fertig:bool,
 *               stich_loesungen:int,endstich_resultate:int}
 */
function msvDashboardAnker(mysqli $conn, int $jahr, ?string $heute = null): array
{
    $heute = $heute ?: date('Y-m-d');
    $anker = [
        'cup'                => null,
        'endstich'           => null,
        'absenden'           => null,
        'feld'               => null,
        'chilbi'             => null,
        'saisonstart'        => null,
        'einsatz'            => null,
        'folgejahr_start'    => null,
        'cup_fertig'         => false,
        'stich_loesungen'    => 0,
        'endstich_resultate' => 0,
    ];

    // --- Anlaesse mit echten Schiesstagen (JMSchiesstage, nicht der Freitext) ---
    $sql = "SELECT jd.Bezeichnung, jd.Info, jd.Maxpunkte,
                   MIN(s.schiesstag) AS von, MAX(s.schiesstag) AS bis
            FROM JMDefinition jd
            JOIN JMSchiesstage s ON s.jm_id = jd.ID
            WHERE jd.year = ? AND jd.hidden = 0
            GROUP BY jd.ID, jd.Bezeichnung, jd.Info, jd.Maxpunkte
            ORDER BY von ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $jahr);
        $stmt->execute();
        $res = $stmt->get_result();

        // Bezeichnungs-Muster -> Anker-Schluessel. Erster Treffer gewinnt,
        // die Liste ist nach Datum sortiert.
        $muster = [
            'vereinscup'    => 'cup',
            'endstich'      => 'endstich',
            'absenden'      => 'absenden',
            'feldschiessen' => 'feld',
        ];

        while ($row = $res->fetch_assoc()) {
            $name = mb_strtolower($row['Bezeichnung'] ?? '');
            foreach ($muster as $needle => $key) {
                if ($anker[$key] === null && mb_strpos($name, $needle) !== false) {
                    $anker[$key] = [
                        'label' => $row['Bezeichnung'],
                        'von'   => $row['von'],
                        'bis'   => $row['bis'],
                    ];
                }
            }
            // Saisonstart = erster Schiesstag eines wertenden Anlasses
            if ((int)$row['Info'] === 0 && (int)$row['Maxpunkte'] > 0) {
                if ($anker['saisonstart'] === null || $row['von'] < $anker['saisonstart']) {
                    $anker['saisonstart'] = $row['von'];
                }
            }
        }
        $stmt->close();
    }

    // --- Chilbi kommt aus den wichtigen Terminen, nicht aus der JM-Definition ---
    $sql = "SELECT MIN(`date`) AS von, MAX(`date`) AS bis
            FROM wichtige_termine
            WHERE year = ? AND name LIKE '%Chilbi%'";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $jahr);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && $row['von']) {
            $anker['chilbi'] = ['label' => 'Chilbi', 'von' => $row['von'], 'bis' => $row['bis']];
        }
        $stmt->close();
    }

    // --- Erster Schiesstag der naechsten Saison (fuer die Vorbereitungs-Kacheln) ---
    $sql = "SELECT MIN(s.schiesstag) AS von
            FROM JMDefinition jd
            JOIN JMSchiesstage s ON s.jm_id = jd.ID
            WHERE jd.year = ? AND jd.hidden = 0 AND jd.Info = 0 AND jd.Maxpunkte > 0";
    $folgejahr = $jahr + 1;
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $folgejahr);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $anker['folgejahr_start'] = $row['von'] ?? null;
        $stmt->close();
    }

    // --- Naechster Arbeitseinsatz ueber alle Plaene (Obli/Feld/Chilbi/Schlossturm) ---
    $sql = "SELECT p.titel, p.typ, p.status,
                   MIN(t.datum) AS von, MAX(t.datum) AS bis
            FROM einsatz_plaene p
            JOIN einsatz_plan_termine t ON t.plan_id = p.id
            WHERE t.datum IS NOT NULL
            GROUP BY p.id, p.titel, p.typ, p.status
            HAVING bis >= ?
            ORDER BY von ASC
            LIMIT 1";
    $grenze = msvDashPlus($heute, -MSV_DASH_NACHLAUF_EINSATZ);
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $grenze);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && $row['von']) {
            $anker['einsatz'] = [
                'label' => $row['titel'],
                'von'   => $row['von'],
                'bis'   => $row['bis'],
            ];
        }
        $stmt->close();
    }

    // --- Fortschritts-Zaehler ---
    $anker['cup_fertig']         = msvDashCount($conn, "SELECT COUNT(*) AS n FROM cupFinalResults WHERE Year = ?", $jahr) > 0;
    $anker['stich_loesungen']    = msvDashCount($conn, "SELECT COUNT(DISTINCT COALESCE(mitglied_id, -gast_id)) AS n FROM endstich_selection WHERE jahr = ?", $jahr);
    $anker['endstich_resultate'] = msvDashCount($conn, "SELECT COUNT(*) AS n FROM endstich WHERE Jahr = ?", $jahr);

    return $anker;
}

/** Kleiner Helfer fuer COUNT-Abfragen mit genau einem int-Parameter. */
function msvDashCount(mysqli $conn, string $sql, int $param): int
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $param);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['n'] ?? 0);
}

/**
 * Anlaesse des Jahres, deren letzter Schiesstag vorbei ist und die noch kein
 * einziges Resultat haben.
 *
 * Ersetzt die fruehere Schaetzung ueber den Monatsnamen im Freitext
 * `Schiesstage` durch die echten Termine aus JMSchiesstage.
 */
function msvDashboardAusstehend(mysqli $conn, int $jahr, string $heute, int $limit = 10): array
{
    $sql = "SELECT jd.ID, jd.Bezeichnung, MAX(s.schiesstag) AS letzter
            FROM JMDefinition jd
            JOIN JMSchiesstage s ON s.jm_id = jd.ID
            WHERE jd.year = ?
              AND jd.hidden = 0
              AND jd.Info = 0
              AND jd.Erweitert = 0
              AND jd.Maxpunkte > 0
            GROUP BY jd.ID, jd.Bezeichnung, jd.Reihenfolge
            HAVING letzter < ?
               AND (SELECT COUNT(*) FROM jmresultate jr WHERE jr.jmdefinitionID = jd.ID) = 0
            ORDER BY letzter ASC, jd.Reihenfolge ASC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('isi', $jahr, $heute, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    $stmt->close();
    return $out;
}

/**
 * Baut die Kachel-Liste und entscheidet pro Kachel, ob sie in "Jetzt aktuell"
 * gehoert.
 *
 * Jede Kachel:
 *   id, titel, desc, icon, iconClass, link, dauerhaft (bool),
 *   aktiv (bool), hinweis (string, nur aktive), status (string, nur inaktive)
 */
function msvDashboardKarten(mysqli $conn, int $jahr, string $heute, int $ausstehendCount = 0): array
{
    $a = msvDashboardAnker($conn, $jahr, $heute);

    $karten = [];

    // --- Dauerhaft sichtbar ---------------------------------------------
    $karten[] = [
        'id' => 'munition', 'titel' => 'Munitionverkauf', 'desc' => 'Munitionskäufe erfassen',
        'icon' => 'bi-cart-check', 'iconClass' => 'red', 'link' => 'munitionskauf.php',
        'dauerhaft' => true, 'aktiv' => true, 'hinweis' => '',
    ];
    $karten[] = [
        'id' => 'heim', 'titel' => 'Heimmeisterschaft', 'desc' => 'Resultate erfassen',
        'icon' => 'bi-house', 'iconClass' => '', 'link' => 'heimresultate.php',
        'dauerhaft' => true, 'aktiv' => true, 'hinweis' => '',
    ];
    $karten[] = [
        'id' => 'kanti', 'titel' => 'Kantonalstich', 'desc' => 'Resultate erfassen',
        'icon' => 'bi-geo-alt', 'iconClass' => '', 'link' => 'kantiresultate.php',
        'dauerhaft' => true, 'aktiv' => true, 'hinweis' => '',
    ];
    $karten[] = [
        'id' => 'mitglieder', 'titel' => 'Mitgliederverwaltung', 'desc' => 'Mitglieder verwalten',
        'icon' => 'bi-people', 'iconClass' => '', 'link' => 'mitgliederverwaltung.php',
        'dauerhaft' => true, 'aktiv' => true, 'hinweis' => '',
    ];
    $karten[] = [
        'id' => 'portal', 'titel' => 'Mitgliederportal', 'desc' => 'Portal-Ansicht öffnen',
        'icon' => 'bi-box-arrow-up-right', 'iconClass' => 'green', 'link' => '../portal/dashboard.php',
        'dauerhaft' => true, 'aktiv' => true, 'hinweis' => '',
    ];

    // --- Jahresmeisterschaft: ab kurz vor dem ersten Schiesstag bis Absenden ---
    $jmVon  = msvDashPlus($a['saisonstart'], -MSV_DASH_VORLAUF_JM);
    $jmBis  = $a['absenden']['bis'] ?? null;
    $karte  = msvDashFenster([
        'id' => 'jm', 'titel' => 'Jahresmeisterschaft', 'desc' => 'Resultate erfassen',
        'icon' => 'bi-trophy', 'iconClass' => '', 'link' => 'jmresultate.php',
    ], $heute, $jmVon, $jmBis);
    // Solange Anlaesse ohne Resultate offen sind, bleibt die Kachel oben -
    // auch nach dem Absenden. Nachtragen muss immer moeglich sein.
    if (!$karte['aktiv'] && $ausstehendCount > 0) {
        $karte['aktiv']  = true;
        $karte['status'] = '';
    }
    // Hinweis nur setzen, wenn msvDashFenster nicht schon "kein Datum
    // hinterlegt" gemeldet hat - sonst wuerde eine leere Saison mit
    // "alle Anlässe erfasst" beschoenigt.
    if ($karte['aktiv'] && $karte['hinweis'] === '') {
        $karte['hinweis'] = $ausstehendCount > 0
            ? ($ausstehendCount === 1 ? '1 Anlass ohne Resultate' : $ausstehendCount . ' Anlässe ohne Resultate')
            : 'alle Anlässe erfasst';
    }
    $karten[] = $karte;

    // --- CUP: ab dem Cup-Tag, bis die Final-Resultate erfasst sind ---
    // Der Cup kann nicht vorgeschossen werden, darum kein Vorlauf.
    $cupVon = $a['cup']['von'] ?? null;
    $karte = msvDashFenster([
        'id' => 'cup', 'titel' => 'CUP', 'desc' => 'CUP Resultate erfassen',
        'icon' => 'bi-journals', 'iconClass' => '', 'link' => 'cup.php',
    ], $heute, $cupVon, null);
    if ($a['cup_fertig']) {
        // Final erfasst -> Saison erledigt, unabhaengig vom Datum.
        $karte['aktiv']  = false;
        $karte['status'] = 'abgeschlossen';
    } elseif ($karte['aktiv'] && $cupVon) {
        $karte['hinweis'] = 'Final noch offen';
    }
    $karten[] = $karte;

    // --- Endschiessen Stichausgabe: 1 Monat vor bis 1 Woche nach dem Endstich ---
    $endstichTag = $a['endstich']['von'] ?? null;
    $karte = msvDashFenster([
        'id' => 'stichausgabe', 'titel' => 'Endschiessen Stichausgabe', 'desc' => 'Stiche ausgeben',
        'icon' => 'bi-bullseye', 'iconClass' => 'red', 'link' => 'endschloesen.php',
    ], $heute,
        msvDashPlus($endstichTag, -MSV_DASH_VORLAUF_STICHAUSGABE),
        msvDashPlus($a['endstich']['bis'] ?? null, MSV_DASH_NACHLAUF_STICHAUSGABE));
    if ($karte['aktiv'] && $endstichTag && $karte['hinweis'] === '') {
        $teile = ['Endstich ' . msvDashRelativ($endstichTag, $heute)];
        if ($a['stich_loesungen'] > 0) {
            $teile[] = $a['stich_loesungen'] . ' Personen gelöst';
        }
        $karte['hinweis'] = implode(' · ', $teile);
    }
    $karten[] = $karte;

    // --- Endschiessen Resultate: ab dem Endstich bis zum Absenden ---
    $karte = msvDashFenster([
        'id' => 'endresultate', 'titel' => 'Endschiessen', 'desc' => 'Resultate erfassen',
        'icon' => 'bi-calendar-event', 'iconClass' => '', 'link' => 'endresultate.php',
    ], $heute, $endstichTag, $a['absenden']['bis'] ?? null);
    if ($karte['aktiv'] && $karte['hinweis'] === '') {
        $karte['hinweis'] = $a['endstich_resultate'] > 0
            ? $a['endstich_resultate'] . ' Resultate erfasst'
            : 'noch keine Resultate';
    }
    $karten[] = $karte;

    // --- Endschiessen Rangliste: ab dem Endstich bis kurz nach dem Absenden ---
    $karten[] = msvDashFenster([
        'id' => 'endschrang', 'titel' => 'Endschiessen Rangliste', 'desc' => 'Rangliste und Auswertung',
        'icon' => 'bi-list-ol', 'iconClass' => 'info', 'link' => 'endschrang.php',
    ], $heute, $endstichTag, msvDashPlus($a['absenden']['bis'] ?? null, MSV_DASH_NACHLAUF_RANGLISTE));

    // --- Einsatzplanung: 6 Wochen vor dem naechsten Arbeitseinsatz ---
    $karte = msvDashFenster([
        'id' => 'einsatz', 'titel' => 'Einsatzplanung', 'desc' => 'Arbeitseinsätze planen',
        'icon' => 'bi-diagram-3', 'iconClass' => '', 'link' => 'einsatzplanung.php',
    ], $heute,
        msvDashPlus($a['einsatz']['von'] ?? null, -MSV_DASH_VORLAUF_EINSATZ),
        msvDashPlus($a['einsatz']['bis'] ?? null, MSV_DASH_NACHLAUF_EINSATZ));
    if ($karte['aktiv'] && !empty($a['einsatz'])) {
        // Laeuft der Einsatz bereits, ist "vor 19 Tagen" irrefuehrend.
        $zeit = ($heute >= $a['einsatz']['von'] && $heute <= $a['einsatz']['bis'])
            ? 'läuft'
            : msvDashRelativ($a['einsatz']['von'], $heute);
        $karte['hinweis'] = $a['einsatz']['label'] . ' · ' . $zeit;
    }
    $karten[] = $karte;

    // --- Saisonvorbereitung: immer dann, wenn gerade keine Saison laeuft ---
    //
    // Vor dem ersten Schiesstag  -> die laufende Saison wird vorbereitet.
    // Nach dem Absenden          -> die naechste Saison wird vorbereitet.
    // Dazwischen (Saison laeuft) -> die Kacheln rutschen nach unten.
    //
    // Das deckt auch den Januar ab: dort ist $jahr bereits das neue Jahr, und
    // vorbereitet wird genau dieses - nicht das uebernaechste.
    $vorbJahr = null;
    if ($a['saisonstart'] === null && ($a['absenden']['bis'] ?? null) === null) {
        $vorbJahr = $jahr;                       // fail open: keine Anker bekannt
    } elseif ($a['saisonstart'] !== null && $heute < $a['saisonstart']) {
        $vorbJahr = $jahr;
    } elseif (($a['absenden']['bis'] ?? null) !== null && $heute > $a['absenden']['bis']) {
        $vorbJahr = $jahr + 1;
    }

    foreach ([
        ['termine',      'Wichtige Termine', 'Terminplan pflegen',        'bi-calendar-week', 'wichtigetermine.php'],
        ['jmdefinition', 'Anlässe',          'JM-Definition der Saison',  'bi-calendar-plus', 'jmdefinition.php'],
    ] as [$id, $titel, $desc, $icon, $link]) {
        $karte = [
            'id' => $id, 'titel' => $titel, 'desc' => $desc,
            'icon' => $icon, 'iconClass' => '', 'link' => $link,
            'dauerhaft' => false,
            'aktiv'     => $vorbJahr !== null,
            'hinweis'   => $vorbJahr !== null ? 'Saison ' . $vorbJahr . ' vorbereiten' : '',
            'status'    => $vorbJahr !== null ? '' : 'Saison läuft',
        ];
        $karten[] = $karte;
    }

    return ['karten' => $karten, 'anker' => $a];
}

/**
 * Entscheidet anhand eines Fensters [von, bis], ob eine Kachel aktiv ist.
 *
 * fail open: Ist weder von noch bis bekannt, bleibt die Kachel aktiv und
 * traegt den Hinweis, dass kein Datum hinterlegt ist. Offene Grenzen (null)
 * gelten als unbegrenzt.
 */
function msvDashFenster(array $karte, string $heute, ?string $von, ?string $bis): array
{
    $karte['dauerhaft'] = false;
    $karte['hinweis']   = '';
    $karte['status']    = '';

    if ($von === null && $bis === null) {
        $karte['aktiv']   = true;
        $karte['hinweis'] = 'kein Datum hinterlegt';
        return $karte;
    }

    $nochNicht = ($von !== null && $heute < $von);
    $vorbei    = ($bis !== null && $heute > $bis);

    $karte['aktiv'] = !$nochNicht && !$vorbei;
    if ($nochNicht) {
        $karte['status'] = 'ab ' . msvDashDatum($von);
    } elseif ($vorbei) {
        $karte['status'] = 'abgeschlossen';
    }
    return $karte;
}
