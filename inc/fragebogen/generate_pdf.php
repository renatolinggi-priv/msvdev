<?php
// generate_pdf_fragebogen.php
// Dieses Skript erzeugt ein PDF, in dem die Fragebogendaten als reiner Text ausgegeben werden.
// In der Tabelle wird am Ende eine Totals-Zeile integriert, die:
// - Für "Mannschaft" die Anzahl der "Ich nehme teil"-Antworten ausgibt,
// - Für "Gruppen" getrennt nach Kat. A und Kat. B die Teilnehmerzahlen darstellt,
// - Für jede erweiterte Frage die Anzahl der "ja"-Antworten anzeigt.

ob_start();

require_once '../vendor/autoload.php';  // Pfad zu Dompdf
require_once '../config.php';           // Stellt $conn (mysqli) bereit
require_once 'config_pdf.php';          // Enthält $header, $footer, CSS etc.

use Dompdf\Dompdf;
use Dompdf\Options;

// 1) GET-Parameter: Jahr
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// 2) HTML-Kopf zusammenbauen
$htmlOutput = $header;
$htmlOutput .= "<title>MSV Wilen Fragebogen $selectedYear</title>";
$htmlOutput .= "</head>\n<body>\n";

$htmlOutput .= "
<img src='{$logoBase64}' alt='MSV Wilen Logo' style='max-width:100px;' />
";
$htmlOutput .= "<h1 style='text-align:left;'>MSV Wilen Fragebogen $selectedYear</h1>";

// 3) Tabelle inkl. Totals-Zeile erzeugen
$htmlOutput .= buildFragebogenTableForPDF($selectedYear, $conn);

// 4) Footer hinzufügen (schließt </body></html>)
$htmlOutput .= $footer;

// --- PDF-Erzeugung ---
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'landscape');
$dompdf->loadHtml($htmlOutput);
$dompdf->render();

$pdfOutput = $dompdf->output();
$date = new DateTime();
$pdfFilePath = 'dat/Fragebogen_' . $date->format('Y-m-d_H-i-s') . '.pdf';
file_put_contents($pdfFilePath, $pdfOutput);

ob_end_clean();

// JSON-Antwort mit dem PDF-Link zurückgeben
echo json_encode(['pdf_link' => 'fragebogen/' . $pdfFilePath]);
exit();

