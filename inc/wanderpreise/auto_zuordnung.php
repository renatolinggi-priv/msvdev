<?php
// inc/wanderpreise/auto_zuordnung.php
// Automatische Zuordnung von Gewinnern basierend auf den in wanderpreise_regeln definierten SQL-Regeln.
//
// Sicherheit (Review 09.2026): Zugriff nur fuer Admin/Vorstand (adminApiGuard), CSRF-Pflicht,
// und jedes Regel-SQL laeuft vor der Ausfuehrung nochmals durch wp_validate_regel_sql() --
// dieselbe Whitelist wie beim Speichern und in der Vorschau. Damit kann auch eine frueher
// gespeicherte Regel nichts Schreibendes mehr ausfuehren.

require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once 'regel_builder.inc.php'; // wp_validate_regel_sql()
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

$conn = null;
$inTransaction = false;

try {
    $conn = get_db_connection();
    if (!$conn) err_json('Datenbankverbindung fehlgeschlagen');
    $conn->set_charset('utf8mb4');

    $details = [];
    $zuordnungen = 0;
    $fehler = 0;

    // Alle Wanderpreise mit aktivierter Auto-Verknuepfung und Regel
    // (verknuepfung_jahr NULL oder 0 = alle Jahre, sonst nur das spezifische Jahr)
    $sqlW = "
        SELECT id, bezeichnung, min_anzahl_gewinne, verknuepfung_regel, verknuepfung_jahr
        FROM wanderpreise
        WHERE auto_verknuepfung = 1
          AND verknuepfung_regel IS NOT NULL
          AND (verknuepfung_jahr IS NULL OR verknuepfung_jahr = 0 OR verknuepfung_jahr = ?)
    ";
    $w = $conn->prepare($sqlW);
    if (!$w) err_json('DB-Fehler (prepare Wanderpreise): ' . $conn->error);
    $w->bind_param("i", $jahr);
    if (!$w->execute()) err_json('DB-Fehler (execute Wanderpreise): ' . $w->error);
    $resW = $w->get_result();

    // Regel-Statement vorbereiten (nur aktive Regeln)
    $getRegel = $conn->prepare("SELECT regel_name, sql_query FROM wanderpreise_regeln WHERE regel_code = ? AND aktiv = 1");
    if (!$getRegel) err_json('DB-Fehler (prepare Regel): ' . $conn->error);

    // Existiert fuer das Jahr bereits ein Eintrag?
    $exists = $conn->prepare("SELECT id FROM wanderpreise_gewinner WHERE wanderpreis_id = ? AND jahr = ?");
    if (!$exists) err_json('DB-Fehler (prepare Exists): ' . $conn->error);

    // Zaehler bisheriger Gewinne
    $countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM wanderpreise_gewinner WHERE wanderpreis_id = ? AND gewinner_id = ?");
    if (!$countStmt) err_json('DB-Fehler (prepare Count): ' . $conn->error);

    // Insert
    $ins = $conn->prepare("INSERT INTO wanderpreise_gewinner
        (wanderpreis_id, gewinner_id, jahr, rang, resultat, bemerkung, ist_definitiv, anzahl_gewinne, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?, ?, NOW(), NOW())");
    if (!$ins) err_json('DB-Fehler (prepare Insert): ' . $conn->error);

    // Optionales Update im Stamm bei definitivem Besitz (darf fehlschlagen)
    $updDef = $conn->prepare("UPDATE wanderpreise SET gewinner_id = ?, verknuepfung_jahr = ?, updated_at = NOW() WHERE id = ?");

    // Transaktion einmal global -- die einzelnen Preise laufen einzeln in Try/Catch,
    // Teilerfolge bleiben bestehen.
    $conn->begin_transaction();
    $inTransaction = true;

    while ($wp = $resW->fetch_assoc()) {
        $wpId   = (int)$wp['id'];
        $wpName = $wp['bezeichnung'];
        $minAnz = (int)$wp['min_anzahl_gewinne'];
        $code   = trim((string)$wp['verknuepfung_regel']);

        try {
            // Jahr schon belegt?
            $exists->bind_param("ii", $wpId, $jahr);
            $exists->execute();
            if ($exists->get_result()->fetch_assoc()) {
                $details[] = "Übersprungen – {$wpName}: Für {$jahr} existiert bereits ein Gewinner.";
                continue;
            }

            // Regel holen
            if ($code === '') {
                $details[] = "Hinweis – {$wpName}: Keine Regel verknüpft.";
                continue;
            }
            $getRegel->bind_param("s", $code);
            $getRegel->execute();
            $rRow = $getRegel->get_result()->fetch_assoc();
            if (!$rRow) {
                $details[] = "Hinweis – {$wpName}: Regel '{$code}' nicht gefunden oder inaktiv.";
                continue;
            }

            // Regel-SQL vor der Ausfuehrung pruefen (zweite Verteidigungslinie)
            $sql = (string)$rRow['sql_query'];
            $sqlFehler = wp_validate_regel_sql($sql);
            if ($sqlFehler !== null) {
                $fehler++;
                $details[] = "Fehler – {$wpName}: Regel '{$code}' abgelehnt ({$sqlFehler}).";
                continue;
            }

            // Kategorie aus dem Regelcode ableiten (A/B), sonst leer
            $kat = '';
            if (preg_match('/A$/i', $code))     $kat = 'Kat. A';
            elseif (preg_match('/B$/i', $code)) $kat = 'Kat. B';

            // Platzhalter ersetzen (falls {kategorie} in der Regel verwendet wird)
            $sql = str_replace(
                ['{jahr}', '{wanderpreis_id}', '{kategorie}'],
                [(int)$jahr, (int)$wpId, $kat],
                $sql
            );

            // Falls die Regel noch SET-Variablen verwendet: rauswerfen & inline ersetzen
            if (stripos($sql, 'SET @year') !== false || stripos($sql, 'SET @kategorie') !== false) {
                $sql = preg_replace('/\bSET\s+@year\s*=\s*[^;]+;?\s*/i', '', $sql);
                $sql = preg_replace('/\bSET\s+@kategorie\s*=\s*[^;]+;?\s*/i', '', $sql);
                $sql = str_replace(
                    ['@year', '@kategorie'],
                    [(string)(int)$jahr, ($kat !== '' ? ("'" . $conn->real_escape_string($kat) . "'") : "''")],
                    $sql
                );
            }

            // Ausfuehren -- bei Semikola Multi-Statements nutzen und das letzte Resultat nehmen
            $r = null;
            if (strpos($sql, ';') !== false) {
                if (!$conn->multi_query($sql)) {
                    $fehler++;
                    $details[] = "Fehler – {$wpName}: SQL-Fehler ({$conn->errno}) " . $conn->error;
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
                $details[] = "Fehler – {$wpName}: " . ($conn->error !== '' ? 'SQL-Fehler – ' . $conn->error : 'Regel liefert kein Resultset.');
                continue;
            }
            if ($r->num_rows < 1) {
                $details[] = "Info – {$wpName}: Keine Daten für {$jahr}, keine Zuordnung.";
                continue;
            }

            // Erste Zeile als Ergebnis (Regel sollte sinnvolle Sortierung liefern)
            $row = $r->fetch_assoc();
            $gewinnerId = (int)($row['gewinner_id'] ?? 0);
            if ($gewinnerId <= 0) {
                $fehler++;
                $details[] = "Fehler – {$wpName}: Regel liefert keine gültige 'gewinner_id'.";
                continue;
            }
            $rang     = isset($row['rang'])      ? (string)$row['rang']      : '';
            $resultat = isset($row['resultat'])  ? (string)$row['resultat']  : '';
            $bemerk   = isset($row['bemerkung']) ? (string)$row['bemerkung'] : '';

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
                $details[] = "Fehler – {$wpName}: Insert-Fehler – " . $ins->error;
                continue;
            }

            // Optional Stamm-Update bei Definitiv
            if ($istDef && $updDef) {
                $updDef->bind_param("iii", $gewinnerId, $jahr, $wpId);
                $updDef->execute(); // Fehler ignorieren
            }

            $zuordnungen++;
            $details[] = "OK – {$wpName}: Gewinner ID {$gewinnerId} zugeordnet"
                       . ($istDef ? " (definitiver Besitz erreicht)" : "")
                       . ($resultat !== '' ? " – Resultat: {$resultat}" : "")
                       . ($rang !== '' ? " – Rang: {$rang}" : "");

        } catch (Throwable $inner) {
            $fehler++;
            $details[] = "Fehler – {$wpName}: " . $inner->getMessage();
            // weiter mit naechstem Preis
        }
    }

    // Commit (Teilerfolge bleiben bestehen)
    $conn->commit();
    $inTransaction = false;

    ok_json([
        'message'      => "{$zuordnungen} Zuordnungen" . ($fehler ? ", {$fehler} Fehler" : ""),
        'zuordnungen'  => $zuordnungen,
        'fehler'       => $fehler,
        'details'      => $details,
        'jahr'         => $jahr
    ]);

} catch (Throwable $e) {
    if ($inTransaction && $conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    err_json('Fehler bei der automatischen Zuordnung: ' . $e->getMessage());
}
