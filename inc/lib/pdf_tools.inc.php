<?php
/**
 * pdf_tools.inc.php — kleine PDF-Werkzeuge über die Server-Binaries (Ghostscript, pdfinfo)
 *
 * Hintergrund: iLoveAPI rendert EXCEL-Dateien immer auf Letter (792×612 pt), auch wenn die Arbeitsmappe
 * A4 vorgibt (paperSize=9, paperWidth/paperHeight werden ignoriert); Word-Dateien kommen korrekt als A4.
 * Excel skaliert den Inhalt dabei auf die schmalere Letter-Breite, und QZ Tray passt die Letter-Seite
 * nochmals in A4 ein → der Ausdruck füllt das Blatt nicht (rund 18 % Breite verschenkt).
 * pdfInhaltAufFormat() misst den Inhalt (gs -sDEVICE=bbox) und legt ihn skaliert und mit den gewünschten
 * Rändern auf das Zielformat (gs pdfwrite mit BeginPage-Transformation).
 *
 * Voraussetzung auf Hostpoint: /usr/local/bin/gs und /usr/local/bin/pdfinfo (siehe CLAUDE.md, «Externe Werkzeuge»).
 */

/** Umgebung für externe Werkzeuge aus Web-PHP (PATH ohne /usr/local/bin, HOME evtl. leer). */
function msvShellEnv(): string
{
    $home = getenv('HOME') ?: dirname(__DIR__, 3); // /home/<user> oberhalb von www/<docroot>/inc/lib
    return 'HOME=' . escapeshellarg($home) . ' PATH=/usr/local/bin:/usr/bin:/bin TMPDIR=' . escapeshellarg(sys_get_temp_dir()) . ' ';
}

/** Seitengrösse der ersten Seite in Punkt [breite, hoehe] oder null. */
function pdfSeitengroesse(string $pdf): ?array
{
    $out = [];
    exec(msvShellEnv() . 'pdfinfo ' . escapeshellarg($pdf) . ' 2>/dev/null', $out);
    foreach ($out as $zeile) {
        if (preg_match('/^Page size:\s+([\d.]+)\s+x\s+([\d.]+)/', $zeile, $m)) {
            return [(float)$m[1], (float)$m[2]];
        }
    }
    return null;
}

/** Inhalts-Bounding-Box der ersten Seite [x0, y0, x1, y1] in Punkt oder null. */
function pdfInhaltsBox(string $pdf): ?array
{
    $out = [];
    exec(msvShellEnv() . 'gs -q -dNOPAUSE -dBATCH -sDEVICE=bbox -dFirstPage=1 -dLastPage=1 ' . escapeshellarg($pdf) . ' 2>&1', $out);
    foreach ($out as $zeile) {
        if (preg_match('/^%%HiResBoundingBox:\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)/', $zeile, $m)) {
            return [(float)$m[1], (float)$m[2], (float)$m[3], (float)$m[4]];
        }
    }
    return null;
}

/**
 * Legt den Inhalt eines PDFs (alle Seiten, eine gemeinsame Skalierung nach Seite 1) auf ein Zielformat.
 *
 * @param string $pdf      Quelle
 * @param float  $breite   Zielbreite in Punkt (A4 quer: 842)
 * @param float  $hoehe    Zielhöhe in Punkt (A4 quer: 595)
 * @param array  $rand     ['l' => pt, 'o' => pt, 'r' => pt, 'u' => pt] gewünschte Mindestränder
 * @return string Pfad der neuen Temp-Datei (Caller löscht); bei Fehlern wird die Quelle unverändert zurückgegeben
 */
function pdfInhaltAufFormat(string $pdf, float $breite, float $hoehe, array $rand = []): string
{
    $l = (float)($rand['l'] ?? 18); $o = (float)($rand['o'] ?? 18);
    $r = (float)($rand['r'] ?? 18); $u = (float)($rand['u'] ?? 18);

    $box = pdfInhaltsBox($pdf);
    if (!$box) {
        error_log('[pdf_tools] Inhalts-Box nicht ermittelbar, PDF bleibt unverändert: ' . basename($pdf));
        return $pdf;
    }
    [$x0, $y0, $x1, $y1] = $box;
    $bw = max(1.0, $x1 - $x0);
    $bh = max(1.0, $y1 - $y0);
    $s  = min(($breite - $l - $r) / $bw, ($hoehe - $o - $u) / $bh);
    // Inhalt innerhalb des Randrahmens zentrieren: Vorder- und Rückseite (Duplex) sitzen so gleich,
    // und kein Rand fällt in den nicht bedruckbaren Bereich des Druckers (typisch 4–5 mm).
    $tx = $l + (($breite - $l - $r) - $bw * $s) / 2 - $x0 * $s;
    $ty = $u + (($hoehe - $o - $u) - $bh * $s) / 2 - $y0 * $s;

    $ziel = tempnam(sys_get_temp_dir(), 'pdfa4_');
    rename($ziel, $ziel . '.pdf'); $ziel .= '.pdf';

    $cmd = msvShellEnv() . sprintf(
        'gs -q -o %s -sDEVICE=pdfwrite -dDEVICEWIDTHPOINTS=%d -dDEVICEHEIGHTPOINTS=%d -dFIXEDMEDIA -dAutoRotatePages=/None '
        . '-c "<</BeginPage {%.4F %.4F translate %.4F %.4F scale}>> setpagedevice" -f %s 2>&1',
        escapeshellarg($ziel), (int)round($breite), (int)round($hoehe), $tx, $ty, $s, $s, escapeshellarg($pdf)
    );
    $out = []; $rc = 0;
    exec($cmd, $out, $rc);
    if ($rc !== 0 || !is_file($ziel) || filesize($ziel) < 500) {
        error_log('[pdf_tools] gs fehlgeschlagen (rc=' . $rc . '): ' . implode(' ', array_slice($out, -3)));
        @unlink($ziel);
        return $pdf;
    }
    return $ziel;
}

/**
 * Komfort: PDF auf A4 quer bringen, falls es nicht schon A4 ist (Letter-Ausgabe von iLoveAPI bei Excel).
 * Sicherheitsrand rundum 8 mm (23 pt): die Excel-Vorlage des Endschiessen-Standblatts hat unten 0 Rand,
 * mit 2 mm Abstand wurde die untere Zeile im Druck abgeschnitten (16.09.2026).
 */
function pdfAufA4QuerFallsNoetig(string $pdf, array $rand = ['l' => 23, 'o' => 23, 'r' => 23, 'u' => 23]): string
{
    $g = pdfSeitengroesse($pdf);
    if ($g && abs($g[0] - 842) < 3 && abs($g[1] - 595) < 3) {
        return $pdf; // schon A4 quer
    }
    return pdfInhaltAufFormat($pdf, 842, 595, $rand);
}
