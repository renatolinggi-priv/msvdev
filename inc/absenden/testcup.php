<?php
require '../phpword/vendor/autoload.php';
include '../config.php';

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\VerticalJc; // Für vertikale Ausrichtung
use PhpOffice\PhpWord\SimpleType\Jc; // Für horizontale Ausrichtung
use PhpOffice\PhpWord\SimpleType\JcTable; // Für Tabellen-Ausrichtung

// Lade die Vorlage
use PhpOffice\PhpWord\Style\Cell;
use PhpOffice\PhpWord\Style\Table;

$templateProcessor = new TemplateProcessor('dat/Testvorlage.docx');

// Stil für die Tabelle


// Stil für die Zellen
$cellStyle = [
    'valign' => VerticalJc::CENTER,
    'alignment' => Jc::CENTER,
    'borderSize' => 6,
    'borderColor' => '000000',
    'borderBottomSize' => 12,
    'borderBottomStyle' => 'double',
    'borderBottomColor' => '000000',
];



// Schriftstil für die Tabelleninhalte

// Funktion zum Erstellen der CUP-Tabelle
function createCupTable($phpWord, $round, $cupPairsResult)
{
    $tableStyle = [
        'alignment' => JcTable::CENTER,  // Tabelle zentriert ausrichten
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 150,
    ];

  
    // Schriftstil für die Tabelleninhalte
    $fontStyle = ['size' => 9];  // Schriftgröße 8
    $loserFontStyle = [  'size' => 9,
    'strikethrough' => true, // Text wird durchgestrichen
    ]; 
    // Normaler Stil für Gewinner
$winnerFontStyle = [
    'size' => 9
];
    $section = $phpWord->addSection();
    $cupTable = $section->addTable($tableStyle);

    // Tabelleninhalt
    $totalRows = count($cupPairsResult);
    foreach ($cupPairsResult as $index => $row) {
     
        //Verlierer Zweiergruppen
        $winner1 = $row['Result1'] > $row['Result2'] || ($row['Result1'] == $row['Result2'] && $row['LowShot1'] > $row['LowShot2']);
        $winner2 = !$winner1;

        $loser = getLoserInThreePair($row);
    
        // Normale Zeile für die Paarung
        if ($row['Round'] == $round) {
            if ($index + 1 == $totalRows) {
                if (!empty($row['Participant3'])) {

                        //Verlierer Dreiergruppen
                        $loser = getLoserInThreePair($row);

                        
                    $cellStyleName = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderRightSize' => 6,
                        'borderRightStyle' => 'dashed',
                        'borderRightColor' => '000000',
                        'spaceAfter' => 0,
                    ];

                    $cellStyleRes = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderleftSize' => 6,
                        'borderleftStyle' => 'dashed',
                        'borderleftColor' => '000000',
                        'spaceAfter' => 0,
                    ];
                    $cupTable->addRow();
                    $cupTable->addCell(2000, $cellStyleName)->addText(getParticipantName($row['Participant1']), $loser == 1 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText($row['Result1'],  $loser == 1 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(2000, $cellStyleName)->addText(getParticipantName($row['Participant2']), $loser == 2 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText($row['Result2'],  $loser == 2 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cellStyleName = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderRightSize' => 6,
                        'borderRightStyle' => 'dashed',
                        'borderRightColor' => '000000',
                        'borderBottomSize' => 6,
                        'borderBottomColor' => '000000',
                        'spaceAfter' => 0,
                    ];

                    $cellStyleRes = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderleftSize' => 6,
                        'borderleftStyle' => 'dashed',
                        'borderleftColor' => '000000',
                        'borderBottomSize' => 6,
                        'borderBottomColor' => '000000',
                        'spaceAfter' => 0,
                    ];
                    $cupTable->addRow();
                    $cupTable->addCell(2000, $cellStyleName)->addText(getParticipantName($row['Participant3']), $loser == 3 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText($row['Result3'],  $loser == 3 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(2000, $cellStyleName)->addText('a ', ['size' => 8, 'color' => 'ffffff']);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText('a ', ['size' => 8, 'color' => 'ffffff']);  // Schriftgröße 8
                } else {
                    $cellStyleName = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderRightSize' => 6,
                        'borderRightStyle' => 'dashed',
                        'borderRightColor' => '000000',
                        'borderBottomSize' => 6,
                        'borderBottomColor' => '000000',
                        'spaceAfter' => 0,
                    ];

                    $cellStyleRes = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderleftSize' => 6,
                        'borderleftStyle' => 'dashed',
                        'borderleftColor' => '000000',
                        'borderBottomSize' => 6,
                        'borderBottomColor' => '000000',
                        'spaceAfter' => 0,
                    ];
                    // Normale Zeile für die Paarung
                    $cupTable->addRow();
                    $cupTable->addCell(2000, $cellStyleName)->addText(getParticipantName($row['Participant1']), $winner1 ? $fontStyle : $loserFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText($row['Result1'], $winner1 ? $fontStyle : $loserFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(2000, $cellStyleName)->addText(getParticipantName($row['Participant2']), $winner2 ? $fontStyle : $loserFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText($row['Result2'], $winner2 ? $fontStyle : $loserFontStyle);  // Schriftgröße 8

                }
            } else {
                if (!empty($row['Participant3'])) {
                      //Verlierer Dreiergruppen
                      $loser = getLoserInThreePair($row);
                      echo $loser;
                    $cellStyleName = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderRightSize' => 6,
                        'borderRightStyle' => 'dashed',
                        'borderRightColor' => '000000',
                        'spaceAfter' => 0,
                    ];

                    $cellStyleRes = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderleftSize' => 6,
                        'borderleftStyle' => 'dashed',
                        'borderleftColor' => '000000',
                        'spaceAfter' => 0,
                    ];
                    // Normale Zeile für die Paarung
                    $cupTable->addRow();
                    $cupTable->addCell(2000, $cellStyleName)->addText(getParticipantName($row['Participant1']), $loser == 1 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText($row['Result1'], $loser == 1 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(2000, $cellStyleName)->addText(getParticipantName($row['Participant2']), $loser == 2 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText($row['Result2'],  $loser == 2 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cellStyleName = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderRightSize' => 6,
                        'borderRightStyle' => 'dashed',
                        'borderRightColor' => '000000',
                        'borderBottomSize' => 12,
                        'borderBottomStyle' => 'double',
                        'borderBottomColor' => '000000',
                        'spaceAfter' => 0,
                    ];

                    $cellStyleRes = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderleftSize' => 6,
                        'borderleftStyle' => 'dashed',
                        'borderleftColor' => '000000',
                        'borderBottomSize' => 12,
                        'borderBottomStyle' => 'double',
                        'borderBottomColor' => '000000',
                        'spaceAfter' => 0,
                    ];
                    $cupTable->addRow();
                    $cupTable->addCell(2000, $cellStyleName)->addText(getParticipantName($row['Participant3']), $loser == 3 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText($row['Result3'], $loser == 3 ? $loserFontStyle : $winnerFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(2000, $cellStyleName)->addText('a', ['size' => 8, 'color' => 'ffffff']);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText('a', ['size' => 8, 'color' => 'ffffff']);  // Schriftgröße 8
                } else {
                    $cellStyleName = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderRightSize' => 6,
                        'borderRightStyle' => 'dashed',
                        'borderRightColor' => '000000',
                        'borderBottomSize' => 12,
                        'borderBottomStyle' => 'double',
                        'borderBottomColor' => '000000',
                        'spaceAfter' => 0,
                    ];

                    $cellStyleRes = [
                        'borderSize' => 6,
                        'borderColor' => '000000',
                        'valign' => VerticalJc::CENTER,
                        'alignment' => Jc::CENTER,
                        'borderleftSize' => 6,
                        'borderleftStyle' => 'dashed',
                        'borderleftColor' => '000000',
                        'borderBottomSize' => 12,
                        'borderBottomStyle' => 'double',
                        'borderBottomColor' => '000000',
                        'spaceAfter' => 0,
                    ];
                    // Normale Zeile für die Paarung
                    $cupTable->addRow();
                    $cupTable->addCell(2000, $cellStyleName)->addText(getParticipantName($row['Participant1']), $winner1 ? $fontStyle : $loserFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText($row['Result1'], $winner1 ? $fontStyle : $loserFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(2000, $cellStyleName)->addText(getParticipantName($row['Participant2']), $winner2 ? $fontStyle : $loserFontStyle);  // Schriftgröße 8
                    $cupTable->addCell(500, $cellStyleRes)->addText($row['Result2'], $winner2 ? $fontStyle : $loserFontStyle);  // Schriftgröße 8

                }
            }
        }
    }

    return $cupTable;
}

// Funktion, um Teilnehmernamen anhand der ID abzurufen
function getParticipantName($id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT CONCAT(Name, ' ',Vorname) as FullName FROM mitglieder WHERE ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($fullName);
    $stmt->fetch();
    $stmt->close();
    return $fullName;
}
$tableStyle = [
    'alignment' => JcTable::CENTER,  // Tabelle zentriert ausrichten

    'cellMargin' => 20,
];
// PhpWord-Instanz erstellen
$phpWord = new PhpWord();
$phpWord->addTableStyle('myTable', $tableStyle);

// Abfrage für die Runden
$pairsQuery = "SELECT * FROM cupPairs WHERE Year = 2024 ORDER BY Round, ID";
$pairsResult = $conn->query($pairsQuery)->fetch_all(MYSQLI_ASSOC);

// Runde 1 Tabelle
$cupTable1 = createCupTable($phpWord, 1, $pairsResult);
$templateProcessor->setComplexBlock('CUPR1', $cupTable1);

// Runde 2 Tabelle
$cupTable2 = createCupTable($phpWord, 2, $pairsResult);
$templateProcessor->setComplexBlock('CUPR2', $cupTable2);

// Finalrunde vorbereiten und hinzufügen
$finalQuery = "SELECT * FROM cupFinalResults WHERE Year = 2024 ORDER BY Result DESC, LowShot DESC";
$finalResult = $conn->query($finalQuery);

$section = $phpWord->addSection();
$finalTable = $section->addTable($tableStyle);
$i = 1;
while ($row = $finalResult->fetch_assoc()) {
    $participant = getParticipantName($row['ParticipantID']);
    $finalTable->addRow();

    if ($i == 1) {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderLeftSize' => 6,
            'borderLeftColor' => '000000',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000'
        ];
    } else {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderTopStyle' => 'dashed',
            'borderLeftSize' => 6,
            'borderLeftColor' => '000000',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000'
        ];
    }

    $finalTable->addCell(500, $cellStyleSieger)->addText("$i.", ['size' => 8]);  // Schriftgröße 8
    if ($i == 1) {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000'
        ];
    } else {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderTopStyle' => 'dashed',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000'
        ];
    }
    $finalTable->addCell(2000, $cellStyleSieger)->addText($participant, ['size' => 8]);  // Schriftgröße 8
    if ($i == 1) {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000',
            'borderRightSize' => 6,
            'borderRightColor' => '000000',
        ];
    } else {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderTopStyle' => 'dashed',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000',
            'borderRightSize' => 6,
            'borderRightColor' => '000000',
        ];
    }
    $finalTable->addCell(1000, $cellStyleSieger)->addText($row['Result'], ['size' => 8]);  // Schriftgröße 8
    $i++;
}

