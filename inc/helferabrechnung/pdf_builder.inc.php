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

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Helferabrechnung</title><style>'
        . pdf_theme_css() . '
        @page { size: A4 ' . $orientation . '; margin: 1.2cm 1.5cm; }
        body { font-size: 10px; }
        .header { position: relative; min-height: 104px; margin: 0 0 6px 0; }   /* Logo ist hochformatig: Höhe reservieren */
        .header img.logo { position: absolute; top: 0; left: 0; width: 56px; max-width: 56px; height: auto; margin: 0; }   /* spezifischer als .header img im Theme */
        h1 { text-align: center; font-size: 17px; margin: 10px 0 2px 0; }
        .subtitle { text-align: center; font-size: 10px; }
        h2 { font-size: 12px; margin: 14px 0 6px 0; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .table th, .table td { padding: 3px 6px; font-size: 10px; }
        .table td:first-child { text-align: left; font-weight: normal; }
        .table td:last-child { font-weight: bold; }
        .table td.num, .table th.num { text-align: right; }
        .table tr.total td { font-weight: bold; border-top: 2px solid #64748b; }
        .table tr.anteil td { font-style: italic; color: #64748b; }
        .small { font-size: 8.5px; color: #64748b; }
        .hinweise { margin-top: 8px; font-size: 9px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; }
        </style></head><body>';
    $html .= '<div class="header"><img src="' . pdf_logo_src() . '" class="logo" alt="">'
        . '<h1>Helferstunden ' . $h($plan['titel']) . '</h1>'
        . '<div class="subtitle">Abrechnung der Helferstunden pro Verein · ' . ($ok ? 'OK-Einsätze mitgezählt' : 'ohne OK-Einsätze') . ' · Stand ' . date('d.m.Y') . '</div></div>';

    // Gesamtabrechnung pro Verein
    $html .= '<h2>Gesamtabrechnung pro Verein</h2><table class="table"><thead><tr><th>Position</th>';
    foreach ($vereine as $v) $html .= '<th class="num">' . $h(EP_VEREINE[$v]) . '</th>';
    $html .= '<th class="num">Total</th></tr></thead><tbody>';
    foreach (HA_ZEILEN as $key => $label) {
        $html .= '<tr><td>' . $h($label) . '</td>';
        foreach ($vereine as $v) $html .= '<td class="num">' . $h(ha_fmt($m['vereine'][$v][$key])) . '</td>';
        $html .= '<td class="num">' . $h(ha_fmt($m['gesamt'][$key])) . '</td></tr>';
    }
    $html .= '<tr class="total"><td>Total Helferstunden</td>';
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
    $html .= '<tr class="total"><td>Einsätze total</td>';
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
    $html .= '<div class="footer">MSV Wilen · Helferabrechnung · erstellt am ' . date('d.m.Y H:i') . '</div></body></html>';
    return $html;
}
