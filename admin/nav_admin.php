<?php
/* =============================================
 * FILE: /admin/nav_admin.php
 * PURPOSE: Navigations-Verwaltung (Hybrid-Table + Slide-Panel)
 *          Baum-Linien, Ein-/Ausklappen, Drag&Drop, Icon-Picker, Trennlinien
 * ============================================= */
require_once __DIR__ . '/../inc/session_config.inc.php';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/remember_me.inc.php';
if (!isset($_SESSION['user_id'])) {
    restoreSessionFromToken();
}

// Admin-Check
if (!function_exists('user_can_manage_navigation')) {
    function user_can_manage_navigation(): bool {
        return ($_SESSION['user_role'] ?? '') === 'admin' || (int)($_SESSION['user_id'] ?? 0) === 1;
    }
}
if (!user_can_manage_navigation()) {
    http_response_code(403);
    die('Kein Zugriff');
}

// CSS fuer diese Seite
$page_specific_css = <<<'CSS'
/* ===== Nav Admin – Hybrid Layout ===== */
.nav-admin-wrapper {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  overflow: visible;
  background: #fff;
  max-width: 960px;
}
.nav-admin-title {
  margin: 0; padding: 1rem 1.25rem; font-weight: 600;
  color: #64748b;
  border-bottom: 2px solid #e2e8f0;
  background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
  border-radius: 12px 12px 0 0;
  display: flex; align-items: center; justify-content: space-between;
}

