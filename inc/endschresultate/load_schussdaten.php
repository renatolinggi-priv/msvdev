<?php
// load_schussdaten.php – Schüsse eines Teilnehmers für das Erfassungs-Panel (Endschiessen Resultate erfassen)
//
// GET teilnehmer (m<ID> = Mitglied, g<ID> = Gast/Jungschütze; seit Migration 094) oder mitgliedID (alt), year
// Antwort: Feldname => Wert für die gelösten Stiche, dazu
//   Partnerrang     1/0: Mitglied steht zusätzlich in der Partner-Rangliste (nur Mitglieder, Migration 096)
//   geloesteStiche  Codes aus «Stiche lösen»
//   teilnehmerTyp   mitglied | gast | js
//   sieUnder        { partner, partnerName, partnerSchuesse[5], kandidaten[{key, name}] } – wenn Sie und Er gelöst
require_once __DIR__ . '/../debug_log.inc.php';
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/teilnehmer.inc.php';

header('Content-Type: application/json; charset=utf-8');

$jahr = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$t = endschTeilnehmer($_GET['teilnehmer'] ?? (isset($_GET['mitgliedID']) ? 'm' . (int)$_GET['mitgliedID'] : ''));
if (!$t) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Kein Teilnehmer gewählt. Bitte die Liste neu laden.']);
    exit;
}
$spalte = $t['spalte'];
$id = $t['id'];

/** Erste Zeile einer Abfrage mit einem Integer-Parameterpaar (id, jahr) */
function zeile(mysqli $conn, string $sql, int $id, int $jahr): ?array
{
    $st = $conn->prepare($sql);
    $st->bind_param('ii', $id, $jahr);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    return $r ?: null;
}

$response = ['teilnehmer' => $t['schluessel']];

// Typ und Existenz (Gäste gibt es pro Jahr)
if ($spalte === 'GastID') {
    $g = zeile($conn, 'SELECT id, geburtsdatum FROM endstich_gaeste WHERE id = ? AND jahr = ?', $id, $jahr);
    if (!$g) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Diesen Gast gibt es im gewählten Jahr nicht. Bitte die Liste neu laden.']);
        exit;
    }
    $response['teilnehmerTyp'] = $g['geburtsdatum'] ? 'js' : 'gast';
} else {
    $response['teilnehmerTyp'] = 'mitglied';
    // Zusätzlich in der Partner-Rangliste (Migration 096)
    $response['Partnerrang'] = zeile($conn, 'SELECT 1 AS ja FROM endstich_partnerrang WHERE MitgliedID = ? AND Jahr = ?', $id, $jahr) ? 1 : 0;
}

// Gelöste Stiche
$selSpalte = $spalte === 'GastID' ? 'gast_id' : 'mitglied_id';
$st = $conn->prepare("SELECT DISTINCT sd.code
                      FROM endstich_selection es
                      INNER JOIN endstich_definition sd ON es.stich_id = sd.id
                      WHERE es.$selSpalte = ? AND es.jahr = ? AND sd.active = 1");
$st->bind_param('ii', $id, $jahr);
$st->execute();
$geloesteStiche = array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), 'code');
$st->close();
$response['geloesteStiche'] = $geloesteStiche;
msv_debug_log('endschresultate', "load_schussdaten {$t['schluessel']} $jahr: " . implode(', ', $geloesteStiche));

$hat = fn(string ...$codes) => (bool)array_intersect($codes, $geloesteStiche);

