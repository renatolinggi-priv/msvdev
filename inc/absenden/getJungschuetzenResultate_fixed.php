<?php
// getJungschuetzenResultate_fixed.php - Korrigierte Version für Block-Entfernung

function getJungschuetzenResultate($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("Starte getJungschuetzenResultate für Jahr: " . $selectedYear);
    
    // Zuerst prüfen ob Daten vorhanden sind
    $checkSql = "
        SELECT COUNT(*) as count
        FROM jungschuetzen j
        INNER JOIN endstich_jung e ON j.id = e.JungschuetzeID 
        WHERE e.Jahr = ? AND e.Schuss1 != 0 AND e.Schuss1 IS NOT NULL
    ";
    
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        logDebug("SQL-Fehler beim Check: " . $conn->error);
        // Bei Fehler Block entfernen
        try {
            $templateProcessor->cloneBlock('JUNGSCHUETZEN_BLOCK', 0);
        } catch (Exception $e) {
            $templateProcessor->setValue('JUNGSCHUETZEN_BLOCK', '');
            $templateProcessor->setValue('/JUNGSCHUETZEN_BLOCK', '');
            $templateProcessor->cloneRow('JRang', 0);
        }
        return;
    }
    
    $checkStmt->bind_param("i", $selectedYear);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkRow = $checkResult->fetch_assoc();
    $dataCount = intval($checkRow['count']);
    $checkStmt->close();
    
    logDebug("Gefundene Jungschützen-Datensätze: " . $dataCount);
    
    // WENN KEINE DATEN: Block komplett entfernen
    if ($dataCount == 0) {
        logDebug("Keine Jungschützen-Daten -> Entferne Block");
        
        $blockRemoved = false;
        
        // Methode 1: cloneBlock mit 0 (PHPWord Standard für Block-Entfernung)
        try {
            $templateProcessor->cloneBlock('JUNGSCHUETZEN_BLOCK', 0);
            logDebug("✓ Block mit cloneBlock(0) entfernt");
            $blockRemoved = true;
        } catch (Exception $e) {
            logDebug("cloneBlock fehlgeschlagen: " . $e->getMessage());
        }
        
        // Methode 2: deleteBlock (falls verfügbar in neueren Versionen)
        if (!$blockRemoved && method_exists($templateProcessor, 'deleteBlock')) {
            try {
                $templateProcessor->deleteBlock('JUNGSCHUETZEN_BLOCK');
                logDebug("✓ Block mit deleteBlock entfernt");
                $blockRemoved = true;
            } catch (Exception $e) {
                logDebug("deleteBlock fehlgeschlagen: " . $e->getMessage());
            }
        }
        
        // Methode 3: replaceBlock mit leerem String
        if (!$blockRemoved && method_exists($templateProcessor, 'replaceBlock')) {
            try {
                $templateProcessor->replaceBlock('JUNGSCHUETZEN_BLOCK', '');
                logDebug("✓ Block mit replaceBlock('') entfernt");
                $blockRemoved = true;
            } catch (Exception $e) {
                logDebug("replaceBlock fehlgeschlagen: " . $e->getMessage());
            }
        }
        
        // Methode 4: Manuelles Entfernen (Fallback)
        if (!$blockRemoved) {
            logDebug("Verwende Fallback: Manuelles Entfernen");
            
            // Block-Marker selbst entfernen
            try {
                $templateProcessor->setValue('JUNGSCHUETZEN_BLOCK', '');
                $templateProcessor->setValue('/JUNGSCHUETZEN_BLOCK', '');
                logDebug("Block-Marker entfernt");
            } catch (Exception $e) {
                logDebug("setValue für Block-Marker fehlgeschlagen");
            }
            
            // Titel/Text zwischen den Markern entfernen (falls als Variable)
            try {
                $templateProcessor->setValue('Endschiessen-Total-Sieger-Jungschützen', '');
            } catch (Exception $e) {
                // Titel ist möglicherweise kein Platzhalter
            }
            
            // Tabelle entfernen
            try {
                $templateProcessor->cloneRow('JRang', 0);
                logDebug("Tabelle mit cloneRow(0) entfernt");
            } catch (Exception $e) {
                logDebug("cloneRow(0) fehlgeschlagen: " . $e->getMessage());
                
                // Alternative: Eine leere Zeile mit Hinweis
                try {
                    $templateProcessor->cloneRow('JRang', 1);
                    $templateProcessor->setValue('JRang#1', '');
                    $templateProcessor->setValue('JName#1', '-- Keine Daten --');
                    $templateProcessor->setValue('JE#1', '');
                    $templateProcessor->setValue('JZ#1', '');
                    $templateProcessor->setValue('JS#1', '');
                    $templateProcessor->setValue('JG#1', '');
                    $templateProcessor->setValue('JK#1', '');
                    $templateProcessor->setValue('JT#1', '');
                    $templateProcessor->setValue('JKK#1', '');
                } catch (Exception $e2) {
                    logDebug("Auch Fallback fehlgeschlagen");
                }
            }
        }
        
        return; // Fertig - keine weiteren Aktionen nötig
    }
    
    // WENN DATEN VORHANDEN: Normale Verarbeitung
    logDebug("Verarbeite " . $dataCount . " Jungschützen-Datensätze");
    
    // SQL für die eigentlichen Daten
    $sql = "
        SELECT
            j.Name,
            j.Vorname,
            j.Geburtsdatum,
            COALESCE(ROUND(GREATEST(COALESCE(g.GSchuss1, 0), COALESCE(g.GSchuss2, 0), COALESCE(g.GSchuss3, 0))/10, 1), 0) AS GlueckTotal,
            COALESCE(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10, 0) AS EndstichTotal,
            COALESCE(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6, 0) AS Schwini_Summe1,
            COALESCE(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6, 0) AS Schwini_Summe2,
            COALESCE(ROUND((COALESCE(k.KSchuss1, 0) + COALESCE(k.KSchuss2, 0) + COALESCE(k.KSchuss3, 0) + COALESCE(k.KSchuss4, 0) + COALESCE(k.KSchuss5, 0)) / 10, 1), 0) AS KunstTotal,
            GREATEST(
                COALESCE(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6, 0),
                COALESCE(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6, 0)
            ) AS MaxSchwini,
            z.ZSchuss1, z.ZSchuss2, z.ZSchuss3, z.ZSchuss4, z.ZSchuss5, z.ZSchuss6
        FROM
            jungschuetzen j
        INNER JOIN endstich_jung e ON j.id = e.JungschuetzeID
        LEFT JOIN schwini_jung s ON j.id = s.JungschuetzeID AND s.Jahr = ?
        LEFT JOIN kunst_jung k ON j.id = k.JungschuetzeID AND k.Jahr = ?
        LEFT JOIN glueck_jung g ON j.id = g.JungschuetzeID AND g.Jahr = ?
        LEFT JOIN zabig_jung z ON j.id = z.JungschuetzeID AND z.Jahr = ?
        WHERE
            e.Jahr = ? AND e.Schuss1 != 0 AND e.Schuss1 IS NOT NULL
        GROUP BY
            j.id, j.Vorname, j.Name, j.Geburtsdatum
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logDebug("SQL-Fehler in prepare: " . $conn->error);
        $templateProcessor->cloneRow('JRang', 1);
        $templateProcessor->setValue('JRang#1', '');
        $templateProcessor->setValue('JName#1', 'Fehler beim Laden der Daten');
        return;
    }
    
    $stmt->bind_param("iiiii", $selectedYear, $selectedYear, $selectedYear, $selectedYear, $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();

    // Daten sammeln und berechnen
    $data = array();
    
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
        
        $data[] = $row;
    }
    
    $stmt->close();
    
    // Sortieren
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
    logDebug("Füge " . $rowCount . " Zeilen in die Tabelle ein");
    
    // Zeilen klonen
    $templateProcessor->cloneRow('JRang', $rowCount);
    
    // Daten einfügen
    $currentRow = 1;
    foreach ($data as $row) {
        if ($currentRow <= 3) {
            // Top 3 fett
            $templateProcessor->setValue("JRang#{$currentRow}", formatBold($currentRow . '.'));
            $templateProcessor->setValue("JName#{$currentRow}", formatBold($row['Name'] . " " . $row['Vorname']));
            $templateProcessor->setValue("JE#{$currentRow}", formatBold($row['EndstichTotal']));
            $templateProcessor->setValue("JZ#{$currentRow}", formatBold($row['ZabigTotal']));
            $templateProcessor->setValue("JS#{$currentRow}", formatBold($row['MaxSchwini']));
            $templateProcessor->setValue("JG#{$currentRow}", formatBold($row['GlueckTotal']));
            $templateProcessor->setValue("JK#{$currentRow}", formatBold($row['KunstTotal']));
            $templateProcessor->setValue("JT#{$currentRow}", formatBold($row['GesamtTotal']));
            $templateProcessor->setValue("JKK#{$currentRow}", '');
        } else {
            // Rest normal
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
    
    logDebug("getJungschuetzenResultate erfolgreich abgeschlossen");
}
?>