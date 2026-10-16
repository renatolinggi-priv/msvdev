<?php
// generate_pdf_jm.php
// Erzeugt ein PDF mit der Jahresmeisterschaft-Rangliste
// Für Kat. A und Kat. B. Werte, Streicher und Total aus jmWertung() (inc/jm_berechnung.inc.php),
// also gleich wie die Rangliste auf «Jahresmeisterschaft erfassen» und das Portal.

ob_start();

require_once '../vendor/autoload.php';
require_once '../config.php';
require_once 'config_pdf.php';
require_once __DIR__ . '/../jm_berechnung.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Zugriff nur Admin-Bereich (admin/vorstand); startet auch die Session
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

// 1) GET-Parameter: Jahr
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Jahr ohne Jahresprogramm: kein leeres PDF (und kein leeres Blatt im Direktdruck), sondern ein Hinweis
$leerStmt = $conn->prepare('SELECT COUNT(*) FROM JMDefinition WHERE year = ? AND Erweitert = 0 AND Info = 0');
$leerStmt->bind_param('i', $selectedYear);
$leerStmt->execute();
$leerStmt->bind_result($anzahlAnlaesse);
$leerStmt->fetch();
$leerStmt->close();
if (!$anzahlAnlaesse) {
    msvAusgabeLeer('Für ' . $selectedYear . ' ist noch kein Jahresprogramm mit JM-Anlässen erfasst.');
}

$wertungA = jmWertung($conn, $selectedYear, ['kategorie' => 'Kat. A']);
$wertungB = jmWertung($conn, $selectedYear, ['kategorie' => 'Kat. B']);

// Vor dem Endstich ist es ein Zwischenstand (ohne Podest), danach die Rangliste
$untertitel = $wertungA['abgeschlossen'] ? 'Rangliste' : 'Zwischenstand per ' . date('d.m.Y');

// 2) HTML-Kopf zusammenbauen mit Logo
$htmlOutput = $header;
$htmlOutput .= "<title>Jahresmeisterschaft $selectedYear</title>";
$htmlOutput .= "</head>\n<body>\n";

// 3) Ranglisten erzeugen: Kat. A / Kat. B, jede auf eigener Seite mit Kopf
$htmlOutput .= pdf_rangliste_kopf("Jahresmeisterschaft $selectedYear", $untertitel);
$htmlOutput .= buildJMRangliste($wertungA, 'Kat. A', $selectedYear);
$htmlOutput .= "<div class='page-break'></div>";
$htmlOutput .= pdf_rangliste_kopf("Jahresmeisterschaft $selectedYear", $untertitel);
$htmlOutput .= buildJMRangliste($wertungB, 'Kat. B', $selectedYear);

// 4) Footer
$htmlOutput .= $footer;

// PDF-Erzeugung
try {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    require_once __DIR__ . '/../pdf/pdf_orientation.inc.php';
    $dompdf->setPaper('A4', pdfOrientationParam('landscape')); // Ausrichtung aus dem Druckprofil, Default quer
    $dompdf->loadHtml($htmlOutput);
    $dompdf->render();
    pdf_seitenfuss($dompdf, "Jahresmeisterschaft $selectedYear");

    // PDF-Datei speichern
    $pdfOutput = $dompdf->output();
    $date = new DateTime();
    $pdfFilePath = 'dat/Jahresmeisterschaft_' . $date->format('Y-m-d_H-i-s') . '.pdf';

    // Verzeichnis erstellen falls nicht vorhanden
    if (!file_exists('dat')) {
        mkdir('dat', 0755, true);
    }

    file_put_contents($pdfFilePath, $pdfOutput);

    // Buffer leeren
    ob_end_clean();

    // JSON-Antwort mit dem PDF-Link für AJAX
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['pdf_link' => 'jmrang/'.$pdfFilePath]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e)]);
    exit;
}

