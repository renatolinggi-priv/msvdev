<?php
// export_jsk_pdf.php - Exportiert JSK-Termine als PDF
use Dompdf\Dompdf;
use Dompdf\Options;

// Ausgabepufferung starten
ob_start();

// Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require '../vendor/autoload.php';
    include '../config.php';
    require_once __DIR__ . '/../admin_api_guard.inc.php';
    adminApiGuard('plain');
    require_once '../pdf/pdf_theme.php';  // zentrales PDF-Theme (Palette/Logo)

    // Verbindung prüfen
    if ($conn->connect_error) {
        throw new Exception("Verbindung fehlgeschlagen: " . $conn->connect_error);
    }

    // Jahr aus Parameter oder aktuelles Jahr
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $currentYear = date('Y');
    if ($year < 2000 || $year > $currentYear + 1) {
        $year = $currentYear;
    }

    // JSK-Termine: gleiche Art-Erkennung wie Export/Übersicht (Keywords aus der DB + Standard-Regeln)
    // statt eines zweiten, hart codierten LIKE-Katalogs.
    require_once __DIR__ . '/standbelegung_config.inc.php';
    $keywords = sb_load_keywords($conn);

    $stmt = $conn->prepare("SELECT Datum, Wochentag, Bezeichnung, StartZeit, EndZeit FROM Standbelegung WHERE Jahr = ? ORDER BY Datum ASC, StartZeit ASC");
    if (!$stmt) {
        throw new Exception("Fehler beim Vorbereiten der Abfrage: " . $conn->error);
    }
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $termine = [];
    while ($row = $result->fetch_assoc()) {
        if (sb_detect_art((string)$row['Bezeichnung'], $keywords) === 'JSK') {
            $termine[] = $row;
        }
    }
    $stmt->close();
    $conn->close();

    // Termine nach Typ gruppieren
    $einschreiben = [];
    $kursTage = [];
    $wettschiessen = [];

    foreach ($termine as $termin) {
        $bez = $termin['Bezeichnung'];
        if (stripos($bez, 'Einschreiben') !== false) {
            $einschreiben[] = $termin;
        } elseif (stripos($bez, 'Wettschiessen') !== false) {
            $wettschiessen[] = $termin;
        } else {
            $kursTage[] = $termin;
        }
    }

    // Hilfsfunktionen
    function formatDatum($datum) {
        $d = new DateTime($datum);
        return $d->format('d.m.Y');
    }

    function formatWochentag($wt) {
        $mapping = [
            'MO' => 'Montag',
            'DI' => 'Dienstag',
            'MI' => 'Mittwoch',
            'DO' => 'Donnerstag',
            'FR' => 'Freitag',
            'SA' => 'Samstag',
            'SO' => 'Sonntag'
        ];
        return $mapping[strtoupper($wt)] ?? $wt;
    }

    function formatZeit($start, $end) {
        $s = $start ? substr($start, 0, 5) : '';
        $e = $end ? substr($end, 0, 5) : '';
        if ($s && $e) {
            return "$s - $e Uhr";
        } elseif ($s) {
            return "ab $s Uhr";
        }
        return '-';
    }

    // Funktion zum Erstellen einer Tabelle ($zeilenKlasse z. B. 'bold' für die Wettschiessen)
    function createTerminTable($data, $title, $leerText = 'Keine Termine gefunden.', $zeilenKlasse = '') {
        if (empty($data)) {
            return '<div class="block">
                        <h2 class="rl-abschnitt">' . htmlspecialchars($title) . '</h2>
                        <p class="muted">' . htmlspecialchars($leerText) . '</p>
                    </div>';
        }

        $html = '<div class="block">
                    <h2 class="rl-abschnitt">' . htmlspecialchars($title) . '</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="datum">Datum</th>
                                <th class="tag">Tag</th>
                                <th class="zeit">Zeit</th>
                                <th class="bezeichnung">Bezeichnung</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($data as $t) {
            $html .= '<tr' . ($zeilenKlasse !== '' ? ' class="' . $zeilenKlasse . '"' : '') . '>
                        <td class="datum">' . formatDatum($t['Datum']) . '</td>
                        <td class="tag">' . formatWochentag($t['Wochentag']) . '</td>
                        <td class="zeit">' . formatZeit($t['StartZeit'], $t['EndZeit']) . '</td>
                        <td class="bezeichnung">' . htmlspecialchars($t['Bezeichnung']) . '</td>
                    </tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    // Ergänzungen zum Ranglisten-Stil (Logo links, Titel, Fusszeile mit Seitenzahl nach dem Rendern)
    $jskOverrides = '
        .table td:last-child { text-align: left; }
        .block { margin-bottom: 4mm; page-break-inside: avoid; }
        .datum { width: 24mm; } .tag { width: 24mm; } .zeit { width: 34mm; } .bezeichnung { width: auto; }
    ';

    // HTML generieren
    $titel = 'Jungschützenkurs ' . $year;
    $html = pdf_rangliste_anfang($titel, 'Gewehr 300 m · Termine', $jskOverrides) . '<div class="container">';

    // Einschreiben, Kurstage, Wettschiessen (Wettschiessen fett hervorgehoben)
    $html .= createTerminTable($einschreiben, 'Einschreiben');
    $html .= createTerminTable($kursTage, 'Kurstage');
    $html .= createTerminTable($wettschiessen, 'Wettschiessen', 'Keine Wettschiessen-Termine gefunden.', 'bold');

    $html .= '</div></body></html>';

    // PDF generieren
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    pdf_seitenfuss($dompdf, 'JSK-Termine ' . $year);

    // Ausgabepuffer leeren
    ob_end_clean();

    // PDF direkt zum Download ausgeben
    $filename = 'JSK_Termine_' . $year . '.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);

} catch (Exception $e) {
    ob_end_clean();
    error_log("JSK PDF-Generator Fehler: " . $e->getMessage());
    
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Fehler beim Erstellen des PDFs</h1>';
    echo '<p>' . htmlspecialchars(msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e)) . '</p>';
    echo '<p><a href="javascript:history.back()">Zurück</a></p>';
}
?>
