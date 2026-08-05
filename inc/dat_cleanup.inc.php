<?php
/**
 * inc/dat_cleanup.inc.php - Aufraeumen der generierten Exportdateien in inc/<modul>/dat/
 *
 * PROBLEM: Praktisch jeder Export-Generator schreibt pro Aufruf eine NEUE zeitgestempelte
 * Datei und raeumt nie auf. Stand 05.08.2026: jmdefinition/dat war bei 965 Dateien,
 * wichtigetermine/dat bei 198, jmrang/dat bei 150, kantirang/dat bei 128, heimrang/dat
 * bei 114 -- zusammen rund 19 MB regenerierbarer PDFs.
 *
 * GEFAHR: In genau denselben Verzeichnissen liegen Dateien, die NICHT geloescht werden
 * duerfen:
 *   - MSVWilen_Logo.jpg / SKSG_Logo.jpg  -> inc/pdf_design.php verteilt das Logo in jedes
 *     Modul-dat/, inc/home.php bindet jmrang/dat/MSVWilen_Logo.jpg direkt als <img> ein
 *   - Vorlagen: Resultatbuch_Template20251015.docx, Resultatbuch_V1..V3.docx,
 *     VorlageFragebogen.docx, Kantonalstich_Abrechnungsformular_ab-2023.xlsm,
 *     Adressliste_MiFu_20260227.xlsx
 *
 * DESHALB wird nicht per Wildcard geloescht, sondern ausschliesslich was die Signatur einer
 * generierten Datei traegt: ein Zeitstempel _YYYY-MM-DD_HH-MM-SS unmittelbar vor der
 * Endung. Keine der oben genannten Schutz-Dateien erfuellt dieses Muster.
 *
 * Gruppierung erfolgt pro Namens-Praefix (der Teil vor dem Zeitstempel) UND Endung. So
 * behaelt jede Dokumentart ihre eigenen N neuesten Staende, z.B. "Jahresprogramm_2025"
 * getrennt von "Jahresprogramm_2026" und von "Jahresprogramm_2026_ENTWURF".
 */

// Signatur einer generierten Datei: <praefix>_YYYY-MM-DD_HH-MM-SS.<endung>
if (!defined('DAT_STAMP_PATTERN')) {
    define('DAT_STAMP_PATTERN', '/^(.+)_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.([A-Za-z0-9]{1,5})$/');
}

/**
 * Raeumt ein dat/-Verzeichnis auf: behaelt je Praefix+Endung die $behalten neuesten
 * generierten Dateien, loescht den Rest. Dateien ohne Zeitstempel-Signatur werden
 * nicht angefasst.
 *
 * @param string $dir       Absoluter Pfad zum dat/-Verzeichnis
 * @param int    $behalten  Anzahl neuester Staende je Praefix (mindestens 1)
 * @param bool   $nurTesten true = nichts loeschen, nur melden was geloescht wuerde
 * @return array{verzeichnis:string, kandidaten:int, geloescht:int, bytes:int,
 *               geschuetzt:int, dateien:string[], fehler:string[]}
 */
