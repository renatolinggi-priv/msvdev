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

/* Karte/Container auf feste, kompakte Breite begrenzen (verlässlicher als col-Breite) */
.main-content-wrapper { max-width: 1000px; }

/* Auf dieser Seite kein Row-Click → cursor zurücksetzen */
.hybrid-table tbody tr.hybrid-row {
    cursor: default;
}
.hybrid-table tbody tr.hybrid-row:hover {
    background: rgba(99, 102, 241, 0.03);
}

/* Etwas kompaktere Liste (Mittelweg) */
.hybrid-table { font-size: 0.86rem; }
.hybrid-table thead th { padding: 0.4rem 0.6rem; }
.hybrid-table tbody td { padding: 0.32rem 0.6rem; }
.hybrid-table tbody .btn-group-sm > .btn,
.hybrid-table tbody .btn {
    padding: 0.25rem 0.55rem;
    font-size: 0.85rem;
    line-height: 1.2;
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
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-narrow">
        <!-- Desktop-Header (unsichtbar auf Mobile) -->
        <?php $page_title = 'JM Standblatt'; include 'partials/page_header.inc.php'; ?>

        <div class="content-background">
          <!-- Filter-Bereich -->
          <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
            <!-- Suchfeld -->
            <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width:280px;">
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
                      <button type="button" id="btnDownloadAll" class="btn btn-outline-info btn-sm w-100">
                        <i class="bi bi-download me-1"></i>Alle (DOCX)
                      </button>
                    </div>
                    <div class="col-6">
                      <button type="button" id="btnDownloadAllPdf" class="btn btn-outline-info btn-sm w-100">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Alle (PDF)
                      </button>
                    </div>
                    <div class="col-6">
                      <button type="button" id="btnPrintAll" class="btn btn-outline-info btn-sm w-100" disabled title="QZ Tray nicht verbunden">
                        <i class="bi bi-printer me-1"></i>Alle drucken
                      </button>
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
                        <button type="button" class="btn btn-outline-info btn-standblatt"
                                data-id="<?= $m['ID'] ?>"
                                data-vorname="<?= htmlspecialchars($m['Vorname']) ?>"
                                data-name="<?= htmlspecialchars($m['Name']) ?>"
                                title="DOCX herunterladen"
                                onclick="event.stopPropagation();">
                          <i class="bi bi-file-earmark-word"></i>
                        </button>
                        <button type="button" class="btn btn-outline-info btn-print-single"
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
                    <button type="button" class="btn btn-outline-info btn-sm w-100 btn-standblatt"
                            data-id="<?= $m['ID'] ?>"
                            data-vorname="<?= htmlspecialchars($m['Vorname']) ?>"
                            data-name="<?= htmlspecialchars($m['Name']) ?>">
                      <i class="bi bi-file-earmark-word me-1"></i>Standblatt herunterladen
                    </button>
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

<?php include 'partials/direktdruck_scripts.inc.php'; ?>

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

// --- QZ Tray Druck-Integration (gemeinsamer Baustein js/msv-direktdruck.js, Profil «jm_standblatt») ---
// Die DOCX-Vorlage ist A4 QUER; die Ausrichtung wird darum explizit mitgegeben, Papier/Rand setzt MsvDruck.
const JM_DOC = 'jm_standblatt';

function printReady() {
    return typeof MsvDruck !== 'undefined' && MsvDruck.bereit(JM_DOC);
}

function updateQzBadge() {
    const badge = document.getElementById('qzBadge');
    const btn = document.getElementById('btnPrintAll');
    const ready = printReady();
    const grund = typeof MsvDruck !== 'undefined' ? MsvDruck.grund(JM_DOC, 'JM Standblatt') : 'QZ Tray nicht verfügbar';
    if (badge) {
        badge.className = ready ? 'badge bg-success' : 'badge bg-danger';
        badge.textContent = ready ? 'QZ verbunden' : (grund.startsWith('Kein Druckprofil') ? 'Kein Profil' : 'QZ getrennt');
    }
    if (btn) {
        btn.disabled = !ready;
        btn.title = ready ? 'Alle Standblätter drucken (' + MsvDruck.profilText(JM_DOC) + ')' : grund;
    }
    document.querySelectorAll('.btn-print-single').forEach(b => {
        b.disabled = !ready;
        b.title = ready ? 'Direktdruck (' + MsvDruck.profilText(JM_DOC) + ')' : grund;
    });
}

function initPrint() {
    if (typeof MsvDruck === 'undefined') { updateQzBadge(); return; }
    MsvDruck.onChange = updateQzBadge; // Verbindung/Profile laden im Hintergrund, Badge folgt
    updateQzBadge();
}

async function printStandblatt(mitgliedId, vorname, name) {
    if (!printReady()) return false;
    const jahr = document.getElementById('yearSelect').value;
    return MsvDruck.print({
        docType: JM_DOC,
        url: `jmstandblatt/generate_jmstandblatt_pdf.php?jahr=${encodeURIComponent(jahr)}&mitglied_id=${encodeURIComponent(mitgliedId)}`,
        jobName: `JM Standblatt ${vorname} ${name} ${jahr}`,
        orientation: 'landscape',
    });
}

// Einzelner Direktdruck (Erfolgs-/Fehlermeldung kommt aus MsvDruck.print)
document.querySelectorAll('.btn-print-single').forEach(btn => {
  btn.addEventListener('click', async function() {
    const b = this;
    const originalHTML = b.innerHTML;
    b.disabled = true;
    b.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    await printStandblatt(b.dataset.id, b.dataset.vorname, b.dataset.name);
    b.innerHTML = originalHTML;
    updateQzBadge();
  });
});

// "Alle drucken" — kombiniertes PDF als EIN Druckjob (Header X-Skipped = Mitglieder ohne Standblatt)
document.getElementById('btnPrintAll').addEventListener('click', async function() {
    const btn = this;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>PDF wird erstellt…';

    const jahr = document.getElementById('yearSelect').value;

    try {
        if (!printReady()) throw new Error('QZ Tray nicht verbunden oder kein Druckprofil');
        const response = await fetch(`jmstandblatt/generate_jmstandblatt_all_pdf.php?jahr=${encodeURIComponent(jahr)}`);
        if (!response.ok) throw new Error(await response.text() || 'PDF-Generierung fehlgeschlagen');

        const skipped = parseInt(response.headers.get('X-Skipped') || '0');
        const blob = await response.blob();

        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Drucke…';
        const ok = await MsvDruck.print({
            docType: JM_DOC,
            blob,
            jobName: `JM Standblätter ${jahr} alle`,
            orientation: 'landscape',
        });
        if (ok && skipped > 0) {
            msvToast(skipped + ' Mitglieder ohne Standblatt übersprungen', 'warning');
        }
    } catch (err) {
        console.error('Druckfehler:', err);
        msvToast('Druckfehler: ' + err.message, 'error');
    } finally {
        btn.innerHTML = originalHTML;
        updateQzBadge();
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
