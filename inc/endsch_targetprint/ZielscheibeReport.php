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

        $html = $this->createCustomHTMLHeader($this->selectedYear, $this->getCustomStyles());

        $titel = 'Zielscheibe';
        if ($this->schuetzenName) {
            $titel .= ' - ' . htmlspecialchars($this->schuetzenName, ENT_QUOTES, 'UTF-8');
        }
        $titel .= ' ' . (int) $this->selectedYear;
        $html .= '<h2>' . $titel . '</h2>';

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

            $html .= '<table style="width: 100%; border: none; border-collapse: collapse; margin: 10px 0;"><tr>';
            $html .= '<td style="width: 40%; vertical-align: top; border: none; padding-right: 15px;">';

            if ($stichName !== '') {
                $vollstichName = htmlspecialchars($stichName, ENT_QUOTES, 'UTF-8');
                $passe = (int) ($stich['passe'] ?? 0);
                if ($passe > 0) {
                    $vollstichName .= ' - ' . $passe . '. Passe';
                }
                $html .= '<h4 style="color: #007bff; margin: 0 0 8px 0; padding: 0; font-size: 13px; font-weight: bold; text-align: left;">' . $vollstichName . '</h4>';
            }

            $html .= $this->createStatistikTable($treffer);
            $html .= '</td>';

            $bildStyle = $istKeilerStich ? 'width: 500px; height: auto;' : 'width: 400px; height: 400px;';
            $html .= '<td style="width: 60%; vertical-align: top; border: none; text-align: center;">';
            $html .= '<img src="' . $bildBase64 . '" style="' . $bildStyle . '" alt="Zielscheibe">';
            $html .= '</td></tr></table>';
            $html .= '<hr style="border: none; border-top: 2px solid #333; margin: 20px 0;">';
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

    private function createCustomHTMLHeader($year, $customStyles) {
        $styles = $this->getParentDefaultStyles() . $customStyles;

        return '<!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <style>' . $styles . '</style>
            <title>Zielscheibe ' . (int) $year . '</title>
        </head>
        <body>
        <div class="container">
            <div class="header">
                <img src="' . $this->logoBase64 . '" alt="Logo" style="width:60px; height:auto;">
            </div>';
    }

    private function getParentDefaultStyles() {
        return '
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
        .container { margin: 0; padding: 0; }
        .footer {
            position: fixed; bottom: 0; left: 0; right: 0; height: 30px;
            text-align: center; font-size: 9px; border-top: 1px; background-color: #ffffff;
        }
        .footer hr { border: none; border-top: 1px solid #cbd5e0; margin: 0; }';
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

        if (file_put_contents($pdfPath, $dompdf->output()) === false) {
            throw new Exception('PDF konnte nicht gespeichert werden');
        }

        return 'inc/endsch_targetprint/dat/' . $pdfFilename;
    }

    private function getCustomStyles() {
        return '
            @page { margin: 8mm 8mm; }
            h2 { text-align: center; color: #333; margin: 2px 0 5px 0; font-size: 11px; }
            .stats-table { width: 100%; border-collapse: collapse; font-size: 9px; }
            .stats-table th, .stats-table td { padding: 3px 4px; border: 1px solid #ddd; text-align: center; }
            .stats-table th {
                background-color: #eef2f7; color: #2d3748; border-bottom: 2px solid #cbd5e0;
                font-weight: bold; font-size: 9px;
            }
            .stats-table tr:nth-child(even) { background-color: #f9f9f9; }
            .total-row { font-weight: bold; background-color: #e8f4f8 !important; }
        ';
    }

    private function createStatistikTable(array $treffer) {
        $html = '<table class="stats-table"><thead><tr>';
        $html .= '<th>Schuss Nr.</th><th>Wertung</th><th>100er</th>';
        $html .= '</tr></thead><tbody>';

        $totalWertung = 0;
        $max100er     = 0;

        foreach ($treffer as $schuss) {
            $nr        = (int) ($schuss['schuss_nr'] ?? 0);
            $wert      = (int) ($schuss['wert'] ?? 0);
            $hunderter = (int) ($schuss['hunderter'] ?? 0);

            $totalWertung += $wert;
            $max100er = max($max100er, $hunderter);

            $html .= '<tr><td>' . ($nr > 0 ? $nr : '?') . '</td><td>' . $wert . '</td><td>' . $hunderter . '</td></tr>';
        }

        $html .= '<tr class="total-row"><td>Total</td><td>' . $totalWertung . '</td><td>' . $max100er . '</td></tr>';
        $html .= '</tbody></table>';

        return $html;
    }
}