$finalTable->addRow();
$finalTable->addCell(null, ['borderTopColor' => '000000', 'borderTopSize' => 6, 'gridSpan' => 3])->addText(" ", ['size' => 8]);  // Schriftgröße 8
$templateProcessor->setComplexBlock('CUPF', $finalTable);



// Standcup vorbereiten und hinzufügen
$finalStandQuery = "SELECT * FROM cupStandFinal WHERE Year = 2024 ORDER BY Result DESC";
$finalResult = $conn->query($finalStandQuery);

$section = $phpWord->addSection();
$standCupTable = $section->addTable($tableStyle);
$i = 1;
while ($row = $finalResult->fetch_assoc()) {
    $participant = $row['ParticipantName'];
    $standCupTable->addRow();

    if ($i == 1) {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderLeftSize' => 6,
            'borderLeftColor' => '000000',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000'
        ];
    } else {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderTopStyle' => 'dashed',
            'borderLeftSize' => 6,
            'borderLeftColor' => '000000',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000'
        ];
    }

    $standCupTable->addCell(500, $cellStyleSieger)->addText("$i.", ['size' => 8]);  // Schriftgröße 8
    if ($i == 1) {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000'
        ];
    } else {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderTopStyle' => 'dashed',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000'
        ];
    }
    $standCupTable->addCell(2000, $cellStyleSieger)->addText($participant, ['size' => 8]);  // Schriftgröße 8
    if ($i == 1) {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000',
            'borderRightSize' => 6,
            'borderRightColor' => '000000',
        ];
    } else {
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderTopStyle' => 'dashed',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000',
            'borderRightSize' => 6,
            'borderRightColor' => '000000',
        ];
    }
    $standCupTable->addCell(1000, $cellStyleSieger)->addText($row['Result'], ['size' => 8]);  // Schriftgröße 8
    $i++;
}

