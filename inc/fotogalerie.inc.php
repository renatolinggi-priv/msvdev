<?php
// inc/fotogalerie.inc.php
// Gemeinsame Helfer fuer die Foto-Galerie (Upload, Bildverarbeitung, EXIF,
// Schiesstage-Zuordnung, Datei-Cleanup, Berechtigungen).
//
// Der "Anlass" ist ein JM-Anlass (JMDefinition). Pro Anlass kann der Vorstand
// eine Galerie freischalten. Fotos werden per EXIF-Aufnahmedatum den
// Schiesstagen (JMDefinition.Schiesstage) zugeordnet -> Slideshow "zum Programm".
//
// Bildtreiber: Intervention Image v3. Ist die PHP-Extension imagick vorhanden
// (Prod: ImageMagick 7 mit HEIC/HEIF/AVIF), wird sie benutzt — sie braucht bei
// grossen Originalen deutlich weniger Speicher als GD und kann HEIC lesen.
// Fehlt sie, faellt alles automatisch auf GD zurueck (dann kein HEIC).

if (!defined('FOTO_MAX_FULL'))   define('FOTO_MAX_FULL', 2560);   // laengste Kante Full-Version
if (!defined('FOTO_MAX_MEDIUM')) define('FOTO_MAX_MEDIUM', 1280); // laengste Kante Medium (Uebersicht, Handy-Slideshow)
if (!defined('FOTO_THUMB'))      define('FOTO_THUMB', 480);       // Thumbnail (quadratisch)
if (!defined('FOTO_MAX_BYTES'))  define('FOTO_MAX_BYTES', 15 * 1024 * 1024); // 15 MB pro Datei

/** Bildtreiber fuer Intervention: 'imagick' wenn verfuegbar, sonst 'gd'. */
function fotoTreiber(): string {
    static $t = null;
    if ($t === null) {
        $t = (extension_loaded('imagick') && class_exists('Imagick')) ? 'imagick' : 'gd';
    }
    return $t;
}

/** HEIC/HEIF-Upload moeglich? Nur mit Imagick, dessen ImageMagick libheif eingebaut hat. */
function fotoHeicUnterstuetzt(): bool {
    static $ok = null;
    if ($ok === null) {
        $ok = false;
        if (fotoTreiber() === 'imagick') {
            try {
                $fmts = array_map('strtoupper', (array) Imagick::queryFormats('HEI*'));
                $ok = in_array('HEIC', $fmts, true);
            } catch (Throwable $e) {
                $ok = false;
            }
        }
    }
    return $ok;
}

/**
 * Erlaubte Bildtypen (finfo-MIME => Endung). HEIC/HEIF nur, wenn der Server sie
 * lesen kann (GD kann es nicht).
 * @return array<string,string>
 */
function fotoErlaubteMimes(): array {
    $m = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (fotoHeicUnterstuetzt()) {
        $m['image/heic'] = 'heic';
        $m['image/heif'] = 'heif';
    }
    return $m;
}

/** Wert fuer das accept-Attribut des Datei-Inputs (passend zu fotoErlaubteMimes()). */
function fotoAcceptAttribut(): string {
    $a = 'image/jpeg,image/png,image/webp';
    if (fotoHeicUnterstuetzt()) $a .= ',image/heic,image/heif,.heic,.heif';
    return $a;
}

// Rueckwaertskompatibel (aeltere Aufrufer lesen $GLOBALS['FOTO_ALLOWED_MIMES'])
if (!isset($GLOBALS['FOTO_ALLOWED_MIMES'])) {
    $GLOBALS['FOTO_ALLOWED_MIMES'] = fotoErlaubteMimes();
}

/** Basisverzeichnis aller Foto-Galerien (absolut). */
function fotoBasisDir(): string {
    return __DIR__ . '/../portal/uploads/fotos/';
}

