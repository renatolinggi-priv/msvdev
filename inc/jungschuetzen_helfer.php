<?php
// jungschuetzen_helfer.php
include 'dbconnect.inc.php';
require_once __DIR__ . '/csrf.inc.php'; // csrf_token(), Session über session_config
include 'header.inc.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" />

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-default">
        <?php $page_title = 'Helferstunden erfassen'; $page_actions = '<button type="button" class="btn-help" data-help="jungschuetzen_helfer.uebersicht" aria-label="Hilfe"></button>' . '<button type="button" id="pdfExportBtn" class="btn btn-outline-info btn-sm" data-tooltip="Gespeicherte Helferstunden des Jahres als PDF"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Helferstunden</span></button>'; $page_show_mobile = true; include 'partials/page_header.inc.php'; ?>
        <div class="content-background">
          <form id="helferstundenForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="table-wrapper">
              <h5 class="table-title"><i class="bi bi-people-fill me-2"></i>Helfereinsätze Jungschützenkurs – <?= date('Y') ?> <button type="button" class="btn-help" data-help="jungschuetzen_helfer.erfassung" aria-label="Hilfe"></button></h5>
              <div id="helferstundenTabelle"></div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
              <button type="submit" class="btn btn-outline-primary btn-sm" id="helferSpeichernBtn"><i class="bi bi-save me-1"></i>Speichern</button>
              <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#freierEintragModal"><i class="bi bi-plus-lg me-1"></i>Zusätzlicher Helfereinsatz</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row mt-3">
  <div class="col-12 text-center">
    <div id="pdfDownloadLink" style="display:none;"></div>
  </div>
</div>

<!-- Modal für freien Eintrag -->
<div class="modal fade" id="freierEintragModal" tabindex="-1" aria-labelledby="freierEintragLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="freierEintragForm" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="freierEintragLabel">Freier Helfereinsatz erfassen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label for="freierTitel" class="form-label">Bezeichnung</label>
          <input type="text" class="form-control" id="freierTitel" name="freierTitel" maxlength="255" required>
        </div>
        <div class="mb-2">
          <label for="freierWilen" class="form-label">Helfer Wilen</label>
          <input type="number" step="0.5" min="0" max="999" class="form-control" id="freierWilen" name="freierWilen" inputmode="decimal">
        </div>
        <div class="mb-2">
          <label for="freierWollerau" class="form-label">Helfer Wollerau</label>
          <input type="number" step="0.5" min="0" max="999" class="form-control" id="freierWollerau" name="freierWollerau" inputmode="decimal">
        </div>
      </div>
      <div class="modal-footer">
      <button type="button" class="btn btn-outline-primary btn-sm" id="freierSpeichernBtn"><i class="bi bi-save me-1"></i>Speichern</button>

      </div>
    </form>
  </div>
</div>
<script>

// Freier Helfereinsatz: Speichern-Klick löst das Formular-Submit aus
$('#freierSpeichernBtn').on('click', function () {
  $('#freierEintragForm').trigger('submit');
});

$(document).on('keydown', function (e) {
  if (e.key === 'Enter' && $('.modal.show').length > 0) {
    const activeModal = $('.modal.show');
    activeModal.find('.btn-primary, .btn[data-bs-dismiss="modal"]').first().trigger('click');
  }
});

// Knopf während einer Anfrage sperren (kein Doppelklick = kein doppelter Eintrag)
function knopfBeschaeftigt($btn, an, text) {
  if (an) {
    $btn.data('orig-html', $btn.html()).prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + msvEsc(text));
  } else if ($btn.data('orig-html') !== undefined) {
    $btn.prop('disabled', false).html($btn.data('orig-html'));
  }
}

