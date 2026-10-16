<?php
// load_jm.php
declare(strict_types=1);

// ======= Konfiguration =======
const DEZIMALSTELLEN = 2;   // Für Punkt-Ausgabe

require_once '../config.php'; // $conn (mysqli)
require_once __DIR__ . '/../admin_api_guard.inc.php';
require_once __DIR__ . '/../partials/empty_state.inc.php'; // msv_empty_row() für die Leerzustände
require_once __DIR__ . '/../jm_berechnung.inc.php';         // jmWertung(): gemeinsame JM-Rechnung (auch PDF und Portal)
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)

// Sicheres HTML
function h(?string $str): string {
    return htmlspecialchars((string)$str ?? '', ENT_QUOTES, 'UTF-8');
}

// ======= 1. Parameter einlesen =======
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$kategorie = $_GET['kategorie'] ?? '';
$asJson = !empty($_GET['json']);

// ======= 2. Werten (Werte, Hochrechnung, Streicher, Total, Rang) =======
// Regeln siehe inc/jm_berechnung.inc.php. Vor dem Endstich ist es ein Zwischenstand:
// gestrichen werden dann nur verpasste Anlässe, höchstens so viele wie Streicher.
$wertung = jmWertung($conn, $year, $kategorie !== '' ? ['kategorie' => $kategorie] : []);
$definitions = $wertung['definitionen'];

if (!$definitions) {
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => "Keine Wettbewerbe für Jahr $year gefunden"]);
        exit;
    }
    echo json_encode([
        'thead' => "<tr><th></th></tr>",
        'tbody' => msv_empty_row(99, "Für $year ist noch kein JM-Anlass definiert. Anlegen unter «Jahresmeisterschaft Definition».")
    ]);
    exit;
}

if (!$wertung['mitglieder']) {
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => "Keine Mitglieder für Kategorie '$kategorie' gefunden"]);
        exit;
    }
    echo json_encode([
        'thead' => "<tr><th></th></tr>",
        'tbody' => msv_empty_row(99, "Keine aktiven Mitglieder in $kategorie gefunden. Die Kategorie richtet sich nach der Waffe in den Mitglieder-Stammdaten.")
    ]);
    exit;
}

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($wertung);
    exit;
}

// ======= 3. Hilfsfunktionen für die Ausgabe =======

// Zelleninhalt einer Spalte in der Hauptzeile: Resultat, gestrichen durchgestrichen;
// verpasst «–» (mit Hinweis, ob die 0 gestrichen ist oder zählt); Anlass noch ohne Resultate «–».
function formatCell(array $entry, int $defID): string {
    $w = $entry['werte'][$defID] ?? null;
    if ($w === null) return "&ndash;";
    if ($w['roh'] === null) {
        $tip = $w['gestrichen'] ? 'Verpasst · als Streicher gestrichen' : 'Verpasst · zählt 0';
        return "<span class='text-muted' data-tooltip='$tip' aria-hidden='true'>&ndash;</span><span class='visually-hidden'>$tip</span>";
    }
    $val = number_format($w['punkte'], DEZIMALSTELLEN, '.', '');
    // Durchstreichen allein erreicht keinen Screenreader: «gestrichen» als unsichtbarer Text dazu
    return $w['gestrichen'] ? "<span class='jm-cell-strichen' data-tooltip='Streicher'>$val<span class='visually-hidden'> gestrichen</span></span>" : $val;
}

// Kurzname für den Spaltenkopf: ohne Ordnungszahl («41. ») und ohne «-Schiessen», damit sich
// ähnliche Anlässe unterscheiden («Muota SV Sattel» statt «Muota-Schiessen SV Sattel»).
function jmKurzname(string $bez): string {
    $kurz = trim(preg_replace(['/^\d+\.\s*/u', '/-Schiessen\b/u'], '', $bez));
    return $kurz !== '' ? $kurz : $bez;
}

