<?php
/**
 * save_guest.php
 * Speichert einen neuen Jungschützen
 */

session_start();
header('Content-Type: application/json');

// CSRF-Schutz
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF Token ungültig']);
    exit;
}

try {
    include '../dbconnect.inc.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// Eingaben validieren
$vorname = trim($_POST['vorname'] ?? '');
$nachname = trim($_POST['nachname'] ?? '');
$jahrgang = intval($_POST['jahrgang'] ?? 0);
$verein = trim($_POST['verein'] ?? '');
$lizenz_nr = trim($_POST['lizenz_nr'] ?? '');
$jahr = intval($_POST['jahr'] ?? date('Y'));
$paket_geloest = isset($_POST['paket_geloest']) ? 1 : 0;
$munition_gp11 = intval($_POST['munition_gp11'] ?? 0);
$munition_gp90 = intval($_POST['munition_gp90'] ?? 0);
$bemerkung = trim($_POST['bemerkung'] ?? '');

// Validierung
if (empty($vorname) || empty($nachname)) {
    echo json_encode(['success' => false, 'message' => 'Vor- und Nachname sind erforderlich']);
    exit;
}

if ($jahrgang < 2000 || $jahrgang > 2015) {
    echo json_encode(['success' => false, 'message' => 'Ungültiger Jahrgang']);
    exit;
}

try {
    // Gesamtpreis berechnen (Paket + Munition)
    $total_preis = 75.00; // Festes Paket
    $total_preis += ($munition_gp11 + $munition_gp90) * 1.65; // Munitionspreis
    
    $sql = "INSERT INTO jsendschloesen_gaeste 
            (vorname, nachname, jahrgang, verein, lizenz_nr, jahr, 
             paket_geloest, munition_gp11, munition_gp90, total_preis, bemerkung) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisssiiiis", 
        $vorname, $nachname, $jahrgang, $verein, $lizenz_nr, $jahr,
        $paket_geloest, $munition_gp11, $munition_gp90, $total_preis, $bemerkung
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'id' => $conn->insert_id,
            'message' => 'Jungschütze erfolgreich erfasst'
        ]);
    } else {
        throw new Exception('Fehler beim Speichern');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>
