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
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/functions.inc.php';

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun;

const ABSENDENBUCH_TEMPLATE = __DIR__ . '/dat/Resultatbuch_Template20251015.docx';

/**
 * Füllt die Absendenbuch-Vorlage für ein Jahr. Der Aufrufer speichert mit ->saveAs().
 */
function absendenbuchTemplateFuellen(int $selectedYear, mysqli $conn): TemplateProcessor
{
    $GLOBALS['selectedYear'] = $selectedYear; // von den get*-Funktionen in functions.inc.php gelesen

    if (!file_exists(ABSENDENBUCH_TEMPLATE)) {
        throw new RuntimeException('Absendenbuch-Vorlage nicht gefunden: ' . basename(ABSENDENBUCH_TEMPLATE));
    }

    // Druckdatum im Format «15. September 2026»
    $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
    $formatter->setPattern('d. MMMM yyyy');
    $printdatum = $formatter->format(new DateTime());
    $textRun = new TextRun();

    $tp = new TemplateProcessor(ABSENDENBUCH_TEMPLATE);
    getJungschuetzenResultate($tp, $conn);
    getPartnerResultate($tp, $conn);
    $tp->setValue('Year', (string)$selectedYear);
    $tp->setValue('PrintDate', $printdatum);
    getEndstich($tp, $conn);
    getSchwini($tp, $conn);
    getSieger($tp, $conn);
    getZabig($tp, $conn);
    getGlueck($tp, $conn, $textRun);
    getKunst($tp, $conn);
    getEndschGesamt($tp, $conn, 'Kat. A');
    getEndschGesamt($tp, $conn, 'Kat. B');
    getHeim($tp, $conn, 'Kat. A');
    getHeim($tp, $conn, 'Kat. B');
    getCup($tp, $conn);
    getKanti($tp, $conn);
    getJMA($tp, $conn);
    getJMB($tp, $conn);

    return $tp;
}