function ladeHelferstunden() {
  $.ajax({
    url: 'jshelfer/load_jshelfer.php',
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      if (!Array.isArray(data) || data.length === 0) {
        $('#helferstundenTabelle').html('<div class="ui-leerzustand"><i class="bi bi-inbox" aria-hidden="true"></i>Keine Helfereinsätze für <?= date('Y') ?>. Termine mit «Jungschützenkurs» im Namen (Wichtige Termine) erscheinen hier automatisch; weitere Einsätze über «Zusätzlicher Helfereinsatz».</div>');
        return;
      }

      // Desktop: Tabelle
      let html = '<div class="desktop-table-container">';
      html += '<table class="table table-sm table-bordered" id="helferTable">';
      html += '<thead><tr><th>Datum</th><th>Bezeichnung</th><th>Wilen</th><th>Wollerau</th><th></th></tr></thead><tbody>';

      data.forEach(event => {
        const helferKey = event.helferID !== null ? event.helferID : `new_${event.eventID}`;
        const wilen = event.helferWilen ?? '';
        const wollerau = event.helferWollerau ?? '';
        const name = msvEsc(event.name ?? '');
        //const datum = event.date ? new Date(event.date).toLocaleDateString('de-DE') : '—';
        const datum = event.date
        ? (() => {
            const d = new Date(event.date);
            const tag = String(d.getDate()).padStart(2, '0');
            const monat = String(d.getMonth() + 1).padStart(2, '0');
            const jahr = d.getFullYear();
            return `${tag}.${monat}.${jahr}`;
            })()
        : '—';

        html += `<tr>
          <td align="right">${datum}</td>
          <td>${event.isCustom ? `<i>${name}</i>` : name}</td>
          <td><input type="number" step="0.5" min="0" max="999" inputmode="decimal" name="helferWilen[${msvEsc(helferKey)}]" class="form-control form-control-sm" value="${msvEsc(wilen)}" aria-label="Stunden Wilen, ${name}"></td>
          <td><input type="number" step="0.5" min="0" max="999" inputmode="decimal" name="helferWollerau[${msvEsc(helferKey)}]" class="form-control form-control-sm" value="${msvEsc(wollerau)}" aria-label="Stunden Wollerau, ${name}"></td>
          <td><button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="${event.helferID}" data-tooltip="Löschen" aria-label="${name} löschen"><i class="bi bi-trash" aria-hidden="true"></i></button></td>
        </tr>`;
      });

      html += '</tbody></table>';
      html += '</div>'; // Ende desktop-table-container

      // Mobile: Cards
      html += '<div class="mobile-cards-container" id="mobileHelferCards">';
      html += '<div class="mobile-search">';
      html += '<div class="position-relative">';
      html += '<i class="bi bi-search search-icon"></i>';
      html += '<input type="text" class="form-control" placeholder="Suchen..." oninput="filterMobileHelfer(this)">';
      html += '</div>';
      html += '</div>';
      html += '<div class="mobile-cards-scroll">';
      html += '<!-- Cards werden per JavaScript generiert -->';
      html += '</div>';
      html += '</div>';

      $('#helferstundenTabelle').html(html);

      // Mobile Cards generieren
      if (typeof buildMobileHelferCards === 'function') {
        buildMobileHelferCards();
      }
    },
    error: function(xhr) {
      const text = msvXhrMessage(xhr, 'Die Helferstunden konnten nicht geladen werden.');
      $('#helferstundenTabelle').html('<div class="ui-leerzustand text-danger"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>'
        + msvEsc(text) + '<div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm" id="helferNeuLaden">Nochmals laden</button></div></div>');
    }
  });
}
$(document).on('click', '#helferNeuLaden', ladeHelferstunden);

// Ausgabe-Baustein msvAusgabe: sperren, Spinner, Download, Toast (Fehler als Toast wie überall)
$('#pdfExportBtn').on('click', function () {
  const year = new Date().getFullYear();
  $('#pdfDownloadLink').hide().html('');
  msvAusgabe(this, {
    url: 'jshelfer/create_helfer_pdf.php',
    data: { year },
    titel: 'Helferstunden Jungschützen ' + year,
    name: 'Jungschuetzen_Helferstunden_' + year,
    fehler: 'Das PDF konnte nicht erstellt werden. Bitte nochmals versuchen.'
  });
});

// Öffnet die Lösch-Bestätigung
$('#helferstundenTabelle').on('click', '.delete-btn', function (e) {
  e.preventDefault();
  const deleteId = $(this).data('id');
  if (!deleteId) return;

  const $zeile = $(this).closest('tr');
  const $karte = $(this).closest('.mobile-card');
  const name = ($zeile.length ? $zeile.find('td').eq(1).text() : $karte.find('.mobile-card-title').text()).trim() || 'diesen Eintrag';

  msvConfirmDelete(msvEsc(name)).then(function (res) {
    if (!res.isConfirmed) return;

    $.ajax({
      url: 'jshelfer/delete_jshelfer.php',
      method: 'POST',
      data: { id: deleteId, csrf_token: $('#helferstundenForm [name="csrf_token"]').val() },
      dataType: 'json',
      success: function(response) {
        msvToast(response.message || 'Eintrag gelöscht.', 'success');
        ladeHelferstunden();
      },
      error: function(xhr) {
        msvError(msvXhrMessage(xhr, 'Der Eintrag konnte nicht gelöscht werden.'));
        if (xhr.status === 404) ladeHelferstunden();
      }
    });
  });
});

$(document).ready(function() {
  ladeHelferstunden();

  $('#helferstundenForm').on('submit', function(e) {
    e.preventDefault();
    const $btn = $('#helferSpeichernBtn');
    if ($btn.prop('disabled')) return;           // läuft schon; Werte prüft der Server (0–999)
    const formData = $(this).serialize();
    knopfBeschaeftigt($btn, true, 'Speichert …');

    $.ajax({
      url: 'jshelfer/save_jshelfer.php',
      method: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response) {
        msvToast(response.message || 'Helferstunden gespeichert.', 'success');
        ladeHelferstunden();
      },
      error: function(xhr) {
        // Eingaben bleiben stehen; nur melden
        msvError(msvXhrMessage(xhr, 'Die Helferstunden konnten nicht gespeichert werden. Deine Eingaben sind noch da.'));
      },
      complete: function() { knopfBeschaeftigt($btn, false); }
    });
  });

  $('#freierEintragForm').on('submit', function(e) {
    e.preventDefault();
    const $btn = $('#freierSpeichernBtn');
    if ($btn.prop('disabled')) return;
    if (!this.checkValidity()) { this.reportValidity(); return; }
    const formData = $(this).serialize();
    knopfBeschaeftigt($btn, true, 'Speichert …');

    $.ajax({
      url: 'jshelfer/add_jshelferevent.php',
      method: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response) {
        msvToast(response.message || 'Freier Eintrag gespeichert.', 'success');
        $('#freierEintragForm')[0].reset();
        $('#freierEintragModal').modal('hide');
        ladeHelferstunden();
      },
      error: function(xhr) {
        // Modal bleibt offen, Eingaben bleiben stehen
        msvError(msvXhrMessage(xhr, 'Der Eintrag konnte nicht gespeichert werden.'));
      },
      complete: function() { knopfBeschaeftigt($btn, false); }
    });
  });

    // Modal-Cleanup bei allen Modals
    $('.modal').on('hidden.bs.modal', function () {
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right', '');
  });
});

