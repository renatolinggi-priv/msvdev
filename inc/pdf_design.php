<?php
/* =============================================
 * FILE: /inc/pdf_design.php
 * PURPOSE: Zentrale PDF-Vorlage bearbeiten (nur Admin). Zeigt nur, was in den PDFs sichtbar wirkt:
 *          Logo und «Listen und Ranglisten» (pdf_rangliste_css; seit 1.68.48 alle PDFs ausser Standblatt)
 *          — mit Live-Vorschau und sicherem Fallback auf Standardwerte.
 *          Speichert nach pdf_theme_settings (Endpoint inc/pdf_design/save.php, nur mitgeschickte Felder);
 *          gelesen von inc/pdf/pdf_theme.php. Die übrigen Schema-Werte wirken in keinem PDF mehr und
 *          stehen deshalb nicht auf der Seite (Entscheid 03.11.2026).
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
$current  = pdf_theme_palette();

// Logo-URL mit Cache-Busting per Dateizeit (Konvention: filemtime, nie time())
$logoMtime = (int)@filemtime(pdf_logo_path());
$logoUrl   = '../images/MSVWilen_Logo.jpg?v=' . ($logoMtime ?: 1);

// Felder einer Schema-Gruppe (auf der Seite nur «Listen»)
$keysVon = static fn(string $g) => array_keys(array_filter($schema, static fn($d) => ($d['group'] ?? '') === $g));

// Mapping Theme-Key -> CSS-Variable der Vorschau
$varMap = [
    'rl_titel' => '--l-titel', 'rl_linie' => '--l-linie', 'rl_zebra' => '--l-zebra', 'rl_streich' => '--l-streich',
    'muted' => '--p-muted',
];
$previewVars = '';
foreach ($varMap as $key => $var) {
    $previewVars .= $var . ':' . $current[$key] . ';';
}
$previewVars .= '--l-logoh:' . $current['rl_logo_h'] . 'mm;';

// Ein Feld der Einstellungen (Farbe: Hex + Wähler, Zahl: Eingabefeld)
$feld = static function (string $key, array $def) use ($current): string {
    $label = htmlspecialchars($def['label']);
    $out = '<div class="pd-field"><label for="f_' . $key . '">' . $label . '</label>';
    if ($def['type'] === 'color') {
        $wert = htmlspecialchars((string)$current[$key]);
        $out .= '<input type="text" class="form-control form-control-sm hex" id="h_' . $key . '" data-key="' . $key . '" value="' . $wert
              . '" maxlength="7" spellcheck="false" aria-label="' . $label . ' (Hex)">'
              . '<input type="color" id="f_' . $key . '" data-key="' . $key . '" value="' . $wert . '">';
    } else {
        $out .= '<input type="number" class="form-control form-control-sm num" id="f_' . $key . '" data-key="' . $key . '" value="'
              . (int)$current[$key] . '" min="' . (int)$def['min'] . '" max="' . (int)$def['max'] . '" step="1">';
    }
    return $out . '</div>';
};

$page_specific_css = <<<'CSS'
.pd-wrap { display: grid; grid-template-columns: 440px minmax(0, 1fr); gap: 24px; align-items: start; }
.pd-card { background:#fff; border:1px solid var(--ui-rand); border-radius:10px; padding:16px 18px; }
.pd-card + .pd-card { margin-top:14px; }
/* Sektionstitel: gleiche Optik wie der zentrale Tabellentitel (.table-title), kompakt */
.pd-card > h3 { font-size:.95rem; font-weight:600; color:var(--ui-text); margin:0 0 10px; }
.pd-field { display:flex; align-items:center; gap:10px; padding:5px 0; }
.pd-field label { flex:1; font-size:.86rem; color:var(--ui-text); }
.pd-field input[type=color] { width:42px; height:30px; border:1px solid #cbd5e0; border-radius:6px; padding:0; background:#fff; cursor:pointer; }
.pd-field input.hex { width:92px; font-family:ui-monospace,Consolas,monospace; font-size:.8rem; text-transform:lowercase; }
.pd-field input.num { width:92px; }
.pd-actions { display:flex; gap:8px; margin-top:18px; flex-wrap:wrap; }
.pd-logo-box { max-height:64px; max-width:160px; border:1px solid var(--ui-rand); border-radius:6px; padding:4px; background:#fff; }
.pd-logo-text { flex:1; min-width:200px; }
.pd-crop-wrap { max-height:60vh; overflow:hidden; }
.pd-crop-wrap img { max-width:100%; display:block; }
/* Vorschau: Werte kommen ausschliesslich aus dem Inline-Style am Wrapper (PHP) */
.pd-preview-sticky { position: sticky; top: 16px; }
@media (max-width: 991.98px) { .pd-wrap { grid-template-columns: 1fr; } .pd-preview-sticky { position: static; } }
/* Vorschau Listen und Ranglisten (pdf_rangliste_css): schwarz auf weiss, Werte aus --l-* und --p-muted */
#listenPreview { background:#fff; border:1px solid var(--ui-rand); border-radius:8px; padding:18px; color:#000; font-family: Helvetica, Arial, sans-serif; font-size:12px; }
#listenPreview .lv-kopf { display:flex; align-items:center; gap:14px; margin-bottom:14px; }
#listenPreview .lv-logo { height:var(--l-logoh); width:auto; display:block; }
#listenPreview .lv-titel { font-size:1.35rem; font-weight:700; line-height:1.1; color:var(--l-titel); }
#listenPreview .lv-unter { font-size:.78rem; color:var(--p-muted); margin-top:3px; }
#listenPreview .lv-abschnitt { font-size:.95rem; font-weight:700; color:var(--l-titel); margin:0 0 4px; }
#listenPreview table { width:100%; border-collapse:collapse; }
#listenPreview th { text-align:left; font-weight:700; border-bottom:1px solid var(--l-linie); padding:4px 6px; }
#listenPreview td { padding:4px 6px; }
#listenPreview .r { text-align:right; }
#listenPreview tbody tr:nth-child(even) td { background:var(--l-zebra); }
#listenPreview tr.b td { font-weight:700; }
#listenPreview tr.lv-total td { font-weight:700; border-top:1px solid var(--l-linie); background:none; }
#listenPreview .lv-streich { background:var(--l-streich); text-decoration:line-through; padding:0 2px; }
#listenPreview .lv-hinweis { font-size:.7rem; color:var(--p-muted); margin-top:6px; }
#listenPreview .lv-fuss { display:flex; justify-content:space-between; gap:10px; margin-top:16px; font-size:.65rem; color:var(--p-muted); }
CSS;

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-default">

        <?php $page_title = 'PDF-Vorlage'; $page_actions = '<button type="button" class="btn-help" data-help="pdf_design.uebersicht" aria-label="Hilfe"></button>'; include 'partials/page_header.inc.php'; ?>

        <form id="pdfDesignForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

          <div class="pd-wrap">
            <!-- LINKS: Einstellungen -->
            <div>
              <div class="pd-card">
                <h3>Logo <button type="button" class="btn-help" data-help="pdf_design.logo" aria-label="Hilfe"></button></h3>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  <img id="logoPreview" class="pd-logo-box" src="<?= htmlspecialchars($logoUrl) ?>" alt="Aktuelles Logo">
                  <div class="pd-logo-text">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="logoChooseBtn"><i class="bi bi-upload me-1"></i>Logo ersetzen</button>
                    <input type="file" id="logoFile" accept="image/png,image/jpeg" hidden>
                    <div class="text-muted small mt-1">Beim Ersetzen wählst du den Bildausschnitt. Die Höhe im PDF stellst du unter <strong>Listen und Ranglisten → Logo-Höhe</strong> ein. Das bisherige Logo wird als Sicherung <code>MSVWilen_Logo.bak.jpg</code> behalten.</div>
                  </div>
                </div>
              </div>

              <!-- Listen und Ranglisten: gilt für fast alle PDFs (Ranglisten-Stil) -->
              <div class="pd-card">
                <h3>Listen und Ranglisten <button type="button" class="btn-help" data-help="pdf_design.listen" aria-label="Hilfe"></button></h3>
                <div class="text-muted small mb-2">Gilt für alle Ranglisten, Listen und Abrechnungen im Stil des Absendenbuchs.</div>
                <?php foreach ($keysVon('Listen') as $key) { echo $feld($key, $schema[$key]); } ?>
              </div>

              <div class="pd-actions">
                <button type="submit" class="btn btn-outline-primary btn-sm" id="pdSave"><i class="bi bi-save me-1"></i>Speichern</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="pdReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Auf Standard zurücksetzen</button>
              </div>
            </div>

            <!-- RECHTS: Live-Vorschau (Variablen am gemeinsamen Wrapper, beide Vorschauen erben sie) -->
            <div class="pd-preview-sticky" id="pdVorschau" style="<?= htmlspecialchars($previewVars, ENT_QUOTES, 'UTF-8') ?>">
              <div class="pd-card">
                <h3>Vorschau Listen und Ranglisten <button type="button" class="btn-help" data-help="pdf_design.vorschau" aria-label="Hilfe"></button></h3>
                <div id="listenPreview">
                  <div class="lv-kopf">
                    <img class="lv-logo" id="lvLogo" src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo">
                    <div><div class="lv-titel">Jahresmeisterschaft <?= date('Y') ?></div><div class="lv-unter">Rangliste · Beispieldaten</div></div>
                  </div>
                  <div class="lv-abschnitt">Kategorie A</div>
                  <table>
                    <thead><tr><th class="r" style="width:42px">Rang</th><th>Name</th><th class="r">Endstich</th><th class="r">Kanti</th><th class="r">Total</th></tr></thead>
                    <tbody>
                      <tr class="b"><td class="r">1.</td><td>Muster Hans</td><td class="r">96.00</td><td class="r">94.50</td><td class="r">190.50</td></tr>
                      <tr class="b"><td class="r">2.</td><td>Beispiel Anna</td><td class="r">93.00</td><td class="r">95.00</td><td class="r">188.00</td></tr>
                      <tr class="b"><td class="r">3.</td><td>Schütze Peter</td><td class="r">91.50</td><td class="r">92.00</td><td class="r">183.50</td></tr>
                      <tr><td class="r">4.</td><td>Tell Wilhelm</td><td class="r"><span class="lv-streich">88.00</span></td><td class="r">90.00</td><td class="r">178.00</td></tr>
                      <tr class="lv-total"><td></td><td>Total</td><td class="r">368.50</td><td class="r">371.50</td><td class="r">740.00</td></tr>
                    </tbody>
                  </table>
                  <div class="lv-hinweis"><span class="lv-streich">88.00</span> = gestrichenes Resultat</div>
                  <div class="lv-fuss"><span>Militärschützenverein Wilen · Jahresmeisterschaft <?= date('Y') ?> · Stand <?= date('d.m.Y') ?></span><span>Seite 1 von 1</span></div>
                </div>
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
  var DEFAULTS = <?= json_encode($defaults) ?>;
  var LOGO_URL = <?= json_encode('../images/MSVWilen_Logo.jpg') ?>;
  var form     = document.getElementById('pdfDesignForm');
  var preview  = document.getElementById('pdVorschau');   // Wrapper beider Vorschauen (CSS-Variablen)
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
          document.getElementById('lvLogo').src = src;
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
    else if (key === 'rl_logo_h') preview.style.setProperty('--l-logoh', parseInt(value, 10) + 'mm');
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

  // Nur die Felder auf der Seite speichern (save.php schreibt nur mitgeschickte Werte)
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
    msvConfirm('Alle PDF-Farben und die Logo-Höhe werden auf die Vorgabe zurückgesetzt: wieder schwarz auf weiss wie das Absendenbuch. Das Logo selbst bleibt.', 'Auf Standard zurücksetzen?', 'Ja, zurücksetzen').then(function (r) {
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
