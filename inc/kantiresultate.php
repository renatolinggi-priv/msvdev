<?php
// kantiresultate.php – Raster pro Mitglied (5 Passen) und Schnellerfassung im Slide-Panel
include 'dbconnect.inc.php';

// Seitenspezifische Styles: nur Aufbau dieser Seite; die Optik kommt aus css/msv-ui.css
$page_specific_css = "
/* Fensterhöhe (.ui-vollhoehe, .ui-scroll) und Erfassungs-Panel (.ui-erfassen) kommen aus css/msv-ui.css */
.hk-scroll { scroll-padding-top: 40px; }

/* Raster; die #hkScroll-Präfixe schlagen die !important-Regeln aus css/fixes/resultate-unified.css */
#hkScroll #kantiresultateTabelle { margin: 0; width: 100%; min-width: 680px; table-layout: fixed; border-collapse: separate; border-spacing: 0; }
#hkScroll #kantiresultateTabelle thead th { position: sticky; top: 0 !important; z-index: 3; padding: 8px 6px; text-align: center; white-space: nowrap; background: var(--ui-flaeche-2) !important; border-bottom: 1px solid var(--ui-linie); }
#hkScroll #kantiresultateTabelle th:not(:first-child),
#hkScroll #kantiresultateTabelle td:not(:first-child) { width: 72px !important; min-width: 72px !important; max-width: 72px !important; }
#hkScroll #kantiresultateTabelle th:first-child,
#hkScroll #kantiresultateTabelle td:first-child { width: auto !important; min-width: 220px !important; max-width: none !important; padding-left: 20px; text-align: left !important; }
#hkScroll #kantiresultateTabelle th:last-child,
#hkScroll #kantiresultateTabelle td:last-child { width: 84px !important; min-width: 84px !important; max-width: 84px !important; }
#hkScroll #kantiresultateTabelle tbody td { height: 44px; padding-top: 0; padding-bottom: 0; vertical-align: middle; text-align: center; border-bottom: 1px solid var(--ui-linie-zart); }
#hkScroll #kantiresultateTabelle tbody td:first-child { cursor: pointer; }
#hkScroll #kantiresultateTabelle tbody tr:not(.group-header):not(.ui-leer):hover > td { background: var(--ui-flaeche-2); }
.hk-zelle { display: flex; align-items: center; gap: 8px; min-width: 0; }
.hk-name { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; color: var(--ui-text); }
.hk-anzahl { flex: none; font-size: .75rem; color: var(--ui-text-3); font-variant-numeric: tabular-nums; }
.hk-anzahl.voll { color: var(--ui-ok-fg); font-weight: 600; }
tr.hk-geaendert .hk-name::after { content: ''; display: inline-block; width: 7px; height: 7px; margin-left: 7px; border-radius: 50%; background: var(--ui-akzent); vertical-align: middle; }
.hk-chip-ungespeichert .ui-punkt { background: var(--ui-akzent) !important; }
#hkScroll #kantiresultateTabelle input.small-input { width: 52px !important; height: 32px !important; margin: 0 auto; padding: 0 4px !important; font-size: .9rem !important; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--ui-text); background: var(--ui-flaeche); border: 1px solid var(--ui-rand-stark) !important; border-radius: 6px !important; box-shadow: none; }
#hkScroll #kantiresultateTabelle input.small-input:not(.filled) { background: var(--ui-flaeche-2); border-color: var(--ui-feldrand-leer) !important; font-weight: 400; color: var(--ui-text-2); }
#hkScroll #kantiresultateTabelle input.small-input:focus { background: var(--ui-gewaehlt); border-color: var(--ui-akzent-dunkel) !important; box-shadow: 0 0 0 1px var(--ui-akzent-dunkel) !important; outline: 0; }
#hkScroll #kantiresultateTabelle input.small-input[aria-invalid=true] { background: var(--ui-fehler-bg) !important; border-color: var(--ui-fehler) !important; color: var(--ui-fehler); box-shadow: 0 0 0 1px var(--ui-fehler) !important; }
#hkFehler { margin: 10px var(--ui-pad) 0; }
#kantiresultateTabelle .sum-cell { font-weight: 700; color: var(--ui-text); font-variant-numeric: tabular-nums; }
#kantiresultateTabelle .sum-cell.empty { font-weight: 400; color: var(--ui-leer); }
#hkScroll #kantiresultateTabelle tbody tr.group-header td.group-header-cell { position: static !important; height: auto; padding: 6px 20px !important; text-align: left !important; background: var(--ui-grund) !important; color: var(--ui-text-2); font-size: .72rem; font-weight: 600 !important; text-transform: uppercase; letter-spacing: .05em; border-left: 0 !important; border-right: 0 !important; border-bottom: 1px solid var(--ui-linie) !important; cursor: default; }
#hkScroll #kantiresultateTabelle tbody tr.ui-leer td { position: static !important; height: auto; padding: 32px 16px !important; text-align: center !important; color: var(--ui-text-2); font-weight: 400 !important; white-space: normal; cursor: default; }

