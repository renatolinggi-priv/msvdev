<?php
// inc/einsatzplan_parser/docx_parser.php - Parst Einsatzpläne aus DOCX-Dateien
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;

/**
 * Parst einen Einsatzplan aus einer DOCX-Datei.
 * Gibt ein Array von Zuweisungen zurück.
 *
 * @param string $filepath Pfad zur DOCX-Datei
 * @return array ['success' => bool, 'data' => [...], 'message' => string]
 */
function parseEinsatzplanDocx($filepath) {
    if (!file_exists($filepath)) {
        return ['success' => false, 'data' => [], 'message' => 'Datei nicht gefunden'];
    }

    try {
        $phpWord = IOFactory::load($filepath);
    } catch (Exception $e) {
        return ['success' => false, 'data' => [], 'message' => 'DOCX konnte nicht geladen werden: ' . $e->getMessage()];
    }

    $zuweisungen = [];

    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            if ($element instanceof Table) {
                $parsed = parseEinsatzTable($element);
                $zuweisungen = array_merge($zuweisungen, $parsed);
            }
        }
    }

    if (empty($zuweisungen)) {
        return ['success' => false, 'data' => [], 'message' => 'Keine Einsätze in der Datei gefunden'];
    }

    return ['success' => true, 'data' => $zuweisungen, 'message' => count($zuweisungen) . ' Einsätze erkannt'];
}

/**
 * Parst eine einzelne Tabelle aus dem Einsatzplan.
 */
function parseEinsatzTable($table) {
    $rows = $table->getRows();
    if (count($rows) < 2) return [];

    // Header-Zeile(n) parsen: Typ erkennen + Daten extrahieren
    $headerInfo = parseHeaderRows($rows);
    if (empty($headerInfo['dates'])) return [];

    $zuweisungen = [];

    // Daten-Zeilen verarbeiten (nach Header)
    for ($r = $headerInfo['data_start']; $r < count($rows); $r++) {
        $cells = $rows[$r]->getCells();
        if (count($cells) < 2) continue;

        // Linke Spalte: Funktionen (mehrzeilig)
        $funktionLines = getCellLines($cells[0]);

        // Funktionen vorbereiten: Gruppen-Header erkennen und Zuordnung bauen
        // z.B. ["Büro:", "Anmeldung", "Anmeldung", "Munition", "Munition"]
        // → nameIndex 0 bekommt "Büro: Anmeldung", nameIndex 1 → "Büro: Anmeldung", etc.
        $funktionMap = buildFunktionMap($funktionLines);

        // Für jede Datums-Spalte
        for ($col = 1; $col < count($cells) && $col <= count($headerInfo['dates']); $col++) {
            $dateInfo = $headerInfo['dates'][$col - 1];
            $nameLines = getCellLines($cells[$col]);

            // Jede Name-Zeile einer Funktion zuordnen
            for ($nameIdx = 0; $nameIdx < count($nameLines); $nameIdx++) {
                $nameText = trim($nameLines[$nameIdx]);
                if (empty($nameText)) continue;
                if (isIgnoredEntry($nameText)) continue;

                // Funktion für diesen Name-Index ermitteln
                $funktion = $funktionMap[$nameIdx] ?? end($funktionMap) ?: 'Unbekannt';

                $zuweisungen[] = [
                    'typ'         => $headerInfo['typ'],
                    'bezeichnung' => $dateInfo['bezeichnung'],
                    'event_datum' => $dateInfo['datum'],
                    'event_zeit'  => $dateInfo['zeit'],
                    'funktion'    => $funktion,
                    'mitglied_name' => $nameText,
                ];
            }
        }
    }

    return $zuweisungen;
}

/**
 * Parst die Header-Zeilen der Tabelle.
 * Erkennt Typ (Obligatorisch/Feldschiessen) und extrahiert Daten.
 */
