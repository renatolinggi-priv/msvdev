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

use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun;

// Vorlage seit 14.10.2026 (aus 20261013 per Skript, nach Word-Vorschauen mit dem Vorstand): Seite «Sieger ${Year}» mit
// ${SIEGER_SEITE} nach dem Deckblatt, Textmarken _abs_* an den Abschnittstiteln (Seitenverweise), Seitenzahlen unten
// Mitte ohne Deckblatt, rechte JM-Hälfte mit gleich hohem Leerabsatz statt Umbruch (Zeilen über den Falz auf gleicher
// Höhe), ohne «Buchfalz» (Word drehte sonst die gespiegelten Ränder um: aussen 5 statt 10 mm), Tabellen ausser JM in
// 9 pt mit festen Spaltenbreiten und rechtsbündigen Zahlen, Siegerlisten ohne Namensumbruch.
// 20261014b (Polish nach Critique 14.10.2026): überall «Rang», Rang rechtsbündig, KK/WP links nach dem Total, Blindzeile
// unter dem Kopf überall 3 pt, Lücken zusammen 240 Twips (Kantonalstich Kat. A/B im selben Raster), Siegerlisten volle
// Breite, Titel auf S. 12 mit «Seitenumbruch oberhalb» statt Umbruchzeichen.
// 20261014c = 20261014b, in Word mit vollständig eingebetteter Schrift (Aptos, alle Schnitte) gespeichert, damit der
// PDF-Dienst nicht auf eine Ersatzschrift ausweicht.
// 20261014d (aus c, Schrift bleibt eingebettet): Deckblatt mit Titel oben und einem Logo unten (60 mm, mittig), Ort/Datum
// 11 pt unter «Ranglisten», alle Deckblatt-Absätze ohne alte Einzüge (eine Mittelachse); Rückseite «Herzlichen Dank» als
// eigener Abschnitt, senkrecht zentriert, ohne Seitenzahl (Heft 24 Seiten).
// 20261014e (aus d): Ranglisten-Raster neu (Leerspalten ohne Zellabstand – Word hatte sie beim Speichern auf 134 Twips
// verbreitert, tcW passte nicht mehr), Endstich mit Spalte TS, Datenzeilen 5 mm (ausser Endschiessen S. 11), Zabig- und
// Glück-Siegerliste zusammen, Kunst-Siegerliste nach der Kunst-Rangliste, pgSz ohne orient=landscape.
// Rückfall: 20261014d in dat/; ältere Vorlagen nur noch in git. Neue Vorlagen per Skript bauen (ABSENDENBUCH_KONZEPT.md).
const ABSENDENBUCH_TEMPLATE = __DIR__ . '/dat/Resultatbuch_Template20261014e.docx';

/**
 * TemplateProcessor für das Absendenbuch:
 *   - Schaltet beim Erzeugen Settings::setOutputEscapingEnabled() ein. Ohne den Schalter setzt PhpWord Werte
 *     (setValue) und den Text der Tabellen aus setComplexBlock() (JM, Cup) roh ins XML; ein «&» oder «<» in einem
 *     Namen oder Anlass zerstörte dann das ganze Dokument.
 *   - Werte als AbsendenFett (formatBold() in functions.inc.php) werden fett gesetzt, indem der Run der Vorlage
 *     <w:b/> bekommt. Schriftgrösse und Schrift der Vorlage bleiben, und das XML ist gültiges OOXML (früher stand
 *     ein eigener <w:r> mitten im <w:t> der Vorlage).
 *   - blockAufloesen(): robuste Block-Auflösung. PhpWords cloneBlock() sucht mit einer gierigen Regex über
 *     das ganze Dokument (~400 KB) und scheitert dabei je nach Grösse still; hier genügt eine String-Suche.
 */
class AbsendenbuchVorlage extends TemplateProcessor
{
    public function __construct($documentTemplate)
    {
        Settings::setOutputEscapingEnabled(true); // vor allen setValue()/setComplexBlock()-Aufrufen
        parent::__construct($documentTemplate);
    }

    public function setValue($search, $replace, $limit = self::MAXIMUM_REPLACEMENTS_DEFAULT): void
    {
        if (is_array($search)) {
            foreach ($search as $i => $s) {
                $this->setValue($s, is_array($replace) ? ($replace[$i] ?? '') : $replace, $limit);
            }
            return;
        }
        if ($replace instanceof AbsendenFett) {
            $this->setValueFett((string)$search, $replace->text, (int)$limit);
            return;
        }
        parent::setValue($search, (string)$replace, $limit); // maskiert dank Settings selbst
    }

