<?php
// save_single_mitglied.php - Einzelnes Mitglied speichern (beim Panel-Schliessen)
include 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

try {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('Keine gültige ID');

    $stmt = $conn->prepare("UPDATE mitglieder SET
        Anrede = ?, vorname = ?, name = ?, Geburtsdatum = ?, waffenid = ?,
        Strasse = ?, PLZ = ?, Ort = ?, Email = ?, Telefon = ?, Mobile = ?, Notizen = ?,
        status = ?, Ehrenmitglied = ?, Verstorben = ?, Vereinsaufnahme = ?, Kommunikation = ?,
        ist_jsk_leiter = ?
        WHERE id = ?");

    $anrede        = $_POST['anrede'] ?? '';
    $vorname       = $_POST['vorname'] ?? '';
    $name          = $_POST['name'] ?? '';
    $geburtsdatum  = $_POST['geburtsdatum'] ?? '';
    $waffenid      = intval($_POST['waffenid'] ?? 0);
    $strasse       = $_POST['strasse'] ?? '';
    $plz           = $_POST['plz'] ?? '';
    $ort           = $_POST['ort'] ?? '';
    $email         = $_POST['email'] ?? '';
    $telefon       = $_POST['telefon'] ?? '';
    $mobile        = $_POST['mobile'] ?? '';
    $notizen       = $_POST['notizen'] ?? '';
    $status        = intval($_POST['status'] ?? 0);
    $ehrenmitglied = intval($_POST['ehrenmitglied'] ?? 0);
    $verstorben    = intval($_POST['verstorben'] ?? 0);
    $vereinsaufnahme = !empty($_POST['vereinsaufnahme']) ? intval($_POST['vereinsaufnahme']) : null;
    $kommunikation = $_POST['kommunikation'] ?? '';
    $ist_jsk_leiter = intval($_POST['ist_jsk_leiter'] ?? 0);

    // Telefon-Format prüfen (+41 XX XXX XX XX)
    $phoneRegex = '/^\+41 \d{2} \d{3} \d{2} \d{2}$/';
    if ($telefon !== '' && !preg_match($phoneRegex, $telefon))
        throw new Exception('Telefon: Bitte Format +41 79 123 45 67 verwenden.');
    if ($mobile !== '' && !preg_match($phoneRegex, $mobile))
        throw new Exception('Mobile: Bitte Format +41 79 123 45 67 verwenden.');

    // Leere Strings als NULL speichern für ENUM-Felder
    if ($anrede === '') $anrede = null;
    if ($kommunikation === '') $kommunikation = null;

    $stmt->bind_param("ssssisssssssiiiisii",
        $anrede, $vorname, $name, $geburtsdatum, $waffenid,
        $strasse, $plz, $ort, $email, $telefon, $mobile, $notizen,
        $status, $ehrenmitglied, $verstorben, $vereinsaufnahme, $kommunikation,
        $ist_jsk_leiter,
        $id
    );

    if (!$stmt->execute()) {
        throw new Exception('Datenbankfehler: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'message' => 'Mitglied gespeichert']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