/* Schnellerfassung (Slide-Panel .ui-erfassen): Grund hinter der Erfassungskarte */
#entryPanel .panel-body { background: var(--ui-grund); }
.hk-feldtitel { display: block; margin-bottom: 4px; font-size: .75rem; font-weight: 500; color: var(--ui-text-2); }
.hk-erfassung { padding: 12px 14px 14px; background: var(--ui-flaeche); border: 1px solid var(--ui-rand); border-radius: 10px; }
.hk-erfassung-kopf { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 10px; }
.hk-erfassung-titel { font-size: .9rem; font-weight: 600; color: var(--ui-text); }
.hk-total { font-size: 1.35rem; font-weight: 700; line-height: 1.1; color: var(--ui-text); font-variant-numeric: tabular-nums; }
.hk-total span { margin-right: 6px; font-size: .8rem; font-weight: 500; color: var(--ui-text-2); }
.hk-hinweis { margin: 10px 2px 0; font-size: .78rem; color: var(--ui-text-3); }
.entry-passen-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; }
.entry-passe-field { display: flex; flex-direction: column; }
.entry-passe-field label { margin-bottom: 4px; font-size: .75rem; font-weight: 500; color: var(--ui-text-2); }
.entry-passe-field input { width: 100%; height: 52px; padding: 0 4px; text-align: center; font-size: 1.35rem; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--ui-text); background: var(--ui-flaeche-2); border: 1px solid var(--ui-feldrand); border-radius: 8px; -moz-appearance: textfield; }
.entry-passe-field input.filled { background: var(--ui-flaeche); border-color: var(--ui-rand-stark); }
.entry-passe-field input:focus { background: var(--ui-gewaehlt); border-color: var(--ui-akzent-dunkel); box-shadow: 0 0 0 1px var(--ui-akzent-dunkel); outline: 0; }
.entry-passe-field input[aria-invalid=true] { background: var(--ui-fehler-bg); border-color: var(--ui-fehler); color: var(--ui-fehler); box-shadow: 0 0 0 1px var(--ui-fehler); }

