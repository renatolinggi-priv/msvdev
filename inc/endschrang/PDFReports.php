<?php
// PDFReports.php - Spezifische Report-Klassen

require_once 'PDFGenerator.php';

/**
 * Endstich Rangliste
 */
class EndstichReport extends PDFGenerator {
    public function generate() {
        $sql = "
            SELECT
                m.ID,
                m.Name,
                m.Vorname,
                e.Schuss1, e.Schuss2, e.Schuss3, e.Schuss4, e.Schuss5,
                e.Schuss6, e.Schuss7, e.Schuss8, e.Schuss9, e.Schuss10,
                e.Tiefschuss,
                w.Kranz_Endstich,
                (e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + 
                 e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10) AS Endstich_Summe
            FROM mitglieder m
            LEFT JOIN endstich e ON m.ID = e.MitgliedID
            LEFT JOIN Waffen w ON w.ID = m.WaffenID
            WHERE e.Schuss1 != 0 AND e.Jahr = {$this->selectedYear}
            ORDER BY Endstich_Summe DESC, e.Tiefschuss DESC
        ";
        
        $data = $this->executeQuery($sql);
        
        // Daten für Tabelle vorbereiten
        $tableData = [];
        $rang = 1;
        foreach ($data as $row) {
            $shots = $this->getSortedShots([
                $row['Schuss1'], $row['Schuss2'], $row['Schuss3'], $row['Schuss4'], $row['Schuss5'],
                $row['Schuss6'], $row['Schuss7'], $row['Schuss8'], $row['Schuss9'], $row['Schuss10']
            ]);
            
            $kk = '';
            if ($rang == 1) {
                $kk = 'KK';
            } elseif ($row['Endstich_Summe'] >= $row['Kranz_Endstich']) {
                $kk = 'KK';
            }
            
            $tableRow = [
                'name' => $row['Name'] . ' ' . $row['Vorname'],
                'total' => $row['Endstich_Summe'],
                'ts' => $row['Tiefschuss'],
                'kk' => $kk
            ];
            
            // Schüsse hinzufügen
            for ($i = 0; $i < 10; $i++) {
                $tableRow['shot' . ($i + 1)] = isset($shots[$i]) ? $shots[$i] : '';
            }
            
            $tableData[] = $tableRow;
            $rang++;
        }
        
        // HTML erstellen
        $html = $this->createHTMLHeader('Endstich Rangliste ' . $this->selectedYear);
        $html .= '<h2>Endstich Rangliste ' . $this->selectedYear . '</h2>';
        
        // Spalten definieren
        $columns = [
            ['field' => 'rang', 'label' => 'Rang', 'align' => 'left'],
            ['field' => 'name', 'label' => 'Name', 'align' => 'left'],
            ['field' => 'shots', 'label' => 'Passe', 'align' => 'left', 'colspan' => 10],
            ['field' => 'total', 'label' => 'Total', 'align' => 'center'],
            ['field' => 'ts', 'label' => 'TS', 'align' => 'center'],
            ['field' => 'kk', 'label' => '', 'align' => 'right']
        ];
        
        // Manuelle Tabelle für komplexe Struktur
        $html .= $this->createEndstichTable($tableData);
        
        $html .= $this->createHTMLFooter();
        
        $pdfPath = $this->generatePDF($html, 'RanglisteEndstich');
        $this->outputDownloadLink($pdfPath);
    }
    
