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
          <h2 class="h4 mb-0 page-title">Kantonalstich – Ranglisten <button type="button" class="btn-help" data-help="kantiabr.uebersicht" aria-label="Hilfe"></button></h2>
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
            <?php
            // Kopfangaben für das SKSG-Abrechnungsformular (werden beim Export gemerkt)
            $kantiKopf = ['kanti_verantwortlicher_id' => '', 'kanti_adresse1' => '', 'kanti_adresse2' => '', 'kanti_email' => ''];
            $kantiMitglieder = [];
            try {
                $stKopf = getDB()->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('kanti_verantwortlicher_id','kanti_adresse1','kanti_adresse2','kanti_email')");
                foreach ($stKopf->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) { $kantiKopf[$k] = (string) $v; }
                // Aktive, lebende Mitglieder als Auswahl für den Verantwortlichen (gewähltes Mitglied immer dabei)
                $stMit = getDB()->prepare("SELECT ID, Vorname, Name, Strasse, PLZ, Ort, Email FROM mitglieder
                                           WHERE (Status = 1 AND Verstorben = 0) OR ID = :sel ORDER BY Name, Vorname");
                $stMit->execute([':sel' => (int) $kantiKopf['kanti_verantwortlicher_id']]);
                $kantiMitglieder = $stMit->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) { /* Felder bleiben leer */ }
            require_once __DIR__ . '/csrf.inc.php';
            ?>
            <div class="row g-2 mt-3" id="kanti-kopf">
              <input type="hidden" id="kanti-csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
              <div class="col-12 col-md-3">
                <select class="form-select form-select-sm" id="kanti-verantwortlicher" data-tooltip="Verantwortlicher auf dem SKSG-Abrechnungsformular; die Wahl bleibt gespeichert">
                  <option value="">– Verantwortlicher wählen –</option>
                  <?php foreach ($kantiMitglieder as $m): ?>
                  <option value="<?= (int) $m['ID'] ?>"<?= (int) $m['ID'] === (int) $kantiKopf['kanti_verantwortlicher_id'] ? ' selected' : '' ?>
                          data-strasse="<?= htmlspecialchars((string) $m['Strasse'], ENT_QUOTES) ?>"
                          data-plzort="<?= htmlspecialchars(trim($m['PLZ'] . ' ' . $m['Ort']), ENT_QUOTES) ?>"
                          data-email="<?= htmlspecialchars((string) $m['Email'], ENT_QUOTES) ?>"><?= htmlspecialchars($m['Name'] . ' ' . $m['Vorname'], ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-3">
                <input type="text" class="form-control form-control-sm" id="kanti-adresse1" placeholder="Strasse Nr." maxlength="120" value="<?= htmlspecialchars($kantiKopf['kanti_adresse1'], ENT_QUOTES) ?>">
              </div>
              <div class="col-12 col-md-3">
                <input type="text" class="form-control form-control-sm" id="kanti-adresse2" placeholder="PLZ Ort" maxlength="120" value="<?= htmlspecialchars($kantiKopf['kanti_adresse2'], ENT_QUOTES) ?>">
              </div>
              <div class="col-12 col-md-3">
                <input type="email" class="form-control form-control-sm" id="kanti-email" placeholder="E-Mail-Adresse" maxlength="120" value="<?= htmlspecialchars($kantiKopf['kanti_email'], ENT_QUOTES) ?>">
              </div>
            </div>
            <div class="d-flex gap-2 mt-2">
              <button class="btn btn-sm btn-outline-info pdf-btn">
                <i class="bi bi-file-pdf me-2"></i>PDF generieren
              </button>
              <button type="button" class="btn btn-sm btn-outline-info msv-druck" data-druck-doctype="kantirang" data-druck-label="Kantonalstich Rangliste" aria-label="Rangliste direkt drucken"><i class="bi bi-printer"></i></button>
              <button class="btn btn-sm btn-outline-info word-btn" data-tooltip="SKSG-Abrechnungsformular (xlsm) mit Titelblatt und Kontrollblatt befüllen">
                <i class="bi bi-file-earmark-excel me-2"></i>SKSG-Abrechnung (Excel)
              </button>
              <button type="button" class="btn-help" data-help="kantiabr.sksg" aria-label="Hilfe"></button>
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
            data: { year: selectedYear, orientation: window.MsvDruck ? MsvDruck.orientierung('kantirang', 'portrait') : 'portrait' },
            success: function(response) {
                if (response && response.pdf_link) {
                    // Automatischer Download
                    downloadFile(response.pdf_link, 'Kantonalstich_' + selectedYear + '.pdf');
                    
                    $('#pdf-link').empty(); // keine Erfolgsbox – Datei wird direkt heruntergeladen
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

    // Verantwortlicher gewählt → Adresse und E-Mail aus den Stammdaten übernehmen
    $('#kanti-verantwortlicher').on('change', function() {
        var o = this.options[this.selectedIndex];
        if (!o || !o.value) return;
        $('#kanti-adresse1').val(o.getAttribute('data-strasse') || '');
        $('#kanti-adresse2').val(o.getAttribute('data-plzort') || '');
        $('#kanti-email').val(o.getAttribute('data-email') || '');
    });

    // Word/Excel-Button Handler mit automatischem Download
    $('.word-btn').on('click', function() {
        var btn = $(this);
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere...');
        
        var selectedYear = $('#yearSelect').val();
        var daten = {
            year: selectedYear,
            verantwortlicher_id: $('#kanti-verantwortlicher').val(),
            adresse1: $('#kanti-adresse1').val(),
            adresse2: $('#kanti-adresse2').val(),
            email: $('#kanti-email').val()
        };
        msvPost('kantiabr/generate_kantiabr_xls.php', daten, function(response) {
            var fn = String(response.xls_link || '').split('/').pop();
            var wordLink = '/inc/kantiabr/dat/' + fn;

            // Automatischer Download
            downloadFile(wordLink, 'Kantonalstich_Abrechnung_' + selectedYear + '.xlsm');

            // Keine Erfolgsbox – die Datei wird direkt heruntergeladen, der Toast genügt.
            // Nur Warnungen des Servers (z.B. fehlende Lizenznummern) bleiben sichtbar.
            var warnungen = response.warnungen || [];
            if (warnungen.length) {
                $('#pdf-link').html(
                    '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>' +
                    'Hinweise zur SKSG-Abrechnung:<ul class="mb-0 mt-2 small">' + warnungen.map(function(w) {
                        return '<li>' + msvEsc(w) + '</li>';
                    }).join('') + '</ul></div>'
                );
            } else {
                $('#pdf-link').empty();
            }
            msvToast('SKSG-Abrechnung mit ' + msvEsc(response.anzahl) + ' Schützen heruntergeladen', warnungen.length ? 'warning' : 'success');
        }, {
            csrf: $('#kanti-csrf').val(),
            failMsg: 'Abrechnung konnte nicht erstellt werden',
            fail: function() { $('#pdf-link').empty(); }
        }).always(function() {
            btn.prop('disabled', false).html(originalHtml);
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

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray): dieselbe Rangliste wie auf «Kantonal Rangliste» → gemeinsames Profil «Kantonalstich Rangliste»
MsvDruck.resolve('kantirang', () => {
    const jahr = document.getElementById('yearSelect').value;
    return { url: 'kantirang/generate_pdf.php?year=' + encodeURIComponent(jahr) + '&orientation=' + MsvDruck.orientierung('kantirang', 'portrait'), jobName: 'Kantonalstich Rangliste ' + jahr };
});
</script>
<?php include 'footer.inc.php'; ?>