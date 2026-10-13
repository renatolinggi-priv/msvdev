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
        $html = "<h3>$kat</h3>";
        $html .= '<table class="table">';
        $html .= '<thead><tr>
                    <th scope="col" class="fixed-width">Rang</th>
                    <th scope="col" class="name-width">Name</th>
                    <th scope="col" class="fixed-width">Endstich</th>
                    <th scope="col" class="fixed-width">Schwini</th>
                    <th scope="col" class="fixed-width">Kunst</th>
                    <th scope="col" class="fixed-width">Glück</th>';
        
        if ($includeZabig) {
            $html .= '<th scope="col" class="fixed-width">Zabig</th>';
        }
        
        $html .= '<th scope="col" class="total-width">Total</th>
                  <th scope="col" class="fixed-width"></th>
                  </tr></thead><tbody>';
        
        $rang = 1;
        $wp = endschWpVergeben($data); // Wanderpreis nur für Mitglieder (Gäste können in Kat. B vorne stehen)
        foreach ($data as $i => $row) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';
            $kk = '';

            if ($rang == 1) {
                $kk = '3KK';
            } elseif ($rang <= 3) {
                $kk = 'KK';
            }
            if ($i === $wp) {
                $kk = $kk !== '' ? $kk . ' + WP' : 'WP';
            }
            
            $html .= "<tr>";
            $html .= "<td align=\"left\" $bold>$rang.</td>";
            $html .= "<td align=\"left\" $bold>{$row['Name']} {$row['Vorname']}</td>";
            $html .= "<td align=\"center\" $bold>{$row['EndstichTotal']}</td>";
            $html .= "<td align=\"center\" $bold>" . ($row['MaxSchwini'] ?? '–') . "</td>";
            $html .= "<td align=\"center\" $bold>{$row['KunstTotal']}</td>";
            $html .= "<td align=\"center\" $bold>{$row['GlueckTotal']}</td>";
            
            if ($includeZabig) {
                $html .= "<td align=\"center\" $bold>{$row['ZabigTotal']}</td>";
            }
            
            $html .= "<td align=\"center\" $bold>{$row['GesamtTotal']}</td>";
            $html .= "<td align=\"right\" $bold>$kk</td>";
            $html .= "</tr>";
            
            if ($rang == 3) {
                $html .= '<tr>';
                $colCount = $includeZabig ? 9 : 8;
                for ($i = 0; $i < $colCount; $i++) {
                    $html .= '<td></td>';
                }
                $html .= '</tr>';
            }
            
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
        $html = $this->createHTMLHeader('Endschiessen Gesamtrangliste');
        $html .= '<h2>Endschiessen Gesamtrangliste ' . $this->selectedYear . '</h2>';
        
        // Kategorie A
        $qA = $this->getGesamtSQL('Kat. A', true);
        $dataA = $this->executePreparedQuery($qA['sql'], $qA['types'], ...$qA['params']);
        if (!empty($dataA)) {
            $html .= $this->createKategorieTable('Kat. A', $dataA, true);
        }

        $html .= "<div class=\"row\">&nbsp;</div>";

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
        $html = $this->createHTMLHeader('Endschiessen Zwischenrangliste ' . $this->selectedYear);
        $html .= '<h2>Endschiessen ' . $this->selectedYear . ' Zwischenrangliste</h2>';
        
        // Kategorie A
        $qA = $this->getGesamtSQL('Kat. A', false);
        $dataA = $this->executePreparedQuery($qA['sql'], $qA['types'], ...$qA['params']);
        if (!empty($dataA)) {
            $html .= $this->createKategorieTable('Kat. A', $dataA, false);
        }

        $html .= "<div class=\"row\">&nbsp;</div>";

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