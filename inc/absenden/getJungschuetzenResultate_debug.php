<?php
// getJungschuetzenResultate_debug.php - Verbesserte Version mit Debug-Output

function getJungschuetzenResultate_debug($templateProcessor, $conn)
{
    global $selectedYear;
    logDebug("=== START getJungschuetzenResultate für Jahr: " . $selectedYear . " ===");
    
    // 1. DATEN-CHECK
    logDebug("SCHRITT 1: Prüfe ob Daten vorhanden sind");
    
    $checkSql = "
        SELECT COUNT(*) as count
        FROM jungschuetzen j
        LEFT JOIN endstich_jung e ON j.id = e.JungschuetzeID AND e.Jahr = ?
        WHERE e.Schuss1 != 0 AND e.Schuss1 IS NOT NULL
    ";
    
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        logDebug("FEHLER: Konnte Check-Statement nicht vorbereiten: " . $conn->error);
        // Bei Fehler: Tabelle trotzdem leer anzeigen
        $templateProcessor->cloneRow('JRang', 0);
        return;
    }
    
    $checkStmt->bind_param("i", $selectedYear);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkRow = $checkResult->fetch_assoc();
    $dataCount = $checkRow['count'];
    $checkStmt->close();
    
    logDebug("Anzahl gültiger Jungschützen-Datensätze: " . $dataCount);
    
    // 2. ENTSCHEIDUNG: Daten vorhanden oder nicht?
    if ($dataCount == 0) {
        logDebug("KEINE DATEN: Versuche Jungschützen-Bereich zu entfernen");
        
        // Prüfe welche Methoden verfügbar sind
        $availableMethods = [];
        $methods = ['cloneBlock', 'deleteBlock', 'replaceBlock', 'setBlockVisibility'];
        foreach ($methods as $method) {
            if (method_exists($templateProcessor, $method)) {
                $availableMethods[] = $method;
            }
        }
        logDebug("Verfügbare Block-Methoden: " . implode(', ', $availableMethods));
        
        // Versuche verschiedene Methoden
        $removed = false;
        
        // Methode 1: cloneBlock mit 0
        if (in_array('cloneBlock', $availableMethods)) {
            try {
                // Versuche mit verschiedenen Block-Namen
                $blockNames = ['JUNGSCHUETZEN', 'JUNGSCHUETZEN_BLOCK', 'JS_BLOCK'];
                foreach ($blockNames as $blockName) {
                    try {
                        $templateProcessor->cloneBlock($blockName, 0);
                        logDebug("✓ Block '{$blockName}' mit cloneBlock(0) entfernt");
                        $removed = true;
                        break;
                    } catch (Exception $e) {
                        logDebug("Block '{$blockName}' nicht gefunden oder konnte nicht entfernt werden");
                    }
                }
            } catch (Exception $e) {
                logDebug("cloneBlock fehlgeschlagen: " . $e->getMessage());
            }
        }
        
        // Methode 2: replaceBlock
        if (!$removed && in_array('replaceBlock', $availableMethods)) {
            try {
                $blockNames = ['JUNGSCHUETZEN', 'JUNGSCHUETZEN_BLOCK', 'JS_BLOCK'];
                foreach ($blockNames as $blockName) {
                    try {
                        $templateProcessor->replaceBlock($blockName, '');
                        logDebug("✓ Block '{$blockName}' mit replaceBlock('') entfernt");
                        $removed = true;
                        break;
                    } catch (Exception $e) {
                        logDebug("replaceBlock für '{$blockName}' fehlgeschlagen");
                    }
                }
            } catch (Exception $e) {
                logDebug("replaceBlock fehlgeschlagen: " . $e->getMessage());
            }
        }
        
        // Methode 3: Nur Tabelle leeren (Fallback)
        if (!$removed) {
            logDebug("Block-Methoden nicht erfolgreich, leere nur die Tabelle");
            try {
                $templateProcessor->cloneRow('JRang', 0);
                logDebug("✓ Tabellen-Zeilen mit cloneRow('JRang', 0) entfernt");
            } catch (Exception $e) {
                logDebug("cloneRow fehlgeschlagen: " . $e->getMessage());
            }
            
            // Versuche auch Titel zu entfernen falls vorhanden
            $titlePlaceholders = [
                'JUNGSCHUETZEN_TITEL',
                'JUNGSCHUETZEN_TITLE', 
                'JS_TITEL',
                'JS_TITLE',
                'JUNGSCHUETZEN_HEADER'
            ];
            
            foreach ($titlePlaceholders as $placeholder) {
                try {
                    $templateProcessor->setValue($placeholder, '');
                    logDebug("Platzhalter '{$placeholder}' geleert");
                } catch (Exception $e) {
                    // Platzhalter existiert nicht, das ist ok
                }
            }
        }
        
        logDebug("=== ENDE getJungschuetzenResultate (keine Daten) ===");
        return;
    }
    
    // 3. DATEN VORHANDEN: Normale Verarbeitung
    logDebug("DATEN VORHANDEN: Beginne normale Verarbeitung für " . $dataCount . " Datensätze");
    
    $sql = "
        SELECT
            j.Name,
            j.Vorname,
            j.Geburtsdatum,
            COALESCE(ROUND(GREATEST(COALESCE(g.GSchuss1, 0), COALESCE(g.GSchuss2, 0), COALESCE(g.GSchuss3, 0))/10, 1), 0) AS GlueckTotal,
            COALESCE(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10, 0) AS EndstichTotal,
            COALESCE(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6, 0) AS Schwini_Summe1,
            COALESCE(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6, 0) AS Schwini_Summe2,
            COALESCE(ROUND((k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) AS KunstTotal,
            GREATEST(
                COALESCE(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6, 0),
                COALESCE(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6, 0)
            ) AS MaxSchwini,
            z.ZSchuss1, z.ZSchuss2, z.ZSchuss3, z.ZSchuss4, z.ZSchuss5, z.ZSchuss6
        FROM
            jungschuetzen j
        LEFT JOIN endstich_jung e ON j.id = e.JungschuetzeID AND e.Jahr = ?
        LEFT JOIN schwini_jung s ON j.id = s.JungschuetzeID AND s.Jahr = ?
        LEFT JOIN kunst_jung k ON j.id = k.JungschuetzeID AND k.Jahr = ?
        LEFT JOIN glueck_jung g ON j.id = g.JungschuetzeID AND g.Jahr = ?
        LEFT JOIN zabig_jung z ON j.id = z.JungschuetzeID AND z.Jahr = ?
        WHERE
            e.Schuss1 != 0 AND e.Schuss1 IS NOT NULL
        GROUP BY
            j.id, j.Vorname, j.Name, j.Geburtsdatum
        ORDER BY
            j.Geburtsdatum ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logDebug("FEHLER: SQL-Prepare fehlgeschlagen: " . $conn->error);
        $templateProcessor->cloneRow('JRang', 0);
        return;
    }
    
    $stmt->bind_param("iiiii", $selectedYear, $selectedYear, $selectedYear, $selectedYear, $selectedYear);
    if (!$stmt->execute()) {
        logDebug("FEHLER: SQL-Execute fehlgeschlagen: " . $stmt->error);
        $stmt->close();
        $templateProcessor->cloneRow('JRang', 0);
        return;
    }
    
    $result = $stmt->get_result();
    logDebug("SQL ausgeführt, Anzahl Zeilen: " . $result->num_rows);

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
        
        logDebug("Verarbeite: " . $row['Name'] . " " . $row['Vorname'] . 
                 " - Total: " . $row['GesamtTotal']);
        
        $data[] = $row;
    }
    
    $stmt->close();
    
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
    logDebug("Finale Anzahl Zeilen für Tabelle: " . $rowCount);
    
    // Clone Zeilen für Tabelle
    try {
        $templateProcessor->cloneRow('JRang', $rowCount);
        logDebug("✓ cloneRow('JRang', " . $rowCount . ") erfolgreich");
    } catch (Exception $e) {
        logDebug("FEHLER bei cloneRow: " . $e->getMessage());
        return;
    }
    
    // Füge Daten ein
    $currentRow = 1;
    foreach ($data as $row) {
        if ($currentRow <= 3) {
            // Top 3 fett formatieren
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
        
        logDebug("Zeile " . $currentRow . " eingefügt");
        $currentRow++;
    }
    
    logDebug("=== ENDE getJungschuetzenResultate (erfolgreich) ===");
}

// Wrapper für normale Funktion
function getJungschuetzenResultate($templateProcessor, $conn)
{
    // Verwende Debug-Version wenn Debug aktiviert ist
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        return getJungschuetzenResultate_debug($templateProcessor, $conn);
    }
    
    // Sonst normale Version (deine bestehende Funktion)
    // ... hier dein bestehender Code ...
    
    // Für den Test verwenden wir erst mal die Debug-Version
    return getJungschuetzenResultate_debug($templateProcessor, $conn);
}
?>