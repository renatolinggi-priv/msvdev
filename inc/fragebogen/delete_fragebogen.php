<?php
include '../config.php';

// CSRF-Schutz
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die('Ungültige Anfrage');
}

// Jahr ermitteln
$year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');

// Prüfen, ob die Verbindung funktioniert
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Transaktion starten
$conn->begin_transaction();

try {
    // Zuerst alle Einträge aus mitglieder_fragebogen_erweitert löschen, 
    // deren zugehöriger Fragebogeneintrag im Jahr $year liegt.
    $sqlDeleteExtended = "
        DELETE fe
        FROM mitglieder_fragebogen_erweitert fe
        JOIN mitglieder_fragebogen fb ON fe.fragebogenID = fb.ID
        WHERE fb.jahr = $year
    ";
    $conn->query($sqlDeleteExtended);

    // Anschließend alle Einträge aus mitglieder_fragebogen löschen, die zum Jahr $year gehören.
    $sqlDeleteMain = "DELETE FROM mitglieder_fragebogen WHERE jahr = $year";
    $conn->query($sqlDeleteMain);

    $conn->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => "Einträge für Jahr $year wurden gelöscht."
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'status'  => 'error',
        'message' => "Fehler beim Löschen: " . $e->getMessage()
    ]);
}

$conn->close();
?>
