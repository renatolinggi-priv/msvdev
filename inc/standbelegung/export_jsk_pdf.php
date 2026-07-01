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

    // JSK-Termine aus Datenbank laden
    $sql = "SELECT Datum, Wochentag, Bezeichnung, StartZeit, EndZeit 
            FROM Standbelegung 
            WHERE Jahr = ? 
            AND (
                Bezeichnung LIKE '%Einschreiben JS-Kurs%' 
                OR Bezeichnung LIKE '%Jungschützenkurs Gewehr%' 
                OR Bezeichnung LIKE '%JSK Wettschiessen%'
                OR Bezeichnung LIKE '%Jungschützenwettschiessen%'
                OR Bezeichnung LIKE '%JSK Gewehr%'
            )
            ORDER BY Datum ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Fehler beim Vorbereiten der Abfrage: " . $conn->error);
    }
    
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $termine = [];
    while ($row = $result->fetch_assoc()) {
        $termine[] = $row;
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

    // Funktion zum Erstellen einer Tabelle
    function createTerminTable($data, $title, $icon = '') {
        if (empty($data)) {
            return '<div class="container">
                        <h2>' . $icon . ' ' . htmlspecialchars($title) . '</h2>
                        <p class="no-data">Keine Termine gefunden.</p>
                    </div>';
        }
        
        $html = '<div class="container">
                    <h2>' . $icon . ' ' . htmlspecialchars($title) . '</h2>
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
            $html .= '<tr>
                        <td class="datum">' . formatDatum($t['Datum']) . '</td>
                        <td class="tag">' . formatWochentag($t['Wochentag']) . '</td>
                        <td class="zeit">' . formatZeit($t['StartZeit'], $t['EndZeit']) . '</td>
                        <td class="bezeichnung">' . htmlspecialchars($t['Bezeichnung']) . '</td>
                    </tr>';
        }
        
        $html .= '</tbody></table></div>';
        return $html;
    }

    // Zentrales Logo eingebettet (fixt veralteten Remote-Domain-Link)
    $logoSrc = pdf_logo_src();

    // JSK-spezifische Layout-Overrides (Farben/Typo kommen aus dem Theme)
    $jskOverrides = '
        @page { margin: 1.5cm 1.5cm 0.8cm 1.5cm; }
        /* Generische Tabelle: Rang-/Total-Spalten-Styling des Themes neutralisieren */
        .table td:first-child, .table td:last-child { text-align: left; font-weight: normal; background-color: transparent; color: inherit; }
        /* Kopf: absolut positioniertes Logo + zentrierter Titel */
        .header { position: relative; margin-bottom: 40px; min-height: 100px; display: block; }
        .logo { position: absolute; top: 0; left: 0; width: 100px; height: auto; margin: 0; }
        h1 { text-align: center; font-size: 20px; margin: 0; padding-top: 20px; }
        .subtitle { text-align: center; font-size: 14px; margin-top: 5px; }
        .container { margin-bottom: 25px; page-break-inside: avoid; clear: both; }
        h2 { font-size: 14px; margin-bottom: 10px; padding-bottom: 5px; }
        .datum { width: 90px; } .tag { width: 80px; } .zeit { width: 120px; } .bezeichnung { width: auto; }
        .no-data { font-style: italic; padding: 10px; }
        .highlight td { background-color: #fff3cd !important; }
    ';

    // HTML generieren
    $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>' . pdf_theme_css() . $jskOverrides . '</style>
    <title>JSK-Termine ' . $year . '</title>
</head>
<body>
    <div class="header">
        <img src="' . $logoSrc . '" class="logo" alt="MSV Wilen Logo">
        <h1>Jungschützenkurs Gewehr 300m</h1>
        <p class="subtitle">Termine ' . $year . '</p>
    </div>
';

    // Einschreiben
    $html .= createTerminTable($einschreiben, 'Einschreiben', '');
    
    // Kurstage
    $html .= createTerminTable($kursTage, 'Kurstage', '');
    
    // Wettschiessen (hervorgehoben)
    if (!empty($wettschiessen)) {
        $html .= '<div class="container">
                    <h2>Wettschiessen</h2>
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
        
        foreach ($wettschiessen as $t) {
            $html .= '<tr class="highlight">
                        <td class="datum">' . formatDatum($t['Datum']) . '</td>
                        <td class="tag">' . formatWochentag($t['Wochentag']) . '</td>
                        <td class="zeit">' . formatZeit($t['StartZeit'], $t['EndZeit']) . '</td>
                        <td class="bezeichnung">' . htmlspecialchars($t['Bezeichnung']) . '</td>
                    </tr>';
        }
        
        $html .= '</tbody></table></div>';
    } else {
        $html .= '<div class="container">
                    <h2>Wettschiessen</h2>
                    <p class="no-data">Keine Wettschiessen-Termine gefunden.</p>
                  </div>';
    }
    
    // Footer
    $html .= '<div class="footer">
                <p>MSV Wilen | ' . date('d.m.Y \u\m H:i') . ' Uhr</p>
              </div>
</body>
</html>';

    // PDF generieren
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

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
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="javascript:history.back()">Zurück</a></p>';
}
?>
