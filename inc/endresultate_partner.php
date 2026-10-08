<?php
// endresultate_partner.php – Slide-Panel Pattern (wie endresultate.php)
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in endresultate_partner.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Seitenspezifische Styles: nur Aufbau und die Sie-und-Er-Punkte; Optik aus css/msv-ui.css
$page_specific_css = "
/* Fensterhöhe (.ui-vollhoehe, .ui-scroll) und Erfassungs-Panel (.ui-erfassen) kommen aus css/msv-ui.css */
.ep-scroll { outline: none; }
#partnerTabelle { margin: 0; border-collapse: separate; border-spacing: 0; }
#partnerTabelle thead th { position: sticky; top: 0; z-index: 2; padding: 8px 10px; white-space: nowrap; text-align: right; }
#partnerTabelle tbody td { height: 40px; padding: 0 10px; white-space: nowrap; text-align: right; vertical-align: middle; border-bottom: 1px solid var(--ui-linie-zart); }
#partnerTabelle thead th:nth-child(-n+2), #partnerTabelle tbody td:nth-child(-n+2) { text-align: left; }
#partnerTabelle thead th:first-child, #partnerTabelle tbody td:first-child { padding-left: 20px; }
#partnerTabelle tbody td:first-child { font-weight: 600; }
#partnerTabelle thead th:last-child, #partnerTabelle tbody td:last-child { text-align: left; padding-right: 20px; }
#partnerTabelle thead th:nth-child(4), #partnerTabelle tbody td:nth-child(4) { text-align: center; }
#partnerTabelle tbody tr.hybrid-row { cursor: pointer; }
#partnerTabelle tbody tr.hybrid-row:hover > td { background: var(--ui-flaeche-2); }
#partnerTabelle tbody tr.ui-leer td { height: auto; padding: 32px 16px; text-align: center; color: var(--ui-text-2); white-space: normal; cursor: default; }

