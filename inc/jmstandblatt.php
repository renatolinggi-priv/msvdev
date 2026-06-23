<?php
// jmstandblatt.php - JM Standblatt generieren (Word) für aktive Mitglieder
require_once 'config.php';

// Session-Kontrolle
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentYear = date('Y');

// Aktive Mitglieder laden
$mitglieder = [];
$sql = "SELECT ID, Vorname, Name FROM mitglieder WHERE Status = 1 AND Verstorben = 0 ORDER BY Name, Vorname";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $mitglieder[] = $row;
    }
}

$page_specific_css = <<<'CSS'
/* ===== JM Standblatt ===== */

/* Auf dieser Seite kein Row-Click → cursor zurücksetzen */
.hybrid-table tbody tr.hybrid-row {
    cursor: default;
}
.hybrid-table tbody tr.hybrid-row:hover {
    background: rgba(99, 102, 241, 0.03);
}

/* Mobile */
@media (max-width: 767.98px) {
    .desktop-table-container { display: none !important; }
    .mobile-cards-container { display: block !important; }
}
@media (min-width: 768px) {
    .mobile-cards-container { display: none !important; }
}
CSS;

include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-xl-9 col-lg-11 col-12 ps-0">
      <div class="main-content-wrapper">
        <!-- Desktop-Header (unsichtbar auf Mobile) -->
        <div class="row mb-4 d-none d-md-flex">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-file-earmark-word me-2"></i>JM Standblatt
            </h2>
          </div>
        </div>

        <div class="content-background">
          <!-- Filter-Bereich -->
          <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
            <!-- Suchfeld -->
            <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width:350px;">
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Mitglied suchen...">
              </div>
            </div>

            <!-- Jahr-Auswahl -->
            <div class="d-flex align-items-center gap-2">
              <label class="form-label mb-0 small fw-bold">Jahr:</label>
              <select id="yearSelect" class="form-select form-select-sm" style="width:100px">
                <?php for ($y = $currentYear + 1; $y >= $currentYear - 3; $y--): ?>
                  <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <!-- Aktionen (Collapse-Card wie Mitgliederverwaltung) -->
            <div class="card action-card mb-0">
              <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                   data-bs-toggle="collapse" data-bs-target="#sbActions"
                   aria-expanded="false" aria-controls="sbActions">
                <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                <i class="bi bi-chevron-down action-chevron"></i>
              </div>
              <div class="collapse" id="sbActions">
                <div class="card-body pt-2 pb-3 px-3">
                  <div class="row g-2">
                    <div class="col-6">
                      <button type="button" id="btnDownloadAll" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-download me-1"></i>Alle (DOCX)
                      </button>
                    </div>
                    <div class="col-6">
                      <button type="button" id="btnDownloadAllPdf" class="btn btn-outline-danger btn-sm w-100">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Alle (PDF)
                      </button>
                    </div>
                    <div class="col-6">
                      <button type="button" id="btnPrintAll" class="btn btn-outline-success btn-sm w-100" disabled title="QZ Tray nicht verbunden">
                        <i class="bi bi-printer me-1"></i>Alle drucken
                      </button>
                    </div>
                    <div class="col-6">
                      <span id="qzBadge" class="badge bg-secondary" style="font-size:.7rem">QZ Tray</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabelle -->
          <div class="table-wrapper">
            <h5 class="table-title">
              <span><i class="bi bi-file-earmark-word me-2"></i>Mitglieder</span>
              <span class="badge bg-secondary" id="memberCount"><?= count($mitglieder) ?> Mitglieder</span>
            </h5>

            <!-- Desktop-Tabelle -->
            <div class="desktop-table-container">
              <table class="hybrid-table" id="mitgliederTable">
                <thead>
                  <tr>
                    <th style="width:80px; text-align:center">Lizenz</th>
                    <th>Name</th>
                    <th>Vorname</th>
                    <th style="width:160px; text-align:center">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($mitglieder as $m): ?>
                  <tr class="hybrid-row"
                      data-id="<?= $m['ID'] ?>"
                      data-name="<?= htmlspecialchars($m['Name']) ?>"
                      data-vorname="<?= htmlspecialchars($m['Vorname']) ?>">
                    <td class="h-nr"><?= htmlspecialchars($m['ID']) ?></td>
                    <td class="h-name"><?= htmlspecialchars($m['Name']) ?></td>
                    <td><?= htmlspecialchars($m['Vorname']) ?></td>
                    <td class="text-center">
                      <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-success btn-standblatt"
                                data-id="<?= $m['ID'] ?>"
                                data-vorname="<?= htmlspecialchars($m['Vorname']) ?>"
                                data-name="<?= htmlspecialchars($m['Name']) ?>"
                                title="DOCX herunterladen"
                                onclick="event.stopPropagation();">
                          <i class="bi bi-file-earmark-word"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-print-single"
                                data-id="<?= $m['ID'] ?>"
                                data-vorname="<?= htmlspecialchars($m['Vorname']) ?>"
                                data-name="<?= htmlspecialchars($m['Name']) ?>"
                                title="Direktdruck"
                                disabled
                                onclick="event.stopPropagation();">
                          <i class="bi bi-printer"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Mobile Cards -->
            <div class="mobile-cards-container" id="mobileStandblattContainer">
              <div class="mobile-search">
                <div class="position-relative">
                  <i class="bi bi-search search-icon"></i>
                  <input type="text" class="form-control" placeholder="Mitglied suchen..."
                         oninput="filterMobileStandblatt(this)">
                </div>
              </div>
              <div class="mobile-cards-scroll" id="mobileStandblattCards">
                <?php foreach ($mitglieder as $m): ?>
                <div class="mobile-card" data-search="<?= strtolower($m['Name'] . ' ' . $m['Vorname'] . ' ' . $m['ID']) ?>">
                  <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                      <div class="fw-bold"><?= htmlspecialchars($m['Name']) ?> <?= htmlspecialchars($m['Vorname']) ?></div>
                      <small class="text-muted">Lizenz: <?= htmlspecialchars($m['ID']) ?></small>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                  </div>
                  <div class="mobile-card-body">
                      <button type="button" class="btn btn-outline-success btn-sm w-100 btn-standblatt"
                              data-id="<?= $m['ID'] ?>"
                              data-vorname="<?= htmlspecialchars($m['Vorname']) ?>"
                              data-name="<?= htmlspecialchars($m['Name']) ?>">
                        <i class="bi bi-file-earmark-word me-1"></i>Standblatt herunterladen
                      </button>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- QZ Tray Scripts -->
