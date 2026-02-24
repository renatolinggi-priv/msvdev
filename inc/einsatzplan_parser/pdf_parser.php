<?php
// inc/einsatzplan_parser/pdf_parser.php - Parst Einsatzpläne aus PDF-Dateien
// Nutzt Textpositionen (X/Y-Koordinaten) für korrekte Spalten-Trennung
// Fallback: getText() mit Spalten-Erkennung über Leerzeichen
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

/**
 * Parst einen Einsatzplan aus einer PDF-Datei.
 *
 * @param string $filepath Pfad zur PDF-Datei
 * @param bool $debug Debug-Infos zurückgeben
 * @return array ['success' => bool, 'data' => [...], 'message' => string]
 */
function parseEinsatzplanPdf($filepath, $debug = false) {
    if (!file_exists($filepath)) {
        return ['success' => false, 'data' => [], 'message' => 'Datei nicht gefunden'];
    }

    if (!class_exists('Smalot\\PdfParser\\Parser')) {
        return ['success' => false, 'data' => [], 'message' => 'PDF-Parser nicht installiert. Bitte smalot/pdfparser via Composer installieren.'];
    }

    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($filepath);
    } catch (Exception $e) {
        return ['success' => false, 'data' => [], 'message' => 'PDF konnte nicht geladen werden: ' . $e->getMessage()];
    }

    $debugInfo = ['strategies' => []];

    // ── Strategie 1: Positionsbasiert via getDataTm() ──
    $fragments = [];
    foreach ($pdf->getPages() as $page) {
        try {
            $dataTm = $page->getDataTm();
            if (!empty($dataTm)) {
                foreach ($dataTm as $item) {
                    // Format: [ [a, b, c, d, tx, ty], "text" ]
                    if (count($item) >= 2 && is_array($item[0]) && count($item[0]) >= 6) {
                        $x = round($item[0][4]); // tx = X-Position
                        $y = round($item[0][5]); // ty = Y-Position
                        $text = trim($item[1]);
                        if (!empty($text)) {
                            $fragments[] = ['x' => $x, 'y' => $y, 'text' => $text];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $debugInfo['dataTm_error'] = $e->getMessage();
        }
    }

    if ($debug) {
        $debugInfo['fragment_count'] = count($fragments);
        $debugInfo['fragment_sample'] = array_slice($fragments, 0, 40);
    }

    if (!empty($fragments)) {
        $zuweisungen = parseFromPositionedFragments($fragments, $debug, $debugInfo);
        if (!empty($zuweisungen)) {
            usort($zuweisungen, fn($a, $b) => strcmp($a['event_datum'], $b['event_datum']));
            $result = ['success' => true, 'data' => $zuweisungen, 'message' => count($zuweisungen) . ' Einsätze erkannt (PDF)'];
            if ($debug) $result['debug'] = $debugInfo;
            return $result;
        }
        $debugInfo['strategies'][] = 'position_based: no results';
    } else {
        $debugInfo['strategies'][] = 'position_based: no fragments';
    }

    // ── Strategie 2: Textbasiert via getText() ──
    $fullText = '';
    foreach ($pdf->getPages() as $page) {
        $fullText .= $page->getText() . "\n";
    }

    if ($debug) {
        $debugInfo['getText_length'] = mb_strlen($fullText);
        $textLines = explode("\n", $fullText);
        $debugInfo['getText_sample'] = array_slice($textLines, 0, 50);
    }

    if (!empty(trim($fullText))) {
        $zuweisungen = parseFromText($fullText, $debug, $debugInfo);
        if (!empty($zuweisungen)) {
            usort($zuweisungen, fn($a, $b) => strcmp($a['event_datum'], $b['event_datum']));
            $result = ['success' => true, 'data' => $zuweisungen, 'message' => count($zuweisungen) . ' Einsätze erkannt (PDF Text-Modus – bitte Vorschau prüfen)'];
            if ($debug) $result['debug'] = $debugInfo;
            return $result;
        }
        $debugInfo['strategies'][] = 'text_based: no results';
    } else {
        $debugInfo['strategies'][] = 'text_based: no text';
    }

    $result = ['success' => false, 'data' => [], 'message' => 'Keine Einsätze im PDF erkannt. Bitte DOCX-Version verwenden.'];
    if ($debug) $result['debug'] = $debugInfo;
    return $result;
}

// ═══════════════════════════════════════════════════════════════
// Strategie 1: Positionsbasierte Analyse via getDataTm()
// (bewährter Original-Algorithmus)
// ═══════════════════════════════════════════════════════════════

function parseFromPositionedFragments($fragments, $debug, &$debugInfo) {
    // Schritt 1: Fragmente nach Y-Koordinate (Zeilen) gruppieren
    // PDF Y-Achse geht von unten nach oben, daher absteigend sortieren
    usort($fragments, function($a, $b) {
        if (abs($a['y'] - $b['y']) < 3) return $a['x'] - $b['x']; // gleiche Zeile: nach X
        return $b['y'] - $a['y']; // verschiedene Zeilen: Y absteigend
    });

    // Fragmente in Zeilen gruppieren (Y-Toleranz: 5 Punkte)
    $rows = [];
    $currentRowY = null;
    $currentRow = [];
    foreach ($fragments as $f) {
        if ($currentRowY === null || abs($f['y'] - $currentRowY) > 5) {
            if (!empty($currentRow)) {
                $rows[] = $currentRow;
            }
            $currentRow = [$f];
            $currentRowY = $f['y'];
        } else {
            $currentRow[] = $f;
        }
    }
    if (!empty($currentRow)) $rows[] = $currentRow;

    // Schritt 2: Spalten-Grenzen erkennen
    $allX = [];
    foreach ($rows as $row) {
        foreach ($row as $f) {
            $allX[] = $f['x'];
        }
    }
    sort($allX);
    $columnBoundaries = detectColumnBoundaries($allX);

    if ($debug) {
        $debugInfo['row_count'] = count($rows);
        $debugInfo['columns_detected'] = $columnBoundaries;
        // Sample: erste 12 Zeilen mit Fragmenten
        $sample = [];
        foreach (array_slice($rows, 0, 12) as $r) {
            $sample[] = array_map(fn($f) => 'x=' . $f['x'] . ': "' . $f['text'] . '"', $r);
        }
        $debugInfo['rows_sample'] = $sample;
    }

    if (count($columnBoundaries) < 2) {
        return [];
    }

    // Schritt 3: Zeilen in Spalten aufteilen
    $tableRows = [];
    foreach ($rows as $row) {
        $cells = array_fill(0, count($columnBoundaries), '');
        foreach ($row as $f) {
            $colIdx = assignToColumn($f['x'], $columnBoundaries);
            if ($cells[$colIdx] !== '') {
                $cells[$colIdx] .= ' ';
            }
            $cells[$colIdx] .= $f['text'];
        }
        $tableRows[] = array_map('trim', $cells);
    }

    if ($debug) {
        $debugInfo['table_rows_sample'] = array_slice($tableRows, 0, 20);
    }

    // Schritt 4: Tabelle interpretieren
    return interpretTable($tableRows);
}

/**
 * Erkennt Spalten-Grenzen aus X-Positionen (Original-Algorithmus).
 */
function detectColumnBoundaries($xPositions) {
    if (empty($xPositions)) return [];

    // Häufigkeitsverteilung der X-Positionen (gerundet auf 10er)
    $buckets = [];
    foreach ($xPositions as $x) {
        $bucket = intval(round($x / 10) * 10);
        $buckets[$bucket] = ($buckets[$bucket] ?? 0) + 1;
    }
    ksort($buckets);

    // Nur Positionen mit mehreren Vorkommen (= Spaltenanfänge)
    $candidates = [];
    foreach ($buckets as $pos => $count) {
        if ($count >= 3) {
            $candidates[] = $pos;
        }
    }

    if (empty($candidates)) return [];

    // Cluster zusammenfassen (Lücke > 50 Punkte = neue Spalte)
    $columns = [$candidates[0]];
    for ($i = 1; $i < count($candidates); $i++) {
        if ($candidates[$i] - $candidates[$i-1] > 50) {
            $columns[] = $candidates[$i];
        }
    }

    return $columns;
}

/**
 * Ordnet eine X-Position der nächsten Spalte zu.
 */
function assignToColumn($x, $columnBoundaries) {
    $best = 0;
    $bestDist = PHP_INT_MAX;
    foreach ($columnBoundaries as $idx => $boundary) {
        $dist = abs($x - $boundary);
        if ($dist < $bestDist) {
            $bestDist = $dist;
            $best = $idx;
        }
    }
    return $best;
}

// ═══════════════════════════════════════════════════════════════
// Strategie 2: Zell-basierte Analyse via getText()
// getText() gibt Zellinhalte sequentiell zurück, getrennt durch
// Leerzeilen. Struktur: Funktion-Zelle → N Datums-Zellen pro Zeile.
// Tab-Zeichen trennen komprimierte Mehrfach-Zeilen.
// ═══════════════════════════════════════════════════════════════

function parseFromText($text, $debug, &$debugInfo) {
    $monate = [
        'Januar' => 1, 'Februar' => 2, 'März' => 3, 'April' => 4,
        'Mai' => 5, 'Juni' => 6, 'Juli' => 7, 'August' => 8,
        'September' => 9, 'Oktober' => 10, 'November' => 11, 'Dezember' => 12
    ];

    $lines = explode("\n", $text);
    $lines = array_map('rtrim', $lines);

    // ── 1. "Funktion"-Zeile finden ──
    $funktionIdx = null;
    foreach ($lines as $idx => $line) {
        if (mb_stripos(trim($line), 'Funktion') !== false) {
            $funktionIdx = $idx;
            break;
        }
    }
    if ($funktionIdx === null) return [];

    // ── 2. Datums-Header parsen ──
    // Jeder Header besteht aus 3 Zeilen: Label, Wochentag+Datum, Uhrzeit
    $dates = [];
    $typ = 'einsatz';
    $i = $funktionIdx + 1;

    // Leerzeilen überspringen
    while ($i < count($lines) && trim($lines[$i]) === '') $i++;

    while ($i + 2 < count($lines)) {
        $line1 = trim($lines[$i]);
        $line2 = trim($lines[$i + 1] ?? '');
        $line3 = trim($lines[$i + 2] ?? '');

        // Prüfe ob line2 ein Datum enthält
        $hasDate = false;
        foreach ($monate as $name => $num) {
            if (preg_match('/\d{1,2}\.\s*' . preg_quote($name, '/') . '/i', $line2)) {
                $hasDate = true;
                break;
            }
        }
        // Fallback: Datum in line1 prüfen (falls Format anders)
        if (!$hasDate) {
            foreach ($monate as $name => $num) {
                if (preg_match('/\d{1,2}\.\s*' . preg_quote($name, '/') . '/i', $line1)) {
                    $hasDate = true;
                    break;
                }
            }
            if (!$hasDate) break;
        }

        $fullHeader = $line1 . ' ' . $line2 . ' ' . $line3;
        $dateInfo = parsePdfDateHeader($fullHeader, $monate);
        if ($dateInfo) {
            $dates[] = $dateInfo;
            if (preg_match('/Obligatorisch/i', $line1)) $typ = 'obligatorisch';
            elseif (preg_match('/Feldschiessen/i', $line1)) $typ = 'feldschiessen';
        }

        $i += 3;
        // Leerzeilen zwischen Headern überspringen
        while ($i < count($lines) && trim($lines[$i]) === '') $i++;
    }

    $numDates = count($dates);
    if ($numDates === 0) return [];

    // Typ auch aus Zeilen VOR "Funktion" erkennen
    if ($typ === 'einsatz') {
        for ($ti = max(0, $funktionIdx - 5); $ti < $funktionIdx; $ti++) {
            if (preg_match('/Obligatorisch/i', $lines[$ti])) { $typ = 'obligatorisch'; break; }
            if (preg_match('/Feldschiessen/i', $lines[$ti])) { $typ = 'feldschiessen'; break; }
        }
    }

    $dataStartIdx = $i;

    if ($debug) {
        $debugInfo['text_funktionIdx'] = $funktionIdx;
        $debugInfo['text_dates'] = $dates;
        $debugInfo['text_typ'] = $typ;
        $debugInfo['text_numDates'] = $numDates;
        $debugInfo['text_dataStartIdx'] = $dataStartIdx;
    }

    // ── 3. Restliche Zeilen in Zellen aufteilen ──
    // Leerzeilen = Zell-Trenner, Tab-Zeichen = komprimierte Mehrfach-Zeilen
    $cells = [];
    $currentCell = [];
    $footerPatterns = '/^(Verhinderung|SELBST|Ersatz|Abtausch|Schiessbeginn|Herzlichen|bitte|Euch|Bei Verhinderung|30 Minuten)/i';

    for ($j = $dataStartIdx; $j < count($lines); $j++) {
        $trimmed = trim($lines[$j]);

        // Footer → Abbruch
        if (preg_match($footerPatterns, $trimmed)) break;

        if ($trimmed === '') {
            // Leerzeile = Zell-Trenner
            if (!empty($currentCell)) {
                $cells[] = $currentCell;
                $currentCell = [];
            }
        } elseif (strpos($trimmed, "\t") !== false) {
            // Tab-getrennte Zeile: aktuelle Zelle abschliessen, dann
            // jedes Tab-Segment als eigene Zelle einfügen
            if (!empty($currentCell)) {
                $cells[] = $currentCell;
                $currentCell = [];
            }
            $segments = explode("\t", $trimmed);
            foreach ($segments as $seg) {
                $seg = trim($seg);
                if (!empty($seg)) {
                    $cells[] = [$seg]; // Einzeilige Zelle
                }
            }
        } else {
            $currentCell[] = $trimmed;
        }
    }
    if (!empty($currentCell)) {
        $cells[] = $currentCell;
    }

    if ($debug) {
        $debugInfo['text_cell_count'] = count($cells);
        $debugInfo['text_cells'] = array_map(
            function($c) { return implode(' | ', $c); },
            array_slice($cells, 0, 40)
        );
    }

    // ── 4. Zellen in Tabellenzeilen gruppieren und Zuweisungen erstellen ──
    // Jede Tabellenzeile = 1 Funktions-Zelle + N Datums-Zellen
    // Ausnahme: komprimierte Einzeiler (Funktion + Namen in einer Zeile)
    $ignored = ['SV Freienbach', 'SV Wollerau'];
    $zuweisungen = [];
    $cellIdx = 0;

    while ($cellIdx < count($cells)) {
        $cell = $cells[$cellIdx];

        // Prüfe ob dies eine komprimierte Zelle ist (Funktion + N Namen in einer Zeile)
        if (count($cell) === 1) {
            $compressed = parseCompressedCell($cell[0], $numDates);
            if ($compressed !== null) {
                // Komprimierte Zelle: Funktion + Namen direkt extrahiert
                foreach ($compressed['names'] as $dateIdx => $name) {
                    if (empty(trim($name))) continue;
                    if (isNameIgnored($name, $ignored)) continue;
                    if ($dateIdx >= $numDates) break;
                    $zuweisungen[] = [
                        'typ'           => $typ,
                        'bezeichnung'   => $dates[$dateIdx]['bezeichnung'],
                        'event_datum'   => $dates[$dateIdx]['datum'],
                        'event_zeit'    => $dates[$dateIdx]['zeit'],
                        'funktion'      => $compressed['funktion'],
                        'mitglied_name' => trim($name),
                    ];
                }
                $cellIdx++;
                continue;
            }
        }

        // Normale Verarbeitung: aktuelle Zelle = Funktions-Zelle
        // Die nächsten N Zellen sind die Datums-Zellen
        if ($cellIdx + $numDates >= count($cells)) break; // Nicht genug Zellen übrig

        $funktionCell = $cell;
        $funktionMap = buildPdfFunktionMap($funktionCell);

        for ($d = 0; $d < $numDates; $d++) {
            $dateCellIdx = $cellIdx + 1 + $d;
            if ($dateCellIdx >= count($cells)) break;

            $dateCell = $cells[$dateCellIdx];

            for ($nameIdx = 0; $nameIdx < count($dateCell); $nameIdx++) {
                $name = trim($dateCell[$nameIdx]);
                if (empty($name)) continue;
                if (isNameIgnored($name, $ignored)) continue;

                // Mindestens 2 Wörter für einen gültigen Namen
                $words = preg_split('/\s+/', $name);
                if (count($words) < 2) continue;

                // Funktion für diesen Name-Index ermitteln
                $funktion = $funktionMap[$nameIdx] ?? (end($funktionMap) ?: 'Unbekannt');

                $zuweisungen[] = [
                    'typ'           => $typ,
                    'bezeichnung'   => $dates[$d]['bezeichnung'],
                    'event_datum'   => $dates[$d]['datum'],
                    'event_zeit'    => $dates[$d]['zeit'],
                    'funktion'      => $funktion,
                    'mitglied_name' => $name,
                ];
            }
        }

        $cellIdx += 1 + $numDates; // Funktions-Zelle + N Datums-Zellen überspringen
    }

    if ($debug) {
        $debugInfo['text_zuweisungen_count'] = count($zuweisungen);
    }

    return $zuweisungen;
}

/**
 * Prüft ob eine einzeilige Zelle komprimiert ist (Funktion + N Namen in einer Zeile).
 * Gibt ['funktion' => ..., 'names' => [...]] zurück, oder null wenn nicht komprimiert.
 */
function parseCompressedCell($line, $numDates) {
    // Name-Muster: "Nachname Vorname" (2 Wörter, jeweils mit Grossbuchstaben)
    $namePattern = '/[A-ZÄÖÜ][a-zäöüéèà]+\s+[A-ZÄÖÜ][a-zäöüéèà]+/u';
    preg_match_all($namePattern, $line, $matches, PREG_OFFSET_CAPTURE);

    // Muss mindestens N Namen enthalten um komprimiert zu sein
    if (count($matches[0]) < $numDates) return null;

    // Die letzten N Treffer sind die Namen (einer pro Datum)
    $allMatches = $matches[0];
    $startIdx = count($allMatches) - $numDates;

    $names = [];
    $firstNameOffset = strlen($line); // Byte-Position des ersten Namens

    for ($i = $startIdx; $i < count($allMatches); $i++) {
        $names[] = $allMatches[$i][0]; // [0] = Text
        $firstNameOffset = min($firstNameOffset, $allMatches[$i][1]); // [1] = Byte-Offset
    }

    // Alles vor dem ersten Namen = Funktionsname
    // PREG_OFFSET_CAPTURE gibt Byte-Offsets → substr statt mb_substr verwenden
    $funktion = trim(substr($line, 0, $firstNameOffset));
    $funktion = rtrim($funktion, ': ');

    if (empty($funktion)) return null;

    // Validierung: Funktion sollte kein reiner Name sein
    $fnWords = preg_split('/\s+/', $funktion);
    if (count($fnWords) === 2 && preg_match($namePattern, $funktion)) {
        // Sieht wie ein Name aus, nicht wie eine Funktion → nicht komprimiert
        return null;
    }

    return [
        'funktion' => $funktion,
        'names' => $names,
    ];
}

/**
 * Baut eine Zuordnung: nameIndex → Funktionsname (für PDF-Text-Zellen).
 * Erkennt Gruppen-Header ("Büro:") und ordnet Sub-Funktionen zu.
 * Nutzt die gleiche Logik wie buildFunktionMap() im DOCX-Parser.
 */
function buildPdfFunktionMap($funktionLines) {
    if (empty($funktionLines)) return [];

    $map = [];
    $nameIdx = 0;

    $firstLine = trim($funktionLines[0] ?? '');
    $hasGroupHeader = str_ends_with($firstLine, ':') && count($funktionLines) > 1;

    if ($hasGroupHeader) {
        // Gruppen-Header (z.B. "Büro:") → Sub-Funktionen zuordnen
        $groupPrefix = rtrim($firstLine, ':');
        for ($i = 1; $i < count($funktionLines); $i++) {
            $fn = trim($funktionLines[$i]);
            if (!empty($fn)) {
                $map[$nameIdx] = $groupPrefix . ': ' . $fn;
            } else {
                $map[$nameIdx] = $map[$nameIdx - 1] ?? $groupPrefix;
            }
            $nameIdx++;
        }
    } else {
        // Keine Gruppen-Header: erste nicht-leere Zeile gilt für alle
        $currentFunktion = '';
        foreach ($funktionLines as $fn) {
            $fn = trim($fn);
            if (!empty($fn)) {
                $currentFunktion = $fn;
            }
            $map[$nameIdx] = $currentFunktion;
            $nameIdx++;
        }
    }

    // Auffüllen für zusätzliche Namen-Zeilen
    $lastFn = end($map) ?: '';
    for ($i = $nameIdx; $i < $nameIdx + 20; $i++) {
        $map[$i] = $lastFn;
    }

    return $map;
}

/**
 * Prüft ob ein Name ignoriert werden soll.
 */
function isNameIgnored($name, $ignored) {
    foreach ($ignored as $pattern) {
        if (mb_stripos($name, $pattern) !== false) return true;
    }
    return false;
}

// ═══════════════════════════════════════════════════════════════
// Gemeinsame Tabellen-Interpretation (für Strategie 1)
// ═══════════════════════════════════════════════════════════════

/**
 * Interpretiert die extrahierte Tabelle.
 * Unterstützt Multi-Zeilen-Header (Datum über mehrere Zeilen verteilt).
 */
function interpretTable($tableRows) {
    $monate = [
        'Januar' => 1, 'Februar' => 2, 'März' => 3, 'April' => 4,
        'Mai' => 5, 'Juni' => 6, 'Juli' => 7, 'August' => 8,
        'September' => 9, 'Oktober' => 10, 'November' => 11, 'Dezember' => 12
    ];

    $numCols = 0;
    foreach ($tableRows as $row) {
        $numCols = max($numCols, count($row));
    }
    if ($numCols < 2) return [];

    // Zeilen auf gleiche Länge bringen
    foreach ($tableRows as &$row) {
        while (count($row) < $numCols) $row[] = '';
    }
    unset($row);

    $ignored = ['SV Freienbach', 'SV Wollerau'];
    $footerPatterns = '/Verhinderung|SELBST|Ersatz|Abtausch|Schiessbeginn|Herzlichen|bitte|Euch|30 Minuten/i';

    // Typ erkennen
    $typ = 'einsatz';
    foreach ($tableRows as $row) {
        $fullText = implode(' ', $row);
        if (preg_match('/Obligatorisch/i', $fullText)) { $typ = 'obligatorisch'; break; }
        if (preg_match('/Feldschiessen/i', $fullText)) { $typ = 'feldschiessen'; break; }
    }

    // ── Header finden ──
    $dates = [];
    $dataStartRow = 0;

    for ($r = 0; $r < min(6, count($tableRows)); $r++) {
        $row = $tableRows[$r];
        $firstCell = trim($row[0] ?? '');

        // Titel-Zeile überspringen
        if (preg_match('/Einsatzplan/i', implode(' ', $row))) {
            $dataStartRow = $r + 1;
            continue;
        }

        // "Funktion"-Zeile → Header parsen
        if (mb_stripos($firstCell, 'Funktion') !== false) {
            // Zuerst versuchen: Daten direkt aus dieser Zeile parsen
            for ($c = 1; $c < count($row); $c++) {
                $dateInfo = parsePdfDateHeader($row[$c], $monate);
                if ($dateInfo) $dates[] = $dateInfo;
            }
            $dataStartRow = $r + 1;

            // Falls keine Daten gefunden: Multi-Zeilen-Header
            // Nachfolgende Zeilen zum Header zusammenführen
            if (empty($dates)) {
                $headerTexts = [];
                for ($c = 0; $c < $numCols; $c++) {
                    $headerTexts[$c] = $row[$c] ?? '';
                }

                for ($hr = $r + 1; $hr < min($r + 5, count($tableRows)); $hr++) {
                    $hrow = $tableRows[$hr];
                    $firstH = trim($hrow[0] ?? '');

                    // Stopp bei Datenzeile (nicht-leere erste Spalte die kein Header-Teil ist)
                    if (!empty($firstH)
                        && !preg_match('/^(\d{1,2}[:.]\d{2}|Montag|Dienstag|Mittwoch|Mittoch|Donnerstag|Freitag|Samstag|Sonntag)/i', $firstH)
                        && mb_stripos($firstH, 'Funktion') === false) {
                        break;
                    }

                    // Prüfe ob Zeile Header-Inhalte hat
                    $isHeaderRow = false;
                    for ($c = 1; $c < min(count($hrow), $numCols); $c++) {
                        $ct = trim($hrow[$c] ?? '');
                        if (preg_match('/\d{1,2}:\d{2}/', $ct)) { $isHeaderRow = true; break; }
                        foreach ($monate as $name => $num) {
                            if (mb_stripos($ct, $name) !== false) { $isHeaderRow = true; break 2; }
                        }
                        if (preg_match('/^(Montag|Dienstag|Mittwoch|Mittoch|Donnerstag|Freitag|Samstag|Sonntag)/i', $ct)) {
                            $isHeaderRow = true; break;
                        }
                    }
                    if (!$isHeaderRow && !empty($firstH)) break;

                    // Zellen zum Header hinzufügen
                    for ($c = 0; $c < min(count($hrow), $numCols); $c++) {
                        $ct = trim($hrow[$c] ?? '');
                        if (!empty($ct)) {
                            $headerTexts[$c] = trim($headerTexts[$c] . ' ' . $ct);
                        }
                    }
                    $dataStartRow = $hr + 1;
                }

                // Nochmals Daten parsen aus zusammengeführtem Header
                for ($c = 1; $c < $numCols; $c++) {
                    $dateInfo = parsePdfDateHeader(trim($headerTexts[$c]), $monate);
                    if ($dateInfo) $dates[] = $dateInfo;
                }
            }
            break;
        }

        // Fallback: Datum direkt in einer Spalte erkennen (ohne "Funktion"-Label)
        if (count($row) >= 3) {
            $dateInfo = parsePdfDateHeader($row[1] ?? '', $monate);
            if ($dateInfo) {
                for ($c = 1; $c < count($row); $c++) {
                    $di = parsePdfDateHeader($row[$c], $monate);
                    if ($di) $dates[] = $di;
                }
                $dataStartRow = $r + 1;
                break;
            }
        }
    }

    if (empty($dates)) return [];

    // ── Daten-Zeilen verarbeiten ──
    $zuweisungen = [];
    $currentFunktion = '';

    for ($r = $dataStartRow; $r < count($tableRows); $r++) {
        $row = $tableRows[$r];
        $firstCell = trim($row[0] ?? '');

        // Footer → Abbruch
        $fullRowText = implode(' ', $row);
        if (preg_match($footerPatterns, $fullRowText)) break;

        // Leere Zeile überspringen
        if (empty(trim($fullRowText))) continue;

        // Funktion aus erster Spalte
        if (!empty($firstCell)) {
            $cleanFn = preg_replace('/\s+/', ' ', $firstCell);
            $cleanFn = rtrim($cleanFn, ':');
            if (!empty($cleanFn)) {
                $currentFunktion = $cleanFn;
            }
        }
        if (empty($currentFunktion)) continue;

        // Namen aus den Datums-Spalten
        for ($c = 1; $c < count($row) && ($c - 1) < count($dates); $c++) {
            $cellText = trim($row[$c] ?? '');
            if (empty($cellText)) continue;

            // Ignorierte Einträge
            $isIgnored = false;
            foreach ($ignored as $ign) {
                if (mb_stripos($cellText, $ign) !== false) {
                    $isIgnored = true;
                    break;
                }
            }
            if ($isIgnored) continue;

            // Name validieren
            if (!preg_match('/^[A-ZÄÖÜ]/u', $cellText)) continue;
            $words = preg_split('/\s+/', $cellText);
            if (count($words) < 2) continue;

            $dateIdx = $c - 1;
            $zuweisungen[] = [
                'typ'           => $typ,
                'bezeichnung'   => $dates[$dateIdx]['bezeichnung'],
                'event_datum'   => $dates[$dateIdx]['datum'],
                'event_zeit'    => $dates[$dateIdx]['zeit'],
                'funktion'      => $currentFunktion,
                'mitglied_name' => $cellText,
            ];
        }
    }

    return $zuweisungen;
}

/**
 * Parst einen Datums-Header aus einer PDF-Tabellenzelle.
 */
function parsePdfDateHeader($text, $monate) {
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (empty($text)) return null;

    $tag = null;
    $monat = null;
    $monatsName = '';
    foreach ($monate as $name => $num) {
        if (preg_match('/(\d{1,2})\.\s*' . preg_quote($name, '/') . '/i', $text, $m)) {
            $tag = intval($m[1]);
            $monat = $num;
            $monatsName = $name;
            break;
        }
    }

    if (!$tag || !$monat) return null;

    $jahr = date('Y');
    if (preg_match('/(\d{4})/', $text, $m)) {
        $jahr = $m[1];
    }

    $datum = sprintf('%04d-%02d-%02d', $jahr, $monat, $tag);

    $zeit = '';
    if (preg_match('/(\d{1,2}:\d{2})\s*[–\-]\s*(\d{1,2}:\d{2})/', $text, $m)) {
        $zeit = $m[1] . ' – ' . $m[2];
    }

    // Bezeichnung kürzen
    $bezeichnung = preg_replace('/\d{1,2}\.\s*' . preg_quote($monatsName, '/') . '.*/', '', $text);
    $wochentage = ['Montag', 'Dienstag', 'Mittwoch', 'Mittoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    foreach ($wochentage as $wt) {
        $bezeichnung = preg_replace('/\b' . preg_quote($wt, '/') . '\b/i', '', $bezeichnung);
    }
    $bezeichnung = trim(preg_replace('/\s+/', ' ', $bezeichnung));
    if (empty($bezeichnung)) {
        $bezeichnung = $tag . '. ' . $monatsName;
    }

    return [
        'bezeichnung' => $bezeichnung,
        'datum'        => $datum,
        'zeit'         => $zeit,
    ];
}
