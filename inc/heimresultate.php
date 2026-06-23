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

/* === Summe & Status Features === */
.sum-cell {
    font-weight: 700;
    color: #6366f1;
    text-align: center;
}
.sum-cell.empty { color: #cbd5e1; }

.status-dot {
    width: 8px; height: 8px; border-radius: 50%;
    display: inline-block; margin-right: 6px;
}
.status-dot.complete { background: #22c55e; }
.status-dot.partial { background: #f59e0b; }
.status-dot.empty { background: #e2e8f0; }

input.small-input.filled {
    background: #f0fdf4;
    border-color: #86efac;
}

/* =========================================
   Erfassen Slide-Panel (Schütze um Schütze)
   ========================================= */
.hybrid-edit-panel {
    position: fixed; top: 0; right: -560px; width: 540px; height: 100vh;
    background: #fff; box-shadow: -8px 0 30px rgba(0,0,0,.12);
    z-index: 1060; transition: right .3s cubic-bezier(.4,0,.2,1);
    display: flex; flex-direction: column;
}
.hybrid-edit-panel.open { right: 0; }

.panel-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.3);
    z-index: 1055; opacity: 0; visibility: hidden; transition: all .3s;
}
.panel-overlay.show { opacity: 1; visibility: visible; }

.panel-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0;
    background: #f8fafc; flex-shrink: 0;
}
.panel-body { padding: 1.25rem; overflow-y: auto; flex: 1; }
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
    grid-template-columns: repeat(auto-fit, minmax(110px,1fr));
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

/* Klickbare Namen-Zelle + ausgewählte Zeile */
#heimresultateTabelle tbody td:first-child { cursor: pointer; }
#heimresultateTabelle tbody tr.panel-selected {
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
       Prefix #desktopTableContainer erhöht Spezifität, damit diese Breiten
       die !important-Regeln aus css/fixes/resultate-unified.css schlagen. */
    #desktopTableContainer .results-list-card { max-width: 900px; }

    /* Passe-Spalten schmaler */
    #desktopTableContainer #heimresultateTabelle th:not(:first-child),
    #desktopTableContainer #heimresultateTabelle td:not(:first-child) {
        width: 70px !important;
        min-width: 70px !important;
        max-width: 70px !important;
    }
    /* Kopfzeile darf nicht umbrechen (PASSE 8 etc.) */
    #desktopTableContainer #heimresultateTabelle thead th {
        font-size: 0.72rem;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }
    /* Namens-Spalte */
    #desktopTableContainer #heimresultateTabelle th:first-child,
    #desktopTableContainer #heimresultateTabelle td:first-child {
        width: 200px !important;
        min-width: 200px !important;
        max-width: 200px !important;
    }
    /* Total-Spalte */
    #desktopTableContainer #heimresultateTabelle th:last-child,
    #desktopTableContainer #heimresultateTabelle td:last-child {
        width: 80px !important;
        min-width: 80px !important;
        max-width: 80px !important;
    }
    /* Eingabefelder zentriert, etwas grösser als 45px */
    #desktopTableContainer #heimresultateTabelle input.small-input {
        width: 52px !important;
        height: 34px !important;
        margin: 0 auto;
    }
    /* Kompaktere Kopf- und Zeilenhöhe */
    #desktopTableContainer #heimresultateTabelle thead th { padding: 0.6rem 0.4rem; }
    #desktopTableContainer #heimresultateTabelle tbody td { padding: 0.35rem 0.4rem; }

    /* ---- Natürlicher Seiten-Scroll statt internem Tabellen-Scroll ----
       Wrapper und Karte wachsen mit dem Inhalt; die ganze Seite scrollt.
       resultate-unified.css erzwingt auf .table-responsive min-height:300px,
       max-height:calc(100vh-350px) und overflow (alle !important) -> das
       verursachte Leerraum bzw. eine Karte, die nicht so hoch wie die Tabelle
       ist. Hier alles aufgehoben: die Karte ist exakt so gross wie die Tabelle. */
    /* Äussere weisse Rahmen (main-content-wrapper + content-background) auf
       Desktop entfernen -> nur die results-list-card umschliesst die Tabelle,
       kein weisser Rest darunter. Seite scrollt natürlich. */
    .main-content-wrapper {
        height: auto !important;
        max-height: none !important;
        overflow: visible !important;
        padding: 0 !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        margin-bottom: 1.5rem !important;
    }
    .content-background {
        overflow: visible !important;
        padding: 0 !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
    }
    /* overflow:visible über die ganze Kette, sonst bricht position:sticky
       (ein overflow:hidden-Vorfahre würde die Kopfzeile mitscrollen) */
    #desktopTableContainer .results-list-card { overflow: visible !important; }
    #desktopTableContainer .table-wrapper { overflow: visible !important; }
    #desktopTableContainer .table-responsive {
        min-height: 0 !important;
        max-height: none !important;
        overflow: visible !important;
    }
    /* Sticky-Kopfzeile beim Seiten-Scroll unter der fixierten Navbar halten */
    #desktopTableContainer #heimresultateTabelle thead th {
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
                                        <div class="col-6 d-none d-md-block">
                                            <button type="button" class="btn btn-primary btn-sm w-100" id="startEntryBtn">
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
                                </div>
                            </div>
                        </div>

                        </div><!-- Ende flex-row Jahr+Aktionen -->

                        <!-- ====== DESKTOP: Tabelle ====== -->
                        <div id="desktopTableContainer">
                            <div class="results-list-card">
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
                                                    <th scope="col" class="text-center" style="width: 65px; border-left: 2px solid #e2e8f0;">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td colspan="10" class="text-center py-4">
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

