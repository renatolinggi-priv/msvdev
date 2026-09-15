<?php
/**
 * inc/pdf_design/save.php – Speichern/Zurücksetzen der PDF-Vorlage und Logo-Upload.
 * Ausgelagert aus pdf_design.php (dort lief der POST vor header.inc.php mit eigenem Login-Code).
 * Nur Admin. Antwort immer JSON.
 *
 * POST action=save   + Schema-Felder      -> Werte validieren und in pdf_theme_settings schreiben
 * POST action=reset                         -> Tabelle leeren (Defaults gelten)
 * POST action=logo   + logo_data (Data-URL) -> Master-Logo + Modul-Kopien atomar ersetzen, Backup anlegen
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/../pdf/pdf_theme.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}

// Leeres $_POST bei ueberschrittenem post_max_size: sonst kaeme ein irrefuehrender CSRF-Fehler
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413);
    die(json_encode(['success' => false, 'message' => 'Anfrage zu gross (Server-Limit post_max_size = ' . ini_get('post_max_size') . '). Bitte ein kleineres Bild wählen.']));
}
csrf_require(true);

// Nur Admin (Vorstand darf den Admin-Bereich nutzen, aber nicht das PDF-Design ändern)
if (($_SESSION['user_role'] ?? '') !== 'admin' && (int)($_SESSION['user_id'] ?? 0) !== 1) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Nur Administratoren dürfen das PDF-Design ändern']));
}

function pd_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$action = $_POST['action'] ?? '';

// ---- Logo ersetzen (zugeschnittenes Bild aus dem Cropper) ----
if ($action === 'logo') {
    try {
        $dataUrl = (string)($_POST['logo_data'] ?? '');
        if (!preg_match('#^data:image/(png|jpe?g);base64,#', $dataUrl)) pd_fail('Ungültiges Bildformat');
        $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($bin === false || strlen($bin) < 50) pd_fail('Bild konnte nicht gelesen werden');
        if (strlen($bin) > 4 * 1024 * 1024) pd_fail('Bild zu gross (max. 4 MB)', 413);
        $info = @getimagesizefromstring($bin);
        if ($info === false || !in_array($info[2] ?? 0, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
            || $info[0] < 10 || $info[1] < 10 || $info[0] > 4000 || $info[1] > 4000) {
            pd_fail('Keine gültige PNG/JPEG-Datei');
        }

        // Auf JPEG mit weissem Hintergrund normalisieren (GD)
        $jpeg = $bin;
        if (function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring($bin);
            if ($src !== false) {
                $w = imagesx($src); $h = imagesy($src);
                $maxW = 1200;
                if ($w > $maxW) {
                    $nh = max(1, (int)round($h * $maxW / $w));
                    $rs = imagecreatetruecolor($maxW, $nh);
                    imagefilledrectangle($rs, 0, 0, $maxW, $nh, imagecolorallocate($rs, 255, 255, 255));
                    imagecopyresampled($rs, $src, 0, 0, 0, 0, $maxW, $nh, $w, $h);
                    imagedestroy($src);
                    $src = $rs; $w = $maxW; $h = $nh;
                }
                $canvas = imagecreatetruecolor($w, $h);
                imagefilledrectangle($canvas, 0, 0, $w, $h, imagecolorallocate($canvas, 255, 255, 255));
                imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
                ob_start();
                imagejpeg($canvas, null, 92);
                $out = ob_get_clean();
                if ($out !== false && $out !== '') $jpeg = $out;
                imagedestroy($src);
                imagedestroy($canvas);
            }
        }

        // Master + alle Modul-Kopien: Backup des Masters, dann atomar (tempnam + rename) schreiben
        $master  = pdf_logo_path();
        $root    = dirname(__DIR__, 2);
        $targets = array_merge([$master], glob($root . '/inc/*/dat/MSVWilen_Logo.jpg') ?: []);
        if (is_file($master)) {
            @copy($master, dirname($master) . '/MSVWilen_Logo.bak.jpg');
        }
        $ok = 0; $failed = [];
        foreach ($targets as $t) {
            $dir = dirname($t);
            $tmp = @tempnam($dir, 'logo_');
            if ($tmp === false || @file_put_contents($tmp, $jpeg) === false || !@rename($tmp, $t)) {
                if ($tmp !== false && is_file($tmp)) @unlink($tmp);
                $failed[] = str_replace($root, '', $t);
                error_log('[pdf_design] Logo konnte nicht geschrieben werden: ' . $t);
                continue;
            }
            @chmod($t, 0644);
            $ok++;
        }
        if ($ok === 0) pd_fail('Logo konnte nicht gespeichert werden (Schreibrechte?)', 500);

        echo json_encode([
            'success' => true,
            'message' => 'Logo aktualisiert (' . $ok . ' Dateien' . ($failed ? ', ' . count($failed) . ' übersprungen' : '') . ')',
            'count'   => $ok,
            'failed'  => $failed,
            'mtime'   => (int)@filemtime($master), // Cache-Busting im Client per filemtime statt Date.now()
        ]);
        exit;
    } catch (\Throwable $e) {
        error_log('[pdf_design] logo: ' . $e->getMessage());
        pd_fail('Fehler beim Logo-Upload', 500);
    }
}

// ---- Werte speichern / zurücksetzen ----
try {
    $pdo = getDB();
    if ($action === 'reset') {
        $pdo->exec('DELETE FROM pdf_theme_settings');
        echo json_encode(['success' => true, 'message' => 'Auf Standard zurückgesetzt', 'values' => pdf_theme_defaults()]);
        exit;
    }
    if ($action === 'save') {
        $clean = [];
        foreach (pdf_theme_schema() as $key => $def) {
            $clean[$key] = pdf_theme_sanitize_value($_POST[$key] ?? null, $def);
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO pdf_theme_settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
        foreach ($clean as $key => $val) {
            $stmt->execute([$key, (string)$val]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'PDF-Design gespeichert', 'values' => $clean]);
        exit;
    }
    pd_fail('Unbekannte Aktion');
} catch (\Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[pdf_design] save: ' . $e->getMessage());
    pd_fail('Fehler beim Speichern', 500);
}
