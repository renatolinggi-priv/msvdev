<?php
/**
 * Mitgliederverwaltung – Liste + Slide-Panel (Muster wie JMDefinition).
 *
 * Seit 1.68.57 (Critique 04.11.2026):
 * - Mitgliedschaft statt vier Punkten: Stand (Mitglied / ausgetreten / verstorben) + Mitgliedsart
 *   (Aktiv, Passiv, Frei, Ehren), Eintritt und Austritt; Filter «Mitglieder | Ausgetreten | Verstorben | Alle».
 * - Eine Prüfung für Panel, Hinzufügen und Import (mitgliederverwaltung/mitglied.inc.php), Fehler am Feld.
 * - Import in zwei Schritten: Prüflauf mit Unterschieden, dann «Übernehmen» mit Sicherung vorher.
 * - Sortierbare Spalten, «voriges/nächstes Mitglied» im Panel, Suche auch nach Telefon und Waffe.
 * Gespeichert wird ausdrücklich (Knopf oder Ctrl+S); Schliessen mit offenen Änderungen fragt nach.
 */
include 'dbconnect.inc.php';
require_once __DIR__ . '/csrf.inc.php'; // csrf_token(), Session über session_config
require_once __DIR__ . '/mitgliederverwaltung/mitglied.inc.php';

$mitMitgliedschaft = mvHatMitgliedschaft($conn);
$jahr = (int)date('Y');
$artOptionen = '';
foreach (['Aktiv' => 'Aktivmitglied', 'Passiv' => 'Passivmitglied', 'Frei' => 'Freimitglied', 'Ehren' => 'Ehrenmitglied'] as $wert => $text) {
    $gesperrt = !$mitMitgliedschaft && !in_array($wert, ['Aktiv', 'Ehren'], true);
    $artOptionen .= '<option value="' . $wert . '"' . ($gesperrt ? ' disabled' : '') . '>' . $text
        . ($gesperrt ? ' (nach der Datenbank-Aktualisierung)' : '') . '</option>';
}
$kommOptionen = '<option value="">—</option><option value="Briefpost">Briefpost</option>'
    . '<option value="Whatsapp">WhatsApp</option><option value="Beides">Beides</option>';