$standCupTable->addRow();
$standCupTable->addCell(null, ['borderTopColor' => '000000', 'borderTopSize' => 6, 'gridSpan' => 3])->addText(" ", ['size' => 8]);  // Schriftgröße 8
$templateProcessor->setComplexBlock('CUPS', $standCupTable);






// Formatierung des Dateinamens mit Datum
$date = new DateTime();
$filename = 'DynamischeTabelleMitCup' . $date->format('Y-m-d_H-i-s') . '.docx';

// Speichern des Dokuments
$templateProcessor->saveAs($filename);

echo "Dokument wurde erfolgreich erstellt: <a href='$filename'>$filename</a>";
function getLoserInThreePair($row) {
    // Erstelle ein Array der Teilnehmer
    $results = [
        ['Participant' => 1, 'Result' => $row['Result1'], 'LowShot' => $row['LowShot1']],
        ['Participant' => 2, 'Result' => $row['Result2'], 'LowShot' => $row['LowShot2']],
        ['Participant' => 3, 'Result' => $row['Result3'], 'LowShot' => $row['LowShot3']]
    ];

    // Finde den Verlierer (kleinste Punktzahl)
    $loserIndex = 0;
    for ($i = 1; $i < 3; $i++) {
        if ($results[$i]['Result'] < $results[$loserIndex]['Result']) {
            $loserIndex = $i;
        } elseif ($results[$i]['Result'] == $results[$loserIndex]['Result']) {
            // Wenn die Punktzahlen gleich sind, entscheidet der LowShot
            if ($results[$i]['LowShot'] < $results[$loserIndex]['LowShot']) {
                $loserIndex = $i;
            }
        }
    }

    // Rückgabe: 1, 2 oder 3, basierend auf welchem Teilnehmer der Verlierer ist
    return $results[$loserIndex]['Participant'];
}

?>