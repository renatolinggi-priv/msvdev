<?php
require '../dompdf/autoload.php'; // Pfad zu Composer's autoload Datei
require '../config.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// SQL-Abfrage
$sql = "
    SELECT
        SUM(CASE WHEN Passe1 IS NOT NULL AND Passe1 != 0 THEN 1 ELSE 0 END) AS Passe1_count,
        SUM(CASE WHEN Passe2 IS NOT NULL AND Passe2 != 0 THEN 1 ELSE 0 END) AS Passe2_count,
        SUM(CASE WHEN Passe3 IS NOT NULL AND Passe3 != 0 THEN 1 ELSE 0 END) AS Passe3_count,
        SUM(CASE WHEN Passe4 IS NOT NULL AND Passe4 != 0 THEN 1 ELSE 0 END) AS Passe4_count,
        SUM(CASE WHEN Passe5 IS NOT NULL AND Passe5 != 0 THEN 1 ELSE 0 END) AS Passe5_count
    FROM kantiresultate
    WHERE Jahr = YEAR(CURDATE())
";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $HauptDoppel = $row['Passe1_count'];
    $Doppel = $row['Passe2_count'] + $row['Passe3_count'] + $row['Passe4_count'] + $row['Passe5_count'];
} else {
    echo "Keine Daten gefunden.";
    exit;
}

$sqlGetSchuetzen = "
    SELECT * FROM `kantiresultate` k
    JOIN mitglieder m ON m.id = k.MitgliedID
    JOIN Waffen w ON w.id = m.WaffenID
    ORDER BY Name
";

$schuetzen = $conn->query($sqlGetSchuetzen);

function GetKat($jahrgang){
    $aktuellesJahr = date('Y');
    $alter = $aktuellesJahr - $jahrgang;

    if ($alter <= 15) {
        return 'SV';
    } elseif ($alter >= 16 && $alter <= 21) {
        return 'JV';
    } elseif ($alter >= 22 && $alter <= 59) {
        return 'E';
    } elseif ($alter >= 60 && $alter <= 69) {
        return 'JV';
    } elseif ($alter >= 70) {
        return 'SV';
    } else {
        return 'Unbekannt'; // Falls das Alter negativ oder nicht plausibel ist
    }
}

// Funktion zum Konvertieren eines Bildes in Base64
function imgToBase64($imgPath) {
    $imageData = base64_encode(file_get_contents($imgPath));
    $src = 'data:' . mime_content_type($imgPath) . ';base64,' . $imageData;
    return $src;
}
$logoBase64 = imgToBase64('dat/SKSG_Logo.jpg');

