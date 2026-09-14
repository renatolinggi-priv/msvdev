<?php
/**
 * endschloesen_api.php – Backend für «Endschiessen – Stiche lösen» (endschloesen.php)
 *
 * Aktionen (GET ?action=…, schreibende per POST mit JSON-Body + X-CSRF-TOKEN):
 *   list_stiche, list_mitglieder, list_waffen, get_spezialpreise
 *   get_selection      (mitglied_id | gast_id | gast_name, jahr)  -> Stiche, Zahlung, Zusatzmunition
 *   get_zusatz_schuesse (Kompatibilität; Inhalt steckt auch in get_selection)
 *   get_year_details   (jahr)                                     -> Matrix für die Übersichtstabelle
 *   save_selection     POST                                       -> Stiche + Zusatzmunition speichern
 *   delete_selection   POST
 *   get_stich_definitions, update_stich_definition POST, update_spezialpreis POST
 *
 * Grundsätze (Überarbeitung 09.2026):
 *   - Zugriff nur für Admin-Bereich (adminApiGuard), CSRF bei POST.
 *   - Preise werden AUSSCHLIESSLICH serverseitig berechnet (preislogik.inc.php);
 *     der Client schickt keinen Preis mehr mit.
 *   - Keine Schema-Änderungen zur Laufzeit mehr (siehe migrations/045_endschiessen_schema.sql).
 *   - Geburtsdatum von Jungschützen als eigenes Feld, nicht im Namen.
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/preislogik.inc.php';

adminApiGuard('json');

if (!isset($conn) || $conn->connect_error) {
    jsonResponse(false, null, 'Datenbankverbindung fehlgeschlagen');
}
$conn->set_charset('utf8mb4');

// ---------------------------------------------------------------------------
// Helfer
// ---------------------------------------------------------------------------
function jsonResponse(bool $success, $data = null, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'data' => $data, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function requestInput(): array
{
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $in = json_decode(file_get_contents('php://input'), true);
        return is_array($in) ? $in : [];
    }
    return $_POST;
}

function checkCSRF(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, null, 'POST erwartet');
    }
    csrf_require(true);
}

/** Prepared Statement mit dynamischer Parameterliste ausführen und Ergebnis liefern. */
function q(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare fehlgeschlagen: ' . $conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('Ausführung fehlgeschlagen: ' . $stmt->error);
    }
    return $stmt;
}

function rows(mysqli_stmt $stmt): array
{
    $out = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    return $out;
}

/** Munitionsart aus Waffe ableiten (GP11 / GP90 / null). */
function endschAmmoPref(?int $waffeId, ?string $bez, ?string $kat): ?string
{
    $map = [1 => 'GP11', 2 => 'GP90']; // 1 = Standardgewehr 300m, 2 = Stgw90
    if ($waffeId && isset($map[$waffeId])) {
        return $map[$waffeId];
    }
    $text = mb_strtolower(trim(($kat ?? '') . ' ' . ($bez ?? '')), 'UTF-8');
    if (preg_match('/\b(stgw|stg)\s*90\b|\bpe\s*90\b|\b(sg|sig)\s*550\b|\bgp\s*90\b|\b5\.56\b|\b\.?223\b/', $text)) {
        return 'GP90';
    }
    if (preg_match('/\b(stgw|stg)\s*57\b|\bk[\s-]?31\b|\bkarabiner\s*31\b|\bk[\s-]?11\b|\bg[\s-]?11\b|\bmousqueton\b|\bordonn?anz\b|\bgp\s*11\b|\bstandardgewehr\b|\bstdg\b/', $text)) {
        return 'GP11';
    }
    return null;
}

/** Gast nach id oder (name, jahr) laden. */
function findeGast(mysqli $conn, int $gastId, string $gastName, int $jahr): ?array
{
    if ($gastId > 0) {
        $r = rows(q($conn, "SELECT id, name, geburtsdatum, waffen_id, jahr FROM endstich_gaeste WHERE id = ?", 'i', [$gastId]));
    } elseif ($gastName !== '') {
        $r = rows(q($conn, "SELECT id, name, geburtsdatum, waffen_id, jahr FROM endstich_gaeste WHERE name = ? AND jahr = ?", 'si', [$gastName, $jahr]));
    } else {
        return null;
    }
    return $r[0] ?? null;
}

