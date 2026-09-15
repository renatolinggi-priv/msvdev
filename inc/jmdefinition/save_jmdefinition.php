<?php
// save_jmdefinition.php – speichert alle Anlaesse eines Jahres (Definition + Schiesstage)
// und in derselben Transaktion Zusatztext (JMInformation) und Anzahl Streicher (Parameter).
// Vorher waren das drei POSTs vom Client; jetzt ein Request, ein Commit.
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/jmdefinition_helpers.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $conn->connect_error]));
}

// Parameter aus dem POST-Request
$bezeichnungen = $_POST['bezeichnung'] ?? [];
$maxpunkte     = $_POST['maxpunkte'] ?? [];
$streicher     = $_POST['streicher'] ?? [];
$erweitert     = $_POST['erweitert'] ?? [];
$adresse       = $_POST['adresse'] ?? [];
$gruppe        = $_POST['gruppe'] ?? [];
$zuschlag      = $_POST['zuschlag'] ?? [];
$schiesstage   = $_POST['schiesstage'] ?? []; // mehrzeiliger Text pro Anlass
$info          = $_POST['info'] ?? [];
// Jahr der GELADENEN Tabelle (der Client schickt currentLoadedYear, nicht das Dropdown --
// sonst landen beim Jahreswechsel die Zeilen des alten Jahres unter dem neuen).
$year          = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
$zusatztext    = isset($_POST['zusatztext']) ? trim((string)$_POST['zusatztext']) : null;
$excludeCount  = isset($_POST['excludeCount']) && $_POST['excludeCount'] !== '' ? (int)$_POST['excludeCount'] : null;

if (!is_array($bezeichnungen)) $bezeichnungen = [];
if ($year < 2000 || $year > 2100) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültiges Jahr']));
}

$parseWarnings = []; // Schluessel = nicht erkannte Schiesstag-Zeilen (dedupliziert)

$conn->begin_transaction();
try {
    // 1. JMDefinition aktualisieren
    $stmtUpdate = $conn->prepare("UPDATE JMDefinition SET Bezeichnung = ?, Maxpunkte = ?, Streicher = ?, Erweitert = ?, Schiesstage = ?, Info = ?, Gruppe = ?, Adresse = ?, Zuschlag = ? WHERE ID = ? AND year = ?");
    if (!$stmtUpdate) {
        throw new Exception('Prepare (Update) fehlgeschlagen: ' . $conn->error);
    }
    foreach ($bezeichnungen as $id => $bezeichnung) {
        $id = (int)$id;
        if ($id < 1) continue;
        $bezeichnung      = trim((string)$bezeichnung);
        $maxpunkt         = isset($maxpunkte[$id]) ? (int)$maxpunkte[$id] : 0;
        $isStreicher      = isset($streicher[$id]) ? 1 : 0;
        $isErweitert      = isset($erweitert[$id]) ? 1 : 0;
        $isInfo           = isset($info[$id]) ? 1 : 0;
        $isGruppe         = isset($gruppe[$id]) ? 1 : 0;
        $zuschlagValue    = isset($zuschlag[$id]) ? (int)$zuschlag[$id] : 0;
        $schiesstageValue = isset($schiesstage[$id]) ? trim((string)$schiesstage[$id]) : '';
        $adresseValue     = isset($adresse[$id]) ? trim((string)$adresse[$id]) : '';

        $stmtUpdate->bind_param(
            'siiisiisiii',
            $bezeichnung, $maxpunkt, $isStreicher, $isErweitert, $schiesstageValue,
            $isInfo, $isGruppe, $adresseValue, $zuschlagValue, $id, $year
        );
        if (!$stmtUpdate->execute()) {
            throw new Exception("Anlass $id konnte nicht aktualisiert werden: " . $stmtUpdate->error);
        }

        // 2. JMSchiesstage aus dem Text neu aufbauen (Jahr aus der Zeile, sonst $year)
        jm_schiesstage_replace($conn, $id, $schiesstageValue, $year, $parseWarnings);
    }
    $stmtUpdate->close();

    // 3. Zusatztext und Anzahl Streicher (nur wenn mitgeschickt)
    if ($zusatztext !== null) {
        jm_information_save($conn, $zusatztext);
    }
    if ($excludeCount !== null) {
        jm_parameter_save($conn, $year, $excludeCount);
    }

    $conn->commit();
} catch (InvalidArgumentException $e) {
    $conn->rollback();
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[save_jmdefinition] ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Fehler beim Speichern: ' . $e->getMessage()]));
}

// 4. Sektionsmeisterschaft: bei mehr als einer SSM einen versteckten Sammel-Eintrag "SSM" anlegen
//    (bestehende Logik; Fehler hier verhindern das Speichern nicht, werden aber geloggt)
try {
    $stmtCheckSSM = $conn->prepare("SELECT COUNT(*) AS AnzSSM FROM JMDefinition WHERE Bezeichnung LIKE ? AND year = ?");
    if (!$stmtCheckSSM) throw new Exception('Prepare (SSM-Check): ' . $conn->error);
    $ssmPattern = '%Sektionsmeisterschaft%';
    $stmtCheckSSM->bind_param('si', $ssmPattern, $year);
    if (!$stmtCheckSSM->execute()) throw new Exception('SSM-Check: ' . $stmtCheckSSM->error);
    $anzSSM = (int)($stmtCheckSSM->get_result()->fetch_assoc()['AnzSSM'] ?? 0);
    $stmtCheckSSM->close();

    if ($anzSSM > 1) {
        $stmtHasSSM = $conn->prepare("SELECT COUNT(*) AS n FROM JMDefinition WHERE Bezeichnung = 'SSM' AND hidden = 1 AND year = ?");
        $stmtHasSSM->bind_param('i', $year);
        $stmtHasSSM->execute();
        $hasSSM = (int)($stmtHasSSM->get_result()->fetch_assoc()['n'] ?? 0);
        $stmtHasSSM->close();

        if ($hasSSM === 0) {
            $stmtMax = $conn->prepare("SELECT COALESCE(MAX(Reihenfolge), 0) + 1 AS next FROM JMDefinition WHERE year = ?");
            $stmtMax->bind_param('i', $year);
            $stmtMax->execute();
            $next = (int)$stmtMax->get_result()->fetch_assoc()['next'];
            $stmtMax->close();

            $stmtInsertSSM = $conn->prepare("INSERT INTO JMDefinition (Reihenfolge, Bezeichnung, Maxpunkte, Streicher, hidden, year, Erweitert, Schiesstage, Gruppe) VALUES (?, 'SSM', 100, 0, 1, ?, 0, '', 0)");
            if (!$stmtInsertSSM) throw new Exception('Prepare (SSM-Insert): ' . $conn->error);
            $stmtInsertSSM->bind_param('ii', $next, $year);
            if (!$stmtInsertSSM->execute()) throw new Exception('SSM-Insert: ' . $stmtInsertSSM->error);
            $stmtInsertSSM->close();
        }
    }
} catch (Throwable $e) {
    error_log('[save_jmdefinition] SSM-Logik: ' . $e->getMessage());
}

$response = ['success' => true, 'message' => 'Alle Änderungen wurden gespeichert'];
if ($parseWarnings) {
    $response['warnings'] = array_keys($parseWarnings);
}
echo json_encode($response);
$conn->close();
