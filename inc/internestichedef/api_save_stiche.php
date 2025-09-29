<?php
// internestichedef/api_save_stiche.php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../config.php'; // liefert $conn (mysqli) – wie in save_ranking.php

// (Optionales) Debug nur in DEV aktivieren
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

try {
    // Nur POST akzeptieren
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    // JSON-Body lesen (Dein JS sendet application/json)
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrf    = $payload['csrf_token'] ?? '';
    $rows    = $payload['rows'] ?? [];

    // CSRF prüfen
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges CSRF-Token.']);
        exit;
    }

    // DB-Verbindung prüfen
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
        exit;
    }

    // Erlaubte Stich-Namen (anpassen, falls nötig)
    $validStiche = ['Heimmeisterschaft','Kantonalstich','Endstich','Schwini','Kunst','Glück','Zabig'];

    // Upsert-Statement vorbereiten
    $sql = "
        INSERT INTO interne_stichdefinition (stich, nummer1, nummer2, nummer3)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            nummer1 = VALUES(nummer1),
            nummer2 = VALUES(nummer2),
            nummer3 = VALUES(nummer3),
            updated_at = CURRENT_TIMESTAMP
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB-Fehler (prepare): ' . $conn->error]);
        exit;
    }

    $affected = 0;

    foreach ($rows as $stich => $vals) {
        if (!in_array($stich, $validStiche, true)) continue;

        $n1 = trim((string)($vals['nummer1'] ?? ''));
        $n2 = trim((string)($vals['nummer2'] ?? ''));
        $n3 = trim((string)($vals['nummer3'] ?? ''));

        // alle als Strings binden
        if (!$stmt->bind_param('ssss', $stich, $n1, $n2, $n3)) {
            echo json_encode(['success' => false, 'message' => 'DB-Fehler (bind): ' . $stmt->error]);
            $stmt->close();
            exit;
        }
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'DB-Fehler (execute): ' . $stmt->error]);
            $stmt->close();
            exit;
        }
        $affected += $stmt->affected_rows;
    }

    $stmt->close();

    echo json_encode(['success' => true, 'affected' => $affected], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    // Immer JSON, nie HTML
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern: ' . $e->getMessage()]);
    exit;
}
