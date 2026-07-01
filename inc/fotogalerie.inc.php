<?php
// inc/fotogalerie.inc.php
// Gemeinsame Helfer fuer die Foto-Galerie (Upload, Bildverarbeitung, EXIF,
// Schiesstage-Zuordnung, Datei-Cleanup, Berechtigungen).
//
// Der "Anlass" ist ein JM-Anlass (JMDefinition). Pro Anlass kann der Vorstand
// eine Galerie freischalten. Fotos werden per EXIF-Aufnahmedatum den
// Schiesstagen (JMDefinition.Schiesstage) zugeordnet -> Slideshow "zum Programm".

if (!defined('FOTO_MAX_FULL'))  define('FOTO_MAX_FULL', 2560);   // laengste Kante Full-Version
if (!defined('FOTO_THUMB'))     define('FOTO_THUMB', 480);       // Thumbnail (quadratisch)
if (!defined('FOTO_MAX_BYTES')) define('FOTO_MAX_BYTES', 15 * 1024 * 1024); // 15 MB pro Datei

// Erlaubte Bildtypen (finfo-MIME => Endung). HEIC fehlt bewusst (GD kann es nicht lesen).
if (!isset($GLOBALS['FOTO_ALLOWED_MIMES'])) {
    $GLOBALS['FOTO_ALLOWED_MIMES'] = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
}

/** Basisverzeichnis aller Foto-Galerien (absolut). */
function fotoBasisDir(): string {
    return __DIR__ . '/../portal/uploads/fotos/';
}

/** Verzeichnis einer Galerie (legt es bei Bedarf an). $thumbs=true -> thumbs/-Unterordner. */
function fotoGalerieDir(int $galerieId, bool $thumbs = false): string {
    $dir = fotoBasisDir() . $galerieId . '/' . ($thumbs ? 'thumbs/' : '');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
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

/**
 * Liest das Aufnahmedatum aus den EXIF-Daten.
 * @return array{0:?string,1:?string} [ 'Y-m-d H:i:s' | null , 'exif' | null ]
 * Hinweis: Intervention Image v3 hat KEINEN eigenen EXIF-Parser -> exif_read_data().
 * Fehlt die PHP-exif-Extension oder das Datum, ist der Rueckgabewert null
 * (Foto landet im Sammel-Tag "Weitere Fotos").
 */
function fotoExifAufnahme(string $path): array {
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        if (is_array($exif)) {
            foreach (['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'] as $key) {
                $val = $exif[$key] ?? ($exif['EXIF'][$key] ?? null);
                if (!empty($val) && $val !== '0000:00:00 00:00:00') {
                    $dt = DateTime::createFromFormat('Y:m:d H:i:s', trim($val));
                    if ($dt instanceof DateTime) {
                        return [$dt->format('Y-m-d H:i:s'), 'exif'];
                    }
                }
            }
        }
    }
    return [null, null];
}

/**
 * Parst JMDefinition.Schiesstage (mehrzeiliger Freitext) in Tag-Segmente.
 * Beispielzeile: "Freitag 23. Mai 2025 18:00 - 20:00"
 * @return array<int,array{index:int,datum:string,label:string}> sortiert nach Datum
 */
function fotoSchiesstageSegmente(?string $text): array {
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
    if (preg_match_all('/(\d{1,2})\.\s*([A-Za-zäöüÄÖÜ]+)\s+(\d{4})/u', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $tag  = (int) $m[1];
            $mon  = $monate[mb_strtolower($m[2], 'UTF-8')] ?? null;
            $jahr = (int) $m[3];
            if ($mon && $tag >= 1 && $tag <= 31) {
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
 * Verarbeitet eine hochgeladene Bilddatei: Auto-Orientierung, Full-Version
 * (max. FOTO_MAX_FULL) + quadratisches Thumbnail (FOTO_THUMB), beide als JPG.
 * @return array{dateiname:string,dateipfad:string,thumb_pfad:string,breite:int,hoehe:int}
 * @throws RuntimeException bei Verarbeitungsfehlern
 */
function fotoSpeichereBild(string $tmpPath, int $galerieId, string $origName): array {
    require_once __DIR__ . '/vendor/autoload.php';

    $base = pathinfo($origName, PATHINFO_FILENAME);
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $base);
    $safe = substr($safe, 0, 40);
    if ($safe === '') $safe = 'foto';
    $fname = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safe . '.jpg';

    try {
        $manager = \Intervention\Image\ImageManager::gd();
        $img = $manager->read($tmpPath);
        $img->orient(); // EXIF-Orientierung anwenden (nur mit exif-Extension wirksam)

        // Full-Version
        $img->scaleDown(FOTO_MAX_FULL, FOTO_MAX_FULL);
        $fullPath = fotoGalerieDir($galerieId) . $fname;
        $img->save($fullPath, quality: 82);
        $w = $img->width();
        $h = $img->height();

        // Thumbnail (quadratischer Ausschnitt) aus der bereits verkleinerten Version
        $img->cover(FOTO_THUMB, FOTO_THUMB);
        $thumbPath = fotoGalerieDir($galerieId, true) . $fname;
        $img->save($thumbPath, quality: 78);
    } catch (Throwable $e) {
        throw new RuntimeException('Bild konnte nicht verarbeitet werden.');
    }

    return [
        'dateiname'  => $fname,
        'dateipfad'  => $fullPath,
        'thumb_pfad' => $thumbPath,
        'breite'     => $w,
        'hoehe'      => $h,
    ];
}

/** Loescht die physischen Dateien (Full + Thumb) eines Foto-Datensatzes. */
function fotoUnlinkDateien(array $foto): void {
    foreach (['dateipfad', 'thumb_pfad'] as $k) {
        if (!empty($foto[$k]) && is_file($foto[$k])) {
            @unlink($foto[$k]);
        }
    }
}

/** Entfernt das komplette Verzeichnis einer Galerie (inkl. thumbs/). */
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
