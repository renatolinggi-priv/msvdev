<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../inc/dbconnect.inc.php';

// --- NUR User-ID 1 darf ---
if (!function_exists('user_can_manage_navigation')) {

    function user_can_manage_navigation(): bool {
        return (int)($_SESSION['user_id'] ?? 0) === 1;
    }
}

function out($a,$code=200){ 
    http_response_code($code); 
    echo json_encode($a, JSON_UNESCAPED_UNICODE); 
    exit; 
}

function bad($m,$c=400){ 
    out(['success'=>false,'message'=>$m],$c); 
}

// Gate
if (!user_can_manage_navigation()) {
    bad('Kein Zugriff', 403);
}

// CSRF nur für Nicht-GET
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        bad('Ungültiger CSRF-Token', 403);
    }
}

// Ensure SortOrder column exists
function ensure_sortorder_column(mysqli $conn){
    $res = $conn->query("SHOW COLUMNS FROM `navigation` LIKE 'SortOrder'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE `navigation` ADD COLUMN `SortOrder` INT NOT NULL DEFAULT 0 AFTER `ParentID`");
        $conn->query("UPDATE `navigation` SET `SortOrder` = `ID`");
        $conn->query("ALTER TABLE `navigation` ADD INDEX idx_parent_sort (ParentID, SortOrder)");
    }
}

function fetch_all(mysqli $conn){
    ensure_sortorder_column($conn);
    $items = [];
    $sql = "SELECT ID, Text, Link, ParentID, SortOrder FROM navigation ORDER BY ParentID, SortOrder, ID";
    if ($res = $conn->query($sql)) {
        while($row = $res->fetch_assoc()){ 
            $items[] = $row; 
        }
        $res->free();
    }
    return $items;
}

// Sortierung innerhalb eines Parents neu berechnen
function reorder_siblings(mysqli $conn, $parent_id) {
    $sql = "SELECT ID FROM navigation WHERE ParentID = ? ORDER BY SortOrder, ID";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = 0;
    while ($row = $result->fetch_assoc()) {
        $update = $conn->prepare("UPDATE navigation SET SortOrder = ? WHERE ID = ?");
        $update->bind_param('ii', $order, $row['ID']);
        $update->execute();
        $update->close();
        $order += 10; // 10er Schritte für spätere Einfügungen
    }
    $stmt->close();
}

