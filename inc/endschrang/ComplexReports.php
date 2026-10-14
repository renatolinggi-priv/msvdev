<?php
// ComplexReports.php - Gesamt- und Zwischenrangliste

require_once 'PDFGenerator.php';
require_once __DIR__ . '/../endschresultate/teilnehmer.inc.php';

/**
 * Basis-Klasse für Ranglisten mit mehreren Kategorien
 */
abstract class MultiKategorieReport extends PDFGenerator {
    
    // Abfrage zentral in endschresultate/teilnehmer.inc.php (auch für «Sieger der letzten Jahre»)
    protected function getGesamtSQL($kat, $includeZabig = true) {
        return endschGesamtAbfrage($kat, (int)$this->selectedYear, $includeZabig);
    }

    protected function getZabigCalculation() {
        return endschZabigPunkteSql();
    }

    protected function createKategorieTable($kat, $data, $includeZabig = true) {
        $html = "<h2>" . ($kat === 'Kat. A' ? 'Kat. A (Standardgewehr)' : 'Kat. B (Ordonnanzwaffen)') . "</h2>";
        $html .= '<table class="table">';
        $html .= '<thead><tr>
                    <th scope="col" class="r">Rang</th>
                    <th scope="col">Name</th>
                    <th scope="col" class="r">Endstich</th>
                    <th scope="col" class="r">Schwini</th>
                    <th scope="col" class="r">Kunst</th>
                    <th scope="col" class="r">Glück</th>';
        
        if ($includeZabig) {
            $html .= '<th scope="col" class="r">Zabig</th>';
        }
        
        $html .= '<th scope="col" class="r">Total</th>
                  <th scope="col"></th>
                  </tr></thead><tbody>';
        
        $rang = 1;
        $wp = endschWpVergeben($data); // Wanderpreis nur für Mitglieder (Gäste können in Kat. B vorne stehen)
        foreach ($data as $i => $row) {
            $kk = '';

            if ($rang == 1) {
                $kk = '3KK';
            } elseif ($rang <= 3) {
                $kk = 'KK';
            }
            if ($i === $wp) {
                $kk = $kk !== '' ? $kk . ' + WP' : 'WP';
            }
            
            $html .= $this->zeileAnfang($rang);
            $html .= "<td>{$row['Name']} {$row['Vorname']}</td>";
            $html .= '<td class="r">' . pdf_strich_wenn_null($row['EndstichTotal']) . '</td>';
            $html .= '<td class="r">' . pdf_strich_wenn_null($row['MaxSchwini'] ?? '') . '</td>';
            $html .= '<td class="r">' . pdf_strich_wenn_null($row['KunstTotal']) . '</td>';
            $html .= '<td class="r">' . pdf_strich_wenn_null($row['GlueckTotal']) . '</td>';

            if ($includeZabig) {
                $html .= '<td class="r">' . pdf_strich_wenn_null($row['ZabigTotal']) . '</td>';
            }
            
            $html .= "<td class=\"r\">{$row['GesamtTotal']}</td>";
            $html .= "<td>$kk</td>";
            $html .= "</tr>";
            
            $rang++;
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
}

/**
 * Gesamtrangliste (mit Zabig)
 */
class GesamtRanglisteReport extends MultiKategorieReport {
    public function generate() {
        $html = $this->ranglisteHeader('Endschiessen ' . $this->selectedYear, 'Gesamtrangliste');
        
        // Kategorie A
        $qA = $this->getGesamtSQL('Kat. A', true);
        $dataA = $this->executePreparedQuery($qA['sql'], $qA['types'], ...$qA['params']);
        if (!empty($dataA)) {
            $html .= $this->createKategorieTable('Kat. A', $dataA, true);
        }

        

        // Kategorie B
        $qB = $this->getGesamtSQL('Kat. B', true);
        $dataB = $this->executePreparedQuery($qB['sql'], $qB['types'], ...$qB['params']);
        if (!empty($dataB)) {
            $html .= $this->createKategorieTable('Kat. B', $dataB, true);
        }

        $html .= $this->createHTMLFooter();

        $pdfPath = $this->generatePDF($html, 'EndschiessenGesamtrangliste');
        $this->outputDownloadLink($pdfPath);
    }
}

/**
 * Zwischenrangliste (ohne Zabig)
 */
class ZwischenRanglisteReport extends MultiKategorieReport {
    public function generate() {
        $html = $this->ranglisteHeader('Endschiessen ' . $this->selectedYear, 'Zwischenrangliste (ohne Zabig)');
        
        // Kategorie A
        $qA = $this->getGesamtSQL('Kat. A', false);
        $dataA = $this->executePreparedQuery($qA['sql'], $qA['types'], ...$qA['params']);
        if (!empty($dataA)) {
            $html .= $this->createKategorieTable('Kat. A', $dataA, false);
        }

        

        // Kategorie B
        $qB = $this->getGesamtSQL('Kat. B', false);
        $dataB = $this->executePreparedQuery($qB['sql'], $qB['types'], ...$qB['params']);
        if (!empty($dataB)) {
            $html .= $this->createKategorieTable('Kat. B', $dataB, false);
        }

        $html .= $this->createHTMLFooter();

        $pdfPath = $this->generatePDF($html, 'EndschschiessenZwischenrangliste');
        $this->outputDownloadLink($pdfPath);
    }
}