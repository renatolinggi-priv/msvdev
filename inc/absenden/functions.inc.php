<?php
require_once __DIR__ . '/../debug_log.inc.php';
require_once __DIR__ . '/../endschresultate/teilnehmer.inc.php'; // Teilnehmer-Quelle (Migration 094)
//functions.inc.php - Überarbeitete Version
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Cell;
use PhpOffice\PhpWord\Style\Table;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\VerticalJc;
use PhpOffice\PhpWord\Element\Table as PhpWordTable;
use PhpOffice\PhpWord\SimpleType\JcTable;
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Liest Anzahl Streicher aus Parameter-Tabelle; Fallback 3
if (!function_exists('getExcludeCount')) {
    function getExcludeCount(mysqli $conn, int $year): int {
        $st = $conn->prepare("SELECT excludeCount FROM Parameter WHERE year = ?");
        $st->bind_param('i', $year);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? max(1, (int)$row['excludeCount']) : 3;
    }
}

// ========================================
// HILFSFUNKTIONEN

// ========================================
function fmt1($v) {
    if ($v === null || $v === '') return '';
    return number_format((float)$v, 1, '.', '');
}

// Fettschrift (Ränge 1–3): ein Wert-Objekt statt XML. AbsendenbuchVorlage::setValue() (absendenbuch_docx.inc.php)
// macht den Run der Vorlage fett, so bleiben Schrift und Grösse der Vorlage und das XML gültig.
// Nur direkt an setValue() übergeben, nie mit Text verketten.
final class AbsendenFett {
    public string $text;
    public function __construct(string $text) { $this->text = $text; }
    // Rückfall für einen gewöhnlichen TemplateProcessor (bisheriges, verschachteltes Verhalten)
    public function __toString(): string {
        return '<w:r><w:rPr><w:b/></w:rPr><w:t>' . htmlspecialchars($this->text) . '</w:t></w:r>';
    }
}
function formatBold($text) {
    return new AbsendenFett((string)$text);
}

// Debug-Logging: nur bei eingeschaltetem Debug-Schalter (inc/debug_log.inc.php).
// Ablaufprotokolle sollen das Fehlerlog nicht mehr zumuellen.
function logDebug($message) {
    if (!msv_debug_log_aktiv()) { return; }
    error_log("[JM Debug] " . date('Y-m-d H:i:s') . " - " . $message);
}

// Echte Fehler: gehen IMMER ins Log, unabhaengig vom Debug-Schalter.
// Frueher liefen auch die SQL-Fehler durch logDebug() - mit dem Schalter
// waeren sie unsichtbar geworden.
function logFehler($message) {
    error_log("[JM Fehler] " . date('Y-m-d H:i:s') . " - " . $message);
    // Im Absendenbuch fehlt dann ein Teil: dem Vorstand melden statt still weglassen
    absendenHinweis('Daten konnten nicht geladen werden, der Abschnitt ist unvollständig.');
}

/**
 * Hinweis fürs Absendenbuch: was leer bleibt oder fehlt, in Klartext für den Vorstand.
 * absendenbuchTemplateFuellen() (absendenbuch_docx.inc.php) setzt den laufenden Abschnitt als Vorspann
 * und liefert die Liste mit dem Buch; die Oberfläche zeigt sie nach dem Erstellen und vor dem Direktdruck.
 */
function absendenHinweis(string $text): void {
    $abschnitt = $GLOBALS['absendenAbschnitt'] ?? '';
    $eintrag = ($abschnitt !== '' ? $abschnitt . ': ' : '') . $text;
    if (!in_array($eintrag, $GLOBALS['absendenHinweise'] ?? [], true)) {
        $GLOBALS['absendenHinweise'][] = $eintrag;
        // dieselben Hinweise mit Abschnitt getrennt, für den gespeicherten Stand des Buchs (Links je Abschnitt)
        $GLOBALS['absendenHinweisDetails'][] = ['abschnitt' => $abschnitt, 'text' => $text];
    }
}

// ========================================
// STAND DES ZULETZT ERSTELLTEN ABSENDENBUCHS
// Pro Jahr in settings (absendenbuch_stand_<jahr>, JSON {"word": {…}, "broschuere": {…}}), bewusst nicht in
// dat/ (per URL erreichbar). Die Generatoren schreiben ihn nach dem Erstellen, endschrang/absenden_bereit.php
// liefert ihn für den Block «Stand des Absendenbuchs» unter «Fürs Absenden».
// ========================================
function absendenStandLesen(mysqli $conn, int $jahr): array {
    $schluessel = 'absendenbuch_stand_' . $jahr;
    $st = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $st->bind_param('s', $schluessel);
    $st->execute();
    $zeile = $st->get_result()->fetch_row();
    $st->close();
    $stand = $zeile ? json_decode((string)$zeile[0], true) : null;
    return is_array($stand) ? $stand : [];
}

/** $art 'word' | 'broschuere'; $datei = Dateiname in absenden/dat/; $extra z.B. seiten, blatt. */
function absendenStandSpeichern(mysqli $conn, int $jahr, string $art, string $datei, array $extra = []): void {
    $stand = absendenStandLesen($conn, $jahr);
    $von = trim((string)($_SESSION['user_name'] ?? ''));
    $stand[$art] = $extra + [
        'datei'    => basename($datei),
        'erstellt' => date('c'),
        'von'      => $von !== '' ? explode(' ', $von)[0] : '',
        'hinweise' => $GLOBALS['absendenHinweisDetails'] ?? [],
    ];
    $schluessel = 'absendenbuch_stand_' . $jahr;
    $wert = json_encode($stand, JSON_UNESCAPED_UNICODE);
    $st = $conn->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $st->bind_param('ss', $schluessel, $wert);
    $st->execute();
    $st->close();
}

/**
 * Optionalen Abschnitt der Vorlage auflösen (${NAME} … ${/NAME}, je in einem eigenen Absatz).
 * Behalten: nur die Marken verschwinden; sonst fällt der ganze Abschnitt weg (Titel und Tabelle).
 * false, wenn die Marken fehlen (z.B. ältere Vorlage) – dann bleibt der Abschnitt stehen.
 */
function absendenBlock($templateProcessor, string $name, bool $behalten): bool {
    if (method_exists($templateProcessor, 'blockAufloesen')) {
        return $templateProcessor->blockAufloesen($name, $behalten);
    }
    $templateProcessor->cloneBlock($name, $behalten ? 1 : 0);
    return !in_array($name, $templateProcessor->getVariables(), true);
}

// Optionaler Abschnitt ohne Daten (Jungschützen, Partner): ganz weglassen. Fehlen die Marken, bleibt
// wenigstens die Tabelle ohne Zeilen, und der Vorstand erfährt, warum Titel und Kopf im Buch stehen.
function absendenAbschnittWeglassen($templateProcessor, string $block, string $rangVariable): void {
    if (absendenBlock($templateProcessor, $block, false)) {
        return;
    }
    $templateProcessor->cloneRow($rangVariable, 0);
    absendenHinweis('keine Resultate, aber der leere Abschnitt liess sich nicht entfernen (Vorlage ohne Blockmarken). Bitte dem Admin melden.');
}

// Lesbarer Name einer Siegerliste (Tabelle siegerdef, Spalte Bezeichnung) für Hinweise und die Prüfliste
// «Absenden vorbereiten» (endschrang/absenden_bereit.php).
function absendenSiegerlisteName(string $bezeichnung): string {
    static $namen = [
        'Kunst' => 'Kunststich', 'Glück' => 'Glückstich', 'Zabigstich' => 'Zabigstich', 'Endstich' => 'Endstich',
        'Schwini' => 'Schwinistich', 'EndschiessenA' => 'Endschiessen Kat. A', 'EndschiessenB' => 'Endschiessen Kat. B',
        'HeimmeisterschaftA' => 'Heimmeisterschaft Kat. A', 'HeimmeisterschaftB' => 'Heimmeisterschaft Kat. B',
        'KantonalstichA' => 'Kantonalstich Kat. A', 'KantonalstichB' => 'Kantonalstich Kat. B',
        'JahresmeisterschaftA' => 'Jahresmeisterschaft Kat. A', 'JahresmeisterschaftB' => 'Jahresmeisterschaft Kat. B',
        'Junioren' => 'Junioren',
    ];
    return $namen[$bezeichnung] ?? $bezeichnung;
}

// ========================================
// HAUPTFUNKTIONEN

// ========================================
function getEndstich($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getEndstich für Jahr: " . $selectedYear);
    // Mitglieder, Gäste und Jungschützen (Migration 094: alle in endstich, Gäste über GastID)
    $sql = "
    SELECT
        u.Schluessel AS ID,
        u.TeilnehmerTyp,
        u.Name,
        u.Vorname,
        u.Geburtsdatum,
        u.Schuss1, u.Schuss2, u.Schuss3, u.Schuss4, u.Schuss5,
        u.Schuss6, u.Schuss7, u.Schuss8, u.Schuss9, u.Schuss10,
        u.Tiefschuss,
        u.Kranz_Endstich,
        COALESCE(u.Schuss1 + u.Schuss2 + u.Schuss3 + u.Schuss4 + u.Schuss5
               + u.Schuss6 + u.Schuss7 + u.Schuss8 + u.Schuss9 + u.Schuss10, 0) AS Endstich_Summe,
        ((u.Schuss1 = 10) + (u.Schuss2 = 10) + (u.Schuss3 = 10) + (u.Schuss4 = 10) + (u.Schuss5 = 10)
       + (u.Schuss6 = 10) + (u.Schuss7 = 10) + (u.Schuss8 = 10) + (u.Schuss9 = 10) + (u.Schuss10 = 10)) AS Anzahl_10
    FROM " . endschQuelle('endstich', ENDSCH_TYPEN_ALLE) . " u
    WHERE COALESCE(u.Schuss1 + u.Schuss2 + u.Schuss3 + u.Schuss4 + u.Schuss5
                 + u.Schuss6 + u.Schuss7 + u.Schuss8 + u.Schuss9 + u.Schuss10, 0) != 0
    ORDER BY
        Endstich_Summe DESC,
        u.Tiefschuss DESC,
        Anzahl_10 DESC,
        u.Geburtsdatum ASC, u.Name ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logFehler("SQL-Fehler in getEndstich prepare: " . $conn->error);
        $templateProcessor->cloneRow('ESRang', 0);
        return;
    }

    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();

    // Daten einsammeln
    $rows = [];
    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            if (!empty($r['Endstich_Summe'])) $rows[] = $r;
        }
    }
    $rowCount = count($rows);
    if ($rowCount === 0) {
        logDebug("Keine Endstich-Daten gefunden");
        absendenHinweis('keine Resultate für ' . $selectedYear . ', die Tabelle bleibt leer.');
        $templateProcessor->cloneRow('ESRang', 0);
        return;
    }
    $templateProcessor->cloneRow('ESRang', $rowCount);

    // Ausgabe
    $iRow = 1;
    foreach ($rows as $r) {
        $isTop3 = ($iRow <= 3);
        $rang   = $iRow . '.';
        $name   = trim($r['Name'] . ' ' . $r['Vorname']);
        $templateProcessor->setValue("ESRang#{$iRow}", $isTop3 ? formatBold($rang) : $rang);
        $templateProcessor->setValue("ESName#{$iRow}", $isTop3 ? formatBold($name) : $name);

        // 10 Schüsse
        for ($k = 1; $k <= 10; $k++) {
            $key = "Schuss{$k}";
            $val = isset($r[$key]) ? $r[$key] : '';

            // schöne Anzeige ohne .0
            $out = (is_numeric($val) && floor($val) == $val) ? (string)intval($val) : (string)$val;
            $templateProcessor->setValue("ESS{$k}#{$iRow}", $isTop3 ? formatBold($out) : $out);
        }
        $sum = (is_numeric($r['Endstich_Summe']) && floor($r['Endstich_Summe']) == $r['Endstich_Summe'])
             ? (string)intval($r['Endstich_Summe']) : (string)$r['Endstich_Summe'];
        $ts  = (is_numeric($r['Tiefschuss']) && floor($r['Tiefschuss']) == $r['Tiefschuss'])
             ? (string)intval($r['Tiefschuss']) : (string)$r['Tiefschuss'];
        $templateProcessor->setValue("ESTO#{$iRow}", $isTop3 ? formatBold($sum) : $sum);

        // Falls du im Template ein Feld für Tiefschuss hast:
        if (method_exists($templateProcessor, 'setValue')) {
            $templateProcessor->setValue("ESTS#{$iRow}", $isTop3 ? formatBold($ts) : $ts);
        }
        $kk = (!is_null($r['Kranz_Endstich']) && $r['Endstich_Summe'] >= $r['Kranz_Endstich']) ? 'KK' : '';
        $templateProcessor->setValue("ESKK#{$iRow}", $isTop3 ? formatBold($kk) : $kk);
        $iRow++;
    }
    $stmt->close();
    logDebug("getEndstich erfolgreich abgeschlossen");
}

function formatNumber($value) {
    if (empty($value) || $value == 0) {
        return $value;
    }

    // Wenn es eine Ganzzahl ist (keine Nachkommastellen), ohne .0 anzeigen
    if (floor($value) == $value) {
        return intval($value);
    }

    // Sonst als Dezimalzahl mit max 2 Stellen, trailing zeros entfernen
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}

/**
 * Hilfsfunktion: entfernt nur reine .0/.00... am Ende (10.0 -> 10, 10.50 bleibt 10.50)
 */
if (!function_exists('fmtNoTrailingZero')) {

    function fmtNoTrailingZero($v) {
        if ($v === null || $v === '') return '';
        if (!is_numeric($v)) {
            return preg_replace('/\.0+$/', '', (string)$v);
        }
        $f = (float)$v;

        // echte 0 -> "0" + Zero-Width Non-Joiner (unsichtbar, verhindert Wegputzen)
        if (abs($f) < 1e-12) {
            return "0" . "\u{200C}"; // U+200C ZWNJ
        }
        if (floor($f) == $f) {
            return (string)intval($f);
        }
        $s = sprintf('%.10f', $f);
        $s = rtrim(rtrim($s, '0'), '.');
        return $s !== '' ? $s : "0\u{200C}";
    }
}

