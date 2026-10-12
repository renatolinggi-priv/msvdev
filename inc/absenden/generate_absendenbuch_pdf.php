<?php
/**
 * generate_absendenbuch_pdf.php — Absendenbuch (Resultatbüchlein) als PDF, standardmässig als Broschüre
 *
 * GET:  year        Jahr
 *       booklet=0   optional: nur das A5-PDF ohne Ausschiessen (z.B. für die Druckerei)
 * Antwort: JSON {success, pdf_link: 'absenden/dat/…pdf', pages, sheets, hinweise: [Klartext …]} (Link relativ zu inc/)
 *          bzw. {success:false, leer:true, message} ohne Endstich-Resultate, {success:false, message} mit HTTP 500
 *
 * Ablauf:
 *   1. Word-Vorlage füllen (absendenbuch_docx.inc.php) → temporäres DOCX
 *   2. DOCX → PDF über inc/lib/convertapi_helper.php (iLoveAPI bzw. ConvertAPI, Seiten A5 hoch)
 *   3. Broschüre ausschiessen mit pdfjam --booklet (TeX/pdfpages, auf Hostpoint unter /usr/local/bin):
 *      zwei A5-Seiten pro A4-Blatt quer, Seitenreihenfolge fürs Falten/Heften, Auffüllen auf ein
 *      Vielfaches von 4 Seiten. Drucken: beidseitig, Wenden an der KURZEN Seite (Duckprofil «Absendenbuch»).
 *
 * dat/-Aufräumen läuft zentral über inc/config.php (datAufraeumenNachAusgabe); der Dateiname trägt darum
 * den Zeitstempel unmittelbar vor der Endung.
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(180);

require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');                          // nur Admin/Vorstand (Personendaten)
require_once __DIR__ . '/../config.php';        // $conn + zentraler dat/-Cleanup
require_once __DIR__ . '/absendenbuch_docx.inc.php';
require_once __DIR__ . '/../lib/convertapi_helper.php';

$year    = absendenbuchJahr();
$booklet = !isset($_GET['booklet']) || $_GET['booklet'] !== '0';
$datDir  = __DIR__ . '/dat';
$ts      = (new DateTime())->format('Y-m-d_H-i-s');
$tmpDocx = null;
$tmpPdf  = null;

/** Seitenzahl eines PDFs (pdfinfo, Fallback: Seitenobjekte zählen). */
function absendenPdfSeiten(string $pdf): int
{
    $out = [];
    exec(absendenShellEnv() . 'pdfinfo ' . escapeshellarg($pdf) . ' 2>/dev/null', $out);
    foreach ($out as $zeile) {
        if (preg_match('/^Pages:\s+(\d+)/', $zeile, $m)) {
            return (int)$m[1];
        }
    }
    $n = preg_match_all('#/Type\s*/Page[^s]#', (string)file_get_contents($pdf));
    if ($n > 0) {
        return $n;
    }
    throw new RuntimeException('Seitenzahl des PDFs nicht ermittelbar');
}

/**
 * Umgebung für externe Werkzeuge: der Web-PHP-Prozess hat u.U. weder PATH mit /usr/local/bin
 * noch HOME (TeX braucht ein schreibbares HOME für seinen Font-Cache).
 */
function absendenShellEnv(): string
{
    $home = getenv('HOME') ?: dirname(__DIR__, 4); // /home/<user> oberhalb von www/<docroot>/inc/absenden
    return 'HOME=' . escapeshellarg($home) . ' PATH=/usr/local/bin:/usr/bin:/bin TMPDIR=' . escapeshellarg(sys_get_temp_dir()) . ' ';
}

