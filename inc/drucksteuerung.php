<?php
// drucksteuerung.php – Drucksteuerung: Verbindung zu QZ Tray, Drucker dieses Browsers, Druckprofile, Protokoll.
// Seit 1.68.52 in der Reihenfolge der Einrichtung (Verbindung → Drucker → Profile), Drucker im Slide-Panel,
// Dokumentkatalog gemeinsam mit dem Direktdruck (js/msv-druck-dokumente.js). Logik: js/app-drucksteuerung.js.
// Seit 1.68.58 Einstieg für Erstnutzer: Schrittleiste in der Kopf-Card (Verbindung → Drucker → Dokumente → Testseite)
// mit dem nächsten Schritt als Knopf, «Alle Dokumente einrichten» in einem Zug, Leerzustände je Verbindungsstand.

$page_specific_css = <<<'CSS'
.dr-stapel { display: flex; flex-direction: column; gap: 14px; }
.dr-kopf-rechts { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.dr-innen { padding: 14px var(--ui-pad); }
.dr-stapel h2[tabindex="-1"]:focus { outline: none; }

/* Einrichtung: Schrittleiste in der Kopf-Card (Muster der Einsatzplanung, 1.68.56) */
.dr-ablauf-zeile { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; flex: 1 1 100%; width: 100%; }
.dr-ablauf { display: flex; flex-wrap: wrap; align-items: center; row-gap: 6px; margin: 0; padding: 0; list-style: none; flex: 1 1 0; min-width: 0; }
.dr-ablauf > li { display: flex; align-items: center; }
.dr-ablauf > li + li::before { content: ""; width: 10px; height: 1px; margin: 0 3px; flex-shrink: 0; background: var(--ui-feldrand); }
.dr-schritt { display: flex; align-items: flex-start; gap: 8px; padding: 4px 8px 4px 4px; border: 1px solid transparent; border-radius: 8px; background: transparent; text-align: left; color: var(--ui-text); transition: background-color .15s, border-color .15s; }
.dr-schritt:hover { background: var(--ui-flaeche-2); border-color: var(--ui-rand); }
.dr-schritt-nr { flex: 0 0 22px; height: 22px; margin-top: 1px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%;
  border: 1px solid var(--ui-feldrand); background: var(--ui-flaeche); color: var(--ui-text-2); font-size: .75rem; font-weight: 700; }
.dr-schritt.ist-fertig .dr-schritt-nr { background: var(--ui-ok-bg); border-color: var(--ui-ok-rand); color: var(--ui-ok-fg); }
.dr-schritt.ist-jetzt .dr-schritt-nr { background: var(--ui-akzent-dunkel); border-color: var(--ui-akzent-dunkel); color: var(--ui-flaeche); }
.dr-schritt-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
.dr-schritt-name { font-size: .85rem; font-weight: 600; white-space: nowrap; }
.dr-schritt.ist-jetzt .dr-schritt-name { color: var(--ui-akzent-dunkel); }
.dr-schritt-stand { font-size: .8rem; color: var(--ui-text-2); white-space: nowrap; }
.dr-ablauf-weiter { margin-left: auto; display: flex; align-items: center; gap: 8px; min-height: 28px; }
.dr-ablauf-weiter .ui-status { font-size: .85rem; }
@media (max-width: 767.98px) {
  .dr-ablauf { flex: 1 1 100%; flex-direction: column; align-items: flex-start; row-gap: 2px; }
  .dr-ablauf > li + li::before { display: none; }
  .dr-ablauf-weiter { margin-left: 0; }
}

/* Verbindung */
.ui-status.fehler { color: var(--ui-fehler); } .ui-status.fehler .ui-punkt { background: var(--ui-fehler); }
.ui-status.aus { color: var(--ui-text-2); } .ui-status.aus .ui-punkt { background: var(--ui-leer); }
.dr-verbindung { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 16px; }
.dr-verbindung .ui-status { font-size: 0.9rem; }
.dr-hinweis { margin: 12px 0 0; font-size: 0.85rem; }
.dr-hinweis p { margin: 0 0 6px; } .dr-hinweis p:last-child { margin-bottom: 0; }
.dr-hinweis .btn { margin-top: 4px; }

/* Drucker */
.dr-matrix-kopf.dr-drucker-zeile,
.dr-drucker-zeile { grid-template-columns: minmax(0, 2fr) 120px minmax(0, 1fr) 76px; }
.dr-drucker-zeile { display: grid; gap: 10px; align-items: center; padding: 9px var(--ui-pad); border-bottom: 1px solid var(--ui-linie-zart); }
#druckerListe .dr-drucker-zeile:last-child { border-bottom: 0; }
.dr-drucker-name { font-weight: 600; font-size: 0.85rem; overflow-wrap: anywhere; }
.dr-drucker-system { display: block; font-size: 0.78rem; font-weight: 400; color: var(--ui-text-2); }
.dr-drucker-zeile .dr-aktionen { display: flex; gap: 4px; justify-content: flex-end; }

/* Profilliste */
.dr-matrix-kopf,
.dr-zeile {
    display: grid;
    grid-template-columns: minmax(170px, 1.7fr) minmax(150px, 1.6fr) 104px 64px minmax(150px, 1.3fr) 120px 70px;
    gap: 10px; align-items: center; padding: 9px var(--ui-pad);
    border-bottom: 1px solid var(--ui-linie-zart);
}
.dr-matrix-kopf {
    padding-top: 7px; padding-bottom: 7px; background: var(--ui-flaeche-2); border-bottom-color: var(--ui-linie);
    font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ui-text-2);
}
.dr-gruppe-knopf {
    width: 100%; display: flex; align-items: center; gap: 8px; padding: 8px var(--ui-pad);
    border: 0; border-bottom: 1px solid var(--ui-linie); background: var(--ui-flaeche-2); text-align: left;
    font: inherit; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ui-text-2);
}
.dr-gruppe-knopf:hover { background: var(--ui-hover); color: var(--ui-text); }
.dr-gruppe-knopf .dr-chevron { transition: transform 0.15s ease-out; }
.dr-gruppe-knopf[aria-expanded="false"] .dr-chevron { transform: rotate(-90deg); }
.dr-gruppe-zahl { margin-left: auto; font-weight: 500; text-transform: none; letter-spacing: 0; }
.dr-zeile:hover { background: var(--ui-flaeche-2); }
.dr-zeile.dr-geaendert { background: var(--ui-gewaehlt); }
.dr-zeile.dr-kopiert { background: var(--ui-akzent-hell); transition: background 0.6s ease-out; }
.dr-name { font-weight: 600; font-size: 0.85rem; }
.dr-desc { display: block; margin-top: 1px; font-size: 0.78rem; color: var(--ui-text-2); }
.dr-punkt { display: inline-block; width: 7px; height: 7px; margin-left: 6px; border-radius: 50%; background: var(--ui-akzent); vertical-align: middle; }
.dr-warn { display: block; margin-top: 3px; font-size: 0.78rem; color: var(--ui-warn-fg); }
.dr-format { font-size: 0.8rem; color: var(--ui-text-2); white-space: nowrap; }
.dr-zeile .form-select-sm, .dr-zeile .form-control-sm { font-size: 0.8rem; }
.dr-zeile .dr-aktionen { display: flex; gap: 4px; justify-content: flex-end; }
.dr-leer { padding: 1.5rem var(--ui-pad); text-align: center; color: var(--ui-text-2); }
.dr-leer > .bi, .dr-leer > [role="cell"] > .bi { display: block; margin-bottom: 0.4rem; font-size: 1.6rem; color: var(--ui-text-3); }
.dr-leer p { margin: 0 0 8px; } .dr-leer p:last-child { margin-bottom: 0; }
.dr-leer strong { color: var(--ui-text); }
.dr-leer .btn { margin: 4px 0 10px; }

