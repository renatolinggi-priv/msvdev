<?php
include '../config.php';

// Input-Validierung
$mitgliedID = isset($_POST['mitgliedID']) ? intval($_POST['mitgliedID']) : 0;
$jahr = isset($_POST['jahr']) ? intval($_POST['jahr']) : date('Y');

// Sicherheitsprüfung
if ($mitgliedID <= 0) {
    die(json_encode(['success' => false, 'message' => 'Ungültige Mitglied-ID']));
}

// Transaktion starten für Datenintegrität
$conn->begin_transaction();

try {
    // Array mit allen zu löschenden Tabellen
    $tables = ['endstich', 'schwini', 'zabig', 'kunst', 'glueck'];
    $deletedTotal = 0;
    
    // Prepared Statement für alle Tabellen vorbereiten
    foreach ($tables as $table) {
        $sql = "DELETE FROM `$table` WHERE Jahr = ? AND MitgliedID = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Fehler beim Vorbereiten der Abfrage für $table: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $jahr, $mitgliedID);
        
        if (!$stmt->execute()) {
            throw new Exception("Fehler beim Löschen aus $table: " . $stmt->error);
        }
        
        $deletedTotal += $stmt->affected_rows;
        $stmt->close();
    }
    
    // ZUSÄTZLICHE SICHERHEITSLÖSCHUNG: jmresultate-Einträge für Endstich löschen
    // Zuerst die ID des Endstich-Wettbewerbs für das gewählte Jahr ermitteln
    $sql_endstich = "SELECT ID FROM JMDefinition WHERE Bezeichnung = 'Endstich' AND year = ?";
    $stmt_endstich = $conn->prepare($sql_endstich);
    
    if (!$stmt_endstich) {
        throw new Exception("Fehler beim Vorbereiten der Endstich-Abfrage: " . $conn->error);
    }
    
    $stmt_endstich->bind_param("i", $jahr);
    $stmt_endstich->execute();
    $result = $stmt_endstich->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $endstich_id = $row['ID'];
        
        // Lösche jmresultate-Einträge für dieses Mitglied und den Endstich
        $sql_delete_jm = "DELETE FROM jmresultate WHERE jmdefinitionID = ? AND mitgliederID = ?";
        $stmt_delete_jm = $conn->prepare($sql_delete_jm);
        
        if (!$stmt_delete_jm) {
            throw new Exception("Fehler beim Vorbereiten der jmresultate-Löschung: " . $conn->error);
        }
        
        $stmt_delete_jm->bind_param("ii", $endstich_id, $mitgliedID);
        
        if (!$stmt_delete_jm->execute()) {
            throw new Exception("Fehler beim Löschen aus jmresultate: " . $stmt_delete_jm->error);
        }
        
        $deletedTotal += $stmt_delete_jm->affected_rows;
        $stmt_delete_jm->close();
    }
    
    $stmt_endstich->close();
    
    // Transaktion erfolgreich abschließen
    $conn->commit();
    
    // Erfolgreiche JSON-Antwort
    echo json_encode([
        'success' => true, 
        'message' => "Mitglied erfolgreich aus allen Endstich-Tabellen gelöscht.",
        'deleted_records' => $deletedTotal
    ]);
    
} catch (Exception $e) {
    // Bei einem Fehler Transaktion rückgängig machen
    $conn->rollback();
    
    // Fehler-JSON-Antwort
    echo json_encode([
        'success' => false,
        'message' => "Fehler beim Löschen: " . $e->getMessage()
    ]);
    
    // Fehler ins Log schreiben
    error_log("Fehler beim Löschen des Mitglieds $mitgliedID aus Endstich-Tabellen: " . $e->getMessage());
}

// Verbindung schließen
$conn->close();
?>