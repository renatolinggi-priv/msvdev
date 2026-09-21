<?php
/**
 * inc/helferabrechnung/xlsx_builder.inc.php – Excel-Export der Helferabrechnung auf Basis der Arbeitsmappe
 * des Benutzers (dat/Helferabrechnung_Vorlage.xlsx = «Helferstunden Schlossturmschiessen», 7 Blätter).
 *
 * Die Vorlage wird geladen und nur in den Datenbereichen befüllt – alle Formeln (SUMIFS/INDEX/MATCH),
 * Formate, Validierungen (x/OK, JA/NEIN) und Spaltenbreiten bleiben erhalten; Excel rechnet beim Öffnen neu.
 *   Uebersicht        Titel, Datengrundlage, Schalter B5 (JA/NEIN) – Rest Formeln
 *   Original PDF      Abbild des Einsatzplans (Funktion × 4 Schichten, Name + x/OK je Verein); nur bei genau 4 Schichten
 *   Zuteilungen       eine Zeile je besetzter Position (A Einsatz-ID, C Funktion, D Name, E Verein, F x/OK, G Stunden manuell, I Bemerkung);
 *                     Nachträge (manuelle Zeilen «Nachtrag Einsatz») als Einsatz-ID «N» mit Stunden manuell
 *   Stammdaten        Einsätze E1…En (Datum, Zeit, Treffpunkt, Dauer = Pauschale), Vereine A14:A16
 *   Pro Person        Name + Verein (Stunden per Formel)
 *   Vor- und Nacharbeiten  manuelle Zeilen vorarbeit + ok_funktion (Status x)
 *   Korrekturen       reine Formeln (Zeilen mit Bemerkung)
 * Grenzen der Vorlage: 6 Einsätze (Stammdaten A4:A9), 199 Zuteilungen, 60 Vor-/Nacharbeiten.
 *
 * Semantik-Unterschied zur App: Zeile «Diverse Vor- & Nacharbeiten» der Vorlage enthält auch die OK-Funktionen
 * (die App weist sie separat aus). Anwesenheit «nicht da» → Status leer (0 Std., «offene Position») + Bemerkung.
 */
if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/abrechnung.inc.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Reihenfolge der Vereine in der Vorlage (Stammdaten A14:A16, Spalten C:E in Uebersicht/Original PDF). */
const HA_XLSX_VEREINE = ['freienbach', 'msv', 'wollerau'];

