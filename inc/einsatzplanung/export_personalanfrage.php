<?php
/**
 * inc/einsatzplanung/export_personalanfrage.php – Excel «Personalanfrage» im Layout des OK Schlossturm,
 * je Verein (Standard MSV Wilen), befüllt aus den Verfügbarkeiten des Plans. Ohne Zeilen = leere Vorlage
 * für die anderen Vereine. GET plan_id, [verein=msv|freienbach|wollerau], [leer=1]
 * → {success, link: 'dat/Personalanfrage_<Jahr>_<Verein>_<ts>.xlsx'}
 */
require_once __DIR__ . '/../config.php';          // dat-Cleanup-Hook
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/plan_helpers.inc.php';
if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

header('Content-Type: application/json; charset=utf-8');
$db     = getDB();
$planId = (int)($_GET['plan_id'] ?? 0);
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);
$verein = $_GET['verein'] ?? 'msv';
if (!isset(EP_VEREINE[$verein])) $verein = 'msv';
$leer = !empty($_GET['leer']);

try {
    $rollen = array_filter(EP_ROLLEN, fn($k) => $k !== 'OK', ARRAY_FILTER_USE_KEY);   // Büro … Parkdienst
    $termine = $plan['termine'];
    $zeilen = $leer ? [] : array_values(array_filter($plan['verfuegbarkeit'], fn($v) => $v['verein'] === $verein));

    $ss = new Spreadsheet(); $sh = $ss->getActiveSheet(); $sh->setTitle((string)$plan['jahr']);
    $nR = count($rollen); $nT = count($termine);
    $colRolle0 = 2; $colGap = $colRolle0 + $nR; $colT0 = $colGap + 1; $lastCol = $colT0 + $nT - 1;
    $L = fn(int $c) => Coordinate::stringFromColumnIndex($c);
    $last = $L(max($lastCol, 3));

    $sh->setCellValue('A1', 'Schlossturmschiessen ' . (int)$plan['jahr']); $sh->mergeCells('A1:' . $last . '1');
    $sh->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sh->setCellValue('A3', 'Anfrage Arbeitseinsatz'); $sh->mergeCells('A3:' . $last . '3'); $sh->getStyle('A3')->getFont()->setBold(true)->setSize(12);
    $sh->setCellValue('A5', 'Verein: ' . EP_VEREINE[$verein]); $sh->mergeCells('A5:' . $last . '5'); $sh->getStyle('A5')->getFont()->setBold(true);

    // Kopf Zeile 7/8
    $sh->setCellValue('A7', "Einsatz / Daten\n\nName, Vorname"); $sh->mergeCells('A7:A8');
    $sh->setCellValue($L($colRolle0) . '7', 'als:'); $sh->mergeCells($L($colRolle0) . '7:' . $L($colGap - 1) . '7');
    $sh->setCellValue($L($colT0) . '7', 'möglich am:'); if ($nT > 1) $sh->mergeCells($L($colT0) . '7:' . $L($lastCol) . '7');
    $c = $colRolle0; foreach ($rollen as $k => $label) { $sh->setCellValue($L($c) . '8', $k); $c++; }
    $c = $colT0; foreach ($termine as $t) { $sh->setCellValue($L($c) . '8', ep_datum_lang($t['datum']) . ' ' . (int)$plan['jahr'] . "\n" . ep_zeit_text($t)); $c++; }
    $sh->getStyle('A7:' . $last . '8')->applyFromArray(['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9D9D9']]]);
    $sh->getStyle('A7:A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sh->getRowDimension(8)->setRowHeight(42);

    // Personen ab Zeile 9 (mindestens 18 Zeilen für handschriftliche Ergänzungen)
    $r = 9; $mitglieder = ep_mitglieder_map($db);
    foreach ($zeilen as $v) {
        $name = !empty($v['mitglied_id']) && isset($mitglieder[(int)$v['mitglied_id']]) ? $mitglieder[(int)$v['mitglied_id']]['Name'] . ', ' . $mitglieder[(int)$v['mitglied_id']]['Vorname'] : str_replace(' ', ', ', (string)$v['name_text']);
        $sh->setCellValue('A' . $r, $name);
        $c = $colRolle0; foreach ($rollen as $k => $label) { if (in_array($k, $v['rollen'], true)) $sh->setCellValue($L($c) . $r, 'X'); $c++; }
        $c = $colT0; foreach ($termine as $t) { if (in_array((int)$t['id'], $v['termin_ids'], true)) $sh->setCellValue($L($c) . $r, 'X'); $c++; }
        $r++;
    }
    $lastRow = max($r - 1, 8 + 18);
    $sh->getStyle('A7:' . $last . $lastRow)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF808080']]]]);
    $sh->getStyle($L($colRolle0) . '9:' . $last . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sh->getStyle($L($colGap) . '7:' . $L($colGap) . $lastRow)->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF2F2F2']], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]]]);

    // Fusstext
    $fuss = "Geschätzte Schützen\n\nDer Personalbedarf für das Schlossturmschiessen liegt bei 24 Personen pro Schiesstag.\nDas heisst 8 Personen pro Verein, um die Arbeiten gleichmässig aufzuteilen.\nBitte tragt alle möglichen Einsätze in der Liste ein für die definitive Einteilung.\n\nHerzlichen Dank\nOK Schlossturmschiessen " . (int)$plan['jahr'];
    $sh->setCellValue('A' . ($lastRow + 2), $fuss); $sh->mergeCells('A' . ($lastRow + 2) . ':' . $last . ($lastRow + 2));
    $sh->getStyle('A' . ($lastRow + 2))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    $sh->getRowDimension($lastRow + 2)->setRowHeight(120);

    $sh->getColumnDimension('A')->setWidth(30);
    for ($c = $colRolle0; $c < $colGap; $c++) $sh->getColumnDimension($L($c))->setWidth(15);
    $sh->getColumnDimension($L($colGap))->setWidth(2);
    for ($c = $colT0; $c <= $lastCol; $c++) $sh->getColumnDimension($L($c))->setWidth(18);
    $sh->getStyle('A1:' . $last . ($lastRow + 2))->getFont()->setName('Arial');
    $sh->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);

    $datDir = __DIR__ . '/dat'; if (!is_dir($datDir)) mkdir($datDir, 0775, true);
    $datei = 'Personalanfrage_' . (int)$plan['jahr'] . '_' . ucfirst($verein) . ($leer ? '_leer' : '') . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    (new Xlsx($ss))->save($datDir . '/' . $datei); $ss->disconnectWorksheets();
    ep_json(['success' => true, 'link' => 'dat/' . $datei, 'message' => 'Personalanfrage erstellt (' . count($zeilen) . ' Person(en))']);
} catch (Throwable $e) {
    error_log('[einsatzplanung/export_personalanfrage] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Excel konnte nicht erstellt werden: ' . $e->getMessage()], 500);
}
