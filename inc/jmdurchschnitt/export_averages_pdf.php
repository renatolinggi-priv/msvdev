<?php
// export_averages_pdf.php
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand), startet die Session
require_once '../vendor/autoload.php';
require_once __DIR__ . '/config_helper.php';
require_once __DIR__ . '/../pdf/pdf_theme.php';
require_once __DIR__ . '/../pdf/pdf_orientation.inc.php'; // orientation aus dem Druckprofil (setPaper + @page)
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
    $dompdf->setPaper('A4', pdfOrientationParam('portrait')); // Ausrichtung aus dem Druckprofil, Default hoch (auch in @page)
    $dompdf->render();
    pdf_seitenfuss($dompdf, $result['anlass_name'] . ' ' . $year . ' Vereinsabrechnung');
    
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
        'message' => msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e)
    ]);
} finally {
    $conn->close();
}

/**
 * Generiert das HTML für das PDF (Vereinsabrechnung) im Ranglisten-Layout (pdf_rangliste_anfang)
 */
function generatePdfHtml($result, $year) {
    // Pflichtteilnehmer und Nicht-Pflichtteilnehmer trennen
    $pflichtteilnehmer = array_slice($result['alle_resultate'], 0, $result['verwendete_resultate']);
    $nichtPflichtteilnehmer = array_slice($result['alle_resultate'], $result['verwendete_resultate']);

    // Summen weiterhin für die Berechnungsformel benötigt
    $summePflicht = array_sum(array_column($pflichtteilnehmer, 'punkte'));
    $summeNichtPflicht = array_sum(array_column($nichtPflichtteilnehmer, 'punkte'));

    $layout = '
    .kv { width: 100%; border-collapse: collapse; margin: 0 0 4mm 0; }
    .kv td { padding: 0.8mm 1.4mm; border: 0; background: none; }
    .kv td.v { text-align: right; width: 30mm; }
    .kv tr.final td { border-top: 0.8pt solid #000; font-weight: bold; padding-top: 1.4mm; }
    .table td.pkt { width: 20mm; }
    .formel { margin: 0 0 1.5mm 0; }
    .kv, .formel { page-break-inside: avoid; }
    ';

    $tabelle = function (array $liste, int $startRang, bool $podest) {
        $html = '<table class="table"><thead><tr>'
              . '<th class="r">Rang</th><th>Name</th><th class="r">Punkte</th>'
              . '</tr></thead><tbody>';
        $rang = $startRang;
        foreach ($liste as $t) {
            $rang++;
            $html .= pdf_rangliste_zeile($rang, $podest)
                   . '<td class="rang">' . $rang . '.</td>'
                   . '<td>' . htmlspecialchars($t['name']) . '</td>'
                   . '<td class="r pkt">' . number_format($t['punkte'], 0) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    };

    $html = pdf_rangliste_anfang($result['anlass_name'] . ' ' . $year, 'Vereinsabrechnung', $layout);

    $html .= '<h2>Vereinswettkampf</h2>
<table class="kv"><tbody>
    <tr><td>Vereinskategorie</td><td class="v">4</td></tr>
    <tr><td>Vereinsschützen / Pflichtteilnehmer</td><td class="v">' . $result['teilnehmer_anzahl'] . ' / ' . $result['verwendete_resultate'] . '</td></tr>
</tbody></table>';

    if (!empty($pflichtteilnehmer)) {
        $html .= '<h2>Pflichtteilnehmer</h2>' . $tabelle($pflichtteilnehmer, 0, true);
    }
    if (!empty($nichtPflichtteilnehmer)) {
        $html .= '<h2>Nicht-Pflichtteilnehmer</h2>' . $tabelle($nichtPflichtteilnehmer, count($pflichtteilnehmer), false);
    }

    // Berechnung - Formel
    $zuschlagsProzent = str_replace('%', '', $result['zuschlag']); // % entfernen für Berechnung

    $html .= '<div style="page-break-inside: avoid;"><h2>Berechnung</h2>
<p class="formel">Endergebnis = (Summe Pflichtteilnehmer + Beteiligungszuschlag × Summe Nicht-Pflichtteilnehmer ÷ 100) ÷ Anzahl Pflichtteilnehmer</p>
<p class="formel">Endergebnis = (' . number_format($summePflicht, 0) . ' + ' . $zuschlagsProzent . '% × ' . number_format($summeNichtPflicht, 0) . ' ÷ 100) ÷ ' . $result['verwendete_resultate'] . '</p>
<table class="kv"><tbody>
    <tr class="final"><td>Endergebnis</td><td class="v">' . number_format($result['endergebnis'], 3) . ' Punkte</td></tr>
</tbody></table></div>
</body></html>';

    return $html;
}
?>