<?php
require '../spreadsheet/autoload.php'; // PHPSpreadsheet Autoloader
include '../config.php';

// SQL-Abfrage für die gewünschten Daten
$sql = "SELECT 
    m.Name, 
    m.Vorname, 
    YEAR(m.Geburtsdatum) AS Geburtsjahr, 
    w.Bezeichnung, 
    kr.Passe1, 
    kr.Passe2, 
    kr.Passe3, 
    kr.Passe4, 
    kr.Passe5
FROM mitglieder m
LEFT JOIN Waffen w ON m.WaffenID = w.ID
LEFT JOIN kantiresultate kr ON m.ID = kr.MitgliedID 
WHERE kr.Passe1 IS NOT NULL
ORDER BY m.Name ASC, m.Vorname ASC;
";

$result = $conn->query($sql);

// Überprüfen, ob Ergebnisse vorhanden sind
if ($result->num_rows > 0) {
    // PHPSpreadsheet-Objekt initialisieren
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Header für die Spalten hinzufügen
    $sheet->setCellValue('A1', 'Name')
          ->setCellValue('B1', 'Vorname')
          ->setCellValue('C1', 'Jahrgang')
          ->setCellValue('D1', '') // Leere Spalte
          ->setCellValue('E1', 'Sportgerät')
          ->setCellValue('F1', 'HD')
          ->setCellValue('G1', '1. ND')
          ->setCellValue('H1', '2. ND')
          ->setCellValue('I1', '3. ND')
          ->setCellValue('J1', '4. ND');

    // Datenzeilen hinzufügen
    $row = 2; // Beginne ab der 2. Zeile (nach dem Header)
    while ($data = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $data['Name'])
              ->setCellValue('B' . $row, $data['Vorname'])
              ->setCellValue('C' . $row, $data['Geburtsjahr'])
              ->setCellValue('E' . $row, $data['Bezeichnung']) // Leere Spalte ist D
              ->setCellValue('F' . $row, $data['Passe1'])
              ->setCellValue('G' . $row, $data['Passe2'])
              ->setCellValue('H' . $row, $data['Passe3'])
              ->setCellValue('I' . $row, $data['Passe4'])
              ->setCellValue('J' . $row, $data['Passe5']);
        $row++;
    }

    // Dateiname und Pfad der exportierten Excel-Datei
    $xlsFileName = 'mitglieder_export_' . date('Ymd_His') . '.xlsx';
    $xlsFilePath = 'dat/' . $xlsFileName;

    // Excel-Datei speichern
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($xlsFilePath);

    // JSON-Antwort mit dem Pfad zur Excel-Datei
    echo json_encode(array('xls_link' => $xlsFilePath));
} else {
    // Wenn keine Daten vorhanden sind
    echo json_encode(array('error' => 'Keine Daten gefunden.'));
}

// Verbindung zur Datenbank schließen
$conn->close();
?>
