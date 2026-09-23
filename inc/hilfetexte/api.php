<?php
/**
 * inc/hilfetexte/api.php – Hilfetext-Endpunkt (Hilfesystem, Migration 066).
 *
 * GET  ?action=lookup&key=…   → {success, item:{titel, inhalt_html}}   admin + vorstand
 * GET  (ohne action)          → {success, items:[…]}                    admin + vorstand
 * GET  ?action=scan           → {success, used:{key:[dateien]}}         nur admin
 * POST action=add|update|delete (form-encoded via msvPost, CSRF)        nur admin
 *
 * Lesen dürfen alle Admin-Rollen, damit die «?»-Buttons auf jeder Seite funktionieren.
 * Schreiben ist Admin-Sache (Benutzerentscheid 22.09.2026).
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/html_sanitizer.inc.php';

header('Content-Type: application/json; charset=utf-8');

function hilfeJson(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function hilfeIstAdmin(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function hilfeKeyOk(string $key): bool
{
    return $key !== '' && strlen($key) <= 100 && preg_match('/^[a-z0-9._\-]+$/i', $key) === 1;
}

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'POST' ? (string)($_POST['action'] ?? '') : (string)($_GET['action'] ?? '');

// ---------------------------------------------------------------------------
// Lookup: einzelner Eintrag für die Anzeige.
// ---------------------------------------------------------------------------
if ($method === 'GET' && $action === 'lookup') {
    $key = trim((string)($_GET['key'] ?? ''));
    if (!hilfeKeyOk($key)) {
        hilfeJson(['success' => false, 'message' => 'Ungültiger Schlüssel'], 400);
    }
    $stmt = $db->prepare("SELECT titel, inhalt_html FROM hilfetexte
                          WHERE schluessel = :k AND ist_geloescht = 0 LIMIT 1");
    $stmt->execute([':k' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        hilfeJson(['success' => false, 'message' => 'Zu diesem Punkt gibt es noch keinen Hilfetext'], 404);
    }
    hilfeJson(['success' => true, 'item' => $row]);
}

function hilfeAlle(PDO $db): array
{
    return $db->query("SELECT id, schluessel, titel, inhalt_html, kategorie, erstellt_am, geaendert_am
                       FROM hilfetexte WHERE ist_geloescht = 0
                       ORDER BY kategorie IS NULL, kategorie, schluessel")
              ->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------------------------
// Liste: für die Editor-Seite (Vorstand sieht sie lesend).
// ---------------------------------------------------------------------------
if ($method === 'GET' && $action === '') {
    hilfeJson(['success' => true, 'items' => hilfeAlle($db), 'kann_bearbeiten' => hilfeIstAdmin()]);
}

// Ab hier nur Admin.
if (!hilfeIstAdmin()) {
    hilfeJson(['success' => false, 'message' => 'Hilfetexte bearbeiten dürfen nur Administratoren'], 403);
}

// ---------------------------------------------------------------------------
// Code-Scan: sucht data-help="…" in inc/ und admin/ (Kommentare gestrippt),
// damit der Editor fehlende und verwaiste Schlüssel anzeigen kann.
// ---------------------------------------------------------------------------
if ($method === 'GET' && $action === 'scan') {
    $projectRoot = realpath(__DIR__ . '/../..');
    $roots       = [$projectRoot . '/inc', $projectRoot . '/admin'];
    $allowedExt  = ['php', 'html', 'htm', 'inc', 'js'];
    $found       = [];

    foreach ($roots as $root) {
        if (!is_dir($root)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            if (!in_array(strtolower($file->getExtension()), $allowedExt, true)) continue;
            $path = str_replace('\\', '/', $file->getPathname());
            if (preg_match('#/(vendor|lib|dat|node_modules)/#i', $path)) continue;
            if (preg_match('#\.(min|bak)\.#i', $path)) continue;

            $content = @file_get_contents($file->getPathname());
            if ($content === false) continue;

            $stripped = preg_replace('#/\*.*?\*/#s', '', $content);
            $stripped = preg_replace('#<!--.*?-->#s', '', $stripped);
            $stripped = preg_replace('#^\s*//[^\n]*#m', '', $stripped);
            $stripped = preg_replace('#^\s*\#[^\n]*#m', '', $stripped);

            if (preg_match_all('/data-help\s*=\s*["\']([a-z0-9._\-]+)["\']/i', $stripped, $m)) {
                $rel = ltrim(str_replace(str_replace('\\', '/', $projectRoot), '', $path), '/');
                foreach ($m[1] as $key) {
                    $found[$key] ??= [];
                    if (!in_array($rel, $found[$key], true)) $found[$key][] = $rel;
                }
            }
        }
    }
    ksort($found);
    hilfeJson(['success' => true, 'used' => $found]);
}

