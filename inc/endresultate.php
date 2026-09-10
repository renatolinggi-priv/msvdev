<?php
// endresultate.php – Slide-Panel Pattern (wie jmdefinition.php)
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in endresultate.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Seitenspezifische Styles
$page_specific_css = "
/* =========================================
   Endresultate – Slide-Panel Layout
   ========================================= */

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

#resultateContainer {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.desktop-table-container {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
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
    letter-spacing: 0.5px;
    padding: 0.75rem;
    background-color: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table tbody td {
    padding: 0.5rem 0.75rem;
    vertical-align: middle;
    border: none;
    text-align: center;
}

.table tbody td:first-child {
    text-align: left;
}

.results-list-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    overflow: hidden;
    margin-bottom: 0;
    display: flex;
    flex-direction: column;
    flex: 0 1 auto;
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

.spinner-border { color: var(--secondary-color) !important; }

/* =========================================
   Hybrid Rows (klickbare Tabelle)
   ========================================= */
#mitgliederTabelle tbody tr.hybrid-row {
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
    border-bottom: 1px solid #f1f3f4;
}
#mitgliederTabelle tbody tr.hybrid-row:hover {
    background: rgba(99,102,241,0.05);
}
#mitgliederTabelle tbody tr.hybrid-row.selected {
    background: rgba(0,123,255,0.08);
    box-shadow: inset 4px 0 0 #007bff;
}
#mitgliederTabelle tbody tr.hybrid-row[data-has-data='1'] td:first-child {
    box-shadow: inset 4px 0 0 #28a745;
}
#mitgliederTabelle tbody tr.hybrid-row[data-has-data='0'] td:first-child {
    box-shadow: inset 4px 0 0 #dee2e6;
}
#mitgliederTabelle tbody tr.hybrid-row.selected td:first-child {
    box-shadow: inset 4px 0 0 #007bff;
}

/* =========================================
   Fortschrittsbalken
   ========================================= */
.progress-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: var(--border-radius);
    padding: 0.75rem 1.25rem;
    margin-bottom: 0.75rem;
    box-shadow: var(--box-shadow);
    flex-shrink: 0;
}

/* =========================================
   Slide-Panel: Container/Overlay/Header/Body zentral in css/msv-styles.css
   (Breite via panel-width Custom-Property am Panel-Element)
   ========================================= */
.panel-footer {
    padding: 0.75rem 1.25rem;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    flex-shrink: 0;
}

/* =========================================
   Stich-Sektionen im Panel: zentral .shot-* in css/msv-styles.css
   ========================================= */

/* Gelöst-Markierung in der Tabelle (Schütze gelöst, noch kein Resultat) */
.geloest-pill {
    display: inline-block;
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #0f766e;
    background: #ccfbf1;
    border-radius: 999px;
    padding: 0.1rem 0.5rem;
    line-height: 1.4;
    white-space: nowrap;
}
#mitgliederTabelle tbody td .cell-empty { color: #cbd5e1; }
@media (max-width: 576px) {
    .button-toolbar { flex-direction: column; }
    .button-toolbar .btn { width: 100%; }
}

/* =========================================
   Mobile
   ========================================= */
