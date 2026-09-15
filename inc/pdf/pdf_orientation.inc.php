<?php
/**
 * pdf_orientation.inc.php — Seitenausrichtung eines Ranglisten-PDFs aus dem Request
 *
 * Die Drucksteuerung erlaubt pro Druckprofil «A4 Portrait» oder «A4 Landscape» (print_profiles.orientation).
 * Direktdruck (js/msv-direktdruck.js, Resolver) und die PDF-Download-Buttons der Seiten geben die Wahl als
 * Parameter «orientation» mit; die Generatoren lesen sie hier und setzen $dompdf->setPaper('A4', …) sowie –
 * wo vorhanden – die CSS-Regel @page { size: A4 <orientation> } (Dompdf: CSS gewinnt über setPaper).
 *
 * Verwendung im Generator:
 *   require_once __DIR__ . '/../pdf/pdf_orientation.inc.php';
 *   $dompdf->setPaper('A4', pdfOrientationParam('portrait'));   // Default = bisheriges Format
 *
 * Ohne Parameter bleibt alles wie bisher; ungültige Werte fallen auf den Default zurück.
 */

/**
 * @param string $default 'portrait' | 'landscape' — Format des Generators, wenn kein Parameter kommt
 */
function pdfOrientationParam(string $default = 'portrait'): string
{
    $o = strtolower(trim((string)($_GET['orientation'] ?? $_POST['orientation'] ?? '')));
    if ($o === 'p' || $o === 'hoch') $o = 'portrait';
    if ($o === 'l' || $o === 'quer') $o = 'landscape';
    return in_array($o, ['portrait', 'landscape'], true) ? $o : $default;
}
