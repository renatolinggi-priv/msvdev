<?php
/**
 * inc/einsatzplanung/rueckmeldung_parse.php – ausgefülltes Word eines Vereins gegen den Plan lesen.
 *
 * Das Dokument stammt aus export_docx.php, deshalb ist die Zuordnung positionsbasiert:
 *   Spalte i ↔ Termin i (Datum wird geprüft), Body-Zeile j ↔ Funktionsgruppe j,
 *   Absatz k in der Zelle ↔ Position k (Reihenfolge Funktion.sort, pos). Leere Absätze verwirft
 *   der Word-Reader – darum druckt der Export offene Positionen als Vereins-Platzhalter.
 *
 * POST multipart: datei (.docx), plan_id, verein=beide|freienbach|wollerau
 * Antwort: {success, vorschlaege:[{slot_id, termin, funktion, verein, verein_label, alt, neu, vorschlag, hinweis}], warnungen:[...]}
 *   vorschlag=true → standardmässig angehakt (Fremdvereins-Position mit neuem Namen)
 *   vorschlag=false → nur Hinweis (MSV-Position geändert, Name entfernt) – Admin entscheidet
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/plan_helpers.inc.php';
require_once __DIR__ . '/docx_builder.inc.php';                 // ep_docx_platzhalter_verein()
require_once __DIR__ . '/../lib/dokument_datei.inc.php';
require_once __DIR__ . '/../einsatzplan_parser/docx_parser.php'; // parseHeaderRows(), getCellLines()

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Table;

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db     = getDB();
$planId = (int)($_POST['plan_id'] ?? 0);
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan || $plan['layout'] !== 'funktion_x_termin') ep_json(['success' => false, 'message' => 'Plan nicht gefunden oder kein Funktionen-Raster'], 404);

$vereinFilter = $_POST['verein'] ?? 'beide';
if (!in_array($vereinFilter, ['beide', 'freienbach', 'wollerau'], true)) $vereinFilter = 'beide';

if (empty($_FILES['datei'])) ep_json(['success' => false, 'message' => 'Keine Datei hochgeladen'], 422);
$check = dokument_datei_pruefen($_FILES['datei']);
if (!$check['ok']) ep_json(['success' => false, 'message' => $check['message']], 422);
if ($check['ext'] !== 'docx') ep_json(['success' => false, 'message' => 'Bitte das ausgefüllte Word (.docx) hochladen'], 422);

try {
    $phpWord = IOFactory::load($_FILES['datei']['tmp_name']);
} catch (Throwable $e) {
    ep_json(['success' => false, 'message' => 'Word konnte nicht gelesen werden: ' . $e->getMessage()], 422);
}
$table = null;
foreach ($phpWord->getSections() as $section) {
    foreach ($section->getElements() as $el) { if ($el instanceof Table) { $table = $el; break 2; } }
}
if (!$table) ep_json(['success' => false, 'message' => 'Keine Tabelle im Dokument gefunden'], 422);

$rows = $table->getRows();
$header = parseHeaderRows($rows);
if (empty($header['dates'])) ep_json(['success' => false, 'message' => 'Kopfzeile mit Terminen nicht erkannt'], 422);

$mitglieder = ep_mitglieder_map($db);
$warnungen  = [];
$vorschlaege = [];

// Spalten ↔ Termine
$termine = $plan['termine'];
$nCols = min(count($header['dates']), count($termine));
if (count($header['dates']) !== count($termine)) {
    $warnungen[] = 'Das Dokument hat ' . count($header['dates']) . ' Terminspalten, der Plan ' . count($termine) . ' – nur die ersten ' . $nCols . ' werden verglichen.';
}
for ($i = 0; $i < $nCols; $i++) {
    if (substr($header['dates'][$i]['datum'], 5) !== substr($termine[$i]['datum'], 5)) {
        $warnungen[] = 'Spalte ' . ($i + 1) . ': Datum im Dokument (' . $header['dates'][$i]['datum'] . ') passt nicht zum Termin ' . $termine[$i]['datum'] . ' – Zuordnung nach Position.';
    }
}

// Body-Zeilen (nicht leere) ↔ Funktionsgruppen
$gruppen = ep_funktionen_gruppiert($plan['funktionen']);
$bodyRows = [];
for ($r = $header['data_start']; $r < count($rows); $r++) {
    $cells = $rows[$r]->getCells();
    if (count($cells) < 2) continue;
    if (trim(getCellFullText($cells[0])) === '') continue;
    $bodyRows[] = $cells;
}
$nRows = min(count($bodyRows), count($gruppen));
if (count($bodyRows) !== count($gruppen)) {
    $warnungen[] = 'Das Dokument hat ' . count($bodyRows) . ' Funktionszeilen, der Plan ' . count($gruppen) . ' – nur die ersten ' . $nRows . ' werden verglichen.';
}

$norm = fn(string $s) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));

for ($j = 0; $j < $nRows; $j++) {
    $g = $gruppen[$j];
    $cells = $bodyRows[$j];
    $gruppeLabel = $g['gruppe'] !== '' ? $g['gruppe'] : ($g['funktionen'][0]['bezeichnung'] ?? '');
    for ($i = 0; $i < $nCols; $i++) {
        $t = $termine[$i];
        if (!isset($cells[$i + 1])) continue;
        $lines = array_values(array_filter(array_map('trim', getCellLines($cells[$i + 1])), fn($l) => $l !== ''));

        // erwartete Positionen in Dokumentreihenfolge
        $erwartet = [];
        foreach ($g['funktionen'] as $f) {
            // Zeilen im Word = grösste Positionszahl der Funktion; Termine mit weniger Positionen enthalten «–»
            for ($pos = 1; $pos <= ep_anzahl_max($plan, $f); $pos++) {
                if ($pos > ep_anzahl_pos($plan, (int)$t['id'], $f)) { $erwartet[] = ['slot' => null, 'funktion' => $f, 'pos' => $pos]; continue; }
                $s = $plan['slot_index'][$t['id'] . '|' . $f['id'] . '|' . $pos] ?? null;
                if ($s) $erwartet[] = ['slot' => $s, 'funktion' => $f, 'pos' => $pos];
            }
        }
        $tlabel = (trim((string)$t['bezeichnung']) !== '' ? $t['bezeichnung'] . ' · ' : '') . ep_datum_lang($t['datum']);
        if (count($lines) !== count($erwartet)) {
            $warnungen[] = 'Zeile «' . $gruppeLabel . '», ' . $tlabel . ': ' . count($lines) . ' Namen im Dokument, ' . count($erwartet) . ' Positionen im Plan – Zelle manuell prüfen.';
            continue;
        }
        foreach ($erwartet as $k => $e) {
            $s = $e['slot']; $f = $e['funktion'];
            $text = $lines[$k];
            if ($s === null) continue;   // Platzhalter «–»: Position gibt es an diesem Termin nicht
            $platzhalter = ep_docx_platzhalter_verein($text);
            $aktuell = ep_slot_text($s, $mitglieder);
            $flabel = (trim((string)$f['gruppe']) !== '' ? $f['gruppe'] . ': ' : '') . $f['bezeichnung'] . ((int)$f['anzahl'] > 1 ? ' (' . $e['pos'] . ')' : '');
            $basis = ['slot_id' => (int)$s['id'], 'termin' => $tlabel, 'funktion' => $flabel, 'verein' => $s['verein'], 'verein_label' => EP_VEREINE[$s['verein']] ?? $s['verein']];

            if ($s['verein'] !== 'msv') {
                if ($vereinFilter !== 'beide' && $s['verein'] !== $vereinFilter) continue;
                $bisher = trim((string)($s['name_text'] ?? ''));
                if ($platzhalter !== null) {
                    // weiterhin Platzhalter: nur melden, wenn vorher ein Name stand
                    if ($bisher !== '') $vorschlaege[] = $basis + ['alt' => $bisher, 'neu' => '', 'vorschlag' => false, 'hinweis' => 'Im Dokument steht wieder der Vereinsname – Name entfernen?'];
                    continue;
                }
                if ($norm($text) === $norm($bisher)) continue;
                $vorschlaege[] = $basis + ['alt' => $bisher, 'neu' => $text, 'vorschlag' => true, 'hinweis' => $bisher === '' ? 'neu gemeldet' : 'geändert'];
            } else {
                // MSV-Position: Abweichung nur melden
                if ($platzhalter === 'msv' && $aktuell === '') continue;
                if ($platzhalter === null && $norm($text) === $norm($aktuell)) continue;
                if ($platzhalter !== null && $platzhalter !== 'msv') {
                    $vorschlaege[] = $basis + ['alt' => $aktuell, 'neu' => '', 'vorschlag' => false, 'hinweis' => 'MSV-Position trägt im Dokument «' . EP_VEREINE[$platzhalter] . '» – Verein im Plan manuell umstellen'];
                    continue;
                }
                $vorschlaege[] = $basis + ['alt' => $aktuell, 'neu' => $platzhalter === 'msv' ? '' : $text, 'vorschlag' => false,
                    'hinweis' => 'MSV-Position im Dokument geändert – nur übernehmen, wenn gewollt'];
            }
        }
    }
}

ep_json(['success' => true, 'vorschlaege' => $vorschlaege, 'warnungen' => $warnungen,
         'message' => count($vorschlaege) . ' Abweichung(en) gefunden']);