@media (max-width: 767.98px) {
    /* Touch-Target-Grössen (form-controls/.btn) zentral in css/msv-styles.css */
    .desktop-table-container { display: none !important; }
    .mobile-cards-container { display: flex !important; }

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

    /* Panel wird Fullscreen auf Mobile */
    .hybrid-edit-panel {
        width: 100vw;
        right: -100vw;
    }
    .panel-overlay { display: none !important; }

    .panel-footer {
        position: sticky;
        bottom: 0;
    }
    .panel-footer .btn {
        min-height: 48px;
        font-size: 0.9rem;
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
}

@media (min-width: 768px) {
    .mobile-cards-container { display: none !important; }
    /* Kompakte Tabellen-Kopfzeile wie auf den anderen Resultate-Seiten
       (kanti/heim) – vorher 0.85rem/1rem -> wirkte viel zu gross. */
    #resultateContainer #mitgliederTabelle thead th {
        font-size: 0.72rem !important;
        padding: 0.6rem 0.4rem !important;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }
    #resultateContainer #mitgliederTabelle tbody td { padding: 0.35rem 0.4rem !important; }
    /* Mitglied-Spalte: Inhaltsbreite, kein Umbruch -> eine Zeile pro Mitglied */
    #resultateContainer #mitgliederTabelle thead th:first-child,
    #resultateContainer #mitgliederTabelle tbody td:first-child {
        width: 1%;
        white-space: nowrap;
        padding-right: 1.25rem !important;
    }
    #resultateContainer #mitgliederTabelle tbody td:first-child { font-weight: 500; }
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
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-wide">
                <!-- Header -->
                <?php $page_title = 'Endschiessen Resultaterfassung'; include 'partials/page_header.inc.php'; ?>

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
<?php
                        $ac_id = 'endresultateActions';
                        ob_start();
                        ?>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <button id="redirect-btn" type="button" class="btn btn-outline-info btn-sm w-100">
                                                <i class="bi bi-trophy me-1"></i>Rangliste
                                            </button>
                                        </div>
                                    </div>
                                    <div class="border-top mt-2 pt-2 text-end">
                                        <button id="delall-btn" type="button" class="btn btn-link btn-sm text-danger text-decoration-none p-0">
                                            <i class="bi bi-trash me-1"></i>Alle Resultate löschen
                                        </button>
                                    </div>
                        <?php
                        $ac_body = ob_get_clean();
                        include 'partials/action_card.inc.php';
                        ?>
                        </div><!-- Ende flex-row Jahr+Aktionen -->

                        <!-- Fortschrittsbalken -->
                        <div class="progress-card">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fw-semibold small">
                                    <i class="bi bi-people me-1"></i>Erfassungsfortschritt
                                </span>
                                <span class="badge bg-success" id="progressBadge">0 / 0</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" id="progressBar" style="width: 0%"></div>
                            </div>
                        </div>

                        <!-- Tabelle Container -->
                        <div id="resultateContainer">
                            <div class="results-list-card">
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
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="8" class="text-center py-4">
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

<!-- Panel Overlay -->
<div class="panel-overlay" id="panelOverlay"></div>

