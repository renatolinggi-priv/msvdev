<?php
// test_regel_sql.php - Testet eine SQL-Regel gegen die aktuelle DB
require_once '../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // nur Admin/Vorstand duerfen Regel-SQL ausfuehren
require_once 'regel_builder.inc.php'; // wp_normalize_kategorie(), wp_validate_regel_sql()
require_once __DIR__ . '/../csrf.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF pruefen
csrf_require(true);

$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'DB-Verbindung fehlgeschlagen']);
    exit;
}

try {
    $sql = trim($_POST['sql'] ?? '');
    $jahr = intval($_POST['jahr'] ?? date('Y'));

    if (empty($sql)) {
        echo json_encode(['success' => false, 'message' => 'Kein SQL angegeben']);
        exit;
    }

    // Sicherheitscheck: nur "SET @var = ...; ... SELECT/WITH" (Whitelist, zentral)
    if (($sqlFehler = wp_validate_regel_sql($sql)) !== null) {
        echo json_encode(['success' => false, 'message' => $sqlFehler]);
        exit;
    }

    // Platzhalter ersetzen
    $kat = wp_normalize_kategorie($_POST['kategorie'] ?? ''); // '' | 'Kat. A' | 'Kat. B'
    $sql = str_replace('{jahr}', $jahr, $sql);
    $sql = str_replace('{wanderpreis_id}', '0', $sql);
    $sql = str_replace('{kategorie}', $kat, $sql);

    // Collation vereinheitlichen (verhindert "Illegal mix of collations")
    $conn->set_charset('utf8mb4');
    $conn->query("SET collation_connection = 'utf8mb4_unicode_ci'");

    // Multi-Query ausfuehren (SET + SELECT werden als separate Statements gesendet)
    if (!$conn->multi_query($sql)) {
        echo json_encode(['success' => false, 'message' => 'SQL-Fehler: ' . $conn->error]);
        exit;
    }

    // Alle Resultsets durchgehen, nur das letzte mit Daten verwenden
    $rows = [];
    $hasResult = true;
    do {
        $result = $conn->store_result();
        if ($result) {
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
        }
        $hasResult = $conn->more_results();
        if ($hasResult) {
            $conn->next_result();
        }
    } while ($hasResult);

    // Pruefen ob es einen Fehler gab
    if ($conn->error) {
        echo json_encode(['success' => false, 'message' => 'SQL-Fehler: ' . $conn->error]);
        exit;
    }

    if (count($rows) === 0) {
        echo json_encode([
            'success' => true,
            'message' => "Kein Ergebnis fuer Jahr $jahr (0 Zeilen)",
            'total'   => 0,
            'columns' => [],
            'rows'    => [],
            'jahr'    => $jahr,
        ]);
    } else {
        // Vorschau auf die ersten 10 Zeilen begrenzen
        $total   = count($rows);
        $preview = array_slice($rows, 0, 10);

        // Mitglieder-Namen zu allen gewinner_id der Vorschau in EINER Abfrage nachladen
        // (neue Verbindung noetig, da multi_query die Hauptverbindung belegt hat).
        $namen = [];
        $ids = [];
        foreach ($preview as $row) {
            if (isset($row['gewinner_id']) && is_numeric($row['gewinner_id'])) {
                $ids[(int)$row['gewinner_id']] = true;
            }
        }
        if ($ids) {
            $conn2 = get_db_connection();
            if ($conn2) {
                $idList = implode(',', array_map('intval', array_keys($ids)));
                $nameResult = $conn2->query(
                    "SELECT ID, CONCAT(Vorname, ' ', Name) AS fullname FROM mitglieder WHERE ID IN ($idList)"
                );
                if ($nameResult) {
                    while ($nameRow = $nameResult->fetch_assoc()) {
                        $namen[(int)$nameRow['ID']] = $nameRow['fullname'];
                    }
                }
                $conn2->close();
            }
        }

        // Spalten aus der ersten Zeile; gewinner_name vorne anstellen.
        $columns = array_keys($preview[0]);
        foreach ($preview as &$row) {
            $gid = isset($row['gewinner_id']) && is_numeric($row['gewinner_id']) ? (int)$row['gewinner_id'] : null;
            $row['gewinner_name'] = ($gid !== null && isset($namen[$gid])) ? $namen[$gid] : '';
        }
        unset($row);
        array_unshift($columns, 'gewinner_name');

        // Kurz-Zusammenfassung (Rueckwaertskompatibilitaet + Toast-Fallback)
        $first = $preview[0];
        $msg = 'Gewinner: ' . ($first['gewinner_name'] !== '' ? $first['gewinner_name'] : '?')
             . ' (ID: ' . ($first['gewinner_id'] ?? 'N/A') . ')';
        if (isset($first['resultat'])) $msg .= ' - Resultat: ' . $first['resultat'];
        $msg .= " ($total Zeile" . ($total > 1 ? 'n' : '') . ')';

        echo json_encode([
            'success' => true,
            'message' => $msg,
            'total'   => $total,
            'columns' => $columns,
            'rows'    => array_values($preview),
            'jahr'    => $jahr,
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}

$conn->close();
