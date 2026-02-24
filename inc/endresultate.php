<?php
// endresultate.php – Neuaufbau nach wichtigetermine-Pattern
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in endresultate.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Seitenspezifische Styles
$page_specific_css = "
/* Endresultate-spezifische Styles */

:root {
    --app-header: 76px;
    --app-footer: 0px;
}

.main-content-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 0 !important;
    height: calc(100vh - var(--app-header) - var(--app-footer) - 20px) !important;
    margin-bottom: 0 !important;
}

.content-background {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;
    overflow: hidden;
}

#endresultateForm {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;
}

.table-wrapper {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;
    margin-bottom: 0 !important;
    overflow: hidden !important;
}

.table-responsive {
    flex: 1 1 auto;
    min-height: 0 !important;
    overflow: auto !important;
    border-radius: 0 0 var(--border-radius) var(--border-radius);
    -webkit-overflow-scrolling: touch;
}

.table {
    border: none;
    margin-bottom: 0;
}

.table thead th {
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    padding: 1rem;
    background-color: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table tbody tr {
    transition: background-color 0.2s ease;
    border-bottom: 1px solid #f1f3f4;
}

.table tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.04);
}

.table tbody td {
    padding: 0.5rem;
    vertical-align: middle;
    border: none;
    text-align: center;
}

.table tbody td:first-child {
    text-align: left;
}

.button-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    align-items: center;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.25rem;
    margin-bottom: 1.25rem;
    flex-shrink: 0;
}

.results-list-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    overflow: hidden;
    margin-bottom: 0;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}

.results-header {
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #dee2e6;
    color: var(--dark-color);
    font-weight: 600;
    font-size: 1rem;
    flex-shrink: 0;
}

.btn-compact { padding: .45rem .75rem; font-size: .875rem; }

.custom-close {
    background: none;
    border: none;
    color: var(--secondary-color);
    font-size: 1.5rem;
    opacity: 0.7;
    transition: all var(--transition-speed) ease;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.custom-close:hover {
    opacity: 1;
    background-color: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
    transform: scale(1.1);
}

.spinner-border { color: var(--secondary-color) !important; }

/* Shooting Category Cards im Modal */
.shooting-category {
    background: #f8f9fa;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.75rem;
}

.shooting-category h6 {
    color: var(--secondary-color);
    font-size: 0.85rem;
}

.shooting-category.disabled {
    opacity: 0.4;
    pointer-events: none;
    position: relative;
}

.shooting-category.disabled::after {
    content: 'Nicht gelöst';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(220, 53, 69, 0.9);
    color: white;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: bold;
    z-index: 10;
}

#schwiniSchuesse .schwini-pass-disabled {
    opacity: 0.4;
    position: relative;
}

#schwiniSchuesse .schwini-pass-disabled::after {
    content: 'Nicht gelöst';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(220, 53, 69, 0.7);
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 0.65rem;
    font-weight: bold;
    z-index: 5;
    white-space: nowrap;
}

#schwiniSchuesse input:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
    opacity: 0.5;
}

.focusable-input:focus {
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    border-color: #007bff;
}

.small-input {
    width: 42px !important;
    text-align: center !important;
    padding: 0.2rem !important;
    font-size: 0.85rem !important;
}

.total-display {
    font-weight: bold;
    font-size: 0.9rem;
    color: var(--secondary-color);
    min-width: 30px;
    text-align: center;
}

/* Action Buttons */
.action-group .btn {
    padding: 0.25rem 0.5rem;
}

