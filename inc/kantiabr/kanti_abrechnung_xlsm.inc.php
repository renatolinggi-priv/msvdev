<?php
/**
 * Befüllt das SKSG-Abrechnungsformular für den Kantonalen Spezialstich
 * (Vorlage dat/Kantonalstich_Abrechnungsformular-ab-2026.xlsm).
 *
 * Die Vorlage enthält geschützte Blätter, ein Makro (vbaProject.bin) und ein Textfeld.
 * PhpSpreadsheet würde Makro und Textfeld verwerfen, darum wird die Datei als Zip
 * geöffnet und die Werte werden direkt ins Zellen-XML geschrieben (gleiches Muster wie
 * inc/endschloesen/generate_standblatt.php). Formeln der Vorlage bleiben stehen, Excel
 * rechnet sie dank fullCalcOnLoad beim Öffnen neu.
 *
 * Blatt «Abrechnung»:   B14 Distanz, B16 Verein, B18 Verantwortlicher, B20/B21 Adresse,
 *                       B23 E-Mail, G12 Datum (statt =TODAY(), damit das Jahr stimmt)
 * Blatt «Kontrollblatt»: Zeilen 18..57 (max. 40 Schützen): B Name, C Vorname, D Jahrgang,
 *                       F Sportgerät (muss exakt einem Listenwert entsprechen), G..K Passen
 */

const KANTI_XLSM_VORLAGE      = __DIR__ . '/dat/Kantonalstich_Abrechnungsformular-ab-2026.xlsm';
const KANTI_XLSM_ERSTE_ZEILE  = 18;
const KANTI_XLSM_MAX_SCHUETZEN = 40;
const KANTI_XLSM_DISTANZ      = 'G300m';
const KANTI_XLSM_VEREIN       = 'Wilen-Wollerau, MSV';

/** Sportgeräte-Liste der Vorlage (Blatt «Liste», Bereich W300m). Reihenfolge egal. */
const KANTI_XLSM_SPORTGERAETE = ['Standardgewehr', 'Freigewehr', 'Karabiner', 'Stgw57/03', 'Stgw57/02', 'Stgw90'];

/**
 * Ordnet die Waffenbezeichnung aus der DB («Stgw57 /03», «Stgw 90» …) dem exakten
 * Listenwert der Vorlage zu. Liefert null, wenn nichts passt.
 */
function kantiXlsmSportgeraet(?string $bezeichnung): ?string
{
    $norm = static function (string $s): string {
        return strtolower(preg_replace('/[\s._-]+/u', '', $s));
    };
    $gesucht = $norm((string) $bezeichnung);
    if ($gesucht === '') {
        return null;
    }
    foreach (KANTI_XLSM_SPORTGERAETE as $wert) {
        if ($norm($wert) === $gesucht) {
            return $wert;
        }
    }
    return null;
}

/**
 * Schreibt einen Wert in eine vorhandene Zelle des Blatt-XML. Der Style (s="…") bleibt,
 * ein bestehender Inhalt oder eine Formel wird ersetzt. null/'' leert die Zelle.
 * Strings gehen als inlineStr, damit sharedStrings.xml unangetastet bleibt.
 */