    /** Platzhalter durch Text ersetzen und den umgebenden Run fett machen (Format der Vorlage bleibt). */
    private function setValueFett(string $search, string $text, int $limit): void
    {
        $makro = (substr($search, 0, 2) === '${') ? $search : '${' . $search . '}';
        $wert  = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml   = $this->tempDocumentMainPart;
        $n = 0;
        $ab = 0;
        while (($limit < 0 || $n < $limit) && ($pos = strpos($xml, $makro, $ab)) !== false) {
            $vor   = substr($xml, 0, $pos);
            $start = max((int)strrpos($vor, '<w:r>'), (int)strrpos($vor, '<w:r '));
            $ende  = strpos($xml, '</w:r>', $pos);
            if ($start === 0 || $ende === false) {
                // ohne umgebenden Run (sollte nicht vorkommen): nur den Text einsetzen
                $xml = substr_replace($xml, $wert, $pos, strlen($makro));
                $ab  = $pos + strlen($wert);
            } else {
                $run = self::runFett(str_replace($makro, $wert, substr($xml, $start, $ende + 6 - $start)));
                $xml = substr($xml, 0, $start) . $run . substr($xml, $ende + 6);
                $ab  = $start + strlen($run);
            }
            $n++;
        }
        $this->tempDocumentMainPart = $xml;
    }

    /** <w:b/><w:bCs/> in die Run-Eigenschaften, an der Stelle, die das Schema verlangt (nach rStyle/rFonts). */
    private static function runFett(string $run): string
    {
        if (preg_match('#<w:rPr>(.*?)</w:rPr>#s', $run, $m, PREG_OFFSET_CAPTURE)) {
            $inhalt = preg_replace('#<w:bCs?(?:\s[^>]*)?/>#', '', $m[1][0]);
            preg_match('#^\s*(?:<w:rStyle[^>]*/>)?\s*(?:<w:rFonts[^>]*/>)?#', $inhalt, $kopf);
            $neu = '<w:rPr>' . $kopf[0] . '<w:b/><w:bCs/>' . substr($inhalt, strlen($kopf[0])) . '</w:rPr>';
            return substr_replace($run, $neu, $m[0][1], strlen($m[0][0]));
        }
        // Run ohne Eigenschaften: direkt nach dem öffnenden Tag einfügen
        $tagEnde = strpos($run, '>') + 1;
        return substr($run, 0, $tagEnde) . '<w:rPr><w:b/><w:bCs/></w:rPr>' . substr($run, $tagEnde);
    }

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

    /**
     * Absatz mit ${NAME} durch fertiges WordprocessingML ersetzen (z.B. eine Tabelle mit Feldern, die PhpWord nicht
     * erzeugt). $xml wird nicht maskiert: Werte darin muss der Aufrufer selbst maskieren. false ohne Platzhalter.
     */
    public function absatzDurchXml(string $name, string $xml): bool
    {
        $doc = $this->tempDocumentMainPart;
        $pos = strpos($doc, '${' . $name . '}');
        if ($pos === false) {
            return false;
        }
        $start = max((int)strrpos(substr($doc, 0, $pos), '<w:p '), (int)strrpos(substr($doc, 0, $pos), '<w:p>'));
        $ende = strpos($doc, '</w:p>', $pos) + 6;
        $this->tempDocumentMainPart = substr($doc, 0, $start) . $xml . substr($doc, $ende);
        return true;
    }
    /**
     * Seitenverweise (PAGEREF _abs_*) mit der Seitenzahl vorbelegen. Word rechnet solche Felder beim Öffnen vor dem
     * Umbruch aus (zeigte überall «1»), darum zählt der Generator selbst: Seitenumbrüche, «Seitenumbruch oberhalb» und
     * Abschnittswechsel (gerade/ungerade Seite) in Dokumentreihenfolge. Das stimmt, solange kein Abschnitt über seine
     * Seite hinauswächst; beim Drucken und beim Umwandeln in PDF rechnen Word bzw. der PDF-Dienst die Felder ohnehin neu.
     * Liefert Textmarke => Seite.
     */
    public function seitenverweiseFuellen(): array
    {
        $xml = $this->tempDocumentMainPart;
        preg_match_all('#<w:sectPr\b.*?</w:sectPr>#s', $xml, $sect);
        $typen = array_map(fn($s) => preg_match('#<w:type w:val="(\w+)"#', $s, $m) ? $m[1] : 'nextPage', $sect[0]);
        preg_match_all('#<w:pageBreakBefore(?: w:val="(?:1|true|on)")?\s*/>|<w:br w:type="page"\s*/>|<w:bookmarkStart\b[^>]*w:name="(_abs_\w+)"|<w:sectPr\b.*?</w:sectPr>#s',
            $xml, $treffer, PREG_SET_ORDER);
        $seite = 1;
        $abschnitt = 0;
        $seiten = [];
        foreach ($treffer as $t) {
            if (str_starts_with($t[0], '<w:sectPr')) {
                $naechster = $typen[++$abschnitt] ?? null;
                if ($naechster === null || $naechster === 'continuous') continue;
                $seite++;
                if (($naechster === 'evenPage' && $seite % 2 === 1) || ($naechster === 'oddPage' && $seite % 2 === 0)) $seite++;
            } elseif (!empty($t[1])) {
                $seiten[$t[1]] ??= $seite;
            } else {
                $seite++;
            }
        }
        $this->tempDocumentMainPart = preg_replace_callback(
            '#( PAGEREF (_abs_\w+) \\\\h </w:instrText></w:r><w:r>(?:<w:rPr>.*?</w:rPr>)?<w:fldChar w:fldCharType="separate"/></w:r><w:r>(?:<w:rPr>.*?</w:rPr>)?<w:t>)(</w:t>)#s',
            fn($m) => isset($seiten[$m[2]]) ? $m[1] . $seiten[$m[2]] . $m[3] : $m[0],
            $xml
        );
        return $seiten;
    }
}

