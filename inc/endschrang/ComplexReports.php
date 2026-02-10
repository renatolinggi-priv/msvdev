<?php
// ComplexReports.php - Gesamt- und Zwischenrangliste

require_once 'PDFGenerator.php';

/**
 * Basis-Klasse für Ranglisten mit mehreren Kategorien
 */
abstract class MultiKategorieReport extends PDFGenerator {
    
    protected function getGesamtSQL($kat, $includeZabig = true) {
        $zabigCalculation = $includeZabig ? $this->getZabigCalculation() : '0';

        $sql = "SELECT
            m.Name,
            m.Vorname,
            m.Geburtsdatum,
            {$zabigCalculation} AS ZabigTotal,
            COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3)/10,1)) AS GlueckTotal,
            COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 +
                        e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS EndstichTotal,
            COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) AS KunstTotal,
            GREATEST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
                    s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) as MaxSchwini,
            (COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 +
                         e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) +
             COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3)/10,1)) +
             {$zabigCalculation} +
             COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) +
             GREATEST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
                     s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6)
            ) AS GesamtTotal
        FROM mitglieder m
        LEFT JOIN endstich e ON m.ID = e.MitgliedID AND e.Jahr = ?
        LEFT JOIN schwini s ON m.ID = s.MitgliedID AND s.Jahr = ?
        LEFT JOIN kunst k ON m.ID = k.MitgliedID AND k.Jahr = ?
        LEFT JOIN glueck g ON m.ID = g.MitgliedID AND g.Jahr = ?
        LEFT JOIN zabig z ON m.ID = z.MitgliedID AND z.Jahr = ?
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        WHERE w.Kategorie = ? AND e.Schuss1 != 0
        GROUP BY m.ID, m.Vorname, m.Name
        ORDER BY GesamtTotal DESC, EndstichTotal DESC, m.Geburtsdatum ASC";

        return ['sql' => $sql, 'types' => 'iiiiis', 'params' => [$this->selectedYear, $this->selectedYear, $this->selectedYear, $this->selectedYear, $this->selectedYear, $kat]];
    }
    
    protected function getZabigCalculation() {
        $calculation = "(";
        for ($i = 1; $i <= 6; $i++) {
            $calculation .= "CASE
                WHEN z.ZSchuss$i >= 91 THEN 10
                WHEN z.ZSchuss$i >= 81 THEN 9
                WHEN z.ZSchuss$i >= 71 THEN 8
                WHEN z.ZSchuss$i >= 61 THEN 7
                WHEN z.ZSchuss$i >= 51 THEN 6
                WHEN z.ZSchuss$i >= 41 THEN 5
                WHEN z.ZSchuss$i >= 31 THEN 4
                WHEN z.ZSchuss$i >= 21 THEN 3
                WHEN z.ZSchuss$i >= 11 THEN 2
                WHEN z.ZSchuss$i >= 1 THEN 1
                ELSE 0
            END";
            if ($i < 6) $calculation .= " + ";
        }
        $calculation .= ")";
        return $calculation;
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
        foreach ($data as $row) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';
            $kk = '';
            
            if ($rang == 1) {
                $kk = '3KK + WP';
            } elseif ($rang <= 3) {
                $kk = 'KK';
            }
            
            $html .= "<tr>";
            $html .= "<td align=\"left\" $bold>$rang.</td>";
            $html .= "<td align=\"left\" $bold>{$row['Name']} {$row['Vorname']}</td>";
            $html .= "<td align=\"center\" $bold>{$row['EndstichTotal']}</td>";
            $html .= "<td align=\"center\" $bold>{$row['MaxSchwini']}</td>";
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