<?php
// api/foto_moderate.php - Moderation der Galerie-Fotos (Vorstand/Admin).
// actions: list | approve | reject | approve_all | rematch | reorder | move_day | set_titel | delete | delete_all
// Freigabe/Ablehnung informiert den Uploader per Inbox/Push (Thema fotos), gebuendelt pro Aktion.
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_error('Methode nicht erlaubt', 405);
if (!validateCsrf($_POST['csrf_token'] ?? '')) json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);

$db     = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$now    = date('Y-m-d H:i:s');
$action = $_POST['action'] ?? '';

/** IDs aus 'id' (einzeln) oder 'ids' (komma-separiert) lesen. */
function moderate_ids(): array {
    $ids = [];
    if (isset($_POST['ids'])) {
        foreach (explode(',', (string) $_POST['ids']) as $v) {
            $v = (int) trim($v);
            if ($v > 0) $ids[] = $v;
        }
    } elseif (isset($_POST['id'])) {
        $v = (int) $_POST['id'];
        if ($v > 0) $ids[] = $v;
    }
    return array_values(array_unique($ids));
}

/**
 * Uploader ueber Freigabe/Ablehnung informieren. $gruppen = Zeilen (hochgeladen_von, galerie_id, n).
 * Der Moderator selbst wird nicht benachrichtigt.
 */
function moderate_uploader_informieren(PDO $db, array $gruppen, bool $freigegeben, int $moderatorId): void {
    $labels = [];
    foreach ($gruppen as $r) {
        $uid = (int) ($r['hochgeladen_von'] ?? 0);
        $gid = (int) ($r['galerie_id'] ?? 0);
        $n   = (int) ($r['n'] ?? 0);
        if ($uid < 1 || $uid === $moderatorId || $n < 1) continue;
        if (!isset($labels[$gid])) {
            $g = fotoGalerieLaden($db, $gid);
            $labels[$gid] = $g ? fotoGalerieLabel($g) : 'Galerie';
        }
        $was = $n === 1 ? 'Dein Foto' : $n . ' deiner Fotos';
        if ($freigegeben) {
            $titel = $n === 1 ? 'Foto freigegeben' : 'Fotos freigegeben';
            $text  = $was . ' zu «' . $labels[$gid] . '» ' . ($n === 1 ? 'ist' : 'sind') . ' jetzt in der Galerie sichtbar.';
        } else {
            $titel = $n === 1 ? 'Foto nicht freigegeben' : 'Fotos nicht freigegeben';
            $text  = $was . ' zu «' . $labels[$gid] . '» ' . ($n === 1 ? 'wurde' : 'wurden') . ' vom Vorstand nicht freigegeben und ' . ($n === 1 ? 'ist' : 'sind') . ' nur für dich sichtbar.';
        }
        fotoMitteilung($uid, $titel, $text, 'portal/anlass.php?id=' . $gid);
    }
}

