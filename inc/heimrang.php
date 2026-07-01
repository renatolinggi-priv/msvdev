<?php
//heimrang.php
include 'dbconnect.inc.php';

// Session-Kontrolle wie in jmresultate.php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'header.inc.php';
?>
<!-- Heimrang.php HTML-Gerüst nach jmrang.php Vorbild -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xxl-8 col-xl-9 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <?php $page_title = "Heimmeisterschaft Ranglisten"; include 'partials/page_header.inc.php'; ?>

                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                <form id="heimresultateForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Jahr-Auswahl + Dokumente erstellen (eine kompakte Karte) -->
                    <div class="export-toolbar mb-3">
                        <div class="export-toolbar-head">
                            <label for="yearSelect" class="export-year-label mb-0">
                                <i class="bi bi-calendar3 me-1"></i>Jahr:
                            </label>
                            <select id="yearSelect" class="form-select form-select-sm export-year-select"></select>
                            <span class="export-toolbar-divider" aria-hidden="true"></span>
                            <i class="bi bi-file-earmark-arrow-down"></i>
                            <span>Dokumente erstellen</span>
                            <button id="redirect-btn" type="button" class="btn btn-outline-primary btn-sm ms-auto">
                                <i class="bi bi-pencil me-1"></i>Resultate bearbeiten
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
                            Heimmeisterschaft Kat. A
                        </h5>
                        <div class="desktop-table-container">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="heimresultateTabelleA">
                                <thead>
                                    <tr>
                                        <th scope="col">Rang</th>
                                        <th scope="col">Name</th>
                                        <th scope="col" class="result-column">Passe 1</th>
                                        <th scope="col" class="result-column">Passe 2</th>
                                        <th scope="col" class="result-column">Passe 3</th>
                                        <th scope="col" class="result-column">Passe 4</th>
                                        <th scope="col" class="result-column">Passe 5</th>
                                        <th scope="col" class="result-column">Passe 6</th>
                                        <th scope="col" class="result-column">Passe 7</th>
                                        <th scope="col" class="result-column">Passe 8</th>
                                        <th scope="col">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamisch per AJAX -->
                                </tbody>
                            </table>
                        </div>
                        </div>
                        <div class="mobile-cards-container" id="mobileCardsHeimA">
                            <div class="mobile-search">
                                <div class="position-relative">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" class="form-control" placeholder="Suchen..."
                                           oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsHeimA')">
                                </div>
                            </div>
                            <div class="mobile-cards-scroll"></div>
                        </div>
                    </div>

                    <!-- Kategorie B Tabelle -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-star-half me-2"></i>
                            Heimmeisterschaft Kat. B
                        </h5>
                        <div class="desktop-table-container">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="heimresultateTabelleB">
                                <thead>
                                    <tr>
                                        <th scope="col">Rang</th>
                                        <th scope="col">Name</th>
                                        <th scope="col" class="result-column">Passe 1</th>
                                        <th scope="col" class="result-column">Passe 2</th>
                                        <th scope="col" class="result-column">Passe 3</th>
                                        <th scope="col" class="result-column">Passe 4</th>
                                        <th scope="col" class="result-column">Passe 5</th>
                                        <th scope="col" class="result-column">Passe 6</th>
                                        <th scope="col" class="result-column">Passe 7</th>
                                        <th scope="col" class="result-column">Passe 8</th>
                                        <th scope="col">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamisch per AJAX -->
                                </tbody>
                            </table>
                        </div>
                        </div>
                        <div class="mobile-cards-container" id="mobileCardsHeimB">
                            <div class="mobile-search">
                                <div class="position-relative">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" class="form-control" placeholder="Suchen..."
                                           oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsHeimB')">
                                </div>
                            </div>
                            <div class="mobile-cards-scroll"></div>
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
            for (let year = currentYear; year >= currentYear - 3; year--) {
                const option = $('<option></option>').val(year).text(year);
                if (year === currentYear) {
                    option.prop('selected', true);
                }
                yearSelect.append(option);
            }
        }

        // Redirect Button Handler
        document.addEventListener('DOMContentLoaded', function () {
            var redirectButton = document.getElementById('redirect-btn');
            if (redirectButton) {
                redirectButton.addEventListener('click', function () {
                    console.log('Button clicked, redirecting...');
                    window.location.href = 'https://jahresmeisterschaft.msvwilen.ch/inc/heimresultate.php';
                });
            }
        });

        $(document).ready(function () {
            var basePath = '';

            // Heimresultate für Kat. A laden
            function loadHeimresultatea() {
                var selectedYear = $('#yearSelect').val();
                $.ajax({
                    url: basePath + 'heimrang/load_heimresultate.php',
                    type: 'GET',
                    data: {
                        year: selectedYear,
                        kat: 'A'
                    },
                    success: function (response) {
                        $('#heimresultateTabelleA tbody').html(response);
                        buildMobileCardsHeimA();
                    }
                });
            }

            // Heimresultate für Kat. B laden
            function loadHeimresultateb() {
                var selectedYear = $('#yearSelect').val();
                $.ajax({
                    url: basePath + 'heimrang/load_heimresultate.php',
                    type: 'GET',
                    data: {
                        year: selectedYear,
                        kat: 'B'
                    },
                    success: function (response) {
                        $('#heimresultateTabelleB tbody').html(response);
                        buildMobileCardsHeimB();
                    }
                });
            }

            // Mobile Cards für Heim Kat. A
            function buildMobileCardsHeimA() {
                MSVMobileCards.initResponsive(function() {
                    MSVMobileCards.buildCards('#heimresultateTabelleA', '#mobileCardsHeimA', {
                        titleColumns: [0, 1],
                        summaryColumns: [9],
                        rankColumn: 0
                    });
                });
            }

            // Mobile Cards für Heim Kat. B
            function buildMobileCardsHeimB() {
                MSVMobileCards.initResponsive(function() {
                    MSVMobileCards.buildCards('#heimresultateTabelleB', '#mobileCardsHeimB', {
                        titleColumns: [0, 1],
                        summaryColumns: [9],
                        rankColumn: 0
                    });
                });
            }

            // PDF-Button Handler
            $(document).on('click', '.pdf-btn', function (e) {
                e.preventDefault();
                var selectedYear = $('#yearSelect').val();

                $.ajax({
                    url: 'heimrang/generate_pdf.php',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        year: selectedYear,
                        kat: 'B'
                    },
                    success: function (response) {
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
                        } else if (response.error) {
                            msvError('Fehler: ' + response.error);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        msvError('Fehler beim Generieren des PDFs: ' + error);
                    }
                });
            });

            // Beim Ändern des Jahres im Dropdown beide Tabellen neu laden
            $('#yearSelect').on('change', function () {
                loadHeimresultatea();
                loadHeimresultateb();
            });

            // Initialisierung beim Laden der Seite
            initializeYearDropdown();
            loadHeimresultatea();
            loadHeimresultateb();
        });
    </script>
    <?php
    include 'footer.inc.php';
    ?>