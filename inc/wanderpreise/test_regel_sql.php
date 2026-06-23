<?php
// test_regel_sql.php - Testet eine SQL-Regel gegen die aktuelle DB
session_start();
require_once '../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF pruefen
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Ungueltiger CSRF-Token']);
    exit;
}

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

    // Sicherheitscheck: Destruktive Statements blocken
    $blocked = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'GRANT', 'REVOKE', 'LOAD', 'CALL'];
    // Kommentare und Strings fuer Keyword-Check entfernen
    $sqlClean = preg_replace("/'[^']*'/", "''", $sql);        // Strings entfernen
    $sqlClean = preg_replace('/--.*$/m', '', $sqlClean);       // Einzeilige Kommentare
    $sqlClean = preg_replace('/\/\*.*?\*\//s', '', $sqlClean); // Block-Kommentare

    foreach ($blocked as $keyword) {
        // Nur als eigenstaendiges Keyword matchen (nicht in Spaltennamen etc.)
        if (preg_match('/\b' . $keyword . '\b/i', $sqlClean)) {
            echo json_encode(['success' => false, 'message' => "Nicht erlaubt: $keyword"]);
            exit;
        }
    }

    // Erlaubte Anfaenge: SELECT, SET, WITH (fuer CTEs und Variablen)
    $sqlUpper = strtoupper(ltrim($sqlClean));
    $allowedStarts = ['SELECT', 'SET', 'WITH'];
    $validStart = false;
    foreach ($allowedStarts as $start) {
        if (str_starts_with($sqlUpper, $start)) {
            $validStart = true;
            break;
        }
    }
    if (!$validStart) {
        echo json_encode(['success' => false, 'message' => 'SQL muss mit SELECT, SET oder WITH beginnen']);
        exit;
    }

    // Platzhalter ersetzen
    $sql = str_replace('{jahr}', $jahr, $sql);
    $sql = str_replace('{wanderpreis_id}', '0', $sql);

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
        echo json_encode(['success' => true, 'message' => "Kein Ergebnis fuer Jahr $jahr (0 Zeilen)"]);
    } else {
        $first = $rows[0];
        $gewinnerId = $first['gewinner_id'] ?? 'N/A';
        $name = '';

        // Name nachladen (neue Verbindung noetig nach multi_query)
        if (is_numeric($gewinnerId)) {
            $conn2 = get_db_connection();
            if ($conn2) {
                $nameResult = $conn2->query(
                    "SELECT CONCAT(Vorname, ' ', Name) AS fullname FROM mitglieder WHERE ID = " . intval($gewinnerId)
                );
                if ($nameResult && $nameRow = $nameResult->fetch_assoc()) {
                    $name = $nameRow['fullname'];
                }
                $conn2->close();
            }
        }

        $msg = "Gewinner: $name (ID: $gewinnerId)";
        if (isset($first['resultat'])) $msg .= " - Resultat: " . $first['resultat'];
        if (isset($first['rang']))     $msg .= " - Rang: " . $first['rang'];
        $msg .= " (" . count($rows) . " Zeile" . (count($rows) > 1 ? 'n' : '') . ")";

        echo json_encode(['success' => true, 'message' => $msg]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}

$conn->close();