/**
 * Tabelle der Seite «Sieger <Jahr>»: pro Wettkampf Sieger, Resultat und Seite (Feld PAGEREF auf die Textmarke
 * _abs_<Marke> am Abschnittstitel; der Generator belegt die Zahl vor (seitenverweiseFuellen()), Drucken und PDF-Dienst
 * rechnen sie neu). Nur Wettkämpfe, die im Buch stehen ($sieger aus absendenSiegerMerken()).
 */
function absendenbuchSiegerSeiteXml(array $sieger): string
{
    $reihe = [ // Marke => [Bezeichnung, Textmarke]
        'Endstich' => ['Endstich', 'Endstich'], 'Schwinistich' => ['Schwinistich', 'Schwinistich'],
        'Zabigstich' => ['Zabigstich', 'Zabigstich'], 'Glueckstich' => ['Glückstich', 'Glueckstich'], 'Kunststich' => ['Kunststich', 'Kunststich'],
        'EndschA' => ['Endschiessen Kat. A', 'EndschA'], 'EndschB' => ['Endschiessen Kat. B', 'EndschB'],
        'EndschJ' => ['Endschiessen Jungschützen', 'EndschJ'], 'EndschP' => ['Endschiessen Partner', 'EndschP'],
        'JMA' => ['Jahresmeisterschaft Kat. A', 'JMA'], 'JMB' => ['Jahresmeisterschaft Kat. B', 'JMB'],
        'HeimA' => ['Heimmeisterschaft Kat. A', 'HeimA'], 'HeimB' => ['Heimmeisterschaft Kat. B', 'HeimB'],
        'KantiA' => ['Kantonalstich Kat. A', 'KantiA'], 'KantiB' => ['Kantonalstich Kat. B', 'KantiB'],
        'Cup' => ['Vereinscup (Final)', 'Cup'], 'Standcup' => ['Standcup', 'Cup'],
    ];
    $e = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $rPr = fn(bool $fett): string => '<w:rPr>' . ($fett ? '<w:b/><w:bCs/>' : '') . '<w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr>';
    $run = fn(string $t, bool $fett = false): string => '<w:r>' . $rPr($fett) . '<w:t xml:space="preserve">' . $e($t) . '</w:t></w:r>';
    $zelle = function (int $w, string $inhalt, bool $rechts = false, bool $linie = false): string {
        return '<w:tc><w:tcPr><w:tcW w:w="' . $w . '" w:type="dxa"/>'
            . ($linie ? '<w:tcBorders><w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/></w:tcBorders>' : '')
            . '</w:tcPr><w:p><w:pPr><w:spacing w:before="40" w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="' . ($rechts ? 'right' : 'left') . '"/></w:pPr>'
            . $inhalt . '</w:p></w:tc>';
    };
    $b = [2750, 2900, 1000, 918];
    $xml = '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($b) . '" w:type="dxa"/><w:tblLayout w:type="fixed"/>'
        . '<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders>'
        . '<w:tblCellMar><w:left w:w="108" w:type="dxa"/><w:right w:w="108" w:type="dxa"/></w:tblCellMar><w:tblLook w:val="0000"/></w:tblPr><w:tblGrid>';
    foreach ($b as $w) $xml .= '<w:gridCol w:w="' . $w . '"/>';
    $xml .= '</w:tblGrid><w:tr>' . $zelle($b[0], $run('Wettkampf', true), false, true) . $zelle($b[1], $run('Sieger', true), false, true)
        . $zelle($b[2], $run('Resultat', true), true, true) . $zelle($b[3], $run('Seite', true), true, true) . '</w:tr>';
    $mitBlock = !empty($sieger['JMA']['name']) || !empty($sieger['JMB']['name']);
    foreach ($reihe as $marke => [$bezeichnung, $textmarke]) {
        if (empty($sieger[$marke]['name'])) continue;
        if ($mitBlock && in_array($marke, ['JMA', 'JMB'], true)) continue; // stehen schon im Block «Jahresmeister»
        $feld = '<w:r>' . $rPr(false) . '<w:fldChar w:fldCharType="begin"/></w:r>'
            . '<w:r>' . $rPr(false) . '<w:instrText xml:space="preserve"> PAGEREF _abs_' . $textmarke . ' \h </w:instrText></w:r>'
            . '<w:r>' . $rPr(false) . '<w:fldChar w:fldCharType="separate"/></w:r><w:r>' . $rPr(false) . '<w:t></w:t></w:r>'
            . '<w:r>' . $rPr(false) . '<w:fldChar w:fldCharType="end"/></w:r>';
        $xml .= '<w:tr><w:trPr><w:trHeight w:val="300"/></w:trPr>' . $zelle($b[0], $run($bezeichnung)) . $zelle($b[1], $run($sieger[$marke]['name'], true))
            . $zelle($b[2], $run($sieger[$marke]['resultat']), true) . $zelle($b[3], $feld, true) . '</w:tr>';
    }
    return absendenbuchJahresmeisterXml($sieger) . $xml . '</w:tbl>';
}

