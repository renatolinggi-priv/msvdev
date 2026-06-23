<?php
//kantiang.php
include 'dbconnect.inc.php';

// Session-Kontrolle wie in jmresultate.php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mobile-Optimierung für Kantirang
$page_specific_css = '
/* Mobile-Optimierung (WCAG AAA Touch Targets) */
@media (max-width: 767.98px) {
    .form-control,
    .form-select,
    input[type="text"],
    input[type="number"],
    select {
        min-height: 48px !important;
        font-size: 16px !important; /* Verhindert iOS Auto-Zoom */
    }

    .btn {
        min-height: 48px !important;
        font-size: 16px !important;
        padding: 0.5rem 1rem !important;
    }
}
';

include 'header.inc.php';
?>
<!-- Kantirang.php HTML-Gerüst nach jmrang.php Vorbild -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xxl-8 col-xl-9 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-trophy me-2"></i>
                            Kantonalstich Ranglisten
                        </h2>
                    </div>
                </div>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                <form id="kantiresultateForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Jahr-Auswahl -->
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                            <i class="bi bi-calendar3 me-1"></i>Jahr:
                        </label>
                        <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
                    </div>
                    <!-- Export-Toolbar (einheitlich mit endschrang.php) -->
                    <div class="export-toolbar mb-4">
                        <div class="export-toolbar-head">
                            <i class="bi bi-file-earmark-arrow-down"></i>
                            <span>Dokumente erstellen</span>
                            <button id="redirect-btn" type="button" class="btn btn-outline-primary btn-sm ms-auto">
                                <i class="bi bi-pencil-square me-1"></i>Resultate bearbeiten
                            </button>
                        </div>
                        <div class="export-group-btns">
                            <button class="btn btn-outline-info btn-sm pdf-btn">
                                <i class="bi bi-file-pdf me-1"></i><span>Rangliste</span>
                            </button>
                        </div>
                        <div id="pdf-link" class="mt-2"></div>
                    </div>

                    <!-- Kategorie A Tabelle -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-star me-2"></i>
                            Kantonalstich Kat. A
                        </h5>

                        <!-- Desktop: Tabelle -->
                        <div class="desktop-table-container">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="KantonalA">
                                    <thead>
                                        <tr>
                                            <th scope="col">Rang</th>
                                            <th scope="col">Name</th>
                                            <th scope="col" class="result-column">Passe 1</th>
                                            <th scope="col" class="result-column">Passe 2</th>
                                            <th scope="col" class="result-column">Passe 3</th>
                                            <th scope="col" class="result-column">Passe 4</th>
                                            <th scope="col" class="result-column">Passe 5</th>
                                            <th scope="col">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dynamisch per AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Mobile: Cards -->
                        <div class="mobile-cards-container" id="mobileCardsKatA">
                            <div class="mobile-search">
                                <div class="position-relative">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" class="form-control" placeholder="Suchen..."
                                           oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsKatA')">
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
                            Kantonalstich Kat. B
                        </h5>

                        <!-- Desktop: Tabelle -->
                        <div class="desktop-table-container">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="KantonalB">
                                    <thead>
                                        <tr>
                                            <th scope="col">Rang</th>
                                            <th scope="col">Name</th>
                                            <th scope="col" class="result-column">Passe 1</th>
                                            <th scope="col" class="result-column">Passe 2</th>
                                            <th scope="col" class="result-column">Passe 3</th>
                                            <th scope="col" class="result-column">Passe 4</th>
                                            <th scope="col" class="result-column">Passe 5</th>
                                            <th scope="col">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dynamisch per AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Mobile: Cards -->
                        <div class="mobile-cards-container" id="mobileCardsKatB">
                            <div class="mobile-search">
                                <div class="position-relative">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" class="form-control" placeholder="Suchen..."
                                           oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsKatB')">
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
    // Initialisierung des Jahres-Dropdowns
    function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        const currentYear = new Date().getFullYear();
        console.log('Current year:', currentYear); // Debug-Ausgabe
        
        for (let year = currentYear; year >= currentYear - 3; year--) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }

        console.log('Selected year after init:', yearSelect.val()); // Debug-Ausgabe
    }

    document.addEventListener('DOMContentLoaded', function() {
        var redirectButton = document.getElementById('redirect-btn');
        redirectButton.addEventListener('click', function() {
            console.log('Button clicked, redirecting...');
            window.location.href = 'https://jahresmeisterschaft.msvwilen.ch/inc/kantiresultate.php';
        });
    });

    $(document).ready(function() {
        var basePath = '';

        // Kantiresultate A laden
        function loadKantonala() {
            var selectedYear = $('#yearSelect').val();
            console.log('Loading Kantonal A for year:', selectedYear); // Debug-Ausgabe
            $.ajax({
                url: basePath + 'kantirang/load_kantonal.php',
                type: 'GET',
                data: {
                    year: selectedYear,
                    kat: 'A'
                },
                success: function(response) {
                    console.log('Kantonal A response:', response); // Debug-Ausgabe
                    $('#KantonalA tbody').html(response);
                    // Mobile Cards generieren
                    buildMobileCardsKatA();
                },
                error: function(xhr, status, error) {
                    console.error('Error loading Kantonal A:', error);
                }
            });
        }

        // Kantiresultate B laden
        function loadKantonalb() {
            var selectedYear = $('#yearSelect').val();
            console.log('Loading Kantonal B for year:', selectedYear); // Debug-Ausgabe
            $.ajax({
                url: basePath + 'kantirang/load_kantonal.php',
                type: 'GET',
                data: {
                    year: selectedYear,
                    kat: 'B'
                },
                success: function(response) {
                    console.log('Kantonal B response:', response); // Debug-Ausgabe
                    $('#KantonalB tbody').html(response);
                    // Mobile Cards generieren
                    buildMobileCardsKatB();
                },
                error: function(xhr, status, error) {
                    console.error('Error loading Kantonal B:', error);
                }
            });
        }

        // Mobile Cards für Kategorie A generieren
        function buildMobileCardsKatA() {
            MSVMobileCards.initResponsive(function() {
                MSVMobileCards.buildCards('#KantonalA', '#mobileCardsKatA', {
                    titleColumns: [0, 1], // Rang + Name
                    summaryColumns: [7],  // Total
                    rankColumn: 0         // Top-3 Highlighting
                });
            });
        }

        // Mobile Cards für Kategorie B generieren
        function buildMobileCardsKatB() {
            MSVMobileCards.initResponsive(function() {
                MSVMobileCards.buildCards('#KantonalB', '#mobileCardsKatB', {
                    titleColumns: [0, 1], // Rang + Name
                    summaryColumns: [7],  // Total
                    rankColumn: 0         // Top-3 Highlighting
                });
            });
        }

        // PDF-Button Handler
   $(document).on('click', '.pdf-btn', function(e) {
    e.preventDefault();
    var selectedYear = $('#yearSelect').val();
    $.ajax({
        url: 'kantirang/generate_pdf.php',
        type: 'GET',
        dataType: 'json',  // Das sagt jQuery, dass es JSON erwartet
        data: {
            year: selectedYear,
        },
        success: function(response) {
            // response ist bereits ein JavaScript-Objekt, NICHT JSON.parse verwenden!
            if (response && response.pdf_link) {
                // PDF direkt herunterladen
                const link = document.createElement('a');
                link.href = response.pdf_link;
                link.download = response.pdf_link.split('/').pop(); // Dateiname extrahieren
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // PDF-Link Container leeren nach Download
                $('#pdf-link').empty();
            } else {
                $('#pdf-link').html('<span class="text-danger">Fehler beim Generieren der PDF-Datei</span>');
            }
        },
        error: function(xhr, status, error) {
            console.error('PDF Generation Error:', error);
            $('#pdf-link').html('<span class="text-danger">Fehler beim Generieren des PDFs: ' + error + '</span>');
        }
    });
});

        // Word-Button Handler (falls du ihn brauchst)
        $(document).on('click', '.word-btn', function(e) {
            e.preventDefault();
            var selectedYear = $('#yearSelect').val();
            $.ajax({
                url: 'kantirang/generate_word.php',
                type: 'GET',
                data: {
                    year: selectedYear,
                },
                success: function(response) {
                    var data = JSON.parse(response);
                    var wordLink = data.pdf_link;
                    $('#pdf-link').html('<a href="' + wordLink + '" target="_blank">Word herunterladen</a>');
                },
                error: function(xhr, status, error) {
                    msvError('Fehler beim Generieren des Word-Dokuments: ' + error);
                }
            });
        });

        // Event Handler für Jahr-Dropdown
        $('#yearSelect').on('change', function() {
            console.log('Year changed to:', $(this).val()); // Debug-Ausgabe
            loadKantonala();
            loadKantonalb();
        });

        // WICHTIG: Korrekte Reihenfolge der Initialisierung
        // 1. Zuerst das Dropdown initialisieren
        initializeYearDropdown();
        
        // 2. Dann die Daten laden (nachdem das Dropdown einen Wert hat)
        loadKantonala();
        loadKantonalb();
    });
</script>
<?
include 'footer.inc.php';
?>