// Klartext je Schritt: beim Füllen bleiben eigene Meldungen sichtbar (z.B. fehlende Vorlage); was der
// Umwandlungsdienst oder pdfjam melden, ist technisch und geht nur ins Log.
$schritt  = 'fuellen';
$klartext = [
    'fuellen'      => 'Das Absendenbuch konnte nicht erstellt werden. Bitte in ein paar Minuten nochmals versuchen und sonst dem Admin melden.',
    'umwandeln'    => 'Die Umwandlung in PDF hat nicht geklappt. Bitte in ein paar Minuten nochmals versuchen; bis dahin geht «Absendenbuch (Word)».',
    'ausschiessen' => 'Das PDF ist erstellt, aber die Broschüre liess sich nicht ausschiessen. Bitte nochmals versuchen und sonst dem Admin melden.',
];

try {
    if (!absendenbuchHatEndstich($conn, $year)) {
        msvAusgabeLeer('Für ' . $year . ' sind noch keine Endstich-Resultate erfasst. Ohne sie gibt es kein Absendenbuch.');
    }
    if (!is_dir($datDir) && !mkdir($datDir, 0755, true)) {
        throw new RuntimeException('Der Ablageordner des Absendenbuchs ist auf dem Server nicht beschreibbar. Bitte dem Admin melden.');
    }

    // 1) Vorlage füllen → temporäres DOCX
    $hinweise = [];
    $tp = absendenbuchTemplateFuellen($year, $conn, $hinweise);
    $tmpDocx = tempnam(sys_get_temp_dir(), 'absenden_');
    rename($tmpDocx, $tmpDocx . '.docx'); $tmpDocx .= '.docx';
    $tp->saveAs($tmpDocx);

    // 2) DOCX → PDF (A5-Seiten)
    $schritt = 'umwandeln';
    $tmpPdf = convertToPdf($tmpDocx, 'docx');
    $pages  = absendenPdfSeiten($tmpPdf);

    if (!$booklet) {
        $ziel = $datDir . '/Resultatbuechlein_A5_' . $ts . '.pdf';
        if (!copy($tmpPdf, $ziel)) {
            throw new RuntimeException('PDF konnte nicht gespeichert werden');
        }
        $antwort = ['pdf_link' => 'absenden/dat/' . basename($ziel), 'pages' => $pages, 'sheets' => null];
    } else {
        // 3) Broschüre: eine Lage (signature) = alle Seiten, aufgerundet auf ein Vielfaches von 4
        $schritt = 'ausschiessen';
        $signature = max(4, (int)(ceil($pages / 4) * 4));
        $ziel = $datDir . '/Resultatbuechlein_Broschuere_' . $ts . '.pdf';
        $cmd = absendenShellEnv()
            . 'pdfjam --booklet true --landscape --paper a4paper --signature ' . $signature . ' --quiet '
            . escapeshellarg($tmpPdf) . ' -o ' . escapeshellarg($ziel) . ' 2>&1';
        $out = [];
        $rc  = 0;
        exec($cmd, $out, $rc);
        if ($rc !== 0 || !is_file($ziel) || filesize($ziel) < 1000) {
            @unlink($ziel);
            throw new RuntimeException('Ausschiessen fehlgeschlagen (pdfjam rc=' . $rc . '): ' . trim(implode(' ', array_slice($out, -3))));
        }
        $antwort = [
            'pdf_link'  => 'absenden/dat/' . basename($ziel),
            'pages'     => $pages,              // Seiten im A5-Dokument
            'signature' => $signature,          // Seiten nach Auffüllen
            'sheets'    => (int)($signature / 4), // A4-Blätter beidseitig
        ];
    }

    echo json_encode(['success' => true] + $antwort + ['hinweise' => $hinweise], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    $meldung = msvFehler($klartext[$schritt], $e); // loggt die Technik
    echo json_encode(['success' => false, 'message' => $schritt === 'fuellen' ? $meldung : $klartext[$schritt]],
        JSON_UNESCAPED_UNICODE);
} finally {
    if ($tmpDocx && file_exists($tmpDocx)) @unlink($tmpDocx);
    if ($tmpPdf && file_exists($tmpPdf)) @unlink($tmpPdf);
}
