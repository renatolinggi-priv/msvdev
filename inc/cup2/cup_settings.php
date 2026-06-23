<?php
/**
 * cup_settings.php
 * Liest und speichert Cup-Einstellungen pro Jahr (Tabelle cupSettings).
 * Aktuell: KatBToFinal (Kat.-B-Gewinner qualifiziert sich automatisch fuers Finale).
 *
 * GET  ?year=YYYY                  -> { success, katb_to_final: 0|1 }
 * POST { year, katb_to_final }     -> speichert (CSRF erforderlich)
 */

include '../config.php';
header('Content-Type: application/json');

$year = isset($_REQUEST['year']) ? (int)$_REQUEST['year'] : (int)date('Y');

// Selbstheilung: benötigtes Schema sicherstellen (falls Migrationen 018/020 nicht liefen)
function ensureCupSchema($conn) {
    try {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS cupSettings (
                Year INT NOT NULL,
                KatBToFinal TINYINT NOT NULL DEFAULT 1,
                PRIMARY KEY (Year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        // Advancers-Spalte auf cupPairs ergänzen, falls noch nicht vorhanden
        $col = $conn->query(
            "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cupPairs' AND COLUMN_NAME = 'Advancers'"
        );
        if ($col && (int)$col->fetch_assoc()['c'] === 0) {
            $conn->query("ALTER TABLE cupPairs ADD COLUMN Advancers TINYINT NULL DEFAULT NULL AFTER Participant3");
        }
    } catch (Throwable $e) {
        error_log("ensureCupSchema: " . $e->getMessage());
    }
}
ensureCupSchema($conn);

// Hilfsfunktion: Einstellung lesen (Default 1 = ein)
function getKatBToFinal($conn, $year) {
    $stmt = $conn->prepare("SELECT KatBToFinal FROM cupSettings WHERE Year = ?");
    if (!$stmt) {
        return 1;
    }
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row !== null ? (int)$row['KatBToFinal'] : 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Schutz (wie save_pairs.php)
    if (session_status() === PHP_SESSION_NONE) session_start();
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF-Validierung fehlgeschlagen']);
        exit;
    }

    $katb = (isset($_POST['katb_to_final']) && (int)$_POST['katb_to_final'] === 1) ? 1 : 0;

    $stmt = $conn->prepare(
        "INSERT INTO cupSettings (Year, KatBToFinal) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE KatBToFinal = VALUES(KatBToFinal)"
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Prepare fehlgeschlagen: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("ii", $year, $katb);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => (bool)$ok,
        'year' => $year,
        'katb_to_final' => $katb
    ]);
    $conn->close();
    exit;
}

// GET: aktuelle Einstellung liefern
echo json_encode([
    'success' => true,
    'year' => $year,
    'katb_to_final' => getKatBToFinal($conn, $year)
]);
$conn->close();
?>
