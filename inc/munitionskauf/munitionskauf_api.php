<?php
/**
 * inc/munitionskauf/munitionskauf_api.php – Backend der Munitionsbestellungen (PDO, seit 21.09.2026).
 *
 * GET  ?action=list_mitglieder                 → {success, data:[{id, Vorname, Name}]}
 * POST ?action=save_bestellung  (JSON-Body)    → {success, message}
 * GET  ?action=get_bestellungen&jahr&filter    → {success, data:[…], totals:{gp11_total, gp90_total, total_preis}}
 * POST ?action=delete_bestellung (JSON {id})   → {success, message}
 * GET  ?action=get_statistics&jahr             → {success, data:{today, week, month, year, top_buyers}}
 * Zugriff nur Admin-Bereich (adminApiGuard), CSRF bei POST (Header X-CSRF-TOKEN, siehe munitionskauf.js).
 * Preis: 50 Rappen pro Schuss (total_preis in Rappen, wie bisher).
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
date_default_timezone_set('Europe/Zurich');

require_once __DIR__ . '/../debug_log.inc.php';
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';

header('Content-Type: application/json; charset=utf-8');

const MK_RAPPEN_PRO_SCHUSS = 50;

/** JSON-Antwort {success, data, message} und Ende. */
function jsonResponse(bool $success, $data = null, string $message = '', int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => $success, 'data' => $data, 'message' => $message]);
    exit;
}

/** JSON-Body eines POST lesen (Fehler → 400). */
function mkInput(): array
{
    $raw = file_get_contents('php://input');
    msv_debug_log('munitionskauf', 'Received data: ' . $raw);
    $input = json_decode($raw ?: '[]', true);
    if (json_last_error() !== JSON_ERROR_NONE) jsonResponse(false, null, 'Ungültige Anfrage: ' . json_last_error_msg(), 400);
    return is_array($input) ? $input : [];
}

/** Datumsbereich eines Filters (Kalenderwoche Mo–So, Monat) als [von, bis] oder null (ganzes Jahr). */
function mkZeitraum(string $filter): ?array
{
    switch ($filter) {
        case 'today': $t = date('Y-m-d'); return [$t, $t];
        case 'week':
            $tag = (int)date('N');   // 1 = Montag … 7 = Sonntag
            return [date('Y-m-d', strtotime('-' . ($tag - 1) . ' days')), date('Y-m-d', strtotime('+' . (7 - $tag) . ' days'))];
        case 'month': return [date('Y-m-01'), date('Y-m-t')];
        default: return null;
    }
}