switch ($action) {

    case 'list': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        if ($gid < 1) json_error('Ungültige Galerie.');
        $g = fotoGalerieLaden($db, $gid);
        if (!$g) json_error('Galerie nicht gefunden.', 404);
        $segmente = fotoGalerieSegmente($g);

        $only = $_POST['status'] ?? ''; // optional Filter
        $sql = "SELECT f.id, f.status, f.titel, f.aufnahme_zeit, f.zeit_quelle, f.tag_index, f.tag_datum, f.tag_manuell,
                       f.hochgeladen_am, u.full_name AS uploader
                  FROM anlass_fotos f
                  LEFT JOIN users u ON u.id = f.hochgeladen_von
                 WHERE f.galerie_id = :gid";
        $params = [':gid' => $gid];
        if (in_array($only, ['pending', 'approved', 'rejected'], true)) {
            $sql .= " AND f.status = :st";
            $params[':st'] = $only;
        }
        // Gleiche Reihenfolge wie Galerie/Slideshow (Tag + manuelle Sortierung) -> Drag&Drop
        // im Admin spiegelt exakt, was die Mitglieder sehen.
        $sql .= " ORDER BY " . fotoOrderBySql('f.');
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $fotos = [];
        foreach ($stmt->fetchAll() as $r) {
            $fotos[] = [
                'id'            => (int) $r['id'],
                'status'        => $r['status'],
                'titel'         => $r['titel'],
                'uploader'      => $r['uploader'],
                'aufnahme_zeit' => $r['aufnahme_zeit'],
                'zeit_quelle'   => $r['zeit_quelle'],
                'tag_index'     => $r['tag_index'] !== null ? (int) $r['tag_index'] : null,
                'tag_datum'     => $r['tag_datum'],
                'tag_manuell'   => (int) ($r['tag_manuell'] ?? 0),
                'thumb_url'     => '../api/foto_serve.php?id=' . (int) $r['id'] . '&size=thumb',
                'medium_url'    => '../api/foto_serve.php?id=' . (int) $r['id'] . '&size=medium',
                'full_url'      => '../api/foto_serve.php?id=' . (int) $r['id'] . '&size=full',
            ];
        }
        echo json_encode(['success' => true, 'fotos' => $fotos, 'schiesstage' => $segmente,
            'cover_foto_id' => (isset($g['cover_foto_id']) && $g['cover_foto_id'] !== null) ? (int) $g['cover_foto_id'] : null]);
        break;
    }

    case 'approve':
    case 'reject': {
        $ids = moderate_ids();
        if (!$ids) json_error('Keine Fotos ausgewählt.');
        $new = $action === 'approve' ? 'approved' : 'rejected';
        $in  = implode(',', array_fill(0, count($ids), '?'));
        // Nur Fotos, deren Status sich wirklich aendert -> keine Mitteilung bei erneutem Klick
        $sel = $db->prepare(
            "SELECT hochgeladen_von, galerie_id, COUNT(*) AS n FROM anlass_fotos
              WHERE id IN ($in) AND status <> ? GROUP BY hochgeladen_von, galerie_id"
        );
        $sel->execute(array_merge($ids, [$new]));
        $betroffen = $sel->fetchAll();
        $stmt = $db->prepare(
            "UPDATE anlass_fotos SET status = ?, moderiert_von = ?, moderiert_am = ? WHERE id IN ($in)"
        );
        $stmt->execute(array_merge([$new, $userId, $now], $ids));
        echo json_encode(['success' => true, 'count' => count($ids),
            'message' => count($ids) . ($action === 'approve' ? ' Foto(s) freigegeben.' : ' Foto(s) abgelehnt.')]);
        moderate_uploader_informieren($db, $betroffen, $action === 'approve', $userId);
        break;
    }

    case 'approve_all': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        if ($gid < 1) json_error('Ungültige Galerie.');
        $sel = $db->prepare(
            "SELECT hochgeladen_von, galerie_id, COUNT(*) AS n FROM anlass_fotos
              WHERE galerie_id = ? AND status = 'pending' GROUP BY hochgeladen_von, galerie_id"
        );
        $sel->execute([$gid]);
        $betroffen = $sel->fetchAll();
        $stmt = $db->prepare(
            "UPDATE anlass_fotos SET status = 'approved', moderiert_von = ?, moderiert_am = ?
              WHERE galerie_id = ? AND status = 'pending'"
        );
        $stmt->execute([$userId, $now, $gid]);
        echo json_encode(['success' => true, 'count' => $stmt->rowCount(),
            'message' => $stmt->rowCount() . ' Foto(s) freigegeben.']);
        moderate_uploader_informieren($db, $betroffen, true, $userId);
        break;
    }

    case 'set_titel': {
        $fid = (int) ($_POST['id'] ?? 0);
        if ($fid < 1) json_error('Ungültige ID.');
        $titel = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($_POST['titel'] ?? ''))), 0, 120);
        $upd = $db->prepare("UPDATE anlass_fotos SET titel = ? WHERE id = ?");
        $upd->execute([$titel !== '' ? $titel : null, $fid]);
        echo json_encode(['success' => true, 'titel' => $titel !== '' ? $titel : null,
            'message' => $titel !== '' ? 'Bildunterschrift gespeichert.' : 'Bildunterschrift entfernt.']);
        break;
    }

    case 'rematch': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        if ($gid < 1) json_error('Ungültige Galerie.');
        $g = fotoGalerieLaden($db, $gid);
        if (!$g) json_error('Galerie nicht gefunden.', 404);
        $segmente = fotoGalerieSegmente($g);

        // Manuell verschobene Fotos (tag_manuell=1) NICHT ueberschreiben
        $sel = $db->prepare("SELECT id, aufnahme_zeit FROM anlass_fotos WHERE galerie_id = ? AND tag_manuell = 0");
        $sel->execute([$gid]);
        $upd = $db->prepare("UPDATE anlass_fotos SET tag_datum = ?, tag_index = ? WHERE id = ?");
        $n = 0;
        foreach ($sel->fetchAll() as $r) {
            $tag = fotoTagInfo($r['aufnahme_zeit'], $segmente);
            $upd->execute([$tag['tag_datum'], $tag['tag_index'], (int) $r['id']]);
            $n++;
        }
        echo json_encode(['success' => true, 'count' => $n, 'message' => $n . ' Foto(s) neu zugeordnet.']);
        break;
    }

    case 'reorder': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        if ($gid < 1) json_error('Ungültige Galerie.');
        $ids = [];
        foreach (explode(',', (string) ($_POST['ids'] ?? '')) as $v) {
            $v = (int) trim($v);
            if ($v > 0) $ids[] = $v;
        }
        if (!$ids) json_error('Keine Reihenfolge übergeben.');
        // sortierung = Position in der übergebenen Reihenfolge (galerie-gebunden gegen Manipulation).
        // In einer Transaktion, damit bei einem Abbruch keine halbe Reihenfolge stehen bleibt.
        $db->beginTransaction();
        try {
            $upd = $db->prepare("UPDATE anlass_fotos SET sortierung = ? WHERE id = ? AND galerie_id = ?");
            $pos = 1;
            foreach ($ids as $id) { $upd->execute([$pos, $id, $gid]); $pos++; }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[foto_moderate reorder] ' . $e->getMessage());
            json_error('Reihenfolge konnte nicht gespeichert werden.', 500);
        }
        echo json_encode(['success' => true, 'message' => 'Reihenfolge gespeichert.']);
        break;
    }

    case 'move_day': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        $fid = (int) ($_POST['foto_id'] ?? 0);
        if ($gid < 1 || $fid < 1) json_error('Ungültige Parameter.');
        $tiRaw = $_POST['tag_index'] ?? '';
        $ti = ($tiRaw === '' || $tiRaw === null) ? null : (int) $tiRaw;
        $td = trim((string) ($_POST['tag_datum'] ?? ''));
        if ($td === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $td)) $td = null;
        // Manuelle Zuordnung -> rematch laesst das Foto kuenftig unangetastet
        $db->prepare("UPDATE anlass_fotos SET tag_index = ?, tag_datum = ?, tag_manuell = 1 WHERE id = ? AND galerie_id = ?")
           ->execute([$ti, $td, $fid, $gid]);
        echo json_encode(['success' => true, 'message' => 'Foto verschoben.']);
        break;
    }

    case 'delete_all': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        if ($gid < 1) json_error('Ungültige Galerie.');
        $sel = $db->prepare("SELECT * FROM anlass_fotos WHERE galerie_id = ?");
        $sel->execute([$gid]);
        $n = 0;
        foreach ($sel->fetchAll() as $foto) { fotoUnlinkDateien($foto); $n++; }
        $db->prepare("DELETE FROM anlass_fotos WHERE galerie_id = ?")->execute([$gid]);
        echo json_encode(['success' => true, 'count' => $n, 'message' => $n . ' Foto(s) gelöscht.']);
        break;
    }

    case 'delete': {
        $ids = moderate_ids();
        if (!$ids) json_error('Keine Fotos ausgewählt.');
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $sel = $db->prepare("SELECT * FROM anlass_fotos WHERE id IN ($in)");
        $sel->execute($ids);
        foreach ($sel->fetchAll() as $foto) fotoUnlinkDateien($foto);
        $db->prepare("DELETE FROM anlass_fotos WHERE id IN ($in)")->execute($ids);
        echo json_encode(['success' => true, 'count' => count($ids), 'message' => count($ids) . ' Foto(s) gelöscht.']);
        break;
    }

    default:
        json_error('Unbekannte Aktion.');
}
