<?php
/**
 * Load Kantonal Rangliste
 * Lädt die Kantonalstich-Rangliste für die Anzeige
 */

include '../config.php';

// Error handling für Production
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Parameter validieren
    $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $kat = isset($_GET['kat']) ? $_GET['kat'] : 'A';
    
    // Sicherheitsprüfung für Kategorie
    if (!in_array($kat, ['A', 'B'])) {
        $kat = 'A';
    }
    
    $kategorie = 'Kat. ' . $kat;
    
    // Verbindung prüfen
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // SQL mit Prepared Statement für Sicherheit
    $sql = "SELECT 
        m.Name, 
        m.Vorname, 
        k.Passe1, k.Passe2, k.Passe3, k.Passe4, k.Passe5,
        (COALESCE(k.Passe1, 0) + COALESCE(k.Passe2, 0) + COALESCE(k.Passe3, 0) + 
         COALESCE(k.Passe4, 0) + COALESCE(k.Passe5, 0)) AS KantiSumme
    FROM kantiresultate k
    INNER JOIN mitglieder m ON m.ID = k.MitgliedID
    INNER JOIN Waffen w ON w.ID = m.WaffenID 
    WHERE w.Kategorie = ? 
      AND k.Jahr = ?
      AND (k.Passe1 > 0 OR k.Passe2 > 0 OR k.Passe3 > 0 OR k.Passe4 > 0 OR k.Passe5 > 0)
    ORDER BY KantiSumme DESC, m.Name, m.Vorname";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("si", $kategorie, $selectedYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Ergebnisse ausgeben
    if ($result->num_rows > 0) {
        $rang = 1;
        $previousTotal = null;
        $sameRankCount = 0;
        
        while ($row = $result->fetch_assoc()) {
            // Rangberechnung bei gleichen Totals
            if ($previousTotal !== null && $row['KantiSumme'] < $previousTotal) {
                $rang += $sameRankCount;
                $sameRankCount = 1;
            } elseif ($previousTotal === $row['KantiSumme']) {
                $sameRankCount++;
            } else {
                $sameRankCount = 1;
            }
            
            echo '<tr>';
            echo '<td>' . $rang . '.</td>';
            echo '<td>' . htmlspecialchars($row["Name"] . " " . $row["Vorname"]) . '</td>';
            echo '<td>' . ($row["Passe1"] ?: '-') . '</td>';
            echo '<td>' . ($row["Passe2"] ?: '-') . '</td>';
            echo '<td>' . ($row["Passe3"] ?: '-') . '</td>';
            echo '<td>' . ($row["Passe4"] ?: '-') . '</td>';
            echo '<td>' . ($row["Passe5"] ?: '-') . '</td>';
            echo '<td><strong>' . $row["KantiSumme"] . '</strong></td>';
            echo '</tr>';
            
            $previousTotal = $row['KantiSumme'];
        }
    } else {
        echo '<tr><td colspan="8" class="text-center">Keine Ergebnisse gefunden.</td></tr>';
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("Load Kantonal Error: " . $e->getMessage());
    echo '<tr><td colspan="8" class="text-center text-danger">Fehler beim Laden der Daten.</td></tr>';
}

// WICHTIG: Keine HTML-Tags, keine Script-Tags, nur die Tabellenzeilen!
?>