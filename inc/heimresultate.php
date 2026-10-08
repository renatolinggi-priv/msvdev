<?php
// heimresultate.php – Raster pro Mitglied (8 Passen) und Schnellerfassung im Slide-Panel
include 'dbconnect.inc.php';

// Seitenspezifische Styles: nur Aufbau dieser Seite; die Optik kommt aus css/msv-ui.css
$page_specific_css = "
/* Kopf-Card + Tabellen-Card füllen das Fenster, das Raster scrollt innen */
.hk-seite { display: flex; flex-direction: column; height: calc(100vh - var(--nav-h, 76px) - 28px); min-height: 520px; margin-bottom: 0 !important; }
.hk-seite > .msv-kopf { flex-shrink: 0; }
.hk-tabelle { flex: 1 1 auto; min-height: 0 !important; display: flex; flex-direction: column; overflow: hidden; }
.hk-desktop { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
.hk-scroll { flex: 1 1 auto; min-height: 0; overflow: auto; scroll-padding-top: 40px; }

/* Raster; die #hkScroll-Präfixe schlagen die !important-Regeln aus css/fixes/resultate-unified.css */
#hkScroll #heimresultateTabelle { margin: 0; width: 100%; min-width: 880px; table-layout: fixed; border-collapse: separate; border-spacing: 0; }
#hkScroll #heimresultateTabelle thead th { position: sticky; top: 0 !important; z-index: 3; padding: 8px 6px; text-align: center; white-space: nowrap; background: var(--ui-flaeche-2) !important; border-bottom: 1px solid var(--ui-linie); }
#hkScroll #heimresultateTabelle th:not(:first-child),
#hkScroll #heimresultateTabelle td:not(:first-child) { width: 72px !important; min-width: 72px !important; max-width: 72px !important; }
#hkScroll #heimresultateTabelle th:first-child,
#hkScroll #heimresultateTabelle td:first-child { width: auto !important; min-width: 220px !important; max-width: none !important; padding-left: 20px; text-align: left !important; }
#hkScroll #heimresultateTabelle th:last-child,
#hkScroll #heimresultateTabelle td:last-child { width: 84px !important; min-width: 84px !important; max-width: 84px !important; }
#hkScroll #heimresultateTabelle tbody td { height: 44px; padding-top: 0; padding-bottom: 0; vertical-align: middle; text-align: center; border-bottom: 1px solid var(--ui-linie-zart); }
#hkScroll #heimresultateTabelle tbody td:first-child { cursor: pointer; }
#hkScroll #heimresultateTabelle tbody tr:not(.group-header):not(.ui-leer):hover > td { background: var(--ui-flaeche-2); }
.hk-zelle { display: flex; align-items: center; gap: 8px; min-width: 0; }
.hk-name { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; color: var(--ui-text); }
.hk-anzahl { flex: none; font-size: .75rem; color: var(--ui-text-3); font-variant-numeric: tabular-nums; }
.hk-anzahl.voll { color: var(--ui-ok-fg); font-weight: 600; }
tr.hk-geaendert .hk-name::after { content: ''; display: inline-block; width: 7px; height: 7px; margin-left: 7px; border-radius: 50%; background: var(--ui-akzent); vertical-align: middle; }
.hk-chip-ungespeichert .ui-punkt { background: var(--ui-akzent) !important; }
#hkScroll #heimresultateTabelle input.small-input { width: 52px !important; height: 32px !important; margin: 0 auto; padding: 0 4px !important; font-size: .9rem !important; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--ui-text); background: var(--ui-flaeche); border: 1px solid #c5ccd6 !important; border-radius: 6px !important; box-shadow: none; }
#hkScroll #heimresultateTabelle input.small-input:not(.filled) { background: var(--ui-flaeche-2); border-color: #dde3ea !important; font-weight: 400; color: var(--ui-text-2); }
#hkScroll #heimresultateTabelle input.small-input:focus { background: var(--ui-gewaehlt); border-color: var(--ui-akzent-dunkel) !important; box-shadow: 0 0 0 1px var(--ui-akzent-dunkel) !important; outline: 0; }
#hkScroll #heimresultateTabelle input.small-input[aria-invalid=true] { background: var(--ui-fehler-bg) !important; border-color: var(--ui-fehler) !important; color: var(--ui-fehler); box-shadow: 0 0 0 1px var(--ui-fehler) !important; }
#hkFehler { margin: 10px var(--ui-pad) 0; }
#heimresultateTabelle .sum-cell { font-weight: 700; color: var(--ui-text); font-variant-numeric: tabular-nums; }
#heimresultateTabelle .sum-cell.empty { font-weight: 400; color: #b8c0cc; }
#hkScroll #heimresultateTabelle tbody tr.group-header td.group-header-cell { position: static !important; height: auto; padding: 6px 20px !important; text-align: left !important; background: var(--ui-grund) !important; color: var(--ui-text-2); font-size: .72rem; font-weight: 600 !important; text-transform: uppercase; letter-spacing: .05em; border-left: 0 !important; border-right: 0 !important; border-bottom: 1px solid var(--ui-linie) !important; cursor: default; }
#hkScroll #heimresultateTabelle tbody tr.ui-leer td { position: static !important; height: auto; padding: 32px 16px !important; text-align: center !important; color: var(--ui-text-2); font-weight: 400 !important; white-space: normal; cursor: default; }

/* Schnellerfassung (Slide-Panel) */
#entryPanel .panel-header h6 { font-size: 1.15rem; line-height: 1.2; }
#entryPanel .panel-pos { color: var(--ui-text-2); font-size: .8rem; }
#entryPanel .panel-body { background: var(--ui-grund); }
#entryPanel .panel-footer .ui-kbd { margin: 0 2px; }
.hk-feldtitel { display: block; margin-bottom: 4px; font-size: .75rem; font-weight: 500; color: var(--ui-text-2); }
.hk-erfassung { padding: 12px 14px 14px; background: var(--ui-flaeche); border: 1px solid var(--ui-rand); border-radius: 10px; }
.hk-erfassung-kopf { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 10px; }
.hk-erfassung-titel { font-size: .9rem; font-weight: 600; color: var(--ui-text); }
.hk-total { font-size: 1.35rem; font-weight: 700; line-height: 1.1; color: var(--ui-text); font-variant-numeric: tabular-nums; }
.hk-total span { margin-right: 6px; font-size: .8rem; font-weight: 500; color: var(--ui-text-2); }
.hk-hinweis { margin: 10px 2px 0; font-size: .78rem; color: var(--ui-text-3); }
.entry-passen-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.entry-passe-field { display: flex; flex-direction: column; }
.entry-passe-field label { margin-bottom: 4px; font-size: .75rem; font-weight: 500; color: var(--ui-text-2); }
.entry-passe-field input { width: 100%; height: 52px; padding: 0 4px; text-align: center; font-size: 1.35rem; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--ui-text); background: var(--ui-flaeche-2); border: 1px solid var(--ui-feldrand); border-radius: 8px; -moz-appearance: textfield; }
.entry-passe-field input.filled { background: var(--ui-flaeche); border-color: #c5ccd6; }
.entry-passe-field input:focus { background: var(--ui-gewaehlt); border-color: var(--ui-akzent-dunkel); box-shadow: 0 0 0 1px var(--ui-akzent-dunkel); outline: 0; }

@media (max-width: 767.98px) {
    .hk-seite { height: auto; min-height: 0; }
    .hk-tabelle { overflow: visible; }
    .hybrid-edit-panel { width: 100vw; right: -100vw; }
    .panel-overlay { display: none !important; }
    .panel-footer { position: sticky; bottom: 0; }
    .panel-footer .btn { min-height: 48px; }
}
/* Handy: aufklappbare Karte pro Mitglied, Suche oben */
#mobileCardsContainer { display: none; }
@media (max-width: 767.98px) {
    #desktopTableContainer { display: none !important; }
    #mobileCardsContainer { display: block; }
    .hk-mobil-suche { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-bottom: 1px solid var(--ui-linie); }
    .hk-mobil-suche .ui-suche { flex: 1 1 auto; width: auto; margin: 0; height: 40px; }
    .hk-mobil-zaehler { flex: none; font-size: .8rem; color: var(--ui-text-2); }
    .mobile-cards-scroll { padding: 10px; }
    .member-card { margin-bottom: 8px; overflow: hidden; background: var(--ui-flaeche); border: 1px solid var(--ui-rand); border-radius: 10px; }
    .member-card-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 14px; cursor: pointer; user-select: none; -webkit-user-select: none; }
    .member-card-header:active { background: var(--ui-flaeche-2); }
    .member-card-header .member-name { font-size: .95rem; font-weight: 600; color: var(--ui-text); }
    .member-card-header .member-summary { font-size: .78rem; color: var(--ui-text-2); }
    .member-card-header .chevron { flex: none; color: var(--ui-text-3); transition: transform .2s ease; }
    .member-card.open .chevron { transform: rotate(180deg); }
    .member-card-body { display: none; padding: 0 14px 14px; border-top: 1px solid var(--ui-linie-zart); }
    .member-card.open .member-card-body { display: block; }
    .passen-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
    .passe-field { display: flex; flex-direction: column; }
    .passe-field label { margin-bottom: 3px; font-size: .75rem; font-weight: 500; color: var(--ui-text-2); }
    .passe-field input { min-height: 48px; text-align: center; font-size: 1.1rem !important; font-weight: 600; background: var(--ui-flaeche-2); border: 1px solid var(--ui-feldrand); border-radius: 8px; -moz-appearance: textfield; }
    .passe-field input.has-value { background: var(--ui-flaeche); border-color: #c5ccd6; }
    .passe-field input:focus { background: var(--ui-gewaehlt); border-color: var(--ui-akzent-dunkel); box-shadow: 0 0 0 1px var(--ui-akzent-dunkel); outline: 0; }
}
";

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
$csrf = csrf_token();
?>
<!-- Select2 (Schützenwahl in der Schnellerfassung) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
.select2-container { z-index: 1065; }
#entryMemberSelect + .select2-container { width: 100% !important; }
.select2-container--bootstrap-5 .select2-selection { min-height: calc(1.5em + 0.75rem + 2px); }
</style>
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-default hk-seite">
                <?php
                $page_title = 'Heimmeisterschaft Resultaterfassung';
                $page_title_after = '<button type="button" class="btn-help" data-help="heimresultate.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_actions = '<button type="button" class="btn btn-outline-primary btn-sm d-none d-md-inline-block" id="startEntryBtn"><i class="bi bi-lightning me-1"></i>Schnellerfassung</button>'
                    . '<button id="redirect-btn" type="button" class="btn btn-outline-info btn-sm"><i class="bi bi-list-ol me-1"></i>Rangliste</button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm" id="publishChangelogBtn"><i class="bi bi-megaphone me-1"></i>Veröffentlichen</button>'
                    . '<button type="submit" form="heimresultateForm" class="btn btn-primary btn-sm" id="rasterSpeichernBtn"><i class="bi bi-save me-1"></i>Speichern</button>';
                $page_extra = '<div class="ui-fortschritt" aria-live="polite"><span class="ui-zahl" id="progressText">–</span></div>'
                    . '<div class="ui-chips" id="progressChips"></div>';
                $page_show_mobile = true;
                include 'partials/page_header.inc.php';
                ?>

                <form id="heimresultateForm" class="ui-karte hk-tabelle" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel">Mitglieder <button type="button" class="btn-help" data-help="heimresultate.tabelle" aria-label="Hilfe"></button></span>
                        <div class="ui-filter d-none d-md-flex" role="group" aria-label="Nach Resultaten filtern">
                            <button type="button" data-filter="alle" aria-pressed="true">Alle <span id="nAlle">0</span></button>
                            <button type="button" data-filter="mit" aria-pressed="false">Mit Resultaten <span id="nMit">0</span></button>
                            <button type="button" data-filter="ohne" aria-pressed="false">Ohne <span id="nOhne">0</span></button>
                        </div>
                        <label class="ui-suche d-none d-md-flex">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <span class="visually-hidden">Mitglied suchen</span>
                            <input type="search" id="hkSuche" placeholder="Mitglied suchen" autocomplete="off">
                        </label>
                    </div>
                    <div class="alert alert-danger small py-2 px-3 msv-eingabe-fehler" id="hkFehler" role="alert" hidden></div>
                    <!-- Desktop: Raster -->
                    <div id="desktopTableContainer" class="hk-desktop">
                        <div class="hk-scroll" id="hkScroll">
                            <table class="table mb-0" id="heimresultateTabelle">
                                <thead>
                                    <tr>
                                        <th scope="col">Mitglied</th>
                                        <?php for ($p = 1; $p <= 8; $p++): ?><th scope="col">Passe <?= $p ?></th><?php endfor; ?>

                                        <th scope="col">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="ui-leer">
                                        <td colspan="10"><div class="spinner-border spinner-border-sm me-2"></div>Lade Resultate …</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Handy: Karte pro Mitglied -->
                    <div id="mobileCardsContainer">
                        <div class="hk-mobil-suche">
                            <label class="ui-suche">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <span class="visually-hidden">Mitglied suchen</span>
                                <input type="search" id="mobileSearch" placeholder="Mitglied suchen" autocomplete="off">
                            </label>
                            <span class="hk-mobil-zaehler" id="mobileCounter"></span>
                        </div>
                        <div class="mobile-cards-scroll" id="mobileCardsList">
                            <div class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2"></div>Lade Resultate …
                            </div>
                        </div>
                    </div>
                    <div class="ui-tasten d-none d-md-flex">
                        <span><kbd>Enter</kbd> / <kbd>Tab</kbd> nächstes Feld</span>
                        <span><kbd>↑</kbd> <kbd>↓</kbd> gleiche Passe, andere Zeile</span>
                        <span><kbd>Ctrl</kbd>+<kbd>S</kbd> speichern</span>
                        <span>Name anklicken: Schnellerfassung</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Schnellerfassung: Slide-Panel -->
<div class="panel-overlay" id="entryOverlay"></div>
<div class="hybrid-edit-panel" id="entryPanel" style="--panel-width: 540px;">
    <div class="panel-header">
        <div class="min-w-0">
            <h6 class="mb-0"><span id="entryName">Erfassen</span> <button type="button" class="btn-help" data-help="heimresultate.schnellerfassung" aria-label="Hilfe"></button></h6>
            <small class="panel-pos" id="entrySubtitle"></small>
        </div>
        <div class="d-flex align-items-center gap-1 ms-auto">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="entryPrev" data-tooltip="Vorheriger Schütze" aria-label="Vorheriger Schütze">
                <i class="bi bi-chevron-up" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="entryNext" data-tooltip="Nächster Schütze" aria-label="Nächster Schütze">
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="entryClose" data-tooltip="Schliessen (Esc)" aria-label="Schliessen (Esc)">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div class="panel-body">
        <label class="hk-feldtitel" for="entryMemberSelect">Schütze wechseln</label>
        <div class="mb-3">
            <select id="entryMemberSelect" style="width:100%"></select>
        </div>
        <div class="hk-erfassung">
            <div class="hk-erfassung-kopf">
                <span class="hk-erfassung-titel">Passen</span>
                <span class="hk-total" id="entryTotalBadge"><span>Total</span>0</span>
            </div>
            <div class="entry-passen-grid" id="entryPassenGrid"></div>
        </div>
        <p class="hk-hinweis">Enter springt ins nächste Feld, im letzten Feld wird gespeichert. Leere Felder vor einer späteren Passe werden als 0 gespeichert.</p>
    </div>
    <div class="panel-footer">
        <div class="d-flex gap-2 w-100 align-items-center">
            <span class="small text-muted me-auto d-none d-md-inline"><kbd class="ui-kbd">Ctrl</kbd>+<kbd class="ui-kbd">Enter</kbd> weiter</span>
            <button type="button" class="btn btn-outline-primary btn-sm" id="entrySaveBtn">
                <i class="bi bi-save me-1"></i>Speichern
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="entrySaveNextBtn">
                Speichern &amp; Weiter <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {

    var isMobile = function() { return window.innerWidth < 768; };

    // ===== Ungespeicherte Änderungen im Raster (pro Mitglied-ID) =====
    var geaendert = new Set();
    function rowId($tr) {
        var m = ($tr.find('input.small-input').first().attr('name') || '').match(/passe\[(\d+)\]/);
        return m ? m[1] : null;
    }
    function markGeaendert($tr) {
        var id = rowId($tr);
        if (!id) return;
        geaendert.add(id);
        $tr.addClass('hk-geaendert');
        zaehlen();
    }
    window.addEventListener('beforeunload', function(e) {
        if (geaendert.size) { e.preventDefault(); e.returnValue = ''; }
    });

    // ===== Jahr-Dropdown =====
    function initializeYearDropdown() {
        msvJahrAuswahl('#yearSelect');
    }

    // ===== Resultate laden =====
    function loadResultate(year) {
        // Desktop Loading
        var $tbody = $('#heimresultateTabelle tbody');
        $tbody.html('<tr class="ui-leer"><td colspan="10"><div class="spinner-border spinner-border-sm me-2"></div>Lade Resultate …</td></tr>');
        // Mobile Loading
        $('#mobileCardsList').html(
            '<div class="text-center py-4 text-muted">' +
            '<div class="spinner-border spinner-border-sm me-2"></div>' +
            'Lade Resultate...</div>'
        );

        $.ajax({
            url: 'heimresultate/load_heimresultate_form.php',
            method: 'GET',
            cache: false,
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                geaendert.clear();
                bindDesktopInputs();
                updateHeimRowStats();
                filterAnwenden();
                EntryPanel.buildIndex();
                buildMobileCards();
            },
            error: function(xhr) {
                $tbody.html('<tr class="ui-leer"><td colspan="10" class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Die Resultate konnten nicht geladen werden: ' + msvEsc(msvXhrMessage(xhr, 'Serverfehler')) + '</td></tr>');
                $('#mobileCardsList').html(
                    '<div class="text-center py-4 text-danger">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Fehler beim Laden</div>'
                );
                msvToast('Fehler beim Laden der Resultate', 'error');
            }
        });
    }

    // ==========================================================
    //  SUMME, STATUS-DOTS, FORTSCHRITT
    // ==========================================================

    function updateHeimRowStats() {
        $('#heimresultateTabelle tbody tr').each(function() {
            const $inputs = $(this).find('input.small-input');
            if (!$inputs.length) return;
            let sum = 0, filled = 0, total = $inputs.length;

            $inputs.each(function() {
                $(this).removeClass('filled');
                const val = parseInt(this.value) || 0;
                if (this.value.trim() !== '' && this.value !== '0') {
                    filled++;
                    $(this).addClass('filled');
                }
                sum += val;
            });

            const $sumCell = $(this).find('.sum-cell');
            if ($sumCell.length) {
                $sumCell.text(sum > 0 ? sum : '\u2013').toggleClass('empty', sum === 0);
            }

            // Stand: Anzahl erfasster Passen neben dem Namen, data-stand für den Filter
            $(this).find('.hk-anzahl').text(filled ? filled + '/' + total : '').toggleClass('voll', filled === total);
            this.setAttribute('data-stand', filled ? 'mit' : 'ohne');
            this.setAttribute('data-voll', filled === total ? '1' : '0');
        });
        zaehlen();
    }

    // Kopf-Card: Mitglieder mit Resultaten, vollständige, ungespeicherte Zeilen; Zähler im Filter
    function zaehlen() {
        var $rows = $('#heimresultateTabelle tbody tr[data-stand]');
        var total = $rows.length;
        var mit = $rows.filter('[data-stand="mit"]').length;
        var voll = $rows.filter('[data-voll="1"]').length;
        $('#progressText').html(mit + ' von ' + total + ' <span>Mitgliedern mit Resultaten</span>');
        $('#progressChips').html(
            (voll ? '<span class="ui-chip"><b>' + voll + '</b> mit allen 8 Passen</span>' : '') +
            (geaendert.size ? '<span class="ui-chip hk-chip-ungespeichert"><span class="ui-punkt"></span><b>' + geaendert.size + '</b> ' +
                (geaendert.size === 1 ? 'Zeile' : 'Zeilen') + ' nicht gespeichert</span>' : '')
        );
        $('#nAlle').text(total);
        $('#nMit').text(mit);
        $('#nOhne').text(total - mit);
    }

    // ===== Filter (Alle / Mit Resultaten / Ohne) und Suche =====
    var hkFilter = 'alle', hkSuchtext = '';
    function filterAnwenden() {
        var $tbody = $('#heimresultateTabelle tbody');
        var $zeilen = $tbody.children('tr[data-stand]');
        var sichtbar = 0;
        $zeilen.each(function() {
            var zeigen = (hkFilter === 'alle' || this.getAttribute('data-stand') === hkFilter)
                && $(this).find('.hk-name').text().toLowerCase().indexOf(hkSuchtext) !== -1;
            this.style.display = zeigen ? '' : 'none';
            if (zeigen) sichtbar++;
        });
        // Gruppen-Überschriften nur in der ungefilterten Liste
        $tbody.children('tr.group-header').toggle(hkFilter === 'alle' && hkSuchtext === '');
        $('#hkKeineTreffer').remove();
        if (!sichtbar && $zeilen.length) {
            $tbody.append('<tr class="ui-leer" id="hkKeineTreffer"><td colspan="10">Keine Mitglieder für diese Auswahl. ' +
                '<button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="hkFilterZurueck">Filter zurücksetzen</button></td></tr>');
        }
    }
    $(document).on('click', '.ui-filter button', function() {
        hkFilter = $(this).data('filter');
        $('.ui-filter button').attr('aria-pressed', 'false');
        $(this).attr('aria-pressed', 'true');
        filterAnwenden();
    });
    $('#hkSuche').on('input', function() { hkSuchtext = this.value.trim().toLowerCase(); filterAnwenden(); });
    $(document).on('click', '#hkFilterZurueck', function() {
        hkFilter = 'alle'; hkSuchtext = ''; $('#hkSuche').val('');
        $('.ui-filter button').attr('aria-pressed', 'false');
        $('.ui-filter button[data-filter="alle"]').attr('aria-pressed', 'true');
        filterAnwenden();
    });

    // Input-Listener für Echtzeit-Updates
    $(document).on('input', '#heimresultateTabelle input.small-input', function() {
        updateHeimRowStats();
    });

    // ==========================================================
    //  MOBILE CARD VIEW – aus Tabellendaten generieren
    // ==========================================================

    function buildMobileCards() {
        var $container = $('#mobileCardsList').empty();
        var $rows = $('#heimresultateTabelle tbody tr');
        var totalMembers = 0;
        var membersWithValues = 0;

        $rows.each(function() {
            var $tr = $(this);
            var $tds = $tr.find('td');
            if ($tds.length < 2) return; // Skip loading/error rows

            totalMembers++;
            var name = $tr.find('.hk-name').text().trim() || $tds.eq(0).text().trim();
            var $inputs = $tr.find('input');
            if ($inputs.length === 0) return;

            // Prüfe ob Werte vorhanden + Summe berechnen
            var hasValues = false;
            var summaryParts = [];
            var totalSum = 0;
            $inputs.each(function(idx) {
                var val = $(this).val();
                if (val && val !== '' && val !== '0') {
                    hasValues = true;
                    summaryParts.push('P' + (idx + 1) + ':' + val);
                    totalSum += parseInt(val) || 0;
                }
            });
            if (hasValues) {
                membersWithValues++;
                summaryParts.push('\u03A3 ' + totalSum);
            }

            // Card HTML bauen
            var cardHtml = '<div class="member-card' + (hasValues ? ' has-values' : '') + '" data-name="' + escapeHtml(name.toLowerCase()) + '">';

            // Header
            cardHtml += '<div class="member-card-header">';
            cardHtml += '  <div>';
            cardHtml += '    <div class="member-name">' + escapeHtml(name) + '</div>';
            if (hasValues) {
                cardHtml += '    <div class="member-summary">' + summaryParts.join(' · ') + '</div>';
            } else {
                cardHtml += '    <div class="member-summary text-muted">Keine Resultate</div>';
            }
            cardHtml += '  </div>';
            cardHtml += '  <i class="bi bi-chevron-down chevron"></i>';
            cardHtml += '</div>';

            // Body mit Passen-Grid
            cardHtml += '<div class="member-card-body">';
            cardHtml += '  <div class="passen-grid">';

            $inputs.each(function(idx) {
                var $origInput = $(this);
                var inputName = $origInput.attr('name');
                var val = $origInput.val() || '';
                var passeNr = idx + 1;
                var hasVal = (val && val !== '' && val !== '0');

                cardHtml += '<div class="passe-field">';
                cardHtml += '  <label>Passe ' + passeNr + '</label>';
                cardHtml += '  <input type="text"';
                cardHtml += '    class="form-control mobile-passe-input' + (hasVal ? ' has-value' : '') + '"';
                cardHtml += '    data-sync="' + inputName + '"';
                cardHtml += '    value="' + escapeHtml(val) + '"';
                cardHtml += '    inputmode="numeric"';
                cardHtml += '    pattern="[0-9]*"';
                cardHtml += '    maxlength="3"';
                cardHtml += '    autocomplete="off">';
                cardHtml += '</div>';
            });

            cardHtml += '  </div>'; // /passen-grid
            cardHtml += '</div>'; // /member-card-body
            cardHtml += '</div>'; // /member-card

            $container.append(cardHtml);
        });

        // Counter aktualisieren
        $('#mobileCounter').html(
            '<i class="bi bi-people-fill me-1"></i>' +
            membersWithValues + '/' + totalMembers + ' erfasst'
        );

        // Mobile Input Events binden
        bindMobileInputs();
    }

    // ===== Mobile Input Events =====
    function bindMobileInputs() {
        var $mobileInputs = $('.mobile-passe-input');

        // Sync: Mobile → Desktop Table
        $mobileInputs.off('input.sync').on('input.sync', function() {
            var $this = $(this);
            var syncName = $this.data('sync');
            var value = $this.val().replace(/[^0-9]/g, '');
            if (value.length > 3) value = value.substring(0, 3);
            $this.val(value);
            $this.attr('aria-invalid', value !== '' && parseInt(value, 10) > 100 ? 'true' : null);

            // Wert in Desktop-Tabelle synchronisieren
            var $ziel = $('input[name="' + syncName + '"]').not('.mobile-passe-input').val(value);
            markGeaendert($ziel.closest('tr'));

            // Visuelles Feedback
            $this.toggleClass('has-value', value !== '' && value !== '0');
        });

        // Focus: 0 leeren
        $mobileInputs.off('focus.mobile').on('focus.mobile', function() {
            var $this = $(this);
            if ($this.val() === '0') $this.val('');
            $this.select();
        });

        // Blur: nochmals übernehmen, Zusammenfassung nachführen (leer bleibt leer = keine Passe)
        $mobileInputs.off('blur.mobile').on('blur.mobile', function() {
            var $this = $(this);
            // Sync nochmal
            var syncName = $this.data('sync');
            $('input[name="' + syncName + '"]').not('.mobile-passe-input').val($this.val());

            // Summary aktualisieren
            updateCardSummary($this.closest('.member-card'));
        });

        // Enter: zum nächsten Feld
        $mobileInputs.off('keydown.mobile').on('keydown.mobile', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var $card = $(this).closest('.member-card');
                var $cardInputs = $card.find('.mobile-passe-input');
                var idx = $cardInputs.index(this);
                if (idx < $cardInputs.length - 1) {
                    $cardInputs.eq(idx + 1).focus();
                } else {
                    // Nächste Card öffnen
                    var $nextCard = $card.next('.member-card');
                    if ($nextCard.length) {
                        $card.removeClass('open');
                        $nextCard.addClass('open');
                        $nextCard[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                        setTimeout(function() {
                            $nextCard.find('.mobile-passe-input:first').focus();
                        }, 300);
                    }
                }
            }
        });
    }

    // ===== Card Summary aktualisieren =====
    function updateCardSummary($card) {
        var parts = [];
        var hasValues = false;
        var totalSum = 0;
        $card.find('.mobile-passe-input').each(function(idx) {
            var val = $(this).val();
            if (val && val !== '' && val !== '0') {
                hasValues = true;
                parts.push('P' + (idx + 1) + ':' + val);
                totalSum += parseInt(val) || 0;
            }
        });
        if (hasValues) parts.push('\u03A3 ' + totalSum);

        $card.toggleClass('has-values', hasValues);
        var $summary = $card.find('.member-summary');
        if (hasValues) {
            $summary.removeClass('text-muted').text(parts.join(' · '));
        } else {
            $summary.addClass('text-muted').text('Keine Resultate');
        }

        // Counter aktualisieren
        var total = $('.member-card').length;
        var withValues = $('.member-card.has-values').length;
        $('#mobileCounter').html(
            '<i class="bi bi-people-fill me-1"></i>' +
            withValues + '/' + total + ' erfasst'
        );
    }

    // ===== Accordion Toggle =====
    $(document).on('click', '.member-card-header', function(e) {
        if ($(e.target).is('input')) return; // Nicht bei Input-Klick
        var $card = $(this).closest('.member-card');
        var wasOpen = $card.hasClass('open');

        // Alle anderen schliessen
        $('.member-card.open').not($card).removeClass('open');

        // Diese togglen
        $card.toggleClass('open', !wasOpen);

        // Bei Öffnen: ins Sichtfeld scrollen
        if (!wasOpen) {
            setTimeout(function() { $card[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }, 50);
        }
    });

    // ===== Mobile Suche =====
    $('#mobileSearch').on('input', function() {
        var query = $(this).val().toLowerCase().trim();
        $('.member-card').each(function() {
            var name = $(this).data('name') || '';
            $(this).toggle(name.indexOf(query) !== -1);
        });
    });

    // ==========================================================
    //  DESKTOP TABLE INPUT HANDLING
    // ==========================================================

    function bindDesktopInputs() {
        var $inputs = $('#heimresultateTabelle input');

        $inputs.off('keydown.heim').on('keydown.heim', function(e) {
            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                var inputs = $('#heimresultateTabelle tbody tr:visible input.small-input');
                var currentIndex = inputs.index(this);
                var nextIndex = e.shiftKey ? currentIndex - 1 : currentIndex + 1;
                if (nextIndex >= 0 && nextIndex < inputs.length) inputs.eq(nextIndex).focus().select();
            } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                // gleiche Passe in der nächsten bzw. vorherigen sichtbaren Zeile
                e.preventDefault();
                var spalte = $(this).closest('td').index();
                var $zeilen = $('#heimresultateTabelle tbody tr:visible').filter(function() { return $(this).find('input.small-input').length > 0; });
                var z = $zeilen.index($(this).closest('tr')) + (e.key === 'ArrowDown' ? 1 : -1);
                if (z >= 0 && z < $zeilen.length) $zeilen.eq(z).children('td').eq(spalte).find('input').focus().select();
            }
        });

        // Eine 0 verschwindet beim Betreten; leer bleibt leer (keine Passe, kein Datensatz)
        $inputs.off('focus.heim').on('focus.heim', function() {
            var $this = $(this);
            if ($this.val() === '0') $this.val('').select();
            else if ($this.val() !== '') $this.select();
        });

        $inputs.off('input.heim').on('input.heim', function(e) {
            var value = $(this).val().replace(/[^0-9]/g, '');
            if (value.length > 3) value = value.substring(0, 3);
            $(this).val(value);
            msvPruefeZahl(this);
            if (!$('#hkFehler').prop('hidden')) msvEingabeFehler('#hkFehler', msvPruefeFelder('#heimresultateTabelle'), false);
            // nur echte Eingaben im Raster; Übernahmen aus dem Panel markiert syncField
            if (e.originalEvent) markGeaendert($(this).closest('tr'));
        });
    }

    // ===== Leere Felder mit 0 füllen vor Speichern =====
    function fillEmptyWithZero() {
        $('#heimresultateTabelle tbody tr').each(function() {
            var inputs = $(this).find('input');
            var hasLaterValue = false;
            for (var i = inputs.length - 1; i >= 0; i--) {
                var $input = $(inputs[i]);
                var val = $input.val().trim();
                if (val !== '' && val !== '0') hasLaterValue = true;
                else if (hasLaterValue && val === '') $input.val('0');
            }
        });
    }

    // ===== Mobile → Desktop sync vor Speichern =====
    function syncMobileToDesktop() {
        $('.mobile-passe-input').each(function() {
            var $this = $(this);
            var syncName = $this.data('sync');
            var value = $this.val();
            $('input[name="' + syncName + '"]').not('.mobile-passe-input').val(value);
        });
    }

    // ===== Speichern =====
    $('#heimresultateForm').on('submit', function(e) {
        e.preventDefault();
        if (isMobile()) syncMobileToDesktop();
        // Werte über 100 oder keine Zahl: nicht speichern, sondern zeigen, wo
        if (msvEingabeFehler('#hkFehler', msvPruefeFelder('#heimresultateTabelle'))) return;

        var $submitBtn = $('#rasterSpeichernBtn');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        fillEmptyWithZero();

        var selectedYear = $('#yearSelect').val();
        var formData = $(this).serialize() + '&year=' + selectedYear + '&jahr=' + selectedYear;

        $.ajax({
            url: 'heimresultate/save_heimresultate.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                if (!resp || !resp.success) { msvToast((resp && resp.message) || 'Fehler beim Speichern der Ergebnisse', 'error'); return; }
                geaendert.clear();
                msvToast('Resultate gespeichert', 'success');
                loadResultate(selectedYear);
            },
            error: function(xhr) {
                msvToast(msvXhrMessage(xhr, 'Fehler beim Speichern der Ergebnisse'), 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });


    // ===== Rangliste =====
    $('#redirect-btn').on('click', function() { window.location.href = 'heimrang.php'; });

    // ===== Jahreswechsel (fragt nach, wenn im Raster noch nicht gespeichert wurde) =====
    var aktuellesJahr = null;
    $('#yearSelect').on('change', async function() {
        var neu = this.value;
        if (neu === aktuellesJahr) return;
        if (geaendert.size) {
            const r = await msvConfirm('Die Änderungen in ' + geaendert.size + (geaendert.size === 1 ? ' Zeile sind' : ' Zeilen sind') +
                ' noch nicht gespeichert und gehen beim Jahreswechsel verloren.', 'Ungespeicherte Änderungen verwerfen?', 'Verwerfen');
            if (!r.isConfirmed) {
                this.value = aktuellesJahr;
                this.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
        }
        aktuellesJahr = neu;
        loadResultate(neu);
    });

    // ===== Resize =====
    var resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            // Bei Wechsel Desktop ↔ Mobile: Cards ggf. neu bauen
            if (isMobile() && $('#mobileCardsList .member-card').length === 0) {
                buildMobileCards();
            }
        }, 150);
    });

    // ===== Hilfsfunktion =====
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ===== Veröffentlichen =====
    $('#publishChangelogBtn').on('click', async function() {
        const r = await msvConfirm('Ein Eintrag wird auf der Website angezeigt.', 'Änderung veröffentlichen?', 'Veröffentlichen');
        if (!r.isConfirmed) return;
        var selectedYear = $('#yearSelect').val();
        $.post('changelog_publish.php', {
            kategorie: 'resultate',
            tabelle: 'heimresultate',
            jahr: selectedYear,
            beschreibung: 'Heimresultate ' + selectedYear + ' aktualisiert',
            csrf_token: $('input[name="csrf_token"]').val()
        }).done(function(res) {
            if (res.success) msvToast(res.message, 'success');
            else msvToast(res.message || 'Fehler', 'error');
        }).fail(function() {
            msvToast('Veröffentlichung fehlgeschlagen', 'error');
        });
    });

    // =========================================
    //  Erfassen-Panel – Schütze um Schütze
    // =========================================
    const EntryPanel = {
        rows: [],
        idx: -1,
        _silent: false,
        _saved: false,   // im Panel gespeichert → Liste beim Schliessen neu laden
        maxLen: 3,       // Heim: 3-stellig
        clampMax: 100,   // max 100
        saveUrl: 'heimresultate/save_heimresultate.php',

        buildIndex() {
            this.rows = [];
            const self = this;
            $('#heimresultateTabelle tbody tr').each(function() {
                const $tr = $(this);
                const $inputs = $tr.find('input.small-input');
                if (!$inputs.length) return;
                const nm = $inputs.first().attr('name') || '';
                const m = nm.match(/passe\[(\d+)\]/);
                if (!m) return;
                self.rows.push({
                    id: m[1],
                    name: $tr.find('.hk-name').text().trim(),
                    $tr: $tr,
                    $inputs: $inputs
                });
            });
        },

        isComplete(row) {
            let all = true;
            row.$inputs.each(function() {
                const v = $(this).val();
                if (v === '' || v === '0') all = false;
            });
            return all;
        },

        firstIncomplete() {
            for (let i = 0; i < this.rows.length; i++) {
                if (!this.isComplete(this.rows[i])) return i;
            }
            return 0;
        },

        nextIncomplete(from) {
            for (let i = from + 1; i < this.rows.length; i++) {
                if (!this.isComplete(this.rows[i])) return i;
            }
            return -1;
        },

        open(idx) {
            if (idx < 0 || idx >= this.rows.length) return;
            this.idx = idx;
            const row = this.rows[idx];

            const $grid = $('#entryPassenGrid').empty();
            row.$inputs.each(function(i) {
                const v = $(this).val() || '';
                const $field = $(
                    '<div class="entry-passe-field" data-pi="' + i + '">' +
                    '<label>Passe ' + (i + 1) + '</label>' +
                    '<input type="text" inputmode="numeric" autocomplete="off" maxlength="' + EntryPanel.maxLen + '">' +
                    '</div>'
                );
                $field.find('input').val(v);
                $grid.append($field);
            });

            $('#entryName').text(row.name);
            $('#entrySubtitle').text((idx + 1) + ' von ' + this.rows.length);
            this.rows.forEach(r => r.$tr.removeClass('selected'));
            row.$tr.addClass('selected');
            if (row.$tr.is(':visible')) row.$tr[0].scrollIntoView({ block: 'nearest' });

            this.refreshFields();
            this.updateProgress();
            this._silent = true;
            this.populateSelect();
            this._silent = false;

            $('#entryOverlay').addClass('show');
            $('#entryPanel').addClass('open');
            setTimeout(function() {
                const $fields = $('#entryPassenGrid input');
                let $target = $fields.filter(function() {
                    const v = $(this).val().trim();
                    return v === '' || v === '0';
                }).first();
                if (!$target.length) $target = $fields.first();
                $target.focus().select();
            }, 150);
        },

        // Schützen-Suche (Select2) befüllen + aktuellen markieren
        populateSelect() {
            const $sel = $('#entryMemberSelect');
            if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
            $sel.empty();
            const self = this;
            this.rows.forEach(function(r, i) {
                const done = self.isComplete(r);
                $sel.append(new Option((done ? '✓ ' : '') + r.name, i, false, i === self.idx));
            });
            $sel.select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#entryPanel'),
                width: '100%',
                placeholder: 'Schütze suchen…'
            }).off('select2:open.entry').on('select2:open.entry', function() {
                setTimeout(function() {
                    const f = document.querySelector('.select2-container--open .select2-search__field');
                    if (f) f.focus();
                }, 0);
            });
        },

        close() {
            $('#entryPanel').removeClass('open');
            $('#entryOverlay').removeClass('show');
            this.rows.forEach(r => r.$tr.removeClass('selected'));
            this.idx = -1;
            // Nach Speichern im Panel frisch laden (Gruppen), aber nie ungespeicherte Raster-Eingaben verwerfen
            if (this._saved && !geaendert.size) {
                this._saved = false;
                loadResultate($('#yearSelect').val());
            }
        },

        navigate(dir) {
            const n = this.idx + dir;
            if (n >= 0 && n < this.rows.length) this.open(n);
        },

        syncField(pi, value) {
            if (this.idx < 0) return;
            const row = this.rows[this.idx];
            row.$inputs.eq(pi).val(value).trigger('input');
            markGeaendert(row.$tr);   // ungespeichert, bis das Panel speichert
        },

        refreshFields() {
            let sum = 0;
            $('#entryPassenGrid .entry-passe-field').each(function() {
                const v = parseInt($(this).find('input').val(), 10) || 0;
                const raw = $(this).find('input').val().trim();
                $(this).find('input').toggleClass('filled', raw !== '' && raw !== '0');
                sum += v;
            });
            $('#entryTotalBadge').html('<span>Total</span>' + sum);
        },

        updateProgress() {
            zaehlen();
        },

        collectPayload(row) {
            const vals = [];
            row.$inputs.each(function() { vals.push($(this).val().trim()); });
            let hasLater = false;
            for (let i = vals.length - 1; i >= 0; i--) {
                if (vals[i] !== '' && vals[i] !== '0') hasLater = true;
                else if (hasLater && vals[i] === '') vals[i] = '0';
            }
            row.$inputs.each(function(i) {
                if ($(this).val().trim() === '' && vals[i] === '0') $(this).val('0').trigger('input');
            });
            const passe = {};
            for (let i = 0; i < vals.length; i++) passe[i + 1] = vals[i];
            const obj = {};
            obj[row.id] = passe;
            return obj;
        },

        save(onDone) {
            if (this.idx < 0 || $('#entrySaveBtn').prop('disabled')) return;   // läuft bereits
            const row = this.rows[this.idx];
            const $btns = $('#entrySaveBtn, #entrySaveNextBtn').prop('disabled', true);
            $.ajax({
                url: this.saveUrl,
                type: 'POST',
                data: {
                    csrf_token: $('input[name="csrf_token"]').first().val(),
                    jahr: $('#yearSelect').val(),
                    year: $('#yearSelect').val(),
                    passe: this.collectPayload(row)
                },
                dataType: 'json',
                success: function(resp) {
                    if (!resp || !resp.success) { msvToast((resp && resp.message) || 'Fehler beim Speichern', 'error'); return; }
                    EntryPanel._saved = true;
                    geaendert.delete(row.id);
                    row.$tr.removeClass('hk-geaendert');
                    EntryPanel.updateProgress();
                    if (typeof onDone === 'function') onDone();
                    else msvToast('Gespeichert', 'success');
                },
                error: function(xhr) { msvToast(msvXhrMessage(xhr, 'Fehler beim Speichern'), 'error'); },
                complete: function() { $btns.prop('disabled', false); }
            });
        },

        saveAndNext() {
            const fromIdx = this.idx;
            this.save(function() {
                const n = EntryPanel.nextIncomplete(fromIdx);
                if (n >= 0) {
                    EntryPanel.open(n);
                } else {
                    msvToast('Alle Schützen erfasst', 'success');
                    EntryPanel.close();
                }
            });
        }
    };

    // Panel-Feld-Eingabe (delegiert): validieren + syncen
    $(document).on('input', '#entryPassenGrid input', function(e) {
        let value = $(this).val().replace(/[^0-9]/g, '');
        if (value.length > EntryPanel.maxLen) value = value.substring(0, EntryPanel.maxLen);
        if (EntryPanel.clampMax !== null && value !== '' && parseInt(value, 10) > EntryPanel.clampMax) {
            value = String(EntryPanel.clampMax);
        }
        $(this).val(value);
        const pi = parseInt($(this).closest('.entry-passe-field').data('pi'), 10);
        EntryPanel.syncField(pi, value);
        EntryPanel.refreshFields();

        // Auto-Weiter beim Tippen: 2-9 → zweistellig, 1 → dreistellig (100); 0 bleibt stehen
        const typed = e.originalEvent && /^insert/.test(e.originalEvent.inputType || '');
        const need = (value === '' || value[0] === '0') ? 0 : (value[0] === '1' ? 3 : 2);
        if (typed && need && value.length >= need) {
            const $inputs = $('#entryPassenGrid input');
            const i = $inputs.index(this);
            if (i < $inputs.length - 1) $inputs.eq(i + 1).focus().select();
        }
    });

    $(document).on('focus', '#entryPassenGrid input', function() {
        if ($(this).val() === '0') $(this).val('');
        $(this).select();
    });

    // Enter: nächstes Feld, letztes Feld → Speichern & Weiter
    $(document).on('keydown', '#entryPassenGrid input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const $inputs = $('#entryPassenGrid input');
            const i = $inputs.index(this);
            if (i < $inputs.length - 1) $inputs.eq(i + 1).focus().select();
            else EntryPanel.saveAndNext();
        }
    });

    $('#startEntryBtn').on('click', function() {
        EntryPanel.buildIndex();
        if (!EntryPanel.rows.length) { msvToast('Keine Mitglieder geladen', 'error'); return; }
        EntryPanel.open(EntryPanel.firstIncomplete());
    });

    // Klick auf Namen-Zelle öffnet Panel bei diesem Schützen
    $(document).on('click', '#heimresultateTabelle tbody td:first-child', function() {
        const $tr = $(this).closest('tr');
        const i = EntryPanel.rows.findIndex(r => r.$tr.is($tr));
        if (i >= 0) EntryPanel.open(i);
    });

    // Select2-Auswahl springt zum Schützen
    $(document).on('change', '#entryMemberSelect', function() {
        if (EntryPanel._silent) return;
        const i = parseInt($(this).val(), 10);
        if (!isNaN(i) && i !== EntryPanel.idx) EntryPanel.open(i);
    });

    $('#entryPrev').on('click', function() { EntryPanel.navigate(-1); });
    $('#entryNext').on('click', function() { EntryPanel.navigate(1); });
    $('#entryClose, #entryOverlay').on('click', function() { EntryPanel.close(); });
    $('#entrySaveBtn').on('click', function() {
        EntryPanel.save(function() {
            msvToast('Gespeichert', 'success');
            EntryPanel.close();
        });
    });
    $('#entrySaveNextBtn').on('click', function() { EntryPanel.saveAndNext(); });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#entryPanel').hasClass('open') && !$('.select2-container--open').length) EntryPanel.close();
    });

    // Ctrl+S speichert (Panel offen: diesen Schützen, sonst das ganze Raster), Ctrl+Enter im Panel: speichern & weiter
    $(document).on('keydown', function(e) {
        if (!(e.ctrlKey || e.metaKey)) return;
        const panelOffen = $('#entryPanel').hasClass('open');
        if (e.key === 's' || e.key === 'S') {
            e.preventDefault();
            if (panelOffen) $('#entrySaveBtn').trigger('click');
            else if (!$('#rasterSpeichernBtn').prop('disabled')) $('#heimresultateForm').trigger('submit');
        } else if (e.key === 'Enter' && panelOffen) {
            e.preventDefault();
            EntryPanel.saveAndNext();
        }
    });

    // Init
    initializeYearDropdown();
    aktuellesJahr = $('#yearSelect').val();
    loadResultate(aktuellesJahr);
});
</script>

<?php include 'footer.inc.php'; ?>
