<?php
// PDFReports.php - Spezifische Report-Klassen

require_once 'PDFGenerator.php';
require_once __DIR__ . '/../endschresultate/teilnehmer.inc.php';

/**
 * Endstich Rangliste
 */
class EndstichReport extends PDFGenerator
{
    public function generate()
    {
        // Mitglieder, Gäste und Jungschützen (Migration 094: alle in endstich, Gäste über GastID)
        $sql = "
            SELECT
                u.Schluessel AS ID,
                u.TeilnehmerTyp,
                u.Name,
                u.Vorname,
                u.Geburtsdatum,
                u.Schuss1, u.Schuss2, u.Schuss3, u.Schuss4, u.Schuss5,
                u.Schuss6, u.Schuss7, u.Schuss8, u.Schuss9, u.Schuss10,
                u.Tiefschuss,
                u.Kranz_Endstich,
                COALESCE(
                    u.Schuss1 + u.Schuss2 + u.Schuss3 + u.Schuss4 + u.Schuss5 +
                    u.Schuss6 + u.Schuss7 + u.Schuss8 + u.Schuss9 + u.Schuss10
                , 0) AS Endstich_Summe,
                ((u.Schuss1 = 10) + (u.Schuss2 = 10) + (u.Schuss3 = 10) + (u.Schuss4 = 10) + (u.Schuss5 = 10) +
                 (u.Schuss6 = 10) + (u.Schuss7 = 10) + (u.Schuss8 = 10) + (u.Schuss9 = 10) + (u.Schuss10 = 10)) AS Anzahl_10
            FROM " . endschQuelle('endstich', ENDSCH_TYPEN_ALLE) . " u
            WHERE COALESCE(
                    u.Schuss1 + u.Schuss2 + u.Schuss3 + u.Schuss4 + u.Schuss5 +
                    u.Schuss6 + u.Schuss7 + u.Schuss8 + u.Schuss9 + u.Schuss10
                , 0) != 0
            ORDER BY
                Endstich_Summe DESC,
                u.Tiefschuss DESC,
                Anzahl_10 DESC,
                u.Geburtsdatum ASC, u.Name ASC
        ";

        $y = (int)$this->selectedYear;
        $data = $this->executePreparedQuery($sql, "i", $y);

        // Daten für Tabelle vorbereiten (mit fmtNoTrailingZero)
        $tableData = [];
        $rang = 1;
        foreach ($data as $row) {
            $shots = $this->getSortedShots([
                $row['Schuss1'],
                $row['Schuss2'],
                $row['Schuss3'],
                $row['Schuss4'],
                $row['Schuss5'],
                $row['Schuss6'],
                $row['Schuss7'],
                $row['Schuss8'],
                $row['Schuss9'],
                $row['Schuss10']
            ]);

            $kk = '';
            if ($rang == 1) {
                $kk = 'KK';
            } elseif (!empty($row['Kranz_Endstich']) && $row['Endstich_Summe'] >= $row['Kranz_Endstich']) {
                $kk = 'KK';
            }

            $tableRow = [
                'name' => $row['Name'] . ' ' . $row['Vorname'],
                'total' => (string) fmtNoTrailingZero($row['Endstich_Summe']),
                'ts' => (string) fmtNoTrailingZero($row['Tiefschuss']),
                'kk' => $kk
            ];

            for ($i = 0; $i < 10; $i++) {
                $val = isset($shots[$i]) ? $shots[$i] : '';
                $tableRow['shot' . ($i + 1)] = ($val === '' ? '' : (string) fmtNoTrailingZero($val));
            }

            $tableData[] = $tableRow;
            $rang++;
        }

        // HTML erstellen
        $html = $this->createHTMLHeader('Endstich Rangliste ' . $this->selectedYear);
        $html .= '<h2>Endstich Rangliste ' . $this->selectedYear . '</h2>';
        $html .= $this->createEndstichTable($tableData);
        $html .= $this->createHTMLFooter();

        $pdfPath = $this->generatePDF($html, 'RanglisteEndstich');
        $this->outputDownloadLink($pdfPath);
    }