// ======= 4. HTML Ausgabe als JSON – Rangliste + gruppierte Aufschlüsselung =======
function renderTable(array $wertung): array {
    $today       = date('Y-m-d');
    $definitions = $wertung['definitionen'];
    $fertig      = $wertung['abgeschlossen']; // nach dem Endstich: definitive Rangliste mit Podest

    // Wettbewerbe in zwei fachliche Gruppen aufteilen (für die Detail-Aufschlüsselung)
    $pflichtDefs = []; // Streicher = 0 (zählt immer)
    $streichDefs = []; // Streicher = 1 (Streichresultate)
    foreach ($definitions as $def) {
        if ((int)$def['Streicher'] === 1) $streichDefs[] = $def;
        else                              $pflichtDefs[] = $def;
    }

    // ---- Hauptspalten = die zuletzt STATTGEFUNDENEN Anlässe (nach Datum, chronologisch) ----
    // Auch noch nicht erfasste werden angezeigt (Zelle "–"). Anlässe ohne Datum
    // (Obligatorisch, Sektionsmeisterschaft, Kantonalstich) erscheinen nur im Detail.
    $occurred = [];
    foreach ($definitions as $def) {
        // 20er-Schiessen (Obligatorisch, Feldschiessen) nicht als Spalte anzeigen
        // (andere Skala). Sie zählen weiterhin und erscheinen im Detail unter Pflicht.
        if ((int)$def['Maxpunkte'] === 20) continue;
        if ($def['letztesDatum'] !== null && $def['letztesDatum'] <= $today) {
            $occurred[] = ['def' => $def, 'date' => $def['letztesDatum']];
        }
    }
    usort($occurred, fn($a, $b) => $a['date'] <=> $b['date']); // chronologisch aufsteigend
    $mainCols = array_slice($occurred, -6);                    // letzte 6 (neueste rechts)
    $mainDefs = array_map(fn($x) => $x['def'], $mainCols);
    $numCols  = count($mainDefs);
    $totalColspan = 2 + $numCols + 2; // Rang + Name + Spalten + Total + Toggle

    // ---- Header ----
    $theadHtml  = "<tr>";
    $theadHtml .= "<th scope='col' class='jm-th-rang'>Rang</th>";
    $theadHtml .= "<th scope='col' class='jm-th-name'>Name</th>";
    // Spaltenkopf = Knopf: öffnet auf «Jahresmeisterschaft erfassen» die Erfassung dieses Anlasses
    foreach ($mainDefs as $def) {
        $theadHtml .= "<th scope='col' class='jm-th-result text-center'><button type='button' class='jm-th-anlass' data-jmdef='" . (int)$def['ID'] . "'"
            . " data-tooltip='" . h($def['Bezeichnung']) . " · Resultate erfassen' aria-label='" . h($def['Bezeichnung']) . ", Resultate erfassen'>"
            . "<span class='jm-th-label' aria-hidden='true'>" . h(jmKurzname($def['Bezeichnung'])) . "</span></button></th>";
    }
    $theadHtml .= "<th scope='col' class='jm-th-total text-center'>Total</th>";
    $theadHtml .= "<th scope='col' class='jm-th-toggle'><span class='visually-hidden'>Aufschlüsselung</span></th>";
    $theadHtml .= "</tr>";

    // ---- Body ----
    // Gruppierung: zuerst Schützen mit gewertetem Resultat (Rangliste), danach als
    // separate Gruppe jene ohne Resultat (alphabetisch, ohne Rang).
    $noResultCount = 0;
    foreach ($wertung['mitglieder'] as $e) { if (!$e['hatResultat']) $noResultCount++; }

    $tbodyHtml = "";
    $rowIdx = 0;
    $noResultHeaderShown = false;

    foreach ($wertung['mitglieder'] as $entry) {
        $rowIdx++;
        $hasResult = $entry['hatResultat'];

        // Trennzeile vor der ersten Zeile ohne Resultat
        if (!$hasResult && !$noResultHeaderShown) {
            $noResultHeaderShown = true;
            $tbodyHtml .= "<tr class='jm-group-row'><td colspan='$totalColspan' class='jm-group-cell'>"
                . "<i class='bi bi-dash-circle me-1'></i>Ohne gewertetes JM-Resultat ($noResultCount)</td></tr>";
        }

        $m = $entry['mitglied'];
        $fullname   = h($m['Name'] . " " . $m['Vorname']);
        $pflichtSum = number_format($entry['sumPflicht'], DEZIMALSTELLEN, '.', '');
        $streichSum = number_format($entry['sumStreich'], DEZIMALSTELLEN, '.', '');
        $totalStr   = number_format($entry['total'], DEZIMALSTELLEN, '.', '');
        $rang       = $entry['rang'];
        $rangStr    = $rang !== null ? (string)$rang : "&ndash;";
        $totalTip   = $fertig ? "" : " data-tooltip='Zwischenstand – bis zum Endstich sind nur verpasste Anlässe gestrichen'";

        // ---- Hauptzeile (Rangliste mit letzten Anlässen) ----
        // Podest (DESIGN «Podest in Ranglisten»): erst wenn es eine Rangliste ist, also nach dem Endstich
        $podest = ($fertig && $rang !== null && $rang <= 3) ? " rank-$rang" : '';
        $tbodyHtml .= "<tr class='jm-main-row$podest' data-row='$rowIdx'>";
        $tbodyHtml .= "<td class='text-center " . ($podest ? 'fw-bold' : 'fw-semibold') . " jm-rang-cell'>$rangStr</td>";
        $tbodyHtml .= "<td class='text-nowrap jm-name-cell'>$fullname</td>";
        foreach ($mainDefs as $def) {
            $tbodyHtml .= "<td class='text-center jm-result-cell'>" . formatCell($entry, (int)$def['ID']) . "</td>";
        }
        $tbodyHtml .= "<td class='text-center fw-bold jm-total-cell'$totalTip>$totalStr</td>";
        $tbodyHtml .= "<td class='text-center'>"
            . "<button type='button' class='btn btn-sm btn-link p-0 jm-toggle-btn' data-tooltip='Aufschlüsselung anzeigen' aria-label='Aufschlüsselung anzeigen' aria-expanded='false'>"
            . "<i class='bi bi-chevron-down' aria-hidden='true'></i></button></td>";
        $tbodyHtml .= "</tr>";

        // ---- Detailzeile: zwei gruppierte Listen ----
        $tbodyHtml .= "<tr class='jm-detail-row' data-row='$rowIdx' style='display:none'>";
        $tbodyHtml .= "<td colspan='$totalColspan'>";
        $tbodyHtml .= "<div class='jm-detail-panel'>";
        $tbodyHtml .= "<div class='jm-detail-groups'>";

        // --- Gruppe 1: Pflicht (zählt immer) ---
        $tbodyHtml .= "<div class='jm-detail-group jm-group-pflicht'>";
        $tbodyHtml .= "<div class='jm-detail-group-head'>"
            . "<span class='jm-detail-group-title'><i class='bi bi-pin-angle me-1'></i>Pflicht</span>"
            . "<span class='jm-detail-group-meta'>zählt immer</span></div>";
        $tbodyHtml .= "<div class='jm-detail-lines'>";
        foreach ($pflichtDefs as $def) {
            $w   = $entry['werte'][(int)$def['ID']] ?? null;
            $max = (int)$def['Maxpunkte'];
            if ($w !== null && $w['roh'] !== null) {
                $valStr = number_format($w['punkte'], DEZIMALSTELLEN, '.', '');
                $maxStr = $max > 0 ? "<span class='jm-line-max'>/ $max</span>" : "";
                $tbodyHtml .= "<div class='jm-detail-line'>"
                    . "<span class='jm-line-name'>" . h($def['Bezeichnung']) . "</span>"
                    . "<span class='jm-line-pts'><span class='jm-line-val'>$valStr</span>$maxStr</span></div>";
            } else {
                $tbodyHtml .= "<div class='jm-detail-line jm-line-empty'>"
                    . "<span class='jm-line-name'>" . h($def['Bezeichnung']) . "</span>"
                    . "<span class='jm-line-pts'><span class='jm-line-val'>&ndash;</span></span></div>";
            }
        }
        $tbodyHtml .= "</div>"; // .jm-detail-lines
        $tbodyHtml .= "<div class='jm-detail-subtotal'><span>Zwischentotal</span><span>$pflichtSum</span></div>";
        $tbodyHtml .= "</div>"; // .jm-detail-group

        // --- Gruppe 2: Streichresultate (Streicher = 1) ---
        // Jeder Anlass, der für alle zählt: geschossene nach Punkten, darunter die verpassten (0).
        // Gestrichene sind durchgestrichen und tragen den Text «gestrichen»; hochgerechnete nennen
        // den Rohwert («aus 77 / 80»), damit man ihn mit der Rangliste des Anlasses vergleichen kann.
        // Anlässe, in denen noch niemand ein Resultat hat, zählen als «offen» nur in der Kopfzeile.
        $streichLines = [];
        $gewertet = 0; $gestrichen = 0; $offen = 0;
        foreach ($streichDefs as $def) {
            $w = $entry['werte'][(int)$def['ID']] ?? null;
            if ($w === null) {
                if ($def['letztesDatum'] === null || $def['letztesDatum'] > $today) $offen++;
                continue;
            }
            $streichLines[] = ['def' => $def, 'w' => $w];
            if ($w['gestrichen']) $gestrichen++; else $gewertet++;
        }
        usort($streichLines, fn($a, $b) => [$a['w']['roh'] === null, $b['w']['punkte']] <=> [$b['w']['roh'] === null, $a['w']['punkte']]);

        $metaParts = ["$gewertet gewertet"];
        if ($gestrichen > 0) $metaParts[] = "$gestrichen gestrichen";
        if ($offen      > 0) $metaParts[] = "$offen offen";
        $metaTxt = implode(' · ', $metaParts);

        $tbodyHtml .= "<div class='jm-detail-group jm-group-streich'>";
        $tbodyHtml .= "<div class='jm-detail-group-head'>"
            . "<span class='jm-detail-group-title'><i class='bi bi-type-strikethrough me-1' aria-hidden='true'></i>Streichresultate</span>"
            . "<span class='jm-detail-group-meta'>$metaTxt</span></div>";
        $tbodyHtml .= "<div class='jm-detail-lines'>";
        if ($streichLines) {
            foreach ($streichLines as $ln) {
                $def = $ln['def'];
                $w   = $ln['w'];
                $verpasst = $w['roh'] === null;
                $valStr = number_format($w['punkte'], DEZIMALSTELLEN, '.', '');
                $cls = 'jm-detail-line' . ($w['gestrichen'] ? ' gestrichen' : '') . ($verpasst ? ' jm-line-verpasst' : '');
                $tags = ($verpasst ? "<span class='jm-line-tag'>verpasst</span>" : '')
                      . ($w['gestrichen'] ? "<span class='jm-line-tag'>gestrichen</span>" : '');
                $roh = (!$verpasst && $def['hochgerechnet'])
                    ? "<span class='jm-line-max' data-tooltip='Auf 100 hochgerechnet'>aus " . (int)$w['roh'] . " / " . (int)$def['Maxpunkte'] . "</span>"
                    : '';
                $tbodyHtml .= "<div class='$cls'>"
                    . "<span class='jm-line-name'>" . h($def['Bezeichnung']) . "</span>"
                    . "<span class='jm-line-pts'>$tags<span class='jm-line-val'>$valStr</span>$roh</span></div>";
            }
        } else {
            $tbodyHtml .= "<div class='jm-detail-line jm-line-empty'>"
                . "<span class='jm-line-name text-muted'>Noch kein Anlass mit Streichresultat geschossen</span>"
                . "<span class='jm-line-pts'><span class='jm-line-val'>&ndash;</span></span></div>";
        }
        $tbodyHtml .= "</div>"; // .jm-detail-lines
        $tbodyHtml .= "<div class='jm-detail-subtotal'><span>Zwischentotal</span><span>$streichSum</span></div>";
        $tbodyHtml .= "</div>"; // .jm-detail-group

        $tbodyHtml .= "</div>"; // .jm-detail-groups

        // Gesamttotal; vor dem Endstich als Zwischenstand gekennzeichnet
        if ($fertig) {
            $tbodyHtml .= "<div class='jm-detail-total'>"
                . "<span><i class='bi bi-trophy me-2'></i>Gesamttotal</span>"
                . "<span class='jm-detail-total-val'>$totalStr</span></div>";
        } else {
            $tbodyHtml .= "<div class='jm-detail-total jm-detail-total-offen'>"
                . "<span><i class='bi bi-hourglass-split me-2'></i>Zwischenstand · bis zum Endstich sind nur verpasste Anlässe gestrichen</span>"
                . "<span class='jm-detail-total-val'>$totalStr</span></div>";
        }

        $tbodyHtml .= "</div>"; // .jm-detail-panel
        $tbodyHtml .= "</td></tr>";
    }

    return ['thead' => $theadHtml, 'tbody' => $tbodyHtml];
}

header('Content-Type: application/json; charset=utf-8');
// «stand»: vor dem Endstich zeigt jmresultate.php «Zwischenstand» statt «Rangliste» und nennt das Endstich-Datum
echo json_encode(renderTable($wertung) + ['stand' => [
    'endstichDone'    => $wertung['abgeschlossen'],
    'endstichDatum'   => $wertung['endstichDatum'],
    'anzahlStreicher' => $wertung['anzahlStreicher'],
]]);