function parseHeaderRows($rows) {
    $result = ['typ' => '', 'dates' => [], 'data_start' => 1];

    $monate = [
        'Januar' => 1, 'Februar' => 2, 'März' => 3, 'April' => 4,
        'Mai' => 5, 'Juni' => 6, 'Juli' => 7, 'August' => 8,
        'September' => 9, 'Oktober' => 10, 'November' => 11, 'Dezember' => 12
    ];

    // Erste Zeile(n) durchgehen
    for ($r = 0; $r < min(3, count($rows)); $r++) {
        $cells = $rows[$r]->getCells();
        if (count($cells) < 2) continue;

        $firstCellText = trim(getCellFullText($cells[0]));

        // Titel-Zeile erkennen (z.B. "Obligatorisch - Einsatzplan 2026")
        if (preg_match('/^(Obligatorisch|Feldschiessen|Eidgenössisch|Bundesübung)/i', $firstCellText)) {
            $result['typ'] = strtolower(preg_replace('/\s.*/', '', $firstCellText));
            continue;
        }

        // Header-Zeile mit "Funktion" in der ersten Spalte
        if (mb_stripos($firstCellText, 'Funktion') !== false || mb_stripos($firstCellText, 'funktion') !== false) {
            // Datums-Spalten parsen
            for ($col = 1; $col < count($cells); $col++) {
                $headerText = getCellFullText($cells[$col]);
                $dateInfo = parseDateHeader($headerText, $monate);
                if ($dateInfo) {
                    $result['dates'][] = $dateInfo;
                }
            }
            $result['data_start'] = $r + 1;
            break;
        }

        // Falls die erste Zeile schon Daten-Spalten hat (Titel über volle Breite)
        if (count($cells) >= 4) {
            $secondCellText = getCellFullText($cells[1]);
            $dateInfo = parseDateHeader($secondCellText, $monate);
            if ($dateInfo) {
                // Diese Zeile ist der Header
                for ($col = 1; $col < count($cells); $col++) {
                    $headerText = getCellFullText($cells[$col]);
                    $di = parseDateHeader($headerText, $monate);
                    if ($di) $result['dates'][] = $di;
                }
                $result['data_start'] = $r + 1;
                break;
            }
        }
    }

    // Separate Zeit-Zeile erkennen: Zeile direkt nach data_start, erste Spalte leer
    // oder enthält "Zeit"/"Uhrzeit", restliche Spalten enthalten Zeitangaben
    if (!empty($result['dates'])) {
        $zeitRowIdx = $result['data_start'];
        if ($zeitRowIdx < count($rows)) {
            $zeitCells = $rows[$zeitRowIdx]->getCells();
            $firstCellText = trim(getCellFullText($zeitCells[0]));
            $isZeitRow = empty($firstCellText)
                || mb_stripos($firstCellText, 'Zeit') !== false
                || mb_stripos($firstCellText, 'Uhrzeit') !== false;

            if ($isZeitRow) {
                // Prüfe ob mind. eine Spalte eine Zeitangabe enthält
                $hasTime = false;
                for ($col = 1; $col < count($zeitCells); $col++) {
                    $t = getCellFullText($zeitCells[$col]);
                    if (preg_match('/\d{1,2}[:.]\d{2}/', $t)) {
                        $hasTime = true;
                        break;
                    }
                }
                if ($hasTime) {
                    // Zeiten den Datums-Einträgen zuordnen (Spalten-Reihenfolge)
                    foreach ($result['dates'] as $idx => &$dateInfo) {
                        $col = $idx + 1; // Spalte 0 = Funktion, ab 1 = Daten
                        if (!isset($zeitCells[$col])) continue;
                        $t = trim(getCellFullText($zeitCells[$col]));
                        if (preg_match('/(\d{1,2}[:.]\d{2})\s*[–-]\s*(\d{1,2}[:.]\d{2})/u', $t, $m)) {
                            $dateInfo['zeit'] = str_replace('.', ':', $m[1]) . ' – ' . str_replace('.', ':', $m[2]);
                        } elseif (preg_match('/(\d{1,2}[:.]\d{2})/', $t, $m)) {
                            $dateInfo['zeit'] = str_replace('.', ':', $m[1]);
                        }
                    }
                    unset($dateInfo);
                    $result['data_start'] = $zeitRowIdx + 1;
                }
            }
        }
    }

    // Typ aus Datums-Bezeichnungen ableiten, falls nicht erkannt
    if (empty($result['typ']) && !empty($result['dates'])) {
        $firstBez = $result['dates'][0]['bezeichnung'] ?? '';
        if (stripos($firstBez, 'Obligatorisch') !== false) {
            $result['typ'] = 'obligatorisch';
        } elseif (stripos($firstBez, 'Feldschiessen') !== false) {
            $result['typ'] = 'feldschiessen';
        } else {
            $result['typ'] = 'einsatz';
        }
    }

    return $result;
}

/**
 * Parst einen Datums-Header wie "1.Obligatorisch Mittwoch 27. Mai 18:00 – 20:00"
 * oder "Freitag 29. Mai 18:00 – 20:00"
 */
