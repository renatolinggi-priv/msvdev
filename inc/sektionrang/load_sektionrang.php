<?php
/**
 * inc/sektionrang/load_sektionrang.php — Tabellenzeilen der Sektionsmeisterschaft-Rangliste.
 * GET: year. Antwort: JSON mit fertigen <tr>-Blöcken für Runde 1 und Runde 2 samt Schnitt.
 */
include '../config.php';
require_once __DIR__ . '/functions.inc.php';
require_once __DIR__ . '/../partials/empty_state.inc.php';

header('Content-Type: application/json; charset=utf-8');

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

/**
 * $pad = Anzahl Leerzeilen, damit beide Runden gleich lang sind und die Schnitt-Blöcke
 * auf gleicher Höhe liegen. Leerzeilen bestehen aus <th>, damit MSVMobileCards.buildCards
 * sie überspringt (baut nur Zeilen mit <td>).
 */
function sektionrangZeilenRunde(array $liste, int $pad = 0): string
{
    if (!$liste) {
        return msv_empty_row(2, 'Keine Resultate gefunden');
    }
    $html = '';
    foreach ($liste as $e) {
        $html .= '<tr>'
               . '<td>' . htmlspecialchars($e['name'], ENT_QUOTES, 'UTF-8') . '</td>'
               . '<td class="result-column fw-semibold">' . $e['punkte'] . '</td>'
               . '</tr>';
    }
    for ($i = 0; $i < $pad; $i++) {
        $html .= '<tr class="pad-row"><th colspan="2">&nbsp;</th></tr>';
    }
    return $html;
}

/**
 * Schnitt-Block unterhalb einer Runde (Regel der Sektionsabrechnungen).
 */
function sektionrangSchnittHtml(?array $s): string
{
    if ($s === null) {
        return '';
    }
    $item = function (string $label, string $value, string $cls = ''): string {
        return '<div class="schnitt-item' . ($cls ? ' ' . $cls : '') . '">'
             . '<span class="schnitt-label">' . $label . '</span>'
             . '<span class="schnitt-value">' . $value . '</span></div>';
    };
    return '<div class="schnitt-box">'
         . $item('Teilnehmer', (string)$s['teilnehmer'])
         . $item('Pflichtteilnehmer', (string)$s['verwendete'])
         . $item('Durchschnitt', number_format($s['durchschnitt'], 2))
         . $item('Zuschlag', $s['zuschlag'] . ' %')
         . $item('Endergebnis', number_format($s['endergebnis'], 3), 'schnitt-final')
         . '</div>';
}

try {
    $daten = sektionrangDaten($conn, $year);
    $n1 = count($daten['runde1']);
    $n2 = count($daten['runde2']);
    $ziel = max($n1, $n2);
    echo json_encode([
        'success'  => true,
        'year'     => $year,
        'runde1'   => sektionrangZeilenRunde($daten['runde1'], $n1 ? $ziel - $n1 : 0),
        'runde2'   => sektionrangZeilenRunde($daten['runde2'], $n2 ? $ziel - $n2 : 0),
        'schnitt1' => sektionrangSchnittHtml($daten['schnitt1']),
        'schnitt2' => sektionrangSchnittHtml($daten['schnitt2']),
        'anzahl'  => [
            'runde1' => $daten['anzahl_runde1'],
            'runde2' => $daten['anzahl_runde2'],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
