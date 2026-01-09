<?php
// export_jmdefinition_pdf.php

require '../dompdf/autoload.php'; // Pfad zu Composer's Autoload Datei
include '../config.php'; // Datenbankverbindung

use Dompdf\Dompdf;
use Dompdf\Options;

// Funktion: Konvertiert ein Bild in Base64
function imgToBase64($imgPath)
{
    if (!file_exists($imgPath)) {
        return '';
    }
    $imageData = base64_encode(file_get_contents($imgPath));
    return 'data:' . mime_content_type($imgPath) . ';base64,' . $imageData;
}

// Zusatztext aus der Datenbank abrufen
$sql = "SELECT text FROM JMInformation ORDER BY created_at DESC LIMIT 1";
$result = $conn->query($sql);
$zusatztext = $result->fetch_assoc()['text'] ?? '';

// Tage und Monate extrahieren
function extractDaysAndMonths($schiesstage)
{
    $lines = explode("\n", $schiesstage);
    $days = [];
    $months = [];
    $currentYear = date("Y");

    foreach ($lines as $line) {
        if (preg_match('/\b(\d{1,2})\.\s+(\w+)(?:\s+(\d{4}))?/u', $line, $matches)) {
            $day = $matches[1]; 
            $month = $matches[2]; 
            $year = isset($matches[3]) ? $matches[3] : $currentYear; // Falls kein Jahr angegeben ist, aktuelles Jahr verwenden

            // Falls das Jahr größer als das aktuelle Jahr ist, füge es hinzu
            if ($year > $currentYear) {
                $month .= " " . $year;
            }

            $days[] = $day;
            $months[] = $month;
        }
    }

    $uniqueDays = implode('. / ', array_unique($days)) . '.';
    $uniqueMonths = implode(' / ', array_unique($months));

    return [
        'days' => $uniqueDays,
        'months' => $uniqueMonths
    ];
}


// Lade das Logo (optional)
$logoBase64 = imgToBase64('../dat/SKSG_Logo.jpg'); // Passe den Pfad zum Logo an

// Aktuelles Jahr (oder spezifisches Jahr aus GET-Parameter)
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$isDraft = isset($_GET['draft']) && $_GET['draft'] == 1;

// SQL-Abfrage vorbereiten, um JMDefinition-Daten zu laden
$sql = "SELECT Reihenfolge, Bezeichnung, Schiesstage, Maxpunkte, Streicher, Erweitert, Info FROM JMDefinition WHERE year = ? ORDER BY Reihenfolge";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => "Fehler bei der Datenbankabfrage: " . $conn->error]);
    exit;
}
$stmt->bind_param("i", $currentYear);
$stmt->execute();
$result = $stmt->get_result();

// Ergebnisse abrufen
$jmdefinitions = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();

/**
 * Extrahiert das erste Jahr aus dem Schiesstage-Feld
 * Gibt das Jahr zurück oder null wenn keins gefunden
 */
function extractFirstYear($schiesstage, $defaultYear) {
    if (empty($schiesstage)) {
        return null;
    }
    // Suche nach 4-stelliger Jahreszahl
    if (preg_match('/(\d{4})/', $schiesstage, $matches)) {
        return intval($matches[1]);
    }
    return $defaultYear;
}

/**
 * Sortiert die JMDefinitions:
 * 1. Einträge mit Datum im aktuellen Jahr (nach Reihenfolge)
 * 2. Einträge ohne Datum (nach Reihenfolge)
 * 3. Einträge mit Datum im Folgejahr (wie GV fürs nächste Jahr)
 */
usort($jmdefinitions, function($a, $b) use ($currentYear) {
    $yearA = extractFirstYear($a['Schiesstage'], $currentYear);
    $yearB = extractFirstYear($b['Schiesstage'], $currentYear);
    
    // Folgejahr-Einträge ans Ende
    $aIsFuture = ($yearA !== null && $yearA > $currentYear) ? 1 : 0;
    $bIsFuture = ($yearB !== null && $yearB > $currentYear) ? 1 : 0;
    
    if ($aIsFuture !== $bIsFuture) {
        return $aIsFuture - $bIsFuture; // Folgejahr kommt nach
    }
    
    // Innerhalb der gleichen Kategorie: nach Reihenfolge
    return $a['Reihenfolge'] - $b['Reihenfolge'];
});

// Prüfen, ob Daten verfügbar sind
if (empty($jmdefinitions)) {
    echo json_encode(['success' => false, 'message' => "Keine Daten für das Jahr $currentYear gefunden."]);
    exit;
}