switch($action){
    case 'list': 
        $items = fetch_all($conn);
        out(['success'=>true,'items'=>$items]);
        break;
    case 'create': 
        $text = trim($_POST['text'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $parent = (int)($_POST['parent_id'] ?? 0);
        if ($text==='' || $link==='') {
            bad('Text und Link sind Pflichtfelder');
        }
        ensure_sortorder_column($conn);

        // Get next sort order
        $stmt = $conn->prepare("SELECT COALESCE(MAX(SortOrder),0)+10 FROM navigation WHERE ParentID=?");
        $stmt->bind_param('i', $parent); 
        $stmt->execute(); 
        $stmt->bind_result($next); 
        $stmt->fetch(); 
        $stmt->close();

        // Insert new item
        $stmt = $conn->prepare("INSERT INTO navigation (Text, Link, ParentID, SortOrder) VALUES (?,?,?,?)");
        $stmt->bind_param('ssii', $text, $link, $parent, $next);
        if (!$stmt->execute()) {
            bad('Einfügen fehlgeschlagen: '.$conn->error, 500);
        }
        $stmt->close();
        out(['success'=>true, 'items'=>fetch_all($conn)]);
        break;
    case 'update': 
        $id = (int)($_POST['id'] ?? 0);
        $text = trim($_POST['text'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $parent = (int)($_POST['parent_id'] ?? 0);
        if ($id<=0) bad('Ungültige ID');
        if ($text==='' || $link==='') bad('Text und Link sind Pflichtfelder');
        if ($parent === $id) bad('Parent darf nicht du selbst sein');

        // Check for circular reference
        $pid = $parent; 
        $max = 20;
        while($pid && $max--){
            $res = $conn->query("SELECT ParentID FROM navigation WHERE ID=".(int)$pid);
            if ($res && $row = $res->fetch_assoc()){
                if ((int)$row['ParentID'] === $id) {
                    bad('Zyklische Parent-Zuordnung nicht erlaubt');
                }
                $pid = (int)$row['ParentID'];
            } else {
                break;
            }
        }
        $stmt = $conn->prepare("UPDATE navigation SET Text=?, Link=?, ParentID=? WHERE ID=?");
        $stmt->bind_param('ssii', $text, $link, $parent, $id);
        if (!$stmt->execute()) {
            bad('Speichern fehlgeschlagen: '.$conn->error, 500);
        }
        $stmt->close();
        out(['success'=>true, 'items'=>fetch_all($conn)]);
        break;
    case 'delete': 
        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0) bad('Ungültige ID');

        // Check for children
        $stmt = $conn->prepare("SELECT COUNT(*) FROM navigation WHERE ParentID=?");
        $stmt->bind_param('i', $id); 
        $stmt->execute(); 
        $stmt->bind_result($cnt); 
        $stmt->fetch(); 
        $stmt->close();
        if ($cnt>0) {
            bad('Eintrag hat Unterpunkte â€“ zuerst Kinder umhängen oder löschen');
        }

        // Get parent for reordering
        $stmt = $conn->prepare("SELECT ParentID FROM navigation WHERE ID=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($parent_id);
        $stmt->fetch();
        $stmt->close();

        // Delete
        $stmt = $conn->prepare("DELETE FROM navigation WHERE ID=? LIMIT 1");
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            bad('Löschen fehlgeschlagen: '.$conn->error, 500);
        }
        $stmt->close();

        // Reorder siblings
        reorder_siblings($conn, $parent_id);
        out(['success'=>true, 'items'=>fetch_all($conn)]);
        break;
    case 'update_position':
        ensure_sortorder_column($conn);
        $id = (int)($_POST['id'] ?? 0);
        $new_parent = (int)($_POST['parent_id'] ?? 0);
        $position = (int)($_POST['position'] ?? 0);
        if ($id <= 0) bad('Ungültige ID');

        // Verhindere dass ein Element sein eigenes Kind wird
        if ($new_parent === $id) {
            bad('Ein Element kann nicht sein eigenes Untermenü sein');
        }

        // Prüfe auf zyklische Referenzen
        if ($new_parent > 0) {
            $check_id = $new_parent;
            $max_depth = 10;
            while ($check_id && $max_depth-- > 0) {
                $stmt = $conn->prepare("SELECT ParentID FROM navigation WHERE ID = ?");
                $stmt->bind_param('i', $check_id);
                $stmt->execute();
                $stmt->bind_result($parent_of_check);
                $stmt->fetch();
                $stmt->close();
                if ($parent_of_check == $id) {
                    bad('Diese Verschiebung würde eine zyklische Referenz erzeugen');
                }
                $check_id = $parent_of_check;
            }
        }
        $conn->begin_transaction();
        try {

            // Hole alte Parent ID für späteres Reordering
            $stmt = $conn->prepare("SELECT ParentID FROM navigation WHERE ID = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->bind_result($old_parent);
            $stmt->fetch();
            $stmt->close();

            // Update Parent
            $stmt = $conn->prepare("UPDATE navigation SET ParentID = ? WHERE ID = ?");
            $stmt->bind_param('ii', $new_parent, $id);
            $stmt->execute();
            $stmt->close();

            // Hole alle Items des neuen Parents (sortiert)
            $stmt = $conn->prepare("SELECT ID FROM navigation WHERE ParentID = ? AND ID != ? ORDER BY SortOrder, ID");
            $stmt->bind_param('ii', $new_parent, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $siblings = [];
            while ($row = $result->fetch_assoc()) {
                $siblings[] = $row['ID'];
            }
            $stmt->close();

            // Füge das verschobene Element an der gewünschten Position ein
            array_splice($siblings, $position, 0, $id);

            // Update SortOrder für alle Geschwister
            $order = 0;
            foreach ($siblings as $sibling_id) {
                $stmt = $conn->prepare("UPDATE navigation SET SortOrder = ? WHERE ID = ?");
                $stmt->bind_param('ii', $order, $sibling_id);
                $stmt->execute();
                $stmt->close();
                $order += 10;
            }

            // Reorder alte Parent-Gruppe wenn verschieden
            if ($old_parent != $new_parent) {
                reorder_siblings($conn, $old_parent);
            }
            $conn->commit();
            out(['success' => true, 'items' => fetch_all($conn)]);
        } catch (Exception $e) {
            $conn->rollback();
            bad('Fehler beim Verschieben: ' . $e->getMessage());
        }
        break;
    case 'batch_update':

        // Batch-Update für mehrere Items gleichzeitig
        $updates_json = $_POST['updates'] ?? '[]';
        $updates = json_decode($updates_json, true);
        if (!is_array($updates) || empty($updates)) {
            bad('Keine Änderungen zu speichern');
        }
        ensure_sortorder_column($conn);
        $conn->begin_transaction();
        try {
            $success_count = 0;
            foreach ($updates as $update) {
                $id = (int)($update['id'] ?? 0);
                $parent_id = (int)($update['parent_id'] ?? 0);
                $sort_order = (int)($update['sort_order'] ?? 0);
                if ($id <= 0) continue;

                // Verhindere dass ein Element sein eigenes Parent wird
                if ($parent_id === $id) {
                    $conn->rollback();
                    bad('Ein Element kann nicht sein eigenes Untermenü sein');
                }

                // Update ParentID und SortOrder
                $stmt = $conn->prepare("UPDATE navigation SET ParentID = ?, SortOrder = ? WHERE ID = ?");
                $stmt->bind_param('iii', $parent_id, $sort_order, $id);
                if ($stmt->execute()) {
                    $success_count++;
                }
                $stmt->close();
            }

            // Reorder alle betroffenen Parent-Gruppen
            $affected_parents = array_unique(array_column($updates, 'parent_id'));
            foreach ($affected_parents as $parent_id) {
                reorder_siblings($conn, $parent_id);
            }
            $conn->commit();
            out([
                'success' => true, 
                'message' => "$success_count Einträge aktualisiert",
                'items' => fetch_all($conn)
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            bad('Fehler beim Batch-Update: ' . $e->getMessage());
        }
        break;
    case 'reorder': 
        ensure_sortorder_column($conn);
        $id = (int)($_POST['id'] ?? 0);
        $dir = $_POST['dir'] ?? '';
        if ($id<=0 || !in_array($dir,['up','down'],true)) {
            bad('Ungültige Parameter');
        }

        // Get current item
        $stmt = $conn->prepare("SELECT ParentID, SortOrder FROM navigation WHERE ID=?");
        $stmt->bind_param('i',$id); 
        $stmt->execute(); 
        $stmt->bind_result($pid,$sort); 
        $stmt->fetch(); 
        $stmt->close();

        // Find swap partner
        if ($dir==='up') {
            $stmt = $conn->prepare("SELECT ID, SortOrder FROM navigation WHERE ParentID=? AND SortOrder < ? ORDER BY SortOrder DESC LIMIT 1");
        } else {
            $stmt = $conn->prepare("SELECT ID, SortOrder FROM navigation WHERE ParentID=? AND SortOrder > ? ORDER BY SortOrder ASC LIMIT 1");
        }
        $stmt->bind_param('ii', $pid, $sort); 
        $stmt->execute(); 
        $stmt->bind_result($oid,$osort);
        if ($stmt->fetch()){
            $stmt->close();

            // Swap sort orders
            $conn->begin_transaction();
            $u1 = $conn->prepare("UPDATE navigation SET SortOrder=? WHERE ID=?");
            $u1->bind_param('ii', $osort, $id); 
            $u1->execute(); 
            $u1->close();
            $u2 = $conn->prepare("UPDATE navigation SET SortOrder=? WHERE ID=?");
            $u2->bind_param('ii', $sort, $oid); 
            $u2->execute(); 
            $u2->close();
            $conn->commit();
        } else {
            $stmt->close();
        }
        out(['success'=>true, 'items'=>fetch_all($conn)]);
        break;
    default: 
        bad('Unbekannte Aktion', 400);
}

?>
