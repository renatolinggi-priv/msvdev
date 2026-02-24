<?php
// heimresultate.php – mit Mobile Card/Accordion View
include 'dbconnect.inc.php';

// Seitenspezifische Styles
$page_specific_css = "
/* Heimresultate-spezifische Styles */

:root {
    --app-header: 76px;
    --app-footer: 0px;
}

/* Flex-Layout für volle Höhennutzung */
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

#heimresultateForm {
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

/* Moderne Tabellen-Styles */
.table {
    border: none;
    margin-bottom: 0;
    table-layout: fixed;
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

/* Button Toolbar */
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


/* Results List Card */
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
    margin: 0;
    color: var(--dark-color);
    font-weight: 600;
    font-size: 1rem;
    flex-shrink: 0;
}

.btn-compact { padding: .45rem .75rem; font-size: .875rem; }

.custom-close {
    background: none; border: none;
    color: var(--secondary-color);
    font-size: 1.5rem; opacity: 0.7;
    transition: all 0.2s ease;
    padding: 0; width: 30px; height: 30px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
}
.custom-close:hover {
    opacity: 1;
    background-color: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
    transform: scale(1.1);
}

.spinner-border { color: var(--secondary-color) !important; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.results-list-card { animation: fadeIn 0.5s ease-out; }

/* =============================================
   MOBILE CARD / ACCORDION VIEW
   ============================================= */

/* Desktop: Cards verstecken, Tabelle zeigen */
#mobileCardsContainer { display: none; }

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

    /* Mobile: Tabelle verstecken, Cards zeigen */
    #desktopTableContainer { display: none !important; }
    #mobileCardsContainer {
        display: flex !important;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }

    /* Höhe anpassen */
    .main-content-wrapper {
        height: auto !important;
        min-height: calc(100vh - var(--app-header) - 10px) !important;
    }

    .content-background {
        overflow: visible;
    }

    /* Mobile Cards Scroll-Container */
    .mobile-cards-scroll {
        flex: 1 1 auto;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding: 0.5rem;
    }

    /* Suchfeld */
    .mobile-search {
        padding: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
        background: #f8f9fa;
        flex-shrink: 0;
    }

    .mobile-search input {
        border-radius: 2rem;
        padding-left: 2.5rem;
        font-size: 0.9rem;
    }

    .mobile-search .search-icon {
        position: absolute;
        left: 1.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
    }

    /* Einzelne Member Card */
    .member-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        margin-bottom: 0.5rem;
        overflow: hidden;
        transition: box-shadow 0.2s ease;
    }

    .member-card.has-values {
        border-left: 3px solid var(--success-color, #28a745);
    }

    .member-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.875rem 1rem;
        cursor: pointer;
        user-select: none;
        -webkit-user-select: none;
        background: white;
        transition: background-color 0.15s ease;
    }

    .member-card-header:active {
        background-color: #f0f4ff;
    }

    .member-card-header .member-name {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--dark-color);
    }

    .member-card-header .member-summary {
        font-size: 0.75rem;
        color: #6c757d;
    }

    .member-card-header .chevron {
        transition: transform 0.25s ease;
        color: #adb5bd;
        font-size: 1.1rem;
        flex-shrink: 0;
        margin-left: 0.5rem;
    }

    .member-card.open .chevron {
        transform: rotate(180deg);
    }

    .member-card-body {
        display: none;
        padding: 0 1rem 1rem 1rem;
        border-top: 1px solid #f1f3f5;
    }

    .member-card.open .member-card-body {
        display: block;
    }

    /* Passen Grid: 2 Spalten */
    .passen-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }

    .passe-field {
        display: flex;
        flex-direction: column;
    }

    .passe-field label {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        margin-bottom: 0.2rem;
    }

    .passe-field input {
        text-align: center;
        font-size: 1.1rem !important;
        font-weight: 500;
        padding: 0.6rem 0.5rem !important;
        border: 1.5px solid #dee2e6;
        border-radius: 0.5rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        -webkit-appearance: none;
        -moz-appearance: textfield;
    }

    .passe-field input:focus {
        border-color: #4a90d9;
        box-shadow: 0 0 0 3px rgba(74, 144, 217, 0.15);
        outline: none;
    }

    .passe-field input.has-value {
        background-color: #f0faf0;
        border-color: #b8dab8;
    }

    /* Button Toolbar Mobile */
    .button-toolbar {
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .button-toolbar .btn-row {
        flex-direction: column;
        width: 100%;
    }

    .button-toolbar .btn {
        width: 100%;
    }


    /* Counter Badge */
    .mobile-counter {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
        color: #6c757d;
        padding: 0.5rem 0.75rem;
    }
}

