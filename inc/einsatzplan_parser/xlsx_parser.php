<?php
// inc/einsatzplan_parser/xlsx_parser.php - Parst Einsatzpläne aus XLSX-Dateien
// Unterstützte Formate:
//   - Schlossturm: Mehrere Vereine, nur MSV Wilen wird importiert (Check-Spalte)
//   - Wiler Chilbi: Person-Zeilen (Nachname/Vorname) mit Einsatztyp je Schicht-Spalte
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Parst einen Einsatzplan aus einer XLSX-Datei.
 * Erkennt automatisch das Format (Schlossturm oder Chilbi).
 *
 * @param string $filepath Pfad zur XLSX-Datei
 * @return array ['success' => bool, 'data' => [...], 'message' => string]
 */
function parseEinsatzplanXlsx($filepath) {
    if (!file_exists($filepath)) {
        return ['success' => false, 'data' => [], 'message' => 'Datei nicht gefunden'];
    }

    try {
        $spreadsheet = IOFactory::load($filepath);
    } catch (Exception $e) {
        return ['success' => false, 'data' => [], 'message' => 'XLSX konnte nicht geladen werden: ' . $e->getMessage()];
    }

    // Sheet auswählen: bevorzugt "definitiv", sonst erstes Sheet
    $sheet = selectSheet($spreadsheet);
    if (!$sheet) {
        return ['success' => false, 'data' => [], 'message' => 'Kein gültiges Arbeitsblatt gefunden'];
    }

    // Format erkennen und entsprechenden Parser verwenden
    if (isChilbiFormat($sheet)) {
        $zuweisungen = parseChilbiDataRows($sheet);
        if (empty($zuweisungen)) {
            return ['success' => false, 'data' => [], 'message' => 'Keine Chilbi-Einsätze gefunden'];
        }
        return ['success' => true, 'data' => $zuweisungen, 'message' => count($zuweisungen) . ' Einsätze erkannt'];
    }

    // --- Schlossturm-Format ---

    // Header-Struktur erkennen
    $header = parseXlsxHeader($sheet);
    if (empty($header['events'])) {
        return ['success' => false, 'data' => [], 'message' => 'Keine Event-Blöcke im Header erkannt'];
    }

    // Titel aus Zeile 1
    $titel = trim($sheet->getCell('A1')->getValue() ?? '');
    $bezeichnung = $titel ?: 'Schlossturmschiessen';

    // Datenzeilen parsen
    $zuweisungen = parseXlsxDataRows($sheet, $header, $bezeichnung);

    if (empty($zuweisungen)) {
        return ['success' => false, 'data' => [], 'message' => 'Keine MSV Wilen Einsätze gefunden'];
    }

    return ['success' => true, 'data' => $zuweisungen, 'message' => count($zuweisungen) . ' Einsätze erkannt'];
}

// ============================================================
//  WILER CHILBI - Format-Erkennung und Parser
// ============================================================

/**
 * Prüft ob das Sheet im Chilbi-Format vorliegt.
 * Erkennungsmerkmal: Zeile 1 enthält "Chilbi" in einer der ersten 5 Spalten.
 */
