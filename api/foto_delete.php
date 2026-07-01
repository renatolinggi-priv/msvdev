<?php
// api/foto_delete.php - Foto loeschen. Erlaubt fuer den Uploader oder Vorstand/Admin.
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand', 'mitglied']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_error('Methode nicht erlaubt', 405);
if (!validateCsrfRequest()) json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$fotoId = (int) ($_POST['id'] ?? 0);
if ($fotoId < 1) json_error('Ungültige ID.');

$stmt = $db->prepare("SELECT * FROM anlass_fotos WHERE id = ?");
$stmt->execute([$fotoId]);
$foto = $stmt->fetch();
if (!$foto) json_error('Foto nicht gefunden.', 404);

if (!isVorstand() && (int) $foto['hochgeladen_von'] !== $userId) {
    json_error('Keine Berechtigung, dieses Foto zu löschen.', 403);
}

fotoUnlinkDateien($foto);
$db->prepare("DELETE FROM anlass_fotos WHERE id = ?")->execute([$fotoId]);

echo json_encode(['success' => true, 'message' => 'Foto gelöscht.']);
