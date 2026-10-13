<?php
// endschrang/absenden_bereit.php – Kennzahlen für «Absenden vorbereiten» auf endschrang.php (nur lesen).
// Offene Stiche und Sie und Er zählt die Seite aus den Ladern der Erfassung (eine Quelle der Wahrheit); hier:
//   wanderpreise  angeschafft bis zum Jahr, nicht in einem früheren Jahr definitiv gewonnen, Gewinner fürs Jahr?
//   siegerlisten  welchen Listen «… Sieger seit» im Absendenbuch das Jahr noch fehlt (von Hand geführt)
//   buchteile     haben JM, Heimmeisterschaft, Kantonalstich und Vereinscup Resultate (sonst leere Tabellen im Buch)
//   buchstand     zuletzt erstelltes Absendenbuch je Art (word, broschuere): Zeit, von, Datei, Hinweise beim Erstellen
// Jeder Teil wird einzeln geprüft; scheitert einer, ist nur er null und die Seite zeigt «nicht geprüft».
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../absenden/functions.inc.php'; // absendenSiegerlisteName(), absendenStandLesen()

header('Content-Type: application/json; charset=utf-8');

$jahr = (int)($_GET['year'] ?? 0);
if ($jahr < 2000 || $jahr > (int)date('Y') + 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr. Bitte oben auf der Seite ein Jahr wählen.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Abfrage mit einem Jahr-Parameter (so oft wie ? vorkommt), alle Zeilen. */
function absBereitZeilen(mysqli $conn, string $sql, int $jahr): array
{
    $st = $conn->prepare($sql);
    $typen = str_repeat('i', substr_count($sql, '?'));
    $st->bind_param($typen, ...array_fill(0, strlen($typen), $jahr));
    $st->execute();
    $zeilen = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $zeilen;
}

/** Teil einzeln prüfen: Fehler nur ins Log, der Teil wird null. */
function absBereitTeil(string $name, callable $pruefen)
{
    try {
        return $pruefen();
    } catch (Throwable $e) {
        error_log('[absenden_bereit] ' . $name . ': ' . get_class($e) . ': ' . $e->getMessage());
        return null;
    }
}

$antwort = ['success' => true, 'jahr' => $jahr];

$antwort['wanderpreise'] = absBereitTeil('wanderpreise', function () use ($conn, $jahr) {
    $r = absBereitZeilen($conn,
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(EXISTS(SELECT 1 FROM wanderpreise_gewinner g WHERE g.wanderpreis_id = w.id AND g.jahr = ?)), 0) AS vergeben
           FROM wanderpreise w
          WHERE w.beschaffung_datum <= ?
            AND NOT EXISTS (SELECT 1 FROM wanderpreise_gewinner d
                             WHERE d.wanderpreis_id = w.id AND d.ist_definitiv = 1 AND d.jahr < ?)', $jahr)[0];
    return ['total' => (int)$r['total'], 'vergeben' => (int)$r['vergeben']];
});

// Dieselbe Quelle wie getSieger() im Absendenbuch: Tabelle sieger je siegerdef
$antwort['siegerlisten'] = absBereitTeil('siegerlisten', function () use ($conn, $jahr) {
    $listen = absBereitZeilen($conn,
        'SELECT sd.Bezeichnung, EXISTS(SELECT 1 FROM sieger s WHERE s.siegerdef = sd.ID AND s.year = ?) AS hat
           FROM siegerdef sd ORDER BY sd.ID', $jahr);
    $ohne = [];
    foreach ($listen as $l) {
        if (!(int)$l['hat']) {
            $ohne[] = absendenSiegerlisteName($l['Bezeichnung']);
        }
    }
    return ['total' => count($listen), 'ohne' => $ohne];
});

// Teile des Buchs mit eigener Erfassung; Kategorie wie im Buch über die Waffe des Mitglieds
$antwort['buchteile'] = absBereitTeil('buchteile', function () use ($conn, $jahr) {
    $nachKat = function (string $sql) use ($conn, $jahr): array {
        $n = [];
        foreach (absBereitZeilen($conn, $sql, $jahr) as $z) {
            $n[$z['Kategorie']] = (int)$z['n'];
        }
        return $n;
    };
    $jm = $nachKat('SELECT w.Kategorie, COUNT(*) AS n FROM jmresultate r
                      JOIN JMDefinition d ON d.ID = r.jmdefinitionID AND d.year = ? AND d.Erweitert = 0 AND d.Info = 0
                      JOIN mitglieder m ON m.ID = r.mitgliederID JOIN Waffen w ON w.ID = m.WaffenID GROUP BY w.Kategorie');
    $heim = $nachKat('SELECT w.Kategorie, COUNT(*) AS n FROM heimresultate h
                        JOIN mitglieder m ON m.ID = h.MitgliedID JOIN Waffen w ON w.ID = m.WaffenID
                       WHERE h.Jahr = ? GROUP BY w.Kategorie');
    $kanti = $nachKat('SELECT w.Kategorie, COUNT(*) AS n FROM kantiresultate k
                         JOIN mitglieder m ON m.ID = k.MitgliedID JOIN Waffen w ON w.ID = m.WaffenID
                        WHERE k.Jahr = ? GROUP BY w.Kategorie');
    $cup = (int)absBereitZeilen($conn, 'SELECT COUNT(*) AS n FROM cupPairs WHERE Year = ?', $jahr)[0]['n'];

    $teile = [];
    foreach (['Kat. A', 'Kat. B'] as $kat) {
        $teile[] = ['teil' => 'Jahresmeisterschaft ' . $kat, 'vorhanden' => ($jm[$kat] ?? 0) > 0, 'link' => 'jmresultate.php'];
    }
    foreach (['Kat. A', 'Kat. B'] as $kat) {
        $teile[] = ['teil' => 'Heimmeisterschaft ' . $kat, 'vorhanden' => ($heim[$kat] ?? 0) > 0, 'link' => 'heimresultate.php'];
    }
    foreach (['Kat. A', 'Kat. B'] as $kat) {
        $teile[] = ['teil' => 'Kantonalstich ' . $kat, 'vorhanden' => ($kanti[$kat] ?? 0) > 0, 'link' => 'kantiresultate.php'];
    }
    $teile[] = ['teil' => 'Vereinscup', 'vorhanden' => $cup > 0, 'link' => 'cup.php'];
    return $teile;
});

// Link relativ zu inc/; fehlt die Datei (dat/-Aufräumen), sagt die Seite «nicht mehr vorhanden»
$antwort['buchstand'] = absBereitTeil('buchstand', function () use ($conn, $jahr) {
    $stand = [];
    foreach (absendenStandLesen($conn, $jahr) as $art => $s) {
        if (!in_array($art, ['word', 'broschuere'], true) || !is_array($s) || empty($s['datei'])) {
            continue;
        }
        $datei = basename((string)$s['datei']);
        $stand[$art] = [
            'link'      => 'absenden/dat/' . $datei,
            'vorhanden' => is_file(__DIR__ . '/../absenden/dat/' . $datei),
            'erstellt'  => (string)($s['erstellt'] ?? ''),
            'von'       => (string)($s['von'] ?? ''),
            'seiten'    => isset($s['seiten']) ? (int)$s['seiten'] : null,
            'blatt'     => isset($s['blatt']) ? (int)$s['blatt'] : null,
            'hinweise'  => array_values(array_filter((array)($s['hinweise'] ?? []), 'is_array')),
        ];
    }
    return $stand;
});

echo json_encode($antwort, JSON_UNESCAPED_UNICODE);