/* Alle Dokumente in einem Zug einrichten (über der Profilliste) */
.dr-schnell { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; padding: 12px var(--ui-pad); border-bottom: 1px solid var(--ui-linie); background: var(--ui-akzent-hell); }
.dr-schnell-text { flex: 1 1 22rem; min-width: 0; font-size: 0.85rem; color: var(--ui-akzent-tief); }
.dr-schnell-text strong { display: block; font-size: 0.9rem; }
.dr-schnell-aktion { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.dr-schnell-aktion .form-select { width: auto; max-width: 16rem; font-size: 0.8rem; }
.dr-schnell-drucker { font-size: 0.85rem; font-weight: 600; color: var(--ui-akzent-tief); }
.dr-fuss {
    position: sticky; bottom: 0; z-index: 2; display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
    padding: 10px var(--ui-pad); background: var(--ui-flaeche); border-top: 1px solid var(--ui-linie);
    border-radius: 0 0 var(--ui-rad-l) var(--ui-rad-l);
}
.dr-fuss-text { margin-right: auto; font-size: 0.85rem; color: var(--ui-text-2); }

/* Protokoll */
.dr-log { max-height: 280px; overflow-y: auto; }
.dr-log-zeile { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; padding: 6px var(--ui-pad); border-bottom: 1px solid var(--ui-linie-zart); font-size: 0.8rem; }
.dr-log-zeile:last-child { border-bottom: 0; }
.dr-log-typ { font-weight: 600; white-space: nowrap; }
.dr-log-datei, .dr-log-drucker { color: var(--ui-text-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 260px; }
.dr-log-zeit { margin-left: auto; color: var(--ui-text-2); white-space: nowrap; }
.dr-log-fehler { flex-basis: 100%; padding-left: 14px; color: var(--ui-fehler); font-size: 0.78rem; }

.dr-stapel, .dr-stapel > .ui-karte, .dr-zeile, .dr-zeile > * { min-width: 0; }

@media (max-width: 767.98px) {
    .dr-matrix-kopf { display: none; }
    .dr-zeile { grid-template-columns: 1fr; gap: 6px; padding: 12px; }
    .dr-zeile > [data-label] { display: flex; align-items: center; gap: 8px; }
    .dr-zeile > [data-label] > * { flex: 1 1 auto; min-width: 0; }
    .dr-zeile > [data-label]::before { content: attr(data-label); flex: 0 0 84px; font-size: 0.72rem; color: var(--ui-text-2); }
    .dr-zeile > [data-label="Dokument"]::before { display: none; }
    .dr-zeile .dr-aktionen { justify-content: flex-start; }
    .dr-drucker-zeile { grid-template-columns: minmax(0, 1fr) auto; padding: 10px 12px; }
    .dr-drucker-zeile > :nth-child(3) { display: none; }
    .dr-drucker-zeile .dr-aktionen { grid-column: 2; grid-row: 1 / span 2; }
    .dr-log-datei { display: none; }
}
CSS;

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';

// Nur Admin + Vorstand (nach header.inc.php, da dieser user_role setzt)
if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'vorstand'])) {
    ob_end_clean();
    header('Location: home.php');
    exit();
}
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-default">
        <?php
        // Schrittleiste: Stand und nächster Schritt setzt js/app-drucksteuerung.js (zeigeAblauf); hier nur das Gerüst ohne Sprung beim Laden
        $drSchritte = ['verbindung' => 'Verbindung', 'drucker' => 'Drucker', 'dokumente' => 'Dokumente', 'test' => 'Testseite'];
        $page_extra = '<div class="dr-ablauf-zeile"><ol class="dr-ablauf" id="drAblauf" aria-label="Einrichtung des Direktdrucks">';
        $drNr = 0;
        foreach ($drSchritte as $key => $name) {
            $page_extra .= '<li><button type="button" class="dr-schritt" data-schritt="' . $key . '">'
                . '<span class="dr-schritt-nr" aria-hidden="true">' . (++$drNr) . '</span>'
                . '<span class="dr-schritt-text"><span class="dr-schritt-name">' . $name . '</span><span class="dr-schritt-stand">…</span></span></button></li>';
        }
        $page_extra .= '</ol><div class="dr-ablauf-weiter" id="drWeiter"></div></div>';
        $page_show_mobile = true; // die Schrittleiste gehört auch auf dem Handy dazu
        $page_title = 'Drucksteuerung';
        $page_actions = '<button type="button" class="btn-help" data-help="drucksteuerung.uebersicht" aria-label="Hilfe"></button>';
        include 'partials/page_header.inc.php';
        ?>

        <div class="dr-stapel">

          <!-- Verbindung zu QZ Tray -->
          <section class="ui-karte" aria-labelledby="drVerbindungTitel">
            <div class="ui-tab-kopf">
              <h2 class="ui-tab-titel mb-0" tabindex="-1" id="drVerbindungTitel">Verbindung <button type="button" class="btn-help" data-help="drucksteuerung.qz" aria-label="Hilfe zur Verbindung"></button></h2>
              <div class="dr-kopf-rechts">
                <span class="ui-chip" id="drArbeitsplatz" tabindex="0" data-tooltip="Drucker und Profile gelten für diesen Browser."><i class="bi bi-laptop" aria-hidden="true"></i>Gilt für diesen Browser</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnConnect" hidden><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Neu verbinden</button>
              </div>
            </div>
            <div class="dr-innen">
              <div class="dr-verbindung">
                <span class="ui-status aus" id="qzStatus" role="status" aria-live="polite"><span class="ui-punkt" aria-hidden="true"></span><span id="qzStatusText">Verbinde mit QZ Tray …</span></span>
              </div>
              <div class="alert alert-warning dr-hinweis" id="qzHinweis" role="status" hidden></div>
              <p class="small text-muted mt-2 mb-0" id="drSetupZeile" hidden></p>
              <div class="alert alert-info dr-hinweis" id="drAndere" hidden></div>
            </div>
          </section>

          <!-- Drucker dieses Browsers -->
          <section class="ui-karte" aria-labelledby="drDruckerTitel">
            <div class="ui-tab-kopf">
              <h2 class="ui-tab-titel mb-0" tabindex="-1" id="drDruckerTitel">Drucker <button type="button" class="btn-help" data-help="drucksteuerung.drucker" aria-label="Hilfe zu den Druckern"></button></h2>
              <div class="dr-kopf-rechts">
                <button type="button" class="btn btn-outline-success btn-sm" id="btnDruckerNeu"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Drucker übernehmen</button>
              </div>
            </div>
            <div role="table" aria-labelledby="drDruckerTitel">
              <div role="rowgroup" id="drDruckerKopf"><div class="dr-matrix-kopf dr-drucker-zeile" role="row">
                <span role="columnheader">Drucker</span><span role="columnheader">Status</span><span role="columnheader">Genutzt von</span><span role="columnheader"><span class="visually-hidden">Aktionen</span></span>
              </div></div>
              <div role="rowgroup" id="druckerListe"><div class="dr-leer">Drucker werden geladen …</div></div>
            </div>
          </section>

          <!-- Druckprofile -->
          <section class="ui-karte" aria-labelledby="drProfileTitel">
            <div class="ui-tab-kopf">
              <h2 class="ui-tab-titel mb-0" tabindex="-1" id="drProfileTitel">Druckprofile <button type="button" class="btn-help" data-help="drucksteuerung.profile" aria-label="Hilfe zu den Druckprofilen"></button></h2>
              <div class="dr-kopf-rechts">
                <span class="ui-chip msv-ungespeichert" id="drUngespeichert" hidden><span class="ui-punkt" aria-hidden="true"></span><span id="drUngespeichertText">Nicht gespeichert</span></span>
              </div>
            </div>
            <div class="dr-schnell" id="drSchnell" hidden></div>
            <div id="profileMatrix" role="table" aria-labelledby="drProfileTitel" aria-describedby="drProfilFussText"></div>
            <div class="dr-fuss" id="drProfilFuss">
              <span class="dr-fuss-text" id="drProfilFussText">Profile werden geladen …</span>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="btnVerwerfen" hidden>Änderungen verwerfen</button>
              <button type="button" class="btn btn-primary btn-sm" id="btnSpeichern"><i class="bi bi-save me-1" aria-hidden="true"></i>Speichern</button>
            </div>
          </section>

          <!-- Druckprotokoll -->
          <section class="ui-karte" aria-labelledby="drLogTitel">
            <div class="ui-tab-kopf">
              <h2 class="ui-tab-titel mb-0" tabindex="-1" id="drLogTitel">Druckprotokoll</h2>
              <div class="dr-kopf-rechts">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLogNeu" aria-label="Druckprotokoll aktualisieren" data-tooltip="Aktualisieren"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
              </div>
            </div>
            <div class="dr-log" id="printLogBody" aria-live="polite">
              <div class="dr-leer">Protokoll wird geladen …</div>
            </div>
          </section>

        </div>
      </div>
    </div>
  </div>
