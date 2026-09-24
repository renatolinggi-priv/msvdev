<?php
// generate_pdf.php - Endpunkt für den Zielscheiben-Ausdruck (JSON-Body, Antwort JSON)
// Erwartet: {alleStiche: [{programmNummer, stichName, passe, schuesse: [{schuss_nr, wert, hunderter, x, y}]}],
//            schuetzenName, jahr}; CSRF-Token im Header X-CSRF-TOKEN.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

header('Content-Type: application/json; charset=utf-8');

function targetprintFehler(string $msg, int $code = 400): void {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$data = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($data)) {
    targetprintFehler('Ungültige JSON-Daten');
}
if (!isset($data['alleStiche']) || !is_array($data['alleStiche'])) {
    targetprintFehler('Keine Stiche-Daten vorhanden');
}

// Eingaben bereinigen: nur bekannte Felder, alles auf Zahlen gecastet
$alleStiche = [];
foreach ($data['alleStiche'] as $stich) {
    if (!is_array($stich) || empty($stich['schuesse']) || !is_array($stich['schuesse'])) {
        continue;
    }
    $schuesse = [];
    foreach ($stich['schuesse'] as $s) {
        if (!is_array($s)) {
            continue;
        }
        $schuesse[] = [
            'schuss_nr' => (int) ($s['schuss_nr'] ?? 0),
            'wert'      => (int) ($s['wert'] ?? 0),
            'hunderter' => (int) ($s['hunderter'] ?? 0),
            'x'         => (float) ($s['x'] ?? 0),
            'y'         => (float) ($s['y'] ?? 0),
        ];
    }
    if (!$schuesse) {
        continue;
    }
    $alleStiche[] = [
        'programmNummer' => preg_replace('/\D+/', '', (string) ($stich['programmNummer'] ?? '')),
        'stichName'      => mb_substr(trim((string) ($stich['stichName'] ?? '')), 0, 100),
        'passe'          => (int) ($stich['passe'] ?? 0),
        'schuesse'       => $schuesse,
    ];
}

if (!$alleStiche) {
    targetprintFehler('Keine Schüsse in den Stichen vorhanden');
}

$schuetzenName = mb_substr(trim((string) ($data['schuetzenName'] ?? '')), 0, 100);
$jahr          = (int) ($data['jahr'] ?? 0);
if ($jahr < 2000 || $jahr > 2100) {
    $jahr = (int) date('Y');
}

$pdfDir = __DIR__ . '/dat';
if (!is_dir($pdfDir) && !mkdir($pdfDir, 0755, true)) {
    targetprintFehler('PDF-Verzeichnis konnte nicht angelegt werden', 500);
}

require_once __DIR__ . '/ZielscheibeReport.php';

try {
    $report = new ZielscheibeReport($conn, $jahr, $alleStiche, $schuetzenName !== '' ? $schuetzenName : null);
    $report->setPDFOutputDir($pdfDir);
    $pdfLink = $report->generate();
} catch (Throwable $e) {
    error_log('[TARGETPRINT] PDF-Generierung fehlgeschlagen: ' . $e->getMessage());
    targetprintFehler('PDF-Generierung fehlgeschlagen: ' . $e->getMessage(), 500);
}

$pdfFile = $pdfDir . '/' . basename($pdfLink);
if (!is_file($pdfFile)) {
    targetprintFehler('PDF wurde nicht erstellt', 500);
}

// Basisklasse puffert die Ausgabe (ob_start im Konstruktor) - vor der Antwort leeren
while (ob_get_level() > 0) { ob_end_clean(); }

echo json_encode([
    'success'  => true,
    'pdf_link' => '/' . ltrim($pdfLink, '/'),
    'filename' => basename($pdfLink),
]);
