<?php
// api/foto_titel.php - Bildunterschrift eines Fotos setzen (Uploader selbst oder Vorstand/Admin).
// POST id, titel (leer = entfernen), csrf_token
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

$titel = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['titel'] ?? '')));
$titel = mb_substr($titel, 0, 120);

$stmt = $db->prepare("SELECT id, hochgeladen_von FROM anlass_fotos WHERE id = ?");
$stmt->execute([$fotoId]);
$foto = $stmt->fetch();
if (!$foto) json_error('Foto nicht gefunden.', 404);

if (!isVorstand() && (int) $foto['hochgeladen_von'] !== $userId) {
    json_error('Keine Berechtigung für dieses Foto.', 403);
}

$db->prepare("UPDATE anlass_fotos SET titel = ? WHERE id = ?")->execute([$titel !== '' ? $titel : null, $fotoId]);

echo json_encode([
    'success' => true,
    'titel'   => $titel !== '' ? $titel : null,
    'message' => $titel !== '' ? 'Bildunterschrift gespeichert.' : 'Bildunterschrift entfernt.',
]);