if ($hat('END')) {
    $r = zeile($conn, "SELECT Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, Schuss6, Schuss7, Schuss8, Schuss9, Schuss10, Tiefschuss, AbsendenAnmeldung
                       FROM endstich WHERE $spalte = ? AND Jahr = ?", $id, $jahr);
    if ($r) $response += $r;
} else {
    // Absenden ist unabhängig vom Endstich immer offen
    $r = zeile($conn, "SELECT AbsendenAnmeldung FROM endstich WHERE $spalte = ? AND Jahr = ?", $id, $jahr);
    if ($r) $response += $r;
}
if ($hat('SCHWINI_P1', 'SCHWINI_P2')) {
    $r = zeile($conn, "SELECT P1Schuss1, P1Schuss2, P1Schuss3, P1Schuss4, P1Schuss5, P1Schuss6, P2Schuss1, P2Schuss2, P2Schuss3, P2Schuss4, P2Schuss5, P2Schuss6
                       FROM schwini WHERE $spalte = ? AND Jahr = ?", $id, $jahr);
    if ($r) $response += $r;
}
if ($hat('KUNST')) {
    $r = zeile($conn, "SELECT KSchuss1, KSchuss2, KSchuss3, KSchuss4, KSchuss5 FROM kunst WHERE $spalte = ? AND Jahr = ?", $id, $jahr);
    if ($r) $response += $r;
}
if ($hat('GLUECK')) {
    $r = zeile($conn, "SELECT GSchuss1, GSchuss2, GSchuss3 FROM glueck WHERE $spalte = ? AND Jahr = ?", $id, $jahr);
    if ($r) $response += $r;
}
// Zabig und Differenzler (Ansage) liegen in derselben Tabelle
if ($hat('ZABIG', 'DIFF')) {
    $r = zeile($conn, "SELECT ZSchuss1, ZSchuss2, ZSchuss3, ZSchuss4, ZSchuss5, ZSchuss6, Ansage FROM zabig WHERE $spalte = ? AND Jahr = ?", $id, $jahr);
    if ($r) {
        $response += $hat('ZABIG') ? $r : ['Ansage' => $r['Ansage']];
    }
}

// Sie und Er: eigene fünf Schüsse, Partnerin/Partner und wer dafür in Frage kommt (Migration 094)
if ($hat('SIEUNDER')) {
    $r = zeile($conn, "SELECT Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, PartnerMitgliedID, PartnerGastID
                       FROM endstich_sieunder WHERE $spalte = ? AND Jahr = ?", $id, $jahr);
    $partner = null;
    if ($r) {
        for ($i = 1; $i <= 5; $i++) {
            $response['SieErSchuss' . $i] = $r['Schuss' . $i];
        }
        if ($r['PartnerMitgliedID'] || $r['PartnerGastID']) {
            $partner = endschSchluessel($r['PartnerMitgliedID'], $r['PartnerGastID']);
        }
    }

    // In Frage kommt, wer im Jahr Sie und Er gelöst hat (ausser der Person selbst), dazu die bisherige Wahl
    $st = $conn->prepare("
        SELECT CONCAT('m', m.ID) AS k, CONVERT(CONCAT(m.Name, ' ', m.Vorname) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS name, 1 AS gruppe
        FROM endstich_selection es
        JOIN endstich_definition sd ON sd.id = es.stich_id AND sd.code = 'SIEUNDER'
        JOIN mitglieder m ON m.ID = es.mitglied_id
        WHERE es.jahr = ?
        UNION
        SELECT CONCAT('g', g.id), CONVERT(g.name USING utf8mb4) COLLATE utf8mb4_unicode_ci, 2
        FROM endstich_selection es
        JOIN endstich_definition sd ON sd.id = es.stich_id AND sd.code = 'SIEUNDER'
        JOIN endstich_gaeste g ON g.id = es.gast_id
        WHERE es.jahr = ?
        ORDER BY gruppe, name");
    $st->bind_param('ii', $jahr, $jahr);
    $st->execute();
    $kandidaten = [];
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $k) {
        if ($k['k'] !== $t['schluessel']) {
            $kandidaten[$k['k']] = ['key' => $k['k'], 'name' => $k['name'], 'typ' => $k['gruppe'] == 1 ? 'mitglied' : 'gast'];
        }
    }
    $st->close();

    $partnerName = null;
    $partnerSchuesse = [null, null, null, null, null];
    if ($partner) {
        $p = endschTeilnehmer($partner);
        $pn = $p['spalte'] === 'MitgliedID'
            ? zeile($conn, "SELECT CONCAT(Name, ' ', Vorname) AS name FROM mitglieder WHERE ID = ? AND ? > 0", $p['id'], $jahr)
            : zeile($conn, 'SELECT name FROM endstich_gaeste WHERE id = ? AND jahr = ?', $p['id'], $jahr);
        $partnerName = $pn['name'] ?? null;
        $ps = zeile($conn, "SELECT Schuss1, Schuss2, Schuss3, Schuss4, Schuss5 FROM endstich_sieunder WHERE {$p['spalte']} = ? AND Jahr = ?", $p['id'], $jahr);
        if ($ps) $partnerSchuesse = array_values($ps);
        if ($partnerName !== null && !isset($kandidaten[$partner])) {
            $kandidaten[$partner] = ['key' => $partner, 'name' => $partnerName, 'typ' => $p['spalte'] === 'MitgliedID' ? 'mitglied' : 'gast'];
        }
    }
    // Schüsse und bestehende Verknüpfung jeder Person, damit die Seite beim Wechsel sofort das Paar-Total zeigt
    $st = $conn->prepare('SELECT MitgliedID, GastID, Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, PartnerMitgliedID, PartnerGastID
                          FROM endstich_sieunder WHERE Jahr = ?');
    $st->bind_param('i', $jahr);
    $st->execute();
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $z) {
        $k = endschSchluessel($z['MitgliedID'], $z['GastID']);
        if (!isset($kandidaten[$k])) continue;
        $kandidaten[$k]['schuesse'] = [$z['Schuss1'], $z['Schuss2'], $z['Schuss3'], $z['Schuss4'], $z['Schuss5']];
        if ($z['PartnerMitgliedID'] || $z['PartnerGastID']) {
            $kandidaten[$k]['gepaartMit'] = endschSchluessel($z['PartnerMitgliedID'], $z['PartnerGastID']);
        }
    }
    $st->close();

    $response['sieUnder'] = [
        'partner'         => $partner,
        'partnerName'     => $partnerName,
        'partnerSchuesse' => $partnerSchuesse,
        'kandidaten'      => array_values($kandidaten),
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
