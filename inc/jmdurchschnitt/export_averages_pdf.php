<?php
// export_averages_pdf.php
session_start();
include '../config.php';
require_once '../vendor/autoload.php';
require_once __DIR__ . '/config_helper.php';
require_once __DIR__ . '/../pdf/pdf_theme.php';
require_once __DIR__ . '/../csrf.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Überprüfen der Datenbankverbindung
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// CSRF Token prüfen
csrf_require(true);

// Parameter aus POST
$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
$definitionId = isset($_POST['definition_id']) ? intval($_POST['definition_id']) : 0;

if (empty($definitionId)) {
    echo json_encode(['success' => false, 'message' => 'Kein Anlass ausgewählt']);
    exit;
}

try {
    // Definition-Details laden
    $defSql = "SELECT ID, Bezeichnung, Maxpunkte, Zuschlag FROM JMDefinition WHERE ID = ? AND year = ?";
    $defStmt = $conn->prepare($defSql);
    $defStmt->bind_param("ii", $definitionId, $year);
    $defStmt->execute();
    $defResult = $defStmt->get_result();
    
    if ($defRow = $defResult->fetch_assoc()) {
        $anlassName = $defRow['Bezeichnung'];
        $zuschlag = $defRow['Zuschlag'] ?? 0;
        
        // Alle Resultate für diese Definition laden
        $resultSql = "SELECT jr.Punkte, m.Name, m.Vorname, m.ID as MitgliedID
                      FROM jmresultate jr
                      JOIN mitglieder m ON jr.mitgliederID = m.ID
                      WHERE jr.jmdefinitionID = ?
                      AND jr.Punkte > 0
                      AND m.status = 1
                      ORDER BY jr.Punkte DESC";
        
        $resultStmt = $conn->prepare($resultSql);
        $resultStmt->bind_param("i", $definitionId);
        $resultStmt->execute();
        $resultData = $resultStmt->get_result();
        
        $teilnehmerResultate = [];
        while ($row = $resultData->fetch_assoc()) {
            $teilnehmerResultate[] = [
                'mitglied_id' => $row['MitgliedID'],
                'name' => $row['Name'] . ' ' . $row['Vorname'],
                'punkte' => floatval($row['Punkte'])
            ];
        }
        
        $teilnehmerAnzahl = count($teilnehmerResultate);
        
        if ($teilnehmerAnzahl > 0) {
            $config = getDurchschnittConfig($conn, $year);
            $verwendeteResultate = calculateUsedResults($teilnehmerAnzahl, $config['anzahl_zaehlende']);
            
            // Die besten X Resultate nehmen (zählende)
            $zaehlendeResultate = array_slice($teilnehmerResultate, 0, $verwendeteResultate);
            $nichtZaehlendeResultate = array_slice($teilnehmerResultate, $verwendeteResultate);
            
            // Summen berechnen
            $summeZaehlende = array_sum(array_column($zaehlendeResultate, 'punkte'));
            $summeNichtZaehlende = array_sum(array_column($nichtZaehlendeResultate, 'punkte'));
            
            // Neue Zuschlagsberechnung: (Summe_zählende + (Zuschlag * Summe_nicht_zählende) / 100) / Anzahl_zählende
            $zuschlagsBonus = ($zuschlag * $summeNichtZaehlende) / 100;
            $endergebnis = round(($summeZaehlende + $zuschlagsBonus) / $verwendeteResultate, 3);
            
            // Klassischer Durchschnitt für Anzeige
            $durchschnitt = round($summeZaehlende / $verwendeteResultate, 2);
            
            $result = [
                'anlass_name' => $anlassName,
                'teilnehmer_anzahl' => $teilnehmerAnzahl,
                'verwendete_resultate' => $verwendeteResultate,
                'durchschnitt' => $durchschnitt,
                'zuschlag' => $zuschlag,
                'endergebnis' => $endergebnis,
                'alle_resultate' => $teilnehmerResultate
            ];
            
            // HTML für PDF generieren
            $html = generatePdfHtml($result, $year);
        } else {
            echo json_encode(['success' => false, 'message' => 'Keine Resultate für diesen Anlass gefunden']);
            exit;
        }
        
        $resultStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Anlass nicht gefunden']);
        exit;
    }
    
    $defStmt->close();
    
    // PDF-Optionen konfigurieren
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    
    // DOMPDF initialisieren
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Dateiname generieren
    $filename = "JM_Vereinsabrechnung_" . preg_replace('/[^a-zA-Z0-9]/', '_', $result['anlass_name']) . "_" . $year . ".pdf";
    $filepath = 'dat/' . $filename;

    // dat-Verzeichnis erstellen falls nicht vorhanden
    if (!file_exists('dat/')) {
        if (!mkdir('dat/', 0777, true)) {
            echo json_encode(['success' => false, 'message' => 'dat-Verzeichnis konnte nicht erstellt werden']);
            exit;
        }
    }
    
    // PDF speichern
    $pdfOutput = $dompdf->output();
    if (file_put_contents($filepath, $pdfOutput) === false) {
        echo json_encode(['success' => false, 'message' => 'PDF konnte nicht gespeichert werden']);
        exit;
    }
    
    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true,
        'pdf_url' => 'jmdurchschnitt/dat/' . $filename,
        'filename' => $filename,
        'message' => 'PDF erfolgreich generiert'
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in export_averages_pdf.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim PDF-Export: ' . $e->getMessage()
    ]);
} finally {
    $conn->close();
}

