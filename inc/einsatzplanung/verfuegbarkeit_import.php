<?php
/**
 * inc/einsatzplanung/verfuegbarkeit_import.php – Verfügbarkeiten aus der Portal-Umfrage oder aus dem
 * Excel «Personalanfrage» (je Verein) übernehmen.
 *
 * POST action=umfrage      plan_id                       → Antworten der verknüpften Umfrage (einsatz_plaene.umfrage_id)
 *                          Rollenfrage = Checkbox, deren Optionen Anfrage-Rollen sind; Terminfrage = Checkbox mit Datum;
 *                          Textfrage = Bemerkung. Upsert je Mitglied (quelle 'umfrage'); manuell erfasste Zeilen bleiben.
 * POST action=excel_parse  plan_id, verein, datei (.xlsx) → Vorschau: [{name, verein, mitglied_id, match, rollen, termin_ids, unbekannt}]
 * POST action=excel_save   plan_id, verein, zeilen = JSON der (ggf. korrigierten) Vorschau → Upsert (quelle 'excel')
 * Antwort: {success, message, …}
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/plan_helpers.inc.php';
require_once __DIR__ . '/../einsatzplan_parser/name_matcher.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ep_json(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$db     = getDB();
$action = $_POST['action'] ?? '';
$planId = (int)($_POST['plan_id'] ?? 0);
$plan   = $planId > 0 ? ep_plan_laden($db, $planId) : null;
if (!$plan) ep_json(['success' => false, 'message' => 'Plan nicht gefunden'], 404);

/** Upsert einer Verfügbarkeit; manuell erfasste Zeilen werden nicht überschrieben. Rückgabe: neu|aktualisiert|manuell */
function ep_verf_upsert(PDO $db, int $planId, string $verein, ?int $mid, string $nameText, array $rollen, array $tids, ?string $bemerkung, string $quelle): string
{
    $sel = $db->prepare("SELECT id, quelle FROM einsatz_plan_verfuegbarkeit WHERE plan_id = ? AND " . ($mid ? "mitglied_id = ?" : "mitglied_id IS NULL AND name_text = ?"));
    $sel->execute([$planId, $mid ?: $nameText]);
    $alt = $sel->fetch();
    $rollenJson = json_encode(array_values($rollen), JSON_UNESCAPED_UNICODE); $tidsJson = json_encode(array_values($tids));
    if ($alt) {
        if ($alt['quelle'] === 'manuell') return 'manuell';
        $db->prepare("UPDATE einsatz_plan_verfuegbarkeit SET verein = ?, rollen = ?, termin_ids = ?, bemerkung = ?, quelle = ? WHERE id = ?")
           ->execute([$verein, $rollenJson, $tidsJson, $bemerkung, $quelle, $alt['id']]);
        return 'aktualisiert';
    }
    $db->prepare("INSERT INTO einsatz_plan_verfuegbarkeit (plan_id, verein, mitglied_id, name_text, rollen, termin_ids, bemerkung, quelle) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([$planId, $verein, $mid ?: null, $mid ? null : $nameText, $rollenJson, $tidsJson, $bemerkung, $quelle]);
    return 'neu';
}

try {
    // ---- Umfrage ---------------------------------------------------------------------------
    if ($action === 'umfrage') {
        $umfrageId = (int)($plan['umfrage_id'] ?? 0);
        if ($umfrageId <= 0) ep_json(['success' => false, 'message' => 'Dem Plan ist keine Umfrage zugeordnet (Titel / Fusstext → Umfrage)'], 422);
        $fragen = $db->prepare("SELECT id, frage_text, frage_typ, optionen FROM umfragen_fragen WHERE umfrage_id = ? ORDER BY reihenfolge, id");
        $fragen->execute([$umfrageId]);
        $rollenFrage = null; $terminFrage = null; $textFrage = null;
        foreach ($fragen->fetchAll() as $f) {
            if ($f['frage_typ'] === 'text') { $textFrage ??= (int)$f['id']; continue; }
            if ($f['frage_typ'] !== 'checkbox') continue;
            $opts = (array)json_decode((string)$f['optionen'], true);
            $nRolle = 0; $nDatum = 0;
            foreach ($opts as $o) { if (ep_rolle_aus_text((string)$o) !== null) $nRolle++; if (preg_match('/\d{1,2}[.,]\d{1,2}[.,]\d{4}/', (string)$o)) $nDatum++; }
            if ($nDatum > 0 && $nDatum >= $nRolle && $terminFrage === null) $terminFrage = (int)$f['id'];
            elseif ($nRolle > 0 && $rollenFrage === null) $rollenFrage = (int)$f['id'];
        }
        if (!$rollenFrage || !$terminFrage) ep_json(['success' => false, 'message' => 'Umfrage hat keine erkennbare Rollen- und Terminfrage (Checkbox mit Rollen bzw. mit Datum)'], 422);

        $st = $db->prepare("SELECT ua.frage_id, ua.antwort, ua.mitglied_id FROM umfragen_antworten ua WHERE ua.umfrage_id = ? ORDER BY ua.mitglied_id");
        $st->execute([$umfrageId]);
        $proMitglied = [];
        foreach ($st->fetchAll() as $a) {
            $mid = (int)$a['mitglied_id'];
            $proMitglied[$mid] ??= ['rollen' => [], 'tids' => [], 'bemerkung' => null, 'unbekannt' => []];
            $fid = (int)$a['frage_id'];
            if ($fid === $rollenFrage) {
                foreach ((array)json_decode((string)$a['antwort'], true) as $o) { $r = ep_rolle_aus_text((string)$o); if ($r) $proMitglied[$mid]['rollen'][] = $r; else $proMitglied[$mid]['unbekannt'][] = (string)$o; }
            } elseif ($fid === $terminFrage) {
                foreach ((array)json_decode((string)$a['antwort'], true) as $o) { $t = ep_termin_aus_text($plan, (string)$o); if ($t) $proMitglied[$mid]['tids'][] = $t; else $proMitglied[$mid]['unbekannt'][] = (string)$o; }
            } elseif ($textFrage && $fid === $textFrage) {
                $proMitglied[$mid]['bemerkung'] = mb_substr(trim((string)$a['antwort']), 0, 255) ?: null;
            }
        }
        $stat = ['neu' => 0, 'aktualisiert' => 0, 'manuell' => 0]; $hinweise = [];
        $mitglieder = ep_mitglieder_map($db);
        foreach ($proMitglied as $mid => $d) {
            if (!isset($mitglieder[$mid])) continue;
            $res = ep_verf_upsert($db, $planId, 'msv', $mid, '', array_unique($d['rollen']), array_unique($d['tids']), $d['bemerkung'], 'umfrage');
            $stat[$res]++;
            if ($d['unbekannt']) $hinweise[] = ep_name_vorname($mitglieder[$mid]) . ': nicht zugeordnet – ' . implode(', ', array_unique($d['unbekannt']));
            if (!$d['rollen'] || !$d['tids']) $hinweise[] = ep_name_vorname($mitglieder[$mid]) . ': ' . (!$d['rollen'] ? 'keine Rolle' : 'kein Termin') . ($d['bemerkung'] ? ' («' . $d['bemerkung'] . '»)' : '');
        }
        ep_json(['success' => true, 'message' => 'Umfrage übernommen: ' . $stat['neu'] . ' neu, ' . $stat['aktualisiert'] . ' aktualisiert' . ($stat['manuell'] ? ', ' . $stat['manuell'] . ' manuell erfasste unverändert' : ''),
                 'statistik' => $stat, 'hinweise' => $hinweise]);
    }

    // ---- Excel Personalanfrage: Vorschau -------------------------------------------------------
    $verein = $_POST['verein'] ?? 'msv';
    if (!isset(EP_VEREINE[$verein])) ep_json(['success' => false, 'message' => 'Ungültiger Verein'], 422);

    if ($action === 'excel_parse') {
        require_once __DIR__ . '/../lib/dokument_datei.inc.php';
        require_once __DIR__ . '/../einsatzplan_parser/personalanfrage_parser.php';
        if (empty($_FILES['datei'])) ep_json(['success' => false, 'message' => 'Keine Datei hochgeladen'], 422);
        $check = dokument_datei_pruefen($_FILES['datei']);
        if (!$check['ok']) ep_json(['success' => false, 'message' => $check['message']], 422);
        if ($check['ext'] !== 'xlsx') ep_json(['success' => false, 'message' => 'Bitte die Personalanfrage als .xlsx hochladen'], 422);
        $res = parsePersonalanfrageXlsx($_FILES['datei']['tmp_name']);
        if (!$res['success']) ep_json(['success' => false, 'message' => $res['message']], 422);

        $warnungen = [];
        foreach ($res['schichten'] as $t) if (ep_termin_aus_text($plan, $t) === null) $warnungen[] = 'Schicht «' . $t . '» passt zu keinem Termin des Plans';
        foreach ($res['rollen'] as $t) if (ep_rolle_aus_text($t) === null) $warnungen[] = 'Rolle «' . $t . '» unbekannt';
        $gematcht = $verein === 'msv' ? matchMitglieder($db, array_map(fn($z) => ['mitglied_name' => $z['name']], $res['zeilen'])) : [];
        $vorschau = [];
        foreach ($res['zeilen'] as $i => $z) {
            $rollen = array_values(array_unique(array_filter(array_map('ep_rolle_aus_text', $z['rollen']))));
            $tids = array_values(array_unique(array_filter(array_map(fn($t) => ep_termin_aus_text($plan, $t), $z['schichten']))));
            $m = $gematcht[$i] ?? null;
            $vorschau[] = ['name' => $z['name'], 'verein' => $verein, 'mitglied_id' => $m && !empty($m['mitglied_id']) ? (int)$m['mitglied_id'] : 0,
                           'match' => $m ? ($m['match_status'] ?? 'none') : '-', 'matched_name' => $m['matched_name'] ?? '', 'rollen' => $rollen, 'termin_ids' => $tids,
                           'hinweis' => (!$rollen ? 'keine Rolle ' : '') . (!$tids ? 'keine Schicht' : '')];
        }
        ep_json(['success' => true, 'message' => $res['message'], 'vorschau' => $vorschau, 'warnungen' => $warnungen]);
    }

    // ---- Excel Personalanfrage: Übernehmen ---------------------------------------------------
    if ($action === 'excel_save') {
        $zeilen = json_decode((string)($_POST['zeilen'] ?? '[]'), true);
        if (!is_array($zeilen) || !$zeilen) ep_json(['success' => false, 'message' => 'Keine Zeilen übergeben'], 422);
        $terminIds = array_map(fn($t) => (int)$t['id'], $plan['termine']);
        $mitglieder = ep_mitglieder_map($db);
        $stat = ['neu' => 0, 'aktualisiert' => 0, 'manuell' => 0]; $n = 0;
        foreach ($zeilen as $z) {
            $name = mb_substr(trim(preg_replace('/\s+/', ' ', (string)($z['name'] ?? ''))), 0, 100);
            $mid = $verein === 'msv' ? (int)($z['mitglied_id'] ?? 0) : 0;
            if ($mid > 0 && !isset($mitglieder[$mid])) $mid = 0;
            if ($mid <= 0 && $name === '') continue;
            $rollen = array_values(array_unique(array_filter((array)($z['rollen'] ?? []), fn($r) => isset(EP_ROLLEN[$r]))));
            $tids = array_values(array_unique(array_filter(array_map('intval', (array)($z['termin_ids'] ?? [])), fn($t) => in_array($t, $terminIds, true))));
            $stat[ep_verf_upsert($db, $planId, $verein, $mid ?: null, $name, $rollen, $tids, null, 'excel')]++;
            $n++;
        }
        ep_json(['success' => true, 'message' => $n . ' Zeile(n) übernommen: ' . $stat['neu'] . ' neu, ' . $stat['aktualisiert'] . ' aktualisiert' . ($stat['manuell'] ? ', ' . $stat['manuell'] . ' manuell erfasste unverändert' : ''), 'statistik' => $stat]);
    }

    ep_json(['success' => false, 'message' => 'Unbekannte Aktion'], 400);
} catch (Throwable $e) {
    error_log('[einsatzplanung/verfuegbarkeit_import] ' . $e->getMessage());
    ep_json(['success' => false, 'message' => 'Import fehlgeschlagen: ' . $e->getMessage()], 500);
}
