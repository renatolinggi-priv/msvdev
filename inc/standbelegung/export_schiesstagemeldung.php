<?php
// export_schiesstagemeldung.php - Exportiert Standbelegung als Schiesstagemeldung Excel
require_once '../config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['entries']) || !is_array($input['entries'])) {
    echo json_encode(['success' => false, 'message' => 'Keine Einträge angegeben']);
    exit;
}

$entries = $input['entries'];
$source = $input['source'] ?? 'overview'; // 'overview' oder 'import'

if (empty($entries)) {
    echo json_encode(['success' => false, 'message' => 'Keine gültigen Einträge']);
    exit;
}

try {
    // Excel erstellen
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Schiesstagemeldung');
    
    // Header
    $headers = ['Disziplin', 'Datum', 'Von', 'Bis', 'Art', 'Anlass', 'Anlass auf Schiessanlage?', 'Anderer Ort'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }
    
    // Header Style
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E0E0E0']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ];
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
    
    // Daten einfügen
    $row = 2;
    foreach ($entries as $entry) {
        // Disziplin
        $kategorie = $entry['Kategorie'] ?? $entry['kategorie'] ?? 'Sonstiges';
        $disziplin = mapDisziplin($kategorie);
        
        // Datum formatieren
        $datumRaw = $entry['Datum'] ?? $entry['datum'] ?? '';
        if (strpos($datumRaw, '-') !== false) {
            // DB-Format yyyy-mm-dd -> dd.mm.yyyy
            $datum = date('d.m.Y', strtotime($datumRaw));
        } else {
            // Bereits im Format dd.mm.yyyy
            $datum = $datumRaw;
        }
        
        // Zeit formatieren
        $startZeit = $entry['StartZeit'] ?? $entry['start_zeit'] ?? '';
        $endZeit = $entry['EndZeit'] ?? $entry['end_zeit'] ?? '';
        $von = $startZeit ? substr($startZeit, 0, 5) : '';
        $bis = $endZeit ? substr($endZeit, 0, 5) : '';
        
        // Art
        $art = $entry['art'] ?? 'AND';
        
        // Bezeichnung
        $bezeichnung = $entry['Bezeichnung'] ?? $entry['bezeichnung'] ?? '';
        
        $sheet->setCellValue('A' . $row, $disziplin);
        $sheet->setCellValue('B' . $row, $datum);
        $sheet->setCellValue('C' . $row, $von);
        $sheet->setCellValue('D' . $row, $bis);
        $sheet->setCellValue('E' . $row, $art);
        $sheet->setCellValue('F' . $row, $bezeichnung);
        $sheet->setCellValue('G' . $row, 'Ja');
        $sheet->setCellValue('H' . $row, '');
        
        $row++;
    }
    
    // Spaltenbreiten anpassen
    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(8);
    $sheet->getColumnDimension('D')->setWidth(8);
    $sheet->getColumnDimension('E')->setWidth(8);
    $sheet->getColumnDimension('F')->setWidth(40);
    $sheet->getColumnDimension('G')->setWidth(22);
    $sheet->getColumnDimension('H')->setWidth(25);
    
    // Datenborder
    $lastRow = $row - 1;
    if ($lastRow >= 2) {
        $dataStyle = [
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ];
        $sheet->getStyle('A2:H' . $lastRow)->applyFromArray($dataStyle);
    }
    
    // Speichern
    $filename = 'Schiesstagemeldung_' . date('Y-m-d_H-i-s') . '.xlsx';
    $filepath = 'dat/' . $filename;
    
    // Verzeichnis erstellen falls nicht vorhanden
    if (!is_dir('dat')) {
        mkdir('dat', 0755, true);
    }
    
    $writer = new Xlsx($spreadsheet);
    $writer->save($filepath);
    
    echo json_encode([
        'success' => true,
        'file' => 'standbelegung/' . $filepath,
        'filename' => $filename,
        'count' => count($entries)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}

/**
 * Mappt Kategorie zu Disziplin-Kürzel
 */
function mapDisziplin($kategorie) {
    $mapping = [
        '300m' => 'G300',
        '50m' => 'KK50',
        '25m' => 'P25',
        '10m' => 'LG10'
    ];
    return $mapping[$kategorie] ?? $kategorie;
}
