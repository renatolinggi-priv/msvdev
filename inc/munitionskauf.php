<?php
// munitionskauf.php - Munitionsbestellungen erfassen (Redesign v2)
include 'dbconnect.inc.php';

// CSRF Token (wird bereits in header.inc.php generiert)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_specific_css = '
/* === MUNITIONSKAUF TABS === */
.msv-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 1.25rem;
}

.msv-tab {
    padding: 0.6rem 1.25rem;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--secondary-color);
    border: none;
    background: none;
    cursor: pointer;
    position: relative;
    transition: color 0.2s;
}

.msv-tab:hover { color: var(--dark-color); }

.msv-tab.active {
    color: var(--dark-color);
    font-weight: 600;
}

.msv-tab.active::after {
    content: "";
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--dark-color);
    border-radius: 2px 2px 0 0;
}

.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* === COMPACT FORM ROW === */
.compact-form-row {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius);
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
    flex-wrap: wrap;
}

.compact-form-row .form-group {
    flex: 1;
    min-width: 0;
}

.compact-form-row .form-group label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--secondary-color);
    margin-bottom: 0.2rem;
    display: block;
    white-space: nowrap;
}

/* === MITGLIED/GAST INLINE === */
.kaeufer-row {
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
    margin-bottom: 1rem;
}

.kaeufer-row .form-group {
    flex: 1;
    min-width: 0;
}

.kaeufer-row .form-group label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--secondary-color);
    margin-bottom: 0.2rem;
    display: block;
    white-space: nowrap;
}

.kaeufer-row .separator {
    padding-bottom: 0.35rem;
    font-size: 0.8rem;
    color: var(--secondary-color);
    font-weight: 500;
    flex-shrink: 0;
}

/* === MUNITIONS GRID === */
.munitions-section {
    background: var(--light-color);
    border: 1px solid #e2e8f0;
    border-radius: var(--border-radius);
    padding: 1rem;
    margin-bottom: 1rem;
}

.munitions-section > h6 {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--secondary-color);
    margin-bottom: 0.75rem;
}

.munitions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.munitions-col {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: var(--border-radius);
    padding: 0.75rem;
}

.munitions-col h6 {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--secondary-color);
    margin-bottom: 0.5rem;
}

.paket-check:checked + label {
    color: var(--success-color);
    font-weight: 600;
}

.custom-preis {
    min-width: 60px;
    font-weight: 600;
    font-size: 0.85rem;
}

/* === TOTAL + ACTIONS ROW === */
.total-actions-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 1rem;
}

.total-actions-row .total-bar {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius);
    padding: 0.45rem 0.75rem;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    height: 38px;
}

.total-actions-row .total-bar .total-amount {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--dark-color);
}

.total-actions-row .action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

/* === STATS TAB === */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.stats-card-inner {
    background: var(--light-color);
    border: 1px solid #e2e8f0;
    border-radius: var(--border-radius);
    padding: 1rem;
}

.stats-card-inner h6 {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 0.75rem;
    font-size: 0.85rem;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    padding: 0.35rem 0;
    font-size: 0.83rem;
}

.stat-row + .stat-row { border-top: 1px solid #eee; }
.stat-row strong { color: var(--secondary-color); }

.stat-row.stat-total {
    border-top: 2px solid #dee2e6;
    margin-top: 0.25rem;
    padding-top: 0.5rem;
}

.stat-row.stat-total strong {
    color: var(--dark-color);
    font-size: 1rem;
}

.top-buyer-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.3rem 0;
    font-size: 0.82rem;
}

.top-buyer-item + .top-buyer-item { border-top: 1px solid #eee; }

.top-buyer-rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #e9ecef;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--secondary-color);
    margin-right: 0.5rem;
}

.top-buyer-item:first-child .top-buyer-rank { background: #ffd700; color: #664d00; }
.top-buyer-item:nth-child(2) .top-buyer-rank { background: #c0c0c0; color: #555; }
.top-buyer-item:nth-child(3) .top-buyer-rank { background: #cd7f32; color: #fff; }

.ammo-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-top: 0.75rem;
}

.ammo-summary-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: var(--border-radius);
    padding: 0.75rem;
    text-align: center;
}

.ammo-summary-card .ammo-type {
    font-size: 0.75rem;
    color: var(--secondary-color);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ammo-summary-card .ammo-count {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--dark-color);
}

.ammo-summary-card .ammo-detail {
    font-size: 0.75rem;
    color: var(--secondary-color);
}

/* === KÄUFE-TABELLE: Sticky-Header Scroll-Bleed Fix ===
   Globale .table thead th sind position:sticky. Bootstrap nutzt
   border-collapse:collapse -> sticky-Borders scrollen weg und die
   Zeilen schieben sich optisch über den Header. Fix: separate +
   box-shadow als Trenner (scrollt nicht mit dem Body weg). */
#bestellungenTabelle {
    border-collapse: separate;
    border-spacing: 0;
}

