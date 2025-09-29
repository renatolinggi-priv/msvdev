<?php
// cuprang.php – modularer Aufbau (Repository/Logic/Renderer getrennt), ähnlich heimrang.php

require_once 'dbconnect.inc.php';
require_once 'cuprang/cup_repository.php';
require_once 'cuprang/cup_table_renderer.php';

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$page_specific_css = '';

$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

include 'header.inc.php';
?>
<div class="container my-4 ">

  <div class="row">
    <div class="col-6">
      <div class="card shadow-sm mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <h1 class="h4 mb-1"><i class="bi bi-trophy-fill me-2"></i>MSV Wilen Vereinscup – Übersicht</h1>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Steuerung -->
  <div class="row g-3 align-items-end">
    <div class="col-6 ">
      <div class="card shadow-sm">
        <div class="card-body">
          <form method="get" class="row g-2">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="d-flex align-items-center gap-2">
              <label for="yearSelect" class="form-label mb-0">Jahr</label>
              <select id="yearSelect" class="form-select form-select-sm" style="width:auto"></select>

              <button id="btnCupPdf" class="btn btn-sm  btn-compact-standard btn-outline-primary">
                <i class="bi bi-file-earmark-pdf"></i> PDF exportieren
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php
  $conn = get_db_connection();
  if (!$conn) {
      echo '<div class="alert alert-danger mt-4">Datenbankverbindung fehlgeschlagen.</div>';
  } else {
      $pairs = cup_fetch_pairs($conn, $selectedYear);
      $final = cup_fetch_final_results($conn, $selectedYear);
      $stand = cup_fetch_standcup_final($conn, $selectedYear);   // <<-- NEU

      // Runden bestimmen
      $rounds = array_values(array_unique(array_map(fn($r) => (int)$r['Round'], $pairs)));
      sort($rounds, SORT_ASC);
      ?>

      <!-- Paarungen -->
      <div class="row mt-2">
        <div class="col-12 col-md-8 col-lg-6"> <!-- schmaler Hauptcontainer, linksbündig -->
          <div class="card shadow-sm">
            <div class="card-header">
              <strong>Paarungen <?= (int)$selectedYear ?></strong>
            </div>
            <div class="card-body">
              <?php
              if (empty($pairs)) {
                  echo '<div class="text-muted">Keine Paarungen vorhanden.</div>';
              } else {
                  foreach ($rounds as $rnd) {
                      echo '<h2 class="h6 mt-3 mb-2"><i class="bi bi-diagram-3"></i> Runde ' . (int)$rnd . '</h2>';
                      echo cup_render_round_table($conn, $pairs, $rnd);
                  }
              }
              ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Finale Rangliste -->
      <div class="row mt-4">
        <div class="col-12 col-md-8 col-lg-6"> <!-- gleicher schmaler Container -->
          <div class="card shadow-sm">
            <div class="card-header">
              <strong>Finale Rangliste <?= (int)$selectedYear ?></strong>
            </div>
            <div class="card-body">
              <?php
              if (empty($final)) {
                  echo '<div class="text-muted">Noch keine Finalresultate vorhanden.</div>';
              } else {
                  echo cup_render_final_ranking_table($conn, $final);
              }
              ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Standcup Final -->
      <div class="row mt-4">
        <div class="col-12 col-md-8 col-lg-6"> <!-- gleicher schmaler Container -->
          <div class="card shadow-sm">
            <div class="card-header">
              <strong>Standcup Final <?= (int)$selectedYear ?></strong>
            </div>
            <div class="card-body">
              <?php
              if (empty($stand)) {
                  echo '<div class="text-muted">Noch keine Standcup-Finaldaten vorhanden.</div>';
              } else {
                  // gleicher Kartenstil wie Finale Rangliste
                  echo cup_render_standcup_table($conn, $stand);
              }
              ?>
            </div>
          </div>
        </div>
      </div>

      <?php
      $conn->close();
  }
  ?>

</div>
<script>
document.getElementById('btnCupPdf').addEventListener('click', async function(){
  const year = document.getElementById('yearSelect').value;
  const btn = this;
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Erzeuge PDF...';
  try {
    // 1) JSON vom Generator holen
    const res = await fetch('/inc/cuprang/generate_cup_pdf.php?year=' + encodeURIComponent(year), {
      headers: { 'Accept': 'application/json' }
    });
    const raw = await res.text();
    let data;
    try { data = JSON.parse(raw); }
    catch (e) { console.error('Server response (not JSON):', raw); throw new Error('Ungültige Antwort vom Server (kein JSON).'); }

    if (!(data && data.success && data.pdf_link)) {
      throw new Error(data && data.error ? data.error : 'PDF konnte nicht erstellt werden.');
    }

    // 2) PDF als Blob laden
    const pdfRes = await fetch(data.pdf_link, { credentials: 'same-origin' });
    if (!pdfRes.ok) throw new Error('PDF konnte nicht geladen werden.');
    const blob = await pdfRes.blob();

    // 3) Dateiname mit Jahr + Zeitstempel bauen
    function pad(n){ return n.toString().padStart(2,'0'); }
    const now = new Date();
    const ts = now.getFullYear() + '-' +
               pad(now.getMonth()+1) + '-' +
               pad(now.getDate()) + '_' +
               pad(now.getHours()) + '-' +
               pad(now.getMinutes()) + '-' +
               pad(now.getSeconds());
    const filename = `cup_${year}_${ts}.pdf`;

    // 4) Download erzwingen
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    alert('Fehler beim PDF-Export: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = orig;
  }
});

// Year-Dropdown füllen
(function() {
  const select = document.getElementById('yearSelect');
  const current = new Date().getFullYear();
  const start = 2024;
  const end = current + 1;
  const selected = <?= (int)$selectedYear ?>;
  for (let y = end; y >= start; y--) {
    const opt = document.createElement('option');
    opt.value = y; opt.textContent = y;
    if (y === selected) opt.selected = true;
    select.appendChild(opt);
  }
})();
</script>
<?php include 'footer.inc.php'; ?>
