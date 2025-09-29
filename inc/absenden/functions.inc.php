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

// ========================================
// HILFSFUNKTIONEN
// ========================================

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
            m.ID,
            m.Name,
            m.Vorname,
            e.Schuss1,
            e.Schuss2,
            e.Schuss3,
            e.Schuss4,
            e.Schuss5,
            e.Schuss6,
            e.Schuss7,
            e.Schuss8,
            e.Schuss9,
            e.Schuss10,
            e.Tiefschuss,
            w.Kranz_Endstich,
            COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS Endstich_Summe,
            SUM(CASE WHEN e.Schuss1 = 10 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN e.Schuss2 = 10 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN e.Schuss3 = 10 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN e.Schuss4 = 10 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN e.Schuss5 = 10 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN e.Schuss6 = 10 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN e.Schuss7 = 10 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN e.Schuss8 = 10 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN e.Schuss9 = 10 THEN 1 ELSE 0 END) +
            SUM(CASE WHEN e.Schuss10 = 10 THEN 1 ELSE 0 END) AS Anzahl_10
        FROM
            mitglieder m
        LEFT JOIN endstich e ON m.ID = e.MitgliedID AND e.Jahr = ?
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        GROUP BY
            m.ID, m.Vorname, m.Name
        ORDER BY
            Endstich_Summe DESC,
            e.Tiefschuss DESC,
            Anzahl_10 DESC
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logDebug("SQL-Fehler in getEndstich prepare: " . $conn->error);
        $templateProcessor->cloneRow('ESRang', 0);
        return;
    }
    
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();

    // Zähle die Zeilen
    $rowCount = 0;
    $data = array();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['Endstich_Summe'])) {
                $data[] = $row;
                $rowCount++;
            }
        }
    }
    
    if ($rowCount == 0) {
        logDebug("Keine Endstich-Daten gefunden");
        $templateProcessor->cloneRow('ESRang', 0);
        return;
    }
    
    // Klone die Platzhalter-Zeile
    $templateProcessor->cloneRow('ESRang', $rowCount);
    
    // Daten in die Tabelle einfügen
    $currentRow = 1;
    foreach ($data as $row) {
        $keys = array_keys($row);
        $schussKeys = preg_grep('/^Schuss\d+$/', $keys);
        $scount = count($schussKeys);

        if ($currentRow <= 3) {
            $templateProcessor->setValue("ESRang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue("ESName#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
            
            for ($i = 1; $i <= $scount; $i++) {
                $schuss = "Schuss" . $i;
                $value = isset($row[$schuss]) ? $row[$schuss] : '';
                $templateProcessor->setValue("ESS$i#{$currentRow}", formatBold($value));
            }
            
            $templateProcessor->setValue("ESTO#{$currentRow}", formatBold($row['Endstich_Summe']));
            $templateProcessor->setValue("ESKK#{$currentRow}", formatBold('KK'));
        } else {
            $templateProcessor->setValue("ESRang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue("ESName#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
            
            for ($i = 1; $i <= $scount; $i++) {
                $schuss = "Schuss" . $i;
                $value = isset($row[$schuss]) ? $row[$schuss] : '';
                $templateProcessor->setValue("ESS$i#{$currentRow}", $value);
            }
            
            $templateProcessor->setValue("ESTO#{$currentRow}", $row['Endstich_Summe']);

            if ($row['Endstich_Summe'] >= $row['Kranz_Endstich']) {
                $templateProcessor->setValue("ESKK#{$currentRow}", "KK");
            } else {
                $templateProcessor->setValue("ESKK#{$currentRow}", "");
            }
        }

        $currentRow++;
    }
    
    $stmt->close();
    logDebug("getEndstich erfolgreich abgeschlossen");
}

function getSchwini($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getSchwini für Jahr: " . $selectedYear);
    
    $sql = "
        SELECT
            m.ID,
            m.Name,
            m.Vorname,
            m.Geburtsdatum,
            s.P1Schuss1,
            s.P1Schuss2,
            s.P1Schuss3,
            s.P1Schuss4,
            s.P1Schuss5,
            s.P1Schuss6,
            s.P2Schuss1,
            s.P2Schuss2,
            s.P2Schuss3,
            s.P2Schuss4,
            s.P2Schuss5,
            s.P2Schuss6,
            COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0) AS Schwini1_Summe,
            COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0) AS Schwini2_Summe,
            GREATEST(
                COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0),
                COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0)
            ) AS Hoechste_Summe,
            LEAST(
                COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0),
                COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0)
            ) AS Tiefste_Summe,
            CASE
                WHEN COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0) >
                     COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0)
                THEN 'Passe 1'
                ELSE 'Passe 2'
            END AS Hoehere_Passe,
            CASE
                WHEN COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0) <
                     COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0)
                THEN 'Passe 1'
                ELSE 'Passe 2'
            END AS Kleinere_Passe
        FROM
            mitglieder m
        LEFT JOIN schwini s ON m.ID = s.MitgliedID AND s.Jahr = ?
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        GROUP BY
            m.ID, m.Vorname, m.Name, m.Geburtsdatum
        HAVING
            Hoechste_Summe != 0
        ORDER BY
            Hoechste_Summe DESC,
            Tiefste_Summe ASC,
            m.Geburtsdatum ASC
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logDebug("SQL-Fehler in getSchwini prepare: " . $conn->error);
        $templateProcessor->cloneRow('SRang', 0);
        return;
    }
    
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();

    // Daten sammeln
    $rowCount = 0;
    $data = array();
    
    if ($result->num_rows > 0) {
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
        $schussKeys = preg_grep('/^P1Schuss\d+$/', $keys);
        $scount = count($schussKeys);

        if ($currentRow <= 3) {
            $templateProcessor->setValue("SRang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue("SName#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
            
            for ($i = 1; $i <= $scount; $i++) {
                $pschuss = ($row['Hoehere_Passe'] == "Passe 1") ? 1 : 2;
                $schuss = "P" . $pschuss . "Schuss" . $i;
                $value = isset($row[$schuss]) ? $row[$schuss] : '0';
                $templateProcessor->setValue("SS$i#{$currentRow}", formatBold($value));
            }
            
            $templateProcessor->setValue("ST1#{$currentRow}", formatBold($row['Hoechste_Summe']));
            $templateProcessor->setValue("ST2#{$currentRow}", formatBold($row['Tiefste_Summe']));
        } else {
            $templateProcessor->setValue("SRang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue("SName#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
            
            for ($i = 1; $i <= $scount; $i++) {
                $pschuss = ($row['Hoehere_Passe'] == "Passe 1") ? 1 : 2;
                $schuss = "P" . $pschuss . "Schuss" . $i;
                $value = isset($row[$schuss]) ? $row[$schuss] : "0";
                $templateProcessor->setValue("SS$i#{$currentRow}", $value);
            }
            
            $templateProcessor->setValue("ST1#{$currentRow}", $row['Hoechste_Summe']);
            $templateProcessor->setValue("ST2#{$currentRow}", $row['Tiefste_Summe']);
        }

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
            $templateProcessor->setValue($kategorie . "T#{$currentRow}", formatBold($row['GesamtTotal']));
            
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
            $templateProcessor->setValue($kategorie . "T#{$currentRow}", $row['GesamtTotal']);
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
        FROM jungschuetzen j
        LEFT JOIN endstich_jung e ON j.id = e.JungschuetzeID AND e.Jahr = ?
        WHERE e.Schuss1 != 0 AND e.Schuss1 IS NOT NULL
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
            $placeholders = ['JName', 'JE', 'JZ', 'JS', 'JG', 'JK', 'JT', 'JKK'];
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
            j.Name,
            j.Vorname,
            j.Geburtsdatum,
            COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3)/10, 1), 0) AS GlueckTotal,
            COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS EndstichTotal,
            COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0) AS Schwini_Summe1,
            COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0) AS Schwini_Summe2,
            COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) AS KunstTotal, 
            GREATEST(COALESCE(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6, 0),
                     COALESCE(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6, 0)) AS MaxSchwini,
            LEAST(COALESCE(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6, 0),
                  COALESCE(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6, 0)) AS MinSchwini,
            z.ZSchuss1, z.ZSchuss2, z.ZSchuss3, z.ZSchuss4, z.ZSchuss5, z.ZSchuss6
        FROM
            jungschuetzen j
        LEFT JOIN endstich_jung e ON j.id = e.JungschuetzeID AND e.Jahr = ?
        LEFT JOIN schwini_jung s ON j.id = s.JungschuetzeID AND s.Jahr = ?
        LEFT JOIN kunst_jung k ON j.id = k.JungschuetzeID AND k.Jahr = ?
        LEFT JOIN glueck_jung g ON j.id = g.JungschuetzeID AND g.Jahr = ?
        LEFT JOIN zabig_jung z ON j.id = z.JungschuetzeID AND z.Jahr = ?
        WHERE
            e.Schuss1 != 0 OR e.Schuss1 IS NOT NULL
        GROUP BY
            j.id, j.Vorname, j.Name
        ORDER BY
            j.Geburtsdatum ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logDebug("SQL-Fehler in getJungschuetzenResultate prepare: " . $conn->error);
        $templateProcessor->cloneRow('JRang', 0);
        return;
    }
    
    $stmt->bind_param("iiiii", $selectedYear, $selectedYear, $selectedYear, $selectedYear, $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();

    // Rest des Codes bleibt gleich...
    $data = array();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $zabigTotal = 0;
            for ($i = 1; $i <= 6; $i++) {
                $schuss = "ZSchuss" . $i;
                if (isset($row[$schuss]) && $row[$schuss] != null) {
                    $zabigTotal += calculatePoints($row[$schuss]);
                }
            }
            $row['ZabigTotal'] = $zabigTotal;
            $row['GesamtTotal'] = $row['EndstichTotal'] + $row['GlueckTotal'] + 
                                  $zabigTotal + $row['KunstTotal'] + $row['MaxSchwini'];
            
            if (!empty($row['EndstichTotal'])) {
                $data[] = $row;
            }
        }
    }
    
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
    $templateProcessor->cloneRow('JRang', $rowCount);
    
    $currentRow = 1;
    foreach ($data as $row) {
        if ($currentRow <= 3) {
            $templateProcessor->setValue("JRang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue("JName#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
            $templateProcessor->setValue("JE#{$currentRow}", formatBold($row['EndstichTotal']));
            $templateProcessor->setValue("JZ#{$currentRow}", formatBold($row['ZabigTotal']));
            $templateProcessor->setValue("JS#{$currentRow}", formatBold($row['MaxSchwini']));
            $templateProcessor->setValue("JG#{$currentRow}", formatBold($row['GlueckTotal']));
            $templateProcessor->setValue("JK#{$currentRow}", formatBold($row['KunstTotal']));
            $templateProcessor->setValue("JT#{$currentRow}", formatBold($row['GesamtTotal']));
            $templateProcessor->setValue("JKK#{$currentRow}", formatBold(''));
        } else {
            $templateProcessor->setValue("JRang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue("JName#{$currentRow}", $row['Name'] . " " . $row['Vorname']);
            $templateProcessor->setValue("JE#{$currentRow}", $row['EndstichTotal']);
            $templateProcessor->setValue("JZ#{$currentRow}", $row['ZabigTotal']);
            $templateProcessor->setValue("JS#{$currentRow}", $row['MaxSchwini']);
            $templateProcessor->setValue("JG#{$currentRow}", $row['GlueckTotal']);
            $templateProcessor->setValue("JK#{$currentRow}", $row['KunstTotal']);
            $templateProcessor->setValue("JT#{$currentRow}", $row['GesamtTotal']);
            $templateProcessor->setValue("JKK#{$currentRow}", '');
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
        // Sie könnten bereits durch cloneBlock entfernt worden sein
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
               OR (COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) +
                   COALESCE(ep.PartnerSchwiniSchuss3, 0) + COALESCE(ep.PartnerSchwiniSchuss4, 0) +
                   COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0)) > 0)
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
            (COALESCE(ep.EndstichSchuss1, 0) + COALESCE(ep.EndstichSchuss2, 0) + COALESCE(ep.EndstichSchuss3, 0) +
             COALESCE(ep.EndstichSchuss4, 0) + COALESCE(ep.EndstichSchuss5, 0) + COALESCE(ep.EndstichSchuss6, 0) +
             COALESCE(ep.EndstichSchuss7, 0) + COALESCE(ep.EndstichSchuss8, 0) + COALESCE(ep.EndstichSchuss9, 0) +
             COALESCE(ep.EndstichSchuss10, 0)) AS Endstich_Summe,
            (COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) +
             COALESCE(ep.PartnerSchwiniSchuss3, 0) + COALESCE(ep.PartnerSchwiniSchuss4, 0) +
             COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0)) AS PartnerSchwini_Summe,
            ((COALESCE(ep.EndstichSchuss1, 0) + COALESCE(ep.EndstichSchuss2, 0) + COALESCE(ep.EndstichSchuss3, 0) +
              COALESCE(ep.EndstichSchuss4, 0) + COALESCE(ep.EndstichSchuss5, 0) + COALESCE(ep.EndstichSchuss6, 0) +
              COALESCE(ep.EndstichSchuss7, 0) + COALESCE(ep.EndstichSchuss8, 0) + COALESCE(ep.EndstichSchuss9, 0) +
              COALESCE(ep.EndstichSchuss10, 0)) +
             (COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) +
              COALESCE(ep.PartnerSchwiniSchuss3, 0) + COALESCE(ep.PartnerSchwiniSchuss4, 0) +
              COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0))) AS Total_Summe
        FROM mitglieder m
        INNER JOIN endresultate_partner ep ON m.ID = ep.MitgliedID
        WHERE ep.Jahr = ?
          AND ((COALESCE(ep.EndstichSchuss1, 0) + COALESCE(ep.EndstichSchuss2, 0) + COALESCE(ep.EndstichSchuss3, 0) +
                COALESCE(ep.EndstichSchuss4, 0) + COALESCE(ep.EndstichSchuss5, 0) + COALESCE(ep.EndstichSchuss6, 0) +
                COALESCE(ep.EndstichSchuss7, 0) + COALESCE(ep.EndstichSchuss8, 0) + COALESCE(ep.EndstichSchuss9, 0) +
                COALESCE(ep.EndstichSchuss10, 0)) > 0
               OR (COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) +
                   COALESCE(ep.PartnerSchwiniSchuss3, 0) + COALESCE(ep.PartnerSchwiniSchuss4, 0) +
                   COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0)) > 0)
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
        if ($currentRow <= 3) {
            // Top 3 fett formatiert
            $templateProcessor->setValue("PRang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue("PName#{$currentRow}", formatBold($row['PartnerName']));
            $templateProcessor->setValue("PE#{$currentRow}", formatBold($row['Endstich_Summe']));
            $templateProcessor->setValue("PS#{$currentRow}", formatBold($row['PartnerSchwini_Summe']));
            $templateProcessor->setValue("PT#{$currentRow}", formatBold($row['Total_Summe']));
            
        } else {
            // Rest normal formatiert
            $templateProcessor->setValue("PRang#{$currentRow}", $currentRow . ".");
            $templateProcessor->setValue("PName#{$currentRow}", $row['PartnerName']);
            $templateProcessor->setValue("PE#{$currentRow}", $row['Endstich_Summe']);
            $templateProcessor->setValue("PS#{$currentRow}", $row['PartnerSchwini_Summe']);
            $templateProcessor->setValue("PTotal#{$currentRow}", $row['Total_Summe']);
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
        WHERE w.Kategorie = ? and h.Passe1 > 0
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

function GetJMA($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte GetJMA für Jahr: " . $selectedYear);

    // Tabellenstile
    $tableStyle = array(
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 50,
    );

    $cellStyle = array('valign' => 'center', 'alignment' => Jc::CENTER);
    $textStyle = array('alignment' => Jc::CENTER);
    $boldFontStyle = array('size' => 6, 'bold' => true, 'alignment' => Jc::CENTER);
    $fontStyle = array('size' => 6);
    $cellHeaderStyle = array('valign' => 'center', 'textDirection' => Cell::TEXT_DIR_BTLR, 'alignment' => Jc::CENTER);

    $jmQuery = "
        SELECT * FROM JMDefinition 
        WHERE year = ? AND Erweitert=0 AND Info=0
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
    if (!$stmt) {
        logDebug("SQL-Fehler in GetJMA prepare: " . $conn->error);
        return;
    }
    
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $jmResult = $stmt->get_result();

    // Daten in Array sammeln
    $jmData = [];
    while ($jmRow = $jmResult->fetch_assoc()) {
        $jmData[] = $jmRow;
    }
    $stmt->close();

    // Anzahl der Definitionen
    $totalDefinitions = count($jmData);
    $midPoint = ceil($totalDefinitions / 2) - 1;
    
    // Arrays aufteilen
    $firstHalf = array_slice($jmData, 0, $midPoint);
    $secondHalf = array_slice($jmData, $midPoint);

    // Tabellen erstellen
    $phpWord = new PhpWord();
    $phpWord->addTableStyle('myTable', $tableStyle);

    // Erste Tabelle
    $table1 = new \PhpOffice\PhpWord\Element\Table('myTable');
    $table1->addRow(3000);
    $table1->addCell(700, ['valign' => 'bottom', 'noWrap' => true])->addText("", $boldFontStyle);
    $table1->addCell(3000, ['valign' => 'bottom', 'noWrap' => true])->addText("Name", $boldFontStyle);

    foreach ($firstHalf as $jmRow) {
        $cell = $table1->addCell(1.5 * 567, $cellHeaderStyle);
        $cell->addText($jmRow['Bezeichnung'], $boldFontStyle);
    }
    
    $table1->addRow(200);
    $table1->addCell(700, ['valign' => 'bottom', 'noWrap' => true])->addText("", $boldFontStyle);
    
    // Zweite Tabelle
    $table2 = new \PhpOffice\PhpWord\Element\Table('myTable');
    $table2->addRow(3000);

    foreach ($secondHalf as $jmRow) {
        $cell = $table2->addCell(1.5 * 567, $cellHeaderStyle);
        $cell->addText($jmRow['Bezeichnung'], $boldFontStyle);
    }

    $table2->addCell(3000, ['valign' => 'bottom', 'noWrap' => true])->addText("Total", $boldFontStyle);
    $table2->addRow(200);
    $table2->addCell(700, ['valign' => 'bottom', 'noWrap' => true])->addText("", $boldFontStyle);
    
    // Mitglieder abrufen
    $mitglieder = getParticipantData('Kat. A', $conn);
    $rang = 1;

    foreach ($mitglieder as $mitglied) {
        if ($rang == 4) {
            $table1->addRow(100);
            $table1->addCell(700, ['valign' => 'bottom', 'noWrap' => true])->addText("", $boldFontStyle);
        }
        
        // Erste Tabelle
        $table1->addRow(200);
        $fontStyleRow = $rang <= 3 ? array('size' => 6, 'bold' => true) : array('size' => 6);

        $table1->addCell(700, ['valign' => 'center', 'noWrap' => true])->addText($rang . ".", $fontStyleRow);
        $table1->addCell(3500, ['valign' => 'center', 'noWrap' => true])->addText($mitglied['Name'] . " " . $mitglied['Vorname'], $fontStyleRow);

        // Resultate für erste Hälfte
        $resultate = getResultateForMember($mitglied['ID'], $firstHalf, $mitglied['Streicher'], $conn);
        foreach ($resultate as $punkte) {
            $cellStyleFont = $punkte['isStreicher'] ? array_merge($fontStyleRow, ['strikethrough' => true]) : $fontStyleRow;
            $bgColor = $punkte['isStreicher'] ? ['bgColor' => 'D3D3D3'] : [];
            $table1->addCell(2 * 567, array_merge(['valign' => 'center', 'alignment' => Jc::CENTER], $bgColor))->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }

        // Zweite Tabelle
        if ($rang == 4) {
            $table2->addRow(100);
            $table2->addCell(700, ['valign' => 'bottom', 'noWrap' => true])->addText("", $boldFontStyle);
        }

        $table2->addRow(200);
        
        // Resultate für zweite Hälfte
        $resultate = getResultateForMember($mitglied['ID'], $secondHalf, $mitglied['Streicher'], $conn);
        foreach ($resultate as $punkte) {
            $cellStyleFont = $punkte['isStreicher'] ? array_merge($fontStyleRow, ['strikethrough' => true]) : $fontStyleRow;
            $bgColor = $punkte['isStreicher'] ? ['bgColor' => 'D3D3D3'] : [];
            $table2->addCell(2 * 567, array_merge(['valign' => 'center', 'alignment' => Jc::CENTER], $bgColor))->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }

        // Total
        $table2->addCell(2 * 567, ['valign' => 'center'])->addText(number_format($mitglied['Total'], 2, '.', ''), $fontStyleRow);

        $rang++;
    }

    $templateProcessor->setComplexBlock('JMA1', $table1);
    $templateProcessor->setComplexBlock('JMA2', $table2);
    
    logDebug("GetJMA erfolgreich abgeschlossen");
}

function GetJMB($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte GetJMB für Jahr: " . $selectedYear);

    // Prüfe ob SSM existiert
    $ssmExists = checkIfSSMExists($conn);

    // Tabellenstile
    $tableStyle = array(
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMarginTop' => 50,
        'cellMarginRight' => 50,
        'cellMarginBottom' => 50,
        'cellMarginLeft' => 50,
    );

    $cellStyle = array('valign' => 'center', 'alignment' => Jc::CENTER);
    $textStyle = array('alignment' => Jc::CENTER);
    $boldFontStyle = array('size' => 6, 'bold' => true, 'alignment' => Jc::CENTER);
    $fontStyle = array('size' => 6);
    $cellHeaderStyle = array('valign' => 'center', 'textDirection' => Cell::TEXT_DIR_BTLR, 'alignment' => Jc::CENTER);

    // SQL-Query abhängig von SSM
    if ($ssmExists) {
        $jmQuery = "
            SELECT * FROM JMDefinition 
            WHERE year = ? 
            AND Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'
            ORDER BY 
                CASE 
                    WHEN Bezeichnung = 'Bester Kantonalstich' THEN 3
                    WHEN Bezeichnung = 'SSM 2024' THEN 4
                    ELSE Reihenfolge
                END
        ";
    } else {
        $jmQuery = "
            SELECT * FROM JMDefinition 
            WHERE year = ? 
            ORDER BY 
                CASE 
                    WHEN Bezeichnung = 'Bester Kantonalstich' THEN 3
                    ELSE Reihenfolge
                END
        ";
    }

    $stmt = $conn->prepare($jmQuery);
    if (!$stmt) {
        logDebug("SQL-Fehler in GetJMB prepare: " . $conn->error);
        return;
    }
    
    $stmt->bind_param("i", $selectedYear);
    $stmt->execute();
    $jmResult = $stmt->get_result();

    // Daten sammeln
    $jmData = [];
    while ($jmRow = $jmResult->fetch_assoc()) {
        $jmData[] = $jmRow;
    }
    $stmt->close();

    // Arrays aufteilen
    $totalDefinitions = count($jmData);
    $midPoint = ceil($totalDefinitions / 2) - 1;
    $firstHalf = array_slice($jmData, 0, $midPoint);
    $secondHalf = array_slice($jmData, $midPoint);

    // Tabellen erstellen
    $phpWord = new PhpWord();
    $phpWord->addTableStyle('myTable', $tableStyle);

    // Erste Tabelle
    $table1 = new \PhpOffice\PhpWord\Element\Table('myTable');
    $table1->addRow(3000);
    $table1->addCell(700, ['valign' => 'bottom', 'noWrap' => true])->addText("", $boldFontStyle);
    $table1->addCell(3000, ['valign' => 'bottom', 'noWrap' => true])->addText("Name", $boldFontStyle);

    foreach ($firstHalf as $jmRow) {
        $cell = $table1->addCell(1.5 * 567, $cellHeaderStyle);
        $cell->addText($jmRow['Bezeichnung'], $boldFontStyle);
    }
    
    $table1->addRow(200);
    $table1->addCell(700, ['valign' => 'bottom', 'noWrap' => true])->addText("", $boldFontStyle);
    
    // Zweite Tabelle
    $table2 = new \PhpOffice\PhpWord\Element\Table('myTable');
    $table2->addRow(3000);

    foreach ($secondHalf as $jmRow) {
        $cell = $table2->addCell(1.5 * 567, $cellHeaderStyle);
        $cell->addText($jmRow['Bezeichnung'], $boldFontStyle);
    }

    $table2->addCell(3000, ['valign' => 'bottom', 'noWrap' => true])->addText("Total", $boldFontStyle);
    $table2->addRow(200);
    $table2->addCell(700, ['valign' => 'bottom', 'noWrap' => true])->addText("", $boldFontStyle);
    
    // Mitglieder abrufen
    $mitglieder = getParticipantData('Kat. B', $conn);
    $rang = 1;

    foreach ($mitglieder as $mitglied) {
        if ($rang == 4) {
            $table1->addRow(100);
            $table1->addCell(700, ['valign' => 'bottom', 'noWrap' => true])->addText("", $boldFontStyle);
        }
        
        // Erste Tabelle
        $table1->addRow(200);
        $fontStyleRow = $rang <= 3 ? array('size' => 6, 'bold' => true) : array('size' => 6);

        $table1->addCell(700, ['valign' => 'center', 'noWrap' => true])->addText($rang . ".", $fontStyleRow);
        $table1->addCell(3500, ['valign' => 'center', 'noWrap' => true])->addText($mitglied['Name'] . " " . $mitglied['Vorname'], $fontStyleRow);

        // Resultate für erste Hälfte
        $resultate = getResultateForMember($mitglied['ID'], $firstHalf, $mitglied['Streicher'], $conn);
        foreach ($resultate as $punkte) {
            $cellStyleFont = $punkte['isStreicher'] ? array_merge($fontStyleRow, ['strikethrough' => true]) : $fontStyleRow;
            $bgColor = $punkte['isStreicher'] ? ['bgColor' => 'D3D3D3'] : [];
            $table1->addCell(2 * 567, array_merge(['valign' => 'center', 'alignment' => Jc::CENTER], $bgColor))->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }

        // Zweite Tabelle
        if ($rang == 4) {
            $table2->addRow(100);
            $table2->addCell(700, ['valign' => 'bottom', 'noWrap' => true])->addText("", $boldFontStyle);
        }

        $table2->addRow(200);
        
        // Resultate für zweite Hälfte
        $resultate = getResultateForMember($mitglied['ID'], $secondHalf, $mitglied['Streicher'], $conn);
        foreach ($resultate as $punkte) {
            $cellStyleFont = $punkte['isStreicher'] ? array_merge($fontStyleRow, ['strikethrough' => true]) : $fontStyleRow;
            $bgColor = $punkte['isStreicher'] ? ['bgColor' => 'D3D3D3'] : [];
            $table2->addCell(2 * 567, array_merge(['valign' => 'center', 'alignment' => Jc::CENTER], $bgColor))->addText($punkte['wert'], $cellStyleFont, $textStyle);
        }

        // Total
        $table2->addCell(2 * 567, ['valign' => 'center'])->addText(number_format($mitglied['Total'], 2, '.', ''), $fontStyleRow);

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

function getTotal($kategorie, $conn) {
    global $selectedYear;
    $resultatetotal = array();

    // Überprüfen ob SSM existiert
    $ssmExists = checkIfSSMExists($conn);

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

            // Resultate mit Streicher = 1
            $sqlResultateStreicher = "
                SELECT
                    jr.jmdefinitionID AS WettbewerbID,
                    CASE 
                        WHEN jd.Maxpunkte != 100 THEN ROUND(MAX(jr.Punkte) / jd.Maxpunkte * 100, 2)
                        ELSE MAX(jr.Punkte)
                    END AS NormalizedPoints
                FROM 
                    jmresultate jr
                INNER JOIN 
                    JMDefinition jd ON jr.jmdefinitionID = jd.ID AND jd.year = ?
                WHERE 
                    jr.mitgliederID = ?
                    AND jd.Streicher = 1
                GROUP BY jr.jmdefinitionID
            ";
            
            $stmt = $conn->prepare($sqlResultateStreicher);
            if ($stmt) {
                $stmt->bind_param("ii", $selectedYear, $mitgliedID);
                $stmt->execute();
                $punkteStreicher = $stmt->get_result();
                
                $streicherArray = array();
                while ($row = $punkteStreicher->fetch_assoc()) {
                    $streicherArray[] = array(
                        'WettbewerbID' => $row['WettbewerbID'],
                        'NormalizedPoints' => $row['NormalizedPoints']
                    );
                }
                $stmt->close();
            }

            // Die drei niedrigsten Resultate streichen
            usort($streicherArray, function ($a, $b) {
                return $a['NormalizedPoints'] <=> $b['NormalizedPoints'];
            });
            
            $excludeCount = 3;
            $verbleibendeResultate = array_slice($streicherArray, $excludeCount);

            $totalStreicher = 0;
            foreach ($verbleibendeResultate as $result) {
                $totalStreicher += $result['NormalizedPoints'];
            }

            // Gesamtpunktzahl
            $total = $totalFix + $totalStreicher;
            $resultatetotal[$mitgliedID] = round($total, 2);
        }
    }
    
    arsort($resultatetotal);
    return $resultatetotal;
}

function GetStreicher($kategorie, $conn) {
    global $selectedYear;
    $MitgliedStreicher = array();

    // Überprüfen ob SSM existiert
    $ssmExists = checkIfSSMExists($conn);

    $sqlMitgliederA = "SELECT m.id FROM mitglieder m
                       INNER JOIN Waffen w ON w.ID = m.WaffenID
                       WHERE w.Kategorie = ? AND m.Status = 1";
    
    $stmt = $conn->prepare($sqlMitgliederA);
    if (!$stmt) {
        logDebug("SQL-Fehler in GetStreicher: " . $conn->error);
        return array();
    }
    
    $stmt->bind_param("s", $kategorie);
    $stmt->execute();
    $mitglieder = $stmt->get_result();
    $stmt->close();

    if ($mitglieder->num_rows > 0) {
        while ($mitglied = $mitglieder->fetch_assoc()) {
            $mitgliedID = $mitglied['id'];

            // Resultate mit Streicher = 1
            $sqlResultateStreicher = "
                SELECT
                    jr.jmdefinitionID AS WettbewerbID,
                    CASE 
                        WHEN jd.Maxpunkte != 100 THEN ROUND((jr.Punkte / jd.Maxpunkte) * 100, 2)
                        ELSE jr.Punkte
                    END AS NormalizedPoints
                FROM 
                    jmresultate jr
                INNER JOIN 
                    JMDefinition jd ON jr.jmdefinitionID = jd.ID AND jd.year = ?
                WHERE 
                    jr.mitgliederID = ?
                    AND jd.Streicher = 1
            ";
            
            if ($ssmExists) {
                $sqlResultateStreicher .= " AND jd.Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
            }
            
            $stmt = $conn->prepare($sqlResultateStreicher);
            if ($stmt) {
                $stmt->bind_param("ii", $selectedYear, $mitgliedID);
                $stmt->execute();
                $punkteStreicher = $stmt->get_result();

                $streicherArray = array();
                while ($row = $punkteStreicher->fetch_assoc()) {
                    $streicherArray[] = array(
                        'WettbewerbID' => $row['WettbewerbID'],
                        'NormalizedPoints' => $row['NormalizedPoints']
                    );
                }
                $stmt->close();

                // Die drei niedrigsten Resultate ermitteln
                usort($streicherArray, function ($a, $b) {
                    return $a['NormalizedPoints'] <=> $b['NormalizedPoints'];
                });

                $excludeCount = 3;
                $gestricheneResultate = array_slice($streicherArray, 0, $excludeCount);

                foreach ($gestricheneResultate as $gestrichen) {
                    $MitgliedStreicher[$mitgliedID][] = $gestrichen['WettbewerbID'];
                }
            }
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

// Ende der Datei
logDebug("functions.inc.php erfolgreich geladen");