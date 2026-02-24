<?php
// api/einsatzplan_import.php - Einsatzplan parsen und importieren
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/einsatzplan_parser/docx_parser.php';
require_once __DIR__ . '/../inc/einsatzplan_parser/pdf_parser.php';
require_once __DIR__ . '/../inc/einsatzplan_parser/xlsx_parser.php';
require_once __DIR__ . '/../inc/einsatzplan_parser/name_matcher.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

// CSRF vor requireRole prüfen: requireRole/requireLogin kann via Remember-Me
// session_regenerate_id(true) auslösen und ein neues csrf_token setzen,
// wodurch das in der Seite eingebettete Token nicht mehr passt.
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sitzung abgelaufen. Bitte Seite neu laden.', 'csrf_expired' => true]);
    exit;
}

requireRole(['admin', 'vorstand']);

$action = $_POST['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'parse':
        handleParse($db);
        break;
    case 'save':
        handleSave($db);
        break;
    case 'update':
        handleUpdate($db);
        break;
    case 'delete':
        handleDelete($db);
        break;
    case 'delete_all':
        handleDeleteAll($db);
        break;
    case 'members':
        handleMembers($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
}

/**
 * Action: parse - Dokument parsen und Vorschau zurückgeben
 */
function handleParse($db) {
    $dokument_id = intval($_POST['dokument_id'] ?? 0);

    if ($dokument_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Keine Dokument-ID angegeben']);
        return;
    }

    // Dokument aus DB laden
    $stmt = $db->prepare("SELECT dateipfad, dateiname FROM vorstand_dokumente WHERE id = ? AND typ = 'einsatzplan'");
    $stmt->execute([$dokument_id]);
    $doc = $stmt->fetch();

    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Dokument nicht gefunden']);
        return;
    }

    $filepath = $doc['dateipfad'];
    if (!file_exists($filepath)) {
        echo json_encode(['success' => false, 'message' => 'Datei nicht gefunden auf dem Server']);
        return;
    }

    // Dateityp erkennen
    $ext = strtolower(pathinfo($doc['dateiname'], PATHINFO_EXTENSION));

    $debug = !empty($_POST['debug']);

    if ($ext === 'docx') {
        $result = parseEinsatzplanDocx($filepath);
    } elseif ($ext === 'pdf') {
        $result = parseEinsatzplanPdf($filepath, $debug);
    } elseif ($ext === 'xlsx' || $ext === 'xls') {
        $result = parseEinsatzplanXlsx($filepath);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nur DOCX-, PDF- und XLSX-Dateien werden unterstützt']);
        return;
    }

    if (!$result['success']) {
        $errorResponse = $result;
        if ($debug && isset($result['debug'])) {
            $errorResponse['debug'] = $result['debug'];
        }
        echo json_encode($errorResponse);
        return;
    }

    // Name-Matching durchführen
    $zuweisungen = matchMitglieder($db, $result['data']);

    // Nach Datum sortieren
    usort($zuweisungen, fn($a, $b) => strcmp($a['event_datum'], $b['event_datum']));

    // Statistiken
    $total = count($zuweisungen);
    $matched = count(array_filter($zuweisungen, fn($z) => $z['mitglied_id'] !== null));
    $unmatched = $total - $matched;

    $response = [
        'success' => true,
        'data' => $zuweisungen,
        'stats' => [
            'total' => $total,
            'matched' => $matched,
            'unmatched' => $unmatched,
        ],
        'message' => $result['message'],
    ];

    // Debug-Infos anhängen falls vorhanden
    if ($debug && isset($result['debug'])) {
        $response['debug'] = $result['debug'];
    }

    echo json_encode($response);
}

/**
 * Action: save - Geparste Zuweisungen in DB speichern
 */
