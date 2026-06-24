<?php
/**
 * Jungschuetzen-Verwaltung – Hybrid Layout (Tabelle + Slide-Panel)
 * Stammdaten der Jungschuetzenkurs-Teilnehmer: manuell erfassen / bearbeiten /
 * loeschen + Excel-Import (SSV-Mitgliederverzeichnis). Plus globaler Master-Schalter
 * fuer die Jungschuetzen-Betreuung.
 */
try { include 'dbconnect.inc.php'; } catch (Exception $e) { die("System error."); }
require_once __DIR__ . '/../auth.php';

// Zugriffsschutz: nur Vorstand/Admin (vor header.inc.php, da dieser Output erzeugt)
if (!isset($_SESSION['user_id']) || !(isAdmin() || isVorstand())) {
    if (($_SESSION['user_id'] ?? 0) != 1) {
        header('Location: home.php');
        exit();
    }
}

$page_specific_css = <<<'CSS'
/* ===== Jungschuetzen-Verwaltung ===== */
.flag-dot {
  display:inline-flex; align-items:center; justify-content:center;
  width:26px; height:26px; border-radius:50%; font-size:0.7rem;
}
.flag-dot.on { background:#22c55e; color:#fff; }
.flag-dot.off { background:#f1f5f9; color:#cbd5e1; }

.js-edit-panel {
  position: fixed; top: 0; right: -560px; width: 520px; height: 100vh;
  background: #fff; box-shadow: -8px 0 30px rgba(0,0,0,0.12);
  z-index: 1060; transition: right 0.3s cubic-bezier(0.4,0,0.2,1);
  display: flex; flex-direction: column;
}
.js-edit-panel.open { right: 0; }
.panel-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.3);
  z-index: 1055; opacity: 0; visibility: hidden; transition: all 0.3s; }
