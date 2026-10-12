<?php
/**
 * absendenbuch_docx.inc.php — Word-Vorlage des Absendenbuchs (Resultatbüchlein) befüllen
 *
 * Gemeinsam genutzt von
 *   - generate_absendenbuch.php      (DOCX-Download, bisheriger Weg)
 *   - generate_absendenbuch_pdf.php  (PDF, standardmässig als Broschüre ausgeschossen, für den Direktdruck)
 *
 * WICHTIG: functions.inc.php setzt $selectedYear auf oberster Ebene aus $_GET['year'] und die
 * get*-Funktionen lesen es per «global $selectedYear». Diese Datei muss darum aus dem globalen
 * Scope eingebunden werden; absendenbuchTemplateFuellen() setzt $GLOBALS['selectedYear'] zusätzlich
 * explizit, damit das Jahr auch ohne GET-Parameter stimmt.
 *
 * Nichts fällt still weg: Jeder Abschnitt läuft einzeln; was leer bleibt oder scheitert, landet als Klartext in
 * $hinweise (absendenHinweis() in functions.inc.php). Am Schluss wird das Buch nach ungefüllten Platzhaltern
 * durchsucht; Reste werden geleert und ebenfalls gemeldet. Die Oberfläche zeigt die Hinweise nach dem Erstellen
 * bzw. fragt vor dem Direktdruck nach.
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/functions.inc.php';

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun;

// Vorlage seit 12.10.2026 mit Blockmarken ${JUNGSCHUETZEN_BLOCK}/${PARTNER_BLOCK} (je ein eigener Absatz);
// die Vorgängerin Resultatbuch_Template20251015.docx bleibt als Rückfall in dat/ liegen.
const ABSENDENBUCH_TEMPLATE = __DIR__ . '/dat/Resultatbuch_Template20261012.docx';

/**
 * TemplateProcessor mit robuster Block-Auflösung. PhpWords cloneBlock() sucht mit einer gierigen Regex über
 * das ganze Dokument (~400 KB) und scheitert dabei je nach Grösse still; hier genügt eine String-Suche.
 */
class AbsendenbuchVorlage extends TemplateProcessor
{
    /**
     * Abschnitt ${NAME} … ${/NAME} auflösen. Behalten: nur die beiden Markenabsätze verschwinden;
     * sonst fällt der ganze Abschnitt samt Marken weg. false, wenn die Marken fehlen.
     */
    public function blockAufloesen(string $name, bool $behalten): bool
    {
        $xml = $this->tempDocumentMainPart;
        $a = strpos($xml, '${' . $name . '}');
        $b = strpos($xml, '${/' . $name . '}');
        if ($a === false || $b === false || $b < $a) {
            return false;
        }
        $absatz = function (int $pos) use ($xml): array {
            $start = max((int)strrpos(substr($xml, 0, $pos), '<w:p '), (int)strrpos(substr($xml, 0, $pos), '<w:p>'));
            return [$start, strpos($xml, '</w:p>', $pos) + 6];
        };
        [$startA, $endeA] = $absatz($a);
        [$startB, $endeB] = $absatz($b);
        $innen = $behalten ? substr($xml, $endeA, $startB - $endeA) : '';
        $this->tempDocumentMainPart = substr($xml, 0, $startA) . $innen . substr($xml, $endeB);
        return true;
    }
}

/** Jahr aus ?year= (Standard: laufendes Jahr); ausserhalb 2000 … nächste fünf Jahre → 400 mit Klartext. */
function absendenbuchJahr(): int
{
    $jahr = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    if ($jahr < 2000 || $jahr > (int)date('Y') + 5) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr. Bitte oben auf der Seite ein Jahr wählen.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $jahr;
}