#bestellungenTabelle thead th {
    position: sticky;
    top: 0;
    z-index: 6;
    background: var(--light-color);
    border-bottom: none;
    box-shadow: inset 0 -2px 0 #dee2e6;
}

/* Total-Zeile am unteren Rand fixieren, solange der Body scrollt */
#bestellungenTabelle tfoot th {
    position: sticky;
    bottom: 0;
    z-index: 6;
    background: #f8f9fa;
    box-shadow: inset 0 2px 0 #dee2e6;
}

/* === MOBILE === */
@media (max-width: 767.98px) {
    /* Desktop-Tabelle ausblenden, Mobile Cards zeigen */
    .desktop-table-container { display: none !important; }
    .mobile-cards-container { display: block !important; }

    /* Compact form row stacken */
    .compact-form-row {
        flex-direction: column;
        gap: 0.5rem;
    }

    .compact-form-row .form-group {
        flex: none !important;
        width: 100%;
    }

    /* Käufer stacken */
    .kaeufer-row {
        flex-direction: column;
        gap: 0.5rem;
    }

    .kaeufer-row .separator { display: none; }

    /* Munitions-Grid stacken */
    .munitions-grid {
        grid-template-columns: 1fr;
    }

    /* Total + Actions stacken */
    .total-actions-row {
        flex-direction: column;
        gap: 0.75rem;
    }

    .total-actions-row .total-bar {
        width: 100%;
        justify-content: center;
        height: auto;
    }

    .total-actions-row .action-buttons {
        width: 100%;
    }

    .total-actions-row .action-buttons .btn {
        flex: 1;
    }

    /* Stats stacken */
    .stats-grid {
        grid-template-columns: 1fr;
    }

    /* Touch-friendly Form Controls */
    .form-control, .form-control-sm, .form-select, .form-select-sm {
        min-height: 48px !important;
        font-size: 16px !important;
    }

    .input-group-text {
        min-height: 48px !important;
        font-size: 16px !important;
    }

    .form-check {
        min-height: 44px !important;
        display: flex !important;
        align-items: center !important;
    }

    .form-check-input {
        min-width: 24px !important;
        min-height: 24px !important;
    }

    .form-check-label {
        font-size: 16px !important;
        padding: 8px !important;
    }

    .btn, .btn-sm {
        min-height: 48px !important;
        font-size: 16px !important;
    }

    /* Mobile Tabs */
    .msv-tab {
        font-size: 0.82rem;
        padding: 0.5rem 0.75rem;
    }

    /* Filter Pills */
    .filter-pills {
        display: flex;
        gap: 0.4rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
        margin-bottom: 0.75rem;
        -webkit-overflow-scrolling: touch;
    }

    .filter-pill {
        flex-shrink: 0;
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        font-size: 0.82rem;
        font-weight: 500;
        border: 1px solid #dee2e6;
        background: #fff;
        color: var(--secondary-color);
        white-space: nowrap;
        min-height: 44px;
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .filter-pill.active {
        background: var(--secondary-color);
        color: #fff;
        border-color: var(--secondary-color);
    }

    /* Mobile Stats Summary */
    .mobile-stats-bar {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 0.6rem 1rem;
        margin-bottom: 0.75rem;
        display: flex;
        justify-content: space-around;
        text-align: center;
    }

    .mobile-stats-bar .stat-item {
        font-size: 0.75rem;
        color: var(--secondary-color);
    }

    .mobile-stats-bar .stat-item strong {
        display: block;
        font-size: 0.95rem;
        color: var(--dark-color);
    }

    /* Mobile Card */
    .mobile-kauf-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        overflow: hidden;
    }

    .mobile-kauf-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        background: #fafbfc;
        border-bottom: 1px solid #f1f3f4;
    }

    .mobile-kauf-card-title {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--dark-color);
    }

    .mobile-kauf-card-subtitle {
        font-size: 0.75rem;
        color: var(--secondary-color);
    }

    .mobile-kauf-card-badge {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--secondary-color);
    }

    .mobile-kauf-card-body {
        padding: 0.6rem 1rem;
    }

    .mobile-kauf-card-row {
        display: flex;
        justify-content: space-between;
        padding: 0.25rem 0;
        font-size: 0.83rem;
    }

    .mobile-kauf-card-row + .mobile-kauf-card-row {
        border-top: 1px solid #f5f5f5;
    }

    .mobile-kauf-card-actions {
        display: flex;
        gap: 0.5rem;
        padding: 0.4rem 1rem;
        border-top: 1px solid #f1f3f4;
        justify-content: flex-end;
    }

    .mobile-search {
        position: relative;
        margin-bottom: 0.75rem;
    }

    .mobile-search input {
        padding-left: 2.5rem;
        min-height: 44px;
        font-size: 16px;
        border-radius: 0.5rem;
    }

    .mobile-search .search-icon {
        position: absolute;
        left: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--secondary-color);
    }

    .mobile-total-footer {
        background: #fff;
        border: 2px solid var(--secondary-color);
        border-radius: 0.5rem;
        padding: 0.6rem 1rem;
        margin-top: 0.5rem;
    }
}

