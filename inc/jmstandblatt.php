<?php
// jmstandblatt.php - JM Standblatt generieren (Word/PDF/Direktdruck) für aktive Mitglieder
require_once 'dbconnect.inc.php';               // $conn (header.inc.php bindet dieselbe Datei ein -> eine Verbindung)
require_once 'partials/empty_state.inc.php';    // msv_empty_row()

$currentYear = (int)date('Y');

// Aktive Mitglieder laden
$mitglieder = [];
$result = $conn->query("SELECT ID, Vorname, Name FROM mitglieder WHERE Status = 1 AND Verstorben = 0 ORDER BY Name, Vorname");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $mitglieder[] = $row;
    }
}

// Zeilen haben keinen Klick-Handler (nur Aktions-Buttons) -> kein Pointer-Cursor
$page_specific_css = <<<'CSS'
.hybrid-table tbody tr.hybrid-row { cursor: default; }
CSS;

include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-narrow">
        <?php $page_title = 'JM Standblatt'; include 'partials/page_header.inc.php'; ?>

        <div class="content-background">
          <!-- Filter-Bereich -->
          <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
            <!-- Suchfeld -->
            <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width:280px;">
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Mitglied suchen..." aria-label="Mitglied suchen">
              </div>
            </div>

            <!-- Jahr-Auswahl -->
            <div class="d-flex align-items-center gap-2">
              <label class="form-label mb-0 small fw-bold" for="yearSelect">Jahr:</label>
              <select id="yearSelect" class="form-select form-select-sm" style="width:100px">
                <?php for ($y = $currentYear + 1; $y >= $currentYear - 3; $y--): ?>
                  <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <!-- Aktionen (zentrales Partial) -->
            <?php
            $ac_id = 'sbActions';
            ob_start(); ?>
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
                      <button type="button" id="btnPrintAll" class="btn btn-outline-info btn-sm w-100" disabled data-tooltip="QZ Tray nicht verbunden">
                        <i class="bi bi-printer me-1"></i>Alle drucken
                      </button>
                    </div>
                    <div class="col-6 d-flex align-items-center small text-muted">
                      <span class="me-2">Direktdruck:</span><span id="qzBadge" class="badge bg-secondary">prüfe…</span>
                    </div>
                  </div>
            <?php
            $ac_body = ob_get_clean();
            include 'partials/action_card.inc.php';
            ?>
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
                  <?php if (!$mitglieder): ?>
                    <?= msv_empty_row(4, 'Keine aktiven Mitglieder gefunden') ?>
                  <?php endif; ?>
                  <?php foreach ($mitglieder as $m): ?>
                  <tr class="hybrid-row"
                      data-id="<?= (int)$m['ID'] ?>"
                      data-name="<?= htmlspecialchars($m['Name']) ?>"
                      data-vorname="<?= htmlspecialchars($m['Vorname']) ?>">
                    <td class="h-nr"><?= (int)$m['ID'] ?></td>
                    <td class="h-name"><?= htmlspecialchars($m['Name']) ?></td>
                    <td><?= htmlspecialchars($m['Vorname']) ?></td>
                    <td class="text-center">
                      <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-info btn-standblatt"
                                data-id="<?= (int)$m['ID'] ?>"
                                data-vorname="<?= htmlspecialchars($m['Vorname']) ?>"
                                data-name="<?= htmlspecialchars($m['Name']) ?>"
                                data-tooltip="DOCX herunterladen">
                          <i class="bi bi-file-earmark-word"></i>
                        </button>
                        <button type="button" class="btn btn-outline-info btn-print-single"
                                data-id="<?= (int)$m['ID'] ?>"
                                data-vorname="<?= htmlspecialchars($m['Vorname']) ?>"
                                data-name="<?= htmlspecialchars($m['Name']) ?>"
                                data-tooltip="Direktdruck"
                                disabled>
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
                  <input type="text" class="form-control" placeholder="Mitglied suchen..." aria-label="Mitglied suchen"
                         oninput="filterMobileStandblatt(this)">
                </div>
              </div>
              <div class="mobile-cards-scroll" id="mobileStandblattCards">
                <?php if (!$mitglieder): ?>
                  <div class="text-center text-muted py-4"><i class="bi bi-inbox d-block mb-2" style="font-size:1.6rem;opacity:.5;"></i>Keine aktiven Mitglieder gefunden</div>
                <?php endif; ?>
                <?php foreach ($mitglieder as $m): ?>
                <div class="mobile-card" data-search="<?= htmlspecialchars(mb_strtolower($m['Name'] . ' ' . $m['Vorname'] . ' ' . $m['ID'])) ?>">
                  <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                      <div class="fw-bold"><?= htmlspecialchars($m['Name']) ?> <?= htmlspecialchars($m['Vorname']) ?></div>
                      <small class="text-muted">Lizenz: <?= (int)$m['ID'] ?></small>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                  </div>
                  <div class="mobile-card-body">
                    <button type="button" class="btn btn-outline-info btn-sm w-100 btn-standblatt"
                            data-id="<?= (int)$m['ID'] ?>"
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
// --- Suche (Desktop): 'input' reagiert auch auf Einfügen/Löschen per Maus ---
$('#searchInput').on('input', function() {
  const q = this.value.toLowerCase();
  let visible = 0;
  $('#mitgliederTable tbody tr[data-id]').each(function() {
    const d = this.dataset;
    const hit = [d.id, d.name, d.vorname].join(' ').toLowerCase().includes(q);
    $(this).toggle(hit);
    if (hit) visible++;
  });
  $('#memberCount').text(visible + ' Mitglieder');
});

