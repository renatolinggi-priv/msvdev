<?php
// generate_pdf_js.php - PDF Generator für JS-Endschiessen

session_start();

// Aktiviere Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    include '../dbconnect.inc.php';
} catch (Exception $e) {
    die(json_encode(['error' => 'Datenbankverbindung fehlgeschlagen']));
}

// Hole Parameter
$action = $_GET['action'] ?? '';
$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : date('Y');

if ($action !== 'generate_pdf') {
    die(json_encode(['error' => 'Ungültige Aktion']));
}

try {
    // Hole JS-Paketpreis aus Spezialpreise-Tabelle
    $js_paket_preis = 7500; // Default CHF 75.00 in cents
    $munition_pro_schuss = 50; // Default CHF 0.50 in cents (gemäss DB)
    
    $sql_preis = "SELECT price_cents FROM endstich_spezialpreise WHERE typ = 'js_paket_preis' LIMIT 1";
    $result_preis = $conn->query($sql_preis);
    if ($result_preis && $row_preis = $result_preis->fetch_assoc()) {
        $js_paket_preis = intval($row_preis['price_cents']);
    }
    
    // Hole auch den Munitionspreis
    $sql_munition = "SELECT price_cents FROM endstich_spezialpreise WHERE typ = 'munition_pro_schuss' LIMIT 1";
    $result_munition = $conn->query($sql_munition);
    if ($result_munition && $row_munition = $result_munition->fetch_assoc()) {
        $munition_pro_schuss = intval($row_munition['price_cents']);
    }
    
    // Hole JS-Daten - NUR Gäste MIT Geburtsdatum (echte Jungschützen)
    $sql = "SELECT 
            g.name,
            g.geburtsdatum,
            SUBSTRING_INDEX(g.name, ' ', 1) as vorname,
            SUBSTRING_INDEX(g.name, ' ', -1) as nachname,
            (SELECT zahlungsmethode FROM endstich_selection WHERE gast_id = g.id AND jahr = ? LIMIT 1) as zahlungsmethode
        FROM endstich_gaeste g
        WHERE g.jahr = ?
        AND g.geburtsdatum IS NOT NULL
        AND g.geburtsdatum != ''
        AND g.geburtsdatum != '0000-00-00'
        AND g.id IN (
            SELECT DISTINCT gast_id FROM endstich_selection WHERE jahr = ? AND gast_id IS NOT NULL
        )
        ORDER BY g.name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $jahr, $jahr, $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $js_liste = [];
    $total_sum = 0;
    $total_gp11 = 0;
    $total_gp90 = 0;
    $anzahl_pakete = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Hole Munition
        $stmt2 = $conn->prepare("SELECT typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE gast_id = (SELECT id FROM endstich_gaeste WHERE name = ? AND jahr = ?) AND jahr = ?");
        $stmt2->bind_param("sii", $row['name'], $jahr, $jahr);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        $gp11 = 0;
        $gp90 = 0;
        $munition_preis_cents = 0;
        
        while ($zusatz = $result2->fetch_assoc()) {
            if ($zusatz['typ'] === 'GP11_60' || $zusatz['typ'] === 'GP11_CUSTOM') {
                $gp11 += $zusatz['anzahl'];
            } else if ($zusatz['typ'] === 'GP90_50' || $zusatz['typ'] === 'GP90_CUSTOM') {
                $gp90 += $zusatz['anzahl'];
            }
            $munition_preis_cents += $zusatz['preis_cents'];
        }
        
        $row['gp11'] = $gp11;
        $row['gp90'] = $gp90;
        // Berechne Preis: JS-Paketpreis + Munitionskosten
        $row['total_preis'] = ($js_paket_preis + $munition_preis_cents) / 100;
        
        $js_liste[] = $row;
        $total_sum += $row['total_preis'];
        $total_gp11 += $gp11;
        $total_gp90 += $gp90;
        $anzahl_pakete++;
    }
    
    // Formatiere Preise für Anzeige
    $js_paket_preis_chf = number_format($js_paket_preis / 100, 2, '.', '');
    $munition_pro_schuss_chf = number_format($munition_pro_schuss / 100, 2, '.', '');
    
    // HTML für PDF generieren
    $html = '
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>JS-Endschiessen ' . $jahr . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.4;
            margin: 20px;
        }
        h1 {
            font-size: 18pt;
            margin-bottom: 10px;
            color: #333;
        }
        .header-info {
            margin-bottom: 20px;
            padding: 10px;
            background: #f5f5f5;
            border: 1px solid #ddd;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background: #333;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 11pt;
            border: 1px solid #333;
        }
        td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 10pt;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary {
            margin-top: 20px;
            padding: 15px;
            background: #f0f0f0;
            border: 2px solid #333;
        }
        .summary h3 {
            margin-top: 0;
        }
        .print-date {
            text-align: right;
            color: #666;
            font-size: 10pt;
            margin-bottom: 10px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="print-date">
        Erstellt am: ' . date('d.m.Y H:i') . ' Uhr
    </div>
    
    <h1>Jungschützen Endschiessen ' . $jahr . '</h1>
    
    <div class="header-info">
        <strong>MSV Jegenstorf</strong><br>
        Festes JS-Paket: Endstich, Schwini Passe 1+2, Zabigstich + 5 Probeschüsse<br>
        Paketpreis: CHF ' . $js_paket_preis_chf . ' | Zusätzliche Munition: CHF ' . $munition_pro_schuss_chf . ' pro Schuss
    </div>';
    
    if (count($js_liste) == 0) {
        $html .= '<p>Keine Jungschützen erfasst für das Jahr ' . $jahr . '</p>';
    } else {
        $html .= '
    <table>
        <thead>
            <tr>
                <th width="5%">Nr.</th>
                <th width="20%">Name</th>
                <th width="20%">Vorname</th>
                <th width="12%" class="text-center">Geburtsdatum</th>
                <th width="8%" class="text-center">Alter</th>
                <th width="8%" class="text-center">GP11</th>
                <th width="8%" class="text-center">GP90</th>
                <th width="9%" class="text-center">Bezahlt</th>
                <th width="10%" class="text-right">CHF</th>
            </tr>
        </thead>
        <tbody>';
        
        $nr = 1;
        foreach ($js_liste as $js) {
            $zahlungsmethode = $js['zahlungsmethode'] === 'karte' ? 'Karte' : 'Bar';
            
            // Berechne Alter aus Geburtsdatum
            $alter = '-';
            $geburtsdatum_format = '-';
            if ($js['geburtsdatum']) {
                $geb = new DateTime($js['geburtsdatum']);
                $heute = new DateTime();
                $diff = $heute->diff($geb);
                $alter = $diff->y;
                $geburtsdatum_format = $geb->format('d.m.Y');
            }
            
            $html .= '
            <tr>
                <td>' . $nr++ . '</td>
                <td>' . htmlspecialchars($js['nachname']) . '</td>
                <td>' . htmlspecialchars($js['vorname']) . '</td>
                <td class="text-center">' . $geburtsdatum_format . '</td>
                <td class="text-center">' . $alter . '</td>
                <td class="text-center">' . ($js['gp11'] ?: '-') . '</td>
                <td class="text-center">' . ($js['gp90'] ?: '-') . '</td>
                <td class="text-center">' . $zahlungsmethode . '</td>
                <td class="text-right"><strong>' . number_format($js['total_preis'], 2, '.', '') . '</strong></td>
            </tr>';
        }
        
        $pakete_total = $anzahl_pakete * ($js_paket_preis / 100);
        
        $html .= '
        </tbody>
    </table>
    
    <div class="summary">
        <h3>Zusammenfassung</h3>
        <table style="width: auto;">
            <tr>
                <td style="padding-right: 30px;"><strong>Anzahl Jungschützen:</strong></td>
                <td>' . $anzahl_pakete . '</td>
            </tr>
            <tr>
                <td><strong>Pakete Total:</strong></td>
                <td>' . $anzahl_pakete . ' x CHF ' . $js_paket_preis_chf . ' = CHF ' . number_format($pakete_total, 2, '.', '') . '</td>
            </tr>
            <tr>
                <td><strong>Munition GP11 Total:</strong></td>
                <td>' . $total_gp11 . ' Schuss</td>
            </tr>
            <tr>
                <td><strong>Munition GP90 Total:</strong></td>
                <td>' . $total_gp90 . ' Schuss</td>
            </tr>
            <tr style="border-top: 2px solid #333; font-size: 14pt;">
                <td style="padding-top: 10px;"><strong>Gesamtbetrag:</strong></td>
                <td style="padding-top: 10px;"><strong>CHF ' . number_format($total_sum, 2, '.', '') . '</strong></td>
            </tr>
        </table>
    </div>';
    }
    
    $html .= '
    <div class="footer">
        MSV Jegenstorf - JS-Endschiessen ' . $jahr . '
    </div>
</body>
</html>';
    
    // Speichere HTML temporär
    $temp_dir = '../temp/';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0777, true);
    }
    
    $filename = 'JS_Endschloesen_' . $jahr . '_' . date('Y-m-d_His') . '.html';
    $filepath = $temp_dir . $filename;
    file_put_contents($filepath, $html);
    
    // Optional: Konvertierung zu PDF mit wkhtmltopdf wenn verfügbar
    $pdf_filename = str_replace('.html', '.pdf', $filename);
    $pdf_filepath = $temp_dir . $pdf_filename;
    
    $wkhtmltopdf_path = '/usr/local/bin/wkhtmltopdf';
    if (!file_exists($wkhtmltopdf_path)) {
        $wkhtmltopdf_path = '/usr/bin/wkhtmltopdf';
    }
    
    if (file_exists($wkhtmltopdf_path)) {
        $cmd = escapeshellcmd($wkhtmltopdf_path) . ' --enable-local-file-access --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm ' . 
               escapeshellarg($filepath) . ' ' . escapeshellarg($pdf_filepath);
        exec($cmd . ' 2>&1', $output, $return_var);
        
        if ($return_var === 0 && file_exists($pdf_filepath)) {
            // PDF erfolgreich erstellt
            echo json_encode([
                'pdf_link' => '/inc/temp/' . $pdf_filename,
                'html_link' => '/inc/temp/' . $filename
            ]);
        } else {
            // Fallback auf HTML
            echo json_encode([
                'pdf_link' => '/inc/temp/' . $filename,
                'html_link' => '/inc/temp/' . $filename,
                'message' => 'PDF-Konvertierung fehlgeschlagen, HTML-Version verfügbar'
            ]);
        }
    } else {
        // Nur HTML verfügbar
        echo json_encode([
            'pdf_link' => '/inc/temp/' . $filename,
            'html_link' => '/inc/temp/' . $filename,
            'message' => 'HTML-Version erstellt'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Fehler beim Generieren: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>