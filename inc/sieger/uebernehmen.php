<?php
// uebernehmen.php — Sieger eines Jahres aus den Ranglisten übernehmen (sieger.php, «Aus Ranglisten übernehmen»)
//   GET  year                      Vorschau je Auszeichnung (siegerVorschlaege), nichts wird geschrieben
//   POST year, siegerdef[] (IDs)   für diese Auszeichnungen den Ranglisten-Sieger fest in sieger eintragen:
//                                  noch keiner erfasst → neu, genau einer erfasst → überschreiben.
// Der Server rechnet beim Speichern neu; vom Browser kommen nur die gewählten Auszeichnungen.
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/sieger_ranglisten.inc.php';

header('Content-Type: application/json; charset=utf-8');

$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post) {
    csrf_require(true);
}

$jahr = (int)($post ? ($_POST['year'] ?? 0) : ($_GET['year'] ?? 0));
if ($jahr < 2000 || $jahr > (int)date('Y') + 1) {
    http_response_code(422);
    die(json_encode(['success' => false, 'message' => 'Ungültiges Jahr. Bitte oben ein Jahr wählen.'], JSON_UNESCAPED_UNICODE));
}

try {
    $liste = siegerVorschlaege($conn, $jahr);

    if (!$post) {
        echo json_encode(['success' => true, 'jahr' => $jahr, 'liste' => $liste], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $gewaehlt = array_map('intval', (array)($_POST['siegerdef'] ?? []));
    if (!$gewaehlt) {
        http_response_code(422);
        die(json_encode(['success' => false, 'message' => 'Bitte mindestens eine Auszeichnung anhaken.'], JSON_UNESCAPED_UNICODE));
    }

    $ins = $conn->prepare("INSERT INTO sieger (Name, Wert, siegerdef, year) VALUES (?, ?, ?, ?)");
    $upd = $conn->prepare("UPDATE sieger SET Name = ?, Wert = ? WHERE ID = ? AND siegerdef = ? AND year = ?");
    $neu = 0;
    $ersetzt = 0;
    $uebersprungen = [];

    $conn->begin_transaction();
    foreach ($liste as $e) {
        if (!in_array($e['siegerdef_id'], $gewaehlt, true)) {
            continue;
        }
        $v = $e['vorschlag'];
        $def = $e['siegerdef_id'];
        if ($v === null || $e['status'] === 'mehrere') {
            // Ohne Resultate oder mit mehreren erfassten Einträgen nichts automatisch ändern
            $uebersprungen[] = $e['bezeichnung'];
            continue;
        }
        if ($e['status'] === 'gleich') {
            continue;
        }
        if ($e['status'] === 'neu') {
            $ins->bind_param('ssii', $v['name'], $v['wert'], $def, $jahr);
            $ins->execute();
            $neu++;
        } else {
            $alt = $e['bisher'][0];
            $upd->bind_param('ssiii', $v['name'], $v['wert'], $alt['id'], $def, $jahr);
            $upd->execute();
            // Vorher-Stand ins Log, falls jemand den Handeintrag zurück will
            error_log("[sieger_uebernehmen] {$jahr} {$e['bezeichnung']}: «{$alt['name']} {$alt['wert']}» ersetzt durch «{$v['name']} {$v['wert']}»");
            $ersetzt++;
        }
    }
    $conn->commit();

    echo json_encode([
        'success'       => true,
        'jahr'          => $jahr,
        'neu'           => $neu,
        'ersetzt'       => $ersetzt,
        'uebersprungen' => $uebersprungen,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => msvFehler($post
        ? 'Die Sieger konnten nicht gespeichert werden. Es wurde nichts geändert; bitte nochmals versuchen.'
        : 'Die Ranglisten konnten nicht gelesen werden. Bitte die Seite neu laden.', $e)], JSON_UNESCAPED_UNICODE);
}
$conn->close();
