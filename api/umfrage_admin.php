<?php
// api/umfrage_admin.php - Admin/Vorstand-API für Umfragen-Verwaltung (AJAX)
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/session_config.inc.php';
require_once __DIR__ . '/../auth.php';

// Auth manuell prüfen (statt requireRole, das HTML/Redirect ausgibt)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}
$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['admin', 'vorstand'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Zugriff verweigert']);
    exit;
}

$db = getDB();

// GET-Requests (list, get)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list':
            try {
                // Alle Umfragen (Antwort-Statistik separat, falls Tabelle fehlt)
                $stmt = $db->query("
                    SELECT u.*
                    FROM umfragen u
                    ORDER BY FIELD(u.status, 'aktiv', 'entwurf', 'geschlossen'), u.erstellt_am DESC
                ");
                $umfragen = $stmt->fetchAll();

                // Gesamtzahl aktive Mitglieder
                $total = (int)$db->query("SELECT COUNT(*) FROM mitglieder WHERE Status = 1 AND Verstorben = 0")->fetchColumn();

                // Fragen-Anzahl + Antwort-Statistik pro Umfrage
                $stmtF = $db->prepare("SELECT COUNT(*) FROM umfragen_fragen WHERE umfrage_id = ?");
                try {
                    $stmtA = $db->prepare("SELECT COUNT(DISTINCT mitglied_id) FROM umfragen_antworten WHERE umfrage_id = ?");
                } catch (Exception $e2) {
                    $stmtA = null;
                }
                foreach ($umfragen as &$u) {
                    $stmtF->execute([$u['id']]);
                    $u['anzahl_fragen'] = (int)$stmtF->fetchColumn();
                    if ($stmtA) {
                        $stmtA->execute([$u['id']]);
                        $u['anzahl_antworten'] = (int)$stmtA->fetchColumn();
                    } else {
                        $u['anzahl_antworten'] = 0;
                    }
                }
                unset($u);

                echo json_encode(['success' => true, 'umfragen' => $umfragen, 'total_mitglieder' => $total]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'DB-Fehler: ' . $e->getMessage()]);
            }
            break;

        case 'get':
            $id = intval($_GET['id'] ?? 0);
            if ($id < 1) {
                echo json_encode(['success' => false, 'message' => 'Ungültige Umfrage-ID']);
                break;
            }
            $stmt = $db->prepare("SELECT * FROM umfragen WHERE id = ?");
            $stmt->execute([$id]);
            $umfrage = $stmt->fetch();
            if (!$umfrage) {
                echo json_encode(['success' => false, 'message' => 'Umfrage nicht gefunden']);
                break;
            }
            $stmtF = $db->prepare("SELECT * FROM umfragen_fragen WHERE umfrage_id = ? ORDER BY reihenfolge");
            $stmtF->execute([$id]);
            $fragen = $stmtF->fetchAll();
            // JSON-Optionen dekodieren
            foreach ($fragen as &$f) {
                $f['optionen'] = $f['optionen'] ? json_decode($f['optionen'], true) : [];
            }
            unset($f);
            echo json_encode(['success' => true, 'umfrage' => $umfrage, 'fragen' => $fragen]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
    }
    exit;
}

// POST-Requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

