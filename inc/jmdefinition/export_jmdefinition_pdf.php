<?php
// export_jmdefinition_pdf.php

require '../vendor/autoload.php'; // Pfad zu Composer's Autoload Datei
include '../config.php'; // Datenbankverbindung
require_once __DIR__ . '/../pdf/pdf_theme.php';

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

            // Falls das Jahr grösser als das aktuelle Jahr ist, füge es hinzu
            if ($year > $currentYear) {
                $month .= " " . $year;
            }

            $days[] = $day;
            $months[] = $month;
        }
    }

    // Ohne erkanntes Datum leer lassen (früher stand dann ein einzelner Punkt)
    $uniqueDays = $days ? implode('. / ', array_unique($days)) . '.' : '';
    $uniqueMonths = implode(' / ', array_unique($months));

    return [
        'days' => $uniqueDays,
        'months' => $uniqueMonths
    ];
}

// Lade das Logo (zentrales MSV-Wilen-Logo)
$logoBase64 = pdf_logo_src();

// Aktuelles Jahr (oder spezifisches Jahr aus GET-Parameter)
$currentYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$isDraft = isset($_GET['draft']) && $_GET['draft'] == 1;

// Anzahl Streicher aus Parameter-Tabelle laden und Platzhalter im Zusatztext ersetzen
$stParam = $conn->prepare("SELECT excludeCount FROM Parameter WHERE year = ?");
$stParam->bind_param('i', $currentYear);
$stParam->execute();
$rowParam = $stParam->get_result()->fetch_assoc();
$stParam->close();
$anzahl_streicher_pdf = $rowParam ? max(1, (int)$rowParam['excludeCount']) : 3;
$zusatztext = str_replace('{anzahl_streicher}', (string)$anzahl_streicher_pdf, $zusatztext);

// SQL-Abfrage vorbereiten, um JMDefinition-Daten zu laden
$sql = "SELECT Reihenfolge, Bezeichnung, Schiesstage, Maxpunkte, Streicher, Erweitert, Info FROM JMDefinition WHERE year = ? ORDER BY Reihenfolge";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $conn->error)]);
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

// HTML für das PDF: Ranglisten-Layout (Logo links, Titel, Fusszeile mit Stand) – muss auf EINE A4-Seite passen.
// Zeilen kompakter als in den Ranglisten; je mehr Anlässe, desto kleiner (geprüft mit 2024–2026: 22/31/34 Zeilen).
$anzahlZeilen = count($jmdefinitions);
if ($anzahlZeilen > 38) {
    [$jpSchrift, $jpPolster] = ['8pt', '0.4mm'];
} elseif ($anzahlZeilen > 31) {
    [$jpSchrift, $jpPolster] = ['8.5pt', '0.6mm'];
} else {
    [$jpSchrift, $jpPolster] = ['9pt', '0.9mm'];
}
$jpCss = '
    .jp { font-size: ' . $jpSchrift . '; margin-bottom: 4mm; }
    .jp th, .jp td { padding: ' . $jpPolster . ' 1.2mm; }
    .jp th { vertical-align: bottom; }
    .jp th.tage, .jp td.tage { white-space: nowrap; padding-right: 3mm; }
    .jp th.monat, .jp td.monat { white-space: nowrap; padding-right: 3mm; }
    .jp th.x, .jp td.x { width: 20mm; text-align: center; }
    .jp td:last-child { text-align: center; }
    .jp-text { font-size: 8.5pt; line-height: 1.35; margin: 0; }
    .watermark { position: fixed; top: 38%; left: 8%; transform: rotate(-35deg); font-size: 90pt; font-weight: bold;
                 color: rgba(200, 0, 0, 0.15); z-index: 1000; white-space: nowrap; }
';
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$titel = 'Jahresprogramm ' . (int)$currentYear;

// Monate eines Anlasses auf eine Zeile: über mehrere Monate abgekürzt («Nov. / Dez.»), einzelne ausgeschrieben
$monatKurz = ['Januar' => 'Jan.', 'Februar' => 'Feb.', 'April' => 'Apr.', 'August' => 'Aug.', 'September' => 'Sept.',
              'Oktober' => 'Okt.', 'November' => 'Nov.', 'Dezember' => 'Dez.'];
