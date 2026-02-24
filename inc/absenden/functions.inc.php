<?php
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

// Hilfsfunktion für Fettschrift
function formatBold($text) {
    return '<w:r><w:rPr><w:b/></w:rPr><w:t>' . htmlspecialchars($text) . '</w:t></w:r>';
}

// Debug-Logging
function logDebug($message) {
    error_log("[JM Debug] " . date('Y-m-d H:i:s') . " - " . $message);
}

// ========================================
// HAUPTFUNKTIONEN

// ========================================
function getEndstich($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getEndstich für Jahr: " . $selectedYear);
    $sql = "
    SELECT
        u.ID,
        u.Name,
        u.Vorname,
        u.Geburtsdatum,
        u.Schuss1, u.Schuss2, u.Schuss3, u.Schuss4, u.Schuss5,
        u.Schuss6, u.Schuss7, u.Schuss8, u.Schuss9, u.Schuss10,
        u.Tiefschuss,
        u.Kranz_Endstich,
        u.Endstich_Summe,
        u.Anzahl_10
    FROM (

        /* Mitglieder */
        SELECT
            m.ID,
            CONVERT(m.Name    USING utf8mb4) COLLATE utf8mb4_unicode_ci  AS Name,
            CONVERT(m.Vorname USING utf8mb4) COLLATE utf8mb4_unicode_ci  AS Vorname,
            m.Geburtsdatum,
            e.Schuss1, e.Schuss2, e.Schuss3, e.Schuss4, e.Schuss5,
            e.Schuss6, e.Schuss7, e.Schuss8, e.Schuss9, e.Schuss10,
            e.Tiefschuss,
            w.Kranz_Endstich,
            COALESCE(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5
                   + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10, 0) AS Endstich_Summe,
            ((e.Schuss1 = 10) + (e.Schuss2 = 10) + (e.Schuss3 = 10) + (e.Schuss4 = 10) + (e.Schuss5 = 10)
           + (e.Schuss6 = 10) + (e.Schuss7 = 10) + (e.Schuss8 = 10) + (e.Schuss9 = 10) + (e.Schuss10 = 10)) AS Anzahl_10
        FROM mitglieder m
        LEFT JOIN endstich e
          ON m.ID = e.MitgliedID AND e.Jahr = ?
        LEFT JOIN Waffen w
          ON w.ID = m.WaffenID
        WHERE COALESCE(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5
                     + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10, 0) != 0
        UNION ALL

        /* JS-Gäste (Stammdaten aus endstich_gaeste, Resultate aus endstich_jung) */
        SELECT
            g.id AS ID,
            CONVERT(COALESCE(g.Nachname, g.Name) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
            CONVERT(COALESCE(g.Vorname, '')      USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Vorname,
            COALESCE(g.Geburtsdatum, DATE('2100-01-01')) AS Geburtsdatum,
            ej.Schuss1, ej.Schuss2, ej.Schuss3, ej.Schuss4, ej.Schuss5,
            ej.Schuss6, ej.Schuss7, ej.Schuss8, ej.Schuss9, ej.Schuss10,
            ej.Tiefschuss,
            NULL AS Kranz_Endstich,
            COALESCE(ej.Schuss1 + ej.Schuss2 + ej.Schuss3 + ej.Schuss4 + ej.Schuss5
                   + ej.Schuss6 + ej.Schuss7 + ej.Schuss8 + ej.Schuss9 + ej.Schuss10, 0) AS Endstich_Summe,
            ((ej.Schuss1 = 10) + (ej.Schuss2 = 10) + (ej.Schuss3 = 10) + (ej.Schuss4 = 10) + (ej.Schuss5 = 10)
           + (ej.Schuss6 = 10) + (ej.Schuss7 = 10) + (ej.Schuss8 = 10) + (ej.Schuss9 = 10) + (ej.Schuss10 = 10)) AS Anzahl_10
        FROM endstich_gaeste g
        INNER JOIN endstich_jung ej
          ON g.id = ej.JungschuetzeID AND ej.Jahr = ?
        WHERE (g.Jahr = ? OR g.Jahr IS NULL)
          AND COALESCE(ej.Schuss1 + ej.Schuss2 + ej.Schuss3 + ej.Schuss4 + ej.Schuss5
                     + ej.Schuss6 + ej.Schuss7 + ej.Schuss8 + ej.Schuss9 + ej.Schuss10, 0) != 0
        UNION ALL

        /* Normale Gäste (Partner) â€“ Endstich aus endresultate_partner */
        SELECT
            ep.ID AS ID,
            CONVERT(ep.PartnerName USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
            CAST('' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS Vorname,
            DATE('2100-01-01') AS Geburtsdatum, -- kein Datum -> nach hinten
            ep.EndstichSchuss1  AS Schuss1,
            ep.EndstichSchuss2  AS Schuss2,
            ep.EndstichSchuss3  AS Schuss3,
            ep.EndstichSchuss4  AS Schuss4,
            ep.EndstichSchuss5  AS Schuss5,
            ep.EndstichSchuss6  AS Schuss6,
            ep.EndstichSchuss7  AS Schuss7,
            ep.EndstichSchuss8  AS Schuss8,
            ep.EndstichSchuss9  AS Schuss9,
            ep.EndstichSchuss10 AS Schuss10,
            LEAST(
                ep.EndstichSchuss1, ep.EndstichSchuss2, ep.EndstichSchuss3, ep.EndstichSchuss4, ep.EndstichSchuss5,
                ep.EndstichSchuss6, ep.EndstichSchuss7, ep.EndstichSchuss8, ep.EndstichSchuss9, ep.EndstichSchuss10
            ) AS Tiefschuss,
            NULL AS Kranz_Endstich,
            COALESCE(
                ep.EndstichSchuss1 + ep.EndstichSchuss2 + ep.EndstichSchuss3 + ep.EndstichSchuss4 + ep.EndstichSchuss5 +
                ep.EndstichSchuss6 + ep.EndstichSchuss7 + ep.EndstichSchuss8 + ep.EndstichSchuss9 + ep.EndstichSchuss10
            , 0) AS Endstich_Summe,
            ((ep.EndstichSchuss1 = 10) + (ep.EndstichSchuss2 = 10) + (ep.EndstichSchuss3 = 10) + (ep.EndstichSchuss4 = 10) + (ep.EndstichSchuss5 = 10) +
             (ep.EndstichSchuss6 = 10) + (ep.EndstichSchuss7 = 10) + (ep.EndstichSchuss8 = 10) + (ep.EndstichSchuss9 = 10) + (ep.EndstichSchuss10 = 10)) AS Anzahl_10
        FROM endresultate_partner ep
        WHERE ep.Jahr = ?
          AND COALESCE(
                ep.EndstichSchuss1 + ep.EndstichSchuss2 + ep.EndstichSchuss3 + ep.EndstichSchuss4 + ep.EndstichSchuss5 +
                ep.EndstichSchuss6 + ep.EndstichSchuss7 + ep.EndstichSchuss8 + ep.EndstichSchuss9 + ep.EndstichSchuss10
              , 0) != 0
    ) u
    ORDER BY
        u.Endstich_Summe DESC,
        u.Tiefschuss DESC,
        u.Anzahl_10 DESC,
        u.Geburtsdatum ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logDebug("SQL-Fehler in getEndstich prepare: " . $conn->error);
        $templateProcessor->cloneRow('ESRang', 0);
        return;
    }

    // 3x Jahr: Mitglieder.endstich, Gäste.endstich_jung, Gäste.endstich_gaeste (optional via OR IS NULL abgefangen)
$stmt->bind_param("iiii", $selectedYear, $selectedYear, $selectedYear, $selectedYear);
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
    $sql = "
    SELECT
        u.ID,
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

        /* Mitglieder */
        SELECT
            m.ID,
            CONVERT(m.Name    USING utf8mb4) COLLATE utf8mb4_unicode_ci  AS Name,
            CONVERT(m.Vorname USING utf8mb4) COLLATE utf8mb4_unicode_ci  AS Vorname,
            m.Geburtsdatum,
            s.P1Schuss1, s.P1Schuss2, s.P1Schuss3, s.P1Schuss4, s.P1Schuss5, s.P1Schuss6,
            s.P2Schuss1, s.P2Schuss2, s.P2Schuss3, s.P2Schuss4, s.P2Schuss5, s.P2Schuss6,
            COALESCE(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6, 0) AS Schwini1_Summe,
            COALESCE(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6, 0) AS Schwini2_Summe
        FROM mitglieder m
        LEFT JOIN schwini s ON m.ID = s.MitgliedID AND s.Jahr = ?
        WHERE COALESCE(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6, 0) != 0
           OR COALESCE(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6, 0) != 0
        UNION ALL

        /* Jungschützen/Gäste aus endstich_gaeste + schwini_jung */
        SELECT
            g.id AS ID,
            CONVERT(g.Name    USING utf8mb4) COLLATE utf8mb4_unicode_ci  AS Name,
            CONVERT(g.Vorname USING utf8mb4) COLLATE utf8mb4_unicode_ci  AS Vorname,
            g.Geburtsdatum,
            sj.P1Schuss1, sj.P1Schuss2, sj.P1Schuss3, sj.P1Schuss4, sj.P1Schuss5, sj.P1Schuss6,
            sj.P2Schuss1, sj.P2Schuss2, sj.P2Schuss3, sj.P2Schuss4, sj.P2Schuss5, sj.P2Schuss6,
            COALESCE(sj.P1Schuss1 + sj.P1Schuss2 + sj.P1Schuss3 + sj.P1Schuss4 + sj.P1Schuss5 + sj.P1Schuss6, 0) AS Schwini1_Summe,
            COALESCE(sj.P2Schuss1 + sj.P2Schuss2 + sj.P2Schuss3 + sj.P2Schuss4 + sj.P2Schuss5 + sj.P2Schuss6, 0) AS Schwini2_Summe
        FROM endstich_gaeste g
        INNER JOIN schwini_jung sj ON g.id = sj.JungschuetzeID AND sj.Jahr = ?
        WHERE COALESCE(sj.P1Schuss1 + sj.P1Schuss2 + sj.P1Schuss3 + sj.P1Schuss4 + sj.P1Schuss5 + sj.P1Schuss6, 0) != 0
           OR COALESCE(sj.P2Schuss1 + sj.P2Schuss2 + sj.P2Schuss3 + sj.P2Schuss4 + sj.P2Schuss5 + sj.P2Schuss6, 0) != 0
        UNION ALL

        /* Partner (12 Schüsse = 2 Passen Ã  6) */
        SELECT
            ep.ID AS ID,
            CONVERT(ep.PartnerName USING utf8mb4) COLLATE utf8mb4_unicode_ci AS Name,
            CAST('' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS Vorname,
            '2100-01-01' AS Geburtsdatum, /* kein Geburtsdatum -> hinten einsortieren */
            ep.PartnerSchwiniSchuss1  AS P1Schuss1,
            ep.PartnerSchwiniSchuss2  AS P1Schuss2,
            ep.PartnerSchwiniSchuss3  AS P1Schuss3,
            ep.PartnerSchwiniSchuss4  AS P1Schuss4,
            ep.PartnerSchwiniSchuss5  AS P1Schuss5,
            ep.PartnerSchwiniSchuss6  AS P1Schuss6,
            ep.PartnerSchwiniSchuss7  AS P2Schuss1,
            ep.PartnerSchwiniSchuss8  AS P2Schuss2,
            ep.PartnerSchwiniSchuss9  AS P2Schuss3,
            ep.PartnerSchwiniSchuss10 AS P2Schuss4,
            ep.PartnerSchwiniSchuss11 AS P2Schuss5,
            ep.PartnerSchwiniSchuss12 AS P2Schuss6,
            COALESCE(ep.PartnerSchwiniSchuss1 + ep.PartnerSchwiniSchuss2 + ep.PartnerSchwiniSchuss3 + ep.PartnerSchwiniSchuss4 + ep.PartnerSchwiniSchuss5 + ep.PartnerSchwiniSchuss6, 0) AS Schwini1_Summe,
            COALESCE(ep.PartnerSchwiniSchuss7 + ep.PartnerSchwiniSchuss8 + ep.PartnerSchwiniSchuss9 + ep.PartnerSchwiniSchuss10 + ep.PartnerSchwiniSchuss11 + ep.PartnerSchwiniSchuss12, 0) AS Schwini2_Summe
        FROM endresultate_partner ep
        WHERE ep.Jahr = ?
          AND (
                COALESCE(ep.PartnerSchwiniSchuss1 + ep.PartnerSchwiniSchuss2 + ep.PartnerSchwiniSchuss3 + ep.PartnerSchwiniSchuss4 + ep.PartnerSchwiniSchuss5 + ep.PartnerSchwiniSchuss6, 0) != 0
             OR COALESCE(ep.PartnerSchwiniSchuss7 + ep.PartnerSchwiniSchuss8 + ep.PartnerSchwiniSchuss9 + ep.PartnerSchwiniSchuss10 + ep.PartnerSchwiniSchuss11 + ep.PartnerSchwiniSchuss12, 0) != 0
          )
    ) u
    ORDER BY
        Hoechste_Summe DESC,
        Tiefste_Summe ASC,
        u.Geburtsdatum ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logDebug("SQL-Fehler in getSchwini prepare: " . $conn->error);
        $templateProcessor->cloneRow('SRang', 0);
        return;
    }

    // drei Jahres-Parameter: Mitglieder, Gäste/Jungschützen, Partner
    $stmt->bind_param("iii", $selectedYear, $selectedYear, $selectedYear);
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
    $sql = "
        SELECT
            m.ID,
            m.Name,
            m.Vorname,
            m.Geburtsdatum,
            z.ZSchuss1,
            z.ZSchuss2,
            z.ZSchuss3,
            z.ZSchuss4,
            z.ZSchuss5,
            z.ZSchuss6,
            GREATEST(
                COALESCE(z.ZSchuss1, 0),
                COALESCE(z.ZSchuss2, 0),
                COALESCE(z.ZSchuss3, 0),
                COALESCE(z.ZSchuss4, 0),
                COALESCE(z.ZSchuss5, 0),
                COALESCE(z.ZSchuss6, 0)
            ) AS TS
        FROM
            mitglieder m
        LEFT JOIN zabig z ON m.ID = z.MitgliedID AND z.Jahr = ?
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        WHERE z.ZSchuss1 != 0
        GROUP BY
            m.ID, m.Vorname, m.Name, m.Geburtsdatum
        ORDER BY
            m.Geburtsdatum ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logDebug("SQL-Fehler in getZabig prepare: " . $conn->error);
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
    $sql = "
        SELECT
            m.ID,
            m.Name,
            m.Vorname,
            m.Geburtsdatum,
            g.GSchuss1,
            g.GSchuss2,
            g.GSchuss3,
            GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3) AS MaxGlueck,
            LEAST(
                GREATEST(g.GSchuss1, g.GSchuss2),
                GREATEST(g.GSchuss1, g.GSchuss3),
                GREATEST(g.GSchuss2, g.GSchuss3)
            ) AS Zwei,
            LEAST(g.GSchuss1, g.GSchuss2, g.GSchuss3) AS Drei
        FROM
            mitglieder m
        LEFT JOIN glueck g ON m.ID = g.MitgliedID AND g.Jahr = ?
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        WHERE g.GSchuss1 != 0
        GROUP BY
            m.ID, m.Vorname, m.Name, m.Geburtsdatum
        ORDER BY
            MaxGlueck DESC,
            Zwei DESC,
            Drei DESC,
            m.Geburtsdatum ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logDebug("SQL-Fehler in getGlueck prepare: " . $conn->error);
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
    $sql = "
        SELECT
            m.ID,
            m.Name,
            m.Vorname,
            m.Geburtsdatum,
            k.KSchuss1,
            k.KSchuss2,
            k.KSchuss3,
            k.KSchuss4,
            k.KSchuss5,
            w.Kranz_Kunst,
            COALESCE(
                k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5, 
                0
            ) AS Kunst_Summe,
            GREATEST(
                COALESCE(k.KSchuss1, 0),
                COALESCE(k.KSchuss2, 0),
                COALESCE(k.KSchuss3, 0),
                COALESCE(k.KSchuss4, 0),
                COALESCE(k.KSchuss5, 0)
            ) AS TS
        FROM
            mitglieder m
        LEFT JOIN kunst k ON m.ID = k.MitgliedID AND k.Jahr = ?
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        WHERE k.KSchuss1 IS NOT NULL AND k.KSchuss1 != 0
        GROUP BY
            m.ID, m.Name, m.Vorname, m.Geburtsdatum, k.KSchuss1, k.KSchuss2, k.KSchuss3, k.KSchuss4, k.KSchuss5, w.Kranz_Kunst
        ORDER BY
            Kunst_Summe DESC,
            TS DESC,
            m.Geburtsdatum ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logDebug("SQL-Fehler in getKunst prepare: " . $conn->error);
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
        $templateProcessor->cloneRow('KRang', 0);
        return;
    }
    $templateProcessor->cloneRow('KRang', $rowCount);
    $currentRow = 1;
    foreach ($data as $row) {
        $keys = array_keys($row);
        $schussKeys = preg_grep('/^KSchuss\d+$/', $keys);
        $scount = count($schussKeys);
        if ($currentRow <= 3) {
            $templateProcessor->setValue("KRang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue("KName#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
            for ($i = 1; $i <= $scount; $i++) {
                $schuss = "KSchuss" . $i;
                $value = isset($row[$schuss]) ? $row[$schuss] : '';
                $templateProcessor->setValue("KS$i#{$currentRow}", formatBold($value));
            }
            $templateProcessor->setValue("KT#{$currentRow}", formatBold($row['Kunst_Summe']));
            if ($currentRow == 1) {
                $templateProcessor->setValue("KKK#{$currentRow}", formatBold('KK + WP'));
            } else {
                $templateProcessor->setValue("KKK#{$currentRow}", formatBold('KK'));
            }
        } else {
            $templateProcessor->setValue("KRang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue("KName#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
            for ($i = 1; $i <= $scount; $i++) {
                $schuss = "KSchuss" . $i;
                $value = isset($row[$schuss]) ? $row[$schuss] : '';
                $templateProcessor->setValue("KS$i#{$currentRow}", $value);
            }
            $templateProcessor->setValue("KT#{$currentRow}", $row['Kunst_Summe']);
            if ($row['Kunst_Summe'] >= $row['Kranz_Kunst']) {
                $templateProcessor->setValue("KKK#{$currentRow}", "KK");
            } else {
                $templateProcessor->setValue("KKK#{$currentRow}", "");
            }
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
        logDebug("SQL-Fehler in getEndschGesamt prepare: " . $conn->error);
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

    // Sortiere nach GesamtTotal DESC, EndstichTotal DESC, Geburtsdatum ASC
    usort($data, function($a, $b) {
        if ($a['GesamtTotal'] != $b['GesamtTotal']) {
            return $b['GesamtTotal'] - $a['GesamtTotal'];
        }
        if ($a['EndstichTotal'] != $b['EndstichTotal']) {
            return $b['EndstichTotal'] - $a['EndstichTotal'];
        }
        return strcmp($a['Geburtsdatum'], $b['Geburtsdatum']);
    });
    $rowCount = count($data);
    $kategorie = "E" . trim(strrchr($kat, ' '));
    if ($rowCount == 0) {
        logDebug("Keine Endsch-Gesamt-Daten gefunden für Kategorie: " . $kat);
        $templateProcessor->cloneRow($kategorie . 'Rang', 0);
        return;
    }
    $templateProcessor->cloneRow($kategorie . 'Rang', $rowCount);
    $currentRow = 1;
    foreach ($data as $row) {
        if ($currentRow <= 3) {
            $templateProcessor->setValue($kategorie . "Rang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue($kategorie . "Name#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
            $templateProcessor->setValue($kategorie . "E#{$currentRow}", formatBold($row['EndstichTotal']));
            $templateProcessor->setValue($kategorie . "Z#{$currentRow}", formatBold($row['ZabigTotal']));
            $templateProcessor->setValue($kategorie . "S#{$currentRow}", formatBold($row['MaxSchwini']));
            $templateProcessor->setValue($kategorie . "G#{$currentRow}", formatBold($row['GlueckTotal']));
            $templateProcessor->setValue($kategorie . "K#{$currentRow}", formatBold($row['KunstTotal']));
            $templateProcessor->setValue($kategorie . "T#{$currentRow}", formatBold(fmt1($row['GesamtTotal'])));
            if ($currentRow == 1) {
                $templateProcessor->setValue($kategorie . "KK#{$currentRow}", formatBold('WP + KK'));
            } else {
                $templateProcessor->setValue($kategorie . "KK#{$currentRow}", formatBold('KK'));
            }
        } else {
            $templateProcessor->setValue($kategorie . "Rang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue($kategorie . "Name#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
            $templateProcessor->setValue($kategorie . "E#{$currentRow}", $row['EndstichTotal']);
            $templateProcessor->setValue($kategorie . "Z#{$currentRow}", $row['ZabigTotal']);
            $templateProcessor->setValue($kategorie . "S#{$currentRow}", $row['MaxSchwini']);
            $templateProcessor->setValue($kategorie . "G#{$currentRow}", $row['GlueckTotal']);
            $templateProcessor->setValue($kategorie . "K#{$currentRow}", $row['KunstTotal']);
            $templateProcessor->setValue($kategorie . "T#{$currentRow}", fmt1($row['GesamtTotal']));
            $templateProcessor->setValue($kategorie . "KK#{$currentRow}", '');
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
        LEFT JOIN endstich_jung e ON g.id = e.JungschuetzeID AND e.Jahr = ?
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

            // Methode 1: cloneBlock mit 0 (entfernt den Block)
            try {
                $templateProcessor->cloneBlock('JUNGSCHUETZEN_BLOCK', 0);
                logDebug("Jungschützen-Block mit cloneBlock entfernt");
                return;
            } catch (Exception $e) {
                logDebug("cloneBlock fehlgeschlagen: " . $e->getMessage());
            }

            // Methode 2: replaceBlock mit leerem String
            try {
                $templateProcessor->replaceBlock('JUNGSCHUETZEN_BLOCK', '');
                logDebug("Jungschützen-Block mit replaceBlock entfernt");
                return;
            } catch (Exception $e) {
                logDebug("replaceBlock fehlgeschlagen: " . $e->getMessage());
            }

            // Methode 3: Alle Platzhalter leer setzen
            // Titel und Überschriften entfernen
            $templateProcessor->setValue('JUNGSCHUETZEN_TITEL', '');
            $templateProcessor->setValue('JUNGSCHUETZEN_SUBTITLE', '');

            // Tabelle ohne Zeilen (= unsichtbar machen)
            $templateProcessor->cloneRow('JRang', 0);

            // Weitere mögliche Platzhalter
            $placeholders = ['JName', 'JE', 'JZ', 'JS', 'JT'];
            foreach ($placeholders as $ph) {
                $templateProcessor->setValue($ph, '');
            }
            logDebug("Jungschützen-Platzhalter geleert");
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
        LEFT JOIN endstich_jung e ON g.id = e.JungschuetzeID AND e.Jahr = ?
        LEFT JOIN schwini_jung s ON g.id = s.JungschuetzeID AND s.Jahr = ?
        LEFT JOIN zabig_jung z ON g.id = z.JungschuetzeID AND z.Jahr = ?
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
        logDebug("SQL-Fehler in getJungschuetzenResultate prepare: " . $conn->error);
        $templateProcessor->cloneRow('JRang', 0);
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
            return $b['GesamtTotal'] - $a['GesamtTotal'];
        }
        if ($a['EndstichTotal'] != $b['EndstichTotal']) {
            return $b['EndstichTotal'] - $a['EndstichTotal'];
        }
        return strcmp($a['geburtsdatum'], $b['geburtsdatum']);
    });
    $rowCount = count($data);
    if ($rowCount == 0) {
        logDebug("Keine Jungschützen-Daten mit Resultaten gefunden");
        $templateProcessor->cloneRow('JRang', 0);
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

    // Block-Marker entfernen nach dem Einfügen der Daten
    try {

        // Entferne den Start-Marker
        $templateProcessor->setValue('JUNGSCHUETZEN_BLOCK', '');

        // Entferne den End-Marker  
        $templateProcessor->setValue('/JUNGSCHUETZEN_BLOCK', '');
        logDebug("Block-Marker entfernt");
    } catch (Exception $e) {

        // Falls die Marker nicht als Variablen erkannt werden, ist das OK
        logDebug("Block-Marker konnten nicht entfernt werden (vermutlich schon weg): " . $e->getMessage());
    }
    logDebug("getJungschuetzenResultate erfolgreich abgeschlossen");
}

function getPartnerResultate($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getPartnerResultate für Jahr: " . $selectedYear);

    // Erst prüfen ob überhaupt Partner-Daten vorhanden sind
    $checkSql = "
        SELECT COUNT(*) as count
        FROM mitglieder m
        INNER JOIN endresultate_partner ep ON m.ID = ep.MitgliedID
        WHERE ep.Jahr = ?
          AND ((COALESCE(ep.EndstichSchuss1, 0) + COALESCE(ep.EndstichSchuss2, 0) + COALESCE(ep.EndstichSchuss3, 0) +
                COALESCE(ep.EndstichSchuss4, 0) + COALESCE(ep.EndstichSchuss5, 0) + COALESCE(ep.EndstichSchuss6, 0) +
                COALESCE(ep.EndstichSchuss7, 0) + COALESCE(ep.EndstichSchuss8, 0) + COALESCE(ep.EndstichSchuss9, 0) +
                COALESCE(ep.EndstichSchuss10, 0)) > 0
               OR GREATEST(
                   (COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) +
                    COALESCE(ep.PartnerSchwiniSchuss3, 0) + COALESCE(ep.PartnerSchwiniSchuss4, 0) +
                    COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0)),
                   (COALESCE(ep.PartnerSchwiniSchuss7, 0) + COALESCE(ep.PartnerSchwiniSchuss8, 0) +
                    COALESCE(ep.PartnerSchwiniSchuss9, 0) + COALESCE(ep.PartnerSchwiniSchuss10, 0) +
                    COALESCE(ep.PartnerSchwiniSchuss11, 0) + COALESCE(ep.PartnerSchwiniSchuss12, 0))
               ) > 0)
    ";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param("i", $selectedYear);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkRow = $checkResult->fetch_assoc();
        $hasData = $checkRow['count'] > 0;
        $checkStmt->close();
        if (!$hasData) {
            logDebug("Keine Partner-Daten vorhanden - entferne Sektion");

            // Methode 1: cloneBlock mit 0 (entfernt den Block)
            try {
                $templateProcessor->cloneBlock('PARTNER_BLOCK', 0);
                logDebug("Partner-Block mit cloneBlock entfernt");
                return;
            } catch (Exception $e) {
                logDebug("cloneBlock fehlgeschlagen: " . $e->getMessage());
            }

            // Methode 2: replaceBlock mit leerem String
            try {
                $templateProcessor->replaceBlock('PARTNER_BLOCK', '');
                logDebug("Partner-Block mit replaceBlock entfernt");
                return;
            } catch (Exception $e) {
                logDebug("replaceBlock fehlgeschlagen: " . $e->getMessage());
            }

            // Methode 3: Alle Platzhalter leer setzen
            // Tabelle ohne Zeilen (= unsichtbar machen)
            $templateProcessor->cloneRow('PRang', 0);
            logDebug("Partner-Platzhalter geleert");
            return;
        }
    }

    // Ab hier der normale Code wenn Daten vorhanden sind
    $sql = "
        SELECT
            m.ID,
            m.Name,
            m.Vorname,
            ep.PartnerName,
            ep.EndstichSchuss1, ep.EndstichSchuss2, ep.EndstichSchuss3, ep.EndstichSchuss4, ep.EndstichSchuss5,
            ep.EndstichSchuss6, ep.EndstichSchuss7, ep.EndstichSchuss8, ep.EndstichSchuss9, ep.EndstichSchuss10,
            ep.PartnerSchwiniSchuss1, ep.PartnerSchwiniSchuss2, ep.PartnerSchwiniSchuss3,
            ep.PartnerSchwiniSchuss4, ep.PartnerSchwiniSchuss5, ep.PartnerSchwiniSchuss6,
            ep.PartnerSchwiniSchuss7, ep.PartnerSchwiniSchuss8, ep.PartnerSchwiniSchuss9,
            ep.PartnerSchwiniSchuss10, ep.PartnerSchwiniSchuss11, ep.PartnerSchwiniSchuss12,
            (COALESCE(ep.EndstichSchuss1, 0) + COALESCE(ep.EndstichSchuss2, 0) + COALESCE(ep.EndstichSchuss3, 0) +
             COALESCE(ep.EndstichSchuss4, 0) + COALESCE(ep.EndstichSchuss5, 0) + COALESCE(ep.EndstichSchuss6, 0) +
             COALESCE(ep.EndstichSchuss7, 0) + COALESCE(ep.EndstichSchuss8, 0) + COALESCE(ep.EndstichSchuss9, 0) +
             COALESCE(ep.EndstichSchuss10, 0)) AS Endstich_Summe,
            GREATEST(
                (COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) +
                 COALESCE(ep.PartnerSchwiniSchuss3, 0) + COALESCE(ep.PartnerSchwiniSchuss4, 0) +
                 COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0)),
                (COALESCE(ep.PartnerSchwiniSchuss7, 0) + COALESCE(ep.PartnerSchwiniSchuss8, 0) +
                 COALESCE(ep.PartnerSchwiniSchuss9, 0) + COALESCE(ep.PartnerSchwiniSchuss10, 0) +
                 COALESCE(ep.PartnerSchwiniSchuss11, 0) + COALESCE(ep.PartnerSchwiniSchuss12, 0))
            ) AS PartnerSchwini_Summe,
            ((COALESCE(ep.EndstichSchuss1, 0) + COALESCE(ep.EndstichSchuss2, 0) + COALESCE(ep.EndstichSchuss3, 0) +
              COALESCE(ep.EndstichSchuss4, 0) + COALESCE(ep.EndstichSchuss5, 0) + COALESCE(ep.EndstichSchuss6, 0) +
              COALESCE(ep.EndstichSchuss7, 0) + COALESCE(ep.EndstichSchuss8, 0) + COALESCE(ep.EndstichSchuss9, 0) +
              COALESCE(ep.EndstichSchuss10, 0)) +
             GREATEST(
                 (COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) +
                  COALESCE(ep.PartnerSchwiniSchuss3, 0) + COALESCE(ep.PartnerSchwiniSchuss4, 0) +
                  COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0)),
                 (COALESCE(ep.PartnerSchwiniSchuss7, 0) + COALESCE(ep.PartnerSchwiniSchuss8, 0) +
                  COALESCE(ep.PartnerSchwiniSchuss9, 0) + COALESCE(ep.PartnerSchwiniSchuss10, 0) +
                  COALESCE(ep.PartnerSchwiniSchuss11, 0) + COALESCE(ep.PartnerSchwiniSchuss12, 0))
             )) AS Total_Summe
        FROM mitglieder m
        INNER JOIN endresultate_partner ep ON m.ID = ep.MitgliedID
        WHERE ep.Jahr = ?
          AND ((COALESCE(ep.EndstichSchuss1, 0) + COALESCE(ep.EndstichSchuss2, 0) + COALESCE(ep.EndstichSchuss3, 0) +
                COALESCE(ep.EndstichSchuss4, 0) + COALESCE(ep.EndstichSchuss5, 0) + COALESCE(ep.EndstichSchuss6, 0) +
                COALESCE(ep.EndstichSchuss7, 0) + COALESCE(ep.EndstichSchuss8, 0) + COALESCE(ep.EndstichSchuss9, 0) +
                COALESCE(ep.EndstichSchuss10, 0)) > 0
               OR GREATEST(
                   (COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) +
                    COALESCE(ep.PartnerSchwiniSchuss3, 0) + COALESCE(ep.PartnerSchwiniSchuss4, 0) +
                    COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0)),
                   (COALESCE(ep.PartnerSchwiniSchuss7, 0) + COALESCE(ep.PartnerSchwiniSchuss8, 0) +
                    COALESCE(ep.PartnerSchwiniSchuss9, 0) + COALESCE(ep.PartnerSchwiniSchuss10, 0) +
                    COALESCE(ep.PartnerSchwiniSchuss11, 0) + COALESCE(ep.PartnerSchwiniSchuss12, 0))
               ) > 0)
        ORDER BY Total_Summe DESC, Endstich_Summe DESC, m.Name ASC, m.Vorname ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logDebug("SQL-Fehler in getPartnerResultate prepare: " . $conn->error);
        $templateProcessor->cloneRow('PRang', 0);
        return;
    }
    $stmt->bind_param("i", $selectedYear);
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
        $templateProcessor->cloneRow('PRang', 0);
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

    // Block-Marker entfernen nach dem Einfügen der Daten
    try {

        // Entferne den Start-Marker
        $templateProcessor->setValue('PARTNER_BLOCK', '');

        // Entferne den End-Marker  
        $templateProcessor->setValue('/PARTNER_BLOCK', '');
        logDebug("Partner Block-Marker entfernt");
    } catch (Exception $e) {

        // Falls die Marker nicht als Variablen erkannt werden, ist das OK
        logDebug("Partner Block-Marker konnten nicht entfernt werden (vermutlich schon weg): " . $e->getMessage());
    }
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
        logDebug("SQL-Fehler in getHeim prepare: " . $conn->error);
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
        logDebug("SQL-Fehler in getKanti prepare (Kat. A): " . $conn->error);
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
        logDebug("SQL-Fehler in getKanti prepare (Kat. B): " . $conn->error);
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
        logDebug("SQL-Fehler in getSieger: " . $conn->error);
        return;
    }
    while ($rowdef = $result->fetch_assoc()) {
        $countSieger = getCountSieger($conn, $rowdef['Bezeichnung']);

        // Klone die Zeilen basierend auf der Anzahl der Sieger
        $row = $rowdef['PlatzhalterWord'] . "Y";
        $templateProcessor->cloneRow($row, $countSieger);
        $siegerdef = $rowdef['Bezeichnung'];
        $sqlSiegerDef = "SELECT * FROM sieger s
                         JOIN siegerdef sd ON sd.ID = s.siegerdef 
                         WHERE sd.Bezeichnung = ? 
                         ORDER BY s.year ASC";
        $stmt = $conn->prepare($sqlSiegerDef);
        if (!$stmt) {
            logDebug("SQL-Fehler in getSieger prepare: " . $conn->error);
            continue;
        }
        $stmt->bind_param("s", $siegerdef);
        $stmt->execute();
        $resultsieger = $stmt->get_result();
        $currentRow = 1;
        $siegers = array();

        // Sammle alle Sieger in ein Array
        while ($row = $resultsieger->fetch_assoc()) {
            $siegers[] = $row;
        }

        // Verarbeite die Sieger paarweise
        for ($i = 0; $i < count($siegers); $i += 2) {
            if ($currentRow <= $countSieger) {

                // Erster Sieger
                $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "Y#{$currentRow}", $siegers[$i]['year']);
                $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "N#{$currentRow}", $siegers[$i]['Name']);
                $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "P#{$currentRow}", $siegers[$i]['Wert']);

                // Zweiter Sieger (falls vorhanden)
                if (isset($siegers[$i + 1])) {
                    $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "Y2#{$currentRow}", $siegers[$i + 1]['year']);
                    $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "N2#{$currentRow}", $siegers[$i + 1]['Name']);
                    $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "P2#{$currentRow}", $siegers[$i + 1]['Wert']);
                } else {

                    // Leere Platzhalter für den 2. Sieger
                    $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "Y2#{$currentRow}", "");
                    $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "N2#{$currentRow}", "");
                    $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "P2#{$currentRow}", "");
                }
                $currentRow++;
            }
        }

        // Entferne ungenutzte Platzhalter
        for ($i = $currentRow; $i <= $countSieger; $i++) {
            $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "Y#{$i}", "");
            $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "N#{$i}", "");
            $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "P#{$i}", "");
            $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "Y2#{$i}", "");
            $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "N2#{$i}", "");
            $templateProcessor->setValue($rowdef['PlatzhalterWord'] . "P2#{$i}", "");
        }
        $stmt->close();
    }
    logDebug("getSieger erfolgreich abgeschlossen");
}

