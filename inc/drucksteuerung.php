<?php
// drucksteuerung.php - Drucksteuerung (QZ Tray Druckprofile, Drucker, Protokoll)

$page_specific_css = '
    /* Collapse-Chevron */
    .action-chevron { transition: transform .2s ease; }
    [data-bs-toggle="collapse"].collapsed .action-chevron { transform: rotate(-90deg); }

    /* Container fuer Drucksteuerung */
    .data-card { max-width: 1100px; }
    .data-card .table-sm td, .data-card .table-sm th { padding: .25rem .5rem; vertical-align: middle; text-align: left; }
    @media (max-width: 991.98px) { .data-card { max-width: 100%; } }

    /* QZ Status Bar */
    .qz-bar { display: flex; align-items: center; gap: 10px; padding: 10px 16px; background: #f8f9fa; border-bottom: 1px solid #dee2e6; border-radius: 8px 8px 0 0; }
    .qz-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .qz-dot.connected { background: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,.4); }
    .qz-dot.disconnected { background: #ef4444; }
    .qz-bar .qz-text { font-size: 0.85rem; font-weight: 500; }
    .qz-bar .qz-actions { margin-left: auto; display: flex; gap: 6px; }

    /* Profil-Matrix Layout */
    .profile-matrix-header,
    .profile-row {
        display: grid;
        grid-template-columns: 1.8fr 2fr 1.3fr 70px 60px 50px;
        gap: 10px;
        padding: 10px 20px;
        align-items: center;
        border-bottom: 1px solid #f0f0f0;
    }
    .profile-matrix-header {
        font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.04em; color: #6c757d; background: #f8f9fa;
        border-bottom: 1px solid #dee2e6; padding: 8px 20px;
    }
    .profile-row:hover { background: #f8f9fa; }
    .profile-row:last-child { border-bottom: none; }

    /* Profil-Name */
    .profile-name-label { font-weight: 600; font-size: 0.85rem; display: block; }
    .profile-name-desc { font-size: 0.75rem; color: #6c757d; display: block; margin-top: 1px; }

    /* Section Labels */
    .profile-section-label {
        font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;
        color: #495057; padding: 10px 20px 5px; background: #f8f9fa;
        border-bottom: 1px solid #dee2e6; cursor: pointer; user-select: none;
        display: flex; align-items: center; gap: 6px;
    }
    .profile-section-label:hover { background: #e9ecef; }
    .profile-section-label .section-chevron { font-size: 0.65rem; transition: transform 0.2s ease; display: inline-block; }
    .profile-section-label.collapsed .section-chevron { transform: rotate(-90deg); }
    .profile-section-label .section-count { font-size: 0.65rem; color: #6c757d; font-weight: 400; margin-left: auto; }

    /* Selects innerhalb der Matrix */
    .profile-select {
        width: 100%; height: 32px; border: 1px solid #dee2e6; border-radius: 6px;
        padding: 0 8px; font-size: 0.8rem; background: #fff;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 12 12\'%3E%3Cpath d=\'M3 5l3 3 3-3\' stroke=\'%235a6577\' stroke-width=\'1.5\' fill=\'none\' stroke-linecap=\'round\'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 8px center;
    }
    .profile-select:focus { outline: none; border-color: #0d6efd; box-shadow: 0 0 0 2px rgba(13,110,253,.15); }

    /* Kopien-Input */
    .profile-copies-input {
        width: 50px; height: 32px; border: 1px solid #dee2e6; border-radius: 6px;
        padding: 0 6px; font-size: 0.85rem; text-align: center;
    }
    .profile-copies-input:focus { outline: none; border-color: #0d6efd; }

    /* Format-Badge */
    .profile-format-badge {
        font-size: 0.75rem; padding: 3px 10px; border-radius: 4px;
        background: #dcfce7; color: #16a34a; font-weight: 600; white-space: nowrap;
    }

    /* Testdruck-Button */
    .profile-test-btn {
        width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 6px; background: transparent; border: 1px solid #dee2e6;
        color: #6c757d; cursor: pointer; font-size: 14px; transition: all 0.15s;
    }
    .profile-test-btn:hover { background: #f8f9fa; color: #212529; }

    /* Druckprotokoll */
    .print-log-list { max-height: 240px; overflow-y: auto; }
    .print-log-row {
        display: flex; align-items: center; gap: 8px;
        padding: 4px 16px; border-bottom: 1px solid #f0f0f0; font-size: 0.78rem;
    }
    .print-log-row:last-child { border-bottom: none; }
    .print-log-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .print-log-dot.ok { background: #22c55e; }
    .print-log-dot.err { background: #ef4444; }
    .print-log-dot.warn { background: #f59e0b; }
    .print-log-type { font-weight: 600; }
    .print-log-printer { color: #6c757d; }
    .print-log-time { color: #6c757d; margin-left: auto; white-space: nowrap; font-size: 0.72rem; }
    .print-log-empty { padding: 12px 16px; font-size: 0.8rem; color: #6c757d; text-align: center; }

    /* Responsive */
    @media (max-width: 767.98px) {
        .profile-matrix-header { display: none; }
        .profile-row { grid-template-columns: 1fr; gap: 6px; padding: 12px 16px; }
        .profile-row > div { display: flex; align-items: center; gap: 8px; }
        .profile-row > div::before { content: attr(data-label); font-size: 0.7rem; color: #6c757d; min-width: 80px; }
        .profile-copies-input { width: 100%; }
        .profile-test-btn { margin-left: auto; }
    }
';

include 'header.inc.php';

// Nur Admin + Vorstand (nach header.inc.php, da dieser user_role setzt)
if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'vorstand'])) {
    ob_end_clean();
    header('Location: home.php');
    exit();
}
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper">
        <div class="row mb-4 d-none d-md-flex">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-printer me-2"></i>Drucksteuerung
            </h2>
          </div>
        </div>
        <div class="content-background">

    <!-- Karte 1: Druckprofile (QZ-Bar + Matrix) -->
    <div class="card data-card mb-3">
        <!-- QZ Status -->
        <div class="qz-bar">
            <div id="qzDot" class="qz-dot disconnected"></div>
            <span id="qzStatusText" class="qz-text">Nicht verbunden mit QZ Tray</span>
            <span id="machineIdBadge" class="badge bg-secondary ms-2" style="font-size:.7rem; font-weight:normal; cursor:help" title=""></span>
            <div class="qz-actions">
                <button id="btnConnect" class="btn btn-primary btn-sm" onclick="Druck.connect()">
                    <i class="bi bi-plug me-1"></i>Verbinden
                </button>
                <button id="btnDisconnect" class="btn btn-outline-secondary btn-sm" onclick="Druck.disconnect()" disabled>
                    <i class="bi bi-x-circle me-1"></i>Trennen
                </button>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="card-header d-flex align-items-center">
            <span class="fw-bold">Druckprofile</span>
            <span class="badge bg-primary ms-2" id="profileCount">0</span>
            <button class="btn btn-success btn-sm ms-auto" onclick="Druck.saveAllProfiles()">
                <i class="bi bi-save me-1"></i>Speichern
            </button>
        </div>

        <!-- Profil-Matrix -->
        <div class="profile-matrix" id="profileMatrix"></div>

        <div class="card-footer text-muted" style="font-size:0.8rem">
            <span id="profileFooter">0 Profile konfiguriert</span>
        </div>
    </div>

    <!-- Karte 2: Drucker -->
    <div class="card data-card mb-3">
        <div class="card-header d-flex align-items-center" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#druckerBody">
            <i class="bi bi-chevron-down action-chevron me-2"></i>
            <span class="fw-bold">Drucker</span>
            <button class="btn btn-primary btn-sm ms-auto" onclick="event.stopPropagation(); Druck.showAddPrinter()">
                <i class="bi bi-plus-lg me-1"></i>Hinzufuegen
            </button>
        </div>
        <div class="collapse" id="druckerBody">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" style="font-size:0.82rem; table-layout:fixed">
                <colgroup>
                    <col>
                    <col style="width:80px">
                    <col style="width:70px">
                    <col style="width:45px">
                </colgroup>
                <thead>
                    <tr>
                        <th>Anzeigename</th>
                        <th>Typ</th>
                        <th>Aktiv</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="printerTableBody">
                    <tr><td colspan="4" class="text-center text-muted">Lade...</td></tr>
                </tbody>
            </table>
        </div>
        </div>
    </div>

    <!-- Karte 3: Druckprotokoll -->
    <div class="card data-card mb-3">
        <div class="card-header d-flex align-items-center" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#protokollBody">
            <i class="bi bi-chevron-down action-chevron me-2"></i>
            <span class="fw-bold">Druckprotokoll</span>
            <button class="btn btn-outline-secondary btn-sm ms-auto" onclick="event.stopPropagation(); Druck.loadPrintLog()">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="collapse" id="protokollBody">
            <div class="print-log-list" id="printLogBody">
                <div class="print-log-empty">Lade...</div>
            </div>
        </div>
    </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Drucker-Panel (Modal) -->
<div class="modal fade" id="printerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printerModalTitle"><i class="bi bi-printer me-2"></i>Drucker hinzufuegen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="printerEditId">
                <div class="mb-3">
                    <label for="printerName" class="form-label">Systemname <span class="text-danger">*</span></label>
                    <select id="printerName" class="form-select">
                        <option value="">-- Drucker aus System waehlen --</option>
                    </select>
                    <div class="form-text">Drucker wird live von QZ Tray geladen</div>
                </div>
                <div class="mb-3">
                    <label for="printerDisplayName" class="form-label">Anzeigename</label>
                    <input type="text" id="printerDisplayName" class="form-control" placeholder="Optionaler Anzeigename">
                </div>
                <div class="mb-3">
                    <label for="printerTyp" class="form-label">Typ</label>
                    <select id="printerTyp" class="form-select">
                        <option value="laser">Laser</option>
                        <option value="tintenstrahl">Tintenstrahl</option>
                        <option value="etiketten">Etiketten</option>
                        <option value="bon">Bon</option>
                        <option value="sonstige">Sonstige</option>
                    </select>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="printerAktiv" checked>
                    <label class="form-check-label" for="printerAktiv">Aktiv</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-success" onclick="Druck.savePrinter()"><i class="bi bi-check-circle me-1"></i>Speichern</button>
            </div>
        </div>
    </div>
</div>

<script>window._csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';</script>

<!-- QZ Tray Scripts -->
<script src="js/lib/rsvp.min.js"></script>
<script src="js/lib/sha-256.min.js"></script>
<script src="js/lib/qz-tray.js"></script>
<script src="js/print-manager.js"></script>
<script src="js/app-drucksteuerung.js"></script>

<?php include 'footer.inc.php'; ?>
