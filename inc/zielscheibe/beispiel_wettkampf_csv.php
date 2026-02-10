<?php
/**
 * Beispiel mit echten CSV-Daten - nur Wettkampfschüsse
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'ZielscheibeGeneratorImagick.php';

// CSV-Daten
$csvDaten = "Nr;Wettkampfschuss;Passe;Wertung;100er Wertung;EF/SF;Lage;X;Y
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
10;1;1;7;69;1;8;-89.7;132.38";

// CSV parsen
$zeilen = explode("\n", $csvDaten);
$header = array_shift($zeilen); // Erste Zeile ist Header

$schuesse = [];
$schussNummer = 1;

foreach ($zeilen as $zeile) {
    if (empty(trim($zeile))) {
        continue;
    }
    
    $spalten = explode(";", $zeile);
    
    // Spalten: Nr, Wettkampfschuss, Passe, Wertung, 100er Wertung, EF/SF, Lage, X, Y
    // Wichtig: trim() um Leerzeichen zu entfernen!
    $wettkampfschuss = isset($spalten[1]) ? (int) trim($spalten[1]) : 0;
    
    // Nur Wettkampfschüsse (Wettkampfschuss = 1)
    if ($wettkampfschuss === 1) {
        $schuesse[] = [
            'schuss_nr' => $schussNummer++,
            'wert' => isset($spalten[3]) ? (int) trim($spalten[3]) : 0,      // Wertung
            'hunderter' => isset($spalten[4]) ? (int) trim($spalten[4]) : 0, // 100er Wertung
            'x' => isset($spalten[7]) ? (float) trim($spalten[7]) : 0.0,     // X
            'y' => isset($spalten[8]) ? (float) trim($spalten[8]) : 0.0,     // Y
        ];
    }
}

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
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🎯 Wettkampf-Zielscheibe</h1>
            
            <div class="info">
                <strong>ℹ️ Daten-Info:</strong><br>
                Nur Wettkampfschüsse (Wettkampfschuss = 1) werden angezeigt.<br>
                Total <?php echo count($schuesse); ?> Wettkampfschüsse gefunden.
            </div>
            
            <?php if ($erfolg && file_exists($tempDatei)): ?>
                <div class="status success">
                    ✅ <strong>Erfolg!</strong> Die Zielscheibe wurde generiert.
                </div>
                
                <h2>Wettkampfschüsse:</h2>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Wertung</th>
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
                            <td><?php echo number_format($schuss['x'], 2); ?> mm</td>
                            <td><?php echo number_format($schuss['y'], 2); ?> mm</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <h2>Generierte Zielscheibe:</h2>
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
                    <strong>📊 Zusammenfassung:</strong><br>
                    Anzahl Schüsse: <?php echo count($schuesse); ?><br>
                    Total: <?php echo $total; ?> Punkte<br>
                    Durchschnitt: <?php echo $durchschnitt; ?> Punkte<br>
                    Bester Schuss: <?php echo $max; ?> Punkte<br>
                    Schlechtester Schuss: <?php echo $min; ?> Punkte
                </div>
                
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