// HTML-Template für das PDF
ob_start();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Jahresprogramm <?php echo $currentYear; ?></title>
    <style>
        @page {
            margin: 20px;
        }

        body {
    font-family: Arial, sans-serif;
    font-size: 10px; /* Schriftgröße verkleinern */
    margin: 10px;
}

        .header {
            text-align: center;
            margin-bottom: 16px;
        }

        .header img {
            width: 150px;
        }

        .header h2 {
            margin: 10px 0;
            text-align: center;
            vertical-align: middle;
        }

        .table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
    font-size: 10px; /* Tabelle kleiner */
}
.table th,
.table td {
    border: 1px solid #000;
    padding: 5px; /* Weniger Platz zwischen Text */
    text-align: left;
}

        .table th {
            background-color: #f2f2f2;
        }

        .footer {
            text-align: center;
            font-size: 9px;
            padding: 10px 0;
            border-top: 1px solid #000;
            position: fixed;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 30px;
        }

        /* Wasserzeichen für Entwurf */
        .watermark {
            position: fixed;
            top: 35%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            font-weight: bold;
            color: rgba(200, 0, 0, 0.15);
            z-index: 1000;
            pointer-events: none;
            white-space: nowrap;
        }

        /* Zentrierung für die Spalten "JM A", "JM B" und "Gruppenschiessen" */
        .table th:nth-child(4),
        .table td:nth-child(4) {
            text-align: center;
            vertical-align: middle;
        }

        .table th:nth-child(5),
        /* Gruppenschiessen */
        .table td:nth-child(5) {
            text-align: center;
            /* Inhalt horizontal zentrieren */
            vertical-align: middle;
            /* Inhalt vertikal zentrieren */
        }
        @page {
    margin: 10px 20px; /* Oben und Unten auf 10px reduzieren, Links und Rechts auf 20px */
}
    </style>
</head>

<body>
    <?php if ($isDraft): ?>
    <div class="watermark">ENTWURF</div>
    <?php endif; ?>
    <table class="table">
        <thead>
            <tr>
                <th colspan="5" style="text-align: center;">
                    <h2 style="text-align: center; font-size: 18px; font-weight: bold;">Jahresprogramm <?php echo $currentYear; ?></h2>
                </th>

            </tr>
            <tr>
                <th colspan="2">Datum</th>
                <th>Schiessanlass</th>
                <th style="text-align: center">Jahresmeisterschaft</th>
                <th>Gruppen-<br>schiessen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jmdefinitions as $jm): ?>
                <?php
                // Verarbeitung der Tage und Monate
                $dateData = extractDaysAndMonths($jm['Schiesstage']);
                // Bezeichnung anpassen: "Endstich" -> "MSV Wilen Endschiessen"
                $bezeichnung = $jm['Bezeichnung'];
                if (trim($bezeichnung) === 'Endstich') {
                    $bezeichnung = 'MSV Wilen Endschiessen';
                }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($dateData['days']); ?></td>
                    <td><?php echo htmlspecialchars($dateData['months']); ?></td>
                    <td><?php echo htmlspecialchars($bezeichnung); ?></td>
                    <td align="center">
                        <?php
                        $isStreicher = isset($jm['Streicher']) ? $jm['Streicher'] : 0;
                        $isErweitert = isset($jm['Erweitert']) ? $jm['Erweitert'] : 0;
                        $isInfo = isset($jm['Info']) ? $jm['Info'] : 0; // Sicherstellen, dass Info existiert

                        // **DEBUG: Werte im PDF ausgeben**
                        // echo "<span style='font-size:10px;'>S:$isStreicher E:$isErweitert I:$isInfo</span><br>";

                        if ($jm['Maxpunkte'] == 20) {
                            echo 'Bonus';
                        } elseif ($isInfo == 1) {
                            echo ''; // Falls Info = 1, KEIN X setzen
                        } elseif ($isStreicher == 1) {
                            echo 'X'; // Falls Streicher = 1, dann X setzen
                        } elseif ($isErweitert == 0 && $isInfo == 0) {
                            echo 'X'; // Falls Erweitert = 0 UND Info = 0 → X setzen
                        } else {
                            echo ''; // Falls keine Bedingung erfüllt ist, bleibt das Feld leer
                        }
                        ?>
                    </td>





                    <td align="center">
                        <?php echo $jm['Erweitert'] ? 'X' : ''; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!-- Zusatztext für das PDF -->
    <?php if (!empty($zusatztext)): ?>
        <div class="notes">
            <p><?php echo nl2br(htmlspecialchars($zusatztext)); ?></p>
        </div>
    <?php endif; ?>

    <div class="footer">
        <p>Erstellt am <?php echo date('d.m.Y'); ?></p>
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
$dompdf->set_option('debugFont', true);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// PDF-Ausgabe speichern
$date = new DateTime();
$draftSuffix = $isDraft ? '_ENTWURF' : '';
$pdfFileName = "Jahresprogramm_{$currentYear}{$draftSuffix}_" . $date->format('Y-m-d_H-i-s') . ".pdf";
$pdfFilePath = "dat/" . $pdfFileName;
file_put_contents($pdfFilePath, $dompdf->output());

// JSON-Antwort mit PDF-Link
echo json_encode([
    'success' => true,
    'pdf_link' => 'jmdefinition/' . $pdfFilePath
]);
?>