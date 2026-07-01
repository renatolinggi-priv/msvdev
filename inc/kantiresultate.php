<?php
// kantiresultate.php – Neuaufbau nach wichtigetermine-Pattern
include 'dbconnect.inc.php';

// Seitenspezifische Styles
$page_specific_css = "
/* Kantiresultate-spezifische Styles */

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

#kantiresultateForm {
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
    table-layout: fixed;
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

.table tbody tr {
    transition: background-color 0.2s ease;
    border-bottom: 1px solid #f1f3f4;
}

.table tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.04);
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

.passe-input {
    width: 55px !important;
    text-align: center !important;
    padding: 0.25rem 0.1rem !important;
    font-size: 0.9rem !important;
    border: 1px solid #dee2e6 !important;
    border-radius: 0.25rem !important;
    transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
}

.passe-input:focus {
    border-color: var(--secondary-color) !important;
    box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25) !important;
    outline: none !important;
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

@media (max-width: 576px) {
    .button-toolbar { flex-direction: column; }
    .button-toolbar .btn { width: 100%; }
}

.spinner-border { color: var(--secondary-color) !important; }

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

    /* Touch-Target-Grössen (form-controls/.btn) zentral in css/msv-styles.css */

    .mobile-card-body .passe-input-mobile {
        min-height: 48px !important;
        font-size: 16px !important;
        padding: 0.5rem !important;
        text-align: center !important;
        font-weight: 500 !important;
    }

    .mobile-card-body .mb-3 {
        margin-bottom: 1rem !important;
    }

    .mobile-card-body .form-label {
        margin-bottom: 0.35rem !important;
        color: #475569 !important;
        font-size: 0.875rem !important;
    }

    .button-toolbar .btn {
        min-height: 48px !important;
        font-size: 0.95rem !important;
    }


}

/* === Gruppen-Trennzeile (mit / ohne Resultate) === */
.table tbody tr.group-header td.group-header-cell {
    background: #eef2f7;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #475569;
    text-align: left;
    padding: 0.45rem 1rem;
    border-top: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}
#kantiresultateTabelle tbody tr.group-header:hover td.group-header-cell,
#kantiresultateTabelle tbody tr.group-header td.group-header-cell {
    background: #eef2f7;
    cursor: default;
}

/* === Bestpasse-Highlighting === */
.best-passe {
    background: #fffbeb !important;
    border-color: #f59e0b !important;
    font-weight: 700 !important;
    color: #92400e !important;
}