/**
 * Block «Jahresmeister» über der Siegertabelle: die zwei Jahresmeister sind der Höhepunkt des Abends und stehen darum
 * etwas grösser, aber im selben Spaltenraster wie die Tabelle (Resultat und Seite in denselben Spalten). Ohne
 * JM-Sieger entfällt der Block.
 */
function absendenbuchJahresmeisterXml(array $sieger): string
{
    $e = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $rPr = fn(int $sz, bool $fett = false, string $farbe = ''): string => '<w:rPr>' . ($fett ? '<w:b/><w:bCs/>' : '')
        . ($farbe !== '' ? '<w:color w:val="' . $farbe . '"/>' : '') . '<w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/></w:rPr>';
    $run = fn(string $t, int $sz, bool $fett = false, string $farbe = ''): string => '<w:r>' . $rPr($sz, $fett, $farbe) . '<w:t xml:space="preserve">' . $e($t) . '</w:t></w:r>';
    $zelle = fn(int $w, string $inhalt, string $jc = 'left'): string => '<w:tc><w:tcPr><w:tcW w:w="' . $w . '" w:type="dxa"/><w:vAlign w:val="bottom"/></w:tcPr>'
        . '<w:p><w:pPr><w:spacing w:before="60" w:after="40" w:line="240" w:lineRule="auto"/><w:jc w:val="' . $jc . '"/></w:pPr>' . $inhalt . '</w:p></w:tc>';
    $b = [2750, 2900, 1000, 918]; // wie die Siegertabelle
    $zeilen = '';
    foreach (['JMA' => 'Kat. A (Standardgewehr)', 'JMB' => 'Kat. B (Ordonnanzwaffen)'] as $marke => $kat) {
        if (empty($sieger[$marke]['name'])) continue;
        $feld = '<w:r>' . $rPr(18) . '<w:fldChar w:fldCharType="begin"/></w:r><w:r>' . $rPr(18) . '<w:instrText xml:space="preserve"> PAGEREF _abs_' . $marke . ' \h </w:instrText></w:r>'
            . '<w:r>' . $rPr(18) . '<w:fldChar w:fldCharType="separate"/></w:r><w:r>' . $rPr(18) . '<w:t></w:t></w:r><w:r>' . $rPr(18) . '<w:fldChar w:fldCharType="end"/></w:r>';
        $zeilen .= '<w:tr>' . $zelle($b[0], $run($kat, 18)) . $zelle($b[1], $run($sieger[$marke]['name'], 18, true))
            . $zelle($b[2], $run($sieger[$marke]['resultat'], 18), 'right') . $zelle($b[3], $feld, 'right') . '</w:tr>';
    }
    if ($zeilen === '') {
        return '';
    }
    $grid = ''; foreach ($b as $w) $grid .= '<w:gridCol w:w="' . $w . '"/>';
    $titel = '<w:p><w:pPr><w:spacing w:before="0" w:after="40"/></w:pPr>' . $run('Jahresmeister', 20, true) . '</w:p>';
    $tabelle = '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($b) . '" w:type="dxa"/><w:tblLayout w:type="fixed"/>'
        . '<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders>'
        . '<w:tblCellMar><w:left w:w="108" w:type="dxa"/><w:right w:w="108" w:type="dxa"/></w:tblCellMar><w:tblLook w:val="0000"/></w:tblPr>'
        . '<w:tblGrid>' . $grid . '</w:tblGrid>' . $zeilen . '</w:tbl>';
    $abstand = '<w:p><w:pPr><w:spacing w:before="0" w:after="240"/></w:pPr></w:p>';
    return $titel . $tabelle . $abstand;
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
 * Zeile unten auf dem Deckblatt: Ort und Datum des Absendens, z.B. «Pfäffikon, 14. November 2026».
 * Termin wie auf der Startseite (dashboard_phasen.inc.php): Anlass im Jahresprogramm, dessen Bezeichnung
 * «Absenden» enthält, Datum aus JMSchiesstage, Ort = Teil der Adresse nach der Postleitzahl.
 * Fehlt der Ort: «Roggenacker»; fehlt der Termin: das Druckdatum – beides wie früher fest in der Vorlage.
 */
function absendenbuchDeckblatt(mysqli $conn, int $jahr, IntlDateFormatter $datumFormat): string
{
    $st = $conn->prepare("SELECT jd.Adresse, MIN(s.schiesstag) AS tag
                            FROM JMDefinition jd JOIN JMSchiesstage s ON s.jm_id = jd.ID
                           WHERE jd.year = ? AND jd.Bezeichnung LIKE '%Absenden%'
                           GROUP BY jd.ID, jd.Adresse ORDER BY tag LIMIT 1");
    $st->bind_param('i', $jahr);
    $st->execute();
    $termin = $st->get_result()->fetch_assoc();
    $st->close();

    $ort = 'Roggenacker';
    if ($termin && preg_match('/\b\d{4}\s+(.+?)\s*$/u', (string)$termin['Adresse'], $m)) {
        $ort = $m[1];
    }
    $datum = $datumFormat->format(new DateTime($termin['tag'] ?? 'now'));
    return $ort . ', ' . $datum;
}

/**
 * Füllt die Absendenbuch-Vorlage für ein Jahr. Der Aufrufer speichert mit ->saveAs().
 * $hinweise: Klartext-Liste für den Vorstand (leere oder unvollständige Abschnitte, Platzhalter-Reste).
 */
function absendenbuchTemplateFuellen(int $selectedYear, mysqli $conn, ?array &$hinweise = null): TemplateProcessor
{
    $GLOBALS['selectedYear'] = $selectedYear; // von den get*-Funktionen in functions.inc.php gelesen
    $GLOBALS['absendenHinweise'] = [];
    $GLOBALS['absendenHinweisDetails'] = [];
    $GLOBALS['absendenSieger'] = []; // Rang 1 je Wettkampf, für die Seite «Sieger <Jahr>» (absendenSiegerMerken())

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
    $tp->setValue('PrintDate', $printdatum); // ältere Vorlagen
    try {
        $tp->setValue('Deckblatt', absendenbuchDeckblatt($conn, $selectedYear, $formatter));
    } catch (Throwable $e) {
        error_log('[absendenbuch] Deckblatt: ' . $e->getMessage());
        $tp->setValue('Deckblatt', 'Roggenacker, ' . $printdatum);
    }

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

    // Seite «Sieger <Jahr>» aus den gemerkten Siegern (erst jetzt, da alle Abschnitte gelaufen sind)
    $GLOBALS['absendenAbschnitt'] = 'Sieger-Seite';
    try {
        $tp->absatzDurchXml('SIEGER_SEITE', absendenbuchSiegerSeiteXml($GLOBALS['absendenSieger']));
        $tp->seitenverweiseFuellen();
    } catch (Throwable $e) {
        error_log('[absendenbuch] Sieger-Seite: ' . get_class($e) . ': ' . $e->getMessage());
        absendenHinweis('konnte nicht erstellt werden, die Seite bleibt leer.');
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
