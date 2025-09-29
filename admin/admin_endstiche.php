<?php
// admin_endstiche.php - Admin-Interface für Stich-Definitionen
try {
    include '../dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in admin_endstiche.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

include '../header.inc.php';

// Check Admin-Rechte (anpassen nach deinem Auth-System)
if (!isset($_SESSION)) { session_start(); }

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="row">
  <div class="col-12">
    <div class="main-content-wrapper">
      <div class="row mb-4">
        <div class="col-md-12">
          <h2 class="h4 mb-0" style="color: var(--secondary-color);">
            <i class="bi bi-gear-fill me-2"></i>
            Admin: Endschiessen Stich-Definitionen
          </h2>
        </div>
      </div>

      <div class="content-background">
        <div class="alert alert-info mb-4">
          <i class="bi bi-info-circle me-2"></i>
          Hier kannst du die verschiedenen Stiche für das Endschiessen verwalten. 
          Änderungen wirken sich auf alle zukünftigen Erfassungen aus.
        </div>

        <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <!-- Stich-Tabelle -->
        <div class="table-responsive">
          <table class="table table-hover" id="stichTable">
            <thead>
              <tr>
                <th width="50">Sort</th>
                <th>Code</th>
                <th>Name</th>
                <th width="100">Schuss</th>
                <th width="120">Preis</th>
                <th width="80">Aktiv</th>
                <th width="120">Aktionen</th>
              </tr>
            </thead>
            <tbody id="stichTableBody">
              <!-- Wird via JS geladen -->
            </tbody>
          </table>
        </div>

        <div class="mt-3">
          <button class="btn btn-success" id="btnAddStich">
            <i class="bi bi-plus-circle"></i> Neuer Stich
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Stich bearbeiten</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editForm">
          <input type="hidden" id="editId">
          
          <div class="mb-3">
            <label for="editCode" class="form-label">Code</label>
            <input type="text" class="form-control" id="editCode" maxlength="50" required>
            <div class="form-text">Interner Code (z.B. END, SCHWINI_P1)</div>
          </div>
          
          <div class="mb-3">
            <label for="editName" class="form-label">Name</label>
            <input type="text" class="form-control" id="editName" maxlength="100" required>
            <div class="form-text">Anzeigename für die Benutzer</div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="editShots" class="form-label">Anzahl Schuss</label>
                <input type="number" class="form-control" id="editShots" min="0" max="100" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="editPrice" class="form-label">Preis (CHF)</label>
                <input type="number" class="form-control" id="editPrice" min="0" max="1000" step="0.01" required>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="editSort" class="form-label">Sortierung</label>
                <input type="number" class="form-control" id="editSort" min="0" max="999" required>
                <div class="form-text">Kleinere Zahlen erscheinen zuerst</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Status</label>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="editActive" checked>
                  <label class="form-check-label" for="editActive">Aktiv</label>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-primary" id="btnSaveEdit">
          <span class="spinner-border spinner-border-sm me-2 d-none" id="saveSpinner"></span>
          Speichern
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  .table .btn-sm { padding: 0.25rem 0.5rem; }
  .badge { font-size: 0.875rem; }
</style>

<script>
(function(){
  const API = '../endschloesen/endschloesen_api.php';
  let STICHE = [];
  let editModal = null;
  
  // Bootstrap Modal initialisieren
  document.addEventListener('DOMContentLoaded', function() {
    editModal = new bootstrap.Modal(document.getElementById('editModal'));
  });
  
  // Helpers
  function fmtCHF(cents) {
    return 'CHF ' + ((cents || 0) / 100).toFixed(2);
  }
  
  function centsFromCHF(chf) {
    return Math.round(parseFloat(chf) * 100);
  }
  
  // Lade Stiche
  function loadStiche() {
    fetch(`${API}?action=get_stich_definitions`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          STICHE = data.data;
          renderTable();
        } else {
          showAlert('Fehler beim Laden: ' + data.message, 'danger');
        }
      })
      .catch(err => {
        console.error('Load error:', err);
        showAlert('Netzwerkfehler beim Laden', 'danger');
      });
  }
  
  // Render Tabelle
  function renderTable() {
    const tbody = document.getElementById('stichTableBody');
    tbody.innerHTML = '';
    
    STICHE.forEach(stich => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${stich.sort_order}</td>
        <td><code>${stich.code}</code></td>
        <td><strong>${stich.name}</strong></td>
        <td>${stich.shots}</td>
        <td>${fmtCHF(stich.price_cents)}</td>
        <td>
          ${stich.active == 1 
            ? '<span class="badge bg-success">Aktiv</span>' 
            : '<span class="badge bg-secondary">Inaktiv</span>'}
        </td>
        <td>
          <button class="btn btn-sm btn-outline-primary btn-edit" data-id="${stich.id}">
            <i class="bi bi-pencil"></i>
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }
  
  // Edit Dialog öffnen
  function openEditDialog(id) {
    const stich = id ? STICHE.find(s => s.id == id) : null;
    
    document.getElementById('modalTitle').textContent = stich ? 'Stich bearbeiten' : 'Neuer Stich';
    document.getElementById('editId').value = id || '';
    
    if (stich) {
      document.getElementById('editCode').value = stich.code;
      document.getElementById('editName').value = stich.name;
      document.getElementById('editShots').value = stich.shots;
      document.getElementById('editPrice').value = (stich.price_cents / 100).toFixed(2);
      document.getElementById('editSort').value = stich.sort_order;
      document.getElementById('editActive').checked = stich.active == 1;
    } else {
      // Defaults für neuen Stich
      document.getElementById('editForm').reset();
      document.getElementById('editSort').value = '100';
      document.getElementById('editActive').checked = true;
    }
    
    editModal.show();
  }
  
  // Speichern
  function saveStich() {
    const id = document.getElementById('editId').value;
    const data = {
      action: 'update_stich_definition',
      id: id,
      name: document.getElementById('editName').value,
      shots: parseInt(document.getElementById('editShots').value),
      price_cents: centsFromCHF(document.getElementById('editPrice').value),
      sort_order: parseInt(document.getElementById('editSort').value),
      active: document.getElementById('editActive').checked ? 1 : 0
    };
    
    // Bei neuem Stich auch Code mitschicken
    if (!id) {
      data.code = document.getElementById('editCode').value;
    }
    
    const spinner = document.getElementById('saveSpinner');
    const btn = document.getElementById('btnSaveEdit');
    spinner.classList.remove('d-none');
    btn.disabled = true;
    
    fetch(API, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.getElementById('csrf_token').value
      },
      body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
      if (result.success) {
        editModal.hide();
        loadStiche(); // Tabelle neu laden
        showAlert('Erfolgreich gespeichert', 'success');
      } else {
        showAlert('Fehler: ' + result.message, 'danger');
      }
    })
    .catch(err => {
      console.error('Save error:', err);
      showAlert('Netzwerkfehler beim Speichern', 'danger');
    })
    .finally(() => {
      spinner.classList.add('d-none');
      btn.disabled = false;
    });
  }
  
  // Alert anzeigen
  function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
      alertDiv.remove();
    }, 5000);
  }
  
  // Event Listeners
  document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-edit')) {
      const id = e.target.closest('.btn-edit').dataset.id;
      openEditDialog(id);
    }
  });
  
  document.getElementById('btnAddStich').addEventListener('click', function() {
    openEditDialog(null);
  });
  
  document.getElementById('btnSaveEdit').addEventListener('click', saveStich);
  
  // Enter-Taste im Form
  document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveStich();
  });
  
  // Initial laden
  loadStiche();
})();
</script>

<?php include '../footer.inc.php'; ?>
