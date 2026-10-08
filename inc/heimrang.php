<?php
//heimrang.php
include 'dbconnect.inc.php';


include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
?>
<!-- Heimrang.php HTML-Gerüst nach jmrang.php Vorbild -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <!-- Äusserer weisser Container -->
            <div class="main-content-wrapper content-width-wide">
                <!-- Header ausserhalb des inneren Containers -->
                <?php
                $page_title = "Heimmeisterschaft Rangliste";
                $page_title_after = '<button type="button" class="btn-help" data-help="heimrang.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_show_mobile = true;
                ob_start(); ?>
<button type="button" class="btn-help" data-help="heimrang.dokumente" aria-label="Hilfe"></button>
<button type="button" class="btn btn-outline-info btn-sm pdf-btn"><i class="bi bi-file-pdf me-1"></i><span>Rangliste</span></button>
<button type="button" class="btn btn-outline-info btn-sm msv-druck" data-druck-doctype="heimrang" data-druck-label="Heimmeisterschaft Rangliste" aria-label="Rangliste direkt drucken"><i class="bi bi-printer"></i></button>
<button id="redirect-btn" type="button" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Resultate bearbeiten </button>
                <?php $page_actions = ob_get_clean();
                include 'partials/page_header.inc.php'; ?>

                <!-- Weisser Hintergrund-Container -->
                <div class="content-background">
                <form id="heimresultateForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <div id="pdf-link"></div>

                    <!-- Kategorie A Tabelle -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-star me-2"></i>
                            Heimmeisterschaft Kat. A <button type="button" class="btn-help" data-help="heimrang.kategorien" aria-label="Hilfe"></button>
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
            msvJahrAuswahl('#yearSelect');
        }

        // Redirect Button Handler
        document.addEventListener('DOMContentLoaded', function () {
            var redirectButton = document.getElementById('redirect-btn');
            if (redirectButton) {
                redirectButton.addEventListener('click', function () {
                    console.log('Button clicked, redirecting...');
                    window.location.href = 'heimresultate.php';
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
                    },
                    error: function (xhr) { msvToast(msvXhrMessage(xhr, 'Heimresultate Kat. A konnten nicht geladen werden'), 'error'); }
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
                    },
                    error: function (xhr) { msvToast(msvXhrMessage(xhr, 'Heimresultate Kat. B konnten nicht geladen werden'), 'error'); }
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
                        kat: 'B',
                        orientation: window.MsvDruck ? MsvDruck.orientierung('heimrang', 'landscape') : 'landscape' // Format aus dem Druckprofil
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
                    error: function (xhr) {
                        console.error('AJAX Error:', xhr.responseText);
                        msvError(msvXhrMessage(xhr, 'Das PDF konnte nicht erstellt werden. Bitte nochmals versuchen.'));
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
    <?php include 'partials/direktdruck_scripts.inc.php'; ?>
    <script>
    // Direktdruck (QZ Tray), Profil «Heimmeisterschaft Rangliste» (A4 quer); kat=B wie beim PDF-Button
    MsvDruck.resolve('heimrang', () => {
        const jahr = document.getElementById('yearSelect').value;
        return { url: 'heimrang/generate_pdf.php?year=' + encodeURIComponent(jahr) + '&kat=B&orientation=' + MsvDruck.orientierung('heimrang', 'landscape'), jobName: 'Heimmeisterschaft Rangliste ' + jahr };
    });
    </script>
    <?php
    include 'footer.inc.php';
    ?>