<?php
// export_jungschuetzenresultate.php

// Start output buffering to prevent any output before headers
ob_start();

// Autoload von Composer
require_once '../spreadsheet/autoload.php'; // Stellen Sie sicher, dass dieser Pfad korrekt ist

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

try {
    // Datenbankverbindung herstellen
    require_once '../config.php'; // Verwenden Sie require_once für bessere Sicherheit und Vermeidung von Mehrfachinklusionen

    // Kategorie ist immer "E"
    $fixedKategorie = "E";

    // SQL-Abfrage zum Verbinden der Tabellen 'jungschuetzen' und 'jungschuetzen_resultate'
    $sql = "
        SELECT 
            js.AHVNummer,
            js.Name,
            js.Vorname,
            js.Ort,
            js.Geburtsdatum,
            js.KursNummer,
            jrs.Belehrungsschiessen1,
            jrs.Belehrungsschiessen2,
            jrs.Belehrungsschiessen3,
            jrs.Praezisionsschiessen,
            jrs.Pruefungsschiessen,
            jrs.Wettkampfschiessen,
            jrs.Hauptschiessen,
            jrs.Wettschiessen,
            jrs.OPResultat,
            jrs.Anerkennungskarte1,
            jrs.FSResultat,
            jrs.Anerkennungskarte,
            jrs.JU_VE_Durchgang1,
            jrs.JU_VE_Durchgang2
        FROM 
            jungschuetzen js
        LEFT JOIN 
            jungschuetzen_resultate jrs ON js.id = jrs.JungschuetzeID
        ORDER BY 
            js.Name ASC, js.Vorname ASC
    ";

    // Führen Sie die Abfrage aus
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Fehler bei der SQL-Abfrage: " . $conn->error);
    }

    if ($result->num_rows == 0) {
        throw new Exception("Keine Daten gefunden.");
    }

    // Erstellen eines neuen Spreadsheet-Objekts
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Definieren der Header-Zeile
    $headers = [
        'A1' => 'POS',
        'B1' => 'Personennummer',
        'C1' => 'Nachname',
        'D1' => 'Vorname',
        'E1' => 'Wohnort',
        'F1' => 'Jahrgang',
        'G1' => 'Teilnehmer / Kursleiter',
        'H1' => '1. Belehrungsschiessen',
        'I1' => '2. Belehrungsschiessen',
        'J1' => '3. Belehrungsschiessen',
        'K1' => 'Präzisionsschiessen',
        'L1' => 'Prüfungsschiessen',
        'M1' => 'Wettkampfschiessen',
        'N1' => 'Hauptschiessen',
        'O1' => 'Wettschiessen',
        'P1' => 'OP Resultat',
        'Q1' => 'Anerkennungskarte1',
        'R1' => 'FS Resultat',
        'S1' => 'Anerkennungskarte',
        'T1' => 'JU+VE 1. Durchgang',
        'U1' => 'JU+VE 2. Durchgang',
        'V1' => 'JU+VE Kategorie',
    ];

    // Setzen der Header in das Spreadsheet
    foreach ($headers as $cell => $header) {
        $sheet->setCellValue($cell, $header);
    }

    // Formatierung der Header (optional)
    $headerStyle = [
        'font' => [
            'bold' => true,
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        ],
    ];

    $sheet->getStyle('A1:V1')->applyFromArray($headerStyle);

    // Definieren der Zuordnung zwischen Datenbankfeldern und Excel-Spalten
    $fieldMapping = [
        'B' => ['AHVNummer', 'string'],
        'C' => ['Name', 'html'],
        'D' => ['Vorname', 'html'],
        'E' => ['Ort', 'html'],
        'F' => ['Geburtsdatum', 'year'],
        'G' => ['KursNummer', 'participant_course'], // Anpassung hier
        'H' => ['Belehrungsschiessen1', 'numeric'],
        'I' => ['Belehrungsschiessen2', 'numeric'],
        'J' => ['Belehrungsschiessen3', 'numeric'],
        'K' => ['Praezisionsschiessen', 'numeric'],
        'L' => ['Pruefungsschiessen', 'numeric'],
        'M' => ['Wettkampfschiessen', 'numeric'],
        'N' => ['Hauptschiessen', 'numeric'],
        'O' => ['Wettschiessen', 'numeric'],
        'P' => ['OPResultat', 'numeric'],
        'Q' => ['Anerkennungskarte1', 'numeric'],
        'R' => ['FSResultat', 'numeric'],
        'S' => ['Anerkennungskarte', 'numeric'],
        'T' => ['JU_VE_Durchgang1', 'numeric'],
        'U' => ['JU_VE_Durchgang2', 'numeric'],
        'V' => ['JU_VE_Durchgang1', 'conditional_fixed'], // Anpassung hier
    ];

    // Initialisieren der POS-Zählung
    $rowNumber = 2; // Start in der zweiten Zeile
    $pos = 1;

    while ($row = $result->fetch_assoc()) {
        // POS
        $sheet->setCellValue('A' . $rowNumber, $pos);

        foreach ($fieldMapping as $column => [$field, $type]) {
            $value = '';

            switch ($type) {
                case 'string':
                    $value = $row[$field];
                    break;

                case 'html':
                    $value = htmlspecialchars($row[$field], ENT_QUOTES, 'UTF-8');
                    break;

                case 'year':
                    $value = !empty($row[$field]) ? date("Y", strtotime($row[$field])) : '';
                    break;

                case 'participant_course':
                    if (isset($row['KursNummer']) && $row['KursNummer'] != 0) {
                        $value = "Teilnehmer Kurs " . $row['KursNummer'];
                    }
                    break;

                case 'numeric':
                    // Setzen Sie den Wert nur, wenn er nicht 0 ist
                    if (isset($row[$field]) && $row[$field] != 0) {
                        $value = $row[$field];
                    }
                    break;

                case 'conditional_fixed':
                    // Setzen Sie $fixedKategorie nur, wenn JU_VE_Durchgang1 nicht 0 ist
                    if (isset($row['JU_VE_Durchgang1']) && $row['JU_VE_Durchgang1'] != 0) {
                        $value = $fixedKategorie;
                    }
                    break;
            }

            if ($value !== '') {
                if ($type === 'string') {
                    // AHVNummer als String behandeln, um führende Nullen zu erhalten
                    $sheet->setCellValueExplicit($column . $rowNumber, $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($column . $rowNumber, $value);
                }
            }
        }

        $rowNumber++;
        $pos++;
    }

    // Optional: Auto-Größe der Spalten
    foreach(range('A','V') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // Erstellen des Writers und Ausgeben der Datei
    $filename = 'Jungschuetzen_Resultate_' . date('Y-m-d') . '.xlsx';

    // Setzen der Header, um die Datei als Download zu erzwingen
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: max-age=0');

    try {
        // Schreiben der Datei
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    } catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
        error_log("Fehler beim Schreiben der Excel-Datei: " . $e->getMessage());
        die("Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.");
    }

    // Beenden des Skripts
    exit;

} catch (Exception $e) {
    error_log("Fehler: " . $e->getMessage());
    die("Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.");
}
?>
