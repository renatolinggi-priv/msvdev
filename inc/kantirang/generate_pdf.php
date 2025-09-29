<?php
// generate_pdf.php für Kantonalstich
use Dompdf\Dompdf;
use Dompdf\Options;

// Ausgabepufferung starten
ob_start();

// Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require '../dompdf/autoload.php';
    include '../config.php';

    // Verbindung prüfen
    if ($conn->connect_error) {
        throw new Exception("Verbindung fehlgeschlagen: " . $conn->connect_error);
    }

    // Parameter validieren
    $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $currentYear = date('Y');
    if ($selectedYear < 2000 || $selectedYear > $currentYear + 1) {
        $selectedYear = $currentYear;
    }

    // Funktion zum Erstellen einer Tabelle
    function createTable($data, $title) {
        if (empty($data)) {
            return '<div class="container"><h2>' . htmlspecialchars($title) . '</h2><p>Keine Ergebnisse gefunden.</p></div>';
        }
        
        $html = '<div class="container">
                    <h2>' . htmlspecialchars($title) . '</h2>
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="rang">Rang</th>
                                <th class="name">Name</th>';
        
        for ($i = 1; $i <= 5; $i++) {
            $html .= '<th class="passe">Passe ' . $i . '</th>';
        }
        
        $html .= '<th class="total">Total</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        $rang = 1;
        $previousTotal = null;
        $sameRankCount = 0;
        
        foreach ($data as $row) {
            // Rangbehandlung bei gleichen Totals
            if ($previousTotal !== null && $row['KantiSumme'] < $previousTotal) {
                $rang += $sameRankCount;
                $sameRankCount = 1;
            } elseif ($previousTotal === $row['KantiSumme']) {
                $sameRankCount++;
            } else {
                $sameRankCount = 1;
            }
            
            $html .= '<tr>
                        <td class="rang">' . $rang . '.</td>
                        <td class="name">' . htmlspecialchars($row['Name'] . ' ' . $row['Vorname']) . '</td>';
            
            for ($i = 1; $i <= 5; $i++) {
                $value = $row["Passe$i"];
                $html .= '<td class="passe">' . ($value > 0 ? $value : '-') . '</td>';
            }
            
            $html .= '<td class="total">' . $row['KantiSumme'] . '</td>
                    </tr>';
            
            $previousTotal = $row['KantiSumme'];
        }
        
        $html .= '</tbody></table></div>';
        return $html;
    }

    // Alle Daten in einer Abfrage holen
    $sql = "SELECT 
        w.Kategorie,
        m.Name, 
        m.Vorname, 
        k.Passe1, k.Passe2, k.Passe3, k.Passe4, k.Passe5,
        (COALESCE(k.Passe1, 0) + COALESCE(k.Passe2, 0) + COALESCE(k.Passe3, 0) + 
         COALESCE(k.Passe4, 0) + COALESCE(k.Passe5, 0)) AS KantiSumme
    FROM kantiresultate k
    INNER JOIN mitglieder m ON m.ID = k.MitgliedID
    INNER JOIN Waffen w ON w.ID = m.WaffenID 
    WHERE w.Kategorie IN ('Kat. A', 'Kat. B')
      AND k.Jahr = ?
      AND (k.Passe1 > 0 OR k.Passe2 > 0 OR k.Passe3 > 0 OR k.Passe4 > 0 OR k.Passe5 > 0)
    ORDER BY w.Kategorie, KantiSumme DESC, m.Name, m.Vorname";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Fehler beim Vorbereiten der Abfrage: " . $conn->error);
    }
    
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Daten nach Kategorie gruppieren
    $kategorienDaten = [
        'Kat. A' => [],
        'Kat. B' => []
    ];
    
    while ($row = $result->fetch_assoc()) {
        $kategorienDaten[$row['Kategorie']][] = $row;
    }
    
    $stmt->close();
    $conn->close();

    // HTML generieren
    $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 1.5cm; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }
        .header {
            position: relative;
            margin-bottom: 50px;
            min-height: 100px;
        }
        .logo {
            position: absolute;
            top: 0;
            left: 0;
            width: 100px;
            height: auto;
        }
        h1 {
            text-align: center;
            font-size: 20px;
            margin: 0;
            padding-top: 20px;
        }
        .container {
            margin-bottom: 30px;
            page-break-inside: avoid;
            clear: both;
        }
        h2 {
            font-size: 16px;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table th, .table td {
            border: 1px solid #000;
            padding: 4px;
            text-align: left;
        }
        .table th {
            background-color: #343a40;
            color: #fff;
            font-weight: bold;
        }
        .rang, .passe, .total {
            text-align: center;
        }
        .rang { width: 50px; }
        .name { width: auto; }
        .passe { width: 65px; }
        .total { 
            width: 70px; 
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            text-align: center;
            font-size: 9px;
            color: #666;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
        }
    </style>
    <title>Kantonalstich ' . $selectedYear . '</title>
</head>
<body>
    <div class="header">
        <img src="https://jahresmeisterschaft.msvwilen.ch/images/MSVWilen_Logo.jpg" class="logo" alt="MSV Wilen Logo">
        <h1>Kantonalstich ' . $selectedYear . '</h1>
    </div>';

    // Beide Kategorien hinzufügen
    $html .= createTable($kategorienDaten['Kat. A'], 'Kategorie A');
    $html .= createTable($kategorienDaten['Kat. B'], 'Kategorie B');
    
    // Footer
    $html .= '<div class="footer">
                <p>Generiert am ' . date('d.m.Y \u\m H:i') . ' Uhr</p>
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

    // Verzeichnis prüfen
    if (!is_dir('dat')) {
        if (!mkdir('dat', 0755, true)) {
            throw new Exception("Konnte Verzeichnis nicht erstellen");
        }
    }

    // PDF speichern
    $date = new DateTime();
    $pdfFilePath = 'dat/RanglisteKantonalstich_' . $date->format('Y-m-d_H-i-s') . '.pdf';
    
    if (!file_put_contents($pdfFilePath, $dompdf->output())) {
        throw new Exception("Konnte PDF nicht speichern");
    }

    // Ausgabepuffer leeren
    ob_end_clean();

    // JSON-Antwort zurückgeben
    header('Content-Type: application/json');
    echo json_encode(array('pdf_link' => "kantirang/" . $pdfFilePath));

} catch (Exception $e) {
    ob_end_clean();
    error_log("PDF-Generator Fehler: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(array('pdf_link' => null));
}
?>