function datAufraeumen(string $dir, int $behalten = 5, bool $nurTesten = false): array {
    $behalten = max(1, $behalten);
    $ergebnis = [
        'verzeichnis' => $dir,
        'kandidaten'  => 0,
        'geloescht'   => 0,
        'bytes'       => 0,
        'geschuetzt'  => 0,
        'dateien'     => [],
        'fehler'      => [],
    ];

    $basis = realpath($dir);
    if ($basis === false || !is_dir($basis)) {
        $ergebnis['fehler'][] = 'Verzeichnis nicht gefunden: ' . $dir;
        return $ergebnis;
    }

    $eintraege = scandir($basis);
    if ($eintraege === false) {
        $ergebnis['fehler'][] = 'Verzeichnis nicht lesbar: ' . $basis;
        return $ergebnis;
    }

    // Nach Praefix + Endung gruppieren
    $gruppen = [];
    foreach ($eintraege as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $pfad = $basis . DIRECTORY_SEPARATOR . $name;
        // Nur echte Dateien; Symlinks und Unterverzeichnisse bleiben unangetastet
        if (!is_file($pfad) || is_link($pfad)) {
            continue;
        }
        if (!preg_match(DAT_STAMP_PATTERN, $name, $m)) {
            $ergebnis['geschuetzt']++; // Logo, Vorlage, sonstiges -> nie loeschen
            continue;
        }
        $schluessel = $m[1] . '.' . strtolower($m[2]);
        $gruppen[$schluessel][] = ['pfad' => $pfad, 'name' => $name, 'mtime' => (int) filemtime($pfad)];
    }

    foreach ($gruppen as $dateien) {
        $ergebnis['kandidaten'] += count($dateien);
        if (count($dateien) <= $behalten) {
            continue;
        }
        // Neueste zuerst; bei gleicher mtime nach Name (Zeitstempel im Namen) absteigend,
        // damit die Reihenfolge deterministisch ist.
        usort($dateien, static function ($a, $b) {
            return ($b['mtime'] <=> $a['mtime']) ?: strcmp($b['name'], $a['name']);
        });

        foreach (array_slice($dateien, $behalten) as $weg) {
            // Doppelte Absicherung: Ziel muss innerhalb des aufzuraeumenden Verzeichnisses liegen
            $real = realpath($weg['pfad']);
            if ($real === false || strpos($real, $basis . DIRECTORY_SEPARATOR) !== 0) {
                $ergebnis['fehler'][] = 'Ausserhalb des Zielverzeichnisses, uebersprungen: ' . $weg['name'];
                continue;
            }
            $groesse = (int) filesize($real);
            if ($nurTesten) {
                $ergebnis['geloescht']++;
                $ergebnis['bytes'] += $groesse;
                $ergebnis['dateien'][] = $weg['name'];
                continue;
            }
            if (@unlink($real)) {
                $ergebnis['geloescht']++;
                $ergebnis['bytes'] += $groesse;
                $ergebnis['dateien'][] = $weg['name'];
            } else {
                $ergebnis['fehler'][] = 'Loeschen fehlgeschlagen: ' . $weg['name'];
            }
        }
    }

    return $ergebnis;
}

/**
 * Registriert das Aufraeumen des dat/-Verzeichnisses, das zum aktuell laufenden Skript
 * gehoert -- ausgefuehrt NACH der Ausgabe (register_shutdown_function), damit es eine
 * Antwort niemals stoeren kann.
 *
 * Aufgerufen wird das zentral aus inc/config.php. Damit ist jeder Generator unter
 * inc/<modul>/ automatisch abgedeckt, ohne dass 25 Schreibstellen einzeln angefasst
 * werden muessen -- auch kuenftige. Skripte, in deren Verzeichnis es kein dat/ gibt,
 * tun nichts.
 *
 * Fehler werden hier bewusst NICHT geloggt: bei parallelen Requests kann ein unlink
 * ins Leere laufen, weil der andere Request dieselbe Datei schon entfernt hat. Das ist
 * harmlos und soll das Error-Log nicht fluten. Der Cron cron/cleanup_dat.php meldet
 * Fehler dagegen sehr wohl.
 *
 * Abschalten fuer einen einzelnen Aufruf: define('DAT_CLEANUP_AUS', true) vor dem
 * Einbinden von inc/config.php.
 */
function datAufraeumenNachAusgabe(int $behalten = 5): void {
    if (defined('DAT_CLEANUP_AUS') && DAT_CLEANUP_AUS) {
        return;
    }
    $skript = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($skript === '') {
        return;
    }
    $dir = dirname($skript) . DIRECTORY_SEPARATOR . 'dat';
    if (!is_dir($dir)) {
        return; // Modul ohne Exportverzeichnis -> nichts zu tun
    }
    register_shutdown_function(static function () use ($dir, $behalten) {
        try {
            datAufraeumen($dir, $behalten);
        } catch (\Throwable $e) {
            // Ein Aufraeumfehler darf einen fertig ausgelieferten Export nie beeinflussen.
        }
    });
}

/**
 * Liefert alle Modul-dat/-Verzeichnisse unter inc/.
 *
 * @return string[] Absolute Pfade
 */
function datVerzeichnisse(): array {
    $treffer = glob(__DIR__ . '/*/dat', GLOB_ONLYDIR) ?: [];
    sort($treffer);
    return $treffer;
}
