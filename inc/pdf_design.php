<?php
/* =============================================
 * FILE: /inc/pdf_design.php
 * PURPOSE: Zentrale PDF-Vorlage (Farben + Layout) bearbeiten — mit Live-Vorschau,
 *          1-Klick-Presets und sicherem Fallback auf Standardwerte (nur Admin).
 *          Speichert nach pdf_theme_settings (Endpoint inc/pdf_design/save.php);
 *          gelesen von inc/pdf/pdf_theme.php.
 * ============================================= */
require_once __DIR__ . '/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/pdf/pdf_theme.php';

// Nur Admin (Vorstand sieht den Admin-Bereich, aber nicht das PDF-Design) – vor header.inc.php
if (!isAdmin() && (int)($_SESSION['user_id'] ?? 0) !== 1) {
    header('Location: home.php');
    exit;
}

$schema   = pdf_theme_schema();
$defaults = pdf_theme_defaults();
$presets  = pdf_theme_presets();
$current  = pdf_theme_palette();

// Logo-URL mit Cache-Busting per Dateizeit (Konvention: filemtime, nie time())
$logoMtime = (int)@filemtime(pdf_logo_path());
$logoUrl   = '../images/MSVWilen_Logo.jpg?v=' . ($logoMtime ?: 1);

// Mapping Theme-Key -> CSS-Variable in der Vorschau
$varMap = [
    'text' => '--p-text', 'muted' => '--p-muted', 'accent' => '--p-accent',
    'head_bg' => '--p-headbg', 'head_text' => '--p-headtext', 'head_line' => '--p-headline',
    'border' => '--p-border', 'zebra' => '--p-zebra', 'total_bg' => '--p-totalbg',
    'gold_bg' => '--p-goldbg', 'gold_tx' => '--p-goldtx', 'silver_bg' => '--p-silverbg',
    'silver_tx' => '--p-silvertx', 'bronze_bg' => '--p-bronzebg', 'bronze_tx' => '--p-bronzetx',
    'win' => '--p-win', 'struck' => '--p-struck',
];

// Vorschau-Variablen einmal aus den aktuellen Werten (kein zweiter Default-Satz im CSS)
$previewVars = '';
foreach ($varMap as $key => $var) {
    $previewVars .= $var . ':' . $current[$key] . ';';
}
$previewVars .= '--p-font:' . $current['base_font'] . 'px;--p-logow:' . $current['logo_width'] . 'px;--p-bw:' . $current['border_width'] . 'px;';

$groupsOrder = ['Allgemein', 'Tabelle', 'Medaillen', 'Status', 'Layout'];

