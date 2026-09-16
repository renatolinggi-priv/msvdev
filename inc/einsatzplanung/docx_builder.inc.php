<?php
/**
 * inc/einsatzplanung/docx_builder.inc.php – Word-Export eines Einsatzplans (PhpWord, from scratch).
 *
 * Zwei Ansichten, für JEDEN Plan verfügbar (gleicher Datenbestand, andere Blickrichtung):
 *
 *  «funktionen» (Funktionen × Termine) – Nachbau der bisherigen Obli/Feld-Dokumente:
 *     A4 quer, eine Tabelle. Zeile 1 Titel (über alle Spalten), Kopf «Funktion» + pro Termin
 *     Bezeichnung / «Wochentag d. Monat» / Zeit. Pro Funktionsgruppe eine Zeile: links Gruppenname
 *     (fett) und je Position der Funktionsname, rechts je Position genau EIN Absatz mit dem Namen.
 *     Offene Positionen tragen den Vereinsnamen als Platzhalter («MSV Wilen», «SV Freienbach»,
 *     «SV Wollerau») – damit hat jede Zelle exakt so viele Absätze wie Positionen, was der
 *     Rückimport (rueckmeldung_parse.php) positionsgenau auswertet.
 *     Layout person_x_schicht (Chilbi): eine Tabelle PRO TAG (sonst acht schmale Spalten), Zellen
 *     listen die eingeteilten Personen, fehlende gemäss Soll erscheinen als «offen».
 *
 *  «personen» (Personen × Termine) – Nachbau des bisherigen Chilbi-Excels:
 *     Name | Vorname | pro Termin eine Spalte (Datum über die Schichten eines Tages zusammengefasst,
 *     Zeitzeile), Zelle = Funktion(en) der Person. Unten eine Zeile «Besetzung» mit Ist/Soll je Termin.
 *
 * Kein Template, weil die Spaltenzahl (Termine) variabel ist.
 */
if (!class_exists(\PhpOffice\PhpWord\PhpWord::class)) require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/plan_helpers.inc.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

const EP_DOCX_BREITE = 16838 - 2 * 567;   // A4 quer abzüglich Ränder (twips)

/** Platzhaltertext einer Position im Word (auch für offene MSV-Positionen). */
function ep_docx_slot_text(array $slot, array $mitglieder): string
{
    $t = ep_slot_text($slot, $mitglieder);
    if ($t !== '') return $t;
    return EP_VEREINE[$slot['verein'] ?? 'msv'] ?? 'MSV Wilen';
}

/** Ist ein Zellentext einer der Vereins-Platzhalter? Liefert den Vereinsschlüssel oder null. */
function ep_docx_platzhalter_verein(string $text): ?string
{
    $t = mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)));
    foreach (EP_VEREINE as $key => $label) {
        if ($t === mb_strtolower($label)) return $key;
    }
    if ($t === 'msv' || $t === 'wilen') return 'msv';
    return null;
}

/** Standard-Ansicht eines Plans: Chilbi → Personen, sonst Funktionen. */
function ep_docx_standard_ansicht(array $plan): string
{
    return $plan['layout'] === 'person_x_schicht' ? 'personen' : 'funktionen';
}

/**
 * DOCX erzeugen und unter $zielPfad speichern.
 * @param array  $plan       ep_plan_laden()
 * @param array  $mitglieder ep_mitglieder_map()
 * @param string $ansicht    'funktionen' | 'personen' | '' (= Standard des Layouts)
 */
function ep_docx_erzeugen(array $plan, array $mitglieder, string $zielPfad, string $ansicht = ''): void
{
    if ($ansicht !== 'funktionen' && $ansicht !== 'personen') $ansicht = ep_docx_standard_ansicht($plan);
    if (isset($plan['zellen'])) $plan = ep_plan_ohne_vorschlaege($plan);   // Einteilungs-Vorschläge erst nach Übernahme

    $pw = new PhpWord();
    $pw->setDefaultFontName('Arial');
    $pw->setDefaultFontSize(11);
    $pw->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language(\PhpOffice\PhpWord\Style\Language::DE_CH));

    $section = $pw->addSection(['orientation' => 'landscape', 'marginTop' => 567, 'marginBottom' => 567, 'marginLeft' => 567, 'marginRight' => 567, 'headerHeight' => 284, 'footerHeight' => 284]);
    $p0 = ['spaceAfter' => 0, 'spaceBefore' => 0];
    $pw->addTableStyle('EpTabelle', ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 60]);

    if ($ansicht === 'personen') {
        ep_docx_ansicht_personen($section, $plan, $mitglieder, $p0);
    } else {
        // eine Tabelle mit allen Terminen (auch Chilbi: 8 Schichten → schmalere Spalten, Kurzdatum, 9 pt)
        ep_docx_ansicht_funktionen($section, $plan, $mitglieder, $p0, $plan['termine'], '');
    }

    // Fusstext
    $fuss = trim((string)($plan['fusstext'] ?? ''));
    if ($fuss !== '') {
        $section->addTextBreak();
        foreach (preg_split('/\r?\n/', $fuss) as $i => $zeile) {
            $section->addText(trim($zeile), ['bold' => $i === 0, 'size' => 11], ['spaceAfter' => 60]);
        }
    }

    IOFactory::createWriter($pw, 'Word2007')->save($zielPfad);
}

