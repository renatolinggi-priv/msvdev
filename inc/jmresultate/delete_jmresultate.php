<?php
include '../config.php';

// 1) GET-Parameter: Jahr
$selectedYear = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');

// Verbindung prüfen
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Transaktion starten
$conn->begin_transaction();

try {
    // Nur Datensätze löschen, deren jmdefinitionID in JMDefinition zum $selectedYear gehört
    $sqlDelete = "
        DELETE r
        FROM jmresultate r
        JOIN JMDefinition d ON d.ID = r.jmdefinitionID
        WHERE d.year = $selectedYear
    ";
    $conn->query($sqlDelete);

    // Transaktion erfolgreich abschließen
    $conn->commit();

    // JSON-Antwort (Beispiel)
    echo json_encode([
        'status' => 'success',
        'message' => "Daten für Jahr $selectedYear wurden gelöscht."
    ]);

} catch (Exception $e) {
    // Bei einem Fehler alles rückgängig machen
    $conn->rollback();
    echo "Fehler beim Löschen: " . $e->getMessage();
}

// Verbindung schließen
$conn->close();
