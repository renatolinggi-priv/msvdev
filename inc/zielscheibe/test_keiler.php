<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>🐗 Keiler-Zielscheibe Test</title>
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
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
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
        .wert-10 { color: #FF0000; font-weight: bold; }
        .wert-9 { color: #0000FF; font-weight: bold; }
        .wert-8 { color: #FF6400; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐗 Keiler-Zielscheibe (Linksläufig)</h1>
        
        <div class="info">
            <strong>ℹ️ Test mit Schwini-Stich Daten</strong><br>
            Programm-Nr: 526 (Schwinistich)<br>
            6 Wettkampfschüsse
        </div>
        
        <?php
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        require_once 'ZielscheibeGeneratorKeiler.php';
        
        // Beispieldaten
        $schuesse = [
            ['schuss_nr' => 1, 'wert' => 10, 'hunderter' => 100, 'x' => -6.56, 'y' => -1.79],
            ['schuss_nr' => 2, 'wert' => 10, 'hunderter' => 95, 'x' => -33.38, 'y' => 2.66],
            ['schuss_nr' => 3, 'wert' => 9, 'hunderter' => 88, 'x' => 51.09, 'y' => 43.47],
            ['schuss_nr' => 4, 'wert' => 10, 'hunderter' => 92, 'x' => -34.39, 'y' => -33.9],
            ['schuss_nr' => 5, 'wert' => 8, 'hunderter' => 79, 'x' => -49.22, 'y' => 99.94],
            ['schuss_nr' => 6, 'wert' => 9, 'hunderter' => 91, 'x' => 42.75, 'y' => 25.35],
        ];
        
        try {
            // Pfad zum Keiler-Bild - ANPASSEN!
            $keilerBildPfad = __DIR__ . '/keiler_scheibe.jpg';
            
            // Wenn nicht gefunden, versuche alternatives Verzeichnis
            if (!file_exists($keilerBildPfad)) {
                $keilerBildPfad = __DIR__ . '/../assets/keiler_scheibe.jpg';
            }
            
            if (!file_exists($keilerBildPfad)) {
                throw new Exception("Keiler-Bild nicht gefunden! Bitte speichere das Bild als 'keiler_scheibe.jpg' in diesem Verzeichnis.");
            }
            
            $tempDatei = sys_get_temp_dir() . '/keiler_zielscheibe_test.png';
            
            $generator = new ZielscheibeGeneratorKeiler(1200, 1200);
            
            // Optional: Zentrum-Offset anpassen
            // Basierend auf deinen Daten: negative X-Werte sind im 10er
            // Das bedeutet Zentrum ist nach LINKS verschoben
            // AUSKOMMENTIERT ZUM TESTEN - verwendet jetzt die Werte aus der Klasse!
            // $generator->setzeZentrumOffset(-239, 44);
            
            $erfolg = $generator->generiereZielscheibe($schuesse, $tempDatei, $keilerBildPfad);
            
            if ($erfolg && file_exists($tempDatei)) {
                echo '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 0;">';
                echo '✅ <strong>Erfolg!</strong> Die Keiler-Zielscheibe wurde generiert.';
                echo '</div>';
                
                echo '<h2>Wettkampfschüsse:</h2>';
                echo '<table><thead><tr>';
                echo '<th>#</th><th>Wertung</th><th>100er</th><th>X (mm)</th><th>Y (mm)</th>';
                echo '</tr></thead><tbody>';
                
                foreach ($schuesse as $schuss) {
                    echo '<tr>';
                    echo '<td>' . $schuss['schuss_nr'] . '</td>';
                    echo '<td class="wert-' . $schuss['wert'] . '">' . $schuss['wert'] . ' Punkte</td>';
                    echo '<td>' . $schuss['hunderter'] . '</td>';
                    echo '<td>' . number_format($schuss['x'], 2) . '</td>';
                    echo '<td>' . number_format($schuss['y'], 2) . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
                
                echo '<h2>Generierte Keiler-Zielscheibe:</h2>';
                echo '<img src="data:image/png;base64,' . base64_encode(file_get_contents($tempDatei)) . '" alt="Keiler-Zielscheibe">';
                
                // Statistik
                $total = array_sum(array_column($schuesse, 'wert'));
                $durchschnitt = round($total / count($schuesse), 2);
                
                echo '<div class="info">';
                echo '<strong>📊 Zusammenfassung:</strong><br>';
                echo 'Anzahl Schüsse: ' . count($schuesse) . '<br>';
                echo 'Total: ' . $total . ' Punkte<br>';
                echo 'Durchschnitt: ' . $durchschnitt . ' Punkte';
                echo '</div>';
                
                echo '<div class="info">';
                echo '<strong>🎯 Zentrum-Einstellungen:</strong><br>';
                echo 'Offset X: -239mm (nach links)<br>';
                echo 'Offset Y: +44mm (nach oben)<br>';
                echo '<em>Diese Werte kannst du in ZielscheibeGeneratorKeiler.php anpassen!</em>';
                echo '</div>';
                
                unlink($tempDatei);
                
            } else {
                echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px;">';
                echo '❌ <strong>Fehler!</strong> Die Keiler-Zielscheibe konnte nicht generiert werden.';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px;">';
            echo '<h2>❌ Fehler!</h2>';
            echo '<p><strong>Fehlermeldung:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>
