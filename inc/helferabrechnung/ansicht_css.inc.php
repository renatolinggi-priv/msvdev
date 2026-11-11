<?php
/**
 * inc/helferabrechnung/ansicht_css.inc.php – rohes CSS der Ansicht «Abrechnung» (wird in einsatzplanung.php an
 * $page_specific_css angehängt; der Header wrappt es in <style>). Verwendung: $page_specific_css .= require __DIR__ . '/…';
 * Farben nur über die --ui-* Tokens aus css/msv-ui.css (seit 1.68.56; vorher fest codierte Hex-Werte).
 * Vereinsfarben wie im Raster der Einsatzplanung: MSV Wilen rot, SV Freienbach blau, SV Wollerau grün.
 */
return <<<'CSS'
.ha-matrix td, .ha-matrix th { white-space: nowrap; }
.ha-matrix tr.ha-total td { font-weight: 700; border-top: 2px solid var(--ui-rand); background: var(--ui-flaeche-2); }
.ha-matrix tr.ha-anteil td { color: var(--ui-text-2); font-style: italic; }
.ha-kz { background: var(--ui-flaeche-2); border: 1px solid var(--ui-rand); border-radius: 8px; padding: .5rem .75rem; }
.ha-kz-val { font-size: 1.35rem; line-height: 1.1; font-weight: 700; color: var(--ui-text); }
.ha-kz-lbl { font-size: .72rem; color: var(--ui-text-2); text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
.ha-v { display: inline-block; padding: .05rem .45rem; border-radius: 6px; font-size: .72rem; font-weight: 600; white-space: nowrap; border: 1px solid transparent; }
.ha-v-msv { background: var(--ui-fehler-bg); border-color: var(--ui-fehler-rand); color: var(--ui-k-rot); }
.ha-v-freienbach { background: var(--ui-akzent-hell); border-color: var(--ui-akzent-rand); color: var(--ui-akzent-dunkel); }
.ha-v-wollerau { background: var(--ui-ok-bg); border-color: var(--ui-ok-rand); color: var(--ui-ok-fg); }
#haDetail tr.ha-zero td { color: var(--ui-text-3); }
.ha-warn { font-size: .85rem; }
.ha-switch .form-check-input { cursor: pointer; }
.ha-switch label { font-size: .8rem; cursor: pointer; }
#haAnsaetze input.ha-pauschale { text-align: right; }
.hybrid-table tbody tr.row-saved td { background: var(--ui-ok-bg) !important; transition: background .6s; }
.ha-kopf { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; }
.ha-kopf .export-group-btns { margin-left: auto; }
.content-background .ha-kopf, .content-background .ha-info, .content-background .nav-tabs { margin-bottom: .75rem; }
/* msv-styles.css und css/fixes/resultate-unified.css (dort !important) geben jeder .table-responsive 300px Mindesthöhe
   für scrollende Listen – hier sind die Tabellen kurz, sonst klafft unter jeder Tabelle eine Lücke */
.content-background .tab-content .table-responsive { min-height: 0 !important; }
.nav-tabs .ui-chip { padding: 0 7px; font-size: .75rem; }
.ui-chip.ha-chip-ok { background: var(--ui-warn-bg); color: var(--ui-warn-fg); }
CSS;
