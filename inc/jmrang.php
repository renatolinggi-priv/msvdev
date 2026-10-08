<?php
// jmrang.php
include 'dbconnect.inc.php';


// CSS für Hybrid-Layout (kompakte Tabelle + aufklappbare Details)
$page_specific_css = '
/* === JM-RANG: HYBRID-LAYOUT === */

/* Tabellentitel Kat. A / Kat. B */
.table-wrapper .table-title {
    position: relative !important;
    z-index: 100 !important;
    background: var(--ui-flaeche) !important;
    padding: 12px 20px !important;
    margin: 0 !important;
    border-bottom: 1px solid var(--ui-linie) !important;
}

/* ===== Tabelle: separate borders gegen sticky-bleed ===== */
#JMA, #JMB { border-collapse: separate !important; border-spacing: 0 !important; }
#JMA tbody td, #JMB tbody td { vertical-align: middle; border-top: none !important; border-right: none !important; border-bottom: 1px solid var(--ui-linie-zart) !important; }
#JMA thead, #JMB thead { position: sticky !important; top: 0 !important; z-index: 11 !important; }
/* Kopfzeile horizontal (Rotation aus msv-styles.css zurücksetzen), Linie per box-shadow gegen sticky-bleed */
#JMA thead th, #JMB thead th {
    position: sticky !important; top: 0 !important; z-index: 10 !important;
    background-color: var(--ui-flaeche-2) !important; color: var(--ui-text-2);
    vertical-align: bottom !important; writing-mode: horizontal-tb !important; text-orientation: initial !important;
    height: auto !important; min-width: auto !important; max-width: none !important; white-space: normal !important; overflow: visible !important;
    font-size: .75rem !important; font-weight: 600 !important; text-transform: none !important; letter-spacing: 0 !important;
    padding: 8px 6px !important; border-top: none !important; border-bottom: none !important; box-shadow: inset 0 -1px 0 var(--ui-linie) !important;
}

