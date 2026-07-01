<?php
// inc/rangliste_import/pdf_rangliste_parser.php
// Parst externe Einzelranglisten (Vereinsstich o.ae.) aus PDF-Dateien.
//
// Strategie: Textfragmente werden ueber ihre X/Y-Koordinaten (getDataTm) zu
// Zeilen rekonstruiert (gleiche bewaehrte Logik wie inc/einsatzplan_parser/pdf_parser.php),
// da flache Textextraktion bei diesen Ranglisten Spaltenversatz erzeugt (Spg/Punkte
// landen auf falschen Zeilen). Anschliessend wird jede Zeile token-basiert interpretiert.
//
// Pro Zeile erkannt: rang (fuehrende Zahl), Name (Tokens bis Jahrgang/Kategorie),
// Lizenznummer (5-7-stellige Zahl = mitglieder.ID), Resultat (letzte Punktezahl) und
// Preis/Auszahlung (Dezimalwert), falls vorhanden.

require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

/**
 * Parst eine Rangliste aus einer PDF-Datei.
 *
 * @param string $filepath Pfad zur PDF-Datei
 * @param bool   $debug    Debug-Infos (Sample-Zeilen) zurueckgeben
 * @return array ['success' => bool, 'rows' => [...], 'message' => string, 'debug'? => [...]]
 */
function parseRanglistePdf($filepath, $debug = false, $ownClubNeedles = ['wilen']) {
    if (!file_exists($filepath)) {
        return ['success' => false, 'rows' => [], 'message' => 'Datei nicht gefunden'];
    }
    if (!class_exists('Smalot\\PdfParser\\Parser')) {
        return ['success' => false, 'rows' => [], 'message' => 'PDF-Parser nicht installiert (smalot/pdfparser).'];
    }

    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($filepath);
    } catch (Throwable $e) {
        return ['success' => false, 'rows' => [], 'message' => 'PDF konnte nicht geladen werden: ' . $e->getMessage()];
    }

    // 1) Zeilen rekonstruieren – positionsbasiert (primaer), sonst getText() (Fallback)
    $lines = ranglisteReconstructLinesPositional($pdf);
    $usedStrategy = 'position';
    if (empty($lines)) {
        $lines = ranglisteReconstructLinesFromText($pdf);
        $usedStrategy = 'text';
    }

    // 2) Jede Zeile interpretieren
    $rows = [];
    foreach ($lines as $line) {
        $row = ranglisteInterpretLine($line);
        if ($row !== null) {
            $rows[] = $row;
        }
    }

    // 3) Sektionsrangierung (Vereinsrangliste) des eigenen Vereins, falls vorhanden
    $sektion = parseSektionsrangierungOwnClub($lines, $ownClubNeedles);

    if (empty($rows) && $sektion === null) {
        $result = ['success' => false, 'rows' => [], 'sektion' => null, 'message' => 'Keine Ranglisten-Zeilen erkannt. Bitte ein anderes PDF verwenden oder Resultate manuell erfassen.'];
        if ($debug) {
            $result['debug'] = ['strategy' => $usedStrategy, 'line_count' => count($lines), 'sample_lines' => array_slice($lines, 0, 40)];
        }
        return $result;
    }

    $result = ['success' => true, 'rows' => $rows, 'sektion' => $sektion, 'message' => count($rows) . ' Ranglisten-Zeilen erkannt'];
    if ($debug) {
        $result['debug'] = ['strategy' => $usedStrategy, 'line_count' => count($lines), 'sample_lines' => array_slice($lines, 0, 40)];
    }
    return $result;
}

/**
 * Sucht in den Zeilen die Sektions-/Vereinsrangierung des EIGENEN Vereins.
 * Erkennungsmerkmale einer Vereinsranglisten-Zeile:
 *   - enthaelt alle $ownClubNeedles (z.B. "wilen"),
 *   - enthaelt einen 3-stelligen Dezimal-Total (z.B. 93.817) -> eindeutig fuer
 *     die Vereinsrangliste (Einzelzeilen haben ganzzahlige Resultate),
 *   - beginnt mit einem Rang.
 * Preis = 2-stelliger Dezimalwert (Auszahlung), falls vorhanden, sonst 0.
 *
 * @return array|null ['rang'=>int, 'preis'=>float, 'verein'=>string] oder null
 */