/* Summenspalte */
.sum-cell {
    font-weight: 700;
    color: #6366f1;
    text-align: center;
}
.sum-cell.empty { color: #cbd5e1; }

/* Status-Dots */
.status-dot {
    width: 8px; height: 8px; border-radius: 50%;
    display: inline-block; margin-right: 6px;
}
.status-dot.complete { background: #22c55e; }
.status-dot.partial { background: #f59e0b; }
.status-dot.empty { background: #e2e8f0; }

/* Filled Input */
input.small-input.filled {
    background: #f0fdf4;
    border-color: #86efac;
}

/* =========================================
   Erfassen Slide-Panel (Schütze um Schütze)
   Container/Overlay/Header/Body zentral in css/msv-styles.css
   (Breite via panel-width Custom-Property am Panel-Element)
   ========================================= */
.panel-footer {
    padding: .75rem 1.25rem; border-top: 1px solid #e2e8f0;
    background: #f8fafc; flex-shrink: 0;
}

.panel-progress { height: 6px; background: #eef2f7; }
.panel-progress-bar {
    height: 100%; width: 0;
    background: linear-gradient(90deg,#22c55e,#16a34a);
    transition: width .3s ease;
}

.entry-passen-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px,1fr));
    gap: .75rem;
}
.entry-passe-field { display: flex; flex-direction: column; }
.entry-passe-field label {
    font-size: .72rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: .5px; color: #64748b; margin-bottom: .25rem;
}
.entry-passe-field input {
    text-align: center; font-size: 1.35rem; font-weight: 600;
    padding: .6rem .4rem; border: 1.5px solid #dee2e6; border-radius: .5rem;
    -moz-appearance: textfield; transition: border-color .2s, box-shadow .2s;
}
.entry-passe-field input:focus {
    border-color: #4a90d9; box-shadow: 0 0 0 3px rgba(74,144,217,.15); outline: none;
}
.entry-passe-field input.filled { background: #f0fdf4; border-color: #86efac; }
.entry-passe-field.is-best input {
    background: #fffbeb; border-color: #f59e0b; color: #92400e;
}

/* Klickbare Namen-Zelle + ausgewählte Zeile */
#kantiresultateTabelle tbody td:first-child { cursor: pointer; }
#kantiresultateTabelle tbody tr.panel-selected {
    background: rgba(74,144,217,.08) !important;
    box-shadow: inset 3px 0 0 #4a90d9;
}

@media (max-width: 767.98px) {
    .hybrid-edit-panel { width: 100vw; right: -100vw; }
    .panel-overlay { display: none !important; }
    .entry-passe-field input { font-size: 1.25rem; min-height: 52px; }
}

/* =========================================
   Kompaktere Desktop-Tabelle (nicht zu breit)
   ========================================= */
@media (min-width: 768px) {
    /* Karte auf Inhaltsbreite begrenzen statt voll auszudehnen.
       Prefix #resultateContainer erhöht Spezifität, damit diese Breiten
       die !important-Regeln aus css/fixes/resultate-unified.css schlagen. */
    #resultateContainer .results-list-card {
        max-width: 760px;
        /* Dezenter Tabellen-Rahmen INNERHALB der content-background-Karte
           (nur Border, kein Schatten) – analog rank-table-wrapper auf jmresultate. */
        background: #fff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 0.5rem !important;
        box-shadow: none !important;
    }

    /* Passe-Spalten schmaler */
    #resultateContainer #kantiresultateTabelle th:not(:first-child),
    #resultateContainer #kantiresultateTabelle td:not(:first-child) {
        width: 80px !important;
        min-width: 80px !important;
        max-width: 80px !important;
    }
    /* Namens-Spalte */
    #resultateContainer #kantiresultateTabelle th:first-child,
    #resultateContainer #kantiresultateTabelle td:first-child {
        width: 200px !important;
        min-width: 200px !important;
        max-width: 200px !important;
    }
    /* Total-Spalte */
    #resultateContainer #kantiresultateTabelle th:last-child,
    #resultateContainer #kantiresultateTabelle td:last-child {
        width: 84px !important;
        min-width: 84px !important;
        max-width: 84px !important;
    }
    /* Eingabefelder zentriert, etwas grösser als 45px */
    #resultateContainer #kantiresultateTabelle input.small-input {
        width: 56px !important;
        height: 34px !important;
        margin: 0 auto;
    }
    /* Kopfzeile darf nicht umbrechen */
    #resultateContainer #kantiresultateTabelle thead th {
        font-size: 0.72rem;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }
    /* Kompaktere Kopf- und Zeilenhöhe */
    #resultateContainer #kantiresultateTabelle thead th { padding: 0.6rem 0.4rem; }
    #resultateContainer #kantiresultateTabelle tbody td { padding: 0.35rem 0.4rem; }

    /* ---- Natürlicher Seiten-Scroll statt internem Tabellen-Scroll ----
       Wrapper und Karte wachsen mit dem Inhalt; die ganze Seite scrollt.
       resultate-unified.css erzwingt auf .table-responsive min-height:300px,
       max-height:calc(100vh-350px) und overflow (alle !important) -> das
       verursachte Leerraum bzw. eine Karte, die nicht so hoch wie die Tabelle
       ist. Hier alles aufgehoben: die Karte ist exakt so gross wie die Tabelle. */
    /* Karte wie auf jmresultate: die aeussere weisse Karte (main-content-wrapper +
       content-background) BEHALTEN. Nur Hoehe/Scroll loesen, damit die Karte mit
       dem Inhalt waechst (kein viewport-fixes Scrollen, kein Leerraum darunter). */
    .main-content-wrapper {
        height: auto !important;
        max-height: none !important;
        overflow: visible !important;
        margin-bottom: 1.5rem !important;
    }
    .content-background {
        overflow: visible !important;
    }
    /* overflow:visible über die ganze Kette, sonst bricht position:sticky
       (ein overflow:hidden-Vorfahre würde die Kopfzeile mitscrollen) */
    #resultateContainer .results-list-card { overflow: visible !important; }
    /* Innerer Wrapper soll KEINE zweite Karte sein (Rahmen/Schatten aus
       resultate-unified.css aufheben) -> sonst doppelte Linie. */
    #resultateContainer .table-wrapper {
        overflow: visible !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
    }
    #resultateContainer .desktop-table-container { overflow: visible !important; }
    #resultateContainer .table-responsive {
        min-height: 0 !important;
        max-height: none !important;
        overflow: visible !important;
    }
    /* Sticky-Kopfzeile beim Seiten-Scroll unter der fixierten Navbar halten */
    #resultateContainer #kantiresultateTabelle thead th {
        top: var(--app-header) !important;
    }
}
";

