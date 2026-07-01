<?php
// api/foto_list.php - Fotos einer Galerie, nach Schiesstagen gruppiert (GET).
// Versorgt sowohl die Galerie-Ansicht als auch die Slideshow.
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand', 'mitglied']);

$db       = getDB();
$userId   = (int) ($_SESSION['user_id'] ?? 0);
$istVorst = isVorstand();

$galerieId = (int) ($_GET['id'] ?? 0);
$g = $galerieId > 0 ? fotoGalerieLaden($db, $galerieId) : null;
if (!$g) json_error('Galerie nicht gefunden.', 404);
if (empty($g['freigeschaltet']) && !$istVorst) json_error('Diese Galerie ist nicht freigeschaltet.', 403);

// Freigegebene Fotos fuer alle; eigene (noch nicht freigegebene) zusaetzlich fuer den Uploader.
$stmt = $db->prepare(
    "SELECT id, titel, status, hochgeladen_von, aufnahme_zeit, tag_datum, tag_index, breite, hoehe
       FROM anlass_fotos
      WHERE galerie_id = :gid AND (status = 'approved' OR hochgeladen_von = :uid)
      ORDER BY (tag_datum IS NULL) ASC, tag_datum ASC, tag_index ASC, sortierung ASC, aufnahme_zeit ASC, id ASC"
);
$stmt->execute([':gid' => $galerieId, ':uid' => $userId]);
$rows = $stmt->fetchAll();

// Schiesstage -> Label-Map fuer nummerierte Tage
$segMap = [];
foreach (fotoSchiesstageSegmente($g['Schiesstage'] ?? null) as $s) {
    $segMap[$s['index']] = $s['label'];
}

$wochentage = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
$monNamen   = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$fmtDatum = function (string $d) use ($wochentage, $monNamen): string {
    $ts = strtotime($d);
    if (!$ts) return $d;
    return $wochentage[(int) date('w', $ts)] . ', ' . (int) date('j', $ts) . '. ' . $monNamen[(int) date('n', $ts)] . ' ' . date('Y', $ts);
};

$gruppen = []; // key => ['label'=>, 'fotos'=>[]]
foreach ($rows as $r) {
    $ti = $r['tag_index'];
    $td = $r['tag_datum'];
    if ($ti !== null) {
        $key   = 'd' . (int) $ti;
        $label = $segMap[(int) $ti] ?? ('Tag ' . (int) $ti);
    } elseif ($td !== null) {
        $key   = 'date:' . $td;
        $label = $fmtDatum($td);
    } else {
        $key   = 'rest';
        $label = 'Weitere Fotos';
    }
    if (!isset($gruppen[$key])) $gruppen[$key] = ['key' => $key, 'label' => $label, 'fotos' => []];
    $gruppen[$key]['fotos'][] = [
        'id'        => (int) $r['id'],
        'titel'     => $r['titel'],
        'status'    => $r['status'],
        'mine'      => ((int) $r['hochgeladen_von'] === $userId),
        'breite'    => $r['breite'] !== null ? (int) $r['breite'] : null,
        'hoehe'     => $r['hoehe'] !== null ? (int) $r['hoehe'] : null,
        'thumb_url' => '../api/foto_serve.php?id=' . (int) $r['id'] . '&size=thumb',
        'full_url'  => '../api/foto_serve.php?id=' . (int) $r['id'] . '&size=full',
    ];
}

echo json_encode([
    'success' => true,
    'galerie' => [
        'id'           => (int) $g['id'],
        'name'         => $g['anlass_name'],
        'jahr'         => (int) $g['jahr'],
        'adresse'      => $g['Adresse'],
        'beschreibung' => $g['beschreibung'],
        'has_programm' => !empty($g['programm_dateipfad']),
        'programm_url' => !empty($g['programm_dateipfad']) ? '../api/foto_serve.php?programm=' . (int) $g['id'] : null,
    ],
    'gruppen'      => array_values($gruppen),
    'can_upload'   => fotoFeatureAktiv() && !empty($g['freigeschaltet']) && (!empty($g['upload_offen']) || $istVorst),
    'can_moderate' => $istVorst,
]);
