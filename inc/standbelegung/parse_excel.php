<?php
// parse_excel.php - Parst den Standbelegungsplan aus Excel
require_once '../config.php';
require_once '../spreadsheet/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage']);
    exit;
}

// File Upload prüfen
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Datei-Upload fehlgeschlagen']);
    exit;
}

$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

try {
    // Excel laden
    $spreadsheet = IOFactory::load($_FILES['file']['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();
    
    $termine = [];
    $currentMonthLeft = '';
    $currentMonthRight = '';
    
    // Deutsche Monatsnamen zu Zahlen
    $monate = [
        'januar' => 1, 'februar' => 2, 'märz' => 3, 'maerz' => 3,
        'april' => 4, 'mai' => 5, 'juni' => 6,
        'juli' => 7, 'august' => 8, 'september' => 9,
        'oktober' => 10, 'november' => 11, 'dezember' => 12
    ];
    
    foreach ($rows as $rowIndex => $row) {
        // Linke Seite (Spalten 0-3)
        $leftEntry = parseEntry($row, 0, $currentMonthLeft, $year, $monate);
        if ($leftEntry) {
            if ($leftEntry['type'] === 'month') {
                $currentMonthLeft = $leftEntry['month'];
            } else {
                $termine[] = $leftEntry;
            }
        }
        
        // Rechte Seite (Spalten 5-8)
        $rightEntry = parseEntry($row, 5, $currentMonthRight, $year, $monate);
        if ($rightEntry) {
            if ($rightEntry['type'] === 'month') {
                $currentMonthRight = $rightEntry['month'];
            } else {
                $termine[] = $rightEntry;
            }
        }
    }
    
    // Nach Datum sortieren
    usort($termine, function($a, $b) {
        $dateA = DateTime::createFromFormat('d.m.Y', $a['datum']);
        $dateB = DateTime::createFromFormat('d.m.Y', $b['datum']);
        if (!$dateA || !$dateB) return 0;
        return $dateA <=> $dateB;
    });
    
    // Statistiken berechnen
    $stats = [
        '300m' => 0,
        '50m' => 0,
        '25m' => 0,
        '10m' => 0,
        'Sonstiges' => 0
    ];
    
    foreach ($termine as $t) {
        if (isset($stats[$t['kategorie']])) {
            $stats[$t['kategorie']]++;
        } else {
            $stats['Sonstiges']++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $termine,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Parst einen Eintrag aus einer Zeile
 */
function parseEntry($row, $startCol, $currentMonth, $year, $monate) {
    $wochentag = isset($row[$startCol]) ? trim($row[$startCol]) : '';
    $tag = isset($row[$startCol + 1]) ? trim(str_replace('.', '', $row[$startCol + 1])) : '';
    $bezeichnung = isset($row[$startCol + 2]) ? trim($row[$startCol + 2]) : '';
    $zeit = isset($row[$startCol + 3]) ? trim($row[$startCol + 3]) : '';
    
    // Leere Zeile überspringen
    if (empty($bezeichnung) && empty($wochentag)) {
        return null;
    }
    
    // Monatsüberschrift erkennen (z.B. "März", "April")
    if (empty($wochentag) && empty($tag) && !empty($bezeichnung)) {
        $monatLower = mb_strtolower($bezeichnung, 'UTF-8');
        if (isset($monate[$monatLower])) {
            return ['type' => 'month', 'month' => $monate[$monatLower]];
        }
    }
    
    // Kein gültiger Wochentag = überspringen
    $validDays = ['MO', 'DI', 'MI', 'DO', 'FR', 'SA', 'SO'];
    if (!in_array(strtoupper($wochentag), $validDays)) {
        return null;
    }
    
    // Tag muss eine Zahl sein
    if (!is_numeric($tag)) {
        return null;
    }
    
    // Datum erstellen
    $monat = $currentMonth ?: 1;
    $datum = sprintf('%02d.%02d.%d', intval($tag), $monat, $year);
    
    // Zeit parsen
    $zeitParsed = parseZeit($zeit);
    
    // Kategorie erkennen
    $kategorie = detectKategorie($bezeichnung);
    
    return [
        'type' => 'entry',
        'datum' => $datum,
        'wochentag' => strtoupper($wochentag),
        'bezeichnung' => $bezeichnung,
        'start_zeit' => $zeitParsed['start'],
        'end_zeit' => $zeitParsed['end'],
        'kategorie' => $kategorie
    ];
}

/**
 * Parst die Zeitangabe
 * Formate: "1800", "0800 - 1200", "ab 2000"
 */
function parseZeit($zeit) {
    $zeit = trim($zeit);
    
    if (empty($zeit)) {
        return ['start' => null, 'end' => null];
    }
    
    // "ab 2000" Format
    if (preg_match('/ab\s*(\d{3,4})/i', $zeit, $matches)) {
        $start = formatTime($matches[1]);
        return ['start' => $start, 'end' => '23:59'];
    }
    
    // "0800 - 1200" Format (mit oder ohne Leerzeichen, mit verschiedenen Trennzeichen)
    if (preg_match('/(\d{3,4})\s*[-–]\s*(\d{3,4})/', $zeit, $matches)) {
        return [
            'start' => formatTime($matches[1]),
            'end' => formatTime($matches[2])
        ];
    }
    
    // Nur Startzeit "1800"
    if (preg_match('/^(\d{3,4})$/', $zeit, $matches)) {
        $start = formatTime($matches[1]);
        return ['start' => $start, 'end' => '23:59'];
    }
    
    return ['start' => null, 'end' => null];
}

/**
 * Formatiert Zeit von "1730" zu "17:30"
 */
function formatTime($time) {
    $time = str_pad($time, 4, '0', STR_PAD_LEFT);
    return substr($time, 0, 2) . ':' . substr($time, 2, 2);
}

/**
 * Erkennt die Kategorie aus der Bezeichnung
 */
function detectKategorie($bezeichnung) {
    $bez = mb_strtolower($bezeichnung, 'UTF-8');
    
    // 300m Erkennung
    if (preg_match('/300\s*m|gewehr\s*300|g\s*300/i', $bez)) {
        return '300m';
    }
    
    // 50m KK Erkennung
    if (preg_match('/kk\s*50|50\s*m\s*kk|kleinkal|50\s*m(?!\s*gk)/i', $bez)) {
        return '50m';
    }
    
    // 25m Pistole Erkennung
    if (preg_match('/25\s*m|pist|p\s*25/i', $bez)) {
        return '25m';
    }
    
    // 10m Luft Erkennung
    if (preg_match('/10\s*m|luft|lp\s|lg\s/i', $bez)) {
        return '10m';
    }
    
    // 50m GK Erkennung (nach 50m KK, um Konflikte zu vermeiden)
    if (preg_match('/50\s*m\s*gk|gk\s*50/i', $bez)) {
        return '50m';
    }
    
    // Spezifische Schiessanlässe
    if (preg_match('/feldschiessen|eidgenössisch|esf|bundesprogramm/i', $bez)) {
        return '300m';
    }
    
    if (preg_match('/jungschütz|js[-\s]?kurs|schülerschiessen/i', $bez)) {
        return '50m';
    }
    
    // Default
    return 'Sonstiges';
}