@media (max-width: 576px) {
    .button-toolbar { flex-direction: column; }
    .button-toolbar .btn { width: 100%; }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.results-list-card {
    animation: fadeIn 0.5s ease-out;
}

/* =========================================
   Mobile Cards Optimierung
   ========================================= */
@media (max-width: 767.98px) {
    /* WCAG AAA Touch Targets: Alle Form-Elemente */
    .form-control,
    .form-select,
    input[type=\"text\"],
    input[type=\"number\"],
    select {
        min-height: 48px !important;
        font-size: 16px !important; /* Verhindert iOS Auto-Zoom */
    }

    .btn {
        min-height: 48px !important;
        font-size: 16px !important;
        padding: 0.5rem 1rem !important;
    }

    .desktop-table-container { display: none !important; }
    .mobile-cards-container { display: flex !important; }

    /* Mobile Scroll Fix: fixe Höhe aufheben */
    .main-content-wrapper {
        height: auto !important;
        min-height: calc(100vh - var(--app-header) - 10px) !important;
    }

    .content-background {
        overflow: visible !important;
    }

    .table-wrapper {
        overflow: visible !important;
    }

    .mobile-card-detail-row {
        padding: 0.75rem 0 !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }

    .mobile-card-detail-label {
        font-size: 0.875rem !important;
        color: #64748b !important;
        font-weight: 500 !important;
    }

    .mobile-card-detail-value {
        font-size: 1rem !important;
        color: #1e293b !important;
    }

    .mobile-card-body .btn {
        min-height: 48px !important;
        font-size: 1rem !important;
    }

    .button-toolbar .btn {
        min-height: 48px !important;
        font-size: 0.95rem !important;
    }

}
";

include 'header.inc.php';
?>
<style><?= $page_specific_css ?></style>
<?php
if (!isset($_SESSION)) { session_start(); }
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-10 col-lg-12 col-12 ps-0">
            <div class="main-content-wrapper">
                <!-- Header -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-target me-2"></i>
                            Endschiessen Resultaterfassung
                        </h2>
                        <p class="text-muted mb-0">Resultate erfassen und verwalten</p>
                    </div>
                </div>

                <div class="content-background">
                    <form id="endresultateForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr-Auswahl + Aktionen nebeneinander -->
                        <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                                <i class="bi bi-calendar3 me-1"></i>Jahr:
                            </label>
                            <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
                        </div>

                        <!-- Aktionsbereich (Bootstrap Collapse) -->
                        <div class="card action-card mb-0">
                            <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                                 data-bs-toggle="collapse" data-bs-target="#endresultateActions"
                                 aria-expanded="false" aria-controls="endresultateActions">
                                <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                                <i class="bi bi-chevron-down action-chevron"></i>
                            </div>
                            <div class="collapse" id="endresultateActions">
                                <div class="card-body pt-2 pb-3 px-3">
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <button id="redirect-btn" type="button" class="btn btn-success w-100">
                                                <i class="bi bi-trophy me-1"></i>Rangliste
                                            </button>
                                        </div>
                                        <div class="col-12">
                                            <button id="delall-btn" type="button" class="btn btn-outline-danger w-100">
                                                <i class="bi bi-trash me-1"></i>Alle Daten löschen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div><!-- Ende flex-row Jahr+Aktionen -->

                        <!-- Tabelle Container -->
                        <div id="resultateContainer">
                            <div class="results-list-card">
                                <div class="results-header">
                                    <i class="bi bi-table me-2"></i>
                                    Resultate
                                </div>
                                <div class="table-wrapper">
                                    <!-- Desktop: Tabelle -->
                                    <div class="desktop-table-container">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0" id="mitgliederTabelle">
                                                <thead>
                                                    <tr>
                                                        <th scope="col"><i class="bi bi-person me-1"></i>Mitglied</th>
                                                        <th scope="col" class="text-center">Endstich</th>
                                                        <th scope="col" class="text-center">Schwini</th>
                                                        <th scope="col" class="text-center">Kunst</th>
                                                        <th scope="col" class="text-center">Glück</th>
                                                        <th scope="col" class="text-center">Zabig</th>
                                                        <th scope="col" class="text-center">Sie und Er</th>
                                                        <th scope="col" class="text-center">Ansage</th>
                                                        <th scope="col" class="text-center">Aktionen</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="9" class="text-center py-4">
                                                            <div class="spinner-border spinner-border-sm me-2"></div>
                                                            Lade Daten...
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Mobile: Cards -->
                                    <div class="mobile-cards-container" id="mobileCardsEnd">
                                        <div class="mobile-search">
                                            <div class="position-relative">
                                                <i class="bi bi-search search-icon"></i>
                                                <input type="text" class="form-control" placeholder="Mitglied suchen..."
                                                       oninput="filterMobileEnd(this)">
                                            </div>
                                        </div>
                                        <div class="mobile-cards-scroll">
                                            <!-- Cards werden per JavaScript generiert -->
                                        </div>
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

<!-- Schuss-Modal -->
<div class="modal fade" id="schussModal" tabindex="-1" aria-labelledby="schussModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="schussModalLabel">
                    <i class="bi bi-target me-2"></i> Erfassen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                <form id="schussForm" style="display: contents;">
                    <input type="hidden" id="mitgliedID" name="mitgliedID">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="row g-2">
                        <!-- Zeile 1: Absenden + Ansage -->
                        <div class="col-md-4">
                            <div id="Absendenanmeldung" class="shooting-category mb-0">
                                <h6 class="mb-1"><i class="bi bi-calendar-check me-1"></i> Absenden</h6>
                                <input type="text" class="form-control form-control-sm focusable-input" id="AbsendenAnmeldung" name="AbsendenAnmeldung" placeholder="Anmeldung">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div id="Differenzler" class="shooting-category mb-0">
                                <h6 class="mb-1"><i class="bi bi-chat-square-text me-1"></i> Ansage</h6>
                                <input type="number" class="form-control form-control-sm focusable-input" id="Ansage" name="Ansage" min="0" max="999" placeholder="Differenzler">
                            </div>
                        </div>
                        <div class="col-md-4"></div>

                        <!-- Zeile 2: Endstich (volle Breite) -->
                        <div class="col-12">
                            <div id="endstichSchuesse" class="shooting-category mb-0" data-stich="END">
                                <h6 class="mb-1"><i class="bi bi-bullseye me-1"></i> Endstich</h6>
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <?php for ($i=1; $i<=10; $i++): ?>
                                        <input type="number" class="small-input endschuss focusable-input" id="Schuss<?= $i ?>" name="Schuss<?= $i ?>" min="0" max="10">
                                    <?php endfor; ?>
                                    <div class="d-flex align-items-center">
                                        <label for="Tiefschuss" class="small me-1 mb-0">TS:</label>
                                        <input type="number" class="small-input focusable-input" id="Tiefschuss" name="Tiefschuss" min="0" max="100" style="width: 50px;">
                                    </div>
                                    <span id="endstichSumme" class="total-display ms-auto">0</span>
                                </div>
                            </div>
                        </div>

                        <!-- Zeile 3: Schwini + Zabig -->
                        <div class="col-md-6">
                            <div id="schwiniSchuesse" class="shooting-category mb-0" data-stich="SCHWINI">
                                <h6 class="mb-1"><i class="bi bi-piggy-bank me-1"></i> Schwini</h6>
                                <div class="mb-1 schwini-passe-1">
                                    <label class="small mb-0" style="font-size: 0.75rem;">Passe 1:</label>
                                    <div class="d-flex align-items-center gap-1">
                                        <?php for ($i=1; $i<=6; $i++): ?>
                                            <input type="number" class="small-input schwini-schuss1 focusable-input" id="P1Schuss<?= $i ?>" name="P1Schuss<?= $i ?>" min="0" max="10">
                                        <?php endfor; ?>
                                        <span id="schwiniSumme1" class="total-display ms-1">0</span>
                                    </div>
                                </div>
                                <div class="schwini-passe-2">
                                    <label class="small mb-0" style="font-size: 0.75rem;">Passe 2:</label>
                                    <div class="d-flex align-items-center gap-1">
                                        <?php for ($i=1; $i<=6; $i++): ?>
                                            <input type="number" class="small-input schwini-schuss2 focusable-input" id="P2Schuss<?= $i ?>" name="P2Schuss<?= $i ?>" min="0" max="10">
                                        <?php endfor; ?>
                                        <span id="schwiniSumme2" class="total-display ms-1">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div id="zabigSchuesse" class="shooting-category mb-0" data-stich="ZABIG">
                                <h6 class="mb-1"><i class="bi bi-moon-stars me-1"></i> Zabig</h6>
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <?php for ($i=1; $i<=6; $i++): ?>
                                        <input type="number" class="small-input zabig focusable-input" id="ZSchuss<?= $i ?>" name="ZSchuss<?= $i ?>" min="0" max="100">
                                    <?php endfor; ?>
                                    <span id="zabigsum" class="total-display ms-1">0</span>
                                </div>
                            </div>
                        </div>

                        <!-- Zeile 4: Sie und Er + Kunst + Glück -->
                        <div class="col-md-6">
                            <div id="sieunderSchuesse" class="shooting-category mb-0" data-stich="SIEUNDER">
                                <h6 class="mb-1">
                                    <i class="bi bi-people me-1"></i>"Sie und Er"
                                    <span class="badge bg-info ms-1" style="font-size: 0.65rem;">Unique</span>
                                </h6>
                                <div class="d-flex align-items-center gap-1 flex-wrap mb-1">
                                    <?php for ($i=6; $i<=10; $i++): ?>
                                        <input type="number"
                                               class="small-input sie-er-schuss sie-er-mitglied focusable-input"
                                               id="SieErSchuss<?= $i ?>"
                                               name="SieErSchuss<?= $i ?>"
                                               data-position="<?= $i ?>"
                                               data-source="mitglied"
                                               min="0" max="10" step="0.1"
                                               style="border-bottom: 3px solid #007bff;"
                                               placeholder="<?= $i ?>">
                                    <?php endfor; ?>
                                    <span class="badge bg-success ms-auto" id="uniqueTotal" style="font-size: 0.7rem;">
                                        <i class="bi bi-calculator me-1"></i>Total: 0
                                    </span>
                                </div>
                                <div id="previewBadges" class="d-flex gap-1 flex-wrap" style="font-size: 0.75rem;"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div id="kunstSchuesse" class="shooting-category mb-0" data-stich="KUNST">
                                <h6 class="mb-1"><i class="bi bi-palette me-1"></i> Kunst</h6>
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <?php for ($i=1; $i<=5; $i++): ?>
                                        <input type="number" class="small-input kunst focusable-input" id="KSchuss<?= $i ?>" name="KSchuss<?= $i ?>" min="0" max="100">
                                    <?php endfor; ?>
                                    <span id="kunstSum" class="total-display ms-1">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div id="glueckSchuesse" class="shooting-category mb-0" data-stich="GLUECK">
                                <h6 class="mb-1"><i class="bi bi-clover me-1"></i> Glück</h6>
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <input type="number" class="small-input glueck focusable-input" id="GSchuss1" name="GSchuss1" min="0" max="100">
                                    <input type="number" class="small-input glueck focusable-input" id="GSchuss2" name="GSchuss2" min="0" max="100">
                                    <input type="number" class="small-input glueck focusable-input" id="GSchuss3" name="GSchuss3" min="0" max="100">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" form="schussForm" class="btn btn-outline-success">
                    <i class="bi bi-save me-2"></i>Speichern
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Abbrechen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Lösch-Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    Bestätigung erforderlich
                </h5>
                <button type="button" class="custom-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body text-center py-4" id="confirmModalBody">
                Sind Sie sicher?
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-compact btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-compact btn-outline-danger" id="confirmAction">
                    <i class="bi bi-check-circle me-1"></i>Bestätigen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    // ===== State =====
    var deleteType = '';
    var itemToDelete = null;

    // ===== Höhenberechnung =====
    function calculateTableHeight() {
        var tableResp = $('.table-responsive');
        if (!tableResp.length) return;
        var availableHeight = window.innerHeight - tableResp.offset().top - 30;
        tableResp.css({ 'max-height': Math.max(300, availableHeight) + 'px', 'overflow-y': 'auto' });
    }

    // ===== Jahr-Dropdown =====
    function initializeYearDropdown() {
        var $yearSelect = $('#yearSelect').empty();
        var currentYear = new Date().getFullYear();
        for (var year = currentYear; year >= currentYear - 3; year--) {
            var $option = $('<option></option>').val(year).text(year);
            if (year === currentYear) $option.prop('selected', true);
            $yearSelect.append($option);
        }
    }

    // ===== Daten laden =====
    function loadData(year) {
        var $tbody = $('#mitgliederTabelle tbody');
        $tbody.html(
            '<tr><td colspan="9" class="text-center py-4">' +
            '<div class="spinner-border spinner-border-sm me-2"></div>' +
            'Lade Daten...</td></tr>'
        );

        $.ajax({
            url: 'endschresultate/load_endschresultate.php',
            type: 'GET',
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                setTimeout(calculateTableHeight, 100);
                buildMobileEndCards();
            },
            error: function() {
                $tbody.html(
                    '<tr><td colspan="9" class="text-center text-danger py-4">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Fehler beim Laden der Daten</td></tr>'
                );
                msvToast('Fehler beim Laden der Daten', 'error');
            }
        });
    }

    // ===== Summen berechnen =====
    function calculateSum(selector, sumId) {
        var sum = 0;
        $(selector).each(function() { sum += parseInt($(this).val()) || 0; });
        $('#' + sumId).text(sum);
    }

    function calculateAllSums() {
        calculateSum('.endschuss', 'endstichSumme');
        calculateSum('.schwini-schuss1', 'schwiniSumme1');
        calculateSum('.schwini-schuss2', 'schwiniSumme2');
        calculateSum('.kunst', 'kunstSum');
        calculateSum('.zabig', 'zabigsum');
    }

    // ===== Enter-Navigation im Modal =====
    function setupEnterNavigation() {
        var $inputs = $('#schussModal .focusable-input:not(:disabled)');
        $inputs.off('keydown.nav').on('keydown.nav', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                var currentIndex = $inputs.index(this);
                var nextIndex = currentIndex + 1;
                if (nextIndex < $inputs.length) {
                    $inputs.eq(nextIndex).focus().select();
                } else {
                    $('#schussModal button[type="submit"]').focus();
                }
            }
        });
    }

    // ===== Stich-Verfügbarkeit =====
    function updateStichAvailability(geloesteStiche) {
        var stichElements = {
            'END': '#endstichSchuesse',
            'SCHWINI_P1': '#schwiniSchuesse',
            'SCHWINI_P2': '#schwiniSchuesse',
            'KUNST': '#kunstSchuesse',
            'GLUECK': '#glueckSchuesse',
            'ZABIG': '#zabigSchuesse',
            'DIFF': '#Differenzler',
            'SIEUNDER': '#sieunderSchuesse'
        };

        var activateElements = {};
        var hasSchiwiniP1 = geloesteStiche.indexOf('SCHWINI_P1') !== -1;
        var hasSchiwiniP2 = geloesteStiche.indexOf('SCHWINI_P2') !== -1;

        geloesteStiche.forEach(function(stichCode) {
            if (stichElements[stichCode]) {
                activateElements[stichElements[stichCode]] = true;
            }
        });

        $('.shooting-category[data-stich], .shooting-category#Differenzler, .shooting-category#Absendenanmeldung').each(function() {
            var $element = $(this);
            var elementId = '#' + $element.attr('id');

            // Absenden immer aktiv
            if (elementId === '#Absendenanmeldung') {
                $element.removeClass('disabled');
                $element.find('input').prop('disabled', false);
                return;
            }

            // Schwini Spezialbehandlung
            if (elementId === '#schwiniSchuesse') {
                if (hasSchiwiniP1 || hasSchiwiniP2) {
                    $element.removeClass('disabled');
                    $('.schwini-passe-1, .schwini-passe-2').removeClass('schwini-pass-disabled');

                    if (hasSchiwiniP1 && !hasSchiwiniP2) {
                        $('.schwini-schuss1').prop('disabled', false);
                        $('.schwini-schuss2').prop('disabled', true).val('');
                        $('.schwini-passe-2').addClass('schwini-pass-disabled');
                    } else if (!hasSchiwiniP1 && hasSchiwiniP2) {
                        $('.schwini-schuss1').prop('disabled', true).val('');
                        $('.schwini-schuss2').prop('disabled', false);
                        $('.schwini-passe-1').addClass('schwini-pass-disabled');
                    } else {
                        $('.schwini-schuss1').prop('disabled', false);
                        $('.schwini-schuss2').prop('disabled', false);
                    }
                } else {
                    $element.addClass('disabled');
                    $element.find('input').prop('disabled', true).val('');
                }
                return;
            }

            // Normale Stiche
            if (activateElements[elementId]) {
                $element.removeClass('disabled');
                $element.find('input').prop('disabled', false);
            } else {
                $element.addClass('disabled');
                $element.find('input').prop('disabled', true).val('');
            }
        });

        setupEnterNavigation();
    }

    // ===== Sie und Er Unique Visualisierung =====
    function updateSieErUniqueVisualization() {
        var valuePositions = {};

        $('.sie-er-mitglied').each(function() {
            var value = parseFloat($(this).val() || 0);
            var position = $(this).data('position');

            if (value > 0) {
                var intValue = Math.floor(value);
                if (!valuePositions[intValue]) valuePositions[intValue] = [];
                valuePositions[intValue].push({
                    position: position,
                    element: $(this),
                    value: value
                });
            }
        });

        // Reset
        $('.sie-er-schuss').css({ 'border-color': '', 'background-color': '' });

        var uniqueValues = [];
        var processedValues = {};

        Object.keys(valuePositions).forEach(function(value) {
            var positions = valuePositions[value];
            if (positions.length === 1) {
                positions[0].element.css({ 'border': '2px solid #28a745', 'background-color': '#f0fff4' });
                uniqueValues.push(parseInt(value));
            } else {
                positions.forEach(function(pos, index) {
                    if (index === 0) {
                        pos.element.css({ 'border': '2px solid #28a745', 'background-color': '#f0fff4' });
                        if (!processedValues[value]) {
                            uniqueValues.push(parseInt(value));
                            processedValues[value] = true;
                        }
                    } else {
                        pos.element.css({ 'border': '2px solid #dc3545', 'background-color': '#fff5f5', 'opacity': '0.7' });
                    }
                });
            }
        });

        // Preview Badges
        var previewHTML = '';
        var processedForPreview = {};
        $('.sie-er-mitglied').each(function() {
            var value = parseFloat($(this).val() || 0);
            if (value > 0) {
                var intValue = Math.floor(value);
                if (processedForPreview[intValue]) {
                    previewHTML += '<span class="badge bg-primary bg-opacity-25 text-primary" style="text-decoration: line-through; font-size: 0.7rem;">' + value + '</span> ';
                } else {
                    previewHTML += '<span class="badge bg-primary" style="font-size: 0.7rem;">' + value + '</span> ';
                    processedForPreview[intValue] = true;
                }
            }
        });
        $('#previewBadges').html(previewHTML || '<span class="text-muted small">Noch keine Werte</span>');

        var uniqueSum = uniqueValues.reduce(function(sum, val) { return sum + val; }, 0);
        $('#uniqueTotal').html('<i class="bi bi-calculator me-1"></i>Total: ' + uniqueSum);
    }

    // ===== Edit Modal öffnen =====
    function openEditModal(mitgliedID) {
        var selectedYear = $('#yearSelect').val();
        var name = $('[data-id="' + mitgliedID + '"]').closest('tr').find('td:first').text();

        $('#schussModalLabel').html('<i class="bi bi-target me-2"></i> Erfassen - ' + name);
        $('#mitgliedID').val(mitgliedID);
        $('#schussForm')[0].reset();
        $('.total-display').text('0');

        // Alle Stiche erstmal deaktivieren
        $('.shooting-category[data-stich], .shooting-category#Differenzler')
            .addClass('disabled')
            .find('input').prop('disabled', true).val('');

        // Absenden immer aktiv
        $('.shooting-category#Absendenanmeldung')
            .removeClass('disabled')
            .find('input').prop('disabled', false);

        $('#schussModal').modal('show');

        $.ajax({
            url: 'endschresultate/load_schussdaten.php',
            type: 'GET',
            data: { mitgliedID: mitgliedID, year: selectedYear },
            dataType: 'json',
            success: function(data) {
                if (data.geloesteStiche && data.geloesteStiche.length > 0) {
                    updateStichAvailability(data.geloesteStiche);
                }

                for (var key in data) {
                    if (key !== 'geloesteStiche') {
                        var $field = $('#' + key);
                        if ($field.length) $field.val(data[key]);
                    }
                }

                calculateAllSums();
                updateSieErUniqueVisualization();

                setTimeout(function() {
                    $('#schussModal .focusable-input:not(:disabled):first').focus().select();
                }, 300);
            },
            error: function() {
                msvToast('Fehler beim Laden der Schussdaten', 'error');
            }
        });
    }

    // ===== Delete Bestätigung anzeigen =====
    function showDeleteConfirmation(type, name) {
        var message;
        if (type === 'all') {
            message = '<div class="d-flex align-items-center">' +
                '<i class="bi bi-exclamation-triangle text-danger me-3" style="font-size: 2rem;"></i>' +
                '<div><strong>Möchtest du wirklich ALLE Resultate des aktuellen Jahres löschen?</strong>' +
                '<br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden!</small></div></div>';
        } else {
            message = '<div class="d-flex align-items-center">' +
                '<i class="bi bi-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>' +
                '<div><strong>Möchtest du die Resultate von "' + name + '" wirklich löschen?</strong>' +
                '<br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden.</small></div></div>';
        }
        $('#confirmModalBody').html(message);
        $('#confirmModal').modal('show');
    }

    // ===== Delete ausführen =====
    function executeDelete() {
        var $btn = $('#confirmAction');
        var originalText = $btn.html();
        var selectedYear = $('#yearSelect').val();

        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Verarbeite...');

        var ajaxConfig = {
            method: 'POST',
            data: {
                jahr: selectedYear,
                year: selectedYear,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
                $('#confirmModal').modal('hide');
            }
        };

        if (deleteType === 'all') {
            ajaxConfig.url = 'endschresultate/delete_endschresultate.php';
            ajaxConfig.success = function() {
                msvToast('Alle Resultate erfolgreich gelöscht', 'success');
                setTimeout(function() { loadData(selectedYear); }, 500);
            };
            ajaxConfig.error = function() { msvToast('Fehler beim Löschen', 'error'); };
        } else if (deleteType === 'single' && itemToDelete !== null) {
            ajaxConfig.url = 'endschresultate/delete_endschresultat.php';
            ajaxConfig.data.mitgliedID = itemToDelete;
            ajaxConfig.success = function() {
                msvToast('Resultate erfolgreich gelöscht', 'success');
                setTimeout(function() { loadData(selectedYear); }, 500);
            };
            ajaxConfig.error = function() { msvToast('Fehler beim Löschen', 'error'); };
        }

        $.ajax(ajaxConfig);
    }

    // ===== Event-Handler =====

    // Jahr-Auswahl
    $('#yearSelect').on('change', function() { loadData($(this).val()); });

    // Rangliste
    $('#redirect-btn').on('click', function() { window.location.href = 'endschrang.php'; });

    // Alle löschen
    $('#delall-btn').on('click', function(e) {
        e.preventDefault();
        deleteType = 'all';
        showDeleteConfirmation('all');
    });

    // Einzeln löschen (dynamisch)
    $(document).on('click', '.delete-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        deleteType = 'single';
        itemToDelete = $(this).data('id');
        var name = $(this).closest('tr').find('td:first').text();
        showDeleteConfirmation('single', name);
    });

    // Bestätigung
    $('#confirmAction').on('click', function() { executeDelete(); });

    // Confirm Modal Reset
    $('#confirmModal').on('hidden.bs.modal', function() {
        deleteType = '';
        itemToDelete = null;
    });

    // Edit-Button (dynamisch)
    $(document).on('click', '.edit-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        openEditModal($(this).data('id'));
    });

    // Summen-Berechnung bei Input
    $(document).on('input change', '.endschuss, .schwini-schuss1, .schwini-schuss2, .kunst, .zabig', function() {
        calculateAllSums();
    });

    // Sie und Er Berechnung
    $(document).on('input change', '.sie-er-schuss', function() {
        updateSieErUniqueVisualization();
    });

    // Enter-Navigation bei Modal-Öffnung
    $('#schussModal').on('shown.bs.modal', function() { setupEnterNavigation(); });

    // Schuss-Form Submit
    $('#schussForm').on('submit', function(e) {
        e.preventDefault();

        var $btn = $('#schussModal button[type="submit"]');
        var originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<i class="bi bi-hourglass-split me-2"></i>Speichere...');

        var selectedYear = $('#yearSelect').val();
        var formData = $(this).serialize() + '&jahr=' + selectedYear;

        $.ajax({
            url: 'endschresultate/save_schuss.php',
            type: 'POST',
            data: formData,
            success: function() {
                msvToast('Resultate erfolgreich gespeichert!', 'success');
                $('#schussModal').modal('hide');
                setTimeout(function() { loadData(selectedYear); }, 500);
            },
            error: function() {
                msvToast('Fehler beim Speichern der Resultate', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Global Scroll
    document.addEventListener('wheel', function(e) {
        var tableContainer = $('.table-responsive')[0];
        if (tableContainer && tableContainer.scrollHeight > tableContainer.clientHeight) {
            tableContainer.scrollTop += e.deltaY;
            e.preventDefault();
        }
    }, { passive: false });

    // Resize
    var resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(calculateTableHeight, 150);
    });

    // ===== Mobile Cards =====
    function buildMobileEndCards() {
        const isMobile = window.matchMedia('(max-width: 767.98px)');
        if (!isMobile.matches) return;

        const table = document.getElementById('mitgliederTabelle');
        const container = document.querySelector('#mobileCardsEnd .mobile-cards-scroll');
        if (!table || !container) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
            return;
        }

        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
            return;
        }

        let html = '';
        rows.forEach((row, idx) => {
            const cells = Array.from(row.querySelectorAll('td'));
            if (cells.length < 9) return;

            const memberName = cells[0]?.textContent?.trim() || 'Unbekannt';
            const endstich = cells[1]?.textContent?.trim() || '-';
            const schwini = cells[2]?.textContent?.trim() || '-';
            const kunst = cells[3]?.textContent?.trim() || '-';
            const glueck = cells[4]?.textContent?.trim() || '-';
            const zabig = cells[5]?.textContent?.trim() || '-';
            const sieUndEr = cells[6]?.textContent?.trim() || '-';
            const ansage = cells[7]?.textContent?.trim() || '-';

            // Extract Mitglied-ID from action button
            const actionBtn = cells[8]?.querySelector('button[data-id]');
            const mitgliedId = actionBtn ? actionBtn.getAttribute('data-id') : '';

            html += `
            <div class="mobile-card" data-index="${idx}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                        <div class="fw-bold">${memberName}</div>
                        <small class="text-muted">Endstich: ${endstich} | Schwini: ${schwini}</small>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="mobile-card-body">
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Endstich</span>
                        <span class="mobile-card-detail-value"><strong>${endstich}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Schwini</span>
                        <span class="mobile-card-detail-value"><strong>${schwini}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Kunst</span>
                        <span class="mobile-card-detail-value"><strong>${kunst}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Glück</span>
                        <span class="mobile-card-detail-value"><strong>${glueck}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Zabig</span>
                        <span class="mobile-card-detail-value"><strong>${zabig}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Sie und Er</span>
                        <span class="mobile-card-detail-value"><strong>${sieUndEr}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Ansage</span>
                        <span class="mobile-card-detail-value"><strong>${ansage}</strong></span>
                    </div>
                    <button type="button" class="btn btn-primary w-100 mt-3"
                            onclick="openSchussModal(${mitgliedId})"
                            style="min-height: 48px;">
                        <i class="bi bi-pencil me-2"></i>Bearbeiten
                    </button>
                </div>
            </div>`;
        });

        container.innerHTML = html;
    }

    window.filterMobileEnd = function(searchInput) {
        const query = searchInput.value.toLowerCase();
        const cards = document.querySelectorAll('#mobileCardsEnd .mobile-card');

        let visibleCount = 0;
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const isVisible = text.includes(query);
            card.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        const container = document.querySelector('#mobileCardsEnd .mobile-cards-scroll');
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

    let wasDesktop = window.matchMedia('(min-width: 768px)').matches;
    window.addEventListener('resize', function() {
        const isNowDesktop = window.matchMedia('(min-width: 768px)').matches;
        if (wasDesktop && !isNowDesktop) {
            buildMobileEndCards();
        }
        wasDesktop = isNowDesktop;
    });

    // ===== Init =====
    initializeYearDropdown();
    loadData(new Date().getFullYear());
    setTimeout(calculateTableHeight, 200);
});
</script>

<?php include 'footer.inc.php'; ?>
