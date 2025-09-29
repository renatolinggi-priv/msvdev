<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Datenbankverbindung herstellen
$host = 'bdebbd4.mysql.db.internal';
$db = 'bdebbd4_msvjm';
$user = 'bdebbd4_msvjm';
$pass = 'xx*97ubWcy+HnLWyf6PW';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// SQL-Abfrage ausführen
$sql = "
SELECT
  m.ID,
  m.Name,
  m.Vorname,
  w.Bezeichnung,
  COALESCE(SUM(h.Passe1 + h.Passe2 + h.Passe3 + h.Passe4 + h.Passe5 + h.Passe6 + h.Passe7 + h.Passe8), 0) AS HeimSum
FROM
  mitglieder m
LEFT JOIN heimresultate h ON m.ID = h.MitgliedID
LEFT JOIN Waffen w ON m.WaffenID = w.ID

WHERE w.Kategorie like 'Kat. A'
GROUP BY
  m.ID, m.Vorname, m.Name order by HeimSum DESC";
  $stmt = $pdo->query($sql);
  $rows = $stmt->fetchAll();
  
  // Neues Word-Dokument erstellen
  $phpWord = new PhpWord();
  $section = $phpWord->addSection();
  
  // Tabellenstil definieren
  $tableStyle = [
      'borderSize' => 2,
      'borderColor' => '999999',
      'cellMargin' => 20,
      'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
  ];
  $firstRowStyle = ['bgColor' => '66BBFF'];
  $cellStyle = ['valign' => 'center'];
  $fontStyle = ['bold' => true, 'align' => 'center'];
  
  // Tabellenüberschrift hinzufügen
  $table = $section->addTable($tableStyle);
  $table->addRow();
  $table->addCell(2000, $cellStyle)->addText('ID', $fontStyle);
  $table->addCell(2000, $cellStyle)->addText('Name', $fontStyle);
  $table->addCell(2000, $cellStyle)->addText('Vorname', $fontStyle);
  $table->addCell(2000, $cellStyle)->addText('HeimSum', $fontStyle);
  
  // Daten in die Tabelle einfügen
  foreach ($rows as $row) {
      $table->addRow();
      $table->addCell(2000, $cellStyle)->addText($row['ID']);
      $table->addCell(2000, $cellStyle)->addText($row['Name']);
      $table->addCell(2000, $cellStyle)->addText($row['Vorname']);
      $table->addCell(2000, $cellStyle)->addText($row['HeimSum']);
  }
  // Tabellenstil definieren
$tableStyle = [
    'borderSize' => 6,
    'borderColor' => '999999',
    'cellMargin' => 50,
    'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER,
];
$firstRowStyle = ['bgColor' => '66BBFF'];
$cellStyle = ['valign' => 'center'];
$fontStyle = ['bold' => true, 'align' => 'center', 'color' => 'FFFFFF'];
$headerFontStyle = ['bold' => true, 'size' => 14, 'name' => 'Arial'];
$dataFontStyle = ['size' => 12, 'name' => 'Arial'];

// Tabellenüberschrift hinzufügen
$table = $section->addTable($tableStyle);
$table->addRow();
$table->addCell(2000, ['bgColor' => '333333', 'valign' => 'center'])->addText('ID', $headerFontStyle, $fontStyle);
$table->addCell(2000, ['bgColor' => '333333', 'valign' => 'center'])->addText('Name', $headerFontStyle, $fontStyle);
$table->addCell(2000, ['bgColor' => '333333', 'valign' => 'center'])->addText('Vorname', $headerFontStyle, $fontStyle);
$table->addCell(2000, ['bgColor' => '333333', 'valign' => 'center'])->addText('HeimSum', $headerFontStyle, $fontStyle);

// Beispiel-Datenzeilen
$rows = [
    ['ID' => 1, 'Name' => 'Muster', 'Vorname' => 'Max', 'HeimSum' => 100],
    ['ID' => 2, 'Name' => 'Beispiel', 'Vorname' => 'Erika', 'HeimSum' => 200],
];

// Daten in die Tabelle einfügen
foreach ($rows as $row) {
    $table->addRow();
    $table->addCell(2000, $cellStyle)->addText($row['ID'], $dataFontStyle);
    $table->addCell(2000, $cellStyle)->addText($row['Name'], $dataFontStyle);
    $table->addCell(2000, $cellStyle)->addText($row['Vorname'], $dataFontStyle);
    $table->addCell(2000, $cellStyle)->addText($row['HeimSum'], $dataFontStyle);
}
$timestamp = time();
$time= date("H.i", $timestamp);
  // Dokument speichern
  $outputFile = "dynamische_tabelle" .$time .".docx";
  $writer = IOFactory::createWriter($phpWord, 'Word2007');
  $writer->save($outputFile);
  
  echo "Dokument wurde erstellt: $outputFile\n";
  ?>