/**
 * Generiert das HTML für das PDF im Stil der Vereinsabrechnung
 */
function generatePdfHtml($result, $year) {
    $currentDate = date('d.m.Y \u\m H:i');

    // Pflichtteilnehmer und Nicht-Pflichtteilnehmer trennen
    $pflichtteilnehmer = array_slice($result['alle_resultate'], 0, $result['verwendete_resultate']);
    $nichtPflichtteilnehmer = array_slice($result['alle_resultate'], $result['verwendete_resultate']);

    // Summen weiterhin für die Berechnungsformel benötigt
    $summePflicht = array_sum(array_column($pflichtteilnehmer, 'punkte'));
    $summeNichtPflicht = array_sum(array_column($nichtPflichtteilnehmer, 'punkte'));

    $html = '<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>' . htmlspecialchars($result['anlass_name']) . ' - Vereinsabrechnung</title>
<style>
@page {
    margin: 1.5cm 1.5cm 2cm 1.5cm;
    size: A4;
}
body {
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    font-size: 10px;
    margin: 0;
    padding: 0;
    color: #333;
}

/* Header */
.header {
    position: relative;
    margin-bottom: 120px;
    padding-bottom: 10px;
}
.logo {
    position: absolute;
    top: 0;
    left: 0;
    width: 80px;
    height: auto;
}
h1 {
    text-align: center;
    font-size: 24px;
    margin: 10px 0 0 0;
    color: #2d3748;
    font-weight: bold;
}
.subtitle {
    text-align: center;
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}

/* Sections */
.section {
    margin-bottom: 15px;
    page-break-inside: avoid;
}
h2 {
    font-size: 12px;
    margin: 10px 0 5px 0;
    color: #3b5998;
    font-weight: bold;
}

/* Info-Box Wettkampf */
.info-box {
    margin-top: 8px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: #f8f9fa;
    padding: 8px 12px;
}
.info-box .info-row {
    display: table;
    width: 100%;
    padding: 2px 0;
}
.info-box .info-label {
    display: table-cell;
    color: #495057;
}
.info-box .info-value {
    display: table-cell;
    text-align: right;
    font-weight: bold;
    color: #3b5998;
}

/* Tabellen */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
thead th {
    background: #f8f9fa;
    padding: 6px 8px;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
    font-weight: bold;
    font-size: 10px;
    color: #495057;
}
tbody td {
    padding: 5px 8px;
    border-bottom: 1px solid #e9ecef;
}

/* Spalten */
.rank {
    width: 40px;
    text-align: center;
    font-weight: bold;
    font-size: 11px;
}
.name {
    width: auto;
    font-size: 10px;
}
.points {
    width: 60px;
    text-align: center;
    font-weight: bold;
    font-size: 11px;
    color: #3b5998;
}

/* Medaillen-Farben (ruhige Pastelltöne, einheitlich mit pdf_theme.php) */
.gold { background: #fdf6e3 !important; }
.gold .rank { color: #8a6d1c; }
.silver { background: #f1f1f1 !important; }
.silver .rank { color: #6b7280; }
.bronze { background: #f7ede2 !important; }
.bronze .rank { color: #9c6b3f; }

/* Berechnung */
.calculation {
    margin: 18px 0;
}
.calculation-line {
    margin: 5px 0;
    padding: 7px 10px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: #f8f9fa;
    font-size: 10px;
}
.calculation-line.title {
    font-weight: bold;
    color: #3b5998;
    background: #f1f5f9;
}
.final-result {
    margin-top: 8px;
    font-weight: bold;
    font-size: 14px;
    color: #fff;
    background: #3b5998;
    padding: 10px 12px;
    border-radius: 4px;
    text-align: center;
}

/* Footer */
.footer {
    position: fixed;
    bottom: 10px;
    left: 1.5cm;
    right: 1.5cm;
    text-align: center;
    font-size: 8px;
    color: #6c757d;
    padding-top: 10px;
    border-top: 1px solid #dee2e6;
    background: white;
}
</style>
</head>
<body>

<div class="header">
    <img src="' . pdf_logo_src() . '" class="logo" alt="MSV Wilen Logo">
    <h1>' . htmlspecialchars($result['anlass_name']) . ' ' . $year . '</h1>
    <div class="subtitle">Vereinsabrechnung &middot; Militärschützenverein Wilen</div>
</div>

<div class="section">
    <h2>Vereinswettkampf</h2>
    <div class="info-box">
        <div class="info-row">
            <span class="info-label">Vereinskategorie</span>
            <span class="info-value">4</span>
        </div>
        <div class="info-row">
            <span class="info-label">Vereinsschützen / Pflichtteilnehmer</span>
            <span class="info-value">' . $result['teilnehmer_anzahl'] . ' / ' . $result['verwendete_resultate'] . '</span>
        </div>
    </div>
</div>';

    // Pflichtteilnehmer Tabelle
    if (!empty($pflichtteilnehmer)) {
        $html .= '
<div class="section">
    <h2>Pflichtteilnehmer</h2>
    <table>
        <thead>
            <tr>
                <th class="rank">Rang</th>
                <th class="name">Name</th>
                <th class="points">Punkte</th>
            </tr>
        </thead>
        <tbody>';

        $rang = 0;
        foreach ($pflichtteilnehmer as $teilnehmer) {
            $rang++;
            $rowClass = '';
            if ($rang == 1) $rowClass = 'gold';
            elseif ($rang == 2) $rowClass = 'silver';
            elseif ($rang == 3) $rowClass = 'bronze';

            $html .= '
            <tr class="' . $rowClass . '">
                <td class="rank">' . $rang . '.</td>
                <td class="name">' . htmlspecialchars($teilnehmer['name']) . '</td>
                <td class="points">' . number_format($teilnehmer['punkte'], 0) . '</td>
            </tr>';
        }

        $html .= '
        </tbody>
    </table>
</div>';
    }

    // Nicht-Pflichtteilnehmer Tabelle
    if (!empty($nichtPflichtteilnehmer)) {
        $html .= '
<div class="section">
    <h2>Nicht-Pflichtteilnehmer</h2>
    <table>
        <thead>
            <tr>
                <th class="rank">Rang</th>
                <th class="name">Name</th>
                <th class="points">Punkte</th>
            </tr>
        </thead>
        <tbody>';

        $rang = count($pflichtteilnehmer);
        foreach ($nichtPflichtteilnehmer as $teilnehmer) {
            $rang++;
            $html .= '
            <tr>
                <td class="rank">' . $rang . '.</td>
                <td class="name">' . htmlspecialchars($teilnehmer['name']) . '</td>
                <td class="points">' . number_format($teilnehmer['punkte'], 0) . '</td>
            </tr>';
        }

        $html .= '
        </tbody>
    </table>
</div>';
    }

    // Berechnung - Formel
    $zuschlagsProzent = str_replace('%', '', $result['zuschlag']); // % entfernen für Berechnung

    $html .= '
<div class="section calculation">
    <h2>Berechnung</h2>
    <div class="calculation-line title">
        Endergebnis = (Summe Pflichtteilnehmer + Beteiligungszuschlag × Summe Nicht-Pflichtteilnehmer ÷ 100) ÷ Anzahl Pflichtteilnehmer
    </div>
    <div class="calculation-line">
        Endergebnis = (' . number_format($summePflicht, 0) . ' + ' . $zuschlagsProzent . '% × ' . number_format($summeNichtPflicht, 0) . ' ÷ 100) ÷ ' . $result['verwendete_resultate'] . '
    </div>
    <div class="final-result">
        Endergebnis: ' . number_format($result['endergebnis'], 3) . ' Punkte
    </div>
</div>

<div class="footer">
    MSV Wilen - Generiert am ' . $currentDate . ' Uhr
</div>
</body>
</html>';

    return $html;
}
?>