<?php

/* =============================================
 * FILE: /admin/nav_admin.php
 * PURPOSE: Bootstrap-UI mit Drag&Drop zum Verwalten der Navigation
 * REQUIRES: ../inc/dbconnect.inc.php liefert $conn (mysqli)
 * SECURITY: nur User-ID 1
 * ============================================= */
if (session_status() === PHP_SESSION_NONE) session_start();

// Nur User mit ID 1 ist Admin
if (!function_exists('user_can_manage_navigation')) {

    function user_can_manage_navigation(): bool {
        return (int)($_SESSION['user_id'] ?? 0) === 1;
    }
}

// DB verbinden
require_once __DIR__ . '/../inc/dbconnect.inc.php';

// HARTE SPERRE für Nicht-Admins
if (!user_can_manage_navigation()) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><div style="margin:2rem;font-family:system-ui">Kein Zugriff</div>';
    exit;
}

// CSRF-Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$csrf = $_SESSION['csrf_token'];
?>

<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Navigation verwalten</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body { background:#f8f9fa; padding-top: 70px; }

    /* Header Bar */
    .admin-header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      background: white;
      border-bottom: 1px solid #dee2e6;
      z-index: 1030;
      padding: 15px 0;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .nav-container { max-width: 1200px; margin: 0 auto; }

    /* Tree Styles für Drag & Drop */
    .tree-container {
      background: white;
      border-radius: 8px;
      padding: 20px;
      min-height: 400px;
    }
    .nav-group {
      margin-bottom: 5px;
    }
    .nav-item {
      padding: 12px 15px;
      margin: 3px 0;
      background: #fff;
      border: 1px solid #dee2e6;
      border-radius: 6px;
      cursor: move;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      position: relative;
    }
    .nav-item:hover {
      background: #f8f9fa;
      border-color: #6c757d;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .nav-item.dragging {
      opacity: 0.4;
    }
    .nav-item.drag-over {
      background: #d4edda;
      border-color: #28a745;
    }

    /* Level Indentation */
    .level-0 { margin-left: 0; }
    .level-1 { margin-left: 40px; background: #f8f9fa; }
    .level-2 { margin-left: 80px; background: #f1f3f5; }
    .level-3 { margin-left: 120px; background: #e9ecef; }
    .drag-handle {
      cursor: grab;
      color: #adb5bd;
      margin-right: 12px;
      font-size: 1.1rem;
    }
    .drag-handle:active { cursor: grabbing; }
    .nav-content {
      flex: 1;
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .nav-text {
      font-weight: 500;
      color: #212529;
      min-width: 150px;
    }
    .nav-link-url {
      color: #6c757d;
      font-size: 0.85rem;
      font-family: 'Courier New', monospace;
      background: #f8f9fa;
      padding: 2px 8px;
      border-radius: 4px;
    }
    .nav-actions {
      display: flex;
      gap: 5px;
    }
    .badge-parent {
      font-size: 0.75rem;
      background: #6c757d;
    }
    .sortable-ghost {
      opacity: 0.4;
      background: #e3f2fd !important;
    }
    .sortable-chosen {
      background: #fff3cd !important;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #6c757d;
    }
    .empty-state i {
      font-size: 3rem;
      margin-bottom: 15px;
      color: #dee2e6;
    }
  </style>

</head>
<body>
<!-- Fixed Header -->
<div class="admin-header">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-3">
        <a href="https://jahresmeisterschaft.msvwilen.ch/inc/home.php" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left me-2"></i>Zurück zur Startseite
        </a>
        <h1 class="h4 m-0">
          <i class="bi bi-menu-button-wide me-2"></i>Navigation verwalten
        </h1>
      </div>
      <div>
        <button id="btnSaveOrder" class="btn btn-success" style="display:none;">
          <i class="bi bi-check-circle me-2"></i>Reihenfolge speichern
        </button>
        <button id="btnCancelOrder" class="btn btn-outline-danger" style="display:none;">
          <i class="bi bi-x-circle me-2"></i>Verwerfen
        </button>
        <button id="btnAdd" class="btn btn-primary">
          <i class="bi bi-plus-circle me-2"></i>Neuer Eintrag
        </button>
        <button id="btnRefresh" class="btn btn-outline-secondary" title="Aktualisieren">
          <i class="bi bi-arrow-clockwise"></i>
        </button>
      </div>
    </div>
  </div>
</div>
<!-- Main Content -->
<div class="container py-4">
  <div class="nav-container">
    <!-- Info Alert -->
    <div class="alert alert-info alert-dismissible fade show" role="alert">
      <i class="bi bi-lightbulb me-2"></i>
      <strong>Einfache Menü-Verwaltung</strong>
      <div class="mt-2">
        <strong>So funktioniert's:</strong>
        <ul class="mb-0 mt-1">
          <li>ðŸ”½ <strong>Verschieben:</strong> Ziehe am â‰¡ Symbol für die Reihenfolge</li>
          <li>â¬…ï¸ <strong>Pfeil links:</strong> Eine Ebene höher verschieben</li>
          <li>âž¡ï¸ <strong>Pfeil rechts:</strong> Als Unterpunkt des Elements darüber</li>
        </ul>
        <div class="mt-2 alert alert-warning mb-0">
          <small><strong>Wichtig:</strong> Änderungen werden erst gespeichert, wenn du oben auf "Reihenfolge speichern" klickst!</small>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <!-- Unsaved Changes Warning -->
    <div class="alert alert-warning" id="unsavedWarning" style="display:none;">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <strong>Ungespeicherte Änderungen!</strong> 
      Klicke auf "Reihenfolge speichern" um die Änderungen zu übernehmen oder "Verwerfen" um sie rückgängig zu machen.
    </div>
    <!-- Navigation Tree -->
    <div class="card shadow-sm">
      <div class="card-header bg-white">
        <h5 class="card-title m-0">Navigationsstruktur</h5>
      </div>
      <div class="card-body">
        <div class="tree-container">
          <ul id="navTree" class="sortable-list">
            <!-- Wird dynamisch gefüllt -->
          </ul>
          <div class="empty-state" id="emptyState" style="display:none;">
            <i class="bi bi-inbox"></i>
            <p>Keine Navigationseinträge vorhanden</p>
            <button class="btn btn-primary btn-sm" onclick="$('#btnAdd').click()">
              <i class="bi bi-plus-circle me-2"></i>Ersten Eintrag erstellen
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="editForm">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-pencil-square me-2"></i>
            <span id="modalTitle">Eintrag bearbeiten</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id" id="fieldId">
          <div class="mb-3">
            <label class="form-label">Titel<span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="text" id="fieldText" maxlength="30" required>
            <div class="form-text">Max. 30 Zeichen</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Link / Datei<span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="link" id="fieldLink" maxlength="255" 
                   placeholder="z.B. home.php oder /ordner/seite.php" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Übergeordneter Menüpunkt</label>
            <select class="form-select" name="parent_id" id="fieldParent">
              <option value="0">[Hauptebene]</option>
            </select>
            <div class="form-text">Wähle wo dieser Eintrag eingeordnet werden soll</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-2"></i>Speichern
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="deleteForm">
        <div class="modal-header">
          <h5 class="modal-title text-danger">
            <i class="bi bi-trash3 me-2"></i>Eintrag löschen
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id" id="delId">
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Möchtest du <strong id="delText"></strong> wirklich löschen?
          </div>
          <p class="text-muted small mb-0">
            Hinweis: Hat der Eintrag Untermenüs, müssen diese zuerst verschoben oder gelöscht werden.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash3 me-2"></i>Löschen
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Scripts -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../inc/js/msv-toast.js"></script>

<script>
const API = 'nav_api.php';
const csrf = '<?= htmlspecialchars($csrf) ?>';
let allItems = [];
let originalItems = []; // Backup der Original-Daten
let hasUnsavedChanges = false; // Track ob es ungespeicherte Änderungen gibt
let sortable = null;
let modal, delModal;
let draggedItem = null;
let currentIndentLevel = 0;

// WordPress-Style Indentation Settings
const INDENT_WIDTH = 40; // Pixel pro Ebene
const MAX_DEPTH = 3; // Maximale Verschachtelungstiefe

// Warnung vor dem Verlassen der Seite bei ungespeicherten Änderungen
window.addEventListener('beforeunload', function (e) {
  if (hasUnsavedChanges) {
    e.preventDefault();
    e.returnValue = '';
  }
});

// Zeige/Verstecke Speichern-Buttons
function showSaveButtons(show = true) {
  if (show) {
    $('#btnSaveOrder').show();
    $('#btnCancelOrder').show();
    $('#unsavedWarning').show();
    hasUnsavedChanges = true;
  } else {
    $('#btnSaveOrder').hide();
    $('#btnCancelOrder').hide();
    $('#unsavedWarning').hide();
    hasUnsavedChanges = false;
  }
}

// Items laden
function loadItems() {
  return $.getJSON(API, { action: 'list' })
    .then(res => {
      if (!res.success) throw new Error(res.message || 'Fehler beim Laden');

      // Konvertiere IDs zu Integers
      allItems = (res.items || []).map(item => ({
        ...item,
        ID: parseInt(item.ID),
        ParentID: parseInt(item.ParentID) || 0,
        SortOrder: parseInt(item.SortOrder) || 0
      }));

      // Backup für Cancel-Funktion
      originalItems = JSON.parse(JSON.stringify(allItems));
      console.log('Loaded items:', allItems);
      renderList();
      fillParentSelect();
      showSaveButtons(false);
    })
    .catch(err => {
      console.error('Load error:', err);
      msvToast('Fehler beim Laden: ' + err.message, 'error');
    });
}

// WordPress-Style List rendern
function renderList() {
  const list = $('#navTree').empty();
  if (!allItems || allItems.length === 0) {
    $('#emptyState').show();
    return;
  }
  $('#emptyState').hide();

  // Items in Map umwandeln für schnellen Zugriff
  const itemMap = new Map();
  allItems.forEach(item => {
    itemMap.set(parseInt(item.ID), {
      ...item,
      ID: parseInt(item.ID),
      ParentID: parseInt(item.ParentID) || 0,
      children: []
    });
  });

  // Hierarchie aufbauen
  itemMap.forEach(item => {
    if (item.ParentID > 0) {
      const parent = itemMap.get(item.ParentID);
      if (parent) {
        parent.children.push(item);
      }
    }
  });

  // Flache Liste mit korrekten Levels erstellen
  const flatList = [];

  function buildFlatList(item, level = 0) {

    // Item zur Liste hinzufügen
    flatList.push({
      ...item,
      level: level
    });

    // Kinder sortieren und rekursiv hinzufügen
    if (item.children && item.children.length > 0) {
      item.children
        .sort((a, b) => (a.SortOrder || 0) - (b.SortOrder || 0))
        .forEach(child => buildFlatList(child, level + 1));
    }
  }

  // Nur Root-Items (ParentID = 0) durchgehen
  const rootItems = [];
  itemMap.forEach(item => {
    if (item.ParentID === 0) {
      rootItems.push(item);
    }
  });

  // Root-Items sortieren und verarbeiten
  rootItems
    .sort((a, b) => (a.SortOrder || 0) - (b.SortOrder || 0))
    .forEach(item => buildFlatList(item, 0));

  // Debug-Info
  console.log('Flat list with levels:', flatList.map(i => ({
    id: i.ID,
    text: i.Text,
    parent: i.ParentID,
    level: i.level
  })));

  // Render alle Items mit korrekter Einrückung
  flatList.forEach((item, index) => {
    const hasChildren = item.children && item.children.length > 0;

    // WICHTIG: Inline-Style für garantierte Einrückung
    const marginLeft = item.level * 40;
    const listItem = $(`
      <li class="sortable-item indent-${item.level}" 
          data-id="${item.ID}" 
          data-parent="${item.ParentID}"
          data-level="${item.level}"
          data-index="${index}"
          style="margin-left: ${marginLeft}px !important; padding-left: 0 !important;">
        <div class="nav-item">
          <i class="bi bi-grip-vertical drag-handle"></i>
          <div class="nav-content">
            <span class="nav-text">
              ${hasChildren ? '<i class="bi bi-folder me-2"></i>' : '<i class="bi bi-file-earmark me-2"></i>'}
              ${escapeHtml(item.Text)}
            </span>
            <span class="nav-link-url">${escapeHtml(item.Link)}</span>
          </div>
          <div class="nav-actions">
            <button class="btn btn-sm btn-outline-secondary btn-level-up" 
                    title="Eine Ebene höher" 
                    ${item.level === 0 ? 'disabled' : ''}>
              <i class="bi bi-arrow-left"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary btn-level-down" 
                    title="Als Unterpunkt">
              <i class="bi bi-arrow-right"></i>
            </button>
            <button class="btn btn-sm btn-outline-primary btn-edit" title="Bearbeiten">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger btn-delete" title="Löschen">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>
        <div class="drop-indicator"></div>
      </li>
    `);
    list.append(listItem);
  });

  // SortableJS initialisieren
  initWordPressSortable();
}

// WordPress-Style Sortable - EINFACHE VERSION NUR FÜR REIHENFOLGE
function initWordPressSortable() {
  const navTree = document.getElementById('navTree');
  if (!navTree) return;
  sortable = Sortable.create(navTree, {
    animation: 150,
    handle: '.drag-handle',
    ghostClass: 'sortable-ghost',
    chosenClass: 'sortable-chosen',
    onEnd: function(evt) {
      const itemId = parseInt(evt.item.dataset.id);
      const newIndex = evt.newIndex;
      const oldIndex = evt.oldIndex;
      if (oldIndex === newIndex) {
        return; // Keine Änderung
      }

      // Behalte die aktuelle Ebene bei
      const currentLevel = parseInt(evt.item.dataset.level) || 0;
      const currentParent = parseInt(evt.item.dataset.parent) || 0;

      // Update nur die Position, nicht die Hierarchie
      updateItemPositionLocally(itemId, currentParent, newIndex);
      showSaveButtons(true);
      msvToast('Position geändert - nicht vergessen zu speichern!', 'info');
    }
  });
}

// Ebene ändern (hoch/runter)
function changeItemLevel(itemId, direction) {
  const item = allItems.find(i => i.ID === itemId);
  if (!item) return;
  const itemElement = document.querySelector(`.sortable-item[data-id="${itemId}"]`);
  const currentLevel = parseInt(itemElement.dataset.level) || 0;
  const currentIndex = Array.from(document.querySelectorAll('.sortable-item')).indexOf(itemElement);
  if (direction === 'left') {

    // Eine Ebene höher (Parent des Parents)
    if (currentLevel === 0) {
      msvToast('Bereits auf Hauptebene', 'info');
      return;
    }

    // Finde den Parent des aktuellen Parents
    const currentParent = allItems.find(i => i.ID === item.ParentID);
    if (currentParent) {
      item.ParentID = currentParent.ParentID || 0;
      itemElement.dataset.parent = item.ParentID;
      itemElement.dataset.level = currentLevel - 1;
      itemElement.style.marginLeft = ((currentLevel - 1) * INDENT_WIDTH) + 'px';
      msvToast('Eine Ebene höher verschoben', 'success');
      updateItemPositionLocally(itemId, item.ParentID, currentIndex);
      showSaveButtons(true);
    }
  } else if (direction === 'right') {

    // Eine Ebene tiefer (als Unterpunkt des vorherigen Elements)
    if (currentLevel >= MAX_DEPTH) {
      msvToast('Maximale Verschachtelungstiefe erreicht', 'warning');
      return;
    }

    // Finde das Element direkt darüber
    const prevElement = itemElement.previousElementSibling;
    if (!prevElement) {
      msvToast('Kein Element darüber vorhanden', 'info');
      return;
    }
    const prevId = parseInt(prevElement.dataset.id);
    const prevLevel = parseInt(prevElement.dataset.level) || 0;

    // Kann nur Unterpunkt werden wenn das Element darüber nicht schon zu tief verschachtelt ist
    if (prevLevel >= currentLevel) {
      item.ParentID = prevId;
      itemElement.dataset.parent = prevId;
      itemElement.dataset.level = prevLevel + 1;
      itemElement.style.marginLeft = ((prevLevel + 1) * INDENT_WIDTH) + 'px';
      const prevItem = allItems.find(i => i.ID === prevId);
      msvToast(`Jetzt Unterpunkt von "${prevItem.Text}"`, 'success');
      updateItemPositionLocally(itemId, prevId, currentIndex);
      showSaveButtons(true);
    } else {
      msvToast('Element darüber ist auf niedrigerer Ebene', 'info');
    }
  }
}

// Position lokal aktualisieren (ohne API)
function updateItemPositionLocally(itemId, newParentId, newIndex) {

  // Finde das Item in allItems
  const item = allItems.find(i => i.ID === itemId);
  if (!item) return;
  const oldParentId = item.ParentID;

  // Update Parent
  item.ParentID = newParentId;

  // Hole alle aktuellen Geschwister (ohne das verschobene Element)
  let siblings = allItems.filter(i => i.ParentID === newParentId && i.ID !== itemId);

  // Sortiere Geschwister nach aktueller SortOrder
  siblings.sort((a, b) => a.SortOrder - b.SortOrder);

  // Füge das verschobene Element an der richtigen Position ein
  // newIndex basiert auf der visuellen Position in der Liste
  // Wir müssen die tatsächliche Position unter den Geschwistern finden
  let actualIndex = 0;
  const visibleItems = document.querySelectorAll('.sortable-item');
  let siblingCount = 0;
  for (let i = 0; i < visibleItems.length && i <= newIndex; i++) {
    const visItem = visibleItems[i];
    const visItemId = parseInt(visItem.dataset.id);
    const visItemParent = parseInt(visItem.dataset.parent);

    // Zähle nur Items mit gleichem Parent (und nicht das verschobene Element selbst)
    if (visItemParent === newParentId && visItemId !== itemId) {
      if (i < newIndex) {
        actualIndex++;
      }
    }
  }

  // Füge Item an berechneter Position ein
  siblings.splice(actualIndex, 0, item);

  // Vergebe neue SortOrder Werte
  siblings.forEach((sibling, idx) => {
    sibling.SortOrder = idx * 10;
  });

  // Wenn Parent gewechselt wurde, müssen auch die alten Geschwister neu sortiert werden
  if (oldParentId !== newParentId) {
    const oldSiblings = allItems.filter(i => i.ParentID === oldParentId);
    oldSiblings.sort((a, b) => a.SortOrder - b.SortOrder);
    oldSiblings.forEach((sibling, idx) => {
      sibling.SortOrder = idx * 10;
    });
  }
  console.log(`Moved item ${itemId} to position ${actualIndex} under parent ${newParentId}`);

  // Neu rendern
  renderList();
}

// Alle Änderungen speichern
function saveAllChanges() {

  // Zeige Ladeindikator
  const saveBtn = $('#btnSaveOrder');
  const originalText = saveBtn.html();
  saveBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...').prop('disabled', true);

  // Bereite Daten für Batch-Update vor
  const updates = [];
  allItems.forEach(item => {
    const original = originalItems.find(o => o.ID === item.ID);
    if (original && (original.ParentID !== item.ParentID || original.SortOrder !== item.SortOrder)) {
      updates.push({
        id: item.ID,
        parent_id: item.ParentID,
        sort_order: item.SortOrder
      });
    }
  });
  if (updates.length === 0) {
    msvToast('Keine Änderungen zu speichern', 'info');
    saveBtn.html(originalText).prop('disabled', false);
    showSaveButtons(false);
    return;
  }

  // Sende Batch-Update an API
  $.post(API, {
    action: 'batch_update',
    updates: JSON.stringify(updates),
    csrf_token: csrf
  }, res => {
    if (!res.success) {
      msvToast(res.message || 'Fehler beim Speichern', 'error');
      saveBtn.html(originalText).prop('disabled', false);
      return;
    }
    msvToast(`${updates.length} Änderungen gespeichert!`, 'success');

    // Seite nach kurzem Delay neu laden
    setTimeout(() => {
      window.location.reload();
    }, 1000);
  }, 'json').fail(() => {
    msvToast('Netzwerkfehler beim Speichern', 'error');
    saveBtn.html(originalText).prop('disabled', false);
  });
}

// Änderungen verwerfen
async function cancelChanges() {
  const result = await msvConfirm('Möchtest du wirklich alle Änderungen verwerfen?', 'Änderungen verwerfen', 'Ja, verwerfen');
  if (!result.isConfirmed) return;

  // Zurücksetzen auf Original
  allItems = JSON.parse(JSON.stringify(originalItems));
  renderList();
  showSaveButtons(false);
  msvToast('Änderungen verworfen', 'info');
}

// Position Update für WordPress-Style (ENTFERNT - wird nicht mehr gebraucht)
// function updateItemPositionWordPress wurde durch updateItemPositionLocally ersetzt
// Debug-Funktion zum Anzeigen der Hierarchie
function buildDebugTree() {
  const tree = [];
  const itemMap = new Map();
  allItems.forEach(item => {
    itemMap.set(item.ID, {...item, children: []});
  });
  itemMap.forEach(item => {
    if (item.ParentID > 0) {
      const parent = itemMap.get(item.ParentID);
      if (parent) {
        parent.children.push(item);
      } else {
        console.warn(`Item ${item.ID} (${item.Text}) hat Parent ${item.ParentID} der nicht existiert!`);
      }
    }
  });

  function printItem(item, level = 0) {
    const indent = '  '.repeat(level);
    const line = `${indent}${item.Text} (ID:${item.ID}, Parent:${item.ParentID})`;
    tree.push(line);
    item.children.forEach(child => printItem(child, level + 1));
  }
  itemMap.forEach(item => {
    if (item.ParentID === 0) {
      printItem(item, 0);
    }
  });
  return tree.join('\n');
}

// Parent Select füllen
function fillParentSelect() {
  const sel = $('#fieldParent').empty();
  sel.append('<option value="0">[Hauptebene]</option>');

  function addOption(item, level = 0) {
    const indent = '&nbsp;&nbsp;'.repeat(level * 2);
    sel.append(`<option value="${item.ID}">${indent}${escapeHtml(item.Text)}</option>`);
    const children = allItems.filter(child => child.ParentID == item.ID);
    children.forEach(child => addOption(child, level + 1));
  }
  const rootItems = allItems.filter(item => !item.ParentID || item.ParentID == 0);
  rootItems.forEach(item => addOption(item));
}

// HTML escapen
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
}

// Event Handlers
$(function() {
  modal = new bootstrap.Modal('#editModal');
  delModal = new bootstrap.Modal('#deleteModal');

  // Initial laden
  loadItems().then(() => {

    // Debug: Zeige Hierarchie in der Konsole
    console.log('=== Navigation Hierarchie ===');
    const tree = buildDebugTree();
    console.log(tree);

    // Prüfe ob Styles geladen wurden
    const testItem = document.querySelector('.sortable-item.indent-1');
    if (testItem) {
      const style = window.getComputedStyle(testItem);
      console.log('Einrückung Level 1:', style.marginLeft);
    }
  });

  // Refresh Button
  $('#btnRefresh').on('click', async () => {
    if (hasUnsavedChanges) {
      const result = await msvConfirm('Es gibt ungespeicherte Änderungen. Wirklich neu laden?', 'Neu laden', 'Ja, neu laden');
      if (!result.isConfirmed) return;
    }
    loadItems().then(() => msvToast('Aktualisiert', 'info'));
  });

  // Speichern Button
  $('#btnSaveOrder').on('click', saveAllChanges);

  // Verwerfen Button
  $('#btnCancelOrder').on('click', cancelChanges);

  // Add Button
  $('#btnAdd').on('click', () => {
    $('#modalTitle').text('Neuen Eintrag anlegen');
    $('#fieldId').val('');
    $('#fieldText').val('');
    $('#fieldLink').val('');
    fillParentSelect();
    $('#fieldParent').val('0');
    modal.show();
  });

  // Edit Button
  $(document).on('click', '.btn-edit', function(e) {
    e.stopPropagation();
    const id = $(this).closest('.sortable-item').data('id');
    const item = allItems.find(x => x.ID == id);
    if (!item) return;
    $('#modalTitle').text('Eintrag bearbeiten');
    $('#fieldId').val(item.ID);
    $('#fieldText').val(item.Text);
    $('#fieldLink').val(item.Link);
    fillParentSelect();
    $('#fieldParent').val(item.ParentID || 0);
    modal.show();
  });

  // Delete Button
  $(document).on('click', '.btn-delete', function(e) {
    e.stopPropagation();
    const id = $(this).closest('.sortable-item').data('id');
    const item = allItems.find(x => x.ID == id);
    if (!item) return;
    $('#delId').val(item.ID);
    $('#delText').text(item.Text);
    delModal.show();
  });

  // Level Up Button (Pfeil links - eine Ebene höher)
  $(document).on('click', '.btn-level-up', function(e) {
    e.stopPropagation();
    const id = $(this).closest('.sortable-item').data('id');
    changeItemLevel(id, 'left');
  });

  // Level Down Button (Pfeil rechts - als Unterpunkt)
  $(document).on('click', '.btn-level-down', function(e) {
    e.stopPropagation();
    const id = $(this).closest('.sortable-item').data('id');
    changeItemLevel(id, 'right');
  });

  // Edit Form Submit
  $('#editForm').on('submit', function(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this));
    const action = data.id ? 'update' : 'create';
    data.action = action;
    $.post(API, data, res => {
      if (!res.success) {
        msvToast(res.message || 'Fehler beim Speichern', 'error');
        return;
      }
      modal.hide();
      allItems = res.items;
      renderTree();
      msvToast(action === 'create' ? 'Eintrag erstellt' : 'Änderungen gespeichert', 'success');
    }, 'json');
  });

  // Delete Form Submit
  $('#deleteForm').on('submit', function(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this));
    data.action = 'delete';
    $.post(API, data, res => {
      if (!res.success) {
        msvToast(res.message || 'Fehler beim Löschen', 'error');
        return;
      }
      delModal.hide();
      allItems = res.items;
      renderTree();
      msvToast('Eintrag gelöscht', 'success');
    }, 'json');
  });
});

</script>

</body>
</html>