<script src="js/lib/rsvp.min.js"></script>
<script src="js/lib/sha-256.min.js"></script>
<script src="js/lib/qz-tray.js"></script>
<script src="js/print-manager.js"></script>

<script>
// --- Desktop-Suche ---
$('#searchInput').on('keyup', function() {
  const q = this.value.toLowerCase();
  $('#mitgliederTable tbody tr.hybrid-row').each(function() {
    const d = this.dataset;
    const text = [d.id, d.name, d.vorname].join(' ').toLowerCase();
    $(this).toggle(text.includes(q));
  });
});

// --- Mobile-Suche ---
function filterMobileStandblatt(input) {
  const q = input.value.toLowerCase();
  document.querySelectorAll('#mobileStandblattCards .mobile-card').forEach(c => {
    c.style.display = (c.dataset.search || '').includes(q) ? '' : 'none';
  });
}

async function downloadStandblatt(btn, mitgliedId, vorname, name) {
  const jahr = document.getElementById('yearSelect').value;
  const originalHTML = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

  try {
    const url = `jmstandblatt/generate_jmstandblatt.php?jahr=${jahr}&mitglied_id=${mitgliedId}`;
    const response = await fetch(url);
    if (!response.ok) throw new Error('Fehler beim Generieren');

    const blob = await response.blob();
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `JM_Standblatt_${jahr}_${vorname}${name}.docx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(a.href);
  } catch (err) {
    console.error(err);
    msvToast('Fehler beim Generieren des Standblatts', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = originalHTML;
  }
}

// Einzelner Download
document.querySelectorAll('.btn-standblatt').forEach(btn => {
  btn.addEventListener('click', function() {
    downloadStandblatt(this, this.dataset.id, this.dataset.vorname, this.dataset.name);
  });
});

// --- QZ Tray Druck-Integration ---
let _pm = null;
let _printConfig = null;

async function initPrint() {
    if (typeof PrintManager === 'undefined' || typeof qz === 'undefined') return;

    _pm = new PrintManager();
    _pm.onStatusChange = (connected) => updateQzBadge(connected);

    try {
        await _pm.connect();
    } catch (err) {
        console.warn('QZ Tray nicht verfuegbar:', err.message);
        _pm = null;
        return;
    }

    await loadPrintConfig();
    updateQzBadge(_pm?.connected ?? false);
}

async function loadPrintConfig() {
    try {
        const res = await $.getJSON('drucksteuerung/profiles_api.php', { doc_type: 'jm_standblatt' });
        if (res.success && res.data.length > 0) {
            _printConfig = res.data[0];
        }
    } catch (err) {
        console.error('Druckprofil laden fehlgeschlagen:', err);
    }
}

function updateQzBadge(connected) {
    const badge = document.getElementById('qzBadge');
    const btn = document.getElementById('btnPrintAll');
    const hasConfig = _printConfig && _printConfig.printer_name;
    const printReady = connected && hasConfig;

    if (badge) {
        badge.className = printReady ? 'badge bg-success' : 'badge bg-danger';
        badge.textContent = printReady ? 'QZ verbunden' : (connected ? 'Kein Profil' : 'QZ getrennt');
    }
    if (btn) {
        btn.disabled = !printReady;
        btn.title = !connected ? 'QZ Tray nicht verbunden' :
                    !hasConfig ? 'Kein Drucker konfiguriert (Drucksteuerung)' : 'Alle Standblätter drucken';
    }
    document.querySelectorAll('.btn-print-single').forEach(b => {
        b.disabled = !printReady;
    });
}

async function printStandblatt(mitgliedId, vorname, name) {
    if (!_pm || !_pm.connected || !_printConfig) return false;

    const jahr = document.getElementById('yearSelect').value;
    const url = `jmstandblatt/generate_jmstandblatt_pdf.php?jahr=${jahr}&mitglied_id=${mitgliedId}`;

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(await response.text() || 'PDF-Generierung fehlgeschlagen');
        const blob = await response.blob();

        const base64 = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result.split(',')[1]);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });

        await _pm.printPixel(
            _printConfig.printer_name,
            [{ type: 'pdf', format: 'base64', data: base64 }],
            {
                copies:      _printConfig.copies || 1,
                orientation: 'landscape',
                colorType:   _printConfig.color_mode || 'blackwhite',
                duplex:      _printConfig.duplex || false,
                rasterize:   false,
                jobName:     `JM_Standblatt_${jahr}_${vorname}${name}`,
            }
        );

        await _pm.logJob('jm_standblatt', _printConfig.printer_name, `${vorname} ${name}`, 'erfolgreich');
        return true;
    } catch (err) {
        console.error('Druckfehler:', err);
        await _pm.logJob('jm_standblatt', _printConfig.printer_name, `${vorname} ${name}`, 'fehler', 1, err.message);
        return false;
    }
}

// Einzelner Direktdruck
document.querySelectorAll('.btn-print-single').forEach(btn => {
  btn.addEventListener('click', async function() {
    const b = this;
    const originalHTML = b.innerHTML;
    b.disabled = true;
    b.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const success = await printStandblatt(b.dataset.id, b.dataset.vorname, b.dataset.name);
    if (success) {
        msvToast(`${b.dataset.vorname} ${b.dataset.name} gedruckt`, 'success');
    } else {
        msvToast(`Druckfehler: ${b.dataset.vorname} ${b.dataset.name}`, 'error');
    }

    b.disabled = false;
    b.innerHTML = originalHTML;
  });
});

// "Alle drucken" — kombiniertes PDF als ein Druckjob
document.getElementById('btnPrintAll').addEventListener('click', async function() {
    const btn = this;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>PDF wird erstellt…';

    const jahr = document.getElementById('yearSelect').value;

    try {
        const response = await fetch(`jmstandblatt/generate_jmstandblatt_all_pdf.php?jahr=${jahr}`);
        if (!response.ok) throw new Error(await response.text() || 'PDF-Generierung fehlgeschlagen');

        const skipped = parseInt(response.headers.get('X-Skipped') || '0');
        const blob = await response.blob();

        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Drucke…';

        const base64 = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result.split(',')[1]);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });

        await _pm.printPixel(
            _printConfig.printer_name,
            [{ type: 'pdf', format: 'base64', data: base64 }],
            {
                copies:      _printConfig.copies || 1,
                orientation: 'landscape',
                colorType:   _printConfig.color_mode || 'blackwhite',
                duplex:      _printConfig.duplex || false,
                rasterize:   false,
                jobName:     `JM_Standblaetter_${jahr}_alle`,
            }
        );

        const total = document.querySelectorAll('.btn-standblatt').length;
        await _pm.logJob('jm_standblatt', _printConfig.printer_name, `Alle Standblätter ${jahr}`, 'erfolgreich', total);

        if (skipped > 0) {
            msvToast((total - skipped) + ' Standblätter gedruckt, ' + skipped + ' übersprungen', 'warning');
        } else {
            msvToast(total + ' Standblätter gedruckt (1 Druckjob)', 'success');
        }
    } catch (err) {
        console.error('Druckfehler:', err);
        msvToast('Druckfehler: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        updateQzBadge(_pm?.connected ?? false);
    }
});

// Alle als PDF herunterladen
document.getElementById('btnDownloadAllPdf').addEventListener('click', async function() {
    const btn = this;
    const originalHTML = btn.innerHTML;
    const jahr = document.getElementById('yearSelect').value;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>PDF wird erstellt…';

    try {
        const response = await fetch(`jmstandblatt/generate_jmstandblatt_all_pdf.php?jahr=${jahr}`);
        if (!response.ok) throw new Error(await response.text() || 'Fehler beim Generieren');

        const blob = await response.blob();
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `JM_Standblaetter_${jahr}_alle.pdf`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(a.href);

        const sizeMB = (blob.size / 1024 / 1024).toFixed(1);
        msvToast(`PDF heruntergeladen (${sizeMB} MB)`, 'success');
    } catch (err) {
        console.error(err);
        msvToast('Fehler: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
});

// QZ Tray initialisieren
$(function() { initPrint(); });

// Alle herunterladen (sequenziell)
document.getElementById('btnDownloadAll').addEventListener('click', async function() {
  const btn = this;
  const originalHTML = btn.innerHTML;
  const buttons = document.querySelectorAll('.btn-standblatt');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>0 / ' + buttons.length;

  let count = 0;
  for (const rowBtn of buttons) {
    await downloadStandblatt(rowBtn, rowBtn.dataset.id, rowBtn.dataset.vorname, rowBtn.dataset.name);
    count++;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + count + ' / ' + buttons.length;
    // Kurze Pause damit der Browser den Download verarbeiten kann
    await new Promise(r => setTimeout(r, 300));
  }

  btn.disabled = false;
  btn.innerHTML = originalHTML;
  msvToast(count + ' Standblätter heruntergeladen', 'success');
});
</script>

<?php include 'footer.inc.php'; ?>
