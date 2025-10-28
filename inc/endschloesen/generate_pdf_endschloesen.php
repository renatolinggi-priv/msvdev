<?php
// generate_pdf_endschloesen.php - PDF-Report für Endschiessen Stiche und Munition

require_once '../wanderpreise/PDFGenerator.php';

/**
 * Endschiessen Auswertungs-Report
 * Zeigt alle erfassten Stiche und bestellte Munition für ein Jahr
 */
class EndschloesenReport extends PDFGenerator
{

    public function __construct($conn, $year = null)
    {
        parent::__construct($conn, $year);
    }

    /**
 * Liest den aktiven JS-Paketpreis (in Cents) aus endstich_spezialpreise.
    */
    private function getJsPaketPreis(): ?int
    {
        $sql = "SELECT price_cents
                FROM endstich_spezialpreise
                WHERE typ='js_paket_preis' AND active=1
                ORDER BY id DESC
                LIMIT 1";
        if ($res = $this->conn->query($sql)) {
            if ($row = $res->fetch_assoc()) {
                return (int)$row['price_cents'];
            }
        }
        return null;
    }
    /**
     * Generiert den Endschiessen-Report
     */
    public function generate()
    {
        $title = 'Endschiessen Auswertung ' . $this->selectedYear;

        // Custom CSS für Querformat-Tabelle
        $customStyles = $this->getCustomStyles();

        // HTML Header (DOMPDF) – wir lassen die Schrift klein & schlicht
        $html = $this->createHTMLHeader($title, $customStyles, 10);
        $html .= '<h1>' . $title . '</h1>';
        //$html .= '<h1>MSV Wilen</h1>';

        // Hole Stich-Definitionen
        $stiche = $this->getStichDefinitionen();

        // Hole Daten für das Jahr
        $data = $this->getYearData();

        if (empty($data)) {
            $html .= '<p>Keine Daten für das Jahr ' . $this->selectedYear . ' erfasst.</p>';
        } else {
            // Erstelle die Haupttabelle
            $html .= $this->createMainTable($stiche, $data);

            // Legende
            $html .= '<div style="margin: 10px 0; font-size: 9pt;">';
            $html .= '<strong>Legende:</strong> ';
            $html .= '<span style="color: #28a745; font-weight: bold;">X</span> = Stich gelöst | ';
            $html .= '<span style="color: #007bff; font-weight: bold; background-color: #e7f3ff; padding: 2px 4px;">P</span> = Partner-Stich (Zabig mit Partner, CHF 10.00) | ';
            $html .= '<span style="color: #6c757d; font-weight: 600;">(Gast)</span> = Gast | ';
            $html .= '<span style="color: #007bff; font-weight: 600;">(JS)</span> = JungschützeIn';
            $html .= '</div>';

            // Zusammenfassung (links Stiche / rechts Munition)
            $html .= $this->createStatisticsBox($stiche, $data);
        }

        $html .= $this->createHTMLFooter(); // sorgt für Fusszeile auch auf Seite 1

        // PDF generieren im Querformat
        $filename = 'Endschiessen_Auswertung_' . $this->selectedYear;
        $pdfPath = $this->generatePDF($html, $filename, 'landscape');
        $this->outputDownloadLink($pdfPath);
    }