/** @param array $a Bündel aus ha_abrechnung() */
function ha_xlsx_erzeugen(array $a, string $zielPfad): void
{
    $vorlage = __DIR__ . '/dat/Helferabrechnung_Vorlage.xlsx';
    if (!is_file($vorlage)) throw new RuntimeException('Excel-Vorlage fehlt: inc/helferabrechnung/dat/Helferabrechnung_Vorlage.xlsx');
    $plan = $a['plan'];
    $termine = $plan['termine'];
    $label = fn(string $k): string => EP_VEREINE[$k] ?? $k;

    $nachtraege  = array_values(array_filter($a['manuell'], fn($m) => $m['kategorie'] === 'einsatz'));
    $vorarbeiten = array_values(array_filter($a['manuell'], fn($m) => $m['kategorie'] !== 'einsatz'));
    if (count($termine) + ($nachtraege ? 1 : 0) > 6) throw new RuntimeException('Die Excel-Vorlage fasst höchstens 6 Einsätze (Stammdaten A4:A9) – der Plan hat ' . count($termine) . ' Schichten' . ($nachtraege ? ' plus Nachträge' : ''));
    if (count($a['zuteilungen']) + count($nachtraege) > 199) throw new RuntimeException('Die Excel-Vorlage fasst höchstens 199 Zuteilungen');
    if (count($vorarbeiten) > 60) throw new RuntimeException('Die Excel-Vorlage fasst höchstens 60 Vor-/Nacharbeiten');

    $ss = IOFactory::createReader('Xlsx')->load($vorlage);

    // Einsatz-IDs je Schicht in Planreihenfolge
    $ids = []; foreach ($termine as $i => $t) $ids[(int)$t['id']] = 'E' . ($i + 1);

    // ---------- Uebersicht ----------
    $ue = $ss->getSheetByNameOrThrow('Uebersicht');
    $ue->setCellValue('A2', $plan['titel'] . '  ·  Auswertung der Einsatzliste nach Verein');
    $ue->setCellValue('A3', 'Datengrundlage: Einsatzplan «' . $plan['titel'] . '» aus der App (' . (EP_STATUS[$plan['status']] ?? $plan['status']) . '), Stand ' . date('d.m.Y H:i'));
    $ue->setCellValueExplicit('B5', $a['ok'] ? 'JA' : 'NEIN', DataType::TYPE_STRING);
    $ue->setCellValue('A56', 'Nachträge: Zeilen mit Einsatz-ID «N» stammen aus dem Tool (manuelle Zeilen «Nachtrag Einsatz»); ihre Stunden stehen in «Stunden manuell». Anwesenheit «nicht da» = Status leer (keine Stunden), mit Bemerkung.');
    try { $ue->getSheetView()->setTopLeftCell('A1'); $ue->setSelectedCell('A1'); } catch (Throwable $e) {}

    // ---------- Stammdaten ----------
    $sd = $ss->getSheetByNameOrThrow('Stammdaten');
    for ($r = 4; $r <= 9; $r++) foreach (['A', 'B', 'C', 'D', 'E', 'F', 'H'] as $c) $sd->setCellValue($c . $r, null);   // G = Formel bleibt
    $r = 4;
    foreach ($termine as $t) {
        $hatP = isset($t['pauschale_std']) && $t['pauschale_std'] !== '' && $t['pauschale_std'] !== null;
        $sd->setCellValue("A$r", $ids[(int)$t['id']]);
        $sd->setCellValue("B$r", ExcelDate::PHPToExcel(new DateTime($t['datum'])));
        $sd->setCellValue("C$r", ep_zeit_text($t));
        $sd->setCellValue("D$r", (string)($t['info'] ?? ''));
        $sd->setCellValue("E$r", ep_termin_stunden($t));
        $sd->setCellValue("F$r", 0);
        $sd->setCellValue("H$r", ha_datum_lang($t['datum']) . ($t['bezeichnung'] ? ' · ' . $t['bezeichnung'] : '') . ' · ' . ($hatP ? 'Pauschale' : 'Schichtdauer (keine Pauschale gesetzt)') . ' ' . ha_fmt(ep_termin_stunden($t)));
        $r++;
    }
    if ($nachtraege) {
        $sd->setCellValue("A$r", 'N'); $sd->setCellValue("C$r", '–'); $sd->setCellValue("E$r", 0); $sd->setCellValue("F$r", 0);
        $sd->setCellValue("H$r", 'Nachträge aus dem Tool (manuelle Zeilen «Nachtrag Einsatz») – Stunden stehen in Zuteilungen, Spalte «Stunden manuell»');
        $r++;
    }
    if ($r <= 9) $sd->setCellValue("H$r", 'freie Zeile für einen zusätzlichen Einsatz');
    foreach (HA_XLSX_VEREINE as $i => $k) $sd->setCellValue('A' . (14 + $i), $label($k));

    // ---------- Zuteilungen ----------
    $zt = $ss->getSheetByNameOrThrow('Zuteilungen');
    $fIdx = []; foreach ($plan['funktionen'] as $i => $f) $fIdx[(int)$f['id']] = $i;
    $tIdx = []; foreach ($termine as $i => $t) $tIdx[(int)$t['id']] = $i;
    $zuteilungen = $a['zuteilungen'];
    usort($zuteilungen, fn($x, $y) => [$fIdx[$x['funktion_id']] ?? 999, $tIdx[$x['termin_id']] ?? 999, $x['pos']] <=> [$fIdx[$y['funktion_id']] ?? 999, $tIdx[$y['termin_id']] ?? 999, $y['pos']]);
    $anzZeilen = count($zuteilungen) + count($nachtraege);
    for ($r = 2; $r <= 200; $r++) {
        foreach (['A', 'C', 'D', 'E', 'F', 'G', 'I'] as $c) $zt->setCellValue($c . $r, null);
        $zt->getRowDimension($r)->setRowHeight(-1);
        ha_xlsx_zeilenstil($zt, $r, $r <= $anzZeilen + 1 ? ($r % 2 === 0 ? 2 : 3) : 100, 'A', 'J');   // Vorlage: gefüllte Zeilen gebändert, leere gelb
    }
    $r = 2;
    foreach ($zuteilungen as $z) {
        $zt->setCellValue("A$r", $ids[$z['termin_id']] ?? '');
        $zt->setCellValue("C$r", $z['funktion']);
        $zt->setCellValue("D$r", $z['person']);
        $zt->setCellValue("E$r", $label($z['verein']));
        $bem = $z['bemerkung'];
        if ($z['anwesend'] === 0) $bem = trim('Anwesenheit «nicht da» – keine Stunden. ' . $bem);
        else $zt->setCellValueExplicit("F$r", $z['ok'] ? 'OK' : 'x', DataType::TYPE_STRING);
        if ($z['korrektur'] !== null) $zt->setCellValue("G$r", $z['korrektur']);
        if ($bem !== '') $zt->setCellValue("I$r", $bem);
        $r++;
    }
    foreach ($nachtraege as $m) {
        $zt->setCellValue("A$r", 'N');
        $zt->setCellValue("C$r", $m['taetigkeit']);
        $zt->setCellValue("D$r", $m['person']);
        $zt->setCellValue("E$r", $label($m['verein']));
        $zt->setCellValueExplicit("F$r", 'x', DataType::TYPE_STRING);
        $zt->setCellValue("G$r", (float)$m['stunden']);
        $zt->setCellValue("I$r", trim((string)($m['bemerkung'] ?? '')) !== '' ? $m['bemerkung'] : 'Nachtrag (manuelle Zeile im Tool)');
        $r++;
    }
    $zt->freezePane('A2');

    // ---------- Pro Person (nur Zuteilungen, wie die Formeln der Vorlage) ----------
    $pp = $ss->getSheetByNameOrThrow('Pro Person');
    $personen = ha_pro_person($zuteilungen, $nachtraege);
    $letzte = max(71, count($personen) + 1);
    $formelC = $pp->getCell('C2')->getValue(); $formelD = $pp->getCell('D2')->getValue();
    for ($r = 2; $r <= $letzte; $r++) {
        $pp->setCellValue("A$r", null); $pp->setCellValue("B$r", null);
        if ($r > 71) { $pp->setCellValue("C$r", str_replace('$A2', '$A' . $r, $formelC)); $pp->setCellValue("D$r", str_replace('$A2', '$A' . $r, $formelD)); }
        ha_xlsx_zeilenstil($pp, $r, $r <= count($personen) + 1 ? ($r % 2 === 0 ? 2 : 3) : 60, 'A', 'D');
    }
    $r = 2;
    foreach ($personen as $p) { $pp->setCellValue("A$r", $p['name']); $pp->setCellValue("B$r", $label($p['verein'])); $r++; }
    $pp->setAutoFilter('A1:D' . $letzte);
    $pp->freezePane('A2');

    // ---------- Vor- und Nacharbeiten ----------
    $vn = $ss->getSheetByNameOrThrow('Vor- und Nacharbeiten');
    for ($r = 5; $r <= 64; $r++) foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $c) $vn->setCellValue($c . $r, null);
    $r = 5;
    foreach ($vorarbeiten as $m) {
        $vn->setCellValue("A$r", $m['taetigkeit']);
        $vn->setCellValue("B$r", $m['person']);
        $vn->setCellValue("C$r", $label($m['verein']));
        $vn->setCellValue("D$r", (float)$m['stunden']);
        $vn->setCellValueExplicit("E$r", 'x', DataType::TYPE_STRING);
        $vn->setCellValue("F$r", trim((string)($m['bemerkung'] ?? '')) !== '' ? $m['bemerkung'] : (HA_KATEGORIEN[$m['kategorie']] ?? ''));
        $r++;
    }

    // ---------- Original PDF: Abbild des Einsatzplans (nur Layout A mit genau 4 Schichten) ----------
    $op = $ss->getSheetByName('Original PDF');
    if ($op) {
        if (($plan['layout'] ?? 'funktion_x_termin') === 'funktion_x_termin' && count($termine) === 4 && isset($plan['slot_index'])) {
            ha_xlsx_original_pdf($op, $plan, $a['mitglieder'], $label);
        } else {
            $ss->removeSheetByIndex($ss->getIndex($op));
        }
    }

    $ss->setActiveSheetIndex(0);
    $w = new Xlsx($ss);
    $w->setPreCalculateFormulas(false);   // Excel rechnet beim Öffnen (fullCalcOnLoad)
    $w->save($zielPfad);
    $ss->disconnectWorksheets();
}

