<?php
// kantiabr.php - Kantonalstich Ranglisten im Stil von backup_restore.php
include 'dbconnect.inc.php';

// Seitenspezifische Styles definieren
$page_specific_css = "
/* Kantonalstich spezifische Styles */
.main-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 2rem;
    margin-bottom: 2rem;
}

.sidebar-card,
...
.table tbody tr:nth-child(2) td:first-child { color: #C0C0C0; } /* Silber */
.table tbody tr:nth-child(3) td:first-child { color: #CD7F32; } /* Bronze */

/* Button Styles */
.btn-compact-standard {
    padding: .375rem .75rem;
    font-size: .875rem;
}

/* Export Links */
#pdf-link a {
    display: inline-block;
    margin-top: 1rem;
    padding: .5rem 1rem;
    background
";
?>
<?php include 'header.inc.php'; ?>

<style>
<?= $page_specific_css ?>
</style>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-9 col-lg-8">
      <div class="main-card">
        <div class="d-none d-md-flex align-items-center justify-content-between mb-3">
          <h2 class="h4 mb-0 page-title">Kantonalstich – Ranglisten</h2>
          <div class="d-flex gap-2">
            <select id="yearSelect" class="form-select form-select-sm" style="width:auto"></select>
            <button id="reload-btn" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Neu laden</button>
            <button id="redirect-btn" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-ol me-1"></i>Zur Erfassung</button>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-lg-6">
            <div class="card">
              <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-trophy me-2"></i>Kategorie A</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadKantonala()"><i class="bi bi-arrow-repeat"></i></button>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table id="KantonalA" class="table table-sm mb-0">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Hauptdoppel</th>
                        <th>1. ND</th>
                        <th>2. ND</th>
                        <th>3. ND</th>
                        <th>4. ND</th>
                        <th>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- wird via AJAX gefüllt -->
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-6">
            <div class="card">
              <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-trophy-fill me-2"></i>Kategorie B</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadKantonalb()"><i class="bi bi-arrow-repeat"></i></button>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table id="KantonalB" class="table table-sm mb-0">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Hauptdoppel</th>
                        <th>1. ND</th>
                        <th>2. ND</th>
                        <th>3. ND</th>
                        <th>4. ND</th>
                        <th>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- wird via AJAX gefüllt -->
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-sm btn-outline-info pdf-btn">
                <i class="bi bi-file-pdf me-2"></i>PDF generieren
              </button>
              <button class="btn btn-sm btn-outline-info word-btn">
                <i class="bi bi-file-earmark-excel me-2"></i>Excel generieren
              </button>
            </div>
            <div id="pdf-link" class="mt-3"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-lg-4">
      <div class="sidebar-card">
        <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>Hinweise</h5>
        <ul class="small mb-0">
          <li>Jahr oben wählen, um Ranglisten neu zu laden.</li>
          <li>PDF/Excel generiert jeweils eine Datei zum Download.</li>
          <li>Bei Problemen erscheint unten eine Fehlermeldung.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    var basePath = '';

    // Initialisierung des Jahres-Dropdowns
    function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        const currentYear = new Date().getFullYear();
        for (let year = currentYear; year >= currentYear - 3; year--) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
    }

    function setStatus(message, type) {
        $('#pdf-link').html(
            '<div class="alert alert-' + (type === 'loading' ? 'info' : type) + ' d-flex align-items-center">' +
            (type === 'success' ? '<i class="bi bi-check-circle-fill me-2"></i>' :
             type === 'danger'  ? '<i class="bi bi-x-circle-fill me-2"></i>' :
                                  '<span class="spinner-border spinner-border-sm me-2"></span>') +
            '<div>' + message + '</div></div>'
        );
    }

    // Automatischer Download einer Datei
    function downloadFile(url, filename) {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename || url.split('/').pop();
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Kantiresultate A laden
    function loadKantonala() {
        var selectedYear = $('#yearSelect').val();

        $.ajax({
            url: basePath + 'kantirang/load_kantonal.php',
            type: 'GET',
            data: { year: selectedYear, kat: 'A' },
            success: function(response) {
                $('#KantonalA tbody').html(response);
                // Zentriere numerische Werte
                $('#KantonalA tbody tr').each(function() {
                    $(this).find('td').each(function(index) {
                        if (index >= 3) $(this).addClass('text-center');
                    });
                });
            },
            error: function(xhr, status, error) {
                setStatus('Fehler beim Laden Kategorie A: ' + error, 'danger');
                msvToast('Fehler beim Laden Kategorie A', 'error');
            }
        });
    }

    // Kantiresultate B laden
    function loadKantonalb() {
        var selectedYear = $('#yearSelect').val();

        $.ajax({
            url: basePath + 'kantirang/load_kantonal.php',
            type: 'GET',
            data: { year: selectedYear, kat: 'B' },
            success: function(response) {
                $('#KantonalB tbody').html(response);
                $('#KantonalB tbody tr').each(function() {
                    $(this).find('td').each(function(index) {
                        if (index >= 3) $(this).addClass('text-center');
                    });
                });
            },
            error: function(xhr, status, error) {
                setStatus('Fehler beim Laden Kategorie B: ' + error, 'danger');
                msvToast('Fehler beim Laden Kategorie B', 'error');
            }
        });
    }

    // PDF Erstellung Button Handler
    $('.pdf-btn').on('click', function() {
        var btn = $(this);
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
        
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'kantirang/generate_pdf.php',
            type: 'GET',
            dataType: 'json',
            data: { year: selectedYear },
            success: function(response) {
                if (response && response.pdf_link) {
                    // Automatischer Download
                    downloadFile(response.pdf_link, 'Kantonalstich_' + selectedYear + '.pdf');
                    
                    $('#pdf-link').html(
                        '<div class="alert alert-success d-flex align-items-center">' +
                        '<i class="bi bi-check-circle-fill me-2"></i>' +
                        '<div>PDF wurde heruntergeladen. <a href="' + response.pdf_link + '" target="_blank" class="alert-link">' +
                        '<i class="bi bi-arrow-clockwise me-1"></i>Erneut herunterladen</a></div></div>'
                    );
                    msvToast('PDF erfolgreich generiert und heruntergeladen', 'success');
                } else {
                    $('#pdf-link').html(
                        '<div class="alert alert-danger">' +
                        '<i class="bi bi-x-circle-fill me-2"></i>Fehler beim Generieren der PDF-Datei</div>'
                    );
                    msvToast('PDF-Generierung fehlgeschlagen', 'error');
                }
            },
            error: function(xhr, status, error) {
                $('#pdf-link').html(
                    '<div class="alert alert-danger">' +
                    '<i class="bi bi-x-circle-fill me-2"></i>Fehler: ' + error + '</div>'
                );
                msvToast('Fehler beim Generieren der PDF: ' + error, 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Word/Excel-Button Handler mit automatischem Download
    $('.word-btn').on('click', function() {
        var btn = $(this);
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
        
        var selectedYear = $('#yearSelect').val();
        $.ajax({
            url: 'kantiabr/generate_kantiabr_xls.php',
            type: 'GET',
            dataType: 'json',
            data: { year: selectedYear },
            success: function(response) {
                var wordLink = (function(p){
                    if(!p) return null;
                    var idx = p.indexOf('/inc/');
                    if(idx !== -1) return p.substring(idx);
                    var fn = p.split('/').pop();
                    return '/inc/kantiabr/dat/' + fn;
                })(response && (response.xls_link || response.pdf_link));

                if (wordLink) {
                    // Automatischer Download
                    downloadFile(wordLink, 'Kantonalstich_' + selectedYear + '.xlsx');
                    
                    $('#pdf-link').html(
                        '<div class="alert alert-success d-flex align-items-center">' +
                        '<i class="bi bi-check-circle-fill me-2"></i>' +
                        '<div>Excel wurde heruntergeladen. <a href="' + wordLink + '" target="_blank" class="alert-link">' +
                        '<i class="bi bi-arrow-clockwise me-1"></i>Erneut herunterladen</a></div></div>'
                    );
                    msvToast('Excel erfolgreich generiert und heruntergeladen', 'success');
                } else {
                    $('#pdf-link').html(
                        '<div class="alert alert-danger">' +
                        '<i class="bi bi-x-circle-fill me-2"></i>Fehler: Kein Download-Link erhalten</div>'
                    );
                    msvToast('Excel-Generierung fehlgeschlagen', 'error');
                }
            },
            error: function(xhr, status, error) {
                $('#pdf-link').html(
                    '<div class="alert alert-danger">' +
                    '<i class="bi bi-x-circle-fill me-2"></i>Fehler: ' + error + '</div>'
                );
                msvToast('Fehler beim Generieren der Excel-Datei: ' + error, 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Event Handler für Jahr-Dropdown
    $('#yearSelect').on('change', function() {
        loadKantonala();
        loadKantonalb();
        $('#pdf-link').empty(); // Clear previous download links
    });

    // Reload Button
    $('#reload-btn').on('click', function() {
        loadKantonala();
        loadKantonalb();
        msvToast('Daten aktualisiert', 'info');
    });

    // Redirect-Button
    $('#redirect-btn').on('click', function() {
        window.location.href = 'https://jahresmeisterschaft.msvwilen.ch/inc/kantiresultate.php';
    });

    // Initialisierung
    initializeYearDropdown();
    loadKantonala();
    loadKantonalb();
});
</script>

<?php include 'footer.inc.php'; ?>