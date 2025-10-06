<?php
/**
 * Zielscheibe Beispiel mit CSV-Daten und Wettkampfschuss-Filterung
 * 
 * Zeigt nur Schüsse an, wo Wettkampfschuss = 1
 */

require_once 'ZielscheibeGenerator.php';

// CSV-Daten (wie von dir bereitgestellt)
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

/**
 * Parst CSV-Daten und filtert nur Wettkampfschüsse
 * 
 * @param string $csvText CSV-Daten als String
 * @param bool $nurWettkampf Nur Wettkampfschüsse (Wettkampfschuss=1)
 * @return array Array mit Schuss-Daten
 */
function parseCSVSchuesse($csvText, $nurWettkampf = true) {
    $zeilen = explode("\n", trim($csvText));
    $schuesse = [];
    
    // Header überspringen
    array_shift($zeilen);
    
    foreach ($zeilen as $zeile) {
        if (empty(trim($zeile))) continue;
        
        $teile = explode(';', $zeile);
        
        if (count($teile) >= 9) {
            $nr = intval($teile[0]);
            $wettkampfschuss = intval($teile[1]);
            $passe = intval($teile[2]);
            $wertung = intval($teile[3]);
            $x = floatval($teile[7]);
            $y = floatval($teile[8]);
            
            // Filter: Nur Wettkampfschüsse?
            if ($nurWettkampf && $wettkampfschuss != 1) {
                continue;
            }
            
            $schuesse[] = [
                'schuss_nr' => $nr,
                'wettkampfschuss' => $wettkampfschuss,
                'passe' => $passe,
                'wert' => $wertung,
                'x' => $x,
                'y' => $y
            ];
        }
    }
    
    return $schuesse;
}

// Modus bestimmen
$modus = isset($_GET['modus']) ? $_GET['modus'] : 'anzeigen';
$alleSchuesse = isset($_GET['alle']) && $_GET['alle'] == '1';

// Daten parsen
$schuesse = parseCSVSchuesse($csvDaten, !$alleSchuesse);

// Statistik berechnen
$anzahl = count($schuesse);
$total = array_sum(array_column($schuesse, 'wert'));
$durchschnitt = $anzahl > 0 ? round($total / $anzahl, 2) : 0;
$max = $anzahl > 0 ? max(array_column($schuesse, 'wert')) : 0;
$min = $anzahl > 0 ? min(array_column($schuesse, 'wert')) : 0;