include 'header.inc.php';
?>
<style><?= $page_specific_css ?></style>
<!-- Select2 (für Schnellerfassung-Schützensuche) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
.select2-container { z-index: 1065; }
#entryMemberSelect + .select2-container { width: 100% !important; }
.select2-container--bootstrap-5 .select2-selection { min-height: calc(1.5em + 0.75rem + 2px); }
</style>
<?php
// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-8 col-lg-11 col-12 ps-0">
            <div class="main-content-wrapper">
                <!-- Header -->
                <?php $page_title = 'Kantonalstich Resultaterfassung'; include 'partials/page_header.inc.php'; ?>

                <div class="content-background">
                    <form id="kantiresultateForm">
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
                        $ac_id = 'kantiresultateActions';
                        ob_start();
                        ?>
                                    <div class="row g-2">
                                        <div class="col-6 d-none d-md-block">
                                            <button type="button" class="btn btn-outline-primary btn-sm w-100" id="startEntryBtn">
                                                <i class="bi bi-pencil-square me-1"></i>Schnellerfassung
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                                <i class="bi bi-save me-1"></i>Speichern
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button id="redirect-btn" type="button" class="btn btn-outline-info btn-sm w-100">
                                                <i class="bi bi-trophy me-1"></i>Rangliste
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="publishChangelogBtn">
                                                <i class="bi bi-megaphone me-1"></i>Publizieren
                                            </button>
                                        </div>
                                    </div>
                                    <div class="border-top mt-2 pt-2 text-end">
                                        <button id="delete-btn" type="button" class="btn btn-link btn-sm text-danger text-decoration-none p-0">
                                            <i class="bi bi-trash me-1"></i>Alle Resultate löschen
                                        </button>
                                    </div>
                        <?php
                        $ac_body = ob_get_clean();
                        include 'partials/action_card.inc.php';
                        ?>
                        </div><!-- Ende flex-row Jahr+Aktionen -->

                        <!-- Tabelle Container -->
                        <div id="resultateContainer">
                            <div class="results-list-card">
                                <div class="table-wrapper">
                                    <!-- Desktop: Tabelle -->
                                    <div class="desktop-table-container">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0" id="kantiresultateTabelle">
                                                <thead>
                                                    <tr>
                                                        <th scope="col" style="min-width: 180px; width: 200px;">
                                                            <i class="bi bi-person me-1"></i>Mitglied
                                                        </th>
                                                        <th scope="col" class="text-center" style="width: 80px;">Passe 1</th>
                                                        <th scope="col" class="text-center" style="width: 80px;">Passe 2</th>
                                                        <th scope="col" class="text-center" style="width: 80px;">Passe 3</th>
                                                        <th scope="col" class="text-center" style="width: 80px;">Passe 4</th>
                                                        <th scope="col" class="text-center" style="width: 80px;">Passe 5</th>
                                                        <th scope="col" class="text-center" style="width: 65px; border-left: 2px solid #e2e8f0;">Total</th>
                
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                    <td colspan="7" class="text-center py-4">
                                                    <div class="spinner-border spinner-border-sm me-2"></div>
                                                    Lade Resultate...
                                                    </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Mobile: Cards -->
                                    <div class="mobile-cards-container" id="mobileCardsKanti">
                                        <div class="mobile-search">
                                            <div class="position-relative">
                                                <i class="bi bi-search search-icon"></i>
                                                <input type="text" class="form-control" placeholder="Mitglied suchen..."
                                                       oninput="filterMobileKanti(this)">
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