function parseSektionsrangierungOwnClub($lines, $ownClubNeedles) {
    if (empty($ownClubNeedles)) return null;

    foreach ($lines as $line) {
        $lower = mb_strtolower($line, 'UTF-8');

        // Eigener Verein? (alle Needles muessen vorkommen)
        $isOwn = true;
        foreach ($ownClubNeedles as $needle) {
            if (mb_strpos($lower, mb_strtolower($needle, 'UTF-8')) === false) { $isOwn = false; break; }
        }
        if (!$isOwn) continue;

        // Signatur einer Vereinsranglisten-Zeile: 3-stelliger Dezimal-Total
        if (!preg_match('/\d+\.\d{3}/', $line)) continue;

        // Rang = fuehrende Zahl
        if (!preg_match('/^\s*(\d{1,3})\b/', $line, $m)) continue;
        $rang = (int) $m[1];
        if ($rang < 1 || $rang > 999) continue;

        // Preis = 2-stelliger Dezimalwert (Auszahlung), Total (3-stellig) wird ignoriert
        $preis = 0.0;
        if (preg_match('/(\d{1,5}\.\d{2})(?!\d)/', $line, $pm)) {
            $preis = (float) $pm[1];
        }

        // Vereinsname = Text zwischen Rang und erster Zahl (Kategorie-Spalte)
        $verein = '';
        if (preg_match('/^\s*\d{1,3}\s+(.+?)\s+\d/u', $line, $vm)) {
            $verein = trim($vm[1]);
        }

        return ['rang' => $rang, 'preis' => $preis, 'verein' => $verein];
    }

    return null;
}

// ───────────────────────────────────────────────────────────────
// Zeilen-Rekonstruktion
// ───────────────────────────────────────────────────────────────

/**
 * Rekonstruiert Zeilen positionsbasiert ueber getDataTm() (X/Y-Koordinaten).
 * Fragmente werden pro Seite nach Y (oben->unten) in Zeilen gruppiert und
 * innerhalb der Zeile nach X (links->rechts) sortiert zusammengefuegt.
 */
function ranglisteReconstructLinesPositional($pdf) {
    $lines = [];

    foreach ($pdf->getPages() as $page) {
        $fragments = [];
        try {
            $dataTm = $page->getDataTm();
        } catch (Throwable $e) {
            continue;
        }
        if (empty($dataTm)) {
            continue;
        }

        foreach ($dataTm as $item) {
            // Format: [ [a, b, c, d, tx, ty], "text" ]
            if (count($item) >= 2 && is_array($item[0]) && count($item[0]) >= 6) {
                $x = (float) $item[0][4];
                $y = (float) $item[0][5];
                $text = trim($item[1]);
                if ($text !== '') {
                    $fragments[] = ['x' => $x, 'y' => $y, 'text' => $text];
                }
            }
        }
        if (empty($fragments)) {
            continue;
        }

        // Sortierung: Y absteigend (PDF-Y zeigt nach oben), bei gleicher Zeile X aufsteigend
        usort($fragments, function ($a, $b) {
            if (abs($a['y'] - $b['y']) < 3) {
                return $a['x'] <=> $b['x'];
            }
            return $b['y'] <=> $a['y'];
        });

        // In Zeilen gruppieren (Y-Toleranz 4 Punkte)
        $currentY = null;
        $current = [];
        foreach ($fragments as $f) {
            if ($currentY === null || abs($f['y'] - $currentY) > 4) {
                if (!empty($current)) {
                    $lines[] = ranglisteJoinFragments($current);
                }
                $current = [$f];
                $currentY = $f['y'];
            } else {
                $current[] = $f;
            }
        }
        if (!empty($current)) {
            $lines[] = ranglisteJoinFragments($current);
        }
    }

    return $lines;
}

/**
 * Fuegt die Fragmente einer Zeile (nach X sortiert) zu einem String zusammen.
 */
function ranglisteJoinFragments($frags) {
    usort($frags, fn($a, $b) => $a['x'] <=> $b['x']);
    $parts = array_map(fn($f) => $f['text'], $frags);
    return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)));
}

