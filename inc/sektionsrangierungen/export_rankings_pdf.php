<?php
// export_rankings_pdf.php
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand), startet die Session
require_once '../vendor/autoload.php';
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/../pdf/pdf_theme.php';

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

try {
    // Rangierungen für das Jahr laden (sortiert nach Datum/Reihenfolge der Anlässe)
    $sql = "SELECT sr.rang, sr.preis, jd.Bezeichnung as bezeichnung, jd.Reihenfolge, jd.Schiesstage
            FROM sektionsrangierungen sr
            JOIN JMDefinition jd ON sr.jmdefinition_id = jd.ID
            WHERE sr.year = ?
            ORDER BY jd.Reihenfolge ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Fehler beim Vorbereiten der SQL-Abfrage: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $year);
    
    if (!$stmt->execute()) {
        throw new Exception('Fehler beim Ausführen der SQL-Abfrage: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $rankings = [];
    
    while ($row = $result->fetch_assoc()) {
        $rankings[] = [
            'rang' => $row['rang'],
            'preis' => $row['preis'],
            'bezeichnung' => $row['bezeichnung'],
            'reihenfolge' => $row['Reihenfolge'],
            'schiesstage' => $row['Schiesstage']
        ];
    }
    
    $stmt->close();
    
    if (empty($rankings)) {
        echo json_encode(['success' => false, 'message' => 'Keine Rangierungen für dieses Jahr gefunden']);
        exit;
    }
    
    // HTML für PDF generieren
    $html = generatePdfHtml($rankings, $year);
    
    // PDF-Optionen konfigurieren
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    
    // DOMPDF initialisieren
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    require_once __DIR__ . '/../pdf/pdf_orientation.inc.php';
    $dompdf->setPaper('A4', pdfOrientationParam('portrait')); // Ausrichtung aus dem Druckprofil, Default hoch
    $dompdf->render();
    pdf_seitenfuss($dompdf, 'Sektionsrangierungen ' . $year);
    
    // Dateiname generieren
    $filename = "Sektionsrangierungen_" . $year . "_" . date('Y-m-d_H-i-s') . ".pdf";
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
        'pdf_url' => 'sektionsrangierungen/dat/' . $filename,
        'filename' => $filename,
        'message' => 'PDF erfolgreich generiert'
    ]);
    
} catch (Exception $e) {
    error_log("Fehler in export_rankings_pdf.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e)
    ]);
} finally {
    $conn->close();
}

/**
 * Generiert das HTML für das PDF im Ranglisten-Layout (pdf_rangliste_anfang)
 */
function generatePdfHtml($rankings, $year) {
    // Statistiken berechnen
    $totalRankings = count($rankings);
    $totalPrizes = array_sum(array_column($rankings, 'preis'));

    // Rangverteilung
    $rankDistribution = [];
    foreach ($rankings as $ranking) {
        $rang = $ranking['rang'];
        $rankDistribution[$rang] = ($rankDistribution[$rang] ?? 0) + 1;
    }
    ksort($rankDistribution);

    $chf = fn($v) => 'CHF ' . number_format((float)$v, 2);
    $layout = '
    .table td.preis { width: 28mm; }
    .table td.rang { width: 14mm; }
    .table td.datum { width: 32mm; }
    .table tr.total td { border-top: 0.8pt solid #000; font-weight: bold; padding-top: 1.4mm; }
    .block { page-break-inside: avoid; }
    ';

    $html = pdf_rangliste_anfang('Sektionsrangierungen ' . $year, 'Rangierungen des Vereins an auswärtigen Anlässen', $layout);

    // Kennzahlen
    $html .= '<table class="table"><thead><tr>'
           . '<th class="r">Anlässe</th><th class="r">Preissumme</th><th class="r">Ø Preis</th>'
           . '</tr></thead><tbody><tr>'
           . '<td class="r">' . $totalRankings . '</td>'
           . '<td class="r">' . $chf($totalPrizes) . '</td>'
           . '<td class="r">' . $chf($totalPrizes / $totalRankings) . '</td>'
           . '</tr></tbody></table>';

    $html .= '<h2>Anlässe</h2><table class="table"><thead><tr>'
           . '<th>Anlass</th><th class="r">Rang</th><th class="r">Preis</th><th>Datum</th>'
           . '</tr></thead><tbody>';
    foreach ($rankings as $ranking) {
        // Datum aus Schiesstage extrahieren (erste Zeile)
        $datum = '';
        if (!empty($ranking['schiesstage'])) {
            $lines = explode("\n", trim($ranking['schiesstage']));
            if (!empty($lines[0])) {
                preg_match('/(\d{1,2}\.\s*\w+\s*\d{4})/', $lines[0], $matches);
                $datum = $matches[1] ?? substr($lines[0], 0, 30) . '...';
            }
        }
        $html .= '<tr>'
               . '<td>' . htmlspecialchars($ranking['bezeichnung']) . '</td>'
               . '<td class="rang">' . (int)$ranking['rang'] . '.</td>'
               . '<td class="r preis">' . $chf($ranking['preis']) . '</td>'
               . '<td class="datum">' . htmlspecialchars($datum) . '</td>'
               . '</tr>';
    }
    $html .= '<tr class="total"><td>Total</td><td></td><td class="r preis">' . $chf($totalPrizes) . '</td><td></td></tr>';
    $html .= '</tbody></table>';

    // Rangverteilung
    $html .= '<div class="block"><h2>Rangverteilung</h2><table class="table"><thead><tr><th>Rang</th>';
    foreach (array_keys($rankDistribution) as $rang) {
        $html .= '<th class="r">' . $rang . '.</th>';
    }
    $html .= '</tr></thead><tbody><tr><td>Anzahl</td>';
    foreach ($rankDistribution as $count) {
        $html .= '<td class="r">' . $count . '</td>';
    }
    $html .= '</tr></tbody></table></div>';

    $html .= '</body></html>';
    return $html;
}
?>