/* Responsive Toolbar für sehr kleine Screens */
@media (max-width: 576px) {
    .button-toolbar { flex-direction: column; }
    .button-toolbar .btn { width: 100%; }
}
";

include 'header.inc.php';
?>
<style><?= $page_specific_css ?></style>
<?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-10 col-lg-11 col-12 ps-0">
            <div class="main-content-wrapper">
                <!-- Header -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-house-door me-2"></i>
                            Heimmeisterschaft Resultaterfassung
                        </h2>
                        <p class="text-muted mb-0">Resultate erfassen und verwalten</p>
                    </div>
                </div>

                <div class="content-background">
                    <form id="heimresultateForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr-Auswahl + Aktionen nebeneinander -->
                        <div class="d-flex flex-wrap gap-3 align-items-start mb-4">

                        <!-- Jahr-Auswahl (ohne Card) -->
                        <div class="d-flex align-items-center gap-2">
                            <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                                <i class="bi bi-calendar3 me-1"></i>Jahr:
                            </label>
                            <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
                        </div>

                        <!-- Aktionsbereich (Bootstrap Collapse) -->
                        <div class="card action-card mb-0">
                            <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                                 data-bs-toggle="collapse" data-bs-target="#heimresultateActions"
                                 aria-expanded="false" aria-controls="heimresultateActions">
                                <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                                <i class="bi bi-chevron-down action-chevron"></i>
                            </div>
                            <div class="collapse" id="heimresultateActions">
                                <div class="card-body pt-2 pb-3 px-3">
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-save me-2"></i>Ergebnisse speichern
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button id="redirect-btn" type="button" class="btn btn-outline-success w-100">
                                                <i class="bi bi-trophy me-1"></i>Rangliste
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button id="delete-btn" type="button" class="btn btn-outline-danger w-100">
                                                <i class="bi bi-trash me-1"></i>Löschen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        </div><!-- Ende flex-row Jahr+Aktionen -->

                        <!-- ====== DESKTOP: Tabelle ====== -->
                        <div id="desktopTableContainer">
                            <div class="results-list-card">
                                <div class="results-header">
                                    <i class="bi bi-table me-2"></i>Resultate
                                </div>
                                <div class="table-wrapper">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0" id="heimresultateTabelle">
                                            <thead>
                                                <tr>
                                                    <th scope="col" style="min-width: 180px; width: 200px;">
                                                        <i class="bi bi-person me-1"></i>Mitglied
                                                    </th>
                                                    <th scope="col" class="text-center" style="width: 75px;">Passe 1</th>
                                                    <th scope="col" class="text-center" style="width: 75px;">Passe 2</th>
                                                    <th scope="col" class="text-center" style="width: 75px;">Passe 3</th>
                                                    <th scope="col" class="text-center" style="width: 75px;">Passe 4</th>
                                                    <th scope="col" class="text-center" style="width: 75px;">Passe 5</th>
                                                    <th scope="col" class="text-center" style="width: 75px;">Passe 6</th>
                                                    <th scope="col" class="text-center" style="width: 75px;">Passe 7</th>
                                                    <th scope="col" class="text-center" style="width: 75px;">Passe 8</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <div class="spinner-border spinner-border-sm me-2"></div>
                                                        Lade Resultate...
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ====== MOBILE: Card/Accordion View ====== -->
                        <div id="mobileCardsContainer">
                            <div class="results-list-card">
                                <div class="results-header d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-people me-2"></i>Resultate</span>
                                    <span class="mobile-counter" id="mobileCounter"></span>
                                </div>
                                <!-- Suchfeld -->
                                <div class="mobile-search position-relative">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" id="mobileSearch" class="form-control form-control-sm"
                                           placeholder="Mitglied suchen..." autocomplete="off">
                                </div>
                                <!-- Scrollbare Cardliste -->
                                <div class="mobile-cards-scroll" id="mobileCardsList">
                                    <div class="text-center py-4 text-muted">
                                        <div class="spinner-border spinner-border-sm me-2"></div>
                                        Lade Resultate...
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

