<?php
// manage_keywords.php - Verwaltet Art-Keywords
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Aktion angegeben']);
    exit;
}

$action = $input['action'];

try {
    switch ($action) {
        case 'add':
            if (empty($input['keyword']) || empty($input['art'])) {
                throw new Exception('Keyword und Art müssen angegeben werden');
            }
            
            $keyword = trim($input['keyword']);
            $art = trim($input['art']);
            
            // Prüfen ob bereits vorhanden
            $checkStmt = $conn->prepare("SELECT ID FROM Standbelegung_ArtKeywords WHERE Keyword = ?");
            $checkStmt->bind_param("s", $keyword);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                throw new Exception('Keyword existiert bereits');
            }
            $checkStmt->close();
            
            // Einfügen
            $stmt = $conn->prepare("INSERT INTO Standbelegung_ArtKeywords (Keyword, Art) VALUES (?, ?)");
            $stmt->bind_param("ss", $keyword, $art);
            
            if (!$stmt->execute()) {
                throw new Exception('Fehler beim Einfügen: ' . $stmt->error);
            }
            
            $newId = $stmt->insert_id;
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'id' => $newId,
                'message' => 'Keyword hinzugefügt'
            ]);
            break;
            
        case 'delete':
            if (empty($input['id'])) {
                throw new Exception('ID muss angegeben werden');
            }
            
            $id = intval($input['id']);
            
            $stmt = $conn->prepare("DELETE FROM Standbelegung_ArtKeywords WHERE ID = ?");
            $stmt->bind_param("i", $id);
            
            if (!$stmt->execute()) {
                throw new Exception('Fehler beim Löschen: ' . $stmt->error);
            }
            
            $deleted = $stmt->affected_rows;
            $stmt->close();
            
            if ($deleted === 0) {
                throw new Exception('Keyword nicht gefunden');
            }
            
            echo json_encode([
                'success' => true,
                'deleted' => $deleted,
                'message' => 'Keyword gelöscht'
            ]);
            break;
            
        case 'list':
            $stmt = $conn->prepare("SELECT ID, Keyword, Art FROM Standbelegung_ArtKeywords ORDER BY Art, Keyword");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $keywords = [];
            while ($row = $result->fetch_assoc()) {
                $keywords[] = $row;
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'keywords' => $keywords
            ]);
            break;
            
        default:
            throw new Exception('Unbekannte Aktion: ' . $action);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
