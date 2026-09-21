<?php
/**
 * inc/helferabrechnung/ansicht_css.inc.php – rohes CSS der Ansicht «Abrechnung» (wird in einsatzplanung.php an
 * $page_specific_css angehängt; der Header wrappt es in <style>). Verwendung: $page_specific_css .= require __DIR__ . '/…';
 */
return <<<'CSS'
.ha-matrix td, .ha-matrix th { white-space: nowrap; }
.ha-matrix tr.ha-total td { font-weight: 700; border-top: 2px solid #cbd5e1; background: #f8fafc; }
.ha-matrix tr.ha-anteil td { color: #64748b; font-style: italic; }
.ha-kz { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .5rem; padding: .5rem .75rem; }
.ha-kz-val { font-size: 1.05rem; font-weight: 700; color: var(--secondary-color); }
.ha-kz-lbl { font-size: .72rem; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }
.ha-v { display: inline-block; padding: .05rem .45rem; border-radius: .6rem; font-size: .72rem; font-weight: 600; white-space: nowrap; }
.ha-v-msv { background: #fde2e2; color: #c62828; }
.ha-v-freienbach { background: #dbeafe; color: #1d4ed8; }
.ha-v-wollerau { background: #dcfce7; color: #15803d; }
#haDetail tr.ha-zero td { color: #94a3b8; }
.ha-warn { font-size: .85rem; }
.ha-switch .form-check-input { cursor: pointer; }
.ha-switch label { font-size: .82rem; cursor: pointer; }
#haAnsaetze input.ha-pauschale { text-align: right; }
.hybrid-table tbody tr.row-saved td { background: #e8f5e9 !important; transition: background .6s; }
.ha-kopf { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; }
.ha-kopf .export-group-btns { margin-left: auto; }
.content-background .ha-kopf, .content-background .ha-info, .content-background .nav-tabs { margin-bottom: .75rem; }
CSS;
