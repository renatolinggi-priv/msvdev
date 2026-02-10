<?php
// export_jmdefinition_pdf.php

require '../vendor/autoload.php'; // Pfad zu Composer's Autoload Datei
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

// Lade das Logo (optional)
$logoBase64 = imgToBase64('../dat/SKSG_Logo.jpg'); // Passe den Pfad zum Logo an

// Aktuelles Jahr (oder spezifisches Jahr aus GET-Parameter)
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// SQL-Abfrage vorbereiten, um JMDefinition-Daten zu laden
$sql = "SELECT Reihenfolge, Bezeichnung, Maxpunkte, Streicher, Erweitert FROM JMDefinition WHERE year = ? ORDER BY Reihenfolge";
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
    <title>JMDefinition PDF</title>
    <style>
        @page { margin: 20px; }
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .container { padding: 10px; }
        .header { display: flex; align-items: center; margin-bottom: 20px; }
        .header .logo { flex: 1; }
        .header .title { flex: 3; text-align: center; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border: 1px solid #000; padding: 8px; text-align: left; }
        .table th { background-color: #f2f2f2; }
        .footer { text-align: center; font-size: 8px; padding: 10px 0; border-top: 1px solid #000; position: fixed; bottom: -40px; left: 0; right: 0; height: 30px; }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <?php if ($logoBase64): ?>
                    <img src="<?php echo $logoBase64; ?>" alt="Logo" style="width:150px; height:auto;">
                <?php endif; ?>
            </div>
            <div class="title">
                <h2>Schwyzer Kantonal-Schützengesellschaft</h2>
                <h3>JMDefinition - <?php echo $currentYear; ?></h3>
            </div>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Reihenfolge</th>
                    <th>Bezeichnung</th>
                    <th>Maxpunkte</th>
                    <th>Streicher</th>
                    <th>Erweitert</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jmdefinitions as $jm): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($jm['Reihenfolge']); ?></td>
                        <td><?php echo htmlspecialchars($jm['Bezeichnung']); ?></td>
                        <td><?php echo htmlspecialchars($jm['Maxpunkte']); ?></td>
                        <td><?php echo $jm['Streicher'] ? 'Ja' : 'Nein'; ?></td>
                        <td><?php echo $jm['Erweitert'] ? 'Ja' : 'Nein'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="footer">
            <p>Erstellt am <?php echo date('d.m.Y'); ?></p>
        </div>
    </div>
</body>

</html>
<?php
$html = ob_get_clean();

// Dompdf initialisieren
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Externe Ressourcen erlauben

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait'); // Papierformat und Orientierung
$dompdf->render();

// PDF-Ausgabe speichern
$date = new DateTime();
$pdfFileName = "JMDefinition_{$currentYear}_" . $date->format('Y-m-d_H-i-s') . ".pdf";
$pdfFilePath = "dat/" . $pdfFileName;
file_put_contents($pdfFilePath, $dompdf->output());

// JSON-Antwort mit PDF-Link
echo json_encode([
    'success' => true,
    'pdf_link' => 'jmdefinition/' . $pdfFilePath
]);
?>
