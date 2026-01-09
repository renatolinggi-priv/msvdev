<?php
// sort_by_date.php - Sortiert JMDefinition nach erstem Datum in Schiesstage
include '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage']);
    exit;
}

$year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

/**
 * Extrahiert das erste Datum aus einem Schiesstage-Text
 * Unterstützt Formate wie:
 * - "Freitag 14. März 2025 14:00"
 * - "Freitag 25. September 17:00" (ohne Jahr - nimmt $defaultYear)
 * - "14. März 2025"
 * - "14. März" (ohne Jahr)
 * - "14.03.2025"
 * - "2025-03-14"
 */
function extractFirstDate($text, $defaultYear) {
    if (empty($text)) {
        return null;
    }
    
    // Deutsche Monatsnamen zu Zahlen
    $monate = [
        'januar' => 1, 'februar' => 2, 'märz' => 3, 'maerz' => 3,
        'april' => 4, 'mai' => 5, 'juni' => 6,
        'juli' => 7, 'august' => 8, 'september' => 9,
        'oktober' => 10, 'november' => 11, 'dezember' => 12
    ];
    
    $textLower = mb_strtolower($text, 'UTF-8');
    
    // Pattern 1: "14. März 2025" (mit Jahr)
    if (preg_match('/(\d{1,2})\.?\s*(januar|februar|märz|maerz|april|mai|juni|juli|august|september|oktober|november|dezember)\s+(\d{4})/i', $text, $matches)) {
        $tag = intval($matches[1]);
        $monat = $monate[mb_strtolower($matches[2], 'UTF-8')] ?? 1;
        $jahr = intval($matches[3]);
        return sprintf('%04d-%02d-%02d', $jahr, $monat, $tag);
    }
    
    // Pattern 2: "14. März" oder "25. September" (ohne Jahr - verwende defaultYear)
    if (preg_match('/(\d{1,2})\.?\s*(januar|februar|märz|maerz|april|mai|juni|juli|august|september|oktober|november|dezember)(?!\s*\d{4})/i', $text, $matches)) {
        $tag = intval($matches[1]);
        $monat = $monate[mb_strtolower($matches[2], 'UTF-8')] ?? 1;
        return sprintf('%04d-%02d-%02d', $defaultYear, $monat, $tag);
    }
    
    // Pattern 3: "14.03.2025" oder "14.3.2025"
    if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $text, $matches)) {
        $tag = intval($matches[1]);
        $monat = intval($matches[2]);
        $jahr = intval($matches[3]);
        return sprintf('%04d-%02d-%02d', $jahr, $monat, $tag);
    }
    
    // Pattern 4: "2025-03-14" (ISO)
    if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $text, $matches)) {
        return sprintf('%04d-%02d-%02d', intval($matches[1]), intval($matches[2]), intval($matches[3]));
    }
    
    // Kein Datum gefunden
    return null;
}

try {
    $conn->begin_transaction();
    
    // Alle Einträge für das Jahr laden
    $stmt = $conn->prepare("SELECT ID, Schiesstage FROM JMDefinition WHERE year = ? AND hidden = 0");
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $datum = extractFirstDate($row['Schiesstage'], $year);
        $entries[] = [
            'id' => $row['ID'],
            'datum' => $datum,
            'schiesstage' => $row['Schiesstage']
        ];
    }
    $stmt->close();
    
    // Sortieren: Einträge mit Datum zuerst (aufsteigend), dann Einträge ohne Datum
    usort($entries, function($a, $b) {
        // Beide ohne Datum -> Reihenfolge beibehalten
        if ($a['datum'] === null && $b['datum'] === null) {
            return 0;
        }
        // Nur a ohne Datum -> ans Ende
        if ($a['datum'] === null) {
            return 1;
        }
        // Nur b ohne Datum -> ans Ende
        if ($b['datum'] === null) {
            return -1;
        }
        // Beide mit Datum -> nach Datum sortieren (aufsteigend)
        return strcmp($a['datum'], $b['datum']);
    });
    
    // Reihenfolge aktualisieren
    $stmtUpdate = $conn->prepare("UPDATE JMDefinition SET Reihenfolge = ? WHERE ID = ?");
    $reihenfolge = 1;
    
    foreach ($entries as $entry) {
        $stmtUpdate->bind_param("ii", $reihenfolge, $entry['id']);
        $stmtUpdate->execute();
        $reihenfolge++;
    }
    $stmtUpdate->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Einträge nach Datum sortiert',
        'count' => count($entries)
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}

$conn->close();
