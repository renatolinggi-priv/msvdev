<?php
/**
 * inc/einsatzplanung/xlsx_builder.inc.php – Excel-Export für Layout person_x_schicht (Wiler Chilbi).
 * Raster wie das bisherige Einsatzplan2026.xlsx: A1 Titel, Zeile 3 Datum je Tag (über die Schichten
 * gemergt), Zeile 4 Zeit je Schicht, ab Zeile 5 Personen (Name | Vorname | Einsatztyp je Schicht),
 * Fusstext unterhalb. PhpSpreadsheet from scratch (Muster: mitgliederverwaltung/generate_mitglieder_xlsx.php).
 */
if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/plan_helpers.inc.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

function ep_xlsx_erzeugen(array $plan, array $mitglieder, string $zielPfad): void
{
    if (isset($plan['zellen'])) $plan = ep_plan_ohne_vorschlaege($plan);
    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setTitle('Einsatzplan');

    $termine = $plan['termine'];
    $n = count($termine);
    $lastCol = Coordinate::stringFromColumnIndex(max(3, 2 + $n));

    $sheet->setCellValue('A1', $plan['titel']);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setName('Arial');

    // Zeile 3: Datum (über Schichten eines Tages gemergt), Zeile 4: Zeit
    $i = 0;
    while ($i < $n) {
        $datum = $termine[$i]['datum'];
        $span = 1;
        while ($i + $span < $n && $termine[$i + $span]['datum'] === $datum) $span++;
        $c1 = Coordinate::stringFromColumnIndex(3 + $i);
        $c2 = Coordinate::stringFromColumnIndex(3 + $i + $span - 1);
        $sheet->setCellValue($c1 . '3', ep_datum_kurz($datum));
        if ($span > 1) $sheet->mergeCells($c1 . '3:' . $c2 . '3');
        $i += $span;
    }
    foreach ($termine as $idx => $t) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex(3 + $idx) . '4', ep_zeit_text($t));
    }
    $sheet->setCellValue('A4', 'Name');
    $sheet->setCellValue('B4', 'Vorname');

    // Personen ab Zeile 5
    $row = 5;
    foreach (ep_chilbi_personen($plan, $mitglieder) as $p) {
        $m = $p['mitglied'];
        $sheet->setCellValue('A' . $row, (string)$m['Name']);
        $sheet->setCellValue('B' . $row, (string)$m['Vorname']);
        foreach ($termine as $idx => $t) {
            $zellen = $p['zellen'][(int)$t['id']] ?? [];
            if ($zellen) $sheet->setCellValue(Coordinate::stringFromColumnIndex(3 + $idx) . $row, implode(' / ', array_map(fn($f) => $f['bezeichnung'], $zellen)));
        }
        $row++;
    }
    $lastRow = max(5, $row - 1);

    // Styles
    $sheet->getStyle('A3:' . $lastCol . $lastRow)->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF999999']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    ]);
    $sheet->getStyle('A3:' . $lastCol . '4')->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9D9D9']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle('A5:B' . $lastRow)->getFont()->setBold(true);
    $sheet->getColumnDimension('A')->setWidth(16);
    $sheet->getColumnDimension('B')->setWidth(14);
    for ($c = 3; $c < 3 + $n; $c++) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(15);
    $sheet->freezePane('C5');

    // Fusstext
    $fuss = trim((string)($plan['fusstext'] ?? ''));
    if ($fuss !== '') {
        $r = $lastRow + 3;
        foreach (preg_split('/\r?\n/', $fuss) as $k => $zeile) {
            $sheet->setCellValue('A' . $r, trim($zeile));
            $sheet->getStyle('A' . $r)->getFont()->setBold($k === 0)->setName('Arial')->setSize(10);
            $r++;
        }
    }

    // Druck: quer, auf eine Seite breit
    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
    $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);

    (new Xlsx($ss))->save($zielPfad);
    $ss->disconnectWorksheets();
}
