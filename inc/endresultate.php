<?php
// endresultate.php – Slide-Panel Pattern (wie jmdefinition.php)
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in endresultate.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Seitenspezifische Styles: nur noch Aufbau dieser Seite; Optik kommt aus css/msv-ui.css
$page_specific_css = "
/* Kopf-Card + Tabellen-Card füllen das Fenster, die Tabelle scrollt innen */
.end-seite { display: flex; flex-direction: column; height: calc(100vh - var(--nav-h, 76px) - 28px); min-height: 520px; margin-bottom: 0 !important; }
.end-seite > .msv-kopf { flex-shrink: 0; }
.end-tabelle { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; overflow: hidden; }
.end-tabelle .desktop-table-container { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
.end-scroll { flex: 1 1 auto; min-height: 0; overflow: auto; outline: none; }
#mitgliederTabelle { margin: 0; border-collapse: separate; border-spacing: 0; }
#mitgliederTabelle thead th { position: sticky; top: 0; z-index: 2; padding: 8px 10px; white-space: nowrap; text-align: right; }
#mitgliederTabelle tbody td { height: 40px; padding: 0 10px; white-space: nowrap; text-align: right; vertical-align: middle; border-bottom: 1px solid var(--ui-linie-zart); }
#mitgliederTabelle thead th:first-child, #mitgliederTabelle tbody td:first-child { text-align: left; padding-left: 20px; }
#mitgliederTabelle tbody td:first-child { font-weight: 600; }
#mitgliederTabelle thead th:last-child, #mitgliederTabelle tbody td:last-child { text-align: left; padding-right: 20px; }
#mitgliederTabelle tbody tr.hybrid-row { cursor: pointer; }
#mitgliederTabelle tbody tr.hybrid-row:hover > td { background: var(--ui-flaeche-2); }
#mitgliederTabelle tbody tr.ui-leer td { height: auto; padding: 32px 16px; text-align: center; color: var(--ui-text-2); white-space: normal; cursor: default; }
#editPanel .panel-header h6 { font-size: 1.15rem; line-height: 1.2; }
#editPanel .panel-pos { color: var(--ui-text-2); font-size: .8rem; }
#editPanel .panel-footer .ui-kbd { margin: 0 2px; }

@media (max-width: 767.98px) {
    .desktop-table-container { display: none !important; }
    .mobile-cards-container { display: flex !important; }
    .end-seite { height: auto; min-height: 0; }
    .end-tabelle { overflow: visible; }
    .hybrid-edit-panel { width: 100vw; right: -100vw; }
    .panel-overlay { display: none !important; }
    .panel-footer { position: sticky; bottom: 0; }
    .panel-footer .btn { min-height: 48px; font-size: 0.9rem; }
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
            <div class="main-content-wrapper content-width-wide end-seite">
                <?php
                $page_title = 'Endschiessen Resultaterfassung';
                $page_title_after = '<button type="button" class="btn-help" data-help="endresultate.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_actions = '<a href="endsch_import.php" class="btn btn-outline-success btn-sm"><i class="bi bi-upload me-1"></i>CSV importieren</a>'
                    . '<button id="redirect-btn" type="button" class="btn btn-outline-info btn-sm"><i class="bi bi-list-ol me-1"></i>Rangliste</button>';
                $page_extra = '<div class="ui-fortschritt" aria-live="polite"><span class="ui-zahl" id="progressText">–</span>'
                    . '<span class="ui-balken" aria-hidden="true"><span id="progressBar"></span></span></div>'
                    . '<div class="ui-chips" id="progressChips"></div>';
                $page_show_mobile = true;
                include 'partials/page_header.inc.php';
                ?>

                <form id="endresultateForm" class="ui-karte end-tabelle" onsubmit="return false">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel">Mitglieder</span>
                        <div class="ui-filter" role="group" aria-label="Nach Stand filtern">
                            <button type="button" data-filter="alle" aria-pressed="true">Alle <span id="nAlle">0</span></button>
                            <button type="button" data-filter="offen" aria-pressed="false">Offen <span id="nOffen">0</span></button>
                            <button type="button" data-filter="ok" aria-pressed="false">Vollständig <span id="nOk">0</span></button>
                        </div>
                        <label class="ui-suche d-none d-md-flex">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <span class="visually-hidden">Mitglied suchen</span>
                            <input type="search" id="endSuche" placeholder="Mitglied suchen" autocomplete="off">
                        </label>
                    </div>

                    <!-- Desktop: Tabelle -->
                    <div class="desktop-table-container">
                        <div class="end-scroll" id="endScroll" tabindex="0" aria-label="Mitglieder. Mit den Pfeiltasten wählen, Enter öffnet die Erfassung.">
                            <table class="table mb-0" id="mitgliederTabelle">
                                <thead>
                                    <tr>
                                        <th scope="col">Mitglied</th>
                                        <th scope="col">Endstich</th>
                                        <th scope="col">Schwini</th>
                                        <th scope="col">Kunst</th>
                                        <th scope="col">Glück</th>
                                        <th scope="col">Zabig</th>
                                        <th scope="col">Sie und Er</th>
                                        <th scope="col">Ansage</th>
                                        <th scope="col">Stand</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="ui-leer">
                                        <td colspan="9"><div class="spinner-border spinner-border-sm me-2"></div>Lade Daten …</td>
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

                    <div class="ui-tasten d-none d-md-flex">
                        <span><kbd>↑</kbd> <kbd>↓</kbd> wählen</span>
                        <span><kbd>Enter</kbd> erfassen</span>
                        <span>im Panel: <kbd>Enter</kbd> nächstes Feld</span>
                        <span><kbd>Ctrl</kbd>+<kbd>S</kbd> speichern</span>
                        <span><kbd>Ctrl</kbd>+<kbd>Enter</kbd> speichern &amp; nächstes offenes</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Panel Overlay -->
<div class="panel-overlay" id="panelOverlay"></div>

<!-- Slide-Panel -->
<div class="hybrid-edit-panel" id="editPanel" style="--panel-width: 600px;">
    <div class="panel-header">
        <div class="min-w-0">
            <h6 class="mb-0" id="panelTitle">Erfassen</h6>
            <small class="panel-pos" id="panelSubtitle"></small>
        </div>
        <div class="d-flex align-items-center gap-1 ms-auto">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelPrev" data-tooltip="Vorheriges Mitglied" aria-label="Vorheriges Mitglied">
                <i class="bi bi-chevron-up" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelNext" data-tooltip="Nächstes Mitglied" aria-label="Nächstes Mitglied">
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelClose" data-tooltip="Schliessen (Esc)" aria-label="Schliessen (Esc)">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="panel-body" id="panelBody">
        <input type="hidden" id="mitgliedID" name="mitgliedID">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <!-- Endstich -->
        <div class="shot-section shot-first" id="endstichSchuesse" data-stich="END">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-bullseye"></i>Endstich <button type="button" class="btn-help" data-help="endresultate.endstich" aria-label="Hilfe"></button></span>
                <span class="shot-status">Nicht gelöst</span>
                <span class="shot-total" id="endstichSumme">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-row">
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=10; $i++): ?>
                        <input type="number" class="shot-input endschuss focusable-input" id="Schuss<?= $i ?>" name="Schuss<?= $i ?>" aria-label="Endstich Schuss <?= $i ?>" min="0" max="10" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="shot-row">
                    <span class="shot-row-label">Tiefschuss</span>
                    <input type="number" class="shot-input shot-input-wide focusable-input" id="Tiefschuss" name="Tiefschuss" aria-label="Endstich Tiefschuss" min="0" max="100" inputmode="numeric">
                </div>
            </div>
        </div>

        <!-- Schwini -->
        <div class="shot-section" id="schwiniSchuesse" data-stich="SCHWINI">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-piggy-bank"></i>Schwini <button type="button" class="btn-help" data-help="endresultate.schwini" aria-label="Hilfe"></button></span>
                <span class="shot-status">Nicht gelöst</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-row schwini-passe-1">
                    <span class="shot-row-label">Passe 1</span>
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=6; $i++): ?>
                        <input type="number" class="shot-input schwini-schuss1 focusable-input" id="P1Schuss<?= $i ?>" name="P1Schuss<?= $i ?>" aria-label="Schwini Passe 1, Schuss <?= $i ?>" min="0" max="10" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                    <span class="shot-status">Nicht gelöst</span>
                    <span class="shot-total" id="schwiniSumme1">0</span>
                </div>
                <div class="shot-row schwini-passe-2">
                    <span class="shot-row-label">Passe 2</span>
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=6; $i++): ?>
                        <input type="number" class="shot-input schwini-schuss2 focusable-input" id="P2Schuss<?= $i ?>" name="P2Schuss<?= $i ?>" aria-label="Schwini Passe 2, Schuss <?= $i ?>" min="0" max="10" inputmode="numeric">
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
                        <input type="number" class="shot-input kunst focusable-input" id="KSchuss<?= $i ?>" name="KSchuss<?= $i ?>" aria-label="Kunst Schuss <?= $i ?>" min="0" max="100" inputmode="numeric">
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
                        <input type="number" class="shot-input glueck focusable-input" id="GSchuss<?= $i ?>" name="GSchuss<?= $i ?>" aria-label="Glück Schuss <?= $i ?>" min="0" max="100" inputmode="numeric">
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
                    <input type="number" class="shot-input zabig focusable-input" id="ZSchuss<?= $i ?>" name="ZSchuss<?= $i ?>" aria-label="Zabig Schuss <?= $i ?>" min="0" max="100" inputmode="numeric">
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Sie und Er -->
        <div class="shot-section" id="sieunderSchuesse" data-stich="SIEUNDER">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-people"></i>Sie und Er <span class="shot-hint">Schüsse 6–10, Mitglied</span> <button type="button" class="btn-help" data-help="endresultate.sieunder" aria-label="Hilfe"></button></span>
                <span class="shot-status">Nicht gelöst</span>
                <span class="shot-total" id="uniqueTotal" data-tooltip="Total der eindeutigen Werte">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-grid">
                    <?php for ($i=6; $i<=10; $i++): ?>
                    <input type="number"
                           class="shot-input shot-input-dec sie-er-schuss sie-er-mitglied focusable-input"
                           id="SieErSchuss<?= $i ?>"
                           name="SieErSchuss<?= $i ?>" aria-label="Sie und Er Schuss <?= $i ?>"
                           data-position="<?= $i ?>"
                           data-source="mitglied"
                           min="0" max="10"
                           placeholder="<?= $i ?>"
                           inputmode="numeric">
                    <?php endfor; ?>
                </div>
                <div class="shot-hint mt-2">Zusammen mit den Schüssen 1–5 der Partnerin. Jeder Wert zählt nur einmal, Doppelte sind rot durchgestrichen.</div>
            </div>
        </div>

        <!-- Ansage + Absenden -->
        <div class="shot-cols">
            <div class="shot-section" id="Differenzler">
                <div class="shot-section-head">
                    <span class="shot-section-title"><i class="bi bi-chat-square-text"></i>Ansage <span class="shot-hint">Differenzler</span> <button type="button" class="btn-help" data-help="endresultate.ansage" aria-label="Hilfe"></button></span>
                    <span class="shot-status">Nicht gelöst</span>
                </div>
                <div class="shot-section-body">
                    <input type="number" class="shot-input shot-input-wide focusable-input" id="Ansage" name="Ansage" aria-label="Ansage (Differenzler)" min="0" max="999" inputmode="numeric">
                </div>
            </div>
            <div class="shot-section" id="Absendenanmeldung">
                <div class="shot-section-head">
                    <span class="shot-section-title"><i class="bi bi-calendar-check"></i>Absenden</span>
                </div>
                <div class="shot-section-body">
                    <input type="text" class="form-control form-control-sm focusable-input" id="AbsendenAnmeldung" name="AbsendenAnmeldung" aria-label="Absenden: Anmeldung" placeholder="Anmeldung">
                </div>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <div class="alert alert-danger small py-2 px-3 msv-eingabe-fehler" id="panelFehler" role="alert" hidden></div>
        <div class="d-flex gap-2 w-100 align-items-center">
            <button type="button" class="btn btn-outline-danger btn-sm" id="panelDeleteBtn" data-tooltip="Resultate dieses Mitglieds löschen" aria-label="Resultate dieses Mitglieds löschen">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
            <span class="small text-muted me-auto d-none d-md-inline"><kbd class="ui-kbd">Ctrl</kbd>+<kbd class="ui-kbd">Enter</kbd> nächstes offenes</span>
            <button type="button" class="btn btn-outline-primary btn-sm" id="panelSaveBtn">
                <i class="bi bi-save me-1"></i>Speichern
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="panelSaveNextBtn">
                Speichern &amp; nächstes offenes <i class="bi bi-arrow-right ms-1"></i>
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
            else if (wahl === 'speichern') this.save(weiter);
        },

        versucheSchliessen() { this.schuetze(() => this.close()); },

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
            $('#panelTitle').text(name || 'Erfassen');
            $('#panelSubtitle').text((this.currentIndex + 1) + ' von ' + this.allRows.length);

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
            this.setGeaendert(false);
            msvEingabeFehler('#panelFehler', []);
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
            this.setGeaendert(false);
            $('#editPanel [aria-invalid]').removeAttr('aria-invalid');
            msvEingabeFehler('#panelFehler', []);
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
            const id = this.allRows[newIndex].id;
            this.schuetze(() => this.open(id));
        },

        save(callback) {
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
                url: 'endschresultate/save_schuss.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(resp) {
                    if (!resp || resp.success !== true) {
                        msvToast('Nicht gespeichert: ' + ((resp && resp.message) || 'unbekannter Fehler') + '. Die Eingaben sind noch da.', 'error');
                        return;
                    }
                    EndEditPanel.setGeaendert(false);
                    msvToast('Resultate gespeichert', 'success');
                    if (callback) {
                        // Tabelle neu laden, Panel bleibt offen, danach weiter
                        loadData($('#yearSelect').val(), callback);
                    } else {
                        EndEditPanel.close();
                        loadData($('#yearSelect').val());
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
            const gespeichert = this.currentMitgliedId;
            this.save(function() {
                // Nach dem Neuladen: nächstes Mitglied mit offenen Stichen, nach dem gespeicherten beginnend
                const rows = EndEditPanel.allRows;
                const i = rows.findIndex(r => r.id == gespeichert);
                const reihe = i >= 0 ? rows.slice(i + 1).concat(rows.slice(0, i)) : rows;
                const naechstes = reihe.find(r => r.stand === 'offen');
                if (naechstes) {
                    EndEditPanel.open(naechstes.id);
                } else {
                    msvToast('Alle gelösten Stiche sind erfasst', 'success');
                    EndEditPanel.close();
                }
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
                const hasData = String($(tr).data('has-data')) === '1';
                this.allRows.push({
                    id: $(tr).data('mitglied-id'),
                    hasData: hasData,
                    stand: $(tr).attr('data-stand') || (hasData ? 'ok' : 'offen'),
                    tr: tr
                });
            });
        },

        updateProgress() {
            const total = this.allRows.length;
            const ok = this.allRows.filter(r => r.stand === 'ok').length;
            const offen = total - ok;
            const pct = total > 0 ? Math.round((ok / total) * 100) : 0;
            $('#progressText').html(ok + ' von ' + total + ' <span>vollständig erfasst</span>');
            $('#progressBar').css('width', pct + '%');
            $('#progressChips').html(
                '<span class="ui-chip"><b>' + total + '</b> Mitglieder mit gelösten Stichen</span>' +
                (offen ? '<span class="ui-chip"><span class="ui-punkt"></span><b>' + offen + '</b> mit offenen Stichen</span>' : '')
            );
            $('#nAlle').text(total);
            $('#nOffen').text(offen);
            $('#nOk').text(ok);
        }
    };

    // =========================================
    //  Jahr-Dropdown
    // =========================================
    function initializeYearDropdown() {
        msvJahrAuswahl('#yearSelect', { jahr: <?php echo isset($_GET['year']) ? (int)$_GET['year'] : 'null'; ?> });
    }

    // =========================================
    //  Daten laden
    // =========================================
    function loadData(year, nachher) {
        var $tbody = $('#mitgliederTabelle tbody');
        if (!nachher) {
            EndEditPanel.close();
            $tbody.html('<tr class="ui-leer"><td colspan="9"><div class="spinner-border spinner-border-sm me-2"></div>Lade Daten …</td></tr>');
        }

        $.ajax({
            url: 'endschresultate/load_endschresultate.php',
            type: 'GET',
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                EndEditPanel.buildRowIndex();
                EndEditPanel.updateProgress();
                filterAnwenden();
                buildMobileEndCards();
                if (typeof nachher === 'function') nachher();
            },
            error: function(xhr) {
                $tbody.html('<tr class="ui-leer"><td colspan="9" class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Die Daten konnten nicht geladen werden: ' + msvEsc(msvXhrMessage(xhr, 'Serverfehler')) + '</td></tr>');
            }
        });
    }

    // =========================================
    //  Filter (Alle / Offen / Vollständig) und Suche
    // =========================================
    var endFilter = 'alle', endSuchtext = '';
    function filterAnwenden() {
        var sichtbar = 0;
        EndEditPanel.allRows.forEach(function(r) {
            var name = $(r.tr).find('td:first').text().toLowerCase();
            var zeigen = (endFilter === 'alle' || r.stand === endFilter) && name.indexOf(endSuchtext) !== -1;
            r.tr.style.display = zeigen ? '' : 'none';
            if (zeigen) sichtbar++;
        });
        $('#endKeineTreffer').remove();
        if (!sichtbar && EndEditPanel.allRows.length) {
            $('#mitgliederTabelle tbody').append(
                '<tr class="ui-leer" id="endKeineTreffer"><td colspan="9">Keine Mitglieder für diese Auswahl. ' +
                '<button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="endFilterZurueck">Filter zurücksetzen</button></td></tr>');
        }
    }
    $(document).on('click', '.ui-filter button', function() {
        endFilter = $(this).data('filter');
        $('.ui-filter button').attr('aria-pressed', 'false');
        $(this).attr('aria-pressed', 'true');
        filterAnwenden();
    });
    $('#endSuche').on('input', function() { endSuchtext = this.value.trim().toLowerCase(); filterAnwenden(); });
    $(document).on('click', '#endFilterZurueck', function() {
        endFilter = 'alle'; endSuchtext = ''; $('#endSuche').val('');
        $('.ui-filter button').attr('aria-pressed', 'false');
        $('.ui-filter button[data-filter="alle"]').attr('aria-pressed', 'true');
        filterAnwenden();
    });

    // Tabelle per Tastatur: Pfeile wählen, Enter öffnet
    $('#endScroll').on('keydown', function(e) {
        var zeilen = EndEditPanel.allRows.filter(function(r) { return r.tr.style.display !== 'none'; });
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
            EndEditPanel.open(zeilen[akt].id);
        }
    });
    $('#endScroll').on('blur', function() { $('#mitgliederTabelle tr.ui-markiert').removeClass('ui-markiert'); });

    // Panel: Ctrl+S speichert, Ctrl+Enter speichert und öffnet das nächste offene Mitglied
    $(document).on('keydown', function(e) {
        if (!$('#editPanel').hasClass('open') || !(e.ctrlKey || e.metaKey)) return;
        if (e.key === 's' || e.key === 'S') { e.preventDefault(); EndEditPanel.save(); }
        else if (e.key === 'Enter') { e.preventDefault(); EndEditPanel.saveAndNext(); }
    });

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
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                var currentIndex = $inputs.index(this);
                var nextIndex = currentIndex + 1;
                if (nextIndex < $inputs.length) {
                    $inputs.eq(nextIndex).focus().select();
                } else {
                    EndEditPanel.saveAndNext();
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


    // Klick auf Tabellenzeile → Panel öffnen
    $(document).on('click', '.hybrid-row', function() {
        const mitgliedId = $(this).data('mitglied-id');
        if (mitgliedId) EndEditPanel.open(mitgliedId);
    });

    // Panel schliessen
    $('#panelClose, #panelOverlay').on('click', function() { EndEditPanel.versucheSchliessen(); });

    // Escape schliesst Panel
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#editPanel').hasClass('open') && !(window.Swal && Swal.isVisible())) {
            e.stopImmediatePropagation();
            EndEditPanel.versucheSchliessen();
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

        const name = $('#panelTitle').text().trim();
        const r = await msvConfirmDelete('', {
            title: 'Resultate löschen?',
            html: 'Alle Endschiessen-Resultate ' + $('#yearSelect').val() + ' von <strong>' + msvEsc(name) + '</strong> werden gelöscht.',
            confirmText: 'Ja, löschen'
        });
        if (!r.isConfirmed) return;

        $.ajax({
            url: 'endschresultate/delete_endschresultat.php',
            method: 'POST',
            data: {
                mitgliedID: mitgliedId,
                jahr: $('#yearSelect').val(),
                csrf_token: $('#editPanel input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success === false) { msvToast('Nicht gelöscht: ' + (resp.message || 'unbekannter Fehler'), 'error'); return; }
                msvToast('Resultate gelöscht', 'success');
                EndEditPanel.close();
                loadData($('#yearSelect').val());
            },
            error: function(xhr) { msvToast('Nicht gelöscht: ' + msvXhrMessage(xhr, 'Serverfehler'), 'error'); }
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

    // Eingaben: «Nicht gespeichert» setzen, Zahl sofort prüfen; eine offene Fehlerliste frischt sich mit auf
    $(document).on('input', '#editPanel input:not([type="hidden"])', function() {
        EndEditPanel.setGeaendert(true);
        if (this.type === 'number') msvPruefeZahl(this);
        if (!$('#panelFehler').prop('hidden')) msvEingabeFehler('#panelFehler', msvPruefeFelder('#editPanel'), false);
    });
    $(document).on('change', '#editPanel select', function(e) { if (e.originalEvent) EndEditPanel.setGeaendert(true); });

    // Seite verlassen mit offenen Eingaben: der Browser fragt nach
    $(window).on('beforeunload', function() { if (EndEditPanel.geaendert) return 'Nicht gespeicherte Eingaben'; });

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