.panel-overlay.show { opacity: 1; visibility: visible; }
.panel-header { display:flex; justify-content:space-between; align-items:center;
  padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0; background:#f8fafc; flex-shrink:0; }
.panel-body { padding:1.25rem; overflow-y:auto; flex:1; }
.panel-label { display:block; font-size:0.8rem; font-weight:600; color:#64748b; margin-bottom:0.3rem; }
.panel-section { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
  color:#94a3b8; margin:1rem 0 0.5rem; padding-bottom:0.35rem; border-bottom:1px solid #e2e8f0; }

.import-area { border:2px dashed #dee2e6; border-radius:1rem; padding:2rem; text-align:center;
  background:#fff; transition:all 0.3s; cursor:pointer; }
.import-area:hover { border-color:#3b82f6; background:#f8fafe; }
.import-area.dragging { border-color:#28a745; background:#d4edda; }

.feature-switch-card { border:1px solid #e2e8f0; border-radius:0.75rem; background:#fff; }

@media (max-width: 767.98px) {
  .desktop-table-container { display:none !important; }
  .mobile-cards-container { display:block !important; }
  .js-edit-panel { width:100%; right:-110%; }
  .js-edit-panel.open { right:0; }
}
@media (min-width: 768px) { .mobile-cards-container { display:none !important; } }
CSS;

include 'header.inc.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$featureAktiv = jskFeatureAktiv();
$canToggle    = isAdmin();
$jahrVorschlag = (int) date('Y');

// Info-/Willkommensblock fuers JSK-Dashboard (aus settings)
$jskInfoTitel = '';
$jskInfoText  = '';
try {
    $st = getDB()->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('jsk_info_titel','jsk_info_text')");
    $st->execute();
    foreach ($st->fetchAll() as $r) {
        if ($r['setting_key'] === 'jsk_info_titel') $jskInfoTitel = (string) $r['setting_value'];
        if ($r['setting_key'] === 'jsk_info_text')  $jskInfoText  = (string) $r['setting_value'];
    }
} catch (Throwable $e) { /* settings evtl. noch leer */ }
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-12 col-lg-11 col-12 ps-0">
      <div class="main-content-wrapper">
        <div class="row mb-4 d-none d-md-flex">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-person-bounding-box me-2"></i>Jungschützen-Verwaltung
            </h2>
          </div>
        </div>

        <!-- Master-Schalter -->
        <div class="feature-switch-card p-3 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <div class="fw-semibold"><i class="bi bi-toggle2-on me-2"></i>Jungschützen-Betreuung</div>
            <small class="text-muted">Schaltet das Anmelden von Schiess-Terminen, das Betreuer-Board und die Benachrichtigungen frei.</small>
          </div>
          <div class="form-check form-switch fs-5 mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="featureSwitch"
                   <?= $featureAktiv ? 'checked' : '' ?> <?= $canToggle ? '' : 'disabled' ?>>
            <label class="form-check-label fs-6" for="featureSwitch" id="featureSwitchLabel">
              <?= $featureAktiv ? 'Aktiv' : 'Deaktiviert' ?>
            </label>
          </div>
        </div>
        <?php if (!$canToggle): ?>
          <p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i>Nur Administratoren können die Funktion global ein-/ausschalten.</p>
        <?php endif; ?>

        <!-- Info-/Willkommensblock fuers JSK-Dashboard -->
        <div class="feature-switch-card p-3 mb-4">
          <div class="fw-semibold mb-1"><i class="bi bi-chat-left-text me-2"></i>Info-Text fürs JSK-Dashboard</div>
          <small class="text-muted d-block mb-2">Wird den Jungschützen oben auf ihrer Übersicht angezeigt (z.B. Trainingszeiten, Kontakt zum Jungschützenleiter, was mitzubringen ist). Leer lassen = kein Block.</small>
          <input type="text" class="form-control form-control-sm mb-2" id="infoTitel" maxlength="120"
                 placeholder="Titel (optional, z.B. Willkommen im Jungschützenkurs)" value="<?= htmlspecialchars($jskInfoTitel, ENT_QUOTES, 'UTF-8') ?>">
          <textarea class="form-control form-control-sm mb-2" id="infoText" rows="3" maxlength="4000"
                    placeholder="Infotext für die Jungschützen…"><?= htmlspecialchars($jskInfoText, ENT_QUOTES, 'UTF-8') ?></textarea>
          <button type="button" class="btn btn-outline-primary btn-sm" id="saveInfoBtn"><i class="bi bi-save me-1"></i>Info speichern</button>
        </div>

        <div class="content-background">
          <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <!-- Suche + Aktionen -->
          <div class="d-flex flex-wrap gap-3 align-items-center mb-4">
            <div class="input-group input-group-sm" style="max-width:350px;">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control" id="searchInput" placeholder="Suchen...">
            </div>
            <button type="button" class="btn btn-outline-success btn-sm" id="btnNewJs">
              <i class="bi bi-person-plus me-1"></i>Hinzufügen
            </button>
            <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
              <i class="bi bi-upload me-1"></i>Excel-Import
            </button>
          </div>

          <div class="table-wrapper">
            <h5 class="table-title">
              <span><i class="bi bi-people me-2"></i>Jungschützen</span>
              <span class="badge bg-secondary" id="jsCount"></span>
            </h5>
            <div class="desktop-table-container">
              <table class="hybrid-table" id="jsTable">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Vorname</th>
                    <th style="width:100px">Geb.datum</th>
                    <th>Ort</th>
                    <th style="max-width:180px">Email</th>
                    <th style="width:140px">Mobile</th>
                    <th style="width:90px; text-align:center">Kurs</th>
                    <th style="width:120px; text-align:center">Konto</th>
                    <th style="width:60px; text-align:center">Aktiv</th>
                  </tr>
                </thead>
                <tbody><!-- dynamisch --></tbody>
              </table>
            </div>

            <div class="mobile-cards-container" id="mobileJsContainer">
              <div class="mobile-cards-scroll" id="mobileJsCards"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Slide-Panel (Edit/New) -->
<div class="panel-overlay" id="panelOverlay"></div>
<div class="js-edit-panel" id="editPanel">
  <div class="panel-header">
    <h6 class="mb-0"><i class="bi bi-pencil-square me-2"></i><span id="panelTitle">Jungschütze</span></h6>
    <button class="btn btn-sm btn-outline-secondary" id="panelClose"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="panel-body">
    <input type="hidden" id="panelId" value="0">

    <div class="panel-section"><i class="bi bi-person me-1"></i>Stammdaten</div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label">Vorname *</label>
        <input type="text" class="form-control form-control-sm" id="panelVorname">
      </div>
      <div class="col-6">
        <label class="panel-label">Name *</label>
        <input type="text" class="form-control form-control-sm" id="panelName">
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label">Geburtsdatum</label>
        <input type="date" class="form-control form-control-sm" id="panelGeburtsdatum">
      </div>
      <div class="col-6">
        <label class="panel-label">AHV-Nummer</label>
        <input type="text" class="form-control form-control-sm" id="panelAhv" placeholder="756.xxxx.xxxx.xx">
      </div>
    </div>

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

    <div class="panel-section"><i class="bi bi-telephone me-1"></i>Kontakt (für Login-Registrierung)</div>
    <div class="mb-2">
      <label class="panel-label">Email</label>
      <input type="email" class="form-control form-control-sm" id="panelEmail">
      <small class="text-muted">Über diese Adresse registriert sich der Jungschütze.</small>
    </div>
    <div class="mb-2">
      <label class="panel-label">Mobile</label>
      <input type="tel" class="form-control form-control-sm" id="panelMobile">
    </div>

    <div class="panel-section"><i class="bi bi-mortarboard me-1"></i>Kurs</div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label">Kurs-Nr.</label>
        <select class="form-select form-select-sm" id="panelKursNr">
          <option value="0">—</option>
          <option value="1">Kurs 1</option>
          <option value="2">Kurs 2</option>
          <option value="3">Kurs 3</option>
          <option value="4">Kurs 4</option>
        </select>
      </div>
      <div class="col-6">
        <label class="panel-label">Kursjahr</label>
        <input type="number" class="form-control form-control-sm" id="panelKursJahr" min="2000" max="2099" placeholder="<?= $jahrVorschlag ?>">
      </div>
    </div>
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" id="panelAktiv" checked>
      <label class="form-check-label" for="panelAktiv">Aktiv</label>
    </div>

    <!-- Login-Konto: Freischaltung durch Vorstand/Admin -->
    <div class="panel-section"><i class="bi bi-box-arrow-in-right me-1"></i>Login-Konto</div>
    <div id="panelKontoArea" class="mb-3"></div>

    <button class="btn btn-primary w-100 mb-2" id="panelSaveBtn"><i class="bi bi-save me-1"></i>Speichern</button>
    <button class="btn btn-outline-danger w-100" id="panelDeleteBtn" style="display:none;">
      <i class="bi bi-trash me-1"></i>Jungschütze löschen
    </button>
  </div>
</div>

<!-- Modal: Excel-Import -->
<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Jungschützen importieren</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info border-0 py-2">
          <small>Excel des SSV-Mitgliederverzeichnisses (oder eigene Liste mit Spalten Vorname/Name/Geburtsdatum/…). Es werden nur plausible Jungschützen-Jahrgänge vorgeschlagen.</small>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small fw-semibold">Kurs-Nr. (für Import)</label>
            <select class="form-select form-select-sm" id="importKursNr">
              <option value="0">—</option>
              <option value="1">Kurs 1</option>
              <option value="2">Kurs 2</option>
              <option value="3">Kurs 3</option>
              <option value="4">Kurs 4</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Kursjahr</label>
            <input type="number" class="form-control form-control-sm" id="importKursJahr" min="2000" max="2099" value="<?= $jahrVorschlag ?>">
          </div>
        </div>
        <div class="import-area" id="dropZone">
          <i class="bi bi-cloud-upload d-block" style="font-size:2rem;color:#94a3b8;"></i>
          <p class="mb-0 mt-2 text-muted">Excel-Datei hier ablegen oder klicken (.xlsx, .xls, .csv)</p>
          <input type="file" id="xlsxFile" accept=".xlsx,.xls,.csv" style="display:none;">
        </div>
        <div id="importPreview" class="mt-3" style="display:none;">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <h6 class="text-muted mb-0"><i class="bi bi-eye me-1"></i>Vorschau</h6>
            <div class="form-check form-check-sm mb-0">
              <input class="form-check-input" type="checkbox" id="previewSelectAll" checked>
              <label class="form-check-label small" for="previewSelectAll">Alle</label>
            </div>
          </div>
          <div id="previewInfo" class="small text-muted mb-1"></div>
          <div class="table-responsive" style="max-height:320px;overflow-y:auto;">
            <table class="table table-sm table-hover align-middle" id="previewTable">
              <thead><tr><th style="width:36px"></th><th>Vorname</th><th>Name</th><th>Geb.</th><th>Alter</th><th>Email</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-success btn-sm" id="confirmImport" style="display:none;">
          <i class="bi bi-check-circle me-1"></i><span id="confirmImportText">Import starten</span>
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
        <p class="mb-0" id="deleteConfirmText">Diesen Jungschützen wirklich löschen?</p>
      </div>
      <div class="modal-footer py-2 justify-content-center">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-danger btn-sm" id="confirmDeleteBtn"><i class="bi bi-trash me-1"></i>Löschen</button>
      </div>
    </div>
  </div>
</div>

<script>
$(function () {
  const basePath = (/\/inc(\/|$)/.test(location.pathname)) ? '' : 'inc/';
  const ep = basePath + 'jungschuetzen_verwaltung/';
  const csrf = $('#csrfToken').val();
  let previewRows = [];

  // ---------- Master-Schalter ----------
  $('#featureSwitch').on('change', function () {
    const on = this.checked;
    $.post(ep + 'set_feature_active.php', { aktiv: on ? 1 : 0, csrf_token: csrf })
      .done(r => {
        const data = typeof r === 'string' ? JSON.parse(r) : r;
        if (data.success) {
          $('#featureSwitchLabel').text(on ? 'Aktiv' : 'Deaktiviert');
          msvToast(data.message, 'success');
        } else { msvToast(data.message || 'Fehler', 'error'); $('#featureSwitch').prop('checked', !on); }
      })
      .fail(() => { msvToast('Fehler beim Speichern', 'error'); $('#featureSwitch').prop('checked', !on); });
  });

  // ---------- Info-Text speichern ----------
  $('#saveInfoBtn').on('click', function () {
    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
    $.post(ep + 'save_info.php', {
      info_titel: $('#infoTitel').val(),
      info_text: $('#infoText').val(),
      csrf_token: csrf
    })
      .done(r => {
        const data = typeof r === 'string' ? JSON.parse(r) : r;
        msvToast(data.success ? data.message : (data.message || 'Fehler'), data.success ? 'success' : 'error');
      })
      .fail(() => msvToast('Fehler beim Speichern', 'error'))
      .always(() => $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Info speichern'));
  });

  // ---------- Slide-Panel ----------
  const Panel = {
    open(tr) {
      const d = tr.dataset;
      $('#panelId').val(d.id);
      $('#panelVorname').val(d.vorname || '');
      $('#panelName').val(d.name || '');
      $('#panelGeburtsdatum').val(d.geburtsdatum || '');
      $('#panelAhv').val(d.ahvnummer || '');
      $('#panelStrasse').val(d.strasse || '');
      $('#panelPlz').val(d.plz || '');
      $('#panelOrt').val(d.ort || '');
      $('#panelEmail').val(d.email || '');
      $('#panelMobile').val(d.mobile || '');
      $('#panelKursNr').val(d.kursnummer || '0');
      $('#panelKursJahr').val(d.kursjahr || '');
      $('#panelAktiv').prop('checked', d.aktiv === '1');
      $('#panelTitle').text((d.vorname || '') + ' ' + (d.name || ''));
      $('#panelDeleteBtn').show().data('id', d.id);
      this.renderKonto(d.kontoUserId, d.kontoStatus);
      $('.hybrid-row').removeClass('selected');
      $(tr).addClass('selected');
      this.show();
    },
    openNew() {
      $('#panelId').val('0');
      ['#panelVorname','#panelName','#panelGeburtsdatum','#panelAhv','#panelStrasse','#panelPlz','#panelOrt','#panelEmail','#panelMobile','#panelKursJahr'].forEach(s => $(s).val(''));
      $('#panelKursNr').val('0');
      $('#panelAktiv').prop('checked', true);
      $('#panelTitle').text('Neuer Jungschütze');
      $('#panelDeleteBtn').hide();
      this.renderKonto(0, '');
      $('.hybrid-row').removeClass('selected');
      this.show();
      setTimeout(() => $('#panelVorname').focus(), 250);
    },
    renderKonto(userId, status) {
      userId = parseInt(userId || 0, 10);
      var html = '';
      if (!userId) {
        html = '<div class="text-muted small"><i class="bi bi-dash-circle me-1"></i>Noch kein Konto registriert. Der Jungschütze registriert sich selbst über die Login-Seite.</div>';
      } else if (status === 'pending') {
        html = '<div class="alert alert-warning py-2 small mb-2"><i class="bi bi-hourglass-split me-1"></i>Registrierung wartet auf Freischaltung.</div>'
             + '<div class="d-flex gap-2">'
             + '<button type="button" class="btn btn-success btn-sm flex-grow-1 js-konto-action" data-act="approve" data-uid="' + userId + '"><i class="bi bi-check-lg me-1"></i>Freischalten</button>'
             + '<button type="button" class="btn btn-outline-danger btn-sm js-konto-action" data-act="reject" data-uid="' + userId + '"><i class="bi bi-x-lg me-1"></i>Ablehnen</button>'
             + '</div>';
      } else if (status === 'approved') {
        html = '<div class="d-flex justify-content-between align-items-center">'
             + '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Konto aktiv</span>'
             + '<button type="button" class="btn btn-outline-secondary btn-sm js-konto-action" data-act="disable" data-uid="' + userId + '"><i class="bi bi-pause-circle me-1"></i>Deaktivieren</button>'
             + '</div>';
      } else if (status === 'disabled') {
        html = '<div class="d-flex justify-content-between align-items-center">'
             + '<span class="badge bg-secondary">Deaktiviert</span>'
             + '<button type="button" class="btn btn-success btn-sm js-konto-action" data-act="enable" data-uid="' + userId + '"><i class="bi bi-play-circle me-1"></i>Aktivieren</button>'
             + '</div>';
      } else if (status === 'rejected') {
        html = '<div class="d-flex justify-content-between align-items-center">'
             + '<span class="badge bg-danger">Abgelehnt</span>'
             + '<button type="button" class="btn btn-success btn-sm js-konto-action" data-act="approve" data-uid="' + userId + '"><i class="bi bi-check-lg me-1"></i>Doch freischalten</button>'
             + '</div>';
      } else {
        html = '<div class="text-muted small">Kein Konto.</div>';
      }
      $('#panelKontoArea').html(html);
    },
    show() { $('#editPanel').addClass('open'); $('#panelOverlay').addClass('show'); },
    close() { $('#editPanel').removeClass('open'); $('#panelOverlay').removeClass('show'); $('.hybrid-row').removeClass('selected'); },
    save() {
      const payload = {
        id: $('#panelId').val(),
        vorname: $('#panelVorname').val().trim(),
        name: $('#panelName').val().trim(),
        geburtsdatum: $('#panelGeburtsdatum').val(),
        ahvnummer: $('#panelAhv').val().trim(),
        strasse: $('#panelStrasse').val().trim(),
        plz: $('#panelPlz').val().trim(),
        ort: $('#panelOrt').val().trim(),
        email: $('#panelEmail').val().trim(),
        mobile: $('#panelMobile').val().trim(),
        kursnummer: $('#panelKursNr').val(),
        kursjahr: $('#panelKursJahr').val(),
        aktiv: $('#panelAktiv').is(':checked') ? 1 : 0,
        csrf_token: csrf
      };
      if (!payload.vorname || !payload.name) { msvToast('Vorname und Name sind erforderlich', 'warning'); return; }
      const $btn = $('#panelSaveBtn');
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
      $.post(ep + 'save_single_js.php', payload)
        .done(r => {
          const data = typeof r === 'string' ? JSON.parse(r) : r;
          if (data.success) { msvToast(data.message, 'success'); Panel.close(); loadJs(); }
          else msvToast(data.message || 'Fehler', 'error');
        })
        .fail(x => msvToast((x.responseJSON && x.responseJSON.message) || 'Fehler beim Speichern', 'error'))
        .always(() => $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Speichern'));
    }
  };

  $(document).on('click', '.hybrid-row', function () { Panel.open(this); });
  $('#panelClose, #panelOverlay').on('click', () => Panel.close());
  $('#btnNewJs').on('click', () => Panel.openNew());
  $('#panelSaveBtn').on('click', () => Panel.save());
  $(document).on('keydown', e => { if (e.key === 'Escape' && $('#editPanel').hasClass('open')) Panel.close(); });

  // Konto freischalten / ablehnen / (de)aktivieren
  $(document).on('click', '.js-konto-action', function () {
    const act = $(this).data('act');
    const uid = $(this).data('uid');
    const confirmTxt = {
      approve: 'Login-Konto jetzt freischalten?',
      reject:  'Registrierung ablehnen?',
      disable: 'Konto deaktivieren?',
      enable:  'Konto wieder aktivieren?'
    }[act] || 'Aktion ausführen?';
    msvConfirm(confirmTxt).then(r => {
      if (!r.isConfirmed) return;
      $.post(ep + 'approve_js_user.php', { action: act, user_id: uid, csrf_token: csrf })
        .done(res => {
          const data = typeof res === 'string' ? JSON.parse(res) : res;
          if (data.success) { msvToast(data.message, 'success'); Panel.close(); loadJs(); }
          else msvToast(data.message || 'Fehler', 'error');
        })
        .fail(x => msvToast((x.responseJSON && x.responseJSON.message) || 'Fehler bei der Verarbeitung', 'error'));
    });
  });

  // ---------- Löschen ----------
  let deleteId = null;
  $('#panelDeleteBtn').on('click', function () {
    deleteId = $(this).data('id');
    $('#deleteConfirmText').html('<strong>' + $('#panelVorname').val() + ' ' + $('#panelName').val() + '</strong> wirklich löschen?');
    Panel.close();
    new bootstrap.Modal('#confirmDeleteModal').show();
  });
  $('#confirmDeleteBtn').on('click', function () {
    if (!deleteId) return;
    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
    $.post(ep + 'delete_js.php', { id: deleteId, csrf_token: csrf })
      .done(r => {
        const data = typeof r === 'string' ? JSON.parse(r) : r;
        bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal')).hide();
        if (data.success) { msvToast('Gelöscht', 'success'); loadJs(); }
        else msvToast(data.message || 'Fehler', 'error');
      })
      .fail(() => msvToast('Fehler beim Löschen', 'error'))
      .always(() => { $btn.prop('disabled', false).html('<i class="bi bi-trash me-1"></i>Löschen'); deleteId = null; });
  });

  // ---------- Suche ----------
  $('#searchInput').on('keyup', function () {
    const q = this.value.toLowerCase();
    $('#jsTable tbody tr.hybrid-row').each(function () {
      const d = this.dataset;
      const t = [d.name, d.vorname, d.email, d.ort].join(' ').toLowerCase();
      $(this).toggle(t.includes(q));
    });
  });

  // ---------- Laden ----------
  function loadJs() {
    Panel.close();
    $('#jsTable tbody').html('<tr><td colspan="9" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Lädt…</td></tr>');
    $.get(ep + 'load_js.php', function (html) {
      $('#jsTable tbody').html(html);
      $('#jsCount').text($('#jsTable tbody tr.hybrid-row').length + ' Jungschützen');
      buildMobile();
    }).fail(() => {
      $('#jsTable tbody').html('<tr><td colspan="9" class="text-center text-danger py-4">Fehler beim Laden</td></tr>');
    });
  }

  // ---------- Import ----------
  const dz = document.getElementById('dropZone');
  if (dz) {
    dz.onclick = () => document.getElementById('xlsxFile').click();
    ['dragenter','dragover','dragleave','drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); }));
    ['dragenter','dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('dragging')));
    ['dragleave','drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('dragging')));
    dz.addEventListener('drop', e => uploadPreview(e.dataTransfer.files[0]));
    $('#xlsxFile').on('change', e => uploadPreview(e.target.files[0]));
  }

  function uploadPreview(file) {
    if (!file) return;
    const fd = new FormData();
    fd.append('action', 'preview');
    fd.append('file', file);
    fd.append('csrf_token', csrf);
    dz.classList.add('dragging');
    $.ajax({ url: ep + 'import_js.php', type: 'POST', data: fd, processData: false, contentType: false })
      .done(r => {
        const data = typeof r === 'string' ? JSON.parse(r) : r;
        if (!data.success) { msvToast(data.message || 'Fehler', 'error'); return; }
        previewRows = data.rows || [];
        renderPreview();
        $('#previewInfo').text(data.message);
        $('#importPreview').show();
        $('#confirmImport').show();
        msvToast(previewRows.length + ' Vorschläge', 'info');
      })
      .fail(x => msvToast((x.responseJSON && x.responseJSON.message) || 'Import-Fehler', 'error'))
      .always(() => dz.classList.remove('dragging'));
  }

  function renderPreview() {
    const esc = s => $('<div>').text(s == null ? '' : s).html();
    const tb = $('#previewTable tbody').empty();
    previewRows.forEach((r, i) => {
      tb.append('<tr><td><input type="checkbox" class="form-check-input prev-chk" data-i="' + i + '" checked></td>'
        + '<td>' + esc(r.vorname) + '</td><td>' + esc(r.name) + '</td><td>' + esc(r.geburtsdatum || '') + '</td>'
        + '<td>' + (r.alter != null ? r.alter : '?') + '</td><td>' + esc(r.email || '') + '</td></tr>');
    });
    updateConfirmCount();
  }
  function updateConfirmCount() {
    $('#confirmImportText').text('Import starten (' + $('.prev-chk:checked').length + ')');
  }
  $('#previewSelectAll').on('change', function () { $('.prev-chk').prop('checked', this.checked); updateConfirmCount(); });
  $(document).on('change', '.prev-chk', updateConfirmCount);

  $('#confirmImport').on('click', function () {
    const sel = [];
    $('.prev-chk:checked').each(function () { sel.push(previewRows[$(this).data('i')]); });
    if (!sel.length) { msvToast('Keine Zeilen ausgewählt', 'warning'); return; }
    const $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
    $.post(ep + 'import_js.php', {
      action: 'import',
      rows: JSON.stringify(sel),
      kursnummer: $('#importKursNr').val(),
      kursjahr: $('#importKursJahr').val(),
      csrf_token: csrf
    })
      .done(r => {
        const data = typeof r === 'string' ? JSON.parse(r) : r;
        if (data.success) {
          msvToast(data.message, 'success');
          bootstrap.Modal.getInstance(document.getElementById('importModal')).hide();
          loadJs();
        } else msvToast(data.message || 'Fehler', 'error');
      })
      .fail(() => msvToast('Import-Fehler', 'error'))
      .always(() => { $btn.prop('disabled', false); updateConfirmCount(); });
  });

  $('#importModal').on('hidden.bs.modal', () => {
    $('#importPreview').hide(); $('#confirmImport').hide(); previewRows = []; $('#xlsxFile').val('');
  });

  // ---------- Mobile Cards ----------
  function buildMobile() {
    if (!window.matchMedia('(max-width: 767.98px)').matches) return;
    const c = document.getElementById('mobileJsCards');
    if (!c) return;
    let html = '';
    $('#jsTable tbody tr.hybrid-row').each(function () {
      const d = this.dataset;
      html += '<div class="mobile-card" data-id="' + d.id + '">'
        + '<div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)"><div>'
        + '<div class="fw-bold">' + d.name + ' ' + d.vorname + '</div>'
        + '<small class="text-muted">' + (d.email || '') + '</small></div><i class="bi bi-chevron-down"></i></div>'
        + '<div class="mobile-card-body"><p class="mb-1"><strong>Geb.:</strong> ' + (d.geburtsdatum || '-') + '</p>'
        + '<p class="mb-2"><strong>Ort:</strong> ' + (d.ort || '-') + '</p>'
        + '<button type="button" class="btn btn-outline-primary btn-sm w-100 mobile-edit-btn" data-id="' + d.id + '"><i class="bi bi-pencil me-1"></i>Bearbeiten</button></div></div>';
    });
    c.innerHTML = html || '<div class="text-center text-muted py-4">Keine Jungschützen</div>';
  }
  $(document).on('click', '.mobile-edit-btn', function (e) {
    e.stopPropagation();
    const tr = document.getElementById('jrow' + $(this).data('id'));
    if (tr) Panel.open(tr);
  });
  window.matchMedia('(max-width: 767.98px)').addEventListener('change', buildMobile);

  loadJs();
});
</script>

<?php include 'footer.inc.php'; ?>