function kantiXlsmSetCell(string &$sheetXml, string $ref, $value, bool $asNumber = false): void
{
    $pattern = '/<c r="' . preg_quote($ref, '/') . '"((?:\s+[\w:]+="[^"]*")*)\s*(?:\/>|>.*?<\/c>)/s';
    if (!preg_match($pattern, $sheetXml, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException("Zelle $ref fehlt in der Vorlage");
    }
    $attrs = preg_replace('/\s+t="[^"]*"/', '', $m[1][0]);

    if ($value === null || $value === '') {
        $neu = '<c r="' . $ref . '"' . $attrs . '/>';
    } elseif ($asNumber) {
        $neu = '<c r="' . $ref . '"' . $attrs . '><v>' . (0 + $value) . '</v></c>';
    } else {
        $txt = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $neu = '<c r="' . $ref . '"' . $attrs . ' t="inlineStr"><is><t>' . $txt . '</t></is></c>';
    }
    $sheetXml = substr_replace($sheetXml, $neu, $m[0][1], strlen($m[0][0]));
}

/** Excel-Datumsseriennummer (1900-System) für ein Kalenderdatum. */
function kantiXlsmDatumSerial(int $jahr, int $monat, int $tag): int
{
    $basis = gmmktime(0, 0, 0, 12, 30, 1899);
    return (int) round((gmmktime(0, 0, 0, $monat, $tag, $jahr) - $basis) / 86400);
}

/**
 * Erzeugt die befüllte Abrechnungsdatei.
 *
 * @param array $kopf      jahr, verantwortlicher, adresse1, adresse2, email
 * @param array $schuetzen je ['name','vorname','jahrgang','waffe','passen'=>[p1..p5]]
 * @param string $ziel     Zielpfad der .xlsm
 * @return string[]        Warnungen (z.B. nicht zuordenbare Sportgeräte)
 */
function kantiAbrechnungXlsmErzeugen(array $kopf, array $schuetzen, string $ziel): array
{
    if (!is_file(KANTI_XLSM_VORLAGE)) {
        throw new RuntimeException('Vorlage ' . basename(KANTI_XLSM_VORLAGE) . ' fehlt');
    }
    if (count($schuetzen) > KANTI_XLSM_MAX_SCHUETZEN) {
        throw new RuntimeException(
            count($schuetzen) . ' Schützen, das Kontrollblatt der SKSG hat aber nur '
            . KANTI_XLSM_MAX_SCHUETZEN . ' Zeilen'
        );
    }
    if (!copy(KANTI_XLSM_VORLAGE, $ziel)) {
        throw new RuntimeException('Zieldatei konnte nicht angelegt werden');
    }

    $zip = new ZipArchive();
    if ($zip->open($ziel) !== true) {
        throw new RuntimeException('Vorlage konnte nicht als Zip geöffnet werden');
    }

    // Alle Teile zuerst lesen (ZipArchive-Pitfall: getFromName nach addFromString ist leer)
    $abrechnung    = $zip->getFromName('xl/worksheets/sheet1.xml');
    $kontrollblatt = $zip->getFromName('xl/worksheets/sheet2.xml');
    $workbook      = $zip->getFromName('xl/workbook.xml');
    if ($abrechnung === false || $kontrollblatt === false || $workbook === false) {
        $zip->close();
        throw new RuntimeException('Vorlage hat nicht den erwarteten Aufbau');
    }

    // --- Titelblatt ---
    $jahr = (int) $kopf['jahr'];
    if ($jahr === (int) date('Y')) {
        $datum = kantiXlsmDatumSerial($jahr, (int) date('n'), (int) date('j'));
    } else {
        $datum = kantiXlsmDatumSerial($jahr, 12, 31);
    }
    kantiXlsmSetCell($abrechnung, 'G12', $datum, true);
    kantiXlsmSetCell($abrechnung, 'B14', KANTI_XLSM_DISTANZ);
    kantiXlsmSetCell($abrechnung, 'B16', KANTI_XLSM_VEREIN);
    kantiXlsmSetCell($abrechnung, 'B18', trim((string) ($kopf['verantwortlicher'] ?? '')));
    kantiXlsmSetCell($abrechnung, 'B20', trim((string) ($kopf['adresse1'] ?? '')));
    kantiXlsmSetCell($abrechnung, 'B21', trim((string) ($kopf['adresse2'] ?? '')));
    kantiXlsmSetCell($abrechnung, 'B23', trim((string) ($kopf['email'] ?? '')));

    // --- Kontrollblatt ---
    $warnungen = [];
    $zeile = KANTI_XLSM_ERSTE_ZEILE;
    foreach ($schuetzen as $s) {
        $geraet = kantiXlsmSportgeraet($s['waffe'] ?? null);
        if ($geraet === null) {
            $geraet = trim((string) ($s['waffe'] ?? ''));
            $warnungen[] = trim($s['name'] . ' ' . $s['vorname']) . ': Sportgerät «' . $geraet
                . '» ist in der SKSG-Liste unbekannt, Kranzlimite wird nicht erkannt';
        }
        kantiXlsmSetCell($kontrollblatt, 'B' . $zeile, $s['name']);
        kantiXlsmSetCell($kontrollblatt, 'C' . $zeile, $s['vorname']);
        kantiXlsmSetCell($kontrollblatt, 'D' . $zeile, $s['jahrgang'] ? (int) $s['jahrgang'] : null, true);
        kantiXlsmSetCell($kontrollblatt, 'F' . $zeile, $geraet);
        foreach (['G', 'H', 'I', 'J', 'K'] as $i => $spalte) {
            $p = $s['passen'][$i] ?? null;
            // Leere Passen bleiben leer: das Formular zählt HD/ND per COUNTA
            kantiXlsmSetCell($kontrollblatt, $spalte . $zeile, ($p !== null && (int) $p > 0) ? (int) $p : null, true);
        }
        $zeile++;
    }

    // --- Neuberechnung beim Öffnen erzwingen (gespeicherte Formelwerte sind veraltet) ---
    if (strpos($workbook, 'fullCalcOnLoad') === false) {
        $workbook = preg_replace('/<calcPr\b([^>]*?)\/>/', '<calcPr$1 fullCalcOnLoad="1"/>', $workbook, 1);
    }

    $zip->addFromString('xl/worksheets/sheet1.xml', $abrechnung);
    $zip->addFromString('xl/worksheets/sheet2.xml', $kontrollblatt);
    $zip->addFromString('xl/workbook.xml', $workbook);
    if (!$zip->close()) {
        throw new RuntimeException('Datei konnte nicht geschrieben werden');
    }

    return $warnungen;
}