<!-- Slide-Panel -->
<div class="hybrid-edit-panel" id="editPanel" style="--panel-width: 600px;">
    <div class="panel-header">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="panelPrev" data-tooltip="Vorheriger">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div>
                <h6 class="mb-0" id="panelTitle"><i class="bi bi-target me-2"></i>Erfassen</h6>
                <small class="text-muted" id="panelSubtitle"></small>
            </div>
            <button class="btn btn-sm btn-outline-secondary" id="panelNext" data-tooltip="Nächster">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
        <button class="btn btn-sm btn-outline-secondary" id="panelClose">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="panel-body" id="panelBody">
        <input type="hidden" id="mitgliedID" name="mitgliedID">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <!-- Endstich -->
        <div class="shot-section shot-first" id="endstichSchuesse" data-stich="END">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-bullseye"></i>Endstich</span>
                <span class="shot-status">Nicht gelöst</span>
                <span class="shot-total" id="endstichSumme">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-row">
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=10; $i++): ?>
                        <input type="number" class="shot-input endschuss focusable-input" id="Schuss<?= $i ?>" name="Schuss<?= $i ?>" min="0" max="10" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="shot-row">
                    <span class="shot-row-label">Tiefschuss</span>
                    <input type="number" class="shot-input shot-input-wide focusable-input" id="Tiefschuss" name="Tiefschuss" min="0" max="100" inputmode="numeric">
                </div>
            </div>
        </div>

        <!-- Schwini -->
        <div class="shot-section" id="schwiniSchuesse" data-stich="SCHWINI">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-piggy-bank"></i>Schwini</span>
                <span class="shot-status">Nicht gelöst</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-row schwini-passe-1">
                    <span class="shot-row-label">Passe 1</span>
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=6; $i++): ?>
                        <input type="number" class="shot-input schwini-schuss1 focusable-input" id="P1Schuss<?= $i ?>" name="P1Schuss<?= $i ?>" min="0" max="10" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                    <span class="shot-status">Nicht gelöst</span>
                    <span class="shot-total" id="schwiniSumme1">0</span>
                </div>
                <div class="shot-row schwini-passe-2">
                    <span class="shot-row-label">Passe 2</span>
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=6; $i++): ?>
                        <input type="number" class="shot-input schwini-schuss2 focusable-input" id="P2Schuss<?= $i ?>" name="P2Schuss<?= $i ?>" min="0" max="10" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                    <span class="shot-status">Nicht gelöst</span>
                    <span class="shot-total" id="schwiniSumme2">0</span>
                </div>
            </div>
        </div>

        <!-- Kunst + Glück -->
        <div class="shot-cols">
            <div class="shot-section" id="kunstSchuesse" data-stich="KUNST">
                <div class="shot-section-head">
                    <span class="shot-section-title"><i class="bi bi-palette"></i>Kunst</span>
                    <span class="shot-status">Nicht gelöst</span>
                    <span class="shot-total" id="kunstSum">0</span>
                </div>
                <div class="shot-section-body">
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=5; $i++): ?>
                        <input type="number" class="shot-input kunst focusable-input" id="KSchuss<?= $i ?>" name="KSchuss<?= $i ?>" min="0" max="100" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <div class="shot-section" id="glueckSchuesse" data-stich="GLUECK">
                <div class="shot-section-head">
                    <span class="shot-section-title"><i class="bi bi-clover"></i>Glück</span>
                    <span class="shot-status">Nicht gelöst</span>
                </div>
                <div class="shot-section-body">
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=3; $i++): ?>
                        <input type="number" class="shot-input glueck focusable-input" id="GSchuss<?= $i ?>" name="GSchuss<?= $i ?>" min="0" max="100" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zabig -->
        <div class="shot-section" id="zabigSchuesse" data-stich="ZABIG">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-moon-stars"></i>Zabig</span>
                <span class="shot-status">Nicht gelöst</span>
                <span class="shot-total" id="zabigsum">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-grid">
                    <?php for ($i=1; $i<=6; $i++): ?>
                    <input type="number" class="shot-input zabig focusable-input" id="ZSchuss<?= $i ?>" name="ZSchuss<?= $i ?>" min="0" max="100" inputmode="numeric">
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Sie und Er -->
        <div class="shot-section" id="sieunderSchuesse" data-stich="SIEUNDER">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-people"></i>Sie und Er <span class="shot-hint">Schüsse 6–10, Mitglied</span></span>
                <span class="shot-status">Nicht gelöst</span>
                <span class="shot-total" id="uniqueTotal" data-tooltip="Total der eindeutigen Werte">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-grid">
                    <?php for ($i=6; $i<=10; $i++): ?>
                    <input type="number"
                           class="shot-input shot-input-dec sie-er-schuss sie-er-mitglied focusable-input"
                           id="SieErSchuss<?= $i ?>"
                           name="SieErSchuss<?= $i ?>"
                           data-position="<?= $i ?>"
                           data-source="mitglied"
                           min="0" max="10" step="0.1"
                           placeholder="<?= $i ?>"
                           inputmode="decimal">
                    <?php endfor; ?>
                </div>
                <div class="shot-hint mt-2">Zusammen mit den Schüssen 1–5 der Partnerin. Jeder Wert zählt nur einmal, Doppelte sind rot durchgestrichen.</div>
            </div>
        </div>

        <!-- Ansage + Absenden -->
        <div class="shot-cols">
            <div class="shot-section" id="Differenzler">
                <div class="shot-section-head">
                    <span class="shot-section-title"><i class="bi bi-chat-square-text"></i>Ansage <span class="shot-hint">Differenzler</span></span>
                    <span class="shot-status">Nicht gelöst</span>
                </div>
                <div class="shot-section-body">
                    <input type="number" class="shot-input shot-input-wide focusable-input" id="Ansage" name="Ansage" min="0" max="999" inputmode="numeric">
                </div>
            </div>
            <div class="shot-section" id="Absendenanmeldung">
                <div class="shot-section-head">
                    <span class="shot-section-title"><i class="bi bi-calendar-check"></i>Absenden</span>
                </div>
                <div class="shot-section-body">
                    <input type="text" class="form-control form-control-sm focusable-input" id="AbsendenAnmeldung" name="AbsendenAnmeldung" placeholder="Anmeldung">
                </div>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <div class="d-flex gap-2 w-100">
            <button type="button" class="btn btn-outline-danger btn-sm" id="panelDeleteBtn">
                <i class="bi bi-trash"></i>
            </button>
            <button type="button" class="btn btn-outline-primary flex-fill" id="panelSaveBtn">
                <i class="bi bi-save me-1"></i>Speichern
            </button>
            <button type="button" class="btn btn-outline-primary flex-fill" id="panelSaveNextBtn">
                Speichern & Nächster <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    // =========================================
    //  EndEditPanel – Slide-Panel Steuerung
    // =========================================
    const EndEditPanel = {
        currentMitgliedId: null,
        allRows: [],
        currentIndex: -1,
        _loadingXhr: null,

        open(mitgliedId) {
            this.currentMitgliedId = mitgliedId;
            this.currentIndex = this.allRows.findIndex(r => r.id == mitgliedId);

            // Zeile markieren
            $('.hybrid-row').removeClass('selected');
            if (this.currentIndex >= 0) {
                const $row = $(this.allRows[this.currentIndex].tr);
                $row.addClass('selected');
                this.allRows[this.currentIndex].tr.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }

            // Titel setzen
            const name = this.currentIndex >= 0
                ? $(this.allRows[this.currentIndex].tr).find('td:first').text().trim()
                : '';
            $('#panelTitle').html('<i class="bi bi-target me-2"></i>' + name);
            $('#panelSubtitle').text((this.currentIndex + 1) + ' / ' + this.allRows.length);

            // Navigation
            $('#panelPrev').prop('disabled', this.currentIndex <= 0);
            $('#panelNext').prop('disabled', this.currentIndex >= this.allRows.length - 1);

            // Hidden Field
            $('#mitgliedID').val(mitgliedId);

            // Form zurücksetzen
            this.resetForm();

            // Panel öffnen
            $('#editPanel').addClass('open');
            $('#panelOverlay').addClass('show');

            // Panel-Body nach oben scrollen
            $('#panelBody').scrollTop(0);

            // Daten laden
            this.loadSchussdaten(mitgliedId);
        },

        close() {
            $('#editPanel').removeClass('open');
            $('#panelOverlay').removeClass('show');
            $('.hybrid-row').removeClass('selected');
            this.currentMitgliedId = null;
            this.currentIndex = -1;
            if (this._loadingXhr) {
                this._loadingXhr.abort();
                this._loadingXhr = null;
            }
        },

        resetForm() {
            // Alle Inputs leeren
            $('#editPanel .focusable-input').val('');
            $('#editPanel .shot-input').val('').removeClass('filled is-unique is-dup');
            $('#editPanel .shot-total').text('0');
            $('.schwini-passe-1, .schwini-passe-2').removeClass('schwini-pass-disabled');

            // Alle Stiche deaktivieren
            $('.shot-section[data-stich], .shot-section#Differenzler').addClass('disabled')
                .find('input').prop('disabled', true).val('');

            // Absenden immer aktiv
            $('#Absendenanmeldung').removeClass('disabled')
                .find('input').prop('disabled', false);
            // Absenden-Feld selbst (falls es kein Kind-Input hat, sondern selbst das Feld ist)
            $('#AbsendenAnmeldung').prop('disabled', false);
        },

        loadSchussdaten(mitgliedId) {
            const year = $('#yearSelect').val();

            // Vorherigen Request abbrechen
            if (this._loadingXhr) this._loadingXhr.abort();

            this._loadingXhr = $.ajax({
                url: 'endschresultate/load_schussdaten.php',
                type: 'GET',
                data: { mitgliedID: mitgliedId, year: year },
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
                    refreshFilled();

                    setTimeout(function() {
                        $('#editPanel .focusable-input:not(:disabled):first').focus().select();
                    }, 300);
                },
                error: function(xhr) {
                    if (xhr.statusText !== 'abort') {
                        msvToast('Fehler beim Laden der Schussdaten', 'error');
                    }
                },
                complete: function() {
                    EndEditPanel._loadingXhr = null;
                }
            });
        },

        navigate(direction) {
            const newIndex = this.currentIndex + direction;
            if (newIndex < 0 || newIndex >= this.allRows.length) return;
            this.open(this.allRows[newIndex].id);
        },

        save(callback) {
            const $saveBtn = $('#panelSaveBtn');
            const $saveNextBtn = $('#panelSaveNextBtn');
            const originalSave = $saveBtn.html();
            const originalNext = $saveNextBtn.html();

            $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
            $saveNextBtn.prop('disabled', true);

            const formData = this.collectFormData();

            $.ajax({
                url: 'endschresultate/save_schuss.php',
                type: 'POST',
                data: formData,
                success: function() {
                    msvToast('Resultate gespeichert!', 'success');

                    // Zeile als "hat Daten" markieren
                    if (EndEditPanel.currentIndex >= 0) {
                        EndEditPanel.allRows[EndEditPanel.currentIndex].hasData = true;
                        $(EndEditPanel.allRows[EndEditPanel.currentIndex].tr).attr('data-has-data', '1');
                    }
                    EndEditPanel.updateProgress();

                    if (callback) {
                        callback();
                    } else {
                        // Tabelle neu laden
                        loadData($('#yearSelect').val());
                    }
                },
                error: function() {
                    msvToast('Fehler beim Speichern', 'error');
                },
                complete: function() {
                    $saveBtn.prop('disabled', false).html(originalSave);
                    $saveNextBtn.prop('disabled', false).html(originalNext);
                }
            });
        },

        saveAndNext() {
            this.save(function() {
                // Nächsten Schützen ohne Daten finden
                for (let i = EndEditPanel.currentIndex + 1; i < EndEditPanel.allRows.length; i++) {
                    if (!EndEditPanel.allRows[i].hasData) {
                        EndEditPanel.open(EndEditPanel.allRows[i].id);
                        return;
                    }
                }
                // Auch vor dem aktuellen suchen
                for (let i = 0; i < EndEditPanel.currentIndex; i++) {
                    if (!EndEditPanel.allRows[i].hasData) {
                        EndEditPanel.open(EndEditPanel.allRows[i].id);
                        return;
                    }
                }
                // Alle erfasst
                msvToast('Alle Schützen erfasst!', 'success');
                EndEditPanel.close();
                loadData($('#yearSelect').val());
            });
        },

        collectFormData() {
            const data = {
                mitgliedID: $('#mitgliedID').val(),
                jahr: $('#yearSelect').val(),
                csrf_token: $('#editPanel input[name="csrf_token"]').val()
            };

            // Alle benannten Inputs aus dem Panel sammeln
            $('#editPanel input[name]:not([type="hidden"])').each(function() {
                if (!this.disabled) {
                    data[this.name] = $(this).val() || '';
                }
            });

            return data;
        },

        buildRowIndex() {
            this.allRows = [];
            $('#mitgliederTabelle tbody tr.hybrid-row').each((_, tr) => {
                this.allRows.push({
                    id: $(tr).data('mitglied-id'),
                    hasData: String($(tr).data('has-data')) === '1',
                    tr: tr
                });
            });
        },

        updateProgress() {
            const total = this.allRows.length;
            const withData = this.allRows.filter(r => r.hasData).length;
            const pct = total > 0 ? Math.round((withData / total) * 100) : 0;
            $('#progressBadge').text(withData + ' / ' + total);
            $('#progressBar').css('width', pct + '%');
        }
    };

    // =========================================
    //  Jahr-Dropdown
    // =========================================
    function initializeYearDropdown() {
        var $yearSelect = $('#yearSelect').empty();
        var currentYear = new Date().getFullYear();
        var selectedYear = <?php echo isset($_GET['year']) ? (int)$_GET['year'] : 'currentYear'; ?>;
        for (var year = currentYear; year >= currentYear - 3; year--) {
            var $option = $('<option></option>').val(year).text(year);
            if (year === selectedYear) $option.prop('selected', true);
            $yearSelect.append($option);
        }
    }

    // =========================================
    //  Daten laden
    // =========================================
    function loadData(year) {
        EndEditPanel.close();

        var $tbody = $('#mitgliederTabelle tbody');
        $tbody.html(
            '<tr><td colspan="8" class="text-center py-4">' +
            '<div class="spinner-border spinner-border-sm me-2"></div>' +
            'Lade Daten...</td></tr>'
        );

        $.ajax({
            url: 'endschresultate/load_endschresultate.php',
            type: 'GET',
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                EndEditPanel.buildRowIndex();
                EndEditPanel.updateProgress();
                buildMobileEndCards();
            },
            error: function() {
                $tbody.html(
                    '<tr><td colspan="8" class="text-center text-danger py-4">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Fehler beim Laden der Daten</td></tr>'
                );
                msvToast('Fehler beim Laden der Daten', 'error');
            }
        });
    }

    // =========================================
    //  Summen berechnen
    // =========================================
    function calculateSum(selector, sumId) {
        var sum = 0;
        $(selector).each(function() { sum += parseInt($(this).val()) || 0; });
        $('#' + sumId).text(sum);
    }

    // Ausgefuellte Felder gruen markieren (wie kanti/heim .filled)
    function refreshFilled() {
        $('#editPanel .shot-input').each(function() {
            $(this).toggleClass('filled', this.value !== '');
        });
    }

    function calculateAllSums() {
        calculateSum('.endschuss', 'endstichSumme');
        calculateSum('.schwini-schuss1', 'schwiniSumme1');
        calculateSum('.schwini-schuss2', 'schwiniSumme2');
        calculateSum('.kunst', 'kunstSum');
        calculateSum('.zabig', 'zabigsum');
    }

    // =========================================
    //  Enter-Navigation im Panel
    // =========================================
    function setupEnterNavigation() {
        var $inputs = $('#editPanel .focusable-input:not(:disabled)');
        $inputs.off('keydown.nav').on('keydown.nav', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                var currentIndex = $inputs.index(this);
                var nextIndex = currentIndex + 1;
                if (nextIndex < $inputs.length) {
                    $inputs.eq(nextIndex).focus().select();
                } else {
                    $('#panelSaveBtn').focus();
                }
            }
        });
    }

    // =========================================
    //  Stich-Verfügbarkeit
    // =========================================
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
        var hasSchwiniP1 = geloesteStiche.indexOf('SCHWINI_P1') !== -1;
        var hasSchwiniP2 = geloesteStiche.indexOf('SCHWINI_P2') !== -1;

        geloesteStiche.forEach(function(stichCode) {
            if (stichElements[stichCode]) {
                activateElements[stichElements[stichCode]] = true;
            }
        });

        $('.shot-section[data-stich], .shot-section#Differenzler, .shot-section#Absendenanmeldung').each(function() {
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
                if (hasSchwiniP1 || hasSchwiniP2) {
                    $element.removeClass('disabled');
                    $('.schwini-passe-1, .schwini-passe-2').removeClass('schwini-pass-disabled');

                    if (hasSchwiniP1 && !hasSchwiniP2) {
                        $('.schwini-schuss1').prop('disabled', false);
                        $('.schwini-schuss2').prop('disabled', true).val('');
                        $('.schwini-passe-2').addClass('schwini-pass-disabled');
                    } else if (!hasSchwiniP1 && hasSchwiniP2) {
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

    // =========================================
    //  Sie und Er Unique Visualisierung
    // =========================================
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
        $('.sie-er-schuss').removeClass('is-unique is-dup');

        var uniqueValues = [];
        var processedValues = {};

        Object.keys(valuePositions).forEach(function(value) {
            var positions = valuePositions[value];
            if (positions.length === 1) {
                positions[0].element.addClass('is-unique');
                uniqueValues.push(parseInt(value));
            } else {
                positions.forEach(function(pos, index) {
                    if (index === 0) {
                        pos.element.addClass('is-unique');
                        if (!processedValues[value]) {
                            uniqueValues.push(parseInt(value));
                            processedValues[value] = true;
                        }
                    } else {
                        pos.element.addClass('is-dup');
                    }
                });
            }
        });

        var uniqueSum = uniqueValues.reduce(function(sum, val) { return sum + val; }, 0);
        $('#uniqueTotal').text(uniqueSum);
    }

    // =========================================
    //  Event-Handler
    // =========================================

    // Jahr-Auswahl
    $('#yearSelect').on('change', function() {
        loadData($(this).val());
    });

    // Rangliste
    $('#redirect-btn').on('click', function() { window.location.href = 'endschrang.php'; });

    // Alle löschen
    $('#delall-btn').on('click', async function(e) {
        e.preventDefault();
        const r = await msvConfirm(
            'Möchtest du wirklich ALLE Resultate des aktuellen Jahres löschen?',
            'Alle Daten löschen',
            'Ja, alles löschen'
        );
        if (!r.isConfirmed) return;

        const selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'endschresultate/delete_endschresultate.php',
            method: 'POST',
            data: {
                jahr: selectedYear,
                year: selectedYear,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function() {
                msvToast('Alle Resultate erfolgreich gelöscht', 'success');
                EndEditPanel.close();
                loadData(selectedYear);
            },
            error: function() { msvToast('Fehler beim Löschen', 'error'); }
        });
    });

    // Klick auf Tabellenzeile → Panel öffnen
    $(document).on('click', '.hybrid-row', function() {
        const mitgliedId = $(this).data('mitglied-id');
        if (mitgliedId) EndEditPanel.open(mitgliedId);
    });

    // Panel schliessen
    $('#panelClose, #panelOverlay').on('click', function() { EndEditPanel.close(); });

    // Escape schliesst Panel
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#editPanel').hasClass('open')) {
            EndEditPanel.close();
            e.stopImmediatePropagation();
        }
    });

    // Panel Navigation
    $('#panelPrev').on('click', function() { EndEditPanel.navigate(-1); });
    $('#panelNext').on('click', function() { EndEditPanel.navigate(1); });

    // Speichern
    $('#panelSaveBtn').on('click', function() { EndEditPanel.save(); });

    // Speichern & Nächster
    $('#panelSaveNextBtn').on('click', function() { EndEditPanel.saveAndNext(); });

    // Löschen aus Panel
    $('#panelDeleteBtn').on('click', async function() {
        const mitgliedId = EndEditPanel.currentMitgliedId;
        if (!mitgliedId) return;

        const name = $('#panelTitle').text().replace('Erfassen', '').replace('–', '').trim();
        const r = await msvConfirm(
            'Möchtest du die Resultate von "' + name + '" wirklich löschen?',
            'Resultate löschen',
            'Ja, löschen'
        );
        if (!r.isConfirmed) return;

        $.ajax({
            url: 'endschresultate/delete_endschresultat.php',
            method: 'POST',
            data: {
                mitgliedID: mitgliedId,
                jahr: $('#yearSelect').val(),
                csrf_token: $('#editPanel input[name="csrf_token"]').val()
            },
            success: function() {
                msvToast('Resultate gelöscht', 'success');
                EndEditPanel.close();
                loadData($('#yearSelect').val());
            },
            error: function() { msvToast('Fehler beim Löschen', 'error'); }
        });
    });

    // Summen-Berechnung bei Input
    $(document).on('input change', '.endschuss, .schwini-schuss1, .schwini-schuss2, .kunst, .zabig', function() {
        calculateAllSums();
    });

    // Feld ausgefuellt -> gruen
    $(document).on('input change', '#editPanel .shot-input', function() {
        $(this).toggleClass('filled', this.value !== '');
    });

    // Sie und Er Berechnung
    $(document).on('input change', '.sie-er-schuss', function() {
        updateSieErUniqueVisualization();
    });

    // =========================================
    //  Mobile Cards
    // =========================================
    function buildMobileEndCards() {
        const isMobile = window.matchMedia('(max-width: 767.98px)');
        if (!isMobile.matches) return;

        const table = document.getElementById('mitgliederTabelle');
        const container = document.querySelector('#mobileCardsEnd .mobile-cards-scroll');
        if (!table || !container) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten gefunden</div></div>';
            return;
        }

        const rows = tbody.querySelectorAll('tr.hybrid-row');
        if (rows.length === 0) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten gefunden</div></div>';
            return;
        }

        let html = '';
        rows.forEach((row, idx) => {
            const cells = Array.from(row.querySelectorAll('td'));
            if (cells.length < 8) return;

            const mitgliedId = row.dataset.mitgliedId;
            const hasData = row.dataset.hasData === '1';
            const memberName = cells[0]?.textContent?.trim() || 'Unbekannt';
            const endstich = cells[1]?.textContent?.trim() || '-';
            const schwini = cells[2]?.textContent?.trim() || '-';
            const kunst = cells[3]?.textContent?.trim() || '-';
            const glueck = cells[4]?.textContent?.trim() || '-';
            const zabig = cells[5]?.textContent?.trim() || '-';
            const sieUndEr = cells[6]?.textContent?.trim() || '-';
            const ansage = cells[7]?.textContent?.trim() || '-';

            const statusDot = hasData
                ? '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:6px;"></span>'
                : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#cbd5e1;margin-right:6px;"></span>';

            html += `
            <div class="mobile-card" data-index="${idx}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                        <div class="fw-bold">${statusDot}${memberName}</div>
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
                    <button type="button" class="btn btn-outline-primary w-100 mt-3"
                            onclick="EndEditPanel.open(${mitgliedId})"
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

    // Responsive Rebuild
    let wasDesktop = window.matchMedia('(min-width: 768px)').matches;
    window.addEventListener('resize', function() {
        const isNowDesktop = window.matchMedia('(min-width: 768px)').matches;
        if (wasDesktop && !isNowDesktop) {
            buildMobileEndCards();
        }
        wasDesktop = isNowDesktop;
    });

    // EndEditPanel global verfügbar machen für Mobile-Cards onclick
    window.EndEditPanel = EndEditPanel;

    // =========================================
    //  Init
    // =========================================
    initializeYearDropdown();
    loadData($('#yearSelect').val());
});
</script>

<?php include 'footer.inc.php'; ?>