function isChilbiFormat($sheet) {
    $highestColIdx = Coordinate::columnIndexFromString($sheet->getHighestColumn());
    for ($c = 1; $c <= min($highestColIdx, 5); $c++) {
        $col = Coordinate::stringFromColumnIndex($c);
        $val = trim($sheet->getCell($col . '1')->getValue() ?? '');
        if (mb_stripos($val, 'Chilbi') !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Normalisiert Chilbi-Zeitangaben mit unklaren Endzeiten.
 *   "17:00-fertig"  → "17:00 - 22:00"
 *   "19:45-Ende"    → "19:45 - 04:00"
 * Varianten mit Leerzeichen um den Bindestrich werden ebenfalls erkannt.
 */
function normalizeChilbiZeit($zeitStr) {
    if (empty($zeitStr)) return $zeitStr;

    // "HH:MM[-]fertig" → "HH:MM - 22:00"
    if (preg_match('/^(\d{1,2}:\d{2})\s*-\s*fertig$/i', $zeitStr, $m)) {
        return $m[1] . ' - 22:00';
    }

    // "HH:MM[-]Ende" → "HH:MM - 04:00"
    if (preg_match('/^(\d{1,2}:\d{2})\s*-\s*Ende$/i', $zeitStr, $m)) {
        return $m[1] . ' - 04:00';
    }

    return $zeitStr;
}

/**
 * Parst das Wiler Chilbi XLSX-Format.
 *
 * Struktur:
 *   Zeile 1: Titel (z.B. "Wiler Chilbi 2025")
 *   Zeile 3: Datum-Header je Schicht-Spalte (ab Col C), z.B. "Do. 20.11.2025"
 *            Spalten ohne Datum erben das Datum der letzten Datumsspalte (mehrere Schichten pro Tag)
 *   Zeile 4: Zeitangabe je Schicht (z.B. "16:00-19:00", "19:45-Ende")
 *   Zeilen 5+: Personen – Col A = Nachname, Col B = Vorname,
 *              Col C–Ende = Einsatztyp wenn eingeteilt (z.B. "Einsatz Stand", "Chef")
 *
 * @return array Zuweisungs-Array im Standard-Format
 */
function parseChilbiDataRows($sheet) {
    $zuweisungen = [];
    $highestColIdx = Coordinate::columnIndexFromString($sheet->getHighestColumn());

    // Titel aus Zeile 1 (erste nicht-leere Zelle)
    $bezeichnung = 'Wiler Chilbi';
    for ($c = 1; $c <= min($highestColIdx, 5); $c++) {
        $val = trim($sheet->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue() ?? '');
        if (!empty($val)) {
            $bezeichnung = $val;
            break;
        }
    }

    // Schicht-Spalten aus Zeile 3 (Datum) und Zeile 4 (Zeit) aufbauen
    // Schlüssel = Spalten-Index, Wert = ['datum' => 'YYYY-MM-DD', 'zeit' => 'HH:MM-...']
    $schichten = [];
    $lastDatum = '';

    for ($c = 3; $c <= $highestColIdx; $c++) {
        $col = Coordinate::stringFromColumnIndex($c);

        // Datum aus Zeile 3: "Do. 20.11.2025", "Fr. 21.11.2025", etc.
        $datumVal = trim($sheet->getCell($col . '3')->getValue() ?? '');
        if (!empty($datumVal) && preg_match('/(\d{1,2})\.(\d{2})\.(\d{4})/', $datumVal, $m)) {
            $lastDatum = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        if (empty($lastDatum)) continue;

        // Zeit aus Zeile 4: kann als Text ("16:00-19:00") oder Excel-Dezimal gespeichert sein
        $zeitCell = $sheet->getCell($col . '4');
        $zeitRaw = $zeitCell->getValue();
        if (is_numeric($zeitRaw) && $zeitRaw > 0 && $zeitRaw < 1) {
            // Excel-Zeitdezimal (z.B. 0.708333... = 17:00) → HH:MM
            $totalMin = (int)round($zeitRaw * 1440);
            $zeitStr = sprintf('%02d:%02d', intdiv($totalMin, 60), $totalMin % 60);
        } else {
            $zeitStr = trim($zeitRaw ?? '');
        }

        $zeitStr = normalizeChilbiZeit($zeitStr);

        // Spalten ohne irgendeinen Inhalt in Zeile 3 und 4 überspringen
        if (empty($datumVal) && $zeitStr === '') continue;

        $schichten[$c] = [
            'datum' => $lastDatum,
            'zeit'  => $zeitStr,
        ];
    }

    if (empty($schichten)) {
        return [];
    }

    // Datenzeilen ab Zeile 5
    $maxRow = $sheet->getHighestRow();
    for ($row = 5; $row <= $maxRow; $row++) {
        $nachname = trim($sheet->getCell('A' . $row)->getValue() ?? '');
        $vorname  = trim($sheet->getCell('B' . $row)->getValue() ?? '');

        // Leere Zeilen überspringen
        if ($nachname === '' && $vorname === '') continue;

        // Footer-Zeilen erkennen (Hinweise, Regeln, etc.)
        if (preg_match('/^(Wer|Total|Hinweis|Regel|Bei|Sollte|Bitte|Anmerk)/i', $nachname)) {
            break;
        }

        $mitglied_name = trim($nachname . ' ' . $vorname);

        foreach ($schichten as $colIdx => $schicht) {
            $col = Coordinate::stringFromColumnIndex($colIdx);
            $funktion = trim($sheet->getCell($col . $row)->getValue() ?? '');

            if ($funktion === '') continue;

            $zuweisungen[] = [
                'typ'           => 'einsatz',
                'bezeichnung'   => $bezeichnung,
                'event_datum'   => $schicht['datum'],
                'event_zeit'    => $schicht['zeit'],
                'funktion'      => $funktion,
                'mitglied_name' => $mitglied_name,
            ];
        }
    }

    return $zuweisungen;
}

/**
 * Wählt das passende Sheet aus der Arbeitsmappe.
 * Bevorzugt Sheets mit "definitiv" im Namen, sonst das letzte Sheet.
 */
function selectSheet($spreadsheet) {
    $sheetNames = $spreadsheet->getSheetNames();
    if (empty($sheetNames)) return null;

    // Bei nur einem Sheet → dieses nehmen
    if (count($sheetNames) === 1) {
        return $spreadsheet->getSheet(0);
    }

    // Sheet mit "definitiv" im Namen suchen (neueste Version bevorzugen)
    $candidates = [];
    foreach ($sheetNames as $i => $name) {
        if (mb_stripos($name, 'definitiv') !== false) {
            $candidates[$i] = $name;
        }
    }

    if (!empty($candidates)) {
        // Höchste Version nehmen (z.B. "2.0" > "")
        $bestIdx = array_key_last($candidates);
        return $spreadsheet->getSheet($bestIdx);
    }

    // Fallback: letztes Sheet mit "Einteilung" im Namen
    for ($i = count($sheetNames) - 1; $i >= 0; $i--) {
        if (mb_stripos($sheetNames[$i], 'Einteilung') !== false) {
            return $spreadsheet->getSheet($i);
        }
    }

    // Letzter Fallback: erstes Sheet
    return $spreadsheet->getSheet(0);
}

/**
 * Parst die Header-Struktur des Sheets.
 * Erkennt Event-Blöcke (Datum, Zeit, Name-Spalte, MSV-Wilen-Spalte).
 *
 * @return array ['events' => [...], 'data_start' => int]
 */
function parseXlsxHeader($sheet) {
    $result = ['events' => [], 'data_start' => 1];

    $maxRow = min($sheet->getHighestRow(), 15); // Header ist in den ersten ~10 Zeilen
    $highestCol = $sheet->getHighestColumn();

    $datumRow = null;
    $zeitRow = null;
    $nameRow = null;

    // Marker-Zeilen finden
    for ($row = 1; $row <= $maxRow; $row++) {
        $colA = trim($sheet->getCell('A' . $row)->getValue() ?? '');

        if (mb_stripos($colA, 'Datum') !== false) {
            $datumRow = $row;
        } elseif (mb_stripos($colA, 'Zeit') !== false) {
            $zeitRow = $row;
        }

        // "Name / Vorname" kann in Spalte B stehen
        if ($nameRow === null) {
            for ($c = 'A'; $c <= $highestCol; $c++) {
                $val = trim($sheet->getCell($c . $row)->getValue() ?? '');
                if (preg_match('/Name\s*\/?\s*Vorname/i', $val)) {
                    $nameRow = $row;
                    break;
                }
            }
        }
    }

    if (!$datumRow) {
        return $result;
    }

    // Event-Blöcke erkennen: Spalten mit Datumswerten in der Datum-Zeile
    $eventBlocks = detectEventBlocks($sheet, $datumRow, $highestCol);

    // MSV Wilen Spalten identifizieren
    foreach ($eventBlocks as &$block) {
        $block['msvCol'] = findMsvWilenColumn($sheet, $datumRow, $block);
        // Zeit aus Zeit-Zeile (Text oder Excel-Dezimal)
        if ($zeitRow) {
            $zeitCell = $sheet->getCell($block['nameCol'] . $zeitRow);
            $zeitRaw  = $zeitCell->getValue();
            if (is_numeric($zeitRaw) && $zeitRaw > 0 && $zeitRaw < 1) {
                // Excel-Zeitdezimal (z.B. 0.333... = 08:00) → nur Startzeit
                $totalMin = (int)round($zeitRaw * 1440);
                $parsed   = sprintf('%02d:%02d', intdiv($totalMin, 60), $totalMin % 60);
            } else {
                $parsed = parseXlsxZeit(trim($zeitRaw ?? ''));
            }
            // Nur überschreiben wenn etwas gefunden, sonst Datum-Zellen-Fallback behalten
            if (!empty($parsed)) {
                $block['zeit'] = $parsed;
            }
        }
    }

    // Blöcke ohne MSV-Wilen-Spalte entfernen
    $eventBlocks = array_filter($eventBlocks, fn($b) => $b['msvCol'] !== null);

    $result['events'] = array_values($eventBlocks);
    $result['data_start'] = ($nameRow ?? $datumRow) + 1;

    // Erste nicht-leere Datenzeile nach Header finden
    for ($row = $result['data_start']; $row <= $result['data_start'] + 5; $row++) {
        $colA = trim($sheet->getCell('A' . $row)->getValue() ?? '');
        if (!empty($colA) && !preg_match('/^(Datum|Zeit|Treffpunkt|Büro|Name)/i', $colA)) {
            $result['data_start'] = $row;
            break;
        }
    }

    return $result;
}

/**
 * Erkennt Event-Blöcke anhand der Datum-Zeile.
 * Jeder Block beginnt mit einer Spalte die ein Datum enthält.
 */
function detectEventBlocks($sheet, $datumRow, $highestCol) {
    $blocks = [];

    for ($c = 'B'; $c <= $highestCol; $c++) {
        $val = trim($sheet->getCell($c . $datumRow)->getValue() ?? '');
        if (empty($val)) continue;

        // Datum erkennen: "Samstag, 13.04.2024" oder "Samstag, 13,04,2024" (Komma oder Punkt)
        if (preg_match('/(\d{1,2})[.,](\d{2})[.,](\d{4})/', $val, $m)) {
            $datum = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);

            // Bezeichnung: Wochentag + Datum
            $bezeichnungPart = preg_replace('/,?\s*\d{1,2}[.,]\d{2}[.,]\d{4}/', '', $val);
            $bezeichnungPart = trim($bezeichnungPart);

            // Zeit aus Datum-Zelle extrahieren (Fallback, z.B. "Sa 13.04.2024 / 08:00-17:00")
            $zeitImDatum = '';
            if (preg_match('/(\d{1,2})[.:h](\d{2})\s*[-–]\s*(\d{1,2})[.:h](\d{2})/', $val, $zt)) {
                $zeitImDatum = sprintf('%02d:%02d – %02d:%02d', $zt[1], $zt[2], $zt[3], $zt[4]);
            }

            $blocks[] = [
                'nameCol'     => $c,
                'datum'        => $datum,
                'bezeichnung'  => $bezeichnungPart ?: $val,
                'msvCol'       => null,
                'zeit'         => $zeitImDatum,
            ];
        }
    }

    return $blocks;
}

/**
 * Findet die MSV Wilen Check-Spalte für einen Event-Block.
 * Sucht in den Spalten nach dem Block-Start nach "MSV" oder "Wilen".
 */
function findMsvWilenColumn($sheet, $datumRow, $block) {
    $nameColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($block['nameCol']);

    // Die nächsten 3-4 Spalten nach der Name-Spalte prüfen
    for ($offset = 1; $offset <= 4; $offset++) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($nameColIdx + $offset);

        // In den Header-Zeilen (Row 3-7) nach "MSV" suchen
        for ($row = max(1, $datumRow - 1); $row <= $datumRow + 5; $row++) {
            $val = trim($sheet->getCell($col . $row)->getValue() ?? '');
            if (mb_stripos($val, 'MSV') !== false || mb_stripos($val, 'Wilen') !== false) {
                return $col;
            }
        }

        // Auch Merged Cells prüfen
        foreach ($sheet->getMergeCells() as $mergeRange) {
            if (preg_match('/^' . preg_quote($col, '/') . '(\d+):/', $mergeRange)) {
                $mergedVal = trim($sheet->getCell($col . '1')->getValue() ?? '');
                for ($r = 1; $r <= $datumRow + 5; $r++) {
                    $mergedVal = trim($sheet->getCell($col . $r)->getValue() ?? '');
                    if (mb_stripos($mergedVal, 'MSV') !== false || mb_stripos($mergedVal, 'Wilen') !== false) {
                        return $col;
                    }
                }
            }
        }
    }

    return null;
}

/**
 * Parst die Datenzeilen und extrahiert MSV Wilen Einsätze.
 */
function parseXlsxDataRows($sheet, $header, $titelBezeichnung) {
    $zuweisungen = [];
    $maxRow = $sheet->getHighestRow();
    $currentFunktion = '';

    for ($row = $header['data_start']; $row <= $maxRow; $row++) {
        $colA = trim($sheet->getCell('A' . $row)->getValue() ?? '');

        // Funktion aktualisieren
        if (!empty($colA)) {
            // Prüfe ob es ein Info-/Footer-Bereich ist
            if (preg_match('/^(Wichtige|Total|Diverse|Anteil|OK-Mitgl)/i', $colA)) {
                break; // Ende der Haupttabelle
            }
            $currentFunktion = $colA;
        }

        if (empty($currentFunktion)) continue;

        // Prüfen ob diese Zeile eine Totalzeile ist (nur Zahlen in Check-Spalten, keine Namen)
        $hasName = false;
        foreach ($header['events'] as $event) {
            $name = trim($sheet->getCell($event['nameCol'] . $row)->getValue() ?? '');
            if (!empty($name) && preg_match('/[a-zA-ZäöüÄÖÜéèêàáâ]/u', $name)) {
                $hasName = true;
                break;
            }
        }
        if (!$hasName && !empty($colA) && preg_match('/^\d/', $colA)) {
            // Zeile mit Funktion die wie eine Zahl beginnt → vermutlich Totalzeile
            break;
        }

        // Pro Event-Block prüfen
        foreach ($header['events'] as $event) {
            $name = trim($sheet->getCell($event['nameCol'] . $row)->getValue() ?? '');
            if (empty($name)) continue;

            // Nur Einträge mit Buchstaben (keine reinen Zahlen)
            if (!preg_match('/[a-zA-ZäöüÄÖÜéèêàáâ]/u', $name)) continue;

            // MSV Wilen Check prüfen
            $msvCheck = $sheet->getCell($event['msvCol'] . $row)->getValue();
            if ($msvCheck === null || $msvCheck === '') continue;

            // Klammerzusätze aus Namen entfernen, z.B. "Schuler Werner (Büro)"
            $cleanName = preg_replace('/\s*\(.*?\)\s*/', '', $name);
            $cleanName = trim($cleanName);

            if (empty($cleanName)) continue;

            $zuweisungen[] = [
                'typ'           => 'einsatz',
                'bezeichnung'   => $titelBezeichnung,
                'event_datum'   => $event['datum'],
                'event_zeit'    => $event['zeit'],
                'funktion'      => $currentFunktion,
                'mitglied_name' => $cleanName,
            ];
        }
    }

    return $zuweisungen;
}

/**
 * Parst eine Zeit-Angabe aus dem Excel.
 * "08.00 - 12.00 Uhr" → "08:00 – 12:00"
 * "13.30 - 17.00" → "13:30 – 17:00"
 */
function parseXlsxZeit($text) {
    if (empty($text)) return '';

    // Format: "08.00 - 12.00 Uhr" oder "08:00 - 12:00"
    if (preg_match('/(\d{1,2})[.:](\d{2})\s*-\s*(\d{1,2})[.:](\d{2})/', $text, $m)) {
        return sprintf('%02d:%02d – %02d:%02d', $m[1], $m[2], $m[3], $m[4]);
    }

    return '';
}