// --- Suche (Mobile) ---
function filterMobileStandblatt(input) {
  const q = input.value.toLowerCase();
  document.querySelectorAll('#mobileStandblattCards .mobile-card').forEach(c => {
    c.style.display = (c.dataset.search || '').includes(q) ? '' : 'none';
  });
}

function standblattUrl(script, mitgliedId) {
  const p = new URLSearchParams({ jahr: document.getElementById('yearSelect').value });
  if (mitgliedId != null) p.set('mitglied_id', mitgliedId);
  return 'jmstandblatt/' + script + '?' + p.toString();
}

function saveBlob(blob, filename) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(a.href);
}

// Uebersprungene Mitglieder aus den Antwort-Headern des Sammel-PDFs melden
function meldeUebersprungene(response) {
  const skipped = parseInt(response.headers.get('X-Skipped') || '0', 10);
  if (!skipped) return;
  let names = '';
  try { names = decodeURIComponent(response.headers.get('X-Skipped-Names') || ''); } catch (e) { /* ignorieren */ }
  msvToast(skipped + ' Mitglied(er) ohne Standblatt übersprungen' + (names ? ': ' + names : ''), 'warning');
}

async function downloadStandblatt(btn, mitgliedId, vorname, name) {
  const jahr = document.getElementById('yearSelect').value;
  const originalHTML = btn ? btn.innerHTML : '';
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }

  try {
    const response = await fetch(standblattUrl('generate_jmstandblatt.php', mitgliedId));
    if (!response.ok) throw new Error(await response.text() || 'Fehler beim Generieren');
    saveBlob(await response.blob(), `JM_Standblatt_${jahr}_${vorname}${name}.docx`);
    return true;
  } catch (err) {
    console.error(err);
    msvToast('Fehler beim Generieren des Standblatts: ' + err.message, 'error');
    return false;
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = originalHTML; }
  }
}

// Einzelner Download (Desktop-Zeile und Mobile-Card tragen dieselbe Klasse)
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
        badge.dataset.tooltip = ready ? MsvDruck.profilText(JM_DOC) : grund;
    }
    if (btn) {
        btn.disabled = !ready;
        btn.dataset.tooltip = ready ? 'Alle Standblätter drucken (' + MsvDruck.profilText(JM_DOC) + ')' : grund;
    }
    document.querySelectorAll('.btn-print-single').forEach(b => {
        b.disabled = !ready;
        b.dataset.tooltip = ready ? 'Direktdruck (' + MsvDruck.profilText(JM_DOC) + ')' : grund;
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
        url: standblattUrl('generate_jmstandblatt_pdf.php', mitgliedId),
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

// "Alle drucken" — kombiniertes PDF als EIN Druckjob
document.getElementById('btnPrintAll').addEventListener('click', async function() {
    const btn = this;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>PDF wird erstellt…';
    const jahr = document.getElementById('yearSelect').value;

    try {
        if (!printReady()) throw new Error('QZ Tray nicht verbunden oder kein Druckprofil');
        const response = await fetch(standblattUrl('generate_jmstandblatt_all_pdf.php'));
        if (!response.ok) throw new Error(await response.text() || 'PDF-Generierung fehlgeschlagen');
        const blob = await response.blob();

        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Drucke…';
        const ok = await MsvDruck.print({
            docType: JM_DOC,
            blob,
            jobName: `JM Standblätter ${jahr} alle`,
            orientation: 'landscape',
        });
        if (ok) meldeUebersprungene(response);
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
        const response = await fetch(standblattUrl('generate_jmstandblatt_all_pdf.php'));
        if (!response.ok) throw new Error(await response.text() || 'Fehler beim Generieren');
        const blob = await response.blob();
        saveBlob(blob, `JM_Standblaetter_${jahr}_alle.pdf`);
        msvToast(`PDF heruntergeladen (${(blob.size / 1024 / 1024).toFixed(1)} MB)`, 'success');
        meldeUebersprungene(response);
    } catch (err) {
        console.error(err);
        msvToast('Fehler: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
});

// Alle als DOCX herunterladen (sequenziell) — ueber die Datenzeilen, nicht ueber Buttons
// (Desktop-Zeile UND Mobile-Card tragen .btn-standblatt -> vorher jedes Blatt doppelt)
document.getElementById('btnDownloadAll').addEventListener('click', async function() {
  const btn = this;
  const originalHTML = btn.innerHTML;
  const rows = Array.from(document.querySelectorAll('#mitgliederTable tbody tr[data-id]'));
  if (!rows.length) { msvToast('Keine Mitglieder vorhanden', 'warning'); return; }
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>0 / ' + rows.length;

  let ok = 0;
  for (const row of rows) {
    const rowBtn = row.querySelector('.btn-standblatt');
    if (await downloadStandblatt(rowBtn, row.dataset.id, row.dataset.vorname, row.dataset.name)) ok++;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + ok + ' / ' + rows.length;
    // Kurze Pause damit der Browser den Download verarbeiten kann
    await new Promise(r => setTimeout(r, 300));
  }

  btn.disabled = false;
  btn.innerHTML = originalHTML;
  msvToast(ok + ' von ' + rows.length + ' Standblättern heruntergeladen', ok === rows.length ? 'success' : 'warning');
});

// QZ Tray initialisieren
$(function() { initPrint(); });
</script>

<?php include 'footer.inc.php'; ?>
