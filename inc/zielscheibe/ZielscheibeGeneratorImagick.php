<?php

/**
 * ZielscheibeGeneratorImagick - erzeugt hochqualitative Visualisierungen mit ImageMagick.
 *
 * Diese Klasse ist eine 1:1 Umsetzung von ZielscheibeGenerator.php mit Imagick statt GD.
 * Die Scheibe folgt dem Schweizer Standard mit schwarzen Ringen (10-6) und hellen Ringen (5-1).
 */
class ZielscheibeGeneratorImagick
{
    private $ringRadien = [
        'mouche' => 25.0,
        10 => 50.0,
        9 => 100.0,
        8 => 150.0,
        7 => 200.0,
        6 => 250.0,
        5 => 300.0,
        4 => 350.0,
        3 => 400.0,
        2 => 450.0,
        1 => 500.0,
        0 => 1000.0,
    ];

    private $bildBreite;
    private $bildHoehe;
    private $mittelpunktX;
    private $mittelpunktY;
    private $skalierungsFaktor;
    private $koordinatenFaktor = 2.7;

    const RAND_SKALIERUNG = 0.85;

    public function __construct($bildBreite = 1200, $bildHoehe = 1200)
    {
        if (!extension_loaded('imagick')) {
            throw new Exception('Imagick Extension ist nicht verfügbar!');
        }

        $this->bildBreite = (int) $bildBreite;
        $this->bildHoehe = (int) $bildHoehe;
        $this->mittelpunktX = $this->bildBreite / 2;
        $this->mittelpunktY = $this->bildHoehe / 2;

        $maxRadius = $this->ringRadien[1];
        $verfuegbarerRadius = min($this->bildBreite, $this->bildHoehe) / 2 * self::RAND_SKALIERUNG;
        $this->skalierungsFaktor = $verfuegbarerRadius / $maxRadius;
    }