</div>

<?php
// Slide-Panel: Drucker übernehmen / bearbeiten
ob_start(); ?>
  <input type="hidden" id="druckerId">
  <div class="mb-3" id="druckerWahlFeld">
    <label for="druckerSystem" class="form-label">Drucker auf diesem Computer</label>
    <select id="druckerSystem" class="form-select form-select-sm" aria-describedby="druckerSystemHinweis"></select>
    <div class="form-text" id="druckerSystemHinweis">Die Liste kommt live von QZ Tray.</div>
  </div>
  <div class="mb-3" id="druckerNameFeld" hidden>
    <span class="form-label d-block">Drucker auf diesem Computer</span>
    <span id="druckerSystemText" class="fw-semibold"></span>
  </div>
  <div class="mb-3">
    <label for="druckerAnzeige" class="form-label">Anzeigename</label>
    <input type="text" id="druckerAnzeige" class="form-control form-control-sm" maxlength="255" aria-describedby="druckerAnzeigeHinweis">
    <div class="form-text" id="druckerAnzeigeHinweis">So erscheint der Drucker in den Profilen, z.&nbsp;B. «Laser Büro». Leer = Name des Druckers.</div>
  </div>
  <div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" role="switch" id="druckerAktiv" checked>
    <label class="form-check-label" for="druckerAktiv">Aktiv</label>
    <div class="form-text mt-0">Ein inaktiver Drucker steht nicht mehr zur Wahl; Profile, die ihn nutzen, behalten ihn.</div>
  </div>
