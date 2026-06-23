<?php
// generate_pdf_all_results.php
// - Erstellt ein PDF mit einer Rangliste, in der *alle* Resultate summiert werden
// - Keine Streicher-Logik, alle Werte werden gezählt

ob_start();
require_once '../vendor/autoload.php';
require_once '../config.php';
require_once 'config_pdf.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Session-Kontrolle
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Parameter
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// HTML-Vorbau mit Logo
$htmlOutput = $header;
$htmlOutput .= '<title>Jahresmeisterschaft ' . $selectedYear . ' (Alle Resultate)</title>
</head>
<body>';

// Logo und Header für erste Seite
$logoPath = '../../images/MSVWilen_Logo.jpg'; // Pfad anpassen falls nötig
$htmlOutput .= '<div class="pdf-header">';
if (file_exists($logoPath)) {
    $htmlOutput .= '<div class="logo-container">';
    $htmlOutput .= '<img src="' . imgToBase64($logoPath) . '" class="logo" alt="MSV Wilen Logo">';
    $htmlOutput .= '</div>';
}
$htmlOutput .= '<div class="header-text">';
$htmlOutput .= '<h1>Jahresmeisterschaft ' . $selectedYear . ' - Alle Resultate (ohne Streichresultate)</h1>';
$htmlOutput .= '</div>';
$htmlOutput .= '</div>';

// Tabelle für Kat. A
$htmlOutput .= createTable('Kat. A', $selectedYear);

// Seitenumbruch
$htmlOutput .= '<div class="page-break"></div>';

// Header für zweite Seite wiederholen
$htmlOutput .= '<div class="pdf-header">';
if (file_exists($logoPath)) {
    $htmlOutput .= '<div class="logo-container">';
    $htmlOutput .= '<img src="' . imgToBase64($logoPath) . '" class="logo" alt="MSV Wilen Logo">';
    $htmlOutput .= '</div>';
}
$htmlOutput .= '<div class="header-text">';
$htmlOutput .= '<h1>Jahresmeisterschaft ' . $selectedYear . ' - Alle Resultate (ohne Streichresultate)</h1>';
$htmlOutput .= '</div>';
$htmlOutput .= '</div>';

// Tabelle für Kat. B
$htmlOutput .= createTable('Kat. B', $selectedYear);

$htmlOutput .= $footer;

// PDF erzeugen
try {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $options->set('isPhpEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->loadHtml($htmlOutput);
    $dompdf->render();

    // Canvas holen für Footer - NACH render()!
    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->get_font("Arial", "normal");
    
    // Footer auf jeder Seite hinzufügen
    $canvas->page_text(20, $canvas->get_height() - 25, 
        "Erstellt am: " . date('d.m.Y H:i') . " Uhr", 
        $font, 7, array(0, 0, 0));
    
    $canvas->page_text($canvas->get_width() / 2 - 50, $canvas->get_height() - 25, 
        "MSV Wilen - Jahresmeisterschaft", 
        $font, 7, array(0, 0, 0));
    
    $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 25, 
        "Seite {PAGE_NUM} von {PAGE_COUNT}", 
        $font, 7, array(0, 0, 0));

    // PDF speichern
    $pdfOutput = $dompdf->output();
    $date = new DateTime();
    $pdfFilePath = 'dat/Jahresmeisterschaft_AlleResultate_' . $date->format('Y-m-d_H-i-s') . '.pdf';
    
    // Verzeichnis erstellen falls nicht vorhanden
    if (!file_exists('dat')) {
        mkdir('dat', 0755, true);
    }
    
    file_put_contents($pdfFilePath, $pdfOutput);

    // Buffer clean
    ob_end_clean();

    // JSON-Antwort
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['pdf_link' => $pdfFilePath]);
    exit;
    
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'PDF-Generierung fehlgeschlagen: ' . $e->getMessage()]);
    exit;
}

/**
 * createTable($kategorie, $selectedYear)
 * Baut eine Tabelle pro Kategorie auf, wobei ALLE Resultate summiert werden.
 */
