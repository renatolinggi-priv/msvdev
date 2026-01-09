<?php
// save_standbelegung.php - Speichert Standbelegung in die Datenbank (mit Upsert)
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['termine']) || !is_array($input['termine'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Daten empfangen']);
    exit;
}

$year = isset($input['year']) ? intval($input['year']) : date('Y');
$termine = $input['termine'];

try {
    $conn->begin_transaction();
    
    // Prepared Statement für Upsert (INSERT ... ON DUPLICATE KEY UPDATE)
    // UNIQUE KEY ist auf (Datum, Bezeichnung, StartZeit, Jahr)
    $stmt = $conn->prepare("
        INSERT INTO Standbelegung (Datum, Wochentag, Bezeichnung, StartZeit, EndZeit, Kategorie, InKalender, Jahr)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            Wochentag = VALUES(Wochentag),
            EndZeit = VALUES(EndZeit),
            Kategorie = VALUES(Kategorie),
            InKalender = VALUES(InKalender)
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $inserted = 0;
    $updated = 0;
    
    foreach ($termine as $termin) {
        // Datum konvertieren von dd.mm.yyyy zu yyyy-mm-dd
        $datumRaw = $termin['datum'] ?? '';
        if (strpos($datumRaw, '.') !== false) {
            $datumParts = explode('.', $datumRaw);
            if (count($datumParts) === 3) {
                $datumDb = sprintf('%04d-%02d-%02d', $datumParts[2], $datumParts[1], $datumParts[0]);
            } else {
                continue; // Ungültiges Datum überspringen
            }
        } else {
            $datumDb = $datumRaw; // Bereits im DB-Format
        }
        
        $wochentag = $termin['wochentag'] ?? '';
        $bezeichnung = $termin['bezeichnung'] ?? '';
        $startZeit = $termin['start_zeit'] ?? null;
        $endZeit = $termin['end_zeit'] ?? null;
        $kategorie = $termin['kategorie'] ?? 'Sonstiges';
        $inKalender = isset($termin['in_kalender']) ? intval($termin['in_kalender']) : 0;
        
        $stmt->bind_param("ssssssii", 
            $datumDb, 
            $wochentag, 
            $bezeichnung, 
            $startZeit, 
            $endZeit, 
            $kategorie, 
            $inKalender,
            $year
        );
        
        if ($stmt->execute()) {
            // affected_rows: 1 = inserted, 2 = updated, 0 = unchanged
            if ($stmt->affected_rows === 1) {
                $inserted++;
            } elseif ($stmt->affected_rows === 2) {
                $updated++;
            }
        }
    }
    
    $stmt->close();
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'inserted' => $inserted,
        'updated' => $updated,
        'message' => "$inserted neu, $updated aktualisiert"
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