if ($method !== 'POST') {
    hilfeJson(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
}
csrf_require(true);

$userId = (int)($_SESSION['user_id'] ?? 0) ?: null;

function hilfeValidiere(PDO $db, ?int $excludeId = null): array
{
    $schluessel = trim((string)($_POST['schluessel'] ?? ''));
    $titel      = trim((string)($_POST['titel'] ?? ''));
    $kategorie  = trim((string)($_POST['kategorie'] ?? ''));
    $inhaltRaw  = (string)($_POST['inhalt_html'] ?? '');

    if (!hilfeKeyOk($schluessel)) {
        hilfeJson(['success' => false, 'message' => 'Schlüssel ist Pflicht (max. 100 Zeichen, nur Buchstaben, Zahlen, Punkt, Unterstrich, Bindestrich)'], 400);
    }
    if ($titel === '' || mb_strlen($titel) > 150) {
        hilfeJson(['success' => false, 'message' => 'Titel ist Pflicht (max. 150 Zeichen)'], 400);
    }
    if (mb_strlen($kategorie) > 50) {
        hilfeJson(['success' => false, 'message' => 'Kategorie darf höchstens 50 Zeichen lang sein'], 400);
    }
    $inhalt = hilfeSanitizeHtml($inhaltRaw);
    if (trim(strip_tags($inhalt)) === '') {
        hilfeJson(['success' => false, 'message' => 'Inhalt ist Pflicht'], 400);
    }

    // Eindeutigkeit mit sauberer Meldung statt SQL-Fehler (auch gegen soft-gelöschte Einträge).
    $sql    = "SELECT id, ist_geloescht FROM hilfetexte WHERE schluessel = :k" . ($excludeId ? " AND id <> :id" : "");
    $params = [':k' => $schluessel];
    if ($excludeId) $params[':id'] = $excludeId;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    if ($dup = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $msg = (int)$dup['ist_geloescht'] === 1
            ? 'Schlüssel existiert als gelöschter Eintrag – bitte anderen Schlüssel wählen'
            : 'Schlüssel existiert bereits';
        hilfeJson(['success' => false, 'message' => $msg], 409);
    }

    return [
        'schluessel'  => $schluessel,
        'titel'       => $titel,
        'kategorie'   => $kategorie !== '' ? $kategorie : null,
        'inhalt_html' => $inhalt,
    ];
}

switch ($action) {
    case 'add': {
        $d    = hilfeValidiere($db);
        $stmt = $db->prepare("INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie, erstellt_von, geaendert_von)
                              VALUES (:s, :t, :i, :k, :ev, :gv)");
        $stmt->execute([':s' => $d['schluessel'], ':t' => $d['titel'], ':i' => $d['inhalt_html'],
                        ':k' => $d['kategorie'], ':ev' => $userId, ':gv' => $userId]);
        hilfeJson(['success' => true, 'message' => 'Hilfetext angelegt', 'items' => hilfeAlle($db)]);
    }

    case 'update': {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) hilfeJson(['success' => false, 'message' => 'Ungültige ID'], 400);
        $d    = hilfeValidiere($db, $id);
        $stmt = $db->prepare("UPDATE hilfetexte
                              SET schluessel = :s, titel = :t, inhalt_html = :i, kategorie = :k, geaendert_von = :gv
                              WHERE id = :id AND ist_geloescht = 0");
        $stmt->execute([':s' => $d['schluessel'], ':t' => $d['titel'], ':i' => $d['inhalt_html'],
                        ':k' => $d['kategorie'], ':gv' => $userId, ':id' => $id]);
        if ($stmt->rowCount() === 0) {
            // rowCount 0 kann auch «keine Änderung» heissen → Existenz prüfen.
            $chk = $db->prepare("SELECT 1 FROM hilfetexte WHERE id = :id AND ist_geloescht = 0");
            $chk->execute([':id' => $id]);
            if (!$chk->fetchColumn()) hilfeJson(['success' => false, 'message' => 'Eintrag nicht gefunden'], 404);
        }
        hilfeJson(['success' => true, 'message' => 'Hilfetext gespeichert', 'items' => hilfeAlle($db)]);
    }

    case 'delete': {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) hilfeJson(['success' => false, 'message' => 'Ungültige ID'], 400);
        $stmt = $db->prepare("UPDATE hilfetexte SET ist_geloescht = 1, geaendert_von = :gv
                              WHERE id = :id AND ist_geloescht = 0");
        $stmt->execute([':gv' => $userId, ':id' => $id]);
        if ($stmt->rowCount() === 0) hilfeJson(['success' => false, 'message' => 'Eintrag nicht gefunden'], 404);
        hilfeJson(['success' => true, 'message' => 'Hilfetext gelöscht', 'items' => hilfeAlle($db)]);
    }
}

hilfeJson(['success' => false, 'message' => 'Unbekannte Aktion'], 400);