$monateText = static function (string $monate) use ($monatKurz): string {
    if (strpos($monate, ' / ') === false) {
        return $monate;
    }
    return implode(' / ', array_map(
        static fn($m) => preg_replace_callback('/^(\p{L}+)/u', static fn($t) => $monatKurz[$t[1]] ?? $t[1], $m),
        explode(' / ', $monate)
    ));
};

$html = pdf_rangliste_anfang($titel, 'Militärschützenverein Wilen · Jahresmeisterschaft und Gruppenschiessen', $jpCss);
if ($isDraft) {
    $html .= '<div class="watermark">ENTWURF</div>';
}
$html .= '<div class="container"><table class="table rl-zebra jp"><thead><tr>'
    . '<th class="tage">Datum</th><th class="monat"></th><th>Schiessanlass</th>'
    . '<th class="x">Jahres-<br>meisterschaft</th><th class="x">Gruppen-<br>schiessen</th>'
    . '</tr></thead><tbody>';

foreach ($jmdefinitions as $jm) {
    $dateData = extractDaysAndMonths($jm['Schiesstage']);
    // Bezeichnung anpassen: "Endstich" -> "MSV Wilen Endschiessen"
    $bezeichnung = trim($jm['Bezeichnung']) === 'Endstich' ? 'MSV Wilen Endschiessen' : $jm['Bezeichnung'];

    // Spalte JM: Bonus (20 Punkte), X (zählt) oder leer (Info / nur erweitert)
    $isStreicher = (int)($jm['Streicher'] ?? 0);
    $isErweitert = (int)($jm['Erweitert'] ?? 0);
    $isInfo      = (int)($jm['Info'] ?? 0);
    if ((int)$jm['Maxpunkte'] === 20) {
        $jmText = 'Bonus';
    } elseif ($isInfo === 1) {
        $jmText = '';
    } elseif ($isStreicher === 1 || $isErweitert === 0) {
        $jmText = 'X';
    } else {
        $jmText = '';
    }

    $html .= '<tr>'
        . '<td class="tage">' . $h($dateData['days']) . '</td>'
        . '<td class="monat">' . $h($monateText($dateData['months'])) . '</td>'
        . '<td>' . $h($bezeichnung) . '</td>'
        . '<td class="x">' . $jmText . '</td>'
        . '<td class="x">' . ($jm['Erweitert'] ? 'X' : '') . '</td>'
        . '</tr>';
}
$html .= '</tbody></table>';

// Zusatztext (Bonus, Streicher …) – wichtig, darum normale Schrift statt grau
if (!empty($zusatztext)) {
    $html .= '<p class="jp-text">' . nl2br($h($zusatztext)) . '</p>';
}
$html .= '</div></body></html>';

// Dompdf initialisieren
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->set_option('debugFont', true);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
pdf_seitenfuss($dompdf, $titel);

// PDF-Ausgabe speichern. Schreibpfad ABSOLUT ueber __DIR__, damit das Ergebnis nicht vom
// Working Directory des Aufrufers abhaengt (bei include aus einem anderen Verzeichnis
// landete die Datei sonst woanders). Der zurueckgegebene Link bleibt relativ wie bisher.
$date = new DateTime();
$draftSuffix = $isDraft ? '_ENTWURF' : '';
$pdfFileName = "Jahresprogramm_{$currentYear}{$draftSuffix}_" . $date->format('Y-m-d_H-i-s') . ".pdf";
$pdfFilePath = "dat/" . $pdfFileName;
$datDir      = __DIR__ . '/dat';
file_put_contents($datDir . '/' . $pdfFileName, $dompdf->output());

// Aufraeumen direkt beim Generieren. Dieses Script ist OHNE LOGIN oeffentlich erreichbar
// und legte bei jedem Aufruf eine neue zeitgestempelte Datei an (Stand 05.08.2026: 965
// Dateien / 7.7 MB). Der woechentliche cron/cleanup_dat.php allein genuegt hier nicht --
// zwischen zwei Laeufen liesse sich das Verzeichnis beliebig weit fluten.
// Der Helfer gruppiert je Namens-Praefix, "Jahresprogramm_2025" und "Jahresprogramm_2026"
// behalten also unabhaengig voneinander ihre neuesten Staende.
require_once __DIR__ . '/../dat_cleanup.inc.php';
datAufraeumen($datDir, 3);

// JSON-Antwort mit PDF-Link
echo json_encode([
    'success' => true,
    'pdf_link' => 'jmdefinition/' . $pdfFilePath
]);
?>
