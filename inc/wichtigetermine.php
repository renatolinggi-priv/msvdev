<?php
// wichtigetermine.php – Hybrid Layout (kompakt)
include 'dbconnect.inc.php';

$page_specific_css = <<<'CSS'
/* ===== Wichtige Termine – Kompaktes Hybrid Layout ===== */

/* --- Inhaltsbreite begrenzen (Box umschliesst die schmale Tabelle eng) --- */
.main-content-wrapper { max-width: 880px; }

/* --- Wrapper --- */
.table-wrapper {
  border: 1px solid #e2e8f0;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  overflow: visible;
}
.table-title {
  position: sticky; top: 0; z-index: 8;
  margin: 0; padding: 0.75rem 1rem; font-weight: 600; font-size: 0.95rem;
  color: var(--dark-color);
  border-bottom: 2px solid #e2e8f0;
  background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%);
}

/* --- Hybrid-Tabelle (kompakt) --- */
.hybrid-table {
  width: 100%; border-collapse: collapse; font-size: 0.85rem;
}
.hybrid-table thead th {
  padding: 0.5rem 0.75rem; font-size: 0.7rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;
  background: linear-gradient(180deg, #f8fafc, #eef2f7);
  border-bottom: 2px solid #e2e8f0;
  position: sticky; top: 0; z-index: 6;
}
.hybrid-table tbody tr.hybrid-row {
  cursor: pointer; transition: background 0.15s;
}
.hybrid-table tbody tr.hybrid-row:hover {
  background: rgba(99,102,241,0.05);
}
.hybrid-table tbody tr.hybrid-row.selected {
  background: rgba(59,130,246,0.08);
  box-shadow: inset 3px 0 0 #3b82f6;
}
.hybrid-table tbody td {
  padding: 0.4rem 0.75rem; vertical-align: middle;
  border-bottom: 1px solid #f1f5f9;
}

/* Inhaltsspalten */
.h-title { font-weight: 500; }
.h-date { font-size: 0.8rem; white-space: nowrap; color: #64748b; }
.h-time { text-align: center; font-size: 0.8rem; }

/* Kompakter Delete-Button */
.btn-delete-sm {
  width: 26px; height: 26px; padding: 0;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 0.75rem; border-radius: 4px;
  color: #94a3b8; border: 1px solid transparent; background: transparent;
  transition: all 0.15s;
}
.btn-delete-sm:hover {
  color: #dc3545; border-color: #dc3545; background: rgba(220,53,69,0.05);
}

/* Monats-Separator */
.hybrid-table tbody tr.month-separator td {
  padding: 0.35rem 0.75rem;
  font-size: 0.7rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.5px;
  color: #94a3b8; background: #fafbfc;
  border-bottom: 1px solid #e2e8f0;
  cursor: default;
}
.hybrid-table tbody tr.month-separator:hover { background: #fafbfc; }

/* Wochentag-Badge kompakt */
.wd-badge {
  display: inline-block; font-size: 0.7rem; font-weight: 600;
  color: #64748b; background: #f1f5f9; border-radius: 3px;
  padding: 1px 5px; margin-right: 4px;
}

/* Zeit-Badge kompakt */
.time-badge {
  display: inline-block; font-size: 0.75rem; font-weight: 500;
  color: #0d6efd; background: rgba(13,110,253,0.08); border-radius: 4px;
  padding: 2px 8px;
}

/* --- Slide-Panel --- */
.hybrid-edit-panel {
  position: fixed; top: 0; right: -380px; width: 360px; height: 100vh;
  background: #fff; box-shadow: -8px 0 30px rgba(0,0,0,0.12);
  z-index: 1060; transition: right 0.3s cubic-bezier(0.4,0,0.2,1);
  display: flex; flex-direction: column;
}
.hybrid-edit-panel.open { right: 0; }
.panel-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.3);
  z-index: 1055; opacity: 0; visibility: hidden; transition: all 0.3s;
}
.panel-overlay.show { opacity: 1; visibility: visible; }
.panel-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 0.875rem 1.125rem; border-bottom: 1px solid #e2e8f0;
  background: #f8fafc; flex-shrink: 0;
}
.panel-body { padding: 1.125rem; overflow-y: auto; flex: 1; }
.panel-label {
  display: block; font-size: 0.78rem; font-weight: 600;
  color: #64748b; margin-bottom: 0.3rem;
}