<!-- Lösch-Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="bi bi-exclamation-triangle text-warning"></i> Bestätigung erforderlich
                </h5>
                <button type="button" class="custom-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle text-danger me-3" style="font-size: 2rem;"></i>
                    <div>
                        <strong>Möchtest du wirklich ALLE Resultate des aktuellen Jahres löschen?</strong>
                        <br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden!</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-compact btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-compact btn-outline-danger" id="confirmDeleteButton">
                    <i class="bi bi-trash me-1"></i>Löschen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    var isMobile = function() { return window.innerWidth < 768; };

    // ===== Höhenberechnung für Desktop-Tabelle =====
    function calculateTableHeight() {
        if (isMobile()) return;
        var tableResp = $('.table-responsive');
        if (!tableResp.length || !tableResp.is(':visible')) return;
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

    // ===== Resultate laden =====
    function loadResultate(year) {
        // Desktop Loading
        var $tbody = $('#heimresultateTabelle tbody');
        $tbody.html(
            '<tr><td colspan="9" class="text-center py-4">' +
            '<div class="spinner-border spinner-border-sm me-2"></div>' +
            'Lade Resultate...</td></tr>'
        );
        // Mobile Loading
        $('#mobileCardsList').html(
            '<div class="text-center py-4 text-muted">' +
            '<div class="spinner-border spinner-border-sm me-2"></div>' +
            'Lade Resultate...</div>'
        );

        $.ajax({
            url: 'heimresultate/load_heimresultate_form.php',
            method: 'GET',
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                bindDesktopInputs();
                buildMobileCards();
                setTimeout(calculateTableHeight, 100);
            },
            error: function() {
                $tbody.html(
                    '<tr><td colspan="9" class="text-center text-danger py-4">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Fehler beim Laden der Daten</td></tr>'
                );
                $('#mobileCardsList').html(
                    '<div class="text-center py-4 text-danger">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Fehler beim Laden</div>'
                );
                msvToast('Fehler beim Laden der Resultate', 'error');
            }
        });
    }

    // ==========================================================
    //  MOBILE CARD VIEW – aus Tabellendaten generieren
    // ==========================================================

    function buildMobileCards() {
        var $container = $('#mobileCardsList').empty();
        var $rows = $('#heimresultateTabelle tbody tr');
        var totalMembers = 0;
        var membersWithValues = 0;

        $rows.each(function() {
            var $tr = $(this);
            var $tds = $tr.find('td');
            if ($tds.length < 2) return; // Skip loading/error rows

            totalMembers++;
            var name = $tds.eq(0).text().trim();
            var $inputs = $tr.find('input');
            if ($inputs.length === 0) return;

            // Prüfe ob Werte vorhanden
            var hasValues = false;
            var summaryParts = [];
            $inputs.each(function(idx) {
                var val = $(this).val();
                if (val && val !== '' && val !== '0') {
                    hasValues = true;
                    summaryParts.push('P' + (idx + 1) + ':' + val);
                }
            });
            if (hasValues) membersWithValues++;

            // Card HTML bauen
            var cardHtml = '<div class="member-card' + (hasValues ? ' has-values' : '') + '" data-name="' + name.toLowerCase() + '">';

            // Header
            cardHtml += '<div class="member-card-header">';
            cardHtml += '  <div>';
            cardHtml += '    <div class="member-name">' + escapeHtml(name) + '</div>';
            if (hasValues) {
                cardHtml += '    <div class="member-summary">' + summaryParts.join(' · ') + '</div>';
            } else {
                cardHtml += '    <div class="member-summary text-muted">Keine Resultate</div>';
            }
            cardHtml += '  </div>';
            cardHtml += '  <i class="bi bi-chevron-down chevron"></i>';
            cardHtml += '</div>';

            // Body mit Passen-Grid
            cardHtml += '<div class="member-card-body">';
            cardHtml += '  <div class="passen-grid">';

            $inputs.each(function(idx) {
                var $origInput = $(this);
                var inputName = $origInput.attr('name');
                var val = $origInput.val() || '';
                var passeNr = idx + 1;
                var hasVal = (val && val !== '' && val !== '0');

                cardHtml += '<div class="passe-field">';
                cardHtml += '  <label>Passe ' + passeNr + '</label>';
                cardHtml += '  <input type="text"';
                cardHtml += '    class="form-control mobile-passe-input' + (hasVal ? ' has-value' : '') + '"';
                cardHtml += '    data-sync="' + inputName + '"';
                cardHtml += '    value="' + escapeHtml(val) + '"';
                cardHtml += '    inputmode="numeric"';
                cardHtml += '    pattern="[0-9]*"';
                cardHtml += '    maxlength="2"';
                cardHtml += '    autocomplete="off">';
                cardHtml += '</div>';
            });

            cardHtml += '  </div>'; // /passen-grid
            cardHtml += '</div>'; // /member-card-body
            cardHtml += '</div>'; // /member-card

            $container.append(cardHtml);
        });

        // Counter aktualisieren
        $('#mobileCounter').html(
            '<i class="bi bi-people-fill me-1"></i>' +
            membersWithValues + '/' + totalMembers + ' erfasst'
        );

        // Mobile Input Events binden
        bindMobileInputs();
    }

    // ===== Mobile Input Events =====
    function bindMobileInputs() {
        var $mobileInputs = $('.mobile-passe-input');

        // Sync: Mobile → Desktop Table
        $mobileInputs.off('input.sync').on('input.sync', function() {
            var $this = $(this);
            var syncName = $this.data('sync');
            var value = $this.val().replace(/[^0-9]/g, '');
            if (value.length > 2) value = value.substring(0, 2);
            $this.val(value);

            // Wert in Desktop-Tabelle synchronisieren
            $('input[name="' + syncName + '"]').not('.mobile-passe-input').val(value);

            // Visuelles Feedback
            $this.toggleClass('has-value', value !== '' && value !== '0');
        });

        // Focus: 0 leeren
        $mobileInputs.off('focus.mobile').on('focus.mobile', function() {
            var $this = $(this);
            if ($this.val() === '0') $this.val('');
            $this.select();
        });

        // Blur: leer → 0
        $mobileInputs.off('blur.mobile').on('blur.mobile', function() {
            var $this = $(this);
            if ($this.val().trim() === '') $this.val('0');
            // Sync nochmal
            var syncName = $this.data('sync');
            $('input[name="' + syncName + '"]').not('.mobile-passe-input').val($this.val());

            // Summary aktualisieren
            updateCardSummary($this.closest('.member-card'));
        });

        // Enter: zum nächsten Feld
        $mobileInputs.off('keydown.mobile').on('keydown.mobile', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var $card = $(this).closest('.member-card');
                var $cardInputs = $card.find('.mobile-passe-input');
                var idx = $cardInputs.index(this);
                if (idx < $cardInputs.length - 1) {
                    $cardInputs.eq(idx + 1).focus();
                } else {
                    // Nächste Card öffnen
                    var $nextCard = $card.next('.member-card');
                    if ($nextCard.length) {
                        $card.removeClass('open');
                        $nextCard.addClass('open');
                        // Scroll zur nächsten Card
                        var scrollContainer = $('#mobileCardsList')[0];
                        var cardTop = $nextCard[0].offsetTop - scrollContainer.offsetTop;
                        scrollContainer.scrollTo({ top: cardTop - 10, behavior: 'smooth' });
                        setTimeout(function() {
                            $nextCard.find('.mobile-passe-input:first').focus();
                        }, 300);
                    }
                }
            }
        });
    }

    // ===== Card Summary aktualisieren =====
    function updateCardSummary($card) {
        var parts = [];
        var hasValues = false;
        $card.find('.mobile-passe-input').each(function(idx) {
            var val = $(this).val();
            if (val && val !== '' && val !== '0') {
                hasValues = true;
                parts.push('P' + (idx + 1) + ':' + val);
            }
        });

        $card.toggleClass('has-values', hasValues);
        var $summary = $card.find('.member-summary');
        if (hasValues) {
            $summary.removeClass('text-muted').text(parts.join(' · '));
        } else {
            $summary.addClass('text-muted').text('Keine Resultate');
        }

        // Counter aktualisieren
        var total = $('.member-card').length;
        var withValues = $('.member-card.has-values').length;
        $('#mobileCounter').html(
            '<i class="bi bi-people-fill me-1"></i>' +
            withValues + '/' + total + ' erfasst'
        );
    }

    // ===== Accordion Toggle =====
    $(document).on('click', '.member-card-header', function(e) {
        if ($(e.target).is('input')) return; // Nicht bei Input-Klick
        var $card = $(this).closest('.member-card');
        var wasOpen = $card.hasClass('open');

        // Alle anderen schliessen
        $('.member-card.open').not($card).removeClass('open');

        // Diese togglen
        $card.toggleClass('open', !wasOpen);

        // Bei Öffnen: ins Sichtfeld scrollen
        if (!wasOpen) {
            setTimeout(function() {
                var scrollContainer = $('#mobileCardsList')[0];
                if (scrollContainer) {
                    var cardTop = $card[0].offsetTop - scrollContainer.offsetTop;
                    scrollContainer.scrollTo({ top: cardTop - 10, behavior: 'smooth' });
                }
            }, 50);
        }
    });

    // ===== Mobile Suche =====
    $('#mobileSearch').on('input', function() {
        var query = $(this).val().toLowerCase().trim();
        $('.member-card').each(function() {
            var name = $(this).data('name') || '';
            $(this).toggle(name.indexOf(query) !== -1);
        });
    });

    // ==========================================================
    //  DESKTOP TABLE INPUT HANDLING
    // ==========================================================

    function bindDesktopInputs() {
        var $inputs = $('#heimresultateTabelle input');

        $inputs.off('keydown.heim').on('keydown.heim', function(e) {
            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                var inputs = $('#heimresultateTabelle input');
                var currentIndex = inputs.index(this);
                var nextIndex = e.shiftKey ? currentIndex - 1 : currentIndex + 1;
                var nextInput = inputs.eq(nextIndex);
                if (nextInput.length) nextInput.focus().select();
            }
        });

        $inputs.off('focus.heim').on('focus.heim', function() {
            var $this = $(this);
            if ($this.val() === '0') $this.val('').select();
            else if ($this.val() !== '') $this.select();
        });

        $inputs.off('blur.heim').on('blur.heim', function() {
            if ($(this).val().trim() === '') $(this).val('0');
        });

        $inputs.off('input.heim').on('input.heim', function() {
            var value = $(this).val().replace(/[^0-9]/g, '');
            if (value.length > 2) value = value.substring(0, 2);
            $(this).val(value);
        });
    }

    // ===== Leere Felder mit 0 füllen vor Speichern =====
    function fillEmptyWithZero() {
        $('#heimresultateTabelle tbody tr').each(function() {
            var inputs = $(this).find('input');
            var hasLaterValue = false;
            for (var i = inputs.length - 1; i >= 0; i--) {
                var $input = $(inputs[i]);
                var val = $input.val().trim();
                if (val !== '' && val !== '0') hasLaterValue = true;
                else if (hasLaterValue && val === '') $input.val('0');
            }
        });
    }

    // ===== Mobile → Desktop sync vor Speichern =====
    function syncMobileToDesktop() {
        $('.mobile-passe-input').each(function() {
            var $this = $(this);
            var syncName = $this.data('sync');
            var value = $this.val();
            $('input[name="' + syncName + '"]').not('.mobile-passe-input').val(value);
        });
    }

    // ===== Speichern =====
    $('#heimresultateForm').on('submit', function(e) {
        e.preventDefault();

        var $submitBtn = $(this).find('button[type="submit"]');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        // Bei Mobile: zuerst syncen
        if (isMobile()) syncMobileToDesktop();

        fillEmptyWithZero();

        var selectedYear = $('#yearSelect').val();
        var formData = $(this).serialize() + '&year=' + selectedYear + '&jahr=' + selectedYear;

        $.ajax({
            url: 'heimresultate/save_heimresultate.php',
            type: 'POST',
            data: formData,
            success: function() {
                msvToast('Ergebnisse erfolgreich gespeichert!', 'success');
                setTimeout(function() { loadResultate(selectedYear); }, 1000);
            },
            error: function() {
                msvToast('Fehler beim Speichern der Ergebnisse', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // ===== Löschen =====
    $('#delete-btn').on('click', function() { $('#confirmModal').modal('show'); });

    $('#confirmDeleteButton').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        var selectedYear = $('#yearSelect').val();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');

        $.ajax({
            url: 'heimresultate/delete_heim.php',
            method: 'POST',
            data: { jahr: selectedYear, csrf_token: $('input[name="csrf_token"]').val() },
            success: function() {
                $('#confirmModal').modal('hide');
                msvToast('Alle Resultate erfolgreich gelöscht', 'success');
                setTimeout(function() { loadResultate(selectedYear); }, 500);
            },
            error: function() { msvToast('Fehler beim Löschen', 'error'); },
            complete: function() { $btn.prop('disabled', false).html(originalText); }
        });
    });

    // ===== Rangliste =====
    $('#redirect-btn').on('click', function() { window.location.href = 'heimrang.php'; });

    // ===== Jahreswechsel =====
    $('#yearSelect').on('change', function() { loadResultate($(this).val()); });

    // ===== Global Scroll (nur Desktop) =====
    document.addEventListener('wheel', function(e) {
        if (isMobile()) return;
        var tableContainer = $('.table-responsive')[0];
        if (tableContainer && tableContainer.scrollHeight > tableContainer.clientHeight) {
            tableContainer.scrollTop += e.deltaY;
            e.preventDefault();
        }
    }, { passive: false });

    // ===== Resize =====
    var resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            calculateTableHeight();
            // Bei Wechsel Desktop ↔ Mobile: Cards ggf. neu bauen
            if (isMobile() && $('#mobileCardsList .member-card').length === 0) {
                buildMobileCards();
            }
        }, 150);
    });

    // ===== Hilfsfunktion =====
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ===== Init =====
    initializeYearDropdown();
    loadResultate(new Date().getFullYear());
    setTimeout(calculateTableHeight, 200);
});
</script>

<?php include 'footer.inc.php'; ?>