    private function createEndstichTable($data)
    {
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

            $html .= '<tr>';
            $html .= "<td align=\"left\" $bold>{$rang}.</td>";
            $html .= "<td align=\"left\" $bold>{$row['name']}</td>";

            // 10 Schüsse (Fallback-Formatierung)
            for ($i = 1; $i <= 10; $i++) {
                $value = isset($row['shot' . $i]) ? $row['shot' . $i] : '';
                if ($value !== '')
                    $value = (string) fmtNoTrailingZero($value);
                $html .= "<td align=\"right\" $bold>$value</td>";
            }

            $total = (string) fmtNoTrailingZero($row['total']);
            $ts = (string) fmtNoTrailingZero($row['ts']);

            $html .= "<td align=\"center\" $bold>$total</td>";
            $html .= "<td align=\"center\" $bold>$ts</td>";
            $html .= "<td align=\"right\" $bold>{$row['kk']}</td>";
            $html .= '</tr>';

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
class SchwiniReport extends PDFGenerator
{
    public function generate()
    {
        // Mitglieder, Gäste und Jungschützen (Migration 094: alle in schwini, Gäste über GastID)
        $sql = "
SELECT
    u.Schluessel AS ID,
    u.TeilnehmerTyp,
    u.Name,
    u.Vorname,
    u.Geburtsdatum,
    u.P1Schuss1, u.P1Schuss2, u.P1Schuss3, u.P1Schuss4, u.P1Schuss5, u.P1Schuss6,
    u.P2Schuss1, u.P2Schuss2, u.P2Schuss3, u.P2Schuss4, u.P2Schuss5, u.P2Schuss6,
    COALESCE((u.P1Schuss1 + u.P1Schuss2 + u.P1Schuss3 + u.P1Schuss4 + u.P1Schuss5 + u.P1Schuss6), 0) AS Schwini1_Summe,
    COALESCE((u.P2Schuss1 + u.P2Schuss2 + u.P2Schuss3 + u.P2Schuss4 + u.P2Schuss5 + u.P2Schuss6), 0) AS Schwini2_Summe,
    GREATEST(
        COALESCE((u.P1Schuss1 + u.P1Schuss2 + u.P1Schuss3 + u.P1Schuss4 + u.P1Schuss5 + u.P1Schuss6), 0),
        COALESCE((u.P2Schuss1 + u.P2Schuss2 + u.P2Schuss3 + u.P2Schuss4 + u.P2Schuss5 + u.P2Schuss6), 0)
    ) AS Hoechste_Summe,
    LEAST(
        COALESCE((u.P1Schuss1 + u.P1Schuss2 + u.P1Schuss3 + u.P1Schuss4 + u.P1Schuss5 + u.P1Schuss6), 0),
        COALESCE((u.P2Schuss1 + u.P2Schuss2 + u.P2Schuss3 + u.P2Schuss4 + u.P2Schuss5 + u.P2Schuss6), 0)
    ) AS Tiefste_Summe
FROM " . endschQuelle('schwini', ENDSCH_TYPEN_ALLE) . " u
WHERE COALESCE(
        (u.P1Schuss1 + u.P1Schuss2 + u.P1Schuss3 + u.P1Schuss4 + u.P1Schuss5 + u.P1Schuss6), 0
     ) != 0
   OR COALESCE(
        (u.P2Schuss1 + u.P2Schuss2 + u.P2Schuss3 + u.P2Schuss4 + u.P2Schuss5 + u.P2Schuss6), 0
     ) != 0
ORDER BY Hoechste_Summe DESC, Tiefste_Summe DESC, Geburtsdatum ASC, Name ASC
";

        $y = (int)$this->selectedYear;
        $data = $this->executePreparedQuery($sql, "i", $y);

        $html = $this->createHTMLHeader('Schwini Rangliste ' . $this->selectedYear);
        $html .= '<h2>Schwini Rangliste ' . $this->selectedYear . '</h2>';
        $html .= $this->createSchwiniTable($data);
        $html .= $this->createHTMLFooter();

        $pdfPath = $this->generatePDF($html, 'RanglisteSchwini');
        $this->outputDownloadLink($pdfPath);
    }

    private function createSchwiniTable($data)
    {
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
                $shots = [
                    $row['P1Schuss1'],
                    $row['P1Schuss2'],
                    $row['P1Schuss3'],
                    $row['P1Schuss4'],
                    $row['P1Schuss5'],
                    $row['P1Schuss6']
                ];
            } else {
                $shots = [
                    $row['P2Schuss1'],
                    $row['P2Schuss2'],
                    $row['P2Schuss3'],
                    $row['P2Schuss4'],
                    $row['P2Schuss5'],
                    $row['P2Schuss6']
                ];
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
class KunstReport extends PDFGenerator
{
    public function generate()
    {
        // Mitglieder und Gäste (Jungschützen schiessen keinen Kunststich)
        $sql = "SELECT
            u.Schluessel AS ID,
            u.TeilnehmerTyp,
            u.Name,
            u.Vorname,
            u.Geburtsdatum,
            u.KSchuss1, u.KSchuss2, u.KSchuss3, u.KSchuss4, u.KSchuss5,
            u.Kranz_Kunst,
            COALESCE(u.KSchuss1 + u.KSchuss2 + u.KSchuss3 + u.KSchuss4 + u.KSchuss5, 0) AS Kunst_Summe,
            GREATEST(
                COALESCE(u.KSchuss1, 0),
                COALESCE(u.KSchuss2, 0),
                COALESCE(u.KSchuss3, 0),
                COALESCE(u.KSchuss4, 0),
                COALESCE(u.KSchuss5, 0)
            ) AS TS
        FROM " . endschQuelle('kunst', ENDSCH_TYPEN_MITGLIED_GAST) . " u
        WHERE u.KSchuss1 IS NOT NULL AND u.KSchuss1 != 0
        ORDER BY Kunst_Summe DESC, TS DESC, u.Geburtsdatum ASC, u.Name ASC";

        $y = (int)$this->selectedYear;
        $data = $this->executePreparedQuery($sql, "i", $y);

        $html = $this->createHTMLHeader('Kunst Rangliste ' . $this->selectedYear);
        $html .= '<h2>Kunst Rangliste ' . $this->selectedYear . '</h2>';
        $html .= $this->createKunstTable($data);
        $html .= $this->createHTMLFooter();

        $pdfPath = $this->generatePDF($html, 'RanglisteKunst');
        $this->outputDownloadLink($pdfPath);
    }

    private function createKunstTable($data)
    {
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
        $wp = endschWpVergeben($data); // Wanderpreis nur für Mitglieder
        foreach ($data as $i => $row) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';

            $kk = '';
            if ($rang == 1 || $row['Kunst_Summe'] >= $row['Kranz_Kunst']) {
                $kk = 'KK';
            }
            if ($i === $wp) {
                $kk = $kk !== '' ? $kk . ' + WP' : 'WP';
            }

            $shots = $this->getSortedShots([
                $row['KSchuss1'],
                $row['KSchuss2'],
                $row['KSchuss3'],
                $row['KSchuss4'],
                $row['KSchuss5']
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
class GlueckReport extends PDFGenerator
{
    public function generate()
    {
        // Mitglieder und Gäste (Jungschützen schiessen keinen Glückstich)
        $sql = "SELECT
            u.Schluessel AS ID,
            u.TeilnehmerTyp,
            u.Name,
            u.Vorname,
            u.Geburtsdatum,
            u.GSchuss1, u.GSchuss2, u.GSchuss3,
            GREATEST(u.GSchuss1, u.GSchuss2, u.GSchuss3) AS MaxGlueck,
            LEAST(
                GREATEST(u.GSchuss1, u.GSchuss2),
                GREATEST(u.GSchuss1, u.GSchuss3),
                GREATEST(u.GSchuss2, u.GSchuss3)
            ) AS ZweitHoechster,
            LEAST(u.GSchuss1, u.GSchuss2, u.GSchuss3) AS DrittHoechster
        FROM " . endschQuelle('glueck', ENDSCH_TYPEN_MITGLIED_GAST) . " u
        WHERE u.GSchuss1 != 0
        ORDER BY MaxGlueck DESC, ZweitHoechster DESC, DrittHoechster DESC, u.Geburtsdatum ASC, u.Name ASC";

        $y = (int)$this->selectedYear;
        $data = $this->executePreparedQuery($sql, "i", $y);

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
            $wp = endschWpVergeben($data); // Wanderpreis nur für Mitglieder
            foreach ($data as $i => $row) {
                $bold = ($rang <= 3) ? 'class="bold"' : '';
                $kk = ($i === $wp) ? 'WP' : '';

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
class ZabigReport extends PDFGenerator
{

    // Fallback, falls calculatePoints() noch nicht global geladen ist
    private function mapToPoints($v)
    {
        if (function_exists('calculatePoints')) {
            return calculatePoints($v);
        }
        if ($v >= 91)
            return 10;
        if ($v >= 81)
            return 9;
        if ($v >= 71)
            return 8;
        if ($v >= 61)
            return 7;
        if ($v >= 51)
            return 6;
        if ($v >= 41)
            return 5;
        if ($v >= 31)
            return 4;
        if ($v >= 21)
            return 3;
        if ($v >= 11)
            return 2;
        if ($v >= 1)
            return 1;
        return 0;
    }

    public function generate()
    {
        // Mitglieder, Gäste und Jungschützen (Migration 094: alle in zabig, Gäste über GastID)
        $sql = "
            SELECT
                u.Schluessel AS ID,
                u.TeilnehmerTyp,
                u.Name,
                u.Vorname,
                u.Geburtsdatum,
                u.ZSchuss1, u.ZSchuss2, u.ZSchuss3, u.ZSchuss4, u.ZSchuss5, u.ZSchuss6
            FROM " . endschQuelle('zabig', ENDSCH_TYPEN_ALLE) . " u
            WHERE COALESCE(u.ZSchuss1 + u.ZSchuss2 + u.ZSchuss3 + u.ZSchuss4 + u.ZSchuss5 + u.ZSchuss6, 0) != 0
        ";

        $y = (int)$this->selectedYear;
        $data = $this->executePreparedQuery($sql, "i", $y);

        // Umrechnung: Schüsse -> Punkte (nur für Anzeige & Total)
        // TS bleibt der beste Rohwert (Hunderterskala)
        foreach ($data as &$row) {
            $raw = [
                (float) $row['ZSchuss1'],
                (float) $row['ZSchuss2'],
                (float) $row['ZSchuss3'],
                (float) $row['ZSchuss4'],
                (float) $row['ZSchuss5'],
                (float) $row['ZSchuss6'],
            ];
            $row['TS'] = max($raw); // bester 100er-Wert, NICHT umrechnen

            // in Punkte mappen
            $points = [];
            foreach ($raw as $v) {
                $points[] = $this->mapToPoints($v);
            }

            // Punkte für die Anzeige sortieren (absteigend)
            rsort($points, SORT_NUMERIC);

            // im Row-Array ablegen
            for ($i = 0; $i < 6; $i++) {
                $row['PShot' . ($i + 1)] = $points[$i];
            }
            $row['Total'] = array_sum($points);
        }
        unset($row);

        // Sortierung: zuerst Total (Punkte) â†“, dann TS (Rohwert 100er) â†“
        usort($data, function ($a, $b) {
            if ($a['Total'] == $b['Total']) {
                return $b['TS'] <=> $a['TS'];
            }
            return $b['Total'] <=> $a['Total'];
        });

        $html = $this->createHTMLHeader('Zabigstich Rangliste ' . $this->selectedYear);
        $html .= '<h2>Zabigstich Rangliste ' . $this->selectedYear . '</h2>';
        $html .= $this->createZabigTable($data);
        $html .= $this->createHTMLFooter();

        $pdfPath = $this->generatePDF($html, 'RanglisteZabig');
        $this->outputDownloadLink($pdfPath);
    }

    private function createZabigTable($data)
    {
        if (empty($data)) {
            return '<p>Keine Ergebnisse gefunden.</p>';
        }

        $html = '<table class="table">';
        $html .= '<thead><tr>
                    <th align="left">Rang</th>
                    <th align="left">Name</th>
                    <th colspan="6" align="left">Passe (Punkte)</th>
                    <th>Total</th>
                    <th>TS</th>
                    <th></th>
                  </tr></thead><tbody>';

        $rang = 1;
        foreach ($data as $row) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';

            $html .= "<tr>";
            $html .= "<td align=\"left\" $bold>{$rang}.</td>";
            $html .= "<td align=\"left\" $bold>{$row['Name']} {$row['Vorname']}</td>";

            // 6 bereits umgerechnete Punkt-Schüsse
            for ($i = 1; $i <= 6; $i++) {
                $p = isset($row['PShot' . $i]) ? $row['PShot' . $i] : '';
                $html .= "<td align=\"right\" $bold>$p</td>";
            }

            $total = (string) (function_exists('fmtNoTrailingZero') ? fmtNoTrailingZero($row['Total']) : $row['Total']);
            $tsRaw = (string) (function_exists('fmtNoTrailingZero') ? fmtNoTrailingZero($row['TS']) : $row['TS']);

            $html .= "<td align=\"center\" $bold>$total</td>";
            $html .= "<td align=\"center\" $bold>($tsRaw)</td>";
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
class DifferenzlerReport extends PDFGenerator
{
    public function generate()
    {
        // Mitglieder und Gäste (wie bisher: wer Zabig geschossen hat; ohne Ansage steht die Differenz = Total)
        $sql = "SELECT
            u.Schluessel AS ID,
            u.TeilnehmerTyp,
            u.Name,
            u.Vorname,
            u.Geburtsdatum,
            u.ZSchuss1, u.ZSchuss2, u.ZSchuss3, u.ZSchuss4, u.ZSchuss5, u.ZSchuss6,
            u.Ansage,
            COALESCE(u.ZSchuss1 + u.ZSchuss2 + u.ZSchuss3 + u.ZSchuss4 + u.ZSchuss5 + u.ZSchuss6, 0) AS DiffTotal,
            ABS(COALESCE(u.ZSchuss1 + u.ZSchuss2 + u.ZSchuss3 + u.ZSchuss4 + u.ZSchuss5 + u.ZSchuss6 - u.Ansage, 0)) AS NormDiff
        FROM " . endschQuelle('zabig', ENDSCH_TYPEN_MITGLIED_GAST) . " u
        WHERE u.ZSchuss1 != 0
        ORDER BY NormDiff ASC, u.Geburtsdatum ASC, u.Name ASC";

        $y = (int)$this->selectedYear;
        $data = $this->executePreparedQuery($sql, "i", $y);

        $html = $this->createHTMLHeader('Differenzler ' . $this->selectedYear);
        $html .= '<h2>Differenzler ' . $this->selectedYear . '</h2>';
        $html .= $this->createDifferenzlerTable($data);
        $html .= $this->createHTMLFooter();

        $pdfPath = $this->generatePDF($html, 'RanglisteDifferenzler');
        $this->outputDownloadLink($pdfPath);
    }

    private function createDifferenzlerTable($data)
    {
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
                $row['ZSchuss1'],
                $row['ZSchuss2'],
                $row['ZSchuss3'],
                $row['ZSchuss4'],
                $row['ZSchuss5'],
                $row['ZSchuss6']
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

class AnmeldungReport extends PDFGenerator
{
    /** Cache für Stich-IDs nach Code */
    private array $stichIdCache = [];

    /** Fragt auch direkt über $this->conn ab – die Leer-Prüfung der Basisklasse würde hier falsch zählen. */
    protected $leerPruefen = false;

    public function generate()
    {
        $html = $this->createHTMLHeader('Absendenanmeldung', '', 12);
        $html .= '<div class="container">';

        // Teil 1: Mitglieder mit Absendenanmeldung
        $html .= '<h5>Absendenanmeldung ' . (int)$this->selectedYear . '</h5>';
        $html .= $this->createAnmeldungTable();

        // Seitenumbruch
        $html .= '</div><div style="page-break-before: always;"></div><div class="container">';

        // Teil 2: Schwini (aus selection), Differenzler, Sie & Er, Partner-Pakete
        $html .= '<h5>Schwini, Differenzler, Sie &amp; Er und Partner-Endschiessen</h5>';
        $html .= $this->createSchwiniDifferenzlerTable();

        $html .= $this->createHTMLFooter();

        $pdfPath = $this->generatePDF($html, 'Mitglieder_Absendenanmeldung');
        $this->outputDownloadLink($pdfPath);
    }

private function createAnmeldungTable()
{
    // Alle Teilnehmer mit Endstich-Eintrag (Migration 094: Gäste und Jungschützen über GastID).
    // Gäste erst ab einer Anmeldung, damit die übernommenen Partnerinnen ohne Angabe nicht als 0 erscheinen.
    $sql = "
        SELECT
            CONCAT(u.Name, IF(u.Vorname = '', '', CONCAT(' ', u.Vorname))) AS Name,
            u.AbsendenAnmeldung AS Anmeldungen
        FROM " . endschQuelle('endstich', ENDSCH_TYPEN_ALLE) . " u
        WHERE u.AbsendenAnmeldung IS NOT NULL
          AND (u.TeilnehmerTyp <> 'gast' OR u.AbsendenAnmeldung > 0)
        ORDER BY Name ASC";

    $y = (int)$this->selectedYear;
    $data = $this->executePreparedQuery($sql, "i", $y);

    if (!empty($data)) {
        $html = '<table class="table table-bordered">';
        $html .= '<thead><tr>
                    <th align="left">Name</th>
                    <th align="center">Anzahl</th>
                  </tr></thead><tbody>';

        $total = 0;
        foreach ($data as $row) {
            $name = htmlspecialchars($row['Name'] ?? '', ENT_QUOTES, 'UTF-8');
            $anz = (int)($row['Anmeldungen'] ?? 0);

            $html .= "<tr>";
            $html .= "<td align='left'>{$name}</td>";
            $html .= "<td align='center'>{$anz}</td>";
            $html .= "</tr>";

            $total += $anz;
        }

        $html .= "<tr>";
        $html .= "<td align='left'><strong>Total</strong></td>";
        $html .= "<td align='center'><strong>$total</strong></td>";
        $html .= "</tr>";

        $html .= '</tbody></table>';
    } else {
        $html = '<p>Keine Ergebnisse gefunden.</p>';
    }

    return $html;
}

    /** CHF-Format aus Rappen */
    private function frStrFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '\'');
    }

    /** CHF-Format aus Franken (int/float) */
    private function frStrFromFr(float|int $fr): string
    {
        return number_format((float)$fr, 2, '.', '\'');
    }

    /** Stich-ID aus endstich_definition per Code (z.B. 'SCHWINI_P1', 'SCHWINI_P2', 'SIEUNDER') */
    private function getStichIdByCode(string $code): ?int
    {
        if (array_key_exists($code, $this->stichIdCache)) {
            return $this->stichIdCache[$code];
        }

        $stmt = $this->conn->prepare("SELECT id FROM endstich_definition WHERE code = ? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $id  = $res['id'] ?? null;

        $this->stichIdCache[$code] = $id ? (int)$id : null;
        return $this->stichIdCache[$code];
    }

    /** Preis (Rappen) aus endstich_definition per Code laden, z.B. 'SIEUNDER' */
    private function getDefinitionPriceCents(string $code): int
    {
        $stmt = $this->conn->prepare("SELECT price_cents FROM endstich_definition WHERE code = ? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        return $res ? (int)$res['price_cents'] : 0;
    }

    /**
     * Schwini-Pässe aus endstich_selection,
     * getrennt nach Mitglieder, Gäste (ohne Geburtsdatum), Jungschützen (mit Geburtsdatum)
     */
    private function getSchwiniCountsFromSelection(): array
    {
        $y   = (int)$this->selectedYear;
        $p1  = $this->getStichIdByCode('SCHWINI_P1') ?? -1;
        $p2  = $this->getStichIdByCode('SCHWINI_P2') ?? -1;

        // Mitglieder
        $sqlMitgl = "
            SELECT
              SUM(CASE WHEN s.stich_id = ? THEN 1 ELSE 0 END) AS p1,
              SUM(CASE WHEN s.stich_id = ? THEN 1 ELSE 0 END) AS p2
            FROM endstich_selection s
            WHERE s.jahr = ? AND s.mitglied_id IS NOT NULL
        ";
        $stmt = $this->conn->prepare($sqlMitgl);
        $stmt->bind_param("iii", $p1, $p2, $y);
        $stmt->execute();
        $m = $stmt->get_result()->fetch_assoc() ?: ['p1'=>0,'p2'=>0];

        // Gäste / Jung via endstich_gaeste
        $sqlGastBase = "
            SELECT
              SUM(CASE WHEN s.stich_id = ? THEN 1 ELSE 0 END) AS p1,
              SUM(CASE WHEN s.stich_id = ? THEN 1 ELSE 0 END) AS p2
            FROM endstich_selection s
            JOIN endstich_gaeste g ON g.id = s.gast_id AND g.jahr = s.jahr
            WHERE s.jahr = ? AND s.gast_id IS NOT NULL
              /**COND**/
        ";

        // Gäste (ohne Geburtsdatum)
        $stmt = $this->conn->prepare(str_replace('/**COND**/', 'AND g.geburtsdatum IS NULL', $sqlGastBase));
        $stmt->bind_param("iii", $p1, $p2, $y);
        $stmt->execute();
        $g = $stmt->get_result()->fetch_assoc() ?: ['p1'=>0,'p2'=>0];

        // Jungschützen (mit Geburtsdatum)
        $stmt = $this->conn->prepare(str_replace('/**COND**/', 'AND g.geburtsdatum IS NOT NULL', $sqlGastBase));
        $stmt->bind_param("iii", $p1, $p2, $y);
        $stmt->execute();
        $j = $stmt->get_result()->fetch_assoc() ?: ['p1'=>0,'p2'=>0];

        return [
            'mitglieder' => ['p1' => (int)$m['p1'], 'p2' => (int)$m['p2']],
            'gaeste'     => ['p1' => (int)$g['p1'], 'p2' => (int)$g['p2']],
            'jung'       => ['p1' => (int)$j['p1']], // P2 für Jung nicht ausgewiesen
        ];
    }

    /** Schwini-Preise (CHF) zentral */
    private function getSchwiniPrices(): array
    {
        return [
            'mitglieder' => ['p1' => 20.0, 'p2' => 14.0],
            'gaeste'     => ['p1' => 20.0, 'p2' => 14.0],
            'jung'       => ['p1' => 16.0], // nur P1
        ];
    }

    /** Sie & Er (Variante A): nur echte SIEUNDER-Stiche zählen */
    private function getSieUndErStats(): array
    {
        $y        = (int)$this->selectedYear;
        $sieId    = $this->getStichIdByCode('SIEUNDER');

        if (!$sieId) {
            return ['anz' => 0, 'amount_cents' => 0];
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM endstich_selection s
            WHERE s.jahr = ? AND s.stich_id = ?
        ");
        $stmt->bind_param("ii", $y, $sieId);
        $stmt->execute();
        $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

        $price = $this->getDefinitionPriceCents('SIEUNDER'); // z.B. 1000 Rp
        return ['anz' => $cnt, 'amount_cents' => $cnt * $price];
    }

    /**
     * Partner-Pakete (Summe über Partner = Gäste ohne Geburtsdatum).
     * Pro Partner max(gast_spezialpreis) (in Rappen) â€” so vermeiden wir Doppelzählungen.
     * Liefert auch Details (heuristisch) für Kombi 2 / Kombi 3.
     */
    private function getPartnerPaketeStats(): array
    {
        $y = (int)$this->selectedYear;

        $stmt = $this->conn->prepare("
            SELECT s.gast_id, MAX(s.gast_spezialpreis) AS price_per_guest
            FROM endstich_selection s
            JOIN endstich_gaeste g ON g.id = s.gast_id AND g.jahr = s.jahr
            WHERE s.jahr = ?
              AND s.gast_id IS NOT NULL
              AND g.geburtsdatum IS NULL
              AND s.gast_spezialpreis IS NOT NULL
            GROUP BY s.gast_id
        ");
        $stmt->bind_param("i", $y);
        $stmt->execute();
        $res = $stmt->get_result();

        $sumCnt = 0;
        $sumCents = 0;

        // Heuristik: <= 4500 => Kombi 2, sonst Kombi 3 (falls Du andere Preise nutzt: Schwelle anpassen)
        $cntKombi2 = 0; $centsKombi2 = 0;
        $cntKombi3 = 0; $centsKombi3 = 0;

        while ($row = $res->fetch_assoc()) {
            $price = (int)($row['price_per_guest'] ?? 0);
            if ($price <= 0) { continue; }
            $sumCnt++;
            $sumCents += $price;

            if ($price <= 4500) {
                $cntKombi2++; $centsKombi2 += $price;
            } else {
                $cntKombi3++; $centsKombi3 += $price;
            }
        }

        return [
            'anz' => $sumCnt,
            'amount_cents' => $sumCents,
            'details' => [
                'gast_kombi_2' => ['cnt' => $cntKombi2, 'amount_cents' => $centsKombi2],
                'gast_kombi_3' => ['cnt' => $cntKombi3, 'amount_cents' => $centsKombi3],
            ],
        ];
    }

    /** Baut die Tabelle: Schwini (aus selection) + Differenzler + Sie & Er + Partner (Netto) + Gesamttotal */
    private function createSchwiniDifferenzlerTable()
    {
        // --- Schwini aus selection ---
        $counts = $this->getSchwiniCountsFromSelection();
        $prices = $this->getSchwiniPrices();

        // Beträge (CHF numerisch)
        $mit_p1_amt = $counts['mitglieder']['p1'] * $prices['mitglieder']['p1'];
        $mit_p2_amt = $counts['mitglieder']['p2'] * $prices['mitglieder']['p2'];
        $ga_p1_amt  = $counts['gaeste']['p1']     * $prices['gaeste']['p1'];
        $ga_p2_amt  = $counts['gaeste']['p2']     * $prices['gaeste']['p2'];
        $ju_p1_amt  = $counts['jung']['p1']       * $prices['jung']['p1'];

        $total_schwini_chf = $mit_p1_amt + $mit_p2_amt + $ga_p1_amt + $ga_p2_amt + $ju_p1_amt;

        // --- Differenzler: Anzahl gelöste Differenzler (endstich_selection, wie Schwini und Sie & Er) ---
        $sql_diff = "SELECT COUNT(*) as Anzahl FROM endstich_selection s JOIN endstich_definition d ON d.id = s.stich_id WHERE d.code = 'DIFF' AND s.jahr = ?";
        $stmt_diff = $this->conn->prepare($sql_diff);
        $y_diff = (int)$this->selectedYear;
        $stmt_diff->bind_param("i", $y_diff);
        $stmt_diff->execute();
        $row_diff = $stmt_diff->get_result()->fetch_assoc();
        $stmt_diff->close();
        $anzahl_diff = (int)($row_diff['Anzahl'] ?? 0);
        $amount_diff_chf = $anzahl_diff * 5.0;

        // --- Sie & Er (nur SIEUNDER) ---
        $se = $this->getSieUndErStats();
        $anzSieUndEr = $se['anz'];
        $amountSieUndEr_chf = $se['amount_cents'] / 100.0;

        // --- Partner-Pakete (Brutto) ---
        $pp = $this->getPartnerPaketeStats();
        $anzPartnerPakete = $pp['anz'];
        $amountPartnerPakete_brutto_chf = $pp['amount_cents'] / 100.0;

        // Details
        $cntKombi2 = $pp['details']['gast_kombi_2']['cnt'] ?? 0;
        $amtKombi2_chf = (($pp['details']['gast_kombi_2']['amount_cents'] ?? 0) / 100.0);
        $cntKombi3 = $pp['details']['gast_kombi_3']['cnt'] ?? 0;
        $amtKombi3_chf = (($pp['details']['gast_kombi_3']['amount_cents'] ?? 0) / 100.0);

        // --- Abzug: Schwini-Anteil der Partner (Gäste ohne Geburtsdatum), bereits oben in Schwini enthalten ---
        $ga_schwini_anteil_chf = $ga_p1_amt + $ga_p2_amt;

        // Partner-Pakete NETTO (ohne bereits oben gezählte Schwini-Anteile der Partner)
        $amountPartnerPakete_netto_chf = max(0.0, $amountPartnerPakete_brutto_chf - $ga_schwini_anteil_chf);

        // --- Gesamttotal (CHF numerisch) ---
        $grand_total_chf = $total_schwini_chf + $amount_diff_chf + $amountSieUndEr_chf + $amountPartnerPakete_netto_chf;

        // --- Strings formatieren ---
        $mit_p1_amt_str = $this->frStrFromFr($mit_p1_amt);
        $mit_p2_amt_str = $this->frStrFromFr($mit_p2_amt);
        $ga_p1_amt_str  = $this->frStrFromFr($ga_p1_amt);
        $ga_p2_amt_str  = $this->frStrFromFr($ga_p2_amt);
        $ju_p1_amt_str  = $this->frStrFromFr($ju_p1_amt);
        $total_schwini_str = $this->frStrFromFr($total_schwini_chf);

        $amount_diff_str = $this->frStrFromFr($amount_diff_chf);
        $amountSieUndEr_str = $this->frStrFromFr($amountSieUndEr_chf);

        $amtKombi2_str = $this->frStrFromFr($amtKombi2_chf);
        $amtKombi3_str = $this->frStrFromFr($amtKombi3_chf);

        $amountPartnerPakete_brutto_str = $this->frStrFromFr($amountPartnerPakete_brutto_chf);
        $ga_schwini_anteil_str = $this->frStrFromFr($ga_schwini_anteil_chf);
        $amountPartnerPakete_netto_str  = $this->frStrFromFr($amountPartnerPakete_netto_chf);

        $grand_total_str = $this->frStrFromFr($grand_total_chf);

        // --- Tabelle rendern ---
        $html = '<table class="table table-bordered">';
        $html .= '<thead><tr>
                    <th align="left">Stich</th>
                    <th align="center">Anzahl</th>
                    <th align="right">Betrag (Fr.)</th>
                  </tr></thead><tbody>';

        // Schwini
        $html .= "<tr><td align='left'><strong>Schwini</strong></td><td></td><td></td></tr>";
        $html .= "<tr><td align='left'>&nbsp;&nbsp;Schwini (1. Passe)</td><td align='center'>".$counts['mitglieder']['p1']."</td><td align='right'>{$mit_p1_amt_str}</td></tr>";
        $html .= "<tr><td align='left'>&nbsp;&nbsp;Schwini (2. Passe)</td><td align='center'>".$counts['mitglieder']['p2']."</td><td align='right'>{$mit_p2_amt_str}</td></tr>";
        $html .= "<tr><td align='left'>&nbsp;&nbsp;Partner Schwini (1. Passe)</td><td align='center'>".$counts['gaeste']['p1']."</td><td align='right'>{$ga_p1_amt_str}</td></tr>";
        $html .= "<tr><td align='left'>&nbsp;&nbsp;Partner Schwini (2. Passe)</td><td align='center'>".$counts['gaeste']['p2']."</td><td align='right'>{$ga_p2_amt_str}</td></tr>";
        $html .= "<tr><td align='left'>&nbsp;&nbsp;Jungschützen Schwini (1. Passe)</td><td align='center'>".$counts['jung']['p1']."</td><td align='right'>{$ju_p1_amt_str}</td></tr>";

        $html .= "<tr><td align='left' colspan='2'><strong>Total Schwini</strong></td><td align='right'><strong>{$total_schwini_str}</strong></td></tr>";
        $html .= "<tr><td colspan='3'></td></tr>";

        // Differenzler
        $html .= "<tr><td align='left'><strong>Differenzler</strong></td><td></td><td></td></tr>";
        $html .= "<tr><td align='left'>Differenzler</td><td align='center'>{$anzahl_diff}</td><td align='right'>{$amount_diff_str}</td></tr>";
        $html .= "<tr><td align='left' colspan='2'><strong>Total Differenzler</strong></td><td align='right'><strong>{$amount_diff_str}</strong></td></tr>";
        $html .= "<tr><td colspan='3'></td></tr>";

        // Sie & Er
        $html .= "<tr><td align='left'><strong>Sie & Er</strong></td><td></td><td></td></tr>";
        $html .= "<tr><td align='left'>Sie &amp; Er</td><td align='center'>{$anzSieUndEr}</td><td align='right'>{$amountSieUndEr_str}</td></tr>";
        $html .= "<tr><td align='left' colspan='2'><strong>Total Sie &amp; Er</strong></td><td align='right'><strong>{$amountSieUndEr_str}</strong></td></tr>";
        $html .= "<tr><td colspan='3'></td></tr>";

        // Partner-Pakete (Details + Brutto/Abzug/Netto)
        $html .= "<tr><td align='left'><strong>Partner Endschiessen</strong></td><td></td><td></td></tr>";
        $html .= "<tr><td align='left'>&nbsp;&nbsp;â€“ Partner-Paket Kombi 2 Stiche</td><td align='center'>{$cntKombi2}</td><td align='right'>{$amtKombi2_str}</td></tr>";
        $html .= "<tr><td align='left'>&nbsp;&nbsp;â€“ Partner-Paket Kombi 3 Stiche</td><td align='center'>{$cntKombi3}</td><td align='right'>{$amtKombi3_str}</td></tr>";
        $html .= "<tr><td align='left'><em>Zwischentotal Partner</em></td><td align='center'>{$anzPartnerPakete}</td><td align='right'><em>{$amountPartnerPakete_brutto_str}</em></td></tr>";
        $html .= "<tr><td align='left'>abzüglich Schwini-Anteil Partner</td><td align='center'></td><td align='right'>- {$ga_schwini_anteil_str}</td></tr>";
        $html .= "<tr><td align='left'><strong>Total Partner Endschiessen</strong></td><td align='center'></td><td align='right'><strong>{$amountPartnerPakete_netto_str}</strong></td></tr>";

        /**
        // Gesamttotal
        $html .= "<tr><td colspan='3'></td></tr>";
        $html .= "<tr><td align='left' colspan='2'><strong>Gesamttotal</strong></td><td align='right'><strong>{$grand_total_str}</strong></td></tr>";
        */
        $html .= '</tbody></table>';

        return $html;
    }
}

/**
 * Partner Rangliste - Endstich + Beste Partner Schwini Passe
 * 
 * WICHTIG: Total = Endstich + BESTE Schwini-Passe (nicht Summe beider Passen!)
 * Bei Gleichstand: 1. Endstich, 2. Nicht gezählte Schwini-Passe
 */
class PartnerRankingReport extends PDFGenerator
{
    public function generate()
    {
        // Gäste ohne Geburtsdatum (Partnerinnen) und als Partner markierte Mitglieder mit Endstich und/oder Schwini
        // (Quelle zentral in endschPartnerQuelle(), Migration 094/096)
        $sql = "
            SELECT
                b.ID, b.PartnerName, b.Endstich_Summe, b.Schwini_Passe1, b.Schwini_Passe2,
                GREATEST(b.Schwini_Passe1, b.Schwini_Passe2) AS Schwini_Beste_Passe,
                LEAST(b.Schwini_Passe1, b.Schwini_Passe2) AS Schwini_Andere_Passe,
                b.Endstich_Summe + GREATEST(b.Schwini_Passe1, b.Schwini_Passe2) AS Total_Summe
            FROM (" . endschPartnerQuelle() . "
            ) b
            WHERE b.Endstich_Summe > 0 OR b.Schwini_Passe1 > 0 OR b.Schwini_Passe2 > 0
            ORDER BY Total_Summe DESC, b.Endstich_Summe DESC, Schwini_Andere_Passe DESC, b.PartnerName ASC
        ";

        $y = (int)$this->selectedYear;
        $data = $this->executePreparedQuery($sql, "ii", $y, $y);
        $html = $this->createHTMLHeader('Partner Rangliste ' . $this->selectedYear);
        $html .= '<h2>Partner Rangliste ' . $this->selectedYear . '</h2>';
        $html .= '<p><strong>Endstichresultat (10 Schuss) + Beste Partner Schwini Passe (6 Schuss)</strong></p>';
        $html .= '<p>Bei Punktgleichheit entscheidet: 1. Besseres Endstichresultat, 2. Höhere nicht gezählte Schwini-Passe</p>';
        $html .= $this->createPartnerRankingTable($data);
        $html .= $this->createHTMLFooter();

        $pdfPath = $this->generatePDF($html, 'RanglistePartner');
        $this->outputDownloadLink($pdfPath);
    }

    private function createPartnerRankingTable($data)
    {
        if (empty($data)) {
            return '<p>Keine Ergebnisse gefunden.</p>';
        }

        $html = '<table class="table">';
        $html .= '<thead><tr>
                    <th align="left">Rang</th>
                    <th align="left">Name</th>
                    <th align="center">Endstich</th>
                    <th align="center">Beste Schwini</th>
                    <th align="center">Total</th>
                  </tr></thead><tbody>';

        $rang = 1;
        foreach ($data as $row) {
            $bold = ($rang <= 3) ? 'class="bold"' : '';

            // Formatiere beste Schwini mit Hinweis auf andere Passe
            $schwini_display = number_format($row['Schwini_Beste_Passe'], 1);
            if ($row['Schwini_Andere_Passe'] > 0) {
                $schwini_display .= ' (' . number_format($row['Schwini_Andere_Passe'], 1) . ')';
            }

            $html .= "<tr>";
            $html .= "<td align=\"left\" $bold>$rang.</td>";
            $html .= "<td align=\"left\" $bold>{$row['PartnerName']}</td>";
            $html .= "<td align=\"center\" $bold>" . number_format($row['Endstich_Summe'], 1) . "</td>";
            $html .= "<td align=\"center\" $bold>$schwini_display</td>";
            $html .= "<td align=\"center\" $bold style=\"font-weight: bold;\">" . number_format($row['Total_Summe'], 1) . "</td>";
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

        // Erklärung hinzufügen
        $html .= '<div style="margin-top: 15px; padding: 10px; background-color: #f8f9fa; border-left: 4px solid #0d6efd; font-size: 9px;">';
        $html .= '<strong>Erläuterung:</strong><br/>';
        $html .= 'â€¢ <strong>Beste Schwini:</strong> Die höhere der beiden Passen wird gezählt<br/>';
        $html .= 'â€¢ Wert in Klammern (x.x) = Nicht gezählte Passe (wird bei Gleichstand berücksichtigt)<br/>';
        $html .= 'â€¢ <strong>Total</strong> = Endstich + Beste Schwini-Passe';
        $html .= '</div>';

        return $html;
    }
}

/**
 * SieEr Rangliste - Spezielle Berechnung mit einzigartigen Werten
 */
class SieErReport extends PDFGenerator
{
    public function generate()
    {
        // Paare aus endstich_sieunder (Migration 094): Mitglied+Gast oder zwei Mitglieder, Total = eindeutige Werte
        $y = (int)$this->selectedYear;
        $data = endschSieUndErPaare($this->conn, $y);
        $this->zeilenGelesen += count($data);

        // Nach Special Total sortieren (absteigend)
        usort($data, function ($a, $b) {
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

    private function createSieErTable($data)
    {
        if (empty($data)) {
            return '<p>Keine Ergebnisse gefunden.</p>';
        }

        $html = '<table class="table">';
        $html .= '<thead><tr>
                    <th style="width: 5%; text-align: left;">Rang</th>
                    <th style="width: 20%; text-align: left;">Name</th>
                    <th style="width: 15%; text-align: left;">Partner</th>
                    <th style="width: 50%; text-align: center;">Sie und Er Schüsse</th>
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
                    $allValues[] = (int) $row['SieErSchuss' . $i];
                }
            }

            // Mitglied Schüsse (6-10)
            for ($i = 6; $i <= 10; $i++) {
                if ($row['SieErSchuss' . $i] !== null && $row['SieErSchuss' . $i] > 0) {
                    $mitgliedShots[] = $row['SieErSchuss' . $i];
                    $allValues[] = (int) $row['SieErSchuss' . $i];
                }
            }

            // Berechne welche Werte unique sind
            $valueCount = array_count_values($allValues);
            $shownValues = [];

            // Erstelle Badge-HTML für Schüsse mit PDF-kompatiblem Inline-Style
            $shotsDisplay = '';

            // Partner Badges (rot)
            foreach ($partnerShots as $shot) {
                $intShot = (int) $shot;
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
                $intShot = (int) $shot;
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
        $html .= 'â€¢ <span style="background-color: #dc3545; color: white; padding: 1px 4px; border-radius: 2px;">Rot</span> = Schüsse der Partnerin bzw. des Partners<br/>';
        $html .= 'â€¢ <span style="background-color: #0d6efd; color: white; padding: 1px 4px; border-radius: 2px;">Blau</span> = Schüsse der erstgenannten Person<br/>';
        $html .= 'â€¢ <span style="text-decoration: line-through;">Durchgestrichen</span> = Duplikate (zählen nicht für Total)<br/>';
        $html .= 'â€¢ <strong>Total*</strong>: Jeder einzigartige Wert wird nur einmal gezählt<br/>';
        $html .= 'â€¢ Beispiel: Wenn 3x die "10" geschossen wurde, zählt sie nur 1x für das Total';
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('fmtNoTrailingZero')) {
    function fmtNoTrailingZero($v)
    {
        if ($v === null || $v === '')
            return '';
        if (!is_numeric($v)) {
            return preg_replace('/\.0+$/', '', (string) $v);
        }

        $f = (float) $v;
        // echte 0 -> "0" + Zero-Width Non-Joiner (unsichtbar, verhindert Wegputzen)
        if (abs($f) < 1e-12) {
            return "0" . "\u{200C}"; // U+200C ZWNJ
        }

        if (floor($f) == $f) {
            return (string) intval($f);
        }
        $s = sprintf('%.10f', $f);
        $s = rtrim(rtrim($s, '0'), '.');
        return $s !== '' ? $s : "0\u{200C}";
    }
}

?>
