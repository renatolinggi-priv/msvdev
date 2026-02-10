<?php

//generate_schuetzenabr_xls.php
require '../vendor/autoload.php'; // Pfad zu Composer's autoload Datei
require '../config.php'; // Deine Konfigurationsdatei
require 'functions.inc.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing; // Klasse für das Zeichnen von Bildern

// Jahr-Parameter verarbeiten
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// SQL-Abfrage, um die Mitgliederinformationen zu erhalten
$sqlGetSchuetzen = "
   SELECT m.ID, m.Ehrenmitglied, m.Name, m.Vorname, w.Kategorie FROM `mitglieder` m
join Waffen w on m.WaffenID = w.ID
WHERE m.Status = 1
ORDER BY Name ASC, Vorname ASC
";

$schuetzen = $conn->query($sqlGetSchuetzen);

// Neues Spreadsheet-Objekt erstellen
$spreadsheet = new Spreadsheet();

// Für jedes Mitglied ein neues Tabellenblatt erstellen
while ($schuetze = $schuetzen->fetch_assoc()) {

    $TotalPlus   = 0;
    $TotalMinus  = 0;
    $TotalGesamt = 0;
    $Zelle = 11;
    $ZelleStart = 10;
    $ZelleName = 8;
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle($schuetze['Name'] . ' ' . $schuetze['Vorname']);

    // Spalte A breiter machen
    $sheet->getColumnDimension('A')->setWidth(40);
    $sheet->getColumnDimension('B')->setWidth(10);
    $sheet->getColumnDimension('C')->setWidth(10);

    // Zellen A3 bis C3 verbinden
    $sheet->mergeCells('A3:C3');
    // Schriftart und -größe für die verbundene Zelle A3:C3 ändern
    $sheet->getStyle('A3')->getFont()->setName('Arial')->setSize(14)->setBold(true);
    $sheet->getStyle('A3:C3')->getAlignment()->setWrapText(true);
    $sheet->getStyle($ZelleName)->getFont()->setName('Arial')->setSize(13)->setBold(true);
  
    // Kopfzeile fett formatieren
    $sheet->getStyle('A1:B1')->getFont()->setBold(true);
    $sheet->getStyle('A8:A8')->getFont()->setBold(true);

      // Bild hinzufügen
      $drawing = new Drawing();
      $drawing->setName('Logo');
      $drawing->setDescription('Dieses Bild ist ein Logo.');
      $drawing->setPath('dat/MSVWilen_Logo.jpg'); // Pfad zum Bild
      $drawing->setHeight(100); // Höhe des Bildes (optional, abhängig von deiner Bildgröße)
      $drawing->setCoordinates('A1'); // Positionierung des Bildes in der Zelle
      $drawing->setOffsetX(0); // Optional: Horizontaler Offset in der Zelle
      $drawing->setOffsetY(0); // Optional: Vertikaler Offset in der Zelle
      $drawing->setWorksheet($sheet);
    // Daten in das Tabellenblatt einfügen
    
    $sheet->setCellValue('A3', 'Mitgliederabrechnung ' . $selectedYear);
    $sheet->setCellValue('B' .$ZelleStart, '-');
    $sheet->setCellValue('C' .$ZelleStart, '+');
    $sheet->setCellValue('A' .$ZelleName, $schuetze['Name'] .' ' .$schuetze['Vorname']);

    $sheet->setCellValue('A' .$Zelle, "Mitgliederbeitrag");
    // Mitgliederbeitrag basierend auf Ehrenmitgliedschaft
    if ($schuetze['Ehrenmitglied'] == 0) {  
        $sheet->setCellValue('B' .$Zelle, "10");
        $TotalMinus = 10;
    } else {
        $sheet->setCellValue('B' .$Zelle, "0");
    }
    $Zelle++;
    $KantiMessage = "Kantonalstich (Durch MSV bezahlt CHF " .getKantiCost($schuetze['ID']) .",00)";
    $sheet->setCellValue('A' .$Zelle, $KantiMessage);
    $sheet->setCellValue('B' .$Zelle, " ");
    //$TotalMinus += getKantiCost($schuetze['ID'], $selectedYear);
    $Zelle++;

    $sheet->setCellValue('A' .$Zelle, "Endschiessen");
    $Zelle++;
    
    $sheet->setCellValue('A' .$Zelle, "Endstich");
    $sheet->setCellValue('C' .$Zelle, getKKEnd($schuetze['ID'], $selectedYear));
    $TotalPlus += getKKEnd($schuetze['ID'], $selectedYear);
    $Zelle++;

    $sheet->setCellValue('A' .$Zelle, "Kunststich");
    $sheet->setCellValue('C' .$Zelle, getKKKunst($schuetze['ID'], $selectedYear));
    $TotalPlus += getKKKunst($schuetze['ID'], $selectedYear);
    $Zelle++;

    $sheet->setCellValue('A' .$Zelle, "Endschiessen");
    $sheet->setCellValue('C' .$Zelle, getKKEndschiessen($schuetze['ID'], $schuetze['Kategorie'], $selectedYear));
    $TotalPlus += getKKEndschiessen($schuetze['ID'], $schuetze['Kategorie'], $selectedYear);
    $Zelle++;

    $sheet->setCellValue('A' .$Zelle, "Endschiessen Gesamt");
    $sheet->setCellValue('C' .$Zelle, getKKEndschiessenGesamt($schuetze['ID'], $schuetze['Kategorie'], $selectedYear));
    $TotalPlus += getKKEndschiessenGesamt($schuetze['ID'], $schuetze['Kategorie'], $selectedYear);
    $Zelle++;

    $sheet->setCellValue('A' .$Zelle, "Heimmeisterschaft");
    $sheet->setCellValue('C' .$Zelle, getKKHeimmeisterschaft($schuetze['ID'], $schuetze['Kategorie'], $selectedYear));
    $TotalPlus += getKKHeimmeisterschaft($schuetze['ID'], $schuetze['Kategorie'], $selectedYear);
    $Zelle++;

    $sheet->setCellValue('A' .$Zelle, "MSV Wilen CUP");
    $sheet->setCellValue('C' .$Zelle, getKKCupFinal($schuetze['ID'], $selectedYear));
    $TotalPlus += getKKCupFinal($schuetze['ID'], $selectedYear);
    $Zelle++;
    $Zelle++;

    $sheet->setCellValue('A' .$Zelle, "Zwischentotal");
    $sheet->setCellValue('B' .$Zelle, $TotalMinus);
    $sheet->setCellValue('C' .$Zelle, $TotalPlus);
    $Zelle++;

    $TotalGesamt = $TotalMinus - $TotalPlus;
    $sheet->setCellValue('A' .$Zelle, "Total");
    if($TotalGesamt < 0){
        $TotalGesamt = $TotalGesamt * -1;
        $sheet->setCellValue('C' .$Zelle, $TotalGesamt);
    }
    else{
        $sheet->setCellValue('B' .$Zelle, $TotalGesamt);
    }

    $sheet->getStyle('A' .$Zelle .':C' .$Zelle)->getFont()->setBold(true);

    $Zelle += 6;
    
    $sheet->mergeCells('A' .$Zelle .':C' .$Zelle);
    $sheet->getStyle('A' .$Zelle .':C' .$Zelle)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DASHED);
    $sheet->setCellValue('A' .$Zelle, "Betrag erhalten: ");
    $Zelle -= 6;

       // Spalten B und C als Währung (CHF) formatieren
    $chfFormat = '[$CHF] #,##0.00'; // Währungsformat für CHF
    $sheet->getStyle('B' .$ZelleStart .':B' .$Zelle)->getNumberFormat()->setFormatCode($chfFormat);
    $sheet->getStyle('C' .$ZelleStart .':C' .$Zelle)->getNumberFormat()->setFormatCode($chfFormat);

    // Zellen zentrieren
    $sheet->getStyle('A1:B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // Zelleninhalte rechtsbündig machen
    $sheet->getStyle('A3:C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); 
    
    $sheet->getStyle('B' .$ZelleStart .':C' .$ZelleStart)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
    $sheet->getStyle('B' .$ZelleStart .':C' .$Zelle)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $ZelleStart++;
    // Rahmen hinzufügen
    $sheet->getStyle('A' .$ZelleStart .':C' .$Zelle)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle('B' .$ZelleStart .':C' .$Zelle)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); 
    
    $sheet->getStyle('A' .$Zelle .':C' .$Zelle)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);
    $sheet->getStyle('A' .$Zelle .':C' .$Zelle)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
    // Hintergrundfarbe der Kopfzeile ändern
    //$sheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_YELLOW);
}

// Das erste Tabellenblatt (Standard) löschen, da es leer ist
$spreadsheet->removeSheetByIndex(0);

// Writer-Objekt erstellen und die Excel-Datei speichern
$writer = new Xlsx($spreadsheet);
$date = new DateTime();
$filename = 'dat/Schuetzenabrechnung_' . $selectedYear . '_' . $date->format('Y-m-d_H-i-s') . '.xlsx';
$writer->save($filename);
echo json_encode(array('excel_link' => $filename));
$conn->close();
?>
