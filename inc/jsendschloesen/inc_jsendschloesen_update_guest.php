<?php
/**
 * update_guest.php
 * Aktualisiert die Daten eines Jungschützen
 */

session_start();
header('Content-Type: application/json');

// CSRF-Schutz
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF Token ungültig']);
    exit;
}

try {
    include '../dbconnect.inc.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// Eingaben validieren
$id = intval($_POST['id'] ?? 0);
$vorname = trim($_POST['vorname'] ?? '');
$nachname = trim($_POST['nachname'] ?? '');
$jahrgang = intval($_POST['jahrgang'] ?? 0);
$verein = trim($_POST['verein'] ?? '');
$lizenz_nr = trim($_POST['lizenz_nr'] ?? '');
$paket_geloest = intval($_POST['paket_geloest'] ?? 0);
$munition_gp11 = intval($_POST['munition_gp11'] ?? 0);
$munition_gp90 = intval($_POST['munition_gp90'] ?? 0);
$bemerkung = trim($_POST['bemerkung'] ?? '');

// Validierung
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ungültige ID']);
    exit;
}

if (empty($vorname) || empty($nachname)) {
    echo json_encode(['success' => false, 'error' => 'Vor- und Nachname sind erforderlich']);
    exit;
}

try {
    // Gesamtpreis berechnen (Paket + Munition)
    $total_preis = 75.00; // Festes Paket
    $total_preis += ($munition_gp11 + $munition_gp90) * 1.65; // Munitionspreis
    
    $sql = "UPDATE jsendschloesen_gaeste 
            SET vorname = ?, nachname = ?, jahrgang = ?, verein = ?, 
                lizenz_nr = ?, paket_geloest = ?, munition_gp11 = ?, 
                munition_gp90 = ?, total_preis = ?, bemerkung = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssissiiisii", 
        $vorname, $nachname, $jahrgang, $verein, $lizenz_nr,
        $paket_geloest, $munition_gp11, $munition_gp90, $total_preis, $bemerkung, $id
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Daten erfolgreich aktualisiert'
        ]);
    } else {
        throw new Exception('Fehler beim Aktualisieren');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>
