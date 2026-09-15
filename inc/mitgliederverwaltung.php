<?php
/**
 * Mitgliederverwaltung – Hybrid Layout (wie JMDefinition)
 * Read-only Tabelle + Slide-Panel zum Bearbeiten. Speichern passiert automatisch beim Schliessen
 * des Panels (save_single_mitglied.php); der frühere Sammel-Speichern-Pfad ist entfernt.
 */
include 'dbconnect.inc.php';

// Seiten-CSS: Panel, Aktions-Card, Flag-Dots, Skeleton, Import-Area sind zentral (msv-styles.css)
$page_specific_css = <<<'CSS'
#panelId[readonly] { background: #f1f5f9; }
.mv-search { max-width: 350px; }
.import-area-icon { font-size: 2rem; color: #94a3b8; }
.import-preview-scroll { max-height: 200px; overflow-y: auto; }
/* kurzes Feedback nach dem Auto-Save einer Zeile */
.hybrid-table tbody tr.row-saved td { background: #e8f5e9 !important; transition: background .6s; }
CSS;

include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-wide">
        <?php $page_title = 'Mitgliederverwaltung'; include 'partials/page_header.inc.php'; ?>

        <div class="content-background">
          <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <!-- Suche + Aktionen -->
          <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
            <div class="d-flex align-items-center gap-2 flex-grow-1 mv-search">
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Suchen..." aria-label="Mitglieder suchen">
              </div>
            </div>

<?php
            $ac_id = 'mvActions';
            ob_start();
            ?>
                  <div class="row g-2">
                    <div class="col-6">
                      <button type="button" class="btn btn-outline-success btn-sm w-100" id="btnNewMember">
                        <i class="bi bi-person-plus me-1"></i>Hinzufügen
                      </button>
                    </div>
                    <div class="col-6">
                      <button type="button" class="btn btn-outline-success btn-sm w-100" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bi bi-upload me-1"></i>Import
                      </button>
                    </div>
                    <div class="col-6">
                      <a href="mitgliederverwaltung/export_csv.php" class="btn btn-outline-info btn-sm w-100">
                        <i class="bi bi-download me-1"></i>CSV
                      </a>
                    </div>
                    <div class="col-6">
                      <button type="button" class="btn btn-outline-info btn-sm w-100 xlsx-export-btn">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Adressliste
                      </button>
                    </div>
                  </div>
            <?php
            $ac_body = ob_get_clean();
            include 'partials/action_card.inc.php';
            ?>
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
                  <input type="text" class="form-control" placeholder="Mitglieder suchen..." aria-label="Mitglieder suchen"
                         oninput="filterMobileMitglieder(this)">
                </div>
              </div>
              <div class="mobile-cards-scroll" id="mobileMitgliederCards"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Slide-Panel (Edit) – zentrale Struktur via inc/partials/side_panel.inc.php -->
<?php
$panel_id    = 'editPanel';
$panel_class = 'mv-edit-panel';
$panel_title = '<i class="bi bi-pencil-square me-2"></i><span id="panelTitle">Mitglied bearbeiten</span>';
ob_start();
?>
    <!-- Stammdaten -->
    <div class="panel-section"><i class="bi bi-person me-1"></i>Stammdaten</div>
    <div class="row g-2 mb-2">
      <div class="col-4">
        <label class="panel-label" for="panelAnrede">Anrede</label>
        <select class="form-select form-select-sm" id="panelAnrede">
          <option value="">—</option>
          <option value="Herr">Herr</option>
          <option value="Frau">Frau</option>
        </select>
      </div>
      <div class="col-4">
        <label class="panel-label" for="panelId">Lizenznr.</label>
        <input type="number" class="form-control form-control-sm" id="panelId" readonly>
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label" for="panelName">Name</label>
        <input type="text" class="form-control form-control-sm" id="panelName">
      </div>
      <div class="col-6">
        <label class="panel-label" for="panelVorname">Vorname</label>
        <input type="text" class="form-control form-control-sm" id="panelVorname">
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label" for="panelGeburtsdatum">Geburtsdatum</label>
        <input type="date" class="form-control form-control-sm" id="panelGeburtsdatum">
      </div>
      <div class="col-6">
        <label class="panel-label" for="panelWaffe">Waffe</label>
        <select class="form-select form-select-sm" id="panelWaffe"></select>
      </div>
    </div>

    <!-- Adresse -->
    <div class="panel-section"><i class="bi bi-geo-alt me-1"></i>Adresse</div>
    <div class="mb-2">
      <label class="panel-label" for="panelStrasse">Strasse</label>
      <input type="text" class="form-control form-control-sm" id="panelStrasse">
    </div>
    <div class="row g-2 mb-2">
      <div class="col-4">
        <label class="panel-label" for="panelPlz">PLZ</label>
        <input type="text" class="form-control form-control-sm" id="panelPlz">
      </div>
      <div class="col-8">
        <label class="panel-label" for="panelOrt">Ort</label>
        <input type="text" class="form-control form-control-sm" id="panelOrt">
      </div>
    </div>

    <!-- Kontakt -->
    <div class="panel-section"><i class="bi bi-telephone me-1"></i>Kontakt</div>
    <div class="mb-2">
      <label class="panel-label" for="panelEmail">Email</label>
      <input type="email" class="form-control form-control-sm" id="panelEmail">
    </div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label" for="panelTelefon">Telefon</label>
        <input type="tel" class="form-control form-control-sm" id="panelTelefon" placeholder="+41 79 123 45 67">
      </div>
      <div class="col-6">
        <label class="panel-label" for="panelMobile">Mobile</label>
        <input type="tel" class="form-control form-control-sm" id="panelMobile" placeholder="+41 79 123 45 67">
      </div>
    </div>
    <div class="mb-2">
      <label class="panel-label" for="panelKommunikation">Kommunikation</label>
      <select class="form-select form-select-sm" id="panelKommunikation">
        <option value="">—</option>
        <option value="Briefpost">Briefpost</option>
        <option value="Whatsapp">Whatsapp</option>
        <option value="Beides">Beides</option>
      </select>
    </div>
    <div class="mb-2">
      <label class="panel-label" for="panelNotizen">Notizen</label>
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
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="panelJskLeiter">
      <label class="form-check-label" for="panelJskLeiter"><i class="bi bi-person-badge me-1"></i>Jungschützenleiter</label>
    </div>
    <div class="row g-2 mb-3">
      <div class="col-6">
        <label class="panel-label" for="panelVereinsaufnahme">Vereinsaufnahme (Jahr)</label>
        <input type="number" class="form-control form-control-sm" id="panelVereinsaufnahme"
               min="1900" max="2099" step="1" placeholder="z.B. 1994">
      </div>
    </div>

    <hr>
    <p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>Änderungen werden beim Schliessen des Panels gespeichert (Enter oder Escape).</p>
    <button type="button" class="btn btn-outline-danger btn-sm w-100" id="panelDeleteBtn">
      <i class="bi bi-trash me-1"></i>Mitglied löschen
    </button>
<?php
$panel_body = ob_get_clean();
include 'partials/side_panel.inc.php';
?>

<!-- Modal: Neues Mitglied -->
<div class="modal fade" id="newMemberModal" tabindex="-1" aria-labelledby="newMemberTitle">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newMemberTitle"><i class="bi bi-person-plus me-2"></i>Neues Mitglied</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <form id="newMemberForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="modal-body">
          <div class="row g-2 mb-2">
            <div class="col-4">
              <label class="form-label fw-bold" for="nmId">Lizenznr. *</label>
              <input type="number" class="form-control form-control-sm" name="id" id="nmId" min="1" required>
            </div>
            <div class="col-4">
              <label class="form-label fw-bold" for="nmName">Name *</label>
              <input type="text" class="form-control form-control-sm" name="name" id="nmName" required>
            </div>
            <div class="col-4">
              <label class="form-label fw-bold" for="nmVorname">Vorname *</label>
              <input type="text" class="form-control form-control-sm" name="vorname" id="nmVorname" required>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-4">
              <label class="form-label fw-bold" for="nmAnrede">Anrede</label>
              <select class="form-select form-select-sm" name="anrede" id="nmAnrede">
                <option value="">—</option><option value="Herr">Herr</option><option value="Frau">Frau</option>
              </select>
            </div>
            <div class="col-4">
              <label class="form-label fw-bold" for="nmBirthday">Geburtsdatum *</label>
              <input type="date" class="form-control form-control-sm" name="birthday" id="nmBirthday" required>
            </div>
            <div class="col-4">
              <label class="form-label fw-bold" for="newWaffenSelect">Waffe *</label>
              <select class="form-select form-select-sm" name="waffenid" id="newWaffenSelect" required>
                <option value="">Bitte wählen...</option>
              </select>
            </div>
          </div>
          <hr class="my-2">
          <div class="row g-2 mb-2">
            <div class="col-12"><label class="form-label fw-bold" for="nmStrasse">Strasse</label>
              <input type="text" class="form-control form-control-sm" name="strasse" id="nmStrasse"></div>
            <div class="col-4"><label class="form-label fw-bold" for="nmPlz">PLZ</label>
              <input type="text" class="form-control form-control-sm" name="plz" id="nmPlz"></div>
            <div class="col-8"><label class="form-label fw-bold" for="nmOrt">Ort</label>
              <input type="text" class="form-control form-control-sm" name="ort" id="nmOrt"></div>
          </div>
          <hr class="my-2">
          <div class="row g-2 mb-2">
            <div class="col-12"><label class="form-label fw-bold" for="nmEmail">Email</label>
              <input type="email" class="form-control form-control-sm" name="email" id="nmEmail"></div>
            <div class="col-6"><label class="form-label fw-bold" for="nmTelefon">Telefon</label>
              <input type="tel" class="form-control form-control-sm" name="telefon" id="nmTelefon" placeholder="+41 79 123 45 67"></div>
            <div class="col-6"><label class="form-label fw-bold" for="nmMobile">Mobile</label>
              <input type="tel" class="form-control form-control-sm" name="mobile" id="nmMobile" placeholder="+41 79 123 45 67"></div>
            <div class="col-6"><label class="form-label fw-bold" for="nmKomm">Kommunikation</label>
              <select class="form-select form-select-sm" name="kommunikation" id="nmKomm">
                <option value="">—</option><option value="Briefpost">Briefpost</option><option value="Whatsapp">Whatsapp</option><option value="Beides">Beides</option>
              </select></div>
            <div class="col-6"><label class="form-label fw-bold" for="nmAufnahme">Vereinsaufnahme (Jahr)</label>
              <input type="number" class="form-control form-control-sm" name="vereinsaufnahme" id="nmAufnahme" min="1900" max="2099" placeholder="z.B. 2026"></div>
            <div class="col-12"><label class="form-label fw-bold" for="nmNotizen">Notizen</label>
              <textarea class="form-control form-control-sm" name="notizen" id="nmNotizen" rows="2"></textarea></div>
          </div>
          <hr class="my-2">
          <div class="row g-2">
            <div class="col-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="status" id="nmStatus" value="1" checked>
                <label class="form-check-label" for="nmStatus">Aktiv</label>
              </div>
            </div>
            <div class="col-6">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="ehrenmitglied" id="nmEhre" value="1">
                <label class="form-check-label" for="nmEhre">Ehrenmitglied</label>
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
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalTitle">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="importModalTitle"><i class="bi bi-upload me-2"></i>CSV Import</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info border-0 py-2">
          <small><strong>Format (Kopfzeile, Semikolon):</strong> <code>ID;Anrede;Vorname;Name;Geburtsdatum;WaffenID;Status;Ehrenmitglied;Strasse;PLZ;Ort;Email;Telefon;Mobile;Notizen;Verstorben;Vereinsaufnahme;Kommunikation</code><br>
          Entspricht dem CSV-Export. Anrede, Vereinsaufnahme und Kommunikation sind optional.</small>
        </div>
        <div class="import-area" id="dropZone" role="button" tabindex="0" aria-label="CSV-Datei wählen">
          <i class="bi bi-cloud-upload d-block import-area-icon"></i>
          <p class="mb-0 mt-2 text-muted">Datei hier ablegen oder klicken</p>
          <input type="file" id="csvFile" accept=".csv" hidden>
        </div>
        <div id="importPreview" class="mt-3" hidden>
          <h6 class="text-muted"><i class="bi bi-eye me-1"></i>Vorschau (erste 5 Zeilen)</h6>
          <div class="table-responsive import-preview-scroll">
            <table class="table table-sm" id="previewTable"><thead></thead><tbody></tbody></table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-success btn-sm" id="confirmImport" hidden>
          <i class="bi bi-check-circle me-1"></i>Import starten
        </button>
      </div>
    </div>
  </div>
</div>

<script src="js/msv-phone.js"></script>
<script>
$(function() {
  const basePath = (/\/inc(\/|$)/.test(location.pathname)) ? '' : 'inc/';
  const CSRF = document.getElementById('csrfToken').value;
  let waffenOptionsHtml = '';

  const esc = s => $('<span>').text(s == null ? '' : String(s)).html();
  const ajaxMsg = (xhr, fallback) => (xhr && xhr.responseJSON && xhr.responseJSON.message)
    || (xhr && xhr.status === 401 ? 'Sitzung abgelaufen – bitte neu anmelden' : fallback);

  // ========== Slide-Panel ==========
  const MVPanel = {
    currentId: null,
    dirty: false,

    open(tr) {
      if (this.dirty && this.currentId) this.saveCurrent(); // vorheriges Mitglied sichern

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
      $('#panelJskLeiter').prop('checked', d.ist_jsk_leiter === '1');
      $('#panelVereinsaufnahme').val(d.vereinsaufnahme || '');

      if (waffenOptionsHtml) {
        $('#panelWaffe').html(waffenOptionsHtml).val(d.waffenid);
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
      if (this.dirty && this.currentId) this.saveCurrent();
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
      if (!$row.length) return;
      const d = $row[0].dataset;
      this.dirty = false;

      $.post(basePath + 'mitgliederverwaltung/save_single_mitglied.php', {
        id, anrede: d.anrede || '', name: d.name, vorname: d.vorname, geburtsdatum: d.geburtsdatum,
        waffenid: d.waffenid, strasse: d.strasse, plz: d.plz, ort: d.ort, email: d.email,
        telefon: d.telefon, mobile: d.mobile, kommunikation: d.kommunikation || '', notizen: d.notizen,
        status: d.status === '1' ? 1 : 0, ehrenmitglied: d.ehrenmitglied === '1' ? 1 : 0,
        verstorben: d.verstorben === '1' ? 1 : 0, ist_jsk_leiter: d.ist_jsk_leiter === '1' ? 1 : 0,
        vereinsaufnahme: d.vereinsaufnahme || '', csrf_token: CSRF
      }, null, 'json')
      .done(r => {
        if (r && r.success) { msvToast('Gespeichert', 'success'); $row.addClass('row-saved'); setTimeout(() => $row.removeClass('row-saved'), 1200); }
        else msvToast((r && r.message) || 'Fehler beim Speichern', 'error');
      })
      .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Speichern'), 'error'));
    },

    syncField(field, value) {
      if (!this.currentId) return;
      const $row = $(`#row${this.currentId}`);
      $row.attr(`data-${field}`, value);
      // Spalten der Tabelle (load_mitglieder_form.php): 3 Vorname, 5 Waffe, 6 Ort, 8 Mobile
      const map = { name: '.h-name', email: '.h-email', vorname: 'td:nth-child(3)', ort: 'td:nth-child(6)', mobile: 'td:nth-child(8)' };
      if (map[field]) $row.find(map[field]).text(value);
      if (field === 'geburtsdatum' && value) {
        const p = value.split('-');
        if (p.length === 3) $row.find('.h-date').text(p[2] + '.' + p[1] + '.' + p[0]);
      }
      this.dirty = true;
    },

    syncSelect(field, value) {
      if (!this.currentId) return;
      const $row = $(`#row${this.currentId}`);
      $row.attr(`data-${field}`, value);
      if (field === 'waffenid') $row.find('td:nth-child(5)').text($('#panelWaffe option:selected').text());
      this.dirty = true;
    },

    syncFlag(flag, checked) {
      if (!this.currentId) return;
      const $row = $(`#row${this.currentId}`);
      $row.attr(`data-${flag}`, checked ? '1' : '0');
      $row.find(`.flag-dot[data-flag="${flag}"]`).toggleClass('on', checked).toggleClass('off', !checked);
      if (flag === 'verstorben') $row.css('opacity', checked ? 0.5 : 1);
      this.dirty = true;
    }
  };

  // Panel Events
  $(document).on('click', '.hybrid-row', function() { MVPanel.open(this); });
  $('#panelClose, #panelOverlay').on('click', () => MVPanel.close());
  $(document).on('keydown', e => {
    if (!$('#editPanel').hasClass('open')) return;
    if (e.key === 'Escape') { MVPanel.close(); e.stopImmediatePropagation(); }
    if (e.key === 'Enter' && !$(e.target).is('textarea')) { e.preventDefault(); MVPanel.close(); } // close speichert
  });
  // Ctrl+S: aktuelles Panel speichern
  $(document).on('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); if (MVPanel.dirty) MVPanel.saveCurrent(); else msvToast('Keine Änderungen', 'info'); }
  });
  $(window).on('beforeunload', function() { if (MVPanel.dirty) return 'Ungespeicherte Änderungen!'; });

  // Live-Sync Panel -> Zeile
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
  $('#panelJskLeiter').on('change', function() { MVPanel.syncFlag('ist_jsk_leiter', this.checked); });

  // Telefonnummern beim Verlassen formatieren (+41 79 123 45 67)
  $('#panelTelefon, #panelMobile, #nmTelefon, #nmMobile').on('blur', function() {
    const v = $(this).val().trim();
    if (v) $(this).val(formatSwissPhone(v)).trigger('input');
  });

  // ========== Daten laden ==========
  function showSkeleton() {
    const row = `<tr><td><div class="skeleton" style="width:40px"></div></td><td><div class="skeleton" style="width:80%"></div></td><td><div class="skeleton" style="width:70%"></div></td><td><div class="skeleton" style="width:60%"></div></td><td><div class="skeleton" style="width:50%"></div></td><td><div class="skeleton" style="width:60%"></div></td><td><div class="skeleton" style="width:90%"></div></td><td><div class="skeleton" style="width:70%"></div></td><td><div class="skeleton" style="width:80px"></div></td></tr>`;
    $('#mitgliederTable tbody').html(row.repeat(6));
  }

  function updateCount() {
    const all = $('#mitgliederTable tbody tr.hybrid-row').length;
    const visible = $('#mitgliederTable tbody tr.hybrid-row:visible').length;
    $('#memberCount').text((visible < all ? visible + ' von ' + all : all) + ' Mitglieder');
  }

  function loadMitglieder() {
    MVPanel.close();
    showSkeleton();
    $.get(basePath + 'mitgliederverwaltung/load_mitglieder_form.php', function(html) {
      $('#mitgliederTable tbody').html(html);
      $('#searchInput').trigger('input'); // aktiven Filter erneut anwenden (setzt auch den Zähler)
      buildMobileCards();
    }).fail(xhr => {
      $('#mitgliederTable tbody').html('<tr><td colspan="9" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden</td></tr>');
      msvToast(ajaxMsg(xhr, 'Fehler beim Laden'), 'error');
    });
  }

  function loadWaffen() {
    $.get(basePath + 'mitgliederverwaltung/load_waffen_option.php', function(data) {
      waffenOptionsHtml = data;
      $('#newWaffenSelect').html('<option value="">Bitte wählen...</option>' + data);
      $('#panelWaffe').html(data);
    }).fail(xhr => msvToast(ajaxMsg(xhr, 'Waffenliste konnte nicht geladen werden'), 'error'));
  }

  // ========== Suche ==========
  $('#searchInput').on('input', function() {
    const q = this.value.toLowerCase();
    $('#mitgliederTable tbody tr.hybrid-row').each(function() {
      const d = this.dataset;
      $(this).toggle([d.id, d.name, d.vorname, d.email, d.ort].join(' ').toLowerCase().includes(q));
    });
    updateCount();
  });

  // ========== Neues Mitglied ==========
  $('#btnNewMember').on('click', () => $('#newMemberModal').modal('show'));
  $('#newMemberModal').on('shown.bs.modal', () => $('#nmId').trigger('focus'));

  $('#newMemberForm').on('submit', function(e) {
    e.preventDefault();
    const $btn = $(this).find('button[type="submit"]');
    const txt = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>...');

    $.post(basePath + 'mitgliederverwaltung/add_mitglied.php', $(this).serialize(), null, 'json')
      .done(function(r) {
        if (!r || !r.success) { msvToast((r && r.message) || 'Fehler beim Hinzufügen', 'error'); return; }
        msvToast('Mitglied hinzugefügt', 'success');
        $('#newMemberModal').modal('hide');
        $('#newMemberForm')[0].reset();
        loadMitglieder();
      })
      .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Hinzufügen'), 'error'))
      .always(() => $btn.prop('disabled', false).html(txt));
  });

  // ========== Löschen ==========
  $('#panelDeleteBtn').on('click', function() {
    const deleteId = $(this).data('id');
    const $row = $(`#row${deleteId}`);
    const name = (($row.data('vorname') || '') + ' ' + ($row.data('name') || '')).trim();
    MVPanel.dirty = false; // nicht mehr speichern, was gleich gelöscht wird
    MVPanel.close();
    msvConfirmDelete(name).then(function(res) {
      if (!res.isConfirmed) return;
      $.post(basePath + 'mitgliederverwaltung/delete_mitglied.php', { id: deleteId, csrf_token: CSRF })
        .done(function() { msvToast('Mitglied gelöscht', 'success'); loadMitglieder(); })
        .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Löschen'), 'error'));
    });
  });

  // ========== CSV Import ==========
  let csvData = null;
  const dz = document.getElementById('dropZone');
  if (dz) {
    dz.addEventListener('click', () => document.getElementById('csvFile').click());
    dz.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); document.getElementById('csvFile').click(); } });
    ['dragenter','dragover','dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); }));
    ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('dragging')));
    ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('dragging')));
    dz.addEventListener('drop', e => handleFiles(e.dataTransfer.files));
    $('#csvFile').on('change', e => handleFiles(e.target.files));
  }

  function handleFiles(files) {
    if (files.length && files[0].name.toLowerCase().endsWith('.csv')) parseCSV(files[0]);
    else msvToast('Bitte eine CSV-Datei wählen', 'warning');
  }

  function parseCSV(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const lines = String(e.target.result).replace(/^﻿/, '').split(/\r?\n/).filter(l => l.trim());
      if (lines.length < 2) { msvToast('CSV enthält keine Datenzeilen', 'error'); return; }
      csvData = [];
      const headers = lines[0].split(';').map(h => h.trim());
      for (let i = 1; i < lines.length; i++) {
        const vals = lines[i].split(';');
        const row = {};
        headers.forEach((h, j) => { row[h] = (vals[j] || '').trim(); });
        csvData.push(row);
      }
      // Vorschau (escaped – Inhalt kommt aus einer fremden Datei)
      $('#previewTable thead').html('<tr>' + headers.map(h => `<th>${esc(h)}</th>`).join('') + '</tr>');
      $('#previewTable tbody').html(csvData.slice(0, 5).map(r => '<tr>' + headers.map(h => `<td>${esc(r[h])}</td>`).join('') + '</tr>').join(''));
      $('#importPreview').prop('hidden', false);
      $('#confirmImport').prop('hidden', false);
      msvToast(`${csvData.length} Datensätze erkannt`, 'info');
    };
    reader.readAsText(file, 'UTF-8');
  }

  $('#confirmImport').on('click', function() {
    if (!csvData) return;
    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

    $.post(basePath + 'mitgliederverwaltung/import_csv.php', { csvData: JSON.stringify(csvData), csrf_token: CSRF }, null, 'json')
      .done(function(r) {
        if (!r) { msvToast('Import-Fehler', 'error'); return; }
        const errs = Array.isArray(r.errors) ? r.errors : [];
        if (r.success) msvToast((r.imported || 0) + ' neu, ' + (r.updated || 0) + ' aktualisiert' + (errs.length ? ', ' + errs.length + ' Fehler' : ''), errs.length ? 'warning' : 'success');
        else msvToast(r.message || 'Import fehlgeschlagen', 'error');
        if (errs.length) {
          msvConfirm('<div class="text-start small">' + errs.slice(0, 15).map(esc).join('<br>') + (errs.length > 15 ? '<br>…' : '') + '</div>', 'Nicht importierte Zeilen', 'OK');
        }
        $('#importModal').modal('hide');
        csvData = null;
        loadMitglieder();
      })
      .fail(xhr => msvToast(ajaxMsg(xhr, 'Import-Fehler'), 'error'))
      .always(() => $btn.prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i>Import starten'));
  });

  $('#importModal').on('hidden.bs.modal', () => { $('#importPreview').prop('hidden', true); $('#confirmImport').prop('hidden', true); $('#csvFile').val(''); csvData = null; });

  // ========== Mobile Cards ==========
  function buildMobileCards() {
    if (!window.matchMedia('(max-width: 767.98px)').matches) return;
    const container = document.getElementById('mobileMitgliederCards');
    if (!container) return;

    let html = '';
    $('#mitgliederTable tbody tr.hybrid-row').each(function() {
      const d = this.dataset;
      const aktiv = d.status === '1', ehre = d.ehrenmitglied === '1', verst = d.verstorben === '1';
      // dataset liefert dekodierte Werte -> fuer innerHTML escapen (Stored XSS ueber Mitgliederfelder)
      html += `
      <div class="mobile-card" data-member-id="${esc(d.id)}">
        <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
          <div>
            <div class="fw-bold">${esc(d.name)} ${esc(d.vorname)}</div>
            <small class="text-muted">Lizenz: ${esc(d.id)}</small>
            <div class="mt-1">
              <span class="badge ${aktiv ? 'bg-success' : 'bg-secondary'} me-1">${aktiv ? '✓ Aktiv' : 'Inaktiv'}</span>
              ${ehre ? '<span class="badge bg-warning text-dark me-1">★ Ehre</span>' : ''}
              ${verst ? '<span class="badge bg-dark">† Verst.</span>' : ''}
            </div>
          </div>
          <i class="bi bi-chevron-down"></i>
        </div>
        <div class="mobile-card-body">
          <p class="mb-1"><strong>Geb.:</strong> ${esc(d.geburtsdatum || '-')}</p>
          <p class="mb-1"><strong>Email:</strong> ${esc(d.email || '-')}</p>
          <p class="mb-1"><strong>Tel.:</strong> ${esc(d.telefon || d.mobile || '-')}</p>
          <p class="mb-2"><strong>Adresse:</strong> ${esc([d.strasse, d.plz, d.ort].filter(Boolean).join(' ') || '-')}</p>
          <button type="button" class="btn btn-outline-primary btn-sm w-100 mobile-edit-btn" data-id="${esc(d.id)}">
            <i class="bi bi-pencil me-1"></i>Bearbeiten
          </button>
        </div>
      </div>`;
    });

    container.innerHTML = html || '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Mitglieder gefunden</div></div>';
  }

  $(document).on('click', '.mobile-edit-btn', function(e) {
    e.stopPropagation();
    const tr = document.getElementById('row' + $(this).data('id'));
    if (tr) MVPanel.open(tr);
  });

  window.filterMobileMitglieder = function(input) {
    const q = input.value.toLowerCase();
    document.querySelectorAll('#mobileMitgliederCards .mobile-card').forEach(c => {
      c.style.display = c.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  };

  window.matchMedia('(max-width: 767.98px)').addEventListener('change', () => buildMobileCards());

  // ========== Excel-Export (Adressliste) ==========
  $(document).on('click', '.xlsx-export-btn', function(e) {
    e.preventDefault();
    const $btn = $(this), orig = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Generiere...');
    $.getJSON(basePath + 'mitgliederverwaltung/generate_mitglieder_xlsx.php')
      .done(function(r) {
        if (r && r.success && r.excel_link) {
          const link = document.createElement('a');
          link.href = basePath + 'mitgliederverwaltung/' + r.excel_link;
          link.download = r.excel_link.split('/').pop();
          document.body.appendChild(link); link.click(); document.body.removeChild(link);
          msvToast('Adressliste exportiert', 'success');
        } else msvToast((r && r.message) || 'Fehler beim Generieren der Excel-Datei', 'error');
      })
      .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Generieren der Excel-Datei'), 'error'))
      .always(() => $btn.prop('disabled', false).html(orig));
  });

  // ========== Start ==========
  loadWaffen();
  loadMitglieder();
});
</script>

<?php include 'footer.inc.php'; ?>