function handleSave($db) {
    $dokument_id = intval($_POST['dokument_id'] ?? 0);
    $zuweisungenJson = $_POST['zuweisungen'] ?? '[]';

    if ($dokument_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Keine Dokument-ID angegeben']);
        return;
    }

    $zuweisungen = json_decode($zuweisungenJson, true);
    if (!is_array($zuweisungen) || empty($zuweisungen)) {
        echo json_encode(['success' => false, 'message' => 'Keine Zuweisungen zum Speichern']);
        return;
    }

    // Dokument prüfen
    $stmt = $db->prepare("SELECT id FROM vorstand_dokumente WHERE id = ? AND typ = 'einsatzplan'");
    $stmt->execute([$dokument_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Dokument nicht gefunden']);
        return;
    }

    // Jahre und Bezeichnungen aus den zu importierenden Daten ermitteln
    $importJahre = [];
    $importBezeichnungen = [];
    foreach ($zuweisungen as $z) {
        $j = date('Y', strtotime($z['event_datum']));
        $importJahre[$j] = true;
        $b = $z['bezeichnung'] ?? '';
        if ($b !== '') $importBezeichnungen[$b] = true;
    }

    try {
        $db->beginTransaction();

        // Alte Einträge für dieses Dokument löschen (Re-Import)
        $stmt = $db->prepare("DELETE FROM einsatz_zuweisungen WHERE dokument_id = ?");
        $stmt->execute([$dokument_id]);

        // Bestehende Einträge mit gleicher Bezeichnung + Jahr löschen (Duplikat-Vermeidung)
        if (!empty($importJahre) && !empty($importBezeichnungen)) {
            $jahrPlaceholders = implode(',', array_fill(0, count($importJahre), '?'));
            $bezPlaceholders = implode(',', array_fill(0, count($importBezeichnungen), '?'));
            $stmt = $db->prepare("
                DELETE FROM einsatz_zuweisungen
                WHERE jahr IN ($jahrPlaceholders) AND bezeichnung IN ($bezPlaceholders)
            ");
            $stmt->execute(array_merge(array_keys($importJahre), array_keys($importBezeichnungen)));
        }

        // Neue Einträge einfügen
        $stmt = $db->prepare("
            INSERT INTO einsatz_zuweisungen (typ, bezeichnung, event_datum, event_zeit, funktion, mitglied_name, mitglied_id, jahr, dokument_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $count = 0;
        foreach ($zuweisungen as $z) {
            $jahr = date('Y', strtotime($z['event_datum']));
            $stmt->execute([
                $z['typ'] ?? 'einsatz',
                $z['bezeichnung'] ?? '',
                $z['event_datum'],
                $z['event_zeit'] ?? null,
                $z['funktion'] ?? '',
                $z['mitglied_name'],
                !empty($z['mitglied_id']) ? intval($z['mitglied_id']) : null,
                $jahr,
                $dokument_id,
            ]);
            $count++;
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => $count . ' Einsätze importiert',
            'count' => $count,
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern: ' . $e->getMessage()]);
    }
}

/**
 * Action: update - Einzelnen Eintrag bearbeiten
 */
function handleUpdate($db) {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Keine ID angegeben']);
        return;
    }

    $funktion = trim($_POST['funktion'] ?? '');
    $mitglied_name = trim($_POST['mitglied_name'] ?? '');
    $mitglied_id = !empty($_POST['mitglied_id']) ? intval($_POST['mitglied_id']) : null;
    $event_datum = $_POST['event_datum'] ?? '';
    $event_zeit = trim($_POST['event_zeit'] ?? '');

    if (empty($mitglied_name)) {
        echo json_encode(['success' => false, 'message' => 'Name ist erforderlich']);
        return;
    }

    $stmt = $db->prepare("
        UPDATE einsatz_zuweisungen
        SET funktion=?, mitglied_name=?, mitglied_id=?, event_datum=?, event_zeit=?
        WHERE id=?
    ");
    $stmt->execute([$funktion, $mitglied_name, $mitglied_id, $event_datum, $event_zeit ?: null, $id]);

    echo json_encode(['success' => true, 'message' => 'Eintrag aktualisiert']);
}

/**
 * Action: delete - Einzelnen Eintrag löschen
 */
function handleDelete($db) {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Keine ID angegeben']);
        return;
    }

    $stmt = $db->prepare("DELETE FROM einsatz_zuweisungen WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Eintrag gelöscht']);
}

/**
 * Action: delete_all - Alle Einträge eines Dokuments löschen
 */
function handleDeleteAll($db) {
    $dokument_id = intval($_POST['dokument_id'] ?? 0);
    if ($dokument_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Keine Dokument-ID angegeben']);
        return;
    }

    $stmt = $db->prepare("DELETE FROM einsatz_zuweisungen WHERE dokument_id = ?");
    $stmt->execute([$dokument_id]);
    $count = $stmt->rowCount();

    echo json_encode(['success' => true, 'message' => $count . ' Einträge gelöscht']);
}

/**
 * Action: members - Mitglieder-Liste für Dropdown
 */
function handleMembers($db) {
    $stmt = $db->query("SELECT ID as id, Name as name, Vorname as vorname FROM mitglieder WHERE Status=1 AND Verstorben=0 ORDER BY Name, Vorname");
    $members = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $members]);
}
