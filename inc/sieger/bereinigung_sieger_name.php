<?php
// bereinige_sieger_namen.php
require_once '../config.php';

echo "Starte Bereinigung der Sieger-Namen...\n<br>";

// Alle Sieger holen
$sql = "SELECT ID, Name FROM sieger WHERE Name != 'Nicht durchgeführt' AND Name != 'Keine Auswertung' AND Name != 'Nicht druchgeführt'";
$result = $conn->query($sql);

$updated = 0;
$notFound = 0;
$already_correct = 0;

while ($row = $result->fetch_assoc()) {
    $sieger_id = $row['ID'];
    $current_name = $row['Name'];
    
    // Versuche 1: Name ist "Vorname Nachname" - suche nach Übereinstimmung
    $parts = explode(' ', $current_name, 2);
    if (count($parts) == 2) {
        $vorname = trim($parts[0]);
        $nachname = trim($parts[1]);
        
        // Variante 1: current_name ist "Vorname Nachname"
        $sql_check = "SELECT Vorname, Name FROM mitglieder WHERE Vorname = ? AND Name = ?";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("ss", $vorname, $nachname);
        $stmt->execute();
        $result_check = $stmt->get_result();
        
        if ($result_check->num_rows > 0) {
            // Gefunden! Name ist "Vorname Nachname", muss zu "Nachname Vorname" werden
            $member = $result_check->fetch_assoc();
            $correct_name = $member['Name'] . ' ' . $member['Vorname'];
            
            if ($current_name !== $correct_name) {
                // Update durchführen
                $sql_update = "UPDATE sieger SET Name = ? WHERE ID = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("si", $correct_name, $sieger_id);
                $stmt_update->execute();
                
                echo "✓ ID $sieger_id: '$current_name' → '$correct_name'\n<br>";
                $updated++;
            } else {
                $already_correct++;
            }
        } else {
            // Variante 2: current_name ist schon "Nachname Vorname" - prüfe umgekehrt
            $sql_check2 = "SELECT Vorname, Name FROM mitglieder WHERE Name = ? AND Vorname = ?";
            $stmt2 = $conn->prepare($sql_check2);
            $stmt2->bind_param("ss", $vorname, $nachname);
            $stmt2->execute();
            $result_check2 = $stmt2->get_result();
            
            if ($result_check2->num_rows > 0) {
                // Ist bereits im richtigen Format "Nachname Vorname"
                echo "○ ID $sieger_id: '$current_name' ist bereits korrekt\n<br>";
                $already_correct++;
            } else {
                // Nicht gefunden
                echo "⚠ ID $sieger_id: Mitglied nicht gefunden für '$current_name'\n<br>";
                $notFound++;
            }
        }
    } else {
        echo "⚠ ID $sieger_id: Kann '$current_name' nicht aufteilen\n<br>";
        $notFound++;
    }
}

echo "\n<br><br>";
echo "=================================\n<br>";
echo "Bereinigung abgeschlossen!\n<br>";
echo "Aktualisiert: $updated\n<br>";
echo "Bereits korrekt: $already_correct\n<br>";
echo "Nicht gefunden: $notFound\n<br>";
echo "=================================\n<br>";

$conn->close();
?>