<!-- Erfassen Slide-Panel -->
<div class="panel-overlay" id="entryOverlay"></div>
<div class="hybrid-edit-panel" id="entryPanel">
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
            <button type="button" class="btn btn-outline-success flex-fill" id="entrySaveBtn">
                <i class="bi bi-save me-1"></i>Speichern
            </button>
            <button type="button" class="btn btn-success flex-fill" id="entrySaveNextBtn">
                Speichern &amp; Weiter <i class="bi bi-arrow-right ms-1"></i>
            </button>
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
            '<tr><td colspan="10" class="text-center py-4">' +
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
            cache: false,
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                bindDesktopInputs();
                updateHeimRowStats();
                EntryPanel.buildIndex();
                buildMobileCards();
                setTimeout(calculateTableHeight, 100);
            },
            error: function() {
                $tbody.html(
                    '<tr><td colspan="10" class="text-center text-danger py-4">' +
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
    //  SUMME, STATUS-DOTS, FORTSCHRITT
    // ==========================================================

    function updateHeimRowStats() {
        $('#heimresultateTabelle tbody tr').each(function() {
            const $inputs = $(this).find('input.small-input');
            let sum = 0, filled = 0, total = $inputs.length;

            $inputs.each(function() {
                $(this).removeClass('filled');
                const val = parseInt(this.value) || 0;
                if (this.value.trim() !== '' && this.value !== '0') {
                    filled++;
                    $(this).addClass('filled');
                }
                sum += val;
            });

            const $sumCell = $(this).find('.sum-cell');
            if ($sumCell.length) {
                $sumCell.text(sum > 0 ? sum : '\u2013').toggleClass('empty', sum === 0);
            }

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
    $(document).on('input', '#heimresultateTabelle input.small-input', function() {
        updateHeimRowStats();
    });

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

            // Prüfe ob Werte vorhanden + Summe berechnen
            var hasValues = false;
            var summaryParts = [];
            var totalSum = 0;
            $inputs.each(function(idx) {
                var val = $(this).val();
                if (val && val !== '' && val !== '0') {
                    hasValues = true;
                    summaryParts.push('P' + (idx + 1) + ':' + val);
                    totalSum += parseInt(val) || 0;
                }
            });
            if (hasValues) {
                membersWithValues++;
                summaryParts.push('\u03A3 ' + totalSum);
            }

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
                cardHtml += '    maxlength="3"';
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
            if (value.length > 3) value = value.substring(0, 3);
            if (value !== '' && parseInt(value, 10) > 100) value = '100';
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
        var totalSum = 0;
        $card.find('.mobile-passe-input').each(function(idx) {
            var val = $(this).val();
            if (val && val !== '' && val !== '0') {
                hasValues = true;
                parts.push('P' + (idx + 1) + ':' + val);
                totalSum += parseInt(val) || 0;
            }
        });
        if (hasValues) parts.push('\u03A3 ' + totalSum);

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
            if (value.length > 3) value = value.substring(0, 3);
            if (value !== '' && parseInt(value, 10) > 100) value = '100';
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
        if ($('#entryPanel').hasClass('open')) return;
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

    // ===== Veröffentlichen =====
    $('#publishChangelogBtn').on('click', async function() {
        const r = await msvConfirm('Änderung veröffentlichen?', 'Ein Eintrag wird auf der Website angezeigt.', 'Veröffentlichen');
        if (!r.isConfirmed) return;
        var selectedYear = $('#yearSelect').val();
        $.post('changelog_publish.php', {
            kategorie: 'resultate',
            tabelle: 'heimresultate',
            jahr: selectedYear,
            beschreibung: 'Heimresultate ' + selectedYear + ' aktualisiert',
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
        maxLen: 3,       // Heim: 3-stellig
        clampMax: 100,   // max 100
        saveUrl: 'heimresultate/save_heimresultate.php',

        buildIndex() {
            this.rows = [];
            const self = this;
            $('#heimresultateTabelle tbody tr').each(function() {
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

        syncField(pi, value) {
            if (this.idx < 0) return;
            const row = this.rows[this.idx];
            row.$inputs.eq(pi).val(value).trigger('input');
        },

        refreshFields() {
            let sum = 0;
            $('#entryPassenGrid .entry-passe-field').each(function() {
                const v = parseInt($(this).find('input').val(), 10) || 0;
                const raw = $(this).find('input').val().trim();
                $(this).find('input').toggleClass('filled', raw !== '' && raw !== '0');
                sum += v;
            });
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
    $(document).on('click', '#heimresultateTabelle tbody td:first-child', function() {
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

    // ===== Init =====
    initializeYearDropdown();
    loadResultate(new Date().getFullYear());
    setTimeout(calculateTableHeight, 200);
});
</script>

<?php include 'footer.inc.php'; ?>