function getSchwini($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getSchwini für Jahr: " . $selectedYear);
    // Mitglieder, Gäste und Jungschützen (Migration 094: alle in schwini, Gäste über GastID)
    $sql = "
    SELECT
        u.ID,
        u.TeilnehmerTyp,
        u.Name,
        u.Vorname,
        u.Geburtsdatum,
        u.P1Schuss1, u.P1Schuss2, u.P1Schuss3, u.P1Schuss4, u.P1Schuss5, u.P1Schuss6,
        u.P2Schuss1, u.P2Schuss2, u.P2Schuss3, u.P2Schuss4, u.P2Schuss5, u.P2Schuss6,
        u.Schwini1_Summe,
        u.Schwini2_Summe,
        GREATEST(u.Schwini1_Summe, u.Schwini2_Summe) AS Hoechste_Summe,
        LEAST(u.Schwini1_Summe,  u.Schwini2_Summe) AS Tiefste_Summe,
        CASE WHEN u.Schwini1_Summe > u.Schwini2_Summe THEN 'Passe 1' ELSE 'Passe 2' END AS Hoehere_Passe,
        CASE WHEN u.Schwini1_Summe < u.Schwini2_Summe THEN 'Passe 1' ELSE 'Passe 2' END AS Kleinere_Passe
    FROM (
        SELECT
            q.Schluessel AS ID, q.TeilnehmerTyp, q.Name, q.Vorname, q.Geburtsdatum,
            q.P1Schuss1, q.P1Schuss2, q.P1Schuss3, q.P1Schuss4, q.P1Schuss5, q.P1Schuss6,
            q.P2Schuss1, q.P2Schuss2, q.P2Schuss3, q.P2Schuss4, q.P2Schuss5, q.P2Schuss6,
            COALESCE(q.P1Schuss1 + q.P1Schuss2 + q.P1Schuss3 + q.P1Schuss4 + q.P1Schuss5 + q.P1Schuss6, 0) AS Schwini1_Summe,
            COALESCE(q.P2Schuss1 + q.P2Schuss2 + q.P2Schuss3 + q.P2Schuss4 + q.P2Schuss5 + q.P2Schuss6, 0) AS Schwini2_Summe
        FROM " . endschQuelle('schwini', ENDSCH_TYPEN_ALLE) . " q
    ) u
    WHERE u.Schwini1_Summe != 0 OR u.Schwini2_Summe != 0
    ORDER BY
        Hoechste_Summe DESC,
        Tiefste_Summe ASC,
        u.Geburtsdatum ASC, u.Name ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logFehler("SQL-Fehler in getSchwini prepare: " . $conn->error);
        $templateProcessor->cloneRow('SRang', 0);
        return;
    }

    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();

    // Daten sammeln
    $rowCount = 0;
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['Hoechste_Summe'])) {
                $data[] = $row;
                $rowCount++;
            }
        }
    }
    if ($rowCount == 0) {
        logDebug("Keine Schwini-Daten gefunden");
        absendenHinweis('keine Resultate für ' . $selectedYear . ', die Tabelle bleibt leer.');
        $templateProcessor->cloneRow('SRang', 0);
        return;
    }
    $templateProcessor->cloneRow('SRang', $rowCount);
    $currentRow = 1;
    foreach ($data as $row) {
        $keys = array_keys($row);
        $schussKeys = preg_grep('/^P1Schuss\\d+$/', $keys);
        $scount = count($schussKeys); // i.d.R. 6
        $isTop3   = ($currentRow <= 3);
        $rangText = $currentRow . '.';
        $nameText = trim($row['Name'] . ' ' . $row['Vorname']);

        // Rang & Name
        $templateProcessor->setValue("SRang#{$currentRow}", $isTop3 ? formatBold($rangText) : $rangText);
        $templateProcessor->setValue("SName#{$currentRow}", $isTop3 ? formatBold($nameText) : $nameText);

        // Welche Passe ausgeben?
        $pschuss = ($row['Hoehere_Passe'] === "Passe 1") ? 1 : 2;

        // Schüsse SS1..SS6 (Anzeige ohne überflüssige .0 â€“ nutzt deine fmtNoTrailingZero)
        for ($i = 1; $i <= $scount; $i++) {
            $schussKey = "P{$pschuss}Schuss{$i}";
            $raw   = isset($row[$schussKey]) ? $row[$schussKey] : '0';
            $value = (string) fmtNoTrailingZero($raw);
            $templateProcessor->setValue("SS{$i}#{$currentRow}", $isTop3 ? formatBold($value) : $value);
        }
        $st1 = (string) fmtNoTrailingZero($row['Hoechste_Summe']);
        $st2 = (string) fmtNoTrailingZero($row['Tiefste_Summe']);
        $templateProcessor->setValue("ST1#{$currentRow}", $isTop3 ? formatBold($st1) : $st1);
        $templateProcessor->setValue("ST2#{$currentRow}", $isTop3 ? formatBold($st2) : $st2);
        $currentRow++;
    }
    $stmt->close();
    logDebug("getSchwini erfolgreich abgeschlossen");
}

function getZabig($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getZabig für Jahr: " . $selectedYear);

    // Vereinfachte SQL ohne die langen CASE-Statements
    // Mitglieder und Gäste (Migration 094: Gäste über GastID; Jungschützen stehen in ihrer eigenen Liste)
    $sql = "
        SELECT
            u.Schluessel AS ID,
            u.TeilnehmerTyp,
            u.Name,
            u.Vorname,
            u.Geburtsdatum,
            u.ZSchuss1,
            u.ZSchuss2,
            u.ZSchuss3,
            u.ZSchuss4,
            u.ZSchuss5,
            u.ZSchuss6,
            GREATEST(
                COALESCE(u.ZSchuss1, 0),
                COALESCE(u.ZSchuss2, 0),
                COALESCE(u.ZSchuss3, 0),
                COALESCE(u.ZSchuss4, 0),
                COALESCE(u.ZSchuss5, 0),
                COALESCE(u.ZSchuss6, 0)
            ) AS TS
        FROM " . endschQuelle('zabig', ENDSCH_TYPEN_MITGLIED_GAST) . " u
        WHERE u.ZSchuss1 != 0
        ORDER BY
            u.Geburtsdatum ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logFehler("SQL-Fehler in getZabig prepare: " . $conn->error);
        $templateProcessor->cloneRow('ZRang', 0);
        return;
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();

    // Daten sammeln und Total in PHP berechnen
    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {

            // Berechne Total mit calculatePoints
            $total = 0;
            for ($i = 1; $i <= 6; $i++) {
                $schuss = "ZSchuss" . $i;
                if (isset($row[$schuss]) && $row[$schuss] != null) {
                    $total += calculatePoints($row[$schuss]);
                }
            }
            $row['Total'] = $total;
            if ($total > 0) {
                $data[] = $row;
            }
        }
    }

    // Sortiere nach Total DESC, TS DESC, Geburtsdatum ASC
    usort($data, function($a, $b) {
        if ($a['Total'] != $b['Total']) {
            return $b['Total'] - $a['Total'];
        }
        if ($a['TS'] != $b['TS']) {
            return $b['TS'] - $a['TS'];
        }
        return strcmp($a['Geburtsdatum'], $b['Geburtsdatum']);
    });
    $rowCount = count($data);
    if ($rowCount == 0) {
        logDebug("Keine Zabig-Daten gefunden");
        absendenHinweis('keine Resultate für ' . $selectedYear . ', die Tabelle bleibt leer.');
        $templateProcessor->cloneRow('ZRang', 0);
        return;
    }
    $templateProcessor->cloneRow('ZRang', $rowCount);
    $currentRow = 1;
    foreach ($data as $row) {
        $keys = array_keys($row);
        $schussKeys = preg_grep('/^ZSchuss\d+$/', $keys);
        $scount = count($schussKeys);
        if ($currentRow <= 3) {
            $templateProcessor->setValue("ZRang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue("ZName#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
            for ($i = 1; $i <= $scount; $i++) {
                $schuss = "ZSchuss" . $i;
                $schusswert = calculatePoints($row[$schuss]);
                $templateProcessor->setValue("ZS$i#{$currentRow}", formatBold($schusswert));
            }
            $templateProcessor->setValue("ZT#{$currentRow}", formatBold($row['Total']));
            $templateProcessor->setValue("ZTS#{$currentRow}", formatBold($row['TS']));
        } else {
            $templateProcessor->setValue("ZRang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue("ZName#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
            for ($i = 1; $i <= $scount; $i++) {
                $schuss = "ZSchuss" . $i;
                $schusswert = calculatePoints($row[$schuss]);
                $templateProcessor->setValue("ZS$i#{$currentRow}", $schusswert);
            }
            $templateProcessor->setValue("ZT#{$currentRow}", $row['Total']);
            $templateProcessor->setValue("ZTS#{$currentRow}", $row['TS']);
        }
        $currentRow++;
    }
    $stmt->close();
    logDebug("getZabig erfolgreich abgeschlossen");
}

function getGlueck($templateProcessor, $conn, $textRun)
{
    global $selectedYear;
    logDebug("Starte getGlueck für Jahr: " . $selectedYear);
    // Mitglieder und Gäste (Migration 094: Gäste über GastID)
    $sql = "
        SELECT
            u.Schluessel AS ID,
            u.TeilnehmerTyp,
            u.Name,
            u.Vorname,
            u.Geburtsdatum,
            u.GSchuss1,
            u.GSchuss2,
            u.GSchuss3,
            GREATEST(u.GSchuss1, u.GSchuss2, u.GSchuss3) AS MaxGlueck,
            LEAST(
                GREATEST(u.GSchuss1, u.GSchuss2),
                GREATEST(u.GSchuss1, u.GSchuss3),
                GREATEST(u.GSchuss2, u.GSchuss3)
            ) AS Zwei,
            LEAST(u.GSchuss1, u.GSchuss2, u.GSchuss3) AS Drei
        FROM " . endschQuelle('glueck', ENDSCH_TYPEN_MITGLIED_GAST) . " u
        WHERE u.GSchuss1 != 0
        ORDER BY
            MaxGlueck DESC,
            Zwei DESC,
            Drei DESC,
            u.Geburtsdatum ASC, u.Name ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logFehler("SQL-Fehler in getGlueck prepare: " . $conn->error);
        $templateProcessor->cloneRow('GRang', 0);
        return;
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    $rowCount = 0;
    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['MaxGlueck'])) {
                $data[] = $row;
                $rowCount++;
            }
        }
    }
    if ($rowCount == 0) {
        logDebug("Keine Glück-Daten gefunden");
        absendenHinweis('keine Resultate für ' . $selectedYear . ', die Tabelle bleibt leer.');
        $templateProcessor->cloneRow('GRang', 0);
        return;
    }
    $templateProcessor->cloneRow('GRang', $rowCount);
    $currentRow = 1;
    foreach ($data as $row) {

        // Bei Glück keine Fettschrift in den ersten 3 Zeilen
        $templateProcessor->setValue("GRang#{$currentRow}", $currentRow . ".");
        $templateProcessor->setValue("GName#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
        $templateProcessor->setValue("GMax#{$currentRow}", $row['MaxGlueck']);
        $templateProcessor->setValue("G2#{$currentRow}", $row['Zwei']);
        $templateProcessor->setValue("G3#{$currentRow}", $row['Drei']);
        $currentRow++;
    }
    $stmt->close();
    logDebug("getGlueck erfolgreich abgeschlossen");
}

