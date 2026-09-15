<?php
// api/anlass_galerie_admin.php - Verwaltung der Foto-Galerien (Vorstand/Admin).
// actions: enable | update | set_cover | delete
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_error('Methode nicht erlaubt', 405);
if (!validateCsrf($_POST['csrf_token'] ?? '')) json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

switch ($action) {

    case 'enable': {
        $jmId = (int) ($_POST['jmdefinition_id'] ?? 0);
        if ($jmId < 1) json_error('Ungültiger Anlass.');
        $chk = $db->prepare("SELECT Bezeichnung FROM JMDefinition WHERE ID = ?");
        $chk->execute([$jmId]);
        $anlassName = $chk->fetchColumn();
        if ($anlassName === false) json_error('Anlass nicht gefunden.', 404);

        $ins = $db->prepare("INSERT IGNORE INTO anlass_galerie (jmdefinition_id, erstellt_von) VALUES (?, ?)");
        $ins->execute([$jmId, $userId]);
        $wasNew = $ins->rowCount() > 0;

        $idStmt = $db->prepare("SELECT id FROM anlass_galerie WHERE jmdefinition_id = ?");
        $idStmt->execute([$jmId]);
        $gid = (int) $idStmt->fetchColumn();

        echo json_encode(['success' => true, 'galerie_id' => $gid, 'message' => 'Galerie freigeschaltet.']);

        // Einmalige Push-Info an Mitglieder (nur bei Erst-Freischaltung). Best effort,
        // laeuft NACH der Antwort und darf das Freischalten nie brechen.
        if ($wasNew) {
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            @session_write_close();
            @include_once __DIR__ . '/../inc/push_helper.php';
            if (function_exists('benachrichtigungZustellen')) {
                try {
                    // In-App-Eintrag fuer alle mit aktivem fotos-Thema (push_aktiv steuert nur den
                    // Push, NICHT die Glocke -> hier bewusst kein push_aktiv-Filter).
                    $empf = $db->query(
                        "SELECT u.id FROM users u
                           LEFT JOIN benachrichtigung_prefs p ON p.user_id = u.id
                          WHERE u.status = 'approved' AND u.role <> 'jungschuetze'
                            AND COALESCE(p.fotos, 1) = 1"
                    )->fetchAll(PDO::FETCH_COLUMN);
                    $url = 'portal/anlass.php?id=' . $gid;
                    foreach ($empf as $uid) {
                        try {
                            benachrichtigungZustellen((int) $uid, 'Neue Foto-Galerie', $anlassName . ' – lade jetzt deine Fotos hoch!', $url, 'fotos');
                        } catch (\Throwable $e) {
                            error_log('foto galerie push (user ' . $uid . '): ' . $e->getMessage());
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('foto galerie push broadcast: ' . $e->getMessage());
                }
            }
        }
        break;
    }

    case 'update': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        if ($gid < 1) json_error('Ungültige Galerie.');
        $stmt = $db->prepare(
            "UPDATE anlass_galerie
                SET freigeschaltet = :fg, moderation_aktiv = :mod, upload_offen = :up, beschreibung = :be
              WHERE id = :id"
        );
        $stmt->execute([
            ':fg'  => !empty($_POST['freigeschaltet']) ? 1 : 0,
            ':mod' => !empty($_POST['moderation_aktiv']) ? 1 : 0,
            ':up'  => !empty($_POST['upload_offen']) ? 1 : 0,
            ':be'  => trim((string) ($_POST['beschreibung'] ?? '')) ?: null,
            ':id'  => $gid,
        ]);
        // rowCount 0 = Galerie existiert nicht (unveraenderte Werte melden MySQL ebenfalls 0 -> Existenz pruefen)
        if ($stmt->rowCount() === 0) {
            $chk = $db->prepare("SELECT id FROM anlass_galerie WHERE id = ?");
            $chk->execute([$gid]);
            if (!$chk->fetchColumn()) json_error('Galerie nicht gefunden.', 404);
        }
        echo json_encode(['success' => true, 'message' => 'Einstellungen gespeichert.']);
        break;
    }

    case 'set_cover': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        if ($gid < 1) json_error('Ungültige Galerie.');
        $fid = (int) ($_POST['foto_id'] ?? 0); // 0 = Vorschaubild entfernen
        if ($fid > 0) {
            $chk = $db->prepare("SELECT id FROM anlass_fotos WHERE id = ? AND galerie_id = ?");
            $chk->execute([$fid, $gid]);
            if (!$chk->fetchColumn()) json_error('Foto nicht gefunden.', 404);
        }
        $db->prepare("UPDATE anlass_galerie SET cover_foto_id = ? WHERE id = ?")
           ->execute([$fid > 0 ? $fid : null, $gid]);
        echo json_encode(['success' => true, 'cover_foto_id' => $fid > 0 ? $fid : null,
            'message' => $fid > 0 ? 'Vorschaubild gesetzt.' : 'Vorschaubild entfernt.']);
        break;
    }

    case 'delete': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        if ($gid < 1) json_error('Ungültige Galerie.');
        // Erst die DB-Zeilen (FK-CASCADE entfernt die Foto-Zeilen), dann die Dateien. Schlaegt der
        // DB-Delete fehl, bleiben Dateien und Zeilen konsistent erhalten (vorher: Dateien weg, Zeilen da).
        $del = $db->prepare("DELETE FROM anlass_galerie WHERE id = ?");
        $del->execute([$gid]);
        if ($del->rowCount() === 0) json_error('Galerie nicht gefunden.', 404);
        fotoLoescheGalerieDir($gid);
        echo json_encode(['success' => true, 'message' => 'Galerie und alle Fotos gelöscht.']);
        break;
    }

    default:
        json_error('Unbekannte Aktion.');
}
