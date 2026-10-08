<?php
//kantiang.php
include 'dbconnect.inc.php';


include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
?>
<!-- Kantirang.php HTML-Gerüst nach jmrang.php Vorbild -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <!-- Äusserer weisser Container -->
            <div class="main-content-wrapper content-width-wide">
                <!-- Header ausserhalb des inneren Containers -->
                <?php
                $page_title = "Kantonalstich Rangliste";
                $page_title_after = '<button type="button" class="btn-help" data-help="kantirang.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_show_mobile = true;
                ob_start(); ?>
<button type="button" class="btn-help" data-help="kantirang.dokumente" aria-label="Hilfe"></button>
<div class="btn-group btn-group-sm" role="group" aria-label="Rangliste">
<button type="button" class="btn btn-outline-info pdf-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Rangliste</span></button>
<button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="kantirang" data-druck-label="Kantonalstich Rangliste" aria-label="Rangliste direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
</div>
<button id="redirect-btn" type="button" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Resultate bearbeiten </button>
                <?php $page_actions = ob_get_clean();
                include 'partials/page_header.inc.php'; ?>

                <!-- Weisser Hintergrund-Container -->
                <div class="content-background">
                <form id="kantiresultateForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Kategorie A Tabelle -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-star me-2"></i>
                            Kantonalstich Kat. A <button type="button" class="btn-help" data-help="kantirang.kategorien" aria-label="Hilfe"></button>
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
        msvJahrAuswahl('#yearSelect');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var redirectButton = document.getElementById('redirect-btn');
        redirectButton.addEventListener('click', function() {
            window.location.href = 'kantiresultate.php';
        });
    });

    $(document).ready(function() {
        var basePath = '';

        // Kantiresultate A laden
        function loadKantonala() {
            var selectedYear = $('#yearSelect').val();
            $.ajax({
                url: basePath + 'kantirang/load_kantonal.php',
                type: 'GET',
                data: {
                    year: selectedYear,
                    kat: 'A'
                },
                success: function(response) {
                    $('#KantonalA tbody').html(response);
                    // Mobile Cards generieren
                    buildMobileCardsKatA();
                },
                error: function(xhr, status, error) {
                    msvToast(msvXhrMessage(xhr, 'Kantonalresultate Kat. A konnten nicht geladen werden'), 'error');
                }
            });
        }

        // Kantiresultate B laden
        function loadKantonalb() {
            var selectedYear = $('#yearSelect').val();
            $.ajax({
                url: basePath + 'kantirang/load_kantonal.php',
                type: 'GET',
                data: {
                    year: selectedYear,
                    kat: 'B'
                },
                success: function(response) {
                    $('#KantonalB tbody').html(response);
                    // Mobile Cards generieren
                    buildMobileCardsKatB();
                },
                error: function(xhr, status, error) {
                    msvToast(msvXhrMessage(xhr, 'Kantonalresultate Kat. B konnten nicht geladen werden'), 'error');
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

        // Rangliste als PDF (Ausgabe-Baustein msvAusgabe: sperren, Spinner, Download, Toast)
        $(document).on('click', '.pdf-btn', function (e) {
            e.preventDefault();
            var jahr = $('#yearSelect').val();
            msvAusgabe(this, {
                url: 'kantirang/generate_pdf.php',
                data: {
                    year: jahr,
                    orientation: window.MsvDruck ? MsvDruck.orientierung('kantirang', 'portrait') : 'portrait' // Format aus dem Druckprofil
                },
                titel: 'Kantonalstich Rangliste ' + jahr,
                name: 'Kantonalstich_Rangliste_' + jahr,
                fehler: 'Die Rangliste konnte nicht erstellt werden. Bitte nochmals versuchen.'
            });
        });

        // Event Handler für Jahr-Dropdown
        $('#yearSelect').on('change', function() {
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
<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray), Profil «Kantonalstich Rangliste»
MsvDruck.resolve('kantirang', () => {
    const jahr = document.getElementById('yearSelect').value;
    return { url: 'kantirang/generate_pdf.php?year=' + encodeURIComponent(jahr) + '&orientation=' + MsvDruck.orientierung('kantirang', 'portrait'), jobName: 'Kantonalstich Rangliste ' + jahr };
});
</script>
<?
include 'footer.inc.php';
?>