/* --- Hybrid-Tabelle --- */
.hybrid-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.hybrid-table thead th {
  padding: 0.45rem 0.7rem; font-size: 0.68rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;
  background: linear-gradient(180deg, #f8fafc, #eef2f7);
  border-bottom: 2px solid #e2e8f0;
  position: sticky; top: 0; z-index: 6;
}
.hybrid-table tbody tr.hybrid-row { cursor: pointer; transition: background 0.15s; }
.hybrid-table tbody tr.hybrid-row:hover { background: #eef2f7 !important; }
.hybrid-table tbody tr.hybrid-row.selected {
  background: #e8f0fe !important; box-shadow: inset 4px 0 0 #3b82f6;
}
.hybrid-table tbody td {
  padding: 0.34rem 0.7rem; vertical-align: middle;
  border-bottom: 1px solid #f1f5f9; font-size: 0.84rem; line-height: 1.35;
}

/* Root vs. Sub-Item Differenzierung */
.hybrid-table tbody tr.row-l0 { background: #f1f5fb; border-top: 1px solid #d8e1ee; }
.hybrid-table tbody tr.row-l0 .h-title { font-weight: 700; font-size: 0.9rem; color: #1e293b; }
.hybrid-table tbody tr.row-l0 .item-icon { font-size: 0.95rem; }
.hybrid-table tbody tr.row-l1 { background: #fff; }
.hybrid-table tbody tr.row-l1 .h-title { color: #475569; font-weight: 500; }
.hybrid-table tbody tr.row-l2,
.hybrid-table tbody tr.row-l3 { background: #fafbfc; }
.hybrid-table tbody tr.row-l2 .h-title,
.hybrid-table tbody tr.row-l3 .h-title { color: #64748b; font-weight: 400; font-size: 0.82rem; }

.h-title { font-weight: 500; }
.h-link {
  color: #64748b; font-size: 0.8rem;
  font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
  background: #f8fafc; padding: 1px 7px; border-radius: 4px;
  display: inline-block; max-width: 280px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  vertical-align: middle;
}
.h-parent { color: #94a3b8; font-size: 0.8rem; font-style: italic; }

/* Drag Handle */
.drag-grip {
  color: #cbd5e1; cursor: grab; font-size: 1.1rem;
  padding: 2px 4px; border-radius: 4px; transition: color 0.15s;
}
.drag-grip:hover { color: #94a3b8; }
.drag-grip:active { cursor: grabbing; }
.nav-row-dragging { background: #fff !important; box-shadow: 0 10px 24px rgba(0,0,0,.12); z-index: 100; }
.nav-row-placeholder {
  background: repeating-linear-gradient(45deg, #f1f5f9, #f1f5f9 10px, #e2e8f0 10px, #e2e8f0 20px) !important;
  border: 2px dashed #93c5fd !important;
}
.drag-child-hidden { display: none !important; }
.drag-children-badge {
  display: inline-block; margin-left: 8px;
  padding: 2px 8px; font-size: 0.7rem; font-weight: 600;
  background: #e9ecef; color: #495057; border-radius: 10px; vertical-align: middle;
}

/* Baum-Linien-Einrueckung */
.level-indent { display: inline-flex; align-items: center; }
.level-tree { display: inline-block; width: 18px; height: 16px; position: relative; color: #cbd5e1; }
.level-tree::before {
  content: ''; position: absolute; left: 5px; top: 0; bottom: 50%;
  border-left: 2px solid #cbd5e1;
}
.level-tree::after {
  content: ''; position: absolute; left: 5px; top: 50%; width: 9px;
  border-top: 2px solid #cbd5e1;
}
.level-tree-trunk { display: inline-block; width: 18px; height: 16px; position: relative; }
.level-tree-trunk::before {
  content: ''; position: absolute; left: 5px; top: 0; bottom: 0;
  border-left: 2px solid #e2e8f0;
}
.item-icon { font-size: 0.88rem; margin-right: 5px; margin-left: 2px; }
.item-icon.folder { color: #f59e0b; }
.item-icon.file { color: #cbd5e1; }

/* Collapse-Chevron */
.btn-toggle-collapse {
  background: transparent; border: none; padding: 0;
  width: 22px; height: 22px; border-radius: 4px;
  color: #475569; cursor: pointer; transition: all 0.15s;
  display: inline-flex; align-items: center; justify-content: center;
  margin-right: 4px; flex-shrink: 0;
}
.btn-toggle-collapse:hover { background: #dbeafe; color: #2563eb; }
.btn-toggle-collapse i { font-size: 0.85rem; transition: transform 0.2s; }
.btn-toggle-placeholder { display: inline-block; width: 22px; margin-right: 4px; }
.row-collapsed { display: none !important; }
.collapsed-children-count {
  display: inline-block; margin-left: 8px;
  padding: 1px 7px; font-size: 0.7rem; font-weight: 600;
  background: #cfe2ff; color: #084298; border-radius: 10px; vertical-align: middle;
}

/* Trennlinien-Row */
.row-trennlinie td { padding-top: 0.5rem; padding-bottom: 0.5rem; }
.row-trennlinie .h-link, .row-trennlinie .h-parent { opacity: 0.6; }
.trennlinie-preview { display: inline-flex; align-items: center; gap: 8px; flex: 1; min-width: 0; }
.trennlinie-hr { flex: 0 0 60px; height: 0; border-top: 2px dashed #94a3b8; }
.trennlinie-label { font-size: 0.8rem; color: #64748b; font-style: italic; white-space: nowrap; }

/* Inline Level-Buttons */
.level-btns { display: flex; gap: 3px; justify-content: center; }
.level-btns .btn { padding: 2px 6px; font-size: 0.75rem; border-radius: 4px; line-height: 1; }

/* --- Slide-Panel --- */
.nav-edit-panel {
  position: fixed; top: 0; right: -540px; width: 520px; height: 100vh;
  background: #fff; box-shadow: -8px 0 30px rgba(0,0,0,0.12);
  z-index: 1060; transition: right 0.3s cubic-bezier(0.4,0,0.2,1);
  display: flex; flex-direction: column;
}
.nav-edit-panel.open { right: 0; }
.panel-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.3);
  z-index: 1055; opacity: 0; visibility: hidden; transition: all 0.3s;
}
.panel-overlay.show { opacity: 1; visibility: visible; }
.panel-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0;
  background: #f8fafc; flex-shrink: 0;
}
.panel-body { padding: 1.25rem; overflow-y: auto; flex: 1; }
.panel-label { display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.35rem; }
.panel-section { padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9; }
.panel-section:last-child { border-bottom: none; }
.panel-level-controls { display: flex; gap: 8px; align-items: center; }
.panel-level-controls .btn { flex: 1; padding: 8px 12px; font-size: 0.85rem; }
.panel-level-info { text-align: center; padding: 8px; background: #f8fafc; border-radius: 8px; font-weight: 600; color: #2563eb; }
.trennlinie-switch { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.5rem 0.75rem; }

/* Icon-Picker */
.icon-picker-preview {
  display: flex; align-items: center; justify-content: center;
  width: 38px; background: #f8fafc; border: 1px solid #dee2e6;
  border-right: none; border-radius: 0.375rem 0 0 0.375rem;
  font-size: 1.1rem; color: #475569;
}
.icon-picker-preview:empty::after { content: '—'; color: #cbd5e1; font-size: 0.85rem; }
.icon-picker-wrap .form-control { border-radius: 0; }
.icon-picker-wrap .btn { border-radius: 0 0.375rem 0.375rem 0; }
.icon-picker-dropdown {
  position: absolute; z-index: 1080;
  width: 320px; max-height: 300px;
  background: #fff; border: 1px solid #e2e8f0;
  border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.15);
  display: none; flex-direction: column;
}
.icon-picker-dropdown.show { display: flex; }
.icon-picker-search {
  padding: 8px 10px; border: none; border-bottom: 1px solid #e2e8f0;
  font-size: 0.85rem; outline: none; border-radius: 8px 8px 0 0;
}
.icon-picker-grid {
  display: grid; grid-template-columns: repeat(6, 1fr);
  gap: 2px; padding: 8px; overflow-y: auto; flex: 1;
}
.icon-picker-item {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 6px 2px; border-radius: 6px; cursor: pointer;
  transition: background 0.15s; font-size: 0.6rem; color: #64748b;
  text-align: center; min-height: 52px;
}
.icon-picker-item:hover { background: #e9ecef; color: #495057; }
.icon-picker-item i { font-size: 1.2rem; margin-bottom: 2px; color: #334155; }
.icon-picker-item.selected { background: #cfe2ff; }

/* Skeleton */
.skeleton {
  height: 20px; border-radius: 4px;
  background: linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);
  background-size: 200% 100%; animation: navSkeletonLoad 1.5s infinite;
}
@keyframes navSkeletonLoad { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* Unsaved indicator */
.unsaved-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: #f59e0b; display: inline-block;
  animation: unsavedPulse 1.5s infinite;
}
@keyframes unsavedPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

/* Mobile */
@media (max-width: 767.98px) {
  .nav-edit-panel { width: 100%; right: -100%; }
  .desktop-table-container { display: none !important; }
  .mobile-cards-container { display: block !important; }
  .nav-edit-panel, .panel-overlay { display: none !important; }
  .icon-picker-dropdown { width: 280px; }
}
@media (min-width: 768px) { .mobile-cards-container { display: none !important; } }

/* Modal auf Handy */
@media (max-width: 576px) {
  .modal-dialog { margin: 0; max-width: 100%; height: 100%; }
  .modal-content { height: 100%; border-radius: 0; }
}
CSS;

// header.inc.php aus inc/ einbinden
chdir(__DIR__ . '/../inc');
include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-12 col-lg-11 col-12 ps-0">
      <div class="main-content-wrapper">

        <!-- Titel -->
        <div class="row mb-3 d-none d-md-flex">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">Navigation verwalten
            </h2>
          </div>
        </div>

        <div class="content-background">

          <!-- Toolbar -->
          <div class="d-flex flex-wrap gap-2 align-items-center mb-3" style="max-width:960px;">
            <button type="button" class="btn btn-success btn-sm" id="btnAddEntry">
              <i class="bi bi-plus-lg me-1"></i>Neuer Eintrag
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="btnSaveAll">
              <i class="bi bi-save me-1"></i>Alles speichern
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRefresh">
              <i class="bi bi-arrow-clockwise me-1"></i>Aktualisieren
            </button>
            <div class="btn-group btn-group-sm ms-1" role="group">
              <button type="button" class="btn btn-outline-secondary" id="btnCollapseAll" data-tooltip="Alle einklappen">
                <i class="bi bi-chevron-double-up"></i>
              </button>
              <button type="button" class="btn btn-outline-secondary" id="btnExpandAll" data-tooltip="Alle ausklappen">
                <i class="bi bi-chevron-double-down"></i>
              </button>
            </div>
            <div id="unsavedBadge" class="d-none align-items-center gap-2 px-3 py-1 rounded-3 ms-2"
                 style="background: #fef3c7; border: 1px solid #fcd34d;">
              <span class="unsaved-dot"></span>
              <small class="fw-semibold text-dark">Ungespeicherte Änderungen</small>
            </div>
          </div>

          <!-- Hybrid-Tabelle -->
          <div class="nav-admin-wrapper">
            <h5 class="nav-admin-title">
              <span><i class="bi bi-menu-button-wide me-2"></i>Navigationsstruktur</span>
              <span class="badge bg-primary rounded-pill" id="navCount">0 Einträge</span>
            </h5>
            <div class="desktop-table-container">
              <table class="hybrid-table" id="navTable">
                <thead>
                  <tr>
                    <th style="width:32px"></th>
                    <th>Titel</th>
                    <th style="width:22%">Link / Datei</th>
                    <th style="width:15%">Übergeordnet</th>
                    <th style="width:120px; text-align:center">Aktionen</th>
                  </tr>
                </thead>
                <tbody><!-- dynamisch --></tbody>
              </table>
            </div>

            <!-- Mobile Cards -->
            <div class="mobile-cards-container" id="mobileNavCards">
              <div class="mobile-search">
                <div class="position-relative">
                  <i class="bi bi-search search-icon"></i>
                  <input type="text" class="form-control" placeholder="Suchen..." oninput="filterMobileNav(this.value)">
                </div>
              </div>
              <div class="mobile-cards-scroll"></div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Slide-Panel -->
<div class="panel-overlay" id="panelOverlay"></div>
<div class="nav-edit-panel" id="editPanel">
  <div class="panel-header">
    <h6 class="mb-0" id="panelTitle"><i class="bi bi-pencil-square me-2"></i>Eintrag bearbeiten</h6>
    <button class="btn btn-sm btn-outline-secondary" id="panelClose"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="panel-body">
    <div class="panel-section">
      <div class="form-check trennlinie-switch mb-3">
        <input class="form-check-input" type="checkbox" id="panelIstTrennlinie">
        <label class="form-check-label fw-semibold" for="panelIstTrennlinie">
          <i class="bi bi-dash-lg me-1"></i>Als Trennlinie anzeigen
        </label>
        <div class="form-text">Eintrag wird im Menü als horizontale Linie gerendert. Titel/Link/Icon werden ignoriert.</div>
      </div>
      <div class="mb-3">
        <label class="panel-label">Titel <span class="text-danger panel-required-mark">*</span></label>
        <input type="text" class="form-control form-control-sm" id="panelText" maxlength="50">
        <div class="form-text">Max. 50 Zeichen</div>
      </div>
      <div class="mb-3">
        <label class="panel-label">Link / Datei <span class="text-danger">*</span></label>
        <input type="text" class="form-control form-control-sm" id="panelLink" maxlength="255"
               placeholder="z.B. home.php oder /ordner/seite.php">
      </div>
      <div class="mb-3" style="position:relative">
        <label class="panel-label">Icon (Bootstrap Icons)</label>
        <div class="input-group input-group-sm icon-picker-wrap">
          <span class="icon-picker-preview" id="panelIconPreview"></span>
          <input type="text" class="form-control" id="panelIcon" maxlength="50" placeholder="z.B. bi-house">
          <button class="btn btn-outline-secondary icon-picker-toggle" type="button" data-target="panelIcon"><i class="bi bi-grid-3x3-gap"></i></button>
        </div>
      </div>
      <div class="mb-3">
        <label class="panel-label">Übergeordneter Menüpunkt</label>
        <select class="form-select form-select-sm" id="panelParent">
          <option value="0">[Hauptebene]</option>
        </select>
      </div>
    </div>

    <div class="panel-section">
      <label class="panel-label mb-2"><i class="bi bi-arrows-move me-1"></i>Ebene verschieben</label>
      <div class="panel-level-info mb-2" id="panelLevelDisplay">Ebene 0 – Hauptebene</div>
      <div class="panel-level-controls">
        <button class="btn btn-outline-secondary" id="panelLevelUp" data-tooltip="Eine Ebene höher">
          <i class="bi bi-arrow-left me-1"></i>Höher
        </button>
        <button class="btn btn-outline-secondary" id="panelLevelDown" data-tooltip="Als Unterpunkt">
          Tiefer<i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </div>

    <div class="panel-section">
      <button class="btn btn-outline-danger w-100" id="panelDeleteBtn">
        <i class="bi bi-trash me-1"></i>Eintrag löschen
      </button>
    </div>
  </div>
</div>

<!-- Modal: Neuer Eintrag -->
<div class="modal fade" id="newEntryModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Neuen Eintrag anlegen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="form-check trennlinie-switch mb-3">
          <input class="form-check-input" type="checkbox" id="newIstTrennlinie">
          <label class="form-check-label fw-semibold" for="newIstTrennlinie">
            <i class="bi bi-dash-lg me-1"></i>Als Trennlinie anzeigen
          </label>
          <div class="form-text">Eintrag wird im Menü als horizontale Linie gerendert. Titel/Link/Icon werden ignoriert.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Titel <span class="text-danger new-required-mark">*</span></label>
          <input type="text" class="form-control" id="newText" maxlength="50">
        </div>
        <div class="mb-3">
          <label class="form-label">Link / Datei <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="newLink" maxlength="255" placeholder="z.B. home.php">
        </div>
        <div class="mb-3" style="position:relative">
          <label class="form-label">Icon (Bootstrap Icons)</label>
          <div class="input-group icon-picker-wrap">
            <span class="icon-picker-preview" id="newIconPreview"></span>
            <input type="text" class="form-control" id="newIcon" maxlength="50" placeholder="z.B. bi-house">
            <button class="btn btn-outline-secondary icon-picker-toggle" type="button" data-target="newIcon"><i class="bi bi-grid-3x3-gap"></i></button>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Übergeordneter Menüpunkt</label>
          <select class="form-select" id="newParent">
            <option value="0">[Hauptebene]</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-1"></i>Abbrechen
        </button>
        <button type="button" class="btn btn-success" id="btnCreateEntry">
          <i class="bi bi-plus-circle me-1"></i>Hinzufügen
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Löschen bestätigen -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Eintrag löschen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center">
          <i class="bi bi-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>
          <div>
            <strong>Möchtest du <span id="deleteItemName"></span> wirklich löschen?</strong>
            <br><small class="text-muted">Hat der Eintrag Unterpunkte, müssen diese zuerst verschoben oder gelöscht werden.</small>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-1"></i>Abbrechen
        </button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
          <i class="bi bi-trash me-1"></i>Löschen
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
$(function() {

const API = 'nav_api.php';
const csrf = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>';

let allItems = [];
let originalItems = [];
let hasChanges = false;
const MAX_DEPTH = 3;

// Eingeklappte Root-Items, persistiert in localStorage
const COLLAPSE_KEY = 'msv_nav_collapsed_roots';
let collapsedRoots = new Set();
try {
  const stored = JSON.parse(localStorage.getItem(COLLAPSE_KEY) || '[]');
  if (Array.isArray(stored)) collapsedRoots = new Set(stored.map(n => parseInt(n)));
} catch (e) { collapsedRoots = new Set(); }
function persistCollapsed() {
  try { localStorage.setItem(COLLAPSE_KEY, JSON.stringify([...collapsedRoots])); } catch (e) {}
}
function isAncestorCollapsed(item) {
  let pid = item.parent_id;
  while (pid > 0) {
    if (collapsedRoots.has(pid)) return true;
    const p = allItems.find(i => i.id === pid);
    pid = p ? p.parent_id : 0;
  }
  return false;
}
function countDescendants(itemId) {
  let n = 0;
  (function walk(pid) {
    allItems.forEach(it => { if (it.parent_id === pid) { n++; walk(it.id); } });
  })(itemId);
  return n;
}

// ========== API-Helfer (form-encoded, $_POST) ==========
function apiPost(payload) {
  return $.post(API, Object.assign({ csrf_token: csrf }, payload));
}

// ========== Unsaved-Tracking ==========
function setDirty(dirty) {
  hasChanges = dirty;
  $('#unsavedBadge').toggleClass('d-none', !dirty).toggleClass('d-flex', dirty);
}
$(window).on('beforeunload', function() { if (hasChanges) return 'Ungespeicherte Änderungen!'; });

// ========== Hilfsfunktionen ==========
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function buildHierarchy() {
  const map = new Map();
  allItems.forEach(it => map.set(it.id, { ...it, children: [] }));
  map.forEach(it => {
    if (it.parent_id > 0) {
      const p = map.get(it.parent_id);
      if (p) p.children.push(it);
    }
  });
  return map;
}

function flattenTree() {
  const map = buildHierarchy();
  const flat = [];
  function walk(item, level) {
    flat.push({ ...item, level });
    item.children.sort((a, b) => a.sort_order - b.sort_order).forEach(c => walk(c, level + 1));
  }
  const roots = [];
  map.forEach(it => { if (it.parent_id === 0) roots.push(it); });
  roots.sort((a, b) => a.sort_order - b.sort_order).forEach(r => walk(r, 0));
  return flat;
}

function getParentName(parentId) {
  if (!parentId || parentId === 0) return '—';
  const p = allItems.find(i => i.id === parentId);
  return p ? p.text : '—';
}

function getLevelLabel(level) {
  const labels = ['Hauptebene', 'Untermenü', 'Ebene 2', 'Ebene 3'];
  return labels[level] || ('Ebene ' + level);
}

// ========== Tabelle rendern ==========
function renderTable() {
  const flat = flattenTree();
  const $tbody = $('#navTable tbody').empty();

  if (flat.length === 0) {
    $tbody.html('<tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Keine Einträge vorhanden</td></tr>');
    $('#navCount').text('0 Einträge');
    return;
  }

  flat.forEach(item => {
    const hasKids = item.children && item.children.length > 0;
    let iconClass;
    if (item.level === 0) {
      iconClass = hasKids ? 'bi-folder-fill folder' : 'bi-folder folder';
    } else {
      iconClass = hasKids ? 'bi-folder-fill folder' : 'bi-file-earmark file';
    }
    let indentHtml = '';
    for (let lv = 0; lv < item.level; lv++) {
      indentHtml += (lv === item.level - 1) ? '<span class="level-tree"></span>' : '<span class="level-tree-trunk"></span>';
    }
    const iconPrev = item.icon ? '<i class="bi ' + esc(item.icon) + ' me-1"></i>' : '';

    // Collapse-Chevron nur auf Root-Items mit Kindern
    const isCollapsed = collapsedRoots.has(item.id);
    let toggleHtml;
    if (item.level === 0 && hasKids) {
      toggleHtml = '<button class="btn-toggle-collapse" data-root-id="' + item.id + '" type="button" title="' + (isCollapsed ? 'Ausklappen' : 'Einklappen') + '">'
        + '<i class="bi bi-chevron-' + (isCollapsed ? 'right' : 'down') + '"></i></button>';
    } else {
      toggleHtml = '<span class="btn-toggle-placeholder"></span>';
    }
    const collapsedBadge = (item.level === 0 && hasKids && isCollapsed)
      ? '<span class="collapsed-children-count">+' + countDescendants(item.id) + '</span>'
      : '';

    const hidden = isAncestorCollapsed(item);
    const isTrenn = item.ist_trennlinie === 1;

    const titleCell = isTrenn
      ? '<td><div class="level-indent">' + indentHtml + toggleHtml
        + '<span class="trennlinie-preview"><span class="trennlinie-hr"></span>'
        + '<span class="trennlinie-label"><i class="bi bi-dash-lg"></i> Trennlinie'
        + (item.text && item.text !== '— Trennlinie —' ? ' <span class="text-muted small">(' + esc(item.text) + ')</span>' : '')
        + '</span></span></div></td>'
      : '<td><div class="level-indent">' + indentHtml + toggleHtml
        + '<i class="bi ' + iconClass + ' item-icon"></i>'
        + '<span class="h-title">' + iconPrev + esc(item.text) + '</span>' + collapsedBadge + '</div></td>';

    const $tr = $('<tr class="hybrid-row row-l' + item.level + (hidden ? ' row-collapsed' : '') + (isTrenn ? ' row-trennlinie' : '') + '" id="navRow' + item.id + '"'
      + ' data-id="' + item.id + '" data-text="' + esc(item.text) + '" data-link="' + esc(item.link) + '"'
      + ' data-icon="' + esc(item.icon || '') + '" data-parent="' + item.parent_id + '" data-level="' + item.level + '"'
      + ' data-sort="' + item.sort_order + '" data-ist-trennlinie="' + (isTrenn ? '1' : '0') + '"'
      + ' data-has-children="' + (hasKids ? '1' : '0') + '">'
      + '<td><i class="bi bi-grip-vertical drag-grip" data-tooltip="Verschieben"></i></td>'
      + titleCell
      + '<td>' + (isTrenn ? '<span class="text-muted small">—</span>' : '<span class="h-link">' + esc(item.link) + '</span>') + '</td>'
      + '<td class="h-parent">' + (item.parent_id ? esc(getParentName(item.parent_id)) : '<span class="text-muted">—</span>') + '</td>'
      + '<td class="text-center"><div class="level-btns">'
      + '<button class="btn btn-outline-secondary btn-level-up" data-tooltip="Ebene höher" ' + (item.level === 0 ? 'disabled' : '') + '><i class="bi bi-arrow-left"></i></button>'
      + '<button class="btn btn-outline-secondary btn-level-down" data-tooltip="Ebene tiefer" ' + (item.level >= MAX_DEPTH ? 'disabled' : '') + '><i class="bi bi-arrow-right"></i></button>'
      + '<button class="btn btn-outline-secondary btn-duplicate" data-tooltip="Duplizieren"><i class="bi bi-files"></i></button>'
      + '</div></td></tr>');
    $tbody.append($tr);
  });

  const visibleCount = flat.filter(it => !isAncestorCollapsed(it)).length;
  $('#navCount').text(visibleCount < flat.length
    ? visibleCount + ' sichtbar (' + flat.length + ' total)'
    : flat.length + ' Einträge');
  initSortable();
}

// ========== Drag & Drop ==========
function getDescendantIds(itemId) {
  const ids = [];
  (function collect(parentId) {
    allItems.filter(i => i.parent_id === parentId)
      .sort((a, b) => a.sort_order - b.sort_order)
      .forEach(child => { ids.push(child.id); collect(child.id); });
  })(itemId);
  return ids;
}

function initSortable() {
  const el = document.querySelector('#navTable tbody');
  if (!el) return;
  if (el._sortable) el._sortable.destroy();
  el._sortable = Sortable.create(el, {
    animation: 150,
    handle: '.drag-grip',
    ghostClass: 'nav-row-placeholder',
    chosenClass: 'nav-row-dragging',
    onStart(evt) {
      evt.item._collapseSnapshot = new Set(collapsedRoots);
      const movedId = parseInt(evt.item.dataset.id);
      const desc = getDescendantIds(movedId);
      if (desc.length > 0) {
        desc.forEach(id => document.getElementById('navRow' + id)?.classList.add('drag-child-hidden'));
        const badge = document.createElement('span');
        badge.className = 'drag-children-badge';
        badge.textContent = '+ ' + desc.length + ' Unterpunkt' + (desc.length > 1 ? 'e' : '');
        evt.item.querySelector('.h-title, .trennlinie-label')?.appendChild(badge);
      }
    },
    onMove(evt) {
      // Sub-Item ueber eingeklapptem Root -> Root automatisch aufklappen
      const draggedLevel = parseInt(evt.dragged.dataset.level);
      if (draggedLevel === 0) return;
      const related = evt.related;
      if (!related || !related.classList.contains('row-l0')) return;
      const rootId = parseInt(related.dataset.id);
      if (!rootId || !collapsedRoots.has(rootId)) return;
      collapsedRoots.delete(rootId);
      getDescendantIds(rootId).forEach(id => {
        document.getElementById('navRow' + id)?.classList.remove('row-collapsed');
      });
      const chev = related.querySelector('.btn-toggle-collapse i');
      if (chev) { chev.classList.remove('bi-chevron-right'); chev.classList.add('bi-chevron-down'); }
      related.querySelector('.collapsed-children-count')?.remove();
    },
    onEnd(evt) {
      evt.item.querySelector('.drag-children-badge')?.remove();
      document.querySelectorAll('.drag-child-hidden').forEach(r => r.classList.remove('drag-child-hidden'));
      const movedRow = evt.item;
      const movedId = parseInt(movedRow.dataset.id);
      const desc = getDescendantIds(movedId);
      if (desc.length > 0) {
        let after = movedRow;
        desc.forEach(id => {
          const row = document.getElementById('navRow' + id);
          if (row) { after.after(row); after = row; }
        });
      }
      rebuildFromDOM();
      // Ziel-Root bestimmen (bleibt offen)
      const moved = allItems.find(i => i.id === movedId);
      let targetRootId = 0;
      if (moved) {
        if (moved.parent_id === 0) targetRootId = moved.id;
        else {
          let pid = moved.parent_id;
          while (pid > 0) {
            const p = allItems.find(i => i.id === pid);
            if (!p) break;
            targetRootId = p.id;
            pid = p.parent_id;
          }
        }
      }
      const snapshot = evt.item._collapseSnapshot || new Set();
      snapshot.forEach(rid => {
        if (rid !== targetRootId && !collapsedRoots.has(rid)) collapsedRoots.add(rid);
      });
      persistCollapsed();
      setDirty(true);
      renderTable();
      msvToast('Position geändert', 'info');
    }
  });
}

function rebuildFromDOM() {
  const rows = document.querySelectorAll('#navTable tbody .hybrid-row');
  const stack = [];
  rows.forEach((row, idx) => {
    const id = parseInt(row.dataset.id);
    const level = parseInt(row.dataset.level);
    const item = allItems.find(i => i.id === id);
    if (!item) return;
    item.sort_order = idx * 10;
    while (stack.length > 0 && stack[stack.length - 1].level >= level) stack.pop();
    if (level === 0) item.parent_id = 0;
    else if (stack.length > 0) item.parent_id = stack[stack.length - 1].id;
    stack.push({ id, level });
  });
}

// ========== Panel ==========
const NavPanel = {
  currentId: null,
  dirty: false,

  open(tr) {
    if (this.dirty && this.currentId) this.saveCurrent();
    const d = tr.dataset;
    this.currentId = parseInt(d.id);
    this.dirty = false;
    $('#panelText').val(d.text);
    $('#panelLink').val(d.link);
    $('#panelIcon').val(d.icon || '');
    updateIconPreview('panelIcon', d.icon || '');
    this.fillParentSelect(this.currentId);
    $('#panelParent').val(d.parent);
    $('#panelIstTrennlinie').prop('checked', d.istTrennlinie === '1');
    applyTrennlinieMode('panel');
    this.updateLevelDisplay(parseInt(d.level));

    $('.hybrid-row').removeClass('selected');
    $(tr).addClass('selected');

    $('#editPanel').addClass('open');
    $('#panelOverlay').addClass('show');
    setTimeout(() => $('#panelText').focus(), 300);
  },

  close() {
    if (this.dirty && this.currentId) this.saveCurrent();
    $('#editPanel').removeClass('open');
    $('#panelOverlay').removeClass('show');
    $('.hybrid-row').removeClass('selected');
    this.currentId = null;
    this.dirty = false;
  },

  fillParentSelect(excludeId) {
    const $sel = $('#panelParent').empty();
    $sel.append('<option value="0">[Hauptebene]</option>');
    flattenTree().forEach(item => {
      if (item.id === excludeId) return;
      let isChild = false, pid = item.parent_id;
      while (pid > 0) {
        if (pid === excludeId) { isChild = true; break; }
        const p = allItems.find(i => i.id === pid);
        pid = p ? p.parent_id : 0;
      }
      if (isChild) return;
      const indent = '  '.repeat(item.level * 2);
      $sel.append('<option value="' + item.id + '">' + indent + esc(item.text) + '</option>');
    });
  },

  updateLevelDisplay(level) {
    $('#panelLevelDisplay').text('Ebene ' + level + ' – ' + getLevelLabel(level));
    $('#panelLevelUp').prop('disabled', level === 0);
    $('#panelLevelDown').prop('disabled', level >= MAX_DEPTH);
  },

  saveCurrent() {
    const id = this.currentId;
    if (!id) return;
    const item = allItems.find(i => i.id === id);
    if (!item) return;
    const istTrenn = $('#panelIstTrennlinie').prop('checked') ? 1 : 0;
    const text = $('#panelText').val().trim();
    const link = $('#panelLink').val().trim();
    if (!istTrenn && (!text || !link)) { msvToast('Titel und Link sind Pflichtfelder', 'warning'); return; }

    apiPost({
      action: 'update', id,
      text, link,
      icon: $('#panelIcon').val().trim(),
      parent_id: parseInt($('#panelParent').val()) || 0,
      ist_trennlinie: istTrenn
    })
    .done(resp => {
      if (!resp.success) { msvToast(resp.message || 'Fehler', 'error'); return; }
      allItems = normalizeItems(resp.items);
      originalItems = JSON.parse(JSON.stringify(allItems));
      renderTable();
      buildMobileCards();
      msvToast('Gespeichert', 'success');
    })
    .fail(() => msvToast('Fehler beim Speichern', 'error'));

    this.dirty = false;
    setDirty(false);
  }
};

// ========== Panel Events ==========
$(document).on('click', '.hybrid-row', function(e) {
  if ($(e.target).closest('.drag-grip, .btn-level-up, .btn-level-down, .btn-duplicate, .btn-toggle-collapse').length) return;
  NavPanel.open(this);
});
$('#panelClose, #panelOverlay').on('click', () => NavPanel.close());
$(document).on('keydown', e => {
  if (e.key === 'Escape' && $('#editPanel').hasClass('open')) {
    NavPanel.close();
    e.stopImmediatePropagation();
  }
});
$('#panelText, #panelLink, #panelIcon').on('input', () => NavPanel.dirty = true);
$('#panelParent, #panelIstTrennlinie').on('change', () => NavPanel.dirty = true);
$('#panelIstTrennlinie').on('change', () => applyTrennlinieMode('panel'));
$('#newIstTrennlinie').on('change', () => applyTrennlinieMode('new'));

// Trennlinien-Modus: Title/Link/Icon abblenden + Pflichtmarkierung entfernen
function applyTrennlinieMode(prefix) {
  const isTrenn = $('#' + prefix + 'IstTrennlinie').prop('checked');
  const $text = $('#' + prefix + 'Text');
  const $link = $('#' + prefix + 'Link');
  const $icon = $('#' + prefix + 'Icon');
  $text.prop('disabled', false).attr('placeholder', isTrenn ? 'interne Bezeichnung (optional)' : '');
  $link.prop('disabled', isTrenn).val(isTrenn ? '#' : ($link.val() === '#' ? '' : $link.val()));
  $icon.prop('disabled', isTrenn);
  const markClass = prefix === 'panel' ? '.panel-required-mark' : '.new-required-mark';
  $(markClass).toggle(!isTrenn);
}

// ========== Collapse / Expand ==========
$(document).on('click', '.btn-toggle-collapse', function(e) {
  e.stopPropagation();
  const id = parseInt($(this).data('root-id'));
  if (!id) return;
  if (collapsedRoots.has(id)) collapsedRoots.delete(id);
  else collapsedRoots.add(id);
  persistCollapsed();
  renderTable();
});
$('#btnCollapseAll').on('click', function() {
  allItems.forEach(it => {
    if (it.parent_id === 0 && allItems.some(c => c.parent_id === it.id)) collapsedRoots.add(it.id);
  });
  persistCollapsed();
  renderTable();
});
$('#btnExpandAll').on('click', function() {
  collapsedRoots.clear();
  persistCollapsed();
  renderTable();
});

// ========== Duplizieren ==========
$(document).on('click', '.btn-duplicate', function(e) {
  e.stopPropagation();
  const id = parseInt($(this).closest('.hybrid-row').data('id'));
  if (!id) return;
  apiPost({ action: 'duplicate', id })
    .done(resp => {
      if (!resp.success) { msvToast(resp.message || 'Fehler', 'error'); return; }
      allItems = normalizeItems(resp.items);
      originalItems = JSON.parse(JSON.stringify(allItems));
      renderTable();
      buildMobileCards();
      msvToast('Eintrag dupliziert — bitte umbenennen', 'success');
      if (resp.new_id) {
        const newRow = document.getElementById('navRow' + resp.new_id);
        if (newRow) NavPanel.open(newRow);
      }
    })
    .fail(() => msvToast('Fehler beim Duplizieren', 'error'));
});

// ========== Panel Level Buttons ==========
$('#panelLevelUp').on('click', function() {
  if (!NavPanel.currentId) return;
  changeItemLevel(NavPanel.currentId, 'left');
  syncPanelAfterLevelChange();
});
$('#panelLevelDown').on('click', function() {
  if (!NavPanel.currentId) return;
  changeItemLevel(NavPanel.currentId, 'right');
  syncPanelAfterLevelChange();
});
function syncPanelAfterLevelChange() {
  const $row = $('#navRow' + NavPanel.currentId);
  if ($row.length) {
    NavPanel.updateLevelDisplay(parseInt($row.attr('data-level')));
    NavPanel.fillParentSelect(NavPanel.currentId);
    $('#panelParent').val($row.attr('data-parent'));
  }
}

// ========== Inline Level Buttons ==========
$(document).on('click', '.btn-level-up', function(e) {
  e.stopPropagation();
  changeItemLevel(parseInt($(this).closest('.hybrid-row').data('id')), 'left');
});
$(document).on('click', '.btn-level-down', function(e) {
  e.stopPropagation();
  changeItemLevel(parseInt($(this).closest('.hybrid-row').data('id')), 'right');
});

function changeItemLevel(itemId, direction) {
  const item = allItems.find(i => i.id === itemId);
  if (!item) return;
  const flat = flattenTree();
  const flatItem = flat.find(f => f.id === itemId);
  if (!flatItem) return;
  const currentLevel = flatItem.level;
  const idx = flat.indexOf(flatItem);

  if (direction === 'left') {
    if (currentLevel === 0) { msvToast('Bereits auf Hauptebene', 'info'); return; }
    const parent = allItems.find(i => i.id === item.parent_id);
    if (parent) {
      item.parent_id = parent.parent_id || 0;
      setDirty(true); renderTable();
      msvToast('Eine Ebene höher', 'success');
    }
  } else {
    if (currentLevel >= MAX_DEPTH) { msvToast('Max. Tiefe erreicht', 'warning'); return; }
    let prev = null;
    for (let i = idx - 1; i >= 0; i--) {
      if (flat[i].level <= currentLevel) { prev = flat[i]; break; }
    }
    if (!prev) { msvToast('Kein Element darüber', 'info'); return; }
    item.parent_id = prev.id;
    setDirty(true); renderTable();
    msvToast('Unterpunkt von "' + prev.text + '"', 'success');
  }
}

// ========== Daten laden ==========
function normalizeItems(items) {
  return (items || []).map(i => ({
    id: parseInt(i.ID),
    text: i.Text,
    link: i.Link,
    icon: i.Icon || '',
    parent_id: parseInt(i.ParentID) || 0,
    sort_order: parseInt(i.SortOrder) || 0,
    ist_trennlinie: parseInt(i.IstTrennlinie) ? 1 : 0
  }));
}

function showSkeleton() {
  const row = '<tr><td><div class="skeleton" style="width:20px"></div></td><td><div class="skeleton" style="width:70%"></div></td><td><div class="skeleton" style="width:60%"></div></td><td><div class="skeleton" style="width:50%"></div></td><td><div class="skeleton" style="width:80px"></div></td></tr>';
  $('#navTable tbody').html(row.repeat(5));
}

function loadNavItems() {
  NavPanel.close();
  showSkeleton();
  $.getJSON(API, { action: 'list' })
    .done(resp => {
      if (!resp.success) { msvToast(resp.message || 'Fehler', 'error'); return; }
      allItems = normalizeItems(resp.items);
      originalItems = JSON.parse(JSON.stringify(allItems));
      // Erstaufruf (keine localStorage-Praeferenz) -> alle Roots mit Kindern einklappen
      if (localStorage.getItem(COLLAPSE_KEY) === null) {
        allItems.forEach(it => {
          if (it.parent_id === 0 && allItems.some(c => c.parent_id === it.id)) collapsedRoots.add(it.id);
        });
        persistCollapsed();
      }
      renderTable();
      buildMobileCards();
      setDirty(false);
    })
    .fail(() => {
      $('#navTable tbody').html('<tr><td colspan="5" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden</td></tr>');
    });
}

// ========== Speichern (Batch) ==========
$('#btnSaveAll').on('click', function() {
  const updates = [];
  allItems.forEach(item => {
    const orig = originalItems.find(o => o.id === item.id);
    if (orig && (orig.parent_id !== item.parent_id || orig.sort_order !== item.sort_order)) {
      updates.push({ id: item.id, parent_id: item.parent_id, sort_order: item.sort_order });
    }
  });
  if (updates.length === 0) { msvToast('Keine Änderungen zu speichern', 'info'); setDirty(false); return; }

  const $btn = $(this), txt = $btn.html();
  $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

  apiPost({ action: 'batch_update', updates: JSON.stringify(updates) })
    .done(resp => {
      if (!resp.success) { msvToast(resp.message || 'Fehler', 'error'); return; }
      allItems = normalizeItems(resp.items);
      originalItems = JSON.parse(JSON.stringify(allItems));
      renderTable();
      buildMobileCards();
      setDirty(false);
      msvToast(updates.length + ' Änderungen gespeichert!', 'success');
    })
    .fail(() => msvToast('Fehler beim Speichern', 'error'))
    .always(() => $btn.prop('disabled', false).html(txt));
});

// ========== Neuer Eintrag ==========
$('#btnAddEntry').on('click', function() {
  $('#newText, #newLink, #newIcon').val('');
  updateIconPreview('newIcon', '');
  $('#newIstTrennlinie').prop('checked', false);
  applyTrennlinieMode('new');
  const $sel = $('#newParent').empty();
  $sel.append('<option value="0">[Hauptebene]</option>');
  flattenTree().forEach(item => {
    const indent = '  '.repeat(item.level * 2);
    $sel.append('<option value="' + item.id + '">' + indent + esc(item.text) + '</option>');
  });
  new bootstrap.Modal(document.getElementById('newEntryModal')).show();
});
$('#newEntryModal').on('shown.bs.modal', () => $('#newText').focus());

$('#btnCreateEntry').on('click', function() {
  const istTrenn = $('#newIstTrennlinie').prop('checked') ? 1 : 0;
  const text = $('#newText').val().trim();
  const link = $('#newLink').val().trim();
  if (!istTrenn && (!text || !link)) { msvToast('Titel und Link sind Pflichtfelder', 'warning'); return; }

  const $btn = $(this), txt = $btn.html();
  $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Erstelle...');

  apiPost({
    action: 'create',
    text, link,
    icon: $('#newIcon').val().trim(),
    parent_id: parseInt($('#newParent').val()) || 0,
    ist_trennlinie: istTrenn
  })
  .done(resp => {
    if (!resp.success) { msvToast(resp.message || 'Fehler', 'error'); return; }
    bootstrap.Modal.getInstance(document.getElementById('newEntryModal'))?.hide();
    allItems = normalizeItems(resp.items);
    originalItems = JSON.parse(JSON.stringify(allItems));
    renderTable();
    buildMobileCards();
    msvToast('Eintrag erstellt!', 'success');
  })
  .fail(() => msvToast('Fehler beim Erstellen', 'error'))
  .always(() => $btn.prop('disabled', false).html(txt));
});

// ========== Löschen ==========
let deleteId = null;
$('#panelDeleteBtn').on('click', function() {
  if (!NavPanel.currentId) return;
  deleteId = NavPanel.currentId;
  const item = allItems.find(i => i.id === deleteId);
  $('#deleteItemName').text(item ? '"' + item.text + '"' : 'diesen Eintrag');
  NavPanel.currentId = null;
  NavPanel.dirty = false;
  NavPanel.close();
  new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
});
$('#confirmDeleteBtn').on('click', function() {
  if (!deleteId) return;
  const $btn = $(this), txt = $btn.html();
  $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');

  apiPost({ action: 'delete', id: deleteId })
    .done(resp => {
      if (!resp.success) { msvToast(resp.message || 'Fehler', 'error'); return; }
      bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'))?.hide();
      allItems = normalizeItems(resp.items);
      originalItems = JSON.parse(JSON.stringify(allItems));
      renderTable();
      buildMobileCards();
      msvToast('Eintrag gelöscht', 'success');
    })
    .fail(() => msvToast('Fehler beim Löschen', 'error'))
    .always(() => { $btn.prop('disabled', false).html(txt); deleteId = null; });
});

// ========== Aktualisieren ==========
$('#btnRefresh').on('click', function() {
  if (hasChanges && !confirm('Ungespeicherte Änderungen gehen verloren. Fortfahren?')) return;
  loadNavItems();
  msvToast('Aktualisiert', 'info');
});

// ========== Mobile Cards ==========
function buildMobileCards() {
  const flat = flattenTree();
  const $scroll = $('#mobileNavCards .mobile-cards-scroll').empty();

  flat.forEach(item => {
    const isTrenn = item.ist_trennlinie === 1;
    const hasKids = item.children && item.children.length > 0;
    const iconClass = isTrenn ? 'bi-dash-lg text-muted' : (hasKids ? 'bi-folder-fill text-warning' : (item.icon ? esc(item.icon) + ' text-secondary' : 'bi-file-earmark text-muted'));
    const indent = ' '.repeat(item.level * 4);
    const title = isTrenn ? '— Trennlinie —' : esc(item.text);

    const card = $(
      '<div class="mobile-card" data-id="' + item.id + '" data-search="' + esc((item.text + ' ' + item.link).toLowerCase()) + '">'
      + '<div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">'
      + '<div><div class="fw-bold"><i class="bi ' + iconClass + ' me-1"></i>' + indent + title + '</div>'
      + '<small class="text-muted">' + (isTrenn ? 'Trennlinie' : esc(item.link)) + ' · Ebene ' + item.level + '</small></div>'
      + '<i class="bi bi-chevron-down"></i></div>'
      + '<div class="mobile-card-body p-3">'
      + '<div class="d-flex gap-2">'
      + '<button class="btn btn-outline-secondary btn-sm flex-fill mobile-dup-nav" data-id="' + item.id + '"><i class="bi bi-files me-1"></i>Duplizieren</button>'
      + '<button class="btn btn-outline-danger btn-sm flex-fill mobile-delete-nav" data-id="' + item.id + '"><i class="bi bi-trash me-1"></i>Löschen</button>'
      + '</div></div></div>'
    );
    $scroll.append(card);
  });
}

window.filterMobileNav = function(query) {
  const q = query.toLowerCase();
  $('#mobileNavCards .mobile-card').each(function() {
    $(this).toggle(($(this).data('search') + '').includes(q));
  });
};

$(document).on('click', '.mobile-delete-nav', function() {
  deleteId = $(this).data('id');
  const item = allItems.find(i => i.id === deleteId);
  $('#deleteItemName').text(item ? '"' + item.text + '"' : 'diesen Eintrag');
  new bootstrap.Modal(document.getElementById('confirmDeleteModal')).show();
});
$(document).on('click', '.mobile-dup-nav', function() {
  const id = $(this).data('id');
  apiPost({ action: 'duplicate', id })
    .done(resp => {
      if (!resp.success) { msvToast(resp.message || 'Fehler', 'error'); return; }
      allItems = normalizeItems(resp.items);
      originalItems = JSON.parse(JSON.stringify(allItems));
      renderTable();
      buildMobileCards();
      msvToast('Eintrag dupliziert', 'success');
    })
    .fail(() => msvToast('Fehler beim Duplizieren', 'error'));
});

// ========== Shortcuts ==========
$(document).on('keydown', function(e) {
  if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); $('#btnSaveAll').click(); }
  if ((e.ctrlKey || e.metaKey) && e.key === 'n') { e.preventDefault(); $('#btnAddEntry').click(); }
});

// ========== Icon-Picker ==========
const ICON_LIST = [
  {name:'bi-house',label:'Haus'},{name:'bi-house-fill',label:'Haus gef.'},
  {name:'bi-menu-button-wide',label:'Menü'},{name:'bi-list',label:'Liste'},
  {name:'bi-grid',label:'Raster'},{name:'bi-grid-3x3-gap',label:'Raster 3x3'},
  {name:'bi-search',label:'Suche'},{name:'bi-filter',label:'Filter'},
  {name:'bi-sliders',label:'Regler'},{name:'bi-gear',label:'Zahnrad'},
  {name:'bi-gear-fill',label:'Zahnrad gef.'},{name:'bi-wrench',label:'Werkzeug'},
  {name:'bi-people',label:'Personen'},{name:'bi-people-fill',label:'Personen gef.'},
  {name:'bi-person',label:'Person'},{name:'bi-person-fill',label:'Person gef.'},
  {name:'bi-person-plus',label:'Person +'},{name:'bi-person-badge',label:'Ausweis'},
  {name:'bi-person-lines-fill',label:'Personenliste'},{name:'bi-person-lock',label:'Gesperrt'},
  {name:'bi-building',label:'Gebäude'},{name:'bi-buildings',label:'Gebäude 2'},
  {name:'bi-shop',label:'Laden'},{name:'bi-geo-alt',label:'Standort'},
  {name:'bi-pin-map',label:'Karte Pin'},{name:'bi-signpost',label:'Wegweiser'},
  {name:'bi-file-earmark',label:'Datei'},{name:'bi-file-earmark-text',label:'Textdatei'},
  {name:'bi-file-earmark-pdf',label:'PDF'},{name:'bi-file-earmark-spreadsheet',label:'Tabelle'},
  {name:'bi-folder',label:'Ordner'},{name:'bi-folder-fill',label:'Ordner gef.'},
  {name:'bi-clipboard',label:'Klemmbrett'},{name:'bi-clipboard-data',label:'Daten'},
  {name:'bi-journal-text',label:'Journal'},{name:'bi-book',label:'Buch'},
  {name:'bi-table',label:'Tabelle'},{name:'bi-layout-text-sidebar',label:'Sidebar'},
  {name:'bi-card-checklist',label:'Checkliste'},{name:'bi-card-list',label:'Kartenliste'},
  {name:'bi-list-check',label:'Häkchenliste'},{name:'bi-list-ol',label:'Nummerierte Liste'},
  {name:'bi-plus-circle',label:'Hinzufügen'},{name:'bi-pencil-square',label:'Bearbeiten'},
  {name:'bi-trash',label:'Löschen'},{name:'bi-download',label:'Download'},
  {name:'bi-upload',label:'Upload'},{name:'bi-cloud-upload',label:'Cloud Up'},
  {name:'bi-cloud-download',label:'Cloud Down'},{name:'bi-printer',label:'Drucker'},
  {name:'bi-send',label:'Senden'},{name:'bi-share',label:'Teilen'},
  {name:'bi-check-circle',label:'Häkchen'},{name:'bi-x-circle',label:'X Kreis'},
  {name:'bi-exclamation-triangle',label:'Warnung'},{name:'bi-info-circle',label:'Info'},
  {name:'bi-question-circle',label:'Frage'},{name:'bi-bell',label:'Glocke'},
  {name:'bi-shield-check',label:'Schutz'},{name:'bi-lock',label:'Schloss'},
  {name:'bi-trophy',label:'Pokal'},{name:'bi-trophy-fill',label:'Pokal gef.'},
  {name:'bi-award',label:'Auszeichnung'},{name:'bi-bullseye',label:'Zielscheibe'},
  {name:'bi-crosshair',label:'Fadenkreuz'},{name:'bi-flag',label:'Flagge'},
  {name:'bi-star',label:'Stern'},{name:'bi-star-fill',label:'Stern gef.'},
  {name:'bi-mortarboard',label:'Mütze'},{name:'bi-diagram-2',label:'Diagramm'},
  {name:'bi-calendar',label:'Kalender'},{name:'bi-calendar-event',label:'Termin'},
  {name:'bi-calendar-check',label:'Kal. Häkchen'},{name:'bi-clock',label:'Uhr'},
  {name:'bi-bar-chart',label:'Balken'},{name:'bi-bar-chart-line',label:'Linien'},
  {name:'bi-graph-up',label:'Trend'},{name:'bi-pie-chart',label:'Torte'},
  {name:'bi-speedometer2',label:'Tacho'},{name:'bi-activity',label:'Aktivität'},
  {name:'bi-envelope',label:'Brief'},{name:'bi-chat-dots',label:'Chat'},
  {name:'bi-telephone',label:'Telefon'},{name:'bi-megaphone',label:'Megafon'},
  {name:'bi-arrow-right-circle',label:'Pfeil'},{name:'bi-box-arrow-up-right',label:'Extern'},
  {name:'bi-link-45deg',label:'Link'},{name:'bi-arrow-repeat',label:'Wiederh.'},
  {name:'bi-tag',label:'Tag'},{name:'bi-tags',label:'Tags'},
  {name:'bi-cart',label:'Warenkorb'},{name:'bi-key',label:'Schlüssel'},
  {name:'bi-eye',label:'Auge'},{name:'bi-eye-slash',label:'Auge aus'},
  {name:'bi-image',label:'Bild'},{name:'bi-camera',label:'Kamera'},
  {name:'bi-qr-code',label:'QR-Code'},{name:'bi-tools',label:'Werkzeuge'},
  {name:'bi-map',label:'Karte'},{name:'bi-hash',label:'Hash'},
  {name:'bi-lightning',label:'Blitz'},{name:'bi-heart',label:'Herz'},
  {name:'bi-database',label:'Datenbank'},{name:'bi-server',label:'Server'},
  {name:'bi-hdd',label:'Festplatte'},{name:'bi-cash-stack',label:'Geld'},
  {name:'bi-receipt',label:'Quittung'},{name:'bi-arrow-left-right',label:'Austausch'}
];

let activePickerTarget = null;
let $pickerDropdown = null;

function createPickerDropdown() {
  if ($pickerDropdown) return $pickerDropdown;
  $pickerDropdown = $('<div class="icon-picker-dropdown" id="iconPickerDropdown">'
    + '<input type="text" class="icon-picker-search" placeholder="Icon suchen...">'
    + '<div class="icon-picker-grid"></div></div>');
  $('body').append($pickerDropdown);

  const $grid = $pickerDropdown.find('.icon-picker-grid');
  ICON_LIST.forEach(ic => {
    $grid.append('<div class="icon-picker-item" data-icon="' + ic.name + '" title="' + ic.name + '">'
      + '<i class="bi ' + ic.name + '"></i><span>' + esc(ic.label) + '</span></div>');
  });

  $pickerDropdown.find('.icon-picker-search').on('input', function() {
    const q = this.value.toLowerCase();
    $grid.find('.icon-picker-item').each(function() {
      const match = this.dataset.icon.toLowerCase().includes(q)
        || this.querySelector('span').textContent.toLowerCase().includes(q);
      this.style.display = match ? '' : 'none';
    });
  });

  $pickerDropdown.on('click', '.icon-picker-item', function() {
    if (!activePickerTarget) return;
    const icon = this.dataset.icon;
    $('#' + activePickerTarget).val(icon).trigger('input');
    updateIconPreview(activePickerTarget, icon);
    closePicker();
  });

  return $pickerDropdown;
}

function updateIconPreview(inputId, val) {
  const $preview = $('#' + inputId + 'Preview');
  if (!$preview.length) return;
  $preview.html(val ? '<i class="bi ' + esc(val) + '"></i>' : '');
}

function openPicker(targetId, $btn) {
  const $dd = createPickerDropdown();
  activePickerTarget = targetId;
  const r = $btn[0].getBoundingClientRect();
  $dd.css({
    top: r.bottom + window.scrollY + 4,
    left: Math.min(r.right - 320, window.innerWidth - 330) + window.scrollX
  });
  const $modal = $btn.closest('.modal');
  if ($modal.length) {
    const inst = bootstrap.Modal.getInstance($modal[0]);
    if (inst && inst._focustrap) inst._focustrap.deactivate();
  }
  const current = $('#' + targetId).val().trim();
  $dd.find('.icon-picker-item').removeClass('selected').show();
  if (current) $dd.find('.icon-picker-item[data-icon="' + current + '"]').addClass('selected');
  $dd.find('.icon-picker-search').val('');
  $dd.addClass('show');
  setTimeout(() => $dd.find('.icon-picker-search').focus(), 50);
}

function closePicker() {
  if (activePickerTarget) {
    const $modal = $('#' + activePickerTarget).closest('.modal');
    if ($modal.length) {
      const inst = bootstrap.Modal.getInstance($modal[0]);
      if (inst && inst._focustrap) inst._focustrap.activate();
    }
  }
  if ($pickerDropdown) $pickerDropdown.removeClass('show');
  activePickerTarget = null;
}

$(document).on('click', '.icon-picker-toggle', function(e) {
  e.stopPropagation();
  const id = $(this).data('target');
  if ($pickerDropdown && $pickerDropdown.hasClass('show') && activePickerTarget === id) closePicker();
  else openPicker(id, $(this));
});
$(document).on('mousedown', function(e) {
  if ($pickerDropdown && $pickerDropdown.hasClass('show')
    && !$(e.target).closest('#iconPickerDropdown, .icon-picker-toggle').length) closePicker();
});
$('#panelIcon').on('input', function() { updateIconPreview('panelIcon', this.value.trim()); });
$('#newIcon').on('input', function() { updateIconPreview('newIcon', this.value.trim()); });

// Tooltips → global via msv-tooltips.js

// ========== Nav-Links fixen (Seite liegt in /admin/, Links zeigen auf /inc/) ==========
document.querySelectorAll('.navbar a[href], .offcanvas-nav a[href], #logoutModal a[href]').forEach(a => {
  const href = a.getAttribute('href');
  if (href && !href.startsWith('http') && !href.startsWith('/') && !href.startsWith('#') && !href.startsWith('../') && !href.startsWith('javascript')) {
    a.setAttribute('href', '../inc/' + href);
  }
});

// ========== Start ==========
loadNavItems();

});
</script>

<?php
chdir(__DIR__ . '/../inc');
include 'footer.inc.php';
?>