/* Beste Passe in der Akzentfarbe (Raster, Panel, Handy) */
#hkScroll #kantiresultateTabelle input.small-input.best-passe { background: var(--ui-akzent-hell); border-color: #9db8ea !important; color: var(--ui-akzent-dunkel); font-weight: 700; }
.entry-passe-field.is-best input { background: var(--ui-akzent-hell); border-color: #9db8ea; color: var(--ui-akzent-dunkel); }
.entry-passe-field.is-best label::after { content: ' · beste'; font-weight: 600; color: var(--ui-akzent-dunkel); }

/* Handy: Karten aus msv-styles, Suche oben */
@media (max-width: 767.98px) {
    .desktop-table-container { display: none !important; }
    .mobile-cards-container { display: flex !important; }
    .mobile-card-body .passe-input-mobile { min-height: 48px; text-align: center; font-size: 16px; font-weight: 600; }
    .mobile-card-body .passe-input-mobile.best { background: var(--ui-akzent-hell); border-color: #9db8ea; color: var(--ui-akzent-dunkel); }
    .mobile-card-body .form-label { margin-bottom: .35rem; font-size: .85rem; color: var(--ui-text-2); }
}
@media (min-width: 768px) {
    .mobile-cards-container { display: none !important; }
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
            <div class="main-content-wrapper content-width-default ui-vollhoehe">
                <?php
                $page_title = 'Kantonalstich erfassen';
                $page_title_after = '<button type="button" class="btn-help" data-help="kantiresultate.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_actions = '<button type="button" class="btn btn-outline-primary btn-sm d-none d-md-inline-block" id="startEntryBtn"><i class="bi bi-lightning me-1"></i>Schnellerfassung</button>'
                    . '<button id="redirect-btn" type="button" class="btn btn-outline-info btn-sm"><i class="bi bi-list-ol me-1"></i>Rangliste</button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm" id="publishChangelogBtn"><i class="bi bi-megaphone me-1"></i>Veröffentlichen</button>'
                    . '<button type="submit" form="kantiresultateForm" class="btn btn-primary btn-sm" id="rasterSpeichernBtn"><i class="bi bi-save me-1"></i>Speichern</button>';
                $page_extra = '<div class="ui-fortschritt" aria-live="polite"><span class="ui-zahl" id="progressText">–</span></div>'
                    . '<div class="ui-chips" id="progressChips"></div>';
                $page_show_mobile = true;
                include 'partials/page_header.inc.php';
                ?>

                <form id="kantiresultateForm" class="ui-karte ui-vollhoehe-karte" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel">Mitglieder <button type="button" class="btn-help" data-help="kantiresultate.tabelle" aria-label="Hilfe"></button></span>
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
                    <div class="desktop-table-container">
                        <div class="ui-scroll hk-scroll" id="hkScroll">
                            <table class="table mb-0" id="kantiresultateTabelle">
                                <thead>
                                    <tr>
                                        <th scope="col">Mitglied</th>
                                        <?php for ($p = 1; $p <= 5; $p++): ?><th scope="col">Passe <?= $p ?></th><?php endfor; ?>

                                        <th scope="col">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="ui-leer">
                                        <td colspan="7"><div class="spinner-border spinner-border-sm me-2"></div>Lade Resultate …</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Handy: Karte pro Mitglied -->
                    <div class="mobile-cards-container" id="mobileCardsKanti">
                        <div class="mobile-search">
                            <div class="position-relative">
                                <i class="bi bi-search search-icon"></i>
                                <input type="text" class="form-control" placeholder="Mitglied suchen..."
                                       oninput="filterMobileKanti(this)">
                            </div>
                        </div>
                        <div class="mobile-cards-scroll">
                            <!-- Karten werden per JavaScript erzeugt -->
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
<div class="hybrid-edit-panel ui-erfassen" id="entryPanel" style="--panel-width: 540px;">
    <div class="panel-header">
        <div class="min-w-0">
            <h6 class="mb-0"><span id="entryName">Erfassen</span> <button type="button" class="btn-help" data-help="kantiresultate.schnellerfassung" aria-label="Hilfe"></button></h6>
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

    function initializeYearDropdown() {
        msvJahrAuswahl('#yearSelect');
    }


    function loadResultate(year) {
        var $tbody = $('#kantiresultateTabelle tbody');
        $tbody.html('<tr class="ui-leer"><td colspan="7"><div class="spinner-border spinner-border-sm me-2"></div>Lade Resultate …</td></tr>');

        $.ajax({
            url: 'kantiresultate/load_kantiresultate_form.php',
            method: 'GET',
            cache: false,
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                geaendert.clear();
                bindInputs();
                updateKantiRowStats();
                filterAnwenden();
                EntryPanel.buildIndex();
                buildMobileKantiCards();
            },
            error: function(xhr) {
                $tbody.html('<tr class="ui-leer"><td colspan="7" class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Die Resultate konnten nicht geladen werden: ' + msvEsc(msvXhrMessage(xhr, 'Serverfehler')) + '</td></tr>');
                msvToast('Fehler beim Laden der Resultate', 'error');
            }
        });
    }

    function bindInputs() {
        var $inputs = $('#kantiresultateTabelle input');

        $inputs.off('keydown.kanti').on('keydown.kanti', function(e) {
            if (e.key === 'Enter') { // Tab bleibt nativ, damit man das Raster verlassen kann
                e.preventDefault();
                var inputs = $('#kantiresultateTabelle tbody tr:visible input.small-input');
                var currentIndex = inputs.index(this);
                var nextIndex = e.shiftKey ? currentIndex - 1 : currentIndex + 1;
                if (nextIndex >= 0 && nextIndex < inputs.length) inputs.eq(nextIndex).focus().select();
            } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                // gleiche Passe in der nächsten bzw. vorherigen sichtbaren Zeile
                e.preventDefault();
                var spalte = $(this).closest('td').index();
                var $zeilen = $('#kantiresultateTabelle tbody tr:visible').filter(function() { return $(this).find('input.small-input').length > 0; });
                var z = $zeilen.index($(this).closest('tr')) + (e.key === 'ArrowDown' ? 1 : -1);
                if (z >= 0 && z < $zeilen.length) $zeilen.eq(z).children('td').eq(spalte).find('input').focus().select();
            }
        });

        // Eine 0 verschwindet beim Betreten; leer bleibt leer (keine Passe, kein Datensatz)
        $inputs.off('focus.kanti').on('focus.kanti', function() {
            var $this = $(this);
            if ($this.val() === '0') $this.val('').select();
            else if ($this.val() !== '') $this.select();
        });

        $inputs.off('input.kanti').on('input.kanti', function(e) {
            var value = $(this).val().replace(/[^0-9]/g, '');
            if (value.length > 3) value = value.substring(0, 3);
            $(this).val(value);
            msvPruefeZahl(this);
            if (!$('#hkFehler').prop('hidden')) msvEingabeFehler('#hkFehler', msvPruefeFelder('#kantiresultateTabelle'), false);
            // nur echte Eingaben im Raster; Übernahmen aus dem Panel markiert syncField
            if (e.originalEvent) markGeaendert($(this).closest('tr'));
        });
    }

    function fillEmptyWithZero() {
        $('#kantiresultateTabelle tbody tr').each(function() {
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

    // Speichern
    $('#kantiresultateForm').on('submit', function(e) {
        e.preventDefault();
        // Werte über 100 oder keine Zahl: nicht speichern, sondern zeigen, wo
        if (msvEingabeFehler('#hkFehler', msvPruefeFelder('#kantiresultateTabelle'))) return;
        var $submitBtn = $('#rasterSpeichernBtn');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        fillEmptyWithZero();
        var selectedYear = $('#yearSelect').val();
        var formData = $(this).serialize() + '&year=' + selectedYear + '&jahr=' + selectedYear;

        $.ajax({
            url: 'kantiresultate/save_kantiresultate.php',
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


    // Rangliste
    $('#redirect-btn').on('click', function() { window.location.href = 'kantirang.php'; });

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


    /**
     * Berechnet pro Zeile: Summe, Bestpasse, Status-Dot, Filled-Klassen
     */
    function updateKantiRowStats() {
        $('#kantiresultateTabelle tbody tr').each(function() {
            const $inputs = $(this).find('input.small-input');
            if (!$inputs.length) return;
            let sum = 0, best = -1, bestIdx = -1, filled = 0, total = $inputs.length;

            $inputs.each(function(i) {
                $(this).removeClass('best-passe filled');
                const val = parseInt(this.value) || 0;
                if (this.value.trim() !== '' && this.value !== '0') {
                    filled++;
                    $(this).addClass('filled');
                }
                sum += val;
                if (val > best) { best = val; bestIdx = i; }
            });

            // Bestpasse hervorheben
            if (bestIdx >= 0 && best > 0) {
                $inputs.eq(bestIdx).addClass('best-passe');
            }

            // Summe-Zelle aktualisieren
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
        var $rows = $('#kantiresultateTabelle tbody tr[data-stand]');
        var total = $rows.length;
        var mit = $rows.filter('[data-stand="mit"]').length;
        var voll = $rows.filter('[data-voll="1"]').length;
        $('#progressText').html(mit + ' von ' + total + ' <span>Mitgliedern mit Resultaten</span>');
        $('#progressChips').html(
            (voll ? '<span class="ui-chip"><b>' + voll + '</b> mit allen 5 Passen</span>' : '') +
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
        var $tbody = $('#kantiresultateTabelle tbody');
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
            $tbody.append('<tr class="ui-leer" id="hkKeineTreffer"><td colspan="7">Keine Mitglieder für diese Auswahl. ' +
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
    $(document).on('input', '#kantiresultateTabelle input.small-input', function() {
        updateKantiRowStats();
    });

    // Mobile Cards für Kanti-Resultate generieren
    function buildMobileKantiCards() {
        const isMobile = window.matchMedia('(max-width: 767.98px)');
        if (!isMobile.matches) return;

        const table = document.getElementById('kantiresultateTabelle');
        const container = document.querySelector('#mobileCardsKanti .mobile-cards-scroll');
        if (!table || !container) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten gefunden</div></div>';
            return;
        }

        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten gefunden</div></div>';
            return;
        }

        let html = '';
        rows.forEach((row, idx) => {
            const cells = Array.from(row.querySelectorAll('td'));
            if (cells.length === 0) return;

            // Erste Zelle: Mitgliedername
            const memberName = row.querySelector('.hk-name')?.textContent?.trim() || cells[0]?.textContent?.trim() || 'Unbekannt';

            // Passe-Inputs extrahieren (Spalten 1-5)
            const inputs = Array.from(row.querySelectorAll('input'));
            if (inputs.length < 5) return;

            let fieldsHtml = '';
            const passeLabels = ['Passe 1', 'Passe 2', 'Passe 3', 'Passe 4', 'Passe 5'];

            // Berechne Summe + Bestpasse für Summary
            let sum = 0, best = -1, bestIdx = -1, hasAny = false;
            inputs.forEach((input, i) => {
                if (i >= 5) return;
                const val = parseInt(input.value) || 0;
                if (input.value.trim() !== '' && input.value !== '0') {
                    hasAny = true;
                }
                sum += val;
                if (val > best) { best = val; bestIdx = i; }
            });

            const summaryHtml = hasAny
                ? `<small class="text-muted">Total: ${sum}</small>`
                : '';

            inputs.forEach((input, i) => {
                if (i >= 5) return;
                const label = passeLabels[i];
                const inputName = input.name || '';
                const inputValue = input.value || '';
                const isBest = (i === bestIdx && best > 0);

                fieldsHtml += `
                    <div class="mb-3">
                        <label class="form-label fw-bold small">${label}${isBest ? ' &#127942;' : ''}</label>
                        <input type="number"
                               class="form-control passe-input-mobile${isBest ? ' best' : ''}"
                               data-name="${inputName}"
                               value="${inputValue}"
                               inputmode="numeric"
                               pattern="[0-9]*"
                               max="100">
                    </div>`;
            });

            html += `
            <div class="mobile-card" data-index="${idx}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                        <div class="fw-bold">${msvEsc(memberName)}</div>
                        ${summaryHtml}
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="mobile-card-body">
                    ${fieldsHtml}
                </div>
            </div>`;
        });

        container.innerHTML = html;

        // Event-Listener für Inputs: Sync zu Desktop-Tabelle
        container.querySelectorAll('input[data-name]').forEach(input => {
            input.addEventListener('input', function() {
                const inputName = this.getAttribute('data-name');
                const desktopInput = table.querySelector(`input[name="${inputName}"]`);
                if (desktopInput) {
                    desktopInput.value = this.value;
                    // Trigger input event für Validierung
                    $(desktopInput).trigger('input');
                    markGeaendert($(desktopInput).closest('tr'));
                }
            });

            // Keyboard navigation (Enter/Tab)
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { // Tab bleibt nativ, damit man das Raster verlassen kann
                    e.preventDefault();
                    const allInputs = Array.from(container.querySelectorAll('input[data-name]'));
                    const currentIndex = allInputs.indexOf(this);
                    const nextIndex = e.shiftKey ? currentIndex - 1 : currentIndex + 1;
                    if (allInputs[nextIndex]) {
                        allInputs[nextIndex].focus();
                        allInputs[nextIndex].select();
                    }
                }
            });

            // Focus: Select on focus
            input.addEventListener('focus', function() {
                if (this.value === '0') {
                    this.value = '';
                }
                this.select();
            });

        });
    }

    // Mobile Search Filter (global für inline oninput)
    window.filterMobileKanti = function(searchInput) {
        const query = searchInput.value.toLowerCase();
        const cards = document.querySelectorAll('#mobileCardsKanti .mobile-card');

        let visibleCount = 0;
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const isVisible = text.includes(query);
            card.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        const container = document.querySelector('#mobileCardsKanti .mobile-cards-scroll');
        const existingEmpty = container.querySelector('.mobile-cards-empty');
        if (visibleCount === 0 && !existingEmpty) {
            container.insertAdjacentHTML('beforeend', `
                <div class="mobile-cards-empty">
                    <i class="bi bi-search"></i>
                    <div>Keine Treffer gefunden</div>
                </div>`);
        } else if (visibleCount > 0 && existingEmpty) {
            existingEmpty.remove();
        }
    };

    // Resize-Listener für Mobile Cards
    let wasDesktop = window.matchMedia('(min-width: 768px)').matches;
    window.addEventListener('resize', function() {
        const isNowDesktop = window.matchMedia('(min-width: 768px)').matches;
        if (wasDesktop && !isNowDesktop) {
            buildMobileKantiCards();
        }
        wasDesktop = isNowDesktop;
    });

    // Veröffentlichen
    $('#publishChangelogBtn').on('click', async function() {
        const r = await msvConfirm('Ein Eintrag wird auf der Website angezeigt.', 'Änderung veröffentlichen?', 'Veröffentlichen');
        if (!r.isConfirmed) return;
        var selectedYear = $('#yearSelect').val();
        $.post('changelog_publish.php', {
            kategorie: 'resultate',
            tabelle: 'kantiresultate',
            jahr: selectedYear,
            beschreibung: 'Kantiresultate ' + selectedYear + ' aktualisiert',
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
        _saved: false,  // im Panel gespeichert → Liste beim Schliessen neu laden
        maxLen: 3,      // Kanti: 3-stellig (Passe bis 100)
        clampMax: 100,  // max 100
        saveUrl: 'kantiresultate/save_kantiresultate.php',

        buildIndex() {
            this.rows = [];
            const self = this;
            $('#kantiresultateTabelle tbody tr').each(function() {
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
                    '<input type="text" inputmode="numeric" autocomplete="off" aria-label="Passe ' + (i + 1) + '" maxlength="' + EntryPanel.maxLen + '">' +
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
            // Nach Speichern im Panel frisch laden, aber nie ungespeicherte Raster-Eingaben verwerfen
            if (this._saved && !geaendert.size) {
                this._saved = false;
                loadResultate($('#yearSelect').val());
            }
        },

        navigate(dir) {
            const n = this.idx + dir;
            if (n >= 0 && n < this.rows.length) this.open(n);
        },

        // Panel-Feld → Raster-Input synchronisieren
        syncField(pi, value) {
            if (this.idx < 0) return;
            const row = this.rows[this.idx];
            row.$inputs.eq(pi).val(value).trigger('input');
            markGeaendert(row.$tr);   // ungespeichert, bis das Panel speichert
        },

        // Filled/Best-Hervorhebung + Total im Panel
        refreshFields() {
            let sum = 0, best = -1, bestIdx = -1;
            const $fields = $('#entryPassenGrid .entry-passe-field');
            $fields.each(function(i) {
                const v = parseInt($(this).find('input').val(), 10) || 0;
                const raw = $(this).find('input').val().trim();
                $(this).removeClass('is-best');
                $(this).find('input').toggleClass('filled', raw !== '' && raw !== '0');
                sum += v;
                if (v > best) { best = v; bestIdx = i; }
            });
            if (bestIdx >= 0 && best > 0) $fields.eq(bestIdx).addClass('is-best');
            $('#entryTotalBadge').html('<span>Total</span>' + sum);
        },

        updateProgress() {
            zaehlen();
        },

        // Leere Felder nach einem späteren Wert mit 0 füllen (wie Raster-Save)
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
        // Über dem Maximum nicht still kappen, sondern markieren; beim Speichern meldet die Fehlerliste den Wert
        if (EntryPanel.clampMax !== null) this.setAttribute('aria-invalid', value !== '' && parseInt(value, 10) > EntryPanel.clampMax ? 'true' : 'false');
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
    $(document).on('click', '#kantiresultateTabelle tbody td:first-child', function() {
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
            else if (!$('#rasterSpeichernBtn').prop('disabled')) $('#kantiresultateForm').trigger('submit');
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
