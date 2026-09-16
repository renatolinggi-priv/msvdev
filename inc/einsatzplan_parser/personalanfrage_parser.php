<?php
/**
 * inc/einsatzplan_parser/personalanfrage_parser.php – Excel «Personalanfrage» des OK Schlossturm lesen.
 *
 * Struktur (rohdaten/Personalanfrage 2026.xlsx): Zeile mit «als:» und «möglich am:» (Kopf), darunter die
 * Rollen (Büro, Schützenmeister, Warner, Türkontrolle, Parkdienst) und die Schichten («Samstag, 18.04.2026
 * 08:00 - 12:00»), ab der Folgezeile Personen «Name, Vorname» in Spalte A mit X-Markierungen.
 *
 * Rückgabe: ['success', 'message', 'rollen' => [col => text], 'schichten' => [col => text],
 *            'zeilen' => [['name', 'nachname', 'vorname', 'rollen' => [text], 'schichten' => [text], 'zeile' => n]]]
 * Die Zuordnung der Texte zu EP_ROLLEN / Plan-Terminen macht der Aufrufer (plan_helpers: ep_rolle_aus_text,
 * ep_termin_aus_text), damit der Parser plan-unabhängig bleibt.
 */
if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function parsePersonalanfrageXlsx(string $filepath): array
{
    if (!is_file($filepath)) return ['success' => false, 'message' => 'Datei nicht gefunden', 'zeilen' => []];
    try {
        $reader = IOFactory::createReaderForFile($filepath);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($filepath)->getSheet(0);
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Excel konnte nicht gelesen werden: ' . $e->getMessage(), 'zeilen' => []];
    }
    $maxRow = $sheet->getHighestRow();
    $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
    $val = fn(int $c, int $r) => trim((string)($sheet->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getFormattedValue() ?? ''));

    // Kopfzeile: «als:» und «möglich am:»
    $kopf = null; $alsCol = null; $moegCol = null;
    for ($r = 1; $r <= min(20, $maxRow) && $kopf === null; $r++) {
        for ($c = 1; $c <= $maxCol; $c++) {
            $v = mb_strtolower($val($c, $r));
            if ($v === 'als:' || $v === 'als') { $alsCol = $c; $kopf = $r; }
            if (str_starts_with($v, 'möglich am')) { $moegCol = $c; $kopf = $r; }
        }
    }
    if ($kopf === null || $alsCol === null || $moegCol === null) {
        return ['success' => false, 'message' => 'Kopfzeile «als:» / «möglich am:» nicht gefunden', 'zeilen' => []];
    }
    // Rollen- und Schicht-Spalten in der Zeile darunter
    $rollen = []; $schichten = [];
    for ($c = $alsCol; $c < $moegCol; $c++) { $t = $val($c, $kopf + 1); if ($t !== '') $rollen[$c] = $t; }
    for ($c = $moegCol; $c <= $maxCol; $c++) { $t = $val($c, $kopf + 1); if ($t !== '' && preg_match('/\d{1,2}[.,]\d{1,2}[.,]\d{4}/', $t)) $schichten[$c] = preg_replace('/\s+/', ' ', $t); }
    if (!$rollen || !$schichten) return ['success' => false, 'message' => 'Rollen- oder Schicht-Spalten nicht erkannt', 'zeilen' => []];

    // Personenzeilen
    $zeilen = []; $leer = 0;
    for ($r = $kopf + 2; $r <= $maxRow; $r++) {
        $name = $val(1, $r);
        if ($name === '') { if (++$leer >= 5) break; continue; }
        $leer = 0;
        if (preg_match('/^(Geschätzte|Der Personalbedarf|Bitte|Herzlichen|OK Schloss|Bemerk)/iu', $name)) break;   // Fusstext
        $name = preg_replace('/\s+/', ' ', $name);
        if (str_contains($name, ',')) { [$nn, $vn] = array_map('trim', explode(',', $name, 2)); }
        else { $parts = explode(' ', $name); $vn = count($parts) > 1 ? array_pop($parts) : ''; $nn = implode(' ', $parts); }
        $z = ['name' => trim($nn . ' ' . $vn), 'nachname' => $nn, 'vorname' => $vn, 'rollen' => [], 'schichten' => [], 'zeile' => $r];
        foreach ($rollen as $c => $t) if ($val($c, $r) !== '') $z['rollen'][] = $t;
        foreach ($schichten as $c => $t) if ($val($c, $r) !== '') $z['schichten'][] = $t;
        $zeilen[] = $z;
    }
    return ['success' => true, 'message' => count($zeilen) . ' Person(en) gelesen', 'rollen' => $rollen, 'schichten' => $schichten, 'zeilen' => $zeilen];
}
