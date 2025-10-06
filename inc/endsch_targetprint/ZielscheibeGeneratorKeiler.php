<?php
/**
 * Keiler-Zielscheibe Generator (Linksläufig)
 * 
 * Verwendet das Keiler-Bild als Hintergrund und zeichnet Schüsse darüber
 * Das Zentrum ist nach LINKS verschoben (ca. 200mm von der Bildmitte)
 */

class ZielscheibeGeneratorKeiler {
    private $breite;
    private $hoehe;
    private $zentrum_x;
    private $zentrum_y;
    private $keilerBild;
    
    // Keiler-spezifische Einstellungen
    private $zentrum_offset_x = -160; // mm nach links verschoben
    private $zentrum_offset_y = 320;   // mm nach oben verschoben
    private $skalierungsfaktor = 1.6;  // Faktor für Schuss-Positionen (1.0 = original)
    
    /**
     * Konstruktor
     */
    public function __construct($breite = 1200, $hoehe = 1200) {
        $this->breite = $breite;
        $this->hoehe = $hoehe;
        
        // Zentrum ist verschoben!
        $this->zentrum_x = ($breite / 2) + ($this->zentrum_offset_x * $breite / 1500);
        $this->zentrum_y = ($hoehe / 2) - ($this->zentrum_offset_y * $hoehe / 1500);
    }
    
    /**
     * Lade Keiler-Hintergrundbild
     */
    private function ladeKeilerBild($bildPfad) {
        if (!file_exists($bildPfad)) {
            throw new Exception("Keiler-Bild nicht gefunden: " . $bildPfad);
        }
        
        $this->keilerBild = new Imagick($bildPfad);
        
        // Auf Zielgröße skalieren
        $this->keilerBild->scaleImage($this->breite, $this->hoehe, true);
        
        return true;
    }
    