/* Sie und Er: kompakte Punkte (Partnerin rot, Mitglied blau) */
.dot-row { display: flex; align-items: center; gap: 2px; justify-content: center; }
.shot-dot { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 4px; font-size: 0.65rem; font-weight: 700; line-height: 1; }
.dot-partner { background: #fee2e2; color: #b42318; }
.dot-partner.unique { background: #b42318; color: #fff; }
.dot-mitglied { background: var(--ui-akzent-hell); color: var(--ui-akzent-dunkel); }
.dot-mitglied.unique { background: var(--ui-akzent-dunkel); color: #fff; }
.dot-struck { text-decoration: line-through; opacity: 0.45; }
.dot-empty { background: var(--ui-flaeche-2); color: var(--ui-leer); border: 1px dashed var(--ui-rand); }
.dot-sep { color: var(--ui-leer); font-size: 0.7rem; margin: 0 1px; }
.sie-er-total { font-weight: 700; font-size: 0.8rem; color: var(--ui-text); min-width: 28px; text-align: right; margin-left: 6px; }
.sie-er-header-legend { font-size: 0.65rem; font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--ui-text-2); margin-top: 2px; }

@media (max-width: 767.98px) {
    .desktop-table-container { display: none !important; }
    .mobile-cards-container { display: flex !important; }
    .mobile-card-detail-row { padding: 0.75rem 0 !important; border-bottom: 1px solid var(--ui-linie-zart) !important; }
    .mobile-card-detail-label { font-size: 0.875rem !important; color: var(--ui-text-2) !important; font-weight: 500 !important; }
    .mobile-card-detail-value { font-size: 1rem !important; color: var(--ui-text) !important; }
    .mobile-card-body .btn { min-height: 48px !important; font-size: 1rem !important; }
}
@media (min-width: 768px) {
    .mobile-cards-container { display: none !important; }
}
";

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
$csrf = csrf_token();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-wide ui-vollhoehe">
                <?php
                $page_title = 'Endschiessen Partnerinnen erfassen';
                $page_title_after = '<button type="button" class="btn-help" data-help="endresultate_partner.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_actions = '<button id="add-partner-btn" type="button" class="btn btn-outline-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Partnerin hinzufügen</button>'
                    . '<button id="redirect-btn" type="button" class="btn btn-outline-info btn-sm"><i class="bi bi-list-ol me-1"></i>Rangliste</button>';
                $page_extra = '<div class="ui-fortschritt" aria-live="polite"><span class="ui-zahl" id="progressText">–</span>'
                    . '<span class="ui-balken" aria-hidden="true"><span id="progressBar"></span></span></div>'
                    . '<div class="ui-chips" id="progressChips"></div>';
                $page_show_mobile = true;
                include 'partials/page_header.inc.php';
                ?>

                <form id="partnerResultateForm" class="ui-karte ui-vollhoehe-karte" onsubmit="return false">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel">Partnerinnen</span>
                        <div class="ui-filter" role="group" aria-label="Nach Stand filtern">
                            <button type="button" data-filter="alle" aria-pressed="true">Alle <span id="nAlle">0</span></button>
                            <button type="button" data-filter="offen" aria-pressed="false">Offen <span id="nOffen">0</span></button>
                            <button type="button" data-filter="ok" aria-pressed="false">Erfasst <span id="nOk">0</span></button>
                        </div>
                        <label class="ui-suche d-none d-md-flex">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <span class="visually-hidden">Partnerin oder Mitglied suchen</span>
                            <input type="search" id="epSuche" placeholder="Partnerin oder Mitglied suchen" autocomplete="off">
                        </label>
                    </div>

                    <!-- Desktop: Tabelle -->
                    <div class="desktop-table-container">
                        <div class="ui-scroll ep-scroll" id="epScroll" tabindex="0" aria-label="Partnerinnen. Mit den Pfeiltasten wählen, Enter öffnet die Erfassung.">
                            <table class="table mb-0" id="partnerTabelle">
                                <thead>
                                    <tr>
                                        <th scope="col">Partnerin</th>
                                        <th scope="col">Mitglied</th>
                                        <th scope="col">Endstich</th>
                                        <th scope="col">Sie und Er<div class="sie-er-header-legend"><span style="color:#b42318">●</span> Partnerin &nbsp; <span style="color:#2b52a0">●</span> Mitglied</div></th>
                                        <th scope="col">Partner Schwini</th>
                                        <th scope="col">Stand</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="ui-leer">
                                        <td colspan="6"><div class="spinner-border spinner-border-sm me-2"></div>Lade Daten …</td>
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

                    <div class="ui-tasten d-none d-md-flex">
                        <span><kbd>↑</kbd> <kbd>↓</kbd> wählen</span>
                        <span><kbd>Enter</kbd> erfassen</span>
                        <span>im Panel: <kbd>Enter</kbd> nächstes Feld</span>
                        <span><kbd>Ctrl</kbd>+<kbd>S</kbd> speichern</span>
                        <span><kbd>Ctrl</kbd>+<kbd>Enter</kbd> speichern &amp; nächste offene</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Panel Overlay -->
<div class="panel-overlay" id="panelOverlay"></div>

<!-- Slide-Panel -->
<div class="hybrid-edit-panel ui-erfassen" id="editPanel" style="--panel-width: 600px;">
    <div class="panel-header">
        <div class="min-w-0">
            <h6 class="mb-0" id="panelTitle">Partnerin erfassen</h6>
            <small class="panel-pos" id="panelSubtitle"></small>
        </div>
        <div class="d-flex align-items-center gap-1 ms-auto">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelPrev" data-tooltip="Vorherige" aria-label="Vorherige">
                <i class="bi bi-chevron-up" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelNext" data-tooltip="Nächste" aria-label="Nächste">
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelClose" data-tooltip="Schliessen (Esc)" aria-label="Schliessen (Esc)">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="panel-body" id="panelBody">
        <input type="hidden" id="partnerID" name="partnerID">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <!-- Grunddaten: Mitglied + Partnerin -->
        <div class="shot-cols shot-first">
            <div class="shot-field">
                <label class="panel-label" for="mitgliedSelect"><i class="bi bi-person me-1"></i>Mitglied</label>
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
            <div class="shot-field">
                <label class="panel-label" for="partnerName"><i class="bi bi-heart me-1"></i>Partnerin</label>
                <input type="text" class="form-control form-control-sm focusable-input" id="partnerName" name="partnerName" placeholder="Name" required>
            </div>
        </div>

        <!-- Endstich (10 Schüsse) -->
        <div class="shot-section">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-bullseye"></i>Endstich <button type="button" class="btn-help" data-help="endresultate_partner.endstich" aria-label="Hilfe"></button></span>
                <span class="shot-total" id="endstichSumme">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-grid">
                    <?php for ($i=1; $i<=10; $i++): ?>
                    <input type="number" class="shot-input shot-input-dec endstich-schuss focusable-input" id="EndstichSchuss<?= $i ?>" name="EndstichSchuss<?= $i ?>" aria-label="Endstich Schuss <?= $i ?>" min="0" max="10" step="0.1" inputmode="decimal">
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Sie und Er (Partnerin 1-5) -->
        <div class="shot-section">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-people"></i>Sie und Er <span class="shot-hint">Schüsse 1–5, Partnerin</span> <button type="button" class="btn-help" data-help="endresultate_partner.sieunder" aria-label="Hilfe"></button></span>
                <span class="shot-total" id="uniqueTotal" data-tooltip="Total der eindeutigen Werte">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-grid">
                    <?php for ($i=1; $i<=5; $i++): ?>
                    <input type="number"
                           class="shot-input shot-input-dec sie-er-schuss sie-er-partner focusable-input"
                           id="SieErSchuss<?= $i ?>"
                           name="SieErSchuss<?= $i ?>" aria-label="Sie und Er Schuss <?= $i ?>"
                           data-position="<?= $i ?>"
                           data-source="partner"
                           min="0" max="10"
                           placeholder="<?= $i ?>"
                           inputmode="numeric">
                    <?php endfor; ?>
                </div>
                <div class="shot-hint mt-2">Zusammen mit den Schüssen 6–10 des Mitglieds (unter Endschiessen Resultate erfassen). Jeder Wert zählt nur einmal, Doppelte sind rot durchgestrichen.</div>
            </div>
        </div>

        <!-- Partner Schwini (2 Passen à 6 Schüsse) -->
        <div class="shot-section">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-piggy-bank"></i>Partner Schwini <button type="button" class="btn-help" data-help="endresultate_partner.schwini" aria-label="Hilfe"></button></span>
                <span class="shot-total" id="schwiniSummeTotal">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-row">
                    <span class="shot-row-label">Passe 1</span>
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=6; $i++): ?>
                        <input type="number" class="shot-input shot-input-dec schwini-passe1 focusable-input" id="PartnerSchwiniSchuss<?= $i ?>" name="PartnerSchwiniSchuss<?= $i ?>" aria-label="Schwini Schuss <?= $i ?>" min="0" max="10" step="0.1" inputmode="decimal">
                        <?php endfor; ?>
                    </div>
                    <span class="shot-total" id="schwiniSumme1">0</span>
                </div>
                <div class="shot-row">
                    <span class="shot-row-label">Passe 2</span>
                    <div class="shot-grid">
                        <?php for ($i=7; $i<=12; $i++): ?>
                        <input type="number" class="shot-input shot-input-dec schwini-passe2 focusable-input" id="PartnerSchwiniSchuss<?= $i ?>" name="PartnerSchwiniSchuss<?= $i ?>" aria-label="Schwini Schuss <?= $i ?>" min="0" max="10" step="0.1" inputmode="decimal">
                        <?php endfor; ?>
                    </div>
                    <span class="shot-total" id="schwiniSumme2">0</span>
                </div>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <div class="alert alert-danger small py-2 px-3 msv-eingabe-fehler" id="panelFehler" role="alert" hidden></div>
        <div class="d-flex gap-2 w-100 align-items-center">
            <button type="button" class="btn btn-outline-danger btn-sm" id="panelDeleteBtn" data-tooltip="Partnerin löschen" aria-label="Partnerin löschen">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
            <span class="small text-muted me-auto d-none d-md-inline"><kbd class="ui-kbd">Ctrl</kbd>+<kbd class="ui-kbd">Enter</kbd> nächste offene</span>
            <button type="button" class="btn btn-outline-primary btn-sm" id="panelSaveBtn">
                <i class="bi bi-save me-1"></i>Speichern
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="panelSaveNextBtn">
                Speichern &amp; nächste offene <i class="bi bi-arrow-right ms-1"></i>
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
        geaendert: false,

        // «Nicht gespeichert» merken und im Panelkopf zeigen
        setGeaendert(an) {
            this.geaendert = !!an;
            msvPanelUngespeichert($('#panelSubtitle').parent(), this.geaendert);
        },

        // Vor Schliessen oder Wechseln: Ungespeichertes nie still verwerfen. weiter() läuft nach
        // «Verwerfen» oder nach erfolgreichem Speichern; bei «Zurück» bleibt das Panel, wie es ist.
        async schuetze(weiter) {
            if (!this.geaendert) { weiter(); return; }
            const wahl = await msvUngespeichert({ wer: $('#panelTitle').text().trim() });
            if (wahl === 'verwerfen') { this.setGeaendert(false); weiter(); }
            else if (wahl === 'speichern') this.save(() => loadData($('#yearSelect').val(), weiter));
        },

        versucheSchliessen() { this.schuetze(() => this.close()); },


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
            $('#panelTitle').text(name || 'Erfassen');
            $('#panelSubtitle').text((this.currentIndex + 1) + ' von ' + this.allRows.length);

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
            $('#panelTitle').text('Neue Partnerin');
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
            $('#panelTitle').text('Gast: ' + guestName);

            // Fokus auf Mitglied-Dropdown
            setTimeout(function() { $('#mitgliedSelect').focus(); }, 300);
        },

        close() {
            this.setGeaendert(false);
            msvEingabeFehler('#panelFehler', []);
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
            this.setGeaendert(false);
            $('#editPanel [aria-invalid]').removeAttr('aria-invalid');
            msvEingabeFehler('#panelFehler', []);
            // Alle Inputs leeren
            $('#editPanel .focusable-input').val('');
            $('#editPanel .shot-input').val('').removeClass('filled is-unique is-dup');
            $('#partnerID').val('');
            $('#mitgliedSelect').val('');
            $('#partnerName').val('');
            $('#editPanel .shot-total').text('0');
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
                        refreshFilled();

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
            if (this.geaendert) { this.schuetze(() => this.navigate(direction)); return; }

            const nextRow = this.allRows[newIndex];
            if (nextRow.isGuest) {
                this.openGuest(nextRow.guestName);
                this.currentIndex = newIndex;
                // Fix: Subtitle nach openGuest setzen
                $('#panelSubtitle').text((newIndex + 1) + ' von ' + this.allRows.length);
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

            // Unplausible Werte zuerst korrigieren lassen (der Server prüft ebenfalls)
            if (msvEingabeFehler('#panelFehler', msvPruefeFelder('#editPanel'))) return;

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
                        PartnerEditPanel.setGeaendert(false);
                        msvToast('Partnerin gespeichert', 'success');

                        if (callback) {
                            callback();
                        } else {
                            PartnerEditPanel.close();
                            loadData($('#yearSelect').val());
                        }
                    } else {
                        msvToast('Nicht gespeichert: ' + (data.message || 'unbekannter Fehler') + '. Die Eingaben sind noch da.', 'error');
                    }
                },
                error: function(xhr) {
                    msvToast('Nicht gespeichert: ' + msvXhrMessage(xhr, 'Serverfehler') + '. Die Eingaben sind noch da.', 'error');
                },
                complete: function() {
                    $saveBtn.prop('disabled', false).html(originalSave);
                    $saveNextBtn.prop('disabled', false).html(originalNext);
                }
            });
        },

        saveAndNext() {
            const vorher = Math.max(this.currentIndex, 0);
            this.save(function() {
                // Tabelle neu laden, dann die nächste offene Zeile ab der bisherigen Position öffnen
                loadData($('#yearSelect').val(), function() {
                    const rows = PartnerEditPanel.allRows;
                    const reihe = rows.slice(vorher).concat(rows.slice(0, vorher));
                    const naechste = reihe.find(r => r.stand === 'offen');
                    if (!naechste) {
                        msvToast('Alle Partnerinnen erfasst', 'success');
                        PartnerEditPanel.close();
                        return;
                    }
                    const idx = rows.indexOf(naechste);
                    if (naechste.isGuest) {
                        PartnerEditPanel.openGuest(naechste.guestName);
                        PartnerEditPanel.currentIndex = idx;
                        $('#panelSubtitle').text((idx + 1) + ' von ' + rows.length);
                        $(naechste.tr).addClass('selected');
                    } else {
                        PartnerEditPanel.open(naechste.id);
                    }
                });
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

                // "Erfasst" = irgendein Resultat vorhanden (Endstich / Sie&Er-Total / Schwini)
                const $tds = $tr.children('td');
                const num = s => parseFloat(String(s || '').replace(',', '.')) || 0;
                const hasData = num($tds.eq(2).text()) > 0
                    || num($tr.find('.sie-er-total').text()) > 0
                    || num($tds.eq(4).text()) > 0;
                $tr.attr('data-has-data', hasData ? '1' : '0');

                this.allRows.push({
                    id: partnerId || null,
                    isGuest: !partnerId && !!guestName,
                    guestName: guestName || null,
                    tr: tr,
                    hasData: hasData,
                    stand: $tr.attr('data-stand') || (hasData ? 'ok' : 'offen')
                });
            });
            this.updateProgress();
        },

        updateProgress() {
            const total = this.allRows.length;
            const ok = this.allRows.filter(r => r.stand === 'ok').length;
            const offen = total - ok;
            const gaeste = this.allRows.filter(r => r.isGuest).length;
            const pct = total > 0 ? Math.round((ok / total) * 100) : 0;
            $('#progressText').html(ok + ' von ' + total + ' <span>erfasst</span>');
            $('#progressBar').css('width', pct + '%');
            $('#progressChips').html(
                (offen ? '<span class="ui-chip"><span class="ui-punkt"></span><b>' + offen + '</b> offen</span>' : '') +
                (gaeste ? '<span class="ui-chip"><b>' + gaeste + '</b> Gäste ohne Erfassung</span>' : '')
            );
            $('#nAlle').text(total);
            $('#nOffen').text(offen);
            $('#nOk').text(ok);
        },

        async deletePartner() {
            const partnerId = this.currentPartnerId;
            if (!partnerId) return;

            const name = $('#partnerName').val() || 'diese Partnerin';
            const r = await msvConfirmDelete(msvEsc(name), { title: 'Partnerin löschen?' });
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
            }, 'json').fail(function(xhr) {
                msvToast('Nicht gelöscht: ' + msvXhrMessage(xhr, 'Serverfehler'), 'error');
            });
        }
    };

    // =========================================
    //  Jahr-Dropdown
    // =========================================
    function initializeYearDropdown() {
        msvJahrAuswahl('#yearSelect');
    }

    // =========================================
    //  Daten laden
    // =========================================
    function loadData(year, callback) {
        var $tbody = $('#partnerTabelle tbody');
        if (!callback) {
            PartnerEditPanel.close();
            $tbody.html('<tr class="ui-leer"><td colspan="6"><div class="spinner-border spinner-border-sm me-2"></div>Lade Daten …</td></tr>');
        }

        $.ajax({
            url: 'endresultate_partner/load_partner_resultate.php',
            type: 'GET',
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                PartnerEditPanel.buildRowIndex();
                filterAnwenden();
                buildMobilePartnerCards();
                if (callback) callback();
            },
            error: function(xhr) {
                $tbody.html('<tr class="ui-leer"><td colspan="6" class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Die Daten konnten nicht geladen werden: ' + msvEsc(msvXhrMessage(xhr, 'Serverfehler')) + '</td></tr>');
            }
        });
    }

    // =========================================
    //  Filter (Alle / Offen / Erfasst), Suche, Tastatur
    // =========================================
    var epFilter = 'alle', epSuchtext = '';
    function filterAnwenden() {
        var sichtbar = 0;
        PartnerEditPanel.allRows.forEach(function(r) {
            var text = $(r.tr).children('td').slice(0, 2).text().toLowerCase();
            var zeigen = (epFilter === 'alle' || r.stand === epFilter) && text.indexOf(epSuchtext) !== -1;
            r.tr.style.display = zeigen ? '' : 'none';
            if (zeigen) sichtbar++;
        });
        $('#epKeineTreffer').remove();
        if (!sichtbar && PartnerEditPanel.allRows.length) {
            $('#partnerTabelle tbody').append(
                '<tr class="ui-leer" id="epKeineTreffer"><td colspan="6">Keine Einträge für diese Auswahl. ' +
                '<button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="epFilterZurueck">Filter zurücksetzen</button></td></tr>');
        }
    }
    $(document).on('click', '.ui-filter button', function() {
        epFilter = $(this).data('filter');
        $('.ui-filter button').attr('aria-pressed', 'false');
        $(this).attr('aria-pressed', 'true');
        filterAnwenden();
    });
    $('#epSuche').on('input', function() { epSuchtext = this.value.trim().toLowerCase(); filterAnwenden(); });
    $(document).on('click', '#epFilterZurueck', function() {
        epFilter = 'alle'; epSuchtext = ''; $('#epSuche').val('');
        $('.ui-filter button').attr('aria-pressed', 'false');
        $('.ui-filter button[data-filter="alle"]').attr('aria-pressed', 'true');
        filterAnwenden();
    });
    $('#epScroll').on('keydown', function(e) {
        var zeilen = PartnerEditPanel.allRows.filter(function(r) { return r.tr.style.display !== 'none'; });
        if (!zeilen.length) return;
        var akt = zeilen.findIndex(function(r) { return r.tr.classList.contains('ui-markiert'); });
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            var neu = akt < 0 ? 0 : Math.max(0, Math.min(zeilen.length - 1, akt + (e.key === 'ArrowDown' ? 1 : -1)));
            zeilen.forEach(function(r) { r.tr.classList.remove('ui-markiert'); });
            zeilen[neu].tr.classList.add('ui-markiert');
            zeilen[neu].tr.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && akt >= 0) {
            e.preventDefault();
            $(zeilen[akt].tr).trigger('click');
        }
    });
    $('#epScroll').on('blur', function() { $('#partnerTabelle tr.ui-markiert').removeClass('ui-markiert'); });
    $(document).on('keydown', function(e) {
        if (!$('#editPanel').hasClass('open') || !(e.ctrlKey || e.metaKey)) return;
        if (e.key === 's' || e.key === 'S') { e.preventDefault(); PartnerEditPanel.save(); }
        else if (e.key === 'Enter') { e.preventDefault(); if ($('#panelSaveNextBtn').is(':visible')) PartnerEditPanel.saveAndNext(); else PartnerEditPanel.save(); }
    });

    // =========================================
    //  Summen berechnen
    // =========================================
    // Ausgefuellte Felder gruen markieren (wie kanti/heim .filled)
    function refreshFilled() {
        $('#editPanel .shot-input').each(function() {
            $(this).toggleClass('filled', this.value !== '');
        });
    }

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
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                var currentIndex = $inputs.index(this);
                var nextIndex = currentIndex + 1;
                if (nextIndex < $inputs.length) {
                    $inputs.eq(nextIndex).focus().select();
                } else if ($('#panelSaveNextBtn').is(':visible')) {
                    PartnerEditPanel.saveAndNext();
                } else {
                    PartnerEditPanel.save();
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

    // Partnerin hinzufügen
    $('#add-partner-btn').on('click', function() {
        PartnerEditPanel.openNew();
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
    $('#panelClose, #panelOverlay').on('click', function() { PartnerEditPanel.versucheSchliessen(); });

    // Escape schliesst Panel
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#editPanel').hasClass('open') && !(window.Swal && Swal.isVisible())) {
            e.stopImmediatePropagation();
            PartnerEditPanel.versucheSchliessen();
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

    // Eingaben: «Nicht gespeichert» setzen, Zahl sofort prüfen; eine offene Fehlerliste frischt sich mit auf
    $(document).on('input', '#editPanel input:not([type="hidden"])', function() {
        PartnerEditPanel.setGeaendert(true);
        if (this.type === 'number') msvPruefeZahl(this);
        if (!$('#panelFehler').prop('hidden')) msvEingabeFehler('#panelFehler', msvPruefeFelder('#editPanel'), false);
    });
    $(document).on('change', '#editPanel select', function(e) { if (e.originalEvent) PartnerEditPanel.setGeaendert(true); });

    // Seite verlassen mit offenen Eingaben: der Browser fragt nach
    $(window).on('beforeunload', function() { if (PartnerEditPanel.geaendert) return 'Nicht gespeicherte Eingaben'; });

    // Summen-Berechnung bei Input
    $(document).on('input change', '.endstich-schuss, .schwini-passe1, .schwini-passe2', function() {
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
    function buildMobilePartnerCards() {
        const isMobile = window.matchMedia('(max-width: 767.98px)');
        if (!isMobile.matches) return;

        const table = document.getElementById('partnerTabelle');
        const container = document.querySelector('#mobileCardsPartner .mobile-cards-scroll');
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
            if (cells.length < 5) return;

            const partnerId = row.dataset.partnerId;
            const guestName = row.dataset.guestName;
            const isGuest = !!guestName && !partnerId;
            const partnerin = cells[0]?.textContent?.trim() || 'Unbekannt';
            const mitglied = cells[1]?.textContent?.trim() || '-';
            const endstich = cells[2]?.textContent?.trim() || '-';
            const sieUndEr = cells[3]?.textContent?.trim() || '-';
            const schwini = cells[4]?.textContent?.trim() || '-';

            const borderStyle = '';
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
    loadData($('#yearSelect').val());
});
</script>

<?php include 'footer.inc.php'; ?>