function getKunst($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getKunst für Jahr: " . $selectedYear);
    // Mitglieder und Gäste (Migration 094: Gäste über GastID, Kranzlimite nach ihrer Waffe)
    $sql = "
        SELECT
            u.Schluessel AS ID,
            u.TeilnehmerTyp,
            u.Name,
            u.Vorname,
            u.Geburtsdatum,
            u.KSchuss1,
            u.KSchuss2,
            u.KSchuss3,
            u.KSchuss4,
            u.KSchuss5,
            u.Kranz_Kunst,
            COALESCE(
                u.KSchuss1 + u.KSchuss2 + u.KSchuss3 + u.KSchuss4 + u.KSchuss5, 
                0
            ) AS Kunst_Summe,
            GREATEST(
                COALESCE(u.KSchuss1, 0),
                COALESCE(u.KSchuss2, 0),
                COALESCE(u.KSchuss3, 0),
                COALESCE(u.KSchuss4, 0),
                COALESCE(u.KSchuss5, 0)
            ) AS TS
        FROM " . endschQuelle('kunst', ENDSCH_TYPEN_MITGLIED_GAST) . " u
        WHERE u.KSchuss1 IS NOT NULL AND u.KSchuss1 != 0
        ORDER BY
            Kunst_Summe DESC,
            TS DESC,
            u.Geburtsdatum ASC, u.Name ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logFehler("SQL-Fehler in getKunst prepare: " . $conn->error);
        $templateProcessor->cloneRow('KRang', 0);
        return;
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    $rowCount = 0;
    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['Kunst_Summe'])) {
                $data[] = $row;
                $rowCount++;
            }
        }
    }
    if ($rowCount == 0) {
        logDebug("Keine Kunst-Daten gefunden");
        absendenHinweis('keine Resultate für ' . $selectedYear . ', die Tabelle bleibt leer.');
        $templateProcessor->cloneRow('KRang', 0);
        return;
    }
    $templateProcessor->cloneRow('KRang', $rowCount);
    $currentRow = 1;
    $wp = endschWpVergeben($data); // Wanderpreis nur für Mitglieder
    foreach ($data as $idx => $row) {
        $keys = array_keys($row);
        $schussKeys = preg_grep('/^KSchuss\d+$/', $keys);
        $scount = count($schussKeys);
        $mitWp = fn(string $kk) => $idx === $wp ? ($kk !== '' ? $kk . ' + WP' : 'WP') : $kk;
        if ($currentRow <= 3) {
            $templateProcessor->setValue("KRang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue("KName#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
            for ($i = 1; $i <= $scount; $i++) {
                $schuss = "KSchuss" . $i;
                $value = isset($row[$schuss]) ? $row[$schuss] : '';
                $templateProcessor->setValue("KS$i#{$currentRow}", formatBold($value));
            }
            $templateProcessor->setValue("KT#{$currentRow}", formatBold($row['Kunst_Summe']));
            $templateProcessor->setValue("KKK#{$currentRow}", formatBold($mitWp('KK')));
        } else {
            $templateProcessor->setValue("KRang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue("KName#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
            for ($i = 1; $i <= $scount; $i++) {
                $schuss = "KSchuss" . $i;
                $value = isset($row[$schuss]) ? $row[$schuss] : '';
                $templateProcessor->setValue("KS$i#{$currentRow}", $value);
            }
            $templateProcessor->setValue("KT#{$currentRow}", $row['Kunst_Summe']);
            $templateProcessor->setValue("KKK#{$currentRow}", $mitWp($row['Kunst_Summe'] >= $row['Kranz_Kunst'] ? 'KK' : ''));
        }
        $currentRow++;
    }
    $stmt->close();
    logDebug("getKunst erfolgreich abgeschlossen");
}

function getEndschGesamt($templateProcessor, $conn, $kat)
{
    global $selectedYear;
    logDebug("Starte getEndschGesamt für Kategorie: " . $kat);

    // Vereinfachte SQL mit weniger CASE-Statements
    $sql = "
        SELECT
            m.Name,
            m.Vorname,
            m.Geburtsdatum,
            COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3)/10,1), 0) AS GlueckTotal,
            COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS EndstichTotal,
            COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6 ), 0) AS Schwini_Summe1,
            COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6 ), 0) AS Schwini_Summe2,
            COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) AS KunstTotal, 
            GREATEST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
                    s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) as MaxSchwini,
            LEAST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
                  s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) as MinSchwini,
            z.ZSchuss1, z.ZSchuss2, z.ZSchuss3, z.ZSchuss4, z.ZSchuss5, z.ZSchuss6
        FROM
            mitglieder m
        LEFT JOIN endstich e ON m.ID = e.MitgliedID AND e.Jahr = ?
        LEFT JOIN schwini s ON m.ID = s.MitgliedID AND s.Jahr = ?
        LEFT JOIN kunst k ON m.ID = k.MitgliedID AND k.Jahr = ?
        LEFT JOIN glueck g ON m.ID = g.MitgliedID AND g.Jahr = ?
        LEFT JOIN zabig z ON m.ID = z.MitgliedID AND z.Jahr = ?
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        WHERE w.Kategorie = ? AND e.Schuss1 != 0
        GROUP BY
            m.ID, m.Vorname, m.Name
        ORDER BY
            m.Geburtsdatum ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logFehler("SQL-Fehler in getEndschGesamt prepare: " . $conn->error);
        $kategorie = "E" . trim(strrchr($kat, ' '));
        $templateProcessor->cloneRow($kategorie . 'Rang', 0);
        return;
    }
    $stmt->bind_param("iiiiis", $selectedYear, $selectedYear, $selectedYear, $selectedYear, $selectedYear, $kat);
    $stmt->execute();
    $result = $stmt->get_result();

    // Daten sammeln und ZabigTotal in PHP berechnen
    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {

            // Berechne ZabigTotal
            $zabigTotal = 0;
            for ($i = 1; $i <= 6; $i++) {
                $schuss = "ZSchuss" . $i;
                if (isset($row[$schuss]) && $row[$schuss] != null) {
                    $zabigTotal += calculatePoints($row[$schuss]);
                }
            }
            $row['ZabigTotal'] = $zabigTotal;

            // Berechne GesamtTotal
            $row['GesamtTotal'] = $row['EndstichTotal'] + $row['GlueckTotal'] + 
                                  $zabigTotal + $row['KunstTotal'] + $row['MaxSchwini'];
            if (!empty($row['EndstichTotal'])) {
                $data[] = $row;
            }
        }
    }

    // Gäste (ohne Jungschützen) nur in Kat. B und nur, wenn sie alle fünf Stiche geschossen haben (Migration 094)
    if ($kat === 'Kat. B') {
        $sqlGast = "
            SELECT
                'gast' AS TeilnehmerTyp,
                ga.name AS Name,
                '' AS Vorname,
                '2100-01-01' AS Geburtsdatum,
                COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3)/10,1), 0) AS GlueckTotal,
                (e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10) AS EndstichTotal,
                ROUND((k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1) AS KunstTotal,
                GREATEST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
                        s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) AS MaxSchwini,
                z.ZSchuss1, z.ZSchuss2, z.ZSchuss3, z.ZSchuss4, z.ZSchuss5, z.ZSchuss6
            FROM endstich_gaeste ga
            INNER JOIN endstich e ON e.GastID = ga.id AND e.Jahr = ga.jahr AND e.Schuss1 != 0
            INNER JOIN schwini s ON s.GastID = ga.id AND s.Jahr = ga.jahr
            INNER JOIN kunst k ON k.GastID = ga.id AND k.Jahr = ga.jahr AND (k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) > 0
            INNER JOIN glueck g ON g.GastID = ga.id AND g.Jahr = ga.jahr AND GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3) > 0
            INNER JOIN zabig z ON z.GastID = ga.id AND z.Jahr = ga.jahr AND (z.ZSchuss1 + z.ZSchuss2 + z.ZSchuss3 + z.ZSchuss4 + z.ZSchuss5 + z.ZSchuss6) > 0
            WHERE ga.jahr = ? AND ga.geburtsdatum IS NULL
              AND GREATEST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
                           s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) > 0
        ";
        $stGast = $conn->prepare($sqlGast);
        if ($stGast) {
            $stGast->bind_param("i", $selectedYear);
            $stGast->execute();
            foreach ($stGast->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $zabigTotal = 0;
                for ($i = 1; $i <= 6; $i++) {
                    $zabigTotal += calculatePoints($row['ZSchuss' . $i] ?? 0);
                }
                $row['ZabigTotal'] = $zabigTotal;
                $row['GesamtTotal'] = $row['EndstichTotal'] + $row['GlueckTotal'] + $zabigTotal + $row['KunstTotal'] + $row['MaxSchwini'];
                $data[] = $row;
            }
            $stGast->close();
        } else {
            logFehler("SQL-Fehler in getEndschGesamt (Gäste) prepare: " . $conn->error);
        }
    }

    // Sortiere nach GesamtTotal DESC, EndstichTotal DESC, Geburtsdatum ASC
    usort($data, function($a, $b) {
        if ($a['GesamtTotal'] != $b['GesamtTotal']) {
            return $b['GesamtTotal'] <=> $a['GesamtTotal'];   // <=>, nicht Minus: usort schneidet 0.4 sonst auf 0 ab
        }
        if ($a['EndstichTotal'] != $b['EndstichTotal']) {
            return $b['EndstichTotal'] <=> $a['EndstichTotal'];
        }
        return strcmp((string)$a['Geburtsdatum'], (string)$b['Geburtsdatum']);
    });
    $rowCount = count($data);
    $kategorie = "E" . trim(strrchr($kat, ' '));
    if ($rowCount == 0) {
        logDebug("Keine Endsch-Gesamt-Daten gefunden für Kategorie: " . $kat);
        absendenHinweis('keine Resultate für ' . $selectedYear . ', die Tabelle bleibt leer.');
        $templateProcessor->cloneRow($kategorie . 'Rang', 0);
        return;
    }
    $templateProcessor->cloneRow($kategorie . 'Rang', $rowCount);
    $currentRow = 1;
    $wp = endschWpVergeben($data); // Wanderpreis nur für Mitglieder
    foreach ($data as $idx => $row) {
        if ($currentRow <= 3) {
            $templateProcessor->setValue($kategorie . "Rang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue($kategorie . "Name#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
            $templateProcessor->setValue($kategorie . "E#{$currentRow}", formatBold($row['EndstichTotal']));
            $templateProcessor->setValue($kategorie . "Z#{$currentRow}", formatBold($row['ZabigTotal']));
            $templateProcessor->setValue($kategorie . "S#{$currentRow}", formatBold($row['MaxSchwini']));
            $templateProcessor->setValue($kategorie . "G#{$currentRow}", formatBold($row['GlueckTotal']));
            $templateProcessor->setValue($kategorie . "K#{$currentRow}", formatBold($row['KunstTotal']));
            $templateProcessor->setValue($kategorie . "T#{$currentRow}", formatBold(fmt1($row['GesamtTotal'])));
            $templateProcessor->setValue($kategorie . "KK#{$currentRow}", formatBold($idx === $wp ? 'WP + KK' : 'KK'));
        } else {
            $templateProcessor->setValue($kategorie . "Rang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue($kategorie . "Name#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
            $templateProcessor->setValue($kategorie . "E#{$currentRow}", $row['EndstichTotal']);
            $templateProcessor->setValue($kategorie . "Z#{$currentRow}", $row['ZabigTotal']);
            $templateProcessor->setValue($kategorie . "S#{$currentRow}", $row['MaxSchwini']);
            $templateProcessor->setValue($kategorie . "G#{$currentRow}", $row['GlueckTotal']);
            $templateProcessor->setValue($kategorie . "K#{$currentRow}", $row['KunstTotal']);
            $templateProcessor->setValue($kategorie . "T#{$currentRow}", fmt1($row['GesamtTotal']));
            $templateProcessor->setValue($kategorie . "KK#{$currentRow}", $idx === $wp ? 'WP' : '');
        }
        $currentRow++;
    }
    $stmt->close();
    logDebug("getEndschGesamt erfolgreich abgeschlossen für Kategorie: " . $kat);
}

function getJungschuetzenResultate($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getJungschuetzenResultate für Jahr: " . $selectedYear);

    // Erst prüfen ob überhaupt Jungschützen-Daten vorhanden sind
    $checkSql = "
        SELECT COUNT(*) as count
        FROM endstich_gaeste g
        LEFT JOIN endstich e ON g.id = e.GastID AND e.Jahr = ?   -- Migration 094: Resultate über GastID
        WHERE g.geburtsdatum IS NOT NULL
          AND g.jahr = ?
          AND e.Schuss1 != 0 AND e.Schuss1 IS NOT NULL
    ";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $selectedYear, $selectedYear);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkRow = $checkResult->fetch_assoc();
        $hasData = $checkRow['count'] > 0;
        $checkStmt->close();
        if (!$hasData) {
            logDebug("Keine Jungschützen-Daten vorhanden - entferne Sektion");
            absendenAbschnittWeglassen($templateProcessor, 'JUNGSCHUETZEN_BLOCK', 'JRang');
            return;
        }
    }

    // Ab hier der normale Code wenn Daten vorhanden sind
    $sql = "
        SELECT
            g.name,
            g.vorname,
            g.nachname,
            g.geburtsdatum,
            COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + 
                         e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS EndstichTotal,
            COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0) AS Schwini_Summe1,
            COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0) AS Schwini_Summe2,
            GREATEST(
                COALESCE(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6, 0),
                COALESCE(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6, 0)
            ) AS MaxSchwini,
            z.ZSchuss1, z.ZSchuss2, z.ZSchuss3, z.ZSchuss4, z.ZSchuss5, z.ZSchuss6
        FROM
            endstich_gaeste g
        LEFT JOIN endstich e ON g.id = e.GastID AND e.Jahr = ?
        LEFT JOIN schwini s ON g.id = s.GastID AND s.Jahr = ?
        LEFT JOIN zabig z ON g.id = z.GastID AND z.Jahr = ?
        WHERE
            g.geburtsdatum IS NOT NULL
            AND g.jahr = ?
            AND (e.Schuss1 != 0 OR e.Schuss1 IS NOT NULL)
        GROUP BY
            g.id, g.name, g.vorname, g.nachname, g.geburtsdatum
        ORDER BY
            g.geburtsdatum ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logFehler("SQL-Fehler in getJungschuetzenResultate prepare: " . $conn->error);
        absendenAbschnittWeglassen($templateProcessor, 'JUNGSCHUETZEN_BLOCK', 'JRang');
        return;
    }
    $stmt->bind_param("iiii", $selectedYear, $selectedYear, $selectedYear, $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();

    // Daten sammeln
    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {

            // Zabig-Punkte berechnen
            $zabigTotal = 0;
            for ($i = 1; $i <= 6; $i++) {
                $schuss = "ZSchuss" . $i;
                if (isset($row[$schuss]) && $row[$schuss] != null) {
                    $zabigTotal += calculatePoints($row[$schuss]);
                }
            }
            $row['ZabigTotal'] = $zabigTotal;

            // GesamtTotal: Nur noch Endstich + Zabig + Schwini (kein Kunst/Glück mehr)
            $row['GesamtTotal'] = $row['EndstichTotal'] + $zabigTotal + $row['MaxSchwini'];
            if (!empty($row['EndstichTotal'])) {
                $data[] = $row;
            }
        }
    }

    // Sortierung
    usort($data, function($a, $b) {
        if ($a['GesamtTotal'] != $b['GesamtTotal']) {
            return $b['GesamtTotal'] <=> $a['GesamtTotal'];
        }
        if ($a['EndstichTotal'] != $b['EndstichTotal']) {
            return $b['EndstichTotal'] <=> $a['EndstichTotal'];
        }
        return strcmp($a['geburtsdatum'], $b['geburtsdatum']);
    });
    $rowCount = count($data);
    if ($rowCount == 0) {
        logDebug("Keine Jungschützen-Daten mit Resultaten gefunden");
        absendenAbschnittWeglassen($templateProcessor, 'JUNGSCHUETZEN_BLOCK', 'JRang');
        $stmt->close();
        return;
    }
    $templateProcessor->cloneRow('JRang', $rowCount);
    $currentRow = 1;
    foreach ($data as $row) {

        // Name zusammensetzen - falls vorname/nachname leer sind, nutze name
        $displayName = !empty($row['vorname']) && !empty($row['nachname']) 
            ? $row['nachname'] . " " . $row['vorname']
            : $row['name'];
        if ($currentRow <= 3) {

            // Top 3 fett formatiert
            $templateProcessor->setValue("JRang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue("JName#{$currentRow}", formatBold($displayName));
            $templateProcessor->setValue("JE#{$currentRow}", formatBold($row['EndstichTotal']));
            $templateProcessor->setValue("JZ#{$currentRow}", formatBold($row['ZabigTotal']));
            $templateProcessor->setValue("JS#{$currentRow}", formatBold($row['MaxSchwini']));
            $templateProcessor->setValue("JT#{$currentRow}", formatBold($row['GesamtTotal']));

            // Kunst und Glück sind nicht mehr vorhanden - leer lassen falls im Template
            $templateProcessor->setValue("JG#{$currentRow}", formatBold(''));
            $templateProcessor->setValue("JK#{$currentRow}", formatBold(''));
            $templateProcessor->setValue("JKK#{$currentRow}", formatBold(''));
        } else {

            // Rest normal formatiert
            $templateProcessor->setValue("JRang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue("JName#{$currentRow}", $displayName);
            $templateProcessor->setValue("JE#{$currentRow}", $row['EndstichTotal']);
            $templateProcessor->setValue("JZ#{$currentRow}", $row['ZabigTotal']);
            $templateProcessor->setValue("JS#{$currentRow}", $row['MaxSchwini']);
            $templateProcessor->setValue("JT#{$currentRow}", $row['GesamtTotal']);
        }
        $currentRow++;
    }
    $stmt->close();

    // Abschnitt bleibt, nur die Blockmarken verschwinden
    absendenBlock($templateProcessor, 'JUNGSCHUETZEN_BLOCK', true);
    logDebug("getJungschuetzenResultate erfolgreich abgeschlossen");
}

function getPartnerResultate($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getPartnerResultate für Jahr: " . $selectedYear);

    // Partnerinnen = Gäste ohne Geburtsdatum und als Partner markierte Mitglieder (zentral, Migration 094/096)
    $partnerQuelle = endschPartnerQuelle();

    // Erst prüfen ob überhaupt Partner-Daten vorhanden sind
    $checkSql = "SELECT COUNT(*) as count FROM ($partnerQuelle) p WHERE p.Endstich_Summe > 0 OR p.Schwini_Passe1 > 0 OR p.Schwini_Passe2 > 0";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $selectedYear, $selectedYear);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkRow = $checkResult->fetch_assoc();
        $hasData = $checkRow['count'] > 0;
        $checkStmt->close();
        if (!$hasData) {
            logDebug("Keine Partner-Daten vorhanden - entferne Sektion");
            absendenAbschnittWeglassen($templateProcessor, 'PARTNER_BLOCK', 'PRang');
            return;
        }
    }

    // Ab hier der normale Code wenn Daten vorhanden sind
    $sql = "
        SELECT p.*, GREATEST(p.Schwini_Passe1, p.Schwini_Passe2) AS PartnerSchwini_Summe,
               p.Endstich_Summe + GREATEST(p.Schwini_Passe1, p.Schwini_Passe2) AS Total_Summe
        FROM ($partnerQuelle) p
        WHERE p.Endstich_Summe > 0 OR p.Schwini_Passe1 > 0 OR p.Schwini_Passe2 > 0
        ORDER BY Total_Summe DESC, p.Endstich_Summe DESC, p.PartnerName ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logFehler("SQL-Fehler in getPartnerResultate prepare: " . $conn->error);
        absendenAbschnittWeglassen($templateProcessor, 'PARTNER_BLOCK', 'PRang');
        return;
    }
    $stmt->bind_param("ii", $selectedYear, $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();

    // Daten sammeln
    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['Total_Summe'])) {
                $data[] = $row;
            }
        }
    }
    $rowCount = count($data);
    if ($rowCount == 0) {
        logDebug("Keine Partner-Daten mit Resultaten gefunden");
        absendenAbschnittWeglassen($templateProcessor, 'PARTNER_BLOCK', 'PRang');
        $stmt->close();
        return;
    }
    $templateProcessor->cloneRow('PRang', $rowCount);
    $currentRow = 1;
    foreach ($data as $row) {

        // Werte formatieren
        $endstich = formatNumber($row['Endstich_Summe']);
        $schwini = formatNumber($row['PartnerSchwini_Summe']);
        $total = formatNumber($row['Total_Summe']);
        if ($currentRow <= 3) {

            // Top 3 fett formatiert
            $templateProcessor->setValue("PRang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue("PName#{$currentRow}", formatBold($row['PartnerName']));
            $templateProcessor->setValue("PE#{$currentRow}", formatBold($endstich));
            $templateProcessor->setValue("PS#{$currentRow}", formatBold($schwini));
            $templateProcessor->setValue("PT#{$currentRow}", formatBold($total));
        } else {

            // Rest normal formatiert
            $templateProcessor->setValue("PRang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue("PName#{$currentRow}", $row['PartnerName']);
            $templateProcessor->setValue("PE#{$currentRow}", $endstich);
            $templateProcessor->setValue("PS#{$currentRow}", $schwini);
            $templateProcessor->setValue("PT#{$currentRow}", $total);
        }
        $currentRow++;
    }
    $stmt->close();

    // Abschnitt bleibt, nur die Blockmarken verschwinden
    absendenBlock($templateProcessor, 'PARTNER_BLOCK', true);
    logDebug("getPartnerResultate erfolgreich abgeschlossen");
}

function getHeim($templateProcessor, $conn, $kat)
{
    global $selectedYear;
    logDebug("Starte getHeim für Kategorie: " . $kat);
    $sql = "
        SELECT m.Name, m.Vorname, h.Passe1, h.Passe2, h.Passe3, h.Passe4, h.Passe5, h.Passe6, h.Passe7, h.Passe8,
           (COALESCE(h.Passe1, 0) + COALESCE(h.Passe2, 0) + COALESCE(h.Passe3, 0) + COALESCE(h.Passe4, 0) + 
            COALESCE(h.Passe5, 0) + COALESCE(h.Passe6, 0) + COALESCE(h.Passe7, 0) + COALESCE(h.Passe8, 0)) AS HeimSumme
    FROM heimresultate h
    INNER JOIN mitglieder m ON m.ID = h.MitgliedID AND h.Jahr = ?
    INNER JOIN Waffen w ON w.ID = m.WaffenID 
    WHERE w.Kategorie = ? 
    AND (h.Passe1 > 0 OR h.Passe2 > 0 OR h.Passe3 > 0 OR h.Passe4 > 0 OR 
         h.Passe5 > 0 OR h.Passe6 > 0 OR h.Passe7 > 0 OR h.Passe8 > 0)
    ORDER BY HeimSumme DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logFehler("SQL-Fehler in getHeim prepare: " . $conn->error);
        $kategorie = "H" . trim(strrchr($kat, ' '));
        $templateProcessor->cloneRow($kategorie . 'Rang', 0);
        return;
    }
    $stmt->bind_param("is", $selectedYear, $kat);
    $stmt->execute();
    $result = $stmt->get_result();
    $rowCount = 0;
    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['HeimSumme'])) {
                $data[] = $row;
                $rowCount++;
            }
        }
    }
    $kategorie = "H" . trim(strrchr($kat, ' '));
    if ($rowCount == 0) {
        logDebug("Keine Heim-Daten gefunden für Kategorie: " . $kat);
        absendenHinweis('keine Resultate für ' . $selectedYear . ', die Tabelle bleibt leer.');
        $templateProcessor->cloneRow($kategorie . 'Rang', 0);
        return;
    }
    $templateProcessor->cloneRow($kategorie . 'Rang', $rowCount);
    $currentRow = 1;
    foreach ($data as $row) {
        $keys = array_keys($row);
        $schussKeys = preg_grep('/^Passe\d+$/', $keys);
        $scount = count($schussKeys);
        if ($currentRow <= 3) {
            $templateProcessor->setValue($kategorie . "Rang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue($kategorie . "Name#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
            for ($i = 1; $i <= $scount; $i++) {
                $passe = "Passe" . $i;
                $value = isset($row[$passe]) ? $row[$passe] : '';
                $templateProcessor->setValue($kategorie . "P" . $i . "#{$currentRow}", formatBold($value));
            }
            $templateProcessor->setValue($kategorie . "T#{$currentRow}", formatBold($row['HeimSumme']));
            if ($currentRow == 1) {
                $templateProcessor->setValue($kategorie . "KK#{$currentRow}", formatBold('WP + 3KK'));
            } elseif ($currentRow == 2) {
                $templateProcessor->setValue($kategorie . "KK#{$currentRow}", formatBold('2KK'));
            } elseif ($currentRow == 3) {
                $templateProcessor->setValue($kategorie . "KK#{$currentRow}", formatBold('KK'));
            }
        } else {
            $templateProcessor->setValue($kategorie . "Rang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue($kategorie . "Name#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
            for ($i = 1; $i <= $scount; $i++) {
                $passe = "Passe" . $i;
                $value = isset($row[$passe]) ? $row[$passe] : '';
                $templateProcessor->setValue($kategorie . "P" . $i . "#{$currentRow}", $value);
            }
            $templateProcessor->setValue($kategorie . "T#{$currentRow}", $row['HeimSumme']);
            $templateProcessor->setValue($kategorie . "KK#{$currentRow}", '');
        }
        $currentRow++;
    }
    $stmt->close();
    logDebug("getHeim erfolgreich abgeschlossen für Kategorie: " . $kat);
}

function getKanti($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getKanti für Jahr: " . $selectedYear);

    // Kategorie A
    $sql = "
        SELECT m.Name, m.Vorname, k.Passe1, k.Passe2, k.Passe3, k.Passe4, k.Passe5,
               (COALESCE(k.Passe1, 0) + COALESCE(k.Passe2, 0) + COALESCE(k.Passe3, 0) + 
                COALESCE(k.Passe4, 0) + COALESCE(k.Passe5, 0)) AS KantonalSumme
        FROM kantiresultate k
        INNER JOIN mitglieder m ON m.ID = k.MitgliedID AND k.Jahr = ?
        INNER JOIN Waffen w ON w.ID = m.WaffenID 
        WHERE w.Kategorie = 'Kat. A'
        ORDER BY KantonalSumme DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logFehler("SQL-Fehler in getKanti prepare (Kat. A): " . $conn->error);
        $templateProcessor->cloneRow('aRang', 0);
        return;
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    $rowCount = 0;
    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['KantonalSumme'])) {
                $data[] = $row;
                $rowCount++;
            }
        }
    }
    if ($rowCount == 0) {
        logDebug("Keine Kanti-Daten gefunden für Kat. A");
        absendenHinweis('Kat. A: keine Resultate für ' . $selectedYear . ', die Tabelle bleibt leer.');
        $templateProcessor->cloneRow('aRang', 0);
    } else {
        $templateProcessor->cloneRow('aRang', $rowCount);
        $currentRow = 1;
        foreach ($data as $row) {
            if ($currentRow <= 3) {
                $templateProcessor->setValue("aRang#{$currentRow}", formatBold($currentRow . '.'));
                $templateProcessor->setValue("KantonalA#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
                $templateProcessor->setValue("aPasse1#{$currentRow}", formatBold($row['Passe1']));
                $templateProcessor->setValue("aPasse2#{$currentRow}", formatBold($row['Passe2']));
                $templateProcessor->setValue("aPasse3#{$currentRow}", formatBold($row['Passe3']));
                $templateProcessor->setValue("aPasse4#{$currentRow}", formatBold($row['Passe4']));
                $templateProcessor->setValue("aPasse5#{$currentRow}", formatBold($row['Passe5']));
                $templateProcessor->setValue("aKantonalSumme#{$currentRow}", formatBold($row['KantonalSumme']));
                if ($currentRow == 1) {
                    $templateProcessor->setValue("aWP#{$currentRow}", formatBold('WP'));
                } else {
                    $templateProcessor->setValue("aWP#{$currentRow}", formatBold(''));
                }
            } else {
                $templateProcessor->setValue("aRang#{$currentRow}", $currentRow . ".");
                $templateProcessor->setValue("KantonalA#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
                $templateProcessor->setValue("aPasse1#{$currentRow}", $row['Passe1']);
                $templateProcessor->setValue("aPasse2#{$currentRow}", $row['Passe2']);
                $templateProcessor->setValue("aPasse3#{$currentRow}", $row['Passe3']);
                $templateProcessor->setValue("aPasse4#{$currentRow}", $row['Passe4']);
                $templateProcessor->setValue("aPasse5#{$currentRow}", $row['Passe5']);
                $templateProcessor->setValue("aKantonalSumme#{$currentRow}", $row['KantonalSumme']);
                $templateProcessor->setValue("aWP#{$currentRow}", '');
            }
            $currentRow++;
        }
    }
    $stmt->close();

    // Kategorie B
    $sql = "
        SELECT m.Name, m.Vorname, k.Passe1, k.Passe2, k.Passe3, k.Passe4, k.Passe5,
               (COALESCE(k.Passe1, 0) + COALESCE(k.Passe2, 0) + COALESCE(k.Passe3, 0) + 
                COALESCE(k.Passe4, 0) + COALESCE(k.Passe5, 0)) AS KantonalSumme
        FROM kantiresultate k
        INNER JOIN mitglieder m ON m.ID = k.MitgliedID AND k.Jahr = ?
        INNER JOIN Waffen w ON w.ID = m.WaffenID 
        WHERE w.Kategorie = 'Kat. B'
        ORDER BY KantonalSumme DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logFehler("SQL-Fehler in getKanti prepare (Kat. B): " . $conn->error);
        $templateProcessor->cloneRow('bRang', 0);
        return;
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    $rowCount = 0;
    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['KantonalSumme'])) {
                $data[] = $row;
                $rowCount++;
            }
        }
    }
    if ($rowCount == 0) {
        logDebug("Keine Kanti-Daten gefunden für Kat. B");
        absendenHinweis('Kat. B: keine Resultate für ' . $selectedYear . ', die Tabelle bleibt leer.');
        $templateProcessor->cloneRow('bRang', 0);
    } else {
        $templateProcessor->cloneRow('bRang', $rowCount);
        $currentRow = 1;
        foreach ($data as $row) {
            if ($currentRow <= 3) {
                $templateProcessor->setValue("bRang#{$currentRow}", formatBold($currentRow . '.'));
                $templateProcessor->setValue("KantonalB#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
                $templateProcessor->setValue("bPasse1#{$currentRow}", formatBold($row['Passe1']));
                $templateProcessor->setValue("bPasse2#{$currentRow}", formatBold($row['Passe2']));
                $templateProcessor->setValue("bPasse3#{$currentRow}", formatBold($row['Passe3']));
                $templateProcessor->setValue("bPasse4#{$currentRow}", formatBold($row['Passe4']));
                $templateProcessor->setValue("bPasse5#{$currentRow}", formatBold($row['Passe5']));
                $templateProcessor->setValue("bKantonalSumme#{$currentRow}", formatBold($row['KantonalSumme']));
                if ($currentRow == 1) {
                    $templateProcessor->setValue("bWP#{$currentRow}", formatBold('WP'));
                } else {
                    $templateProcessor->setValue("bWP#{$currentRow}", formatBold(''));
                }
            } else {
                $templateProcessor->setValue("bRang#{$currentRow}", $currentRow . ".");
                $templateProcessor->setValue("KantonalB#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
                $templateProcessor->setValue("bPasse1#{$currentRow}", $row['Passe1']);
                $templateProcessor->setValue("bPasse2#{$currentRow}", $row['Passe2']);
                $templateProcessor->setValue("bPasse3#{$currentRow}", $row['Passe3']);
                $templateProcessor->setValue("bPasse4#{$currentRow}", $row['Passe4']);
                $templateProcessor->setValue("bPasse5#{$currentRow}", $row['Passe5']);
                $templateProcessor->setValue("bKantonalSumme#{$currentRow}", $row['KantonalSumme']);
                $templateProcessor->setValue("bWP#{$currentRow}", '');
            }
            $currentRow++;
        }
    }
    $stmt->close();
    logDebug("getKanti erfolgreich abgeschlossen");
}

function getSieger($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getSieger");
    $sql = "SELECT * FROM siegerdef";
    $result = $conn->query($sql);
    if (!$result) {
        logFehler("SQL-Fehler in getSieger: " . $conn->error);
        return;
    }
    // Die Siegerlisten stehen in der Tabelle sieger (Seite «Sieger der letzten Jahre»: aus den Ranglisten übernehmen
    // oder von Hand erfassen). Nur Jahre bis zum Buchjahr
    // zeigen (ein Buch für 2024 ohne spätere Sieger) und melden, welchen Listen das Buchjahr noch fehlt.
    // Im Buch stehen je Liste die letzten 30 Sieger bis zum Buchjahr (zwei pro Zeile), damit die Listen nicht
    // jedes Jahr wachsen; die Überschrift «… seit» nennt das erste gezeigte Jahr (${<Präfix>AB} in der Vorlage).
    $maxSieger = 30;
    $ohneBuchjahr = [];
    $vorlagenVariablen = array_flip($templateProcessor->getVariables());
    while ($rowdef = $result->fetch_assoc()) {
        $p = $rowdef['PlatzhalterWord'];
        $siegerdef = $rowdef['Bezeichnung'];
        if (!isset($vorlagenVariablen[$p . 'Y'])) {
            // Liste ohne Tabelle in der Vorlage (z.B. ältere Vorlage): nicht abbrechen, nur melden
            logFehler("getSieger: Vorlage hat keine Tabelle für «{$siegerdef}» (\${{$p}Y})");
            continue;
        }
        $stmt = $conn->prepare("SELECT s.year, s.Name, s.Wert FROM sieger s
                                 JOIN siegerdef sd ON sd.ID = s.siegerdef
                                 WHERE sd.Bezeichnung = ? AND s.year <= ?
                                 ORDER BY s.year DESC, s.ID DESC
                                 LIMIT ?");
        if (!$stmt) {
            logFehler("SQL-Fehler in getSieger prepare: " . $conn->error);
            continue;
        }
        $stmt->bind_param("sii", $siegerdef, $selectedYear, $maxSieger);
        $stmt->execute();
        $siegers = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC)); // älteste zuerst
        $stmt->close();
        if (!in_array((int)$selectedYear, array_map('intval', array_column($siegers, 'year')), true)) {
            $ohneBuchjahr[] = absendenSiegerlisteName($siegerdef);
        }

        $templateProcessor->setValue($p . 'AB', $siegers ? (string)$siegers[0]['year'] : '');
        $zeilen = (int)ceil(count($siegers) / 2);
        $templateProcessor->cloneRow($p . 'Y', $zeilen);
        for ($z = 1; $z <= $zeilen; $z++) {
            $links = $siegers[2 * $z - 2];
            $rechts = $siegers[2 * $z - 1] ?? ['year' => '', 'Name' => '', 'Wert' => ''];
            $templateProcessor->setValue("{$p}Y#{$z}", $links['year']);
            $templateProcessor->setValue("{$p}N#{$z}", $links['Name']);
            $templateProcessor->setValue("{$p}P#{$z}", $links['Wert']);
            $templateProcessor->setValue("{$p}Y2#{$z}", $rechts['year']);
            $templateProcessor->setValue("{$p}N2#{$z}", $rechts['Name']);
            $templateProcessor->setValue("{$p}P2#{$z}", $rechts['Wert']);
        }
    }
    if ($ohneBuchjahr) {
        $welche = count($ohneBuchjahr) === $result->num_rows
            ? 'Allen ' . $result->num_rows . ' Listen fehlt ' . $selectedYear
            : count($ohneBuchjahr) . ' von ' . $result->num_rows . ' Listen fehlt ' . $selectedYear . ' (' . implode(', ', $ohneBuchjahr) . ')';
        absendenHinweis($welche . '. Auf der Seite «Sieger der letzten Jahre» mit «Aus Ranglisten übernehmen» ergänzen und das Buch neu erstellen.');
    }
    logDebug("getSieger erfolgreich abgeschlossen");
}

