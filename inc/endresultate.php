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
/* Fensterhöhe (.ui-vollhoehe, .ui-scroll) und Erfassungs-Panel (.ui-erfassen) kommen aus css/msv-ui.css */
#mitgliederTabelle { margin: 0; border-collapse: separate; border-spacing: 0; }
#mitgliederTabelle thead th { position: sticky; top: 0; z-index: 2; padding: 8px 10px; white-space: nowrap; text-align: right; }
#mitgliederTabelle tbody td { height: 40px; padding: 0 10px; white-space: nowrap; text-align: right; vertical-align: middle; border-bottom: 1px solid var(--ui-linie-zart); }
#mitgliederTabelle thead th:first-child, #mitgliederTabelle tbody td:first-child { text-align: left; padding-left: 20px; }
#mitgliederTabelle tbody td:first-child { font-weight: 600; }
#mitgliederTabelle thead th:last-child, #mitgliederTabelle tbody td:last-child { text-align: left; padding-right: 20px; }
#mitgliederTabelle tbody tr.hybrid-row { cursor: pointer; }
#mitgliederTabelle tbody tr.hybrid-row:hover > td { background: var(--ui-flaeche-2); }
#mitgliederTabelle tbody tr.ui-leer td { height: auto; padding: 32px 16px; text-align: center; color: var(--ui-text-2); white-space: normal; cursor: default; }
/* Gäste und Jungschützen (seit Migration 094 in derselben Liste) – gleiche Marke wie auf «Stiche lösen» */
.typ-badge { display: inline-block; margin-left: 6px; padding: 1px 6px; border-radius: 6px; background: var(--ui-linie-zart); color: var(--ui-text-2); font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; vertical-align: middle; }
.typ-badge.js { background: var(--ui-akzent-hell); color: var(--ui-akzent-dunkel); }
/* Sie und Er: Partnerwahl und Schüsse der Partnerin/des Partners (nur Anzeige) */
#sieunderSchuesse .su-partner { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
#sieunderSchuesse .su-partner label { margin: 0; font-size: .85rem; color: var(--ui-text-2); }
#sieunderSchuesse .su-partner select { width: auto; min-width: 220px; max-width: 100%; }
#sieunderSchuesse .su-partner-schuesse { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 8px; font-size: .85rem; color: var(--ui-text-2); }
#sieunderSchuesse .su-schuss { display: inline-flex; align-items: center; justify-content: center; min-width: 28px; height: 26px; padding: 0 6px; border: 1px solid var(--ui-linie); border-radius: 6px; background: var(--ui-flaeche-2); color: var(--ui-text); font-weight: 600; }
#sieunderSchuesse .su-schuss.is-dup { text-decoration: line-through; color: var(--ui-fehler); }

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
                $page_title = 'Endschiessen Resultate erfassen';
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

                <form id="endresultateForm" class="ui-karte ui-vollhoehe-karte" onsubmit="return false">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel">Teilnehmer</span>
                        <div class="ui-filter" role="group" aria-label="Nach Stand filtern">
                            <button type="button" data-filter="alle" aria-pressed="true">Alle <span id="nAlle">0</span></button>
                            <button type="button" data-filter="offen" aria-pressed="false">Offen <span id="nOffen">0</span></button>
                            <button type="button" data-filter="ok" aria-pressed="false">Vollständig <span id="nOk">0</span></button>
                        </div>
                        <label class="ui-suche d-none d-md-flex">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <span class="visually-hidden">Name suchen</span>
                            <input type="search" id="endSuche" placeholder="Name suchen" autocomplete="off">
                        </label>
                    </div>

                    <!-- Desktop: Tabelle -->
                    <div class="desktop-table-container">
                        <div class="ui-scroll end-scroll" id="endScroll" tabindex="0" data-zeilen-tastatur aria-label="Teilnehmer. Mit den Pfeiltasten wählen, Enter öffnet die Erfassung.">
                            <table class="table mb-0" id="mitgliederTabelle">
                                <thead>
                                    <tr>
                                        <th scope="col">Teilnehmer</th>
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
                                <input type="text" class="form-control" placeholder="Name suchen..."
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
<div class="hybrid-edit-panel ui-erfassen" id="editPanel" style="--panel-width: 600px;">
    <div class="panel-header">
        <div class="min-w-0">
            <h6 class="mb-0" id="panelTitle">Erfassen</h6>
            <small class="panel-pos" id="panelSubtitle"></small>
        </div>
        <div class="d-flex align-items-center gap-1 ms-auto">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelPrev" data-tooltip="Vorherige Person" aria-label="Vorherige Person">
                <i class="bi bi-chevron-up" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelNext" data-tooltip="Nächste Person" aria-label="Nächste Person">
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelClose" data-tooltip="Schliessen (Esc)" aria-label="Schliessen (Esc)">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="panel-body" id="panelBody">
        <input type="hidden" id="teilnehmer" name="teilnehmer">
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
                <span class="shot-section-title"><i class="bi bi-people"></i>Sie und Er <span class="shot-hint">fünf eigene Schüsse</span> <button type="button" class="btn-help" data-help="endresultate.sieunder" aria-label="Hilfe"></button></span>
                <span class="shot-status">Nicht gelöst</span>
                <span class="shot-total" id="uniqueTotal" data-tooltip="Total des Paars: jeder Wert zählt einmal">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-grid">
                    <?php for ($i=1; $i<=5; $i++): ?>
                    <input type="number"
                           class="shot-input shot-input-dec sie-er-schuss sie-er-mitglied focusable-input"
                           id="SieErSchuss<?= $i ?>"
                           name="SieErSchuss<?= $i ?>" aria-label="Sie und Er Schuss <?= $i ?>"
                           data-position="<?= $i ?>"
                           min="0" max="10"
                           placeholder="<?= $i ?>"
                           inputmode="numeric">
                    <?php endfor; ?>
                </div>
                <div class="su-partner">
                    <label for="SieUnderPartner">Partnerin / Partner</label>
                    <select class="form-select form-select-sm focusable-input" id="SieUnderPartner" name="SieUnderPartner">
                        <option value="">– noch nicht gewählt –</option>
                    </select>
                </div>
                <div class="su-partner-schuesse" id="suPartnerSchuesse" hidden></div>
                <div class="shot-hint mt-2">Zur Auswahl steht, wer Sie und Er gelöst hat – Mitglied oder Gast. Die Verknüpfung gilt für beide. Jeder Wert zählt im Paar nur einmal, Doppelte sind rot durchgestrichen.</div>
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

        <!-- Partner-Rangliste: Mitglied zusätzlich aufführen (nur Mitglieder, Migration 096) -->
        <div class="shot-section" id="partnerrangSection" hidden>
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-person-heart"></i>Partner-Rangliste <button type="button" class="btn-help" data-help="endresultate.partnerrang" aria-label="Hilfe"></button></span>
            </div>
            <div class="shot-section-body">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="Partnerrang" name="Partnerrang" value="1">
                    <label class="form-check-label" for="Partnerrang">Als Partnerin bzw. Partner zusätzlich in der Partner-Rangliste aufführen</label>
                </div>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <div class="alert alert-danger small py-2 px-3 msv-eingabe-fehler" id="panelFehler" role="alert" hidden></div>
        <div class="d-flex gap-2 w-100 align-items-center">
            <button type="button" class="btn btn-outline-danger btn-sm" id="panelDeleteBtn" data-tooltip="Resultate dieser Person löschen" aria-label="Resultate dieser Person löschen">
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
        currentKey: null,          // Teilnehmer-Schlüssel m<ID> (Mitglied) bzw. g<ID> (Gast/Jungschütze)
        sieUnder: null,            // Kandidaten und Schüsse für Sie und Er (aus load_schussdaten)
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

        open(key) {
            this.currentKey = key;
            this.currentIndex = this.allRows.findIndex(r => r.id === key);

            // Zeile markieren
            $('.hybrid-row').removeClass('selected');
            if (this.currentIndex >= 0) {
                const $row = $(this.allRows[this.currentIndex].tr);
                $row.addClass('selected');
                this.allRows[this.currentIndex].tr.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }

            // Titel setzen
            const zeile = this.currentIndex >= 0 ? this.allRows[this.currentIndex] : null;
            const name = zeile ? $(zeile.tr).find('.tn-name').text().trim() : '';
            const typText = zeile && zeile.typ === 'gast' ? 'Gast · ' : (zeile && zeile.typ === 'js' ? 'Jungschütze/-in · ' : '');
            $('#panelTitle').text(name || 'Erfassen');
            $('#panelSubtitle').text(typText + (this.currentIndex + 1) + ' von ' + this.allRows.length);

            // Navigation
            $('#panelPrev').prop('disabled', this.currentIndex <= 0);
            $('#panelNext').prop('disabled', this.currentIndex >= this.allRows.length - 1);

            // Hidden Field
            $('#teilnehmer').val(key);

            // Form zurücksetzen
            this.resetForm();

            // Panel öffnen
            $('#editPanel').addClass('open');
            $('#panelOverlay').addClass('show');

            // Panel-Body nach oben scrollen
            $('#panelBody').scrollTop(0);

            // Daten laden
            this.loadSchussdaten(key);
        },

        close() {
            this.setGeaendert(false);
            msvEingabeFehler('#panelFehler', []);
            $('#editPanel').removeClass('open');
            $('#panelOverlay').removeClass('show');
            $('.hybrid-row').removeClass('selected');
            this.currentKey = null;
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
            this.sieUnder = null;
            $('#Partnerrang').prop('checked', false);
            $('#partnerrangSection').prop('hidden', true);
            $('#SieUnderPartner').find('option:not(:first)').remove();
            $('#suPartnerSchuesse').prop('hidden', true).empty();
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

        loadSchussdaten(key) {
            const year = $('#yearSelect').val();

            // Vorherigen Request abbrechen
            if (this._loadingXhr) this._loadingXhr.abort();

            this._loadingXhr = $.ajax({
                url: 'endschresultate/load_schussdaten.php',
                type: 'GET',
                data: { teilnehmer: key, year: year },
                dataType: 'json',
                success: function(data) {
                    // Immer aufrufen: ohne gelöste Stiche bleibt nur Absenden offen, die Enter-Navigation braucht es trotzdem
                    updateStichAvailability(data.geloesteStiche || []);

                    $('#partnerrangSection').prop('hidden', data.teilnehmerTyp !== 'mitglied');
                    $('#Partnerrang').prop('checked', String(data.Partnerrang) === '1');
                    for (var feld in data) {
                        if (['geloesteStiche', 'sieUnder', 'teilnehmer', 'teilnehmerTyp', 'Partnerrang'].indexOf(feld) === -1) {
                            var $field = $('#' + feld);
                            if ($field.length) $field.val(data[feld]);
                        }
                    }
                    sieUnderAnzeigen(data.sieUnder || null);

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
                    msvToast('Resultate gespeichert' + (resp.geleert && resp.geleert.length ? ' – geleert: ' + resp.geleert.join(', ') : ''), 'success');
                    if (callback) {
                        // Tabelle neu laden, Panel bleibt offen, danach weiter
                        loadData($('#yearSelect').val(), callback);
                    } else {
                        EndEditPanel.close();
                        loadData($('#yearSelect').val());
                    }
                },
                error: function(xhr) {
                    msvToast('Nicht gespeichert: ' + msvXhrMessage(xhr, 'Serverfehler').replace(/\.?$/, '.') + ' Die Eingaben sind noch da.', 'error');
                },
                complete: function() {
                    $saveBtn.prop('disabled', false).html(originalSave);
                    $saveNextBtn.prop('disabled', false).html(originalNext);
                }
            });
        },

        saveAndNext() {
            const gespeichert = this.currentKey;
            this.save(function() {
                // Nach dem Neuladen: nächstes Mitglied mit offenen Stichen, nach dem gespeicherten beginnend
                const rows = EndEditPanel.allRows;
                const i = rows.findIndex(r => r.id === gespeichert);
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
                teilnehmer: $('#teilnehmer').val(),
                jahr: $('#yearSelect').val(),
                csrf_token: $('#editPanel input[name="csrf_token"]').val()
            };

            // Alle benannten Inputs aus dem Panel sammeln
            $('#editPanel input[name]:not([type="hidden"]):not([type="checkbox"]), #editPanel select[name]').each(function() {
                if (!this.disabled) {
                    data[this.name] = $(this).val() || '';
                }
            });
            if (!$('#partnerrangSection').prop('hidden')) {
                data.Partnerrang = $('#Partnerrang').is(':checked') ? 1 : 0;
            }

            return data;
        },

        buildRowIndex() {
            this.allRows = [];
            $('#mitgliederTabelle tbody tr.hybrid-row').each((_, tr) => {
                const hasData = String($(tr).data('has-data')) === '1';
                this.allRows.push({
                    id: String($(tr).attr('data-teilnehmer')),
                    typ: $(tr).attr('data-typ') || 'mitglied',
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
                '<span class="ui-chip"><b>' + total + '</b> Teilnehmer mit gelösten Stichen</span>' +
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
    // Tabelle per Tastatur: msv-panel-a11y.js ([data-zeilen-tastatur] an #endScroll) – Pfeile, Pos1/Ende, Enter, Ansage

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
    //  Sie und Er: Partnerwahl und Paar-Total (Migration 094)
    // =========================================
    // Partnerwahl aus load_schussdaten füllen: wer im Jahr Sie und Er gelöst hat, mit seinen Schüssen
    function sieUnderAnzeigen(su) {
        EndEditPanel.sieUnder = su;
        const $sel = $('#SieUnderPartner');
        $sel.find('option:not(:first)').remove();
        if (!su) { updateSieErUniqueVisualization(); return; }
        const namen = {};
        (su.kandidaten || []).forEach(k => { namen[k.key] = k.name; });
        let gruppe = null, $og = null;
        (su.kandidaten || []).forEach(k => {
            const g = k.typ === 'gast' ? 'Gäste' : 'Mitglieder';
            if (g !== gruppe) { gruppe = g; $og = $('<optgroup>').attr('label', g).appendTo($sel); }
            // Schon mit jemand anderem verknüpft: dazuschreiben, die Wahl löst die alte Verknüpfung
            const mit = k.gepaartMit && k.gepaartMit !== EndEditPanel.currentKey ? ' – mit ' + (namen[k.gepaartMit] || 'jemand anderem') : '';
            $('<option>').val(k.key).text(k.name + mit).appendTo($og);
        });
        $sel.val(su.partner || '');
        updateSieErUniqueVisualization();
    }

    // Schüsse der gewählten Partnerin bzw. des Partners (gespeicherter Stand)
    function partnerSchuesse() {
        const su = EndEditPanel.sieUnder;
        const key = $('#SieUnderPartner').val();
        if (!su || !key) return [];
        const k = (su.kandidaten || []).find(x => x.key === key);
        return ((k && k.schuesse) || []).filter(v => v !== null && v !== '' && Number(v) > 0).map(Number);
    }

    // Jeder Wert zählt im Paar nur einmal: Partner-Schüsse zuerst, dann die eigenen; Doppelte durchgestrichen
    function updateSieErUniqueVisualization() {
        const gesehen = {};
        const $anzeige = $('#suPartnerSchuesse').empty();
        const partner = partnerSchuesse();
        const key = $('#SieUnderPartner').val();
        if (key) {
            const name = $('#SieUnderPartner option:selected').text().replace(/ – mit .*$/, '');
            $anzeige.append($('<span>').text(partner.length ? name + ':' : name + ' hat noch keine Schüsse erfasst.'));
            partner.forEach(v => {
                const dup = !!gesehen[v];
                gesehen[v] = true;
                $anzeige.append($('<span class="su-schuss">').toggleClass('is-dup', dup).text(v));
            });
        }
        $anzeige.prop('hidden', !key);

        $('.sie-er-schuss').removeClass('is-unique is-dup');
        $('.sie-er-mitglied').each(function() {
            const v = Math.floor(parseFloat($(this).val() || 0));
            if (!(v > 0)) return;
            $(this).addClass(gesehen[v] ? 'is-dup' : 'is-unique');
            gesehen[v] = true;
        });

        const total = Object.keys(gesehen).reduce((s, v) => s + Number(v), 0);
        $('#uniqueTotal').text(total);
    }
    $(document).on('change', '#SieUnderPartner', updateSieErUniqueVisualization);

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
        const key = $(this).attr('data-teilnehmer');
        if (key) EndEditPanel.open(key);
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
        const key = EndEditPanel.currentKey;
        if (!key) return;

        const name = $('#panelTitle').text().trim();
        const jahr = $('#yearSelect').val();
        const csrf = $('#editPanel input[name="csrf_token"]').val();
        // Erst fragen, was gelöscht würde (auch Partnerin und JM-Endstich), dann bestätigen; der Server sichert vorher
        let p;
        try { p = await $.post('endschresultate/delete_endschresultat.php', { teilnehmer: key, jahr, aktion: 'pruefen', csrf_token: csrf }, null, 'json'); }
        catch (xhr) { msvToast(msvXhrMessage(xhr, 'Prüfen hat nicht geklappt'), 'error'); return; }
        const b = (p && p.bezuege) || {};
        const liste = Object.keys(b).map(k => '<li>' + msvEsc(k) + ': ' + msvEsc(b[k]) + '</li>').join('');
        if (!liste) { msvToast('Für ' + jahr + ' sind von ' + msvEsc(name) + ' keine Resultate gespeichert.', 'info'); return; }
        const r = await msvConfirmDelete('', {
            title: 'Resultate löschen?',
            html: 'Von <strong>' + msvEsc(name) + '</strong> wird für ' + jahr + ' gelöscht:<ul class="text-start mt-2 mb-2 ps-3">' + liste + '</ul>Vorher wird die Datenbank gesichert.',
            confirmText: 'Ja, löschen'
        });
        if (!r.isConfirmed) return;

        $.ajax({
            url: 'endschresultate/delete_endschresultat.php',
            method: 'POST',
            data: {
                teilnehmer: key,
                jahr: jahr,
                aktion: 'loeschen',
                csrf_token: csrf
            },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success === false) { msvToast(resp.message || 'Nicht gelöscht', 'error'); return; }
                msvToast('Resultate gelöscht' + (resp && resp.sicherung ? ' – Sicherung ' + msvEsc(resp.sicherung) : ''), 'success');
                EndEditPanel.close();
                loadData($('#yearSelect').val());
            },
            error: function(xhr) { msvToast(msvXhrMessage(xhr, 'Nicht gelöscht'), 'error'); }
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
    $(document).on('change', '#editPanel select, #Partnerrang', function(e) { if (e.originalEvent) EndEditPanel.setGeaendert(true); });

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

            const key = row.dataset.teilnehmer;
            const hasData = row.dataset.hasData === '1';
            const memberName = row.querySelector('.tn-name')?.textContent?.trim() || 'Unbekannt';
            const typMarke = row.dataset.typ === 'js' ? ' (JS)' : (row.dataset.typ === 'gast' ? ' (Gast)' : '');
            const endstich = cells[1]?.textContent?.trim() || '-';
            const schwini = cells[2]?.textContent?.trim() || '-';
            const kunst = cells[3]?.textContent?.trim() || '-';
            const glueck = cells[4]?.textContent?.trim() || '-';
            const zabig = cells[5]?.textContent?.trim() || '-';
            const sieUndEr = cells[6]?.textContent?.trim() || '-';
            const ansage = cells[7]?.textContent?.trim() || '-';

            const statusDot = hasData
                ? '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--ui-ok-fg);margin-right:6px;"></span>'
                : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--ui-leer);margin-right:6px;"></span>';

            html += `
            <div class="mobile-card" data-index="${idx}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                        <div class="fw-bold">${statusDot}${msvEsc(memberName)}${typMarke}</div>
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
                            onclick="EndEditPanel.open('${key}')"
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
