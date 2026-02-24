<?php
// jmrang.php
include 'dbconnect.inc.php';

// Session-Kontrolle wie in jmresultate.php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSS für Hybrid-Layout (kompakte Tabelle + aufklappbare Details)
$page_specific_css = '
/* === JM-RANG: HYBRID-LAYOUT === */

/* Table Title */
.table-wrapper .table-title {
    position: relative !important;
    z-index: 100 !important;
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%) !important;
    padding: 1rem 1.5rem !important;
    margin: 0 !important;
    border-bottom: 2px solid #dee2e6 !important;
}

/* ===== Tabelle: separate borders gegen sticky-bleed ===== */
#JMA,
#JMB {
    border-collapse: separate !important;
    border-spacing: 0 !important;
}

/* Doppel-Borders vermeiden bei border-collapse:separate */
#JMA tbody td,
#JMB tbody td {
    border-top: none !important;
    border-bottom: 1px solid #dee2e6 !important;
}

/* thead selbst als opake Hintergrund-Schicht */
#JMA thead,
#JMB thead {
    position: sticky !important;
    top: 0 !important;
    z-index: 11 !important;
}

/* ===== HEADER: horizontal, NICHT vertikal ===== */
#JMA thead th,
#JMB thead th {
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
    background-color: var(--light-color) !important;
    vertical-align: bottom !important;
    /* Vertikale Rotation aus msv-styles.css zurücksetzen */
    writing-mode: horizontal-tb !important;
    text-orientation: initial !important;
    height: auto !important;
    min-width: auto !important;
    max-width: none !important;
    white-space: normal !important;
    overflow: visible !important;
    font-size: 0.8rem !important;
    padding: 0.5rem 0.4rem !important;
    font-weight: 600 !important;
    /* Border durch box-shadow ersetzen (kein bleed-through bei sticky) */
    border-bottom: none !important;
    border-top: none !important;
    box-shadow: inset 0 -2px 0 #dee2e6 !important;
}

/* Spaltenbreiten */
.jm-th-rang { width: 55px !important; }
.jm-th-name { min-width: 150px !important; }
.jm-th-result { min-width: 70px !important; max-width: 120px !important; }
.jm-th-total { width: 75px !important; }
.jm-th-toggle { width: 36px !important; }

/* Klickbare Hauptzeilen */
.jm-main-row { cursor: pointer; }

#JMA tbody tr.jm-main-row:hover td,
#JMB tbody tr.jm-main-row:hover td {
    background-color: rgba(108, 117, 125, 0.06) !important;
}

/* Toggle-Button */
.jm-toggle-btn {
    color: #6c757d !important;
    text-decoration: none !important;
    font-size: 1rem !important;
}
.jm-toggle-btn i {
    transition: transform 0.2s ease;
}
.jm-toggle-btn.expanded i {
    transform: rotate(180deg);
}
.jm-toggle-btn:hover {
    color: var(--primary-color) !important;
}

/* ===== DETAIL-PANEL ===== */
.jm-detail-row > td {
    padding: 0 !important;
    border-top: none !important;
    /* Override .table td:first-child (width:60px, text-align:center) */
    width: auto !important;
    text-align: left !important;
    background-color: transparent !important;
    font-weight: normal !important;
}

.jm-detail-panel {
    background: #f8fafb !important;
    border-top: 1px solid #e2e8f0 !important;
    border-bottom: 2px solid #dee2e6 !important;
    padding: 0.75rem 1.25rem !important;
    text-align: left !important;
}

/* Card-Grid für Detail-Panel */
.jm-detail-list {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important;
    gap: 0.5rem !important;
}

.jm-detail-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.5rem 0.65rem;
    text-align: center;
    transition: box-shadow 0.15s;
}

