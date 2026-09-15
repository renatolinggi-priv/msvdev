<?php
// save_standbelegung.php – Import-Vorschau speichern (Upsert ueber UNIQUE (Datum, Bezeichnung, StartZeit, Jahr))
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/standbelegung_config.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']));
}
csrf_require(true); // Header X-CSRF-TOKEN

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['termine']) || !is_array($input['termine'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Keine Daten empfangen']));
}

$year = isset($input['year']) ? (int)$input['year'] : (int)date('Y');
if ($year < 2000 || $year > 2100) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Ungültiges Jahr']));
}

$inserted = 0; $updated = 0; $unchanged = 0;
$errors = [];

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare("
        INSERT INTO Standbelegung (Datum, Wochentag, Bezeichnung, StartZeit, EndZeit, Kategorie, InKalender, Jahr)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            Wochentag = VALUES(Wochentag),
            EndZeit = VALUES(EndZeit),
            Kategorie = VALUES(Kategorie),
            InKalender = VALUES(InKalender)
    ");
    if (!$stmt) throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);

    foreach ($input['termine'] as $i => $termin) {
        $zeile = $i + 1;
        if (!is_array($termin)) { $errors[] = "Zeile $zeile: ungültiges Format"; continue; }

        // Datum dd.mm.yyyy (Excel) oder yyyy-mm-dd
        $datumRaw = trim((string)($termin['datum'] ?? ''));
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $datumRaw, $m)) {
            $datumDb = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        } else {
            $datumDb = $datumRaw;
        }
        $d = DateTime::createFromFormat('Y-m-d', $datumDb);
        if (!$d || $d->format('Y-m-d') !== $datumDb) { $errors[] = "Zeile $zeile: ungültiges Datum «{$datumRaw}»"; continue; }

        $bezeichnung = trim((string)($termin['bezeichnung'] ?? ''));
        if ($bezeichnung === '') { $errors[] = "Zeile $zeile ($datumRaw): Bezeichnung fehlt"; continue; }
        $kategorie = (string)($termin['kategorie'] ?? 'Sonstiges');
        if (!in_array($kategorie, SB_KATEGORIEN, true)) $kategorie = 'Sonstiges';
        $wochentag = mb_substr(trim((string)($termin['wochentag'] ?? '')), 0, 2) ?: ['SO', 'MO', 'DI', 'MI', 'DO', 'FR', 'SA'][(int)$d->format('w')];
        $inKalender = !empty($termin['in_kalender']) ? 1 : 0;

        $zeiten = [];
        foreach (['start_zeit', 'end_zeit'] as $key) {
            $z = trim((string)($termin[$key] ?? ''));
            if ($z === '') { $zeiten[$key] = null; continue; }
            if (!preg_match('/^(\d{1,2})[:.](\d{2})/', $z, $tm)) { $zeiten[$key] = null; continue; }
            $zeiten[$key] = sprintf('%02d:%02d:00', (int)$tm[1], (int)$tm[2]);
        }

        $stmt->bind_param('ssssssii', $datumDb, $wochentag, $bezeichnung, $zeiten['start_zeit'], $zeiten['end_zeit'], $kategorie, $inKalender, $year);
        if (!$stmt->execute()) {
            // Vorher still uebersprungen -> Import meldete Erfolg, Fehler blieben unsichtbar
            $errors[] = "Zeile $zeile ($datumRaw, $bezeichnung): " . $stmt->error;
            error_log('[standbelegung/save_standbelegung] ' . $stmt->error);
            continue;
        }
        // affected_rows: 1 = neu, 2 = aktualisiert, 0 = unveraendert
        if ($stmt->affected_rows === 1) $inserted++;
        elseif ($stmt->affected_rows === 2) $updated++;
        else $unchanged++;
    }
    $stmt->close();
    $conn->commit();

    echo json_encode([
        'success'   => true,
        'inserted'  => $inserted,
        'updated'   => $updated,
        'unchanged' => $unchanged,
        'errors'    => $errors,
        'message'   => "$inserted neu, $updated aktualisiert" . ($unchanged ? ", $unchanged unverändert" : '') . ($errors ? ', ' . count($errors) . ' Fehler' : ''),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[standbelegung/save_standbelegung] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Import konnte nicht gespeichert werden']);
}
$conn->close();
