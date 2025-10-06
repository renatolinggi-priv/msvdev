<?php
/**
 * Debug-Seite zur Analyse der Koordinaten und Kalibrierung
 */

require_once 'ZielscheibeGenerator.php';

// CSV-Daten
$csvDaten = "Nr;Wettkampfschuss;Passe;Wertung;100er Wertung;EF/SF;Lage;X;Y
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

function parseCSVSchuesse($csvText, $nurWettkampf = true) {
    $zeilen = explode("\n", trim($csvText));
    $schuesse = [];
    array_shift($zeilen);
    
    foreach ($zeilen as $zeile) {
        if (empty(trim($zeile))) continue;
        $teile = explode(';', $zeile);
        
        if (count($teile) >= 9) {
            $nr = intval($teile[0]);
            $wettkampfschuss = intval($teile[1]);
            $wertung = intval($teile[3]);
            $x = floatval($teile[7]);
            $y = floatval($teile[8]);
            
            if ($nurWettkampf && $wettkampfschuss != 1) continue;
            
            $schuesse[] = [
                'schuss_nr' => $nr,
                'wert' => $wertung,
                'x' => $x,
                'y' => $y
            ];
        }
    }
    return $schuesse;
}

$schuesse = parseCSVSchuesse($csvDaten);
$generator = new ZielscheibeGenerator(1000, 1000);

// Kalibrierung durchführen
$besterFaktor = $generator->kalibriereKoordinatenFaktor($schuesse);