/**
 * Verzeichnis einer Galerie (legt es bei Bedarf an).
 * $sub: false = Full-Version, true oder 'thumbs' = Thumbnails, 'medium' = Medium-Version.
 */
function fotoGalerieDir(int $galerieId, bool|string $sub = false): string {
    if ($sub === true) $sub = 'thumbs';
    $dir = fotoBasisDir() . $galerieId . '/' . ($sub ? rtrim($sub, '/') . '/' : '');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/** Pfad der Medium-Version zu einem Full-Pfad (Konvention: <galerie>/medium/<dateiname>). */
function fotoMediumPfad(string $fullPfad): string {
    return dirname($fullPfad) . '/medium/' . basename($fullPfad);
}

/** Globaler Schalter settings.fotogalerie_aktiv (Default an). Pro Request gecacht. */
function fotoFeatureAktiv(): bool {
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $stmt = getDB()->prepare("SELECT setting_value FROM settings WHERE setting_key = 'fotogalerie_aktiv'");
        $stmt->execute();
        $v = $stmt->fetchColumn();
        // Default an, falls Zeile fehlt
        $cached = ($v === false || $v === null) ? true : ($v === '1' || $v === 1);
    } catch (Throwable $e) {
        $cached = true;
    }
    return $cached;
}

/** EXIF-Zeitstring "Y:m:d H:i:s" -> "Y-m-d H:i:s" oder null. */
function fotoExifZeitParsen(?string $val): ?string {
    $val = trim((string) $val);
    if ($val === '' || $val === '0000:00:00 00:00:00') return null;
    $dt = DateTime::createFromFormat('Y:m:d H:i:s', $val);
    return ($dt instanceof DateTime) ? $dt->format('Y-m-d H:i:s') : null;
}

/**
 * Liest das Aufnahmedatum aus den EXIF-Daten.
 * @return array{0:?string,1:?string} [ 'Y-m-d H:i:s' | null , 'exif' | null ]
 * Stufe 1: exif_read_data() (JPEG/TIFF). Stufe 2: Imagick-Eigenschaften
 * exif:DateTimeOriginal (deckt HEIC/HEIF/WebP ab, sofern ImageMagick sie liefert).
 * Fehlt beides, ist der Rueckgabewert null — der Aufrufer kann auf das
 * Dateidatum des Clients zurueckfallen (fotoAufnahmeAusDateidatum).
 */
function fotoExifAufnahme(string $path): array {
    $keys = ['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'];

    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        if (is_array($exif)) {
            foreach ($keys as $key) {
                $z = fotoExifZeitParsen($exif[$key] ?? ($exif['EXIF'][$key] ?? null));
                if ($z !== null) return [$z, 'exif'];
            }
        }
    }

    if (fotoTreiber() === 'imagick') {
        try {
            $im = new Imagick();
            $im->pingImage($path);
            $props = $im->getImageProperties('exif:DateTime*');
            if (!$props) {
                // Ping liefert bei manchen Formaten keine Metadaten -> vollstaendig lesen
                $im->clear();
                $im->readImage($path);
                $props = $im->getImageProperties('exif:DateTime*');
            }
            $im->clear();
            foreach ($keys as $key) {
                $z = fotoExifZeitParsen($props['exif:' . $key] ?? null);
                if ($z !== null) return [$z, 'exif'];
            }
        } catch (Throwable $e) {
            // kein EXIF lesbar
        }
    }
    return [null, null];
}

/**
 * Fallback-Aufnahmezeit aus dem Dateidatum, das der Browser mitschickt
 * (File.lastModified, Millisekunden seit 1970 UTC). WhatsApp- und Screenshot-
 * Bilder haben kein EXIF; ihr Dateidatum liegt aber meist am richtigen Tag.
 * Plausibel = zwischen 2000 und morgen. Rueckgabe in Europe/Zurich.
 */