// CSRF prüfen
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiges CSRF-Token. Bitte Seite neu laden.']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'save':
        // Umfrage + Fragen speichern (JSON im POST-Feld 'data')
        $data = json_decode($_POST['data'] ?? '{}', true);
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
            break;
        }

        $titel = trim($data['titel'] ?? '');
        $beschreibung = trim($data['beschreibung'] ?? '');
        $gueltig_bis = !empty($data['gueltig_bis']) ? $data['gueltig_bis'] : null;
        $zielgruppe = in_array($data['zielgruppe'] ?? '', ['alle', 'vorstand']) ? $data['zielgruppe'] : 'alle';
        $fragen = $data['fragen'] ?? [];
        $umfrage_id = intval($data['id'] ?? 0);
        $activate = !empty($data['activate']);

        if (empty($titel)) {
            echo json_encode(['success' => false, 'message' => 'Bitte einen Titel eingeben']);
            break;
        }
        if (empty($fragen)) {
            echo json_encode(['success' => false, 'message' => 'Bitte mindestens eine Frage hinzufügen']);
            break;
        }

        // Fragen validieren
        $allowedTypes = ['radio', 'checkbox', 'dropdown', 'text'];
        foreach ($fragen as $i => $frage) {
            if (empty(trim($frage['frage_text'] ?? ''))) {
                echo json_encode(['success' => false, 'message' => 'Frage ' . ($i + 1) . ': Bitte Text eingeben']);
                break 2;
            }
            if (!in_array($frage['frage_typ'] ?? '', $allowedTypes)) {
                echo json_encode(['success' => false, 'message' => 'Frage ' . ($i + 1) . ': Ungültiger Fragetyp']);
                break 2;
            }
            // Radio/Checkbox/Dropdown brauchen mindestens 2 Optionen
            if (in_array($frage['frage_typ'], ['radio', 'checkbox', 'dropdown'])) {
                $opts = array_filter($frage['optionen'] ?? [], function($o) { return trim($o) !== ''; });
                if (count($opts) < 2) {
                    echo json_encode(['success' => false, 'message' => 'Frage ' . ($i + 1) . ': Mindestens 2 Optionen nötig']);
                    break 2;
                }
            }
        }

        try {
            $db->beginTransaction();

            if ($umfrage_id > 0) {
                // Bearbeiten: prüfen ob Umfrage existiert
                $stmt = $db->prepare("SELECT status FROM umfragen WHERE id = ?");
                $stmt->execute([$umfrage_id]);
                $existing = $stmt->fetch();
                if (!$existing) {
                    $db->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Umfrage nicht gefunden']);
                    break;
                }

                if ($existing['status'] === 'geschlossen') {
                    // Geschlossen: nur Metadaten editierbar
                    $stmt = $db->prepare("UPDATE umfragen SET titel = ?, beschreibung = ?, gueltig_bis = ? WHERE id = ?");
                    $stmt->execute([$titel, $beschreibung, $gueltig_bis, $umfrage_id]);
                    $db->commit();
                    echo json_encode(['success' => true, 'message' => 'Umfrage aktualisiert', 'id' => $umfrage_id]);
                    break;
                }

                // Entwurf oder Aktiv: alles editierbar (Metadaten + Fragen)
                if ($existing['status'] === 'entwurf') {
                    $stmt = $db->prepare("UPDATE umfragen SET titel = ?, beschreibung = ?, gueltig_bis = ?, zielgruppe = ? WHERE id = ?");
                    $stmt->execute([$titel, $beschreibung, $gueltig_bis, $zielgruppe, $umfrage_id]);
                } else {
                    // Aktiv: Zielgruppe nicht mehr änderbar
                    $stmt = $db->prepare("UPDATE umfragen SET titel = ?, beschreibung = ?, gueltig_bis = ? WHERE id = ?");
                    $stmt->execute([$titel, $beschreibung, $gueltig_bis, $umfrage_id]);
                }

                // Alte Fragen löschen und neu einfügen
                $db->prepare("DELETE FROM umfragen_fragen WHERE umfrage_id = ?")->execute([$umfrage_id]);
            } else {
                // Neue Umfrage erstellen
                $stmt = $db->prepare("INSERT INTO umfragen (titel, beschreibung, gueltig_bis, zielgruppe, erstellt_von) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$titel, $beschreibung, $gueltig_bis, $zielgruppe, $_SESSION['user_id']]);
                $umfrage_id = (int)$db->lastInsertId();
            }

            // Fragen einfügen
            $stmtF = $db->prepare("INSERT INTO umfragen_fragen (umfrage_id, frage_text, frage_typ, pflichtfeld, reihenfolge, optionen) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($fragen as $i => $frage) {
                $optionen = null;
                if (in_array($frage['frage_typ'], ['radio', 'checkbox', 'dropdown'])) {
                    $opts = array_values(array_filter($frage['optionen'] ?? [], function($o) { return trim($o) !== ''; }));
                    $optionen = json_encode($opts, JSON_UNESCAPED_UNICODE);
                }
                $stmtF->execute([
                    $umfrage_id,
                    trim($frage['frage_text']),
                    $frage['frage_typ'],
                    !empty($frage['pflichtfeld']) ? 1 : 0,
                    $i,
                    $optionen
                ]);
            }

            // Optional: direkt aktivieren
            if ($activate) {
                $db->prepare("UPDATE umfragen SET status = 'aktiv' WHERE id = ?")->execute([$umfrage_id]);
            }

            $db->commit();
            $msg = $activate ? 'Umfrage erstellt und aktiviert' : 'Umfrage gespeichert';
            echo json_encode(['success' => true, 'message' => $msg, 'id' => $umfrage_id]);

        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
        break;

    case 'activate':
        $id = intval($_POST['id'] ?? 0);
        if ($id < 1) {
            echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
            break;
        }
        // Prüfen ob Fragen vorhanden
        $cnt = (int)$db->prepare("SELECT COUNT(*) FROM umfragen_fragen WHERE umfrage_id = ?")->execute([$id]) ? 0 : 0;
        $stmtCnt = $db->prepare("SELECT COUNT(*) FROM umfragen_fragen WHERE umfrage_id = ?");
        $stmtCnt->execute([$id]);
        $cnt = (int)$stmtCnt->fetchColumn();
        if ($cnt === 0) {
            echo json_encode(['success' => false, 'message' => 'Umfrage hat keine Fragen']);
            break;
        }
        $stmt = $db->prepare("UPDATE umfragen SET status = 'aktiv' WHERE id = ? AND status = 'entwurf'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Umfrage aktiviert']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Umfrage nicht gefunden oder bereits aktiv']);
        }
        break;

    case 'close':
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE umfragen SET status = 'geschlossen' WHERE id = ? AND status = 'aktiv'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Umfrage geschlossen']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Umfrage nicht gefunden oder nicht aktiv']);
        }
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM umfragen WHERE id = ? AND status = 'entwurf'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Umfrage gelöscht']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nur Entwürfe können gelöscht werden']);
        }
        break;

    case 'copy':
        $id = intval($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM umfragen WHERE id = ?");
        $stmt->execute([$id]);
        $orig = $stmt->fetch();
        if (!$orig) {
            echo json_encode(['success' => false, 'message' => 'Umfrage nicht gefunden']);
            break;
        }

        try {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO umfragen (titel, beschreibung, gueltig_bis, zielgruppe, erstellt_von, status) VALUES (?, ?, NULL, ?, ?, 'entwurf')");
            $stmt->execute([
                $orig['titel'] . ' (Kopie)',
                $orig['beschreibung'],
                $orig['zielgruppe'],
                $_SESSION['user_id']
            ]);
            $newId = (int)$db->lastInsertId();

            // Fragen kopieren
            $stmtF = $db->prepare("SELECT * FROM umfragen_fragen WHERE umfrage_id = ? ORDER BY reihenfolge");
            $stmtF->execute([$id]);
            $stmtIns = $db->prepare("INSERT INTO umfragen_fragen (umfrage_id, frage_text, frage_typ, pflichtfeld, reihenfolge, optionen) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($stmtF->fetchAll() as $f) {
                $stmtIns->execute([$newId, $f['frage_text'], $f['frage_typ'], $f['pflichtfeld'], $f['reihenfolge'], $f['optionen']]);
            }

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Umfrage kopiert als Entwurf', 'id' => $newId]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
}
