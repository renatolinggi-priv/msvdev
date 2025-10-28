<?php
/**
 * Migrations-Script: Endstich und Kantonalstich in jmresultate speichern
 * 
 * Dieses Script sollte EINMALIG ausgeführt werden, um bestehende Daten
 * aus endstich und kantiresultate in jmresultate zu migrieren.
 */

include '../config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Migration: Endstich und Kantonalstich nach jmresultate</h2>";
echo "<pre>";

// =====================================================
// 1. ENDSTICH MIGRATION
// =====================================================
echo "\n=== ENDSTICH MIGRATION ===\n";

$jahre = [2024, 2025]; // Füge hier weitere Jahre hinzu, falls nötig

foreach ($jahre as $jahr) {
    echo "\nJahr: $jahr\n";
    
    // Hole Endstich Definition ID für dieses Jahr
    $defSql = "SELECT ID FROM JMDefinition WHERE Bezeichnung = 'Endstich' AND year = ?";
    $defStmt = $conn->prepare($defSql);
    $defStmt->bind_param("i", $jahr);
    $defStmt->execute();
    $defResult = $defStmt->get_result();
    
    if ($defResult->num_rows == 0) {
        echo "  ⚠️  Keine Endstich-Definition für Jahr $jahr gefunden\n";
        $defStmt->close();
        continue;
    }
    
    $defRow = $defResult->fetch_assoc();
    $endstichDefID = $defRow['ID'];
    echo "  ✓ Endstich Definition ID: $endstichDefID\n";
    $defStmt->close();
    
    // Hole alle Endstich-Einträge für dieses Jahr
    $endstichSql = "SELECT MitgliedID,
                    COALESCE(Schuss1,0) + COALESCE(Schuss2,0) + COALESCE(Schuss3,0) + 
                    COALESCE(Schuss4,0) + COALESCE(Schuss5,0) + COALESCE(Schuss6,0) + 
                    COALESCE(Schuss7,0) + COALESCE(Schuss8,0) + COALESCE(Schuss9,0) + 
                    COALESCE(Schuss10,0) AS Summe
                    FROM endstich 
                    WHERE Jahr = ?";
    $endstichStmt = $conn->prepare($endstichSql);
    $endstichStmt->bind_param("i", $jahr);
    $endstichStmt->execute();
    $endstichResult = $endstichStmt->get_result();
    
    $count = 0;
    $updated = 0;
    $inserted = 0;
    
    while ($row = $endstichResult->fetch_assoc()) {
        $mitgliedID = $row['MitgliedID'];
        $summe = $row['Summe'];
        
        // Prüfe ob bereits Eintrag existiert
        $checkSql = "SELECT ID, Punkte FROM jmresultate WHERE mitgliederID = ? AND jmdefinitionID = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $mitgliedID, $endstichDefID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update
            $existingRow = $checkResult->fetch_assoc();
            $oldPunkte = $existingRow['Punkte'];
            
            $updateSql = "UPDATE jmresultate SET Punkte = ? WHERE mitgliederID = ? AND jmdefinitionID = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("iii", $summe, $mitgliedID, $endstichDefID);
            $updateStmt->execute();
            $updateStmt->close();
            
            if ($oldPunkte != $summe) {
                echo "  ↻ Mitglied $mitgliedID: $oldPunkte → $summe Punkte\n";
                $updated++;
            }
        } else {
            // Insert
            $insertSql = "INSERT INTO jmresultate (mitgliederID, jmdefinitionID, Punkte, Info) VALUES (?, ?, ?, '')";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("iii", $mitgliedID, $endstichDefID, $summe);
            $insertStmt->execute();
            $insertStmt->close();
            
            echo "  + Mitglied $mitgliedID: $summe Punkte eingefügt\n";
            $inserted++;
        }
        
        $checkStmt->close();
        $count++;
    }
    
    $endstichStmt->close();
    echo "  ✓ $count Einträge verarbeitet ($inserted neu, $updated aktualisiert)\n";
}

// =====================================================
// 2. KANTONALSTICH MIGRATION
// =====================================================
echo "\n\n=== KANTONALSTICH MIGRATION ===\n";

foreach ($jahre as $jahr) {
    echo "\nJahr: $jahr\n";
    
    // Hole Kantonalstich Definition ID für dieses Jahr
    $defSql = "SELECT ID FROM JMDefinition WHERE Bezeichnung = 'Bester Kantonalstich' AND year = ?";
    $defStmt = $conn->prepare($defSql);
    $defStmt->bind_param("i", $jahr);
    $defStmt->execute();
    $defResult = $defStmt->get_result();
    
    if ($defResult->num_rows == 0) {
        echo "  ⚠️  Keine Kantonalstich-Definition für Jahr $jahr gefunden\n";
        $defStmt->close();
        continue;
    }
    
    $defRow = $defResult->fetch_assoc();
    $kantiDefID = $defRow['ID'];
    echo "  ✓ Kantonalstich Definition ID: $kantiDefID\n";
    $defStmt->close();
    
    // Hole alle Kantonalstich-Einträge für dieses Jahr (höchste Passe)
    $kantiSql = "SELECT MitgliedID,
                 GREATEST(
                     COALESCE(Passe1,0),
                     COALESCE(Passe2,0),
                     COALESCE(Passe3,0),
                     COALESCE(Passe4,0),
                     COALESCE(Passe5,0)
                 ) AS BestePasse
                 FROM kantiresultate 
                 WHERE Jahr = ?";
    $kantiStmt = $conn->prepare($kantiSql);
    $kantiStmt->bind_param("i", $jahr);
    $kantiStmt->execute();
    $kantiResult = $kantiStmt->get_result();
    
    $count = 0;
    $updated = 0;
    $inserted = 0;
    
    while ($row = $kantiResult->fetch_assoc()) {
        $mitgliedID = $row['MitgliedID'];
        $bestePasse = $row['BestePasse'];
        
        // Prüfe ob bereits Eintrag existiert
        $checkSql = "SELECT ID, Punkte FROM jmresultate WHERE mitgliederID = ? AND jmdefinitionID = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $mitgliedID, $kantiDefID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Update
            $existingRow = $checkResult->fetch_assoc();
            $oldPunkte = $existingRow['Punkte'];
            
            $updateSql = "UPDATE jmresultate SET Punkte = ? WHERE mitgliederID = ? AND jmdefinitionID = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("iii", $bestePasse, $mitgliedID, $kantiDefID);
            $updateStmt->execute();
            $updateStmt->close();
            
            if ($oldPunkte != $bestePasse) {
                echo "  ↻ Mitglied $mitgliedID: $oldPunkte → $bestePasse Punkte\n";
                $updated++;
            }
        } else {
            // Insert
            $insertSql = "INSERT INTO jmresultate (mitgliederID, jmdefinitionID, Punkte, Info) VALUES (?, ?, ?, '')";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("iii", $mitgliedID, $kantiDefID, $bestePasse);
            $insertStmt->execute();
            $insertStmt->close();
            
            echo "  + Mitglied $mitgliedID: $bestePasse Punkte eingefügt\n";
            $inserted++;
        }
        
        $checkStmt->close();
        $count++;
    }
    
    $kantiStmt->close();
    echo "  ✓ $count Einträge verarbeitet ($inserted neu, $updated aktualisiert)\n";
}

echo "\n\n=== MIGRATION ABGESCHLOSSEN ===\n";
echo "</pre>";

$conn->close();
?>