.jm-detail-card:hover {
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

/* Hauptwettbewerbe hervorheben */
.jm-detail-card-main {
    border-left: 3px solid var(--primary-color, #0d6efd);
}

/* Streicher-Card */
.jm-detail-card-streicher {
    opacity: 0.55;
    border-style: dashed;
}

.jm-detail-card-name {
    font-size: 0.72rem;
    color: #6c757d;
    margin-bottom: 0.2rem;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.jm-detail-card-pts {
    font-size: 1.05rem;
    font-weight: 700;
    color: #212529;
}

.jm-detail-card-streicher .jm-detail-card-pts {
    color: #dc3545;
    text-decoration: line-through;
}

/* Kein Resultat */
.jm-detail-card-empty .jm-detail-card-pts {
    color: #adb5bd;
    font-weight: 400;
}

/* Total-Zeile */
.jm-detail-sum {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: rgba(40, 167, 69, 0.07);
    border-radius: 0.3rem;
    border: 1px solid rgba(40, 167, 69, 0.2);
    font-weight: 700;
    font-size: 0.9rem;
}

.jm-detail-sum-val {
    color: #198754;
    font-size: 1rem;
}

/* Tablet */
@media (max-width: 1199.98px) {
    .jm-detail-list {
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    }
}

/* Mobile */
@media (max-width: 767.98px) {
    .form-control, .form-select,
    input[type="text"], input[type="number"], select {
        min-height: 48px !important;
        font-size: 16px !important;
    }
    .btn {
        min-height: 48px !important;
        font-size: 16px !important;
        padding: 0.5rem 1rem !important;
    }
    .jm-detail-list {
        grid-template-columns: repeat(2, 1fr) !important;
    }

    /* JM Mobile Card Styles */
    .jm-mobile-card .mobile-card-header {
        padding: 0.75rem 1rem;
    }
    .jm-mobile-rang {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #e9ecef;
        font-weight: 700;
        font-size: 0.85rem;
        color: #495057;
        flex-shrink: 0;
    }
    .rank-1 .jm-mobile-rang { background: #ffd700; color: #5a4800; }
    .rank-2 .jm-mobile-rang { background: #c0c0c0; color: #3a3a3a; }
    .rank-3 .jm-mobile-rang { background: #cd7f32; color: #fff; }

    .jm-mobile-total {
        font-weight: 700;
        font-size: 0.95rem;
        color: #198754;
        white-space: nowrap;
    }

    /* Detail-Panel innerhalb Mobile Card Body */
    .jm-mobile-card .mobile-card-body {
        padding: 0 !important;
    }
    .jm-mobile-card .mobile-card-body .jm-detail-panel {
        border-top: none !important;
        border-bottom: none !important;
        padding: 0.75rem !important;
    }
    .jm-mobile-card .jm-detail-card {
        padding: 0.4rem 0.5rem;
    }
    .jm-mobile-card .jm-detail-card-name {
        font-size: 0.68rem;
    }
    .jm-mobile-card .jm-detail-card-pts {
        font-size: 0.95rem;
    }
}
';

include 'header.inc.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-10 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-trophy me-2"></i>
                            Jahresmeisterschaft Ranglisten
                        </h2>
                    </div>
                </div>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                <form id="jmresultateForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Jahr-Auswahl + Aktionen nebeneinander -->
                    <div class="d-flex flex-wrap gap-3 align-items-start mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                            <i class="bi bi-calendar3 me-1"></i>Jahr:
                        </label>
                        <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
                    </div>

                    <!-- Aktionsbereich (Bootstrap Collapse) -->
                    <div class="card action-card mb-0">
                        <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                             data-bs-toggle="collapse" data-bs-target="#jmrangActions"
                             aria-expanded="false" aria-controls="jmrangActions">
                            <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                            <i class="bi bi-chevron-down action-chevron"></i>
                        </div>
                        <div class="collapse" id="jmrangActions">
                            <div class="card-body pt-2 pb-3 px-3">
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <button id="redirect-btn" type="button" class="btn btn-success w-100">
                                            <i class="bi bi-pencil-square me-2"></i>Bearbeiten
                                        </button>
                                    </div>
                                </div>
                                <div class="border-top pt-2">
                                    <small class="text-muted d-block mb-2"><i class="bi bi-download me-1"></i>Exporte</small>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <button class="pdfrang-btn btn btn-outline-danger btn-sm w-100">
                                                <i class="bi bi-file-pdf me-1"></i>nach Rang
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button class="pdf-btn btn btn-outline-danger btn-sm w-100">
                                                <i class="bi bi-file-pdf me-1"></i>nach Name
                                            </button>
                                        </div>
                                    </div>
                                    <div id="pdf-link" class="mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div><!-- Ende flex-row Jahr+Aktionen -->

                    <div class="info-card mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Hinweis:</strong> Die roten durchgestrichenen Werte sind Streicher und werden nicht in die Gesamtwertung einbezogen.
                    </div>

                    <!-- Kategorie A Tabelle -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-star me-2"></i>
                            Jahresmeisterschaft Kat. A
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
        const yearSelect = $('#yearSelect').empty();
        for (let year = currentYear; year >= currentYear - 3; year--) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
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
                scrollContainer.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
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
        msvToast('Lade Daten für Jahr ' + selectedYear, 'info');
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
            window.location.href = 'https://jahresmeisterschaft.msvwilen.ch/inc/jmresultate.php';
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
                year: selectedYear
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
                year: selectedYear
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
        const total = $(this).find('td:last-child').text();

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

<?php
include 'footer.inc.php';
?>