<?php
// jmrang.php
include 'dbconnect.inc.php';

// Session-Kontrolle wie in jmresultate.php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Spezifische Z-Index Fixes für JM-Rang Tabellen
$page_specific_css = '
/* === Z-INDEX FIX FÜR JM-RANG TABELLEN === */
/* Table Title muss über allen sticky Elementen sein */
.table-wrapper .table-title {
    position: relative !important;
    z-index: 100 !important;
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%) !important;
    padding: 1rem 1.5rem !important;
    margin: 0 !important;
    border-bottom: 2px solid #dee2e6 !important;
}

/* Container darf nicht drüber gehen */
.table-responsive {
    position: relative;
    z-index: 1;
    overflow: auto;
}

/* Sticky Header */
#JMA thead,
#JMB thead {
    position: sticky;
    top: 0;
    z-index: 10 !important;
}

#JMA thead th,
#JMB thead th {
    position: sticky;
    top: 0;
    z-index: 10 !important;
    background-color: var(--light-color) !important;
}

/* Erste Spalte (Rang) */
#JMA th:first-child,
#JMA td:first-child,
#JMB th:first-child,
#JMB td:first-child {
    position: sticky !important;
    left: 0 !important;
    z-index: 5 !important;
    background-color: rgba(108, 117, 125, 0.02) !important;
    border-right: 2px solid #dee2e6 !important;
    text-align: center;
    font-weight: 600;
}

/* Zweite Spalte (Name) */
#JMA th:nth-child(2),
#JMA td:nth-child(2),
#JMB th:nth-child(2),
#JMB td:nth-child(2) {
    position: sticky !important;
    left: 60px !important;
    z-index: 5 !important;
    background-color: rgba(108, 117, 125, 0.02) !important;
    border-right: 2px solid #dee2e6 !important;
    min-width: 200px;
    font-weight: 500;
}

/* Letzte Spalte (Total) */
#JMA th:last-child,
#JMA td:last-child,
#JMB th:last-child,
#JMB td:last-child {
    position: sticky !important;
    right: 0 !important;
    z-index: 5 !important;
    background-color: rgba(108, 117, 125, 0.05) !important;
    font-weight: 700;
    text-align: center;
}

/* Header der sticky Spalten */
#JMA thead th:first-child,
#JMA thead th:nth-child(2),
#JMA thead th:last-child,
#JMB thead th:first-child,
#JMB thead th:nth-child(2),
#JMB thead th:last-child {
    z-index: 15 !important;
    background-color: var(--light-color) !important;
}

/* Vertikale Header */
#JMA .vertical-header,
#JMB .vertical-header {
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
    background-color: var(--light-color) !important;
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
                <div class="row mb-4">
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

                    <!-- Jahr-Auswahl in eigener Card -->
                    <div class="year-selection-card">
                        <div class="row align-items-center">
                            <div class="col-md-5">
                                <label for="yearSelect" class="form-label fw-bold">
                                    <i class="bi bi-calendar3 me-1"></i> Jahr auswählen:
                                </label>
                                <select id="yearSelect" class="form-select">
                                    <!-- Optionen werden per JavaScript eingefügt -->
                                </select>
                            </div>
                        </div>
                        <div class="info-card mt-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Hinweis:</strong> Die roten durchgestrichenen Werte sind Streicher und werden nicht in die Gesamtwertung einbezogen.
                        </div>
                    </div>

                    <!-- Button Toolbar -->
                    <div class="button-toolbar">
                        <div class="button-group">
                            <button class="btn btn-compact-standard btn-outline-info pdfrang-btn" type="button">
                                <i class="bi bi-file-pdf me-2"></i>
                                Rangliste nach Rang
                            </button>
                            <button class="btn btn-compact-standard btn-outline-info pdf-btn" type="button">
                                <i class="bi bi-file-pdf me-2"></i>
                                Rangliste nach Name
                            </button>
                            <button id="redirect-btn" class="btn btn-compact-standard btn-outline-success" type="button">
                                <i class="bi bi-pencil-square me-2"></i>
                                Bearbeiten
                            </button>
                        </div>
                        <div id="pdf-link"></div>
                    </div>

                    <!-- Nachrichten Container -->
                    <div id="message"></div>

                    <!-- Kategorie A Tabelle -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-star me-2"></i>
                            Jahresmeisterschaft Kat. A
                        </h5>
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

                    <!-- Kategorie B Tabelle -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-star-half me-2"></i>
                            Jahresmeisterschaft Kat. B
                        </h5>
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
                </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<script>
$(document).ready(function() {
    const basePath = '';
    const currentYear = new Date().getFullYear();

    // Toast-Funktion (standardisierte Version)
    function showToast(message, type = 'info') {
        if ($('#toast-container').length === 0) {
            $('body').append('<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>');
        }
        
        const toast = $('<div>')
            .addClass(`toast-message toast-${type}`)
            .html(`<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>${message}`);

        $('#toast-container').append(toast);

        setTimeout(() => {
            toast.addClass('show');
        }, 100);

        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Initialisierung des Jahres-Dropdowns
    function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        for (let year = 2024; year <= currentYear; year++) {
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
            $table.fadeTo(200, 1);
        });
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
                        showToast(parsed.error, 'error');
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
                showToast('Fehler beim Laden der Daten.', 'error');
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
        showToast('Lade Daten für Jahr ' + selectedYear, 'info');
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
                    showToast('PDF wurde erfolgreich generiert!', 'success');
                } else {
                    showToast('PDF konnte nicht generiert werden.', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Fehler beim Generieren des PDFs: ' + error, 'error');
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
                    showToast('PDF wurde erfolgreich generiert!', 'success');
                } else {
                    showToast('PDF konnte nicht generiert werden.', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Fehler beim Generieren des PDFs: ' + error, 'error');
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

    // Kontextmenü für Tabellen (Rechtsklick)
    $(document).on('contextmenu', '.table tbody tr', function(e) {
        e.preventDefault();
        const name = $(this).find('td:nth-child(2)').text();
        const rang = $(this).find('td:first-child').text();
        const total = $(this).find('td:last-child').text();
        
        showToast(`${name} - Rang: ${rang} - Total: ${total}`, 'info');
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
    //     showToast('CSV-Export erfolgreich!', 'success');
    // });
});
</script>

<?php
include 'footer.inc.php';
?>