/** Zellstile einer Vorlagenzeile ($quelle) auf Zeile $ziel übertragen (Spalten $von..$bis). */
function ha_xlsx_zeilenstil(Worksheet $s, int $ziel, int $quelle, string $von, string $bis): void
{
    if ($ziel === $quelle) return;
    foreach (range($von, $bis) as $c) $s->duplicateStyle($s->getStyle($c . $quelle), $c . $ziel);
}

/**
 * Blatt «Original PDF» füllen: Zeile 4 Datum, 5 Zeit, 6 Treffpunkt, 7 Kopf; ab Zeile 8 je Funktion × Position
 * eine Zeile mit Name + x/OK in der Vereinsspalte; Totalzeilen (COUNTIF) bleiben Formeln, Kontrollzeile = Ist.
 */
function ha_xlsx_original_pdf(Worksheet $op, array $plan, array $mitglieder, callable $label): void
{
    $bloecke = [['B', 'C', 'D', 'E'], ['F', 'G', 'H', 'I'], ['J', 'K', 'L', 'M'], ['N', 'O', 'P', 'Q']];
    $kurz = fn(string $k): string => preg_replace('/^(SV|MSV)\s+/u', '', $label($k));
    $op->setCellValue('A1', 'Original-Einsatzliste (Abbild des Einsatzplans)');
    $op->setCellValue('A2', '1:1-Abbild des Einsatzplans «' . $plan['titel'] . '» aus der App zur manuellen Kontrolle. Dieses Blatt enthält keine Auswertungslogik – ausgewertet wird das Blatt «Zuteilungen».');
    foreach ($plan['termine'] as $i => $t) {
        [$nc, $c1, $c2, $c3] = $bloecke[$i];
        $op->setCellValue($nc . '4', ha_datum_lang($t['datum']));
        $op->setCellValue($nc . '5', ep_zeit_text($t));
        $op->setCellValue($nc . '6', (string)($t['info'] ?? ''));
        foreach ([$c1, $c2, $c3] as $j => $vc) $op->setCellValue($vc . '7', $kurz(HA_XLSX_VEREINE[$j]));
    }

    // Datenzeilen aufbauen
    $zeilen = [];
    foreach ($plan['funktionen'] as $f) {
        $max = ep_anzahl_max($plan, $f);
        for ($pos = 1; $pos <= $max; $pos++) {
            $zeile = ['funktion' => trim(($f['gruppe'] ? $f['gruppe'] . ': ' : '') . $f['bezeichnung']), 'slots' => []];
            foreach ($plan['termine'] as $i => $t) {
                $s = $plan['slot_index'][$t['id'] . '|' . $f['id'] . '|' . $pos] ?? null;
                $zeile['slots'][$i] = $s && ep_slot_fix($s) ? $s : null;
            }
            $zeilen[] = $zeile;
        }
    }
    $verfuegbar = 33;                                   // Zeilen 8..40 der Vorlage
    $extra = max(0, count($zeilen) - $verfuegbar);
    if ($extra > 0) $op->insertNewRowBefore(40, $extra); // innerhalb des COUNTIF-Bereichs → Formeln wachsen mit
    $letzteDaten = 40 + $extra;
    for ($r = 8; $r <= $letzteDaten; $r++) {
        foreach (range('A', 'Q') as $c) $op->setCellValue($c . $r, null);
        if ($r > 8) ha_xlsx_zeilenstil($op, $r, 8, 'A', 'Q');
    }
    $r = 8; $kontrolle = [];
    foreach ($zeilen as $z) {
        $op->setCellValue("A$r", $z['funktion']);
        foreach ($z['slots'] as $i => $s) {
            if (!$s) continue;
            [$nc, $c1, $c2, $c3] = $bloecke[$i];
            $op->setCellValue($nc . $r, ep_slot_text($s, $mitglieder));
            $vi = array_search($s['verein'] ?? 'msv', HA_XLSX_VEREINE, true);
            if ($vi === false) continue;
            $vc = [$c1, $c2, $c3][$vi];
            $istOk = (int)($s['ok'] ?? 0) === 1;
            $op->setCellValueExplicit($vc . $r, $istOk ? 'OK' : 'x', DataType::TYPE_STRING);
            if (!$istOk) $kontrolle[$vc] = ($kontrolle[$vc] ?? 0) + 1;
        }
        $r++;
    }
    // Kontrollzeile (in der Vorlage von Hand aus dem PDF): Ist-Zahl der «x» je Spalte → Differenz 0
    $kz = 43 + $extra;
    foreach ($bloecke as [$nc, $c1, $c2, $c3]) foreach ([$c1, $c2, $c3] as $vc) $op->setCellValue($vc . $kz, $kontrolle[$vc] ?? 0);
    $op->freezePane('B8');
}