/** Titelzeile über die ganze Tabelle. */
function ep_docx_titelzeile($table, string $text, int $spalten): void
{
    $table->addRow();
    $c = $table->addCell(EP_DOCX_BREITE, ['gridSpan' => $spalten, 'bgColor' => 'D9D9D9', 'valign' => 'center']);
    $c->addText($text, ['bold' => true, 'size' => 14], ['spaceAfter' => 40, 'spaceBefore' => 40]);
}

/**
 * Ansicht Funktionen × Termine für die übergebenen Termine (Teilmenge bei «pro Tag»).
 */
function ep_docx_ansicht_funktionen($section, array $plan, array $mitglieder, array $p0, array $termine, string $titelZusatz): void
{
    $istA = $plan['layout'] === 'funktion_x_termin';
    $n = max(1, count($termine));
    $eng = $n > 4;                                   // viele Spalten (Chilbi): kompakter setzen
    $fnW = $eng ? 2600 : 3800;
    $tW  = (int)floor((EP_DOCX_BREITE - $fnW) / $n);
    $fs   = $eng ? ['size' => 9] : [];
    $grau = ['color' => '7F7F7F', 'italic' => true] + $fs;
    $fett = ['bold' => true] + $fs;

    $table = $section->addTable('EpTabelle');
    ep_docx_titelzeile($table, $plan['titel'] . ' - Einsatzplan ' . (int)$plan['jahr'] . $titelZusatz, $n + 1);

    // Kopfzeile
    $table->addRow(null, ['tblHeader' => true]);
    $c = $table->addCell($fnW, ['valign' => 'bottom', 'bgColor' => 'F2F2F2']);
    $c->addText('Funktion', $fett, $p0);
    foreach ($termine as $t) {
        $c = $table->addCell($tW, ['bgColor' => 'F2F2F2']);
        if (trim((string)$t['bezeichnung']) !== '') $c->addText($t['bezeichnung'], $fett, $p0);
        $c->addText($eng ? ep_datum_kurz($t['datum']) : ep_datum_lang($t['datum']), $fett, $p0);
        $zeit = ep_zeit_text($t);
        if ($zeit !== '') $c->addText($zeit, $fs, $p0);
        if (trim((string)($t['info'] ?? '')) !== '') $c->addText($t['info'], ['size' => 8, 'color' => '595959'], $p0);   // z.B. Treffpunkt / Büro (Schlossturm)
    }

    // Body: eine Zeile pro Funktionsgruppe
    foreach (ep_funktionen_gruppiert($plan['funktionen']) as $g) {
        if (!$istA) {
            // Chilbi: Zeilen ohne Einsatz und ohne Soll an diesem Tag auslassen
            $relevant = false;
            foreach ($g['funktionen'] as $f) foreach ($termine as $t) {
                $key = $t['id'] . '|' . $f['id'];
                if (!empty($plan['zellen'][$key]) || !empty($plan['soll'][$key])) $relevant = true;
            }
            if (!$relevant) continue;
        }
        $table->addRow(null, ['cantSplit' => true]);
        $c = $table->addCell($fnW, ['valign' => 'top']);
        if ($g['gruppe'] !== '') $c->addText($g['gruppe'] . ':', $fett, $p0);
        foreach ($g['funktionen'] as $f) {
            if ($istA) {
                for ($pos = 1; $pos <= max(1, (int)$f['anzahl']); $pos++) $c->addText($f['bezeichnung'], ['bold' => $g['gruppe'] === ''] + $fs, $p0);
            } else {
                $c->addText($f['bezeichnung'], $fett, $p0);
            }
        }
        foreach ($termine as $t) {
            $c = $table->addCell($tW, ['valign' => 'top']);
            if ($g['gruppe'] !== '') $c->addText('', [], $p0); // Ausrichtung zur Gruppenzeile links
            foreach ($g['funktionen'] as $f) {
                $key = $t['id'] . '|' . $f['id'];
                if ($istA) {
                    for ($pos = 1; $pos <= max(1, (int)$f['anzahl']); $pos++) {
                        $s = $plan['slot_index'][$key . '|' . $pos] ?? ['verein' => 'msv'];
                        $text = ep_docx_slot_text($s, $mitglieder);
                        $c->addText($text, ep_docx_platzhalter_verein($text) !== null ? $grau : $fs, $p0);
                    }
                } else {
                    $slots = array_values(array_filter($plan['zellen'][$key] ?? [], 'ep_slot_besetzt'));
                    foreach ($slots as $s) $c->addText(ep_slot_text($s, $mitglieder), $fs, $p0);
                    $soll = $plan['soll'][$key] ?? 0;
                    for ($i = count($slots); $i < $soll; $i++) $c->addText('offen', $grau, $p0);
                    if (!$slots && !$soll) $c->addText('–', $grau, $p0);
                }
            }
        }
    }
}

