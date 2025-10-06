<?php
/**
 * Beispiel mit echten CSV-Daten - nur Wettkampfschüsse
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'ZielscheibeGeneratorImagick.php';

// CSV-Daten mit 2 Schwini-Stichen (unterschiedliche Passen)
$csvDaten = "
522;Endstich;01.10.2025-17:47:52;Linie: 7;Total: 92
Nr;Wettkampfschuss;Passe;Wertung;100er Wertung;EF/SF;Lage;X;Y
1;0;0;7;69;1;2;99.7;124.4
2;0;0;10;98;1;0;14.47;5.08
3;0;0;9;81;1;3;100.24;-24.89
4;0;0;8;80;1;2;74.14;76.55
5;0;0;10;91;1;5;14.04;-48.59
6;0;0;9;81;1;5;9.91;-103.16
7;0;0;10;91;1;4;44.05;-27.05
8;0;0;9;88;1;1;-15.96;64.26
9;0;0;9;90;1;8;-23.78;51.97
1;1;1;10;93;1;8;-24.71;34.44
2;1;1;9;90;1;2;28.9;46.77
3;1;1;10;93;1;5;-3.53;-42.4
4;1;1;9;86;1;6;-55.69;-54.43
5;1;1;9;89;1;3;57.93;-24.11
6;1;1;10;94;1;2;15.75;31.65
7;1;1;9;87;1;8;-61.2;32.65
8;1;1;10;91;1;7;-47.13;-18.38
9;1;1;9;82;1;7;-94.77;4.88
10;1;1;7;69;1;8;-89.7;132.38

525;Zabigstich;01.10.2025-18:13:24;Linie: 8;Total: 537
Nr;Wettkampfschuss;Passe;Wertung;100er Wertung;EF/SF;Lage;X;Y
1;1;0;87;87;1;4;39.18;-60.81
2;1;0;75;75;1;7;-130.4;-18.77
3;1;0;87;87;1;8;-65.76;29.2
4;1;0;90;90;1;8;-39.25;38.35
5;1;0;99;99;1;0;-9.27;-8.72
6;1;0;99;99;1;0;8.04;4.77

526;Schwinistich;01.10.2025-18:54:01;Linie: 2;Total: 56
Nr;Wettkampfschuss;Passe;Wertung;100er Wertung;EF/SF;Lage;X;Y
1;1;1;10;100;1;0;-6.56;-1.79
2;1;1;10;95;1;7;-33.38;2.66
3;1;1;9;88;1;2;51.09;43.47
4;1;1;10;92;1;6;-34.39;-33.9
5;1;1;8;79;1;8;-49.22;99.94
6;1;1;9;91;1;2;42.75;25.35

526;Schwinistich;01.10.2025-19:15:30;Linie: 2;Total: 54
Nr;Wettkampfschuss;Passe;Wertung;100er Wertung;EF/SF;Lage;X;Y
1;1;2;9;89;1;1;-15.23;45.67
2;1;2;10;97;1;5;-25.11;8.34
3;1;2;9;85;1;3;62.45;38.90
4;1;2;9;88;1;7;-42.78;-28.56
5;1;2;8;76;1;8;-58.90;105.23
6;1;2;9;90;1;4;38.67;20.11
";

// CSV parsen
$zeilen = explode("\n", $csvDaten);

// Array für alle Stiche
$alleStiche = [];
$aktuellerStich = null;

foreach ($zeilen as $zeile) {
    $zeile = trim($zeile);
    if (empty($zeile)) continue;
    
    // Prüfe ob es eine Header-Zeile ist (Programmnummer;Name;Datum;...)
    // Format: 522;Endstich;01.10.2025-17:47:52;Linie: 7;Total: 92
    // Erkennbar am Datum-Pattern im 3. Feld
    $teile = explode(';', $zeile);
    if (count($teile) >= 3 && preg_match('/\d{2}\.\d{2}\.\d{4}/', $teile[2])) {
        // Neuer Stich beginnt
        if ($aktuellerStich !== null) {
            $alleStiche[] = $aktuellerStich;
        }
        
        // Header parsen: 522;Endstich;01.10.2025-17:47:52;Linie: 7;Total: 92
        $aktuellerStich = [
            'programmNummer' => trim($teile[0]),
            'stichName' => trim($teile[1]),
            'schuesse' => [],
            'schussNummer' => 1,
            'passe' => null // Wird aus den Schuss-Daten ermittelt
        ];
        continue;
    }
    
    // Überschriften-Zeile überspringen
    if (strpos($zeile, 'Nr;Wettkampfschuss') === 0) {
        continue;
    }
    
    // Schuss-Daten parsen
    if ($aktuellerStich !== null) {
        $spalten = explode(";", $zeile);
        
        // Prüfe ob es genug Spalten gibt
        if (count($spalten) < 9) continue;
        
        $wettkampfschuss = isset($spalten[1]) ? (int) trim($spalten[1]) : 0;
        
        // Nur Wettkampfschüsse (Wettkampfschuss = 1)
        if ($wettkampfschuss === 1) {
            $passe = isset($spalten[2]) ? (int) trim($spalten[2]) : 0;
            $wertung = isset($spalten[3]) ? (int) trim($spalten[3]) : 0;
            $hunderter = isset($spalten[4]) ? (int) trim($spalten[4]) : 0;
            
            // Passe-Nummer speichern (vom ersten Wettkampfschuss)
            if ($aktuellerStich['passe'] === null) {
                $aktuellerStich['passe'] = $passe;
            }
            
            // WICHTIG: Wenn Wertung > 10, dann ist es bereits ein 100er-Wert
            // und muss umgerechnet werden
            if ($wertung > 10) {
                // Wertung ist bereits 100er-Wert, umrechnen:
                // 100-91 = 10, 90-81 = 9, 80-71 = 8, etc.
                $hunderter = $wertung; // Original-Wert als 100er speichern
                if ($wertung >= 91) $wertung = 10;
                else if ($wertung >= 81) $wertung = 9;
                else if ($wertung >= 71) $wertung = 8;
                else if ($wertung >= 61) $wertung = 7;
                else if ($wertung >= 51) $wertung = 6;
                else if ($wertung >= 41) $wertung = 5;
                else if ($wertung >= 31) $wertung = 4;
                else if ($wertung >= 21) $wertung = 3;
                else if ($wertung >= 11) $wertung = 2;
                else $wertung = 1;
            }
            
            $aktuellerStich['schuesse'][] = [
                'schuss_nr' => $aktuellerStich['schussNummer']++,
                'wert' => $wertung,
                'hunderter' => $hunderter,
                'x' => isset($spalten[7]) ? (float) trim($spalten[7]) : 0.0,
                'y' => isset($spalten[8]) ? (float) trim($spalten[8]) : 0.0,
            ];
        }
    }
}

// Letzten Stich hinzufügen
if ($aktuellerStich !== null) {
    $alleStiche[] = $aktuellerStich;
}

// Für Kompatibilität: Ersten Stich als Standard verwenden
$programmNummer = !empty($alleStiche) ? $alleStiche[0]['programmNummer'] : null;
$stichName = !empty($alleStiche) ? $alleStiche[0]['stichName'] : null;
$schuesse = !empty($alleStiche) ? $alleStiche[0]['schuesse'] : [];

// DEBUG: Zeige was geparst wurde
echo "<pre>DEBUG: Anzahl Stiche gefunden: " . count($alleStiche) . "\n";
foreach ($alleStiche as $idx => $stich) {
    $passeTxt = $stich['passe'] ? " - " . $stich['passe'] . ". Passe" : "";
    echo "Stich " . ($idx+1) . ": " . $stich['stichName'] . $passeTxt . " (Programm " . $stich['programmNummer'] . ") - " . count($stich['schuesse']) . " Wettkampfschüsse\n";
}
echo "Verwende Stich 1 für Anzeige: " . $stichName . " mit " . count($schuesse) . " Schüssen</pre>\n";

try {
    // Als Datei speichern für Anzeige
    $tempDatei = sys_get_temp_dir() . '/wettkampf_zielscheibe_' . time() . '.png';

    $generator = new ZielscheibeGeneratorImagick(1200, 1200);
    
    // WICHTIG: Koordinatenfaktor setzen!
    // Die Werte scheinen bereits in Millimetern zu sein, also Faktor 1.0
    $generator->setzeKoordinatenFaktor(1.1);
    
    $erfolg = $generator->generiereZielscheibe($schuesse, $tempDatei);

    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>🎯 Wettkampf-Zielscheibe</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 1400px;
                margin: 20px auto;
                padding: 20px;
                background: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .status {
                padding: 15px;
                margin: 20px 0;
                border-radius: 8px;
                font-size: 16px;
            }
            .success {
                background: #d4edda;
                color: #155724;
                border: 2px solid #c3e6cb;
            }
            .info {
                background: #d1ecf1;
                color: #0c5460;
                border: 2px solid #bee5eb;
                margin: 20px 0;
                padding: 15px;
                border-radius: 8px;
            }
            img {
                border: 3px solid #333;
                margin: 20px 0;
                max-width: 100%;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                border-radius: 5px;
            }
            h1 {
                color: #333;
                border-bottom: 3px solid #007bff;
                padding-bottom: 10px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            th, td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            th {
                background: #007bff;
                color: white;
            }
            tr:hover {
                background: #f5f5f5;
            }
            .wert-10 { color: #FF0000; font-weight: bold; }
            .wert-9 { color: #0000FF; font-weight: bold; }
            .wert-8 { color: #FF6400; font-weight: bold; }
            .wert-7 { color: #FF6400; }
            .btn-pdf {
                background-color: #dc3545;
                color: white;
                padding: 12px 24px;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                display: inline-block;
                margin: 20px 0;
                text-decoration: none;
                transition: background-color 0.3s;
            }
            .btn-pdf:hover {
                background-color: #c82333;
            }
            .btn-pdf:disabled {
                background-color: #6c757d;
                cursor: not-allowed;
            }
            #pdf-status {
                display: none;
                margin: 10px 0;
                padding: 10px;
                border-radius: 5px;
            }
            #pdf-status.loading {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }
            #pdf-status.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            #pdf-status.error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🎯 Wettkampf-Zielscheibe</h1>
            
            <?php if ($stichName): ?>
            <div class="info">
                <strong>🎯 Stich:</strong> <?php echo htmlspecialchars($stichName); ?> (Programm-Nr: <?php echo htmlspecialchars($programmNummer); ?>)
            </div>
            <?php endif; ?>
            
            <div class="info">
                <strong>ℹ️ Daten-Info:</strong><br>
                Nur Wettkampfschüsse (Wettkampfschuss = 1) werden angezeigt.<br>
                Total <?php echo count($alleStiche); ?> Stiche mit Wettkampfschüssen gefunden.
            </div>
            
            <?php if ($erfolg && file_exists($tempDatei)): ?>
                <div class="status success">
                    ✅ <strong>Erfolg!</strong> Die Zielscheibe wurde generiert.
                </div>
                
                <!-- PDF Download Button -->
                <button id="btn-generate-pdf" class="btn-pdf" onclick="generatePDF()">
                    📄 Alle Stiche als PDF herunterladen
                </button>
                <div id="pdf-status"></div>
                
                <h2>Wettkampfschüsse (Erster Stich):</h2>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Wertung</th>
                            <th>100er</th>
                            <th>X</th>
                            <th>Y</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schuesse as $schuss): ?>
                        <tr>
                            <td><?php echo $schuss['schuss_nr']; ?></td>
                            <td class="wert-<?php echo $schuss['wert']; ?>">
                                <?php echo $schuss['wert']; ?> Punkte
                            </td>
                            <td><?php echo $schuss['hunderter']; ?></td>
                            <td><?php echo number_format($schuss['x'], 2); ?> mm</td>
                            <td><?php echo number_format($schuss['y'], 2); ?> mm</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <h2>Generierte Zielscheibe (Erster Stich):</h2>
                <img src="data:image/png;base64,<?php echo base64_encode(file_get_contents($tempDatei)); ?>" 
                     alt="Wettkampf-Zielscheibe">
                
                <?php
                // Statistik berechnen
                $total = 0;
                $werteArray = [];
                foreach ($schuesse as $schuss) {
                    $total += $schuss['wert'];
                    $werteArray[] = $schuss['wert'];
                }
                $durchschnitt = count($schuesse) > 0 ? round($total / count($schuesse), 2) : 0;
                $max = !empty($werteArray) ? max($werteArray) : 0;
                $min = !empty($werteArray) ? min($werteArray) : 0;
                ?>
                
                <div class="info">
                    <strong>📊 Zusammenfassung (Erster Stich):</strong><br>
                    Anzahl Schüsse: <?php echo count($schuesse); ?><br>
                    Total: <?php echo $total; ?> Punkte<br>
                    Durchschnitt: <?php echo $durchschnitt; ?> Punkte<br>
                    Bester Schuss: <?php echo $max; ?> Punkte<br>
                    Schlechtester Schuss: <?php echo $min; ?> Punkte
                </div>
                
                <script>
                // ALLE Stiche für PDF-Generierung
                const alleStiche = <?php echo json_encode($alleStiche); ?>;
                
                function generatePDF() {
                    const btn = document.getElementById('btn-generate-pdf');
                    const status = document.getElementById('pdf-status');
                    
                    // Button deaktivieren
                    btn.disabled = true;
                    btn.textContent = '⏳ PDF wird erstellt...';
                    
                    // Status anzeigen
                    status.className = 'loading';
                    status.style.display = 'block';
                    status.textContent = 'PDF wird generiert, bitte warten...';
                    
                    // AJAX Request
                    fetch('generate_pdf_zielscheibe.php?year=<?php echo date('Y'); ?>&name=Wettkampf', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            alleStiche: alleStiche
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.pdf_link) {
                            status.className = 'success';
                            status.innerHTML = '✅ PDF erfolgreich erstellt! <a href="' + data.pdf_link + '" target="_blank" style="color: #155724; font-weight: bold; text-decoration: underline;">Jetzt herunterladen</a>';
                            
                            // Automatisch öffnen
                            window.open(data.pdf_link, '_blank');
                        } else if (data.error) {
                            status.className = 'error';
                            status.textContent = '❌ Fehler: ' + data.error;
                        }
                        
                        // Button wieder aktivieren
                        btn.disabled = false;
                        btn.textContent = '📄 Alle Stiche als PDF herunterladen';
                    })
                    .catch(error => {
                        status.className = 'error';
                        status.textContent = '❌ Fehler bei der PDF-Generierung: ' + error.message;
                        
                        // Button wieder aktivieren
                        btn.disabled = false;
                        btn.textContent = '📄 Alle Stiche als PDF herunterladen';
                    });
                }
                </script>
                
                <?php
                // Temporäre Datei löschen
                unlink($tempDatei);
                ?>
                
            <?php else: ?>
                <div class="status error">
                    ❌ <strong>Fehler!</strong> Die Zielscheibe konnte nicht generiert werden.
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Fehler</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; border: 2px solid #f5c6cb; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>❌ Fehler!</h2>
            <p><strong>Fehlermeldung:</strong> <?php echo htmlspecialchars($e->getMessage()); ?></p>
            <p><strong>Zeile:</strong> <?php echo $e->getLine(); ?></p>
        </div>
    </body>
    </html>
    <?php
}
?>
