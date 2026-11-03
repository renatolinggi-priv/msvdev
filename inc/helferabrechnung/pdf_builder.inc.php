<?php
/**
 * inc/helferabrechnung/pdf_builder.inc.php – HTML des PDF «Helferabrechnung» (Abrechnungsblatt, Dompdf).
 * Reine Funktion ohne DB, damit sie lokal ohne Endpunkt testbar ist; export_pdf.php rendert das HTML.
 */
require_once __DIR__ . '/abrechnung.inc.php';
require_once __DIR__ . '/../pdf/pdf_theme.php';

/** @param array $a Bündel aus ha_abrechnung(); $orientation 'landscape'|'portrait' */
function ha_pdf_html(array $a, string $orientation = 'landscape'): string
{
    $plan = $a['plan']; $m = $a['matrix']; $k = $a['kennzahlen']; $ok = $a['ok'];
    $vereine = array_keys(EP_VEREINE);
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    // Ranglisten-Layout (Logo links, Titel, Fusszeile mit Seitenzahl: pdf_seitenfuss() in export_pdf.php)
    $css = '
        @page { size: A4 ' . $orientation . '; }
        .table th.num, .table td.num { text-align: right; }
        .table td:last-child { font-weight: bold; }
        .table tr.anteil td { color: #64748b; }
        .small { font-size: 7.5pt; color: #64748b; }
        .hinweise { margin-top: 3mm; font-size: 8.5pt; }
    ';
    $html = pdf_rangliste_anfang(
        'Helferstunden ' . (string)$plan['titel'],
        'Abrechnung pro Verein · ' . ($ok ? 'OK-Einsätze mitgezählt' : 'ohne OK-Einsätze'),
        $css
    );

    // Gesamtabrechnung pro Verein
    $html .= '<h2>Gesamtabrechnung pro Verein</h2><table class="table"><thead><tr><th>Position</th>';
    foreach ($vereine as $v) $html .= '<th class="num">' . $h(EP_VEREINE[$v]) . '</th>';
    $html .= '<th class="num">Total</th></tr></thead><tbody>';
    foreach (HA_ZEILEN as $key => $label) {
        $html .= '<tr><td>' . $h($label) . '</td>';
        foreach ($vereine as $v) $html .= '<td class="num">' . $h(ha_fmt($m['vereine'][$v][$key])) . '</td>';
        $html .= '<td class="num">' . $h(ha_fmt($m['gesamt'][$key])) . '</td></tr>';
    }
    $html .= '<tr class="rl-total"><td>Total Helferstunden</td>';
    foreach ($vereine as $v) $html .= '<td class="num">' . $h(ha_fmt($m['vereine'][$v]['total'])) . '</td>';
    $html .= '<td class="num">' . $h(ha_fmt($m['gesamt']['total'])) . '</td></tr>';
    $html .= '<tr class="anteil"><td>Anteil in %</td>';
    foreach ($vereine as $v) $html .= '<td class="num">' . $h(number_format($m['vereine'][$v]['anteil'] * 100, 1, '.', '')) . ' %</td>';
    $html .= '<td class="num">' . ($m['gesamt']['total'] > 0 ? '100.0 %' : '–') . '</td></tr></tbody></table>';

    // Stunden je Schicht × Verein
    $html .= '<h2>Helferstunden je Schicht und Verein</h2><table class="table"><thead><tr><th>Schicht</th>';
    foreach ($vereine as $v) $html .= '<th class="num">' . $h(EP_VEREINE[$v]) . '</th>';
    $html .= '<th class="num">Total</th></tr></thead><tbody>';
    foreach ($m['termine'] as $t) {
        $html .= '<tr><td>' . $h($t['label']) . '</td>';
        foreach ($vereine as $v) $html .= '<td class="num">' . $h(ha_fmt($t['vereine'][$v]['stunden'])) . ' <span class="small">(' . (int)$t['vereine'][$v]['positionen'] . ')</span></td>';
        $html .= '<td class="num">' . $h(ha_fmt($t['total']['stunden'])) . ' <span class="small">(' . (int)$t['total']['positionen'] . ')</span></td></tr>';
    }
    if ($m['nachtrag']) {
        $html .= '<tr><td>Nachträge (manuelle Zeilen)</td>';
        foreach ($vereine as $v) $html .= '<td class="num">' . $h(ha_fmt($m['nachtrag'][$v] ?? 0)) . '</td>';
        $html .= '<td class="num">' . $h(ha_fmt(array_sum($m['nachtrag']))) . '</td></tr>';
    }
    $html .= '<tr class="rl-total"><td>Einsätze total</td>';
    foreach ($vereine as $v) $html .= '<td class="num">' . $h(ha_fmt($m['vereine'][$v]['einsaetze'])) . ' <span class="small">(' . (int)$m['vereine'][$v]['positionen'] . ')</span></td>';
    $html .= '<td class="num">' . $h(ha_fmt($m['gesamt']['einsaetze'])) . ' <span class="small">(' . (int)$m['gesamt']['positionen'] . ')</span></td></tr></tbody></table>';
    $html .= '<div class="small">Stunden (Anzahl zählende Positionen). Pauschale je Schicht: '
        . $h(implode(' · ', array_map(fn($t) => ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t) . ' = ' . ha_fmt(ep_termin_stunden($t)) . ' h', $plan['termine'])))
        . '. Positionen mit Anwesenheit «nicht da»' . ($ok ? '' : ' und OK-Positionen') . ' zählen nicht.</div>';

    // Kennzahlen + Hinweise
    $html .= '<div class="hinweise"><b>Kennzahlen:</b> ' . (int)$k['positionen'] . ' besetzte Positionen, ' . (int)$k['zaehlend'] . ' zählend · '
        . (int)$k['ok_positionen'] . ' OK-Positionen (' . $h(ha_fmt($k['ok_stunden'])) . ' h) · ' . (int)$k['nicht_da'] . ' nicht da · '
        . (int)$k['korrekturen'] . ' Stundenkorrekturen · ' . (int)$k['manuell'] . ' manuelle Zeilen';
    foreach ($a['warnungen'] as $w) if ($w['typ'] !== 'status') $html .= '<br>Hinweis: ' . $h($w['text']);
    $html .= '</div>';
    $html .= '</body></html>';
    return $html;
}
