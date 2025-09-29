<?php
include '../config.php';
// load_endschresultate.php
$jahr = isset($_POST['jahr']) ? $_POST['jahr'] : date('Y'); // Jahr aus der POST-Anfrage holen
error_log($jahr);  // Zum Überprüfen, ob das Jahr korrekt übergeben wurde

// Transaktion starten
$conn->begin_transaction();

try {
    // Bestehende Löschungen
    $conn->query("DELETE FROM `endstich` WHERE `Jahr` = $jahr;");
    $conn->query("DELETE FROM `glueck` WHERE `Jahr` = $jahr;");
    $conn->query("DELETE FROM `kunst` WHERE `Jahr` = $jahr;");
    $conn->query("DELETE FROM `schwini` WHERE `Jahr` = $jahr;");
    $conn->query("DELETE FROM `zabig` WHERE `Jahr` = $jahr;");
    
    // NEUE SICHERHEITSLÖSCHUNG: Lösche alle jmresultate-Einträge, die zum Endstich gehören
    // Zuerst die ID des Endstich-Wettbewerbs für das gewählte Jahr ermitteln
    $sql_endstich_id = "SELECT ID FROM JMDefinition 
                        WHERE Bezeichnung = 'Endstich' 
                        AND year = ?";
    
    $stmt = $conn->prepare($sql_endstich_id);
    $stmt->bind_param('i', $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $endstich_id = $row['ID'];
        
        // Jetzt alle jmresultate-Einträge für diesen Endstich löschen
        $sql_delete_jmresultate = "DELETE FROM jmresultate 
                                   WHERE jmdefinitionID = ?";
        
        $stmt_delete = $conn->prepare($sql_delete_jmresultate);
        $stmt_delete->bind_param('i', $endstich_id);
        $stmt_delete->execute();
        
        $deleted_rows = $stmt_delete->affected_rows;
        error_log("Gelöschte jmresultate-Einträge für Endstich: " . $deleted_rows);
    }

    // Transaktion erfolgreich abschließen
    $conn->commit();
    echo "Tabellen erfolgreich geleert.";
} catch (Exception $e) {
    // Bei einem Fehler Transaktion rückgängig machen
    $conn->rollback();
    echo "Fehler beim Leeren der Tabellen: " . $e->getMessage();
}

// Verbindung schließen
$conn->close();
?>