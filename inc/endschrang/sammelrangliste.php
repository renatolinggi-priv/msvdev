<?php
// sammelrangliste.php: hängt die Ranglisten fürs Absenden zu einem PDF zusammen (A4, kein Heft, zum Vorlesen).
// Die Seite «Endschiessen» (Ansicht Rangliste, Knopf «Sammelrangliste») ruft zuerst jeden Generator einzeln auf.
// Jeder liefert einen Link in <modul>/dat/; die Liste kommt hierher (POST teile[] relativ zu inc/, in der
// Reihenfolge zum Vorlesen) und wird mit pdfunite verbunden (Rückfall Ghostscript).
include __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

header('Content-Type: application/json; charset=utf-8');

function sammelAntwort(int $status, array $daten): void
{
    http_response_code($status);
    echo json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$jahr  = (int)($_POST['year'] ?? date('Y'));
$teile = $_POST['teile'] ?? [];
if (!is_array($teile) || !$teile || count($teile) > 30) {
    sammelAntwort(400, ['success' => false, 'message' => 'Es gibt keine Ranglisten zum Zusammenfügen.']);
}

// Nur erzeugte Ranglisten aus den bekannten Modulen (Signatur mit Zeitstempel wie in dat_cleanup.inc.php)
$incDir = realpath(__DIR__ . '/..');
$muster = '~^(endschrang|heimrang|kantirang|cuprang|jmrang|einzelrangierung|sektionsrangierungen)/dat/'
        . '[A-Za-z0-9_-]+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.pdf$~';
$dateien = [];
foreach ($teile as $teil) {
    $teil = (string)$teil;
    $pfad = preg_match($muster, $teil) ? realpath($incDir . '/' . $teil) : false;
    if ($pfad === false || strpos($pfad, $incDir . DIRECTORY_SEPARATOR) !== 0 || !is_file($pfad)) {
        msvFehler('', 'Teil abgelehnt: ' . $teil);
        sammelAntwort(400, ['success' => false, 'message' => 'Eine Rangliste fehlt auf dem Server. Bitte nochmals «Sammelrangliste» klicken.']);
    }
    $dateien[] = $pfad;
}

$ziel = __DIR__ . '/dat/Sammelrangliste_' . $jahr . '_' . date('Y-m-d_H-i-s') . '.pdf';
$env  = 'PATH=/usr/local/bin:/usr/bin:/bin ';
$quellen = implode(' ', array_map('escapeshellarg', $dateien));

if (count($dateien) === 1) {
    $ok = copy($dateien[0], $ziel);
} else {
    $out = [];
    exec($env . 'pdfunite ' . $quellen . ' ' . escapeshellarg($ziel) . ' 2>&1', $out, $rc);
    $ok = $rc === 0 && is_file($ziel) && filesize($ziel) > 1000;
    if (!$ok) {
        msvFehler('', 'pdfunite rc=' . $rc . ': ' . trim(implode(' ', array_slice($out, -3))));
        @unlink($ziel);
        $out = [];
        exec($env . 'gs -q -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite -sOutputFile=' . escapeshellarg($ziel)
            . ' ' . $quellen . ' 2>&1', $out, $rc);
        $ok = $rc === 0 && is_file($ziel) && filesize($ziel) > 1000;
        if (!$ok) {
            msvFehler('', 'gs rc=' . $rc . ': ' . trim(implode(' ', array_slice($out, -3))));
            @unlink($ziel);
        }
    }
}
if (!$ok) {
    sammelAntwort(500, ['success' => false, 'message' => 'Die Ranglisten konnten nicht zusammengefügt werden. Bitte einzeln herunterladen und dem Admin melden.']);
}

// Seitenzahl für die Rückmeldung (nur Information)
$seiten = null;
$out = [];
exec($env . 'pdfinfo ' . escapeshellarg($ziel) . ' 2>/dev/null', $out);
foreach ($out as $zeile) {
    if (preg_match('/^Pages:\s+(\d+)/', $zeile, $m)) {
        $seiten = (int)$m[1];
    }
}

sammelAntwort(200, [
    'success'  => true,
    'pdf_link' => 'endschrang/dat/' . basename($ziel),
    'seiten'   => $seiten,
    'teile'    => count($dateien),
]);