/**
 * Ansicht Personen × Termine (Chilbi-Excel-Nachbau) – für jedes Layout.
 */
function ep_docx_ansicht_personen($section, array $plan, array $mitglieder, array $p0): void
{
    $termine = $plan['termine'];
    $n = max(1, count($termine));
    $nameW = 2000; $vorW = 1800;
    $tW = (int)floor((EP_DOCX_BREITE - $nameW - $vorW) / $n);
    $klein = ['size' => 9];

    $table = $section->addTable('EpTabelle');
    ep_docx_titelzeile($table, $plan['titel'] . ' - Einsatzplan ' . (int)$plan['jahr'], $n + 2);

    // Datumszeile (Termine desselben Tages zusammenfassen)
    $table->addRow(null, ['tblHeader' => true]);
    $table->addCell($nameW, ['bgColor' => 'F2F2F2'])->addText('Name', ['bold' => true], $p0);
    $table->addCell($vorW, ['bgColor' => 'F2F2F2'])->addText('Vorname', ['bold' => true], $p0);
    $i = 0;
    while ($i < count($termine)) {
        $datum = $termine[$i]['datum'];
        $span = 1;
        while ($i + $span < count($termine) && $termine[$i + $span]['datum'] === $datum) $span++;
        $c = $table->addCell($tW * $span, ['gridSpan' => $span, 'bgColor' => 'F2F2F2']);
        $c->addText(ep_datum_kurz($datum), ['bold' => true], $p0);
        $i += $span;
    }
    // Zeit-/Bezeichnungszeile
    $table->addRow(null, ['tblHeader' => true]);
    $table->addCell($nameW, ['bgColor' => 'F2F2F2'])->addText('', [], $p0);
    $table->addCell($vorW, ['bgColor' => 'F2F2F2'])->addText('', [], $p0);
    foreach ($termine as $t) {
        $c = $table->addCell($tW, ['bgColor' => 'F2F2F2']);
        if (trim((string)$t['bezeichnung']) !== '') $c->addText($t['bezeichnung'], ['size' => 9, 'bold' => true], $p0);
        $c->addText(ep_zeit_text($t), $klein, $p0);
    }

    // Personen
    foreach (ep_chilbi_personen($plan, $mitglieder) as $p) {
        $m = $p['mitglied'];
        $table->addRow(null, ['cantSplit' => true]);
        $table->addCell($nameW)->addText((string)$m['Name'], [], $p0);
        $table->addCell($vorW)->addText((string)$m['Vorname'], [], $p0);
        foreach ($termine as $t) {
            $zellen = $p['zellen'][(int)$t['id']] ?? [];
            $table->addCell($tW)->addText(implode(' / ', array_map(fn($f) => $f['bezeichnung'], $zellen)), $klein, $p0);
        }
    }

    // Besetzung je Termin (Ist/Soll)
    $table->addRow(null, ['cantSplit' => true]);
    $c = $table->addCell($nameW + $vorW, ['gridSpan' => 2, 'bgColor' => 'F2F2F2', 'valign' => 'top']);
    $c->addText('Besetzung', ['bold' => true, 'size' => 9], $p0);
    foreach ($termine as $t) {
        $c = $table->addCell($tW, ['bgColor' => 'F2F2F2', 'valign' => 'top']);
        $b = ep_besetzung($plan, (int)$t['id']);
        if (!$b) { $c->addText('–', ['size' => 8, 'color' => '7F7F7F'], $p0); continue; }
        foreach ($b as $z) {
            $fehlt = $z['soll'] !== null && $z['ist'] < $z['soll'];
            $c->addText($z['funktion']['bezeichnung'] . ' ' . $z['ist'] . ($z['soll'] !== null ? '/' . $z['soll'] : ''), ['size' => 8, 'bold' => $fehlt, 'color' => $fehlt ? 'C00000' : '000000'], $p0);
        }
    }
}