/* --- Aktions-Card --- */
.action-card { border-color: #e2e8f0; }
.action-card-header { cursor: pointer; user-select: none; background-color: #f8fafc; }
.action-card-header:hover { background-color: #f1f5f9; }
.action-chevron { transition: transform .2s ease; }
.action-card-header[aria-expanded="true"] .action-chevron { transform: rotate(180deg); }

/* --- Skeleton Loader --- */
.skeleton {
  height: 16px; border-radius: 4px;
  background: linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);
  background-size: 200% 100%; animation: loading 1.5s infinite;
}
@keyframes loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* --- Empty State --- */
.empty-state {
  padding: 3rem 1rem; text-align: center; color: #94a3b8;
}
.empty-state i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; }

/* --- Mobile --- */
@media (max-width: 767.98px) {
  .desktop-table-container { display: none !important; }
  .mobile-cards-container { display: block !important; }
  /* Slide-Panel auf Mobile = Fullscreen */
  .hybrid-edit-panel {
    width: 100% !important; right: -105% !important;
  }
  .hybrid-edit-panel.open { right: 0 !important; }
  .panel-body .form-control, .panel-body input {
    min-height: 48px !important; font-size: 16px !important;
  }
  .panel-body .btn {
    min-height: 48px !important; font-size: 16px !important;
  }

  /* Container-Padding auf Mobile minimal */
  .main-content-wrapper {
    padding: 0.25rem !important;
    margin: 0 !important;
    box-shadow: none !important;
    border: none !important;
    background: transparent !important;
  }
  .content-background {
    padding: 0.375rem !important;
    margin: 0 !important;
    box-shadow: none !important;
    border: none !important;
    background: transparent !important;
  }
  .container-fluid {
    padding-left: 0.25rem !important;
    padding-right: 0.25rem !important;
  }
  .table-wrapper {
    border: none;
    border-radius: 0;
    box-shadow: none;
    margin: 0 -0.375rem;
  }
  .mobile-cards-scroll {
    padding: 0;
  }
  .mobile-event-card {
    border-radius: 0.5rem; padding: 0.5rem 0.65rem; margin-bottom: 0.35rem;
    border-color: #edf0f3;
  }
  .mobile-month-header {
    padding: 0.6rem 0 0.2rem; margin-left: 0.125rem;
  }
  .d-flex.flex-wrap.gap-3 {
    gap: 0.5rem !important;
    margin-bottom: 0.5rem !important;
  }
  /* Touch-friendly Inputs */
  .modal .form-control, .modal input, .modal select {
    min-height: 48px !important; font-size: 16px !important;
  }
  .modal .btn, .modal .btn-compact {
    min-height: 48px !important; font-size: 16px !important; padding: 0.5rem 1rem !important;
  }
}
@media (min-width: 768px) {
  .mobile-cards-container { display: none !important; }
}

/* --- Mobile Event Cards --- */
.mobile-event-card {
  background: white; border: 1px solid #e2e8f0;
  border-radius: 0.75rem; padding: 0.6rem 0.875rem; margin-bottom: 0.5rem;
  cursor: pointer; transition: background 0.15s, border-color 0.15s;
}
.mobile-event-card:active {
  background: #f8fafc;
}
.mobile-event-card.selected {
  border-color: #3b82f6; background: rgba(59,130,246,0.04);
}
.mobile-month-header {
  font-weight: 700; font-size: 0.8rem; text-transform: uppercase;
  letter-spacing: 0.5px; color: #64748b;
  padding: 0.75rem 0.25rem 0.25rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 0.4rem;
}
@media (max-width: 576px) {
  .modal-dialog { margin: 0; max-width: 100%; height: 100%; }
  .modal-content { height: 100%; border-radius: 0; }
}
CSS;

include 'header.inc.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-8 col-lg-10 col-12 ps-lg-3">
      <div class="main-content-wrapper">
        <div class="row mb-4 d-none d-md-flex">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-calendar-event me-2"></i>Wichtige Termine
            </h2>
            <p class="text-muted mb-0 small">Termine verwalten und exportieren
              <span class="badge bg-secondary ms-1" id="eventCount" style="font-size:0.7rem; vertical-align:middle;">0</span>
            </p>
          </div>
        </div>

        <div class="content-background">
          <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <!-- Mobile: Neuer-Termin + Jahrauswahl -->
          <div class="d-flex d-md-none align-items-center gap-2 mb-3 justify-content-end">
            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#newEventModal">
              <i class="bi bi-plus-lg me-1"></i>Neu
            </button>
            <select id="eventYearMobile" class="form-select form-select-sm" style="width: auto; min-width: 80px;"></select>
          </div>

          <!-- Desktop: Jahr-Auswahl + Aktionen -->
          <div class="d-none d-md-flex flex-wrap gap-3 align-items-start mb-4">
            <div class="d-flex align-items-center gap-2">
              <label for="eventYear" class="form-label fw-bold mb-0 text-nowrap">
                <i class="bi bi-calendar3 me-1"></i>Jahr:
              </label>
              <select id="eventYear" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
            </div>

            <div class="card action-card mb-0">
              <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                   data-bs-toggle="collapse" data-bs-target="#wichtigetermineActions"
                   aria-expanded="false" aria-controls="wichtigetermineActions">
                <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                <i class="bi bi-chevron-down action-chevron"></i>
              </div>
              <div class="collapse" id="wichtigetermineActions">
                <div class="card-body pt-2 pb-3 px-3">
                  <div class="row g-2 mb-3">
                    <div class="col-6">
                      <button type="button" class="btn btn-outline-success btn-sm w-100" data-bs-toggle="modal" data-bs-target="#newEventModal">
                        <i class="bi bi-plus-lg me-1"></i>Hinzufügen
                      </button>
                    </div>
                    <div class="col-6">
                      <button type="button" class="btn btn-outline-info btn-sm w-100" data-bs-toggle="modal" data-bs-target="#copyYearModal"
                              data-tooltip="Termine vom Vorjahr übernehmen">
                        <i class="bi bi-calendar2-week me-1"></i>Vom Vorjahr
                      </button>
                    </div>
                  </div>
                  <div class="border-top pt-2 mb-1">
                    <small class="text-muted d-block mb-2"><i class="bi bi-gear me-1"></i>Verwalten</small>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="publishChangelogBtn">
                      <i class="bi bi-megaphone me-1"></i>Veröffentlichen
                    </button>
                  </div>
                  <div class="border-top pt-2">
                    <small class="text-muted d-block mb-2"><i class="bi bi-download me-1"></i>Exporte</small>
                    <div class="row g-2">
                      <div class="col-6">
                        <button type="button" id="generatePDFButton" class="btn btn-outline-danger btn-sm w-100">
                          <i class="bi bi-file-pdf me-1"></i>PDF
                        </button>
                      </div>
                      <div class="col-6">
                        <button type="button" id="generateIcsButton" class="btn btn-outline-secondary btn-sm w-100">
                          <i class="bi bi-calendar-plus me-1"></i>ICS
                        </button>
                      </div>
                    </div>
                  </div>
                  <div class="border-top mt-2 pt-2 text-end">
                    <button type="button" id="deleteAllBtn" class="btn btn-link btn-sm text-danger text-decoration-none p-0">
                      <i class="bi bi-trash me-1"></i>Alle Termine löschen
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Hybrid-Tabelle -->
          <div class="table-wrapper" id="eventsListContainer">
            <h5 class="table-title"><i class="bi bi-calendar-event me-2"></i>Wichtige Termine</h5>
            <div class="desktop-table-container">
              <table class="hybrid-table" id="eventsTable">
                <thead>
                  <tr>
                    <th style="width:45%"><i class="bi bi-tag me-1"></i>Bezeichnung</th>
                    <th style="width:25%"><i class="bi bi-calendar-date me-1"></i>Datum</th>
                    <th style="width:20%; text-align:center"><i class="bi bi-clock me-1"></i>Zeit</th>
                    <th style="width:10%; text-align:center">Optionen</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <!-- Mobile Cards -->
            <div class="mobile-cards-container" id="mobileEventsCards">
              <div class="mobile-cards-scroll"></div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Slide-Panel (Edit) -->
<div class="panel-overlay" id="panelOverlay"></div>
<div class="hybrid-edit-panel" id="editPanel">
  <div class="panel-header">
    <h6 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Termin bearbeiten</h6>
    <button class="btn btn-sm btn-outline-secondary" id="panelClose"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="panel-body">
    <div class="mb-3">
      <label class="panel-label"><i class="bi bi-tag me-1"></i>Bezeichnung</label>
      <input type="text" class="form-control form-control-sm" id="panelName" required>
    </div>
    <div class="mb-3">
      <label class="panel-label"><i class="bi bi-calendar-date me-1"></i>Datum</label>
      <input type="date" class="form-control form-control-sm" id="panelDate" required>
    </div>
    <div class="mb-3">
      <label class="panel-label"><i class="bi bi-clock me-1"></i>Zeit</label>
      <input type="text" class="form-control form-control-sm" id="panelTime" placeholder="z.B. 18.00 - 20.00" required>
    </div>
    <hr>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-primary btn-sm flex-fill" id="panelSaveBtn">
        <i class="bi bi-check-circle me-1"></i>Speichern
      </button>
      <button class="btn btn-outline-danger btn-sm flex-fill" id="panelDeleteBtn">
        <i class="bi bi-trash me-1"></i>Löschen
      </button>
    </div>
  </div>
</div>

<!-- Modal: Neuer Termin -->
<div class="modal fade" id="newEventModal" tabindex="-1" aria-labelledby="newEventModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newEventModalLabel"><i class="bi bi-plus-lg me-2"></i>Neuen Termin hinzufügen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="newEventName" class="form-label">Bezeichnung *</label>
          <input type="text" class="form-control" id="newEventName" placeholder="z.B. DV SKSG in Rothenturm" required>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="newEventDate" class="form-label">Datum *</label>
            <input type="date" class="form-control" id="newEventDate" required>
          </div>
          <div class="col-md-6">
            <label for="newEventTime" class="form-label">Zeit *</label>
            <input type="text" class="form-control" id="newEventTime" placeholder="z.B. 18.00 - 20.00" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-compact" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-1"></i>Abbrechen
        </button>
        <button type="button" class="btn btn-outline-success btn-compact" id="addEventBtn">
          <i class="bi bi-plus-circle me-1"></i>Hinzufügen
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Löschen bestätigen -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteModalLabel">
          <i class="bi bi-exclamation-triangle text-warning me-2"></i>Bestätigung erforderlich
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center">
          <i class="bi bi-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>
          <div>
            <strong id="deleteConfirmText">Möchtest du diesen Termin wirklich löschen?</strong>
            <br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden.</small>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-compact" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-1"></i>Abbrechen
        </button>
        <button type="button" class="btn btn-outline-danger btn-compact" id="confirmDeleteButton">
          <i class="bi bi-trash me-1"></i>Löschen
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Vom Vorjahr übernehmen -->
<div class="modal fade" id="copyYearModal" tabindex="-1" aria-labelledby="copyYearModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="copyYearModalLabel"><i class="bi bi-calendar2-week me-2"></i>Termine vom Vorjahr übernehmen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
          <label for="copySourceYear" class="form-label mb-0 fw-semibold text-nowrap">Quelljahr</label>
          <select id="copySourceYear" class="form-select form-select-sm" style="width:auto; min-width:90px;"></select>
          <span class="text-muted small ms-1">→ wird ins Jahr <strong id="copyTargetYearLabel"></strong> übernommen</span>
          <div class="form-check ms-auto mb-0">
            <input class="form-check-input" type="checkbox" id="copySelectAll">
            <label class="form-check-label small" for="copySelectAll">Alle auswählen</label>
          </div>
        </div>
        <p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>Das Datum wird automatisch auf denselben Wochentag im Zieljahr verschoben. Bereits vorhandene Termine sind abgewählt.</p>
        <div id="copyList"><div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Lädt…</div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-compact" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i>Abbrechen</button>
        <button type="button" class="btn btn-outline-info btn-compact" id="copyApplyBtn"><i class="bi bi-check2-circle me-1"></i>Übernehmen</button>
      </div>
    </div>
  </div>
</div>

<script>
$(function() {
  const csrfToken = $('#csrfToken').val();
  const currentYear = new Date().getFullYear();
  let deleteId = null;

  const monthNames = {
    1:'Januar',2:'Februar',3:'März',4:'April',5:'Mai',6:'Juni',
    7:'Juli',8:'August',9:'September',10:'Oktober',11:'November',12:'Dezember'
  };
  const weekdayNames = ['So','Mo','Di','Mi','Do','Fr','Sa'];

  // ========== Slide-Panel ==========
  const EditPanel = {
    currentId: null,

    open(el) {
      const d = el.dataset;
      this.currentId = d.id;
      $('#panelName').val(d.name);
      $('#panelDate').val(d.date);
      $('#panelTime').val(d.time);

      $('.hybrid-row').removeClass('selected');
      $('.mobile-event-card').removeClass('selected');
      $(el).addClass('selected');
      $('#editPanel').addClass('open');
      $('#panelOverlay').addClass('show');
      setTimeout(() => $('#panelName').focus(), 300);
    },

    close() {
      $('#editPanel').removeClass('open');
      $('#panelOverlay').removeClass('show');
      $('.hybrid-row').removeClass('selected');
      $('.mobile-event-card').removeClass('selected');
      this.currentId = null;
    },

    save() {
      if (!this.currentId) return;
      const name = $('#panelName').val().trim();
      const date = $('#panelDate').val();
      const time = $('#panelTime').val().trim();

      if (!name || !date || !time) { msvToast('Bitte alle Felder ausfüllen', 'warning'); return; }

      const $btn = $('#panelSaveBtn'), orig = $btn.html();
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

      $.post('wichtigetermine/update_event.php', {
        event_id: this.currentId, event_name: name, event_date: date, event_time: time, csrf_token: csrfToken
      })
      .done(resp => {
        const r = typeof resp === 'object' ? resp : JSON.parse(resp);
        if (r.success) {
          msvToast('Termin aktualisiert', 'success');
          EditPanel.close();
          setTimeout(() => loadEvents($('#eventYear').val()), 300);
        } else { msvToast('Fehler: ' + (r.message || 'Unbekannt'), 'error'); }
      })
      .fail(() => msvToast('Fehler beim Speichern', 'error'))
      .always(() => $btn.prop('disabled', false).html(orig));
    }
  };

  // Panel-Events: Desktop-Zeilen
  $(document).on('click', '.hybrid-row', function(e) {
    if ($(e.target).closest('.btn-delete-sm').length) return;
    EditPanel.open(this);
  });
  // Panel-Events: Mobile Cards
  $(document).on('click', '.mobile-event-card', function() {
    EditPanel.open(this);
  });
  $('#panelClose, #panelOverlay').on('click', () => EditPanel.close());
  $(document).on('keydown', e => {
    if (e.key === 'Escape' && $('#editPanel').hasClass('open')) { EditPanel.close(); e.stopImmediatePropagation(); }
  });
  $('#panelSaveBtn').on('click', () => EditPanel.save());
  $('#editPanel input').on('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); EditPanel.save(); } });

  // ========== Year-Select (Desktop + Mobile) ==========
  function initializeYearDropdown() {
    $('#eventYear, #eventYearMobile').each(function() {
      const $sel = $(this).empty();
      for (let y = currentYear + 1; y >= currentYear - 3; y--) {
        $sel.append($('<option/>').val(y).text(y).prop('selected', y === currentYear));
      }
    });
  }

  // ========== Vom Vorjahr übernehmen ==========
  let copyData = [];
  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
      ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }
  function isoToDate(iso) { const p = iso.split('-').map(Number); return new Date(p[0], p[1]-1, p[2]); }
  function dateToIso(dt) {
    return dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0');
  }
  function fmtShort(dt) {
    return weekdayNames[dt.getDay()] + ' ' + String(dt.getDate()).padStart(2,'0') + '.' + String(dt.getMonth()+1).padStart(2,'0') + '.';
  }
  // Verschiebt ein Datum auf denselben Wochentag im Zieljahr (schaltjahr-sicher).
  function shiftDateToYear(isoDate, targetYear) {
    const p = isoDate.split('-').map(Number);
    const src = new Date(p[0], p[1]-1, p[2]);
    let cand = new Date(targetYear, p[1]-1, p[2]);
    if (cand.getMonth() !== p[1]-1) cand = new Date(targetYear, p[1], 0); // 29.2. -> 28.2.
    let delta = src.getDay() - cand.getDay();
    if (delta > 3) delta -= 7; else if (delta < -3) delta += 7;
    cand.setDate(cand.getDate() + delta);
    return cand;
  }
  function smartRename(name, sourceYear, targetYear) {
    let n = String(name || '').replace(/^(\d+)\.(\s)/, (_, num, sp) => (parseInt(num,10)+1) + '.' + sp);
    n = n.replace(/\b(20\d{2})\b/g, yr => (parseInt(yr,10) === sourceYear ? String(targetYear) : yr));
    return n;
  }
  function copyNorm(s) { return String(s || '').trim().toLowerCase().replace(/\s+/g, ' '); }

  function renderCopyList(events, sourceYear, targetYear) {
    const targetNames = new Set();
    $('#eventsTable tbody tr.hybrid-row').each(function () {
      const b = copyNorm($(this).attr('data-name'));
      if (b) targetNames.add(b);
    });
    copyData = events.map(ev => {
      const newName = smartRename(ev.name, sourceYear, targetYear);
      const iso = dateToIso(shiftDateToYear(ev.date, targetYear));
      const preview = fmtShort(isoToDate(ev.date)) + ' → ' + fmtShort(isoToDate(iso));
      const dup = targetNames.has(copyNorm(newName)) || targetNames.has(copyNorm(ev.name));
      return { name: newName, date: iso, time: ev.time, preview, dup };
    });
    if (!copyData.length) {
      $('#copyList').html('<div class="text-muted py-3 text-center">Keine Termine im Quelljahr.</div>');
      return;
    }
    let html = '<div class="list-group">';
    copyData.forEach((e, i) => {
      html += '<label class="list-group-item d-flex gap-2 align-items-start" style="cursor:pointer">' +
        '<input class="form-check-input flex-shrink-0 mt-1 copy-cb" type="checkbox" data-i="' + i + '"' + (e.dup ? '' : ' checked') + '>' +
        '<span class="flex-grow-1">' +
          '<span class="fw-semibold">' + escapeHtml(e.name) + '</span>' +
          (e.dup ? ' <span class="badge bg-warning text-dark">bereits vorhanden</span>' : '') +
          '<br><small class="text-muted">' + escapeHtml(e.preview) + ' · ' + escapeHtml(e.time) + '</small>' +
        '</span>' +
      '</label>';
    });
    html += '</div>';
    $('#copyList').html(html);
    syncCopySelectAll();
  }
  function syncCopySelectAll() {
    const total = $('#copyList .copy-cb').length;
    const checked = $('#copyList .copy-cb:checked').length;
    $('#copySelectAll').prop('checked', total > 0 && checked === total);
  }
  function loadCopyList() {
    const sourceYear = parseInt($('#copySourceYear').val(), 10);
    const targetYear = parseInt($('#eventYear').val(), 10);
    $('#copyList').html('<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Lädt…</div>');
    $.getJSON('wichtigetermine/load_events.php', { year: sourceYear })
      .done(resp => {
        if (resp && resp.success) renderCopyList(resp.events || [], sourceYear, targetYear);
        else $('#copyList').html('<div class="text-danger py-3 text-center">Fehler beim Laden.</div>');
      })
      .fail(() => $('#copyList').html('<div class="text-danger py-3 text-center">Fehler beim Laden.</div>'));
  }

  $('#copyYearModal').on('show.bs.modal', function () {
    const targetYear = parseInt($('#eventYear').val(), 10);
    $('#copyTargetYearLabel').text(targetYear);
    const $src = $('#copySourceYear').empty();
    for (let y = currentYear + 1; y >= currentYear - 3; y--) {
      if (y === targetYear) continue;
      const $o = $('<option/>').val(y).text(y);
      if (y === targetYear - 1) $o.prop('selected', true);
      $src.append($o);
    }
    loadCopyList();
  });
  $('#copySourceYear').on('change', loadCopyList);
  $('#copySelectAll').on('change', function () { $('#copyList .copy-cb').prop('checked', this.checked); });
  $(document).on('change', '.copy-cb', syncCopySelectAll);

  $('#copyApplyBtn').on('click', function () {
    const targetYear = parseInt($('#eventYear').val(), 10);
    const chosen = [];
    $('#copyList .copy-cb:checked').each(function () { chosen.push(copyData[$(this).data('i')]); });
    if (!chosen.length) { msvToast('Bitte mindestens einen Termin auswählen', 'warning'); return; }
    const events = chosen.map(e => ({ name: e.name, date: e.date, time: e.time }));
    const $b = $(this), t = $b.html();
    $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Übernehme…');
    $.post('wichtigetermine/copy_events.php', {
      target_year: targetYear, events, csrf_token: csrfToken
    })
      .done(resp => {
        const r = typeof resp === 'object' ? resp : JSON.parse(resp);
        if (r && r.success) {
          bootstrap.Modal.getInstance(document.getElementById('copyYearModal'))?.hide();
          msvToast(r.count + ' Termin(e) übernommen', 'success');
          loadEvents(targetYear);
        } else {
          msvToast(r && r.message ? r.message : 'Fehler beim Übernehmen', 'error');
        }
      })
      .fail(() => msvToast('Fehler beim Übernehmen', 'error'))
      .always(() => $b.prop('disabled', false).html(t));
  });

  // ========== Skeleton ==========
  function showSkeleton() {
    const r = '<tr><td><div class="skeleton" style="width:65%"></div></td><td><div class="skeleton" style="width:55%"></div></td><td><div class="skeleton" style="width:45%"></div></td><td></td></tr>';
    $('#eventsTable tbody').html(r.repeat(6));
  }

  // ========== Events laden ==========
  function loadEvents(year) {
    EditPanel.close();
    showSkeleton();

    $.getJSON('wichtigetermine/load_events.php', { year })
    .done(data => {
      if (data.success && data.events && data.events.length) {
        renderEvents(data.events, year);
      } else {
        $('#eventsTable tbody').html('<tr><td colspan="4"><div class="empty-state"><i class="bi bi-calendar-x"></i>Keine Termine für ' + year + '</div></td></tr>');
        $('#eventCount').text(0);
        buildMobileCards();
      }
    })
    .fail(() => {
      $('#eventsTable tbody').html('<tr><td colspan="4" class="text-center text-danger py-3"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden</td></tr>');
      msvToast('Fehler beim Laden der Termine', 'error');
    });
  }

  // ========== Events rendern ==========
  function renderEvents(events, year) {
    let html = '', curMonth = 0;

    events.forEach(ev => {
      const d = new Date(ev.date);
      const month = d.getMonth() + 1;
      const esc = s => $('<span>').text(s).html();

      if (month !== curMonth) {
        html += `<tr class="month-separator"><td colspan="4"><i class="bi bi-calendar2-month me-1"></i>${monthNames[month]} ${year}</td></tr>`;
        curMonth = month;
      }

      const wd = weekdayNames[d.getDay()];
      const dateFmt = String(d.getDate()).padStart(2,'0') + '.' + String(month).padStart(2,'0') + '.' + d.getFullYear();

      html += `<tr class="hybrid-row" id="row${ev.ID}" data-id="${ev.ID}" data-name="${esc(ev.name)}" data-date="${ev.date}" data-time="${esc(ev.time)}">
        <td class="h-title">${esc(ev.name)}</td>
        <td class="h-date"><span class="wd-badge">${wd}</span>${dateFmt}</td>
        <td class="h-time"><span class="time-badge">${esc(ev.time)}</span></td>
        <td class="text-center"><button class="btn-delete-sm delete-event" data-id="${ev.ID}" data-name="${esc(ev.name)}" data-tooltip="Löschen"><i class="bi bi-trash3"></i></button></td>
      </tr>`;
    });

    $('#eventsTable tbody').html(html);
    $('#eventCount').text(events.length);
    buildMobileCards();
  }

  // ========== Mobile Cards ==========
  function buildMobileCards() {
    const sc = document.querySelector('#mobileEventsCards .mobile-cards-scroll');
    if (!sc) return;

    const rows = document.querySelectorAll('#eventsTable tbody tr.hybrid-row, #eventsTable tbody tr.month-separator');
    if (!rows.length) { sc.innerHTML = '<div class="empty-state"><i class="bi bi-calendar-x"></i>Keine Termine</div>'; return; }

    let html = '';
    rows.forEach(row => {
      if (row.classList.contains('month-separator')) {
        html += `<div class="mobile-month-header">${row.querySelector('td')?.textContent?.trim() || ''}</div>`;
        return;
      }
      if (!row.classList.contains('hybrid-row')) return;
      const d = row.dataset, dt = new Date(d.date);
      const wd = weekdayNames[dt.getDay()];
      const ds = String(dt.getDate()).padStart(2,'0') + '.' + String(dt.getMonth()+1).padStart(2,'0') + '.' + dt.getFullYear();

      html += `<div class="mobile-event-card" data-id="${d.id}" data-name="${d.name}" data-date="${d.date}" data-time="${d.time}">
        <div class="d-flex justify-content-between align-items-center">
          <div style="min-width:0;">
            <div style="font-weight:600; font-size:0.85rem;">${d.name}</div>
            <div class="d-flex align-items-center gap-1" style="font-size:0.8rem; color:#6c757d; margin-top:0.15rem;">
              <span class="wd-badge">${wd}</span><span>${ds}</span>
              <span style="color:#ced4da;">·</span>
              <span class="time-badge">${d.time}</span>
            </div>
          </div>
          <i class="bi bi-chevron-right" style="color:#cbd5e1; font-size:0.9rem; flex-shrink:0;"></i>
        </div>
      </div>`;
    });
    sc.innerHTML = html;
  }

  // ========== Neuer Termin ==========
  $('#addEventBtn').on('click', function() {
    const name = $('#newEventName').val().trim(), date = $('#newEventDate').val(), time = $('#newEventTime').val().trim();
    if (!name || !date || !time) { msvToast('Bitte alle Felder ausfüllen', 'warning'); return; }

    const $btn = $(this), orig = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

    $.post('wichtigetermine/add_event.php', { event_name: name, event_date: date, event_time: time, year: $('#eventYear').val(), csrf_token: csrfToken })
    .done(resp => {
      const r = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (r.success) {
        $('#newEventModal').modal('hide');
        $('#newEventName, #newEventDate, #newEventTime').val('');
        msvToast('Termin hinzugefügt', 'success');
        loadEvents($('#eventYear').val());
      } else { msvToast('Fehler: ' + (r.message || 'Unbekannt'), 'error'); }
    })
    .fail(() => msvToast('Fehler beim Hinzufügen', 'error'))
    .always(() => $btn.prop('disabled', false).html(orig));
  });

  // ========== Löschen ==========
  $(document).on('click', '.delete-event', function(e) {
    e.stopPropagation();
    deleteId = $(this).data('id');
    const name = $(this).data('name') || 'diesen Termin';
    $('#deleteConfirmText').text('«' + name + '» wirklich löschen?');
    EditPanel.close();
    $('#confirmDeleteModal').modal('show');
  });

  $('#panelDeleteBtn').on('click', function() {
    deleteId = EditPanel.currentId;
    const name = $('#panelName').val() || 'diesen Termin';
    $('#deleteConfirmText').text('«' + name + '» wirklich löschen?');
    EditPanel.close();
    $('#confirmDeleteModal').modal('show');
  });

  $('#confirmDeleteButton').on('click', function() {
    if (!deleteId) return;
    const $btn = $(this), orig = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

    $.post('wichtigetermine/delete_event.php', { event_id: deleteId, csrf_token: csrfToken })
    .done(resp => {
      const r = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (r.success) {
        $('#confirmDeleteModal').modal('hide');
        msvToast('Termin gelöscht', 'success');
        setTimeout(() => loadEvents($('#eventYear').val()), 300);
      } else { msvToast('Fehler: ' + (r.message || 'Unbekannt'), 'error'); }
    })
    .fail(() => msvToast('Fehler beim Löschen', 'error'))
    .always(() => { $btn.prop('disabled', false).html(orig); deleteId = null; });
  });

  // ========== Alle Termine löschen ==========
  $('#deleteAllBtn').on('click', async function() {
    const year = $('#eventYear').val();
    const count = parseInt($('#eventCount').text(), 10) || 0;
    if (!count) { msvToast('Keine Termine zum Löschen vorhanden', 'warning'); return; }
    const r = await msvConfirm('Alle Termine löschen?', `Alle ${count} Termine des Jahres ${year} werden unwiderruflich gelöscht.`, 'Alle löschen');
    if (!r.isConfirmed) return;

    const $btn = $(this), orig = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

    $.post('wichtigetermine/delete_all_events.php', { year, csrf_token: csrfToken })
    .done(resp => {
      const r2 = typeof resp === 'object' ? resp : JSON.parse(resp);
      if (r2.success) {
        msvToast((r2.count || 0) + ' Termin(e) gelöscht', 'success');
        loadEvents(year);
      } else { msvToast('Fehler: ' + (r2.message || 'Unbekannt'), 'error'); }
    })
    .fail(() => msvToast('Fehler beim Löschen', 'error'))
    .always(() => $btn.prop('disabled', false).html(orig));
  });

  // ========== Exporte ==========
  function triggerDownload(url) {
    const a = document.createElement('a'); a.href = url; a.download = '';
    document.body.appendChild(a); a.click(); a.remove();
  }

  function exportAction(btn, url, paramName) {
    const $btn = $(btn), orig = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
    $.getJSON(url, { year: $('#eventYear').val() })
    .done(data => {
      const link = data[paramName];
      if (data.success && link) { triggerDownload(link); msvToast('Download gestartet', 'success'); }
      else { msvToast(data.message || 'Fehler', 'error'); }
    })
    .fail(() => msvToast('Export-Fehler', 'error'))
    .always(() => $btn.prop('disabled', false).html(orig));
  }

  $('#generateIcsButton').on('click', function() { exportAction(this, 'wichtigetermine/export_all_ics.php', 'ics_link'); });
  $('#generatePDFButton').on('click', function() { exportAction(this, 'wichtigetermine/create_pdf.php', 'pdf_link'); });

  // ========== Shortcuts ==========
  $(document).on('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'n') { e.preventDefault(); $('#newEventModal').modal('show'); } });
  $('#newEventModal').on('shown.bs.modal', () => $('#newEventName').trigger('focus'));

  // ========== Jahrwechsel (beide Selects synchron) ==========
  $('#eventYear, #eventYearMobile').on('change', function() {
    const val = $(this).val();
    $('#eventYear, #eventYearMobile').val(val);
    loadEvents(val);
  });

  // ========== Resize: Mobile Cards neu bauen ==========
  let resizeTimer;
  $(window).on('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(buildMobileCards, 200);
  });

  // ========== Veröffentlichen ==========
  $('#publishChangelogBtn').on('click', async function() {
    const r = await msvConfirm('Änderung veröffentlichen?', 'Ein Eintrag wird auf der Website angezeigt.', 'Veröffentlichen');
    if (!r.isConfirmed) return;
    $.post('changelog_publish.php', {
        kategorie: 'termine',
        tabelle: 'wichtige_termine',
        jahr: $('#eventYear').val(),
        beschreibung: 'Wichtige Termine aktualisiert',
        csrf_token: csrfToken
    }).done(function(res) {
        if (res.success) msvToast(res.message, 'success');
        else msvToast(res.message || 'Fehler', 'error');
    }).fail(function() {
        msvToast('Veröffentlichung fehlgeschlagen', 'error');
    });
  });

  // ========== Start ==========
  initializeYearDropdown();
  loadEvents(currentYear);
});
</script>

<?php include 'footer.inc.php'; ?>