// Erwartete Distanzen für jeden Ring (Mittelpunkt des Rings)
$erwarteteDistanzen = [
    10 => 75,   // Mitte zwischen 50 und 100mm
    9 => 150,   // Mitte zwischen 100 und 200mm
    8 => 250,   // Mitte zwischen 200 und 300mm
    7 => 350,   // Mitte zwischen 300 und 400mm
    6 => 450,   // Mitte zwischen 400 und 500mm
    5 => 550,
    4 => 650,
    3 => 750,
    2 => 850,
    1 => 950
];

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koordinaten-Kalibrierung Debug</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            max-width: 1600px;
            margin: 20px auto;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .container {
            background: #252526;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #3e3e42;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h2 {
            color: #569cd6;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #1e1e1e;
            font-size: 13px;
        }
        th {
            background: #2d2d30;
            color: #4ec9b0;
            padding: 10px;
            text-align: left;
            border: 1px solid #3e3e42;
        }
        td {
            padding: 8px;
            border: 1px solid #3e3e42;
        }
        tr:hover {
            background: #2d2d30;
        }
        .correct { color: #4ec9b0; font-weight: bold; }
        .wrong { color: #f48771; font-weight: bold; }
        .info { color: #ce9178; }
        .highlight {
            background: #264f78;
            padding: 15px;
            border-left: 3px solid #569cd6;
            margin: 20px 0;
        }
        .success {
            background: #1e3a1e;
            border-left-color: #4ec9b0;
        }
        .warning {
            background: #3a2e1e;
            border-left-color: #ce9178;
        }
        .number { color: #b5cea8; }
        .key { color: #9cdcfe; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Koordinaten-Kalibrierung Debug</h1>
        
        <div class="highlight success">
            <strong class="key">Kalibrierter Faktor:</strong> <span class="number"><?php echo round($besterFaktor, 3); ?></span><br>
            <span class="info">Die Koordinaten werden mit diesem Faktor multipliziert, um Millimeter zu erhalten.</span>
        </div>
        
        <h2>📊 Detaillierte Schuss-Analyse</h2>
        <table>
            <thead>
                <tr>
                    <th>Nr</th>
                    <th>Wertung<br>(Soll)</th>
                    <th>X</th>
                    <th>Y</th>
                    <th>Distanz<br>(Original)</th>
                    <th>Distanz<br>(× <?php echo round($besterFaktor, 2); ?>)</th>
                    <th>Erwartet<br>für Ring</th>
                    <th>Differenz</th>
                    <th>Ring<br>(Berechnet)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $ringRadien = [
                    'mouche' => 50, 10 => 100, 9 => 200, 8 => 300, 7 => 400,
                    6 => 500, 5 => 600, 4 => 700, 3 => 800, 2 => 900, 1 => 1000
                ];
                
                $korrektCount = 0;
                $gesamtAbweichung = 0;
                
                foreach ($schuesse as $s) {
                    $distanzOriginal = sqrt($s['x'] * $s['x'] + $s['y'] * $s['y']);
                    $distanzKalibriert = $distanzOriginal * $besterFaktor;
                    
                    // Ring aus kalibrierter Distanz berechnen
                    $berechneterRing = 0;
                    if ($distanzKalibriert <= $ringRadien['mouche']) {
                        $berechneterRing = 10;
                    } else {
                        for ($r = 10; $r >= 1; $r--) {
                            if ($distanzKalibriert <= $ringRadien[$r]) {
                                $berechneterRing = $r;
                                break;
                            }
                        }
                    }
                    
                    $korrekt = ($berechneterRing == $s['wert']);
                    if ($korrekt) $korrektCount++;
                    
                    $statusClass = $korrekt ? 'correct' : 'wrong';
                    $statusText = $korrekt ? '✓ Korrekt' : '✗ Abweichung';
                    
                    $erwarteteDistanz = isset($erwarteteDistanzen[$s['wert']]) ? $erwarteteDistanzen[$s['wert']] : 0;
                    $abweichung = $distanzKalibriert - $erwarteteDistanz;
                    $gesamtAbweichung += abs($abweichung);
                    
                    echo "<tr>";
                    echo "<td class='number'>{$s['schuss_nr']}</td>";
                    echo "<td class='number'><strong>{$s['wert']}</strong></td>";
                    echo "<td class='number'>" . number_format($s['x'], 2) . "</td>";
                    echo "<td class='number'>" . number_format($s['y'], 2) . "</td>";
                    echo "<td class='number'>" . number_format($distanzOriginal, 1) . "</td>";
                    echo "<td class='number'><strong>" . number_format($distanzKalibriert, 1) . " mm</strong></td>";
                    echo "<td class='info'>" . number_format($erwarteteDistanz, 0) . " mm</td>";
                    echo "<td class='number' style='color: " . (abs($abweichung) < 50 ? '#4ec9b0' : '#f48771') . "'>" . 
                         ($abweichung > 0 ? '+' : '') . number_format($abweichung, 1) . " mm</td>";
                    echo "<td class='number'><strong>$berechneterRing</strong></td>";
                    echo "<td class='$statusClass'>$statusText</td>";
                    echo "</tr>";
                }
                
                $prozentKorrekt = round(($korrektCount / count($schuesse)) * 100);
                $durchschnittAbweichung = round($gesamtAbweichung / count($schuesse), 1);
                ?>
            </tbody>
        </table>
        
        <div class="highlight <?php echo $prozentKorrekt >= 80 ? 'success' : 'warning'; ?>">
            <strong class="key">Genauigkeit:</strong> 
            <span class="number"><?php echo $korrektCount; ?> / <?php echo count($schuesse); ?></span> 
            (<span class="number"><?php echo $prozentKorrekt; ?>%</span>) Schüsse korrekt<br>
            <strong class="key">Durchschnittliche Abweichung:</strong> 
            <span class="number"><?php echo $durchschnittAbweichung; ?> mm</span><br>
            <br>
            <?php if ($prozentKorrekt < 80): ?>
            <span class="wrong">⚠️ Niedrige Genauigkeit!</span><br>
            <span class="info">Die Koordinaten könnten:</span><br>
            • In 1/10 Millimetern sein (Faktor sollte ~2.2 sein)<br>
            • Ein anderes Koordinatensystem verwenden<br>
            • Manuell korrigierte Werte enthalten
            <?php else: ?>
            <span class="correct">✓ Gute Übereinstimmung!</span>
            <?php endif; ?>
        </div>
        
        <h2>🎯 Manuelle Faktor-Tests</h2>
        <table>
            <thead>
                <tr>
                    <th>Faktor</th>
                    <th>Beispiel: Schuss #10 (Wert 7)</th>
                    <th>Original: 159.9</th>
                    <th>Kalibriert</th>
                    <th>Erwarteter Bereich</th>
                    <th>Passt?</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $testFaktoren = [1.0, 1.5, 2.0, 2.2, 2.5, 3.0];
                foreach ($testFaktoren as $f) {
                    $testDistanz = 159.9 * $f;
                    $passt = ($testDistanz >= 300 && $testDistanz <= 400);
                    $passClass = $passt ? 'correct' : 'wrong';
                    $passText = $passt ? '✓ Ja' : '✗ Nein';
                    
                    echo "<tr>";
                    echo "<td class='number'><strong>" . number_format($f, 1) . "</strong></td>";
                    echo "<td class='info'>-89.7, 132.38</td>";
                    echo "<td class='number'>159.9</td>";
                    echo "<td class='number'>" . number_format($testDistanz, 1) . " mm</td>";
                    echo "<td class='info'>300 - 400 mm (Ring 7)</td>";
                    echo "<td class='$passClass'>$passText</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
        
        <h2>📏 Ring-Radien (300m Scheibe-A)</h2>
        <table>
            <thead>
                <tr>
                    <th>Ring</th>
                    <th>Radius (mm)</th>
                    <th>Bereich</th>
                    <th>Erwarteter Mittelpunkt</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Mouche</td><td class="number">0 - 50</td><td class="info">Innerster Punkt</td><td class="number">-</td></tr>
                <tr><td class="number">10</td><td class="number">50 - 100</td><td>Schwarz</td><td class="number">75 mm</td></tr>
                <tr><td class="number">9</td><td class="number">100 - 200</td><td>Schwarz</td><td class="number">150 mm</td></tr>
                <tr><td class="number">8</td><td class="number">200 - 300</td><td>Schwarz</td><td class="number">250 mm</td></tr>
                <tr><td class="number">7</td><td class="number">300 - 400</td><td>Schwarz</td><td class="number">350 mm</td></tr>
                <tr><td class="number">6</td><td class="number">400 - 500</td><td>Schwarz</td><td class="number">450 mm</td></tr>
                <tr><td class="number">5</td><td class="number">500 - 600</td><td>Beige</td><td class="number">550 mm</td></tr>
                <tr><td class="number">4</td><td class="number">600 - 700</td><td>Beige</td><td class="number">650 mm</td></tr>
                <tr><td class="number">3</td><td class="number">700 - 800</td><td>Beige</td><td class="number">750 mm</td></tr>
                <tr><td class="number">2</td><td class="number">800 - 900</td><td>Beige</td><td class="number">850 mm</td></tr>
                <tr><td class="number">1</td><td class="number">900 - 1000</td><td>Beige</td><td class="number">950 mm</td></tr>
                <tr><td class="number">0</td><td class="number">&gt; 1000</td><td class="info">Ausserhalb</td><td class="number">-</td></tr>
            </tbody>
        </table>
        
        <div class="highlight">
            <strong class="key">Interpretation:</strong><br>
            • <span class="key">Erwartete Distanz:</span> Mittelpunkt des Rings basierend auf der Wertung<br>
            • <span class="key">Differenz:</span> Abweichung zwischen gemessener und erwarteter Distanz<br>
            • <span class="correct">Grün:</span> Differenz &lt; 50mm (sehr gut)<br>
            • <span class="wrong">Rot:</span> Differenz &gt; 50mm (Koordinaten-Problem)<br>
            <br>
            <span class="info">Hinweis: Die Wertung berücksichtigt das "Anstechen" der Ringlinie (bester Ringwert zählt).
            Deshalb sind kleine Abweichungen normal.</span>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="beispiel_wettkampf.php?modus=info" 
               style="display: inline-block; padding: 12px 24px; background: #569cd6; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">
                🎯 Zur Zielscheiben-Ansicht
            </a>
            <a href="?refresh=1" 
               style="display: inline-block; padding: 12px 24px; background: #4ec9b0; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">
                🔄 Neu laden
            </a>
        </div>
    </div>
</body>
</html>
