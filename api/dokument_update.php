<?php
// api/dokument_update.php - Dokument-Metadaten aktualisieren, optional neue Datei
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/lib/dokument_datei.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiges CSRF-Token']);
    exit;
}

function update_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$doc_id = (int)($_POST['id'] ?? 0);
if ($doc_id < 1) update_fail('Ungültige ID');

$db = getDB();
$stmt = $db->prepare("SELECT * FROM vorstand_dokumente WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();
if (!$doc) update_fail('Dokument nicht gefunden', 404);

// Nur eigene Uploads oder Admin darf bearbeiten
if ((int)$doc['hochgeladen_von'] !== (int)$_SESSION['user_id'] && ($_SESSION['user_role'] ?? '') !== 'admin') {
    update_fail('Keine Berechtigung', 403);
}

$titel        = trim($_POST['titel'] ?? '');
$beschreibung = trim($_POST['beschreibung'] ?? '');
if ($titel === '') update_fail('Titel ist erforderlich');

try {
    $datum = dokument_datum_pruefen($_POST['datum'] ?? '');
} catch (InvalidArgumentException $e) {
    update_fail($e->getMessage());
}
$jahr = $datum ? (int)substr($datum, 0, 4) : (int)($doc['jahr'] ?? date('Y'));

// Sichtbarkeit: JSK immer alle Mitglieder (Kommentar in der Verwaltung sagt das, der Endpoint
// akzeptierte trotzdem "Nur Vorstand" und sperrte damit die Jungschuetzen aus)
$sichtbar_fuer = $_POST['sichtbar_fuer'] ?? $doc['sichtbar_fuer'];
if ($doc['typ'] === 'jsk') {
    $sichtbar_fuer = 'alle_mitglieder';
} elseif (!in_array($sichtbar_fuer, ['admin', 'vorstand', 'alle_mitglieder'], true)) {
    update_fail('Ungültiger Sichtbarkeits-Wert');
}

// Neue Datei optional
$new = null; // ['name','path','size','ext']
if (isset($_FILES['datei']) && ($_FILES['datei']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $file  = $_FILES['datei'];
    $check = dokument_datei_pruefen($file);
    if (!$check['ok']) update_fail($check['message']);

    $upload_base = __DIR__ . '/../portal/uploads/dokumente/' . $doc['typ'] . '/';
    if (!is_dir($upload_base) && !mkdir($upload_base, 0755, true)) {
        update_fail('Upload-Verzeichnis konnte nicht angelegt werden', 500);
    }
    $filepath = $upload_base . dokument_zielname($file['name'], $check['ext']);
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        update_fail('Fehler beim Speichern der Datei', 500);
    }
    $new = ['name' => $file['name'], 'path' => $filepath, 'size' => $file['size'], 'ext' => $check['ext']];

    // Gleiche Regel wie beim Upload: DOCX/XLSX-Einsatzplaene sind "Nur Admin"
    if ($doc['typ'] === 'einsatzplan' && in_array($check['ext'], ['docx', 'xlsx', 'xls'], true)) {
        $sichtbar_fuer = 'admin';
    }
}

try {
    if ($new) {
        $upd = $db->prepare("UPDATE vorstand_dokumente
                             SET titel=?, beschreibung=?, datum=?, sichtbar_fuer=?, jahr=?, dateiname=?, dateipfad=?, dateigroesse=?
                             WHERE id=?");
        $upd->execute([$titel, $beschreibung !== '' ? $beschreibung : null, $datum, $sichtbar_fuer, $jahr,
                       $new['name'], $new['path'], $new['size'], $doc_id]);
        // Alte Datei erst nach erfolgreichem Update entfernen
        if (!empty($doc['dateipfad']) && $doc['dateipfad'] !== $new['path'] && file_exists($doc['dateipfad'])) {
            @unlink($doc['dateipfad']);
        }
    } else {
        $upd = $db->prepare("UPDATE vorstand_dokumente SET titel=?, beschreibung=?, datum=?, sichtbar_fuer=?, jahr=? WHERE id=?");
        $upd->execute([$titel, $beschreibung !== '' ? $beschreibung : null, $datum, $sichtbar_fuer, $jahr, $doc_id]);
    }
    echo json_encode(['success' => true, 'message' => 'Dokument aktualisiert', 'jahr' => $jahr, 'sichtbar_fuer' => $sichtbar_fuer]);
} catch (Throwable $e) {
    if ($new) @unlink($new['path']); // neue Datei nicht als Leiche liegen lassen
    error_log('[dokument_update] ' . $e->getMessage());
    update_fail('Dokument konnte nicht aktualisiert werden', 500);
}