    private function createEndstichTable($data) {
        if (empty($data)) {
            return '<p>Keine Ergebnisse gefunden.</p>';
        }
        
        $html = '<table class="table">';
        $html .= '<thead><tr>
                    <th align="left">Rang</th>
                    <th align="left">Name</th>
                    <th colspan="10" align="left">Passe</th>
                    <th>Total</th>
                    <th>TS</th>
                    <th></th>
                  </tr></thead><tbody>';
        
        $rang = 1;
        foreach ($data as $row) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';
            
            $html .= "<tr>";
            $html .= "<td align=\"left\" $bold>$rang.</td>";
            $html .= "<td align=\"left\" $bold>{$row['name']}</td>";
            
            // 10 Schüsse
            for ($i = 1; $i <= 10; $i++) {
                $value = isset($row['shot' . $i]) ? $row['shot' . $i] : '';
                $html .= "<td align=\"right\" $bold>$value</td>";
            }
            
            $html .= "<td align=\"center\" $bold>{$row['total']}</td>";
            $html .= "<td align=\"center\" $bold>{$row['ts']}</td>";
            $html .= "<td align=\"right\" $bold>{$row['kk']}</td>";
            $html .= "</tr>";
            
            if ($rang == 3) {
                $html .= '<tr>';
                for ($i = 0; $i < 14; $i++) {
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
 * Schwini Rangliste
 */
class SchwiniReport extends PDFGenerator {
    public function generate() {
        $sql = "SELECT
            m.ID,
            m.Name,
            m.Vorname,
            m.Geburtsdatum,
            s.P1Schuss1, s.P1Schuss2, s.P1Schuss3, s.P1Schuss4, s.P1Schuss5, s.P1Schuss6,
            s.P2Schuss1, s.P2Schuss2, s.P2Schuss3, s.P2Schuss4, s.P2Schuss5, s.P2Schuss6,
            COALESCE((s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0) AS Schwini1_Summe,
            COALESCE((s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0) AS Schwini2_Summe,
            GREATEST(
                COALESCE((s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0),
                COALESCE((s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0)
            ) AS Hoechste_Summe,
            LEAST(
                COALESCE((s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0),
                COALESCE((s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0)
            ) AS Tiefste_Summe
        FROM mitglieder m
        LEFT JOIN schwini s ON m.ID = s.MitgliedID AND s.Jahr = {$this->selectedYear}
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        GROUP BY m.ID
        HAVING Schwini1_Summe != 0

        UNION ALL

        SELECT
            j.id AS ID,
            j.Name,
            j.Vorname,
            j.Geburtsdatum,
            sj.P1Schuss1, sj.P1Schuss2, sj.P1Schuss3, sj.P1Schuss4, sj.P1Schuss5, sj.P1Schuss6,
            sj.P2Schuss1, sj.P2Schuss2, sj.P2Schuss3, sj.P2Schuss4, sj.P2Schuss5, sj.P2Schuss6,
            COALESCE((sj.P1Schuss1 + sj.P1Schuss2 + sj.P1Schuss3 + sj.P1Schuss4 + sj.P1Schuss5 + sj.P1Schuss6), 0) AS Schwini1_Summe,
            COALESCE((sj.P2Schuss1 + sj.P2Schuss2 + sj.P2Schuss3 + sj.P2Schuss4 + sj.P2Schuss5 + sj.P2Schuss6), 0) AS Schwini2_Summe,
            GREATEST(
                COALESCE((sj.P1Schuss1 + sj.P1Schuss2 + sj.P1Schuss3 + sj.P1Schuss4 + sj.P1Schuss5 + sj.P1Schuss6), 0),
                COALESCE((sj.P2Schuss1 + sj.P2Schuss2 + sj.P2Schuss3 + sj.P2Schuss4 + sj.P2Schuss5 + sj.P2Schuss6), 0)
            ) AS Hoechste_Summe,
            LEAST(
                COALESCE((sj.P1Schuss1 + sj.P1Schuss2 + sj.P1Schuss3 + sj.P1Schuss4 + sj.P1Schuss5 + sj.P1Schuss6), 0),
                COALESCE((sj.P2Schuss1 + sj.P2Schuss2 + sj.P2Schuss3 + sj.P2Schuss4 + sj.P2Schuss5 + sj.P2Schuss6), 0)
            ) AS Tiefste_Summe
        FROM jungschuetzen j
        LEFT JOIN schwini_jung sj ON j.id = sj.JungschuetzeID AND sj.Jahr = {$this->selectedYear}
        GROUP BY j.id
        HAVING Schwini1_Summe != 0
        
        ORDER BY Hoechste_Summe DESC, Tiefste_Summe DESC, Geburtsdatum ASC";
        
        $data = $this->executeQuery($sql);
        
        $html = $this->createHTMLHeader('Schwini Rangliste ' . $this->selectedYear);
        $html .= '<h2>Schwini Rangliste ' . $this->selectedYear . '</h2>';
        $html .= $this->createSchwiniTable($data);
        $html .= $this->createHTMLFooter();
        
        $pdfPath = $this->generatePDF($html, 'RanglisteSchwini');
        $this->outputDownloadLink($pdfPath);
    }
    
    private function createSchwiniTable($data) {
        if (empty($data)) {
            return '<p>Keine Ergebnisse gefunden.</p>';
        }
        
        $html = '<table class="table">';
        $html .= '<thead><tr>
                    <th align="left">Rang</th>
                    <th align="left">Name</th>
                    <th colspan="6" align="left">Passe</th>
                    <th>Total</th>
                    <th></th>
                  </tr></thead><tbody>';
        
        $rang = 1;
        foreach ($data as $row) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';
            
            // Bestimme welche Passe höher ist
            if ($row['Schwini1_Summe'] >= $row['Schwini2_Summe']) {
                $shots = [$row['P1Schuss1'], $row['P1Schuss2'], $row['P1Schuss3'], 
                         $row['P1Schuss4'], $row['P1Schuss5'], $row['P1Schuss6']];
            } else {
                $shots = [$row['P2Schuss1'], $row['P2Schuss2'], $row['P2Schuss3'], 
                         $row['P2Schuss4'], $row['P2Schuss5'], $row['P2Schuss6']];
            }
            rsort($shots);
            
            $html .= "<tr>";
            $html .= "<td align=\"left\" $bold>$rang.</td>";
            $html .= "<td align=\"left\" $bold>{$row['Name']} {$row['Vorname']}</td>";
            
            foreach ($shots as $shot) {
                $html .= "<td align=\"right\" $bold>$shot</td>";
            }
            
            $html .= "<td align=\"center\" $bold>{$row['Hoechste_Summe']}</td>";
            $html .= "<td align=\"center\" $bold>({$row['Tiefste_Summe']})</td>";
            $html .= "</tr>";
            
            if ($rang == 3) {
                $html .= '<tr>';
                for ($i = 0; $i < 10; $i++) {
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
 * Kunst Rangliste
 */
class KunstReport extends PDFGenerator {
    public function generate() {
        $sql = "SELECT
            m.ID,
            m.Name,
            m.Vorname,
            m.Geburtsdatum,
            k.KSchuss1, k.KSchuss2, k.KSchuss3, k.KSchuss4, k.KSchuss5,
            w.Kranz_Kunst,
            COALESCE(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5, 0) AS Kunst_Summe,
            GREATEST(
                COALESCE(k.KSchuss1, 0),
                COALESCE(k.KSchuss2, 0),
                COALESCE(k.KSchuss3, 0),
                COALESCE(k.KSchuss4, 0),
                COALESCE(k.KSchuss5, 0)
            ) AS TS
        FROM mitglieder m
        LEFT JOIN kunst k ON m.ID = k.MitgliedID
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        WHERE k.KSchuss1 IS NOT NULL AND k.KSchuss1 != 0 AND k.Jahr = {$this->selectedYear}
        GROUP BY m.ID
        ORDER BY Kunst_Summe DESC, TS DESC, m.Geburtsdatum ASC";
        
        $data = $this->executeQuery($sql);
        
        $html = $this->createHTMLHeader('Kunst Rangliste ' . $this->selectedYear);
        $html .= '<h2>Kunst Rangliste ' . $this->selectedYear . '</h2>';
        $html .= $this->createKunstTable($data);
        $html .= $this->createHTMLFooter();
        
        $pdfPath = $this->generatePDF($html, 'RanglisteKunst');
        $this->outputDownloadLink($pdfPath);
    }
    
    private function createKunstTable($data) {
        if (empty($data)) {
            return '<p>Keine Ergebnisse gefunden.</p>';
        }
        
        $html = '<table class="table">';
        $html .= '<thead><tr>
                    <th align="left">Rang</th>
                    <th align="left">Name</th>
                    <th colspan="5" align="left">Passe</th>
                    <th>Total</th>
                    <th></th>
                  </tr></thead><tbody>';
        
        $rang = 1;
        foreach ($data as $row) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';
            
            $kk = '';
            if ($rang == 1) {
                $kk = 'KK + WP';
            } elseif ($row['Kunst_Summe'] >= $row['Kranz_Kunst']) {
                $kk = 'KK';
            }
            
            $shots = $this->getSortedShots([
                $row['KSchuss1'], $row['KSchuss2'], $row['KSchuss3'], 
                $row['KSchuss4'], $row['KSchuss5']
            ]);
            
            $html .= "<tr>";
            $html .= "<td align=\"left\" $bold>$rang.</td>";
            $html .= "<td align=\"left\" $bold>{$row['Name']} {$row['Vorname']}</td>";
            
            foreach ($shots as $shot) {
                $html .= "<td align=\"right\" $bold>$shot</td>";
            }
            // Leere Zellen auffüllen
            for ($i = count($shots); $i < 5; $i++) {
                $html .= "<td align=\"right\" $bold></td>";
            }
            
            $html .= "<td align=\"center\" $bold>{$row['Kunst_Summe']}</td>";
            $html .= "<td align=\"right\" $bold>$kk</td>";
            $html .= "</tr>";
            
            if ($rang == 3) {
                $html .= '<tr>';
                for ($i = 0; $i < 8; $i++) {
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
 * Glück Rangliste
 */
class GlueckReport extends PDFGenerator {
    public function generate() {
        $sql = "SELECT
            m.ID,
            m.Name,
            m.Vorname,
            m.Geburtsdatum,
            g.GSchuss1, g.GSchuss2, g.GSchuss3,
            GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3) AS MaxGlueck,
            LEAST(
                GREATEST(g.GSchuss1, g.GSchuss2),
                GREATEST(g.GSchuss1, g.GSchuss3),
                GREATEST(g.GSchuss2, g.GSchuss3)
            ) AS ZweitHoechster,
            LEAST(g.GSchuss1, g.GSchuss2, g.GSchuss3) AS DrittHoechster
        FROM mitglieder m
        LEFT JOIN glueck g ON m.ID = g.MitgliedID
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        WHERE g.GSchuss1 != 0 AND g.Jahr = {$this->selectedYear}
        GROUP BY m.ID
        ORDER BY MaxGlueck DESC, ZweitHoechster DESC, DrittHoechster DESC, m.Geburtsdatum ASC";
        
        $data = $this->executeQuery($sql);
        
        $html = $this->createHTMLHeader('Glückstich Rangliste ' . $this->selectedYear);
        $html .= '<h2>Glückstich Rangliste ' . $this->selectedYear . '</h2>';
        
        // Tabelle erstellen
        if (!empty($data)) {
            $html .= '<table class="table">';
            $html .= '<thead><tr>
                        <th align="left">Rang</th>
                        <th align="left">Name</th>
                        <th align="left">Resultat</th>
                        <th align="left">Schüsse</th>
                        <th></th>
                      </tr></thead><tbody>';
            
            $rang = 1;
            foreach ($data as $row) {
                $bold = ($rang <= 3) ? 'class="bold"' : '';
                $kk = ($rang == 1) ? 'WP' : '';
                
                $html .= "<tr>";
                $html .= "<td align=\"left\" $bold>$rang.</td>";
                $html .= "<td align=\"left\" $bold>{$row['Name']} {$row['Vorname']}</td>";
                $html .= "<td align=\"center\" $bold>{$row['MaxGlueck']}</td>";
                $html .= "<td align=\"left\" $bold>({$row['GSchuss1']}, {$row['GSchuss2']}, {$row['GSchuss3']})</td>";
                $html .= "<td align=\"left\" $bold>$kk</td>";
                $html .= "</tr>";
                
                $rang++;
            }
            
            $html .= '</tbody></table>';
        } else {
            $html .= '<p>Keine Ergebnisse gefunden.</p>';
        }
        
        $html .= $this->createHTMLFooter();
        
        $pdfPath = $this->generatePDF($html, 'RanglisteGlueck');
        $this->outputDownloadLink($pdfPath);
    }
}

/**
 * Zabig Rangliste
 */
class ZabigReport extends PDFGenerator {
    public function generate() {
        $sql = "SELECT
            m.ID,
            m.Name,
            m.Vorname,
            m.Geburtsdatum,
            z.ZSchuss1, z.ZSchuss2, z.ZSchuss3, z.ZSchuss4, z.ZSchuss5, z.ZSchuss6,
            GREATEST(z.ZSchuss1, z.ZSchuss2, z.ZSchuss3, z.ZSchuss4, z.ZSchuss5, z.ZSchuss6) AS TS
        FROM mitglieder m
        LEFT JOIN zabig z ON m.ID = z.MitgliedID
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        WHERE z.ZSchuss1 != 0 AND z.Jahr = {$this->selectedYear}
        GROUP BY m.ID
        ORDER BY m.Geburtsdatum ASC";
        
        $data = $this->executeQuery($sql);
        
        // Zabig-Punkte berechnen und sortieren
        foreach ($data as &$row) {
            $total = 0;
            for ($i = 1; $i <= 6; $i++) {
                $total += $this->getZabigPoints($row['ZSchuss' . $i]);
            }
            $row['Total'] = $total;
        }
        
        // Nach Total und TS sortieren
        usort($data, function($a, $b) {
            if ($a['Total'] == $b['Total']) {
                return $b['TS'] - $a['TS'];
            }
            return $b['Total'] - $a['Total'];
        });
        
        $html = $this->createHTMLHeader('Zabigstich Rangliste ' . $this->selectedYear);
        $html .= '<h2>Zabigstich Rangliste ' . $this->selectedYear . '</h2>';
        $html .= $this->createZabigTable($data);
        $html .= $this->createHTMLFooter();
        
        $pdfPath = $this->generatePDF($html, 'RanglisteZabig');
        $this->outputDownloadLink($pdfPath);
    }
    
    private function createZabigTable($data) {
        if (empty($data)) {
            return '<p>Keine Ergebnisse gefunden.</p>';
        }
        
        $html = '<table class="table">';
        $html .= '<thead><tr>
                    <th align="left">Rang</th>
                    <th align="left">Name</th>
                    <th colspan="6" align="left">Passe</th>
                    <th>Total</th>
                    <th></th>
                    <th></th>
                  </tr></thead><tbody>';
        
        $rang = 1;
        foreach ($data as $row) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';
            
            $shots = $this->getSortedShots([
                $row['ZSchuss1'], $row['ZSchuss2'], $row['ZSchuss3'],
                $row['ZSchuss4'], $row['ZSchuss5'], $row['ZSchuss6']
            ]);
            
            $html .= "<tr>";
            $html .= "<td align=\"left\" $bold>$rang.</td>";
            $html .= "<td align=\"left\" $bold>{$row['Name']} {$row['Vorname']}</td>";
            
            foreach ($shots as $shot) {
                $html .= "<td align=\"right\" $bold>$shot</td>";
            }
            
            $html .= "<td align=\"center\" $bold>{$row['Total']}</td>";
            $html .= "<td align=\"center\" $bold>({$row['TS']})</td>";
            $html .= "</tr>";
            
            if ($rang == 3) {
                $html .= '<tr>';
                for ($i = 0; $i < 10; $i++) {
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
 * Differenzler Rangliste
 */
class DifferenzlerReport extends PDFGenerator {
    public function generate() {
        $sql = "SELECT
            m.ID,
            m.Name,
            m.Vorname,
            m.Geburtsdatum,
            z.ZSchuss1, z.ZSchuss2, z.ZSchuss3, z.ZSchuss4, z.ZSchuss5, z.ZSchuss6,
            z.Ansage,
            COALESCE(SUM(z.ZSchuss1 + z.ZSchuss2 + z.ZSchuss3 + z.ZSchuss4 + z.ZSchuss5 + z.ZSchuss6), 0) AS DiffTotal,
            ABS(COALESCE(SUM(z.ZSchuss1 + z.ZSchuss2 + z.ZSchuss3 + z.ZSchuss4 + z.ZSchuss5 + z.ZSchuss6 - z.Ansage), 0)) AS NormDiff
        FROM mitglieder m
        LEFT JOIN zabig z ON m.ID = z.MitgliedID
        LEFT JOIN Waffen w ON w.ID = m.WaffenID
        WHERE z.ZSchuss1 != 0 AND z.Jahr = {$this->selectedYear}
        GROUP BY m.ID
        ORDER BY NormDiff ASC, m.Geburtsdatum ASC";
        
        $data = $this->executeQuery($sql);
        
        $html = $this->createHTMLHeader('Differenzler ' . $this->selectedYear);
        $html .= '<h2>Differenzler ' . $this->selectedYear . '</h2>';
        $html .= $this->createDifferenzlerTable($data);
        $html .= $this->createHTMLFooter();
        
        $pdfPath = $this->generatePDF($html, 'RanglisteDifferenzler');
        $this->outputDownloadLink($pdfPath);
    }
    
    private function createDifferenzlerTable($data) {
        if (empty($data)) {
            return '<p>Keine Ergebnisse gefunden.</p>';
        }
        
        $html = '<table class="table">';
        $html .= '<thead><tr>
                    <th align="left">Rang</th>
                    <th align="left">Name</th>
                    <th colspan="6" align="left">Passe</th>
                    <th>Total</th>
                    <th>Differenz</th>
                    <th>Ansage</th>
                  </tr></thead><tbody>';
        
        $rang = 1;
        foreach ($data as $row) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';
            
            $shots = $this->getSortedShots([
                $row['ZSchuss1'], $row['ZSchuss2'], $row['ZSchuss3'],
                $row['ZSchuss4'], $row['ZSchuss5'], $row['ZSchuss6']
            ]);
            
            $html .= "<tr>";
            $html .= "<td align=\"left\" $bold>$rang.</td>";
            $html .= "<td align=\"left\" $bold>{$row['Name']} {$row['Vorname']}</td>";
            
            foreach ($shots as $shot) {
                $html .= "<td align=\"right\" $bold>$shot</td>";
            }
            
            $html .= "<td align=\"center\" $bold>{$row['DiffTotal']}</td>";
            $html .= "<td align=\"center\" $bold>{$row['NormDiff']}</td>";
            $html .= "<td align=\"center\" $bold>({$row['Ansage']})</td>";
            $html .= "</tr>";
            
            if ($rang == 3) {
                $html .= '<tr>';
                for ($i = 0; $i < 11; $i++) {
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
 * Absendenanmeldung Report
 */
class AnmeldungReport extends PDFGenerator {
    public function generate() {
        $html = $this->createHTMLHeader('Absendenanmeldung', '', 12);
        $html .= '<div class="container">';
        
        // Teil 1: Mitglieder mit Absendenanmeldung
        $html .= '<h5>Mitglieder mit Absendenanmeldung ' . $this->selectedYear . '</h5>';
        $html .= $this->createAnmeldungTable();
        
        // Seitenumbruch
        $html .= '</div><div style="page-break-before: always;"></div><div class="container">';
        
        // Teil 2: Anzahl Schwinistiche und Differenzler
        $html .= '<h5>Anzahl Schwinistiche und Differenzler</h5>';
        $html .= $this->createSchwiniDifferenzlerTable();
        
        $html .= $this->createHTMLFooter();
        
        $pdfPath = $this->generatePDF($html, 'Mitglieder_Absendenanmeldung');
        $this->outputDownloadLink($pdfPath);
    }
    
    private function createAnmeldungTable() {
        $sql = "
            SELECT 
                m.Name, 
                m.Vorname, 
                e.AbsendenAnmeldung AS Anmeldungen
            FROM mitglieder m
            LEFT JOIN endstich e ON m.ID = e.MitgliedID
            WHERE e.AbsendenAnmeldung IS NOT NULL AND e.Jahr = {$this->selectedYear}
            GROUP BY m.ID

            UNION ALL

            SELECT 
                js.Name,
                js.Vorname,
                ej.AbsendenAnmeldung AS Anmeldungen
            FROM jungschuetzen js
            LEFT JOIN endstich_jung ej ON js.ID = ej.JungschuetzeID
            WHERE ej.AbsendenAnmeldung IS NOT NULL AND ej.Jahr = {$this->selectedYear}
            GROUP BY js.ID

            ORDER BY Name ASC, Vorname ASC";
        
        $data = $this->executeQuery($sql);
        
        if (!empty($data)) {
            $html = '<table class="table table-bordered">';
            $html .= '<thead><tr>
                        <th align="left">Name</th>
                        <th align="left">Vorname</th>
                        <th align="center">Anzahl</th>
                      </tr></thead><tbody>';
            
            $total = 0;
            foreach ($data as $row) {
                $html .= "<tr>";
                $html .= "<td align='left'>{$row['Name']}</td>";
                $html .= "<td align='left'>{$row['Vorname']}</td>";
                $html .= "<td align='center'>{$row['Anmeldungen']}</td>";
                $html .= "</tr>";
                $total += $row['Anmeldungen'];
            }
            
            $html .= "<tr>";
            $html .= "<td align='left' colspan='2'><strong>Total</strong></td>";
            $html .= "<td align='center'><strong>$total</strong></td>";
            $html .= "</tr>";
            
            $html .= '</tbody></table>';
        } else {
            $html = '<p>Keine Ergebnisse gefunden.</p>';
        }
        
        return $html;
    }
    
    private function createSchwiniDifferenzlerTable() {
        // Schwini Mitglieder zählen
        $sql1 = "
            SELECT
                SUM(CASE WHEN (COALESCE(P1Schuss1, 0) + COALESCE(P1Schuss2, 0) + COALESCE(P1Schuss3, 0) + 
                              COALESCE(P1Schuss4, 0) + COALESCE(P1Schuss5, 0) + COALESCE(P1Schuss6, 0)) > 0 
                    THEN 1 ELSE 0 END) AS Passe1_Count,
                SUM(CASE WHEN (COALESCE(P2Schuss1, 0) + COALESCE(P2Schuss2, 0) + COALESCE(P2Schuss3, 0) + 
                              COALESCE(P2Schuss4, 0) + COALESCE(P2Schuss5, 0) + COALESCE(P2Schuss6, 0)) > 0 
                    THEN 1 ELSE 0 END) AS Passe2_Count
            FROM schwini
            WHERE Jahr = {$this->selectedYear}";
        
        // Schwini Jungschützen zählen
        $sql2 = "
            SELECT
                SUM(CASE WHEN (COALESCE(P1Schuss1, 0) + COALESCE(P1Schuss2, 0) + COALESCE(P1Schuss3, 0) + 
                              COALESCE(P1Schuss4, 0) + COALESCE(P1Schuss5, 0) + COALESCE(P1Schuss6, 0)) > 0 
                    THEN 1 ELSE 0 END) AS Passe1_Count,
                SUM(CASE WHEN (COALESCE(P2Schuss1, 0) + COALESCE(P2Schuss2, 0) + COALESCE(P2Schuss3, 0) + 
                              COALESCE(P2Schuss4, 0) + COALESCE(P2Schuss5, 0) + COALESCE(P2Schuss6, 0)) > 0 
                    THEN 1 ELSE 0 END) AS Passe2_Count
            FROM schwini_jung
            WHERE Jahr = {$this->selectedYear}";
        
        // Differenzler zählen
        $sql_diff = "SELECT COUNT(Ansage) as Anzahl FROM zabig WHERE Ansage IS NOT NULL AND Jahr = {$this->selectedYear}";
        
        // Daten abrufen
        $result1 = $this->conn->query($sql1);
        $row1 = $result1->fetch_assoc();
        $passe1_reg = $row1['Passe1_Count'] ?: 0;
        $passe2_reg = $row1['Passe2_Count'] ?: 0;
        
        $result2 = $this->conn->query($sql2);
        $row2 = $result2->fetch_assoc();
        $passe1_jung = $row2['Passe1_Count'] ?: 0;
        $passe2_jung = $row2['Passe2_Count'] ?: 0;
        
        $result_diff = $this->conn->query($sql_diff);
        $row_diff = $result_diff->fetch_assoc();
        $anzahl_diff = $row_diff['Anzahl'] ?: 0;
        
        // Beträge berechnen
        $amount_passe1_reg = $passe1_reg * 20;
        $amount_passe2_reg = $passe2_reg * 14;
        $total_jung = $passe1_jung + $passe2_jung;
        $amount_jung = $total_jung * 16;
        $amount_diff = $anzahl_diff * 5;
        
        $total_schwini = $amount_passe1_reg + $amount_passe2_reg + $amount_jung;
        
        // Tabelle erstellen
        $html = '<table class="table table-bordered">';
        $html .= '<thead><tr>
                    <th align="left">Stich</th>
                    <th align="center">Anzahl</th>
                    <th align="right">Betrag (Fr.)</th>
                  </tr></thead><tbody>';
        
        $html .= "<tr><td align='left'>Schwini (1. Passe)</td><td align='center'>$passe1_reg</td><td align='right'>$amount_passe1_reg</td></tr>";
        $html .= "<tr><td align='left'>Schwini (2. Passe)</td><td align='center'>$passe2_reg</td><td align='right'>$amount_passe2_reg</td></tr>";
        $html .= "<tr><td align='left'>Schwini Jungschützen</td><td align='center'>$total_jung</td><td align='right'>$amount_jung</td></tr>";
        $html .= "<tr><td align='left' colspan='2'><strong>Total Betrag Schwini (Fr.)</strong></td><td align='right'><strong>$total_schwini</strong></td></tr>";
        $html .= "<tr><td align='left'>Differenzler</td><td align='center'>$anzahl_diff</td><td align='right'>$amount_diff</td></tr>";
        $html .= "<tr><td align='left' colspan='2'><strong>Total Betrag Differenzler (Fr.)</strong></td><td align='right'><strong>$amount_diff</strong></td></tr>";
        
        $html .= '</tbody></table>';
        
        return $html;
    }
}
/**
 * Partner Rangliste - Endstich + Partner Schwini
 */
class PartnerRankingReport extends PDFGenerator {
    public function generate() {
        $sql = "
            SELECT
                m.ID,
                m.Name,
                m.Vorname,
                ep.PartnerName,
                ep.EndstichSchuss1, ep.EndstichSchuss2, ep.EndstichSchuss3, ep.EndstichSchuss4, ep.EndstichSchuss5,
                ep.EndstichSchuss6, ep.EndstichSchuss7, ep.EndstichSchuss8, ep.EndstichSchuss9, ep.EndstichSchuss10,
                ep.PartnerSchwiniSchuss1, ep.PartnerSchwiniSchuss2, ep.PartnerSchwiniSchuss3,
                ep.PartnerSchwiniSchuss4, ep.PartnerSchwiniSchuss5, ep.PartnerSchwiniSchuss6,
                (COALESCE(ep.EndstichSchuss1, 0) + COALESCE(ep.EndstichSchuss2, 0) + COALESCE(ep.EndstichSchuss3, 0) +
                 COALESCE(ep.EndstichSchuss4, 0) + COALESCE(ep.EndstichSchuss5, 0) + COALESCE(ep.EndstichSchuss6, 0) +
                 COALESCE(ep.EndstichSchuss7, 0) + COALESCE(ep.EndstichSchuss8, 0) + COALESCE(ep.EndstichSchuss9, 0) +
                 COALESCE(ep.EndstichSchuss10, 0)) AS Endstich_Summe,
                (COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) +
                 COALESCE(ep.PartnerSchwiniSchuss3, 0) + COALESCE(ep.PartnerSchwiniSchuss4, 0) +
                 COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0)) AS PartnerSchwini_Summe,
                ((COALESCE(ep.EndstichSchuss1, 0) + COALESCE(ep.EndstichSchuss2, 0) + COALESCE(ep.EndstichSchuss3, 0) +
                  COALESCE(ep.EndstichSchuss4, 0) + COALESCE(ep.EndstichSchuss5, 0) + COALESCE(ep.EndstichSchuss6, 0) +
                  COALESCE(ep.EndstichSchuss7, 0) + COALESCE(ep.EndstichSchuss8, 0) + COALESCE(ep.EndstichSchuss9, 0) +
                  COALESCE(ep.EndstichSchuss10, 0)) +
                 (COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) +
                  COALESCE(ep.PartnerSchwiniSchuss3, 0) + COALESCE(ep.PartnerSchwiniSchuss4, 0) +
                  COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0))) AS Total_Summe
            FROM mitglieder m
            INNER JOIN endresultate_partner ep ON m.ID = ep.MitgliedID
            WHERE ep.Jahr = {$this->selectedYear}
              AND ((COALESCE(ep.EndstichSchuss1, 0) + COALESCE(ep.EndstichSchuss2, 0) + COALESCE(ep.EndstichSchuss3, 0) +
                    COALESCE(ep.EndstichSchuss4, 0) + COALESCE(ep.EndstichSchuss5, 0) + COALESCE(ep.EndstichSchuss6, 0) +
                    COALESCE(ep.EndstichSchuss7, 0) + COALESCE(ep.EndstichSchuss8, 0) + COALESCE(ep.EndstichSchuss9, 0) +
                    COALESCE(ep.EndstichSchuss10, 0)) > 0
                   OR (COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) +
                       COALESCE(ep.PartnerSchwiniSchuss3, 0) + COALESCE(ep.PartnerSchwiniSchuss4, 0) +
                       COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0)) > 0)
            ORDER BY Total_Summe DESC, Endstich_Summe DESC, m.Name ASC, m.Vorname ASC
        ";
        
        $data = $this->executeQuery($sql);
        
        $html = $this->createHTMLHeader('Partner Rangliste ' . $this->selectedYear);
        $html .= '<h2>Partner Rangliste ' . $this->selectedYear . '</h2>';
        $html .= '<p><strong>Endstichresultat (10 Schuss) + Partner Schwiniresultat (6 Schuss)</strong></p>';
        $html .= '<p>Bei Punktgleichheit entscheidet das bessere Endstichresultat</p>';
        $html .= $this->createPartnerRankingTable($data);
        $html .= $this->createHTMLFooter();
        
        $pdfPath = $this->generatePDF($html, 'RanglistePartner');
        $this->outputDownloadLink($pdfPath);
    }
    
    private function createPartnerRankingTable($data) {
        if (empty($data)) {
            return '<p>Keine Ergebnisse gefunden.</p>';
        }
        
        $html = '<table class="table">';
        $html .= '<thead><tr>
                    <th align="left">Rang</th>
                    <th align="left">Name</th>
                    <th align="center">Endstich</th>
                    <th align="center">Partner Schwini</th>
                    <th align="center">Total</th>
                  </tr></thead><tbody>';
        
        $rang = 1;
        foreach ($data as $row) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';
            
            $html .= "<tr>";
            $html .= "<td align=\"left\" $bold>$rang.</td>";
            $html .= "<td align=\"left\" $bold>{$row['PartnerName']}</td>";
            $html .= "<td align=\"center\" $bold>{$row['Endstich_Summe']}</td>";
            $html .= "<td align=\"center\" $bold>{$row['PartnerSchwini_Summe']}</td>";
            $html .= "<td align=\"center\" $bold style=\"font-weight: bold;\">{$row['Total_Summe']}</td>";
            $html .= "</tr>";
            
            // Trennlinie nach Rang 3
            if ($rang == 3) {
                $html .= '<tr>';
                for ($i = 0; $i < 5; $i++) {
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
 * SieEr Rangliste - Spezielle Berechnung mit einzigartigen Werten
 */
class SieErReport extends PDFGenerator {
    public function generate() {
        $sql = "
            SELECT
                m.ID,
                m.Name,
                m.Vorname,
                ep.PartnerName,
                ep.SieErSchuss1, ep.SieErSchuss2, ep.SieErSchuss3, ep.SieErSchuss4, ep.SieErSchuss5,
                ep.SieErSchuss6, ep.SieErSchuss7, ep.SieErSchuss8, ep.SieErSchuss9, ep.SieErSchuss10
            FROM mitglieder m
            INNER JOIN endresultate_partner ep ON m.ID = ep.MitgliedID
            WHERE ep.Jahr = {$this->selectedYear}
              AND (COALESCE(ep.SieErSchuss1, 0) + COALESCE(ep.SieErSchuss2, 0) + COALESCE(ep.SieErSchuss3, 0) +
                   COALESCE(ep.SieErSchuss4, 0) + COALESCE(ep.SieErSchuss5, 0) + COALESCE(ep.SieErSchuss6, 0) +
                   COALESCE(ep.SieErSchuss7, 0) + COALESCE(ep.SieErSchuss8, 0) + COALESCE(ep.SieErSchuss9, 0) +
                   COALESCE(ep.SieErSchuss10, 0)) > 0
            ORDER BY m.Name ASC, m.Vorname ASC
        ";
        
        $data = $this->executeQuery($sql);
        
        // Spezielle Total-Berechnung durchführen und sortieren
        foreach ($data as &$row) {
            $shots = [
                $row['SieErSchuss1'], $row['SieErSchuss2'], $row['SieErSchuss3'],
                $row['SieErSchuss4'], $row['SieErSchuss5'], $row['SieErSchuss6'],
                $row['SieErSchuss7'], $row['SieErSchuss8'], $row['SieErSchuss9'],
                $row['SieErSchuss10']
            ];
            
            // Nur einzigartige Werte sammeln und summieren
            $uniqueValues = [];
            foreach ($shots as $shot) {
                if ($shot !== null && $shot > 0) {
                    // Konvertiere zu Integer um Decimal/Integer Probleme zu vermeiden
                    $intValue = (int)$shot;
                    $uniqueValues[$intValue] = $intValue;
                }
            }
            
            $row['SpecialTotal'] = array_sum($uniqueValues);
        }
        
        // Nach Special Total sortieren (absteigend)
        usort($data, function($a, $b) {
            if ($a['SpecialTotal'] == $b['SpecialTotal']) {
                // Bei Gleichstand nach Namen sortieren
                return strcmp($a['Name'] . $a['Vorname'], $b['Name'] . $b['Vorname']);
            }
            return $b['SpecialTotal'] - $a['SpecialTotal'];
        });
        
        $html = $this->createHTMLHeader('SieEr Rangliste ' . $this->selectedYear);
        $html .= '<h2>Sie und Er Rangliste ' . $this->selectedYear . '</h2>';
        $html .= '<p><strong>Spezielle Berechnung: Jeder Wert wird nur einmal gezählt</strong></p>';
        $html .= '<p>Beispiel: 3x 10er = nur eine 10 für die Summe</p>';
        $html .= $this->createSieErTable($data);
        $html .= $this->createHTMLFooter();
        
        $pdfPath = $this->generatePDF($html, 'RanglisteSieEr');
        $this->outputDownloadLink($pdfPath);
    }
    
    private function createSieErTable($data) {
        if (empty($data)) {
            return '<p>Keine Ergebnisse gefunden.</p>';
        }
        
        $html = '<table class="table">';
        $html .= '<thead><tr>
                    <th style="width: 5%; text-align: left;">Rang</th>
                    <th style="width: 20%; text-align: left;">Name</th>
                    <th style="width: 15%; text-align: left;">Partner</th>
                    <th style="width: 50%; text-align: center;">SieEr Schüsse (1-10)</th>
                    <th style="width: 10%; text-align: center;">Total*</th>
                  </tr></thead><tbody>';
        
        $rang = 1;
        foreach ($data as $row) {
            $bold = ($rang <= 3) ? 'font-weight: bold;' : '';
            
            // Sammle Schüsse mit Quelleninfo
            $partnerShots = [];
            $mitgliedShots = [];
            $allValues = [];
            
            // Partner Schüsse (1-5)
            for ($i = 1; $i <= 5; $i++) {
                if ($row['SieErSchuss' . $i] !== null && $row['SieErSchuss' . $i] > 0) {
                    $partnerShots[] = $row['SieErSchuss' . $i];
                    $allValues[] = (int)$row['SieErSchuss' . $i];
                }
            }
            
            // Mitglied Schüsse (6-10)
            for ($i = 6; $i <= 10; $i++) {
                if ($row['SieErSchuss' . $i] !== null && $row['SieErSchuss' . $i] > 0) {
                    $mitgliedShots[] = $row['SieErSchuss' . $i];
                    $allValues[] = (int)$row['SieErSchuss' . $i];
                }
            }
            
            // Berechne welche Werte unique sind
            $valueCount = array_count_values($allValues);
            $shownValues = [];
            
            // Erstelle Badge-HTML für Schüsse mit PDF-kompatiblem Inline-Style
            $shotsDisplay = '';
            
            // Partner Badges (rot)
            foreach ($partnerShots as $shot) {
                $intShot = (int)$shot;
                if (in_array($intShot, $shownValues)) {
                    // Duplikat - durchgestrichen mit hellem Hintergrund
                    $shotsDisplay .= '<span style="' .
                        'background-color: #ffcccc; ' .
                        'color: #cc0000; ' .
                        'border: 1px solid #cc0000; ' .
                        'padding: 2px 5px; ' .
                        'margin: 0 2px; ' .
                        'font-size: 10px; ' .
                        'font-weight: 600; ' .
                        'text-decoration: line-through; ' .
                        'border-radius: 3px; ' .
                        '">' . $shot . '</span>';
                } else {
                    // Erstes Vorkommen - volle Farbe
                    $shotsDisplay .= '<span style="' .
                        'background-color: #dc3545; ' .
                        'color: white; ' .
                        'border: 1px solid #dc3545; ' .
                        'padding: 2px 5px; ' .
                        'margin: 0 2px; ' .
                        'font-size: 10px; ' .
                        'font-weight: 600; ' .
                        'border-radius: 3px; ' .
                        '">' . $shot . '</span>';
                    $shownValues[] = $intShot;
                }
            }
            
            // Trennstrich zwischen Partner und Mitglied
            if (!empty($partnerShots) && !empty($mitgliedShots)) {
                $shotsDisplay .= ' <span style="color: #999; margin: 0 3px; font-weight: bold;">|</span> ';
            }
            
            // Mitglied Badges (blau)
            foreach ($mitgliedShots as $shot) {
                $intShot = (int)$shot;
                if (in_array($intShot, $shownValues)) {
                    // Duplikat - durchgestrichen mit hellem Hintergrund
                    $shotsDisplay .= '<span style="' .
                        'background-color: #cce5ff; ' .
                        'color: #004085; ' .
                        'border: 1px solid #004085; ' .
                        'padding: 2px 5px; ' .
                        'margin: 0 2px; ' .
                        'font-size: 10px; ' .
                        'font-weight: 600; ' .
                        'text-decoration: line-through; ' .
                        'border-radius: 3px; ' .
                        '">' . $shot . '</span>';
                } else {
                    // Erstes Vorkommen - volle Farbe
                    $shotsDisplay .= '<span style="' .
                        'background-color: #0d6efd; ' .
                        'color: white; ' .
                        'border: 1px solid #0d6efd; ' .
                        'padding: 2px 5px; ' .
                        'margin: 0 2px; ' .
                        'font-size: 10px; ' .
                        'font-weight: 600; ' .
                        'border-radius: 3px; ' .
                        '">' . $shot . '</span>';
                    $shownValues[] = $intShot;
                }
            }
            
            $html .= '<tr>';
            $html .= '<td style="text-align: left; ' . $bold . '">' . $rang . '.</td>';
            $html .= '<td style="text-align: left; ' . $bold . '">' . $row['Name'] . ' ' . $row['Vorname'] . '</td>';
            $html .= '<td style="text-align: left; ' . $bold . '">' . $row['PartnerName'] . '</td>';
            $html .= '<td style="text-align: center; padding: 5px;">' . $shotsDisplay . '</td>';
            $html .= '<td style="text-align: center; font-weight: bold; ' . $bold . '">' . $row['SpecialTotal'] . '</td>';
            $html .= '</tr>';
            
            // Trennlinie nach Rang 3
            if ($rang == 3) {
                $html .= '<tr><td colspan="5" style="border-bottom: 2px solid #dee2e6; padding: 3px;"></td></tr>';
            }
            
            $rang++;
        }
        
        $html .= '</tbody></table>';
        
        // Erklärungsbox am Ende
        $html .= '<div style="margin-top: 15px; padding: 10px; background-color: #f8f9fa; border-left: 4px solid #0d6efd; font-size: 9px;">';
        $html .= '<strong>Erläuterung zur speziellen Berechnung:</strong><br/>';
        $html .= '• <span style="background-color: #dc3545; color: white; padding: 1px 4px; border-radius: 2px;">Rot</span> = Partner-Schüsse (Position 1-5)<br/>';
        $html .= '• <span style="background-color: #0d6efd; color: white; padding: 1px 4px; border-radius: 2px;">Blau</span> = Mitglied-Schüsse (Position 6-10)<br/>';
        $html .= '• <span style="text-decoration: line-through;">Durchgestrichen</span> = Duplikate (zählen nicht für Total)<br/>';
        $html .= '• <strong>Total*</strong>: Jeder einzigartige Wert wird nur einmal gezählt<br/>';
        $html .= '• Beispiel: Wenn 3x die "10" geschossen wurde, zählt sie nur 1x für das Total';
        $html .= '</div>';
        
        return $html;
    }
}
?>