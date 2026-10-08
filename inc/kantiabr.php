<?php
// kantiabr.php – Kantonalstich: Ranglisten Kat. A/B und SKSG-Abrechnungsformular
include 'dbconnect.inc.php';

// Seitenspezifische Styles: nur Aufbau dieser Seite; die Optik kommt aus css/msv-ui.css
$page_specific_css = "
.ka-tabellen { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 14px; }
@media (max-width: 991.98px) { .ka-tabellen { grid-template-columns: 1fr; } }
.ka-karte { overflow: hidden; margin-bottom: 14px; }
.ka-tabellen .ka-karte { margin-bottom: 0; }
.ka-karte .table-responsive { min-height: 0 !important; max-height: none !important; }
.ka-karte .table { margin: 0; }
.ka-karte .table thead th:first-child, .ka-karte .table tbody td:first-child { padding-left: 20px; }
.ka-karte .table thead th:last-child, .ka-karte .table tbody td:last-child { padding-right: 20px; font-weight: 700; }
.ka-karte .table tbody tr:nth-child(-n+3) td:first-child { font-weight: 700; }
.ka-sksg { padding: 14px var(--ui-pad) var(--ui-pad); }
.ka-sksg-text { max-width: 70ch; margin: 0 0 12px; font-size: .85rem; color: var(--ui-text-2); }
.ka-aktionen { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 12px; }
";

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';

// Kopfangaben für das SKSG-Abrechnungsformular (werden beim Erstellen gespeichert)
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
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-wide">
        <?php
        $page_title = 'Kantonalstich – Ranglisten';
        $page_title_after = '<button type="button" class="btn-help" data-help="kantiabr.uebersicht" aria-label="Hilfe"></button>'
            . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
            . '<select id="yearSelect" class="form-select form-select-sm"></select>';
        $page_actions = '<button type="button" class="btn btn-outline-info btn-sm pdf-btn"><i class="bi bi-file-pdf me-1"></i>Rangliste PDF</button>'
            . '<button type="button" class="btn btn-outline-info btn-sm msv-druck" data-druck-doctype="kantirang" data-druck-label="Kantonalstich Rangliste" aria-label="Rangliste direkt drucken"><i class="bi bi-printer"></i></button>'
            . '<button type="button" id="redirect-btn" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Zur Erfassung</button>';
        $page_show_mobile = true;
        include 'partials/page_header.inc.php';
        ?>

        <div id="pdf-link"></div>

        <!-- Ranglisten Kategorie A und B -->
        <div class="ka-tabellen">
          <?php foreach (['A' => 'KantonalA', 'B' => 'KantonalB'] as $kat => $tab): ?>
          <section class="ui-karte ka-karte" aria-label="Kategorie <?= $kat ?>">
            <div class="ui-tab-kopf">
              <span class="ui-tab-titel">Kategorie <?= $kat ?></span>
              <button type="button" class="btn btn-sm btn-outline-secondary ms-auto ka-neu" data-kat="<?= $kat ?>" data-tooltip="Neu laden" aria-label="Kategorie <?= $kat ?> neu laden"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></button>
            </div>
            <div class="table-responsive">
              <table id="<?= $tab ?>" class="table table-sm mb-0">
                <thead>
                  <tr><th>#</th><th>Name</th><th>Hauptdoppel</th><th>1. ND</th><th>2. ND</th><th>3. ND</th><th>4. ND</th><th>Total</th></tr>
                </thead>
                <tbody><!-- wird via AJAX gefüllt --></tbody>
              </table>
            </div>
          </section>
          <?php endforeach; ?>
        </div>

        <!-- SKSG-Abrechnungsformular -->
        <section class="ui-karte ka-karte" aria-label="SKSG-Abrechnung">
          <div class="ui-tab-kopf">
            <span class="ui-tab-titel">SKSG-Abrechnung <button type="button" class="btn-help" data-help="kantiabr.sksg" aria-label="Hilfe"></button></span>
          </div>
          <div class="ka-sksg">
            <p class="ka-sksg-text">Füllt das Abrechnungsformular des SKSG (xlsm) mit Titelblatt und Kontrollblatt für das gewählte Jahr. Verantwortlicher und Adresse werden beim Erstellen gespeichert.</p>
            <div class="row g-2" id="kanti-kopf">
              <input type="hidden" id="kanti-csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
              <div class="col-12 col-md-3">
                <label class="form-label small mb-1" for="kanti-verantwortlicher">Verantwortlicher</label>
                <select class="form-select form-select-sm" id="kanti-verantwortlicher" data-tooltip="Verantwortlicher auf dem SKSG-Abrechnungsformular; die Wahl bleibt gespeichert">
                  <option value="">– wählen –</option>
                  <?php foreach ($kantiMitglieder as $m): ?>
                  <option value="<?= (int) $m['ID'] ?>"<?= (int) $m['ID'] === (int) $kantiKopf['kanti_verantwortlicher_id'] ? ' selected' : '' ?>
                          data-strasse="<?= htmlspecialchars((string) $m['Strasse'], ENT_QUOTES) ?>"
                          data-plzort="<?= htmlspecialchars(trim($m['PLZ'] . ' ' . $m['Ort']), ENT_QUOTES) ?>"
                          data-email="<?= htmlspecialchars((string) $m['Email'], ENT_QUOTES) ?>"><?= htmlspecialchars($m['Name'] . ' ' . $m['Vorname'], ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label small mb-1" for="kanti-adresse1">Strasse</label>
                <input type="text" class="form-control form-control-sm" id="kanti-adresse1" placeholder="Strasse Nr." maxlength="120" value="<?= htmlspecialchars($kantiKopf['kanti_adresse1'], ENT_QUOTES) ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label small mb-1" for="kanti-adresse2">PLZ Ort</label>
                <input type="text" class="form-control form-control-sm" id="kanti-adresse2" placeholder="PLZ Ort" maxlength="120" value="<?= htmlspecialchars($kantiKopf['kanti_adresse2'], ENT_QUOTES) ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label small mb-1" for="kanti-email">E-Mail</label>
                <input type="email" class="form-control form-control-sm" id="kanti-email" placeholder="E-Mail-Adresse" maxlength="120" value="<?= htmlspecialchars($kantiKopf['kanti_email'], ENT_QUOTES) ?>">
              </div>
            </div>
            <div class="ka-aktionen">
              <button type="button" class="btn btn-sm btn-outline-info word-btn" data-tooltip="SKSG-Abrechnungsformular (xlsm) mit Titelblatt und Kontrollblatt befüllen">
                <i class="bi bi-file-earmark-excel me-1"></i>SKSG-Abrechnung (Excel)
              </button>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</div>
<script>
$(document).ready(function() {
    var basePath = '';

    // Initialisierung des Jahres-Dropdowns
    function initializeYearDropdown() {
        msvJahrAuswahl('#yearSelect');
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

    // Neu laden pro Kategorie (vorher inline onclick auf nicht globale Funktionen – lief ins Leere)
    $(document).on('click', '.ka-neu', function() {
        if ($(this).data('kat') === 'A') loadKantonala(); else loadKantonalb();
    });

    // Redirect-Button
    $('#redirect-btn').on('click', function() {
        window.location.href = 'kantiresultate.php';
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