<?php
// jungschuetzen_helfer.php
include 'dbconnect.inc.php';
include 'header.inc.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" />

<div class="container-fluid">
  <h5>Helfereinsätze Jungschützenkurs â€“ <?= date('Y') ?></h5>
  <form id="helferstundenForm">
    <div id="helferstundenTabelle" class="col-4"></div>
    <button type="submit" class="btn btn-outline-primary btn-sm mt-3"><i class="bi bi-save me-1"></i>Speichern</button>
    <button type="button" class="btn btn-outline-success btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#freierEintragModal">âž• Zusätzlicher Helfereinsatz</button>
    <button type="button" id="pdfExportBtn" class="btn btn-outline-info btn-sm mt-3"><i class="bi bi-file-pdf me-1"></i>Helferstunden als PDF exportieren</button>
  </form>
</div>
<div class="row mt-3">
  <div class="col-12 text-center">
    <div id="pdfDownloadLink" style="display:none;"></div>
  </div>
</div>

<!-- Bootstrap Modal für Rückmeldungen -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="feedbackModalLabel">Hinweis</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body" id="feedbackMessage"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal für freien Eintrag -->
<div class="modal fade" id="freierEintragModal" tabindex="-1" aria-labelledby="freierEintragLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="freierEintragForm" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="freierEintragLabel">Freier Helfereinsatz erfassen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label for="freierTitel" class="form-label">Bezeichnung</label>
          <input type="text" class="form-control" id="freierTitel" name="freierTitel" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Helfer Wilen</label>
          <input type="number" step="0.5" min="0" class="form-control" name="freierWilen">
        </div>
        <div class="mb-2">
          <label class="form-label">Helfer Wollerau</label>
          <input type="number" step="0.5" min="0" class="form-control" name="freierWollerau">
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

function showModalMessage(message) {
  $('#feedbackMessage').html(message);
  const modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
  modal.show();
}

function ladeHelferstunden() {
  $.ajax({
    url: 'jshelfer/load_jshelfer.php',
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      if (data.length === 0) {
        $('#helferstundenTabelle').html('<p>Keine Einträge gefunden.</p>');
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
        const name = event.name ?? '';
        //const datum = event.date ? new Date(event.date).toLocaleDateString('de-DE') : 'â€"';
        const datum = event.date
        ? (() => {
            const d = new Date(event.date);
            const tag = String(d.getDate()).padStart(2, '0');
            const monat = String(d.getMonth() + 1).padStart(2, '0');
            const jahr = d.getFullYear();
            return `${tag}.${monat}.${jahr}`;
            })()
        : 'â€"';

        html += `<tr>
          <td align="right">${datum}</td>
          <td>${event.isCustom ? `<i>${name}</i>` : name}</td>
          <td><input type="number" step="0.5" name="helferWilen[${helferKey}]" class="form-control form-control-sm" value="${wilen}"></td>
          <td><input type="number" step="0.5" name="helferWollerau[${helferKey}]" class="form-control form-control-sm" value="${wollerau}"></td>
          <td><button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="${event.helferID}" data-tooltip="Löschen"><i class="bi bi-trash"></i></button></td>
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
    error: function() {
      showModalMessage('Fehler beim Laden der Daten.');
    }
  });
}

$('#pdfExportBtn').on('click', function () {
  const year = new Date().getFullYear();

  $.ajax({
    url: 'jshelfer/create_helfer_pdf.php',
    method: 'GET',
    data: { year },
    dataType: 'json',
    success: function(response) {
      if (response.success && response.pdf_link) {
        const linkHtml = `<a href="${response.pdf_link}" download><i class="bi bi-file-pdf me-1"></i><strong>PDF herunterladen</strong></a>`;
        $('#pdfDownloadLink').html(linkHtml).show();
      } else {
        $('#pdfDownloadLink').hide().html('');
        showModalMessage('PDF konnte nicht erstellt werden.');
      }
    },
    error: function(xhr, status, error) {
      $('#pdfDownloadLink').hide().html('');
      showModalMessage('Fehler beim PDF-Export: ' + error);
    }
  });
});

// Öffnet die Lösch-Bestätigung
$('#helferstundenTabelle').on('click', '.delete-btn', function (e) {
  e.preventDefault();
  const deleteId = $(this).data('id');
  if (!deleteId) return;

  const name = ($(this).closest('tr').find('td').eq(1).text().trim()) || 'diesen Eintrag';

  msvConfirmDelete(name).then(function (res) {
    if (!res.isConfirmed) return;

    $.ajax({
      url: 'jshelfer/delete_jshelfer.php',
      method: 'POST',
      data: { id: deleteId },
      dataType: 'json',
      success: function(response) {
        showModalMessage(response.success || response.error);
        ladeHelferstunden();
      },
      error: function(xhr, status, error) {
        showModalMessage('Fehler beim Löschen: ' + error);
      }
    });
  });
});

$(document).ready(function() {
  ladeHelferstunden();

  $('#helferstundenForm').on('submit', function(e) {
    e.preventDefault();
    const formData = $(this).serialize();

    $.ajax({
      url: 'jshelfer/save_jshelfer.php',
      method: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response) {
        showModalMessage(response.success || response.error);
        ladeHelferstunden();
      },
      error: function(xhr, status, error) {
        showModalMessage('Fehler beim Speichern: ' + error);
      }
    });
  });

  $('#freierEintragForm').on('submit', function(e) {
    e.preventDefault();
    const formData = $(this).serialize();

    $.ajax({
      url: 'jshelfer/add_jshelferevent.php',
      method: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response) {
        showModalMessage(response.success || response.error);
        $('#freierEintragForm')[0].reset();
        $('#freierEintragModal').modal('hide');
        ladeHelferstunden();
      },
      error: function(xhr, status, error) {
        showModalMessage('Fehler beim Speichern des freien Eintrags: ' + error);
      }
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