// ========================================================================
// Hilfsfunktion buildJMRangliste($wertung, $kategorie, $year): HTML-Tabelle einer Kategorie
// ========================================================================
function buildJMRangliste(array $wertung, string $kategorie, int $year): string {
    $definitions = $wertung['definitionen'];
    if (!$definitions) {
        return "<p>Keine Wettbewerbe für $year gefunden.</p>";
    }
    if (!$wertung['mitglieder']) {
        return "<p>Keine Mitglieder für Kategorie $kategorie gefunden.</p>";
    }
    $fertig = $wertung['abgeschlossen'];
    $anzahlStreicher = $wertung['anzahlStreicher'];

    $html = '<h2>' . ($kategorie === 'Kat. A' ? 'Kat. A (Standardgewehr)' : 'Kat. B (Ordonnanzwaffen)') . '</h2>';

    // Kopfhöhe dynamisch an die längste Bezeichnung anpassen, damit die rotierten
    // Labels vollständig in den Kopf passen und nicht nach oben in den Titel ausbrechen.
    $maxLen = 1;
    foreach ($definitions as $d) {
        $b = trim($d['Bezeichnung']);
        $len = function_exists('mb_strlen') ? mb_strlen($b) : strlen($b);
        if ($len > $maxLen) { $maxLen = $len; }
    }
    $headFont = 8; // px
    $headH    = min(230, max(96, (int)ceil($maxLen * $headFont * 0.62) + 12)); // Textlänge + Polster

    // Volle Seitenbreite über PROZENTUALE Spaltenbreiten im normalen Auto-Layout
    // (Dompdf setzt das zuverlässig um; Namen werden nie abgeschnitten). Rang 4% +
    // Name 13% + Total 5%, die Resultat-Spalten teilen sich die restlichen 78%.
    $nCols  = count($definitions);
    $colPct = $nCols > 0 ? round(78 / $nCols, 4) : 78;

    $html .= '<table class="table jm" style="width: 100%;">';
    $html .= '<thead><tr style="height: ' . $headH . 'px;">';
    $html .= '<th style="width: 4%; vertical-align: bottom; text-align: right;">Rang</th>';
    // Name-Spalte: schmal, aber breit genug für die längsten Namen auf einer Zeile
    $html .= '<th style="width: 13%; text-align: left; vertical-align: bottom; padding-right: 8px;">Name</th>';

    // Spaltenüberschriften mit 90-Grad-Rotation - OHNE KÜRZUNGEN; gleichmässig verteilt
    foreach ($definitions as $d) {
        $bez = trim($d['Bezeichnung']);

        // Rotation mit div-Container; Kopf hoch genug für volle Bezeichnung
        $html .= '<th class="result-col" style="width: ' . $colPct . '%; height: ' . $headH . 'px; vertical-align: bottom; position: relative; padding: 0;">';
        $html .= '<div style="position: absolute; bottom: 6px; left: 19px; transform: rotate(-90deg); transform-origin: left bottom; white-space: nowrap; font-size: ' . $headFont . 'px; width: ' . ($headH - 12) . 'px;">';
        $html .= htmlspecialchars($bez);
        $html .= '</div>';
        $html .= '</th>';
    }

    $html .= '<th style="width: 5%; vertical-align: bottom; text-align: right;">Total</th>';
    $html .= '</tr></thead><tbody>';

    // Inhalt: Reihenfolge und Rang kommen aus jmWertung (Gleichstand: Endstich entscheidet).
    // Mitglieder ohne gewertetes Resultat stehen am Schluss, ohne Rang.
    $nachPodest = 0;
    foreach ($wertung['mitglieder'] as $entry) {
        $rang = $entry['rang'];
        $m = $entry['mitglied'];
        $fullname = htmlspecialchars($m['Name'].' '.$m['Vorname']);

        // Rang 1–3 fett, vor der ersten Zeile danach etwas Abstand; im Zwischenstand kein Podest
        $stufe = ($rang !== null && $rang <= 3) ? $rang : ($nachPodest++ === 0 ? 4 : 5);
        $html .= pdf_rangliste_zeile($stufe, $fertig);
        $html .= "<td class='rang'>" . ($rang !== null ? "$rang." : '') . "</td>";
        $html .= "<td class='name'>$fullname</td>";

        foreach ($definitions as $def) {
            $w = $entry['werte'][$def['ID']] ?? null;
            // Nachkommastellen nur bei hochgerechneten Resultaten; rohe Resultate ganzzahlig
            $cellContent = '–';
            if ($w !== null) {
                $pVal = number_format($w['punkte'], $def['hochgerechnet'] ? 2 : 0, '.', '');
                $cellContent = $w['gestrichen'] ? "<span class='struck'>$pVal</span>" : $pVal;
            }
            $html .= "<td class='result-col'>$cellContent</td>";
        }

        // Total: einheitlich zwei Nachkommastellen
        $html .= "<td class='total'>" . number_format($entry['total'], 2, '.', '') . "</td>";
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';
    $html .= $fertig
        ? '<p class="rl-hinweis"><span class="struck">96.00</span> = Streicher: die ' . $anzahlStreicher
            . ' schlechtesten Resultate zählen nicht. 0 = nicht teilgenommen, – = Anlass noch ohne Resultate.</p>'
        : '<p class="rl-hinweis">Zwischenstand: bis zum Endstich sind nur verpasste Anlässe gestrichen (<span class="struck">0</span>, höchstens '
            . $anzahlStreicher . '), danach die ' . $anzahlStreicher . ' schlechtesten Resultate. – = Anlass noch ohne Resultate.</p>';
    return $html;
}