function parseDateHeader($text, $monate) {
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (empty($text)) return null;

    // Bezeichnung: alles vor dem Wochentag oder der ganze Text
    $bezeichnung = $text;

    // Datum extrahieren: "27. Mai" oder "28. August"
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

    // Jahr: aktuelles Jahr verwenden (oder aus Text extrahieren)
    $jahr = date('Y');
    if (preg_match('/(\d{4})/', $text, $m)) {
        $jahr = $m[1];
    }

    $datum = sprintf('%04d-%02d-%02d', $jahr, $monat, $tag);

    // Zeit extrahieren: "18:00 – 20:00", "18.00-20.00", "13:30 – 15:30"
    $zeit = '';
    if (preg_match('/(\d{1,2}[:.]\d{2})\s*[–-]\s*(\d{1,2}[:.]\d{2})/u', $text, $m)) {
        $zeit = str_replace('.', ':', $m[1]) . ' – ' . str_replace('.', ':', $m[2]);
    } elseif (preg_match('/(\d{1,2}[:.]\d{2})\s*Uhr/i', $text, $m)) {
        $zeit = str_replace('.', ':', $m[1]);
    }

    // Bezeichnung kürzen: Nur den beschreibenden Teil
    $bezeichnung = preg_replace('/\d{1,2}\.\s*' . preg_quote($monatsName, '/') . '.*/', '', $bezeichnung);
    $bezeichnung = trim(preg_replace('/\s+/', ' ', $bezeichnung));
    // Wochentag entfernen
    $wochentage = ['Montag', 'Dienstag', 'Mittwoch', 'Mittoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    foreach ($wochentage as $wt) {
        $bezeichnung = preg_replace('/\b' . preg_quote($wt, '/') . '\b/i', '', $bezeichnung);
    }
    $bezeichnung = trim($bezeichnung);
    if (empty($bezeichnung)) {
        $bezeichnung = $tag . '. ' . $monatsName;
    }

    return [
        'bezeichnung' => $bezeichnung,
        'datum'        => $datum,
        'zeit'         => $zeit,
    ];
}

/**
 * Extrahiert alle Textzeilen aus einer Zelle (jede TextRun = eine Zeile).
 */
function getCellLines($cell) {
    $lines = [];
    foreach ($cell->getElements() as $element) {
        if ($element instanceof TextRun) {
            // TextRun::getText() sammelt alle enthaltenen Text-Elemente
            $lines[] = $element->getText();
        } elseif ($element instanceof Text) {
            $lines[] = $element->getText();
        } elseif (method_exists($element, 'getElements')) {
            // Andere Container (z.B. verschachtelte Paragraphen): rekursiv extrahieren
            $innerText = '';
            foreach ($element->getElements() as $child) {
                if ($child instanceof TextRun || $child instanceof Text) {
                    $innerText .= $child->getText();
                }
            }
            if ($innerText !== '') {
                $lines[] = $innerText;
            }
        }
    }
    return $lines;
}

/**
 * Extrahiert den gesamten Text einer Zelle als einen String.
 */
function getCellFullText($cell) {
    $lines = getCellLines($cell);
    return implode(' ', array_map('trim', $lines));
}

/**
 * Baut eine Zuordnung: nameIndex → Funktionsname.
 * Erkennt Gruppen-Header ("Büro:") und ordnet Sub-Funktionen korrekt zu.
 *
 * Beispiel: ["Büro:", "Anmeldung", "Anmeldung", "Munition", "Munition"]
 * Hat die Namensspalte 4 Zeilen, wird daraus:
 *   0 → "Büro: Anmeldung", 1 → "Büro: Anmeldung", 2 → "Büro: Munition", 3 → "Büro: Munition"
 *
 * Beispiel: ["Schützenmeister"] → 0 → "Schützenmeister", 1 → "Schützenmeister", ...
 */
function buildFunktionMap($funktionLines) {
    if (empty($funktionLines)) return [];

    $map = [];
    $groupPrefix = '';
    $nameIdx = 0;

    // Prüfe ob erste Zeile ein Gruppen-Header ist (endet mit ":")
    $firstLine = trim($funktionLines[0] ?? '');
    $hasGroupHeader = str_ends_with($firstLine, ':') && count($funktionLines) > 1;

    if ($hasGroupHeader) {
        // Erste Zeile ist Gruppen-Header, z.B. "Büro:"
        $groupPrefix = rtrim($firstLine, ':');

        // Restliche Zeilen sind die eigentlichen Funktionen
        for ($i = 1; $i < count($funktionLines); $i++) {
            $fn = trim($funktionLines[$i]);
            if (!empty($fn)) {
                $map[$nameIdx] = $groupPrefix . ': ' . $fn;
            } else {
                // Leere Zeile: letzte Funktion beibehalten
                $map[$nameIdx] = $map[$nameIdx - 1] ?? $groupPrefix;
            }
            $nameIdx++;
        }
    } else {
        // Keine Gruppen-Header: jede Zeile ist eine Funktion
        // Erste nicht-leere Zeile gilt für alle nachfolgenden Namen
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

    // Auffüllen für zusätzliche Namen-Zeilen (mehr Namen als Funktionen)
    // → letzte Funktion wiederholen
    $lastFn = end($map) ?: '';
    for ($i = $nameIdx; $i < $nameIdx + 20; $i++) {
        $map[$i] = $lastFn;
    }

    return $map;
}

/**
 * Prüft ob ein Eintrag ignoriert werden soll (andere Vereine).
 */
function isIgnoredEntry($text) {
    $ignored = ['SV Freienbach', 'SV Wollerau'];
    foreach ($ignored as $pattern) {
        if (mb_stripos($text, $pattern) !== false) {
            return true;
        }
    }
    return false;
}
