<?php
/**
 * Beispiel für die Verwendung der ZielscheibeGenerator-Klasse
 * 
 * Dieses Skript liest Schuss-Daten und generiert eine Zielscheiben-Visualisierung
 */

require_once 'ZielscheibeGenerator.php';

// Beispiel-Daten (wie von dir bereitgestellt)
// Format: schuss_nr;serie;durchgang;wert;total;ring;ring2;x;y
$rohdaten = [
    "1;0;0;7;69;1;2;99.7;124.4",
    "2;0;0;10;98;1;0;14.47;5.08",
    "3;0;0;9;81;1;3;100.24;-24.89",
    "4;0;0;8;80;1;2;74.14;76.55",
    "5;0;0;10;91;1;5;14.04;-48.59",
    "6;0;0;9;81;1;5;9.91;-103.16",
    "7;0;0;10;91;1;4;44.05;-27.05",
    "8;0;0;9;88;1;1;-15.96;64.26",
    "9;0;0;9;90;1;8;-23.78;51.97",
    "1;1;1;10;93;1;8;-24.71;34.44",
    "2;1;1;9;90;1;2;28.9;46.77",
    "3;1;1;10;93;1;5;-3.53;-42.4",
    "4;1;1;9;86;1;6;-55.69;-54.43",
    "5;1;1;9;89;1;3;57.93;-24.11",
    "6;1;1;10;94;1;2;15.75;31.65",
    "7;1;1;9;87;1;8;-61.2;32.65",
    "8;1;1;10;91;1;7;-47.13;-18.38",
    "9;1;1;9;82;1;7;-94.77;4.88",
    "10;1;1;7;69;1;8;-89.7;132.38"
];

/**
 * Parst die Rohdaten in ein strukturiertes Array
 */
function parseSchuesse($rohdaten) {
    $schuesse = [];
    
    foreach ($rohdaten as $zeile) {
        $teile = explode(';', $zeile);
        
        if (count($teile) >= 9) {
            $schuesse[] = [
                'schuss_nr' => intval($teile[0]),
                'serie' => intval($teile[1]),
                'durchgang' => intval($teile[2]),
                'wert' => intval($teile[3]),
                'total' => intval($teile[4]),
                'ring' => intval($teile[5]),
                'ring2' => intval($teile[6]),
                'x' => floatval($teile[7]),
                'y' => floatval($teile[8])
            ];
        }
    }
    
    return $schuesse;
}

// Daten parsen
$schuesse = parseSchuesse($rohdaten);

// Generator erstellen
$generator = new ZielscheibeGenerator(1000, 1000); // Größeres Bild für bessere Qualität

// Prüfen ob ein Ausgabe-Modus angegeben wurde
$modus = isset($_GET['modus']) ? $_GET['modus'] : 'anzeigen';

if ($modus === 'speichern') {
    // Als Datei speichern
    $ausgabeDatei = __DIR__ . '/zielscheibe_beispiel.png';
    $erfolg = $generator->generiereZielscheibe($schuesse, $ausgabeDatei);
    
    if ($erfolg) {
        echo "Zielscheibe erfolgreich gespeichert: " . $ausgabeDatei;
        echo "<br><br>";
        echo "<img src='zielscheibe_beispiel.png' alt='Zielscheibe' style='max-width: 100%; height: auto;'>";
    } else {
        echo "Fehler beim Speichern der Zielscheibe.";
    }
} else {
    // Direkt als Bild ausgeben
    $generator->generiereZielscheibe($schuesse);
}
?>
