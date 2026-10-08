<?php
/**
 * Speichert Kantonalstich-Resultate aus dem Raster oder der Schnellerfassung.
 * POST: jahr, csrf_token, passe[MitgliedID][1..5]
 * - Bestehender Datensatz: nur ausgefüllte Felder werden übernommen (leer = unverändert).
 * - Neuer Datensatz nur, wenn mindestens ein Feld ausgefüllt ist (auch 0).
 * - Erst werden alle Werte geprüft (ganze Zahlen 0–100), dann in einer Transaktion geschrieben:
 *   ein ungültiger Wert speichert gar nichts.
 */
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

const PASSEN = 5;

function antwort(int $code, bool $ok, string $msg): void {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg]);
}

/** Passenwert prüfen: '' (nicht ändern) oder ganze Zahl 0–100. */
function passenWert($v): string {
    $v = trim((string)$v);
    if ($v === '') return '';
    if (!ctype_digit($v) || (int)$v > 100) {
        throw new InvalidArgumentException('Ungültiger Wert «' . mb_substr($v, 0, 10) . '»: erlaubt sind ganze Zahlen von 0 bis 100.');
    }
    return (string)(int)$v;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { antwort(405, false, 'Nur POST erlaubt'); exit; }

$jahr  = isset($_POST['jahr']) ? (int)$_POST['jahr'] : (int)date('Y');
$passe = $_POST['passe'] ?? null;
if ($jahr < 2000 || $jahr > (int)date('Y') + 5) { antwort(400, false, 'Ungültiges Jahr'); exit; }
if (!is_array($passe))                          { antwort(400, false, 'Keine Resultate übermittelt'); exit; }
if ($conn->connect_error)                       { antwort(500, false, 'Datenbankfehler'); exit; }

$transaktion = false;
try {
    $daten = [];
    foreach ($passe as $mitgliedID => $passen) {
        $mitgliedID = (int)$mitgliedID;
        if ($mitgliedID <= 0 || !is_array($passen)) continue;
        $werte = [];
        for ($i = 1; $i <= PASSEN; $i++) {
            $werte[$i] = passenWert($passen[$i] ?? '');
        }
        $daten[$mitgliedID] = $werte;
    }

    $conn->begin_transaction();
    $transaktion = true;

    $check = $conn->prepare('SELECT 1 FROM kantiresultate WHERE MitgliedID = ? AND Jahr = ? LIMIT 1');
    if (!$check) throw new RuntimeException('SELECT vorbereiten: ' . $conn->error);
    $spalten = implode(', ', array_map(fn($i) => "Passe$i", range(1, PASSEN)));
    $insert = $conn->prepare("INSERT INTO kantiresultate (MitgliedID, Jahr, $spalten) VALUES (" . implode(', ', array_fill(0, PASSEN + 2, '?')) . ')');
    if (!$insert) throw new RuntimeException('INSERT vorbereiten: ' . $conn->error);

    foreach ($daten as $mitgliedID => $werte) {
        $check->bind_param('ii', $mitgliedID, $jahr);
        if (!$check->execute()) throw new RuntimeException('SELECT: ' . $check->error);
        $res = $check->get_result();
        $vorhanden = $res->num_rows > 0;
        $res->free();

        if ($vorhanden) {
            // nur ausgefüllte Felder übernehmen
            $set = []; $vals = []; $types = '';
            foreach ($werte as $i => $w) {
                if ($w === '') continue;
                $set[] = "Passe$i = ?";
                $vals[] = (int)$w;
                $types .= 'i';
            }
            if (!$set) continue;
            $stmt = $conn->prepare('UPDATE kantiresultate SET ' . implode(', ', $set) . ' WHERE MitgliedID = ? AND Jahr = ?');
            if (!$stmt) throw new RuntimeException('UPDATE vorbereiten: ' . $conn->error);
            $types .= 'ii';
            $vals[] = $mitgliedID;
            $vals[] = $jahr;
            $stmt->bind_param($types, ...$vals);
            if (!$stmt->execute()) throw new RuntimeException('UPDATE: ' . $stmt->error);
            $stmt->close();
        } else {
            // neuer Datensatz nur mit mindestens einem ausgefüllten Feld (auch 0)
            if (!array_filter($werte, fn($w) => $w !== '')) continue;
            $ins = [$mitgliedID, $jahr];
            foreach ($werte as $w) $ins[] = $w === '' ? 0 : (int)$w;
            $insert->bind_param(str_repeat('i', PASSEN + 2), ...$ins);
            if (!$insert->execute()) throw new RuntimeException('INSERT: ' . $insert->error);
        }
    }
    $check->close();
    $insert->close();
    $conn->commit();
    antwort(200, true, 'Alle Ergebnisse wurden erfolgreich gespeichert');
} catch (InvalidArgumentException $e) {
    antwort(400, false, $e->getMessage());
} catch (Throwable $e) {
    if ($transaktion) { try { $conn->rollback(); } catch (Throwable $_) {} }
    error_log('[KANTIRESULTATE_SAVE] ' . $e->getMessage());
    antwort(500, false, 'Speichern fehlgeschlagen, es wurde nichts geändert.');
}
$conn->close();