// HTML-Template
ob_start();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @page {
            margin: 20px;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .container {
            padding: 10px;
        }
        h2, h3, h4, h5 {
            text-align: center;
            margin: 10px 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            border: 1px solid black;
            padding: 8px;
        }
        .left-align {
            text-align: left;
        }
        .center-align {
            text-align: center;
        }
        .total-spacing {
            margin-top: 30px;
        }
        .footer {
            text-align: center;
            font-size: 10px;
            padding: 10px 0;
            border-top: 1px solid #000;
            position: fixed;
            bottom: -40px;
            left: 0;
            right: 0;
            height: 30px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="<?php echo $logoBase64; ?>" alt="Logo" style="width:150px; height:auto;">
        </div>
        <div class="header">
            <h3>Schwyzer Kantonal-Schützengesellschaft</h3>
            <p>Renate Peters<br>
            Büelhof 5<br>
            8852 Altendorf</p>
            <h4>Kantonaler Spezialstich 300m<br>MSV Wilen-Wollerau<br>Abrechnung</h4>
        </div>
        <table class="table">
            <tr>
                <td><?php echo $HauptDoppel; ?> Hauptdoppel à Fr. 13.00</td>
                <td><?php echo number_format($HauptDoppel * 13, 2, '.', '') . " Fr."; ?></td>
            </tr>
            <tr>
                <td><?php echo $Doppel; ?> Nachdoppel à Fr. 3.00</td>
                <td><?php echo number_format($Doppel * 3, 2, '.', '') . " Fr."; ?></td>
            </tr>
            <tr>
                <td class="total-spacing"><strong>Totalbetrag</strong></td>
                <td class="total-spacing"><strong><?php echo number_format(($HauptDoppel * 13) + ($Doppel * 3), 2, '.', '') . " Fr."; ?></strong></td>
            </tr>
        </table>
        <p>Die Richtigkeit der Abrechnung bescheinigt:</p>
        <p>Stempel: _______________ Unterschrift: _______________</p>
        <div class="page-break"></div>
        <div class="header">
            <h4>Schwyzer Kantonal-Schützengesellschaft</h4>
            <h5>KONTROLLBLATT KANTONALER SPEZIALSTICH</h5>
            <h6>Name der Sektion: MSV Wilen-Wollerau</h6>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th class="left-align" scope="col">Nr.</th>
                    <th class="left-align" scope="col">Name</th>
                    <th class="left-align" scope="col">Vorname</th>
                    <th class="left-align" scope="col">Jahrgang</th>
                    <th class="left-align" scope="col">Waffe</th>
                    <th class="center-align" scope="col">HD</th>
                    <th class="center-align" scope="col">1.ND</th>
                    <th class="center-align" scope="col">2.ND</th>
                    <th class="center-align" scope="col">3.ND</th>
                    <th class="center-align" scope="col">4.ND</th>
                    <th class="center-align" scope="col">EKK</th>
                    <th class="center-align" scope="col">SKK</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; ?>
                <?php while($schuetze = $schuetzen->fetch_assoc()): ?>
                <tr>
                    <td class="left-align"><?php echo $i; ?></td>
                    <td class="left-align"><?php echo $schuetze['Name']; ?></td>
                    <td class="left-align"><?php echo $schuetze['Vorname']; ?></td>
                    <td class="left-align"><?php echo substr($schuetze['Geburtsdatum'], 0, 4); ?></td>
                    <td class="left-align"><?php echo $schuetze['Bezeichnung']; ?></td>
                    <td class="center-align"><?php echo $schuetze['Passe1']; ?></td>
                    <td class="center-align"><?php echo $schuetze['Passe2']; ?></td>
                    <td class="center-align"><?php echo $schuetze['Passe3']; ?></td>
                    <td class="center-align"><?php echo $schuetze['Passe4']; ?></td>
                    <td class="center-align"><?php echo $schuetze['Passe5']; ?></td>
                    <?php
                        $Alterskategorie = GetKat(substr($schuetze['Geburtsdatum'], 0, 4));
                        $SQLKranz = "SELECT 
                                        COUNT(CASE WHEN kr.Passe1 >= kd.Limite THEN 1 ELSE NULL END) AS Passe1_count,
                                        COUNT(CASE WHEN kr.Passe2 >= kd.Limite THEN 1 ELSE NULL END) AS Passe2_count,
                                        COUNT(CASE WHEN kr.Passe3 >= kd.Limite THEN 1 ELSE NULL END) AS Passe3_count,
                                        COUNT(CASE WHEN kr.Passe4 >= kd.Limite THEN 1 ELSE NULL END) AS Passe4_count,
                                        COUNT(CASE WHEN kr.Passe5 >= kd.Limite THEN 1 ELSE NULL END) AS Passe5_count
                                    FROM 
                                        mitglieder m
                                    JOIN 
                                        kantiresultate kr ON m.ID = kr.MitgliedID
                                    JOIN 
                                        Waffen w ON m.WaffenID = w.ID
                                    JOIN 
                                        kantidefinition kd ON w.ID = kd.WaffenID AND kd.Alterskategorie = '$Alterskategorie'
                                    WHERE 
                                        kr.Jahr = YEAR(CURDATE()) AND m.ID = {$schuetze['MitgliedID']}
                                    GROUP BY 
                                        m.ID";
                        $kranz = $conn->query($SQLKranz);  
                        $KranzCount = 0;  
                        if ($kranz->num_rows > 0) {
                            $row = $kranz->fetch_assoc();
                            $KranzCount += $row['Passe1_count'];
                            $KranzCount += $row['Passe2_count'];
                            $KranzCount += $row['Passe3_count'];
                            $KranzCount += $row['Passe4_count'];
                            $KranzCount += $row['Passe5_count'];
                        }           
                    ?>
                    <td class="center-align"><?php echo ($KranzCount >= 3) ? "X" : ""; ?></td>
                    <td class="center-align"><?php echo ($KranzCount > 0 && $KranzCount <= 2) ? "X" : ""; ?></td>
                </tr>
                <?php $i++; ?>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Dompdf initialisieren
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// HTML in PDF umwandeln
$dompdf->loadHtml($html);

// Querformat einstellen
$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

// Fußzeile auf jeder Seite hinzufügen
$canvas = $dompdf->getCanvas();
$canvas->page_text(270, 800, "© " . date('Y') . " Schwyzer Kantonal-Schützengesellschaft", null, 10, array(0,0,0));

// PDF-Datei speichern
$pdfOutput = $dompdf->output();
$date = new DateTime();
$pdfFilePath = 'dat/MSVWilenKantiabrechnung_' . $date->format('Y-m-d_H-i-s') . '.pdf';

file_put_contents($pdfFilePath, $pdfOutput);

// Leeren des Ausgabepuffers und Beenden der Ausgabepufferung
ob_end_clean();

// Download-Link anzeigen
echo json_encode(array('pdf_link' => $pdfFilePath));

$conn->close();

?>