    public function generiereZielscheibe(array $treffer, $ausgabeDatei = null, $mitLegende = true)
    {
        try {
            // Bild erstellen mit beigem Hintergrund
            $image = new Imagick();
            $image->newImage($this->bildBreite, $this->bildHoehe, new ImagickPixel('#F5F0DC'));
            
            // JPEG statt PNG für bessere Performance
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(90); // Hohe Qualität, aber komprimiert

            // Prüfen ob Zoom nötig
            $zoomAktiv = $this->sollteZoomen($treffer);

            $this->zeichneRinge($image, $zoomAktiv);
            $this->zeichneFadenkreuz($image);
            $this->zeichneTreffer($image, $treffer);
            
            // Legende nur zeichnen wenn gewünscht
            if ($mitLegende) {
                $this->zeichneLegende($image, $treffer, null); // null = kein Schützenname, kann später hinzugefügt werden
            }

            if ($ausgabeDatei !== null) {
                $erfolg = $image->writeImage($ausgabeDatei);
                $image->clear();
                $image->destroy();
                return $erfolg;
            }

            header('Content-Type: image/jpeg');
            echo $image;
            $image->clear();
            $image->destroy();

            return true;

        } catch (Exception $e) {
            error_log("Fehler beim Generieren der Zielscheibe: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generiert Zielscheibe direkt im Memory als Blob (keine Temp-Datei)
     * @return array ['success' => bool, 'blob' => string, 'mime' => string]
     */
    public function generiereZielscheibeBlob(array $treffer, $mitLegende = true)
    {
        try {
            // Bild erstellen mit beigem Hintergrund
            $image = new Imagick();
            $image->newImage($this->bildBreite, $this->bildHoehe, new ImagickPixel('#F5F0DC'));
            
            // JPEG statt PNG für bessere Performance
            $image->setImageFormat('jpeg');
            $image->setImageCompressionQuality(90);

            // Prüfen ob Zoom nötig
            $zoomAktiv = $this->sollteZoomen($treffer);

            $this->zeichneRinge($image, $zoomAktiv);
            $this->zeichneFadenkreuz($image);
            $this->zeichneTreffer($image, $treffer);
            
            if ($mitLegende) {
                $this->zeichneLegende($image, $treffer, null);
            }

            // Direkt als Blob zurückgeben (kein File I/O)
            $blob = $image->getImageBlob();
            $mime = 'image/jpeg';
            
            $image->clear();
            $image->destroy();

            return [
                'success' => true,
                'blob' => $blob,
                'mime' => $mime
            ];

        } catch (Exception $e) {
            error_log("Fehler beim Generieren der Zielscheibe: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Zeichnet die Ringe - GENAU wie in GD-Version!
     */
    private function zeichneRinge($image, $zoom = false)
    {
        if ($zoom) {
            $ringe = [5, 6, 7, 8, 9, 10];
            $maxRadius = $this->ringRadien[5];
            $verfuegbarerRadius = min($this->bildBreite, $this->bildHoehe) / 2 * self::RAND_SKALIERUNG;
            $zoomFaktor = $verfuegbarerRadius / $maxRadius;
        } else {
            $ringe = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
            $zoomFaktor = $this->skalierungsFaktor;
        }

        // Jeden Ring zeichnen: ERST füllen, DANN Kontur
        foreach ($ringe as $ring) {
            $radius = $this->ringRadien[$ring] * $zoomFaktor;
            $diameter = $radius * 2;
            
            // Füllfarbe bestimmen
            $fuellFarbe = $ring >= 6 ? '#000000' : '#F5F0DC'; // Schwarz oder Beige
            $linienFarbe = $ring >= 6 ? '#646464' : '#000000'; // Dunkelgrau oder Schwarz
            
            // 1. Gefüllter Kreis
            $drawFill = new ImagickDraw();
            $drawFill->setFillColor(new ImagickPixel($fuellFarbe));
            $drawFill->setStrokeOpacity(0); // Keine Kontur beim Füllen
            $drawFill->circle(
                $this->mittelpunktX,
                $this->mittelpunktY,
                $this->mittelpunktX + $radius,
                $this->mittelpunktY
            );
            $image->drawImage($drawFill);
            
            // 2. Kontur-Kreis
            $drawStroke = new ImagickDraw();
            $drawStroke->setFillOpacity(0); // Nicht füllen
            $drawStroke->setStrokeColor(new ImagickPixel($linienFarbe));
            $drawStroke->setStrokeWidth(1);
            $drawStroke->setStrokeAntialias(true);
            $drawStroke->circle(
                $this->mittelpunktX,
                $this->mittelpunktY,
                $this->mittelpunktX + $radius,
                $this->mittelpunktY
            );
            $image->drawImage($drawStroke);
        }

        // Mouche (innerster Kreis)
        $moucheRadius = $this->ringRadien['mouche'] * $zoomFaktor;
        
        // Gefüllt
        $drawMoucheFill = new ImagickDraw();
        $drawMoucheFill->setFillColor(new ImagickPixel('#000000'));
        $drawMoucheFill->setStrokeOpacity(0);
        $drawMoucheFill->circle(
            $this->mittelpunktX,
            $this->mittelpunktY,
            $this->mittelpunktX + $moucheRadius,
            $this->mittelpunktY
        );
        $image->drawImage($drawMoucheFill);
        
        // Kontur
        $drawMoucheStroke = new ImagickDraw();
        $drawMoucheStroke->setFillOpacity(0);
        $drawMoucheStroke->setStrokeColor(new ImagickPixel('#646464'));
        $drawMoucheStroke->setStrokeWidth(1);
        $drawMoucheStroke->setStrokeAntialias(true);
        $drawMoucheStroke->circle(
            $this->mittelpunktX,
            $this->mittelpunktY,
            $this->mittelpunktX + $moucheRadius,
            $this->mittelpunktY
        );
        $image->drawImage($drawMoucheStroke);

        $this->zeichneBeschriftungen($image, $zoom, $zoomFaktor);
        $this->zeichneKreuze($image, $zoom, $zoomFaktor);

        if ($zoom) {
            $this->skalierungsFaktor = $zoomFaktor;
        }
    }

    private function zeichneBeschriftungen($image, $zoom = false, $zoomFaktor = null)
    {
        $faktor = $zoomFaktor ?? $this->skalierungsFaktor;
        $ringe = $zoom ? [5, 6, 7, 8, 9, 10] : [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        
        $fontSize = 24;
        $fontPath = $this->findArialFont();

        foreach ($ringe as $ring) {
            $radius = $this->ringRadien[$ring] * $faktor;
            $textFarbe = $ring >= 6 ? '#FFFFFF' : '#000000';
            $ringText = (string) $ring;
            $textRadius = $radius - 30;

            $positionen = [
                ['x' => $this->mittelpunktX, 'y' => $this->mittelpunktY - $textRadius], // Oben
                ['x' => $this->mittelpunktX, 'y' => $this->mittelpunktY + $textRadius], // Unten
                ['x' => $this->mittelpunktX - $textRadius, 'y' => $this->mittelpunktY], // Links
                ['x' => $this->mittelpunktX + $textRadius, 'y' => $this->mittelpunktY], // Rechts
            ];

            foreach ($positionen as $pos) {
                $this->zeichneZentriertenText($image, $ringText, $pos['x'], $pos['y'], $fontSize, $textFarbe, $fontPath);
            }
        }
    }

    private function zeichneKreuze($image, $zoom = false, $zoomFaktor = null)
    {
        // Kreuze deaktiviert - werden nicht mehr gezeichnet
        return;
    }

    private function zeichneFadenkreuz($image)
    {
        $laenge = 10; // Kleiner: war 25
        
        $draw = new ImagickDraw();
        $draw->setStrokeColor(new ImagickPixel('#C8C8C8'));
        $draw->setStrokeWidth(1);
        $draw->setStrokeOpacity(0.5);
        $draw->setStrokeAntialias(true);
        
        $draw->line(
            $this->mittelpunktX - $laenge,
            $this->mittelpunktY,
            $this->mittelpunktX + $laenge,
            $this->mittelpunktY
        );
        
        $draw->line(
            $this->mittelpunktX,
            $this->mittelpunktY - $laenge,
            $this->mittelpunktX,
            $this->mittelpunktY + $laenge
        );
        
        $image->drawImage($draw);
    }

    private function zeichneTreffer($image, array $treffer)
    {
        foreach ($treffer as $index => $trefferDaten) {
            if (!isset($trefferDaten['x'], $trefferDaten['y'])) {
                continue;
            }

            $xMm = $this->transformiereKoordinate((float) $trefferDaten['x']);
            $yMm = $this->transformiereKoordinate((float) $trefferDaten['y']);

            $pixelX = $this->mittelpunktX + ($xMm * $this->skalierungsFaktor);
            $pixelY = $this->mittelpunktY - ($yMm * $this->skalierungsFaktor);

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
            $drawRand->circle($pixelX, $pixelY, $pixelX + $kreisRadius + 1, $pixelY);
            $image->drawImage($drawRand);

            // Hauptkreis rot
            $drawKreis = new ImagickDraw();
            $drawKreis->setFillColor(new ImagickPixel($kreiFarbe));
            $drawKreis->setStrokeColor(new ImagickPixel('#000000'));
            $drawKreis->setStrokeWidth(1.5);
            $drawKreis->setStrokeAntialias(true);
            $drawKreis->circle($pixelX, $pixelY, $pixelX + $kreisRadius, $pixelY);
            $image->drawImage($drawKreis);

            // Schuss-Nummer mittig im Kreis
            $schussNr = isset($trefferDaten['schuss_nr']) ? (int) $trefferDaten['schuss_nr'] : $index + 1;

            // Weiße Nummer zentriert
            $drawText = new ImagickDraw();
            $drawText->setFillColor(new ImagickPixel('#FFFFFF'));
            $drawText->setFont($this->findFont());
            $drawText->setFontSize(18);
            $drawText->setFontWeight(900); // Extra Bold
            $drawText->setTextAntialias(true);
            $drawText->setTextAlignment(Imagick::ALIGN_CENTER);
            
            // Text mittig im Kreis positionieren
            $image->annotateImage($drawText, $pixelX, $pixelY + 6, 0, (string) $schussNr);
        }
    }

    private function zeichneLegende($image, array $treffer, $schuetzenName = null)
    {
        if (empty($treffer)) {
            return;
        }

        // Berechne benötigte Höhe basierend auf Anzahl Schüsse
        $anzahlSchuesse = count($treffer);
        $headerHoehe = $schuetzenName ? 70 : 50;
        $zeilenHoehe = 18; // Mehr Abstand zwischen Zeilen
        $footerHoehe = 40;
        $gesamtHoehe = $headerHoehe + ($anzahlSchuesse * $zeilenHoehe) + $footerHoehe;
        $breite = 220; // Breiter für bessere Lesbarkeit

        // Hintergrund für Legende
        $drawBg = new ImagickDraw();
        $drawBg->setFillColor(new ImagickPixel('rgba(255, 255, 255, 0.98)'));
        $drawBg->setStrokeColor(new ImagickPixel('#333333'));
        $drawBg->setStrokeWidth(2.5);
        $drawBg->rectangle(10, 10, 10 + $breite, 10 + $gesamtHoehe);
        $image->drawImage($drawBg);

        $startY = 35;
        $aktuelleY = $startY;

        // Schützenname (optional)
        if ($schuetzenName) {
            $drawName = new ImagickDraw();
            $drawName->setFillColor(new ImagickPixel('#000000'));
            $drawName->setFont($this->findFont());
            $drawName->setFontSize(17);
            $drawName->setFontWeight(900);
            $drawName->setTextAntialias(true);
            $image->annotateImage($drawName, 25, $aktuelleY, 0, $schuetzenName);
            $aktuelleY += 30;
        }

        // Tabellen-Header mit Hintergrund
        $drawHeaderBg = new ImagickDraw();
        $drawHeaderBg->setFillColor(new ImagickPixel('#f0f0f0'));
        $drawHeaderBg->rectangle(20, $aktuelleY - 15, 10 + $breite - 10, $aktuelleY + 5);
        $image->drawImage($drawHeaderBg);

        $drawHeader = new ImagickDraw();
        $drawHeader->setFillColor(new ImagickPixel('#333333'));
        $drawHeader->setFont($this->findFont());
        $drawHeader->setFontSize(13);
        $drawHeader->setFontWeight(700);
        $drawHeader->setTextAntialias(true);
        
        $image->annotateImage($drawHeader, 25, $aktuelleY, 0, 'Nr');
        $image->annotateImage($drawHeader, 75, $aktuelleY, 0, 'Wertung');
        $image->annotateImage($drawHeader, 155, $aktuelleY, 0, '100er');
        $aktuelleY += 22;

        // Schüsse
        $drawText = new ImagickDraw();
        $drawText->setFillColor(new ImagickPixel('#000000'));
        $drawText->setFont($this->findFont());
        $drawText->setFontSize(14);
        $drawText->setFontWeight(400);
        $drawText->setTextAntialias(true);

        $totalWertung = 0;
        $max100er = 0;

        foreach ($treffer as $trefferDaten) {
            $nr = isset($trefferDaten['schuss_nr']) ? (int) $trefferDaten['schuss_nr'] : '?';
            $wertung = isset($trefferDaten['wert']) ? (int) $trefferDaten['wert'] : 0;
            $hunderter = isset($trefferDaten['hunderter']) ? (int) $trefferDaten['hunderter'] : 0;

            $totalWertung += $wertung;
            // Höchsten 100er-Wert finden
            if ($hunderter > $max100er) {
                $max100er = $hunderter;
            }

            // Zebra-Streifen für bessere Lesbarkeit
            if ($nr % 2 == 0) {
                $drawZebra = new ImagickDraw();
                $drawZebra->setFillColor(new ImagickPixel('#f9f9f9'));
                $drawZebra->rectangle(20, $aktuelleY - 13, 10 + $breite - 10, $aktuelleY + 5);
                $image->drawImage($drawZebra);
            }

            $image->annotateImage($drawText, 25, $aktuelleY, 0, (string) $nr);
            $image->annotateImage($drawText, 75, $aktuelleY, 0, (string) $wertung);
            $image->annotateImage($drawText, 155, $aktuelleY, 0, (string) $hunderter);
            $aktuelleY += $zeilenHoehe;
        }

        // Trennlinie dicker und mit Abstand
        $aktuelleY += 5;
        $drawLinie = new ImagickDraw();
        $drawLinie->setStrokeColor(new ImagickPixel('#000000'));
        $drawLinie->setStrokeWidth(2);
        $drawLinie->line(25, $aktuelleY, 10 + $breite - 15, $aktuelleY);
        $image->drawImage($drawLinie);
        $aktuelleY += 20;

        // Total mit Hintergrund
        $drawTotalBg = new ImagickDraw();
        $drawTotalBg->setFillColor(new ImagickPixel('#e8f4f8'));
        $drawTotalBg->rectangle(20, $aktuelleY - 15, 10 + $breite - 10, $aktuelleY + 5);
        $image->drawImage($drawTotalBg);

        $drawTotal = new ImagickDraw();
        $drawTotal->setFillColor(new ImagickPixel('#000000'));
        $drawTotal->setFont($this->findFont());
        $drawTotal->setFontSize(16);
        $drawTotal->setFontWeight(900);
        $drawTotal->setTextAntialias(true);
        
        $image->annotateImage($drawTotal, 25, $aktuelleY, 0, 'Total:');
        $image->annotateImage($drawTotal, 75, $aktuelleY, 0, (string) $totalWertung);
        $image->annotateImage($drawTotal, 155, $aktuelleY, 0, (string) $max100er);
    }

    private function findArialFont()
    {
        $possiblePaths = [
            __DIR__ . '/fonts/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
            '/usr/share/fonts/truetype/msttcorefonts/arial.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function findFont()
    {
        $possibleFonts = ['Arial-Bold', 'Arial', 'Helvetica-Bold', 'Helvetica', 'DejaVu-Sans-Bold', 'DejaVu-Sans'];

        foreach ($possibleFonts as $font) {
            try {
                $test = new ImagickDraw();
                $test->setFont($font);
                return $font;
            } catch (Exception $e) {
                continue;
            }
        }

        return 'Arial';
    }

    private function zeichneZentriertenText($image, $text, $x, $y, $fontSize, $textFarbe, $fontPath)
    {
        if ($fontPath && file_exists($fontPath)) {
            // TTF Font mit imagettfbbox simulieren
            $draw = new ImagickDraw();
            $draw->setFont($fontPath);
            $draw->setFontSize($fontSize);
            $draw->setFillColor(new ImagickPixel($textFarbe));
            $draw->setTextAntialias(true);
            $draw->setTextAlignment(Imagick::ALIGN_CENTER);
            
            $image->annotateImage($draw, $x, $y + 8, 0, $text);
        } else {
            // Fallback auf Imagick Font
            $draw = new ImagickDraw();
            $draw->setFont($this->findFont());
            $draw->setFontSize($fontSize);
            $draw->setFillColor(new ImagickPixel($textFarbe));
            $draw->setTextAntialias(true);
            $draw->setTextAlignment(Imagick::ALIGN_CENTER);
            
            $image->annotateImage($draw, $x, $y + 8, 0, $text);
        }
    }

    public function berechneRingwert($x, $y)
    {
        $xMm = $this->transformiereKoordinate((float) $x);
        $yMm = $this->transformiereKoordinate((float) $y);
        $distanz = sqrt($xMm * $xMm + $yMm * $yMm);

        if ($distanz <= $this->ringRadien['mouche']) {
            return 10;
        }

        for ($ring = 10; $ring >= 1; $ring--) {
            if ($distanz <= $this->ringRadien[$ring]) {
                return $ring;
            }
        }

        return 0;
    }

    public function setzeKoordinatenFaktor($faktor)
    {
        $this->koordinatenFaktor = (float) $faktor;
    }

    private function sollteZoomen(array $treffer)
    {
        if (empty($treffer)) {
            return false;
        }

        foreach ($treffer as $trefferDaten) {
            if (!isset($trefferDaten['wert'])) {
                continue;
            }

            $wert = (int) $trefferDaten['wert'];

            if ($wert < 6) {
                return false;
            }
        }

        return true;
    }

    private function transformiereKoordinate($wert)
    {
        return ((float) $wert) * $this->koordinatenFaktor;
    }
}