function getCup($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getCup für Jahr: " . $selectedYear);

    // Stil für die Zellen
    $cellStyle = [
        'valign' => VerticalJc::CENTER,
        'alignment' => Jc::CENTER,
        'borderSize' => 6,
        'borderColor' => '000000',
        'borderBottomSize' => 12,
        'borderBottomStyle' => 'double',
        'borderBottomColor' => '000000',
    ];

    // Funktion zum Erstellen der CUP-Tabelle
    function createCupTable($phpWord, $round, $cupPairsResult)
    {
        $tableStyle = [
            'alignment' => JcTable::CENTER,
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 150,
        ];
        $fontStyle = ['size' => 8];
        $loserFontStyle = [
            'size' => 8,
            'strikethrough' => true,
        ];
        $winnerFontStyle = ['size' => 8];
        $section = $phpWord->addSection();
        $cupTable = $section->addTable($tableStyle);

        // Tabelleninhalt
        $totalRows = count($cupPairsResult);
        foreach ($cupPairsResult as $index => $row) {
            if ($row['Round'] != $round) {
                continue;
            }

            // Verlierer bestimmen
            $winner1 = $row['Result1'] > $row['Result2'] || 
                      ($row['Result1'] == $row['Result2'] && $row['LowShot1'] > $row['LowShot2']);
            $winner2 = !$winner1;
            $loser = null;
            if (!empty($row['Participant3'])) {
                $loser = getLoserInThreePair($row);
            }

            // Zellstile definieren
            $cellStyleName = [
                'borderSize' => 6,
                'borderColor' => '000000',
                'valign' => VerticalJc::CENTER,
                'alignment' => Jc::CENTER,
                'borderRightSize' => 6,
                'borderRightStyle' => 'dashed',
                'borderRightColor' => '000000',
                'spaceAfter' => 0,
            ];
            $cellStyleRes = [
                'borderSize' => 6,
                'borderColor' => '000000',
                'valign' => VerticalJc::CENTER,
                'alignment' => Jc::CENTER,
                'borderleftSize' => 6,
                'borderleftStyle' => 'dashed',
                'borderleftColor' => '000000',
                'spaceAfter' => 0,
            ];

            // Prüfe ob letzte Zeile
            $isLastRow = ($index + 1 == $totalRows) || 
                        (isset($cupPairsResult[$index + 1]) && $cupPairsResult[$index + 1]['Round'] != $round);
            if ($isLastRow) {
                $cellStyleName['borderBottomSize'] = 6;
                $cellStyleName['borderBottomColor'] = '000000';
                $cellStyleRes['borderBottomSize'] = 6;
                $cellStyleRes['borderBottomColor'] = '000000';
            } else {
                $cellStyleName['borderBottomSize'] = 12;
                $cellStyleName['borderBottomStyle'] = 'double';
                $cellStyleName['borderBottomColor'] = '000000';
                $cellStyleRes['borderBottomSize'] = 12;
                $cellStyleRes['borderBottomStyle'] = 'double';
                $cellStyleRes['borderBottomColor'] = '000000';
            }

            // Zeilen hinzufügen
            if (!empty($row['Participant3'])) {

                // Dreiergruppe
                $cupTable->addRow();
                $cupTable->addCell(2000, $cellStyleName)->addText(
                    getParticipantName($row['Participant1']), 
                    $loser == 1 ? $loserFontStyle : $winnerFontStyle
                );
                $cupTable->addCell(500, $cellStyleRes)->addText(
                    $row['Result1'],  
                    $loser == 1 ? $loserFontStyle : $winnerFontStyle
                );
                $cupTable->addCell(2000, $cellStyleName)->addText(
                    getParticipantName($row['Participant2']), 
                    $loser == 2 ? $loserFontStyle : $winnerFontStyle
                );
                $cupTable->addCell(500, $cellStyleRes)->addText(
                    $row['Result2'],  
                    $loser == 2 ? $loserFontStyle : $winnerFontStyle
                );

                // Zweite Zeile für dritten Teilnehmer
                $cupTable->addRow();
                $cupTable->addCell(2000, $cellStyleName)->addText(
                    getParticipantName($row['Participant3']), 
                    $loser == 3 ? $loserFontStyle : $winnerFontStyle
                );
                $cupTable->addCell(500, $cellStyleRes)->addText(
                    $row['Result3'],  
                    $loser == 3 ? $loserFontStyle : $winnerFontStyle
                );
                $cupTable->addCell(2000, $cellStyleName)->addText('', ['size' => 8, 'color' => 'ffffff']);
                $cupTable->addCell(500, $cellStyleRes)->addText('', ['size' => 8, 'color' => 'ffffff']);
            } else {

                // Normale Paarung
                $cupTable->addRow();
                $cupTable->addCell(2000, $cellStyleName)->addText(
                    getParticipantName($row['Participant1']), 
                    $winner1 ? $fontStyle : $loserFontStyle
                );
                $cupTable->addCell(500, $cellStyleRes)->addText(
                    $row['Result1'], 
                    $winner1 ? $fontStyle : $loserFontStyle
                );
                $cupTable->addCell(2000, $cellStyleName)->addText(
                    getParticipantName($row['Participant2']), 
                    $winner2 ? $fontStyle : $loserFontStyle
                );
                $cupTable->addCell(500, $cellStyleRes)->addText(
                    $row['Result2'], 
                    $winner2 ? $fontStyle : $loserFontStyle
                );
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
    $tableStyle = [
        'alignment' => JcTable::CENTER,
        'cellMargin' => 20,
    ];

    // PhpWord-Instanz erstellen
    $phpWord = new PhpWord();
    $phpWord->addTableStyle('myTable', $tableStyle);

    // Abfrage für die Runden
    $pairsQuery = "SELECT * FROM cupPairs WHERE Year = ? ORDER BY Round, ID";
    $stmt = $conn->prepare($pairsQuery);
    if (!$stmt) {
        logDebug("SQL-Fehler in getCup prepare: " . $conn->error);
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
        logDebug("SQL-Fehler in getCup Final prepare: " . $conn->error);
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
        $finalTable->addRow();
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderLeftSize' => 6,
            'borderLeftColor' => '000000',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000'
        ];
        if ($i > 1) {
            $cellStyleSieger['borderTopStyle'] = 'dashed';
        }
        $finalTable->addCell(500, $cellStyleSieger)->addText("$i.", ['size' => 8]);
        $cellStyleSieger['borderLeftSize'] = 0;
        $finalTable->addCell(2000, $cellStyleSieger)->addText($participant, ['size' => 8]);
        $cellStyleSieger['borderRightSize'] = 6;
        $cellStyleSieger['borderRightColor'] = '000000';
        $finalTable->addCell(1000, $cellStyleSieger)->addText($row['Result'], ['size' => 8]);
        $i++;
    }
    $finalTable->addRow();
    $finalTable->addCell(null, ['borderTopColor' => '000000', 'borderTopSize' => 6, 'gridSpan' => 3])->addText(" ", ['size' => 8]);
    $stmt->close();
    $templateProcessor->setComplexBlock('CUPF', $finalTable);

    // Standcup
    $finalStandQuery = "SELECT * FROM cupStandFinal WHERE Year = ? ORDER BY Result DESC";
    $stmt = $conn->prepare($finalStandQuery);
    if (!$stmt) {
        logDebug("SQL-Fehler in getCup Stand prepare: " . $conn->error);
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
        $standCupTable->addRow();
        $cellStyleSieger = [
            'borderTopSize' => 6,
            'borderTopColor' => '000000',
            'borderLeftSize' => 6,
            'borderLeftColor' => '000000',
            'borderBottomStyle' => 'dashed',
            'borderBottomSize' => 6,
            'borderBottomColor' => '000000'
        ];
        if ($i > 1) {
            $cellStyleSieger['borderTopStyle'] = 'dashed';
        }
        $standCupTable->addCell(500, $cellStyleSieger)->addText("$i.", ['size' => 8]);
        $cellStyleSieger['borderLeftSize'] = 0;
        $standCupTable->addCell(2000, $cellStyleSieger)->addText($participant, ['size' => 8]);
        $cellStyleSieger['borderRightSize'] = 6;
        $cellStyleSieger['borderRightColor'] = '000000';
        $standCupTable->addCell(1000, $cellStyleSieger)->addText($row['Result'], ['size' => 8]);
        $i++;
    }
    $standCupTable->addRow();
    $standCupTable->addCell(null, ['borderTopColor' => '000000', 'borderTopSize' => 6, 'gridSpan' => 3])->addText(" ", ['size' => 8]);
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
    $boldFontStyle   = ['size' => 6, 'bold' => true, 'alignment' => Jc::CENTER];
    $cellHeaderStyle = ['valign' => 'center', 'alignment' => Jc::CENTER];
    if (defined('PhpOffice\\PhpWord\\Style\\Cell::TEXT_DIR_BTLR')) {
        $cellHeaderStyle['textDirection'] = \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR;
    }

    // JM-Definitionen
    $jmQuery = "
        SELECT *
        FROM JMDefinition 
        WHERE year = ? AND Erweitert = 0 AND Info = 0 AND Gruppe = 0
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
    if (!$stmt) { logDebug("SQL-Fehler in GetJMA prepare: ".$conn->error); return; }
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
        $templateProcessor->setValue('JMA1', '');
        $templateProcessor->setValue('JMA2', '');
        return;
    }

    // Split
    $midPoint   = (int)ceil(count($jmDefs) / 2);
    $firstHalf  = array_slice($jmDefs, 0, $midPoint);
    $secondHalf = array_slice($jmDefs, $midPoint);

    // Tabellen
    $phpWord = new PhpWord();
    $phpWord->addTableStyle('myTable', $tableStyle);
    $table1 = new \PhpOffice\PhpWord\Element\Table('myTable');
    $table1->addRow(3000);
    $table1->addCell(700,  ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
    $table1->addCell(3000, ['valign'=>'bottom','noWrap'=>true])->addText("Name", $boldFontStyle);
    foreach ($firstHalf as $d) {
        $c = $table1->addCell(1.5 * 567, $cellHeaderStyle);
        $c->addText($d['Bezeichnung'], $boldFontStyle);
    }
    $table1->addRow(200);
    $table1->addCell(700, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
    $table2 = new \PhpOffice\PhpWord\Element\Table('myTable');
    $table2->addRow(3000);
    foreach ($secondHalf as $d) {
        $c = $table2->addCell(1.5 * 567, $cellHeaderStyle);
        $c->addText($d['Bezeichnung'], $boldFontStyle);
    }
    $table2->addCell(3000, ['valign'=>'bottom','noWrap'=>true])->addText("Total", $boldFontStyle);
    $table2->addRow(200);
    $table2->addCell(700, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);

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
                if (isset($idxFirst[$endID]) && isset($resFirst[$idxFirst[$endID]])) {
                    $resFirst[$idxFirst[$endID]]['wert'] = $show;
                    $resFirst[$idxFirst[$endID]]['isStreicher'] = false;
                } elseif (isset($idxSecond[$endID]) && isset($resSecond[$idxSecond[$endID]])) {
                    $resSecond[$idxSecond[$endID]]['wert'] = $show;
                    $resSecond[$idxSecond[$endID]]['isStreicher'] = false;
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
            $table1->addCell(700, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
            $table2->addRow(100);
            $table2->addCell(700, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
        }
        $isTop3 = ($rang <= 3);
        $fontStyleRow = $isTop3 ? ['size'=>6,'bold'=>true] : ['size'=>6];

        // Tabelle 1
        $table1->addRow(200);
        $table1->addCell(700,  ['valign'=>'center','noWrap'=>true])->addText($rang.'.', $fontStyleRow);
        $table1->addCell(3500, ['valign'=>'center','noWrap'=>true])->addText($row['name'].' '.$row['vorname'], $fontStyleRow);
        foreach ($row['resFirst'] as $punkte) {
            $cellStyleFont = !empty($punkte['isStreicher']) ? array_merge($fontStyleRow, ['strikethrough'=>true]) : $fontStyleRow;
            $bgColor       = !empty($punkte['isStreicher']) ? ['bgColor'=>'D3D3D3'] : [];
            $table1->addCell(2 * 567, array_merge($cellStyle, $bgColor))
                   ->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }

        // Tabelle 2
        $table2->addRow(200);
        foreach ($row['resSecond'] as $punkte) {
            $cellStyleFont = !empty($punkte['isStreicher']) ? array_merge($fontStyleRow, ['strikethrough'=>true]) : $fontStyleRow;
            $bgColor       = !empty($punkte['isStreicher']) ? ['bgColor'=>'D3D3D3'] : [];
            $table2->addCell(2 * 567, array_merge($cellStyle, $bgColor))
                   ->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }
        $totalOut = number_format($row['total'], 2, '.', '');
        $table2->addCell(2 * 567, ['valign'=>'center'])->addText($totalOut, $fontStyleRow);
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
    $boldFontStyle   = ['size' => 6, 'bold' => true, 'alignment' => Jc::CENTER];
    $cellHeaderStyle = ['valign' => 'center', 'alignment' => Jc::CENTER];
    if (defined('PhpOffice\\PhpWord\\Style\\Cell::TEXT_DIR_BTLR')) {
        $cellHeaderStyle['textDirection'] = \PhpOffice\PhpWord\Style\Cell::TEXT_DIR_BTLR;
    }

    // JM-Definitionen
    $jmQuery = "
        SELECT *
        FROM JMDefinition 
        WHERE year = ? AND Erweitert = 0 AND Info = 0 AND Gruppe = 0
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
    if (!$stmt) { logDebug("SQL-Fehler in GetJMB prepare: ".$conn->error); return; }
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
        $templateProcessor->setValue('JMB1', '');
        $templateProcessor->setValue('JMB2', '');
        return;
    }

    // Split
    $midPoint   = (int)ceil(count($jmDefs) / 2);
    $firstHalf  = array_slice($jmDefs, 0, $midPoint);
    $secondHalf = array_slice($jmDefs, $midPoint);

    // Tabellen
    $phpWord = new PhpWord();
    $phpWord->addTableStyle('myTable', $tableStyle);
    $table1 = new \PhpOffice\PhpWord\Element\Table('myTable');
    $table1->addRow(3000);
    $table1->addCell(700,  ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
    $table1->addCell(3000, ['valign'=>'bottom','noWrap'=>true])->addText("Name", $boldFontStyle);
    foreach ($firstHalf as $d) {
        $c = $table1->addCell(1.5 * 567, $cellHeaderStyle);
        $c->addText($d['Bezeichnung'], $boldFontStyle);
    }
    $table1->addRow(200);
    $table1->addCell(700, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
    $table2 = new \PhpOffice\PhpWord\Element\Table('myTable');
    $table2->addRow(3000);
    foreach ($secondHalf as $d) {
        $c = $table2->addCell(1.5 * 567, $cellHeaderStyle);
        $c->addText($d['Bezeichnung'], $boldFontStyle);
    }
    $table2->addCell(3000, ['valign'=>'bottom','noWrap'=>true])->addText("Total", $boldFontStyle);
    $table2->addRow(200);
    $table2->addCell(700, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);

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
                if (isset($idxFirst[$endID]) && isset($resFirst[$idxFirst[$endID]])) {
                    $resFirst[$idxFirst[$endID]]['wert'] = $show;
                    $resFirst[$idxFirst[$endID]]['isStreicher'] = false;
                } elseif (isset($idxSecond[$endID]) && isset($resSecond[$idxSecond[$endID]])) {
                    $resSecond[$idxSecond[$endID]]['wert'] = $show;
                    $resSecond[$idxSecond[$endID]]['isStreicher'] = false;
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
            $table1->addCell(700, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
            $table2->addRow(100);
            $table2->addCell(700, ['valign'=>'bottom','noWrap'=>true])->addText("", $boldFontStyle);
        }
        $isTop3 = ($rang <= 3);
        $fontStyleRow = $isTop3 ? ['size'=>6,'bold'=>true] : ['size'=>6];

        // Tabelle 1
        $table1->addRow(200);
        $table1->addCell(700,  ['valign'=>'center','noWrap'=>true])->addText($rang.'.', $fontStyleRow);
        $table1->addCell(3500, ['valign'=>'center','noWrap'=>true])->addText($row['name'].' '.$row['vorname'], $fontStyleRow);
        foreach ($row['resFirst'] as $punkte) {
            $cellStyleFont = !empty($punkte['isStreicher']) ? array_merge($fontStyleRow, ['strikethrough'=>true]) : $fontStyleRow;
            $bgColor       = !empty($punkte['isStreicher']) ? ['bgColor'=>'D3D3D3'] : [];
            $table1->addCell(2 * 567, array_merge($cellStyle, $bgColor))
                   ->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }

        // Tabelle 2
        $table2->addRow(200);
        foreach ($row['resSecond'] as $punkte) {
            $cellStyleFont = !empty($punkte['isStreicher']) ? array_merge($fontStyleRow, ['strikethrough'=>true]) : $fontStyleRow;
            $bgColor       = !empty($punkte['isStreicher']) ? ['bgColor'=>'D3D3D3'] : [];
            $table2->addCell(2 * 567, array_merge($cellStyle, $bgColor))
                   ->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }
        $totalOut = number_format($row['total'], 2, '.', '');
        $table2->addCell(2 * 567, ['valign'=>'center'])->addText($totalOut, $fontStyleRow);
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

function getCountSieger($conn, $def) {
    $countSieger = 0;
    $sqlSiegerDef = "SELECT COUNT(*) as count FROM sieger s
                     JOIN siegerdef sd ON sd.ID = s.siegerdef 
                     WHERE sd.Bezeichnung = ?";
    $stmt = $conn->prepare($sqlSiegerDef);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("s", $def);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $countSieger = $row['count'] / 2;
    $countSieger = round($countSieger);
    $countSieger = intval($countSieger);
    return $countSieger;
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

function getParticipantData($kategorie, $conn) {
    $total = getTotal($kategorie, $conn);
    $streicher = GetStreicher($kategorie, $conn);
    $sqlMitglieder = "SELECT m.ID, m.Vorname, m.Name FROM mitglieder m
                      INNER JOIN Waffen w ON w.ID = m.WaffenID
                      WHERE w.Kategorie = ? AND m.Status = 1";
    $stmt = $conn->prepare($sqlMitglieder);
    if (!$stmt) {
        logDebug("SQL-Fehler in getParticipantData: " . $conn->error);
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
            if ($punkteRow['Streicher'] == 1 && $punkteRow['Maxpunkte'] != 100) {
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
// ERSETZE DIE KOMPLETTE getTotal() FUNKTION (ab Zeile 2477)

// ========================================================================
function getTotal($kategorie, $conn) {
    global $selectedYear;
    $resultatetotal = array();

    // Überprüfen ob SSM existiert
    $ssmExists = checkIfSSMExists($conn);

    // Erst alle Wettkämpfe mit Streicher=1 holen
    $sqlWettkaempfe = "SELECT ID, Maxpunkte FROM JMDefinition 
                       WHERE year = ? AND Streicher = 1 AND Info = 0 AND Erweitert = 0
                       ORDER BY Reihenfolge";
    $stmt = $conn->prepare($sqlWettkaempfe);
    if (!$stmt) {
        logDebug("SQL-Fehler in getTotal (Wettkämpfe): " . $conn->error);
        return array();
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $wettkaempfeResult = $stmt->get_result();
    $wettkaempfe = array();
    while ($row = $wettkaempfeResult->fetch_assoc()) {
        $wettkaempfe[] = array(
            'ID' => $row['ID'],
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

    // ==========================================
    // Mitglieder abrufen
    $sqlMitgliederA = "SELECT m.id FROM mitglieder m
                       INNER JOIN Waffen w ON w.ID = m.WaffenID
                       WHERE w.Kategorie = ? AND m.Status = 1";
    $stmt = $conn->prepare($sqlMitgliederA);
    if (!$stmt) {
        logDebug("SQL-Fehler in getTotal: " . $conn->error);
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

            // Resultate mit Streicher = 0
            $sqlResultateFix = "
                SELECT MAX(jm.Punkte) AS Punkte
                FROM jmresultate jm
                INNER JOIN JMDefinition jd ON jd.ID = jm.jmdefinitionID
                WHERE jm.mitgliederID = ? 
                  AND jd.Streicher = 0 
                  AND jd.Info = 0 
                  AND jd.Erweitert = 0
                  AND jd.year = ?
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

            // Resultate mit Streicher = 1 - NUR MIT AKTIVEN WETTKÄMPFEN
            $streicherArray = array();
            foreach ($wettkaempfe as $wettkampf) {
                $wettbewerbID = $wettkampf['ID'];
                $maxPunkte = $wettkampf['Maxpunkte'];

                // Resultat für diesen Wettkampf holen
                $sqlPunkte = "SELECT MAX(Punkte) AS Punkte 
                             FROM jmresultate 
                             WHERE mitgliederID = ? AND jmdefinitionID = ?";
                $stmt = $conn->prepare($sqlPunkte);
                if ($stmt) {
                    $stmt->bind_param("ii", $mitgliedID, $wettbewerbID);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $punkte = 0; // Default: nicht teilgenommen = 0
                    if ($row = $result->fetch_assoc()) {
                        if ($row['Punkte'] !== null) {
                            $punkte = $row['Punkte'];

                            // Normalisieren auf 100 wenn nötig
                            if ($maxPunkte != 100) {
                                $punkte = round(($punkte / $maxPunkte) * 100, 2);
                            }
                        }
                    }
                    $stmt->close();
                    $streicherArray[] = array(
                        'WettbewerbID' => $wettbewerbID,
                        'NormalizedPoints' => $punkte
                    );
                }
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
    $sqlWettkaempfe = "SELECT ID, Maxpunkte FROM JMDefinition 
                       WHERE year = ? AND Streicher = 1 AND Info = 0 AND Erweitert = 0";
    if ($ssmExists) {
        $sqlWettkaempfe .= " AND Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
    }
    $sqlWettkaempfe .= " ORDER BY Reihenfolge";
    $stmt = $conn->prepare($sqlWettkaempfe);
    if (!$stmt) {
        logDebug("SQL-Fehler in GetStreicher (Wettkämpfe): " . $conn->error);
        return array();
    }
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $wettkaempfeResult = $stmt->get_result();
    $wettkaempfe = array();
    while ($row = $wettkaempfeResult->fetch_assoc()) {
        $wettkaempfe[] = array(
            'ID' => $row['ID'],
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
        logDebug("SQL-Fehler in GetStreicher (Mitglieder): " . $conn->error);
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
                $wettbewerbID = $wettkampf['ID'];
                $maxPunkte = $wettkampf['Maxpunkte'];

                // Resultat für diesen Wettkampf holen
                $sqlPunkte = "SELECT MAX(Punkte) AS Punkte 
                             FROM jmresultate 
                             WHERE mitgliederID = ? AND jmdefinitionID = ?";
                $stmt = $conn->prepare($sqlPunkte);
                if ($stmt) {
                    $stmt->bind_param("ii", $mitgliedID, $wettbewerbID);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $punkte = 0; // Default: nicht teilgenommen = 0
                    if ($row = $result->fetch_assoc()) {
                        if ($row['Punkte'] !== null) {
                            $punkte = $row['Punkte'];

                            // Normalisieren auf 100 wenn nötig
                            if ($maxPunkte != 100) {
                                $punkte = round(($punkte / $maxPunkte) * 100, 2);
                            }
                        }
                    }
                    $stmt->close();
                    $streicherArray[] = array(
                        'WettbewerbID' => $wettbewerbID,
                        'NormalizedPoints' => $punkte
                    );
                }
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
        error_log("[JM Debug] DBG Endstich raw=$raw scaled=$scal");
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
        error_log("[JM Debug] DBG Kanti raw=$raw scaled=$scal");
    }

    // Ausgeben
    $dumpSide = function($label, $defs, $cells) {
        error_log("[JM Debug] ---- $label ----");
        foreach ($cells as $idx => $c) {
            $defId = $c['defId'] ?? null;
            $bez   = '';
            foreach ($defs as $d) { if ((int)$d['ID'] === (int)$defId) { $bez = $d['Bezeichnung']; break; } }
            $w = $c['wert'] ?? '';
            $n = array_key_exists('num',$c) ? $c['num'] : null;
            $s = !empty($c['isStreicher']) ? ' (Streicher)' : '';
            error_log("[JM Debug] ".sprintf("#%02d %-25s ID=%s  wert=%s  num=%s%s",
                $idx+1, $bez, $defId, var_export($w,true), var_export($n,true), $s));
        }
    };
    $dumpSide('LEFT',  $defsLeft,  $left);
    $dumpSide('RIGHT', $defsRight, $right);
    $tA = $calcTotal($left);
    $tB = $calcTotal($right);
    error_log("[JM Debug] TOTAL_A=$tA TOTAL_B=$tB TOTAL=".($tA+$tB));
}

// Ende der Datei
logDebug("functions.inc.php erfolgreich geladen");
