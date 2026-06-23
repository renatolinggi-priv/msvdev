<?php
// generate_mitglieder_xlsx.php - Excel-Export Adressliste
require '../vendor/autoload.php';
require 'config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

header('Content-Type: application/json; charset=utf-8');

try {
    // Mitglieder laden - alle ausser Verstorbene
    $sql = "SELECT m.ID, m.Anrede, m.Vorname, m.Name, m.Geburtsdatum,
                   m.Status, m.Ehrenmitglied, m.Strasse, m.PLZ, m.Ort,
                   m.Email, m.Telefon, m.Mobile, m.Notizen,
                   m.Vereinsaufnahme, m.Kommunikation
            FROM mitglieder m
            WHERE m.Verstorben = 0
            ORDER BY m.Name ASC, m.Vorname ASC";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception('Datenbankfehler: ' . $conn->error);
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Adressliste');

    // Header definieren
    $headers = [
        'A' => 'Anrede',
        'B' => 'Name',
        'C' => 'Vorname',
        'D' => 'Strasse',
        'E' => 'PLZ',
        'F' => 'Ort',
        'G' => 'Ehrenmitglied',
        'H' => 'Lizenznr',
        'I' => 'Notiz',
        'J' => 'Geb. Datum',
        'K' => 'Telefon',
        'L' => 'Mobile',
        'M' => 'E-Mail Adresse',
        'N' => 'Vereinsaufnahme',
        'O' => 'Briefpost / Whatsapp'
    ];

    foreach ($headers as $col => $title) {
        $sheet->setCellValue($col . '1', $title);
    }

    // Header-Style
    $headerStyle = [
        'font' => ['bold' => true, 'name' => 'Arial', 'size' => 10],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9D9D9']],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A1:O1')->applyFromArray($headerStyle);

    // Daten schreiben
    $row = 2;
    while ($m = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $m['Anrede']);
        $sheet->setCellValue('B' . $row, $m['Name']);
        $sheet->setCellValue('C' . $row, $m['Vorname']);
        $sheet->setCellValue('D' . $row, $m['Strasse']);
        // PLZ als Text (fuehrende Nullen erhalten)
        $sheet->setCellValueExplicit('E' . $row, $m['PLZ'], DataType::TYPE_STRING);
        $sheet->setCellValue('F' . $row, $m['Ort']);
        // Ehrenmitglied: 1 -> "Ja", 0 -> "Nein"
        $sheet->setCellValue('G' . $row, $m['Ehrenmitglied'] == 1 ? 'Ja' : 'Nein');
        $sheet->setCellValue('H' . $row, $m['ID']);
        $sheet->setCellValue('I' . $row, $m['Notizen']);
        // Geburtsdatum als Excel-Datum
        if (!empty($m['Geburtsdatum']) && $m['Geburtsdatum'] !== '0000-00-00') {
            $date = new DateTime($m['Geburtsdatum']);
            $sheet->setCellValue('J' . $row, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date));
            $sheet->getStyle('J' . $row)->getNumberFormat()
                  ->setFormatCode('DD.MM.YYYY');
        }
        $sheet->setCellValue('K' . $row, $m['Telefon']);
        $sheet->setCellValue('L' . $row, $m['Mobile']);
        $sheet->setCellValue('M' . $row, $m['Email']);
        $sheet->setCellValue('N' . $row, $m['Vereinsaufnahme']);
        $sheet->setCellValue('O' . $row, $m['Kommunikation']);
        $row++;
    }

    // Spaltenbreiten
    $widths = [
        'A' => 8, 'B' => 15, 'C' => 12, 'D' => 22, 'E' => 6, 'F' => 14,
        'G' => 14, 'H' => 10, 'I' => 10, 'J' => 12, 'K' => 16,
        'L' => 16, 'M' => 28, 'N' => 16, 'O' => 18
    ];
    foreach ($widths as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }

    // Datenbereich formatieren
    $lastRow = $row - 1;
    if ($lastRow >= 2) {
        $dataStyle = [
            'font' => ['name' => 'Arial', 'size' => 10],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ];
        $sheet->getStyle('A2:O' . $lastRow)->applyFromArray($dataStyle);
    }

    // Speichern und JSON-Response
    $writer = new Xlsx($spreadsheet);
    $date = new DateTime();
    $filename = 'dat/Adressliste_MiFu_' . $date->format('Ymd') . '.xlsx';
    $writer->save($filename);
    echo json_encode(['excel_link' => $filename]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