    /**
     * Generiere Zielscheibe mit Schüssen
     * 
     * @param array $schuesse Array mit Schuss-Daten
     * @param string $ausgabeDatei Pfad zur Ausgabedatei
     * @param string $keilerBildPfad Pfad zum Keiler-Hintergrundbild
     * @return bool Erfolg
     */
    public function generiereZielscheibe($schuesse, $ausgabeDatei, $keilerBildPfad) {
        try {
            // Keiler-Bild als Basis laden
            $this->ladeKeilerBild($keilerBildPfad);
            $canvas = $this->keilerBild;
            
            // Zeichne alle Schüsse
            foreach ($schuesse as $schuss) {
                $this->zeichneSchuss($canvas, $schuss);
            }
            
            // Zeichne Zentrumsmarkierung
            // $this->zeichneZentrum($canvas);
            
            // Speichern - JPEG statt PNG für bessere Performance
            $canvas->setImageFormat('jpeg');
            $canvas->setImageCompressionQuality(90);
            $canvas->writeImage($ausgabeDatei);
            $canvas->clear();
            $canvas->destroy();
            
            return true;
            
        } catch (Exception $e) {
            error_log("Fehler beim Generieren der Keiler-Zielscheibe: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generiert Keiler-Zielscheibe direkt im Memory als Blob (keine Temp-Datei)
     * @param array $schuesse Array mit Schuss-Daten
     * @param string $keilerBildPfad Pfad zum Keiler-Hintergrundbild
     * @return array ['success' => bool, 'blob' => string, 'mime' => string]
     */
    public function generiereZielscheibeBlob($schuesse, $keilerBildPfad) {
        try {
            // Keiler-Bild als Basis laden
            $this->ladeKeilerBild($keilerBildPfad);
            $canvas = $this->keilerBild;
            
            // Zeichne alle Schüsse
            foreach ($schuesse as $schuss) {
                $this->zeichneSchuss($canvas, $schuss);
            }
            
            // JPEG statt PNG für bessere Performance
            $canvas->setImageFormat('jpeg');
            $canvas->setImageCompressionQuality(90);
            
            // Direkt als Blob zurückgeben (kein File I/O)
            $blob = $canvas->getImageBlob();
            $mime = 'image/jpeg';
            
            $canvas->clear();
            $canvas->destroy();
            
            return [
                'success' => true,
                'blob' => $blob,
                'mime' => $mime
            ];
            
        } catch (Exception $e) {
            error_log("Fehler beim Generieren der Keiler-Zielscheibe: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Zeichne einen einzelnen Schuss
     * Verwendet dasselbe Design wie ZielscheibeGeneratorImagick
     */
    private function zeichneSchuss($canvas, $schuss) {
        $x_mm = isset($schuss['x']) ? (float)$schuss['x'] : 0.0;
        $y_mm = isset($schuss['y']) ? (float)$schuss['y'] : 0.0;
        $nr = isset($schuss['schuss_nr']) ? (int)$schuss['schuss_nr'] : 0;
        
        // Umrechnung mm → Pixel (1500mm Scheibe) mit Skalierungsfaktor
        $faktor = ($this->breite / 1500.0) * $this->skalierungsfaktor;
        
        // Position berechnen (Y-Achse invertieren!)
        $px = $this->zentrum_x + ($x_mm * $faktor);
        $py = $this->zentrum_y - ($y_mm * $faktor);
        
        // Größerer Kreis für bessere Sichtbarkeit
        $kreisRadius = 16;
        
        // Alle Schüsse rot
        $kreiFarbe = '#FF0000';
        $kreisRandFarbe = '#8B0000'; // Dunkelrot für Rand
        
        // Äußerer dunkler Rand (für Tiefe)
        $drawRand = new ImagickDraw();
        $drawRand->setFillColor(new ImagickPixel($kreisRandFarbe));
        $drawRand->setStrokeOpacity(0);
        $drawRand->setStrokeAntialias(true);
        $drawRand->circle($px, $py, $px + $kreisRadius + 1, $py);
        $canvas->drawImage($drawRand);
        $drawRand->clear();
        $drawRand->destroy();
        
        // Hauptkreis rot
        $drawKreis = new ImagickDraw();
        $drawKreis->setFillColor(new ImagickPixel($kreiFarbe));
        $drawKreis->setStrokeColor(new ImagickPixel('#000000'));
        $drawKreis->setStrokeWidth(1.5);
        $drawKreis->setStrokeAntialias(true);
        $drawKreis->circle($px, $py, $px + $kreisRadius, $py);
        $canvas->drawImage($drawKreis);
        $drawKreis->clear();
        $drawKreis->destroy();
        
        // Schuss-Nummer mittig im Kreis
        if ($nr > 0) {
            // Weiße Nummer zentriert
            $drawText = new ImagickDraw();
            $drawText->setFillColor(new ImagickPixel('#FFFFFF'));
            $drawText->setFont('Arial-Bold');
            $drawText->setFontSize(18);
            $drawText->setFontWeight(900); // Extra Bold
            $drawText->setTextAntialias(true);
            $drawText->setTextAlignment(Imagick::ALIGN_CENTER);
            
            // Text mittig im Kreis positionieren
            $canvas->annotateImage($drawText, $px, $py + 6, 0, (string)$nr);
            $drawText->clear();
            $drawText->destroy();
        }
    }
    
    /**
     * Zeichne Zentrumsmarkierung
     */
    private function zeichneZentrum($canvas) {
        $draw = new ImagickDraw();
        $draw->setStrokeColor(new ImagickPixel('yellow'));
        $draw->setFillColor('none');
        $draw->setStrokeWidth(2);
        
        // Kleines Kreuz
        $size = 15;
        $draw->line(
            $this->zentrum_x - $size, $this->zentrum_y,
            $this->zentrum_x + $size, $this->zentrum_y
        );
        $draw->line(
            $this->zentrum_x, $this->zentrum_y - $size,
            $this->zentrum_x, $this->zentrum_y + $size
        );
        
        // Kreis um Zentrum
        $draw->circle(
            $this->zentrum_x, $this->zentrum_y,
            $this->zentrum_x + $size, $this->zentrum_y
        );
        
        $canvas->drawImage($draw);
        $draw->clear();
        $draw->destroy();
    }
    
    /**
     * Setze Zentrum-Offset manuell
     */
    public function setzeZentrumOffset($offset_x_mm, $offset_y_mm) {
        $this->zentrum_offset_x = $offset_x_mm;
        $this->zentrum_offset_y = $offset_y_mm;
        
        // Zentrum neu berechnen
        $this->zentrum_x = ($this->breite / 2) + ($this->zentrum_offset_x * $this->breite / 1500);
        $this->zentrum_y = ($this->hoehe / 2) - ($this->zentrum_offset_y * $this->hoehe / 1500);
    }
    
    /**
     * Setze Skalierungsfaktor für Schuss-Positionen
     * 
     * @param float $faktor Skalierungsfaktor (1.0 = original, >1.0 = weiter weg, <1.0 = näher)
     */
    public function setzeSkalierungsfaktor($faktor) {
        $this->skalierungsfaktor = (float)$faktor;
    }
}
?>