function fotoAufnahmeAusDateidatum(mixed $lastModifiedMs): ?string {
    if ($lastModifiedMs === null || $lastModifiedMs === '' || !is_numeric($lastModifiedMs)) return null;
    $sec = (int) floor(((float) $lastModifiedMs) / 1000);
    if ($sec < 946684800 || $sec > time() + 86400) return null; // 2000-01-01 .. morgen
    try {
        $dt = (new DateTime('@' . $sec))->setTimezone(new DateTimeZone('Europe/Zurich'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Parst JMDefinition.Schiesstage (mehrzeiliger Freitext) in Tag-Segmente.
 * Beispielzeilen: "Freitag 23. Mai 2025 18:00 - 20:00", "Samstag 8. August 17.00 - 20.00"
 * Fehlt die Jahreszahl (haeufig!), wird $jahrFallback (= JMDefinition.year) genommen.
 * @return array<int,array{index:int,datum:string,label:string}> sortiert nach Datum
 */
function fotoSchiesstageSegmente(?string $text, ?int $jahrFallback = null): array {
    if ($text === null || trim($text) === '') return [];

    $monate = [
        'januar' => 1, 'jan' => 1, 'februar' => 2, 'feb' => 2,
        'maerz' => 3, 'märz' => 3, 'mär' => 3, 'mrz' => 3,
        'april' => 4, 'apr' => 4, 'mai' => 5, 'juni' => 6, 'jun' => 6,
        'juli' => 7, 'jul' => 7, 'august' => 8, 'aug' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9, 'oktober' => 10, 'okt' => 10,
        'november' => 11, 'nov' => 11, 'dezember' => 12, 'dez' => 12,
    ];

    $dates = [];
    // "8. August 2025" oder "8. August" (Jahr optional; "17.00" danach ist keine Jahreszahl)
    if (preg_match_all('/(\d{1,2})\.\s*([A-Za-zäöüÄÖÜ]+)\.?(?:\s+(\d{4}))?/u', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $tag  = (int) $m[1];
            $mon  = $monate[mb_strtolower($m[2], 'UTF-8')] ?? null;
            $jahr = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : ($jahrFallback ?: null);
            if ($mon && $jahr && $tag >= 1 && $tag <= 31 && checkdate($mon, $tag, $jahr)) {
                $dates[sprintf('%04d-%02d-%02d', $jahr, $mon, $tag)] = true;
            }
        }
    }
    if (!$dates) return [];

    $list = array_keys($dates);
    sort($list);

    $wochentage = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    $monNamen   = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    $segs = [];
    $i = 1;
    foreach ($list as $d) {
        $ts = strtotime($d);
        $label = 'Tag ' . $i . ' · ' . $wochentage[(int) date('w', $ts)] . ', '
               . (int) date('j', $ts) . '. ' . $monNamen[(int) date('n', $ts)];
        $segs[] = ['index' => $i, 'datum' => $d, 'label' => $label];
        $i++;
    }
    return $segs;
}

/**
 * Ordnet ein Aufnahmedatum einem Schiesstag zu.
 * @return array{tag_datum:?string,tag_index:?int}
 *  - exakter Schiesstag -> dessen index (nummerierter "Tag N")
 *  - sonst               -> eigenes Aufnahmedatum, index NULL (eigene Datums-Gruppe)
 *  - kein Aufnahmedatum  -> NULL/NULL (Sammel-Tag "Weitere Fotos")
 *
 * WICHTIG: Es wird NICHT auf den naechstfrueheren Schiesstag "eingerastet" — sonst
 * landen z.B. Samstag-Fotos faelschlich beim Freitag, wenn der Samstag in den
 * Schiesstagen fehlt oder anders formatiert ist. Das Foto bekommt dann eine eigene
 * Tagesgruppe nach seinem echten Aufnahmedatum.
 */
function fotoTagInfo(?string $aufnahme, array $segmente): array {
    if (!$aufnahme) return ['tag_datum' => null, 'tag_index' => null];
    $d = substr($aufnahme, 0, 10);

    foreach ($segmente as $s) {
        if ($s['datum'] === $d) return ['tag_datum' => $s['datum'], 'tag_index' => $s['index']];
    }
    return ['tag_datum' => $d, 'tag_index' => null];
}

/**
 * Sortier-Reihenfolge fuer Galerie, Slideshow und Admin-Ansicht (SQL-Fragment ohne
 * "ORDER BY", $a = Tabellen-Alias inkl. Punkt oder ''). Fotos ohne vergebene Position
 * (sortierung = 0, z.B. aeltere Uploads) kommen ans Ende ihres Tages, nicht davor.
 */
function fotoOrderBySql(string $a = ''): string {
    return "({$a}tag_datum IS NULL) ASC, {$a}tag_datum ASC, {$a}tag_index ASC,
            ({$a}sortierung = 0) ASC, {$a}sortierung ASC, {$a}aufnahme_zeit ASC, {$a}id ASC";
}

/** Naechste freie Position in einer Galerie (neue Uploads reihen sich hinten ein). */
function fotoSortierungNaechste(PDO $db, int $galerieId): int {
    $st = $db->prepare("SELECT COALESCE(MAX(sortierung), 0) + 1 FROM anlass_fotos WHERE galerie_id = ?");
    $st->execute([$galerieId]);
    return (int) $st->fetchColumn();
}

/** Intervention-ImageManager mit dem verfuegbaren Treiber. */
function fotoImageManager(): \Intervention\Image\ImageManager {
    require_once __DIR__ . '/vendor/autoload.php';
    return fotoTreiber() === 'imagick'
        ? \Intervention\Image\ImageManager::imagick()
        : \Intervention\Image\ImageManager::gd();
}

/**
 * Verarbeitet eine hochgeladene Bilddatei: Auto-Orientierung, Full-Version
 * (max. FOTO_MAX_FULL), Medium-Version (max. FOTO_MAX_MEDIUM) + quadratisches
 * Thumbnail (FOTO_THUMB), alle als JPG ohne Metadaten (Imagick) bzw. wie GD sie schreibt.
 * @return array{dateiname:string,dateipfad:string,thumb_pfad:string,medium_pfad:string,breite:int,hoehe:int}
 * @throws RuntimeException bei Verarbeitungsfehlern
 */
function fotoSpeichereBild(string $tmpPath, int $galerieId, string $origName): array {
    $base = pathinfo($origName, PATHINFO_FILENAME);
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $base);
    $safe = substr($safe, 0, 40);
    if ($safe === '') $safe = 'foto';
    $fname = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safe . '.jpg';

    $fullPath   = fotoGalerieDir($galerieId) . $fname;
    $mediumPath = fotoGalerieDir($galerieId, 'medium') . $fname;
    $thumbPath  = fotoGalerieDir($galerieId, true) . $fname;

    try {
        $img = fotoImageManager()->read($tmpPath);
        $img->orient(); // EXIF-Orientierung anwenden

        // Full-Version
        $img->scaleDown(FOTO_MAX_FULL, FOTO_MAX_FULL);
        $img->save($fullPath, quality: 82);
        $w = $img->width();
        $h = $img->height();

        // Medium-Version aus der bereits verkleinerten Full-Version
        $img->scaleDown(FOTO_MAX_MEDIUM, FOTO_MAX_MEDIUM);
        $img->save($mediumPath, quality: 80);

        // Thumbnail (quadratischer Ausschnitt)
        $img->cover(FOTO_THUMB, FOTO_THUMB);
        $img->save($thumbPath, quality: 78);
    } catch (Throwable $e) {
        foreach ([$fullPath, $mediumPath, $thumbPath] as $p) { if (is_file($p)) @unlink($p); }
        error_log('[fotogalerie] Bildverarbeitung (' . fotoTreiber() . ') fehlgeschlagen: ' . $e->getMessage());
        throw new RuntimeException('Bild konnte nicht verarbeitet werden.');
    }

    return [
        'dateiname'   => $fname,
        'dateipfad'   => $fullPath,
        'thumb_pfad'  => $thumbPath,
        'medium_pfad' => $mediumPath,
        'breite'      => $w,
        'hoehe'       => $h,
    ];
}

/**
 * Liefert den Pfad der Medium-Version eines Fotos und erzeugt sie bei Bedarf aus der
 * Full-Version (Fotos von vor der Einfuehrung der Medium-Groesse). Gibt bei Fehlern
 * den Full-Pfad zurueck, damit die Anzeige nie leer bleibt.
 */
function fotoMediumSicherstellen(array $foto): string {
    $full = (string) ($foto['dateipfad'] ?? '');
    if ($full === '' || !is_file($full)) return $full;
    $medium = fotoMediumPfad($full);
    if (is_file($medium)) return $medium;
    if (!fotoPfadErlaubt($full)) return $full;

    $dir = dirname($medium);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return $full;
    try {
        $img = fotoImageManager()->read($full);
        $img->scaleDown(FOTO_MAX_MEDIUM, FOTO_MAX_MEDIUM);
        // erst temporaer schreiben, dann umbenennen -> parallele Requests sehen nie eine halbe Datei
        $tmp = $medium . '.' . bin2hex(random_bytes(3)) . '.tmp';
        $img->save($tmp, quality: 80);
        if (!@rename($tmp, $medium)) { @unlink($tmp); return is_file($medium) ? $medium : $full; }
        return $medium;
    } catch (Throwable $e) {
        error_log('[fotogalerie] Medium-Erzeugung fehlgeschlagen: ' . $e->getMessage());
        return $full;
    }
}

/** Loescht die physischen Dateien (Full + Medium + Thumb) eines Foto-Datensatzes. */
function fotoUnlinkDateien(array $foto): void {
    $pfade = [$foto['dateipfad'] ?? null, $foto['thumb_pfad'] ?? null];
    if (!empty($foto['dateipfad'])) $pfade[] = fotoMediumPfad((string) $foto['dateipfad']);
    foreach ($pfade as $p) {
        if (!empty($p) && is_file($p)) {
            @unlink($p);
        }
    }
}

/** Entfernt das komplette Verzeichnis einer Galerie (inkl. thumbs/ und medium/). */
function fotoLoescheGalerieDir(int $galerieId): void {
    $dir = fotoBasisDir() . $galerieId;
    if (!is_dir($dir)) return;
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($rii as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($dir);
}

/** Prueft, ob ein Pfad innerhalb des Galerie-Basisverzeichnisses liegt (Path-Traversal-Schutz). */
function fotoPfadErlaubt(?string $pfad): bool {
    if (!$pfad) return false;
    $real = realpath($pfad);
    $base = realpath(fotoBasisDir());
    if ($real === false || $base === false) return false;
    return strpos($real, $base) === 0;
}

/** Laedt eine Galerie inkl. JM-Anlass-Daten (Bezeichnung, Jahr, Schiesstage, Adresse). */
function fotoGalerieLaden(PDO $db, int $galerieId): ?array {
    $st = $db->prepare(
        "SELECT g.*, d.Bezeichnung AS anlass_name, d.year AS jahr, d.Schiesstage, d.Adresse
           FROM anlass_galerie g
           JOIN JMDefinition d ON d.ID = g.jmdefinition_id
          WHERE g.id = ?"
    );
    $st->execute([$galerieId]);
    $row = $st->fetch();
    return $row ?: null;
}

/** Schiesstage-Segmente einer geladenen Galerie (mit Jahr-Fallback aus dem Anlass). */
function fotoGalerieSegmente(array $g): array {
    return fotoSchiesstageSegmente($g['Schiesstage'] ?? null, isset($g['jahr']) ? (int) $g['jahr'] : null);
}
