<?php
// inc/wanderpreise/auto_zuordnung.php
// Automatische Zuordnung von Gewinnern basierend auf den in wanderpreise_regeln definierten SQL-Regeln

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';
require_once __DIR__ . '/../csrf.inc.php';
header('Content-Type: application/json; charset=utf-8');
if (function_exists('ob_get_level')) { while (ob_get_level()) { ob_end_clean(); } }

function err_json($msg, $code = 500, $extra = []) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}
function ok_json($data = []) {
    echo json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Method/CSRF ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') err_json('Method not allowed', 405);
csrf_require(true);

// --- Eingaben ---
$jahr = isset($_POST['jahr']) ? (int)$_POST['jahr'] : (int)date('Y');
if ($jahr < 1900 || $jahr > 2100) err_json('Ungültiges Jahr', 400);

try {
    $conn = get_db_connection();
    $conn->set_charset('utf8mb4');

    $details = [];
    $zuordnungen = 0;
    $fehler = 0;

    // Alle Wanderpreise mit aktivierter Auto-Verknüpfung und Regel
 // --- Wanderpreise laden (Option C: NULL oder 0 = alle Jahre, sonst nur spezifisches Jahr) ---
    $sqlW = "
        SELECT id, bezeichnung, min_anzahl_gewinne, verknuepfung_regel, verknuepfung_jahr
        FROM wanderpreise
        WHERE auto_verknuepfung = 1
        AND verknuepfung_regel IS NOT NULL
        AND (
                verknuepfung_jahr IS NULL
            OR verknuepfung_jahr = 0
            OR verknuepfung_jahr = ?
        )
    ";
    $w = $conn->prepare($sqlW);
    if (!$w) err_json('DB-Fehler (prepare Wanderpreise): '.$conn->error);
    $w->bind_param("i", $jahr);
    if (!$w->execute()) err_json('DB-Fehler (execute Wanderpreise): '.$w->error);
    $resW = $w->get_result();

    // Regel-Statement vorbereiten (nur aktive Regeln)
    $sqlRegel = "SELECT regel_name, sql_query FROM wanderpreise_regeln WHERE regel_code = ? AND aktiv = 1";
    $getRegel = $conn->prepare($sqlRegel);
    if (!$getRegel) err_json('DB-Fehler (prepare Regel): '.$conn->error);

    // Prüfen, ob für das Jahr bereits ein Eintrag existiert
    $sqlExists = "SELECT id FROM wanderpreise_gewinner WHERE wanderpreis_id = ? AND jahr = ?";
    $exists = $conn->prepare($sqlExists);
    if (!$exists) err_json('DB-Fehler (prepare Exists): '.$conn->error);

    // Zähler bisheriger Gewinne
    $sqlCount = "SELECT COUNT(*) AS c FROM wanderpreise_gewinner WHERE wanderpreis_id = ? AND gewinner_id = ?";
    $countStmt = $conn->prepare($sqlCount);
    if (!$countStmt) err_json('DB-Fehler (prepare Count): '.$conn->error);

    // Insert
    $sqlIns = "INSERT INTO wanderpreise_gewinner
        (wanderpreis_id, gewinner_id, jahr, rang, resultat, bemerkung, ist_definitiv, anzahl_gewinne, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?, ?, NOW(), NOW())";
    $ins = $conn->prepare($sqlIns);
    if (!$ins) err_json('DB-Fehler (prepare Insert): '.$conn->error);

    // Optionales Update im Stamm bei definitivem Besitz
    $sqlUpdDef = "UPDATE wanderpreise SET gewinner_id = ?, verknuepfung_jahr = ?, updated_at = NOW() WHERE id = ?";
    $updDef = $conn->prepare($sqlUpdDef); // darf fehlschlagen, kein err_json()

    // Transaktion einmal global â€“ die einzelnen Preise laufen einzeln in Try/Catch
    $conn->begin_transaction();

    while ($wp = $resW->fetch_assoc()) {
        $wpId   = (int)$wp['id'];
        $wpName = $wp['bezeichnung'];
        $minAnz = (int)$wp['min_anzahl_gewinne'];
        $code   = trim((string)$wp['verknuepfung_regel']);

        try {
            // Jahr schon belegt?
            $exists->bind_param("ii", $wpId, $jahr);
            $exists->execute();
            $has = $exists->get_result()->fetch_assoc();
            if ($has) {
                $details[] = "â­ï¸ {$wpName}: Für {$jahr} existiert bereits ein Gewinner â€“ übersprungen.";
                continue;
            }

            // Regel holen
            if ($code === '') {
                $details[] = "âš ï¸ {$wpName}: Keine Regel verknüpft.";
                continue;
            }
            $getRegel->bind_param("s", $code);
            $getRegel->execute();
            $rRow = $getRegel->get_result()->fetch_assoc();
            if (!$rRow) {
                $details[] = "âš ï¸ {$wpName}: Regel '{$code}' nicht gefunden oder inaktiv.";
                continue;
            }

            // SQL aus DB holen
$sql = (string)$rRow['sql_query'];

// Kategorie aus dem Regelcode ableiten (A/B), sonst leer
$kat = '';
if (preg_match('/A$/i', $code))      $kat = 'Kat. A';
elseif (preg_match('/B$/i', $code))  $kat = 'Kat. B';

// Platzhalter ersetzen (falls {kategorie} in der Regel verwendet wird)
$sql = str_replace(
    ['{jahr}', '{wanderpreis_id}', '{kategorie}'],
    [(int)$jahr, (int)$wpId, $kat],
    $sql
);

// Falls die Regel noch SET-Variablen verwendet: rauswerfen & inline ersetzen
if (stripos($sql, 'SET @year') !== false || stripos($sql, 'SET @kategorie') !== false) {
    // SET-Zeilen entfernen
    $sql = preg_replace('/\bSET\s+@year\s*=\s*[^;]+;?\s*/i', '', $sql);
    $sql = preg_replace('/\bSET\s+@kategorie\s*=\s*[^;]+;?\s*/i', '', $sql);
    // Verwendungen der Session-Variablen inline ersetzen
    $sql = str_replace(
        ['@year', '@kategorie'],
        [(string)(int)$jahr, ($kat !== '' ? ("'".$conn->real_escape_string($kat)."'") : "''")],
        $sql
    );
}

// Ausführen â€“ wenn Semikola drin sind, Multi-Statements nutzen und letztes Resultat nehmen
$r = null;
if (strpos($sql, ';') !== false) {
    if (!$conn->multi_query($sql)) {
        $fehler++;
        $details[] = "âŒ {$wpName}: SQL-Fehler ({$conn->errno}) ".$conn->error;
        continue;
    }
    do {
        if ($tmp = $conn->store_result()) {
            if ($r) $r->free();
            $r = $tmp; // letztes SELECT ist relevant
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    $r = $conn->query($sql);
}

if (!$r instanceof mysqli_result) {
    $fehler++;
    $details[] = "âŒ {$wpName}: Regel liefert kein Resultset.";
    continue;
}

            if ($r === false) {
                $fehler++;
                $details[] = "âŒ {$wpName}: SQL-Fehler â€“ ".$conn->error;
                continue;
            }
            if ($r->num_rows < 1) {
                $details[] = "â„¹ï¸ {$wpName}: Keine Daten für {$jahr} â€“ keine Zuordnung.";
                continue;
            }

            // Erste Zeile als Ergebnis (Regel sollte sinnvolle Sortierung liefern)
            $row = $r->fetch_assoc();
            $gewinnerId = (int)($row['gewinner_id'] ?? 0);
            if ($gewinnerId <= 0) {
                $fehler++;
                $details[] = "âŒ {$wpName}: Regel liefert keine gültige 'gewinner_id'.";
                continue;
            }
            $rang     = isset($row['rang'])     ? (string)$row['rang']     : '';
            $resultat = isset($row['resultat']) ? (string)$row['resultat'] : '';
            $bemerk   = isset($row['bemerkung'])? (string)$row['bemerkung']: '';

            // Anzahl bisherige Gewinne
            $countStmt->bind_param("ii", $wpId, $gewinnerId);
            $countStmt->execute();
            $anz = (int)$countStmt->get_result()->fetch_assoc()['c'];
            $anzNeu = $anz + 1;
            $istDef = ($anzNeu >= max(1, $minAnz)) ? 1 : 0;

            // Insert
            $ins->bind_param("iiisssii", $wpId, $gewinnerId, $jahr, $rang, $resultat, $bemerk, $istDef, $anzNeu);
            if (!$ins->execute()) {
                $fehler++;
                $details[] = "âŒ {$wpName}: Insert-Fehler â€“ ".$ins->error;
                continue;
            }

            // Optional Stamm-Update bei Definitiv
            if ($istDef && $updDef) {
                $updDef->bind_param("iii", $gewinnerId, $jahr, $wpId);
                $updDef->execute(); // Fehler ignorieren
            }

            $zuordnungen++;
            $details[] = "âœ… {$wpName}: Gewinner ID {$gewinnerId} zugeordnet"
                       . ($istDef ? " (definitiver Besitz erreicht)" : "")
                       . ($resultat !== '' ? " â€“ Resultat: {$resultat}" : "")
                       . ($rang !== '' ? " â€“ Rang: {$rang}" : "");

        } catch (Throwable $inner) {
            $fehler++;
            $details[] = "âŒ {$wpName}: ".$inner->getMessage();
            // weiter mit nächstem Preis
        }
    }

    // Commit (wir lassen Teilerfolge bestehen)
    $conn->commit();

    ok_json([
        'message'      => "{$zuordnungen} Zuordnungen" . ($fehler ? ", {$fehler} Fehler" : ""),
        'zuordnungen'  => $zuordnungen,
        'fehler'       => $fehler,
        'details'      => $details,
        'jahr'         => $jahr
    ]);

} catch (Throwable $e) {
    // Rollback auf Nummer sicher
    if (isset($conn) && $conn instanceof mysqli && $conn->errno === 0) {
        $conn->rollback();
    }
    err_json('Fehler bei der automatischen Zuordnung: '.$e->getMessage());
}