/**
 * Fallback: Zeilen direkt aus getText() (zeilenweise).
 */
function ranglisteReconstructLinesFromText($pdf) {
    $fullText = '';
    foreach ($pdf->getPages() as $page) {
        try {
            $fullText .= $page->getText() . "\n";
        } catch (Throwable $e) {
            // Seite ueberspringen
        }
    }
    $lines = [];
    foreach (explode("\n", $fullText) as $line) {
        $line = trim(preg_replace('/\s+/u', ' ', $line));
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    return $lines;
}

// ───────────────────────────────────────────────────────────────
// Zeilen-Interpretation
// ───────────────────────────────────────────────────────────────

/**
 * Interpretiert eine rekonstruierte Zeile.
 * Gibt ['rang','raw_name','lizenz','resultat','preis'] zurueck oder null,
 * wenn die Zeile keine verwertbare Schuetzen-Zeile ist.
 */
function ranglisteInterpretLine($line) {
    $line = trim($line);
    if ($line === '') {
        return null;
    }

    // Offensichtliche Kopf-/Fuss-/Titelzeilen ueberspringen
    if (preg_match('/^(Rang\b|Teilnehmer\b|Verein\b|Spg\b|Punkte\b|VereinsWK|Seite\s+\d|Einzelrangliste|Ranglisten|Vereinsstich|Vereinsabrechnung|Vereinswettkampf|Vereinskategorie|Pflichtteilnehmer|Nicht-Pflicht|Einzelresultate)/iu', $line)) {
        return null;
    }

    $tokensRaw = preg_split('/\s+/u', $line);
    if (count($tokensRaw) < 2) {
        return null;
    }
    // Bereinigte Tokens (ohne umschliessende Kommas)
    $tokens = array_map(fn($t) => trim($t, ','), $tokensRaw);

    // Fuehrender Rang (1–3 Ziffern, optional)
    $rang = null;
    $nameStart = 0;
    if (preg_match('/^\d{1,3}$/', $tokens[0])) {
        $rang = (int) $tokens[0];
        $nameStart = 1;
    }

    // Resultat direkt nach dem Rang (z.B. Verbandsschiessen-Layout):
    // Manche Ranglisten schieben das Resultat per Kerning optisch in die rechte
    // Spalte, im Textfluss (getDataTm) liegt es aber im selben Fragment wie der
    // Rang – also unmittelbar dahinter. Ein 1–3-stelliges Token direkt nach dem
    // Rang ist daher ein fuehrendes Resultat. (In den uebrigen Ranglisten folgt
    // dem Rang der Name, also ein Buchstaben-Token -> kein Konflikt.)
    $leadingResultat = null;
    if ($rang !== null && isset($tokens[$nameStart]) && preg_match('/^\d{1,3}$/', $tokens[$nameStart])) {
        $leadingResultat = (int) $tokens[$nameStart];
        $nameStart++;
    }

    // Lizenznummer = erstes 5–7-stelliges Token (= mitglieder.ID)
    $lizenz = null;
    $lizenzIdx = null;
    foreach ($tokens as $i => $t) {
        if (preg_match('/^\d{5,7}$/', $t)) {
            $lizenz = $t;
            $lizenzIdx = $i;
            break;
        }
    }
    // Falls die Zeile mit der Lizenz beginnt: Name beginnt direkt danach
    if ($lizenzIdx !== null && $lizenzIdx < $nameStart + 1) {
        $nameStart = $lizenzIdx + 1;
    }

    // Jahrgang (1900–2035) als Namens-Endgrenze
    $yearIdx = null;
    for ($i = $nameStart; $i < count($tokens); $i++) {
        if (preg_match('/^(19|20)\d{2}$/', $tokens[$i])) {
            $yearIdx = $i;
            break;
        }
    }

    // Name extrahieren
    $name = ranglisteExtractName($tokensRaw, $tokens, $nameStart, $yearIdx);
    $nameWordCount = $name === '' ? 0 : count(preg_split('/\s+/u', $name));
    $hasName = $nameWordCount >= 2;

    // Ohne Name UND ohne Lizenz ist die Zeile nicht verwertbar
    if (!$hasName && $lizenz === null) {
        return null;
    }

    // Resultat + Preis aus dem hinteren Teil der Zeile
    $scanStart = ($yearIdx !== null) ? $yearIdx + 1 : $nameStart + max($nameWordCount, 1);
    list($resultat, $preis) = ranglisteExtractResultPreis($tokens, $scanStart);

    // Fallback: Resultat aus dem fuehrenden Token (Verbandsschiessen-Layout),
    // falls im hinteren Zeilenteil keine Punktezahl gefunden wurde.
    if ($resultat === null && $leadingResultat !== null) {
        $resultat = $leadingResultat;
    }

    // Zeile nur behalten, wenn ein Resultat ODER eine Lizenz vorhanden ist
    // (reine Namens-Zeilen ohne Resultat sind nicht importierbar).
    if ($resultat === null && $lizenz === null) {
        return null;
    }

    return [
        'rang'     => $rang,
        'raw_name' => $name,
        'lizenz'   => $lizenz,
        'resultat' => $resultat, // int|null
        'preis'    => $preis,    // float|null
    ];
}

/**
 * Extrahiert den Namen ab $start.
 * Sammelt aufeinanderfolgende Buchstaben-Tokens und stoppt bei:
 *  - einem Token mit Ziffern (z.B. Jahrgang),
 *  - dem Jahrgang-Index,
 *  - einem Komma am Ende des Original-Tokens (Feldtrenner),
 *  - einem reinen GROSSBUCHSTABEN-Kuerzel <=3 Zeichen (Kategorie/Waffe: SV, FW, KA, E, S, V).
 */
function ranglisteExtractName($tokensRaw, $tokens, $start, $yearIdx) {
    $nameParts = [];
    $end = ($yearIdx !== null) ? $yearIdx : count($tokens);

    for ($i = $start; $i < $end; $i++) {
        $t = $tokens[$i];
        if ($t === '') {
            continue;
        }
        // Token mit Ziffer -> Ende des Namens
        if (preg_match('/\d/u', $t)) {
            break;
        }
        // Reines Grossbuchstaben-Kuerzel (<=3) -> Kategorie/Waffe, nicht Teil des Namens
        if (preg_match('/^[A-ZÄÖÜ]{1,3}$/u', $t)) {
            break;
        }
        // Nur Buchstaben/Apostroph/Bindestrich gelten als Namensbestandteil
        if (!preg_match('/^[\p{L}\'’\-]+$/u', $t)) {
            break;
        }
        $nameParts[] = $t;
        // Komma am Ende des Original-Tokens = Feldtrenner -> Name endet hier
        if (substr(rtrim($tokensRaw[$i]), -1) === ',') {
            break;
        }
        // Sicherheitslimit
        if (count($nameParts) >= 5) {
            break;
        }
    }

    return trim(implode(' ', $nameParts));
}

/**
 * Sucht ab $scanStart das Resultat und den Preis.
 *  - Preis  = Dezimalwert (z.B. 50.00) = Auszahlung
 *  - Resultat = letzte eigenstaendige 1–3-stellige Ganzzahl (= Punkte-Spalte),
 *    Jahrgaenge (4-stellig) und Lizenznummern (5–7-stellig) werden ignoriert.
 *
 * @return array [int|null $resultat, float|null $preis]
 */
function ranglisteExtractResultPreis($tokens, $scanStart) {
    $resultat = null;
    $preis = null;

    for ($i = $scanStart; $i < count($tokens); $i++) {
        $t = $tokens[$i];
        if (preg_match('/^\d{1,4}\.\d{2}$/', $t)) {
            // Dezimal -> Auszahlung/Preis (letzter gewinnt)
            $preis = (float) $t;
            continue;
        }
        if (preg_match('/^\d{1,3}$/', $t)) {
            // 1–3-stellige Ganzzahl -> Resultat-Kandidat (letzter gewinnt = Punkte-Spalte)
            $resultat = (int) $t;
            continue;
        }
        // 4-stellige (Jahr) und 5–7-stellige (Lizenz) Zahlen sowie Text ignorieren
    }

    return [$resultat, $preis];
}
