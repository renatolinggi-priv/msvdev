<?php
/* =============================================
 * FILE: /inc/pdf_design.php
 * PURPOSE: Zentrale PDF-Vorlage (Farben + Layout) bearbeiten — mit Live-Vorschau,
 *          1-Klick-Presets und sicherem Fallback auf Standardwerte (nur Admin).
 *          Speichert nach pdf_theme_settings; gelesen von inc/pdf/pdf_theme.php.
 *          Liegt in /inc/, damit der Menüeintrag (DB-Navigation) wie alle anderen
 *          Seiten ein blosser Dateiname ist: pdf_design.php
 * ============================================= */
require_once __DIR__ . '/session_config.inc.php';
require_once __DIR__ . '/dbconnect.inc.php';
require_once __DIR__ . '/remember_me.inc.php';
require_once __DIR__ . '/pdf/pdf_theme.php';

if (!isset($_SESSION['user_id'])) {
    restoreSessionFromToken();
}

// Login erforderlich
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . (file_exists('login.php') ? 'login.php' : '../login.php'));
    exit;
}

// Nur Admin
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin' || (int)($_SESSION['user_id'] ?? 0) === 1;
if (!$isAdmin) {
    http_response_code(403);
    die('Kein Zugriff');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$schema   = pdf_theme_schema();
$defaults = pdf_theme_defaults();
$presets  = pdf_theme_presets();

// ---- AJAX: Speichern / Zurücksetzen ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF-Token — bitte Seite neu laden.']);
        exit;
    }
    $action = $_POST['action'] ?? '';

    // ---- Logo ersetzen (zugeschnittenes Bild aus dem Cropper) ----
    if ($action === 'logo') {
        try {
            $dataUrl = $_POST['logo_data'] ?? '';
            if (!preg_match('#^data:image/(png|jpe?g);base64,#', $dataUrl)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ungültiges Bildformat']);
                exit;
            }
            $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
            if ($bin === false || strlen($bin) < 50) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Bild konnte nicht gelesen werden']);
                exit;
            }
            if (strlen($bin) > 4 * 1024 * 1024) {
                http_response_code(413);
                echo json_encode(['success' => false, 'message' => 'Bild zu gross (max. 4 MB)']);
                exit;
            }
            $info = @getimagesizefromstring($bin);
            if ($info === false || !in_array($info[2] ?? 0, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
                || $info[0] < 10 || $info[1] < 10 || $info[0] > 4000 || $info[1] > 4000) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Keine gültige PNG/JPEG-Datei']);
                exit;
            }

            // Auf JPEG mit weissem Hintergrund normalisieren (falls GD verfügbar)
            $jpeg = $bin;
            if (function_exists('imagecreatefromstring')) {
                $src = @imagecreatefromstring($bin);
                if ($src !== false) {
                    $w = imagesx($src);
                    $h = imagesy($src);
                    $maxW = 1200;
                    if ($w > $maxW) {
                        $nh = max(1, (int) round($h * $maxW / $w));
                        $rs  = imagecreatetruecolor($maxW, $nh);
                        imagefilledrectangle($rs, 0, 0, $maxW, $nh, imagecolorallocate($rs, 255, 255, 255));
                        imagecopyresampled($rs, $src, 0, 0, 0, 0, $maxW, $nh, $w, $h);
                        imagedestroy($src);
                        $src = $rs; $w = $maxW; $h = $nh;
                    }
                    $canvas = imagecreatetruecolor($w, $h);
                    imagefilledrectangle($canvas, 0, 0, $w, $h, imagecolorallocate($canvas, 255, 255, 255));
                    imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
                    ob_start();
                    imagejpeg($canvas, null, 92);
                    $out = ob_get_clean();
                    if ($out !== false && $out !== '') { $jpeg = $out; }
                    imagedestroy($src);
                    imagedestroy($canvas);
                }
            }

            // Master + alle vorhandenen Modul-Kopien synchron überschreiben
            $root    = dirname(__DIR__); // Projekt-Root
            $targets = array_merge(
                [$root . '/images/MSVWilen_Logo.jpg'],
                glob($root . '/inc/*/dat/MSVWilen_Logo.jpg') ?: []
            );
            $ok = 0; $fail = 0;
            foreach ($targets as $t) {
                if ((is_file($t) && is_writable($t)) || (!is_file($t) && is_writable(dirname($t)))) {
                    if (@file_put_contents($t, $jpeg) !== false) { $ok++; continue; }
                }
                $fail++;
            }
            if ($ok === 0) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Logo konnte nicht gespeichert werden (Schreibrechte?)']);
                exit;
            }
            echo json_encode([
                'success' => true,
                'message' => 'Logo aktualisiert (' . $ok . ' Dateien' . ($fail ? ', ' . $fail . ' übersprungen' : '') . ')',
                'count'   => $ok,
            ]);
            exit;
        } catch (\Throwable $e) {
            error_log('pdf_design logo: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Fehler beim Logo-Upload']);
            exit;
        }
    }

    try {
        $pdo = getDB();
        pdf_theme_ensure_table($pdo);

        if ($action === 'reset') {
            $pdo->exec('DELETE FROM pdf_theme_settings');
            echo json_encode(['success' => true, 'message' => 'Auf Standard zurückgesetzt', 'values' => $defaults]);
            exit;
        }

        if ($action === 'save') {
            $clean = [];
            foreach ($schema as $key => $def) {
                $clean[$key] = pdf_theme_sanitize_value($_POST[$key] ?? null, $def);
            }
            $stmt = $pdo->prepare('INSERT INTO pdf_theme_settings (skey, svalue) VALUES (?, ?)
                                   ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
            foreach ($clean as $key => $val) {
                $stmt->execute([$key, (string)$val]);
            }
            echo json_encode(['success' => true, 'message' => 'PDF-Design gespeichert', 'values' => $clean]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
        exit;
    } catch (\Throwable $e) {
        error_log('pdf_design save: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern']);
        exit;
    }
}

// Aktuelle (validierte) Werte für die Anzeige
try { pdf_theme_ensure_table(getDB()); } catch (\Throwable $e) { /* selbstheilend, ignorieren */ }
$current = pdf_theme_palette();

// Mapping Theme-Key -> CSS-Variable in der Vorschau
$varMap = [
    'text' => '--p-text', 'muted' => '--p-muted', 'accent' => '--p-accent',
    'head_bg' => '--p-headbg', 'head_text' => '--p-headtext', 'head_line' => '--p-headline',
    'border' => '--p-border', 'zebra' => '--p-zebra', 'total_bg' => '--p-totalbg',
    'gold_bg' => '--p-goldbg', 'gold_tx' => '--p-goldtx', 'silver_bg' => '--p-silverbg',
    'silver_tx' => '--p-silvertx', 'bronze_bg' => '--p-bronzebg', 'bronze_tx' => '--p-bronzetx',
    'win' => '--p-win', 'struck' => '--p-struck',
];

// Inline-Style-String für die Vorschau aus aktuellen Werten
$previewVars = '';
foreach ($varMap as $key => $var) {
    $previewVars .= $var . ':' . $current[$key] . ';';
}
$previewVars .= '--p-font:' . $current['base_font'] . 'px;';
$previewVars .= '--p-logow:' . $current['logo_width'] . 'px;';
$previewVars .= '--p-bw:' . $current['border_width'] . 'px;';

// Reihenfolge der Gruppen
$groupsOrder = ['Allgemein', 'Tabelle', 'Medaillen', 'Status', 'Layout'];

$page_specific_css = <<<'CSS'
.main-content-wrapper { max-width: 1160px; }
.pd-wrap { display: grid; grid-template-columns: 440px minmax(0, 1fr); gap: 24px; align-items: start; }
@media (max-width: 991.98px) { .main-content-wrapper { max-width: none; } .pd-wrap { grid-template-columns: 1fr; } }

.pd-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px 18px; }
.pd-card h3 { font-size:.78rem; text-transform:uppercase; letter-spacing:.5px; color:#64748b; font-weight:700; margin:0 0 10px; }
.pd-card + .pd-card { margin-top:14px; }

/* Einklappbare Cards */
.pd-collapsible h3.pd-toggle { display:flex; align-items:center; justify-content:space-between; cursor:pointer; margin:0; user-select:none; }
.pd-collapsible .pd-chevron { font-size:.8rem; color:#94a3b8; transition:transform .2s; }
.pd-collapsible.collapsed h3.pd-toggle { margin:0; }
.pd-collapsible:not(.collapsed) h3.pd-toggle { margin-bottom:10px; }
.pd-collapsible.collapsed .pd-chevron { transform:rotate(-90deg); }
.pd-collapsible.collapsed .pd-card-body { display:none; }

.pd-field { display:flex; align-items:center; gap:10px; padding:5px 0; }
.pd-field label { flex:1; font-size:.86rem; color:#2d3748; }
.pd-field input[type=color] { width:42px; height:30px; border:1px solid #cbd5e0; border-radius:6px; padding:0; background:#fff; cursor:pointer; }
.pd-field input.hex { width:92px; font-family:ui-monospace,Consolas,monospace; font-size:.8rem; text-transform:lowercase; }
.pd-field input.num { width:92px; }

.pd-presets { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:6px; }
.pd-actions { display:flex; gap:8px; margin-top:18px; flex-wrap:wrap; }

/* Vorschau */
.pd-preview-sticky { position: sticky; top: 16px; }
#pdfPreview {
  --p-text:#2d3748; --p-muted:#64748b; --p-accent:#3b5998;
  --p-headbg:#eef2f7; --p-headtext:#2d3748; --p-headline:#cbd5e0;
  --p-border:#e2e8f0; --p-zebra:#f8fafc; --p-totalbg:#f1f5f9;
  --p-goldbg:#fdf6e3; --p-goldtx:#8a6d1c; --p-silverbg:#f1f1f1; --p-silvertx:#6b7280;
  --p-bronzebg:#f7ede2; --p-bronzetx:#9c6b3f; --p-win:#2f855a; --p-struck:#c0392b;
  --p-font:9px; --p-logow:120px; --p-bw:1px;
  background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:18px; color:var(--p-text);
  font-family: Arial, sans-serif;
}
#pdfPreview .pv-logo { width:var(--p-logow); max-width:100%; height:auto; display:block; }
#pdfPreview h4 { text-align:center; color:var(--p-accent); margin:10px 0 14px; font-size:1.05rem; }
#pdfPreview table { width:100%; border-collapse:collapse; font-size:calc(var(--p-font) + 3px); background:#fff; }
#pdfPreview th, #pdfPreview td { border:var(--p-bw) solid var(--p-border); padding:5px 8px; text-align:left; }
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
      <div class="main-content-wrapper">

        <div class="row mb-3">
          <div class="col-md-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0 page-title">PDF-Design
            </h2>
          </div>
        </div>

        <form id="pdfDesignForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

          <div class="pd-wrap">
            <!-- LINKS: Einstellungen -->
            <div>
              <div class="pd-card">
                <h3>Logo</h3>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  <img id="logoPreview" src="../images/MSVWilen_Logo.jpg?t=<?= time() ?>" alt="Aktuelles Logo"
                       style="max-height:64px;max-width:160px;border:1px solid #e2e8f0;border-radius:6px;padding:4px;background:#fff;">
                  <div style="flex:1;min-width:200px;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="logoChooseBtn"><i class="bi bi-upload me-1"></i>Logo ersetzen</button>
                    <input type="file" id="logoFile" accept="image/png,image/jpeg" hidden>
                    <div class="text-muted small mt-1">Beim Ersetzen wählst du den Bildausschnitt (wie beim Profilbild). Die Anzeigegrösse im PDF stellst du unten unter <strong>Layout → Logo-Breite</strong> ein.</div>
                  </div>
                </div>
              </div>

              <div class="pd-card pd-group">
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

              <?php foreach ($groupsOrder as $group): ?>
                <div class="pd-card pd-collapsible collapsed">
                  <h3 class="pd-toggle"><span><?= htmlspecialchars($group) ?></span><i class="bi bi-chevron-down pd-chevron"></i></h3>
                  <div class="pd-card-body">
                    <?php foreach ($schema as $key => $def): if (($def['group'] ?? '') !== $group) continue; ?>
                      <div class="pd-field">
                        <label for="f_<?= $key ?>"><?= htmlspecialchars($def['label']) ?></label>
                        <?php if ($def['type'] === 'color'): ?>
                          <input type="text" class="form-control form-control-sm hex" data-key="<?= $key ?>" value="<?= htmlspecialchars($current[$key]) ?>" maxlength="7" spellcheck="false">
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
                <button type="submit" class="btn btn-outline-primary btn-sm" id="pdSave">
                  <i class="bi bi-save me-1"></i>Speichern
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="pdReset">
                  <i class="bi bi-arrow-counterclockwise me-1"></i>Auf Standard zurücksetzen
                </button>
              </div>
            </div>

            <!-- RECHTS: Live-Vorschau -->
            <div class="pd-preview-sticky">
              <div class="pd-card">
                <h3>Live-Vorschau</h3>
                <div id="pdfPreview" style="<?= htmlspecialchars($previewVars, ENT_QUOTES, 'UTF-8') ?>">
                  <img id="pvLogo" class="pv-logo" src="../images/MSVWilen_Logo.jpg?t=<?= time() ?>" alt="Logo">
                  <h4>Jahresmeisterschaft 2026</h4>
                  <table>
                    <thead><tr><th style="width:42px">Rang</th><th>Name</th><th style="text-align:center">Endstich</th><th style="text-align:center">Kanti</th><th style="width:64px">Total</th></tr></thead>
                    <tbody>
                      <tr class="r1"><td>1</td><td>Muster Hans</td><td style="text-align:center">96.00</td><td style="text-align:center">94.50</td><td>190.50</td></tr>
                      <tr class="r2"><td>2</td><td>Beispiel Anna</td><td style="text-align:center">93.00</td><td style="text-align:center">95.00</td><td>188.00</td></tr>
                      <tr class="r3"><td>3</td><td>Schütze Peter</td><td style="text-align:center">91.50</td><td style="text-align:center">92.00</td><td>183.50</td></tr>
                      <tr><td>4</td><td>Tell Wilhelm</td><td style="text-align:center"><span class="pv-struck">88.00</span></td><td style="text-align:center">90.00</td><td>178.00</td></tr>
                    </tbody>
                  </table>
                  <div class="pv-badges">
                    <span>Cup:</span> <span class="pv-win">Muster Hans</span> <span>vs</span> <span class="pv-lose">Tell Wilhelm</span>
                    <span class="ms-auto"></span>
                    <span class="pv-badge pv-b1">1.</span><span class="pv-badge pv-b2">2.</span><span class="pv-badge pv-b3">3.</span>
                  </div>
                  <div class="pv-foot">Erstellt am 22.06.2026 · MSV Wilen · Seite 1 von 1</div>
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
<div class="modal fade" id="logoCropModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-crop me-2"></i>Logo zuschneiden</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <div style="max-height:60vh;overflow:hidden;">
          <img id="cropImg" style="max-width:100%;display:block;">
        </div>
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
// Logo ersetzen + zuschneiden (Cropper.js)
(function () {
  var fileInput = document.getElementById('logoFile');
  var chooseBtn = document.getElementById('logoChooseBtn');
  var cropImg   = document.getElementById('cropImg');
  var modalEl   = document.getElementById('logoCropModal');
  var saveBtn   = document.getElementById('logoCropSave');
  var preview   = document.getElementById('logoPreview');
  var csrf      = document.querySelector('#pdfDesignForm [name=csrf_token]').value;
  var cropper   = null;
  var bsModal   = (typeof bootstrap !== 'undefined' && bootstrap.Modal) ? new bootstrap.Modal(modalEl) : null;

  chooseBtn.addEventListener('click', function () { fileInput.click(); });

  fileInput.addEventListener('change', function () {
    var file = this.files && this.files[0];
    if (!file) return;
    if (!/^image\/(png|jpe?g)$/.test(file.type)) { msvError('Bitte eine PNG- oder JPEG-Datei wählen.'); this.value = ''; return; }
    if (file.size > 6 * 1024 * 1024) { msvError('Datei zu gross (max. 6 MB).'); this.value = ''; return; }
    var reader = new FileReader();
    reader.onload = function (e) {
      cropImg.src = e.target.result;
      if (bsModal) { bsModal.show(); }
    };
    reader.readAsDataURL(file);
    this.value = '';
  });

  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', function () {
      if (typeof Cropper === 'undefined') { msvError('Zuschnitt-Bibliothek konnte nicht geladen werden.'); return; }
      if (cropper) { cropper.destroy(); }
      cropper = new Cropper(cropImg, { viewMode: 1, autoCropArea: 1, background: false, movable: true, zoomable: true });
    });
    modalEl.addEventListener('hidden.bs.modal', function () {
      if (cropper) { cropper.destroy(); cropper = null; }
    });
  }

  saveBtn.addEventListener('click', function () {
    if (!cropper) return;
    var canvas = cropper.getCroppedCanvas({ maxWidth: 1200, maxHeight: 1200, fillColor: '#fff' });
    if (!canvas) { msvError('Zuschnitt fehlgeschlagen.'); return; }
    var dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    var fd = new FormData();
    fd.set('csrf_token', csrf);
    fd.set('action', 'logo');
    fd.set('logo_data', dataUrl);
    saveBtn.disabled = true;
    fetch(window.location.pathname, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        saveBtn.disabled = false;
        if (res && res.success) {
          if (bsModal) { bsModal.hide(); }
          var bust = '../images/MSVWilen_Logo.jpg?t=' + Date.now();
          preview.src = bust;
          var pv = document.getElementById('pvLogo');
          if (pv) { pv.src = bust; }
          msvToast(res.message || 'Logo aktualisiert', 'success');
        } else {
          msvError((res && res.message) || 'Fehler beim Speichern des Logos');
        }
      })
      .catch(function () { saveBtn.disabled = false; msvError('Netzwerkfehler beim Logo-Upload'); });
  });
})();
</script>
<script>
(function () {
  var VARMAP = <?= json_encode($varMap) ?>;
  var PRESETS = <?= json_encode(array_map(function ($p) { return $p['values']; }, $presets)) ?>;
  var DEFAULTS = <?= json_encode($defaults) ?>;
  var preview = document.getElementById('pdfPreview');
  var form = document.getElementById('pdfDesignForm');

  function isColorKey(k) { return VARMAP.hasOwnProperty(k); }

  function applyToPreview(key, value) {
    if (isColorKey(key)) {
      preview.style.setProperty(VARMAP[key], value);
    } else if (key === 'base_font') {
      preview.style.setProperty('--p-font', parseInt(value, 10) + 'px');
    } else if (key === 'logo_width') {
      preview.style.setProperty('--p-logow', parseInt(value, 10) + 'px');
    } else if (key === 'border_width') {
      preview.style.setProperty('--p-bw', parseInt(value, 10) + 'px');
    }
  }

  // Farb-Picker <-> Hex-Textfeld synchron halten + Vorschau aktualisieren
  function syncColor(key, value) {
    if (!/^#[0-9a-fA-F]{6}$/.test(value)) return;
    value = value.toLowerCase();
    document.querySelectorAll('[data-key="' + key + '"]').forEach(function (el) {
      if (el.value.toLowerCase() !== value) el.value = value;
    });
    applyToPreview(key, value);
  }

  document.querySelectorAll('input[type=color][data-key]').forEach(function (el) {
    ['input', 'change'].forEach(function (evt) {
      el.addEventListener(evt, function () { syncColor(this.dataset.key, this.value); });
    });
  });
  document.querySelectorAll('input.hex[data-key]').forEach(function (el) {
    ['input', 'change'].forEach(function (evt) {
      el.addEventListener(evt, function () { syncColor(this.dataset.key, this.value.trim()); });
    });
  });
  document.querySelectorAll('input.num[data-key]').forEach(function (el) {
    ['input', 'change'].forEach(function (evt) {
      el.addEventListener(evt, function () { applyToPreview(this.dataset.key, this.value); });
    });
  });

  // Einklappbare Cards (Kopfzeile klicken)
  document.querySelectorAll('.pd-collapsible h3.pd-toggle').forEach(function (h) {
    h.addEventListener('click', function () {
      this.closest('.pd-collapsible').classList.toggle('collapsed');
    });
  });

  // Presets
  document.querySelectorAll('.pd-preset').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var vals = PRESETS[this.dataset.preset];
      if (!vals) return;
      Object.keys(vals).forEach(function (key) {
        var v = vals[key];
        document.querySelectorAll('[data-key="' + key + '"]').forEach(function (el) { el.value = v; });
        applyToPreview(key, v);
      });
    });
  });

  function setAll(vals) {
    Object.keys(vals).forEach(function (key) {
      var v = vals[key];
      document.querySelectorAll('[data-key="' + key + '"]').forEach(function (el) { el.value = v; });
      applyToPreview(key, v);
    });
  }

  function collectValues() {
    var out = {};
    document.querySelectorAll('input[type=color][data-key]').forEach(function (el) { out[el.dataset.key] = el.value; });
    document.querySelectorAll('input.num[data-key]').forEach(function (el) { out[el.dataset.key] = el.value; });
    return out;
  }

  function post(action) {
    var fd = new FormData();
    fd.set('csrf_token', form.querySelector('[name=csrf_token]').value);
    fd.set('action', action);
    if (action === 'save') {
      var vals = collectValues();
      Object.keys(vals).forEach(function (k) { fd.set(k, vals[k]); });
    }
    return fetch(window.location.pathname, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    post('save').then(function (res) {
      if (res && res.success) { msvToast(res.message || 'Gespeichert', 'success'); }
      else { msvError((res && res.message) || 'Fehler beim Speichern'); }
    }).catch(function () { msvError('Netzwerkfehler beim Speichern'); });
  });

  document.getElementById('pdReset').addEventListener('click', function () {
    msvConfirm('Alle PDF-Farben und Layout-Werte werden auf die Vorgabe „Hell &amp; minimal“ zurückgesetzt.', 'Auf Standard zurücksetzen?', 'Ja, zurücksetzen').then(function (r) {
      if (!r.isConfirmed) return;
      post('reset').then(function (res) {
        if (res && res.success) { setAll(res.values || DEFAULTS); msvToast(res.message || 'Zurückgesetzt', 'success'); }
        else { msvError((res && res.message) || 'Fehler'); }
      }).catch(function () { msvError('Netzwerkfehler'); });
    });
  });
})();
</script>

<?php include 'footer.inc.php'; ?>