if ($modus === 'info') {
    // Zeige Info-Seite
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Zielscheibe - Wettkampfschüsse</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 1200px;
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
            h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
            .zielscheibe { text-align: center; margin: 30px 0; }
            .zielscheibe img { max-width: 100%; border: 2px solid #ddd; border-radius: 5px; }
            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin: 30px 0;
            }
            .stat-box {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
            }
            .stat-value { font-size: 36px; font-weight: bold; }
            .stat-label { font-size: 14px; opacity: 0.9; margin-top: 5px; }
            .buttons { text-align: center; margin: 20px 0; }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                margin: 5px;
                background: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                transition: background 0.3s;
            }
            .btn:hover { background: #0056b3; }
            .btn.secondary { background: #6c757d; }
            .btn.secondary:hover { background: #545b62; }
            .info-box {
                background: #e7f3ff;
                padding: 15px;
                border-left: 4px solid #007bff;
                margin: 20px 0;
                border-radius: 4px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            table th, table td {
                padding: 10px;
                text-align: center;
                border: 1px solid #ddd;
            }
            table th {
                background: #007bff;
                color: white;
            }
            table tr:nth-child(even) { background: #f9f9f9; }
            .wettkampf { background: #d4edda !important; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🎯 Zielscheibe - Wettkampfschüsse (Scheibe-A)</h1>
            
            <div class="info-box">
                <strong>ℹ️ Anzeige:</strong> Es werden nur die <strong>Wettkampfschüsse</strong> angezeigt (Wettkampfschuss = 1).
                <br>Probeschüsse (Wettkampfschuss = 0) werden <strong>nicht</strong> dargestellt.
            </div>
            
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-value"><?php echo $anzahl; ?></div>
                    <div class="stat-label">Wettkampfschüsse</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $total; ?></div>
                    <div class="stat-label">Total Punkte</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $durchschnitt; ?></div>
                    <div class="stat-label">Durchschnitt</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $max; ?></div>
                    <div class="stat-label">Maximum</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?php echo $min; ?></div>
                    <div class="stat-label">Minimum</div>
                </div>
            </div>
            
            <div class="zielscheibe">
                <img src="?modus=anzeigen" alt="Zielscheibe">
            </div>
            
            <div class="buttons">
                <a href="?modus=anzeigen" class="btn" target="_blank">Nur Bild öffnen</a>
                <a href="?modus=speichern" class="btn secondary">Als Datei speichern</a>
                <a href="?modus=info&alle=1" class="btn secondary">Alle Schüsse anzeigen</a>
            </div>
            
            <h2>Schuss-Daten (Wettkampfschüsse)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nr</th>
                        <th>Wertung</th>
                        <th>Passe</th>
                        <th>X (mm)</th>
                        <th>Y (mm)</th>
                        <th>Distanz</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schuesse as $schuss): 
                        $distanz = round(sqrt($schuss['x'] * $schuss['x'] + $schuss['y'] * $schuss['y']), 1);
                    ?>
                    <tr class="wettkampf">
                        <td><?php echo $schuss['schuss_nr']; ?></td>
                        <td><strong><?php echo $schuss['wert']; ?></strong></td>
                        <td><?php echo $schuss['passe']; ?></td>
                        <td><?php echo $schuss['x']; ?></td>
                        <td><?php echo $schuss['y']; ?></td>
                        <td><?php echo $distanz; ?> mm</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="info-box">
                <strong>🎨 Farb-Codierung:</strong>
                <ul style="margin: 10px 0 0 0;">
                    <li><span style="color: red;">●</span> <strong>Rot:</strong> Wertung ≥ 9 (sehr gut)</li>
                    <li><span style="color: blue;">●</span> <strong>Blau:</strong> Wertung 7-8 (gut)</li>
                    <li><span style="color: orange;">●</span> <strong>Orange:</strong> Wertung ≤ 6 (niedriger)</li>
                </ul>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Generator erstellen
$generator = new ZielscheibeGenerator(1000, 1000);

// Automatische Kalibrierung des Koordinaten-Faktors
$faktor = $generator->kalibriereKoordinatenFaktor($schuesse);

// Debug-Info (optional)
if (isset($_GET['debug'])) {
    echo "Kalibrierter Koordinaten-Faktor: " . round($faktor, 2) . "<br>";
    echo "Beispiel-Umrechnung: 100 Einheiten = " . round(100 * $faktor) . " mm<br><br>";
}

// Je nach Modus ausgeben
if ($modus === 'speichern') {
    $dateiname = $alleSchuesse ? 'zielscheibe_alle.png' : 'zielscheibe_wettkampf.png';
    $ausgabeDatei = __DIR__ . '/' . $dateiname;
    
    $erfolg = $generator->generiereZielscheibe($schuesse, $ausgabeDatei);
    
    if ($erfolg) {
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Gespeichert</title></head><body>";
        echo "<h2>✅ Zielscheibe erfolgreich gespeichert!</h2>";
        echo "<p>Datei: <code>" . $ausgabeDatei . "</code></p>";
        echo "<img src='" . $dateiname . "' style='max-width: 800px; border: 2px solid #ddd;'>";
        echo "<br><br><a href='?modus=info'>← Zurück zur Übersicht</a>";
        echo "</body></html>";
    } else {
        echo "Fehler beim Speichern der Zielscheibe.";
    }
} else {
    // Direkt als Bild ausgeben
    $generator->generiereZielscheibe($schuesse);
}
?>
