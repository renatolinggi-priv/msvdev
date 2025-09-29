<?php
// jungschuetzen_helfer.php
include 'dbconnect.inc.php';
include 'header.inc.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" />

<div class="container-fluid">
  <h5>Helfereinsätze Jungschützenkurs – <?= date('Y') ?></h5>
  <form id="helferstundenForm">
    <div id="helferstundenTabelle" class="col-4"></div>
    <button type="submit" class="btn btn-outline-primary mt-3">💾 Speichern</button>
    <button type="button" class="btn btn-outline-success mt-3" data-bs-toggle="modal" data-bs-target="#freierEintragModal">➕ Zusätzlicher Helfereinsatz</button>
    <button type="button" id="pdfExportBtn" class="btn btn-outline-secondary mt-3">📄 Helferstunden als PDF exportieren</button>
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
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal für freien Eintrag -->
<div class="modal fade" id="freierEintragModal" tabindex="-1" aria-labelledby="freierEintragLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="freierEintragForm" class="modal-content">
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
      <button type="button" class="btn btn-primary" id="freierSpeichernBtn">💾 Speichern</button>

      </div>
    </form>
  </div>
</div>
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="confirmDeleteLabel">Eintrag löschen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        Möchtest du diesen Eintrag wirklich löschen?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">🗑️ Löschen</button>
      </div>
    </div>
  </div>
</div>
<script>


let deleteId = null;
// Freier Helfereinsatz: Speichern-Klick löst das Formular-Submit aus
$('#freierSpeichernBtn').on('click', function () {
  $('#freierEintragForm').trigger('submit');
});


$(document).on('keydown', function (e) {
  if (e.key === 'Enter' && $('.modal.show').length > 0) {
    const activeModal = $('.modal.show');

    if (activeModal.attr('id') === 'confirmDeleteModal') {
      $('#confirmDeleteBtn').trigger('click');
    } else {
      activeModal.find('.btn-primary, .btn[data-bs-dismiss="modal"]').first().trigger('click');
    }
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

      let html = '<table class="table table-sm table-bordered">';
      html += '<thead><tr><th>Datum</th><th>Bezeichnung</th><th>Wilen</th><th>Wollerau</th><th></th></tr></thead><tbody>';

      data.forEach(event => {
        const helferKey = event.helferID !== null ? event.helferID : `new_${event.eventID}`;
        const wilen = event.helferWilen ?? '';
        const wollerau = event.helferWollerau ?? '';
        const name = event.name ?? '';
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
          <td><input type="number" step="0.5" name="helferWilen[${helferKey}]" class="form-control form-control-sm" value="${wilen}"></td>
          <td><input type="number" step="0.5" name="helferWollerau[${helferKey}]" class="form-control form-control-sm" value="${wollerau}"></td>
          <td><button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="${event.helferID}" title="Löschen">🗑️</button></td>
        </tr>`;
      });

      html += '</tbody></table>';
      $('#helferstundenTabelle').html(html);
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
        const linkHtml = `<a href="${response.pdf_link}" download>📄 <strong>PDF herunterladen</strong></a>`;
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

// Öffnet das Lösch-Bestätigungsmodal
$('#helferstundenTabelle').on('click', '.delete-btn', function (e) {
  e.preventDefault();
  deleteId = $(this).data('id');

  if (deleteId) {
    const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    modal.show();
  }
});

// Bestätigt das Löschen
$('#confirmDeleteBtn').on('click', function () {
  if (!deleteId) return;

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

  const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
  confirmModal.hide();
  deleteId = null;

  // Sicherstellen, dass Backdrop verschwindet
  $('.modal-backdrop').remove();
  $('body').removeClass('modal-open').css('padding-right', '');
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

</script>


<?php include 'footer.inc.php'; ?>
