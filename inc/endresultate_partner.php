<?php
// endresultate_partner.php – Slide-Panel Pattern (wie endresultate.php)
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in endresultate_partner.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Seitenspezifische Styles
$page_specific_css = "
/* =========================================
   Partner Endresultate – Slide-Panel Layout
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

#partnerResultateForm {
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

.spinner-border { color: var(--secondary-color) !important; }

/* =========================================
   Sie und Er: Kompakte Dot-Darstellung
   ========================================= */
.dot-row {
    display: flex;
    align-items: center;
    gap: 2px;
    justify-content: center;
}
.shot-dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 700;
    line-height: 1;
}
.dot-partner {
    background: #fee2e2;
    color: #dc2626;
}
.dot-partner.unique {
    background: #dc2626;
    color: #fff;
}
.dot-mitglied {
    background: #dbeafe;
    color: #2563eb;
}
.dot-mitglied.unique {
    background: #2563eb;
    color: #fff;
}
.dot-struck {
    text-decoration: line-through;
    opacity: 0.45;
}
.dot-empty {
    background: #f9fafb;
    color: #cbd5e1;
    border: 1px dashed #e2e8f0;
}
.dot-sep {
    color: #cbd5e1;
    font-size: 0.7rem;
    margin: 0 1px;
}
.sie-er-total {
    font-weight: 700;
    font-size: 0.8rem;
    color: #1e293b;
    min-width: 28px;
    text-align: right;
    margin-left: 6px;
}
.sie-er-header-legend {
    font-size: 0.55rem;
    font-weight: 400;
    text-transform: none;
    letter-spacing: 0;
    color: #94a3b8;
    margin-top: 2px;
}

/* =========================================
   Hybrid Rows (klickbare Tabelle)
   ========================================= */
#partnerTabelle tbody tr.hybrid-row {
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
    border-bottom: 1px solid #f1f3f4;
}
#partnerTabelle tbody tr.hybrid-row:hover {
    background: rgba(99,102,241,0.05);
}
#partnerTabelle tbody tr.hybrid-row.selected {
    background: rgba(0,123,255,0.08);
    box-shadow: inset 4px 0 0 #007bff;
}
#partnerTabelle tbody tr.hybrid-row.selected td:first-child {
    box-shadow: inset 4px 0 0 #007bff;
}
#partnerTabelle tbody tr.hybrid-row.table-warning {
    cursor: pointer;
}
#partnerTabelle tbody tr.hybrid-row.table-warning:hover {
    background: rgba(255, 193, 7, 0.15);
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
   Stich-Karten im Panel
   ========================================= */
.panel-stich-card {
    background: transparent;
    border: none;
    border-top: 1px solid #e2e8f0;
    border-radius: 0;
    padding: 0.625rem 0 0.375rem;
    margin-bottom: 0.25rem;
}

.row > .col-6 > .panel-stich-card {
    border-top: none;
    padding-top: 0;
}