try {
    $db = getDB();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_require(true);
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list_mitglieder':
            $rows = $db->query("SELECT ID AS id, Vorname, Name FROM mitglieder WHERE Status = 1 ORDER BY Name, Vorname")->fetchAll();
            jsonResponse(true, $rows);

        case 'save_bestellung':
            $input      = mkInput();
            $jahr       = (int)($input['jahr'] ?? date('Y'));
            $kaufDatum  = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($input['kauf_datum'] ?? '')) ? $input['kauf_datum'] : date('Y-m-d');
            $anlass     = mb_substr(trim((string)($input['anlass'] ?? '')), 0, 100);
            $mitgliedId = (int)($input['mitglied_id'] ?? 0) ?: null;
            $gastName   = mb_substr(trim((string)($input['gast_name'] ?? '')), 0, 100) ?: null;
            $munition   = is_array($input['munition'] ?? null) ? $input['munition'] : [];
            if (!$mitgliedId && !$gastName) jsonResponse(false, null, 'Kein Käufer angegeben', 422);
            if (!$munition) jsonResponse(false, null, 'Keine Munition ausgewählt', 422);

            $gp11 = 0; $gp90 = 0; $total = 0; $details = [];
            foreach ($munition as $item) {
                $anzahl = (int)($item['anzahl'] ?? 0);
                $typ    = mb_substr(trim((string)($item['typ'] ?? '')), 0, 50);
                if ($anzahl <= 0 || $typ === '') continue;
                if (str_contains($typ, 'GP11')) $gp11 += $anzahl; elseif (str_contains($typ, 'GP90')) $gp90 += $anzahl;
                $total += $anzahl * MK_RAPPEN_PRO_SCHUSS;
                $details[] = [$typ, $anzahl];
            }
            if (!$details) jsonResponse(false, null, 'Keine Munition ausgewählt', 422);

            $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO munitionskauf (jahr, kauf_datum, anlass, mitglied_id, gast_name, gp11_total, gp90_total, total_preis, created_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())")
                   ->execute([$jahr, $kaufDatum, $anlass, $mitgliedId, $gastName, $gp11, $gp90, $total]);
                $bestellungId = (int)$db->lastInsertId();
                $ins = $db->prepare("INSERT INTO munitionskauf_details (bestellung_id, typ, anzahl, preis_pro_schuss) VALUES (?, ?, ?, ?)");
                foreach ($details as [$typ, $anzahl]) $ins->execute([$bestellungId, $typ, $anzahl, MK_RAPPEN_PRO_SCHUSS]);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }
            jsonResponse(true, ['id' => $bestellungId], 'Bestellung erfolgreich gespeichert');

        case 'get_bestellungen':
            $jahr   = (int)($_GET['jahr'] ?? date('Y'));
            $filter = (string)($_GET['filter'] ?? 'today');
            $zr     = mkZeitraum($filter);
            msv_debug_log('munitionskauf', "getBestellungen - Jahr: $jahr, Filter: $filter" . ($zr ? " ({$zr[0]} bis {$zr[1]})" : ''));
            $where  = 'munitionskauf.jahr = ?' . ($zr ? ' AND munitionskauf.kauf_datum BETWEEN ? AND ?' : '');
            $params = $zr ? [$jahr, $zr[0], $zr[1]] : [$jahr];

            $st = $db->prepare("SELECT munitionskauf.*, COALESCE(CONCAT(mitglieder.Name, ' ', mitglieder.Vorname), munitionskauf.gast_name) AS kaeufer_name
                                FROM munitionskauf LEFT JOIN mitglieder ON munitionskauf.mitglied_id = mitglieder.ID
                                WHERE $where ORDER BY munitionskauf.kauf_datum DESC, munitionskauf.created_at DESC");
            $st->execute($params);
            $bestellungen = $st->fetchAll();

            $st = $db->prepare("SELECT COALESCE(SUM(gp11_total), 0) AS gp11_total, COALESCE(SUM(gp90_total), 0) AS gp90_total, COALESCE(SUM(total_preis), 0) AS total_preis
                                FROM munitionskauf WHERE $where");
            $st->execute($params);
            $totals = $st->fetch() ?: [];
            msv_debug_log('munitionskauf', 'Found ' . count($bestellungen) . " records for filter '$filter'");
            // Struktur wie bisher: JS erwartet data (Liste) und totals auf oberster Ebene
            echo json_encode(['success' => true, 'data' => $bestellungen,
                              'totals' => ['gp11_total' => (int)($totals['gp11_total'] ?? 0), 'gp90_total' => (int)($totals['gp90_total'] ?? 0), 'total_preis' => (float)($totals['total_preis'] ?? 0)]]);
            exit;

        case 'delete_bestellung':
            $id = (int)(mkInput()['id'] ?? 0);
            if ($id <= 0) jsonResponse(false, null, 'Ungültige ID', 422);
            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM munitionskauf_details WHERE bestellung_id = ?")->execute([$id]);
                $st = $db->prepare("DELETE FROM munitionskauf WHERE id = ?");
                $st->execute([$id]);
                if ($st->rowCount() === 0) { $db->rollBack(); jsonResponse(false, null, 'Bestellung nicht gefunden', 404); }
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                throw $e;
            }
            jsonResponse(true, null, 'Bestellung gelöscht');

        case 'get_statistics':
            $jahr  = (int)($_GET['jahr'] ?? date('Y'));
            $summe = function (?array $zr) use ($db, $jahr): float {
                $st = $db->prepare("SELECT COALESCE(SUM(total_preis), 0) FROM munitionskauf WHERE jahr = ?" . ($zr ? ' AND kauf_datum BETWEEN ? AND ?' : ''));
                $st->execute($zr ? [$jahr, $zr[0], $zr[1]] : [$jahr]);
                return (float)$st->fetchColumn();
            };
            $stats = ['today' => $summe(mkZeitraum('today')), 'week' => $summe(mkZeitraum('week')), 'month' => $summe(mkZeitraum('month')), 'year' => $summe(null)];
            $st = $db->prepare("SELECT COALESCE(CONCAT(mitglieder.Name, ' ', mitglieder.Vorname), munitionskauf.gast_name) AS name, SUM(munitionskauf.total_preis) AS total
                                FROM munitionskauf LEFT JOIN mitglieder ON munitionskauf.mitglied_id = mitglieder.ID
                                WHERE munitionskauf.jahr = ?
                                GROUP BY munitionskauf.mitglied_id, munitionskauf.gast_name, mitglieder.Name, mitglieder.Vorname
                                ORDER BY total DESC LIMIT 5");
            $st->execute([$jahr]);
            $stats['top_buyers'] = $st->fetchAll();
            jsonResponse(true, $stats);

        default:
            jsonResponse(false, null, 'Ungültige Aktion', 400);
    }
} catch (Throwable $e) {
    error_log('[munitionskauf_api] ' . $e->getMessage());
    jsonResponse(false, null, 'Systemfehler beim Verarbeiten der Anfrage', 500);
}