$page_specific_css = <<<'CSS'
.pd-wrap { display: grid; grid-template-columns: 440px minmax(0, 1fr); gap: 24px; align-items: start; }
.pd-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px 18px; }
.pd-card + .pd-card { margin-top:14px; }
/* Sektionstitel: gleiche Optik wie der zentrale Tabellentitel (.table-title), kompakt */
.pd-card > h3, .pd-toggle { font-size:.95rem; font-weight:600; color:#1e293b; margin:0 0 10px; }
.pd-toggle { display:flex; width:100%; align-items:center; justify-content:space-between; background:none; border:0; padding:0; cursor:pointer; text-align:left; }
.pd-toggle:focus-visible { outline:2px solid #3b5998; outline-offset:2px; border-radius:4px; }
.pd-chevron { font-size:.8rem; color:#94a3b8; transition:transform .2s; }
.pd-toggle[aria-expanded="false"] { margin:0; }
.pd-toggle[aria-expanded="false"] .pd-chevron { transform:rotate(-90deg); }
.pd-field { display:flex; align-items:center; gap:10px; padding:5px 0; }
.pd-field label { flex:1; font-size:.86rem; color:#2d3748; }
.pd-field input[type=color] { width:42px; height:30px; border:1px solid #cbd5e0; border-radius:6px; padding:0; background:#fff; cursor:pointer; }
.pd-field input.hex { width:92px; font-family:ui-monospace,Consolas,monospace; font-size:.8rem; text-transform:lowercase; }
.pd-field input.num { width:92px; }
.pd-presets { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:6px; }
.pd-actions { display:flex; gap:8px; margin-top:18px; flex-wrap:wrap; }
.pd-logo-box { max-height:64px; max-width:160px; border:1px solid #e2e8f0; border-radius:6px; padding:4px; background:#fff; }
.pd-logo-text { flex:1; min-width:200px; }
.pd-crop-wrap { max-height:60vh; overflow:hidden; }
.pd-crop-wrap img { max-width:100%; display:block; }
/* Vorschau: Werte kommen ausschliesslich aus dem Inline-Style (PHP) */
.pd-preview-sticky { position: sticky; top: 16px; }
@media (max-width: 991.98px) { .pd-wrap { grid-template-columns: 1fr; } .pd-preview-sticky { position: static; } }
#pdfPreview { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:18px; color:var(--p-text); font-family: Arial, sans-serif; }
#pdfPreview .pv-logo { width:var(--p-logow); max-width:100%; height:auto; display:block; }
#pdfPreview h4 { text-align:center; color:var(--p-accent); margin:10px 0 14px; font-size:1.05rem; }
#pdfPreview table { width:100%; border-collapse:collapse; font-size:calc(var(--p-font) + 3px); background:#fff; }
#pdfPreview th, #pdfPreview td { border:var(--p-bw) solid var(--p-border); padding:5px 8px; text-align:left; }
#pdfPreview th.c, #pdfPreview td.c { text-align:center; }
#pdfPreview thead th { background:var(--p-headbg); color:var(--p-headtext); border-bottom:2px solid var(--p-headline); font-weight:700; }
#pdfPreview tbody tr:nth-child(even) { background:var(--p-zebra); }
#pdfPreview td:first-child { text-align:center; font-weight:700; }
#pdfPreview td:last-child { text-align:right; font-weight:700; background:var(--p-totalbg); color:var(--p-accent); }
#pdfPreview tr.r1 td:first-child { background:var(--p-goldbg); color:var(--p-goldtx); }
#pdfPreview tr.r2 td:first-child { background:var(--p-silverbg); color:var(--p-silvertx); }
#pdfPreview tr.r3 td:first-child { background:var(--p-bronzebg); color:var(--p-bronzetx); }
#pdfPreview .pv-struck { color:var(--p-struck); text-decoration:line-through; }
#pdfPreview .pv-win { color:var(--p-win); font-weight:700; }
#pdfPreview .pv-lose { color:var(--p-struck); text-decoration:line-through; }
#pdfPreview .pv-badges { margin-top:12px; display:flex; gap:8px; align-items:center; font-size:.8rem; }
#pdfPreview .pv-badge { padding:2px 9px; border-radius:12px; font-weight:700; font-size:.72rem; }
#pdfPreview .pv-b1 { background:var(--p-goldbg); color:var(--p-goldtx); }
#pdfPreview .pv-b2 { background:var(--p-silverbg); color:var(--p-silvertx); }
#pdfPreview .pv-b3 { background:var(--p-bronzebg); color:var(--p-bronzetx); }
#pdfPreview .pv-foot { margin-top:14px; border-top:1px solid var(--p-headline); padding-top:6px; text-align:center; font-size:.7rem; color:var(--p-muted); }
CSS;

include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-default">

        <?php $page_title = 'PDF-Vorlage'; include 'partials/page_header.inc.php'; ?>

        <form id="pdfDesignForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <div class="pd-wrap">
            <!-- LINKS: Einstellungen -->
            <div>
              <div class="pd-card">
                <h3>Logo</h3>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  <img id="logoPreview" class="pd-logo-box" src="<?= htmlspecialchars($logoUrl) ?>" alt="Aktuelles Logo">
                  <div class="pd-logo-text">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="logoChooseBtn"><i class="bi bi-upload me-1"></i>Logo ersetzen</button>
                    <input type="file" id="logoFile" accept="image/png,image/jpeg" hidden>
                    <div class="text-muted small mt-1">Beim Ersetzen wählst du den Bildausschnitt. Die Anzeigegrösse im PDF stellst du unter <strong>Layout → Logo-Breite</strong> ein. Das bisherige Logo wird als Sicherung <code>MSVWilen_Logo.bak.jpg</code> behalten.</div>
                  </div>
                </div>
              </div>

              <div class="pd-card">
                <h3>Vorlagen</h3>
                <div class="pd-presets">
                  <?php foreach ($presets as $pk => $preset): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary pd-preset" data-preset="<?= htmlspecialchars($pk) ?>">
                      <i class="bi bi-magic me-1"></i><?= htmlspecialchars($preset['label']) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
                <div class="text-muted small">1-Klick laden, danach einzelne Werte frei anpassen.</div>
              </div>

              <?php foreach ($groupsOrder as $gi => $group): $gid = 'pdGroup' . $gi; ?>
                <div class="pd-card">
                  <button type="button" class="pd-toggle" aria-expanded="false" aria-controls="<?= $gid ?>">
                    <span><?= htmlspecialchars($group) ?></span><i class="bi bi-chevron-down pd-chevron"></i>
                  </button>
                  <div class="pd-card-body" id="<?= $gid ?>" hidden>
                    <?php foreach ($schema as $key => $def): if (($def['group'] ?? '') !== $group) continue; ?>
                      <div class="pd-field">
                        <label for="f_<?= $key ?>"><?= htmlspecialchars($def['label']) ?></label>
                        <?php if ($def['type'] === 'color'): ?>
                          <input type="text" class="form-control form-control-sm hex" id="h_<?= $key ?>" data-key="<?= $key ?>" value="<?= htmlspecialchars($current[$key]) ?>" maxlength="7" spellcheck="false" aria-label="<?= htmlspecialchars($def['label']) ?> (Hex)">
                          <input type="color" id="f_<?= $key ?>" data-key="<?= $key ?>" value="<?= htmlspecialchars($current[$key]) ?>">
                        <?php else: ?>
                          <input type="number" class="form-control form-control-sm num" id="f_<?= $key ?>" data-key="<?= $key ?>"
                                 value="<?= (int)$current[$key] ?>" min="<?= (int)$def['min'] ?>" max="<?= (int)$def['max'] ?>" step="1">
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>

              <div class="pd-actions">
                <button type="submit" class="btn btn-outline-primary btn-sm" id="pdSave"><i class="bi bi-save me-1"></i>Speichern</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="pdReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Auf Standard zurücksetzen</button>
              </div>
            </div>

            <!-- RECHTS: Live-Vorschau -->
            <div class="pd-preview-sticky">
              <div class="pd-card">
                <h3>Live-Vorschau</h3>
                <div id="pdfPreview" style="<?= htmlspecialchars($previewVars, ENT_QUOTES, 'UTF-8') ?>">
                  <img id="pvLogo" class="pv-logo" src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo">
                  <h4>Jahresmeisterschaft <?= date('Y') ?></h4>
                  <table>
                    <thead><tr><th style="width:42px">Rang</th><th>Name</th><th class="c">Endstich</th><th class="c">Kanti</th><th style="width:64px">Total</th></tr></thead>
                    <tbody>
                      <tr class="r1"><td>1</td><td>Muster Hans</td><td class="c">96.00</td><td class="c">94.50</td><td>190.50</td></tr>
                      <tr class="r2"><td>2</td><td>Beispiel Anna</td><td class="c">93.00</td><td class="c">95.00</td><td>188.00</td></tr>
                      <tr class="r3"><td>3</td><td>Schütze Peter</td><td class="c">91.50</td><td class="c">92.00</td><td>183.50</td></tr>
                      <tr><td>4</td><td>Tell Wilhelm</td><td class="c"><span class="pv-struck">88.00</span></td><td class="c">90.00</td><td>178.00</td></tr>
                    </tbody>
                  </table>
                  <div class="pv-badges">
                    <span>Cup:</span> <span class="pv-win">Muster Hans</span> <span>vs</span> <span class="pv-lose">Tell Wilhelm</span>
                    <span class="ms-auto"></span>
                    <span class="pv-badge pv-b1">1.</span><span class="pv-badge pv-b2">2.</span><span class="pv-badge pv-b3">3.</span>
                  </div>
                  <div class="pv-foot">Erstellt am <?= date('d.m.Y') ?> · MSV Wilen · Seite 1 von 1</div>
                </div>
                <div class="text-muted small mt-2"><i class="bi bi-info-circle me-1"></i>Browser-Vorschau – im PDF (Dompdf) praktisch identisch, da nur Volltöne &amp; einfache Rahmen verwendet werden.</div>
              </div>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

<!-- Logo-Zuschnitt Modal -->
<div class="modal fade" id="logoCropModal" tabindex="-1" aria-labelledby="logoCropTitle">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logoCropTitle"><i class="bi bi-crop me-2"></i>Logo zuschneiden</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div class="pd-crop-wrap"><img id="cropImg" alt="Zuzuschneidendes Logo"></div>
        <div class="text-muted small mt-2">Ziehe den Rahmen, um den sichtbaren Bereich zu wählen (freies Seitenverhältnis). Transparente Flächen werden weiss.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-primary btn-sm" id="logoCropSave"><i class="bi bi-save me-1"></i>Zuschneiden &amp; speichern</button>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
(function () {
  var SAVE_URL = 'pdf_design/save.php';
  var VARMAP   = <?= json_encode($varMap) ?>;
  var PRESETS  = <?= json_encode(array_map(static fn($p) => $p['values'], $presets)) ?>;
  var DEFAULTS = <?= json_encode($defaults) ?>;
  var LOGO_URL = <?= json_encode('../images/MSVWilen_Logo.jpg') ?>;
  var form     = document.getElementById('pdfDesignForm');
  var preview  = document.getElementById('pdfPreview');
  var csrf     = form.querySelector('[name=csrf_token]').value;

  // Gemeinsamer POST: prueft r.ok, liefert lesbare Meldung statt „Netzwerkfehler" bei 413/403/500
  function postAction(action, extra) {
    var fd = new FormData();
    fd.set('csrf_token', csrf);
    fd.set('action', action);
    if (extra) Object.keys(extra).forEach(function (k) { fd.set(k, extra[k]); });
    return fetch(SAVE_URL, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (res) {
          if (!r.ok && !(res && res.message)) {
            res = { success: false, message: r.status === 413 ? 'Anfrage zu gross für den Server (Bild verkleinern)' : (r.status === 401 ? 'Sitzung abgelaufen – bitte neu anmelden' : 'Serverfehler (' + r.status + ')') };
          }
          return res;
        });
      });
  }

  // ---- Logo ersetzen + zuschneiden (Cropper.js) ----
  var fileInput = document.getElementById('logoFile'), cropImg = document.getElementById('cropImg');
  var modalEl = document.getElementById('logoCropModal'), saveBtn = document.getElementById('logoCropSave');
  var cropper = null, bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);

  document.getElementById('logoChooseBtn').addEventListener('click', function () { fileInput.click(); });
  fileInput.addEventListener('change', function () {
    var file = this.files && this.files[0];
    if (!file) return;
    if (!/^image\/(png|jpe?g)$/.test(file.type)) { msvError('Bitte eine PNG- oder JPEG-Datei wählen.'); this.value = ''; return; }
    if (file.size > 6 * 1024 * 1024) { msvError('Datei zu gross (max. 6 MB).'); this.value = ''; return; }
    var reader = new FileReader();
    reader.onload = function (e) { cropImg.src = e.target.result; bsModal.show(); };
    reader.readAsDataURL(file);
    this.value = '';
  });
  modalEl.addEventListener('shown.bs.modal', function () {
    if (typeof Cropper === 'undefined') { msvError('Zuschnitt-Bibliothek konnte nicht geladen werden.'); return; }
    if (cropper) cropper.destroy();
    cropper = new Cropper(cropImg, { viewMode: 1, autoCropArea: 1, background: false, movable: true, zoomable: true });
  });
  modalEl.addEventListener('hidden.bs.modal', function () { if (cropper) { cropper.destroy(); cropper = null; } });

  saveBtn.addEventListener('click', function () {
    if (!cropper) return;
    var canvas = cropper.getCroppedCanvas({ maxWidth: 1200, maxHeight: 1200, fillColor: '#fff' });
    if (!canvas) { msvError('Zuschnitt fehlgeschlagen.'); return; }
    saveBtn.disabled = true;
    postAction('logo', { logo_data: canvas.toDataURL('image/jpeg', 0.92) })
      .then(function (res) {
        if (res && res.success) {
          bsModal.hide();
          var src = LOGO_URL + '?v=' + (res.mtime || Date.now()); // mtime vom Server = filemtime-Konvention
          document.getElementById('logoPreview').src = src;
          document.getElementById('pvLogo').src = src;
          msvToast(res.message || 'Logo aktualisiert', 'success');
          if (res.failed && res.failed.length) msvToast('Nicht geschrieben: ' + res.failed.join(', '), 'warning');
        } else msvError((res && res.message) || 'Fehler beim Speichern des Logos');
      })
      .catch(function () { msvError('Netzwerkfehler beim Logo-Upload'); })
      .finally(function () { saveBtn.disabled = false; });
  });

  // ---- Felder <-> Vorschau ----
  function applyToPreview(key, value) {
    if (VARMAP.hasOwnProperty(key)) preview.style.setProperty(VARMAP[key], value);
    else if (key === 'base_font') preview.style.setProperty('--p-font', parseInt(value, 10) + 'px');
    else if (key === 'logo_width') preview.style.setProperty('--p-logow', parseInt(value, 10) + 'px');
    else if (key === 'border_width') preview.style.setProperty('--p-bw', parseInt(value, 10) + 'px');
  }
  function syncColor(key, value) {
    if (!/^#[0-9a-fA-F]{6}$/.test(value)) return;
    value = value.toLowerCase();
    document.querySelectorAll('[data-key="' + key + '"]').forEach(function (el) { if (el.value.toLowerCase() !== value) el.value = value; });
    applyToPreview(key, value);
  }
  function setAll(vals) {
    Object.keys(vals).forEach(function (key) {
      document.querySelectorAll('[data-key="' + key + '"]').forEach(function (el) { el.value = vals[key]; });
      applyToPreview(key, vals[key]);
    });
  }
  document.querySelectorAll('input[type=color][data-key]').forEach(function (el) {
    ['input', 'change'].forEach(function (evt) { el.addEventListener(evt, function () { syncColor(this.dataset.key, this.value); }); });
  });
  document.querySelectorAll('input.hex[data-key]').forEach(function (el) {
    ['input', 'change'].forEach(function (evt) { el.addEventListener(evt, function () { syncColor(this.dataset.key, this.value.trim()); }); });
  });
  document.querySelectorAll('input.num[data-key]').forEach(function (el) {
    ['input', 'change'].forEach(function (evt) { el.addEventListener(evt, function () { applyToPreview(this.dataset.key, this.value); }); });
  });

  // Einklappbare Gruppen (Button mit aria-expanded, tastaturbedienbar)
  document.querySelectorAll('.pd-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var open = this.getAttribute('aria-expanded') === 'true';
      this.setAttribute('aria-expanded', String(!open));
      document.getElementById(this.getAttribute('aria-controls')).hidden = open;
    });
  });

  // Presets (gleicher Weg wie Zurücksetzen)
  document.querySelectorAll('.pd-preset').forEach(function (btn) {
    btn.addEventListener('click', function () { var vals = PRESETS[this.dataset.preset]; if (vals) setAll(vals); });
  });

  function collectValues() {
    var out = {};
    document.querySelectorAll('input[type=color][data-key], input.num[data-key]').forEach(function (el) { out[el.dataset.key] = el.value; });
    return out;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('pdSave'); btn.disabled = true;
    postAction('save', collectValues()).then(function (res) {
      if (res && res.success) msvToast(res.message || 'Gespeichert', 'success');
      else msvError((res && res.message) || 'Fehler beim Speichern');
    }).catch(function () { msvError('Netzwerkfehler beim Speichern'); }).finally(function () { btn.disabled = false; });
  });

  document.getElementById('pdReset').addEventListener('click', function () {
    msvConfirm('Alle PDF-Farben und Layout-Werte werden auf die Vorgabe „Hell &amp; minimal" zurückgesetzt.', 'Auf Standard zurücksetzen?', 'Ja, zurücksetzen').then(function (r) {
      if (!r.isConfirmed) return;
      postAction('reset').then(function (res) {
        if (res && res.success) { setAll(res.values || DEFAULTS); msvToast(res.message || 'Zurückgesetzt', 'success'); }
        else msvError((res && res.message) || 'Fehler');
      }).catch(function () { msvError('Netzwerkfehler'); });
    });
  });
})();
</script>

<?php include 'footer.inc.php'; ?>