/** Zusatzmunition eines Teilnehmers. */
function ladeZusatz(mysqli $conn, string $spalte, int $id, int $jahr): array
{
    return rows(q($conn, "SELECT typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE $spalte = ? AND jahr = ?", 'ii', [$id, $jahr]));
}

/**
 * Spalte endstich_selection.waffen_id vorhanden? (Migration 047)
 * Bis die Migration gelaufen ist, arbeitet die API ohne die Spalte weiter
 * (Waffe dann nur aus den Stammdaten).
 */
function endschHatWaffeSpalte(mysqli $conn): bool
{
    static $hat = null;
    if ($hat === null) {
        $res = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'waffen_id'");
        $hat = $res ? $res->num_rows > 0 : false;
    }
    return $hat;
}

/** Waffen-Stammdaten als Map ID => ['bezeichnung' => …, 'kategorie' => …]. */
function endschWaffenMap(mysqli $conn): array
{
    $map = [];
    foreach (rows(q($conn, "SELECT ID AS id, Bezeichnung AS bezeichnung, Kategorie AS kategorie FROM Waffen")) as $w) {
        $map[(int)$w['id']] = $w;
    }
    return $map;
}

// ---------------------------------------------------------------------------
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // -------------------------------------------------------------- Stammdaten
        case 'list_stiche':
            $r = rows(q($conn, "SELECT id, code, name, shots, price_cents, sort_order FROM endstich_definition WHERE active = 1 ORDER BY sort_order, name"));
            jsonResponse(true, $r);

        case 'get_stich_definitions':
            $r = rows(q($conn, "SELECT id, code, name, shots, price_cents, sort_order, active FROM endstich_definition ORDER BY sort_order, name"));
            jsonResponse(true, $r);

        case 'list_mitglieder':
            $r = rows(q($conn, "SELECT m.ID AS id, m.Name AS Nachname, m.Vorname, m.WaffenID AS waffe_id, w.Bezeichnung AS waffe_bez, w.Kategorie AS waffe_kat
                                FROM mitglieder m LEFT JOIN Waffen w ON w.ID = m.WaffenID
                                WHERE COALESCE(m.Verstorben, 0) != 1 ORDER BY m.Name, m.Vorname"));
            jsonResponse(true, $r);

        case 'list_waffen':
            $r = rows(q($conn, "SELECT id AS ID, bezeichnung AS Bezeichnung, kategorie AS Kategorie FROM Waffen ORDER BY kategorie, bezeichnung"));
            jsonResponse(true, $r);

        case 'get_spezialpreise':
            // Format wie bisher: typ => Zeile (Frontend liest .price_cents)
            $out = [];
            foreach (endschLadeSpezialpreise($conn) as $typ => $cents) {
                $out[$typ] = ['typ' => $typ, 'price_cents' => $cents];
            }
            jsonResponse(true, $out);

        case 'update_spezialpreis':
            checkCSRF();
            $in  = requestInput();
            $typ = trim((string)($in['typ'] ?? ''));
            $cents = (int)($in['price_cents'] ?? 0);
            if ($typ === '' || !array_key_exists($typ, ENDSCH_PREIS_DEFAULTS)) {
                jsonResponse(false, null, 'Unbekannter Preistyp');
            }
            q($conn, "INSERT INTO endstich_spezialpreise (typ, price_cents, sort_order, active) VALUES (?, ?, 100, 1)
                      ON DUPLICATE KEY UPDATE price_cents = VALUES(price_cents)", 'si', [$typ, $cents]);
            jsonResponse(true, ['typ' => $typ, 'price_cents' => $cents], 'Preis aktualisiert');

        case 'update_stich_definition':
            checkCSRF();
            $in = requestInput();
            $id = (int)($in['id'] ?? 0);
            $name  = trim((string)($in['name'] ?? ''));
            $shots = (int)($in['shots'] ?? 0);
            $price = (int)($in['price_cents'] ?? 0);
            $sort  = (int)($in['sort_order'] ?? 100);
            $active = !empty($in['active']) ? 1 : 0;

            if ($id === 0) {
                $code = strtoupper(trim((string)($in['code'] ?? '')));
                if ($code === '' || $name === '') {
                    jsonResponse(false, null, 'Code und Name sind erforderlich');
                }
                if (!preg_match('/^[A-Z0-9_]{2,50}$/', $code)) {
                    jsonResponse(false, null, 'Code nur aus Grossbuchstaben, Ziffern und Unterstrich');
                }
                if (rows(q($conn, "SELECT id FROM endstich_definition WHERE code = ?", 's', [$code]))) {
                    jsonResponse(false, null, 'Ein Stich mit diesem Code existiert bereits');
                }
                q($conn, "INSERT INTO endstich_definition (code, name, shots, price_cents, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)",
                    'ssiiii', [$code, $name, $shots, $price, $sort, $active]);
                jsonResponse(true, ['id' => $conn->insert_id], 'Neuer Stich erstellt');
            }
            if ($name === '') {
                jsonResponse(false, null, 'Name ist erforderlich');
            }
            q($conn, "UPDATE endstich_definition SET name = ?, shots = ?, price_cents = ?, sort_order = ?, active = ? WHERE id = ?",
                'siiiii', [$name, $shots, $price, $sort, $active, $id]);
            jsonResponse(true, ['id' => $id], 'Stich aktualisiert');

        // -------------------------------------------------------------- Auswahl lesen
        case 'get_selection':
        case 'get_zusatz_schuesse':
            $mitgliedId = (int)($_GET['mitglied_id'] ?? 0);
            $gastId     = (int)($_GET['gast_id'] ?? 0);
            $gastName   = trim((string)($_GET['gast_name'] ?? ''));
            $jahr       = (int)($_GET['jahr'] ?? date('Y'));

            if ($mitgliedId) {
                $spalte = 'mitglied_id';
                $id = $mitgliedId;
                $extra = ['typ' => 'mitglied'];
            } else {
                $gast = findeGast($conn, $gastId, $gastName, $jahr);
                if (!$gast) {
                    jsonResponse(true, [], '', ['gefunden' => false]);
                }
                $spalte = 'gast_id';
                $id = (int)$gast['id'];
                $extra = [
                    'typ'          => endschTeilnehmerTyp($gast['geburtsdatum']),
                    'gast_id'      => $id,
                    'gast_name'    => $gast['name'],
                    'geburtsdatum' => $gast['geburtsdatum'],
                    'waffen_id'    => $gast['waffen_id'] ? (int)$gast['waffen_id'] : null,
                ];
            }

            $zusatz = ladeZusatz($conn, $spalte, $id, $jahr);
            if ($action === 'get_zusatz_schuesse') {
                jsonResponse(true, $zusatz);
            }

            $waffeCol = endschHatWaffeSpalte($conn) ? 'es.waffen_id' : 'NULL AS waffen_id';
            $sel = rows(q($conn, "SELECT es.stich_id, es.zahlungsmethode, es.sie_und_er, $waffeCol, ed.code
                                  FROM endstich_selection es JOIN endstich_definition ed ON ed.id = es.stich_id
                                  WHERE es.$spalte = ? AND es.jahr = ?", 'ii', [$id, $jahr]));
            $ids = [];
            $zahlung = null;
            $zabigPartner = false;
            $selWaffe = null;
            foreach ($sel as $s) {
                $ids[] = (int)$s['stich_id'];
                if (!empty($s['zahlungsmethode'])) {
                    $zahlung = $s['zahlungsmethode'];
                }
                if ($s['code'] === 'ZABIG' && (int)$s['sie_und_er'] === 1) {
                    $zabigPartner = true;
                }
                if (!empty($s['waffen_id'])) {
                    $selWaffe = (int)$s['waffen_id'];
                }
            }
            // Bei der Lösung gewählte Waffe hat Vorrang vor den Stammdaten des Gastes;
            // für Mitglieder kennt der Client die Stammdaten-Waffe aus list_mitglieder.
            $extra['waffen_id'] = $selWaffe ?? ($extra['waffen_id'] ?? null);
            $extra += [
                'gefunden'        => true,
                'zahlungsmethode' => $zahlung ?? 'karte',
                'zabig_partner'   => $zabigPartner,
                'is_js'           => ($extra['typ'] ?? '') === 'js',
                'zusatz'          => $zusatz,
            ];
            jsonResponse(true, $ids, '', $extra);

        // -------------------------------------------------------------- Auswahl speichern
        case 'save_selection':
            checkCSRF();
            $in = requestInput();

            $typ        = (string)($in['typ'] ?? '');
            $mitgliedId = (int)($in['mitglied_id'] ?? 0);
            $gastId     = (int)($in['gast_id'] ?? 0);
            $gastName   = trim((string)($in['gast_name'] ?? ''));
            $geburt     = trim((string)($in['gast_geburtsdatum'] ?? ''));
            $waffenId   = isset($in['waffen_id']) && (int)$in['waffen_id'] > 0 ? (int)$in['waffen_id'] : null;
            $jahr       = (int)($in['jahr'] ?? date('Y'));
            $stichIds   = array_values(array_unique(array_filter(array_map('intval', (array)($in['stiche'] ?? [])))));
            $zahlung    = in_array($in['zahlungsmethode'] ?? '', ['bar', 'karte'], true) ? $in['zahlungsmethode'] : 'karte';
            $zabigPartner = !empty($in['zabig_partner']);
            $zusatzIn   = is_array($in['zusatz_schuesse'] ?? null) ? $in['zusatz_schuesse'] : [];

            // Altes Frontend-Format (Typ nicht mitgeschickt) tolerant ableiten
            if (!in_array($typ, ['mitglied', 'gast', 'js'], true)) {
                $typ = $mitgliedId ? 'mitglied' : ($geburt !== '' ? 'js' : 'gast');
            }
            if ($typ === 'mitglied' && !$mitgliedId) {
                jsonResponse(false, null, 'Kein Mitglied gewählt');
            }
            if ($typ !== 'mitglied' && $gastName === '' && !$gastId) {
                jsonResponse(false, null, 'Kein Gastname angegeben');
            }
            if ($typ === 'js' && $geburt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $geburt)) {
                jsonResponse(false, null, 'Geburtsdatum ungültig');
            }
            if ($jahr < 2000 || $jahr > (int)date('Y') + 1) {
                jsonResponse(false, null, 'Jahr ungültig');
            }
            if ($waffenId !== null && !rows(q($conn, "SELECT ID FROM Waffen WHERE ID = ?", 'i', [$waffenId]))) {
                jsonResponse(false, null, 'Waffe ungültig');
            }

            $stiche  = endschLadeStiche($conn);          // code => def
            $spezial = endschLadeSpezialpreise($conn);
            $idZuCode = [];
            foreach ($stiche as $code => $def) {
                $idZuCode[$def['id']] = $code;
            }

            // Nur bekannte, aktive und für den Typ erlaubte Stiche übernehmen
            $codes = [];
            foreach ($stichIds as $sid) {
                if (!isset($idZuCode[$sid])) {
                    continue;
                }
                $code = $idZuCode[$sid];
                if ($typ === 'js' && !in_array($code, ENDSCH_JS_PAKET_CODES, true)) {
                    continue;
                }
                if ($typ === 'gast' && !in_array($code, ENDSCH_GAST_ERLAUBT, true)) {
                    continue;
                }
                if ($typ === 'mitglied' && $code === 'PROBE') {
                    continue;
                }
                $codes[$sid] = $code;
            }
            $stichIds = array_keys($codes);
            // Waffe ist Pflicht, sobald Stiche gelöst werden (Standblatt-Platzhalter ${waffe})
            if ($stichIds && $waffenId === null) {
                jsonResponse(false, null, 'Keine Waffe gewählt');
            }
            $preis    = endschBerechnePreis(array_values($codes), $typ, $zabigPartner, $stiche, $spezial);
            $createdBy = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'system';
            $hatWaffeSpalte = endschHatWaffeSpalte($conn);

            $conn->begin_transaction();
            try {
                if ($typ === 'mitglied') {
                    if (!rows(q($conn, "SELECT ID FROM mitglieder WHERE ID = ?", 'i', [$mitgliedId]))) {
                        throw new RuntimeException('Ungültiges Mitglied');
                    }
                    $spalte = 'mitglied_id';
                    $id = $mitgliedId;
                } else {
                    // Legacy: Datum im Namen «Name (dd.mm.yyyy)» herauslösen
                    if (preg_match('/^(.*?)\s*\((\d{1,2})\.(\d{1,2})\.(\d{4})\)\s*$/', $gastName, $m)) {
                        $gastName = trim($m[1]);
                        if ($geburt === '') {
                            $geburt = sprintf('%04d-%02d-%02d', $m[4], $m[3], $m[2]);
                        }
                    }
                    $geburtDb = ($typ === 'js' && $geburt !== '') ? $geburt : null;
                    $gast = findeGast($conn, $gastId, $gastName, $jahr);
                    if ($gast) {
                        $id = (int)$gast['id'];
                        // Stammdaten des Gastes mitpflegen (Name-Änderung nur bei id-basiertem Aufruf)
                        $neuerName = ($gastId && $gastName !== '') ? $gastName : $gast['name'];
                        q($conn, "UPDATE endstich_gaeste SET name = ?, geburtsdatum = ?, waffen_id = ? WHERE id = ?",
                            'ssii', [$neuerName, $geburtDb, $waffenId, $id]);
                    } else {
                        q($conn, "INSERT INTO endstich_gaeste (name, geburtsdatum, waffen_id, jahr, created_by) VALUES (?, ?, ?, ?, ?)",
                            'ssiis', [$gastName, $geburtDb, $waffenId, $jahr, $createdBy]);
                        $id = (int)$conn->insert_id;
                    }
                    $spalte = 'gast_id';
                }

                // Bestehende Auswahl ermitteln
                $bestehend = array_map(fn($r) => (int)$r['stich_id'],
                    rows(q($conn, "SELECT stich_id FROM endstich_selection WHERE $spalte = ? AND jahr = ?", 'ii', [$id, $jahr])));
                $loeschen  = array_values(array_diff($bestehend, $stichIds));
                $anlegen   = array_values(array_diff($stichIds, $bestehend));
                $behalten  = array_values(array_intersect($bestehend, $stichIds));

                if ($loeschen) {
                    $ph = implode(',', array_fill(0, count($loeschen), '?'));
                    q($conn, "DELETE FROM endstich_selection WHERE $spalte = ? AND jahr = ? AND stich_id IN ($ph)",
                        'ii' . str_repeat('i', count($loeschen)), array_merge([$id, $jahr], $loeschen));
                }
                foreach ($anlegen as $sid) {
                    q($conn, "INSERT INTO endstich_selection ($spalte, jahr, stich_id, zahlungsmethode, created_by) VALUES (?, ?, ?, ?, ?)",
                        'iiiss', [$id, $jahr, $sid, $zahlung, $createdBy]);
                }
                // Zahlungsart, Waffe, Partner-Flag und Gast-Gesamtpreis auf allen Zeilen konsistent setzen:
                // Partner-Flag nur am ZABIG, Gast-Spezialpreis genau EINMAL (kleinste stich_id).
                // waffen_id als geprüfter Integer direkt im SQL (kein NULL-Binding, Spalte erst ab Migration 047).
                $waffeSet = ($hatWaffeSpalte && $waffenId !== null) ? ', waffen_id = ' . (int)$waffenId : '';
                q($conn, "UPDATE endstich_selection SET zahlungsmethode = ?, sie_und_er = 0, gast_spezialpreis = NULL$waffeSet WHERE $spalte = ? AND jahr = ?",
                    'sii', [$zahlung, $id, $jahr]);
                if ($typ === 'mitglied' && $zabigPartner && isset($stiche['ZABIG'])) {
                    q($conn, "UPDATE endstich_selection SET sie_und_er = 1 WHERE mitglied_id = ? AND jahr = ? AND stich_id = ?",
                        'iii', [$id, $jahr, $stiche['ZABIG']['id']]);
                }
                if ($typ !== 'mitglied' && $stichIds) {
                    q($conn, "UPDATE endstich_selection SET gast_spezialpreis = ? WHERE gast_id = ? AND jahr = ? ORDER BY stich_id LIMIT 1",
                        'iii', [$preis, $id, $jahr]);
                }

                // Zusatzmunition komplett neu schreiben (Preis serverseitig)
                q($conn, "DELETE FROM endstich_zusatz_schuss WHERE $spalte = ? AND jahr = ?", 'ii', [$id, $jahr]);
                $erlaubteTypen = ['GP11_60', 'GP90_50', 'GP11_CUSTOM', 'GP90_CUSTOM'];
                foreach ($zusatzIn as $z) {
                    $ztyp = (string)($z['typ'] ?? '');
                    $anz  = (int)($z['anzahl'] ?? 0);
                    if (!in_array($ztyp, $erlaubteTypen, true) || $anz <= 0) {
                        continue;
                    }
                    q($conn, "INSERT INTO endstich_zusatz_schuss ($spalte, jahr, typ, anzahl, preis_cents, created_by) VALUES (?, ?, ?, ?, ?, ?)",
                        'iisiis', [$id, $jahr, $ztyp, $anz, endschZusatzPreis($anz, $spezial), $createdBy]);
                }

                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

            jsonResponse(true, [
                'typ'         => $typ,
                'entity_id'   => $id,
                'stiche'      => $stichIds,
                'preis_cents' => $preis,
                'zusatz_gespeichert' => count($zusatzIn),
                'zusatzmunition_pro_schuss' => $spezial['munition_pro_schuss'],
            ], count($stichIds) ? 'Gespeichert' : 'Auswahl geleert');

        // -------------------------------------------------------------- Löschen
        case 'delete_selection':
            checkCSRF();
            $in = requestInput();
            $entityId = (int)($in['entity_id'] ?? 0);
            $typ      = (string)($in['typ'] ?? '');
            $jahr     = (int)($in['jahr'] ?? date('Y'));
            if (!$entityId || !in_array($typ, ['mitglied', 'gast', 'js'], true)) {
                jsonResponse(false, null, 'Fehlende Parameter');
            }
            $spalte = $typ === 'mitglied' ? 'mitglied_id' : 'gast_id';
            $conn->begin_transaction();
            try {
                q($conn, "DELETE FROM endstich_selection WHERE $spalte = ? AND jahr = ?", 'ii', [$entityId, $jahr]);
                q($conn, "DELETE FROM endstich_zusatz_schuss WHERE $spalte = ? AND jahr = ?", 'ii', [$entityId, $jahr]);
                if ($spalte === 'gast_id') {
                    q($conn, "DELETE FROM endstich_gaeste WHERE id = ? AND jahr = ?", 'ii', [$entityId, $jahr]);
                }
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
            jsonResponse(true, null, 'Gelöscht');

        // -------------------------------------------------------------- Jahresübersicht
        case 'get_year_details':
            $jahr = (int)($_GET['jahr'] ?? date('Y'));
            $spezial = endschLadeSpezialpreise($conn);

            // Teilnehmer: Mitglieder und Gäste mit Stichen ODER Zusatzmunition
            $teilnehmer = rows(q($conn, "
                SELECT 'mitglied' COLLATE utf8mb4_general_ci AS typ, m.ID AS entity_id,
                       CONCAT(m.Name, ' ', m.Vorname) COLLATE utf8mb4_general_ci AS name, NULL AS geburtsdatum,
                       m.WaffenID AS waffe_id, w.Bezeichnung COLLATE utf8mb4_general_ci AS waffe_bez,
                       w.Kategorie COLLATE utf8mb4_general_ci AS waffe_kat, 1 AS sort_group
                FROM mitglieder m LEFT JOIN Waffen w ON w.ID = m.WaffenID
                WHERE m.ID IN (SELECT mitglied_id FROM endstich_selection WHERE jahr = ? AND mitglied_id IS NOT NULL
                               UNION SELECT mitglied_id FROM endstich_zusatz_schuss WHERE jahr = ? AND mitglied_id IS NOT NULL)
                UNION ALL
                SELECT 'gast' COLLATE utf8mb4_general_ci, g.id, g.name COLLATE utf8mb4_general_ci, g.geburtsdatum,
                       g.waffen_id, w2.Bezeichnung COLLATE utf8mb4_general_ci, w2.Kategorie COLLATE utf8mb4_general_ci, CASE WHEN g.geburtsdatum IS NOT NULL THEN 3 ELSE 2 END
                FROM endstich_gaeste g LEFT JOIN Waffen w2 ON w2.ID = g.waffen_id
                WHERE g.jahr = ? AND g.id IN (SELECT gast_id FROM endstich_selection WHERE jahr = ? AND gast_id IS NOT NULL
                                              UNION SELECT gast_id FROM endstich_zusatz_schuss WHERE jahr = ? AND gast_id IS NOT NULL)
                ORDER BY sort_group, name", 'iiiii', [$jahr, $jahr, $jahr, $jahr, $jahr]));

            // Alle Stiche und Zusatzmunition des Jahres in je EINER Abfrage
            $waffeCol  = endschHatWaffeSpalte($conn) ? 'es.waffen_id' : 'NULL AS waffen_id';
            $waffenMap = endschWaffenMap($conn);
            $selektionen = rows(q($conn, "SELECT es.mitglied_id, es.gast_id, es.stich_id, es.zahlungsmethode, es.sie_und_er, es.gast_spezialpreis, $waffeCol,
                                                  ed.code, ed.shots, ed.price_cents
                                           FROM endstich_selection es JOIN endstich_definition ed ON ed.id = es.stich_id
                                           WHERE es.jahr = ? ORDER BY es.stich_id", 'i', [$jahr]));
            $zusaetze = rows(q($conn, "SELECT mitglied_id, gast_id, typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE jahr = ?", 'i', [$jahr]));

            $key = fn(string $typ, int $id) => ($typ === 'mitglied' ? 'm' : 'g') . $id;
            $selByKey = $zusByKey = [];
            foreach ($selektionen as $s) {
                $k = $s['mitglied_id'] ? 'm' . (int)$s['mitglied_id'] : 'g' . (int)$s['gast_id'];
                $selByKey[$k][] = $s;
            }
            foreach ($zusaetze as $z) {
                $k = $z['mitglied_id'] ? 'm' . (int)$z['mitglied_id'] : 'g' . (int)$z['gast_id'];
                $zusByKey[$k][] = $z;
            }

            $details = [];
            foreach ($teilnehmer as $t) {
                $istMitglied = $t['typ'] === 'mitglied';
                $teilTyp = $istMitglied ? 'mitglied' : endschTeilnehmerTyp($t['geburtsdatum']);
                $e = [
                    'typ'          => $t['typ'],
                    'teilnehmer_typ' => $teilTyp,
                    'entity_id'    => (int)$t['entity_id'],
                    'mitglied_id'  => (int)$t['entity_id'],
                    'name'         => $t['name'],
                    'geburtsdatum' => $t['geburtsdatum'],
                    'waffe_id'     => $t['waffe_id'] ? (int)$t['waffe_id'] : null,
                    'waffe_bez'    => $t['waffe_bez'],
                    'waffe_kat'    => $t['waffe_kat'],
                    'stiche'       => [],
                    'partner_stiche' => [],
                    'zusatz_schuesse' => [],
                    'total_shots'  => 0,
                    'total_price'  => 0,
                    'zahlungsmethode' => null,
                    'munition_schuss' => 0,
                    'munition_preis'  => 0,
                    'stich_gp11' => 0, 'stich_gp90' => 0, 'zusatz_gp11' => 0, 'zusatz_gp90' => 0,
                ];
                $k = $key($t['typ'], (int)$t['entity_id']);
                // Bei der Lösung gewählte Waffe (Migration 047) hat Vorrang vor den Stammdaten
                foreach ($selByKey[$k] ?? [] as $s) {
                    if (!empty($s['waffen_id']) && isset($waffenMap[(int)$s['waffen_id']])) {
                        $w = $waffenMap[(int)$s['waffen_id']];
                        $e['waffe_id']  = (int)$s['waffen_id'];
                        $e['waffe_bez'] = $w['bezeichnung'];
                        $e['waffe_kat'] = $w['kategorie'];
                        break;
                    }
                }
                $ammo = endschAmmoPref($e['waffe_id'], $e['waffe_bez'], $e['waffe_kat']);

                $gastPreisGesetzt = false;
                $codes = [];
                $zabigPartner = false;
                foreach ($selByKey[$k] ?? [] as $s) {
                    if ($s['code'] === 'PROBE' && $teilTyp !== 'js') {
                        continue;
                    }
                    $e['stiche'][] = (int)$s['stich_id'];
                    $codes[] = $s['code'];
                    $e['total_shots'] += (int)$s['shots'];
                    if ($ammo === 'GP11') { $e['stich_gp11'] += (int)$s['shots']; }
                    if ($ammo === 'GP90') { $e['stich_gp90'] += (int)$s['shots']; }
                    if (!empty($s['zahlungsmethode'])) { $e['zahlungsmethode'] = $s['zahlungsmethode']; }
                    if ($s['code'] === 'ZABIG' && (int)$s['sie_und_er'] === 1) {
                        $zabigPartner = true;
                        $e['partner_stiche'][] = (int)$s['stich_id'];
                    }
                    if (!$istMitglied && !$gastPreisGesetzt && $s['gast_spezialpreis'] !== null) {
                        $e['total_price'] = (int)$s['gast_spezialpreis'];
                        $gastPreisGesetzt = true;
                    }
                }
                if ($istMitglied) {
                    // Mitglieder: Einzelpreise aus der Definition, Partner-Zabig aus den Spezialpreisen
                    $defs = [];
                    foreach ($selByKey[$k] ?? [] as $s) {
                        $defs[$s['code']] = ['id' => (int)$s['stich_id'], 'price_cents' => (int)$s['price_cents']];
                    }
                    $e['total_price'] = endschBerechnePreis($codes, 'mitglied', $zabigPartner, $defs, $spezial);
                } elseif (!$gastPreisGesetzt) {
                    // Altdaten ohne gespeicherten Gesamtpreis: aus den Regeln nachrechnen
                    $defs = [];
                    foreach ($selByKey[$k] ?? [] as $s) {
                        $defs[$s['code']] = ['id' => (int)$s['stich_id'], 'price_cents' => (int)$s['price_cents']];
                    }
                    $e['total_price'] = endschBerechnePreis($codes, $teilTyp, false, $defs, $spezial);
                }

                foreach ($zusByKey[$k] ?? [] as $z) {
                    $e['zusatz_schuesse'][] = ['typ' => $z['typ'], 'anzahl' => (int)$z['anzahl'], 'preis_cents' => (int)$z['preis_cents']];
                    $typNorm = strtoupper((string)$z['typ']);
                    if (strpos($typNorm, 'GP11') !== false) { $e['zusatz_gp11'] += (int)$z['anzahl']; }
                    elseif (strpos($typNorm, 'GP90') !== false) { $e['zusatz_gp90'] += (int)$z['anzahl']; }
                    $e['munition_schuss'] += (int)$z['anzahl'];
                    $e['munition_preis']  += (int)$z['preis_cents'];
                    $e['total_price']     += (int)$z['preis_cents'];
                }
                $e['zahlungsmethode'] = $e['zahlungsmethode'] ?? 'karte';
                $details[] = $e;
            }
            jsonResponse(true, $details);

        default:
            jsonResponse(false, null, 'Unbekannte Action: ' . $action);
    }
} catch (Throwable $e) {
    error_log('[endschloesen_api] ' . $action . ': ' . $e->getMessage());
    http_response_code(500);
    jsonResponse(false, null, 'Fehler: ' . $e->getMessage());
}
