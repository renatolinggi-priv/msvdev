<?php
/**
 * Generiert eine Zielscheibe basierend auf Daten aus der Datenbank
 * 
 * Verwendung: zielscheibe_anzeigen.php?schuetze_id=123&serie_id=456
 */

require_once '../config.php';
require_once 'ZielscheibeGenerator.php';

// Parameter prüfen
$schuetze_id = isset($_GET['schuetze_id']) ? intval($_GET['schuetze_id']) : 0;
$serie_id = isset($_GET['serie_id']) ? intval($_GET['serie_id']) : 0;
$ausgabe_modus = isset($_GET['ausgabe']) ? $_GET['ausgabe'] : 'bild'; // 'bild' oder 'datei'

if ($schuetze_id <= 0 || $serie_id <= 0) {
    die("Fehler: Ungültige Parameter. Bitte schuetze_id und serie_id angeben.");
}

/**
 * Lädt Schuss-Daten aus der Datenbank
 */
function ladeSchuesse($conn, $schuetze_id, $serie_id) {
    // HINWEIS: Tabellen- und Spaltennamen müssen an deine Datenbank angepasst werden!
    // Dies ist nur ein Beispiel
    
    $sql = "SELECT 
                schuss_nummer as schuss_nr,
                wert,
                koordinate_x as x,
                koordinate_y as y
            FROM schuesse
            WHERE schuetze_id = ? AND serie_id = ?
            ORDER BY schuss_nummer ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $schuetze_id, $serie_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schuesse = [];
    while ($row = $result->fetch_assoc()) {
        $schuesse[] = $row;
    }
    
    $stmt->close();
    
    return $schuesse;
}

// Daten laden
$schuesse = ladeSchuesse($conn, $schuetze_id, $serie_id);

if (empty($schuesse)) {
    die("Keine Schuss-Daten gefunden für Schütze ID $schuetze_id und Serie ID $serie_id.");
}

// Generator erstellen
$generator = new ZielscheibeGenerator(1000, 1000);

// Je nach Modus ausgeben
if ($ausgabe_modus === 'datei') {
    // Als Datei speichern
    $dateiname = "zielscheibe_schuetze_{$schuetze_id}_serie_{$serie_id}.png";
    $ausgabeDatei = __DIR__ . '/' . $dateiname;
    
    $erfolg = $generator->generiereZielscheibe($schuesse, $ausgabeDatei);
    
    if ($erfolg) {
        // Als Download anbieten
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $dateiname . '"');
        readfile($ausgabeDatei);
        
        // Temporäre Datei löschen
        unlink($ausgabeDatei);
    } else {
        echo "Fehler beim Generieren der Zielscheibe.";
    }
} else {
    // Direkt als Bild ausgeben
    $generator->generiereZielscheibe($schuesse);
}

$conn->close();
?>
