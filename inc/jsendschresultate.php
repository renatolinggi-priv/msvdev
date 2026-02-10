<?php
// jsendschresultate.php – Resultate erfassen (modernisiert)
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in jsendschresultate.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

include 'header.inc.php';

/* CSRF */
if (!isset($_SESSION)) { session_start(); }
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<link rel="stylesheet" href="../css/fixes/no-page-scroll-override.css">
<link rel="stylesheet" href="../css/fixes/resultate-unified.css">
<link rel="stylesheet" href="../css/fixes/endresultate-firstcol-override.css">

<style>
/* ===== Schönere Tabelle – JS-Endschiessen ===== */
.table-modern {
  border-collapse: separate !important;
  border-spacing: 0;
  background: #fff;
  box-shadow: 0 6px 18px rgba(0,0,0,.06);
  border-radius: 12px;
  overflow: hidden;
  font-variant-numeric: tabular-nums;
}
.table-modern thead th {
  background: linear-gradient(180deg, #f8f9fb 0%, #eef1f5 100%);
  color: #2b3035;
  font-weight: 700;
  border-bottom: 1px solid #e5e7eb !important;
  padding: 10px 12px;
  position: sticky;
  top: 0;
  z-index: 3;
}
.table-modern tbody td {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f3f5;
}
.table-modern tbody tr:hover { background: #f8fafc; }

/* Erste Spalte links + optional sticky auf Desktop */
.table-modern tbody td:first-child,
.table-modern thead th.th-name { text-align: left !important; }
@media (min-width: 992px) {
  .table-modern thead th.th-name,
  .table-modern tbody td:first-child {
    position: sticky; left: 0; background: #fff; z-index: 2;
  }
  .table-modern thead th.th-name { background: linear-gradient(180deg, #f8f9fb 0%, #eef1f5 100%); z-index: 4; }
}

/* Numerische Spalten mittig und markiert */
.table-modern td.num { text-align: center !important; font-weight: 600; }

/* Zeilen klickbar (für Modal) */
.table-clickable tbody tr { cursor: pointer; }
/* Buttons in erster Spalte: Row-Klick nicht auslösen */
.table-clickable .btn { cursor: pointer; }

/* Einheitliche Aktionsbuttons wie in jsendschloesen.php */
.btn-group.btn-group-sm .btn { border-radius: 8px !important; line-height: 1 !important; }
.btn-edit-js   { /* Bootstrap outline-secondary stilistisch ok */ }
.btn-delete-js { /* Bootstrap outline-danger stilistisch ok */ }

/* Kleinere Anpassungen für sehr kleine Screens */
@media (max-width: 400px) {
  .table-modern tbody td { padding: 8px 10px; }
}

/* ===== JS-Endschiessen Resultate: Tabelle im Stil von "Jungschützen erfassen" ===== */

/* Keine Karte/Box-Optik: kein Schatten, kein fetter Radius */
.table-modern {
  border-collapse: separate !important;
  border-spacing: 0;
  background: #fff;
  box-shadow: none !important;       /* << Schatten weg */
  border: 1px solid #e5e7eb;         /* dezenter Rand wie im Screenshot */
  border-radius: 8px;                /* kleiner Radius statt großer Card-Look */
}

/* Header: flach, hellgrau, ohne Gradient, ohne Schatten */
.table-modern thead th {
  background: #f2f4f7 !important;    /* flacher Header */
  color: #2b3035;
  font-weight: 700;
  border-bottom: 1px solid #e5e7eb !important;
  padding: 10px 12px;
  position: sticky;
  top: 0;
  z-index: 3;
}

/* Zellen: kompakt, fein abgesetzt */
.table-modern tbody td {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f3f5;
}
.table-modern tbody tr:hover { background: #f8fafc; }

/* Erste Spalte linksbündig (wie gehabt); sticky darf bleiben */
.table-modern thead th.th-name,
.table-modern tbody td:first-child { text-align: left !important; }

/* Numerische Spalten mittig */
.table-modern td.num { text-align: center !important; font-weight: 600; }

/* Aktionszelle: zentriert */
.table-modern td:last-child { text-align: center; }

/* Button-Gruppe klein und clean – wie im Erfassen-Screen */
.btn-group.btn-group-sm .btn {
  border-radius: 8px !important;
  line-height: 1 !important;
  padding: .25rem .45rem; /* kompakter */
}

/* Icon-only Buttons (optional, falls du reine Icons nutzt) */
.btn-icon-compact {
  width: 28px;
  height: 28px;
  padding: 0 !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
/* Gruppierte Aktionsbuttons */
.action-group .btn { 
  line-height: 1 !important;
  padding: .25rem .45rem !important;
  border-radius: 8px !important;
}

.action-group .btn + .btn { margin-left: -1px; }

.action-group .btn:first-child {
  border-top-right-radius: 0 !important;
  border-bottom-right-radius: 0 !important;
}
.action-group .btn:last-child {
  border-top-left-radius: 0 !important;
  border-bottom-left-radius: 0 !important;
}

/* Edit-Button neutral mit blauem Icon */
.btn-action-edit {
  border-color: #dee2e6 !important;
  color: #0d6efd !important;
  background-color: #fff !important;
}

/* Delete-Button klassisch rot */
.btn-action-delete {
  border-color: #dc3545 !important;
  color: #dc3545 !important;
  background-color: #fff !important;
}

</style>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-8 col-lg-12 col-12 ps-0">
      <div class="main-content-wrapper">
        <div class="row mb-4">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-target me-2"></i>
              JS-Endschiessen – Resultate erfassen
            </h2>
          </div>
        </div>

        <div class="content-background">
          <form id="jungschuetzenEndresultateForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- Jahr -->
            <div class="year-selection-card mb-3">
              <div class="row align-items-center">
                <div class="col-md-5">
                  <label for="yearSelect" class="form-label fw-bold">
                    <i class="bi bi-calendar3 me-1"></i>Jahr auswählen:
                  </label>
                  <select id="yearSelect" class="form-select"></select>
                </div>
              </div>
            </div>

            <!-- Toolbar -->
            <div class="button-toolbar mb-3">
              <div class="button-group d-flex gap-2 flex-wrap">
                <button id="redirect-btn" type="button" class="btn btn-compact-standard btn-outline-success">
                  <i class="bi bi-trophy me-2"></i>Rangliste
                </button>
                <button id="delall-btn" type="button" class="btn btn-compact-standard btn-outline-danger">
                  <i class="bi bi-trash me-2"></i>Alle Daten löschen
                </button>
                <button type="button" class="btn btn-compact-standard btn-outline-info ges-btn">
                  <i class="bi bi-file-earmark-pdf me-2"></i>PDF Gesamtrangliste
                </button>
              </div>
            </div>

            <!-- Messages / PDF Link -->
            <div id="message" class="mb-2"></div>
            <div id="pdf-link" class="mb-3"></div>

            <!-- Tabelle -->
            <div class="table-wrapper">
              <div class="table-responsive">
                <table class="table table-sm mb-0 table-modern table-clickable align-middle" id="jungschuetzenTabelle">
                  <colgroup>
                    <col style="width: 42%">
                    <col style="width: 16%">
                    <col style="width: 16%">
                    <col style="width: 16%">
                    <col style="width: 10%">
                  </colgroup>
                  <thead>
                    <tr>
                      <th scope="col" class="th-name text-start"><i class="bi bi-person me-1"></i>Jungschütze</th>
                      <th scope="col" class="text-center">Endstich</th>
                      <th scope="col" class="text-center">Schwini</th>
                      <th scope="col" class="text-center">Zabig</th>
                      <th scope="col" class="text-center">Aktionen</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="5" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                        Lade Jungschützen...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Schuss-Modal -->
<div class="modal fade" id="schussModal" tabindex="-1" aria-labelledby="schussModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="schussModalLabel">
          <i class="bi bi-target me-2"></i> Erfassen
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <form id="schussForm" style="display: contents;">
          <input type="hidden" id="jungschuetzeID" name="jungschuetzeID">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

          <div class="row g-3">
            <div class="col-12">
              <div id="endstichSchuesse" class="shooting-category mb-2">
                <h6 class="mb-2"><i class="bi bi-bullseye me-2"></i> Endstich</h6>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <?php for ($i=1; $i<=10; $i++): ?>
                    <input type="number" class="small-input endschuss" id="Schuss<?= $i ?>" name="Schuss<?= $i ?>" min="0" max="10">
                  <?php endfor; ?>
                  <div class="ms-2 d-flex align-items-center">
                    <label for="Tiefschuss" class="small me-1">TS:</label>
                    <input type="number" class="small-input" id="Tiefschuss" name="Tiefschuss" min="0" max="100">
                  </div>
                  <span id="endstichSumme" class="total-display ms-auto">0</span>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div id="schwiniSchuesse" class="shooting-category mb-2">
                <h6 class="mb-2"><i class="bi bi-piggy-bank me-2"></i> Schwini</h6>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <?php for ($i=1; $i<=6; $i++): ?>
                    <input type="number" class="small-input schwini-schuss" id="SchwiniSchuss<?= $i ?>" name="SchwiniSchuss<?= $i ?>" min="0" max="10">
                  <?php endfor; ?>
                  <span id="schwiniSumme" class="total-display ms-auto">0</span>
                </div>
              </div>
            </div>

            <div class="col-md-12">
              <div id="zabigSchuesse" class="shooting-category mb-2">
                <h6 class="mb-2"><i class="bi bi-moon-stars me-2"></i> Zabig</h6>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <?php for ($i=1; $i<=6; $i++): ?>
                    <input type="number" class="small-input zabig" id="ZSchuss<?= $i ?>" name="ZSchuss<?= $i ?>" min="0" max="100">
                  <?php endfor; ?>
                  <span id="zabigsum" class="total-display ms-auto">0</span>
                </div>
              </div>
            </div>

            <div class="col-md-12">
              <div id="Absendenanmeldung" class="shooting-category">
                <h6 class="mb-2"><i class="bi bi-calendar-check me-2"></i> Absenden</h6>
                <input type="text" class="form-control form-control-sm" id="AbsendenAnmeldung" name="AbsendenAnmeldung" placeholder="Anmeldung">
              </div>
            </div>

          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-2"></i>Abbrechen
        </button>
        <button type="submit" form="schussForm" class="btn btn-outline-success">
          <i class="bi bi-save me-2"></i>Speichern
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Bestätigungsmodal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="confirmModalLabel">
          <i class="bi bi-exclamation-triangle text-warning me-2"></i>
          Bestätigung erforderlich
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body text-center py-4">Sind Sie sicher, dass Sie diese Aktion durchführen möchten?</div>
      <div class="modal-footer border-0 justify-content-center">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-2"></i>Abbrechen
        </button>
        <button type="button" class="btn btn-outline-danger" id="confirmAction">
          <i class="bi bi-check-circle me-2"></i>Bestätigen
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Gemeinsame Bibliothek -->
<script src="js/msv-resultate-common.js"></script>

<script>
// Globale Fehlerbehandlung
window.onerror = function(msg, url, line, col, error) {
  console.error('Global error:', msg, 'at', url, 'line', line);
  return false;
};

$(document).ready(function() {
  let deleteType = '';
  let jungschuetzeIDToDelete = null;

  initializeYearSelect();
  loadJungschuetzen();
  setupEventHandlers();

  function initializeYearSelect() {
    const currentYear = new Date().getFullYear();
    const startYear = 2020;
    let options = '';
    for (let year = currentYear; year >= startYear; year--) {
      const selected = year === currentYear ? 'selected' : '';
      options += `<option value="${year}" ${selected}>${year}</option>`;
    }
    $('#yearSelect').html(options);
  }

  // Vereinheitliche Aktions-Buttons wie in jsendschloesen.php
  function unifyRowActionButtons($scope) {
    $scope.find('tr').each(function(){
      const $row = $(this);
      const $cells = $row.find('td');
      if ($cells.length < 5) return;

      const $actionsCell = $cells.eq(4);
      // Sammle evtl. vorhandene Buttons (versch. Klassen)
      const $edit = $actionsCell.find('.edit-btn, .btn-edit-js').first();
      const $del  = $actionsCell.find('.delete-btn, .btn-delete-js').first();

      // Falls die Zelle bereits eine btn-group hat, nix tun
      if ($actionsCell.find('.btn-group.btn-group-sm').length) return;

      // IDs/Namen aus existierenden Buttons oder Zeile
      const id = ($edit.data('id')) || ($del.data('id')) || null;
      const name = ($del.data('name')) || ($row.find('td:first').text().trim()) || '';

      // Neue Gruppe bauen
      const $group = $(`
        <div class="btn-group btn-group-sm" role="group">
          <button class="btn btn-outline-secondary btn-edit-js" title="Bearbeiten">
            <i class="bi bi-pencil-square"></i>
          </button>
          <button class="btn btn-outline-danger btn-delete-js" title="Löschen">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      `);
      if (id) {
        $group.find('.btn-edit-js').attr('data-id', id);
        $group.find('.btn-delete-js').attr({'data-id': id, 'data-name': name});
      }
      $actionsCell.empty().append($group);
    });
  }

  function setupEventHandlers() {
    // Jahr-Wechsel
    $('#yearSelect').on('change', function() {
      const selectedYear = $(this).val();
      msvToast(`Lade Daten für Jahr ${selectedYear}...`, 'info');
      loadJungschuetzen();
    });

    // Zeilenklick öffnet Modal (nicht bei Buttonklick)
    $(document).on('click', '#jungschuetzenTabelle tbody tr', function(e) {
      if ($(e.target).closest('.btn').length === 0) {
        const $row = $(this);
        const $editBtn = $row.find('.btn-edit-js, .edit-btn').first();
        if ($editBtn.length > 0) {
          const jungschuetzeID = $editBtn.data('id');
          const name = $row.find('td:first').text().trim();
          openEditModal(jungschuetzeID, name);
        }
      }
    });

    // PDF Gesamtrangliste direkt herunterladen
    $('.ges-btn').on('click', function (e) {
      e.preventDefault();
      const $btn = $(this);
      const originalText = $btn.html();

      $btn.prop('disabled', true)
          .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');

      $.ajax({
        url: 'jsendsch/generate_pdf_gesamt.php',
        type: 'GET',
        dataType: 'json',
        data: { year: $('#yearSelect').val() || new Date().getFullYear() },
        success: function (data) {
          try {
            if (typeof data === 'string') data = JSON.parse(data);
            let href = data && data.pdf_link ? String(data.pdf_link) : '';
            if (!href) { msvToast('Keine PDF-URL erhalten.', 'error'); return; }
            if (!/^https?:\/\//i.test(href) && !href.startsWith('/')) {
              href = 'jsendsch/' + href.replace(/^\.?\//, '');
            }
            const a = document.createElement('a');
            a.href = encodeURI(href);
            a.download = href.split('/').pop();
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);

            msvToast('PDF erfolgreich generiert und heruntergeladen', 'success');
            $('#pdf-link').empty();
          } catch (err) {
            console.error('PDF parse/render error:', err);
            msvToast('Fehler beim Verarbeiten der PDF-Antwort', 'error');
          }
        },
        error: function (xhr, status, error) {
          console.error('PDF AJAX error:', status, error, xhr.responseText);
          msvToast('Fehler beim Generieren des PDFs: ' + error, 'error');
        },
        complete: function () {
          $btn.prop('disabled', false).html(originalText);
        }
      });
    });

    // Delete-Buttons (Zeilen-Event nicht propagieren)
    $(document).on('click', '.delete-btn, .btn-delete-js, .btn-action-delete', function(e) {
      e.preventDefault(); e.stopPropagation();
      deleteType = 'single';
      jungschuetzeIDToDelete = $(this).data('id');
      const name = $(this).data('name') || $(this).closest('tr').find('td:first').text();
      $('#confirmModal .modal-body').text(`Sind Sie sicher, dass Sie die Daten von "${name}" löschen möchten?`);
      $('#confirmModal').modal('show');
    });

    // Edit-Buttons (Zeilen-Event nicht propagieren)
    $(document).on('click', '.edit-btn, .btn-edit-js, .btn-action-edit', function(e) {
      e.preventDefault(); e.stopPropagation();
      const jungschuetzeID = $(this).data('id');
      const name = $(this).closest('tr').find('td:first').text().trim();
      openEditModal(jungschuetzeID, name);
    });

    // Bestätigung im Modal (alle / single)
    $('#confirmAction').on('click', function() {
      const $btn = $(this);
      const originalText = $btn.html();
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Löschen...');

      if (deleteType === 'all') {
        $.ajax({
          url: 'jsendsch/delete_endschresultate.php',
          method: 'POST',
          data: {
            csrf_token: $('input[name="csrf_token"]').val(),
            year: $('#yearSelect').val() || new Date().getFullYear()
          },
          success: function() {
            msvToast('Alle Daten erfolgreich gelöscht', 'success');
            $('#confirmModal').modal('hide');
            setTimeout(() => loadJungschuetzen(), 600);
          },
          error: function(xhr, status, error) {
            console.error('Delete all error:', error, 'Response:', xhr.responseText);
            msvToast('Fehler beim Löschen aller Daten', 'error');
          },
          complete: function() {
            $btn.prop('disabled', false).html(originalText);
          }
        });
      } else if (deleteType === 'single' && jungschuetzeIDToDelete !== null) {
        $.ajax({
          url: 'jsendsch/delete_endschresultat.php',
          method: 'POST',
          data: {
            jungschuetzeID: jungschuetzeIDToDelete,
            csrf_token: $('input[name="csrf_token"]').val(),
            year: $('#yearSelect').val() || new Date().getFullYear()
          },
          success: function() {
            msvToast('Jungschütze erfolgreich gelöscht', 'success');
            $('#confirmModal').modal('hide');
            setTimeout(() => loadJungschuetzen(), 600);
          },
          error: function(xhr, status, error) {
            console.error('Delete single error:', error, 'Response:', xhr.responseText);
            msvToast('Fehler beim Löschen des Jungschützen', 'error');
          },
          complete: function() {
            $btn.prop('disabled', false).html(originalText);
          }
        });
      }
    });

    // Button "Alle Daten löschen"
    $('#delall-btn').on('click', function() {
      deleteType = 'all';
      $('#confirmModal .modal-body').text('Sind Sie sicher, dass Sie ALLE Jungschützen-Daten löschen möchten?');
      $('#confirmModal').modal('show');
    });

    // Button "Rangliste"
    $('#redirect-btn').on('click', function() {
      window.location.href = 'jungschuetzen_rangliste.php';
    });

    // Summen im Modal neu berechnen
    $(document).on('input change', '.endschuss, .schwini-schuss, .zabig', function() {
      calculateSum();
    });
  }

  // Daten laden
  function loadJungschuetzen() {
    $('#jungschuetzenTabelle tbody').html(`
      <tr>
        <td colspan="5" class="text-center py-4">
          <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
          Lade Jungschützen...
        </td>
      </tr>
    `);

    $.ajax({
      url: 'jsendsch/load_endschresultate.php',
      type: 'GET',
      data: { year: $('#yearSelect').val() || new Date().getFullYear() },
      timeout: 10000,
      success: function(response) {
        const $tbody = $('#jungschuetzenTabelle tbody');
        $tbody.html(response);

        // numerische Spalten kennzeichnen (2,3,4)
        $tbody.find('tr').each(function(){
          $(this).find('td:nth-child(2), td:nth-child(3), td:nth-child(4)').addClass('num');
        });

        // Buttons vereinheitlichen wie in jsendschloesen.php
        unifyRowActionButtons($tbody);

        console.log('Jungschützen loaded successfully');
        if ($('#jungschuetzenTabelle').data('loaded')) {
          msvToast('Jungschützen erfolgreich geladen', 'success');
        }
        $('#jungschuetzenTabelle').data('loaded', true);
      },
      error: function(xhr, status, error) {
        console.error('Load error:', status, error, 'Response:', xhr.responseText);
        let errorMessage = 'Fehler beim Laden der Jungschützen';
        if (status === 'timeout')       errorMessage = 'Zeitüberschreitung beim Laden der Daten';
        else if (xhr.status === 404)    errorMessage = 'Datei nicht gefunden (jsendsch/load_endschresultate.php)';
        else if (xhr.status === 500)    errorMessage = 'Server-Fehler – Bitte Logs prüfen';

        $('#jungschuetzenTabelle tbody').html(`
          <tr>
            <td colspan="5" class="text-center text-danger py-4">
              <i class="bi bi-exclamation-triangle me-2"></i> ${errorMessage}
              <br><small>Status: ${xhr.status} - ${error}</small>
            </td>
          </tr>
        `);
        msvToast(errorMessage, 'error');
      }
    });
  }

  // Edit-Modal laden
  function openEditModal(jungschuetzeID, name) {
    if (!jungschuetzeID) { msvToast('Fehler: Keine Jungschützen-ID gefunden', 'error'); return; }
    $('#schussModalLabel').html(`<i class="bi bi-target me-2"></i> Erfassen – ${name}`);
    $('#jungschuetzeID').val(jungschuetzeID);
    $('#schussForm')[0].reset();
    $('.total-display').text('0');

    $.ajax({
      url: 'jsendsch/load_schussdaten.php',
      type: 'GET',
      data: { jungschuetzeID, year: $('#yearSelect').val() || new Date().getFullYear() },
      success: function(response) {
        try {
          const data = typeof response === 'string' ? JSON.parse(response) : response;
          for (const key in data) {
            if (Object.hasOwn(data, key) && data[key] !== null) {
              const $f = $('#' + key);
              if ($f.length) $f.val(data[key]);
            }
          }
          calculateSum();
          $('#schussModal').modal('show');
        } catch (e) {
          console.error('Parse error:', e);
          msvToast('Fehler beim Verarbeiten der Schussdaten', 'error');
          $('#schussModal').modal('show');
        }
      },
      error: function(xhr, status, error) {
        console.error('Load error:', error, 'Status:', xhr.status, 'Response:', xhr.responseText);
        msvToast('Fehler beim Laden der Schussdaten', 'error');
        $('#schussModal').modal('show');
      }
    });
  }

  // Speichern
  $('#schussForm').on('submit', function(e) {
    e.preventDefault();
    const $submitBtn = $('button[form="schussForm"]');
    const originalText = $submitBtn.html();

    const jungschuetzeID = $('#jungschuetzeID').val();
    if (!jungschuetzeID) { msvToast('Fehler: Keine Jungschützen-ID gefunden', 'error'); return; }

    $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

    const formData = $(this).serialize() + '&year=' + ($('#yearSelect').val() || new Date().getFullYear());

    $.ajax({
      url: 'jsendsch/save_schuss_v2.php',
      type: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response) {
        if (response.debug) console.log('Debug info:', response.debug);
        if (response.success) {
          msvToast(response.message || 'Resultate erfolgreich gespeichert', 'success');
          $('#schussModal').modal('hide');
          setTimeout(() => loadJungschuetzen(), 600);
        } else {
          msvToast('Fehler: ' + (response.message || 'Unbekannt'), 'error');
        }
      },
      error: function(xhr, status, error) {
        console.error('Save error:', error, 'Status:', xhr.status, 'Response:', xhr.responseText);
        try {
          const resp = JSON.parse(xhr.responseText);
          msvToast('Fehler: ' + (resp.message || error), 'error');
        } catch(e2) {
          msvToast('Fehler beim Speichern: ' + error, 'error');
        }
      },
      complete: function() {
        $submitBtn.prop('disabled', false).html(originalText);
      }
    });
  });

  // Summenberechnung im Modal
  function calculateSum() {
    let endstichSum = 0;
    $('.endschuss').each(function() { endstichSum += (parseFloat($(this).val()) || 0); });
    $('#endstichSumme').text(endstichSum);

    let schwiniSum = 0;
    $('.schwini-schuss').each(function() { schwiniSum += (parseFloat($(this).val()) || 0); });
    $('#schwiniSumme').text(schwiniSum);

    let zabigSum = 0;
    $('.zabig').each(function() {
      let val = parseFloat($(this).val()) || 0;
      if (val > 0) {
        val = Math.max(0, Math.min(100, val));
        const score = Math.ceil(val / 10);
        zabigSum += Math.max(0, Math.min(10, score));
      }
    });
    $('#zabigsum').text(zabigSum);
  }
});
</script>

<?php
include 'footer.inc.php';
?>