<!-- Erfassen Slide-Panel -->
<div class="panel-overlay" id="entryOverlay"></div>
<div class="hybrid-edit-panel" id="entryPanel" style="--panel-width: 540px;">
    <div class="panel-header">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="entryPrev" data-tooltip="Vorheriger">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div>
                <h6 class="mb-0"><i class="bi bi-person me-2"></i><span id="entryName">Erfassen</span></h6>
                <small class="text-muted" id="entrySubtitle"></small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="entryNext" data-tooltip="Nächster">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="entryClose">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="panel-progress"><div class="panel-progress-bar" id="entryProgressBar"></div></div>
    <div class="panel-body">
        <div class="mb-3">
            <select id="entryMemberSelect" style="width:100%"></select>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-muted small" id="entryProgressText"></span>
            <span class="badge bg-light text-dark border" id="entryTotalBadge"><i class="bi bi-calculator me-1"></i>Total: 0</span>
        </div>
        <div class="entry-passen-grid" id="entryPassenGrid"></div>
    </div>
    <div class="panel-footer">
        <div class="d-flex gap-2 w-100">
            <button type="button" class="btn btn-outline-primary flex-fill" id="entrySaveBtn">
                <i class="bi bi-save me-1"></i>Speichern
            </button>
            <button type="button" class="btn btn-outline-primary flex-fill" id="entrySaveNextBtn">
                Speichern &amp; Weiter <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    function calculateTableHeight() {
        const tableResp = $('.table-responsive');
        if (!tableResp.length) return;
        const tableTop = tableResp.offset().top;
        const availableHeight = window.innerHeight - tableTop - 30;
        tableResp.css({ 'max-height': Math.max(300, availableHeight) + 'px', 'overflow-y': 'auto' });
    }

    function initializeYearDropdown() {
        const $yearSelect = $('#yearSelect').empty();
        const currentYear = new Date().getFullYear();
        for (let year = currentYear; year >= currentYear - 3; year--) {
            const $option = $('<option></option>').val(year).text(year);
            if (year === currentYear) $option.prop('selected', true);
            $yearSelect.append($option);
        }
    }

    // Falls die PHP-Datei noch keine Total/Beste-Zellen liefert, fügt JS sie hinzu
    function ensureSumBestCells() {
        $('#kantiresultateTabelle tbody tr').each(function() {
            var $row = $(this);
            if ($row.find('.sum-cell').length === 0 && $row.find('input.small-input').length > 0) {
                $row.append(
                    '<td style="border-left:2px solid #e2e8f0;"><span class="sum-cell empty">&ndash;</span></td>'
                );
            }
            // Status-Dot ergänzen falls fehlend
            var $nameCell = $row.find('td:first');
            if ($nameCell.length && $nameCell.find('.status-dot').length === 0 && $row.find('input.small-input').length > 0) {
                $nameCell.prepend('<span class="status-dot empty"></span>');
            }
        });
    }

    function loadResultate(year) {
        var $tbody = $('#kantiresultateTabelle tbody');
        $tbody.html(
            '<tr><td colspan="7" class="text-center py-4">' +
            '<div class="spinner-border spinner-border-sm me-2"></div>' +
            'Lade Resultate...</td></tr>'
        );

        $.ajax({
            url: 'kantiresultate/load_kantiresultate_form.php',
            method: 'GET',
            cache: false,
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                ensureSumBestCells();
                bindInputs();
                updateKantiRowStats();
                EntryPanel.buildIndex();
                setTimeout(calculateTableHeight, 100);
                buildMobileKantiCards();
            },
            error: function() {
                $tbody.html(
                    '<tr><td colspan="7" class="text-center text-danger py-4">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Fehler beim Laden der Daten</td></tr>'
                );
                msvToast('Fehler beim Laden der Resultate', 'error');
            }
        });
    }

    function bindInputs() {
        var $inputs = $('#kantiresultateTabelle input');

        $inputs.off('keydown.kanti').on('keydown.kanti', function(e) {
            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                var inputs = $('#kantiresultateTabelle input');
                var currentIndex = inputs.index(this);
                var nextIndex = e.shiftKey ? currentIndex - 1 : currentIndex + 1;
                var nextInput = inputs.eq(nextIndex);
                if (nextInput.length) nextInput.focus().select();
            }
        });

        $inputs.off('focus.kanti').on('focus.kanti', function() {
            var $this = $(this);
            if ($this.val() === '0') $this.val('').select();
            else if ($this.val() !== '') $this.select();
        });

        $inputs.off('blur.kanti').on('blur.kanti', function() {
            if ($(this).val().trim() === '') $(this).val('0');
        });

        $inputs.off('input.kanti').on('input.kanti', function() {
            var value = $(this).val().replace(/[^0-9]/g, '');
            if (value.length > 2) value = value.substring(0, 2);
            $(this).val(value);
        });
    }

    function fillEmptyWithZero() {
        $('#kantiresultateTabelle tbody tr').each(function() {
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

    // Speichern
    $('#kantiresultateForm').on('submit', function(e) {
        e.preventDefault();
        var $submitBtn = $(this).find('button[type="submit"]');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        fillEmptyWithZero();
        var selectedYear = $('#yearSelect').val();
        var formData = $(this).serialize() + '&year=' + selectedYear + '&jahr=' + selectedYear;

        $.ajax({
            url: 'kantiresultate/save_kantiresultate.php',
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

    // Löschen
    $('#delete-btn').on('click', function() {
        var selectedYear = $('#yearSelect').val();
        msvConfirmDelete('alle Resultate des Jahres ' + selectedYear).then(function(res) {
            if (!res.isConfirmed) return;
            $.ajax({
                url: 'kantiresultate/delete_kanti.php',
                method: 'POST',
                data: { jahr: selectedYear, csrf_token: $('input[name="csrf_token"]').val() },
                success: function() {
                    msvToast('Alle Resultate erfolgreich gelöscht', 'success');
                    setTimeout(function() { loadResultate(selectedYear); }, 500);
                },
                error: function() { msvToast('Fehler beim Löschen', 'error'); }
            });
        });
    });

    // Rangliste
    $('#redirect-btn').on('click', function() { window.location.href = 'kantirang.php'; });

    // Jahreswechsel
    $('#yearSelect').on('change', function() { loadResultate($(this).val()); });

    // Global Scroll
    document.addEventListener('wheel', function(e) {
        if ($('#entryPanel').hasClass('open')) return;
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

    /**
     * Berechnet pro Zeile: Summe, Bestpasse, Status-Dot, Filled-Klassen
     */
    function updateKantiRowStats() {
        $('#kantiresultateTabelle tbody tr').each(function() {
            const $inputs = $(this).find('input.small-input');
            let sum = 0, best = -1, bestIdx = -1, filled = 0, total = $inputs.length;

            $inputs.each(function(i) {
                $(this).removeClass('best-passe filled');
                const val = parseInt(this.value) || 0;
                if (this.value.trim() !== '' && this.value !== '0') {
                    filled++;
                    $(this).addClass('filled');
                }
                sum += val;
                if (val > best) { best = val; bestIdx = i; }
            });

            // Bestpasse hervorheben
            if (bestIdx >= 0 && best > 0) {
                $inputs.eq(bestIdx).addClass('best-passe');
            }

            // Summe-Zelle aktualisieren
            const $sumCell = $(this).find('.sum-cell');
            if ($sumCell.length) {
                $sumCell.text(sum > 0 ? sum : '\u2013').toggleClass('empty', sum === 0);
            }

            // Status-Dot
            const $dot = $(this).find('.status-dot');
            if ($dot.length) {
                $dot.removeClass('complete partial empty');
                if (filled === total && filled > 0) $dot.addClass('complete');
                else if (filled > 0) $dot.addClass('partial');
                else $dot.addClass('empty');
            }
        });

    }

    // Input-Listener für Echtzeit-Updates
    $(document).on('input', '#kantiresultateTabelle input.small-input', function() {
        updateKantiRowStats();
    });

    // Mobile Cards für Kanti-Resultate generieren
    function buildMobileKantiCards() {
        const isMobile = window.matchMedia('(max-width: 767.98px)');
        if (!isMobile.matches) return;

        const table = document.getElementById('kantiresultateTabelle');
        const container = document.querySelector('#mobileCardsKanti .mobile-cards-scroll');
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
            if (cells.length === 0) return;

            // Erste Zelle: Mitgliedername
            const memberName = cells[0]?.textContent?.trim() || 'Unbekannt';

            // Passe-Inputs extrahieren (Spalten 1-5)
            const inputs = Array.from(row.querySelectorAll('input'));
            if (inputs.length < 5) return;

            let fieldsHtml = '';
            const passeLabels = ['Passe 1', 'Passe 2', 'Passe 3', 'Passe 4', 'Passe 5'];

            // Berechne Summe + Bestpasse für Summary
            let sum = 0, best = -1, bestIdx = -1, hasAny = false;
            inputs.forEach((input, i) => {
                if (i >= 5) return;
                const val = parseInt(input.value) || 0;
                if (input.value.trim() !== '' && input.value !== '0') {
                    hasAny = true;
                }
                sum += val;
                if (val > best) { best = val; bestIdx = i; }
            });

            const summaryHtml = hasAny
                ? `<small class="text-muted">Total: ${sum}</small>`
                : '';

            const borderStyle = hasAny ? 'border-left: 3px solid #22c55e;' : '';

            inputs.forEach((input, i) => {
                if (i >= 5) return;
                const label = passeLabels[i];
                const inputName = input.name || '';
                const inputValue = input.value || '';
                const isBest = (i === bestIdx && best > 0);

                fieldsHtml += `
                    <div class="mb-3">
                        <label class="form-label fw-bold small">${label}${isBest ? ' &#127942;' : ''}</label>
                        <input type="number"
                               class="form-control passe-input-mobile${isBest ? ' best' : ''}"
                               data-name="${inputName}"
                               value="${inputValue}"
                               inputmode="numeric"
                               pattern="[0-9]*"
                               maxlength="2"
                               style="${isBest ? 'background:#fffbeb; border-color:#f59e0b;' : ''}">
                    </div>`;
            });

            html += `
            <div class="mobile-card" data-index="${idx}" style="${borderStyle}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                        <div class="fw-bold">${memberName}</div>
                        ${summaryHtml}
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="mobile-card-body">
                    ${fieldsHtml}
                </div>
            </div>`;
        });

        container.innerHTML = html;

        // Event-Listener für Inputs: Sync zu Desktop-Tabelle
        container.querySelectorAll('input[data-name]').forEach(input => {
            input.addEventListener('input', function() {
                const inputName = this.getAttribute('data-name');
                const desktopInput = table.querySelector(`input[name="${inputName}"]`);
                if (desktopInput) {
                    desktopInput.value = this.value;
                    // Trigger input event für Validierung
                    $(desktopInput).trigger('input');
                }
            });

            // Keyboard navigation (Enter/Tab)
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === 'Tab') {
                    e.preventDefault();
                    const allInputs = Array.from(container.querySelectorAll('input[data-name]'));
                    const currentIndex = allInputs.indexOf(this);
                    const nextIndex = e.shiftKey ? currentIndex - 1 : currentIndex + 1;
                    if (allInputs[nextIndex]) {
                        allInputs[nextIndex].focus();
                        allInputs[nextIndex].select();
                    }
                }
            });

            // Focus: Select on focus
            input.addEventListener('focus', function() {
                if (this.value === '0') {
                    this.value = '';
                }
                this.select();
            });

            // Blur: Set to 0 if empty
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.value = '0';
                    const inputName = this.getAttribute('data-name');
                    const desktopInput = table.querySelector(`input[name="${inputName}"]`);
                    if (desktopInput) desktopInput.value = '0';
                }
            });
        });
    }

    // Mobile Search Filter (global für inline oninput)
    window.filterMobileKanti = function(searchInput) {
        const query = searchInput.value.toLowerCase();
        const cards = document.querySelectorAll('#mobileCardsKanti .mobile-card');

        let visibleCount = 0;
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const isVisible = text.includes(query);
            card.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        const container = document.querySelector('#mobileCardsKanti .mobile-cards-scroll');
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

    // Resize-Listener für Mobile Cards
    let wasDesktop = window.matchMedia('(min-width: 768px)').matches;
    window.addEventListener('resize', function() {
        const isNowDesktop = window.matchMedia('(min-width: 768px)').matches;
        if (wasDesktop && !isNowDesktop) {
            buildMobileKantiCards();
        }
        wasDesktop = isNowDesktop;
    });

    // Veröffentlichen
    $('#publishChangelogBtn').on('click', async function() {
        const r = await msvConfirm('Änderung veröffentlichen?', 'Ein Eintrag wird auf der Website angezeigt.', 'Veröffentlichen');
        if (!r.isConfirmed) return;
        var selectedYear = $('#yearSelect').val();
        $.post('changelog_publish.php', {
            kategorie: 'resultate',
            tabelle: 'kantiresultate',
            jahr: selectedYear,
            beschreibung: 'Kantiresultate ' + selectedYear + ' aktualisiert',
            csrf_token: $('input[name="csrf_token"]').val()
        }).done(function(res) {
            if (res.success) msvToast(res.message, 'success');
            else msvToast(res.message || 'Fehler', 'error');
        }).fail(function() {
            msvToast('Veröffentlichung fehlgeschlagen', 'error');
        });
    });

    // =========================================
    //  Erfassen-Panel – Schütze um Schütze
    // =========================================
    const EntryPanel = {
        rows: [],
        idx: -1,
        _silent: false,
        maxLen: 2,      // Kanti: 2-stellig
        clampMax: null, // kein 100er-Clamp
        saveUrl: 'kantiresultate/save_kantiresultate.php',

        buildIndex() {
            this.rows = [];
            const self = this;
            $('#kantiresultateTabelle tbody tr').each(function() {
                const $tr = $(this);
                const $inputs = $tr.find('input.small-input');
                if (!$inputs.length) return;
                const nm = $inputs.first().attr('name') || '';
                const m = nm.match(/passe\[(\d+)\]/);
                if (!m) return;
                self.rows.push({
                    id: m[1],
                    name: $tr.find('td:first').text().trim(),
                    $tr: $tr,
                    $inputs: $inputs
                });
            });
        },

        isComplete(row) {
            let all = true;
            row.$inputs.each(function() {
                const v = $(this).val();
                if (v === '' || v === '0') all = false;
            });
            return all;
        },

        firstIncomplete() {
            for (let i = 0; i < this.rows.length; i++) {
                if (!this.isComplete(this.rows[i])) return i;
            }
            return 0;
        },

        nextIncomplete(from) {
            for (let i = from + 1; i < this.rows.length; i++) {
                if (!this.isComplete(this.rows[i])) return i;
            }
            return -1;
        },

        open(idx) {
            if (idx < 0 || idx >= this.rows.length) return;
            this.idx = idx;
            const row = this.rows[idx];

            const $grid = $('#entryPassenGrid').empty();
            row.$inputs.each(function(i) {
                const v = $(this).val() || '';
                const $field = $(
                    '<div class="entry-passe-field" data-pi="' + i + '">' +
                    '<label>Passe ' + (i + 1) + '</label>' +
                    '<input type="text" inputmode="numeric" autocomplete="off" maxlength="' + EntryPanel.maxLen + '">' +
                    '</div>'
                );
                $field.find('input').val(v);
                $grid.append($field);
            });

            $('#entryName').text(row.name);
            $('#entrySubtitle').text('Schütze ' + (idx + 1) + ' / ' + this.rows.length);
            this.rows.forEach(r => r.$tr.removeClass('panel-selected'));
            row.$tr.addClass('panel-selected');

            this.refreshFields();
            this.updateProgress();
            this._silent = true;
            this.populateSelect();
            this._silent = false;

            $('#entryOverlay').addClass('show');
            $('#entryPanel').addClass('open');
            setTimeout(function() { $('#entryPassenGrid input').first().focus().select(); }, 150);
        },

        // Schützen-Suche (Select2) befüllen + aktuellen markieren
        populateSelect() {
            const $sel = $('#entryMemberSelect');
            if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
            $sel.empty();
            const self = this;
            this.rows.forEach(function(r, i) {
                const done = self.isComplete(r);
                $sel.append(new Option((done ? '✓ ' : '') + r.name, i, false, i === self.idx));
            });
            $sel.select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#entryPanel'),
                width: '100%',
                placeholder: 'Schütze suchen…'
            }).off('select2:open.entry').on('select2:open.entry', function() {
                setTimeout(function() {
                    const f = document.querySelector('.select2-container--open .select2-search__field');
                    if (f) f.focus();
                }, 0);
            });
        },

        close() {
            $('#entryPanel').removeClass('open');
            $('#entryOverlay').removeClass('show');
            this.rows.forEach(r => r.$tr.removeClass('panel-selected'));
            this.idx = -1;
        },

        navigate(dir) {
            const n = this.idx + dir;
            if (n >= 0 && n < this.rows.length) this.open(n);
        },

        // Panel-Feld → Raster-Input synchronisieren
        syncField(pi, value) {
            if (this.idx < 0) return;
            const row = this.rows[this.idx];
            row.$inputs.eq(pi).val(value).trigger('input');
        },

        // Filled/Best-Hervorhebung + Total im Panel
        refreshFields() {
            let sum = 0, best = -1, bestIdx = -1;
            const $fields = $('#entryPassenGrid .entry-passe-field');
            $fields.each(function(i) {
                const v = parseInt($(this).find('input').val(), 10) || 0;
                const raw = $(this).find('input').val().trim();
                $(this).removeClass('is-best');
                $(this).find('input').toggleClass('filled', raw !== '' && raw !== '0');
                sum += v;
                if (v > best) { best = v; bestIdx = i; }
            });
            if (bestIdx >= 0 && best > 0) $fields.eq(bestIdx).addClass('is-best');
            $('#entryTotalBadge').html('<i class="bi bi-calculator me-1"></i>Total: ' + sum);
        },

        updateProgress() {
            const total = this.rows.length;
            let done = 0;
            this.rows.forEach(r => { if (this.isComplete(r)) done++; });
            const pct = total ? Math.round(done / total * 100) : 0;
            $('#entryProgressBar').css('width', pct + '%');
            $('#entryProgressText').text(done + ' / ' + total + ' vollständig erfasst');
        },

        // Leere Felder nach einem späteren Wert mit 0 füllen (wie Raster-Save)
        collectPayload(row) {
            const vals = [];
            row.$inputs.each(function() { vals.push($(this).val().trim()); });
            let hasLater = false;
            for (let i = vals.length - 1; i >= 0; i--) {
                if (vals[i] !== '' && vals[i] !== '0') hasLater = true;
                else if (hasLater && vals[i] === '') vals[i] = '0';
            }
            row.$inputs.each(function(i) {
                if ($(this).val().trim() === '' && vals[i] === '0') $(this).val('0').trigger('input');
            });
            const passe = {};
            for (let i = 0; i < vals.length; i++) passe[i + 1] = vals[i];
            const obj = {};
            obj[row.id] = passe;
            return obj;
        },

        save(onDone) {
            if (this.idx < 0) return;
            const row = this.rows[this.idx];
            const $btns = $('#entrySaveBtn, #entrySaveNextBtn').prop('disabled', true);
            $.ajax({
                url: this.saveUrl,
                type: 'POST',
                data: {
                    csrf_token: $('input[name="csrf_token"]').first().val(),
                    jahr: $('#yearSelect').val(),
                    year: $('#yearSelect').val(),
                    passe: this.collectPayload(row)
                },
                success: function() {
                    EntryPanel.updateProgress();
                    if (typeof onDone === 'function') onDone();
                    else msvToast('Gespeichert', 'success');
                },
                error: function() { msvToast('Fehler beim Speichern', 'error'); },
                complete: function() { $btns.prop('disabled', false); }
            });
        },

        saveAndNext() {
            const fromIdx = this.idx;
            this.save(function() {
                const n = EntryPanel.nextIncomplete(fromIdx);
                if (n >= 0) {
                    EntryPanel.open(n);
                } else {
                    msvToast('Alle Schützen erfasst', 'success');
                    EntryPanel.close();
                }
            });
        }
    };

    // Panel-Feld-Eingabe (delegiert): validieren + syncen
    $(document).on('input', '#entryPassenGrid input', function() {
        let value = $(this).val().replace(/[^0-9]/g, '');
        if (value.length > EntryPanel.maxLen) value = value.substring(0, EntryPanel.maxLen);
        if (EntryPanel.clampMax !== null && value !== '' && parseInt(value, 10) > EntryPanel.clampMax) {
            value = String(EntryPanel.clampMax);
        }
        $(this).val(value);
        const pi = parseInt($(this).closest('.entry-passe-field').data('pi'), 10);
        EntryPanel.syncField(pi, value);
        EntryPanel.refreshFields();
    });

    $(document).on('focus', '#entryPassenGrid input', function() {
        if ($(this).val() === '0') $(this).val('');
        $(this).select();
    });

    // Enter: nächstes Feld, letztes Feld → Speichern & Weiter
    $(document).on('keydown', '#entryPassenGrid input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const $inputs = $('#entryPassenGrid input');
            const i = $inputs.index(this);
            if (i < $inputs.length - 1) $inputs.eq(i + 1).focus().select();
            else EntryPanel.saveAndNext();
        }
    });

    $('#startEntryBtn').on('click', function() {
        EntryPanel.buildIndex();
        if (!EntryPanel.rows.length) { msvToast('Keine Mitglieder geladen', 'error'); return; }
        EntryPanel.open(EntryPanel.firstIncomplete());
    });

    // Klick auf Namen-Zelle öffnet Panel bei diesem Schützen
    $(document).on('click', '#kantiresultateTabelle tbody td:first-child', function() {
        const $tr = $(this).closest('tr');
        const i = EntryPanel.rows.findIndex(r => r.$tr.is($tr));
        if (i >= 0) EntryPanel.open(i);
    });

    // Select2-Auswahl springt zum Schützen
    $(document).on('change', '#entryMemberSelect', function() {
        if (EntryPanel._silent) return;
        const i = parseInt($(this).val(), 10);
        if (!isNaN(i) && i !== EntryPanel.idx) EntryPanel.open(i);
    });

    $('#entryPrev').on('click', function() { EntryPanel.navigate(-1); });
    $('#entryNext').on('click', function() { EntryPanel.navigate(1); });
    $('#entryClose, #entryOverlay').on('click', function() { EntryPanel.close(); });
    $('#entrySaveBtn').on('click', function() { EntryPanel.save(); });
    $('#entrySaveNextBtn').on('click', function() { EntryPanel.saveAndNext(); });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#entryPanel').hasClass('open') && !$('.select2-container--open').length) EntryPanel.close();
    });

    // Init
    initializeYearDropdown();
    loadResultate(new Date().getFullYear());
    setTimeout(calculateTableHeight, 200);
});
</script>

<?php include 'footer.inc.php'; ?>
