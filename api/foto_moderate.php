<?php
// api/foto_moderate.php - Moderation der Galerie-Fotos (Vorstand/Admin).
// actions: list | approve | reject | approve_all | rematch | delete
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

switch ($action) {

    case 'list': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        if ($gid < 1) json_error('Ungültige Galerie.');
        $g = fotoGalerieLaden($db, $gid);
        if (!$g) json_error('Galerie nicht gefunden.', 404);
        $segmente = fotoSchiesstageSegmente($g['Schiesstage'] ?? null);

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
        $sql .= " ORDER BY (f.tag_datum IS NULL) ASC, f.tag_datum ASC, f.tag_index ASC,
                           f.sortierung ASC, f.aufnahme_zeit ASC, f.id ASC";
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
        $stmt = $db->prepare(
            "UPDATE anlass_fotos SET status = ?, moderiert_von = ?, moderiert_am = ? WHERE id IN ($in)"
        );
        $stmt->execute(array_merge([$new, $userId, $now], $ids));
        echo json_encode(['success' => true, 'count' => count($ids),
            'message' => count($ids) . ($action === 'approve' ? ' Foto(s) freigegeben.' : ' Foto(s) abgelehnt.')]);
        break;
    }

    case 'approve_all': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        if ($gid < 1) json_error('Ungültige Galerie.');
        $stmt = $db->prepare(
            "UPDATE anlass_fotos SET status = 'approved', moderiert_von = ?, moderiert_am = ?
              WHERE galerie_id = ? AND status = 'pending'"
        );
        $stmt->execute([$userId, $now, $gid]);
        echo json_encode(['success' => true, 'count' => $stmt->rowCount(),
            'message' => $stmt->rowCount() . ' Foto(s) freigegeben.']);
        break;
    }

    case 'rematch': {
        $gid = (int) ($_POST['galerie_id'] ?? 0);
        if ($gid < 1) json_error('Ungültige Galerie.');
        $g = fotoGalerieLaden($db, $gid);
        if (!$g) json_error('Galerie nicht gefunden.', 404);
        $segmente = fotoSchiesstageSegmente($g['Schiesstage'] ?? null);

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
        // sortierung = Position in der übergebenen Reihenfolge (galerie-gebunden gegen Manipulation)
        $upd = $db->prepare("UPDATE anlass_fotos SET sortierung = ? WHERE id = ? AND galerie_id = ?");
        $pos = 1;
        foreach ($ids as $id) { $upd->execute([$pos, $id, $gid]); $pos++; }
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