function getCup($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getCup für Jahr: " . $selectedYear);

    // Funktion zum Erstellen der CUP-Tabelle
    function createCupTable($phpWord, $round, $cupPairsResult)
    {
        // Wie bisher eine Zeile pro Paarung in der Reihenfolge der Auslosung (Name | Pkt | Name | Pkt), ohne Rahmen wie
        // die übrigen Tabellen im Buch; eine feine Linie unter jeder Begegnung hält die Gruppen zusammen (eine Dreiergruppe
        // hat keine Linie zwischen ihren zwei Zeilen). Wer weiterkommt fett, die anderen normal.
        $tabelle = ['layout' => \PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED, 'cellMarginLeft' => 80, 'cellMarginRight' => 80];
        $wName = 2900; $wPkt = 650;
        $fett = ['size' => 8, 'bold' => true];
        $normal = ['size' => 8];
        $absLinks = ['spaceAfter' => 0];
        $absRechts = ['spaceAfter' => 0, 'alignment' => Jc::END];
        $cupTable = $phpWord->addSection()->addTable($tabelle);

        foreach ($cupPairsResult as $row) {
            if ($row['Round'] != $round) {
                continue;
            }
            if (!empty($row['Participant3'])) {
                $verlierer = getThreePairLosers($row);
                $weiter = fn(int $n): bool => !in_array($n, $verlierer);
            } else {
                $manuell = (int)($row['ManualWinner'] ?? 0);
                $erster = $manuell > 0
                    ? $manuell === (int)$row['Participant1']
                    : ($row['Result1'] > $row['Result2']
                       || ($row['Result1'] == $row['Result2'] && $row['LowShot1'] > $row['LowShot2']));
                $weiter = fn(int $n): bool => $n === 1 ? $erster : !$erster;
            }
            // Zeilen der Begegnung: [Nr links, Nr rechts]; Dreiergruppe zwei Zeilen
            $zeilen = !empty($row['Participant3']) ? [[1, 2], [3, null]] : [[1, 2]];
            foreach ($zeilen as $z => [$l, $r]) {
                $letzte = $z === count($zeilen) - 1;
                // ohne Rahmen wie die übrigen Tabellen im Buch; nur eine feine Linie unter jeder Begegnung
                // (eine Dreiergruppe bleibt so als Block erkennbar)
                $zelle = ['valign' => VerticalJc::CENTER];
                if ($letzte) { $zelle += ['borderBottomSize' => 4, 'borderBottomColor' => '000000']; }
                $cupTable->addRow(320); // mindestens ~5.6 mm, etwas luftiger
                foreach ([$l, $r] as $n) {
                    if ($n === null) {
                        $cupTable->addCell($wName, $zelle)->addText('', $normal, $absLinks);
                        $cupTable->addCell($wPkt, $zelle)->addText('', $normal, $absRechts);
                        continue;
                    }
                    $schrift = $weiter($n) ? $fett : $normal;
                    $cupTable->addCell($wName, $zelle)->addText(getParticipantName($row['Participant' . $n]), $schrift, $absLinks);
                    $cupTable->addCell($wPkt, $zelle)->addText((string)$row['Result' . $n], $schrift, $absRechts);
                }
            }
        }
        return $cupTable;
    }
    // Funktion für Teilnehmernamen
    function getParticipantName($id)
    {
        global $conn;
        static $nameCache = array();
        if (isset($nameCache[$id])) {
            return $nameCache[$id];
        }
        $stmt = $conn->prepare("SELECT CONCAT(Name, ' ', Vorname) as FullName FROM mitglieder WHERE ID = ?");
        if (!$stmt) {
            return "Unbekannt";
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $fullName = $row ? $row['FullName'] : "Unbekannt";
        $nameCache[$id] = $fullName;
        return $fullName;
    }
    // Final und Standcup: linksbündig unter den Runden, so breit wie deren erste Hälfte (Punkte stehen untereinander)
    $tableStyle = ['layout' => \PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED, 'cellMarginLeft' => 80, 'cellMarginRight' => 80];

    // PhpWord-Instanz erstellen
    $phpWord = new PhpWord();
    $phpWord->addTableStyle('myTable', $tableStyle);

    // Abfrage für die Runden
    $pairsQuery = "SELECT * FROM cupPairs WHERE Year = ? ORDER BY Round, ID";
    $stmt = $conn->prepare($pairsQuery);
    if (!$stmt) {
        logFehler("SQL-Fehler in getCup prepare: " . $conn->error);
        return;
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    $pairsResult = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Runde 1 Tabelle
    $cupTable1 = createCupTable($phpWord, 1, $pairsResult);
    $templateProcessor->setComplexBlock('CUPR1', $cupTable1);

    // Runde 2 Tabelle
    $cupTable2 = createCupTable($phpWord, 2, $pairsResult);
    $templateProcessor->setComplexBlock('CUPR2', $cupTable2);

    // Finalrunde
    $finalQuery = "SELECT * FROM cupFinalResults WHERE Year = ? ORDER BY Result DESC, LowShot DESC";
    $stmt = $conn->prepare($finalQuery);
    if (!$stmt) {
        logFehler("SQL-Fehler in getCup Final prepare: " . $conn->error);
        return;
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $finalResult = $stmt->get_result();
    $section = $phpWord->addSection();
    $finalTable = $section->addTable($tableStyle);
    $i = 1;
    while ($row = $finalResult->fetch_assoc()) {
        $participant = getParticipantName($row['ParticipantID']);
        $finalTable->addRow(320);
        // Rangliste ohne Rahmen, nur der Sieger fett, Punkte rechtsbündig
        $zelle = ['valign' => VerticalJc::CENTER]; // ohne Rahmen wie die übrigen Ranglisten
        $schrift = ['size' => 8, 'bold' => $i === 1];
        $finalTable->addCell(450, $zelle)->addText("$i.", $schrift, ['spaceAfter' => 0]);
        $finalTable->addCell(2450, $zelle)->addText($participant, $schrift, ['spaceAfter' => 0]);
        $finalTable->addCell(650, $zelle)->addText($row['Result'], $schrift, ['spaceAfter' => 0, 'alignment' => Jc::END]);
        $i++;
    }
    $stmt->close();
    $templateProcessor->setComplexBlock('CUPF', $finalTable);

    // Standcup
    $finalStandQuery = "SELECT * FROM cupStandFinal WHERE Year = ? ORDER BY Result DESC";
    $stmt = $conn->prepare($finalStandQuery);
    if (!$stmt) {
        logFehler("SQL-Fehler in getCup Stand prepare: " . $conn->error);
        return;
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $finalResult = $stmt->get_result();
    $section = $phpWord->addSection();
    $standCupTable = $section->addTable($tableStyle);
    $i = 1;
    while ($row = $finalResult->fetch_assoc()) {
        $participant = $row['ParticipantName'];
        $standCupTable->addRow(320);
        // Rangliste ohne Rahmen, nur der Sieger fett, Punkte rechtsbündig
        $zelle = ['valign' => VerticalJc::CENTER]; // ohne Rahmen wie die übrigen Ranglisten
        $schrift = ['size' => 8, 'bold' => $i === 1];
        $standCupTable->addCell(450, $zelle)->addText("$i.", $schrift, ['spaceAfter' => 0]);
        $standCupTable->addCell(2450, $zelle)->addText($participant, $schrift, ['spaceAfter' => 0]);
        $standCupTable->addCell(650, $zelle)->addText($row['Result'], $schrift, ['spaceAfter' => 0, 'alignment' => Jc::END]);
        // Verein hinter den Punkten, unter der zweiten Namensspalte der Runden (Punkte bleiben untereinander)
        $standCupTable->addCell(2900, $zelle)->addText((string)($row['club'] ?? ''), $schrift, ['spaceAfter' => 0]);
        $i++;
    }
    $stmt->close();
    $templateProcessor->setComplexBlock('CUPS', $standCupTable);
    logDebug("getCup erfolgreich abgeschlossen");
}

function getJMA($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte GetJMA für Jahr: " . $selectedYear);

    // Styles
    $tableStyle      = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 50];
    $cellStyle       = ['valign' => 'center', 'alignment' => Jc::CENTER];
    $textStyle       = ['alignment' => Jc::CENTER];
    $boldFontStyle   = ['size' => 7, 'bold' => true, 'alignment' => Jc::CENTER]; // Anlass-Titel 7 pt: der längste (40 Zeichen) füllt die Kopfzeile sonst ganz
    $cellHeaderStyle = ['valign' => 'center', 'alignment' => Jc::CENTER];
    if (defined('PhpOffice\\PhpWord\\Style\\Cell::TEXT_DIR_BTLR')) {
        $cellHeaderStyle['textDirection'] = \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR;
    }

    // JM-Definitionen
    $jmQuery = "
        SELECT *
        FROM JMDefinition 
        WHERE year = ? AND Erweitert = 0 AND Info = 0
        ORDER BY 
            CASE 
                WHEN Bezeichnung = 'Obligatorisch' THEN 1
                WHEN Bezeichnung = 'Feldschiessen' THEN 2
                WHEN Bezeichnung LIKE '%Kantonalstich%' THEN 3
                WHEN Bezeichnung LIKE '%Sektionsmeisterschaft%' THEN 4
                ELSE 5
            END,
            Reihenfolge
    ";
    $stmt = $conn->prepare($jmQuery);
    if (!$stmt) { logFehler("SQL-Fehler in GetJMA prepare: ".$conn->error); return; }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $jmResult = $stmt->get_result();
    $jmDefs = [];
    $defById = [];
    $endstichDef = null;
    $kantiDef    = null;
    while ($r = $jmResult->fetch_assoc()) {
        $jmDefs[] = $r;
        $defById[(int)$r['ID']] = $r;
        if ($r['Bezeichnung'] === 'Endstich')             $endstichDef = $r;
        if ($r['Bezeichnung'] === 'Bester Kantonalstich') $kantiDef    = $r;
    }
    $stmt->close();
    if (empty($jmDefs)) {
        logDebug("GetJMA: keine JMDefinition-Zeilen gefunden");
        absendenHinweis('für ' . $selectedYear . ' sind keine Anlässe definiert, die Seiten bleiben leer.');
        $templateProcessor->setValue('JMA1', '');
        $templateProcessor->setValue('JMA2', '');
        return;
    }

    // Split
    $midPoint   = (int)ceil(count($jmDefs) / 2);
    $firstHalf  = array_slice($jmDefs, 0, $midPoint);
    $secondHalf = array_slice($jmDefs, $midPoint);

    // Spaltenbreiten (Twips) passend zum Satzspiegel (A5, 7568 Twips): Kopf- und Datenzeilen gleich breit, festes
    // Layout, damit Word und der PDF-Dienst gleich rechnen. Beide Hälften der Doppelseite mit derselben Anlass-Breite.
    $satzspiegel = 7568;
    $wRang = 400; $wName = 1700; $wTotal = 700;
    // Direkt an der Tabelle: festes Layout und enger Zellabstand links/rechts (oben/unten 0 wie bisher, sonst würden die Zeilen höher). Der benannte Stil «myTable» gelangt nicht in die
    // Vorlage (setComplexBlock übernimmt nur die Tabelle), darum wirkten seine Werte nie; Rahmen bleiben wie bisher weg.
    $jmTabelle = ['layout' => \PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED, 'cellMarginLeft' => 50, 'cellMarginRight' => 50];
    $wAnlass = (int)floor(min(
        ($satzspiegel - $wRang - $wName) / max(1, count($firstHalf)),
        ($satzspiegel - $wTotal) / max(1, count($secondHalf))
    ));

    // Tabellen
    $phpWord = new PhpWord();
    $phpWord->addTableStyle('myTable', $tableStyle);
    $table1 = new \PhpOffice\PhpWord\Element\Table($jmTabelle);
    $table1->addRow(3000);
    $table1->addCell($wRang,  ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
    $table1->addCell($wName, ['valign'=>'bottom','noWrap'=>true])->addText("Name", $boldFontStyle);
    foreach ($firstHalf as $d) {
        $c = $table1->addCell($wAnlass, $cellHeaderStyle);
        $c->addText($d['Bezeichnung'], $boldFontStyle);
    }
    $table1->addRow(200);
    $table1->addCell($wRang, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
    $table2 = new \PhpOffice\PhpWord\Element\Table($jmTabelle);
    $table2->addRow(3000);
    foreach ($secondHalf as $d) {
        $c = $table2->addCell($wAnlass, $cellHeaderStyle);
        $c->addText($d['Bezeichnung'], $boldFontStyle);
    }
    $table2->addCell($wTotal, ['valign'=>'bottom','noWrap'=>true])->addText("Total", $boldFontStyle);
    $table2->addRow(200);
    $table2->addCell($wRang, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);

    // Teilnehmer Kat. A
    $mitglieder = getParticipantData('Kat. A', $conn);

    // Helper: Spezialwerte & Skalierung
    $calcEndstich = function(int $mid) use ($conn, $selectedYear) {
        $sql = "SELECT
                    COALESCE(Schuss1,0)+COALESCE(Schuss2,0)+COALESCE(Schuss3,0)+COALESCE(Schuss4,0)+COALESCE(Schuss5,0)
                  + COALESCE(Schuss6,0)+COALESCE(Schuss7,0)+COALESCE(Schuss8,0)+COALESCE(Schuss9,0)+COALESCE(Schuss10,0) AS P
                FROM endstich WHERE MitgliedID=? AND Jahr=?";
        if (!$st = $conn->prepare($sql)) return null;
        $st->bind_param("ii", $mid, $selectedYear);
        $st->execute(); $res = $st->get_result(); $row = $res->fetch_assoc(); $st->close();
        return $row ? (float)$row['P'] : null;
    };
    $calcBestKanti = function(int $mid) use ($conn, $selectedYear) {
        $sql = "SELECT GREATEST(
                    COALESCE(Passe1,0),COALESCE(Passe2,0),COALESCE(Passe3,0),COALESCE(Passe4,0),COALESCE(Passe5,0)
                ) AS P FROM kantiresultate WHERE MitgliedID=? AND Jahr=?";
        if (!$st = $conn->prepare($sql)) return null;
        $st->bind_param("ii", $mid, $selectedYear);
        $st->execute(); $res = $st->get_result(); $row = $res->fetch_assoc(); $st->close();
        return $row ? (float)$row['P'] : null;
    };
    $applyScale = function($raw, array $defRow) {
        if ($raw === null) return null;
        $p = (float)$raw + (float)$defRow['Zuschlag'];
        $max = (int)$defRow['Maxpunkte'];
        if ($max > 0 && $p > $max) $p = $max;
        return $p;
    };
    $indexByDefId = function(array $defs) {
        $map = [];
        foreach ($defs as $idx => $d) $map[(int)$d['ID']] = $idx;
        return $map;
    };
    $idxFirst  = $indexByDefId($firstHalf);
    $idxSecond = $indexByDefId($secondHalf);

    // Wir sammeln erstmal alle Zeilen und sortieren dann
    $rows = [];
    foreach ($mitglieder as $mitglied) {
        $mid = (int)$mitglied['ID'];

        // Resultate laut Deiner Logik (inkl. Streicher/Umrechnungen)
        $resFirst  = getResultateForMember($mid, $firstHalf,  $mitglied['Streicher'], $conn);
        $resSecond = getResultateForMember($mid, $secondHalf, $mitglied['Streicher'], $conn);

        // Spezialwerte überschreiben (nur Anzeige + Totalsumme)
        if ($endstichDef) {
            $endID = (int)$endstichDef['ID'];
            $endVal = $applyScale($calcEndstich($mid), $endstichDef);
            if ($endVal !== null) {
                $show = function_exists('fmtNoTrailingZero') ? (string)fmtNoTrailingZero($endVal) : (string)$endVal;

                    // Wenn 0, als Bindestrich darstellen
                    if ($endVal == 0 || $endVal === 0.0) $show = '-';
                // isStreicher NICHT ueberschreiben: der Endstich ist Streicher=1 und kann
                // von GetStreicher als gestrichen markiert sein (analog zur Rangliste)
                if (isset($idxFirst[$endID]) && isset($resFirst[$idxFirst[$endID]])) {
                    $resFirst[$idxFirst[$endID]]['wert'] = $show;
                } elseif (isset($idxSecond[$endID]) && isset($resSecond[$idxSecond[$endID]])) {
                    $resSecond[$idxSecond[$endID]]['wert'] = $show;
                }
            }
        }
        if ($kantiDef) {
            $kID = (int)$kantiDef['ID'];
            $kVal = $applyScale($calcBestKanti($mid), $kantiDef);
            if ($kVal !== null) {
                $show = function_exists('fmtNoTrailingZero') ? (string)fmtNoTrailingZero($kVal) : (string)$kVal;

                    // Wenn 0, als Bindestrich darstellen
                    if ($kVal == 0 || $kVal === 0.0) $show = '-';
                if (isset($idxFirst[$kID]) && isset($resFirst[$idxFirst[$kID]])) {
                    $resFirst[$idxFirst[$kID]]['wert'] = $show;
                    $resFirst[$idxFirst[$kID]]['isStreicher'] = false;
                } elseif (isset($idxSecond[$kID]) && isset($resSecond[$idxSecond[$kID]])) {
                    $resSecond[$idxSecond[$kID]]['wert'] = $show;
                    $resSecond[$idxSecond[$kID]]['isStreicher'] = false;
                }
            }
        }

        // Total **neu** aus angezeigten Werten berechnen (Streicher NICHT zählen)
        $toNumber = function($v) {
            if ($v === null || $v === '') return 0.0;
            if (is_numeric($v)) return (float)$v;

            // Strings defensiv in Zahl verwandeln
            $v = str_replace([' ', ' '], '', (string)$v);      // NBSP/Spaces
            $v = str_replace(',', '.', $v);
            return is_numeric($v) ? (float)$v : 0.0;
        };
        $calcTotal = function(array $cells) use ($toNumber) {
            $sum = 0.0;
            foreach ($cells as $c) {
                if (!empty($c['isStreicher'])) continue;           // Streicher ignorieren
                $sum += $toNumber($c['wert']);
            }
            return $sum;
        };
        $totalA = $calcTotal($resFirst);
        $totalB = $calcTotal($resSecond);
        $computedTotal = $totalA + $totalB;
        $rows[] = [
            'id'        => $mid,
            'name'      => $mitglied['Name'],
            'vorname'   => $mitglied['Vorname'],
            'resFirst'  => $resFirst,
            'resSecond' => $resSecond,
            'total'     => $computedTotal,
        ];
    }

    // Sortierung nach Total DESC, dann Name/Vorname
    usort($rows, function($a, $b) {
        if ($a['total'] < $b['total']) return 1;
        if ($a['total'] > $b['total']) return -1;
        $n1 = $a['name'].' '.$a['vorname'];
        $n2 = $b['name'].' '.$b['vorname'];
        return strcasecmp($n1, $n2);
    });

    // Ausgabe
    $rang = 1;
    foreach ($rows as $row) {
        if ($rang == 4) {
            $table1->addRow(100);
            $table1->addCell($wRang, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
            $table2->addRow(100);
            $table2->addCell($wRang, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
        }
        $isTop3 = ($rang <= 3);
        $fontStyleRow = $isTop3 ? ['size'=>7.5,'bold'=>true] : ['size'=>7.5];
        $extra = ($rang % 2 === 0) ? ['bgColor' => 'F2F2F2'] : []; // jede zweite Zeile leicht grau

        // Tabelle 1
        $table1->addRow(200);
        $table1->addCell($wRang,  array_merge(['valign'=>'center','noWrap'=>true], $extra))->addText($rang.'.', $fontStyleRow);
        $table1->addCell($wName, array_merge(['valign'=>'center','noWrap'=>true], $extra))->addText($row['name'].' '.$row['vorname'], $fontStyleRow);
        foreach ($row['resFirst'] as $punkte) {
            $cellStyleFont = !empty($punkte['isStreicher']) ? array_merge($fontStyleRow, ['strikethrough'=>true]) : $fontStyleRow;
            $bgColor       = !empty($punkte['isStreicher']) ? ['bgColor'=>'D3D3D3'] : [];
            $table1->addCell($wAnlass, array_merge($cellStyle, $extra, $bgColor))
                   ->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }

        // Tabelle 2
        $table2->addRow(200);
        foreach ($row['resSecond'] as $punkte) {
            $cellStyleFont = !empty($punkte['isStreicher']) ? array_merge($fontStyleRow, ['strikethrough'=>true]) : $fontStyleRow;
            $bgColor       = !empty($punkte['isStreicher']) ? ['bgColor'=>'D3D3D3'] : [];
            $table2->addCell($wAnlass, array_merge($cellStyle, $extra, $bgColor))
                   ->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }
        $totalOut = number_format($row['total'], 2, '.', '');
        $table2->addCell($wTotal, array_merge(['valign'=>'center'], $extra))->addText($totalOut, $fontStyleRow);
        $rang++;
    }
    $templateProcessor->setComplexBlock('JMA1', $table1);
    $templateProcessor->setComplexBlock('JMA2', $table2);
    logDebug("GetJMA erfolgreich abgeschlossen");
}

function getJMB($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte GetJMB für Jahr: " . $selectedYear);

    // Styles
    $tableStyle      = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 50];
    $cellStyle       = ['valign' => 'center', 'alignment' => Jc::CENTER];
    $textStyle       = ['alignment' => Jc::CENTER];
    $boldFontStyle   = ['size' => 7, 'bold' => true, 'alignment' => Jc::CENTER]; // Anlass-Titel 7 pt: der längste (40 Zeichen) füllt die Kopfzeile sonst ganz
    $cellHeaderStyle = ['valign' => 'center', 'alignment' => Jc::CENTER];
    if (defined('PhpOffice\\PhpWord\\Style\\Cell::TEXT_DIR_BTLR')) {
        $cellHeaderStyle['textDirection'] = \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR;
    }

    // JM-Definitionen
    $jmQuery = "
        SELECT *
        FROM JMDefinition 
        WHERE year = ? AND Erweitert = 0 AND Info = 0
        ORDER BY 
            CASE 
                WHEN Bezeichnung = 'Obligatorisch' THEN 1
                WHEN Bezeichnung = 'Feldschiessen' THEN 2
                WHEN Bezeichnung LIKE '%Kantonalstich%' THEN 3
                WHEN Bezeichnung LIKE '%Sektionsmeisterschaft%' THEN 4
                ELSE 5
            END,
            Reihenfolge
    ";
    $stmt = $conn->prepare($jmQuery);
    if (!$stmt) { logFehler("SQL-Fehler in GetJMB prepare: ".$conn->error); return; }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $jmResult = $stmt->get_result();
    $jmDefs = [];
    $defById = [];
    $endstichDef = null;
    $kantiDef    = null;
    while ($r = $jmResult->fetch_assoc()) {
        $jmDefs[] = $r;
        $defById[(int)$r['ID']] = $r;
        if ($r['Bezeichnung'] === 'Endstich')             $endstichDef = $r;
        if ($r['Bezeichnung'] === 'Bester Kantonalstich') $kantiDef    = $r;
    }
    $stmt->close();
    if (empty($jmDefs)) {
        logDebug("GetJMB: keine JMDefinition-Zeilen gefunden");
        absendenHinweis('für ' . $selectedYear . ' sind keine Anlässe definiert, die Seiten bleiben leer.');
        $templateProcessor->setValue('JMB1', '');
        $templateProcessor->setValue('JMB2', '');
        return;
    }

    // Split
    $midPoint   = (int)ceil(count($jmDefs) / 2);
    $firstHalf  = array_slice($jmDefs, 0, $midPoint);
    $secondHalf = array_slice($jmDefs, $midPoint);

    // Spaltenbreiten (Twips) passend zum Satzspiegel (A5, 7568 Twips): Kopf- und Datenzeilen gleich breit, festes
    // Layout, damit Word und der PDF-Dienst gleich rechnen. Beide Hälften der Doppelseite mit derselben Anlass-Breite.
    $satzspiegel = 7568;
    $wRang = 400; $wName = 1700; $wTotal = 700;
    // Direkt an der Tabelle: festes Layout und enger Zellabstand links/rechts (oben/unten 0 wie bisher, sonst würden die Zeilen höher). Der benannte Stil «myTable» gelangt nicht in die
    // Vorlage (setComplexBlock übernimmt nur die Tabelle), darum wirkten seine Werte nie; Rahmen bleiben wie bisher weg.
    $jmTabelle = ['layout' => \PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED, 'cellMarginLeft' => 50, 'cellMarginRight' => 50];
    $wAnlass = (int)floor(min(
        ($satzspiegel - $wRang - $wName) / max(1, count($firstHalf)),
        ($satzspiegel - $wTotal) / max(1, count($secondHalf))
    ));

    // Tabellen
    $phpWord = new PhpWord();
    $phpWord->addTableStyle('myTable', $tableStyle);
    $table1 = new \PhpOffice\PhpWord\Element\Table($jmTabelle);
    $table1->addRow(3000);
    $table1->addCell($wRang,  ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
    $table1->addCell($wName, ['valign'=>'bottom','noWrap'=>true])->addText("Name", $boldFontStyle);
    foreach ($firstHalf as $d) {
        $c = $table1->addCell($wAnlass, $cellHeaderStyle);
        $c->addText($d['Bezeichnung'], $boldFontStyle);
    }
    $table1->addRow(200);
    $table1->addCell($wRang, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
    $table2 = new \PhpOffice\PhpWord\Element\Table($jmTabelle);
    $table2->addRow(3000);
    foreach ($secondHalf as $d) {
        $c = $table2->addCell($wAnlass, $cellHeaderStyle);
        $c->addText($d['Bezeichnung'], $boldFontStyle);
    }
    $table2->addCell($wTotal, ['valign'=>'bottom','noWrap'=>true])->addText("Total", $boldFontStyle);
    $table2->addRow(200);
    $table2->addCell($wRang, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);

    // Teilnehmer Kat. A
    $mitglieder = getParticipantData('Kat. B', $conn);

    // Helper: Spezialwerte & Skalierung
    $calcEndstich = function(int $mid) use ($conn, $selectedYear) {
        $sql = "SELECT
                    COALESCE(Schuss1,0)+COALESCE(Schuss2,0)+COALESCE(Schuss3,0)+COALESCE(Schuss4,0)+COALESCE(Schuss5,0)
                  + COALESCE(Schuss6,0)+COALESCE(Schuss7,0)+COALESCE(Schuss8,0)+COALESCE(Schuss9,0)+COALESCE(Schuss10,0) AS P
                FROM endstich WHERE MitgliedID=? AND Jahr=?";
        if (!$st = $conn->prepare($sql)) return null;
        $st->bind_param("ii", $mid, $selectedYear);
        $st->execute(); $res = $st->get_result(); $row = $res->fetch_assoc(); $st->close();
        return $row ? (float)$row['P'] : null;
    };
    $calcBestKanti = function(int $mid) use ($conn, $selectedYear) {
        $sql = "SELECT GREATEST(
                    COALESCE(Passe1,0),COALESCE(Passe2,0),COALESCE(Passe3,0),COALESCE(Passe4,0),COALESCE(Passe5,0)
                ) AS P FROM kantiresultate WHERE MitgliedID=? AND Jahr=?";
        if (!$st = $conn->prepare($sql)) return null;
        $st->bind_param("ii", $mid, $selectedYear);
        $st->execute(); $res = $st->get_result(); $row = $res->fetch_assoc(); $st->close();
        return $row ? (float)$row['P'] : null;
    };
    $applyScale = function($raw, array $defRow) {
        if ($raw === null) return null;
        $p = (float)$raw + (float)$defRow['Zuschlag'];
        $max = (int)$defRow['Maxpunkte'];
        if ($max > 0 && $p > $max) $p = $max;
        return $p;
    };
    $indexByDefId = function(array $defs) {
        $map = [];
        foreach ($defs as $idx => $d) $map[(int)$d['ID']] = $idx;
        return $map;
    };
    $idxFirst  = $indexByDefId($firstHalf);
    $idxSecond = $indexByDefId($secondHalf);

    // Wir sammeln erstmal alle Zeilen und sortieren dann
    $rows = [];
    foreach ($mitglieder as $mitglied) {
        $mid = (int)$mitglied['ID'];

        // Resultate laut Deiner Logik (inkl. Streicher/Umrechnungen)
        $resFirst  = getResultateForMember($mid, $firstHalf,  $mitglied['Streicher'], $conn);
        $resSecond = getResultateForMember($mid, $secondHalf, $mitglied['Streicher'], $conn);

        // Spezialwerte überschreiben (nur Anzeige + Totalsumme)
        if ($endstichDef) {
            $endID = (int)$endstichDef['ID'];
            $endVal = $applyScale($calcEndstich($mid), $endstichDef);
            if ($endVal !== null) {
                $show = function_exists('fmtNoTrailingZero') ? (string)fmtNoTrailingZero($endVal) : (string)$endVal;

                    // Wenn 0, als Bindestrich darstellen
                    if ($endVal == 0 || $endVal === 0.0) $show = '-';
                // isStreicher NICHT ueberschreiben: der Endstich ist Streicher=1 und kann
                // von GetStreicher als gestrichen markiert sein (analog zur Rangliste)
                if (isset($idxFirst[$endID]) && isset($resFirst[$idxFirst[$endID]])) {
                    $resFirst[$idxFirst[$endID]]['wert'] = $show;
                } elseif (isset($idxSecond[$endID]) && isset($resSecond[$idxSecond[$endID]])) {
                    $resSecond[$idxSecond[$endID]]['wert'] = $show;
                }
            }
        }
        if ($kantiDef) {
            $kID = (int)$kantiDef['ID'];
            $kVal = $applyScale($calcBestKanti($mid), $kantiDef);
            if ($kVal !== null) {
                $show = function_exists('fmtNoTrailingZero') ? (string)fmtNoTrailingZero($kVal) : (string)$kVal;

                    // Wenn 0, als Bindestrich darstellen
                    if ($kVal == 0 || $kVal === 0.0) $show = '-';
                if (isset($idxFirst[$kID]) && isset($resFirst[$idxFirst[$kID]])) {
                    $resFirst[$idxFirst[$kID]]['wert'] = $show;
                    $resFirst[$idxFirst[$kID]]['isStreicher'] = false;
                } elseif (isset($idxSecond[$kID]) && isset($resSecond[$idxSecond[$kID]])) {
                    $resSecond[$idxSecond[$kID]]['wert'] = $show;
                    $resSecond[$idxSecond[$kID]]['isStreicher'] = false;
                }
            }
        }

        // Total **neu** aus angezeigten Werten berechnen (Streicher NICHT zählen)
        $toNumber = function($v) {
            if ($v === null || $v === '') return 0.0;
            if (is_numeric($v)) return (float)$v;

            // Strings defensiv in Zahl verwandeln
            $v = str_replace([' ', ' '], '', (string)$v);      // NBSP/Spaces
            $v = str_replace(',', '.', $v);
            return is_numeric($v) ? (float)$v : 0.0;
        };
        $calcTotal = function(array $cells) use ($toNumber) {
            $sum = 0.0;
            foreach ($cells as $c) {
                if (!empty($c['isStreicher'])) continue;           // Streicher ignorieren
                $sum += $toNumber($c['wert']);
            }
            return $sum;
        };
        $totalA = $calcTotal($resFirst);
        $totalB = $calcTotal($resSecond);
        $computedTotal = $totalA + $totalB;
        $rows[] = [
            'id'        => $mid,
            'name'      => $mitglied['Name'],
            'vorname'   => $mitglied['Vorname'],
            'resFirst'  => $resFirst,
            'resSecond' => $resSecond,
            'total'     => $computedTotal,
        ];
    }

    // Sortierung nach Total DESC, dann Name/Vorname
    usort($rows, function($a, $b) {
        if ($a['total'] < $b['total']) return 1;
        if ($a['total'] > $b['total']) return -1;
        $n1 = $a['name'].' '.$a['vorname'];
        $n2 = $b['name'].' '.$b['vorname'];
        return strcasecmp($n1, $n2);
    });

    // Ausgabe
    $rang = 1;
    foreach ($rows as $row) {
        if ($rang == 4) {
            $table1->addRow(100);
            $table1->addCell($wRang, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
            $table2->addRow(100);
            $table2->addCell($wRang, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
        }
        $isTop3 = ($rang <= 3);
        $fontStyleRow = $isTop3 ? ['size'=>7.5,'bold'=>true] : ['size'=>7.5];
        $extra = ($rang % 2 === 0) ? ['bgColor' => 'F2F2F2'] : []; // jede zweite Zeile leicht grau

        // Tabelle 1
        $table1->addRow(200);
        $table1->addCell($wRang,  array_merge(['valign'=>'center','noWrap'=>true], $extra))->addText($rang.'.', $fontStyleRow);
        $table1->addCell($wName, array_merge(['valign'=>'center','noWrap'=>true], $extra))->addText($row['name'].' '.$row['vorname'], $fontStyleRow);
        foreach ($row['resFirst'] as $punkte) {
            $cellStyleFont = !empty($punkte['isStreicher']) ? array_merge($fontStyleRow, ['strikethrough'=>true]) : $fontStyleRow;
            $bgColor       = !empty($punkte['isStreicher']) ? ['bgColor'=>'D3D3D3'] : [];
            $table1->addCell($wAnlass, array_merge($cellStyle, $extra, $bgColor))
                   ->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }

        // Tabelle 2
        $table2->addRow(200);
        foreach ($row['resSecond'] as $punkte) {
            $cellStyleFont = !empty($punkte['isStreicher']) ? array_merge($fontStyleRow, ['strikethrough'=>true]) : $fontStyleRow;
            $bgColor       = !empty($punkte['isStreicher']) ? ['bgColor'=>'D3D3D3'] : [];
            $table2->addCell($wAnlass, array_merge($cellStyle, $extra, $bgColor))
                   ->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }
        $totalOut = number_format($row['total'], 2, '.', '');
        $table2->addCell($wTotal, array_merge(['valign'=>'center'], $extra))->addText($totalOut, $fontStyleRow);
        $rang++;
    }
    $templateProcessor->setComplexBlock('JMB1', $table1);
    $templateProcessor->setComplexBlock('JMB2', $table2);
    logDebug("GetJMB erfolgreich abgeschlossen");
}

// ========================================
// HILFSFUNKTIONEN

// ========================================
function calculatePoints($value) {
    if ($value >= 91) return 10;
    if ($value >= 81) return 9;
    if ($value >= 71) return 8;
    if ($value >= 61) return 7;
    if ($value >= 51) return 6;
    if ($value >= 41) return 5;
    if ($value >= 31) return 4;
    if ($value >= 21) return 3;
    if ($value >= 11) return 2;
    if ($value >= 1) return 1;
    return 0;
}

function getLoserInThreePair($row) {

    // Array mit Teilnehmerdaten
    $results = [
        ['Participant' => 1, 'Result' => $row['Result1'], 'LowShot' => $row['LowShot1']],
        ['Participant' => 2, 'Result' => $row['Result2'], 'LowShot' => $row['LowShot2']],
        ['Participant' => 3, 'Result' => $row['Result3'], 'LowShot' => $row['LowShot3']]
    ];

    // Finde den Verlierer
    $loserIndex = 0;
    for ($i = 1; $i < 3; $i++) {
        if ($results[$i]['Result'] < $results[$loserIndex]['Result']) {
            $loserIndex = $i;
        } elseif ($results[$i]['Result'] == $results[$loserIndex]['Result']) {
            if ($results[$i]['LowShot'] < $results[$loserIndex]['LowShot']) {
                $loserIndex = $i;
            }
        }
    }
    return $results[$loserIndex]['Participant'];
}

// Liefert die Positionen (1/2/3) der Verlierer einer Dreier-Paarung.
// Berücksichtigt Advancers (1 oder 2 kommen weiter) und ManualWinner
// (positiv = Gewinner, negativ = ausgeschiedener bei Dreier-Gleichstand).
function getThreePairLosers($row) {
    $advancers = (isset($row['Advancers']) && (int)$row['Advancers'] === 1) ? 1 : 2;
    $manual = !empty($row['ManualWinner']) ? (int)$row['ManualWinner'] : 0;

    $parts = [
        1 => ['id' => (int)$row['Participant1'], 'r' => (int)$row['Result1'], 'ls' => (int)$row['LowShot1']],
        2 => ['id' => (int)$row['Participant2'], 'r' => (int)$row['Result2'], 'ls' => (int)$row['LowShot2']],
        3 => ['id' => (int)$row['Participant3'], 'r' => (int)$row['Result3'], 'ls' => (int)$row['LowShot3']],
    ];

    // Positionen nach Resultat (absteigend), dann Tiefschuss (absteigend) sortieren
    $order = [1, 2, 3];
    usort($order, function($a, $b) use ($parts) {
        if ($parts[$a]['r'] === $parts[$b]['r']) {
            return $parts[$b]['ls'] - $parts[$a]['ls'];
        }
        return $parts[$b]['r'] - $parts[$a]['r'];
    });

    // Gewinner-IDs bestimmen
    $winnerIds = [];
    if ($advancers === 1) {
        $winnerIds = ($manual > 0) ? [$manual] : [$parts[$order[0]]['id']];
    } else {
        if ($manual < 0) {
            $loserId = abs($manual);
            foreach ($parts as $p) { if ($p['id'] !== $loserId) { $winnerIds[] = $p['id']; } }
        } elseif ($manual > 0) {
            $winnerIds = [$manual];
            foreach ($order as $pos) {
                if ($parts[$pos]['id'] !== $manual && count($winnerIds) < 2) { $winnerIds[] = $parts[$pos]['id']; }
            }
        } else {
            $winnerIds = [$parts[$order[0]]['id'], $parts[$order[1]]['id']];
        }
    }

    // Verlierer = Positionen, deren ID nicht zu den Gewinnern gehört
    $losers = [];
    foreach ($parts as $pos => $p) {
        if (!in_array($p['id'], $winnerIds)) { $losers[] = $pos; }
    }
    return $losers;
}

function getParticipantData($kategorie, $conn) {
    $total = getTotal($kategorie, $conn);
    $streicher = GetStreicher($kategorie, $conn);
    $sqlMitglieder = "SELECT m.ID, m.Vorname, m.Name FROM mitglieder m
                      INNER JOIN Waffen w ON w.ID = m.WaffenID
                      WHERE w.Kategorie = ? AND m.Status = 1";
    $stmt = $conn->prepare($sqlMitglieder);
    if (!$stmt) {
        logFehler("SQL-Fehler in getParticipantData: " . $conn->error);
        return array();
    }
    $stmt->bind_param("s", $kategorie);
    $stmt->execute();
    $mitgliederResult = $stmt->get_result();
    $mitglieder = [];
    while ($row = $mitgliederResult->fetch_assoc()) {
        $mitgliedID = $row['ID'];
        $mitglieder[] = [
            'ID' => $mitgliedID,
            'Name' => $row['Name'],
            'Vorname' => $row['Vorname'],
            'Total' => isset($total[$mitgliedID]) ? $total[$mitgliedID] : 0,
            'Streicher' => isset($streicher[$mitgliedID]) ? $streicher[$mitgliedID] : [],
        ];
    }
    $stmt->close();

    // Nach Total sortieren
    usort($mitglieder, function ($a, $b) {
        return $b['Total'] <=> $a['Total'];
    });
    return $mitglieder;
}

function getResultateForMember($mitgliedID, $jmData, $streicher, $conn) {
    global $selectedYear;
    $resultate = [];
    foreach ($jmData as $jmRow) {
        $wettbewerbID = $jmRow['ID'];
        $sqlPunkte = "
            SELECT 
                MAX(jm.Punkte) AS Punkte,
                jd.Streicher,
                jd.Maxpunkte
            FROM 
                jmresultate jm
            JOIN 
                JMDefinition jd ON jm.jmdefinitionID = jd.ID AND jd.year = ?
            WHERE 
                jm.mitgliederID = ?
                AND jd.ID = ?
            GROUP BY jd.ID, jd.Streicher, jd.Maxpunkte
        ";
        $stmt = $conn->prepare($sqlPunkte);
        if (!$stmt) {
            $resultate[] = ['wert' => '-', 'isStreicher' => false];
            continue;
        }
        $stmt->bind_param("iii", $selectedYear, $mitgliedID, $wettbewerbID);
        $stmt->execute();
        $punkteResult = $stmt->get_result();
        if ($punkteResult->num_rows > 0) {
            $punkteRow = $punkteResult->fetch_assoc();
            $punkte = $punkteRow['Punkte'];
            // Hochrechnung auf 100 nur wenn Maxpunkte < 100; ueber 100 (z.B. 120) wird nie umgerechnet
            if ($punkteRow['Streicher'] == 1 && $punkteRow['Maxpunkte'] > 0 && $punkteRow['Maxpunkte'] < 100) {
                $punkte = round(($punkte * 100.0 / $punkteRow['Maxpunkte']), 2);
            }

            // Wenn Punkte 0 sind, als Bindestrich darstellen
            if ($punkte == 0 || $punkte === 0.0) {
                $punkte = '-';
            }
        } else {
            $punkte = '-';
        }
        $stmt->close();
        $isStreicher = in_array($wettbewerbID, $streicher);
        $resultate[] = [
            'wert' => $punkte,
            'isStreicher' => $isStreicher
        ];
    }
    return $resultate;
}

// ========================================================================
// Sonderfaelle Endstich / Bester Kantonalstich: deren Resultate liegen in den
// eigenen Tabellen endstich/kantiresultate, NICHT in jmresultate (analog zur
// JM-Rangliste inc/jmrang/load_jm.php).
// ========================================================================

// Endstich-Punkte eines Mitglieds (Summe der 10 Schuesse, beste Zeile); null = keine
function absendenEndstichPunkte($conn, $year, $mitgliedID) {
    $sql = "SELECT MAX(
                COALESCE(Schuss1,0)+COALESCE(Schuss2,0)+COALESCE(Schuss3,0)+COALESCE(Schuss4,0)+COALESCE(Schuss5,0)+
                COALESCE(Schuss6,0)+COALESCE(Schuss7,0)+COALESCE(Schuss8,0)+COALESCE(Schuss9,0)+COALESCE(Schuss10,0)
            ) AS P FROM endstich WHERE MitgliedID = ? AND Jahr = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("ii", $mitgliedID, $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row && $row['P'] !== null) ? (float)$row['P'] : null;
}

// Bester Kantonalstich eines Mitglieds (hoechste Passe ueber alle Zeilen); null = keine
function absendenKantiPunkte($conn, $year, $mitgliedID) {
    $sql = "SELECT MAX(GREATEST(
                COALESCE(Passe1,0),COALESCE(Passe2,0),COALESCE(Passe3,0),COALESCE(Passe4,0),COALESCE(Passe5,0)
            )) AS P FROM kantiresultate WHERE MitgliedID = ? AND Jahr = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("ii", $mitgliedID, $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row && $row['P'] !== null) ? (float)$row['P'] : null;
}

// Gibt es fuer den Sonderfall ueberhaupt Resultate im Jahr? (Aktiv-Check Streicher-Pool)
function absendenSonderfallAktiv($conn, $year, $bezeichnung) {
    $table = ($bezeichnung === 'Endstich') ? 'endstich' : 'kantiresultate';
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM $table WHERE Jahr = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && (int)$row['c'] > 0;
}

// Normalisierter Streicher-Wert eines Mitglieds fuer einen Streicher-Wettkampf
// (0 = nicht teilgenommen). Hochrechnung auf 100 nur wenn Maxpunkte < 100;
// ueber 100 (z.B. 120) wird nie umgerechnet.
function absendenStreicherWert($conn, $year, $mitgliedID, array $wettkampf) {
    $bez = $wettkampf['Bezeichnung'] ?? '';
    if ($bez === 'Endstich') {
        $punkte = absendenEndstichPunkte($conn, $year, $mitgliedID) ?? 0;
    } elseif ($bez === 'Bester Kantonalstich') {
        $punkte = absendenKantiPunkte($conn, $year, $mitgliedID) ?? 0;
    } else {
        $punkte = 0;
        $stmt = $conn->prepare("SELECT MAX(Punkte) AS Punkte FROM jmresultate WHERE mitgliederID = ? AND jmdefinitionID = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $mitgliedID, $wettkampf['ID']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && $row['Punkte'] !== null) $punkte = $row['Punkte'];
        }
    }
    $maxPunkte = (int)$wettkampf['Maxpunkte'];
    if ($punkte > 0 && $maxPunkte > 0 && $maxPunkte < 100) {
        $punkte = round(($punkte / $maxPunkte) * 100, 2);
    }
    return $punkte;
}

// ========================================================================
// ERSETZE DIE KOMPLETTE getTotal() FUNKTION (ab Zeile 2477)

// ========================================================================
function getTotal($kategorie, $conn) {
    global $selectedYear;
    $resultatetotal = array();

    // Überprüfen ob SSM existiert
    $ssmExists = checkIfSSMExists($conn);

    // Erst alle Wettkämpfe mit Streicher=1 holen
    $sqlWettkaempfe = "SELECT ID, Bezeichnung, Maxpunkte FROM JMDefinition
                       WHERE year = ? AND Streicher = 1 AND Info = 0 AND Erweitert = 0
                       ORDER BY Reihenfolge";
    $stmt = $conn->prepare($sqlWettkaempfe);
    if (!$stmt) {
        logFehler("SQL-Fehler in getTotal (Wettkämpfe): " . $conn->error);
        return array();
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $wettkaempfeResult = $stmt->get_result();
    $wettkaempfe = array();
    while ($row = $wettkaempfeResult->fetch_assoc()) {
        $wettkaempfe[] = array(
            'ID' => $row['ID'],
            'Bezeichnung' => $row['Bezeichnung'],
            'Maxpunkte' => $row['Maxpunkte']
        );
    }
    $stmt->close();

    // ==========================================
    // ÄNDERUNG: Nur aktive Wettbewerbe berücksichtigen

    // ==========================================
    $activeStreicherIDs = array();
    foreach ($wettkaempfe as $wettkampf) {
        $wettbewerbID = $wettkampf['ID'];

        // Endstich/Bester Kantonalstich: Resultate liegen in eigenen Tabellen, nicht in jmresultate
        if (in_array($wettkampf['Bezeichnung'] ?? '', array('Endstich', 'Bester Kantonalstich'), true)) {
            if (absendenSonderfallAktiv($conn, $selectedYear, $wettkampf['Bezeichnung'])) {
                $activeStreicherIDs[] = $wettbewerbID;
            }
            continue;
        }

        $sqlCheck = "SELECT COUNT(*) as count FROM jmresultate WHERE jmdefinitionID = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        if ($stmtCheck) {
            $stmtCheck->bind_param("i", $wettbewerbID);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            $rowCheck = $resCheck->fetch_assoc();
            $stmtCheck->close();

            // Nur wenn mindestens 1 Resultat vorhanden
            if ($rowCheck['count'] > 0) {
                $activeStreicherIDs[] = $wettbewerbID;
            }
        }
    }

    // Filtere: Nur aktive Wettbewerbe verwenden
    $wettkaempfe = array_filter($wettkaempfe, function($w) use ($activeStreicherIDs) {
        return in_array($w['ID'], $activeStreicherIDs);
    });

    // Re-index nach dem Filter
    $wettkaempfe = array_values($wettkaempfe);
    logDebug("getTotal - Aktive Streicher-Wettbewerbe: " . count($wettkaempfe));

    // Sonderfaelle mit Streicher=0 (z.B. Bester Kantonalstich): Resultate aus eigenen Tabellen
    $fixSonderDefs = array();
    $stmtS = $conn->prepare("SELECT ID, Bezeichnung FROM JMDefinition
                             WHERE year = ? AND Erweitert = 0 AND Info = 0 AND Streicher = 0
                               AND Bezeichnung IN ('Endstich', 'Bester Kantonalstich')");
    if ($stmtS) {
        $stmtS->bind_param("i", $selectedYear);
        $stmtS->execute();
        $resS = $stmtS->get_result();
        while ($rowS = $resS->fetch_assoc()) {
            $fixSonderDefs[] = $rowS;
        }
        $stmtS->close();
    }

    // ==========================================
    // Mitglieder abrufen
    $sqlMitgliederA = "SELECT m.id FROM mitglieder m
                       INNER JOIN Waffen w ON w.ID = m.WaffenID
                       WHERE w.Kategorie = ? AND m.Status = 1";
    $stmt = $conn->prepare($sqlMitgliederA);
    if (!$stmt) {
        logFehler("SQL-Fehler in getTotal: " . $conn->error);
        return array();
    }
    $stmt->bind_param("s", $kategorie);
    $stmt->execute();
    $mitglieder = $stmt->get_result();
    $stmt->close();
    if ($mitglieder->num_rows > 0) {
        while ($mitglied = $mitglieder->fetch_assoc()) {
            $mitgliedID = $mitglied['id'];
            $totalFix = 0;

            // Resultate mit Streicher = 0 (Endstich/Kanti ausgenommen: eigene Tabellen, s.u.)
            $sqlResultateFix = "
                SELECT MAX(jm.Punkte) AS Punkte
                FROM jmresultate jm
                INNER JOIN JMDefinition jd ON jd.ID = jm.jmdefinitionID
                WHERE jm.mitgliederID = ?
                  AND jd.Streicher = 0
                  AND jd.Info = 0
                  AND jd.Erweitert = 0
                  AND jd.year = ?
                  AND jd.Bezeichnung NOT IN ('Endstich', 'Bester Kantonalstich')
                GROUP BY jm.jmdefinitionID
            ";
            $stmt = $conn->prepare($sqlResultateFix);
            if ($stmt) {
                $stmt->bind_param("ii", $mitgliedID, $selectedYear);
                $stmt->execute();
                $punkteFix = $stmt->get_result();
                while ($row = $punkteFix->fetch_assoc()) {
                    $totalFix += $row['Punkte'];
                }
                $stmt->close();
            }

            // Sonderfaelle mit Streicher=0 aus ihren eigenen Tabellen dazuzaehlen
            foreach ($fixSonderDefs as $sd) {
                $p = ($sd['Bezeichnung'] === 'Endstich')
                    ? absendenEndstichPunkte($conn, $selectedYear, $mitgliedID)
                    : absendenKantiPunkte($conn, $selectedYear, $mitgliedID);
                if ($p !== null) {
                    $totalFix += $p;
                }
            }

            // Resultate mit Streicher = 1 - NUR MIT AKTIVEN WETTKÄMPFEN
            $streicherArray = array();
            foreach ($wettkaempfe as $wettkampf) {
                // Wert holen (Endstich/Kanti aus eigenen Tabellen) und normalisieren
                $streicherArray[] = array(
                    'WettbewerbID' => $wettkampf['ID'],
                    'NormalizedPoints' => absendenStreicherWert($conn, $selectedYear, $mitgliedID, $wettkampf)
                );
            }

            // Zähle fehlende Resultate (0-Werte)
            $fehlendeResultate = array_filter($streicherArray, function($r) {
                return $r['NormalizedPoints'] == 0 || $r['NormalizedPoints'] === 0.0;
            });
            $excludeCount = getExcludeCount($conn, $selectedYear);

            // Wenn mehr fehlende als Streicher erlaubt: Nimm die ersten nach Reihenfolge (sind bereits sortiert)
            if (count($fehlendeResultate) > $excludeCount) {

                // Streiche die ersten $excludeCount fehlenden Wettbewerbe
                $gestricheneIDs = array();
                $count = 0;
                foreach ($streicherArray as $result) {
                    if ($result['NormalizedPoints'] == 0 || $result['NormalizedPoints'] === 0.0) {
                        $gestricheneIDs[] = $result['WettbewerbID'];
                        $count++;
                        if ($count >= $excludeCount) break;
                    }
                }

                // Filtere die verbleibenden Resultate
                $verbleibendeResultate = array_filter($streicherArray, function($r) use ($gestricheneIDs) {
                    return !in_array($r['WettbewerbID'], $gestricheneIDs);
                });
            } else {

                // Normale Logik: Die $excludeCount niedrigsten Resultate streichen
                usort($streicherArray, function ($a, $b) {
                    return $a['NormalizedPoints'] <=> $b['NormalizedPoints'];
                });
                $verbleibendeResultate = array_slice($streicherArray, $excludeCount);
            }
            $totalStreicher = 0;
            foreach ($verbleibendeResultate as $result) {
                $totalStreicher += $result['NormalizedPoints'];
            }

            // Gesamtpunktzahl
            $total = $totalFix + $totalStreicher;
            $resultatetotal[$mitgliedID] = round($total, 2);

            // Debug-Ausgabe
            $fehlendCount = count($fehlendeResultate);
            $logikType = ($fehlendCount > 3) ? "REIHENFOLGE (>3 fehlend)" : "NIEDRIGSTE WERTE";
            logDebug("getTotal - Mitglied $mitgliedID: Fix=$totalFix, Streicher=$totalStreicher, Total=$total (" . count($streicherArray) . " Wettkämpfe, " . count($verbleibendeResultate) . " gewertet, $fehlendCount fehlend, Logik: $logikType)");
        }
    }
    arsort($resultatetotal);
    return $resultatetotal;
}

// ========================================================================
// ERSETZE DIE KOMPLETTE GetStreicher() FUNKTION (ab Zeile 2616)

// ========================================================================
function GetStreicher($kategorie, $conn) {
    global $selectedYear;
    $MitgliedStreicher = array();

    // Überprüfen ob SSM existiert
    $ssmExists = checkIfSSMExists($conn);

    // Erst alle Wettkämpfe mit Streicher=1 holen
    $sqlWettkaempfe = "SELECT ID, Bezeichnung, Maxpunkte FROM JMDefinition
                       WHERE year = ? AND Streicher = 1 AND Info = 0 AND Erweitert = 0";
    if ($ssmExists) {
        $sqlWettkaempfe .= " AND Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
    }
    $sqlWettkaempfe .= " ORDER BY Reihenfolge";
    $stmt = $conn->prepare($sqlWettkaempfe);
    if (!$stmt) {
        logFehler("SQL-Fehler in GetStreicher (Wettkämpfe): " . $conn->error);
        return array();
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $wettkaempfeResult = $stmt->get_result();
    $wettkaempfe = array();
    while ($row = $wettkaempfeResult->fetch_assoc()) {
        $wettkaempfe[] = array(
            'ID' => $row['ID'],
            'Bezeichnung' => $row['Bezeichnung'],
            'Maxpunkte' => $row['Maxpunkte']
        );
    }
    $stmt->close();

    // ==========================================
    // ÄNDERUNG: Nur aktive Wettbewerbe berücksichtigen

    // ==========================================
    $activeStreicherIDs = array();
    foreach ($wettkaempfe as $wettkampf) {
        $wettbewerbID = $wettkampf['ID'];

        // Endstich/Bester Kantonalstich: Resultate liegen in eigenen Tabellen, nicht in jmresultate
        if (in_array($wettkampf['Bezeichnung'] ?? '', array('Endstich', 'Bester Kantonalstich'), true)) {
            if (absendenSonderfallAktiv($conn, $selectedYear, $wettkampf['Bezeichnung'])) {
                $activeStreicherIDs[] = $wettbewerbID;
            }
            continue;
        }

        $sqlCheck = "SELECT COUNT(*) as count FROM jmresultate WHERE jmdefinitionID = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        if ($stmtCheck) {
            $stmtCheck->bind_param("i", $wettbewerbID);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            $rowCheck = $resCheck->fetch_assoc();
            $stmtCheck->close();

            // Nur wenn mindestens 1 Resultat vorhanden
            if ($rowCheck['count'] > 0) {
                $activeStreicherIDs[] = $wettbewerbID;
            }
        }
    }

    // Filtere: Nur aktive Wettbewerbe verwenden
    $wettkaempfe = array_filter($wettkaempfe, function($w) use ($activeStreicherIDs) {
        return in_array($w['ID'], $activeStreicherIDs);
    });

    // Re-index nach dem Filter
    $wettkaempfe = array_values($wettkaempfe);
    logDebug("GetStreicher - Aktive Streicher-Wettbewerbe: " . count($wettkaempfe));

    // ==========================================
    // Mitglieder holen
    $sqlMitglieder = "SELECT m.id FROM mitglieder m
                      INNER JOIN Waffen w ON w.ID = m.WaffenID
                      WHERE w.Kategorie = ? AND m.Status = 1";
    $stmt = $conn->prepare($sqlMitglieder);
    if (!$stmt) {
        logFehler("SQL-Fehler in GetStreicher (Mitglieder): " . $conn->error);
        return array();
    }
    $stmt->bind_param("s", $kategorie);
    $stmt->execute();
    $mitglieder = $stmt->get_result();
    $stmt->close();
    if ($mitglieder->num_rows > 0) {
        while ($mitglied = $mitglieder->fetch_assoc()) {
            $mitgliedID = $mitglied['id'];
            $streicherArray = array();

            // Für jeden AKTIVEN Wettkampf mit Streicher=1 das Resultat holen (oder 0 wenn nicht vorhanden)
            foreach ($wettkaempfe as $wettkampf) {
                // Wert holen (Endstich/Kanti aus eigenen Tabellen) und normalisieren
                $streicherArray[] = array(
                    'WettbewerbID' => $wettkampf['ID'],
                    'NormalizedPoints' => absendenStreicherWert($conn, $selectedYear, $mitgliedID, $wettkampf)
                );
            }

            // Zähle fehlende Resultate (0-Werte)
            $fehlendeResultate = array_filter($streicherArray, function($r) {
                return $r['NormalizedPoints'] == 0 || $r['NormalizedPoints'] === 0.0;
            });
            $excludeCount = getExcludeCount($conn, $selectedYear);
            $gestricheneResultate = array();

            // Wenn mehr fehlende als Streicher erlaubt: Nimm die ersten nach Reihenfolge (sind bereits sortiert)
            if (count($fehlendeResultate) > $excludeCount) {

                // Streiche die ersten $excludeCount fehlenden Wettbewerbe
                $count = 0;
                foreach ($streicherArray as $result) {
                    if ($result['NormalizedPoints'] == 0 || $result['NormalizedPoints'] === 0.0) {
                        $gestricheneResultate[] = $result;
                        $count++;
                        if ($count >= $excludeCount) break;
                    }
                }
            } else {

                // Normale Logik: Die $excludeCount niedrigsten Resultate streichen
                usort($streicherArray, function ($a, $b) {
                    return $a['NormalizedPoints'] <=> $b['NormalizedPoints'];
                });
                $gestricheneResultate = array_slice($streicherArray, 0, $excludeCount);
            }
            foreach ($gestricheneResultate as $gestrichen) {
                $MitgliedStreicher[$mitgliedID][] = $gestrichen['WettbewerbID'];
            }

            // Debug-Ausgabe für Streicher
            $fehlendCount = count($fehlendeResultate);
            $logikType = ($fehlendCount > $excludeCount) ? "REIHENFOLGE (>$excludeCount fehlend)" : "NIEDRIGSTE WERTE";
            logDebug("GetStreicher - Mitglied $mitgliedID: " . count($gestricheneResultate) . " Streicher von " . count($streicherArray) . " Wettkämpfen ($fehlendCount fehlend, Logik: $logikType)");
        }
    }
    return $MitgliedStreicher;
}

function checkIfSSMExists($conn) {
    global $selectedYear;
    $sqlCheckSSM = "SELECT ID FROM JMDefinition WHERE Bezeichnung = 'SSM 2024' AND year = ?";
    $stmt = $conn->prepare($sqlCheckSSM);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $resultSSM = $stmt->get_result();
    $exists = ($resultSSM && $resultSSM->num_rows > 0);
    $stmt->close();
    return $exists;
}

function jmDebugMemberTotals($conn, $selectedYear, $mitgliedId, $defsLeft, $defsRight) {

    // Hilfsfunktionen wie in GetJMA
    $calcEndstich = function(int $mid) use ($conn, $selectedYear) {
        $sql = "SELECT
                    COALESCE(Schuss1,0)+COALESCE(Schuss2,0)+COALESCE(Schuss3,0)+COALESCE(Schuss4,0)+COALESCE(Schuss5,0)
                  + COALESCE(Schuss6,0)+COALESCE(Schuss7,0)+COALESCE(Schuss8,0)+COALESCE(Schuss9,0)+COALESCE(Schuss10,0) AS P
                FROM endstich WHERE MitgliedID=? AND Jahr=?";
        if (!$st = $conn->prepare($sql)) return null;
        $st->bind_param("ii", $mid, $selectedYear);
        $st->execute(); $res = $st->get_result(); $row = $res->fetch_assoc(); $st->close();
        return $row ? (float)$row['P'] : null;
    };
    $calcBestKanti = function(int $mid) use ($conn, $selectedYear) {
        $sql = "SELECT GREATEST(
                    COALESCE(Passe1,0),COALESCE(Passe2,0),COALESCE(Passe3,0),COALESCE(Passe4,0),COALESCE(Passe5,0)
                ) AS P FROM kantiresultate WHERE MitgliedID=? AND Jahr=?";
        if (!$st = $conn->prepare($sql)) return null;
        $st->bind_param("ii", $mid, $selectedYear);
        $st->execute(); $res = $st->get_result(); $row = $res->fetch_assoc(); $st->close();
        return $row ? (float)$row['P'] : null;
    };
    $applyScale = function($raw, array $defRow) {
        if ($raw === null) return null;
        $p = (float)$raw + (float)$defRow['Zuschlag'];
        $max = (int)$defRow['Maxpunkte'];
        if ($max > 0 && $p > $max) $p = $max;
        return $p;
    };
    $toNumber = function($v) {
        if ($v === null) return 0.0;
        if (is_float($v) || is_int($v)) return (float)$v;
        $s = (string)$v;
        $s = preg_replace('/[\x{200C}\x{200B}\x{A0}\s]+/u', '', $s);
        if (preg_match('/-?\d+(?:[.,]\d+)?/', $s, $m)) {
            return (float)str_replace(',', '.', $m[0]);
        }
        return 0.0;
    };
    $calcTotal = function(array $cells) use ($toNumber) {
        $sum = 0.0;
        foreach ($cells as $c) {
            if (!empty($c['isStreicher'])) continue;
            $sum += array_key_exists('num', $c) ? (float)$c['num'] : $toNumber($c['wert']);
        }
        return $sum;
    };

    // Definitions-Mapping
    $defById = [];
    foreach (array_merge($defsLeft, $defsRight) as $d) { $defById[(int)$d['ID']] = $d; }

    // Deine normale Logik holen (liefert 'wert' + 'isStreicher')
    $left  = getResultateForMember($mitgliedId, $defsLeft,  /*Streicher*/0, $conn);
    $right = getResultateForMember($mitgliedId, $defsRight, /*Streicher*/0, $conn);

    // Endstich/Kanti finden (nach Bezeichnung)
    $endDef = null; $kantiDef = null;
    foreach ($defById as $d) {
        if ($d['Bezeichnung'] === 'Endstich')             $endDef = $d;
        if ($d['Bezeichnung'] === 'Bester Kantonalstich') $kantiDef = $d;
    }

    // Overrides anwenden (auch 'num' setzen)
    if ($endDef) {
        $raw  = $calcEndstich($mitgliedId);
        $scal = $applyScale($raw, $endDef);
        foreach ([$left, $right] as &$side) {
            foreach ($side as &$cell) {
                if (!isset($cell['defId'])) continue;
                if ((int)$cell['defId'] === (int)$endDef['ID']) {
                    $cell['wert'] = function_exists('fmtNoTrailingZero') ? (string)fmtNoTrailingZero($scal) : (string)$scal;
                    $cell['num']  = $scal;
                    $cell['isStreicher'] = false;
                }
            }
        }
        unset($side, $cell);
        logDebug("DBG Endstich raw=$raw scaled=$scal");
    }
    if ($kantiDef) {
        $raw  = $calcBestKanti($mitgliedId);
        $scal = $applyScale($raw, $kantiDef);
        foreach ([$left, $right] as &$side) {
            foreach ($side as &$cell) {
                if (!isset($cell['defId'])) continue;
                if ((int)$cell['defId'] === (int)$kantiDef['ID']) {
                    $cell['wert'] = function_exists('fmtNoTrailingZero') ? (string)fmtNoTrailingZero($scal) : (string)$scal;
                    $cell['num']  = $scal;
                    $cell['isStreicher'] = false;
                }
            }
        }
        unset($side, $cell);
        logDebug("DBG Kanti raw=$raw scaled=$scal");
    }

    // Ausgeben
    $dumpSide = function($label, $defs, $cells) {
        logDebug("---- $label ----");
        foreach ($cells as $idx => $c) {
            $defId = $c['defId'] ?? null;
            $bez   = '';
            foreach ($defs as $d) { if ((int)$d['ID'] === (int)$defId) { $bez = $d['Bezeichnung']; break; } }
            $w = $c['wert'] ?? '';
            $n = array_key_exists('num',$c) ? $c['num'] : null;
            $s = !empty($c['isStreicher']) ? ' (Streicher)' : '';
            logDebug(sprintf("#%02d %-25s ID=%s  wert=%s  num=%s%s",
                $idx+1, $bez, $defId, var_export($w,true), var_export($n,true), $s));
        }
    };
    $dumpSide('LEFT',  $defsLeft,  $left);
    $dumpSide('RIGHT', $defsRight, $right);
    $tA = $calcTotal($left);
    $tB = $calcTotal($right);
    logDebug("TOTAL_A=$tA TOTAL_B=$tB TOTAL=".($tA+$tB));
}

// Ende der Datei
logDebug("functions.inc.php erfolgreich geladen");