function createTable($kategorie, $selectedYear) {
    global $conn;

    // 1) Wettbewerbe laden
    $sqlWettbewerbe = "
        SELECT ID, Bezeichnung, Maxpunkte 
        FROM JMDefinition
        WHERE year = ?
          AND hidden = 0
          AND Erweitert = 0
          AND Info = 0
        ORDER BY 
            CASE 
                WHEN Bezeichnung = 'Obligatorisch' THEN 1
                WHEN Bezeichnung = 'Feldschiessen' THEN 2
                WHEN Bezeichnung LIKE '%Kantonalstich%' THEN 3
                WHEN Bezeichnung LIKE '%Sektionsmeisterschaft%' THEN 4
                ELSE 5
            END, Reihenfolge
    ";
    $stmtW = $conn->prepare($sqlWettbewerbe);
    $stmtW->bind_param('i', $selectedYear);
    $stmtW->execute();
    $resW = $stmtW->get_result();
    
    $wettbewerbe = [];
    while ($row = $resW->fetch_assoc()) {
        $wettbewerbe[$row['ID']] = [
            'Bezeichnung' => $row['Bezeichnung'],
            'Maxpunkte'   => $row['Maxpunkte'],
        ];
    }

    // 2) Mitglieder in der Kategorie laden - SORTIERT NACH NAME UND VORNAME
    $sqlM = "
        SELECT m.ID, m.Vorname, m.Name
        FROM mitglieder m
        JOIN Waffen w ON w.ID = m.WaffenID
        WHERE w.Kategorie = ? AND m.Status = 1
        ORDER BY m.Name ASC, m.Vorname ASC
    ";
    $stmtM = $conn->prepare($sqlM);
    $stmtM->bind_param('s', $kategorie);
    $stmtM->execute();
    $resM = $stmtM->get_result();
    
    $mitglieder = [];
    while ($rowM = $resM->fetch_assoc()) {
        $mitglieder[$rowM['ID']] = $rowM;
    }

    // Datenstruktur für Sortierung vorbereiten
    $resultData = [];

    // 3) Für jeden Schützen alle Wettbewerbe durchgehen
    foreach ($mitglieder as $mid => $mData) {
        $fullname = htmlspecialchars($mData['Name'] . ' ' . $mData['Vorname']);
        $competitorSum = 0;
        $wettbewerbResultate = [];

        // Pro Wettbewerb
        foreach ($wettbewerbe as $wbID => $wb) {
            $bez       = $wb['Bezeichnung'];
            $maxpunkte = (int)$wb['Maxpunkte'];

            // Hole ALLE relevanten Werte
            $werte = [];
            
            if ($bez === 'Endstich') {
                // Endstich => summe Schuss1..10
                $sqlEnd = "
                    SELECT Schuss1,Schuss2,Schuss3,Schuss4,Schuss5,
                           Schuss6,Schuss7,Schuss8,Schuss9,Schuss10
                    FROM endstich
                    WHERE MitgliedID = ?
                      AND Jahr = ?
                ";
                $stmtEnd = $conn->prepare($sqlEnd);
                $stmtEnd->bind_param('ii', $mid, $selectedYear);
                $stmtEnd->execute();
                $resEnd = $stmtEnd->get_result();
                
                if ($resEnd && $resEnd->num_rows > 0) {
                    while($rowE = $resEnd->fetch_assoc()) {
                        $summe = array_sum($rowE);
                        $werte[] = $summe; 
                    }
                }
            }
            elseif ($bez === 'Bester Kantonalstich') {
                // Bester Kantonalstich
                $sqlK = "
                    SELECT GREATEST(
                      COALESCE(Passe1,0),
                      COALESCE(Passe2,0),
                      COALESCE(Passe3,0),
                      COALESCE(Passe4,0),
                      COALESCE(Passe5,0)
                    ) AS best 
                    FROM kantiresultate
                    WHERE MitgliedID = ?
                      AND Jahr = ?
                ";
                $stmtK = $conn->prepare($sqlK);
                $stmtK->bind_param('ii', $mid, $selectedYear);
                $stmtK->execute();
                $resK = $stmtK->get_result();
                
                if ($resK && $resK->num_rows > 0) {
                    while($rowK = $resK->fetch_assoc()) {
                        $werte[] = (int)$rowK['best'];
                    }
                }
            }
            else {
                // Normaler Wettbewerb aus jmresultate
                $sqlJ = "
                    SELECT Punkte
                    FROM jmresultate
                    WHERE mitgliederID = ?
                      AND jmdefinitionID = ?
                ";
                $stmtJ = $conn->prepare($sqlJ);
                $stmtJ->bind_param('ii', $mid, $wbID);
                $stmtJ->execute();
                $resJ = $stmtJ->get_result();
                
                if ($resJ && $resJ->num_rows > 0) {
                    while($rowJ = $resJ->fetch_assoc()) {
                        $werte[] = (int)$rowJ['Punkte'];
                    }
                }
            }

            // Skalierung
            $scaledValues = [];
            foreach ($werte as $val) {
                if (!in_array($bez, ['Obligatorisch','Feldschiessen','Einzelwettschiessen']) && $maxpunkte > 0 && $maxpunkte < 100) {
                    $val = round(($val * 100.0 / $maxpunkte), 2);
                }
                $scaledValues[] = $val;
            }

            // Speichern für Ausgabe
            if (count($scaledValues) > 0) {
                $summe = array_sum($scaledValues);
                $competitorSum += $summe;
                $wettbewerbResultate[$wbID] = $scaledValues;
            } else {
                $wettbewerbResultate[$wbID] = [];
            }
        }

        // Daten für Sortierung speichern
        $resultData[] = [
            'name' => $fullname,
            'total' => $competitorSum,
            'resultate' => $wettbewerbResultate
        ];
    }

    // Nach Name und Vorname sortieren (NICHT nach Total)
    usort($resultData, function($a, $b) {
        // Extrahiere Nachname und Vorname aus dem vollständigen Namen
        $partsA = explode(' ', $a['name']);
        $partsB = explode(' ', $b['name']);
        
        // Annahme: Letztes Element ist Vorname, alles davor ist Nachname
        $vornameA = array_pop($partsA);
        $nachnameA = implode(' ', $partsA);
        
        $vornameB = array_pop($partsB);
        $nachnameB = implode(' ', $partsB);
        
        // Erst nach Nachname sortieren
        $nachnameCmp = strcasecmp($nachnameA, $nachnameB);
        if ($nachnameCmp !== 0) {
            return $nachnameCmp;
        }
        // Bei gleichem Nachname nach Vorname sortieren
        return strcasecmp($vornameA, $vornameB);
    });

    // HTML-Tabelle aufbauen mit Rotation
    $html = '<div class="container">';
    $html .= '<h2>Kategorie ' . str_replace('Kat. ', '', $kategorie) . '</h2>';
    
    // Kopfhöhe dynamisch an die längste Wettbewerbs-Bezeichnung anpassen, damit die
    // rotierten Labels vollständig in den Kopf passen und nicht nach oben in den Titel ausbrechen.
    $maxLen = 1;
    foreach ($wettbewerbe as $wb) {
        $b = trim($wb['Bezeichnung']);
        $len = function_exists('mb_strlen') ? mb_strlen($b) : strlen($b);
        if ($len > $maxLen) { $maxLen = $len; }
    }
    $headFont = 8; // px
    $headH    = min(230, max(96, (int)ceil($maxLen * $headFont * 0.62) + 12)); // Textlänge + Polster

    // Volle Seitenbreite über PROZENTUALE Spaltenbreiten im normalen Auto-Layout
    // (Dompdf setzt das zuverlässig um; Namen werden nie abgeschnitten, da Auto-Layout
    // die Spalte notfalls vergrössert). Name 14% + Total 6%, Resultate teilen sich 80%.
    $nCols  = count($wettbewerbe);
    $colPct = $nCols > 0 ? round(80 / $nCols, 4) : 80;

    $html .= '<table class="table" style="width: 100%;">';
    $html .= '<thead><tr style="height: ' . $headH . 'px;">';
    // Name-Spalte: schmal, aber breit genug für die längsten Namen auf einer Zeile
    $html .= '<th style="width: 14%; text-align: left; vertical-align: bottom; padding-right: 8px;">Name</th>';

    // Spaltenüberschriften mit 90-Grad-Rotation - OHNE KÜRZUNGEN; gleichmässig verteilt
    foreach ($wettbewerbe as $wbID => $wb) {
        $bez = trim($wb['Bezeichnung']);

        // Rotation mit div-Container; Kopf hoch genug für volle Bezeichnung
        $html .= '<th class="result-col" style="width: ' . $colPct . '%; height: ' . $headH . 'px; vertical-align: bottom; position: relative; padding: 0;">';
        $html .= '<div style="position: absolute; bottom: 6px; left: 19px; transform: rotate(-90deg); transform-origin: left bottom; white-space: nowrap; font-size: ' . $headFont . 'px; width: ' . ($headH - 12) . 'px;">';
        $html .= htmlspecialchars($bez);
        $html .= '</div>';
        $html .= '</th>';
    }

    $html .= '<th style="width: 6%; vertical-align: bottom; text-align: right;">Total</th></tr>
    </thead><tbody>';

    // Daten ausgeben - OHNE RANG-SPALTE
    foreach ($resultData as $entry) {
        $html .= "<tr>";
        $html .= "<td style='text-align: left; font-weight: normal; font-size: 11px; white-space: nowrap; padding-right: 8px;'>{$entry['name']}</td>";

        // Pro Wettbewerb (hochgerechnete Resultate mit 2 Nachkommastellen, sonst ohne)
        foreach ($wettbewerbe as $wbID => $wb) {
            if (!empty($entry['resultate'][$wbID])) {
                $parts = [];
                foreach ($entry['resultate'][$wbID] as $v) {
                    // Ganzzahlige (nicht hochgerechnete) Resultate ohne Nachkommastellen
                    $dec = (abs($v - round($v)) < 0.005) ? 0 : 2;
                    $parts[] = "<span class='value'>" . number_format($v, $dec, '.', '') . "</span>";
                }
                $display = implode(', ', $parts);
            } else {
                $display = '<span class="no-data">-</span>';
            }
            $html .= "<td class='result-col'>$display</td>";
        }

        // Total: nur Nachkommastellen wenn nicht ganzzahlig
        $totalDec = (abs($entry['total'] - round($entry['total'])) < 0.005) ? 0 : 2;
        $html .= "<td><strong>" . number_format($entry['total'], $totalDec, '.', '') . "</strong></td>";
        $html .= "</tr>";
    }

    $html .= '</tbody></table>';

    $html .= '</div>';
    return $html;
}