// Seiten-CSS (nur Tokens). Panel, Skeleton, Import-Fläche zentral in msv-styles.css / msv-ui.css
$page_specific_css = <<<'CSS'
/* Lizenznummer ist fest: als Wert zeigen, nicht als Feld, das man vergeblich anklickt */
.mv-edit-panel #panelId[readonly] { background: transparent; border-style: dashed; color: var(--ui-text-2); cursor: default; box-shadow: none; }
.mv-fest { font-size: 0.7rem; color: var(--ui-text-3); margin-left: 3px; }
.mv-werkzeug { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; margin-bottom: 12px; }
.mv-zaehler { font-size: 0.85rem; font-weight: 500; color: var(--ui-text-2); }
#mitgliederTable .mv-c-nr { width: 80px; text-align: center; }
#mitgliederTable .mv-c-datum { width: 104px; }
#mitgliederTable .mv-c-mobile { width: 150px; }
#mitgliederTable .mv-c-mitgliedschaft { width: 210px; }
/* Eine Zeile pro Mitglied: Nummern und Daten brechen nie um, lange E-Mails werden gekürzt (voll im Panel) */
#mitgliederTable :is(.h-nr, .h-date, .h-mobile) { white-space: nowrap; }
#mitgliederTable .h-email { max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.desktop-table-container:has(> #mitgliederTable) { overflow-x: auto; }
/* Laptop: weniger wichtige Spalten weichen, «Mitgliedschaft» bleibt immer sichtbar (alles steht im Panel) */
@media (max-width: 1499.98px) { #mitgliederTable :is(.mv-c-ort, .h-ort) { display: none; } }
@media (max-width: 1399.98px) { #mitgliederTable :is(.mv-c-datum, .h-date) { display: none; } }
@media (max-width: 1299.98px) { #mitgliederTable :is(.mv-c-waffe, .h-waffe) { display: none; } }
@media (max-width: 1199.98px) { #mitgliederTable :is(.mv-c-mobile, .h-mobile) { display: none; } }
.mv-sort { display: inline-flex; align-items: center; gap: 4px; padding: 0; border: 0; background: none; font: inherit; color: inherit; letter-spacing: inherit; text-transform: inherit; cursor: pointer; }
.mv-sort:focus-visible { outline: 2px solid var(--ui-akzent-dunkel); outline-offset: 2px; border-radius: 3px; }
.mv-sort .bi { font-size: 0.7rem; color: var(--ui-leer); }
th[aria-sort="ascending"] .mv-sort .bi, th[aria-sort="descending"] .mv-sort .bi { color: var(--ui-text); }
.mv-mitgliedschaft { display: inline-flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; }
.mv-mitgliedschaft .ui-chip { font-size: 0.75rem; padding: 1px 7px; }
.ui-status.mv-aus { color: var(--ui-text-2); } .ui-status.mv-aus .ui-punkt { background: var(--ui-leer); }
.ui-status.mv-tot { color: var(--ui-text-2); } .ui-status.mv-tot .ui-punkt { background: var(--ui-k-grau); }
.hybrid-table tbody tr.mv-leise > td { color: var(--ui-text-3); }
.mv-fehlt { color: var(--ui-warn-fg); font-weight: 600; }
.hybrid-table tbody tr.row-saved td { background: var(--ui-ok-bg) !important; transition: background .6s; }
.form-select[aria-invalid="true"] { border-color: var(--ui-fehler); background-color: var(--ui-fehler-bg); box-shadow: 0 0 0 1px var(--ui-fehler); }
.mv-pflicht { color: var(--ui-text-3); font-weight: 400; }
.mv-aufklapp { display: inline-flex; align-items: center; gap: 6px; padding: 0; border: 0; background: none; font-size: 0.85rem; font-weight: 600; color: var(--ui-akzent-dunkel); }
.mv-aufklapp .bi { transition: transform .15s; }
.mv-aufklapp[aria-expanded="true"] .bi { transform: rotate(90deg); }
/* bewusst ohne .table-responsive: resultate-unified.css erzwingt dort 200px Mindesthöhe (!important) */
.import-preview-scroll { max-height: 260px; overflow: auto; }
#dropZone.mv-kompakt { padding: 12px 16px; }
#dropZone.mv-kompakt > .bi { display: none !important; }
#dropZone.mv-kompakt #dropZoneText { margin-top: 0 !important; }
.mv-diff td { font-size: 0.8rem; vertical-align: top; }
.mv-diff del { color: var(--ui-text-3); }
.mv-diff ins { text-decoration: none; font-weight: 600; }
.mobile-card-header.mv-karte-kopf { width: 100%; border: 0; background: transparent; text-align: left; font: inherit; color: inherit; }
@media (max-width: 767.98px) {
  /* Handy: vier Filter als 2×2-Raster, keiner verschwindet am Rand */
  #mvFilter { display: grid; grid-template-columns: 1fr 1fr; width: 100%; }
  #mvFilter button { justify-content: center; }
}
CSS;

include 'header.inc.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-wide">
        <?php
        $page_title = 'Mitgliederverwaltung';
        $page_show_mobile = true; // Hinzufügen/Import/Exporte stehen nur hier
        $page_title_after = '<button type="button" class="btn-help" data-help="mitgliederverwaltung.uebersicht" aria-label="Hilfe"></button>';
        $page_actions = '<button type="button" class="btn btn-outline-success btn-sm" id="btnNewMember"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Hinzufügen</button>'
            . '<button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload me-1" aria-hidden="true"></i>Import</button>'
            . '<button type="button" class="btn btn-outline-info btn-sm csv-export-btn" data-tooltip="Alle Felder, zugleich Vorlage für den Import"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i><span>Mitglieder (CSV)</span></button>'
            . '<button type="button" class="btn btn-outline-info btn-sm xlsx-export-btn"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i><span>Adressliste (Excel)</span></button>';
        include 'partials/page_header.inc.php';
        ?>

        <div class="content-background">
          <input type="hidden" id="csrfToken" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

          <div class="table-wrapper">
            <h5 class="table-title">
              <span><i class="bi bi-people me-2" aria-hidden="true"></i>Mitglieder <button type="button" class="btn-help" data-help="mitgliederverwaltung.liste" aria-label="Hilfe"></button></span>
              <span class="mv-zaehler" id="memberCount" aria-live="polite"></span>
            </h5>

            <div class="mv-werkzeug">
              <div class="ui-filter" id="mvFilter" role="group" aria-label="Nach Mitgliedschaft filtern">
                <button type="button" data-stand="mitglied" aria-pressed="true">Mitglieder <span data-zahl="mitglied"></span></button>
                <button type="button" data-stand="ausgetreten" aria-pressed="false">Ausgetreten <span data-zahl="ausgetreten"></span></button>
                <button type="button" data-stand="verstorben" aria-pressed="false">Verstorben <span data-zahl="verstorben"></span></button>
                <button type="button" data-stand="alle" aria-pressed="false">Alle <span data-zahl="alle"></span></button>
              </div>
              <label class="ui-suche d-none d-md-flex">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span class="visually-hidden">Mitglieder suchen</span>
                <input type="search" id="searchInput" class="mv-suche" placeholder="Name, Ort, Telefon, Waffe …" autocomplete="off">
              </label>
            </div>

            <!-- Desktop: Tabelle -->
            <div class="desktop-table-container">
              <table class="hybrid-table" id="mitgliederTable" data-zeilen-tastatur tabindex="0" aria-label="Mitglieder – Pfeiltasten wählen eine Zeile, Enter öffnet sie">
                <thead>
                  <tr>
                    <th class="mv-c-nr" data-sort="id" aria-sort="none"><button type="button" class="mv-sort">Lizenz<i class="bi bi-arrow-down-up" aria-hidden="true"></i></button></th>
                    <th data-sort="name" aria-sort="ascending"><button type="button" class="mv-sort">Name<i class="bi bi-arrow-down-up" aria-hidden="true"></i></button></th>
                    <th data-sort="vorname" aria-sort="none"><button type="button" class="mv-sort">Vorname<i class="bi bi-arrow-down-up" aria-hidden="true"></i></button></th>
                    <th class="mv-c-datum" data-sort="geburtsdatum" aria-sort="none"><button type="button" class="mv-sort">Geb.datum<i class="bi bi-arrow-down-up" aria-hidden="true"></i></button></th>
                    <th class="mv-c-waffe" data-sort="waffe" aria-sort="none"><button type="button" class="mv-sort">Waffe<i class="bi bi-arrow-down-up" aria-hidden="true"></i></button></th>
                    <th class="mv-c-ort" data-sort="ort" aria-sort="none"><button type="button" class="mv-sort">Ort<i class="bi bi-arrow-down-up" aria-hidden="true"></i></button></th>
                    <th data-sort="email" aria-sort="none"><button type="button" class="mv-sort">E-Mail<i class="bi bi-arrow-down-up" aria-hidden="true"></i></button></th>
                    <th class="mv-c-mobile" data-sort="mobile" aria-sort="none"><button type="button" class="mv-sort">Mobile<i class="bi bi-arrow-down-up" aria-hidden="true"></i></button></th>
                    <th class="mv-c-mitgliedschaft" data-sort="mitgliedschaft" aria-sort="none"><button type="button" class="mv-sort">Mitgliedschaft<i class="bi bi-arrow-down-up" aria-hidden="true"></i></button></th>
                  </tr>
                </thead>
                <tbody><!-- dynamisch --></tbody>
              </table>
            </div>

            <!-- Mobile: Karten -->
            <div class="mobile-cards-container" id="mobileMitgliederContainer">
              <div class="mobile-search">
                <div class="position-relative">
                  <i class="bi bi-search search-icon" aria-hidden="true"></i>
                  <input type="search" class="form-control mv-suche" id="mobileSearch" placeholder="Mitglieder suchen …" aria-label="Mitglieder suchen" autocomplete="off">
                </div>
              </div>
              <div class="mobile-cards-scroll" id="mobileMitgliederCards"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Slide-Panel (Bearbeiten) – Struktur zentral in inc/partials/side_panel.inc.php -->
<?php
$panel_id    = 'editPanel';
$panel_class = 'mv-edit-panel';
$panel_title = '<i class="bi bi-pencil-square me-2" aria-hidden="true"></i><span id="panelTitle">Mitglied bearbeiten</span>';
ob_start();
?>
    <div class="alert alert-danger py-2 small mb-3" id="panelFehler" role="alert" hidden></div>

    <!-- Stammdaten -->
    <div class="panel-section"><i class="bi bi-person me-1" aria-hidden="true"></i>Stammdaten <button type="button" class="btn-help" data-help="mitgliederverwaltung.stammdaten" aria-label="Hilfe"></button></div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label" for="panelName">Name</label>
        <input type="text" class="form-control form-control-sm" id="panelName" maxlength="255" required>
      </div>
      <div class="col-6">
        <label class="panel-label" for="panelVorname">Vorname</label>
        <input type="text" class="form-control form-control-sm" id="panelVorname" maxlength="255" required>
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-4">
        <label class="panel-label" for="panelAnrede">Anrede</label>
        <select class="form-select form-select-sm" id="panelAnrede">
          <option value="">—</option>
          <option value="Herr">Herr</option>
          <option value="Frau">Frau</option>
        </select>
      </div>
      <div class="col-8">
        <label class="panel-label" for="panelGeburtsdatum">Geburtsdatum</label>
        <input type="date" class="form-control form-control-sm" id="panelGeburtsdatum" min="1900-01-01" max="<?= date('Y-m-d') ?>" required>
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-4">
        <label class="panel-label" for="panelId">Lizenznr.<i class="bi bi-lock-fill mv-fest" aria-hidden="true" data-tooltip="Die Lizenznummer lässt sich nach dem Anlegen nicht ändern"></i></label>
        <input type="text" class="form-control form-control-sm" id="panelId" readonly tabindex="-1" aria-description="nicht änderbar">
      </div>
      <div class="col-8">
        <label class="panel-label" for="panelWaffe">Waffe</label>
        <select class="form-select form-select-sm" id="panelWaffe" required></select>
      </div>
    </div>

    <!-- Mitgliedschaft -->
    <div class="panel-section"><i class="bi bi-person-vcard me-1" aria-hidden="true"></i>Mitgliedschaft <button type="button" class="btn-help" data-help="mitgliederverwaltung.status" aria-label="Hilfe"></button></div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label" for="panelStand">Stand</label>
        <select class="form-select form-select-sm" id="panelStand">
          <option value="mitglied">Mitglied</option>
          <option value="ausgetreten">Ausgetreten</option>
          <option value="verstorben">Verstorben</option>
        </select>
      </div>
      <div class="col-6">
        <label class="panel-label" for="panelArt">Mitgliedsart</label>
        <select class="form-select form-select-sm" id="panelArt"><?= $artOptionen ?></select>
      </div>
    </div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label" for="panelVereinsaufnahme">Eintritt (Jahr)</label>
        <input type="number" class="form-control form-control-sm" id="panelVereinsaufnahme" min="1901" max="<?= $jahr + 1 ?>" step="1" placeholder="z.B. 1994">
      </div>
      <?php if ($mitMitgliedschaft): ?>
      <div class="col-6" id="panelAustrittFeld" hidden>
        <label class="panel-label" for="panelAustrittsjahr">Austritt (Jahr)</label>
        <input type="number" class="form-control form-control-sm" id="panelAustrittsjahr" min="1901" max="<?= $jahr + 1 ?>" step="1" placeholder="z.B. <?= $jahr ?>">
      </div>
      <?php endif; ?>
    </div>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="panelJskLeiter">
      <label class="form-check-label" for="panelJskLeiter">Gehört zur Jungschützenleitung</label>
    </div>

    <!-- Adresse -->
    <div class="panel-section"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Adresse</div>
    <div class="mb-2">
      <label class="panel-label" for="panelStrasse">Strasse</label>
      <input type="text" class="form-control form-control-sm" id="panelStrasse" maxlength="255" autocomplete="off">
    </div>
    <div class="row g-2 mb-2">
      <div class="col-4">
        <label class="panel-label" for="panelPlz">PLZ</label>
        <input type="text" class="form-control form-control-sm" id="panelPlz" maxlength="10" inputmode="numeric" autocomplete="off">
      </div>
      <div class="col-8">
        <label class="panel-label" for="panelOrt">Ort</label>
        <input type="text" class="form-control form-control-sm" id="panelOrt" maxlength="100" autocomplete="off">
      </div>
    </div>

    <!-- Kontakt -->
    <div class="panel-section"><i class="bi bi-telephone me-1" aria-hidden="true"></i>Kontakt</div>
    <div class="mb-2">
      <label class="panel-label" for="panelEmail">E-Mail</label>
      <input type="email" class="form-control form-control-sm" id="panelEmail" maxlength="255" autocomplete="off">
    </div>
    <div class="row g-2 mb-2">
      <div class="col-6">
        <label class="panel-label" for="panelTelefon">Telefon</label>
        <input type="tel" class="form-control form-control-sm" id="panelTelefon" placeholder="079 123 45 67" autocomplete="off">
      </div>
      <div class="col-6">
        <label class="panel-label" for="panelMobile">Mobile</label>
        <input type="tel" class="form-control form-control-sm" id="panelMobile" placeholder="079 123 45 67" autocomplete="off">
      </div>
    </div>
    <div class="mb-2">
      <label class="panel-label" for="panelKommunikation">Kommunikation</label>
      <select class="form-select form-select-sm" id="panelKommunikation"><?= $kommOptionen ?></select>
    </div>
    <div class="mb-3">
      <label class="panel-label" for="panelNotizen">Notizen</label>
      <textarea class="form-control form-control-sm" id="panelNotizen" rows="2"></textarea>
    </div>

    <hr>
    <button type="button" class="btn btn-outline-danger btn-sm w-100" id="panelDeleteBtn">
      <i class="bi bi-trash me-1" aria-hidden="true"></i>Mitglied löschen
    </button>
<?php
$panel_body = ob_get_clean();
$panel_footer = '<div class="d-flex gap-2 w-100 align-items-center">'
    . '<div class="btn-group" role="group" aria-label="Mitglied wechseln">'
    . '<button type="button" class="btn btn-outline-secondary btn-sm" id="panelPrev" aria-label="Voriges Mitglied" data-tooltip="Voriges Mitglied (Alt+↑)"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>'
    . '<button type="button" class="btn btn-outline-secondary btn-sm" id="panelNext" aria-label="Nächstes Mitglied" data-tooltip="Nächstes Mitglied (Alt+↓)"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>'
    . '</div>'
    . '<span class="small text-muted d-none d-md-inline"><kbd class="ui-kbd">Ctrl</kbd>+<kbd class="ui-kbd">S</kbd> speichern · <kbd class="ui-kbd">Esc</kbd> schliessen</span>'
    . '<button type="button" class="btn btn-primary btn-sm ms-auto" id="panelSaveBtn"><i class="bi bi-save me-1" aria-hidden="true"></i>Speichern</button></div>';
include 'partials/side_panel.inc.php';
?>

<!-- Modal: Neues Mitglied -->
<div class="modal fade" id="newMemberModal" tabindex="-1" aria-labelledby="newMemberTitle">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="newMemberTitle"><i class="bi bi-person-plus me-2" aria-hidden="true"></i>Neues Mitglied <button type="button" class="btn-help" data-help="mitgliederverwaltung.neu" aria-label="Hilfe"></button></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <form id="newMemberForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="modal-body">
          <p class="small text-muted mb-3">Felder mit * braucht es zum Anlegen. Adresse und Kontakt kannst du auch später im Panel ergänzen.</p>
          <div class="alert alert-danger py-2 small mb-3" id="nmFehler" role="alert" hidden></div>

          <div class="panel-section">Person</div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="panel-label" for="nmName">Name <span class="mv-pflicht" aria-hidden="true">*</span></label>
              <input type="text" class="form-control form-control-sm" name="name" id="nmName" maxlength="255" required autocomplete="off">
            </div>
            <div class="col-6">
              <label class="panel-label" for="nmVorname">Vorname <span class="mv-pflicht" aria-hidden="true">*</span></label>
              <input type="text" class="form-control form-control-sm" name="vorname" id="nmVorname" maxlength="255" required autocomplete="off">
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-4">
              <label class="panel-label" for="nmAnrede">Anrede</label>
              <select class="form-select form-select-sm" name="anrede" id="nmAnrede">
                <option value="">—</option><option value="Herr">Herr</option><option value="Frau">Frau</option>
              </select>
            </div>
            <div class="col-8">
              <label class="panel-label" for="nmGeburtsdatum">Geburtsdatum <span class="mv-pflicht" aria-hidden="true">*</span></label>
              <input type="date" class="form-control form-control-sm" name="geburtsdatum" id="nmGeburtsdatum" min="1900-01-01" max="<?= date('Y-m-d') ?>" required>
            </div>
          </div>

          <div class="panel-section">Mitgliedschaft</div>
          <div class="row g-2 mb-3">
            <div class="col-4">
              <label class="panel-label" for="nmId">Lizenznr. <span class="mv-pflicht" aria-hidden="true">*</span></label>
              <input type="number" class="form-control form-control-sm" name="id" id="nmId" min="1" step="1" required>
            </div>
            <div class="col-8">
              <label class="panel-label" for="newWaffenSelect">Waffe <span class="mv-pflicht" aria-hidden="true">*</span></label>
              <select class="form-select form-select-sm" name="waffenid" id="newWaffenSelect" required>
                <option value="">Bitte wählen …</option>
              </select>
            </div>
            <div class="col-8">
              <label class="panel-label" for="nmArt">Mitgliedsart</label>
              <select class="form-select form-select-sm" name="mitgliedsart" id="nmArt"><?= $artOptionen ?></select>
            </div>
            <div class="col-4">
              <label class="panel-label" for="nmAufnahme">Eintritt (Jahr)</label>
              <input type="number" class="form-control form-control-sm" name="vereinsaufnahme" id="nmAufnahme" min="1901" max="<?= $jahr + 1 ?>" step="1" value="<?= $jahr ?>">
            </div>
          </div>

          <button type="button" class="mv-aufklapp" data-bs-toggle="collapse" data-bs-target="#nmKontakt" aria-expanded="false" aria-controls="nmKontakt">
            <i class="bi bi-chevron-right" aria-hidden="true"></i>Adresse und Kontakt
          </button>
          <div class="collapse" id="nmKontakt">
            <div class="row g-2 mt-1">
              <div class="col-12"><label class="panel-label" for="nmStrasse">Strasse</label>
                <input type="text" class="form-control form-control-sm" name="strasse" id="nmStrasse" maxlength="255" autocomplete="off"></div>
              <div class="col-4"><label class="panel-label" for="nmPlz">PLZ</label>
                <input type="text" class="form-control form-control-sm" name="plz" id="nmPlz" maxlength="10" inputmode="numeric" autocomplete="off"></div>
              <div class="col-8"><label class="panel-label" for="nmOrt">Ort</label>
                <input type="text" class="form-control form-control-sm" name="ort" id="nmOrt" maxlength="100" autocomplete="off"></div>
              <div class="col-12"><label class="panel-label" for="nmEmail">E-Mail</label>
                <input type="email" class="form-control form-control-sm" name="email" id="nmEmail" maxlength="255" autocomplete="off"></div>
              <div class="col-6"><label class="panel-label" for="nmTelefon">Telefon</label>
                <input type="tel" class="form-control form-control-sm" name="telefon" id="nmTelefon" placeholder="079 123 45 67" autocomplete="off"></div>
              <div class="col-6"><label class="panel-label" for="nmMobile">Mobile</label>
                <input type="tel" class="form-control form-control-sm" name="mobile" id="nmMobile" placeholder="079 123 45 67" autocomplete="off"></div>
              <div class="col-6"><label class="panel-label" for="nmKomm">Kommunikation</label>
                <select class="form-select form-select-sm" name="kommunikation" id="nmKomm"><?= $kommOptionen ?></select></div>
              <div class="col-12"><label class="panel-label" for="nmNotizen">Notizen</label>
                <textarea class="form-control form-control-sm" name="notizen" id="nmNotizen" rows="2"></textarea></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Abbrechen
          </button>
          <button type="submit" class="btn btn-outline-success btn-sm" id="nmSubmit">
            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Hinzufügen
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: CSV-Import (Prüflauf, dann Übernehmen) -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalTitle">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="importModalTitle"><i class="bi bi-upload me-2" aria-hidden="true"></i>Mitglieder importieren <button type="button" class="btn-help" data-help="mitgliederverwaltung.import" aria-label="Hilfe"></button></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <p class="small mb-2">Am einfachsten: <a href="#" class="mv-vorlage">aktuelle Mitglieder als CSV herunterladen</a>, in Excel ändern, als CSV speichern und hier wieder hochladen.</p>
        <ul class="small text-muted mb-3 ps-3">
          <li>Die Spalte <strong>ID</strong> (Lizenznummer) bestimmt, wer aktualisiert und wer neu angelegt wird.</li>
          <li>Geändert werden nur Spalten, die in der Datei stehen. Fehlt zum Beispiel «Status», bleibt die Mitgliedschaft, wie sie ist.</li>
          <li>Zuerst siehst du, was sich ändert. Erst «Übernehmen» schreibt; vorher wird die Datenbank gesichert.</li>
        </ul>
        <div class="import-area" id="dropZone" role="button" tabindex="0" aria-label="CSV-Datei wählen">
          <i class="bi bi-cloud-upload d-block" aria-hidden="true"></i>
          <p class="mb-0 mt-2 text-muted" id="dropZoneText">CSV-Datei hier ablegen oder klicken</p>
          <input type="file" id="csvFile" accept=".csv,.txt,text/csv" hidden>
        </div>
        <div id="importErgebnis" class="mt-3" hidden>
          <div class="ui-chips mb-2" id="importZahlen" aria-live="polite"></div>
          <p class="small text-muted mb-2" id="importHinweis"></p>
          <div class="import-preview-scroll">
            <table class="table table-sm mv-diff mb-0" id="importDiff">
              <thead><tr><th scope="col">Lizenz</th><th scope="col">Mitglied</th><th scope="col">Änderung</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="alert alert-warning py-2 small mt-2 mb-0" id="importFehler" hidden></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Abbrechen</button>
        <button type="button" class="btn btn-outline-success btn-sm" id="confirmImport" hidden>
          <i class="bi bi-check-circle me-1" aria-hidden="true"></i><span>Übernehmen</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script src="js/msv-phone.js?v=<?= (int)@filemtime(__DIR__ . '/js/msv-phone.js') ?>"></script>
<script>
$(function() {
  const basePath = (/\/inc(\/|$)/.test(location.pathname)) ? '' : 'inc/';
  const CSRF = document.getElementById('csrfToken').value;
  const ART_TEXT = { Aktiv: 'Aktivmitglied', Passiv: 'Passivmitglied', Frei: 'Freimitglied', Ehren: 'Ehrenmitglied' };
  const STAND_REIHE = { mitglied: 0, ausgetreten: 1, verstorben: 2 };
  const DIESES_JAHR = <?= $jahr ?>;
  const $tbody = $('#mitgliederTable tbody');

  // ========== Hilfen ==========
  // Stand aus den Spalten Status/Verstorben: verstorben vor ausgetreten vor Mitglied
  const standVon = d => d.verstorben === '1' ? 'verstorben' : (d.status === '1' ? 'mitglied' : 'ausgetreten');

  function mvZelleMitgliedschaft(d) {
    const stand = standVon(d);
    let html;
    if (stand === 'mitglied') {
      html = '<span class="ui-status ok"><span class="ui-punkt" aria-hidden="true"></span>' + (ART_TEXT[d.mitgliedsart] || 'Aktivmitglied') + '</span>';
    } else if (stand === 'ausgetreten') {
      html = '<span class="ui-status mv-aus"><span class="ui-punkt" aria-hidden="true"></span>Ausgetreten' + (d.austrittsjahr ? ' ' + msvEsc(d.austrittsjahr) : '') + '</span>';
      if (d.mitgliedsart === 'Ehren') html += '<span class="ui-chip">Ehrenmitglied</span>';
    } else {
      html = '<span class="ui-status mv-tot"><span class="ui-punkt" aria-hidden="true"></span>Verstorben</span>';
      if (d.mitgliedsart === 'Ehren') html += '<span class="ui-chip">Ehrenmitglied</span>';
    }
    if (d.ist_jsk_leiter === '1') html += '<span class="ui-chip">JSK-Leitung</span>';
    return '<span class="mv-mitgliedschaft">' + html + '</span>';
  }

  function zeileDarstellen(tr) {
    const d = tr.dataset;
    tr.classList.toggle('mv-leise', standVon(d) !== 'mitglied');
    const td = tr.querySelector('.h-mitgliedschaft');
    if (td) td.innerHTML = mvZelleMitgliedschaft(d);
  }

  const zeilen = () => Array.from($tbody[0].querySelectorAll('tr.hybrid-row'));
  const sichtbareZeilen = () => zeilen().filter(tr => !tr.hidden);

  // Fehler am Feld: fehler = { feld: Text }, karte = { feld: '#elementId' }
  function zeigeFehler(box, fehler, karte) {
    const $box = $(box);
    $box.closest('.modal, .mv-edit-panel').find('[aria-invalid="true"]').removeAttr('aria-invalid');
    const liste = [];
    Object.keys(fehler || {}).forEach(feld => {
      const el = document.querySelector(karte[feld] || '#_kein_feld');
      if (!el) return;
      el.setAttribute('aria-invalid', 'true');
      liste.push({ el, name: msvFeldName(el).replace(/\s*\*$/, ''), wert: el.tagName === 'SELECT' ? '' : el.value, fehler: fehler[feld] });
    });
    // in der Reihenfolge des Formulars melden, nicht in der Reihenfolge der Prüfung
    liste.sort((a, b) => (a.el.compareDocumentPosition(b.el) & Node.DOCUMENT_POSITION_FOLLOWING) ? -1 : 1);
    if (liste.length && !$(liste[0].el).is(':visible')) $(liste[0].el).closest('.collapse').collapse('show');
    msvEingabeFehler($box[0], liste);
    return liste.length;
  }
  function fehlerWeg(box) {
    $(box).closest('.modal, .mv-edit-panel').find('[aria-invalid="true"]').removeAttr('aria-invalid');
    msvEingabeFehler($(box)[0], []);
  }
  $(document).on('input change', '.mv-edit-panel [aria-invalid="true"], #newMemberModal [aria-invalid="true"]', function() {
    this.removeAttribute('aria-invalid');
  });

  // Pflichtfelder und Formate vor dem Senden (Server prüft dasselbe noch einmal)
  function pruefeVorher(karte) {
    const f = {};
    ['name', 'vorname', 'geburtsdatum', 'waffenid', 'id'].forEach(k => {
      const el = karte[k] && document.querySelector(karte[k]);
      if (el && !el.readOnly && String(el.value).trim() === '') f[k] = (k === 'waffenid' ? 'Bitte eine wählen.' : 'Bitte ausfüllen.');
    });
    ['email', 'geburtsdatum'].forEach(k => {
      const el = karte[k] && document.querySelector(karte[k]);
      if (el && !f[k] && el.validity && (el.validity.typeMismatch || el.validity.badInput || el.validity.rangeOverflow || el.validity.rangeUnderflow)) {
        f[k] = k === 'email' ? 'Keine gültige E-Mail-Adresse.' : 'Kein gültiges Datum.';
      }
    });
    ['vereinsaufnahme', 'austrittsjahr'].forEach(k => {
      const el = karte[k] && document.querySelector(karte[k]);
      if (el && !f[k]) { const t = msvPruefeZahl(el); if (t) f[k] = t; }
    });
    return f;
  }

  const PANEL_FELDER = {
    name: '#panelName', vorname: '#panelVorname', anrede: '#panelAnrede', geburtsdatum: '#panelGeburtsdatum',
    waffenid: '#panelWaffe', mitgliedsart: '#panelArt', vereinsaufnahme: '#panelVereinsaufnahme',
    austrittsjahr: '#panelAustrittsjahr', strasse: '#panelStrasse', plz: '#panelPlz', ort: '#panelOrt',
    email: '#panelEmail', telefon: '#panelTelefon', mobile: '#panelMobile', kommunikation: '#panelKommunikation',
    notizen: '#panelNotizen'
  };
  const NEU_FELDER = {
    id: '#nmId', name: '#nmName', vorname: '#nmVorname', anrede: '#nmAnrede', geburtsdatum: '#nmGeburtsdatum',
    waffenid: '#newWaffenSelect', mitgliedsart: '#nmArt', vereinsaufnahme: '#nmAufnahme', strasse: '#nmStrasse',
    plz: '#nmPlz', ort: '#nmOrt', email: '#nmEmail', telefon: '#nmTelefon', mobile: '#nmMobile',
    kommunikation: '#nmKomm', notizen: '#nmNotizen'
  };

  // ========== Slide-Panel ==========
  const MVPanel = {
    currentId: null,
    dirty: false,
    snapshot: null,   // Zeile beim Öffnen bzw. nach dem letzten Speichern (für «Verwerfen»)

    setDirty(an) { this.dirty = !!an; msvPanelUngespeichert($('#editPanelTitel'), this.dirty); },

    // Schliessen oder Wechseln mit offenen Änderungen: nachfragen (Speichern / Verwerfen / Zurück).
    async schuetze(weiter) {
      if (!this.dirty) { weiter(); return; }
      const wahl = await msvUngespeichert({ wer: $('#panelTitle').text().trim() });
      if (wahl === 'speichern') { try { await this.saveCurrent(weiter); } catch (e) { /* Meldung kommt aus saveCurrent */ } }
      else if (wahl === 'verwerfen') {
        const tr = document.getElementById('row' + this.currentId);
        msvZeileZurueck(tr, this.snapshot);
        this.setDirty(false);
        weiter();
      }
    },

    versucheSchliessen() { this.schuetze(() => this.close()); },

    open(tr) {
      if (this.dirty && this.currentId && tr.dataset.id !== this.currentId) {
        const ziel = tr.id;
        this.schuetze(() => this.open(document.getElementById(ziel) || tr));
        return;
      }

      const d = tr.dataset;
      this.currentId = d.id;
      this.snapshot = msvZeileMerken(tr);
      fehlerWeg('#panelFehler');

      $('#panelAnrede').val(d.anrede || '');
      $('#panelId').val(d.id);
      $('#panelName').val(d.name);
      $('#panelVorname').val(d.vorname);
      $('#panelGeburtsdatum').val(d.geburtsdatum);
      $('#panelStrasse').val(d.strasse);
      $('#panelPlz').val(d.plz);
      $('#panelOrt').val(d.ort);
      $('#panelEmail').val(d.email);
      $('#panelTelefon').val(d.telefon);
      $('#panelMobile').val(d.mobile);
      $('#panelKommunikation').val(d.kommunikation || '');
      $('#panelNotizen').val(d.notizen);
      $('#panelStand').val(standVon(d));
      $('#panelArt').val(d.mitgliedsart || 'Aktiv');
      $('#panelVereinsaufnahme').val(d.vereinsaufnahme || '');
      $('#panelAustrittsjahr').val(d.austrittsjahr || '');
      $('#panelAustrittFeld').prop('hidden', standVon(d) !== 'ausgetreten');
      $('#panelJskLeiter').prop('checked', d.ist_jsk_leiter === '1');
      waffenGeladen.then(() => $('#panelWaffe').val(d.waffenid && $('#panelWaffe option[value="' + d.waffenid + '"]').length ? d.waffenid : ''));

      $('#panelTitle').text((d.vorname + ' ' + d.name).trim());
      $('#panelDeleteBtn').data('id', d.id);

      this.setDirty(false);
      $('.hybrid-row').removeClass('selected');
      $(tr).addClass('selected');
      tr.scrollIntoView({ block: 'nearest' });

      $('#editPanel').addClass('open');
      $('#panelOverlay').addClass('show');
    },

    // Nur schliessen; gefragt wird vorher in versucheSchliessen()
    close() {
      $('#editPanel').removeClass('open');
      $('#panelOverlay').removeClass('show');
      $('.hybrid-row').removeClass('selected');
      this.currentId = null;
      this.snapshot = null;
      this.setDirty(false);
      anwenden(); // eine geänderte Mitgliedschaft fällt erst jetzt aus dem Filter
    },

    // Voriges/nächstes sichtbares Mitglied (fragt bei offenen Änderungen nach)
    wechsle(schritt) {
      if (!this.currentId) return;
      const liste = sichtbareZeilen();
      const i = liste.findIndex(tr => tr.dataset.id === this.currentId);
      const ziel = liste[i + schritt];
      if (ziel) this.open(ziel);
      else msvToast(schritt > 0 ? 'Das ist das letzte Mitglied der Liste' : 'Das ist das erste Mitglied der Liste', 'info');
    },

    // Speichert das offene Mitglied; erst nach Erfolg gilt es als gespeichert (sonst bleibt der Hinweis stehen)
    saveCurrent(weiter) {
      const id = this.currentId;
      const tr = id && document.getElementById('row' + id);
      if (!tr) return $.Deferred().reject().promise();
      const vorher = pruefeVorher(PANEL_FELDER);
      if (Object.keys(vorher).length) { zeigeFehler('#panelFehler', vorher, PANEL_FELDER); return $.Deferred().reject().promise(); }
      const d = tr.dataset;

      return $.post(basePath + 'mitgliederverwaltung/save_single_mitglied.php', {
        id, anrede: d.anrede || '', name: d.name, vorname: d.vorname, geburtsdatum: d.geburtsdatum,
        waffenid: d.waffenid, strasse: d.strasse, plz: d.plz, ort: d.ort, email: d.email,
        telefon: d.telefon, mobile: d.mobile, kommunikation: d.kommunikation || '', notizen: d.notizen,
        status: d.status === '1' ? 1 : 0, verstorben: d.verstorben === '1' ? 1 : 0,
        mitgliedsart: d.mitgliedsart || 'Aktiv', austrittsjahr: d.austrittsjahr || '',
        ist_jsk_leiter: d.ist_jsk_leiter === '1' ? 1 : 0, vereinsaufnahme: d.vereinsaufnahme || '', csrf_token: CSRF
      }, null, 'json')
      .done(r => {
        const w = r.werte || {};
        // vereinheitlichte Werte (Telefon +41 …) in Zeile und Panel übernehmen
        ['telefon', 'mobile', 'email', 'ehrenmitglied', 'austrittsjahr'].forEach(k => {
          if (w[k] === undefined) return;
          tr.setAttribute('data-' + k, w[k] === null ? '' : w[k]);
          if (PANEL_FELDER[k] && MVPanel.currentId === id) $(PANEL_FELDER[k]).val(w[k] === null ? '' : w[k]);
        });
        $(tr).find('.h-mobile').text(tr.dataset.mobile || '');
        zeileDarstellen(tr);
        fehlerWeg('#panelFehler');
        MVPanel.setDirty(false);
        MVPanel.snapshot = msvZeileMerken(tr);
        zaehlen();
        msvToast('Gespeichert', 'success');
        $(tr).addClass('row-saved'); setTimeout(() => $(tr).removeClass('row-saved'), 1200);
        if (typeof weiter === 'function') weiter();
      })
      .fail(xhr => {
        const r = xhr.responseJSON || {};
        if (!(r.fehler && zeigeFehler('#panelFehler', r.fehler, PANEL_FELDER))) msvToast(msvXhrMessage(xhr, 'Das Mitglied konnte nicht gespeichert werden.'), 'error');
      });
    },

    // Feld → data-* der Zeile (Speichern liest von dort) und sichtbare Zelle
    sync(field, value) {
      if (!this.currentId) return;
      const tr = document.getElementById('row' + this.currentId);
      if (!tr) return;
      tr.setAttribute('data-' + field, value);
      const zelle = { name: '.h-name', vorname: '.h-vorname', ort: '.h-ort', email: '.h-email', mobile: '.h-mobile' }[field];
      if (zelle) $(tr).find(zelle).text(value);
      if (field === 'geburtsdatum') {
        const p = String(value).split('-');
        $(tr).find('.h-date').text(p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : '');
      }
      if (field === 'waffenid') $(tr).find('.h-waffe').text(value ? $('#panelWaffe option:selected').text() : '');
      if (field === 'name' || field === 'vorname') $('#panelTitle').text((tr.dataset.vorname + ' ' + tr.dataset.name).trim());
      if (['status', 'verstorben', 'mitgliedsart', 'austrittsjahr', 'ist_jsk_leiter'].includes(field)) zeileDarstellen(tr);
      this.setDirty(true);
    }
  };

  // Panel-Ereignisse
  $(document).on('click', '.hybrid-row', function() { MVPanel.open(this); });
  $('#panelClose, #panelOverlay').on('click', () => MVPanel.versucheSchliessen());
  $('#panelPrev').on('click', () => MVPanel.wechsle(-1));
  $('#panelNext').on('click', () => MVPanel.wechsle(1));
  $(document).on('keydown', e => {
    if (!$('#editPanel').hasClass('open')) return;
    if (e.key === 'Escape' && !(window.Swal && Swal.isVisible())) { e.stopImmediatePropagation(); MVPanel.versucheSchliessen(); }
    if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) { e.preventDefault(); MVPanel.wechsle(e.key === 'ArrowDown' ? 1 : -1); }
    // Enter: ins nächste Feld wie auf allen Erfassungsseiten (gespeichert wird mit «Speichern» oder Ctrl+S)
    if (e.key === 'Enter' && $(e.target).is('#editPanel input:not([type="checkbox"]):not([type="radio"]):not([type="button"]):not([type="submit"]), #editPanel select')) {
      e.preventDefault();
      const felder = $('#editPanel').find('input:not([type="hidden"]):not(:disabled):not([readonly]), select:not(:disabled), textarea:not(:disabled)').filter(':visible').toArray();
      const i = felder.indexOf(e.target);
      if (i >= 0 && i < felder.length - 1) { felder[i + 1].focus(); if (felder[i + 1].select) felder[i + 1].select(); }
    }
  });
  // Ctrl+S: im Neu-Dialog absenden, sonst das offene Mitglied speichern
  $(document).on('keydown', function(e) {
    if (!((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S'))) return;
    e.preventDefault();
    if ($('#newMemberModal').hasClass('show')) { $('#newMemberForm')[0].requestSubmit(); return; }
    if (!MVPanel.currentId) return;
    if (MVPanel.dirty) MVPanel.saveCurrent(); else msvToast('Keine Änderungen', 'info');
  });
  $('#panelSaveBtn').on('click', function() { if (MVPanel.dirty) MVPanel.saveCurrent(); else msvToast('Keine Änderungen', 'info'); });
  $(window).on('beforeunload', function() { if (MVPanel.dirty) return 'Ungespeicherte Änderungen!'; });

  // Live-Sync Panel → Zeile
  [['#panelName', 'name'], ['#panelVorname', 'vorname'], ['#panelStrasse', 'strasse'], ['#panelPlz', 'plz'],
   ['#panelOrt', 'ort'], ['#panelEmail', 'email'], ['#panelTelefon', 'telefon'], ['#panelMobile', 'mobile'],
   ['#panelNotizen', 'notizen']].forEach(([sel, feld]) => $(sel).on('input', function() { MVPanel.sync(feld, this.value); }));
  [['#panelGeburtsdatum', 'geburtsdatum'], ['#panelWaffe', 'waffenid'], ['#panelAnrede', 'anrede'],
   ['#panelKommunikation', 'kommunikation'], ['#panelVereinsaufnahme', 'vereinsaufnahme'],
   ['#panelAustrittsjahr', 'austrittsjahr'], ['#panelArt', 'mitgliedsart']].forEach(([sel, feld]) => $(sel).on('change', function() { MVPanel.sync(feld, this.value); }));
  $('#panelJskLeiter').on('change', function() { MVPanel.sync('ist_jsk_leiter', this.checked ? '1' : '0'); });
  // Stand: Mitglied / ausgetreten / verstorben → Status, Verstorben, Austrittsjahr
  $('#panelStand').on('change', function() {
    const stand = this.value;
    if (stand === 'mitglied') {
      MVPanel.sync('status', '1'); MVPanel.sync('verstorben', '0');
      $('#panelAustrittsjahr').val(''); MVPanel.sync('austrittsjahr', '');
    } else if (stand === 'ausgetreten') {
      MVPanel.sync('status', '0'); MVPanel.sync('verstorben', '0');
      if ($('#panelAustrittsjahr').length && !$('#panelAustrittsjahr').val()) { $('#panelAustrittsjahr').val(DIESES_JAHR); MVPanel.sync('austrittsjahr', String(DIESES_JAHR)); }
    } else {
      MVPanel.sync('verstorben', '1'); // Status bleibt; alle Listen schliessen Verstorbene ohnehin aus
    }
    $('#panelAustrittFeld').prop('hidden', stand !== 'ausgetreten');
  });

  // Telefonnummern beim Verlassen formatieren (Schweiz: +41 79 123 45 67, Ausland bleibt)
  $('#panelTelefon, #panelMobile, #nmTelefon, #nmMobile').on('blur', function() {
    const v = $(this).val().trim();
    if (v && formatSwissPhone(v) !== v) $(this).val(formatSwissPhone(v)).trigger('input');
  });

  // ========== Daten laden ==========
  function showSkeleton() {
    const breiten = ['40px', '80%', '70%', '60%', '50%', '60%', '90%', '70%', '80%'];
    const row = '<tr>' + breiten.map(b => `<td><div class="skeleton" style="width:${b}"></div></td>`).join('') + '</tr>';
    $tbody.html(row.repeat(6));
  }

  function loadMitglieder() {
    MVPanel.close();
    showSkeleton();
    return $.get(basePath + 'mitgliederverwaltung/load_mitglieder_form.php').then(html => {
      $tbody.html(html);
      zeilen().forEach(zeileDarstellen);
      sortieren();
      anwenden();
    }, xhr => {
      $tbody.html('<tr><td colspan="9" class="text-center py-4"><div class="text-danger mb-2"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>'
        + msvEsc(msvXhrMessage(xhr, 'Die Mitglieder konnten nicht geladen werden.')) + '</div>'
        + '<button type="button" class="btn btn-outline-secondary btn-sm mv-neu-laden"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Nochmals versuchen</button></td></tr>');
    });
  }
  $(document).on('click', '.mv-neu-laden', () => loadMitglieder());

  const waffenGeladen = $.get(basePath + 'mitgliederverwaltung/load_waffen_option.php').then(data => {
    $('#newWaffenSelect').html('<option value="">Bitte wählen …</option>' + data);
    $('#panelWaffe').html('<option value="">Bitte wählen …</option>' + data);
  }, xhr => msvToast(msvXhrMessage(xhr, 'Die Waffenliste konnte nicht geladen werden. Bitte die Seite neu laden.'), 'error'));

  // ========== Filter, Suche, Zähler ==========
  const url = new URL(location.href);
  let stand = ['mitglied', 'ausgetreten', 'verstorben', 'alle'].includes(url.searchParams.get('stand')) ? url.searchParams.get('stand') : 'mitglied';
  let suche = '';

  function zaehlen() {
    const n = { mitglied: 0, ausgetreten: 0, verstorben: 0, alle: 0 };
    zeilen().forEach(tr => { n[standVon(tr.dataset)]++; n.alle++; });
    Object.keys(n).forEach(k => $('#mvFilter [data-zahl="' + k + '"]').text(n[k]));
    const sichtbar = sichtbareZeilen().length;
    const imFilter = n[stand];
    const wort = { mitglied: 'Mitglieder', ausgetreten: 'ausgetreten', verstorben: 'verstorben', alle: 'Einträge' }[stand];
    $('#memberCount').text(sichtbar < imFilter ? sichtbar + ' von ' + imFilter + ' ' + wort : imFilter + ' ' + wort);
  }

  function anwenden() {
    const q = suche.trim().toLowerCase();
    zeilen().forEach(tr => {
      const d = tr.dataset;
      const passt = (stand === 'alle' || standVon(d) === stand)
        && (!q || [d.id, d.name, d.vorname, d.email, d.ort, d.plz, d.strasse, d.telefon, d.mobile, d.waffe].join(' ').toLowerCase().includes(q));
      tr.hidden = !passt;
    });
    $tbody.find('tr.mv-leer').remove();
    if (zeilen().length && !sichtbareZeilen().length) {
      $tbody.append('<tr class="mv-leer"><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-inbox me-2" aria-hidden="true"></i>'
        + (q ? 'Keine Treffer für «' + msvEsc(suche.trim()) + '».' : 'Keine Mitglieder in dieser Auswahl.')
        + (q ? ' <button type="button" class="btn btn-outline-secondary btn-sm ms-2 mv-suche-leeren">Suche leeren</button>' : '')
        + (stand !== 'alle' ? ' <button type="button" class="btn btn-outline-secondary btn-sm ms-2 mv-alle-zeigen">Alle zeigen</button>' : '')
        + '</td></tr>');
    }
    $('#mvFilter button').each(function() { this.setAttribute('aria-pressed', this.dataset.stand === stand ? 'true' : 'false'); });
    zaehlen();
    buildMobileCards();
  }

  function setzeStand(neu) {
    stand = neu;
    const u = new URL(location.href);
    if (stand === 'mitglied') u.searchParams.delete('stand'); else u.searchParams.set('stand', stand);
    history.replaceState(null, '', u);
    anwenden();
  }
  $('#mvFilter').on('click', 'button', function() { setzeStand(this.dataset.stand); });
  $(document).on('input', '.mv-suche', function() {
    suche = this.value;
    $('.mv-suche').not(this).val(this.value);
    anwenden();
  });
  $(document).on('click', '.mv-suche-leeren', () => { suche = ''; $('.mv-suche').val(''); anwenden(); });
  $(document).on('click', '.mv-alle-zeigen', () => setzeStand('alle'));

  // ========== Sortierung ==========
  let sortFeld = 'name', sortRichtung = 1;
  const sortWert = (tr, feld) => {
    const d = tr.dataset;
    if (feld === 'id') return Number(d.id);
    if (feld === 'mitgliedschaft') return STAND_REIHE[standVon(d)] + '|' + (d.mitgliedsart || '');
    return String(d[feld] || '').toLowerCase();
  };
  function sortieren() {
    const vgl = (a, b) => {
      const x = sortWert(a, sortFeld), y = sortWert(b, sortFeld);
      const r = typeof x === 'number' ? x - y : String(x).localeCompare(String(y), 'de');
      if (r) return r * sortRichtung;
      return String(a.dataset.name).localeCompare(b.dataset.name, 'de') || String(a.dataset.vorname).localeCompare(b.dataset.vorname, 'de');
    };
    zeilen().sort(vgl).forEach(tr => $tbody[0].appendChild(tr));
    $tbody.find('tr.mv-leer').appendTo($tbody);
    $('#mitgliederTable thead th[data-sort]').each(function() {
      this.setAttribute('aria-sort', this.dataset.sort === sortFeld ? (sortRichtung > 0 ? 'ascending' : 'descending') : 'none');
      $(this).find('.mv-sort .bi').attr('class', this.dataset.sort === sortFeld
        ? (sortRichtung > 0 ? 'bi bi-sort-down-alt' : 'bi bi-sort-up') : 'bi bi-arrow-down-up');
    });
  }
  $('#mitgliederTable thead').on('click', '.mv-sort', function(e) {
    e.stopPropagation();
    const feld = $(this).closest('th').data('sort');
    sortRichtung = feld === sortFeld ? -sortRichtung : 1;
    sortFeld = feld;
    sortieren();
    buildMobileCards();
  });

  // Neues oder geändertes Mitglied anspringen und kurz hervorheben
  function markiere(id) {
    const tr = document.getElementById('row' + id);
    if (!tr) return;
    tr.scrollIntoView({ block: 'center' });
    $(tr).addClass('row-saved');
    setTimeout(() => $(tr).removeClass('row-saved'), 1800);
  }

  // ========== Neues Mitglied ==========
  $('#btnNewMember').on('click', () => $('#newMemberModal').modal('show'));
  $('#newMemberModal').on('shown.bs.modal', () => $('#nmName').trigger('focus'));
  $('#newMemberModal').on('hidden.bs.modal', () => fehlerWeg('#nmFehler'));

  function neuSenden(trotzdem) {
    const $btn = $('#nmSubmit');
    const daten = $('#newMemberForm').serialize() + (trotzdem ? '&trotzdem=1' : '');
    msvAusgabeLaden($btn[0], true);
    $.post(basePath + 'mitgliederverwaltung/add_mitglied.php', daten, null, 'json')
      .done(function(r) {
        msvToast(r.message || 'Mitglied hinzugefügt', 'success');
        $('#newMemberModal').modal('hide');
        $('#newMemberForm')[0].reset();
        $('#nmKontakt').collapse('hide');
        suche = ''; $('.mv-suche').val('');
        setzeStand('mitglied');
        loadMitglieder().then(() => markiere(r.id));
      })
      .fail(async function(xhr) {
        const r = xhr.responseJSON || {};
        if (r.duplikat) {
          const wahl = await msvConfirm('Es gibt schon <strong>' + msvEsc(r.duplikat.name) + '</strong> mit demselben Geburtsdatum (Lizenz ' + msvEsc(r.duplikat.id) + '). Ist es eine andere Person?',
            'Mitglied doppelt?', 'Trotzdem anlegen', 'Zurück');
          if (wahl.isConfirmed) neuSenden(true);
          return;
        }
        if (!(r.fehler && zeigeFehler('#nmFehler', r.fehler, NEU_FELDER))) msvToast(msvXhrMessage(xhr, 'Das Mitglied konnte nicht angelegt werden.'), 'error');
      })
      .always(() => msvAusgabeLaden($btn[0], false));
  }
  $('#newMemberForm').on('submit', function(e) {
    e.preventDefault();
    const vorher = pruefeVorher(NEU_FELDER);
    if (Object.keys(vorher).length) { zeigeFehler('#nmFehler', vorher, NEU_FELDER); return; }
    fehlerWeg('#nmFehler');
    neuSenden(false);
  });

  // ========== Löschen ==========
  // Löschen nur ohne Geschichte: erst fragen, was am Mitglied hängt (Resultate, Einsätze, Konto …).
  // Mit Geschichte ist Löschen gesperrt und «Als ausgetreten markieren» der Weg; ohne wird vorher gesichert.
  $('#panelDeleteBtn').on('click', async function() {
    const deleteId = $(this).data('id');
    const tr = document.getElementById('row' + deleteId);
    const name = tr ? ((tr.dataset.vorname || '') + ' ' + (tr.dataset.name || '')).trim() : '';
    const url = basePath + 'mitgliederverwaltung/delete_mitglied.php';
    // Offene Änderungen zuerst klären (Speichern / Verwerfen / Zurück), dann prüfen
    let weiter = false;
    await MVPanel.schuetze(() => { weiter = true; });
    if (!weiter) return;
    MVPanel.close();
    let p;
    try { p = await $.post(url, { id: deleteId, aktion: 'pruefen', csrf_token: CSRF }, null, 'json'); }
    catch (xhr) { msvToast(msvXhrMessage(xhr, 'Die Prüfung vor dem Löschen hat nicht geklappt.'), 'error'); return; }
    if (p.gesperrt) {
      const austreten = await msvLoeschenGesperrt({
        text: '<strong>' + msvEsc(name) + '</strong> hat Daten im Verein. Löschen würde das Mitglied aus früheren Ranglisten und Listen entfernen, darum ist es gesperrt.',
        bezuege: p.bezuege,
        hinweis: p.aktiv ? 'Wer nicht mehr dabei ist, wird als ausgetreten markiert: Das Mitglied fällt aus den aktuellen Listen, seine Resultate bleiben.' : 'Das Mitglied ist bereits als ausgetreten markiert.',
        alternative: p.aktiv ? 'Als ausgetreten markieren' : ''
      });
      if (austreten) msvPost(url, { id: deleteId, aktion: 'inaktiv' }, r => { msvToast(r.message || 'Als ausgetreten markiert', 'success'); loadMitglieder(); }, { csrf: CSRF });
      return;
    }
    const res = await msvConfirmDelete(name, {
      html: 'Möchtest du <strong>' + msvEsc(name) + '</strong> wirklich löschen? Es hängen keine Resultate, Einsätze oder Konten daran. Vorher wird die Datenbank gesichert.'
    });
    if (!res.isConfirmed) return;
    msvPost(url, { id: deleteId, aktion: 'loeschen' }, r => {
      msvToast((r.message || 'Mitglied gelöscht') + '. Die Datenbank wurde vorher gesichert.', 'success');
      loadMitglieder();
    }, { csrf: CSRF, failMsg: 'Das Mitglied konnte nicht gelöscht werden.' });
  });

  // ========== CSV-Import: Prüflauf, dann Übernehmen ==========
  let importDatei = null;
  const dz = document.getElementById('dropZone');
  dz.addEventListener('click', () => document.getElementById('csvFile').click());
  dz.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); document.getElementById('csvFile').click(); } });
  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); }));
  ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('dragging')));
  ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('dragging')));
  dz.addEventListener('drop', e => dateiGewaehlt(e.dataTransfer.files));
  $('#csvFile').on('change', e => dateiGewaehlt(e.target.files));
  $(document).on('click', '.mv-vorlage', e => { e.preventDefault(); $('.csv-export-btn').first().trigger('click'); });

  function importSenden(aktion) {
    const fd = new FormData();
    fd.append('datei', importDatei);
    fd.append('aktion', aktion);
    fd.append('csrf_token', CSRF);
    return $.ajax({ url: basePath + 'mitgliederverwaltung/import_csv.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' });
  }

  function importZuruecksetzen() {
    importDatei = null;
    $('#csvFile').val('');
    $('#importErgebnis').prop('hidden', true);
    $('#confirmImport').prop('hidden', true);
    $('#dropZone').removeClass('mv-kompakt');
    $('#dropZoneText').text('CSV-Datei hier ablegen oder klicken');
  }

  function dateiGewaehlt(files) {
    if (!files || !files.length) return;
    const f = files[0];
    if (!/\.(csv|txt)$/i.test(f.name)) { msvToast('Bitte eine CSV-Datei wählen (in Excel: Speichern unter › CSV).', 'warning'); return; }
    importDatei = f;
    $('#dropZoneText').text('«' + f.name + '» wird geprüft …');
    $('#importErgebnis').prop('hidden', true);
    $('#confirmImport').prop('hidden', true);
    importSenden('pruefen')
      .done(zeigePruefung)
      .fail(xhr => { msvToast(msvXhrMessage(xhr, 'Die Datei konnte nicht geprüft werden.'), 'error'); importZuruecksetzen(); });
  }

  function zeigePruefung(r) {
    $('#dropZoneText').text('«' + importDatei.name + '» – andere Datei wählen');
    $('#dropZone').addClass('mv-kompakt'); // Platz für das Ergebnis, der Übernehmen-Knopf bleibt in Sicht
    const fehler = r.fehler || [];
    $('#importZahlen').html(
      '<span class="ui-chip"><b>' + r.neu + '</b> neu</span>'
      + '<span class="ui-chip"><b>' + r.geaendert + '</b> geändert</span>'
      + '<span class="ui-chip"><b>' + r.unveraendert + '</b> unverändert</span>'
      + (fehler.length ? '<span class="ui-chip"><span class="ui-punkt" aria-hidden="true"></span><b>' + fehler.length + '</b> mit Fehler</span>' : ''));
    $('#importHinweis').text('Gelesen: ' + (r.spalten || []).join(', ') + '.'
      + ((r.ignoriert || []).length ? ' Nicht verwendet: ' + r.ignoriert.join(', ') + '.' : '')
      + (r.kodierung && r.kodierung !== 'UTF-8' ? ' Umlaute aus einer Excel-Datei (Windows) umgewandelt.' : ''));
    const liste = r.aenderungen || [];
    $('#importDiff tbody').html(liste.length ? liste.map(a => '<tr><td>' + msvEsc(a.id) + '</td><td>' + msvEsc(a.name) + '</td><td>'
        + (a.art === 'neu' ? '<span class="ui-chip">neues Mitglied</span>'
          : a.felder.map(f => msvEsc(f[0]) + ': <del>' + msvEsc(f[1]) + '</del> → <ins>' + msvEsc(f[2]) + '</ins>').join('<br>'))
        + '</td></tr>').join('')
      : '<tr><td colspan="3" class="text-muted text-center py-3">Keine Änderungen gegenüber den gespeicherten Mitgliedern.</td></tr>');
    if (fehler.length) {
      $('#importFehler').html('<strong>' + (fehler.length === 1 ? 'Diese Zeile wird' : 'Diese ' + fehler.length + ' Zeilen werden') + ' übersprungen:</strong><ul class="mb-0 ps-3">'
        + fehler.slice(0, 20).map(t => '<li>' + msvEsc(t) + '</li>').join('') + (fehler.length > 20 ? '<li>…</li>' : '') + '</ul>').prop('hidden', false);
    } else {
      $('#importFehler').prop('hidden', true).empty();
    }
    const n = r.neu + r.geaendert;
    $('#confirmImport').prop('hidden', n === 0).find('span').text(n === 1 ? '1 Änderung übernehmen' : n + ' Änderungen übernehmen');
    $('#importErgebnis').prop('hidden', false);
  }

  $('#confirmImport').on('click', function() {
    if (!importDatei) return;
    const btn = this;
    msvAusgabeLaden(btn, true);
    importSenden('uebernehmen')
      .done(r => {
        msvToast(r.message || 'Import übernommen', (r.fehler || []).length ? 'warning' : 'success');
        $('#importModal').modal('hide');
        loadMitglieder();
      })
      .fail(xhr => msvToast(msvXhrMessage(xhr, 'Der Import ist fehlgeschlagen; es wurde nichts geändert.'), 'error'))
      .always(() => msvAusgabeLaden(btn, false));
  });

  $('#importModal').on('hidden.bs.modal', importZuruecksetzen);

  // ========== Mobile Karten ==========
  function buildMobileCards() {
    if (!window.matchMedia('(max-width: 767.98px)').matches) return;
    const container = document.getElementById('mobileMitgliederCards');
    if (!container) return;
    // dataset liefert dekodierte Werte → für innerHTML escapen
    container.innerHTML = sichtbareZeilen().map(tr => {
      const d = tr.dataset;
      return `
      <div class="mobile-card" data-member-id="${msvEsc(d.id)}">
        <button type="button" class="mobile-card-header mv-karte-kopf" aria-expanded="false">
          <span class="d-block">
            <span class="fw-bold d-block">${msvEsc(d.name)} ${msvEsc(d.vorname)}</span>
            <small class="text-muted d-block">Lizenz ${msvEsc(d.id)}${d.waffe ? ' · ' + msvEsc(d.waffe) : ''}</small>
            <span class="d-block mt-1">${mvZelleMitgliedschaft(d)}</span>
          </span>
          <i class="bi bi-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="mobile-card-body">
          <p class="mb-1"><strong>Geb.:</strong> ${msvEsc(d.geburtsdatum ? d.geburtsdatum.split('-').reverse().join('.') : '–')}</p>
          <p class="mb-1"><strong>E-Mail:</strong> ${msvEsc(d.email || '–')}</p>
          <p class="mb-1"><strong>Telefon:</strong> ${msvEsc(d.telefon || '–')}</p>
          <p class="mb-1"><strong>Mobile:</strong> ${msvEsc(d.mobile || '–')}</p>
          <p class="mb-2"><strong>Adresse:</strong> ${msvEsc([d.strasse, [d.plz, d.ort].filter(Boolean).join(' ')].filter(Boolean).join(', ') || '–')}</p>
          <button type="button" class="btn btn-outline-primary btn-sm w-100 mobile-edit-btn" data-id="${msvEsc(d.id)}">
            <i class="bi bi-pencil me-1" aria-hidden="true"></i>Bearbeiten
          </button>
        </div>
      </div>`;
    }).join('') || '<div class="mobile-cards-empty"><i class="bi bi-inbox" aria-hidden="true"></i><div>Keine Mitglieder in dieser Auswahl</div></div>';
  }

  $(document).on('click', '.mv-karte-kopf', function() { MSVMobileCards.toggle(this); });
  $(document).on('click', '.mobile-edit-btn', function(e) {
    e.stopPropagation();
    const tr = document.getElementById('row' + $(this).data('id'));
    if (tr) MVPanel.open(tr);
  });
  window.matchMedia('(max-width: 767.98px)').addEventListener('change', () => buildMobileCards());

  // ========== Ausgaben ==========
  // Ausgabe-Baustein msvAusgabe: sperren, Spinner, Download, Toast
  const heute = new Date().toISOString().slice(0, 10);
  $(document).on('click', '.xlsx-export-btn', function(e) {
    e.preventDefault();
    msvAusgabe(this, {
      url: basePath + 'mitgliederverwaltung/generate_mitglieder_xlsx.php',
      linkPrefix: basePath + 'mitgliederverwaltung/',
      titel: 'Adressliste (Excel)',
      name: 'Mitglieder_Adressliste_' + heute,
      fehler: 'Die Adressliste konnte nicht erstellt werden. Bitte nochmals versuchen.'
    });
  });
  $(document).on('click', '.csv-export-btn', function(e) {
    e.preventDefault();
    msvAusgabe(this, {
      url: basePath + 'mitgliederverwaltung/export_csv.php',
      titel: 'Mitglieder (CSV)',
      name: 'Mitglieder_' + heute,
      fehler: 'Der CSV-Export ist fehlgeschlagen. Bitte nochmals versuchen.'
    });
  });

  // ========== Start ==========
  loadMitglieder();
});
</script>

<?php include 'footer.inc.php'; ?>