.panel-stich-card h6 {
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 0.375rem;
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

    .panel-stich-card .small-input {
        width: 38px !important;
        min-height: 38px !important;
        font-size: 14px !important;
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
                <?php $page_title = 'Partner Endresultate'; include 'partials/page_header.inc.php'; ?>

                <div class="content-background">
                    <form id="partnerResultateForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr-Auswahl + Hinzufügen + Aktionen -->
                        <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
                            <div class="d-flex align-items-center gap-2">
                                <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                                    <i class="bi bi-calendar3 me-1"></i>Jahr:
                                </label>
                                <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
                            </div>

                            <button id="add-partner-btn" type="button" class="btn btn-outline-success btn-sm">
                                <i class="bi bi-plus me-1"></i>Partnerin hinzufügen
                            </button>

                            <!-- Aktionsbereich (Bootstrap Collapse) -->
<?php
                            $ac_id = 'partnerActions';
                            ob_start();
                            ?>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <button id="redirect-btn" type="button" class="btn btn-outline-info btn-sm w-100">
                                                    <i class="bi bi-trophy me-1"></i>Rangliste
                                                </button>
                                            </div>
                                            <div class="col-6">
                                                <button id="delete-year-btn" type="button" class="btn btn-outline-danger btn-sm w-100">
                                                    <i class="bi bi-trash me-1"></i>Löschen
                                                </button>
                                            </div>
                                        </div>
                            <?php
                            $ac_body = ob_get_clean();
                            include 'partials/action_card.inc.php';
                            ?>
                        </div>

                        <!-- Tabelle Container -->
                        <div id="resultateContainer">
                            <div class="results-list-card">
                                <div class="results-header">
                                    <i class="bi bi-table me-2"></i>
                                    Partner-Resultate
                                </div>
                                <div class="table-wrapper">
                                    <!-- Desktop: Tabelle -->
                                    <div class="desktop-table-container">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0" id="partnerTabelle">
                                                <thead>
                                                    <tr>
                                                        <th scope="col"><i class="bi bi-heart me-1"></i>Partnerin</th>
                                                        <th scope="col"><i class="bi bi-person me-1"></i>Mitglied</th>
                                                        <th scope="col" class="text-center">Endstich</th>
                                                        <th scope="col" class="text-center">Sie und Er<div class="sie-er-header-legend"><span style="color:#dc2626">●</span> Partner &nbsp; <span style="color:#2563eb">●</span> Mitglied</div></th>
                                                        <th scope="col" class="text-center">Partner Schwini</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="5" class="text-center py-4">
                                                            <div class="spinner-border spinner-border-sm me-2"></div>
                                                            Lade Daten...
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Mobile: Cards -->
                                    <div class="mobile-cards-container" id="mobileCardsPartner">
                                        <div class="mobile-search">
                                            <div class="position-relative">
                                                <i class="bi bi-search search-icon"></i>
                                                <input type="text" class="form-control" placeholder="Suchen..."
                                                       oninput="filterMobilePartner(this)">
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
<div class="hybrid-edit-panel" id="editPanel" style="--panel-width: 540px;">
    <div class="panel-header">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="panelPrev" data-tooltip="Vorherige">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div>
                <h6 class="mb-0" id="panelTitle"><i class="bi bi-people me-2"></i>Partnerin erfassen</h6>
                <small class="text-muted" id="panelSubtitle"></small>
            </div>
            <button class="btn btn-sm btn-outline-secondary" id="panelNext" data-tooltip="Nächste">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
        <button class="btn btn-sm btn-outline-secondary" id="panelClose">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="panel-body" id="panelBody">
        <input type="hidden" id="partnerID" name="partnerID">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <!-- Grunddaten: Mitglied + Partnerin -->
        <div class="row g-2 mb-2">
            <div class="col-6">
                <div class="panel-stich-card">
                    <h6><i class="bi bi-person me-1"></i>Mitglied</h6>
                    <select class="form-select form-select-sm focusable-input" id="mitgliedSelect" name="mitgliedID" required>
                        <option value="">-- Wählen --</option>
                        <?php
                        $sql = "SELECT ID, Name, Vorname FROM mitglieder WHERE Verstorben = 0 ORDER BY Name, Vorname";
                        $result = $conn->query($sql);
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='" . $row['ID'] . "'>" . htmlspecialchars($row['Name'] . " " . $row['Vorname']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="col-6">
                <div class="panel-stich-card">
                    <h6><i class="bi bi-heart me-1"></i>Partnerin</h6>
                    <input type="text" class="form-control form-control-sm focusable-input" id="partnerName" name="partnerName" placeholder="Name" required>
                </div>
            </div>
        </div>

        <!-- Endstich (10 Schüsse) -->
        <div class="panel-stich-card">
            <h6><i class="bi bi-bullseye me-1"></i>Endstich <span id="endstichSumme" class="total-display float-end">0</span></h6>
            <div class="d-flex align-items-center gap-1 flex-wrap">
                <?php for ($i=1; $i<=10; $i++): ?>
                    <input type="number" class="small-input endstich-schuss focusable-input" id="EndstichSchuss<?= $i ?>" name="EndstichSchuss<?= $i ?>" min="0" max="10" step="0.1" inputmode="decimal">
                <?php endfor; ?>
            </div>
        </div>

        <!-- Sie und Er (Partner 1-5) -->
        <div class="panel-stich-card">
            <h6>
                <i class="bi bi-heart me-1"></i>"Sie und Er"
                <span class="badge bg-info ms-1" style="font-size: 0.6rem;">Partner 1-5</span>
                <span class="badge bg-success float-end" id="uniqueTotal" style="font-size: 0.65rem;"><i class="bi bi-calculator me-1"></i>Total: 0</span>
            </h6>
            <div class="d-flex align-items-center gap-1 flex-wrap mb-1">
                <?php for ($i=1; $i<=5; $i++): ?>
                    <input type="number"
                           class="small-input sie-er-schuss sie-er-partner focusable-input"
                           id="SieErSchuss<?= $i ?>"
                           name="SieErSchuss<?= $i ?>"
                           data-position="<?= $i ?>"
                           data-source="partner"
                           min="0" max="10" step="0.1"
                           style="border-bottom: 3px solid #dc3545;"
                           placeholder="<?= $i ?>"
                           inputmode="decimal">
                <?php endfor; ?>
            </div>
            <div id="previewBadges" class="d-flex gap-1 flex-wrap" style="font-size: 0.7rem;"></div>
        </div>

        <!-- Partner Schwini (2 Passen à 6 Schüsse) -->
        <div class="panel-stich-card">
            <h6><i class="bi bi-piggy-bank me-1"></i>Partner Schwini</h6>
            <div class="mb-1">
                <label class="small mb-0" style="font-size: 0.7rem;">Passe 1: <span id="schwiniSumme1" class="total-display">0</span></label>
                <div class="d-flex align-items-center gap-1">
                    <?php for ($i=1; $i<=6; $i++): ?>
                        <input type="number" class="small-input schwini-passe1 focusable-input" id="PartnerSchwiniSchuss<?= $i ?>" name="PartnerSchwiniSchuss<?= $i ?>" min="0" max="10" step="0.1" inputmode="decimal">
                    <?php endfor; ?>
                </div>
            </div>
            <div>
                <label class="small mb-0" style="font-size: 0.7rem;">Passe 2: <span id="schwiniSumme2" class="total-display">0</span></label>
                <div class="d-flex align-items-center gap-1">
                    <?php for ($i=7; $i<=12; $i++): ?>
                        <input type="number" class="small-input schwini-passe2 focusable-input" id="PartnerSchwiniSchuss<?= $i ?>" name="PartnerSchwiniSchuss<?= $i ?>" min="0" max="10" step="0.1" inputmode="decimal">
                    <?php endfor; ?>
                </div>
            </div>
            <div class="mt-1">
                <small class="fw-bold">Total: <span id="schwiniSummeTotal" class="text-primary">0</span></small>
            </div>
        </div>

        <!-- Info-Hinweis -->
        <div class="alert alert-info py-2 px-3 mb-0" style="font-size: 0.75rem;">
            <i class="bi bi-info-circle me-1"></i>
            <strong>"Sie und Er":</strong> 5 Schüsse der Partnerin (hier) + 5 Schüsse des Mitglieds (separat erfasst). Unique-Logik: Jeder Wert zählt nur 1x.
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
                Speichern & Nächste <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    // =========================================
    //  PartnerEditPanel – Slide-Panel Steuerung
    // =========================================
    const PartnerEditPanel = {
        currentPartnerId: null,
        allRows: [],
        currentIndex: -1,
        _loadingXhr: null,
        isNewEntry: false,

        open(partnerId) {
            this.currentPartnerId = partnerId;
            this.isNewEntry = false;
            this.currentIndex = this.allRows.findIndex(r => r.id == partnerId);

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
                : 'Erfassen';
            $('#panelTitle').html('<i class="bi bi-people me-2"></i>' + name);
            $('#panelSubtitle').text((this.currentIndex + 1) + ' / ' + this.allRows.length);

            // Navigation
            $('#panelPrev').prop('disabled', this.currentIndex <= 0);
            $('#panelNext').prop('disabled', this.currentIndex >= this.allRows.length - 1);

            // Delete-Button sichtbar (existierender Eintrag)
            $('#panelDeleteBtn').show();
            $('#panelSaveNextBtn').show();

            // Form zurücksetzen + Panel öffnen
            this.resetForm();
            $('#editPanel').addClass('open');
            $('#panelOverlay').addClass('show');
            $('#panelBody').scrollTop(0);

            // Daten laden
            this.loadPartnerData(partnerId);
        },

        openNew() {
            this.currentPartnerId = null;
            this.isNewEntry = true;
            this.currentIndex = -1;

            // Keine Zeile markiert
            $('.hybrid-row').removeClass('selected');

            // Titel
            $('#panelTitle').html('<i class="bi bi-plus me-2"></i>Neue Partnerin');
            $('#panelSubtitle').text('Neuer Eintrag');
            $('#panelPrev').prop('disabled', true);
            $('#panelNext').prop('disabled', true);

            // Delete-Button verstecken (neuer Eintrag)
            $('#panelDeleteBtn').hide();
            $('#panelSaveNextBtn').hide();

            // Form reset, Panel öffnen
            this.resetForm();
            $('#editPanel').addClass('open');
            $('#panelOverlay').addClass('show');
            $('#panelBody').scrollTop(0);

            // Fokus auf Mitglied-Dropdown
            setTimeout(function() { $('#mitgliedSelect').focus(); }, 300);
        },

        openGuest(guestName) {
            this.openNew();

            // Name vorausfüllen
            $('#partnerName').val(guestName);

            // Titel anpassen
            $('#panelTitle').html('<i class="bi bi-people me-2"></i>Gast: ' + guestName);

            // Fokus auf Mitglied-Dropdown
            setTimeout(function() { $('#mitgliedSelect').focus(); }, 300);
        },

        close() {
            $('#editPanel').removeClass('open');
            $('#panelOverlay').removeClass('show');
            $('.hybrid-row').removeClass('selected');
            this.currentPartnerId = null;
            this.currentIndex = -1;
            this.isNewEntry = false;
            if (this._loadingXhr) {
                this._loadingXhr.abort();
                this._loadingXhr = null;
            }
        },

        resetForm() {
            // Alle Inputs leeren
            $('#editPanel .focusable-input').val('');
            $('#editPanel .small-input').val('');
            $('#partnerID').val('');
            $('#mitgliedSelect').val('');
            $('#partnerName').val('');
            $('.total-display').text('0');
            $('#schwiniSummeTotal').text('0');
            $('#previewBadges').html('');
            $('#uniqueTotal').html('<i class="bi bi-calculator me-1"></i>Total: 0');
        },

        loadPartnerData(partnerId) {
            if (this._loadingXhr) this._loadingXhr.abort();

            this._loadingXhr = $.ajax({
                url: 'endresultate_partner/load_partner_data.php',
                type: 'GET',
                data: { id: partnerId },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        const p = data.partner;

                        // Grunddaten
                        $('#partnerID').val(p.ID);
                        $('#mitgliedSelect').val(p.MitgliedID);
                        $('#partnerName').val(p.PartnerName);

                        // Endstich
                        for (let i = 1; i <= 10; i++) {
                            $('#EndstichSchuss' + i).val(p['EndstichSchuss' + i] || '');
                        }

                        // Sie und Er (Partner 1-5)
                        for (let i = 1; i <= 5; i++) {
                            $('#SieErSchuss' + i).val(p['SieErSchuss' + i] || '');
                        }

                        // Partner Schwini (1-12)
                        for (let i = 1; i <= 12; i++) {
                            $('#PartnerSchwiniSchuss' + i).val(p['PartnerSchwiniSchuss' + i] || '');
                        }

                        calculateAllSums();
                        updateSieErUniqueVisualization();

                        setTimeout(function() {
                            $('#editPanel .focusable-input:first').focus().select();
                        }, 300);
                    } else {
                        msvToast('Fehler beim Laden', 'error');
                    }
                },
                error: function(xhr) {
                    if (xhr.statusText !== 'abort') {
                        msvToast('Fehler beim Laden der Partner-Daten', 'error');
                    }
                },
                complete: function() {
                    PartnerEditPanel._loadingXhr = null;
                }
            });
        },

        navigate(direction) {
            const newIndex = this.currentIndex + direction;
            if (newIndex < 0 || newIndex >= this.allRows.length) return;

            const nextRow = this.allRows[newIndex];
            if (nextRow.isGuest) {
                this.openGuest(nextRow.guestName);
                this.currentIndex = newIndex;
                // Fix: Subtitle nach openGuest setzen
                $('#panelSubtitle').text((newIndex + 1) + ' / ' + this.allRows.length);
                $('#panelPrev').prop('disabled', newIndex <= 0);
                $('#panelNext').prop('disabled', newIndex >= this.allRows.length - 1);
                $(nextRow.tr).addClass('selected');
            } else {
                this.open(nextRow.id);
            }
        },

        save(callback) {
            // Validierung
            if (!$('#partnerName').val().trim()) {
                msvToast('Bitte den Namen der Partnerin eingeben', 'error');
                $('#partnerName').focus();
                return;
            }
            if (!$('#mitgliedSelect').val()) {
                msvToast('Bitte ein Mitglied auswählen', 'error');
                $('#mitgliedSelect').focus();
                return;
            }

            const $saveBtn = $('#panelSaveBtn');
            const $saveNextBtn = $('#panelSaveNextBtn');
            const originalSave = $saveBtn.html();
            const originalNext = $saveNextBtn.html();

            $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
            $saveNextBtn.prop('disabled', true);

            const formData = this.collectFormData();

            $.ajax({
                url: 'endresultate_partner/save_partner_schuss.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        msvToast('Partnerin gespeichert!', 'success');

                        if (callback) {
                            callback();
                        } else {
                            PartnerEditPanel.close();
                            loadData($('#yearSelect').val());
                        }
                    } else {
                        msvToast('Fehler: ' + (data.message || 'Unbekannt'), 'error');
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
                // Nächste Partnerin in der Liste
                if (PartnerEditPanel.currentIndex >= 0) {
                    const nextIndex = PartnerEditPanel.currentIndex + 1;
                    if (nextIndex < PartnerEditPanel.allRows.length) {
                        const nextRow = PartnerEditPanel.allRows[nextIndex];
                        // Tabelle neu laden, dann nächsten öffnen
                        loadData($('#yearSelect').val(), function() {
                            if (nextIndex < PartnerEditPanel.allRows.length) {
                                const newNext = PartnerEditPanel.allRows[nextIndex];
                                if (newNext.isGuest) {
                                    PartnerEditPanel.openGuest(newNext.guestName);
                                } else {
                                    PartnerEditPanel.open(newNext.id);
                                }
                            }
                        });
                        return;
                    }
                }
                // Kein Nächster → schliessen + Tabelle neu laden
                msvToast('Alle Partnerinnen erfasst!', 'success');
                PartnerEditPanel.close();
                loadData($('#yearSelect').val());
            });
        },

        collectFormData() {
            const data = {
                mitgliedID: $('#mitgliedSelect').val(),
                partnerName: $('#partnerName').val(),
                jahr: $('#yearSelect').val(),
                csrf_token: $('#editPanel input[name="csrf_token"]').val()
            };

            // Endstich
            for (let i = 1; i <= 10; i++) {
                data['EndstichSchuss' + i] = $('#EndstichSchuss' + i).val() || '0';
            }

            // Sie und Er (Partner 1-5)
            for (let i = 1; i <= 5; i++) {
                data['SieErSchuss' + i] = $('#SieErSchuss' + i).val() || '0';
            }

            // Partner Schwini (1-12)
            for (let i = 1; i <= 12; i++) {
                data['PartnerSchwiniSchuss' + i] = $('#PartnerSchwiniSchuss' + i).val() || '0';
            }

            return data;
        },

        buildRowIndex() {
            this.allRows = [];
            $('#partnerTabelle tbody tr.hybrid-row').each((_, tr) => {
                const $tr = $(tr);
                const partnerId = $tr.data('partner-id');
                const guestName = $tr.data('guest-name');

                this.allRows.push({
                    id: partnerId || null,
                    isGuest: !partnerId && !!guestName,
                    guestName: guestName || null,
                    tr: tr
                });
            });
        },

        async deletePartner() {
            const partnerId = this.currentPartnerId;
            if (!partnerId) return;

            const name = $('#partnerName').val() || 'diese Partnerin';
            const r = await msvConfirm(
                'Möchtest du "' + name + '" wirklich löschen?',
                'Partnerin löschen',
                'Ja, löschen'
            );
            if (!r.isConfirmed) return;

            $.post('endresultate_partner/delete_partner.php', {
                id: partnerId,
                csrf_token: $('#editPanel input[name="csrf_token"]').val()
            }, function(data) {
                if (data.success) {
                    msvToast('Partnerin gelöscht', 'success');
                    PartnerEditPanel.close();
                    loadData($('#yearSelect').val());
                } else {
                    msvToast('Fehler: ' + (data.message || 'Unbekannt'), 'error');
                }
            }, 'json').fail(function() {
                msvToast('Fehler beim Löschen', 'error');
            });
        }
    };

    // =========================================
    //  Jahr-Dropdown
    // =========================================
    function initializeYearDropdown() {
        var $yearSelect = $('#yearSelect').empty();
        var currentYear = new Date().getFullYear();
        for (var year = currentYear; year >= currentYear - 3; year--) {
            var $option = $('<option></option>').val(year).text(year);
            if (year === currentYear) $option.prop('selected', true);
            $yearSelect.append($option);
        }
    }

    // =========================================
    //  Daten laden
    // =========================================
    function loadData(year, callback) {
        PartnerEditPanel.close();

        var $tbody = $('#partnerTabelle tbody');
        $tbody.html(
            '<tr><td colspan="5" class="text-center py-4">' +
            '<div class="spinner-border spinner-border-sm me-2"></div>' +
            'Lade Daten...</td></tr>'
        );

        $.ajax({
            url: 'endresultate_partner/load_partner_resultate.php',
            type: 'GET',
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                PartnerEditPanel.buildRowIndex();
                buildMobilePartnerCards();
                if (callback) callback();
            },
            error: function() {
                $tbody.html(
                    '<tr><td colspan="5" class="text-center text-danger py-4">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Fehler beim Laden der Daten</td></tr>'
                );
                msvToast('Fehler beim Laden der Partner-Daten', 'error');
            }
        });
    }

    // =========================================
    //  Summen berechnen
    // =========================================
    function calculateAllSums() {
        // Endstich
        var endstichSum = 0;
        $('.endstich-schuss').each(function() { endstichSum += parseFloat($(this).val()) || 0; });
        $('#endstichSumme').text(endstichSum.toFixed(1));

        // Schwini Passe 1
        var schwiniSum1 = 0;
        $('.schwini-passe1').each(function() { schwiniSum1 += parseFloat($(this).val()) || 0; });
        $('#schwiniSumme1').text(schwiniSum1.toFixed(1));

        // Schwini Passe 2
        var schwiniSum2 = 0;
        $('.schwini-passe2').each(function() { schwiniSum2 += parseFloat($(this).val()) || 0; });
        $('#schwiniSumme2').text(schwiniSum2.toFixed(1));

        // Schwini Total
        $('#schwiniSummeTotal').text((schwiniSum1 + schwiniSum2).toFixed(1));
    }

    // =========================================
    //  Enter-Navigation im Panel
    // =========================================
    function setupEnterNavigation() {
        var $inputs = $('#editPanel .focusable-input');
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
    //  Sie und Er Unique Visualisierung
    // =========================================
    function updateSieErUniqueVisualization() {
        var valuePositions = {};

        $('.sie-er-partner').each(function() {
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
        $('.sie-er-partner').each(function() {
            var value = parseFloat($(this).val() || 0);
            if (value > 0) {
                var intValue = Math.floor(value);
                if (processedForPreview[intValue]) {
                    previewHTML += '<span class="badge bg-danger bg-opacity-25 text-danger" style="text-decoration: line-through; font-size: 0.7rem;">' + value + '</span> ';
                } else {
                    previewHTML += '<span class="badge bg-danger" style="font-size: 0.7rem;">' + value + '</span> ';
                    processedForPreview[intValue] = true;
                }
            }
        });
        $('#previewBadges').html(previewHTML || '<span class="text-muted small">Noch keine Werte</span>');

        var uniqueSum = uniqueValues.reduce(function(sum, val) { return sum + val; }, 0);
        $('#uniqueTotal').html('<i class="bi bi-calculator me-1"></i>Total: ' + uniqueSum);
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

    // Partnerin hinzufügen
    $('#add-partner-btn').on('click', function() {
        PartnerEditPanel.openNew();
    });

    // Jahr löschen
    $('#delete-year-btn').on('click', async function() {
        const year = $('#yearSelect').val();

        // Zuerst Count abfragen
        let countData;
        try {
            countData = await $.get('endresultate_partner/count_year_entries.php', { year: year });
        } catch(e) {
            msvToast('Fehler beim Abrufen der Daten', 'error');
            return;
        }

        if (!countData.success || countData.count === 0) {
            msvToast('Keine Einträge für dieses Jahr vorhanden', 'info');
            return;
        }

        const r = await msvConfirm(
            'Möchtest du wirklich ALLE ' + countData.count + ' Partner-Resultate des Jahres ' + year + ' löschen?',
            'Alle Partner-Daten löschen',
            'Ja, alles löschen'
        );
        if (!r.isConfirmed) return;

        $.post('endresultate_partner/delete_year_data.php', {
            year: year,
            csrf_token: $('input[name="csrf_token"]').val()
        }, function(data) {
            if (data.success) {
                msvToast(data.message, 'success');
                PartnerEditPanel.close();
                loadData(year);
            } else {
                msvToast('Fehler: ' + data.message, 'error');
            }
        }, 'json');
    });

    // Klick auf Tabellenzeile → Panel öffnen
    $(document).on('click', '.hybrid-row', function() {
        const partnerId = $(this).data('partner-id');
        const guestName = $(this).data('guest-name');

        if (partnerId) {
            PartnerEditPanel.open(partnerId);
        } else if (guestName) {
            PartnerEditPanel.openGuest(guestName);
        }
    });

    // Panel schliessen
    $('#panelClose, #panelOverlay').on('click', function() { PartnerEditPanel.close(); });

    // Escape schliesst Panel
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#editPanel').hasClass('open')) {
            PartnerEditPanel.close();
            e.stopImmediatePropagation();
        }
    });

    // Panel Navigation
    $('#panelPrev').on('click', function() { PartnerEditPanel.navigate(-1); });
    $('#panelNext').on('click', function() { PartnerEditPanel.navigate(1); });

    // Speichern
    $('#panelSaveBtn').on('click', function() { PartnerEditPanel.save(); });

    // Speichern & Nächste
    $('#panelSaveNextBtn').on('click', function() { PartnerEditPanel.saveAndNext(); });

    // Löschen aus Panel
    $('#panelDeleteBtn').on('click', function() { PartnerEditPanel.deletePartner(); });

    // Summen-Berechnung bei Input
    $(document).on('input change', '.endstich-schuss, .schwini-passe1, .schwini-passe2', function() {
        calculateAllSums();
    });

    // Sie und Er Berechnung
    $(document).on('input change', '.sie-er-schuss', function() {
        updateSieErUniqueVisualization();
    });

    // =========================================
    //  Mobile Cards
    // =========================================
    function buildMobilePartnerCards() {
        const isMobile = window.matchMedia('(max-width: 767.98px)');
        if (!isMobile.matches) return;

        const table = document.getElementById('partnerTabelle');
        const container = document.querySelector('#mobileCardsPartner .mobile-cards-scroll');
        if (!table || !container) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
            return;
        }

        const rows = tbody.querySelectorAll('tr.hybrid-row');
        if (rows.length === 0) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
            return;
        }

        let html = '';
        rows.forEach((row, idx) => {
            const cells = Array.from(row.querySelectorAll('td'));
            if (cells.length < 5) return;

            const partnerId = row.dataset.partnerId;
            const guestName = row.dataset.guestName;
            const isGuest = !!guestName && !partnerId;
            const partnerin = cells[0]?.textContent?.trim() || 'Unbekannt';
            const mitglied = cells[1]?.textContent?.trim() || '-';
            const endstich = cells[2]?.textContent?.trim() || '-';
            const sieUndEr = cells[3]?.textContent?.trim() || '-';
            const schwini = cells[4]?.textContent?.trim() || '-';

            const borderStyle = isGuest ? 'border-left: 3px solid #ffc107;' : '';
            const btnAction = partnerId
                ? `PartnerEditPanel.open(${partnerId})`
                : `PartnerEditPanel.openGuest('${guestName.replace(/'/g, "\\'")}')`;
            const btnLabel = isGuest ? 'Erfassen' : 'Bearbeiten';

            html += `
            <div class="mobile-card" data-index="${idx}" style="${borderStyle}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                        <div class="fw-bold"><i class="bi bi-heart me-2"></i>${partnerin}</div>
                        <small class="text-muted">mit ${mitglied}</small>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="mobile-card-body">
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Mitglied</span>
                        <span class="mobile-card-detail-value"><strong>${mitglied}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Endstich</span>
                        <span class="mobile-card-detail-value"><strong>${endstich}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Sie und Er</span>
                        <span class="mobile-card-detail-value"><strong>${sieUndEr}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Partner Schwini</span>
                        <span class="mobile-card-detail-value"><strong>${schwini}</strong></span>
                    </div>
                    <button type="button" class="btn btn-outline-primary w-100 mt-3"
                            onclick="${btnAction}"
                            style="min-height: 48px;">
                        <i class="bi bi-pencil me-2"></i>${btnLabel}
                    </button>
                </div>
            </div>`;
        });

        container.innerHTML = html;
    }

    window.filterMobilePartner = function(searchInput) {
        const query = searchInput.value.toLowerCase();
        const cards = document.querySelectorAll('#mobileCardsPartner .mobile-card');

        let visibleCount = 0;
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const isVisible = text.includes(query);
            card.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        const container = document.querySelector('#mobileCardsPartner .mobile-cards-scroll');
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
            buildMobilePartnerCards();
        }
        wasDesktop = isNowDesktop;
    });

    // PartnerEditPanel global verfügbar machen für Mobile-Cards onclick
    window.PartnerEditPanel = PartnerEditPanel;

    // Enter-Navigation initial setup
    setupEnterNavigation();

    // =========================================
    //  Init
    // =========================================
    initializeYearDropdown();
    loadData(new Date().getFullYear());
});
</script>

<?php include 'footer.inc.php'; ?>
