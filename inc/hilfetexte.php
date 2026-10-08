<?php
/**
 * inc/hilfetexte.php – Hilfetexte verwalten (Hilfesystem, Migration 066).
 *
 * Pflegt die Tabelle hilfetexte: Schlüssel, Titel, Inhalt (sicheres HTML), Kategorie.
 * Die Einträge erscheinen überall im Admin-Bereich, wo ein
 *   <button type="button" class="btn-help" data-help="schluessel" aria-label="Hilfe"></button>
 * steht (Anzeige: inc/js/msv-help.js). Bearbeiten dürfen nur Administratoren, der Vorstand
 * sieht die Liste lesend. Logik: inc/hilfetexte/hilfetexte.js, Endpunkt inc/hilfetexte/api.php.
 */
include 'dbconnect.inc.php';
require_once __DIR__ . '/partials/empty_state.inc.php';   // msv_empty_row()

$page_specific_css = <<<'CSS'
/* ===== Hilfetexte – Editor ===== */
.hilfe-key   { font-family: 'SF Mono', 'Fira Code', Consolas, monospace; font-size: 0.8rem; color: #1e40af; }
.hilfe-cat   { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 0.72rem;
               background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.hilfe-empty { color: #64748b; }
.hilfe-date  { color: #64748b; font-size: 0.78rem; white-space: nowrap; }
.usage-yes   { color: #15803d; font-size: 0.78rem; margin-right: 0.35rem; }
.usage-no    { color: #b45309; font-size: 0.78rem; }
.usage-files { color: #64748b; font-size: 0.72rem; word-break: break-all; }

/* Scan-Banner */
.scan-banner { display: flex; gap: 1rem; align-items: flex-start; padding: 0.75rem 1rem;
               border-radius: 8px; border: 1px solid; margin: 0.75rem 0 0; font-size: 0.85rem; }
.scan-banner.scan-ok   { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.scan-banner.scan-warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.scan-banner.scan-bad  { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.scan-banner-title { font-weight: 600; }
.scan-stats { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.scan-stat-pill { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 0.75rem;
                  background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.08); }
.scan-keylist { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.3rem; }
.scan-key { display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 12px;
            background: #fff; border: 1px solid #cbd5e1; color: #1e293b; cursor: pointer;
            font-family: Consolas, monospace; font-size: 0.75rem; }
.scan-key:hover { background: #1e293b; color: #fff; border-color: #1e293b; }

/* Format-Toolbar über der Textarea */
.editor-toolbar { display: flex; flex-wrap: wrap; gap: 2px; padding: 4px; margin-bottom: -1px;
                  background: #f8fafc; border: 1px solid #ced4da; border-bottom: 0; border-radius: 6px 6px 0 0; }
.editor-toolbar .ed-btn { border: 1px solid transparent; background: transparent; width: 28px; height: 26px;
                          border-radius: 4px; font-size: 0.85rem; color: #334155; display: inline-flex;
                          align-items: center; justify-content: center; font-weight: 600; }
.editor-toolbar .ed-btn:hover { background: #e2e8f0; border-color: #cbd5e1; }
.editor-toolbar .ed-sep { width: 1px; align-self: stretch; background: #cbd5e1; margin: 3px 4px; }
#sgInhalt { border-radius: 0 0 6px 6px; font-family: Consolas, 'SF Mono', monospace; font-size: 0.82rem; }
.tag-hint { font-size: 0.72rem; color: #64748b; margin-top: 0.3rem; }
.tag-hint code { font-size: 0.7rem; }
#sgPreview { font-size: 0.88rem; line-height: 1.5; }
#sgPreview :first-child { margin-top: 0; }
#sgPreview :last-child  { margin-bottom: 0; }

/* Link-Dialog über dem Slide-Panel (Panel 1060, Help-Modal 1080) */
.link-dialog-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.45); z-index: 1090;
                       opacity: 0; visibility: hidden; transition: opacity 0.18s; }
.link-dialog-overlay.active { opacity: 1; visibility: visible; }
.link-dialog { position: fixed; top: 50%; left: 50%; transform: translate(-50%,-48%) scale(0.97);
               width: min(480px, 92vw); max-height: 85vh; background: #fff; border-radius: 12px;
               box-shadow: 0 25px 60px rgba(0,0,0,0.3); z-index: 1100; opacity: 0; visibility: hidden;
               transition: opacity 0.18s, transform 0.18s, visibility 0.18s; display: flex; flex-direction: column; overflow: hidden; }
.link-dialog.open { opacity: 1; visibility: visible; transform: translate(-50%,-50%) scale(1); }
.link-dialog-header { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem;
                      background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.link-dialog-header h6 { margin: 0; font-weight: 600; font-size: 0.9rem; }
.link-dialog-body { padding: 0.9rem 1rem; overflow-y: auto; }
.link-dialog-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 0.65rem 1rem;
                      border-top: 1px solid #e2e8f0; background: #f8fafc; }
.link-suggestions { display: flex; flex-wrap: wrap; gap: 0.3rem; padding: 0.5rem; max-height: 180px; overflow-y: auto;
                    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }
.link-suggestions .link-sug { display: inline-flex; align-items: center; gap: 0.3rem; padding: 3px 9px; font-size: 0.78rem;
                              background: #fff; color: #334155; border: 1px solid #cbd5e1; border-radius: 14px; cursor: pointer; }
.link-suggestions .link-sug:hover { background: #1e293b; color: #fff; border-color: #1e293b; }
.link-suggestions .link-sug-link { font-family: Consolas, monospace; font-size: 0.7rem; opacity: 0.7; }
.link-suggestions .hilfe-empty { font-size: 0.8rem; padding: 0.3rem; }
CSS;

include 'header.inc.php';

$kannBearbeiten = (($_SESSION['user_role'] ?? '') === 'admin');

// Vorschläge für den Link-Dialog: alle Seiten aus der Admin-Navigation.
$navPages = [];
try {
    $navPages = getDB()->query("SELECT Text AS text, Link AS link FROM navigation
                                WHERE Link <> '' AND Link <> '#' AND IstTrennlinie = 0
                                ORDER BY Text")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $navPages = [];
}

// Seitenkopf-Aktionen
ob_start(); ?>
  <input type="search" class="form-control form-control-sm" id="hilfeSearch" style="width:240px"
         placeholder="Schlüssel, Titel oder Kategorie …" aria-label="Hilfetexte durchsuchen">
  <?php if ($kannBearbeiten): ?>
  <button type="button" class="btn btn-outline-secondary btn-sm" id="btnScanHilfe"
          data-tooltip="Durchsucht den Code nach data-help-Verweisen und vergleicht mit der Liste">
    <i class="bi bi-search me-1"></i>Code-Scan
  </button>
  <button type="button" class="btn btn-outline-success btn-sm" id="btnAddHilfe">
    <i class="bi bi-plus-lg me-1"></i>Neuer Hilfetext
  </button>
  <?php endif; ?>
  <button type="button" class="btn-help" data-help="hilfetexte.uebersicht" aria-label="Hilfe"></button>
<?php
$page_actions     = ob_get_clean();
$page_show_mobile = true;
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-default">
        <?php $page_title = 'Hilfetexte'; include 'partials/page_header.inc.php'; ?>

        <?php if (!$kannBearbeiten): ?>
        <div class="alert alert-light border small py-2 mb-3"><i class="bi bi-lock me-2"></i>Hilfetexte bearbeiten können nur Administratoren. Die Liste ist hier zur Einsicht.</div>
        <?php endif; ?>

        <div id="scanBanner" class="mb-3"></div>

        <div class="table-wrapper">
          <h5 class="table-title"><span><i class="bi bi-question-circle me-2"></i>Hilfetexte</span><span class="badge bg-secondary" id="hilfeCount">0</span></h5>
          <div class="table-responsive">
            <table class="hybrid-table" id="hilfeTable">
              <thead>
                <tr>
                  <th style="width:26%">Schlüssel</th>
                  <th>Titel</th>
                  <th style="width:14%">Kategorie</th>
                  <th style="width:22%" id="thUsage" class="d-none">Verwendung</th>
                  <th style="width:130px">Geändert</th>
                </tr>
              </thead>
              <tbody>
                <?= msv_empty_row(5, 'Lade …', 'bi-hourglass-split') ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
// ---------------------------------------------------------------- Slide-Panel
ob_start(); ?>
  <input type="hidden" id="sgId" value="">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <div class="mb-3">
    <label class="form-label" for="sgSchluessel">Schlüssel <span class="text-danger">*</span></label>
    <input type="text" class="form-control form-control-sm" id="sgSchluessel" maxlength="100"
           placeholder="z.B. jmdefinition.skalierung" pattern="[a-z0-9._\-]+" <?= $kannBearbeiten ? '' : 'readonly' ?>>
    <div class="form-text">Kleinbuchstaben, Zahlen, Punkt, Unterstrich, Bindestrich. Konvention: <code>seite.thema</code>.</div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="sgTitel">Titel <span class="text-danger">*</span></label>
    <input type="text" class="form-control form-control-sm" id="sgTitel" maxlength="150" <?= $kannBearbeiten ? '' : 'readonly' ?>>
  </div>
  <div class="mb-3">
    <label class="form-label" for="sgKategorie">Kategorie</label>
    <input type="text" class="form-control form-control-sm" id="sgKategorie" maxlength="50" list="hilfeKategorien"
           placeholder="z.B. jmdefinition" <?= $kannBearbeiten ? '' : 'readonly' ?>>
    <datalist id="hilfeKategorien"></datalist>
  </div>
  <div class="mb-3">
    <label class="form-label" for="sgInhalt">Inhalt <span class="text-danger">*</span></label>
    <?php if ($kannBearbeiten): ?>
    <div class="editor-toolbar" role="toolbar" aria-label="Formatierung">
      <button type="button" class="ed-btn" data-wrap="p" data-tooltip="Absatz"><i class="bi bi-paragraph"></i></button>
      <button type="button" class="ed-btn" data-wrap="strong" data-tooltip="Fett (Ctrl+B)"><i class="bi bi-type-bold"></i></button>
      <button type="button" class="ed-btn" data-wrap="em" data-tooltip="Kursiv (Ctrl+I)"><i class="bi bi-type-italic"></i></button>
      <button type="button" class="ed-btn" data-wrap="u" data-tooltip="Unterstrichen (Ctrl+U)"><i class="bi bi-type-underline"></i></button>
      <span class="ed-sep"></span>
      <button type="button" class="ed-btn" data-wrap="h5" data-tooltip="Zwischentitel">H</button>
      <button type="button" class="ed-btn" data-block="ul" data-tooltip="Liste"><i class="bi bi-list-ul"></i></button>
      <button type="button" class="ed-btn" data-block="ol" data-tooltip="Nummerierte Liste"><i class="bi bi-list-ol"></i></button>
      <span class="ed-sep"></span>
      <button type="button" class="ed-btn" data-wrap="code" data-tooltip="Code inline"><i class="bi bi-code"></i></button>
      <button type="button" class="ed-btn" data-block="pre" data-tooltip="Code-Block"><i class="bi bi-code-square"></i></button>
      <button type="button" class="ed-btn" data-link data-tooltip="Link einfügen"><i class="bi bi-link-45deg"></i></button>
      <span class="ed-sep"></span>
      <button type="button" class="ed-btn" data-insert="<br>" data-tooltip="Zeilenumbruch"><i class="bi bi-arrow-return-left"></i></button>
      <span class="ed-sep"></span>
      <button type="button" class="ed-btn" data-undo data-tooltip="Rückgängig (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise"></i></button>
      <button type="button" class="ed-btn" data-redo data-tooltip="Wiederherstellen (Ctrl+Y)"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
    <?php endif; ?>
    <textarea class="form-control" id="sgInhalt" rows="10" <?= $kannBearbeiten ? '' : 'readonly' ?>
              placeholder="Text eingeben oder markieren und Format-Knopf klicken …"></textarea>
    <div class="tag-hint">
      Erlaubt: <code>p</code> <code>br</code> <code>strong</code> <code>em</code> <code>u</code> <code>ul</code>
      <code>ol</code> <code>li</code> <code>code</code> <code>pre</code> <code>h4</code>–<code>h6</code> <code>a</code>.
      Anderes wird beim Speichern entfernt.
    </div>
  </div>
  <div class="mb-2">
    <label class="form-label">Vorschau</label>
    <div id="sgPreview" class="border rounded p-3 bg-light" style="min-height:80px;">
      <span class="text-muted">Vorschau erscheint nach Eingabe …</span>
    </div>
  </div>
<?php
$panel_body = ob_get_clean();

ob_start();
if ($kannBearbeiten): ?>
  <div class="d-flex gap-2 w-100">
    <button type="button" class="btn btn-outline-danger btn-sm" id="sgDelete" style="display:none;"><i class="bi bi-trash me-1"></i>Löschen</button>
    <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" id="sgCancel">Abbrechen</button>
    <button type="button" class="btn btn-outline-primary btn-sm" id="sgSave"><i class="bi bi-save me-1"></i>Speichern</button>
  </div>
<?php else: ?>
  <div class="d-flex w-100"><button type="button" class="btn btn-outline-secondary btn-sm ms-auto" id="sgCancel">Schliessen</button></div>
<?php endif;
$panel_footer = ob_get_clean();

$panel_id       = 'hilfePanel';
$panel_close_id = 'hilfePanelClose';
$panel_title    = '<i class="bi bi-question-circle me-2"></i><span id="hilfePanelTitle">Hilfetext</span>';
$panel_width    = '560px';
include 'partials/side_panel.inc.php';
?>

<?php if ($kannBearbeiten): ?>
<!-- Link-Dialog (über dem Slide-Panel) -->
<div class="link-dialog-overlay" id="linkDialogOverlay"></div>
<div class="link-dialog" id="linkDialog" role="dialog" aria-modal="true" aria-labelledby="linkDialogTitle" hidden>
  <div class="link-dialog-header">
    <h6 id="linkDialogTitle"><i class="bi bi-link-45deg me-2"></i>Link einfügen</h6>
    <button type="button" class="btn-close" id="linkDialogCloseX" aria-label="Schliessen"></button>
  </div>
  <div class="link-dialog-body">
    <div class="mb-3">
      <label class="form-label" for="linkDialogText">Linktext</label>
      <input type="text" class="form-control form-control-sm" id="linkDialogText" placeholder="Wird im Hilfetext angezeigt">
    </div>
    <div class="mb-3">
      <label class="form-label" for="linkDialogUrl">Ziel</label>
      <input type="text" class="form-control form-control-sm" id="linkDialogUrl" placeholder="https://… oder seite.php">
      <div class="form-text">Extern mit <code>https://</code>, intern der Dateiname der Seite (z.B. <code>jmdefinition.php</code>).</div>
    </div>
    <div class="mb-1">
      <label class="form-label">Vorhandene Seiten</label>
      <div class="link-suggestions" id="linkDialogSuggestions"></div>
    </div>
  </div>
  <div class="link-dialog-footer">
    <button type="button" class="btn btn-outline-secondary btn-sm" id="linkDialogCancel">Abbrechen</button>
    <button type="button" class="btn btn-outline-primary btn-sm" id="linkDialogInsert"><i class="bi bi-check2 me-1"></i>Einfügen</button>
  </div>
</div>
<?php endif; ?>

<script>
window.HILFE_NAV_PAGES = <?= json_encode($navPages, JSON_UNESCAPED_UNICODE) ?>;
window.HILFE_KANN_BEARBEITEN = <?= $kannBearbeiten ? 'true' : 'false' ?>;
window.HILFE_API = 'hilfetexte/api.php';
</script>
<script src="hilfetexte/hilfetexte.js?v=<?= @filemtime(__DIR__ . '/hilfetexte/hilfetexte.js') ?: '1' ?>"></script>

<?php include 'footer.inc.php'; ?>