// Mobile Cards für Jungschützen-Helfer generieren
function buildMobileHelferCards() {
  const isMobile = window.matchMedia('(max-width: 767.98px)');
  if (!isMobile.matches) return;

  const table = document.querySelector('#helferTable');
  if (!table) return;

  const container = document.querySelector('#mobileHelferCards .mobile-cards-scroll');
  if (!container) return;

  container.innerHTML = '';
  const rows = table.querySelectorAll('tbody tr');

  rows.forEach(row => {
    const cells = row.querySelectorAll('td');
    if (cells.length < 5) return;

    const datum = cells[0].textContent.trim();
    const bezeichnung = cells[1].innerHTML.trim(); // innerHTML to preserve <i> tags

    const wilenInput = cells[2].querySelector('input');
    const wollerauInput = cells[3].querySelector('input');
    const deleteBtn = cells[4].querySelector('.delete-btn');

    const wilenName = wilenInput ? wilenInput.name : '';
    const wilenValue = wilenInput ? wilenInput.value : '';
    const wollerauName = wollerauInput ? wollerauInput.name : '';
    const wollerauValue = wollerauInput ? wollerauInput.value : '';
    const deleteId = deleteBtn ? deleteBtn.getAttribute('data-id') : '';

    const card = document.createElement('div');
    card.className = 'mobile-card';
    card.innerHTML = `
      <div class="mobile-card-header">
        <div class="mobile-card-title">${bezeichnung}</div>
        ${deleteBtn && deleteId ? `
          <button type="button" class="btn btn-outline-danger btn-sm delete-btn" data-id="${deleteId}">
            <i class="bi bi-trash"></i>
          </button>
        ` : ''}
      </div>
      <div class="mobile-card-body">
        <div class="mobile-card-row">
          <span class="mobile-card-label"><i class="bi bi-calendar3 me-1"></i>Datum:</span>
          <span class="mobile-card-value">${datum}</span>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold small">Helfer Wilen:</label>
          <input type="number" step="0.5"
                 class="form-control helfer-input-mobile"
                 data-name="${wilenName}"
                 value="${wilenValue}"
                 inputmode="decimal">
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold small">Helfer Wollerau:</label>
          <input type="number" step="0.5"
                 class="form-control helfer-input-mobile"
                 data-name="${wollerauName}"
                 value="${wollerauValue}"
                 inputmode="decimal">
        </div>
      </div>
    `;
    container.appendChild(card);
  });

  // Event-Listener für Mobile Inputs: Sync zu Desktop
  container.querySelectorAll('input[data-name]').forEach(input => {
    input.addEventListener('input', function() {
      const inputName = this.getAttribute('data-name');
      const desktopInput = table.querySelector(`input[name="${inputName}"]`);
      if (desktopInput) {
        desktopInput.value = this.value;
      }
    });
  });
}

// Global filterMobileHelfer function
window.filterMobileHelfer = function(searchInput) {
  const searchTerm = searchInput.value.toLowerCase();
  const cards = document.querySelectorAll('#mobileHelferCards .mobile-card');

  cards.forEach(card => {
    const text = card.textContent.toLowerCase();
    card.style.display = text.includes(searchTerm) ? '' : 'none';
  });
};

</script>

<style>
/* === MOBILE OPTIMIZATION === */
@media (max-width: 767.98px) {
  /* Desktop-Tabelle ausblenden */
  .desktop-table-container {
    display: none !important;
  }

  /* Mobile Cards anzeigen */
  .mobile-cards-container {
    display: block !important;
  }

  /* Input-Anpassungen */
  .helfer-input-mobile {
    min-height: 44px !important;
    font-size: 16px !important;
    padding: 0.5rem !important;
  }

  /* Button-Anpassungen */
  .btn {
    min-height: 44px;
    font-size: 0.9rem;
  }

  /* Container-Anpassungen */
  .container-fluid {
    padding: 0.5rem;
  }
}

/* Desktop: Mobile Cards ausblenden */
@media (min-width: 768px) {
  .mobile-cards-container {
    display: none !important;
  }
}
</style>

<?php include 'footer.inc.php'; ?>