<?php
$panel_id = 'druckerPanel';
$panel_overlay_id = 'druckerOverlay';
$panel_close_id = 'druckerClose';
$panel_width = '420px';
$panel_title = '<span id="druckerPanelTitel">Drucker übernehmen</span>';
$panel_body = ob_get_clean();
$panel_footer = '<div class="d-flex justify-content-end gap-2 w-100">'
    . '<button type="button" class="btn btn-outline-secondary btn-sm" id="druckerAbbrechen">Abbrechen</button>'
    . '<button type="button" class="btn btn-primary btn-sm" id="druckerSpeichern"><i class="bi bi-save me-1" aria-hidden="true"></i>Speichern</button>'
    . '</div>';
include 'partials/side_panel.inc.php';
?>

<!-- QZ Tray -->
<script src="js/lib/rsvp.min.js"></script>
<script src="js/lib/sha-256.min.js"></script>
<script src="js/lib/qz-tray.js"></script>
<script src="js/print-manager.js?v=<?php echo @filemtime(__DIR__ . '/js/print-manager.js') ?: '1'; ?>"></script>
<script src="js/msv-druck-dokumente.js?v=<?php echo @filemtime(__DIR__ . '/js/msv-druck-dokumente.js') ?: '1'; ?>"></script>
<script src="js/app-drucksteuerung.js?v=<?php echo @filemtime(__DIR__ . '/js/app-drucksteuerung.js') ?: '1'; ?>"></script>

<?php include 'footer.inc.php'; ?>