/** Hat das Jahr Endstich-Resultate? Ohne sie gibt es kein Absendenbuch (sonst ein leeres Heft). */
function absendenbuchHatEndstich(mysqli $conn, int $jahr): bool
{
    $st = $conn->prepare('SELECT 1 FROM ' . endschQuelle('endstich', ENDSCH_TYPEN_ALLE) . ' u
                           WHERE COALESCE(u.Schuss1 + u.Schuss2 + u.Schuss3 + u.Schuss4 + u.Schuss5
                                        + u.Schuss6 + u.Schuss7 + u.Schuss8 + u.Schuss9 + u.Schuss10, 0) != 0
                           LIMIT 1');
    $st->bind_param('i', $jahr);
    $st->execute();
    $hat = (bool)$st->get_result()->fetch_row();
    $st->close();
    return $hat;
}

/**
 * Füllt die Absendenbuch-Vorlage für ein Jahr. Der Aufrufer speichert mit ->saveAs().
 * $hinweise: Klartext-Liste für den Vorstand (leere oder unvollständige Abschnitte, Platzhalter-Reste).
 */
function absendenbuchTemplateFuellen(int $selectedYear, mysqli $conn, ?array &$hinweise = null): TemplateProcessor
{
    $GLOBALS['selectedYear'] = $selectedYear; // von den get*-Funktionen in functions.inc.php gelesen
    $GLOBALS['absendenHinweise'] = [];

    if (!file_exists(ABSENDENBUCH_TEMPLATE)) {
        error_log('[absendenbuch] Vorlage fehlt: ' . ABSENDENBUCH_TEMPLATE);
        throw new RuntimeException('Die Vorlage des Absendenbuchs fehlt auf dem Server. Bitte dem Admin melden.');
    }

    // Druckdatum im Format «15. September 2026»
    $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
    $formatter->setPattern('d. MMMM yyyy');
    $printdatum = $formatter->format(new DateTime());
    $textRun = new TextRun();

    $tp = new AbsendenbuchVorlage(ABSENDENBUCH_TEMPLATE);
    $tp->setValue('Year', (string)$selectedYear);
    $tp->setValue('PrintDate', $printdatum);

    // Jeder Abschnitt einzeln: scheitert einer, fehlt nur er (mit Hinweis), nicht das ganze Buch.
    $abschnitte = [
        'Jungschützen'               => fn() => getJungschuetzenResultate($tp, $conn),
        'Partner'                    => fn() => getPartnerResultate($tp, $conn),
        'Endstich'                   => fn() => getEndstich($tp, $conn),
        'Schwinistich'               => fn() => getSchwini($tp, $conn),
        'Siegerlisten'               => fn() => getSieger($tp, $conn),
        'Zabigstich'                 => fn() => getZabig($tp, $conn),
        'Glückstich'                 => fn() => getGlueck($tp, $conn, $textRun),
        'Kunststich'                 => fn() => getKunst($tp, $conn),
        'Endschiessen Total Kat. A'  => fn() => getEndschGesamt($tp, $conn, 'Kat. A'),
        'Endschiessen Total Kat. B'  => fn() => getEndschGesamt($tp, $conn, 'Kat. B'),
        'Heimmeisterschaft Kat. A'   => fn() => getHeim($tp, $conn, 'Kat. A'),
        'Heimmeisterschaft Kat. B'   => fn() => getHeim($tp, $conn, 'Kat. B'),
        'Vereinscup'                 => fn() => getCup($tp, $conn),
        'Kantonalstich'              => fn() => getKanti($tp, $conn),
        'Jahresmeisterschaft Kat. A' => fn() => getJMA($tp, $conn),
        'Jahresmeisterschaft Kat. B' => fn() => getJMB($tp, $conn),
    ];
    foreach ($abschnitte as $titel => $fuellen) {
        $GLOBALS['absendenAbschnitt'] = $titel;
        try {
            $fuellen();
        } catch (Throwable $e) {
            error_log('[absendenbuch] ' . $titel . ': ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            absendenHinweis('konnte nicht erstellt werden und fehlt oder ist unvollständig.');
        }
    }

    // Kontrolle des fertigen Buchs: offene Blockmarken (Abschnitt brach vorher ab) auflösen,
    // danach darf kein ${…} mehr stehen. Reste leeren, damit nichts davon gedruckt wird, und melden.
    $GLOBALS['absendenAbschnitt'] = 'Vorlage';
    foreach (array_unique($tp->getVariables()) as $v) {
        if (substr($v, -6) === '_BLOCK' && $v[0] !== '/') {
            absendenBlock($tp, $v, true);
        }
    }
    $rest = array_values(array_unique($tp->getVariables()));
    if ($rest) {
        foreach ($rest as $v) {
            $tp->setValue($v, '');
        }
        error_log('[absendenbuch] ungefüllte Platzhalter: ' . implode(', ', $rest));
        absendenHinweis(count($rest) . ' Platzhalter blieben ungefüllt und sind im Buch leer ('
            . implode(', ', array_map(fn($v) => '${' . $v . '}', array_slice($rest, 0, 5)))
            . (count($rest) > 5 ? ' …' : '') . '). Bitte dem Admin melden.');
    }
    unset($GLOBALS['absendenAbschnitt']);

    $hinweise = $GLOBALS['absendenHinweise'];
    return $tp;
}
