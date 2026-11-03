<?php
// ZielscheibeReport.php - Zielscheiben (Trefferbilder) als PDF, ein Block pro Stich

require_once __DIR__ . '/PDFGenerator.php';
require_once __DIR__ . '/ZielscheibeGeneratorImagick.php';
require_once __DIR__ . '/ZielscheibeGeneratorKeiler.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class ZielscheibeReport extends PDFGenerator {
    /** @var array<int, array{programmNummer:string, stichName:string, passe:int, schuesse:array}> */
    private $alleStiche;
    private $schuetzenName;
    private $customPdfDir = null;
    /** Programmnummer => ['stich' => ..., 'restable' => ...], aus interne_stichdefinition */
    private $stichMeta = [];
    /** Dokumentname in der Fusszeile (Verein · Dokument · Stand, Seite x von y) */
    private $fussText = '';

    public function __construct($conn, $year = null, array $alleStiche = [], $schuetzenName = null) {
        parent::__construct($conn, $year);
        $this->alleStiche    = $alleStiche;
        $this->schuetzenName = $schuetzenName !== null && $schuetzenName !== '' ? (string) $schuetzenName : null;
        $this->ladeStichMeta();
    }

    public function setPDFOutputDir($dir) {
        $this->customPdfDir = $dir;
    }

    /**
     * Erzeugt das PDF und gibt den Browser-relativen Pfad zurück (z.B. inc/endsch_targetprint/dat/x.pdf).
     * @throws Exception wenn keine Daten vorhanden sind oder kein Stich gezeichnet werden konnte
     */
    public function generate(): string {
        $hatSchuesse = false;
        foreach ($this->alleStiche as $stich) {
            if (!empty($stich['schuesse'])) { $hatSchuesse = true; break; }
        }
        if (!$hatSchuesse) {
            throw new Exception('Keine Schüsse in den Stichen vorhanden');
        }

        // Ranglisten-Layout: Logo links, Titel, Name als Untertitel; Fusszeile in generatePDFToCustomDir()
        $this->fussText = 'Zielscheibe ' . (int) $this->selectedYear . ($this->schuetzenName ? ' · ' . $this->schuetzenName : '');
        $html = pdf_rangliste_anfang('Zielscheibe ' . (int) $this->selectedYear, (string) $this->schuetzenName, $this->getCustomStyles())
              . '<div class="container">';

        $gezeichnet = 0;
        foreach ($this->alleStiche as $stichIndex => $stich) {
            $treffer = $stich['schuesse'] ?? [];
            if (empty($treffer)) {
                continue;
            }

            $programmNummer = (string) ($stich['programmNummer'] ?? '');
            $meta           = $this->stichMeta[$programmNummer] ?? null;
            $stichName      = $meta['stich'] ?? ($stich['stichName'] ?? '');
            $istKeilerStich = ($meta['restable'] ?? '') === 'schwini';

            if ($istKeilerStich) {
                $keilerBildPfad = __DIR__ . '/keiler_scheibe.jpg';
                if (!file_exists($keilerBildPfad)) {
                    error_log('[TARGETPRINT] Keiler-Bild nicht gefunden: ' . $keilerBildPfad);
                    continue;
                }
                $generator = new ZielscheibeGeneratorKeiler(1200, 1200);
                $generator->setzeSkalierungsfaktor(1.6);
                $result = $generator->generiereZielscheibeBlob($treffer, $keilerBildPfad);
            } else {
                $generator = new ZielscheibeGeneratorImagick(1200, 1200);
                $generator->setzeKoordinatenFaktor(1.1);
                $result = $generator->generiereZielscheibeBlob($treffer, false);
            }

            if (empty($result['success'])) {
                error_log('[TARGETPRINT] Zielscheiben-Generierung fehlgeschlagen für Stich ' . $stichIndex
                    . ' (Programm ' . $programmNummer . '): ' . ($result['error'] ?? 'unbekannt'));
                continue;
            }
            $gezeichnet++;

            $bildBase64 = 'data:' . $result['mime'] . ';base64,' . base64_encode($result['blob']);

            // Block: links Stichname + Schüsse, rechts das Trefferbild (eine Zeile, wird nie geteilt)
            $html .= '<table class="rl-scheibe' . ($gezeichnet > 1 ? ' rl-scheibe-folge' : '') . '"><tr>';
            $html .= '<td class="rl-scheibe-text">';

            if ($stichName !== '') {
                $vollstichName = htmlspecialchars($stichName, ENT_QUOTES, 'UTF-8');
                $passe = (int) ($stich['passe'] ?? 0);
                if ($passe > 0) {
                    $vollstichName .= ' · ' . $passe . '. Passe';
                }
                $html .= '<h2 class="rl-abschnitt">' . $vollstichName . '</h2>';
            }

            $html .= $this->createStatistikTable($treffer);
            $html .= '</td>';

            $bildStyle = $istKeilerStich ? 'width: 500px; height: auto;' : 'width: 400px; height: 400px;';
            $html .= '<td class="rl-scheibe-bild">';
            $html .= '<img src="' . $bildBase64 . '" style="' . $bildStyle . '" alt="Zielscheibe">';
            $html .= '</td></tr></table>';
        }

        if ($gezeichnet === 0) {
            throw new Exception('Kein Stich konnte gezeichnet werden');
        }

        $html .= $this->createHTMLFooter();

        $filename = 'Zielscheibe';
        if ($this->schuetzenName) {
            $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $this->schuetzenName);
            if ($safe !== '') {
                $filename .= '_' . $safe;
            }
        }

        if ($this->customPdfDir) {
            return $this->generatePDFToCustomDir($html, $filename, 'portrait', $this->customPdfDir);
        }
        return $this->generatePDF($html, $filename, 'portrait');
    }

    /** Programmnummern -> Stichname/Tabelle einmal laden (statt einer Abfrage pro Stich). */
    private function ladeStichMeta(): void {
        if (!$this->conn) {
            return;
        }
        $res = $this->conn->query('SELECT stich, restable, nummer1, nummer2, nummer3 FROM interne_stichdefinition');
        if (!$res) {
            return;
        }
        while ($row = $res->fetch_assoc()) {
            foreach (['nummer1', 'nummer2', 'nummer3'] as $col) {
                $nr = trim((string) ($row[$col] ?? ''));
                if ($nr !== '') {
                    $this->stichMeta[$nr] = ['stich' => $row['stich'], 'restable' => $row['restable']];
                }
            }
        }
        $res->free();
    }

    protected function generatePDFToCustomDir($html, $filename, $orientation, $customDir) {
        $pdfFilename = $filename . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $pdfPath     = rtrim($customDir, '/\\') . '/' . $pdfFilename;

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();
        pdf_seitenfuss($dompdf, $this->fussText);

        if (file_put_contents($pdfPath, $dompdf->output()) === false) {
            throw new Exception('PDF konnte nicht gespeichert werden');
        }

        return 'inc/endsch_targetprint/dat/' . $pdfFilename;
    }

    /** Ergänzungen zum Ranglisten-Stil: Block Text | Trefferbild, dünne Linie zwischen den Stichen */
    private function getCustomStyles() {
        return '
            table.rl-scheibe { width: 100%; border-collapse: collapse; margin: 0 0 4mm 0; page-break-inside: avoid; }
            table.rl-scheibe-folge { border-top: 0.5pt solid #999; padding-top: 4mm; }
            table.rl-scheibe-folge td.rl-scheibe-text, table.rl-scheibe-folge td.rl-scheibe-bild { padding-top: 4mm; }
            td.rl-scheibe-text { width: 38%; vertical-align: top; padding-right: 6mm; }
            td.rl-scheibe-bild { width: 62%; vertical-align: top; text-align: center; }
            td.rl-scheibe-text .rl-abschnitt { margin-top: 0; }
            .stats-table { font-size: 9pt; }
            .stats-table th, .stats-table td { padding: 0.9mm 1.4mm; }
        ';
    }

    private function createStatistikTable(array $treffer) {
        $html = '<table class="table stats-table"><thead><tr>';
        $html .= '<th class="r">Schuss</th><th class="r">Wertung</th><th class="r">100er</th>';
        $html .= '</tr></thead><tbody>';

        $totalWertung = 0;
        $max100er     = 0;

        foreach ($treffer as $schuss) {
            $nr        = (int) ($schuss['schuss_nr'] ?? 0);
            $wert      = (int) ($schuss['wert'] ?? 0);
            $hunderter = (int) ($schuss['hunderter'] ?? 0);

            $totalWertung += $wert;
            $max100er = max($max100er, $hunderter);

            $html .= '<tr><td class="r">' . ($nr > 0 ? $nr : '?') . '</td><td class="r">' . $wert . '</td><td class="r">' . $hunderter . '</td></tr>';
        }

        $html .= '<tr class="rl-total"><td>Total</td><td class="r">' . $totalWertung . '</td><td class="r">' . $max100er . '</td></tr>';
        $html .= '</tbody></table>';

        return $html;
    }
}
