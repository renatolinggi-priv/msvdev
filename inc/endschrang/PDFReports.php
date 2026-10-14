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
        $html = $this->ranglisteHeader('Endstich ' . $this->selectedYear, 'Endschiessen · Rangliste');
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
                    <th class="r">Rang</th>
                    <th>Name</th>
                    <th colspan="10">Passe</th>
                    <th class="r">Total</th>
                    <th class="r">TS</th>
                    <th></th>
                  </tr></thead><tbody>';

        $rang = 1;
        foreach ($data as $row) {
            $html .= $this->zeileAnfang($rang);
            $html .= "<td>{$row['name']}</td>";

            for ($i = 1; $i <= 10; $i++) {
                $value = isset($row['shot' . $i]) ? $row['shot' . $i] : '';
                if ($value !== '')
                    $value = (string) fmtNoTrailingZero($value);
                $html .= "<td class=\"r\">$value</td>";
            }

            $total = (string) fmtNoTrailingZero($row['total']);
            $ts = pdf_strich_wenn_null($row['ts']);

            $html .= "<td class=\"r\">$total</td>";
            $html .= "<td class=\"r\">$ts</td>";
            $html .= "<td>{$row['kk']}</td>";
            $html .= '</tr>';
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

        $html = $this->ranglisteHeader('Schwinistich ' . $this->selectedYear, 'Endschiessen · Rangliste');
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
                    <th class="r">Rang</th>
                    <th>Name</th>
                    <th colspan="6">Passe</th>
                    <th class="r">Total</th>
                    <th class="r">2. Passe</th>
                  </tr></thead><tbody>';

        $rang = 1;
        foreach ($data as $row) {
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

            $html .= $this->zeileAnfang($rang);
            $html .= "<td>{$row['Name']} {$row['Vorname']}</td>";

            foreach ($shots as $shot) {
                $html .= "<td class=\"r\">$shot</td>";
            }

            $html .= "<td class=\"r\">{$row['Hoechste_Summe']}</td>";
            $html .= '<td class="r">' . pdf_strich_wenn_null($row['Tiefste_Summe']) . '</td>';
            $html .= "</tr>";

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
        WHERE COALESCE(u.KSchuss1 + u.KSchuss2 + u.KSchuss3 + u.KSchuss4 + u.KSchuss5, 0) != 0
        ORDER BY Kunst_Summe DESC, TS DESC, u.Geburtsdatum ASC, u.Name ASC";

        $y = (int)$this->selectedYear;
        $data = $this->executePreparedQuery($sql, "i", $y);

        $html = $this->ranglisteHeader('Kunststich ' . $this->selectedYear, 'Endschiessen · Rangliste');
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
                    <th class="r">Rang</th>
                    <th>Name</th>
                    <th colspan="5">Passe</th>
                    <th class="r">Total</th>
                    <th></th>
                  </tr></thead><tbody>';

        $rang = 1;
        $wp = endschWpVergeben($data); // Wanderpreis nur für Mitglieder
        foreach ($data as $i => $row) {
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

            $html .= $this->zeileAnfang($rang);
            $html .= "<td>{$row['Name']} {$row['Vorname']}</td>";

            foreach ($shots as $shot) {
                $html .= "<td class=\"r\">$shot</td>";
            }
            // Leere Zellen auffüllen
            for ($j = count($shots); $j < 5; $j++) {
                $html .= '<td></td>';
            }

            $html .= "<td class=\"r\">{$row['Kunst_Summe']}</td>";
            $html .= "<td>$kk</td>";
            $html .= "</tr>";

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
        WHERE GREATEST(COALESCE(u.GSchuss1, 0), COALESCE(u.GSchuss2, 0), COALESCE(u.GSchuss3, 0)) != 0
        ORDER BY MaxGlueck DESC, ZweitHoechster DESC, DrittHoechster DESC, u.Geburtsdatum ASC, u.Name ASC";

        $y = (int)$this->selectedYear;
        $data = $this->executePreparedQuery($sql, "i", $y);

        $html = $this->ranglisteHeader('Glückstich ' . $this->selectedYear, 'Endschiessen · Rangliste');

        // Tabelle erstellen (wie im Absendenbuch: bester Schuss, dahinter die beiden anderen absteigend)
        if (!empty($data)) {
            $html .= '<table class="table">';
            $html .= '<thead><tr>
                        <th class="r">Rang</th>
                        <th>Name</th>
                        <th class="r">Bester Schuss</th>
                        <th>Weitere Schüsse</th>
                        <th></th>
                      </tr></thead><tbody>';

            $rang = 1;
            $wp = endschWpVergeben($data); // Wanderpreis nur für Mitglieder
            foreach ($data as $i => $row) {
                $kk = ($i === $wp) ? 'WP' : '';
                $weitere = $this->getSortedShots([$row['GSchuss1'], $row['GSchuss2'], $row['GSchuss3']]);
                array_shift($weitere);

                $html .= $this->zeileAnfang($rang);
                $html .= "<td>{$row['Name']} {$row['Vorname']}</td>";
                $html .= "<td class=\"r\">{$row['MaxGlueck']}</td>";
                $html .= '<td>' . ($weitere ? '(' . implode(', ', $weitere) . ')' : '') . '</td>';
                $html .= "<td>$kk</td>";
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

            // Hunderterwerte gleich sortieren: die Punkte sind monoton, so steht jeder Rohwert unter seinem Punkt
            $rawSortiert = $raw;
            rsort($rawSortiert, SORT_NUMERIC);

            // im Row-Array ablegen
            for ($i = 0; $i < 6; $i++) {
                $row['PShot' . ($i + 1)] = $points[$i];
                $row['RShot' . ($i + 1)] = $rawSortiert[$i];
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

        $html = $this->ranglisteHeader('Zabigstich ' . $this->selectedYear, 'Endschiessen · Rangliste');
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
                    <th class="r">Rang</th>
                    <th>Name</th>
                    <th colspan="6">Passe (Punkte)</th>
                    <th class="r">Total</th>
                    <th class="r">TS</th>
                  </tr></thead><tbody>';

        $rang = 1;
        foreach ($data as $row) {
            $html .= $this->zeileAnfang($rang);
            $html .= "<td>{$row['Name']} {$row['Vorname']}</td>";

            // 6 bereits umgerechnete Punkt-Schüsse; bei den ersten drei darunter die Hunderterwertung
            for ($i = 1; $i <= 6; $i++) {
                $p = isset($row['PShot' . $i]) ? $row['PShot' . $i] : '';
                if ($rang <= 3 && isset($row['RShot' . $i])) {
                    $r = (string) (function_exists('fmtNoTrailingZero') ? fmtNoTrailingZero($row['RShot' . $i]) : $row['RShot' . $i]);
                    $p .= '<br><span class="muted" style="font-size: 7pt; font-weight: normal;">' . $r . '</span>';
                }
                $html .= "<td class=\"r\">$p</td>";
            }

            $total = (string) (function_exists('fmtNoTrailingZero') ? fmtNoTrailingZero($row['Total']) : $row['Total']);

            $html .= "<td class=\"r\">$total</td>";
            $html .= '<td class="r">' . pdf_strich_wenn_null($row['TS']) . '</td>';
            $html .= "</tr>";
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
        WHERE COALESCE(u.ZSchuss1 + u.ZSchuss2 + u.ZSchuss3 + u.ZSchuss4 + u.ZSchuss5 + u.ZSchuss6, 0) != 0
        ORDER BY NormDiff ASC, u.Geburtsdatum ASC, u.Name ASC";

        $y = (int)$this->selectedYear;
        $data = $this->executePreparedQuery($sql, "i", $y);

        $html = $this->ranglisteHeader('Differenzler ' . $this->selectedYear, 'Endschiessen · Rangliste');
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
                    <th class="r">Rang</th>
                    <th>Name</th>
                    <th colspan="6">Passe</th>
                    <th class="r">Total</th>
                    <th class="r">Ansage</th>
                    <th class="r">Differenz</th>
                  </tr></thead><tbody>';

        $rang = 1;
        foreach ($data as $row) {
            $shots = $this->getSortedShots([
                $row['ZSchuss1'],
                $row['ZSchuss2'],
                $row['ZSchuss3'],
                $row['ZSchuss4'],
                $row['ZSchuss5'],
                $row['ZSchuss6']
            ]);

            $html .= $this->zeileAnfang($rang);
            $html .= "<td>{$row['Name']} {$row['Vorname']}</td>";

            foreach ($shots as $shot) {
                $html .= "<td class=\"r\">$shot</td>";
            }

            $html .= "<td class=\"r\">{$row['DiffTotal']}</td>";
            $html .= '<td class="r">' . pdf_strich_wenn_null($row['Ansage']) . '</td>';
            $html .= "<td class=\"r\">{$row['NormDiff']}</td>";
            $html .= "</tr>";

            $rang++;
        }

        $html .= '</tbody></table>';
        return $html;
    }
}

/**
 * Alle Einzelwettbewerbe in einem PDF (Endstich, Schwini, Kunst, Glück, Zabig, Differenzler)
 * Die Berichte laufen im Sammelmodus von PDFGenerator und liefern ihr HTML statt einer Datei; jede Rangliste
 * beginnt auf einer neuen Seite. Ranglisten ohne Resultate fallen weg.
 */
class EinzelwettbewerbeReport extends PDFGenerator
{
    public function generate()
    {
        PDFGenerator::$sammlung = [];
        try {
            foreach ([EndstichReport::class, SchwiniReport::class, KunstReport::class,
                      GlueckReport::class, ZabigReport::class, DifferenzlerReport::class] as $klasse) {
                (new $klasse($this->conn, $this->selectedYear))->generate();
            }
            $berichte = PDFGenerator::$sammlung;
        } finally {
            PDFGenerator::$sammlung = null;
        }

        // Kopf (Styles) und Fusszeile vom ersten Bericht, dazwischen der Inhalt jedes Berichts
        $kopf = $fuss = '';
        $teile = [];
        foreach ($berichte as $html) {
            $start = stripos($html, '<body>');
            $ende = strpos($html, '<div class="footer">');
            if ($start === false || $ende === false) {
                continue;
            }
            if (!$teile) {
                $kopf = substr($html, 0, $start + 6);
                $fuss = substr($html, $ende);
            }
            $teile[] = substr($html, $start + 6, $ende - $start - 6);
        }
        if (!$teile) {
            msvAusgabeLeer('Für ' . $this->selectedYear . ' gibt es noch keine Resultate in den Einzelwettbewerben.');
        }

        $kopf = preg_replace('~<title>.*?</title>~s', '<title>Einzelwettbewerbe ' . $this->selectedYear . '</title>', $kopf);
        $html = $kopf . implode('<div style="page-break-before: always;"></div>', $teile) . $fuss;

        $this->zeilenGelesen = count($teile);
        $this->ranglisteLayout = true; // Fusszeile mit Seitenzahl über alle Seiten
        $this->fussText = 'Einzelwettbewerbe ' . $this->selectedYear;
        $pdfPath = $this->generatePDF($html, 'Einzelwettbewerbe');
        $this->outputDownloadLink($pdfPath);
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
        $html = $this->ranglisteHeader('Partner ' . $this->selectedYear, 'Endschiessen · Rangliste');
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
                    <th class="r">Rang</th>
                    <th>Name</th>
                    <th class="r">Endstich</th>
                    <th class="r">Schwini</th>
                    <th class="r">2. Passe</th>
                    <th class="r">Total</th>
                  </tr></thead><tbody>';

        $rang = 1;
        foreach ($data as $row) {
            $html .= $this->zeileAnfang($rang);
            $html .= "<td>{$row['PartnerName']}</td>";
            $html .= '<td class="r">' . pdf_strich_wenn_null(fmtNoTrailingZero($row['Endstich_Summe'])) . '</td>';
            $html .= '<td class="r">' . pdf_strich_wenn_null(fmtNoTrailingZero($row['Schwini_Beste_Passe'])) . '</td>';
            $html .= '<td class="r">' . pdf_strich_wenn_null(fmtNoTrailingZero($row['Schwini_Andere_Passe'])) . '</td>';
            $html .= '<td class="r">' . fmtNoTrailingZero($row['Total_Summe']) . '</td>';
            $html .= "</tr>";

            $rang++;
        }

        $html .= '</tbody></table>';

        $html .= '<p class="rl-hinweis">Total = Endstich (10 Schuss) + bessere Schwini-Passe (6 Schuss). '
               . 'Bei Gleichstand entscheiden der Endstich, dann die 2. Schwini-Passe.</p>';

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

        $html = $this->ranglisteHeader('Sie und Er ' . $this->selectedYear, 'Endschiessen · Rangliste');
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
                    <th class="r">Rang</th>
                    <th>Name</th>
                    <th>Partner</th>
                    <th colspan="10">Schüsse</th>
                    <th class="r">Total</th>
                  </tr></thead><tbody>';

        $rang = 1;
        foreach ($data as $row) {
            // Schüsse der Partnerin bzw. des Partners (1–5) zuerst, dann die der erstgenannten Person (6–10).
            // Jeder Wert zählt nur einmal: Wiederholungen grau und durchgestrichen.
            $gezaehlt = [];
            $zellen = '';
            for ($i = 1; $i <= 10; $i++) {
                $v = $row['SieErSchuss' . $i];
                $trenner = $i === 6 ? ' style="padding-left: 4mm;"' : '';
                if ($v === null || $v <= 0) {
                    $zellen .= "<td class=\"r\"$trenner></td>";
                    continue;
                }
                $w = (int) $v;
                if (in_array($w, $gezaehlt, true)) {
                    $zellen .= "<td class=\"r\"$trenner><span class=\"muted\" style=\"text-decoration: line-through; font-weight: normal;\">$w</span></td>";
                } else {
                    $gezaehlt[] = $w;
                    $zellen .= "<td class=\"r\"$trenner>$w</td>";
                }
            }

            $html .= $this->zeileAnfang($rang);
            $html .= '<td>' . $row['Name'] . ' ' . $row['Vorname'] . '</td>';
            $html .= '<td>' . $row['PartnerName'] . '</td>';
            $html .= $zellen;
            $html .= '<td class="r">' . $row['SpecialTotal'] . '</td>';
            $html .= '</tr>';

            $rang++;
        }

        $html .= '</tbody></table>';
        $html .= '<p class="rl-hinweis">Jeder Wert zählt nur einmal: Wiederholungen sind durchgestrichen und zählen nicht zum Total. '
               . 'Links die Schüsse der Partnerin bzw. des Partners (sie zählen zuerst), rechts die der erstgenannten Person.</p>';

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