// ========================================================================
// Funktion buildFragebogenTableForPDF($year, $conn)
// Baut eine HTML-Tabelle mit Fragebogendaten (als reiner Text) 
// und hängt als letzte Zeile eine Totals-Zeile an, die
// - die Anzahl "Ich nehme teil" in der Mannschaft,
// - die Gruppen-Teilnahmen getrennt nach Kat. A und Kat. B,
// - und pro erweiterter Frage die Anzahl der "ja"-Antworten anzeigt.
// ========================================================================
function buildFragebogenTableForPDF($year, $conn)
{
    // 1) Mitglieder laden â€“ inkl. Waffenkategorie (aus Tabelle Waffen)
    $sqlM = "
        SELECT m.ID, m.Vorname, m.Name, m.WaffenID, w.Kategorie
        FROM mitglieder m
        JOIN Waffen w ON w.ID = m.WaffenID
        ORDER BY m.Name, m.Vorname
    ";
    $resM = $conn->query($sqlM);
    $members = [];
    while ($row = $resM->fetch_assoc()) {
        $members[] = $row;
    }
    
    // 2) Erweitert=1 Definitionen laden
    $sqlD = "
        SELECT ID, Bezeichnung 
        FROM JMDefinition 
        WHERE year = $year AND Erweitert = 1
        ORDER BY Reihenfolge
    ";
    $resD = $conn->query($sqlD);
    $defs = [];
    while ($rd = $resD->fetch_assoc()) {
        $defs[] = $rd;
    }
    
    // 3) Waffen laden (als Array indexiert nach ID)
    $sqlW = "SELECT ID, Bezeichnung FROM Waffen ORDER BY Bezeichnung";
    $resW = $conn->query($sqlW);
    $waffen = [];
    while ($rw = $resW->fetch_assoc()) {
        $waffen[$rw['ID']] = $rw['Bezeichnung'];
    }
    
    // 4) Fragebogendaten laden aus mitglieder_fragebogen
    $sqlFB = "SELECT * FROM mitglieder_fragebogen WHERE jahr = $year";
    $resFB = $conn->query($sqlFB);
    $fragebogenData = []; // indexiert nach mitgliedID
    while ($row = $resFB->fetch_assoc()) {
        $fragebogenData[$row['mitgliedID']] = $row;
    }
    
    // 5) Erweiterte Antworten laden aus mitglieder_fragebogen_erweitert
    $sqlExt = "
        SELECT fe.*, fb.mitgliedID 
        FROM mitglieder_fragebogen_erweitert fe
        JOIN mitglieder_fragebogen fb ON fe.fragebogenID = fb.ID
        WHERE fb.jahr = $year
    ";
    $resExt = $conn->query($sqlExt);
    $extData = []; // indexiert: $extData[mitgliedID][jmdefinitionID] = antwort
    while ($row = $resExt->fetch_assoc()) {
        $extData[$row['mitgliedID']][$row['jmdefinitionID']] = $row['antwort'];
    }
    
    // 6) Totals initialisieren
    $totalMannschaftTeil = 0; // Zählt, wie viele "Ich nehme teil" bei Mannschaft
    $totalGruppenKatA   = 0; // Zählt Teilnehmer in Gruppen, Kategorie Kat. A
    $totalGruppenKatB   = 0; // Zählt Teilnehmer in Gruppen, Kategorie Kat. B
    $summaryErweitert   = []; // key: jmdefinitionID, value: Anzahl "ja"
    foreach ($defs as $df) {
        $summaryErweitert[$df['ID']] = 0;
    }
    
    // 7) HTML-Tabelle aufbauen
    $html = '<table border="1" cellspacing="0" cellpadding="5" style="width:100%; border-collapse:collapse;">';
    
    // Tabellenkopf
    $html .= '<thead><tr>';
    $html .= '<th>Name</th>';
    $html .= '<th>Waffe</th>';
    $html .= '<th>ZSMM</th>';
    $html .= '<th>GM</th>';
    foreach ($defs as $df) {
        $html .= '<th>' . htmlspecialchars($df['Bezeichnung']) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    
    // Zeilen für jedes Mitglied
    foreach ($members as $m) {
        $mid = $m['ID'];
        $fullname = htmlspecialchars($m['Name'] . ' ' . $m['Vorname']);
        $html .= '<tr>';
        $html .= '<td>' . $fullname . '</td>';
        
        // Waffe: anhand gespeicherter Daten (oder Standardwert)
        $currentWaffeID = isset($fragebogenData[$mid]['waffenID']) ? $fragebogenData[$mid]['waffenID'] : $m['WaffenID'];
        $waffeText = isset($waffen[$currentWaffeID]) ? $waffen[$currentWaffeID] : '';
        $html .= '<td>' . htmlspecialchars($waffeText) . '</td>';
        
        // Mannschaft: als Text
        $currentMannschaft = isset($fragebogenData[$mid]['mannschaft']) ? $fragebogenData[$mid]['mannschaft'] : '';
        if ($currentMannschaft === 'teil') {
            $mannschaftText = 'Ich nehme teil';
            $totalMannschaftTeil++;
        } elseif ($currentMannschaft === 'nicht') {
            $mannschaftText = 'Ich nehme nicht teil';
        } elseif ($currentMannschaft === 'evtl') {
            $mannschaftText = 'Ich nehme nur teil, wenn Gruppe füllt';
        } else {
            $mannschaftText = '';
        }
        $html .= '<td>' . htmlspecialchars($mannschaftText) . '</td>';
        
        // Gruppen: als Text
        $currentGruppen = isset($fragebogenData[$mid]['gruppen']) ? $fragebogenData[$mid]['gruppen'] : '';
        if ($currentGruppen === 'teil') {
            $gruppenText = 'Ich nehme teil';
            if (isset($m['Kategorie'])) {
                if ($m['Kategorie'] === 'Kat. A') {
                    $totalGruppenKatA++;
                } elseif ($m['Kategorie'] === 'Kat. B') {
                    $totalGruppenKatB++;
                }
            }
        } elseif ($currentGruppen === 'nicht') {
            $gruppenText = 'Ich nehme nicht teil';
        } elseif ($currentGruppen === 'evtl') {
            $gruppenText = 'Ich nehme nur teil, wenn Gruppe füllt';
        } else {
            $gruppenText = '';
        }
        $html .= '<td>' . htmlspecialchars($gruppenText) . '</td>';
        
        // Erweiterte Felder: als Text ("ja" oder "nein")
        foreach ($defs as $df) {
            $defID = $df['ID'];
            $currentAnswer = isset($extData[$mid][$defID]) ? $extData[$mid][$defID] : 'nein';
            $html .= '<td>' . htmlspecialchars($currentAnswer) . '</td>';
            if ($currentAnswer === 'ja') {
                $summaryErweitert[$defID]++;
            }
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody>';
    
    // 8) Totals-Zeile in der Tabelle einfügen
    $totalRow = '<tr style="font-weight:bold;">';
    // Spalte "Name": Zellen verbinden (colspan 2 für Name und Waffe)
    $totalRow .= '<td colspan="2">Total</td>';
    // Mannschaft: Anzahl der "Ich nehme teil"
    $totalRow .= '<td>' . $totalMannschaftTeil . '</td>';
    // Gruppen: beide Werte getrennt anzeigen
    $totalRow .= '<td>Kat. A: ' . $totalGruppenKatA . ' / Kat. B: ' . $totalGruppenKatB . '</td>';
    // Für jede erweiterte Frage
    foreach ($defs as $df) {
        $defID = $df['ID'];
        $totalRow .= '<td>' . $summaryErweitert[$defID] . '</td>';
    }
    $totalRow .= '</tr>';
    $html .= $totalRow;
    
    $html .= '</table>';
    
    return $html;
}
?>