    /**
     * Holt alle aktiven Stich-Definitionen
     */
    private function getStichDefinitionen()
    {
        $sql = "SELECT id, code, name, shots, price_cents, sort_order 
                FROM endstich_definition 
                WHERE active = 1 
                ORDER BY sort_order, name";

        $result = $this->conn->query($sql);

        $stiche = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $stiche[] = $row;
            }
        }

        return $stiche;
    }

    /**
     * Holt alle Daten für das Jahr
     */
    private function getYearData()
    {
        $jahr = (int) $this->selectedYear;

        $jsPaketPreis = $this->getJsPaketPreis(); 
        // Mitglieder mit Waffe
        $sqlMit = "SELECT DISTINCT 
                    m.ID as mitglied_id,
                    CONCAT(m.Name, ' ', m.Vorname) as name,
                    'mitglied' as typ,
                    m.WaffenID as waffe_id,
                    w.Bezeichnung as waffe_bez,
                    w.Kategorie as waffe_kat
                FROM mitglieder m
                LEFT JOIN Waffen w ON w.ID = m.WaffenID
                WHERE m.ID IN (
                    SELECT DISTINCT mitglied_id FROM endstich_selection WHERE jahr = ? AND mitglied_id IS NOT NULL
                    UNION
                    SELECT DISTINCT mitglied_id FROM endstich_zusatz_schuss WHERE jahr = ? AND mitglied_id IS NOT NULL
                )
                ORDER BY m.Name, m.Vorname";

        $stmt = $this->conn->prepare($sqlMit);
        $stmt->bind_param("ii", $jahr, $jahr);
        $stmt->execute();
        $resMit = $stmt->get_result();

        $details = [];

        while ($row = $resMit->fetch_assoc()) {
            $mid = (int) $row['mitglied_id'];
            $details['m' . $mid] = [
                'typ' => 'mitglied',
                'entity_id' => $mid,
                'name' => $row['name'],
                'waffe_id' => isset($row['waffe_id']) ? (int) $row['waffe_id'] : null,
                'waffe_bez' => $row['waffe_bez'] ?? null,
                'waffe_kat' => $row['waffe_kat'] ?? null,
                'stiche' => [],
                'partner_stiche' => [],
                'zusatz_schuesse' => [],
                'total_shots' => 0,
                'total_price' => 0,
                'zahlungsmethode' => 'bar',
                'stich_gp11' => 0,
                'stich_gp90' => 0,
                'zusatz_gp11' => 0,
                'zusatz_gp90' => 0,
                'munition_schuss' => 0,
                'munition_preis' => 0
            ];
        }

        // Gäste mit Waffe und Geburtsdatum (für JS-Erkennung)
        $sqlGast = "SELECT DISTINCT 
                    g.id as gast_id,
                    g.name as name,
                    'gast' as typ,
                    g.geburtsdatum,
                    g.waffen_id as waffe_id,
                    w.Bezeichnung as waffe_bez,
                    w.Kategorie as waffe_kat
                FROM endstich_gaeste g
                LEFT JOIN Waffen w ON w.ID = g.waffen_id
                WHERE g.jahr = ? AND g.id IN (
                    SELECT DISTINCT gast_id FROM endstich_selection WHERE jahr = ? AND gast_id IS NOT NULL
                    UNION
                    SELECT DISTINCT gast_id FROM endstich_zusatz_schuss WHERE jahr = ? AND gast_id IS NOT NULL
                )
                ORDER BY g.name";

        $stmt = $this->conn->prepare($sqlGast);
        $stmt->bind_param("iii", $jahr, $jahr, $jahr);
        $stmt->execute();
        $resGast = $stmt->get_result();

        while ($row = $resGast->fetch_assoc()) {
            $gid = (int) $row['gast_id'];
            $details['g' . $gid] = [
                'typ' => 'gast',
                'entity_id' => $gid,
                'name' => $row['name'],
                'geburtsdatum' => $row['geburtsdatum'] ?? null,
                'waffe_id' => isset($row['waffe_id']) ? (int) $row['waffe_id'] : null,
                'waffe_bez' => $row['waffe_bez'] ?? null,
                'waffe_kat' => $row['waffe_kat'] ?? null,
                'stiche' => [],
                'partner_stiche' => [],
                'zusatz_schuesse' => [],
                'total_shots' => 0,
                'total_price' => 0,
                'zahlungsmethode' => 'bar',
                'stich_gp11' => 0,
                'stich_gp90' => 0,
                'zusatz_gp11' => 0,
                'zusatz_gp90' => 0,
                'munition_schuss' => 0,
                'munition_preis' => 0
            ];
        }

                // Hilfsfunktion Ammo aus Waffe ableiten
            // Hilfsfunktion Ammo aus Waffe ableiten
        $detectAmmo = function ($waffe_id, $waffe_bez, $waffe_kat) {
            // feste Map (IDs an eure DB anpassen)
            $map = [1 => 'GP11', 2 => 'GP90']; // <- ggf. um IDs erweitern
            if ($waffe_id && isset($map[(int) $waffe_id]))
                return $map[(int) $waffe_id];
            $str = trim(($waffe_kat ?? '') . ' ' . ($waffe_bez ?? ''));
            $lc = function_exists('mb_strtolower') ? mb_strtolower($str, 'UTF-8') : strtolower($str);
            // GP90-Erkennung (Stgw 90 etc.)
            if (preg_match('/\b(stgw|stg)\s*90\b|\bpe\s*90\b|\b(sg|sig)\s*550\b|\bgp\s*90\b|\b5\.56\b|\b\.223\b|\b223\b/', $lc))
                return 'GP90';
            // GP11-Erkennung (Stgw 57 / K31 / Karabiner 31 …)
            if (preg_match('/\b(stgw|stg)\s*57\b|\bk[\s-]?31\b|\bkarabiner\s*31\b|\bk[\s-]?11\b|\bg[\s-]?11\b|\bmousqueton\b|\bordonn?anz\b|\bgp\s*11\b|\b7[,\.\s]*5\s*x\s*55\b/', $lc))
                return 'GP11';
            // ★ Neu: „Karabiner“ ohne Zahl als GP11 werten
            if (preg_match('/\bkarabiner\b/', $lc))
                return 'GP11';
            // Schon drin: Standardgewehr → GP11
            if (preg_match('/\bstandardgewehr\b|\bstdg\b/i', $str))
                return 'GP11';
            return null;
        };


        // Stiche & Preise
        foreach ($details as $key => &$entry) {
            $isMitglied = ($entry['typ'] === 'mitglied');
            if ($isMitglied) {
                $stmt = $this->conn->prepare("SELECT es.stich_id, es.zahlungsmethode, es.sie_und_er,
                                                     ed.shots, ed.price_cents, ed.code
                                              FROM endstich_selection es
                                              JOIN endstich_definition ed ON es.stich_id = ed.id
                                              WHERE es.mitglied_id = ? AND es.jahr = ?");
                $stmt->bind_param("ii", $entry['entity_id'], $jahr);
            } else {
                $stmt = $this->conn->prepare("SELECT es.stich_id, es.zahlungsmethode, es.gast_spezialpreis,
                                                     ed.shots, ed.price_cents, ed.code
                                              FROM endstich_selection es
                                              JOIN endstich_definition ed ON es.stich_id = ed.id
                                              WHERE es.gast_id = ? AND es.jahr = ?");
                $stmt->bind_param("ii", $entry['entity_id'], $jahr);
            }
            $stmt->execute();
            $rs = $stmt->get_result();

            $ammoPref = $detectAmmo($entry['waffe_id'], $entry['waffe_bez'], $entry['waffe_kat']);
            $gastSpezialpreisGesetzt = false;

            while ($row = $rs->fetch_assoc()) {
                $code = strtoupper(trim((string)($row['code'] ?? '')));
                
                // PROBE nur für Nicht-JS ignorieren (für JS wird PROBE angezeigt)
                $isJS = !$isMitglied && !empty($entry['geburtsdatum']);
                if ($code === 'PROBE' && !$isJS) {
                    continue;
                }

                $entry['stiche'][] = (int) $row['stich_id'];
                $entry['total_shots'] += (int) $row['shots'];

                // Schüsse nach Ammo zuordnen
                if ($ammoPref === 'GP11')
                    $entry['stich_gp11'] += (int) $row['shots'];
                elseif ($ammoPref === 'GP90')
                    $entry['stich_gp90'] += (int) $row['shots'];

                // Preis
                if ($isMitglied) {
                    $preis = (int) $row['price_cents'];
                    if (($row['code'] ?? '') === 'ZABIG' && (int) ($row['sie_und_er'] ?? 0) === 1) {
                        $preis = 1000; // CHF 10.00
                        $entry['partner_stiche'][] = (int) $row['stich_id'];
                    }
                    $entry['total_price'] += $preis;
                } else {
                    // Gäste: zuerst prüfen, ob JS + Paket aktiv, dann evtl. Gast-Spezialpreis, sonst Einzelpreise
                    if ($isJS && $jsPaketPreis !== null && !$gastSpezialpreisGesetzt) {
                        // JS-Paket greift EINMALIG: Einzel-Stichpreise werden nicht mehr addiert
                        $entry['total_price'] = (int)$jsPaketPreis;
                        $gastSpezialpreisGesetzt = true; // Flag wiederverwenden, um Doppelbelegung zu vermeiden
                    } elseif (!$gastSpezialpreisGesetzt && !empty($row['gast_spezialpreis'])) {
                        // Fallback: vorhandener individueller Gast-Spezialpreis (einmalig)
                        $entry['total_price'] = (int)$row['gast_spezialpreis'];
                        $gastSpezialpreisGesetzt = true;
                    } elseif (!$gastSpezialpreisGesetzt) {
                        // Weder JS-Paket noch Gast-Spezialpreis → normal summieren
                        $entry['total_price'] += (int)$row['price_cents'];
                    }
                }

                if (!empty($row['zahlungsmethode']))
                    $entry['zahlungsmethode'] = $row['zahlungsmethode'];
            }

            // Zusatzschüsse
            if ($isMitglied) {
                $stmt = $this->conn->prepare("SELECT typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE mitglied_id = ? AND jahr = ?");
                $stmt->bind_param("ii", $entry['entity_id'], $jahr);
            } else {
                $stmt = $this->conn->prepare("SELECT typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                $stmt->bind_param("ii", $entry['entity_id'], $jahr);
            }
            $stmt->execute();
            $rs2 = $stmt->get_result();
            while ($row = $rs2->fetch_assoc()) {
                $entry['zusatz_schuesse'][] = [
                    'typ' => $row['typ'],
                    'anzahl' => (int) $row['anzahl'],
                    'preis_cents' => (int) $row['preis_cents']
                ];
                $entry['munition_schuss'] += (int) $row['anzahl'];
                $entry['munition_preis'] += (int) $row['preis_cents'];
                $entry['total_price'] += (int) $row['preis_cents'];

                // Split nach Typ
                $typNorm = strtoupper(str_replace(['-', '_', ' '], '', (string) $row['typ']));
                if (strpos($typNorm, 'GP11') !== false)
                    $entry['zusatz_gp11'] += (int) $row['anzahl'];
                elseif (strpos($typNorm, 'GP90') !== false)
                    $entry['zusatz_gp90'] += (int) $row['anzahl'];
            }
        }
        unset($entry);

        // in Array-Form zurückgeben (sortiert)
        $arr = array_values($details);
        usort($arr, function ($a, $b) {
            $ga = ($a['typ'] === 'mitglied') ? 1 : 2;
            $gb = ($b['typ'] === 'mitglied') ? 1 : 2;
            if ($ga !== $gb)
                return $ga - $gb;
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });
        return $arr;
    }

    /**
     * Erstellt die Haupttabelle
     */
    private function createMainTable($stiche, $data)
    {
        // Alle Stiche anzeigen, PROBE als erstes
        $sticheFiltered = $stiche;
        usort($sticheFiltered, function($a, $b) {
            $aCode = strtoupper((string)($a['code'] ?? ''));
            $bCode = strtoupper((string)($b['code'] ?? ''));
            
            // PROBE immer zuerst
            if ($aCode === 'PROBE') return -1;
            if ($bCode === 'PROBE') return 1;
            
            // Sonst nach sort_order
            return ((int)($a['sort_order'] ?? 9999)) - ((int)($b['sort_order'] ?? 9999));
        });

        // Map für Code pro ID (für P bei Zabig)
        $stichById = [];
        foreach ($stiche as $s) {
            $stichById[(int) $s['id']] = $s;
        }

        $html = '<table class="table main-table">';
        $html .= '<thead><tr>';
        $html .= '<th class="name-col">Mitglied</th>';

        foreach ($sticheFiltered as $stich) {
            $html .= '<th class="stich-col" title="' . htmlspecialchars($stich['name']) . '">';
            $html .= htmlspecialchars($this->shortenStichName($stich['name']));
            $html .= '</th>';
        }

        // Waffe + Munition + Total
        $html .= '<th class="waffe-col">Waffe</th>';
        $html .= '<th class="munition-col">GP11</th>';
        $html .= '<th class="munition-col">GP90</th>';
        $html .= '<th class="munition-col">Z.GP11</th>';
        $html .= '<th class="munition-col">Z.GP90</th>';
        $html .= '<th class="munition-col">Mun.Total</th>';
        $html .= '<th class="total-col">Z.Mun.CHF</th>';
        $html .= '<th class="total-col">Total&nbsp;CHF</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($data as $entry) {
            $html .= '<tr>';

            // Name mit Kennzeichnung (Gast) oder (JS)
            $displayName = htmlspecialchars($entry['name']);
            if ($entry['typ'] === 'gast') {
                if (!empty($entry['geburtsdatum'])) {
                    $displayName .= ' <span style="color:#007bff; font-weight:600;">(JS)</span>';
                } else {
                    $displayName .= ' <span style="color:#6c757d; font-weight:600;">(Gast)</span>';
                }
            }
            $html .= '<td class="name-cell" style="font-family: Arial, Helvetica, sans-serif; font-weight:700;">' . $displayName . '</td>';

            // Häkchen je Stich (mit P für Partner-Zabig)
            $stichIds = isset($entry['stiche']) && is_array($entry['stiche']) ? $entry['stiche'] : [];
            $partnerIds = isset($entry['partner_stiche']) && is_array($entry['partner_stiche']) ? $entry['partner_stiche'] : [];

            foreach ($sticheFiltered as $stich) {
                $sid = (int) $stich['id'];
                $has = in_array($sid, $stichIds, true);
                $isPartner = in_array($sid, $partnerIds, true) && strtoupper((string) ($stichById[$sid]['code'] ?? '')) === 'ZABIG';

                if ($has) {
                    $badge = $isPartner
                        ? '<span style="color:#007bff; font-weight:bold; background:#e7f3ff; padding:2px 4px;">P</span>'
                        : '<span style="color:#28a745; font-weight:bold;">X</span>';
                    $html .= '<td class="stich-cell" style="text-align:center;">' . $badge . '</td>';
                } else {
                    $html .= '<td class="stich-cell" style="text-align:center;"><span style="color:#bbb;">–</span></td>';
                }
            }

            // Waffe
            // NEU: nur die Bezeichnung, ohne Kategorie
            $waffeTxt = trim((string) ($entry['waffe_bez'] ?? '')) !== '' ? $entry['waffe_bez'] : '-';
            $html .= '<td class="waffe-cell">' . htmlspecialchars($waffeTxt) . '</td>';


            // Munition: aufgeteilte Werte
            $stich_gp11 = (int) ($entry['stich_gp11'] ?? 0);
            $stich_gp90 = (int) ($entry['stich_gp90'] ?? 0);
            $zusatz_gp11 = (int) ($entry['zusatz_gp11'] ?? 0);
            $zusatz_gp90 = (int) ($entry['zusatz_gp90'] ?? 0);
            $mun_preis_cents = (int) ($entry['munition_preis'] ?? 0);

            $html .= '<td class="munition-cell" style="text-align:center;">' . ($stich_gp11 > 0 ? $stich_gp11 : '–') . '</td>';
            $html .= '<td class="munition-cell" style="text-align:center;">' . ($stich_gp90 > 0 ? $stich_gp90 : '–') . '</td>';
            $html .= '<td class="munition-cell" style="text-align:center;">' . ($zusatz_gp11 > 0 ? $zusatz_gp11 : '–') . '</td>';
            $html .= '<td class="munition-cell" style="text-align:center;">' . ($zusatz_gp90 > 0 ? $zusatz_gp90 : '–') . '</td>';
            
            // Gesamt-Munition
            $total_munition = $stich_gp11 + $stich_gp90 + $zusatz_gp11 + $zusatz_gp90;
            $html .= '<td class="munition-cell" style="text-align:center; font-weight:700; background:#f0f8ff;">' . ($total_munition > 0 ? $total_munition : '–') . '</td>';
            
            $html .= '<td class="total-cell">' . number_format($mun_preis_cents / 100, 2, '.', '') . '</td>';

            // Total
            $html .= '<td class="total-cell">' . number_format(((int) ($entry['total_price'] ?? 0)) / 100, 2, '.', '') . '</td>';

            $html .= '</tr>';
        }

        // Berechne Totale für die Summenzeile
        $total_stich_gp11 = 0;
        $total_stich_gp90 = 0;
        $total_zusatz_gp11 = 0;
        $total_zusatz_gp90 = 0;
        $total_munition_gesamt = 0;
        $total_munition_preis = 0;
        $total_gesamt_preis = 0;
        
        foreach ($data as $entry) {
            $total_stich_gp11 += (int) ($entry['stich_gp11'] ?? 0);
            $total_stich_gp90 += (int) ($entry['stich_gp90'] ?? 0);
            $total_zusatz_gp11 += (int) ($entry['zusatz_gp11'] ?? 0);
            $total_zusatz_gp90 += (int) ($entry['zusatz_gp90'] ?? 0);
            $total_munition_preis += (int) ($entry['munition_preis'] ?? 0);
            $total_gesamt_preis += (int) ($entry['total_price'] ?? 0);
        }
        $total_munition_gesamt = $total_stich_gp11 + $total_stich_gp90 + $total_zusatz_gp11 + $total_zusatz_gp90;
        
        // Total-Zeile
        $html .= '<tr style="background:#e9ecef; font-weight:700; border-top: 2px solid #333;">';
        $html .= '<td class="name-cell" colspan="' . (count($sticheFiltered) + 2) . '" style="text-align:right; padding-right:10px;"><strong>TOTAL</strong></td>';
        $html .= '<td class="munition-cell" style="text-align:center;"><strong>' . $total_stich_gp11 . '</strong></td>';
        $html .= '<td class="munition-cell" style="text-align:center;"><strong>' . $total_stich_gp90 . '</strong></td>';
        $html .= '<td class="munition-cell" style="text-align:center;"><strong>' . $total_zusatz_gp11 . '</strong></td>';
        $html .= '<td class="munition-cell" style="text-align:center;"><strong>' . $total_zusatz_gp90 . '</strong></td>';
        $html .= '<td class="munition-cell" style="text-align:center; background:#d4e8ff;"><strong>' . $total_munition_gesamt . '</strong></td>';
        $html .= '<td class="total-cell"><strong>' . number_format($total_munition_preis / 100, 2, '.', '') . '</strong></td>';
        $html .= '<td class="total-cell"><strong>' . number_format($total_gesamt_preis / 100, 2, '.', '') . '</strong></td>';
        $html .= '</tr>';

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Erstellt die Zusammenfassung (links Stiche, rechts Munition)
     */
    private function createStatisticsBox($stiche, $data)
    {
        // Zähle Teilnehmer
        $countMitglieder = 0;
        $countGaeste = 0;
        $countJS = 0;
        
        foreach ($data as $row) {
            if ($row['typ'] === 'mitglied') {
                $countMitglieder++;
            } elseif ($row['typ'] === 'gast') {
                if (!empty($row['geburtsdatum'])) {
                    $countJS++;
                } else {
                    $countGaeste++;
                }
            }
        }
        $countTotal = $countMitglieder + $countGaeste + $countJS;
        
        // Teilnehmer-Box (oben, volle Breite)
        /*
        $teilnehmerBox = '<div style="border: 2px solid #1f3b63; padding: 12px; margin-bottom: 20px; background: #f8f9fa;">';
        $teilnehmerBox .= '<div class="h3" style="color: #1f3b63; margin: 0 0 10px 0;">Teilnehmer-Übersicht</div>';
        $teilnehmerBox .= '<table style="width: 40%; border-collapse: collapse;">';
        $teilnehmerBox .= '<tr><td style="padding: 4px 8px; border-bottom: 1px solid #dee2e6;"><strong>Mitglieder:</strong></td><td style="padding: 4px 8px; text-align: right; border-bottom: 1px solid #dee2e6;">' . $countMitglieder . '</td></tr>';
        $teilnehmerBox .= '<tr><td style="padding: 4px 8px; border-bottom: 1px solid #dee2e6;"><strong>Gäste:</strong></td><td style="padding: 4px 8px; text-align: right; border-bottom: 1px solid #dee2e6;">' . $countGaeste . '</td></tr>';
        $teilnehmerBox .= '<tr><td style="padding: 4px 8px; border-bottom: 1px solid #dee2e6;"><strong>JungschützenInnen:</strong></td><td style="padding: 4px 8px; text-align: right; border-bottom: 1px solid #dee2e6;"><span style="color: #007bff; font-weight: 600;">' . $countJS . '</span></td></tr>';
        $teilnehmerBox .= '<tr style="background: #e9ecef;"><td style="padding: 6px 8px;"><strong>Total Teilnehmer:</strong></td><td style="padding: 6px 8px; text-align: right;"><strong>' . $countTotal . '</strong></td></tr>';
        $teilnehmerBox .= '</table>';
        $teilnehmerBox .= '</div>';
        */
        // Aggregation (alle Stiche inkl. PROBE)
        $agg = [];
        foreach ($stiche as $s) {
            $agg[(int) $s['id']] = [
                'name' => $s['name'],
                'count' => 0,
                'price' => (int) ($s['price_cents'] ?? 0),
                'order' => (int) ($s['sort_order'] ?? 9999),
            ];
        }

        $partnerCount = 0;
        $totalEinnahmenCents = 0;

        foreach ($data as $row) {
            $totalEinnahmenCents += (int) ($row['total_price'] ?? 0);
            if (!empty($row['stiche'])) {
                foreach ($row['stiche'] as $sid) {
                    $sid = (int) $sid;
                    if (!isset($agg[$sid]))
                        continue; // unbekannter Stich
                    $isPartner = isset($row['partner_stiche']) && in_array($sid, $row['partner_stiche'], true);
                    if ($isPartner)
                        $partnerCount++;
                    else
                        $agg[$sid]['count']++;
                }
            }
        }

        uasort($agg, function ($a, $b) {
            if ($a['order'] === $b['order'])
                return strcasecmp($a['name'], $b['name']);
            return $a['order'] <=> $b['order'];
        });

        // Munition totals
        $stich_gp11 = $stich_gp90 = $zusatz_gp11 = $zusatz_gp90 = 0;
        foreach ($data as $r) {
            $stich_gp11 += (int) ($r['stich_gp11'] ?? 0);
            $stich_gp90 += (int) ($r['stich_gp90'] ?? 0);
            $zusatz_gp11 += (int) ($r['zusatz_gp11'] ?? 0);
            $zusatz_gp90 += (int) ($r['zusatz_gp90'] ?? 0);
        }
        $gesamtSchuss = $stich_gp11 + $stich_gp90 + $zusatz_gp11 + $zusatz_gp90;
 /*
        // ---- linke Spalte: NUR Stiche (ohne die zwei Kennzahlen oben)
        $left = '<div class="h3" style="margin:0 0 6px 0;">Stiche</div>';
        $left .= '<table class="stat-table"><thead><tr>';
        $left .= '<th>Stich</th><th style="width:80px; text-align:right;">Anzahl</th><th style="width:120px; text-align:right;">Einnahmen&nbsp;CHF</th>';
        $left .= '</tr></thead><tbody>';

        $sumEinnahmen = 0;
        foreach ($agg as $row) {
            $anz = (int) $row['count'];
            $ein = $anz * (int) $row['price'];
            $sumEinnahmen += $ein;

            $left .= '<tr>';
            $left .= '<td>' . htmlspecialchars($row['name']) . '</td>';
            $left .= '<td style="text-align:right;">' . number_format($anz, 0, ',', '\'') . '</td>';
            $left .= '<td style="text-align:right;">' . number_format($ein / 100, 2, '.', '\'') . '</td>';
            $left .= '</tr>';
        }
        if ($partnerCount > 0) {
            $ein = $partnerCount * 1000; // CHF 10.00 pro Partner-Zabig
            $sumEinnahmen += $ein;
            $left .= '<tr>';
            $left .= '<td>Zabig <span style="color:#007bff;">(Partner)</span></td>';
            $left .= '<td style="text-align:right;">' . number_format($partnerCount, 0, ',', '\'') . '</td>';
            $left .= '<td style="text-align:right;">' . number_format($ein / 100, 2, '.', '\'') . '</td>';
            $left .= '</tr>';
        }
        $left .= '</tbody><tfoot><tr>';
        $left .= '<td style="text-align:right;"><strong>Total</strong></td>';
        $left .= '<td></td>';
        $left .= '<td style="text-align:right;"><strong>' . number_format($sumEinnahmen / 100, 2, ".", "\'") . '</strong></td>';
        $left .= '</tr></tfoot></table>';
*/
        // ---- rechte Spalte: Munition kompakt (Tabelle)
        $ammo = '<div class="h3" style="margin:0 0 6px 0;">Munition – Zusammenfassung</div>';
        $ammo .= '<table class="ammo-table"><thead><tr>';
        $ammo .= '<th></th><th>GP11</th><th>GP90</th>';
        $ammo .= '</tr></thead><tbody>';
        $ammo .= '<tr><td>Endschiessen</td><td>' . number_format($stich_gp11, 0, ',', '\'') . '</td><td>' . number_format($stich_gp90, 0, ',', '\'') . '</td></tr>';
        $ammo .= '<tr><td>Zusatzverkauf</td><td>' . number_format($zusatz_gp11, 0, ',', '\'') . '</td><td>' . number_format($zusatz_gp90, 0, ',', '\'') . '</td></tr>';
        $ammo .= '<tr class="total"><td>Total</td><td><strong>' . number_format($stich_gp11 + $zusatz_gp11, 0, ',', '\'') . '</strong></td><td><strong>' . number_format($stich_gp90 + $zusatz_gp90, 0, ',', '\'') . '</strong></td></tr>';
        $ammo .= '<tr style="border-top: 2px solid #ddd;"><td colspan="3" style="text-align:center; padding:8px; font-weight:700; background:#f0f8ff;">Gesamt-Schuss: ' . number_format($gesamtSchuss, 0, ',', '\'') . '</td></tr>';
        $ammo .= '</tbody></table>';

        // Nebeneinander & top aligned
       // $html = $teilnehmerBox; // Teilnehmer-Box zuerst
        $html = '<table class="two-col"><tr>';
        //$html .= '<td class="two-col-left" valign="top">' . $left . '</td>';
        $html .= '<td class="two-col-right" valign="top">' . $ammo . '</td>';
        $html .= '</tr></table>';

        return $html;
    }



    /**
     * Kürzt lange Stich-Namen für die Tabellen-Header
     */
    private function shortenStichName($name)
    {
        $n = trim((string) $name);
        $lower = function_exists('mb_strtolower') ? mb_strtolower($n, 'UTF-8') : strtolower($n);

        // Spezielle Kürzungen / Umbenennungen
        $map = [
            'probeschüsse' => "P",
            'probe' => "P",
            'schwini p. 1' => "Schwini 1",
            'schwini p. 2' => "Schwini 2",
            'schwini passe 1' => "Schwini 1",
            'schwini passe 2' => "Schwini 2",
            'schwini 1' => "Schwini 1",
            'schwini 2' => "Schwini 2",
            'differenzler' => "Diff.",
            'sie und er' => "Sie+Er",
        ];
        if (isset($map[$lower]))
            return $map[$lower];

        // Generisch: "Schwini … <Zahl>" -> "Schwini <Zahl>" (NBSP)
        if (preg_match('/^schwini.*?(\d)$/u', $lower, $m)) {
            return "Schwini {$m[1]}"; // NBSP verhindert Umbruch
        }

        // Fallback: Spaces -> NBSP + sanft kürzen
        $label = preg_replace('/\s+/', ' ', $n);
        $label = str_replace(' ', " ", $label); // U+00A0 NBSP
        if (function_exists('mb_strlen') && mb_strlen($label, 'UTF-8') > 12) {
            $label = mb_substr($label, 0, 11, 'UTF-8') . '…';
        } elseif (strlen($label) > 12) {
            $label = substr($label, 0, 11) . '…';
        }
        return $label;
    }


    private function createAmmoSummary($data)
    {
        $stich_gp11 = $stich_gp90 = $zusatz_gp11 = $zusatz_gp90 = 0;

        foreach ($data as $row) {
            $stich_gp11 += (int) ($row['stich_gp11'] ?? 0);
            $stich_gp90 += (int) ($row['stich_gp90'] ?? 0);
            $zusatz_gp11 += (int) ($row['zusatz_gp11'] ?? 0);
            $zusatz_gp90 += (int) ($row['zusatz_gp90'] ?? 0);
        }

        $total_gp11 = $stich_gp11 + $zusatz_gp11;
        $total_gp90 = $stich_gp90 + $zusatz_gp90;

        $fmt = function ($n) {
            return number_format((int) $n, 0, ',', '\''); };

        $html = '<div class="ammo-summary">';
        $html .= '<div class="h4">Munition – Zusammenfassung</div>';

        // GP11
        $html .= '<div><strong>GP11</strong></div>';
        $html .= '<div>Endschiessen GP11: ' . $fmt($stich_gp11) . '</div>';
        $html .= '<div>Zusatzverkauf GP11: ' . $fmt($zusatz_gp11) . '</div>';
        $html .= '<div><strong>Total GP11: ' . $fmt($total_gp11) . '</strong></div>';

        $html .= '<div style="height:8px;"></div>';

        // GP90
        $html .= '<div><strong>GP90</strong></div>';
        $html .= '<div>Endschiessen GP90: ' . $fmt($stich_gp90) . '</div>';
        $html .= '<div>Zusatzverkauf GP90: ' . $fmt($zusatz_gp90) . '</div>';
        $html .= '<div><strong>Total GP90: ' . $fmt($total_gp90) . '</strong></div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Gibt das benutzerdefinierte CSS für den Report zurück
     */
    private function getCustomStyles()
{
    return '
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; }
h1, h2, h3, h4, h5, h6,
table, thead, tbody, tfoot, tr, th, td,
p, li, span, div {
  font-family: Arial, Helvetica, sans-serif !important;
}
        /* Seitentitel (Elemente, nicht Klassen!) */
        h1 { 
            color:#1f3b63; 
            font-size:19pt; 
            font-weight:700; 
            letter-spacing:.2px; 
            margin:10px 0 2px; 
            text-align:center; 
        }
        h2 { 
            color:#5c6b80; 
            font-size:11pt; 
            font-weight:600; 
            letter-spacing:.2px; 
            margin:0 0 16px; 
            text-align:center; 
        }

        .h3 { font-size:12pt; font-weight:700; margin:8px 0 6px; }
        .h4 { font-size:10pt; font-weight:700; margin:6px 0 6px; }

        /* Haupttabelle – Header modern, einmalig definiert */
        .main-table { width:100%; border-collapse:collapse; margin:16px 0; font-size:8pt; }
        .main-table th {
            background:#f3f6fb;
            color:#2a3340;
            padding:6px 4px;
            text-align:center;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.35px;
            font-size:7.6pt;
            border:1px solid #e1e7ef;
            border-bottom:2px solid #d4deea;
        }
        .main-table td { padding:3px; border:1px solid #eaeaea; }

        .name-col  { width:20%; text-align:left !important; }
        .name-cell { text-align:left; font-weight:600; padding-left:5px !important; }

        /* Stich-Header: nie umbrechen + kompakt */
        .stich-col { width:6%; font-size:7pt; white-space:nowrap; }

        .waffe-col   { width:12%; }
        .munition-col{ width:5%; }
        .total-col   { width:8%; }
        .total-cell  { text-align:right; padding-right:5px !important; font-weight:700; background:#fff7e6; }

        /* Zwei Spalten für Zusammenfassung – final 58 / 42 */
        .two-col { width:100%; border-collapse:separate; border-spacing:0; }
        .two-col td { vertical-align:top; padding-top:0; }
        .two-col-left  { width:58%; padding-right:10px; }
        .two-col-right { width:42%; padding-left:10px; }

        /* Stiche- und Munitionstabellen */
        .stat-table, .ammo-table { border-collapse:collapse; margin:0 0 10px 0; }
        .stat-table  { width:85%; } /* schmaler als 100% */
        .ammo-table  { width:100%; }

        .stat-table th, .ammo-table th { background:#f6f8fb; color:#333; padding:5px; text-align:left; border:1px solid #e6e6e6; }
        .stat-table td, .ammo-table td { padding:5px; border:1px solid #eeeeee; }
        .stat-table tfoot td { border-top:2px solid #ddd; font-weight:700; }

        .ammo-table th:nth-child(2), .ammo-table th:nth-child(3),
        .ammo-table td:nth-child(2), .ammo-table td:nth-child(3) { text-align:right; }
        .ammo-table tr.total td { font-weight:700; border-top:2px solid #ddd; }

        .pagebreak { page-break-before: always; }

        /* Erzwinge Arial (Fallback: Helvetica) für alle Tabelleninhalte */
        .main-table, .main-table td, .main-table th,
        .stat-table, .stat-table td, .stat-table th,
        .ammo-table, .ammo-table td, .ammo-table th,
        .name-cell {
          font-family: Arial, Helvetica, sans-serif !important;
        }
    ';
}


}

// Handler für AJAX-Request
if (isset($_GET['action']) && $_GET['action'] === 'generate_pdf') {
    try {
        // Database connection
        require_once '../dbconnect.inc.php';

        if (!isset($conn)) {
            throw new Exception('Datenbankverbindung nicht verfügbar');
        }

        $year = isset($_GET['jahr']) ? (int) $_GET['jahr'] : date('Y');

        $report = new EndschloesenReport($conn, $year);
        $report->generate();

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>