/* Desktop: Mobile-Elemente ausblenden */
@media (min-width: 768px) {
    .mobile-cards-container { display: none !important; }
    .mobile-only { display: none !important; }
    .filter-pills { display: none !important; }
}
';

include 'header.inc.php';
?>

<div class="container-fluid">
<div class="row">
  <div class="col-xl-7 col-lg-9 col-md-11 col-12 ps-0">
    <div class="main-content-wrapper">

      <!-- Page Title -->
      <div class="row mb-3 d-none d-md-flex">
        <div class="col-md-12">
          <h2 class="h4 mb-0" style="color: var(--secondary-color);">
            <i class="bi bi-cart-check me-2"></i>Munitionskauf erfassen
          </h2>
        </div>
      </div>

      <!-- Tabs -->
      <div class="msv-tabs">
        <button class="msv-tab active" data-tab="tab-erfassung">
          <i class="bi bi-cart-plus me-1"></i>Erfassung
        </button>
        <button class="msv-tab d-none d-md-inline-block" data-tab="tab-kaeufe">
          <i class="bi bi-table me-1"></i>Käufe
        </button>
        <button class="msv-tab" data-tab="tab-statistiken">
          <i class="bi bi-graph-up me-1"></i>Statistiken
        </button>
        <!-- Mobile-only: Käufe-Tab -->
        <button class="msv-tab d-md-none" data-tab="tab-kaeufe-mobile">
          <i class="bi bi-list-ul me-1"></i>Käufe
        </button>
      </div>

      <!-- ============ TAB: Erfassung ============ -->
      <div class="tab-pane active" id="tab-erfassung">
        <div class="content-background">
          <form id="munitionForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- Kompakte Zeile: Jahr / Datum / Anlass -->
            <div class="compact-form-row">
              <div class="form-group" style="flex: 0 0 110px;">
                <label><i class="bi bi-calendar3 me-1"></i>Jahr</label>
                <select id="yearSelect" class="form-select form-select-sm"></select>
              </div>
              <div class="form-group" style="flex: 0 0 160px;">
                <label><i class="bi bi-calendar-event me-1"></i>Kaufdatum</label>
                <input type="date" id="kaufDatum" class="form-control form-control-sm" required>
              </div>
              <div class="form-group">
                <label><i class="bi bi-tag me-1"></i>Anlass</label>
                <input type="text" id="anlass" class="form-control form-control-sm" placeholder="z.B. Training, Feldschiessen...">
              </div>
            </div>

            <!-- Mitglied / Gast: nebeneinander -->
            <div class="kaeufer-row">
              <div class="form-group">
                <label><i class="bi bi-person me-1"></i>Mitglied</label>
                <select id="mitgliedSelect" class="form-select form-select-sm">
                  <option value="">– Mitglied wählen –</option>
                </select>
              </div>
              <div class="separator">oder</div>
              <div class="form-group">
                <label><i class="bi bi-person-plus me-1"></i>Gast</label>
                <input type="text" class="form-control form-control-sm" id="gastName" placeholder="Gast-Name eingeben">
              </div>
            </div>

            <!-- Munition: Nebeneinander -->
            <div class="munitions-section">
              <h6><i class="bi bi-box-seam me-1"></i>Munition (CHF 0.50/Schuss)</h6>
              <div class="munitions-grid">
                <!-- Standard-Pakete -->
                <div class="munitions-col">
                  <h6>Standard-Pakete</h6>
                  <div class="form-check mb-2">
                    <input class="form-check-input paket-check" type="checkbox"
                           id="paket_gp11_60" data-typ="GP11_60" data-anzahl="60">
                    <label class="form-check-label small" for="paket_gp11_60">
                      <strong>60× GP11</strong> <span class="text-muted ms-1">CHF 30</span>
                    </label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input paket-check" type="checkbox"
                           id="paket_gp90_50" data-typ="GP90_50" data-anzahl="50">
                    <label class="form-check-label small" for="paket_gp90_50">
                      <strong>50× GP90</strong> <span class="text-muted ms-1">CHF 25</span>
                    </label>
                  </div>
                </div>
                <!-- Individuelle Anzahl -->
                <div class="munitions-col">
                  <h6>Individuelle Anzahl</h6>
                  <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text" style="width: 48px;">GP11</span>
                    <input type="number" class="form-control custom-anzahl"
                           id="custom_gp11" data-typ="GP11_CUSTOM"
                           min="0" max="500" step="1" value="0"
                           inputmode="numeric" placeholder="Anz.">
                    <span class="input-group-text custom-preis small">CHF 0</span>
                  </div>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text" style="width: 48px;">GP90</span>
                    <input type="number" class="form-control custom-anzahl"
                           id="custom_gp90" data-typ="GP90_CUSTOM"
                           min="0" max="500" step="1" value="0"
                           inputmode="numeric" placeholder="Anz.">
                    <span class="input-group-text custom-preis small">CHF 0</span>
                  </div>
                </div>
              </div>

            </div>

            <!-- Total + Actions in einer Zeile -->
            <div class="total-actions-row">
              <div class="total-bar">
                <span class="text-muted small">GP11:&nbsp;</span><strong id="total_gp11">0</strong>
                <span class="text-muted small ms-2">GP90:&nbsp;</span><strong id="total_gp90">0</strong>
                <span class="total-amount ms-3" id="total_preis">CHF 0.00</span>
              </div>
              <div class="action-buttons">
                <button type="button" id="btnReset" class="btn btn-compact-standard btn-outline-secondary" title="Zurücksetzen">
                  <i class="bi bi-arrow-counterclockwise"></i>
                </button>
                <button type="submit" id="btnSave" class="btn btn-compact-standard btn-primary">
                  <span class="spinner-border spinner-border-sm me-1 d-none" id="saveSpinner"></span>
                  <i class="bi bi-save me-1"></i>Speichern
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- Desktop: Bestellungen-Tabelle direkt unter dem Formular -->
        <div class="d-none d-md-block" id="desktopKaeufeContainer">
          <div class="table-wrapper">
            <div class="table-title">
              <span><i class="bi bi-table me-2"></i>Munitionskäufe</span>
              <div class="button-group">
                <button type="button" id="btnFilterToday" class="btn btn-compact-standard btn-outline-secondary btn-sm">Heute</button>
                <button type="button" id="btnFilterWeek" class="btn btn-compact-standard btn-outline-secondary btn-sm">Woche</button>
                <button type="button" id="btnFilterMonth" class="btn btn-compact-standard btn-outline-secondary btn-sm">Monat</button>
                <button type="button" id="btnFilterYear" class="btn btn-compact-standard btn-outline-secondary btn-sm active">Jahr</button>
                <button type="button" id="btnGeneratePDF" class="btn btn-compact-standard btn-outline-primary btn-sm ms-2">
                  <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                </button>
              </div>
            </div>
            <div class="desktop-table-container">
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="bestellungenTabelle">
                  <thead>
                    <tr>
                      <th style="width: 95px;">Datum</th>
                      <th style="min-width: 140px;">Käufer</th>
                      <th>Anlass</th>
                      <th class="text-center">GP11</th>
                      <th class="text-center">GP90</th>
                      <th class="text-end">Preis</th>
                      <th style="width: 50px;"></th>
                    </tr>
                  </thead>
                  <tbody id="bestellungenTableBody">
                    <tr><td colspan="7" class="text-muted text-center">Wird geladen...</td></tr>
                  </tbody>
                  <tfoot class="table-light">
                    <tr>
                      <th colspan="3">Total</th>
                      <th class="text-center" id="footerGP11">0</th>
                      <th class="text-center" id="footerGP90">0</th>
                      <th class="text-end" id="footerPreis">CHF 0.00</th>
                      <th></th>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============ TAB: Käufe (Desktop, als eigener Tab wenn gewünscht) ============ -->
      <div class="tab-pane" id="tab-kaeufe">
        <div class="content-background">
          <p class="text-muted text-center"><i class="bi bi-arrow-up me-1"></i>Die Käufe-Tabelle wird direkt unter dem Erfassungsformular angezeigt.</p>
        </div>
      </div>

      <!-- ============ TAB: Käufe Mobile ============ -->
      <div class="tab-pane" id="tab-kaeufe-mobile">
        <div class="content-background">
          <!-- Filter Pills -->
          <div class="filter-pills" id="mobileFilterPills">
            <span class="filter-pill" data-filter="today">Heute</span>
            <span class="filter-pill" data-filter="week">Woche</span>
            <span class="filter-pill" data-filter="month">Monat</span>
            <span class="filter-pill active" data-filter="year">Jahr</span>
            <span class="filter-pill" id="mobilePdfBtn"><i class="bi bi-file-earmark-pdf"></i></span>
          </div>

          <!-- Mobile Search -->
          <div class="mobile-search">
            <i class="bi bi-search search-icon"></i>
            <input type="text" class="form-control" placeholder="Käufer suchen..." id="mobileSearchInput">
          </div>

          <!-- Mobile Cards Container -->
          <div id="mobileCardsContainer">
            <div class="text-muted text-center py-3">Wird geladen...</div>
          </div>

          <!-- Mobile Total -->
          <div class="mobile-total-footer" id="mobileTotalFooter">
            <div class="d-flex justify-content-between align-items-center">
              <strong class="small">Total</strong>
              <div class="small">
                GP11: <strong id="mobileFooterGP11">0</strong> ·
                GP90: <strong id="mobileFooterGP90">0</strong> ·
                <strong style="color: var(--secondary-color);" id="mobileFooterPreis">CHF 0</strong>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============ TAB: Statistiken ============ -->
      <div class="tab-pane" id="tab-statistiken">
        <div class="content-background">
          <div class="stats-grid">
            <!-- Umsatz -->
            <div class="stats-card-inner">
              <h6><i class="bi bi-cash-stack me-2"></i>Umsatz <span class="badge bg-secondary" id="statsYear">2026</span></h6>
              <div class="stat-row">
                <span>Heute</span>
                <strong id="statsToday">CHF 0.00</strong>
              </div>
              <div class="stat-row">
                <span>Diese Woche</span>
                <strong id="statsWeek">CHF 0.00</strong>
              </div>
              <div class="stat-row">
                <span>Diesen Monat</span>
                <strong id="statsMonth">CHF 0.00</strong>
              </div>
              <div class="stat-row stat-total">
                <span><strong>Jahrestotal</strong></span>
                <strong id="statsYearTotal">CHF 0.00</strong>
              </div>
            </div>

            <!-- Top Käufer -->
            <div class="stats-card-inner">
              <h6><i class="bi bi-trophy me-2"></i>Top Käufer (Jahr)</h6>
              <div id="topKaeuferList">
                <div class="text-muted small">Wird geladen...</div>
              </div>
            </div>
          </div>

          <!-- Munitionsverbrauch -->
          <div style="margin-top: 1rem;">
            <h6 style="font-size: 0.85rem; font-weight: 600; color: var(--dark-color); margin-bottom: 0.5rem;">
              <i class="bi bi-box-seam me-2"></i>Munitionsverbrauch (Jahr)
            </h6>
            <div class="ammo-summary">
              <div class="ammo-summary-card">
                <div class="ammo-type">GP11</div>
                <div class="ammo-count" id="ammoGP11">0</div>
                <div class="ammo-detail" id="ammoGP11Detail">Schuss · CHF 0</div>
              </div>
              <div class="ammo-summary-card">
                <div class="ammo-type">GP90</div>
                <div class="ammo-count" id="ammoGP90">0</div>
                <div class="ammo-detail" id="ammoGP90Detail">Schuss · CHF 0</div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<script src="munitionskauf/munitionskauf.js?v=<?= time() ?>"></script>

<?php include 'footer.inc.php'; ?>
