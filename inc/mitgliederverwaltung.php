<?php
/**
 * Mitgliederverwaltung – Hybrid Layout (wie JMDefinition)
 * Read-only Tabelle + Slide-Panel zum Bearbeiten
 */
try { include 'dbconnect.inc.php'; } catch (Exception $e) { die("System error."); }

$page_specific_css = <<<'CSS'
/* ===== Mitgliederverwaltung – Seitenspezifisch ===== */

/* --- Flag-Dots --- */
.flag-dots { display: flex; gap: 6px; justify-content: center; }
.flag-dot {
  position: relative;
  width: 28px; height: 28px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 0.75rem; cursor: default;
  transition: transform 0.15s;
}
.flag-dot:hover { transform: scale(1.15); z-index: 9999; }
.flag-dot.on { background: #3b82f6; color: #fff; }
.flag-dot.off { background: #f1f5f9; color: #cbd5e1; }

/* Tooltip → global via msv-styles.css + msv-tooltips.js */

/* --- Slide-Panel --- */
.mv-edit-panel {
  position: fixed; top: 0; right: -520px; width: 500px; height: 100vh;
  background: #fff; box-shadow: -8px 0 30px rgba(0,0,0,0.12);
  z-index: 1060; transition: right 0.3s cubic-bezier(0.4,0,0.2,1);
  display: flex; flex-direction: column;
}
.mv-edit-panel.open { right: 0; }
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
.panel-header h6 { font-weight: 600; }
.panel-body {
  padding: 1.25rem; overflow-y: auto; flex: 1;
}
.panel-label {
  display: block; font-size: 0.8rem; font-weight: 600;
  color: #64748b; margin-bottom: 0.35rem;
}
.panel-section {
  font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.5px; color: #94a3b8;
  margin: 1rem 0 0.5rem; padding-bottom: 0.35rem;
  border-bottom: 1px solid #e2e8f0;
}

/* --- Aktions-Card --- */
.action-card { border-color: #e2e8f0; }
.action-card-header { cursor: pointer; user-select: none; background-color: #f8fafc; }
.action-card-header:hover { background-color: #f1f5f9; }
.action-chevron { transition: transform .2s ease; }
.action-card-header[aria-expanded="true"] .action-chevron { transform: rotate(180deg); }

/* --- Skeleton --- */
.skeleton {
  height: 20px; border-radius: 4px;
  background: linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);
  background-size: 200% 100%; animation: sk-load 1.5s infinite;
}
@keyframes sk-load { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* --- Import Area --- */
.import-area {
  border: 2px dashed #dee2e6; border-radius: 1rem;
  padding: 2rem; text-align: center; background: #fff;
  transition: all 0.3s; cursor: pointer;
}
.import-area:hover { border-color: #3b82f6; background: #f8fafe; }
.import-area.dragging { border-color: #28a745; background: #d4edda; }

/* --- Mobile --- */
@media (max-width: 767.98px) {
  .desktop-table-container { display: none !important; }
  .mobile-cards-container { display: block !important; }
  .mv-edit-panel { width: 100%; right: -110%; }
  .mv-edit-panel.open { right: 0; }
}
@media (min-width: 768px) {
  .mobile-cards-container { display: none !important; }
}

CSS;

include 'header.inc.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-7 col-lg-11 col-12 ps-0">
      <div class="main-content-wrapper">
        <div class="row mb-4 d-none d-md-flex">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-people-fill me-2"></i>Mitgliederverwaltung
            </h2>
          </div>
        </div>

        <div class="content-background">
          <form id="mitgliederForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- Suche + Aktionen -->
            <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
              <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width:350px;">
                <div class="input-group input-group-sm">
                  <span class="input-group-text"><i class="bi bi-search"></i></span>
                  <input type="text" class="form-control" id="searchInput" placeholder="Suchen...">
                </div>
              </div>

              <div class="card action-card mb-0">
                <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                     data-bs-toggle="collapse" data-bs-target="#mvActions"
                     aria-expanded="false" aria-controls="mvActions">
                  <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                  <i class="bi bi-chevron-down action-chevron"></i>
                </div>
                <div class="collapse" id="mvActions">
                  <div class="card-body pt-2 pb-3 px-3">
                    <div class="row g-2">
                      <div class="col-6">
                        <button type="button" class="btn btn-outline-success btn-sm w-100" id="btnNewMember">
                          <i class="bi bi-person-plus me-1"></i>Hinzufügen
                        </button>
                      </div>
                      <div class="col-6">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                          <i class="bi bi-save me-1"></i>Speichern
                        </button>
                      </div>
                      <div class="col-6">
                        <a href="mitgliederverwaltung/export_csv.php" class="btn btn-outline-info btn-sm w-100">
                          <i class="bi bi-download me-1"></i>CSV
                        </a>
                      </div>
                      <div class="col-6">
                        <button type="button" class="btn btn-outline-warning btn-sm w-100" data-bs-toggle="modal" data-bs-target="#importModal">
                          <i class="bi bi-upload me-1"></i>Import
                        </button>
                      </div>
                      <div class="col-6">
                        <button type="button" class="btn btn-outline-success btn-sm w-100 xlsx-export-btn">
                          <i class="bi bi-file-earmark-spreadsheet me-1"></i>Adressliste
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Desktop: Hybrid-Tabelle -->
            <div class="table-wrapper">
              <h5 class="table-title">
                <span><i class="bi bi-people me-2"></i>Mitglieder</span>
                <span class="badge bg-secondary" id="memberCount"></span>
              </h5>
              <div class="desktop-table-container">
                <table class="hybrid-table" id="mitgliederTable">
                  <thead>
                    <tr>
                      <th style="width:70px; text-align:center">Lizenz</th>
                      <th>Name</th>
                      <th>Vorname</th>
                      <th style="width:100px">Geb.datum</th>
                      <th>Waffe</th>
                      <th>Ort</th>
                      <th style="max-width:180px">Email</th>
                      <th style="width:140px">Mobile</th>
                      <th style="width:120px; text-align:center">Status</th>
                    </tr>
                  </thead>
                  <tbody><!-- dynamisch --></tbody>
                </table>
              </div>

              <!-- Mobile Cards -->
              <div class="mobile-cards-container" id="mobileMitgliederContainer">
                <div class="mobile-search">
                  <div class="position-relative">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" class="form-control" placeholder="Mitglieder suchen..."
                           oninput="filterMobileMitglieder(this)">
                  </div>
                </div>
                <div class="mobile-cards-scroll" id="mobileMitgliederCards"></div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Slide-Panel (Edit) -->
<div class="panel-overlay" id="panelOverlay"></div>
<div class="mv-edit-panel" id="editPanel">
  <div class="panel-header">
    <h6 class="mb-0"><i class="bi bi-pencil-square me-2"></i><span id="panelTitle">Mitglied bearbeiten</span></h6>
    <button class="btn btn-sm btn-outline-secondary" id="panelClose"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="panel-body">
    <!-- Stammdaten -->
    <div class="panel-section"><i class="bi bi-person me-1"></i>Stammdaten</div>
    <div class="row g-2 mb-2">
      <div class="col-4">
        <label class="panel-label">Anrede</label>
        <select class="form-select form-select-sm" id="panelAnrede">
          <option value="">—</option>
          <option value="Herr">Herr</option>
          <option value="Frau">Frau</option>
        </select>
      </div>
      <div class="col-4">
        <label class="panel-label">Lizenznr.</label>
        <input type="number" class="form-control form-control-sm" id="panelId" readonly style="background:#f1f5f9;">
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label">Name</label>
        <input type="text" class="form-control form-control-sm" id="panelName">
      </div>
      <div class="col-6">
        <label class="panel-label">Vorname</label>
        <input type="text" class="form-control form-control-sm" id="panelVorname">
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label">Geburtsdatum</label>
        <input type="date" class="form-control form-control-sm" id="panelGeburtsdatum">
      </div>
      <div class="col-6">
        <label class="panel-label">Waffe</label>
        <select class="form-select form-select-sm" id="panelWaffe"></select>
      </div>
    </div>

    <!-- Adresse -->
    <div class="panel-section"><i class="bi bi-geo-alt me-1"></i>Adresse</div>
    <div class="mb-2">
      <label class="panel-label">Strasse</label>
      <input type="text" class="form-control form-control-sm" id="panelStrasse">
    </div>
    <div class="row g-2 mb-2">
      <div class="col-4">
        <label class="panel-label">PLZ</label>
        <input type="text" class="form-control form-control-sm" id="panelPlz">
      </div>
      <div class="col-8">
        <label class="panel-label">Ort</label>
        <input type="text" class="form-control form-control-sm" id="panelOrt">
      </div>
    </div>

    <!-- Kontakt -->
    <div class="panel-section"><i class="bi bi-telephone me-1"></i>Kontakt</div>
    <div class="mb-2">
      <label class="panel-label">Email</label>
      <input type="email" class="form-control form-control-sm" id="panelEmail">
    </div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label">Telefon</label>
        <input type="tel" class="form-control form-control-sm" id="panelTelefon" placeholder="+41 79 123 45 67">
      </div>
      <div class="col-6">
        <label class="panel-label">Mobile</label>
        <input type="tel" class="form-control form-control-sm" id="panelMobile" placeholder="+41 79 123 45 67">
      </div>
    </div>
    <div class="mb-2">
      <label class="panel-label">Kommunikation</label>
      <select class="form-select form-select-sm" id="panelKommunikation">
        <option value="">—</option>
        <option value="Briefpost">Briefpost</option>
        <option value="Whatsapp">Whatsapp</option>
        <option value="Beides">Beides</option>
      </select>
    </div>
    <div class="mb-2">
      <label class="panel-label">Notizen</label>
      <textarea class="form-control form-control-sm" id="panelNotizen" rows="2"></textarea>
    </div>

    <!-- Status -->
    <div class="panel-section"><i class="bi bi-toggles me-1"></i>Status</div>
    <div class="row g-2 mb-2">
      <div class="col-4">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="panelStatus">
          <label class="form-check-label" for="panelStatus">Aktiv</label>
        </div>
      </div>
      <div class="col-4">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="panelEhre">
          <label class="form-check-label" for="panelEhre">Ehrenmitglied</label>
        </div>
      </div>
      <div class="col-4">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="panelVerstorben">
          <label class="form-check-label" for="panelVerstorben">Verstorben</label>
        </div>
      </div>
    </div>
    <div class="mb-3">
      <label class="panel-label">Vereinsaufnahme (Jahr)</label>
      <input type="number" class="form-control form-control-sm" id="panelVereinsaufnahme"
             min="1900" max="2099" step="1" placeholder="z.B. 1994" style="max-width:140px;">
    </div>

    <hr>
    <button class="btn btn-outline-danger w-100" id="panelDeleteBtn">
      <i class="bi bi-trash me-1"></i>Mitglied löschen
    </button>
  </div>
</div>

<!-- Modal: Neues Mitglied -->
<div class="modal fade" id="newMemberModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Neues Mitglied</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="newMemberForm">
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <div class="col-4">
              <label class="form-label fw-bold">Lizenznr. *</label>
              <input type="number" class="form-control form-control-sm" name="id" required>
            </div>
            <div class="col-4">
              <label class="form-label fw-bold">Name *</label>
              <input type="text" class="form-control form-control-sm" name="name" required>
            </div>
            <div class="col-4">
              <label class="form-label fw-bold">Vorname *</label>
              <input type="text" class="form-control form-control-sm" name="vorname" required>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label fw-bold">Geburtsdatum *</label>
              <input type="date" class="form-control form-control-sm" name="birthday" required>
            </div>
            <div class="col-6">
              <label class="form-label fw-bold">Waffe *</label>
              <select class="form-select form-select-sm" name="waffenid" id="newWaffenSelect" required>
                <option value="">Bitte wählen...</option>
              </select>
            </div>
          </div>
          <hr class="my-2">
          <div class="row g-2 mb-2">
            <div class="col-12"><label class="form-label fw-bold">Strasse</label>
              <input type="text" class="form-control form-control-sm" name="strasse"></div>
            <div class="col-4"><label class="form-label fw-bold">PLZ</label>
              <input type="text" class="form-control form-control-sm" name="plz"></div>
            <div class="col-8"><label class="form-label fw-bold">Ort</label>
              <input type="text" class="form-control form-control-sm" name="ort"></div>
          </div>
          <hr class="my-2">
          <div class="row g-2 mb-2">
            <div class="col-12"><label class="form-label fw-bold">Email</label>
              <input type="email" class="form-control form-control-sm" name="email"></div>
            <div class="col-6"><label class="form-label fw-bold">Telefon</label>
              <input type="text" class="form-control form-control-sm" name="telefon"></div>
            <div class="col-6"><label class="form-label fw-bold">Mobile</label>
              <input type="text" class="form-control form-control-sm" name="mobile"></div>
            <div class="col-12"><label class="form-label fw-bold">Notizen</label>
              <textarea class="form-control form-control-sm" name="notizen" rows="2"></textarea></div>
          </div>
          <hr class="my-2">
          <div class="row g-2">
            <div class="col-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="status" value="1" checked>
                <label class="form-check-label">Aktiv</label>
              </div>
            </div>
            <div class="col-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="ehrenmitglied" value="1">
                <label class="form-check-label">Ehrenmitglied</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i>Abbrechen
          </button>
          <button type="submit" class="btn btn-outline-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Hinzufügen
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: CSV Import -->
<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-upload me-2"></i>CSV Import</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info border-0 py-2">
          <small><strong>Format:</strong> <code>ID;Vorname;Name;Geburtsdatum;WaffenID;Status;Ehrenmitglied;Strasse;PLZ;Ort;Email;Telefon;Mobile;Notizen;Verstorben</code></small>
        </div>
        <div class="import-area" id="dropZone">
          <i class="bi bi-cloud-upload d-block" style="font-size:2rem;color:#94a3b8;"></i>
          <p class="mb-0 mt-2 text-muted">Datei hier ablegen oder klicken</p>
          <input type="file" id="csvFile" accept=".csv" style="display:none;">
        </div>
        <div id="importPreview" class="mt-3" style="display:none;">
          <h6 class="text-muted"><i class="bi bi-eye me-1"></i>Vorschau</h6>
          <div class="table-responsive" style="max-height:200px;overflow-y:auto;">
            <table class="table table-sm" id="previewTable"><thead></thead><tbody></tbody></table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-success btn-sm" id="confirmImport" style="display:none;">
          <i class="bi bi-check-circle me-1"></i>Import starten
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Löschen bestätigen -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Löschen?</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-3">
        <p class="mb-0" id="deleteConfirmText">Dieses Mitglied wirklich löschen?</p>
      </div>
      <div class="modal-footer py-2 justify-content-center">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="confirmDeleteBtn">
          <i class="bi bi-trash me-1"></i>Löschen
        </button>
      </div>
    </div>
  </div>
</div>

<script src="js/msv-phone.js"></script>
<script>
$(function() {
  const basePath = (/\/inc(\/|$)/.test(location.pathname)) ? '' : 'inc/';
  let hasChanges = false;
  let deleteId = null;
  let waffenOptionsHtml = '';

  // ========== Slide-Panel ==========
  const MVPanel = {
    currentId: null,
    dirty: false,

    open(tr) {
      // Vorheriges Mitglied speichern falls geändert
      if (this.dirty && this.currentId) {
        this.saveCurrent();
      }

      const d = tr.dataset;
      this.currentId = d.id;

      $('#panelAnrede').val(d.anrede || '');
      $('#panelId').val(d.id);
      $('#panelName').val(d.name);
      $('#panelVorname').val(d.vorname);
      $('#panelGeburtsdatum').val(d.geburtsdatum);
      $('#panelStrasse').val(d.strasse);
      $('#panelPlz').val(d.plz);
      $('#panelOrt').val(d.ort);
      $('#panelEmail').val(d.email);
      $('#panelTelefon').val(d.telefon);
      $('#panelMobile').val(d.mobile);
      $('#panelKommunikation').val(d.kommunikation || '');
      $('#panelNotizen').val(d.notizen);
      $('#panelStatus').prop('checked', d.status === '1');
      $('#panelEhre').prop('checked', d.ehrenmitglied === '1');
      $('#panelVerstorben').prop('checked', d.verstorben === '1');
      $('#panelVereinsaufnahme').val(d.vereinsaufnahme || '');

      // Waffen-Dropdown im Panel befüllen
      if (waffenOptionsHtml) {
        $('#panelWaffe').html(waffenOptionsHtml);
        $('#panelWaffe').val(d.waffenid);
      }

      $('#panelTitle').text(d.vorname + ' ' + d.name);
      $('#panelDeleteBtn').data('id', d.id);

      this.dirty = false;
      $('.hybrid-row').removeClass('selected');
      $(tr).addClass('selected');

      $('#editPanel').addClass('open');
      $('#panelOverlay').addClass('show');
    },

    close() {
      if (this.dirty && this.currentId) {
        this.saveCurrent();
      }
      $('#editPanel').removeClass('open');
      $('#panelOverlay').removeClass('show');
      $('.hybrid-row').removeClass('selected');
      this.currentId = null;
      this.dirty = false;
    },

    saveCurrent() {
      const id = this.currentId;
      if (!id) return;
      const $row = $(`#row${id}`);
      const d = $row[0].dataset;

      $.post(basePath + 'mitgliederverwaltung/save_single_mitglied.php', {
        id: id,
        anrede: d.anrede || '',
        name: d.name,
        vorname: d.vorname,
        geburtsdatum: d.geburtsdatum,
        waffenid: d.waffenid,
        strasse: d.strasse,
        plz: d.plz,
        ort: d.ort,
        email: d.email,
        telefon: d.telefon,
        mobile: d.mobile,
        kommunikation: d.kommunikation || '',
        notizen: d.notizen,
        status: d.status === '1' ? 1 : 0,
        ehrenmitglied: d.ehrenmitglied === '1' ? 1 : 0,
        verstorben: d.verstorben === '1' ? 1 : 0,
        vereinsaufnahme: d.vereinsaufnahme || '',
        csrf_token: $('input[name="csrf_token"]').val()
      })
      .done(() => msvToast('Gespeichert', 'success'))
      .fail(() => msvToast('Fehler beim Speichern', 'error'));

      hasChanges = false;
    },

    syncField(field, value) {
      if (!this.currentId) return;
      const id = this.currentId;
      const $row = $(`#row${id}`);
      $row.find(`input[name="${field}[${id}]"]`).val(value);
      $row.attr(`data-${field}`, value);

      const map = { name: '.h-name', email: '.h-email' };
      if (map[field]) $row.find(map[field]).text(value);
      if (field === 'vorname') $row.find('td:nth-child(3)').text(value);
      if (field === 'geburtsdatum' && value) {
        const parts = value.split('-');
        if (parts.length === 3) $row.find('.h-date').text(parts[2] + '.' + parts[1] + '.' + parts[0]);
      }
      this.dirty = true;
      hasChanges = true;
    },

    syncSelect(field, value) {
      if (!this.currentId) return;
      const id = this.currentId;
      $(`#row${id}`).find(`input[name="${field}[${id}]"]`).val(value);
      $(`#row${id}`).attr(`data-${field}`, value);
      this.dirty = true;
      hasChanges = true;
    },

    syncFlag(flag, checked) {
      if (!this.currentId) return;
      const id = this.currentId;
      const $row = $(`#row${id}`);
      $row.attr(`data-${flag}`, checked ? '1' : '0');
      $row.find(`.flag-input[data-flag="${flag}"]`).remove();
      if (checked) {
        $row.append(`<input type='hidden' name='${flag}[${id}]' value='1' class='flag-input' data-flag='${flag}'>`);
      }
      const $dot = $row.find(`.flag-dot[data-flag="${flag}"]`);
      $dot.toggleClass('on', checked).toggleClass('off', !checked);

      if (flag === 'verstorben') {
        $row.css('opacity', checked ? 0.5 : 1);
      }
      this.dirty = true;
      hasChanges = true;
    }
  };

  // Flag-Dot Tooltips → global via msv-tooltips.js

  // Panel Events
  $(document).on('click', '.hybrid-row', function() { MVPanel.open(this); });
  $('#panelClose, #panelOverlay').on('click', () => MVPanel.close());
  $(document).on('keydown', e => {
    if (e.key === 'Escape' && $('#editPanel').hasClass('open')) { MVPanel.close(); e.stopImmediatePropagation(); }
    if (e.key === 'Enter' && $('#editPanel').hasClass('open')) {
      // Nicht auslösen wenn in Textarea
      if ($(e.target).is('textarea')) return;
      e.preventDefault();
      MVPanel.close(); // close triggert saveCurrent()
    }
  });

  // Live-Sync
  $('#panelName').on('input', function() { MVPanel.syncField('name', this.value); });
  $('#panelVorname').on('input', function() { MVPanel.syncField('vorname', this.value); });
  $('#panelGeburtsdatum').on('change', function() { MVPanel.syncField('geburtsdatum', this.value); });
  $('#panelStrasse').on('input', function() { MVPanel.syncField('strasse', this.value); });
  $('#panelPlz').on('input', function() { MVPanel.syncField('plz', this.value); });
  $('#panelOrt').on('input', function() { MVPanel.syncField('ort', this.value); });
  $('#panelEmail').on('input', function() { MVPanel.syncField('email', this.value); });
  $('#panelTelefon').on('input', function() { MVPanel.syncField('telefon', this.value); });
  $('#panelMobile').on('input', function() { MVPanel.syncField('mobile', this.value); });
  $('#panelNotizen').on('input', function() { MVPanel.syncField('notizen', this.value); });
  $('#panelWaffe').on('change', function() { MVPanel.syncSelect('waffenid', this.value); });
  $('#panelAnrede').on('change', function() { MVPanel.syncSelect('anrede', this.value); });
  $('#panelKommunikation').on('change', function() { MVPanel.syncSelect('kommunikation', this.value); });
  $('#panelVereinsaufnahme').on('change', function() { MVPanel.syncField('vereinsaufnahme', this.value); });
  $('#panelStatus').on('change', function() { MVPanel.syncFlag('status', this.checked); });
  $('#panelEhre').on('change', function() { MVPanel.syncFlag('ehrenmitglied', this.checked); });
  $('#panelVerstorben').on('change', function() { MVPanel.syncFlag('verstorben', this.checked); });

  // Phone auto-format on blur
  $('#panelTelefon, #panelMobile').on('blur', function() {
    var v = $(this).val().trim();
    if (v) {
      var formatted = formatSwissPhone(v);
      $(this).val(formatted).trigger('input');
    }
  });

  // ========== Daten laden ==========
  function showSkeleton() {
    const row = `<tr><td><div class="skeleton" style="width:40px"></div></td><td><div class="skeleton" style="width:80%"></div></td><td><div class="skeleton" style="width:70%"></div></td><td><div class="skeleton" style="width:60%"></div></td><td><div class="skeleton" style="width:50%"></div></td><td><div class="skeleton" style="width:60%"></div></td><td><div class="skeleton" style="width:90%"></div></td><td><div class="skeleton" style="width:70%"></div></td><td><div class="skeleton" style="width:80px"></div></td></tr>`;
    $('#mitgliederTable tbody').html(row.repeat(6));
  }

  function loadMitglieder() {
    MVPanel.close();
    showSkeleton();
    $.get(basePath + 'mitgliederverwaltung/load_mitglieder_form.php', function(html) {
      $('#mitgliederTable tbody').html(html);
      const count = $('#mitgliederTable tbody tr.hybrid-row').length;
      $('#memberCount').text(count + ' Mitglieder');
      buildMobileCards();
    }).fail(() => {
      $('#mitgliederTable tbody').html('<tr><td colspan="9" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden</td></tr>');
      msvToast('Fehler beim Laden', 'error');
    });
  }

  function loadWaffen() {
    $.get(basePath + 'mitgliederverwaltung/load_waffen_options.php', function(data) {
      waffenOptionsHtml = data;
      $('#newWaffenSelect').html('<option value="">Bitte wählen...</option>' + data);
      $('#panelWaffe').html(data);
    }).fail(function() {
      $.get(basePath + 'mitgliederverwaltung/load_waffen_option.php', function(data) {
        waffenOptionsHtml = data;
        $('#newWaffenSelect').html('<option value="">Bitte wählen...</option>' + data);
        $('#panelWaffe').html(data);
      });
    });
  }

  // ========== Suche ==========
  $('#searchInput').on('keyup', function() {
    const q = this.value.toLowerCase();
    $('#mitgliederTable tbody tr.hybrid-row').each(function() {
      const d = this.dataset;
      const text = [d.id, d.name, d.vorname, d.email, d.ort].join(' ').toLowerCase();
      $(this).toggle(text.includes(q));
    });
  });

  // ========== Speichern ==========
  $('#mitgliederForm').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    const txt = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

    $.post(basePath + 'mitgliederverwaltung/save_mitglieder.php', $(this).serialize())
      .done(function(resp) {
        msvToast('Änderungen gespeichert!', 'success');
        hasChanges = false;
        MVPanel.close();
        setTimeout(() => loadMitglieder(), 500);
      })
      .fail(() => msvToast('Fehler beim Speichern', 'error'))
      .always(() => $btn.prop('disabled', false).html(txt));
  });

  // Ctrl+S
  $(document).on('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); $('#mitgliederForm').trigger('submit'); }
  });

  // Ungespeicherte Änderungen warnen
  $(window).on('beforeunload', function() {
    if (hasChanges) return 'Ungespeicherte Änderungen!';
  });

  // ========== Neues Mitglied ==========
  $('#btnNewMember').on('click', () => $('#newMemberModal').modal('show'));
  $('#newMemberModal').on('shown.bs.modal', () => $('#newMemberModal').find('input[name="id"]').focus());

  $('#newMemberForm').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    const txt = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>...');

    $.post(basePath + 'mitgliederverwaltung/add_mitglied.php', $(this).serialize())
      .done(function() {
        msvToast('Mitglied hinzugefügt!', 'success');
        $('#newMemberModal').modal('hide');
        $('#newMemberForm')[0].reset();
        loadMitglieder();
      })
      .fail(() => msvToast('Fehler beim Hinzufügen', 'error'))
      .always(() => $btn.prop('disabled', false).html(txt));
  });

  // ========== Löschen ==========
  $('#panelDeleteBtn').on('click', function() {
    deleteId = $(this).data('id');
    const $row = $(`#row${deleteId}`);
    const name = $row.data('vorname') + ' ' + $row.data('name');
    $('#deleteConfirmText').html(`<strong>${name}</strong> wirklich löschen?`);
    MVPanel.close();
    $('#confirmDeleteModal').modal('show');
  });

  $('#confirmDeleteBtn').on('click', function() {
    if (!deleteId) return;
    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

    $.post(basePath + 'mitgliederverwaltung/delete_mitglied.php', {
      id: deleteId,
      csrf_token: $('input[name="csrf_token"]').val()
    })
    .done(function() {
      $('#confirmDeleteModal').modal('hide');
      msvToast('Mitglied gelöscht', 'success');
      loadMitglieder();
    })
    .fail(() => msvToast('Fehler beim Löschen', 'error'))
    .always(function() { $btn.prop('disabled', false).html('<i class="bi bi-trash me-1"></i>Löschen'); deleteId = null; });
  });

  // ========== CSV Import ==========
  let csvData = null;
  const dz = document.getElementById('dropZone');
  if (dz) {
    dz.onclick = () => document.getElementById('csvFile').click();
    ['dragenter','dragover','dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); }));
    ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('dragging')));
    ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('dragging')));
    dz.addEventListener('drop', e => handleFiles(e.dataTransfer.files));
    $('#csvFile').on('change', e => handleFiles(e.target.files));
  }

  function handleFiles(files) {
    if (files.length && files[0].name.endsWith('.csv')) parseCSV(files[0]);
    else msvToast('Bitte eine CSV-Datei wählen', 'warning');
  }

  function parseCSV(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const lines = e.target.result.split('\n').filter(l => l.trim());
      if (lines.length < 2) { msvToast('CSV leer', 'error'); return; }
      csvData = [];
      const headers = lines[0].split(';');
      for (let i = 1; i < lines.length; i++) {
        const vals = lines[i].split(';');
        const row = {};
        headers.forEach((h, j) => { row[h.trim()] = vals[j]?.trim() || ''; });
        csvData.push(row);
      }
      // Preview
      const thead = $('#previewTable thead').empty();
      const tbody = $('#previewTable tbody').empty();
      thead.append('<tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr>');
      csvData.slice(0, 5).forEach(r => {
        tbody.append('<tr>' + headers.map(h => `<td>${r[h.trim()] || ''}</td>`).join('') + '</tr>');
      });
      $('#importPreview').show();
      $('#confirmImport').show();
      msvToast(`${csvData.length} Datensätze erkannt`, 'info');
    };
    reader.readAsText(file, 'UTF-8');
  }

  $('#confirmImport').on('click', function() {
    if (!csvData) return;
    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

    $.post(basePath + 'mitgliederverwaltung/import_csv.php', {
      csvData: JSON.stringify(csvData),
      csrf_token: $('input[name="csrf_token"]').val()
    })
    .done(function() {
      msvToast('Import erfolgreich!', 'success');
      $('#importModal').modal('hide');
      csvData = null;
      loadMitglieder();
    })
    .fail(() => msvToast('Import-Fehler', 'error'))
    .always(() => $btn.prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i>Import starten'));
  });

  $('#importModal').on('hidden.bs.modal', () => { $('#importPreview').hide(); $('#confirmImport').hide(); csvData = null; });

  // ========== Mobile Cards ==========
  function buildMobileCards() {
    if (!window.matchMedia('(max-width: 767.98px)').matches) return;
    const container = document.getElementById('mobileMitgliederCards');
    if (!container) return;

    let html = '';
    $('#mitgliederTable tbody tr.hybrid-row').each(function() {
      const d = this.dataset;
      const aktiv = d.status === '1';
      const ehre = d.ehrenmitglied === '1';
      const verst = d.verstorben === '1';

      html += `
      <div class="mobile-card" data-member-id="${d.id}">
        <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
          <div>
            <div class="fw-bold">${d.name} ${d.vorname}</div>
            <small class="text-muted">Lizenz: ${d.id}</small>
            <div class="mt-1">
              <span class="badge ${aktiv ? 'bg-success' : 'bg-secondary'} me-1">${aktiv ? '✓ Aktiv' : 'Inaktiv'}</span>
              ${ehre ? '<span class="badge bg-warning text-dark me-1">★ Ehre</span>' : ''}
              ${verst ? '<span class="badge bg-dark">† Verst.</span>' : ''}
            </div>
          </div>
          <i class="bi bi-chevron-down"></i>
        </div>
        <div class="mobile-card-body">
          <p class="mb-1"><strong>Geb.:</strong> ${d.geburtsdatum || '-'}</p>
          <p class="mb-1"><strong>Email:</strong> ${d.email || '-'}</p>
          <p class="mb-1"><strong>Tel.:</strong> ${d.telefon || '-'}</p>
          <p class="mb-2"><strong>Adresse:</strong> ${d.strasse || ''} ${d.plz || ''} ${d.ort || ''}</p>
          <button type="button" class="btn btn-outline-primary btn-sm w-100 mobile-edit-btn" data-id="${d.id}">
            <i class="bi bi-pencil me-1"></i>Bearbeiten
          </button>
        </div>
      </div>`;
    });

    container.innerHTML = html || '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Mitglieder</div></div>';
  }

  // Mobile: Edit öffnet Panel
  $(document).on('click', '.mobile-edit-btn', function(e) {
    e.stopPropagation();
    const id = $(this).data('id');
    const tr = document.getElementById('row' + id);
    if (tr) MVPanel.open(tr);
  });

  window.filterMobileMitglieder = function(input) {
    const q = input.value.toLowerCase();
    document.querySelectorAll('#mobileMitgliederCards .mobile-card').forEach(c => {
      c.style.display = c.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  };

  window.matchMedia('(max-width: 767.98px)').addEventListener('change', () => buildMobileCards());

  // ========== Excel-Export ==========
  $(document).on('click', '.xlsx-export-btn', function(e) {
    e.preventDefault();
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Generiere...');

    $.ajax({
      url: basePath + 'mitgliederverwaltung/generate_mitglieder_xlsx.php',
      type: 'GET',
      success: function(response) {
        var data = typeof response === 'string' ? JSON.parse(response) : response;
        if (data.excel_link) {
          const link = document.createElement('a');
          link.href = basePath + 'mitgliederverwaltung/' + data.excel_link;
          link.download = data.excel_link.split('/').pop();
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          msvToast('Adressliste wurde exportiert.', 'success');
        } else if (data.message) {
          msvToast('Fehler: ' + data.message, 'error');
        }
      },
      error: function() {
        msvToast('Fehler beim Generieren der Excel-Datei.', 'error');
      },
      complete: function() {
        btn.prop('disabled', false).html('<i class="bi bi-file-earmark-spreadsheet me-1"></i>Adressliste exportieren');
      }
    });
  });

  // ========== Start ==========
  loadWaffen();
  loadMitglieder();
});
</script>

<?php include 'footer.inc.php'; ?>
