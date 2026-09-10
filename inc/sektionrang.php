<?php
// sektionrang.php — Rangliste Sektionsmeisterschaft (Runde 1 und Runde 2, je mit Schnitt)
include 'dbconnect.inc.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_specific_css = '
    .sektionrang-count { font-size: 0.75rem; font-weight: 500; color: #6c757d; margin-left: auto; }
    @media (min-width: 768px) {
        .sektionrang-runden .table-wrapper + .table-wrapper { margin-top: 0 !important; }
    }
    /* Kompakte Zeilen, Namen einzeilig, schmale Resultat-Spalten */
    .sektionrang-table th, .sektionrang-table td { padding: 0.3rem 0.6rem; white-space: nowrap; vertical-align: middle; }
    .sektionrang-table th:first-child, .sektionrang-table td:first-child { width: 100%; text-align: left; }
    .sektionrang-table th.result-column, .sektionrang-table td.result-column { width: 84px; min-width: 84px; text-align: center; }
    /* Leerzeilen zum Auffuellen der kuerzeren Liste (als <th>, damit MSVMobileCards sie ignoriert) */
    .sektionrang-table tbody tr.pad-row th { background: transparent; font-weight: normal; text-transform: none; }
    /* Schnitt-Block (Regel der Sektionsabrechnungen) */
    .schnitt-box { display: flex; flex-wrap: wrap; gap: 0.35rem 1.25rem; align-items: baseline;
        padding: 0.5rem 0.75rem; border-top: 1px solid #e9ecef; background: #f8f9fa; font-size: 0.8rem; }
    .schnitt-item { display: flex; align-items: baseline; gap: 0.35rem; }
    .schnitt-label { color: #6c757d; text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.02em; }
    .schnitt-value { font-weight: 600; font-variant-numeric: tabular-nums; }
    .schnitt-final { margin-left: auto; }
    .schnitt-final .schnitt-value { color: #198754; font-size: 0.95rem; }
';

include 'header.inc.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-default">
                <?php $page_title = "Sektionsmeisterschaft Rangliste"; include 'partials/page_header.inc.php'; ?>

                <div class="content-background">
                <form id="sektionrangForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Jahr-Auswahl + Dokumente erstellen -->
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
                            <button type="button" class="btn btn-outline-info btn-sm pdf-btn">
                                <i class="bi bi-file-pdf me-1"></i><span>Rangliste</span>
                            </button>
                        </div>
                        <div id="pdf-link" class="mt-2"></div>
                    </div>

                    <!-- Runde 1 + Runde 2 nebeneinander -->
                    <div class="row g-4 sektionrang-runden">
                        <div class="col-md-6">
                            <div class="table-wrapper">
                                <h5 class="table-title d-flex align-items-center">
                                    <i class="bi bi-1-circle me-2"></i>
                                    Runde 1
                                    <span class="sektionrang-count" id="countRunde1"></span>
                                </h5>
                                <div class="desktop-table-container">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0 sektionrang-table" id="sektionrangTabelleR1">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Name</th>
                                                    <th scope="col" class="result-column">Resultat</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div id="schnittRunde1"></div>
                                <div class="mobile-cards-container" id="mobileCardsR1">
                                    <div class="mobile-search">
                                        <div class="position-relative">
                                            <i class="bi bi-search search-icon"></i>
                                            <input type="text" class="form-control" placeholder="Suchen..."
                                                   oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsR1')">
                                        </div>
                                    </div>
                                    <div class="mobile-cards-scroll"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="table-wrapper">
                                <h5 class="table-title d-flex align-items-center">
                                    <i class="bi bi-2-circle me-2"></i>
                                    Runde 2
                                    <span class="sektionrang-count" id="countRunde2"></span>
                                </h5>
                                <div class="desktop-table-container">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0 sektionrang-table" id="sektionrangTabelleR2">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Name</th>
                                                    <th scope="col" class="result-column">Resultat</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div id="schnittRunde2"></div>
                                <div class="mobile-cards-container" id="mobileCardsR2">
                                    <div class="mobile-search">
                                        <div class="position-relative">
                                            <i class="bi bi-search search-icon"></i>
                                            <input type="text" class="form-control" placeholder="Suchen..."
                                                   oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsR2')">
                                        </div>
                                    </div>
                                    <div class="mobile-cards-scroll"></div>
                                </div>
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
    function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        const currentYear = new Date().getFullYear();
        for (let year = currentYear; year >= currentYear - 3; year--) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) option.prop('selected', true);
            yearSelect.append(option);
        }
    }

    $(document).ready(function () {
        $('#redirect-btn').on('click', function () {
            window.location.href = 'jmresultate.php';
        });

        function anzahlText(n) {
            return n === 1 ? '1 Schütze' : n + ' Schützen';
        }

        function buildCards() {
            MSVMobileCards.initResponsive(function () {
                MSVMobileCards.buildCards('#sektionrangTabelleR1', '#mobileCardsR1', {
                    titleColumns: [0], summaryColumns: [1]
                });
                MSVMobileCards.buildCards('#sektionrangTabelleR2', '#mobileCardsR2', {
                    titleColumns: [0], summaryColumns: [1]
                });
            });
        }

        function loadRangliste() {
            const year = $('#yearSelect').val();
            $.ajax({
                url: 'sektionrang/load_sektionrang.php',
                type: 'GET',
                dataType: 'json',
                data: { year: year },
                success: function (resp) {
                    if (!resp || !resp.success) {
                        msvError('Fehler: ' + ((resp && resp.message) || 'Rangliste konnte nicht geladen werden'));
                        return;
                    }
                    $('#sektionrangTabelleR1 tbody').html(resp.runde1);
                    $('#sektionrangTabelleR2 tbody').html(resp.runde2);
                    $('#schnittRunde1').html(resp.schnitt1 || '');
                    $('#schnittRunde2').html(resp.schnitt2 || '');
                    $('#countRunde1').text(resp.anzahl.runde1 ? anzahlText(resp.anzahl.runde1) : '');
                    $('#countRunde2').text(resp.anzahl.runde2 ? anzahlText(resp.anzahl.runde2) : '');
                    buildCards();
                },
                error: function (xhr) {
                    console.error('AJAX Error:', xhr.responseText);
                    msvError('Fehler beim Laden der Rangliste');
                }
            });
        }

        $(document).on('click', '.pdf-btn', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const $label = $btn.find('span');
            const originalText = $label.text();
            $btn.prop('disabled', true);
            $label.text('Erstellt…');

            $.ajax({
                url: 'sektionrang/generate_pdf.php',
                type: 'GET',
                dataType: 'json',
                data: { year: $('#yearSelect').val() },
                success: function (response) {
                    if (response.pdf_link) {
                        const link = document.createElement('a');
                        link.href = response.pdf_link;
                        link.download = response.pdf_link.split('/').pop();
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        $('#pdf-link').empty();
                    } else {
                        msvError('Fehler: ' + (response.error || 'PDF konnte nicht erstellt werden'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    msvError('Fehler beim Generieren des PDFs: ' + error);
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    $label.text(originalText);
                }
            });
        });

        $('#yearSelect').on('change', loadRangliste);

        initializeYearDropdown();
        loadRangliste();
    });
</script>
<?php include 'footer.inc.php'; ?>
