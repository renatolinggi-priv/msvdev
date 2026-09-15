<?php
/**
 * inc/standbelegung/standbelegung_config.inc.php
 *
 * Eine Quelle fuer Art-Codes, Kategorien und die Standard-Erkennung der Art aus der
 * Bezeichnung. Vorher lag die Erkennungslogik dreifach vor (JS detectArt, Hilfetext im HTML,
 * harter LIKE-Katalog in export_jsk_pdf.php). Die Seite gibt SB_RULES per json_encode an das
 * JavaScript weiter; export_jsk_pdf.php filtert mit sb_detect_art().
 */

const SB_ART_CODES = [
    'SF'  => 'Schützenfest',
    'FS'  => 'Feldschiessen',
    'OP'  => 'Obligatorisches Programm',
    'WK'  => 'Wettkampf/Match',
    'JSK' => 'Jungschützenkurs',
    'TR'  => 'Training',
    'VS'  => 'Versammlung',
    'AND' => 'Anderes',
];

const SB_KATEGORIEN = ['300m', '50m', '25m', '10m', 'Sonstiges'];

/**
 * Standard-Erkennung (Reihenfolge = Prioritaet). Jeder Eintrag: Art => Liste von Suchbegriffen,
 * die in der kleingeschriebenen Bezeichnung enthalten sein muessen ("gv" wird als ganzes Wort geprueft).
 * Keywords aus der Tabelle Standbelegung_ArtKeywords haben Vorrang vor diesen Regeln.
 */
const SB_ART_RULES = [
    'FS'  => ['feldschiessen'],
    'OP'  => ['bundesprogramm', 'obligator'],
    'JSK' => ['jungschütz', 'js-kurs', 'jskurs', 'jsk'],
    'TR'  => ['training'],
    'VS'  => ['versammlung', 'absenden', 'gv'],
];

/**
 * Art aus der Bezeichnung ableiten: zuerst DB-Keywords, dann Standard-Regeln, sonst 'AND'.
 *
 * @param array<int, array{Keyword:string, Art:string}> $keywords
 */
function sb_detect_art(string $bezeichnung, array $keywords): string
{
    $bez = mb_strtolower(trim($bezeichnung));
    if ($bez === '') return 'AND';
    foreach ($keywords as $kw) {
        $k = mb_strtolower((string)($kw['Keyword'] ?? ''));
        if ($k !== '' && mb_strpos($bez, $k) !== false) return (string)$kw['Art'];
    }
    foreach (SB_ART_RULES as $art => $terms) {
        foreach ($terms as $t) {
            $treffer = strlen($t) <= 3
                ? (bool)preg_match('/\b' . preg_quote($t, '/') . '\b/u', $bez) // kurze Begriffe nur als Wort
                : mb_strpos($bez, $t) !== false;
            if ($treffer) return $art;
        }
    }
    return 'AND';
}

/** Alle Art-Keywords aus der DB. */
function sb_load_keywords(mysqli $conn): array
{
    $out = [];
    $res = $conn->query("SELECT ID, Keyword, Art FROM Standbelegung_ArtKeywords ORDER BY Art, Keyword");
    while ($res && ($row = $res->fetch_assoc())) {
        $row['ID'] = (int)$row['ID'];
        $out[] = $row;
    }
    return $out;
}