/* Spaltenbreiten (width statt min-width, da #JMx thead th min-width:auto erzwingt) */
.jm-th-rang  { width: 55px !important; }
.jm-th-result{ width: 84px !important; }
.jm-th-total { width: 90px !important; }
.jm-th-toggle{ width: 40px !important; }
/* Lange Anlass-Namen in der Kopfzeile kürzen (voller Name via Tooltip) */
.jm-th-label { display: block; max-width: 78px; margin: 0 auto; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.jm-main-row { cursor: pointer; }
#JMA tbody tr.jm-main-row:hover td, #JMB tbody tr.jm-main-row:hover td { background-color: var(--ui-flaeche-2) !important; }
/* Trennzeile «Ohne gewertetes JM-Resultat» */
.jm-group-row td.jm-group-cell,
.jm-group-row:hover td.jm-group-cell { padding: 6px 12px !important; background: var(--ui-grund) !important; color: var(--ui-text-2) !important; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; border-top: 1px solid var(--ui-linie) !important; border-bottom: 1px solid var(--ui-linie) !important; }
.jm-group-row td.jm-group-cell i { display: none; }
.jm-result-cell   { font-variant-numeric: tabular-nums; }
.jm-cell-strichen { color: var(--ui-k-rot); text-decoration: line-through; }
.jm-total-cell    { color: var(--ui-text); font-variant-numeric: tabular-nums; font-size: 1rem; }
.jm-rang-cell     { color: var(--ui-text-2); }
.jm-toggle-btn { color: var(--ui-text-3) !important; text-decoration: none !important; font-size: 1rem !important; }
.jm-toggle-btn i { transition: transform .2s ease; }
.jm-toggle-btn.expanded i { transform: rotate(180deg); }
.jm-toggle-btn:hover { color: var(--ui-akzent) !important; }

/* ===== Aufschlüsselung (Detail-Zeile) ===== */
.jm-detail-row > td { padding: 0 !important; border-top: none !important; width: auto !important; text-align: left !important; background-color: transparent !important; font-weight: normal !important; }
.jm-detail-panel { padding: 14px 20px !important; text-align: left !important; background: var(--ui-grund) !important; border-top: 1px solid var(--ui-linie) !important; border-bottom: 1px solid var(--ui-rand) !important; }
.jm-detail-groups { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; align-items: start; }
.jm-detail-group { overflow: hidden; background: var(--ui-flaeche); border: 1px solid var(--ui-rand); border-radius: 10px; }
.jm-detail-group-head { display: flex; justify-content: space-between; align-items: baseline; padding: 8px 12px; background: var(--ui-flaeche-2); border-bottom: 1px solid var(--ui-linie); }
.jm-detail-group-title { font-size: .82rem; font-weight: 600; color: var(--ui-text); }
.jm-detail-group-meta  { font-size: .72rem; color: var(--ui-text-2); }
.jm-detail-lines { padding: 4px 6px; }
.jm-detail-line { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; padding: 5px 8px; border-radius: 6px; font-size: .85rem; }
.jm-detail-line + .jm-detail-line { border-top: 1px solid var(--ui-linie-zart); }
.jm-detail-line:hover { background: var(--ui-flaeche-2); }
.jm-line-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--ui-text); }
.jm-line-pts  { display: inline-flex; align-items: baseline; gap: .35rem; flex-shrink: 0; white-space: nowrap; }
.jm-line-val  { font-weight: 700; color: var(--ui-text); font-variant-numeric: tabular-nums; }
.jm-line-max  { font-size: .72rem; color: var(--ui-text-3); }
.jm-line-empty .jm-line-name,
.jm-line-empty .jm-line-val { font-weight: 400; color: #b8c0cc; }
.jm-detail-line.gestrichen { opacity: .75; }
.jm-detail-line.gestrichen .jm-line-val { color: var(--ui-k-rot); text-decoration: line-through; }
.jm-line-tag { padding: 1px 6px; border-radius: 6px; background: #fdecea; color: var(--ui-k-rot); font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.jm-detail-subtotal { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: var(--ui-flaeche-2); border-top: 1px solid var(--ui-linie); font-size: .8rem; font-weight: 600; color: var(--ui-text-2); }
.jm-detail-subtotal span:last-child { font-weight: 700; color: var(--ui-text); font-variant-numeric: tabular-nums; }
.jm-detail-total { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding: 10px 14px; background: var(--ui-ok-bg); border: 1px solid var(--ui-ok-rand); border-radius: var(--ui-rad); font-size: .95rem; font-weight: 700; color: var(--ui-ok-fg); }
.jm-detail-total-val { font-size: 1.1rem; color: var(--ui-ok-fg); font-variant-numeric: tabular-nums; }
.jm-detail-total.jm-detail-total-offen { background: var(--ui-flaeche-2); border-color: var(--ui-rand); color: var(--ui-text-2); font-size: .85rem; font-weight: 600; }

/* ===== Handy ===== */
@media (max-width: 767.98px) {
    .jm-detail-groups { grid-template-columns: 1fr !important; }
    .jm-mobile-card .mobile-card-header { padding: .75rem 1rem; }
    .jm-mobile-rang { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%; background: var(--ui-flaeche-2); border: 1px solid var(--ui-rand); font-size: .85rem; font-weight: 700; color: var(--ui-text-2); }
    .rank-1 .jm-mobile-rang { background: #ffd700; border-color: #ffd700; color: #5a4800; }
    .rank-2 .jm-mobile-rang { background: #c0c0c0; border-color: #c0c0c0; color: #3a3a3a; }
    .rank-3 .jm-mobile-rang { background: #cd7f32; border-color: #cd7f32; color: #fff; }
    .jm-mobile-total { white-space: nowrap; font-size: .95rem; font-weight: 700; color: var(--ui-text); }
    .jm-mobile-card .mobile-card-body { padding: 0 !important; }
    .jm-mobile-card .mobile-card-body .jm-detail-panel { padding: .75rem !important; border-top: none !important; border-bottom: none !important; }
}
';

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <!-- Äusserer weisser Container -->
            <div class="main-content-wrapper content-width-wide">
                <!-- Header ausserhalb des inneren Containers -->
                <?php
                $page_title = "Jahresmeisterschaft Ranglisten";
                $page_title_after = '<button type="button" class="btn-help" data-help="jmrang.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_show_mobile = true;
                ob_start(); ?>
<button type="button" class="btn-help" data-help="jmrang.dokumente" aria-label="Hilfe"></button>
<button type="button" class="btn btn-outline-info btn-sm pdfrang-btn"><i class="bi bi-file-pdf me-1"></i><span>Rangliste (nach Rang)</span></button>
<button type="button" class="btn btn-outline-info btn-sm msv-druck" data-druck-doctype="jmrang" data-druck-label="JM Rangliste" data-druck-script="generate_pdf_jm.php" data-druck-job="JM Rangliste nach Rang" data-druck-linkprefix="" aria-label="Rangliste nach Rang drucken"><i class="bi bi-printer"></i></button>
<button type="button" class="btn btn-outline-info btn-sm pdf-btn"><i class="bi bi-file-pdf me-1"></i><span>Rangliste (nach Name)</span></button>
<button type="button" class="btn btn-outline-info btn-sm msv-druck" data-druck-doctype="jmrang" data-druck-label="JM Rangliste" data-druck-script="generate_pdf_all_results.php" data-druck-job="JM Rangliste nach Name" data-druck-linkprefix="jmrang/" aria-label="Rangliste nach Name drucken"><i class="bi bi-printer"></i></button>
<button id="redirect-btn" type="button" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Resultate bearbeiten </button>
                <?php $page_actions = ob_get_clean();
                include 'partials/page_header.inc.php'; ?>

                <!-- Weisser Hintergrund-Container -->
                <div class="content-background">
                <form id="jmresultateForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <div id="pdf-link"></div>

                    <!-- Kategorie A Tabelle -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-star me-2"></i>
                            Jahresmeisterschaft Kat. A <button type="button" class="btn-help" data-help="jmrang.rangliste" aria-label="Hilfe"></button>
                        </h5>

                        <!-- Desktop: Tabelle -->
                        <div class="desktop-table-container">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="JMA">
                                    <thead>
                                        <!-- Dynamisch per AJAX -->
                                    </thead>
                                    <tbody>
                                        <!-- Dynamisch per AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Mobile: Cards -->
                        <div class="mobile-cards-container" id="mobileCardsJMA">
                            <div class="mobile-search">
                                <div class="position-relative">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" class="form-control" placeholder="Suchen..."
                                           oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsJMA')">
                                </div>
                            </div>
                            <div class="mobile-cards-scroll">
                                <!-- Cards werden per JavaScript generiert -->
                            </div>
                        </div>
                    </div>

                    <!-- Kategorie B Tabelle -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-star-half me-2"></i>
                            Jahresmeisterschaft Kat. B
                        </h5>

                        <!-- Desktop: Tabelle -->
                        <div class="desktop-table-container">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="JMB">
                                    <thead>
                                        <!-- Dynamisch per AJAX -->
                                    </thead>
                                    <tbody>
                                        <!-- Dynamische Inhalte hier -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Mobile: Cards -->
                        <div class="mobile-cards-container" id="mobileCardsJMB">
                            <div class="mobile-search">
                                <div class="position-relative">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" class="form-control" placeholder="Suchen..."
                                           oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsJMB')">
                                </div>
                            </div>
                            <div class="mobile-cards-scroll">
                                <!-- Cards werden per JavaScript generiert -->
                            </div>
                        </div>
                    </div>
                </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const basePath = '';
    const currentYear = new Date().getFullYear();

    // Initialisierung des Jahres-Dropdowns
    function initializeYearDropdown() {
        msvJahrAuswahl('#yearSelect');
    }

    // Tabelleninhalt aktualisieren mit Animation
    function updateTable(tableSelector, theadHtml, tbodyHtml) {
        const $table = $(tableSelector);

        // Fade out
        $table.fadeTo(200, 0.5, function() {
            $table.find('thead').html(theadHtml);
            $table.find('tbody').html(tbodyHtml);

            // Tooltips für Resultat-Zellen hinzufügen
            $table.find('td[data-toggle="tooltip"]').each(function() {
                new bootstrap.Tooltip(this);
            });

            // Fade in
            $table.fadeTo(200, 1, function() {
                // Mobile Cards nach dem Laden generieren
                if (tableSelector === '#JMA') {
                    buildMobileCardsJMA();
                } else if (tableSelector === '#JMB') {
                    buildMobileCardsJMB();
                }
            });
        });
    }

    // Custom Mobile Cards Builder für JM-Ranglisten
    // Verarbeitet nur .jm-main-row und übernimmt .jm-detail-panel aus der zugehörigen Detail-Zeile
    function buildJMMobileCards(tableSelector, containerSelector) {
        MSVMobileCards.initResponsive(function() {
            const table = document.querySelector(tableSelector);
            const container = document.querySelector(containerSelector);
            if (!table || !container) return;

            const scrollContainer = container.querySelector('.mobile-cards-scroll');
            if (!scrollContainer) return;

            const mainRows = table.querySelectorAll('tbody tr.jm-main-row');
            if (mainRows.length === 0) {
                scrollContainer.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten gefunden</div></div>';
                return;
            }

            let html = '';
            mainRows.forEach((row, idx) => {
                const cells = Array.from(row.querySelectorAll('td'));
                if (cells.length === 0) return;

                const rowIdx = row.dataset.row;
                const rang = cells[0]?.textContent?.trim() || '';
                const name = cells[1]?.textContent?.trim() || '';
                // Total ist die vorletzte Spalte (vor dem Toggle-Button)
                const totalCell = cells[cells.length - 2] || cells[cells.length - 1];
                const total = totalCell?.textContent?.trim() || '';

                // Rank-Klasse für Top 3
                const rankNum = parseInt(rang) || 0;
                let rankClass = '';
                if (rankNum >= 1 && rankNum <= 3) rankClass = ' rank-' + rankNum;

                // Detail-Panel HTML aus der zugehörigen .jm-detail-row übernehmen
                const detailRow = table.querySelector('tr.jm-detail-row[data-row="' + rowIdx + '"]');
                let detailHtml = '';
                if (detailRow) {
                    const panel = detailRow.querySelector('.jm-detail-panel');
                    if (panel) detailHtml = panel.outerHTML;
                }

                html += '<div class="mobile-card jm-mobile-card' + rankClass + '" data-index="' + idx + '">' +
                    '<div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">' +
                        '<div class="d-flex align-items-center gap-2">' +
                            '<span class="jm-mobile-rang">' + rang + '</span>' +
                            '<span class="fw-bold">' + name + '</span>' +
                        '</div>' +
                        '<div class="d-flex align-items-center gap-2">' +
                            '<span class="jm-mobile-total">' + total + '</span>' +
                            '<i class="bi bi-chevron-down"></i>' +
                        '</div>' +
                    '</div>' +
                    '<div class="mobile-card-body">' + detailHtml + '</div>' +
                '</div>';
            });

            scrollContainer.innerHTML = html;
        });
    }

    // Mobile Cards für JM Kat. A generieren
    function buildMobileCardsJMA() {
        buildJMMobileCards('#JMA', '#mobileCardsJMA');
    }

    // Mobile Cards für JM Kat. B generieren
    function buildMobileCardsJMB() {
        buildJMMobileCards('#JMB', '#mobileCardsJMB');
    }

    // Generischer AJAX-Aufruf mit verbessertem Loading
    function loadData(url, params, targetSelector) {
        // Zeige einen schöneren Ladeindikator
        $(targetSelector).find('tbody').html(
            '<tr><td colspan="100%" class="loading-indicator">' +
            '<div class="spinner-border spinner-border-sm me-2"></div>' +
            'Lade Daten...' +
            '</td></tr>'
        );
        
        $.ajax({
            url: basePath + url,
            type: 'GET',
            data: params,
            success: function(response) {
                try {
                    const parsed = typeof response === 'string' ? JSON.parse(response) : response;
                    if (parsed.thead && parsed.tbody) {
                        updateTable(targetSelector, parsed.thead, parsed.tbody);
                    } else if (parsed.error) {
                        msvToast(parsed.error, 'error');
                        $(targetSelector).find('tbody').html(
                            '<tr><td colspan="100%" class="text-center text-danger">' +
                            '<i class="bi bi-exclamation-triangle me-2"></i>' +
                            parsed.error +
                            '</td></tr>'
                        );
                    } else {
                        // Fallback für HTML-Response
                        $(targetSelector).html(response);
                    }
                } catch (e) {
                    // Falls kein JSON, gehe davon aus, dass es HTML ist
                    $(targetSelector).html(response);
                }
            },
            error: function(xhr, status, error) {
                console.error('Fehler beim Laden von ' + url + ':', error);
                msvToast('Fehler beim Laden der Daten.', 'error');
                $(targetSelector).find('tbody').html(
                    '<tr><td colspan="100%" class="text-center text-danger">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Fehler beim Laden der Daten' +
                    '</td></tr>'
                );
            }
        });
    }

    // Spezifische Funktionen zum Laden von JMA und JMB
    function loadJMA(year) {
        loadData('jmrang/load_jm.php', {
            year: year,
            kategorie: 'Kat. A'
        }, '#JMA');
    }

    function loadJMB(year) {
        loadData('jmrang/load_jm.php', {
            year: year,
            kategorie: 'Kat. B'
        }, '#JMB');
    }

    // Event-Handler für Jahresauswahl
    $('#yearSelect').on('change', function() {
        const selectedYear = $(this).val();
        loadJMA(selectedYear);
        loadJMB(selectedYear);
    });

    // Initialisierung
    initializeYearDropdown();
    const initialYear = $('#yearSelect').val();
    loadJMA(initialYear);
    loadJMB(initialYear);

    // Redirect-Button mit Animation
    $('#redirect-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Lade...');
        
        setTimeout(() => {
            window.location.href = 'jmresultate.php';
        }, 500);
    });

    // PDF-Generierung nach Rang
    $('.pdfrang-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');
        
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'jmrang/generate_pdf_jm.php',
            type: 'GET',
            dataType: 'json',
            data: {
                year: selectedYear,
                orientation: window.MsvDruck ? MsvDruck.orientierung('jmrang', 'landscape') : 'landscape' // Format aus dem Druckprofil
            },
            success: function(response) {
                if (response.pdf_link) {
                    // PDF direkt herunterladen
                    const link = document.createElement('a');
                    link.href = response.pdf_link;
                    link.download = response.pdf_link.split('/').pop(); // Dateiname extrahieren
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // PDF-Link Container leeren nach Download
                    $('#pdf-link').empty();
                    msvToast('PDF wurde erfolgreich generiert!', 'success');
                } else {
                    msvToast('PDF konnte nicht generiert werden.', 'error');
                }
            },
            error: function(xhr, status, error) {
                msvToast('Fehler beim Generieren des PDFs: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // PDF-Generierung nach Name
    $('.pdf-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');
        
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'jmrang/generate_pdf_all_results.php',
            type: 'GET',
            dataType: 'json',
            data: {
                year: selectedYear,
                orientation: window.MsvDruck ? MsvDruck.orientierung('jmrang', 'landscape') : 'landscape' // Format aus dem Druckprofil
            },
            success: function(response) {
                if (response.pdf_link) {
                    // PDF direkt herunterladen
                    const link = document.createElement('a');
                    link.href = 'jmrang/' + response.pdf_link;
                    link.download = response.pdf_link.split('/').pop(); // Dateiname extrahieren
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // PDF-Link Container leeren nach Download
                    $('#pdf-link').empty();
                    msvToast('PDF wurde erfolgreich generiert!', 'success');
                } else {
                    msvToast('PDF konnte nicht generiert werden.', 'error');
                }
            },
            error: function(xhr, status, error) {
                msvToast('Fehler beim Generieren des PDFs: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Tastenkombinationen für Power-User
    $(document).on('keydown', function(e) {
        // Strg/Cmd + P = PDF nach Rang
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            $('.pdfrang-btn').click();
        }
        // Strg/Cmd + E = Bearbeiten
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            e.preventDefault();
            $('#redirect-btn').click();
        }
    });

    // Expand/Collapse Detail-Zeilen (Klick auf ganze Zeile oder Button)
    $(document).on('click', '.jm-main-row', function() {
        const rowIdx = $(this).data('row');
        const $detail = $(`tr.jm-detail-row[data-row="${rowIdx}"]`);
        const $btn = $(this).find('.jm-toggle-btn');

        $detail.toggle();
        $btn.toggleClass('expanded');
    });

    // Kontextmenü für Tabellen (Rechtsklick)
    $(document).on('contextmenu', '.table tbody tr.jm-main-row', function(e) {
        e.preventDefault();
        const name = $(this).find('td:nth-child(2)').text();
        const rang = $(this).find('td:first-child').text();
        const total = $(this).find('.jm-total-cell').text();

        msvToast(`${name} - Rang: ${rang} - Total: ${total}`, 'info');
    });

    // Export als CSV Funktionalität (optional)
    function exportTableToCSV(tableId, filename) {
        const table = document.getElementById(tableId);
        let csv = [];
        
        // Headers
        const headers = [];
        $(table).find('thead th').each(function() {
            headers.push($(this).text().trim());
        });
        csv.push(headers.join(';'));
        
        // Rows
        $(table).find('tbody tr').each(function() {
            const row = [];
            $(this).find('td').each(function() {
                row.push($(this).text().trim().replace(/\s+/g, ' '));
            });
            csv.push(row.join(';'));
        });
        
        // Download
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
    }

    // Optional: Export-Buttons hinzufügen
    // $('.button-group').append(
    //     '<button class="btn btn-outline-secondary export-csv-a" type="button">' +
    //     '<i class="bi bi-file-earmark-spreadsheet me-2"></i>Export Kat. A (CSV)' +
    //     '</button>'
    // );
    
    // $(document).on('click', '.export-csv-a', function() {
    //     exportTableToCSV('JMA', 'jahresmeisterschaft_kat_a.csv');
    //     msvToast('CSV-Export erfolgreich!', 'success');
    // });
});
</script>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray), Profil «JM Rangliste» (A4 quer). Achtung Pfad-Asymmetrie der Generatoren:
// generate_pdf_jm.php liefert 'jmrang/dat/…', generate_pdf_all_results.php nur 'dat/…' → linkPrefix am Button.
MsvDruck.resolve('jmrang', (btn) => {
    const jahr = document.getElementById('yearSelect').value;
    return {
        url: 'jmrang/' + btn.dataset.druckScript + '?year=' + encodeURIComponent(jahr)
            + '&orientation=' + MsvDruck.orientierung('jmrang', 'landscape'),
        jobName: btn.dataset.druckJob + ' ' + jahr,
        linkPrefix: btn.dataset.druckLinkprefix || ''
    };
});
</script>
<?php
include 'footer.inc.php';
?>