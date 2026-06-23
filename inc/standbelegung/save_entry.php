<?php
// save_entry.php - Speichert oder aktualisiert einen einzelnen Standbelegung-Eintrag
require_once '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']));
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Keine Daten empfangen']);
    exit;
}

// Pflichtfelder prüfen
if (empty($input['datum']) || empty($input['bezeichnung'])) {
    echo json_encode(['success' => false, 'message' => 'Datum und Bezeichnung sind erforderlich']);
    exit;
}

$id = isset($input['id']) && $input['id'] ? intval($input['id']) : null;
$datum = $input['datum'];
$bezeichnung = trim($input['bezeichnung']);
$startZeit = !empty($input['start_zeit']) ? $input['start_zeit'] : null;
$endZeit = !empty($input['end_zeit']) ? $input['end_zeit'] : null;
$kategorie = $input['kategorie'] ?? 'Sonstiges';
$inKalender = isset($input['in_kalender']) ? intval($input['in_kalender']) : 0;

// Jahr aus Datum extrahieren
$jahr = date('Y', strtotime($datum));

// Wochentag berechnen
$wochentage = ['SO', 'MO', 'DI', 'MI', 'DO', 'FR', 'SA'];
$wochentag = $wochentage[date('w', strtotime($datum))];

try {
    if ($id) {
        // Update bestehender Eintrag
        $stmt = $conn->prepare("
            UPDATE Standbelegung 
            SET Datum = ?, Wochentag = ?, Bezeichnung = ?, StartZeit = ?, EndZeit = ?, Kategorie = ?, InKalender = ?, Jahr = ?
            WHERE ID = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssssiis", $datum, $wochentag, $bezeichnung, $startZeit, $endZeit, $kategorie, $inKalender, $jahr, $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();

        echo json_encode([
            'success' => true,
            'id' => $id,
            'wochentag' => $wochentag,
            'action' => 'updated',
            'message' => 'Eintrag aktualisiert'
        ]);
        
    } else {
        // Neuer Eintrag
        $stmt = $conn->prepare("
            INSERT INTO Standbelegung (Datum, Wochentag, Bezeichnung, StartZeit, EndZeit, Kategorie, InKalender, Jahr)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssssii", $datum, $wochentag, $bezeichnung, $startZeit, $endZeit, $kategorie, $inKalender, $jahr);
        
        if (!$stmt->execute()) {
            // Prüfe ob Duplikat
            if ($conn->errno === 1062) {
                throw new Exception("Ein Eintrag mit diesem Datum, Bezeichnung und Startzeit existiert bereits");
            }
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $newId = $stmt->insert_id;
        $stmt->close();

        echo json_encode([
            'success' => true,
            'id' => $newId,
            'wochentag' => $wochentag,
            'action' => 'inserted',
            'message' => 'Eintrag hinzugefügt'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
