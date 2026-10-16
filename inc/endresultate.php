<?php
// endresultate.php – Endschiessen auf einer Seite (seit 1.68.29), zwei Ansichten derselben Tabelle:
//   «Erfassen» (Standard): Teilnehmer nach Name, Rohwerte, Stand, Erfassung im Slide-Panel
//   «Rangliste» (?ansicht=rangliste): nach Kat. A, Kat. B und «Ohne Wertung», Prüfliste «Absenden vorbereiten»
//   und alle Dokumente (vorher endschrang.php, das hierher weiterleitet). Bearbeiten geht in beiden Ansichten.
// Rang und Total kommen aus endschGesamtAbfrage() (dieselbe Rechnung wie die Gesamtrangliste im PDF).
$__ansicht = (($_GET['ansicht'] ?? '') === 'rangliste') ? 'rangliste' : 'erfassen';
try {
    include 'dbconnect.inc.php';
} catch (Exception $e) {
    error_log("Include error in endresultate.php: " . $e->getMessage());
    die("System error. Please try again later.");
}

// Seitenspezifische Styles: nur noch Aufbau dieser Seite; Optik kommt aus css/msv-ui.css
$page_specific_css = "
/* Fensterhöhe (.ui-vollhoehe, .ui-scroll) und Erfassungs-Panel (.ui-erfassen) kommen aus css/msv-ui.css */
#mitgliederTabelle { margin: 0; border-collapse: separate; border-spacing: 0; }
#mitgliederTabelle thead th { position: sticky; top: 0; z-index: 2; padding: 8px 10px; white-space: nowrap; text-align: right; }
#mitgliederTabelle tbody td { height: 40px; padding: 0 10px; white-space: nowrap; text-align: right; vertical-align: middle; border-bottom: 1px solid var(--ui-linie-zart); }
#mitgliederTabelle thead th:first-child, #mitgliederTabelle tbody td:first-child { text-align: left; padding-left: 20px; }
#mitgliederTabelle tbody td:first-child { font-weight: 600; }
#mitgliederTabelle thead th:last-child, #mitgliederTabelle tbody td:last-child { text-align: left; padding-right: 20px; }
#mitgliederTabelle tbody tr.hybrid-row { cursor: pointer; }
#mitgliederTabelle tbody tr.hybrid-row:hover > td { background: var(--ui-flaeche-2); }
#mitgliederTabelle tbody tr.ui-leer td { height: auto; padding: 32px 16px; text-align: center; color: var(--ui-text-2); white-space: normal; cursor: default; }
/* Gäste und Jungschützen (seit Migration 094 in derselben Liste) – gleiche Marke wie auf «Stiche lösen» */
.typ-badge { display: inline-block; margin-left: 6px; padding: 1px 6px; border-radius: 6px; background: var(--ui-linie-zart); color: var(--ui-text-2); font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; vertical-align: middle; }
.typ-badge.js { background: var(--ui-akzent-hell); color: var(--ui-akzent-dunkel); }
/* Sie und Er: Partnerwahl und Schüsse der Partnerin/des Partners (nur Anzeige) */
#sieunderSchuesse .su-partner { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
#sieunderSchuesse .su-partner label { margin: 0; font-size: .85rem; color: var(--ui-text-2); }
#sieunderSchuesse .su-partner select { width: auto; min-width: 220px; max-width: 100%; }
#sieunderSchuesse .su-partner-schuesse { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 8px; font-size: .85rem; color: var(--ui-text-2); }
#sieunderSchuesse .su-schuss { display: inline-flex; align-items: center; justify-content: center; min-width: 28px; height: 26px; padding: 0 6px; border: 1px solid var(--ui-linie); border-radius: 6px; background: var(--ui-flaeche-2); color: var(--ui-text); font-weight: 600; }
#sieunderSchuesse .su-schuss.is-dup { text-decoration: line-through; color: var(--ui-fehler); }

/* Rang je Kategorie vor dem Namen («A 3.»), Total; ohne Wertung steht in der Ansicht «Rangliste» der Grund */
.es-rang { display: inline-block; width: 2.6rem; font-size: .8rem; font-weight: 600; color: var(--ui-text-2); }
.es-rang:empty::before { content: '–'; font-weight: 400; color: var(--ui-leer); }
.es-grund { display: none; margin-left: 8px; font-size: .78rem; font-weight: 400; color: var(--ui-text-3); }
#mitgliederTabelle tbody td.es-total { font-weight: 700; color: var(--ui-text); }
#mitgliederTabelle tbody tr.es-gruppe td { height: auto; padding: 6px 20px; text-align: left; background: var(--ui-grund); color: var(--ui-text-2); font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid var(--ui-linie); cursor: default; }

/* Ansichten: «Erfassen» füllt das Fenster (nur die Tabelle), «Rangliste» scrollt normal mit Prüfliste und Dokumenten */
.es-ansicht-erfassen .es-nur-rangliste, .es-ansicht-rangliste .es-nur-erfassen { display: none !important; }
.es-ansicht-erfassen .es-seite, .es-ansicht-erfassen .es-raster { display: contents; }
.es-ansicht-rangliste .es-grund { display: inline; }
.es-ansicht-rangliste #mitgliederTabelle tbody tr.rank-1 > td { background: color-mix(in srgb, var(--ui-gold-bg) 40%, var(--ui-flaeche)); }
.es-ansicht-rangliste #mitgliederTabelle tbody tr.rank-2 > td { background: color-mix(in srgb, var(--ui-silber-bg) 40%, var(--ui-flaeche)); }
.es-ansicht-rangliste #mitgliederTabelle tbody tr.rank-3 > td { background: color-mix(in srgb, var(--ui-bronze-bg) 40%, var(--ui-flaeche)); }
.es-ansicht-rangliste #mitgliederTabelle tbody tr:is(.rank-1, .rank-2, .rank-3) .es-rang { color: var(--ui-text); font-weight: 700; }

/* Ansicht «Rangliste»: links Prüfliste und Tabelle, rechts alle Dokumente in einer Leiste (aus endschrang.php).
   Erst ab 1040px Inhaltsbreite zweispaltig, damit die Spalten der Tabelle Platz behalten. */
.es-seite { container-type: inline-size; margin-bottom: 14px; }
.es-raster { display: grid; grid-template-columns: minmax(0, 1fr); grid-template-areas: \"pruef\" \"doks\" \"rang\"; gap: 14px 16px; align-items: start; }
.es-absenden { grid-area: pruef; }
.es-doks { grid-area: doks; }
.es-raster > #endresultateForm { grid-area: rang; margin-bottom: 0 !important; }
@container (min-width: 1040px) {
    .es-raster { grid-template-columns: minmax(0, 1fr) 17rem; grid-template-rows: auto 1fr; grid-template-areas: \"pruef doks\" \"rang doks\"; }
}
.es-abs-stand { margin-left: auto; }
.es-abs-liste { list-style: none; margin: 0; padding: 14px var(--ui-pad) 16px; display: grid; gap: 10px; }
.es-abs-liste li { display: flex; align-items: baseline; gap: 6px 12px; flex-wrap: wrap; }
.es-abs-liste .ui-status { min-width: 6.5rem; }
.es-abs-liste .ui-status:not(.ok):not(.offen) { color: var(--ui-text-2); }
.es-abs-liste .ui-status:not(.ok):not(.offen) .ui-punkt { background: var(--ui-text-3); }
.es-abs-liste a { font-weight: 600; white-space: nowrap; }
.es-abs-zeit { margin-left: 8px; font-size: .75rem; color: var(--ui-text-2); white-space: nowrap; }
.es-doks-inhalt { display: grid; gap: 16px; padding: 14px 16px 16px; }
.es-dok-label { margin: 0 0 6px; font-size: .72rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--ui-text-2); }
.es-abs-hinweis { margin: -2px 0 8px; font-size: .8rem; line-height: 1.35; color: var(--ui-warn-fg); }
.es-buchstand { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--ui-linie); font-size: .8rem; line-height: 1.4; }
.es-buchstand-titel { display: flex; align-items: center; gap: 4px; margin: 0 0 6px; font-weight: 600; color: var(--ui-text); }
.es-buchstand-lauf { margin: 0 0 6px; color: var(--ui-text-2); font-variant-numeric: tabular-nums; }
.es-buchstand-lauf:has(#absBuchLauf:empty) { display: none; }
.es-buchdatei { display: flex; flex-wrap: wrap; align-items: center; gap: 2px 8px; margin: 0 0 6px; }
.es-buchdatei-art { font-weight: 600; color: var(--ui-text); }
.es-buchdatei-meta { color: var(--ui-text-2); font-variant-numeric: tabular-nums; }
.es-buchdatei-weg { color: var(--ui-warn-fg); }
.es-buchdatei a { font-weight: 600; }
.es-buchdatei .msv-druck { padding: 0 .4rem; line-height: 1.4; }
.es-buchpunkte-kopf { margin: 8px 0 4px; }
.es-buchpunkte { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
.es-buchpunkte li { color: var(--ui-text); }
.es-buchpunkte a { font-weight: 600; white-space: nowrap; }
.es-buchpunkte .es-erledigt { color: var(--ui-ok-fg); }
.es-dok-weitere { border-top: 1px solid var(--ui-linie); padding-top: 12px; }
.es-dok-weitere > summary { list-style: none; display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: .85rem; font-weight: 600; color: var(--ui-text); border-radius: 6px; }
.es-dok-weitere > summary::-webkit-details-marker { display: none; }
.es-dok-weitere > summary:hover { color: var(--ui-akzent-dunkel); }
.es-dok-weitere > summary:focus-visible { outline: 2px solid var(--ui-akzent); outline-offset: 2px; }
.es-dok-weitere > summary > i { color: var(--ui-text-3); transition: transform .15s ease-out; }
.es-dok-weitere[open] > summary > i { transform: rotate(90deg); }
.es-dok-anzahl { font-weight: 400; color: var(--ui-text-2); font-variant-numeric: tabular-nums; }
.es-dok-weitere-inhalt { display: grid; gap: 16px; margin-top: 12px; }
@media (prefers-reduced-motion: reduce) { .es-dok-weitere > summary > i { transition: none; } }
.es-dok-liste { display: flex; flex-wrap: wrap; gap: 6px 8px; }
.es-dok-liste > .btn-group > .btn:first-child { text-align: left; }
.es-dok-liste > .btn-group > .msv-druck { flex: 0 0 2.25rem; }
@container (min-width: 1040px) {
    .es-dok-liste { flex-direction: column; flex-wrap: nowrap; }
    .es-dok-liste > .btn-group { width: 100%; }
    .es-dok-liste > .btn-group > .btn:first-child { flex: 1 1 auto; }
}

@media (max-width: 767.98px) {
    .desktop-table-container { display: none !important; }
    .mobile-cards-container { display: flex !important; }
    .mobile-card-detail-row { padding: 0.75rem 0 !important; border-bottom: 1px solid var(--ui-linie-zart) !important; }
    .mobile-card-detail-label { font-size: 0.875rem !important; color: var(--ui-text-2) !important; font-weight: 500 !important; }
    .mobile-card-detail-value { font-size: 1rem !important; color: var(--ui-text) !important; }
    .mobile-card-body .btn { min-height: 48px !important; font-size: 1rem !important; }
}
@media (min-width: 768px) {
    .mobile-cards-container { display: none !important; }
}
";

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
$csrf = csrf_token();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-wide es-ansicht-<?= $__ansicht ?><?= $__ansicht === 'erfassen' ? ' ui-vollhoehe' : '' ?>" id="esSeite">
                <?php
                $page_title = $__ansicht === 'rangliste' ? 'Endschiessen Rangliste' : 'Endschiessen Resultate erfassen';
                $page_title_after = '<button type="button" class="btn-help" data-help="endresultate.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>'
                    . '<div class="ui-filter" role="group" aria-label="Ansicht" id="esAnsicht">'
                    . '<button type="button" data-ansicht="erfassen" aria-pressed="' . ($__ansicht === 'erfassen' ? 'true' : 'false') . '">Erfassen</button>'
                    . '<button type="button" data-ansicht="rangliste" aria-pressed="' . ($__ansicht === 'rangliste' ? 'true' : 'false') . '">Rangliste</button>'
                    . '</div>';
                $page_actions = '<a href="endsch_import.php" class="btn btn-outline-success btn-sm es-nur-erfassen"><i class="bi bi-upload me-1"></i>CSV importieren</a>';
                $page_extra = '<div class="ui-fortschritt" aria-live="polite"><span class="ui-zahl" id="progressText">–</span>'
                    . '<span class="ui-balken" aria-hidden="true"><span id="progressBar"></span></span></div>'
                    . '<div class="ui-chips" id="progressChips"></div>';
                $page_show_mobile = true;
                include 'partials/page_header.inc.php';
                ?>

                <div class="es-seite"><div class="es-raster">
                <!-- Ansicht «Rangliste»: Absenden vorbereiten (Prüfliste) -->
                <section class="ui-karte es-absenden es-nur-rangliste" aria-labelledby="absTitel">
                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel" id="absTitel"><i class="bi bi-flag me-1" aria-hidden="true"></i>Absenden vorbereiten <button type="button" class="btn-help" data-help="endschrang.absenden" aria-label="Hilfe"></button></span>
                        <span class="es-abs-stand" id="absStand" aria-live="polite"></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="absNeu" aria-label="Prüfliste neu prüfen" data-tooltip="Neu prüfen"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
                    </div>
                    <ul class="es-abs-liste" id="absListe" aria-label="Prüfliste">
                        <li data-pruefung="stiche"><span class="ui-status"><span class="ui-punkt"></span>…</span><span>Stiche werden geprüft</span></li>
                        <li data-pruefung="partner"><span class="ui-status"><span class="ui-punkt"></span>…</span><span>Sie und Er wird geprüft</span></li>
                        <li data-pruefung="wanderpreise"><span class="ui-status"><span class="ui-punkt"></span>…</span><span>Wanderpreise werden geprüft</span></li>
                        <li data-pruefung="siegerlisten"><span class="ui-status"><span class="ui-punkt"></span>…</span><span>Siegerlisten werden geprüft</span></li>
                        <li data-pruefung="buch"><span class="ui-status"><span class="ui-punkt"></span>…</span><span>Weitere Teile des Absendenbuchs werden geprüft</span></li>
                    </ul>
                </section>
                <!-- Ansicht «Rangliste»: alle Dokumente in einer Leiste (rechts, über die ganze Höhe) -->
                <aside class="ui-karte es-doks es-nur-rangliste" aria-labelledby="doksTitel">
                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel" id="doksTitel"><i class="bi bi-file-earmark-arrow-down me-1" aria-hidden="true"></i>Dokumente <button type="button" class="btn-help" data-help="endschrang.dokumente" aria-label="Hilfe"></button></span>
                    </div>
                    <div class="es-doks-inhalt">
                        <div role="group" aria-labelledby="dokAbsenden">
                            <p class="es-dok-label" id="dokAbsenden">Fürs Absenden</p>
                            <p class="es-abs-hinweis" id="absDokHinweis" hidden></p>
                            <div class="es-dok-liste">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Absendenbuch (Word)">
                                    <button type="button" class="btn btn-outline-info abs-btn"><i class="bi bi-file-earmark-word me-1" aria-hidden="true"></i><span>Absendenbuch (Word)</span></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Absendenbuch (Broschüre)">
                                    <button type="button" class="btn btn-outline-info absbk-btn" aria-describedby="absbkHinweis" data-tooltip="Das Absendenbuch als PDF zum Falten: je zwei A5-Seiten auf A4 quer. Beidseitig drucken, an der kurzen Kante wenden."><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Absendenbuch (Broschüre)</span></button>
                                    <span class="visually-hidden" id="absbkHinweis">Das Absendenbuch als PDF zum Falten: je zwei A5-Seiten auf A4 quer. Beidseitig drucken, an der kurzen Kante wenden.</span>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="absendenbuch" data-druck-label="Absendenbuch (Broschüre)" aria-label="Absendenbuch (Broschüre) direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Gesamtrangliste">
                                    <button type="button" class="btn btn-outline-info ges-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Gesamtrangliste</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_gesamt.php" data-druck-job="Endschiessen Gesamtrangliste" aria-label="Gesamtrangliste direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                            </div>
                            <!-- Stand des zuletzt erstellten Absendenbuchs (aus endschrang/absenden_bereit.php: buchstand) -->
                            <section class="es-buchstand" id="absBuchstand" aria-labelledby="absBuchstandTitel" hidden>
                                <p class="es-buchstand-titel" id="absBuchstandTitel">Stand des Absendenbuchs <button type="button" class="btn-help" data-help="endschrang.buchstand" aria-label="Hilfe"></button></p>
                                <p class="es-buchstand-lauf"><span id="absBuchLauf" role="status" aria-live="polite"></span> <span id="absBuchUhr" aria-hidden="true"></span></p>
                                <div id="absBuchDateien"></div>
                                <div id="absBuchPunkte"></div>
                            </section>
                        </div>
                        <!-- Seltener Gebrauchtes eingeklappt (Zustand pro Browser gemerkt); «Fürs Absenden» bleibt offen -->
                        <details class="es-dok-weitere" id="esDokWeitere">
                        <summary><i class="bi bi-chevron-right" aria-hidden="true"></i>Weitere Dokumente <span class="es-dok-anzahl">11</span></summary>
                        <div class="es-dok-weitere-inhalt">
                        <div role="group" aria-labelledby="dokUebersicht">
                            <p class="es-dok-label" id="dokUebersicht">Übersicht</p>
                            <div class="es-dok-liste">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Zwischenrangliste">
                                    <button type="button" class="btn btn-outline-info zwi-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Zwischenrangliste</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_zwischenrangliste.php" data-druck-job="Endschiessen Zwischenrangliste" aria-label="Zwischenrangliste direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Anmeldungen">
                                    <button type="button" class="btn btn-outline-info anm-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Anmeldungen</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_anmeldung.php" data-druck-job="Endschiessen Anmeldung" aria-label="Anmeldungen direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                            </div>
                        </div>
                        <div role="group" aria-labelledby="dokEinzel">
                            <p class="es-dok-label" id="dokEinzel">Einzelwettbewerbe</p>
                            <div class="es-dok-liste">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Alle Einzelwettbewerbe">
                                    <button type="button" class="btn btn-outline-info einzel-btn" data-tooltip="Endstich, Schwini, Kunst, Glück, Zabig und Differenzler in einem PDF, jede Rangliste auf einer eigenen Seite."><i class="bi bi-files me-1" aria-hidden="true"></i><span>Alle Einzelwettbewerbe</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_einzelwettbewerbe.php" data-druck-job="Endschiessen Einzelwettbewerbe" aria-label="Alle Einzelwettbewerbe direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Endstich">
                                    <button type="button" class="btn btn-outline-info end-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Endstich</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_end.php" data-druck-job="Endschiessen Endstich" aria-label="Endstich direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Schwini">
                                    <button type="button" class="btn btn-outline-info sch-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Schwini</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_schwini.php" data-druck-job="Endschiessen Schwini" aria-label="Schwini direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Kunst">
                                    <button type="button" class="btn btn-outline-info kun-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Kunst</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_kunst.php" data-druck-job="Endschiessen Kunst" aria-label="Kunst direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Glück">
                                    <button type="button" class="btn btn-outline-info glu-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Glück</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_glueck.php" data-druck-job="Endschiessen Glück" aria-label="Glück direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Zabig">
                                    <button type="button" class="btn btn-outline-info zab-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Zabig</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_zabig.php" data-druck-job="Endschiessen Zabig" aria-label="Zabig direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Differenzler">
                                    <button type="button" class="btn btn-outline-info dif-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Differenzler</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_diff.php" data-druck-job="Endschiessen Differenzler" aria-label="Differenzler direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                            </div>
                        </div>
                        <div role="group" aria-labelledby="dokPartner">
                            <p class="es-dok-label" id="dokPartner">Partner</p>
                            <div class="es-dok-liste">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Partner">
                                    <button type="button" class="btn btn-outline-info part-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Partner</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_partner.php" data-druck-job="Endschiessen Partner" aria-label="Partner direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Sie &amp; Er">
                                    <button type="button" class="btn btn-outline-info sieer-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Sie &amp; Er</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_sieer.php" data-druck-job="Endschiessen Sie und Er" aria-label="Sie &amp; Er direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                            </div>
                        </div>
                        </div>
                        </details>
                    </div>
                </aside>

                <form id="endresultateForm" class="ui-karte ui-vollhoehe-karte" onsubmit="return false">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="ui-tab-kopf">
                        <span class="ui-tab-titel">Teilnehmer <button type="button" class="btn-help es-nur-rangliste" data-help="endschrang.wertung" aria-label="Hilfe zur Wertung"></button></span>
                        <div class="ui-filter es-nur-erfassen" role="group" aria-label="Nach Stand filtern" id="esFilter">
                            <button type="button" data-filter="alle" aria-pressed="true">Alle <span id="nAlle">0</span></button>
                            <button type="button" data-filter="offen" aria-pressed="false">Offen <span id="nOffen">0</span></button>
                            <button type="button" data-filter="ok" aria-pressed="false">Vollständig <span id="nOk">0</span></button>
                        </div>
                        <label class="ui-suche d-none d-md-flex">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <span class="visually-hidden">Name suchen</span>
                            <input type="search" id="endSuche" placeholder="Name suchen" autocomplete="off">
                        </label>
                    </div>

                    <!-- Desktop: Tabelle -->
                    <div class="desktop-table-container">
                        <div class="ui-scroll end-scroll" id="endScroll" tabindex="0" data-zeilen-tastatur aria-label="Teilnehmer. Mit den Pfeiltasten wählen, Enter öffnet die Erfassung.">
                            <table class="table mb-0" id="mitgliederTabelle">
                                <thead>
                                    <tr>
                                        <th scope="col">Teilnehmer</th>
                                        <th scope="col">Endstich</th>
                                        <th scope="col">Schwini</th>
                                        <th scope="col">Kunst</th>
                                        <th scope="col">Glück</th>
                                        <th scope="col">Zabig</th>
                                        <th scope="col">Sie und Er</th>
                                        <th scope="col">Ansage</th>
                                        <th scope="col" data-tooltip="Wie in der Gesamtrangliste: Endstich, bester Schwini, Kunst / 10, Glück / 10, Zabig-Punkte">Total</th>
                                        <th scope="col">Stand</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="ui-leer">
                                        <td colspan="10"><div class="spinner-border spinner-border-sm me-2"></div>Lade Daten …</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile: Cards -->
                    <div class="mobile-cards-container" id="mobileCardsEnd">
                        <div class="mobile-search">
                            <div class="position-relative">
                                <i class="bi bi-search search-icon"></i>
                                <input type="search" class="form-control" placeholder="Name suchen..." aria-label="Teilnehmer suchen"
                                       oninput="filterMobileEnd(this)">
                            </div>
                        </div>
                        <div class="mobile-cards-scroll">
                            <!-- Cards werden per JavaScript generiert -->
                        </div>
                    </div>

                    <div class="ui-tasten d-none d-md-flex">
                        <span><kbd>↑</kbd> <kbd>↓</kbd> wählen</span>
                        <span><kbd>Enter</kbd> erfassen</span>
                        <span>im Panel: <kbd>Enter</kbd> nächstes Feld</span>
                        <span><kbd>Ctrl</kbd>+<kbd>S</kbd> speichern</span>
                        <span><kbd>Ctrl</kbd>+<kbd>Enter</kbd> speichern &amp; nächstes offenes</span>
                    </div>
                </form>
                </div></div><!-- /es-raster, /es-seite -->
            </div>
        </div>
    </div>
</div>

<!-- Panel Overlay -->
<div class="panel-overlay" id="panelOverlay"></div>

<!-- Slide-Panel -->
<div class="hybrid-edit-panel ui-erfassen" id="editPanel" style="--panel-width: 600px;">
    <div class="panel-header">
        <div class="min-w-0">
            <h6 class="mb-0" id="panelTitle">Erfassen</h6>
            <small class="panel-pos" id="panelSubtitle"></small>
        </div>
        <div class="d-flex align-items-center gap-1 ms-auto">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelPrev" data-tooltip="Vorherige Person" aria-label="Vorherige Person">
                <i class="bi bi-chevron-up" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelNext" data-tooltip="Nächste Person" aria-label="Nächste Person">
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="panelClose" data-tooltip="Schliessen (Esc)" aria-label="Schliessen (Esc)">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="panel-body" id="panelBody">
        <input type="hidden" id="teilnehmer" name="teilnehmer">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <!-- Endstich -->
        <div class="shot-section shot-first" id="endstichSchuesse" data-stich="END">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-bullseye"></i>Endstich <button type="button" class="btn-help" data-help="endresultate.endstich" aria-label="Hilfe"></button></span>
                <span class="shot-status">Nicht gelöst</span>
                <span class="shot-total" id="endstichSumme">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-row">
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=10; $i++): ?>
                        <input type="number" class="shot-input endschuss focusable-input" id="Schuss<?= $i ?>" name="Schuss<?= $i ?>" aria-label="Endstich Schuss <?= $i ?>" min="0" max="10" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="shot-row">
                    <span class="shot-row-label">Tiefschuss</span>
                    <input type="number" class="shot-input shot-input-wide focusable-input" id="Tiefschuss" name="Tiefschuss" aria-label="Endstich Tiefschuss" min="0" max="100" inputmode="numeric">
                </div>
            </div>
        </div>

        <!-- Schwini -->
        <div class="shot-section" id="schwiniSchuesse" data-stich="SCHWINI">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-piggy-bank"></i>Schwini <button type="button" class="btn-help" data-help="endresultate.schwini" aria-label="Hilfe"></button></span>
                <span class="shot-status">Nicht gelöst</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-row schwini-passe-1">
                    <span class="shot-row-label">Passe 1</span>
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=6; $i++): ?>
                        <input type="number" class="shot-input schwini-schuss1 focusable-input" id="P1Schuss<?= $i ?>" name="P1Schuss<?= $i ?>" aria-label="Schwini Passe 1, Schuss <?= $i ?>" min="0" max="10" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                    <span class="shot-status">Nicht gelöst</span>
                    <span class="shot-total" id="schwiniSumme1">0</span>
                </div>
                <div class="shot-row schwini-passe-2">
                    <span class="shot-row-label">Passe 2</span>
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=6; $i++): ?>
                        <input type="number" class="shot-input schwini-schuss2 focusable-input" id="P2Schuss<?= $i ?>" name="P2Schuss<?= $i ?>" aria-label="Schwini Passe 2, Schuss <?= $i ?>" min="0" max="10" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                    <span class="shot-status">Nicht gelöst</span>
                    <span class="shot-total" id="schwiniSumme2">0</span>
                </div>
            </div>
        </div>

        <!-- Kunst + Glück -->
        <div class="shot-cols">
            <div class="shot-section" id="kunstSchuesse" data-stich="KUNST">
                <div class="shot-section-head">
                    <span class="shot-section-title"><i class="bi bi-palette"></i>Kunst</span>
                    <span class="shot-status">Nicht gelöst</span>
                    <span class="shot-total" id="kunstSum">0</span>
                </div>
                <div class="shot-section-body">
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=5; $i++): ?>
                        <input type="number" class="shot-input kunst focusable-input" id="KSchuss<?= $i ?>" name="KSchuss<?= $i ?>" aria-label="Kunst Schuss <?= $i ?>" min="0" max="100" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <div class="shot-section" id="glueckSchuesse" data-stich="GLUECK">
                <div class="shot-section-head">
                    <span class="shot-section-title"><i class="bi bi-clover"></i>Glück</span>
                    <span class="shot-status">Nicht gelöst</span>
                </div>
                <div class="shot-section-body">
                    <div class="shot-grid">
                        <?php for ($i=1; $i<=3; $i++): ?>
                        <input type="number" class="shot-input glueck focusable-input" id="GSchuss<?= $i ?>" name="GSchuss<?= $i ?>" aria-label="Glück Schuss <?= $i ?>" min="0" max="100" inputmode="numeric">
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zabig -->
        <div class="shot-section" id="zabigSchuesse" data-stich="ZABIG">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-moon-stars"></i>Zabig</span>
                <span class="shot-status">Nicht gelöst</span>
                <span class="shot-total" id="zabigsum">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-grid">
                    <?php for ($i=1; $i<=6; $i++): ?>
                    <input type="number" class="shot-input zabig focusable-input" id="ZSchuss<?= $i ?>" name="ZSchuss<?= $i ?>" aria-label="Zabig Schuss <?= $i ?>" min="0" max="100" inputmode="numeric">
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Sie und Er -->
        <div class="shot-section" id="sieunderSchuesse" data-stich="SIEUNDER">
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-people"></i>Sie und Er <span class="shot-hint">fünf eigene Schüsse</span> <button type="button" class="btn-help" data-help="endresultate.sieunder" aria-label="Hilfe"></button></span>
                <span class="shot-status">Nicht gelöst</span>
                <span class="shot-total" id="uniqueTotal" data-tooltip="Total des Paars: jeder Wert zählt einmal">0</span>
            </div>
            <div class="shot-section-body">
                <div class="shot-grid">
                    <?php for ($i=1; $i<=5; $i++): ?>
                    <input type="number"
                           class="shot-input shot-input-dec sie-er-schuss sie-er-mitglied focusable-input"
                           id="SieErSchuss<?= $i ?>"
                           name="SieErSchuss<?= $i ?>" aria-label="Sie und Er Schuss <?= $i ?>"
                           data-position="<?= $i ?>"
                           min="0" max="10"
                           placeholder="<?= $i ?>"
                           inputmode="numeric">
                    <?php endfor; ?>
                </div>
                <div class="su-partner">
                    <label for="SieUnderPartner">Partnerin / Partner</label>
                    <select class="form-select form-select-sm focusable-input" id="SieUnderPartner" name="SieUnderPartner">
                        <option value="">– noch nicht gewählt –</option>
                    </select>
                </div>
                <div class="su-partner-schuesse" id="suPartnerSchuesse" hidden></div>
                <div class="shot-hint mt-2">Zur Auswahl steht, wer Sie und Er gelöst hat – Mitglied oder Gast. Die Verknüpfung gilt für beide. Jeder Wert zählt im Paar nur einmal, Doppelte sind rot durchgestrichen.</div>
            </div>
        </div>

        <!-- Ansage + Absenden -->
        <div class="shot-cols">
            <div class="shot-section" id="Differenzler">
                <div class="shot-section-head">
                    <span class="shot-section-title"><i class="bi bi-chat-square-text"></i>Ansage <span class="shot-hint">Differenzler</span> <button type="button" class="btn-help" data-help="endresultate.ansage" aria-label="Hilfe"></button></span>
                    <span class="shot-status">Nicht gelöst</span>
                </div>
                <div class="shot-section-body">
                    <input type="number" class="shot-input shot-input-wide focusable-input" id="Ansage" name="Ansage" aria-label="Ansage (Differenzler)" min="0" max="999" inputmode="numeric">
                </div>
            </div>
            <div class="shot-section" id="Absendenanmeldung">
                <div class="shot-section-head">
                    <span class="shot-section-title"><i class="bi bi-calendar-check"></i>Absenden</span>
                </div>
                <div class="shot-section-body">
                    <input type="text" class="form-control form-control-sm focusable-input" id="AbsendenAnmeldung" name="AbsendenAnmeldung" aria-label="Absenden: Anmeldung" placeholder="Anmeldung">
                </div>
            </div>
        </div>

        <!-- Partner-Rangliste: Mitglied zusätzlich aufführen (nur Mitglieder, Migration 096) -->
        <div class="shot-section" id="partnerrangSection" hidden>
            <div class="shot-section-head">
                <span class="shot-section-title"><i class="bi bi-person-heart"></i>Partner-Rangliste <button type="button" class="btn-help" data-help="endresultate.partnerrang" aria-label="Hilfe"></button></span>
            </div>
            <div class="shot-section-body">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="Partnerrang" name="Partnerrang" value="1">
                    <label class="form-check-label" for="Partnerrang">Als Partnerin bzw. Partner zusätzlich in der Partner-Rangliste aufführen</label>
                </div>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <div class="alert alert-danger small py-2 px-3 msv-eingabe-fehler" id="panelFehler" role="alert" hidden></div>
        <div class="d-flex gap-2 w-100 align-items-center">
            <button type="button" class="btn btn-outline-danger btn-sm" id="panelDeleteBtn" data-tooltip="Resultate dieser Person löschen" aria-label="Resultate dieser Person löschen">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
            <span class="small text-muted me-auto d-none d-md-inline"><kbd class="ui-kbd">Ctrl</kbd>+<kbd class="ui-kbd">Enter</kbd> nächstes offenes</span>
            <button type="button" class="btn btn-outline-primary btn-sm" id="panelSaveBtn">
                <i class="bi bi-save me-1"></i>Speichern
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="panelSaveNextBtn">
                Speichern &amp; nächstes offenes <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    // =========================================
    //  EndEditPanel – Slide-Panel Steuerung
    // =========================================
    const EndEditPanel = {
        currentKey: null,          // Teilnehmer-Schlüssel m<ID> (Mitglied) bzw. g<ID> (Gast/Jungschütze)
        sieUnder: null,            // Kandidaten und Schüsse für Sie und Er (aus load_schussdaten)
        allRows: [],
        currentIndex: -1,
        _loadingXhr: null,
        geaendert: false,

        // «Nicht gespeichert» merken und im Panelkopf zeigen
        setGeaendert(an) {
            this.geaendert = !!an;
            msvPanelUngespeichert($('#panelSubtitle').parent(), this.geaendert);
        },

        // Vor Schliessen oder Wechseln: Ungespeichertes nie still verwerfen. weiter() läuft nach
        // «Verwerfen» oder nach erfolgreichem Speichern; bei «Zurück» bleibt das Panel, wie es ist.
        async schuetze(weiter) {
            if (!this.geaendert) { weiter(); return; }
            const wahl = await msvUngespeichert({ wer: $('#panelTitle').text().trim() });
            if (wahl === 'verwerfen') { this.setGeaendert(false); weiter(); }
            else if (wahl === 'speichern') this.save(weiter);
        },

        versucheSchliessen() { this.schuetze(() => this.close()); },

        open(key) {
            this.currentKey = key;
            this.currentIndex = this.allRows.findIndex(r => r.id === key);

            // Zeile markieren
            $('.hybrid-row').removeClass('selected');
            if (this.currentIndex >= 0) {
                const $row = $(this.allRows[this.currentIndex].tr);
                $row.addClass('selected');
                this.allRows[this.currentIndex].tr.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }

            // Titel setzen
            const zeile = this.currentIndex >= 0 ? this.allRows[this.currentIndex] : null;
            const name = zeile ? $(zeile.tr).find('.tn-name').text().trim() : '';
            const typText = zeile && zeile.typ === 'gast' ? 'Gast · ' : (zeile && zeile.typ === 'js' ? 'Jungschütze/-in · ' : '');
            $('#panelTitle').text(name || 'Erfassen');
            $('#panelSubtitle').text(typText + (this.currentIndex + 1) + ' von ' + this.allRows.length);

            // Navigation
            $('#panelPrev').prop('disabled', this.currentIndex <= 0);
            $('#panelNext').prop('disabled', this.currentIndex >= this.allRows.length - 1);

            // Hidden Field
            $('#teilnehmer').val(key);

            // Form zurücksetzen
            this.resetForm();

            // Panel öffnen
            $('#editPanel').addClass('open');
            $('#panelOverlay').addClass('show');

            // Panel-Body nach oben scrollen
            $('#panelBody').scrollTop(0);

            // Daten laden
            this.loadSchussdaten(key);
        },

        close() {
            this.setGeaendert(false);
            msvEingabeFehler('#panelFehler', []);
            $('#editPanel').removeClass('open');
            $('#panelOverlay').removeClass('show');
            $('.hybrid-row').removeClass('selected');
            this.currentKey = null;
            this.currentIndex = -1;
            if (this._loadingXhr) {
                this._loadingXhr.abort();
                this._loadingXhr = null;
            }
        },

        resetForm() {
            this.setGeaendert(false);
            $('#editPanel [aria-invalid]').removeAttr('aria-invalid');
            msvEingabeFehler('#panelFehler', []);
            // Alle Inputs leeren
            $('#editPanel .focusable-input').val('');
            $('#editPanel .shot-input').val('').removeClass('filled is-unique is-dup');
            $('#editPanel .shot-total').text('0');
            this.sieUnder = null;
            $('#Partnerrang').prop('checked', false);
            $('#partnerrangSection').prop('hidden', true);
            $('#SieUnderPartner').find('option:not(:first)').remove();
            $('#suPartnerSchuesse').prop('hidden', true).empty();
            $('.schwini-passe-1, .schwini-passe-2').removeClass('schwini-pass-disabled');

            // Alle Stiche deaktivieren
            $('.shot-section[data-stich], .shot-section#Differenzler').addClass('disabled')
                .find('input').prop('disabled', true).val('');

            // Absenden immer aktiv
            $('#Absendenanmeldung').removeClass('disabled')
                .find('input').prop('disabled', false);
            // Absenden-Feld selbst (falls es kein Kind-Input hat, sondern selbst das Feld ist)
            $('#AbsendenAnmeldung').prop('disabled', false);
        },

        loadSchussdaten(key) {
            const year = $('#yearSelect').val();

            // Vorherigen Request abbrechen
            if (this._loadingXhr) this._loadingXhr.abort();

            this._loadingXhr = $.ajax({
                url: 'endschresultate/load_schussdaten.php',
                type: 'GET',
                data: { teilnehmer: key, year: year },
                dataType: 'json',
                success: function(data) {
                    // Immer aufrufen: ohne gelöste Stiche bleibt nur Absenden offen, die Enter-Navigation braucht es trotzdem
                    updateStichAvailability(data.geloesteStiche || []);

                    $('#partnerrangSection').prop('hidden', data.teilnehmerTyp !== 'mitglied');
                    $('#Partnerrang').prop('checked', String(data.Partnerrang) === '1');
                    for (var feld in data) {
                        if (['geloesteStiche', 'sieUnder', 'teilnehmer', 'teilnehmerTyp', 'Partnerrang'].indexOf(feld) === -1) {
                            var $field = $('#' + feld);
                            if ($field.length) $field.val(data[feld]);
                        }
                    }
                    sieUnderAnzeigen(data.sieUnder || null);

                    calculateAllSums();
                    updateSieErUniqueVisualization();
                    refreshFilled();

                    setTimeout(function() {
                        $('#editPanel .focusable-input:not(:disabled):first').focus().select();
                    }, 300);
                },
                error: function(xhr) {
                    if (xhr.statusText !== 'abort') {
                        msvToast('Fehler beim Laden der Schussdaten', 'error');
                    }
                },
                complete: function() {
                    EndEditPanel._loadingXhr = null;
                }
            });
        },

        navigate(direction) {
            const newIndex = this.currentIndex + direction;
            if (newIndex < 0 || newIndex >= this.allRows.length) return;
            const id = this.allRows[newIndex].id;
            this.schuetze(() => this.open(id));
        },

        save(callback) {
            // Unplausible Werte zuerst korrigieren lassen (der Server prüft ebenfalls)
            if (msvEingabeFehler('#panelFehler', msvPruefeFelder('#editPanel'))) return;
            const $saveBtn = $('#panelSaveBtn');
            const $saveNextBtn = $('#panelSaveNextBtn');
            const originalSave = $saveBtn.html();
            const originalNext = $saveNextBtn.html();

            $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
            $saveNextBtn.prop('disabled', true);

            const formData = this.collectFormData();

            $.ajax({
                url: 'endschresultate/save_schuss.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(resp) {
                    if (!resp || resp.success !== true) {
                        msvToast('Nicht gespeichert: ' + ((resp && resp.message) || 'unbekannter Fehler') + '. Die Eingaben sind noch da.', 'error');
                        return;
                    }
                    EndEditPanel.setGeaendert(false);
                    msvToast('Resultate gespeichert' + (resp.geleert && resp.geleert.length ? ' – geleert: ' + resp.geleert.join(', ') : ''), 'success');
                    if (callback) {
                        // Tabelle neu laden, Panel bleibt offen, danach weiter
                        loadData($('#yearSelect').val(), callback);
                    } else {
                        EndEditPanel.close();
                        loadData($('#yearSelect').val());
                    }
                },
                error: function(xhr) {
                    msvToast('Nicht gespeichert: ' + msvXhrMessage(xhr, 'Serverfehler').replace(/\.?$/, '.') + ' Die Eingaben sind noch da.', 'error');
                },
                complete: function() {
                    $saveBtn.prop('disabled', false).html(originalSave);
                    $saveNextBtn.prop('disabled', false).html(originalNext);
                }
            });
        },

        saveAndNext() {
            const gespeichert = this.currentKey;
            this.save(function() {
                // Nach dem Neuladen: nächstes Mitglied mit offenen Stichen, nach dem gespeicherten beginnend
                const rows = EndEditPanel.allRows;
                const i = rows.findIndex(r => r.id === gespeichert);
                const reihe = i >= 0 ? rows.slice(i + 1).concat(rows.slice(0, i)) : rows;
                const naechstes = reihe.find(r => r.stand === 'offen');
                if (naechstes) {
                    EndEditPanel.open(naechstes.id);
                } else {
                    msvToast('Alle gelösten Stiche sind erfasst', 'success');
                    EndEditPanel.close();
                }
            });
        },

        collectFormData() {
            const data = {
                teilnehmer: $('#teilnehmer').val(),
                jahr: $('#yearSelect').val(),
                csrf_token: $('#editPanel input[name="csrf_token"]').val()
            };

            // Alle benannten Inputs aus dem Panel sammeln
            $('#editPanel input[name]:not([type="hidden"]):not([type="checkbox"]), #editPanel select[name]').each(function() {
                if (!this.disabled) {
                    data[this.name] = $(this).val() || '';
                }
            });
            if (!$('#partnerrangSection').prop('hidden')) {
                data.Partnerrang = $('#Partnerrang').is(':checked') ? 1 : 0;
            }

            return data;
        },

        buildRowIndex() {
            this.allRows = [];
            $('#mitgliederTabelle tbody tr.hybrid-row').each((_, tr) => {
                const hasData = String($(tr).data('has-data')) === '1';
                this.allRows.push({
                    id: String($(tr).attr('data-teilnehmer')),
                    typ: $(tr).attr('data-typ') || 'mitglied',
                    hasData: hasData,
                    stand: $(tr).attr('data-stand') || (hasData ? 'ok' : 'offen'),
                    tr: tr
                });
            });
        },

        updateProgress() {
            const total = this.allRows.length;
            const ok = this.allRows.filter(r => r.stand === 'ok').length;
            const offen = total - ok;
            const pct = total > 0 ? Math.round((ok / total) * 100) : 0;
            $('#progressText').html(ok + ' von ' + total + ' <span>vollständig erfasst</span>');
            $('#progressBar').css('width', pct + '%');
            $('#progressChips').html(
                '<span class="ui-chip"><b>' + total + '</b> Teilnehmer mit gelösten Stichen</span>' +
                (offen ? '<span class="ui-chip"><span class="ui-punkt"></span><b>' + offen + '</b> mit offenen Stichen</span>' : '')
            );
            $('#nAlle').text(total);
            $('#nOffen').text(offen);
            $('#nOk').text(ok);
        }
    };

    // =========================================
    //  Jahr-Dropdown
    // =========================================
    function initializeYearDropdown() {
        msvJahrAuswahl('#yearSelect', { jahr: <?php echo isset($_GET['year']) ? (int)$_GET['year'] : 'null'; ?> });
    }

    // =========================================
    //  Daten laden
    // =========================================
    function loadData(year, nachher) {
        var $tbody = $('#mitgliederTabelle tbody');
        if (!nachher) {
            EndEditPanel.close();
            $tbody.html('<tr class="ui-leer"><td colspan="10"><div class="spinner-border spinner-border-sm me-2"></div>Lade Daten …</td></tr>');
        }

        $.ajax({
            url: 'endschresultate/load_endschresultate.php',
            type: 'GET',
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                esUrsprung = $tbody.children().toArray();
                sortieren();   // Reihenfolge der Ansicht, Zeilenindex, Fortschritt, Filter
                buildMobileEndCards();
                if (esAnsicht === 'rangliste' && window.absendenPruefen) window.absendenPruefen();
                if (typeof nachher === 'function') nachher();
            },
            error: function(xhr) {
                $tbody.html('<tr class="ui-leer"><td colspan="10" class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Die Daten konnten nicht geladen werden: ' + msvEsc(msvXhrMessage(xhr, 'Serverfehler')) + '</td></tr>');
            }
        });
    }

    // =========================================
    //  Ansicht «Erfassen» (nach Name, wie geladen) / «Rangliste» (Kat. A, Kat. B, ohne Wertung)
    // =========================================
    var esAnsicht = $('#esSeite').hasClass('es-ansicht-rangliste') ? 'rangliste' : 'erfassen';
    var esUrsprung = [];   // Zeilen in der geladenen Reihenfolge
    function sortieren() {
        var $tbody = $('#mitgliederTabelle tbody');
        $tbody.children('tr.es-gruppe').remove();
        if (esUrsprung.length) {
            if (esAnsicht === 'erfassen') {
                $tbody.append(esUrsprung);
            } else {
                var zeilen = esUrsprung.filter(function(tr) { return tr.classList.contains('hybrid-row'); });
                var kopf = function(text) { return $('<tr class="es-gruppe"><td colspan="10"></td></tr>').find('td').text(text).end(); };
                ['A', 'B'].forEach(function(kat) {
                    var g = zeilen.filter(function(tr) { return tr.getAttribute('data-kat') === kat; })
                        .sort(function(a, b) { return a.getAttribute('data-rang') - b.getAttribute('data-rang'); });
                    if (g.length) $tbody.append(kopf('Kat. ' + kat + ' (' + g.length + ')'), g);
                });
                var ohne = zeilen.filter(function(tr) { return !tr.hasAttribute('data-rang'); });
                if (ohne.length) $tbody.append(kopf('Ohne Wertung (' + ohne.length + ')'), ohne);
            }
        }
        EndEditPanel.buildRowIndex();   // Vor/Zurück im Panel folgen der sichtbaren Reihenfolge
        EndEditPanel.updateProgress();
        filterAnwenden();
    }
    function zeigeAnsicht(ansicht) {
        esAnsicht = ansicht;
        var $seite = $('#esSeite');
        $seite.toggleClass('es-ansicht-rangliste', ansicht === 'rangliste')
              .toggleClass('es-ansicht-erfassen', ansicht === 'erfassen')
              .toggleClass('ui-vollhoehe', ansicht === 'erfassen');   // «Rangliste» scrollt normal
        $('#esAnsicht button').each(function() { this.setAttribute('aria-pressed', String(this.getAttribute('data-ansicht') === ansicht)); });
        // Titel = Menütext = Browser-Tab folgt der Ansicht
        var titel = ansicht === 'rangliste' ? 'Endschiessen Rangliste' : 'Endschiessen Resultate erfassen';
        $seite.find('.page-title').first().text(titel);
        document.title = titel + ' - MSV Wilen';
        var url = new URL(location.href);
        url.searchParams.delete('year');
        if (ansicht === 'rangliste') url.searchParams.set('ansicht', 'rangliste'); else url.searchParams.delete('ansicht');
        history.replaceState(null, '', url);
        if (ansicht === 'rangliste') {   // Filter «Offen/Vollständig» gibt es nur beim Erfassen
            endFilter = 'alle';
            $('#esFilter button').attr('aria-pressed', 'false');
            $('#esFilter button[data-filter="alle"]').attr('aria-pressed', 'true');
        }
        sortieren();
        buildMobileEndCards();
        if (ansicht === 'rangliste' && esUrsprung.length && window.absendenPruefen) window.absendenPruefen();
    }
    $('#esAnsicht').on('click', 'button', function() {
        var ansicht = this.getAttribute('data-ansicht');
        if (ansicht !== esAnsicht) zeigeAnsicht(ansicht);
    });

    // =========================================
    //  Filter (Alle / Offen / Vollständig) und Suche
    // =========================================
    var endFilter = 'alle', endSuchtext = '';
    function filterAnwenden() {
        var sichtbar = 0;
        EndEditPanel.allRows.forEach(function(r) {
            var name = $(r.tr).find('td:first').text().toLowerCase();
            var zeigen = (endFilter === 'alle' || r.stand === endFilter) && name.indexOf(endSuchtext) !== -1;
            r.tr.style.display = zeigen ? '' : 'none';
            if (zeigen) sichtbar++;
        });
        $('#endKeineTreffer').remove();
        if (!sichtbar && EndEditPanel.allRows.length) {
            $('#mitgliederTabelle tbody').append(
                '<tr class="ui-leer" id="endKeineTreffer"><td colspan="10">Keine Mitglieder für diese Auswahl. ' +
                '<button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="endFilterZurueck">Filter zurücksetzen</button></td></tr>');
        }
    }
    $(document).on('click', '#esFilter button', function() {
        endFilter = $(this).data('filter');
        $('#esFilter button').attr('aria-pressed', 'false');
        $(this).attr('aria-pressed', 'true');
        filterAnwenden();
    });
    $('#endSuche').on('input', function() { endSuchtext = this.value.trim().toLowerCase(); filterAnwenden(); });
    $(document).on('click', '#endFilterZurueck', function() {
        endFilter = 'alle'; endSuchtext = ''; $('#endSuche').val('');
        $('#esFilter button').attr('aria-pressed', 'false');
        $('#esFilter button[data-filter="alle"]').attr('aria-pressed', 'true');
        filterAnwenden();
    });

    // Tabelle per Tastatur: Pfeile wählen, Enter öffnet
    // Tabelle per Tastatur: msv-panel-a11y.js ([data-zeilen-tastatur] an #endScroll) – Pfeile, Pos1/Ende, Enter, Ansage

    // Panel: Ctrl+S speichert, Ctrl+Enter speichert und öffnet das nächste offene Mitglied
    $(document).on('keydown', function(e) {
        if (!$('#editPanel').hasClass('open') || !(e.ctrlKey || e.metaKey)) return;
        if (e.key === 's' || e.key === 'S') { e.preventDefault(); EndEditPanel.save(); }
        else if (e.key === 'Enter') { e.preventDefault(); EndEditPanel.saveAndNext(); }
    });

    // =========================================
    //  Summen berechnen
    // =========================================
    function calculateSum(selector, sumId) {
        var sum = 0;
        $(selector).each(function() { sum += parseInt($(this).val()) || 0; });
        $('#' + sumId).text(sum);
    }

    // Ausgefuellte Felder gruen markieren (wie kanti/heim .filled)
    function refreshFilled() {
        $('#editPanel .shot-input').each(function() {
            $(this).toggleClass('filled', this.value !== '');
        });
    }

    function calculateAllSums() {
        calculateSum('.endschuss', 'endstichSumme');
        calculateSum('.schwini-schuss1', 'schwiniSumme1');
        calculateSum('.schwini-schuss2', 'schwiniSumme2');
        calculateSum('.kunst', 'kunstSum');
        calculateSum('.zabig', 'zabigsum');
    }

    // =========================================
    //  Enter-Navigation im Panel
    // =========================================
    function setupEnterNavigation() {
        var $inputs = $('#editPanel .focusable-input:not(:disabled)');
        $inputs.off('keydown.nav').on('keydown.nav', function(e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                var currentIndex = $inputs.index(this);
                var nextIndex = currentIndex + 1;
                if (nextIndex < $inputs.length) {
                    $inputs.eq(nextIndex).focus().select();
                } else {
                    EndEditPanel.saveAndNext();
                }
            }
        });
    }

    // =========================================
    //  Stich-Verfügbarkeit
    // =========================================
    function updateStichAvailability(geloesteStiche) {
        var stichElements = {
            'END': '#endstichSchuesse',
            'SCHWINI_P1': '#schwiniSchuesse',
            'SCHWINI_P2': '#schwiniSchuesse',
            'KUNST': '#kunstSchuesse',
            'GLUECK': '#glueckSchuesse',
            'ZABIG': '#zabigSchuesse',
            'DIFF': '#Differenzler',
            'SIEUNDER': '#sieunderSchuesse'
        };

        var activateElements = {};
        var hasSchwiniP1 = geloesteStiche.indexOf('SCHWINI_P1') !== -1;
        var hasSchwiniP2 = geloesteStiche.indexOf('SCHWINI_P2') !== -1;

        geloesteStiche.forEach(function(stichCode) {
            if (stichElements[stichCode]) {
                activateElements[stichElements[stichCode]] = true;
            }
        });

        $('.shot-section[data-stich], .shot-section#Differenzler, .shot-section#Absendenanmeldung').each(function() {
            var $element = $(this);
            var elementId = '#' + $element.attr('id');

            // Absenden immer aktiv
            if (elementId === '#Absendenanmeldung') {
                $element.removeClass('disabled');
                $element.find('input').prop('disabled', false);
                return;
            }

            // Schwini Spezialbehandlung
            if (elementId === '#schwiniSchuesse') {
                if (hasSchwiniP1 || hasSchwiniP2) {
                    $element.removeClass('disabled');
                    $('.schwini-passe-1, .schwini-passe-2').removeClass('schwini-pass-disabled');

                    if (hasSchwiniP1 && !hasSchwiniP2) {
                        $('.schwini-schuss1').prop('disabled', false);
                        $('.schwini-schuss2').prop('disabled', true).val('');
                        $('.schwini-passe-2').addClass('schwini-pass-disabled');
                    } else if (!hasSchwiniP1 && hasSchwiniP2) {
                        $('.schwini-schuss1').prop('disabled', true).val('');
                        $('.schwini-schuss2').prop('disabled', false);
                        $('.schwini-passe-1').addClass('schwini-pass-disabled');
                    } else {
                        $('.schwini-schuss1').prop('disabled', false);
                        $('.schwini-schuss2').prop('disabled', false);
                    }
                } else {
                    $element.addClass('disabled');
                    $element.find('input').prop('disabled', true).val('');
                }
                return;
            }

            // Normale Stiche
            if (activateElements[elementId]) {
                $element.removeClass('disabled');
                $element.find('input').prop('disabled', false);
            } else {
                $element.addClass('disabled');
                $element.find('input').prop('disabled', true).val('');
            }
        });

        setupEnterNavigation();
    }

    // =========================================
    //  Sie und Er: Partnerwahl und Paar-Total (Migration 094)
    // =========================================
    // Partnerwahl aus load_schussdaten füllen: wer im Jahr Sie und Er gelöst hat, mit seinen Schüssen
    function sieUnderAnzeigen(su) {
        EndEditPanel.sieUnder = su;
        const $sel = $('#SieUnderPartner');
        $sel.find('option:not(:first)').remove();
        if (!su) { updateSieErUniqueVisualization(); return; }
        const namen = {};
        (su.kandidaten || []).forEach(k => { namen[k.key] = k.name; });
        let gruppe = null, $og = null;
        (su.kandidaten || []).forEach(k => {
            const g = k.typ === 'gast' ? 'Gäste' : 'Mitglieder';
            if (g !== gruppe) { gruppe = g; $og = $('<optgroup>').attr('label', g).appendTo($sel); }
            // Schon mit jemand anderem verknüpft: dazuschreiben, die Wahl löst die alte Verknüpfung
            const mit = k.gepaartMit && k.gepaartMit !== EndEditPanel.currentKey ? ' – mit ' + (namen[k.gepaartMit] || 'jemand anderem') : '';
            $('<option>').val(k.key).text(k.name + mit).appendTo($og);
        });
        $sel.val(su.partner || '');
        updateSieErUniqueVisualization();
    }

    // Schüsse der gewählten Partnerin bzw. des Partners (gespeicherter Stand)
    function partnerSchuesse() {
        const su = EndEditPanel.sieUnder;
        const key = $('#SieUnderPartner').val();
        if (!su || !key) return [];
        const k = (su.kandidaten || []).find(x => x.key === key);
        return ((k && k.schuesse) || []).filter(v => v !== null && v !== '' && Number(v) > 0).map(Number);
    }

    // Jeder Wert zählt im Paar nur einmal: Partner-Schüsse zuerst, dann die eigenen; Doppelte durchgestrichen
    function updateSieErUniqueVisualization() {
        const gesehen = {};
        const $anzeige = $('#suPartnerSchuesse').empty();
        const partner = partnerSchuesse();
        const key = $('#SieUnderPartner').val();
        if (key) {
            const name = $('#SieUnderPartner option:selected').text().replace(/ – mit .*$/, '');
            $anzeige.append($('<span>').text(partner.length ? name + ':' : name + ' hat noch keine Schüsse erfasst.'));
            partner.forEach(v => {
                const dup = !!gesehen[v];
                gesehen[v] = true;
                $anzeige.append($('<span class="su-schuss">').toggleClass('is-dup', dup).text(v));
            });
        }
        $anzeige.prop('hidden', !key);

        $('.sie-er-schuss').removeClass('is-unique is-dup');
        $('.sie-er-mitglied').each(function() {
            const v = Math.floor(parseFloat($(this).val() || 0));
            if (!(v > 0)) return;
            $(this).addClass(gesehen[v] ? 'is-dup' : 'is-unique');
            gesehen[v] = true;
        });

        const total = Object.keys(gesehen).reduce((s, v) => s + Number(v), 0);
        $('#uniqueTotal').text(total);
    }
    $(document).on('change', '#SieUnderPartner', updateSieErUniqueVisualization);

    // =========================================
    //  Event-Handler
    // =========================================

    // Jahr-Auswahl
    $('#yearSelect').on('change', function() {
        loadData($(this).val());
    });


    // Klick auf Tabellenzeile → Panel öffnen
    $(document).on('click', '.hybrid-row', function() {
        const key = $(this).attr('data-teilnehmer');
        if (key) EndEditPanel.open(key);
    });

    // Panel schliessen
    $('#panelClose, #panelOverlay').on('click', function() { EndEditPanel.versucheSchliessen(); });

    // Escape schliesst Panel
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#editPanel').hasClass('open') && !(window.Swal && Swal.isVisible())) {
            e.stopImmediatePropagation();
            EndEditPanel.versucheSchliessen();
        }
    });

    // Panel Navigation
    $('#panelPrev').on('click', function() { EndEditPanel.navigate(-1); });
    $('#panelNext').on('click', function() { EndEditPanel.navigate(1); });

    // Speichern
    $('#panelSaveBtn').on('click', function() { EndEditPanel.save(); });

    // Speichern & Nächster
    $('#panelSaveNextBtn').on('click', function() { EndEditPanel.saveAndNext(); });

    // Löschen aus Panel
    $('#panelDeleteBtn').on('click', async function() {
        const key = EndEditPanel.currentKey;
        if (!key) return;

        const name = $('#panelTitle').text().trim();
        const jahr = $('#yearSelect').val();
        const csrf = $('#editPanel input[name="csrf_token"]').val();
        // Erst fragen, was gelöscht würde (auch Partnerin und JM-Endstich), dann bestätigen; der Server sichert vorher
        let p;
        try { p = await $.post('endschresultate/delete_endschresultat.php', { teilnehmer: key, jahr, aktion: 'pruefen', csrf_token: csrf }, null, 'json'); }
        catch (xhr) { msvToast(msvXhrMessage(xhr, 'Prüfen hat nicht geklappt'), 'error'); return; }
        const b = (p && p.bezuege) || {};
        const liste = Object.keys(b).map(k => '<li>' + msvEsc(k) + ': ' + msvEsc(b[k]) + '</li>').join('');
        if (!liste) { msvToast('Für ' + jahr + ' sind von ' + msvEsc(name) + ' keine Resultate gespeichert.', 'info'); return; }
        const r = await msvConfirmDelete('', {
            title: 'Resultate löschen?',
            html: 'Von <strong>' + msvEsc(name) + '</strong> wird für ' + jahr + ' gelöscht:<ul class="text-start mt-2 mb-2 ps-3">' + liste + '</ul>Vorher wird die Datenbank gesichert.',
            confirmText: 'Ja, löschen'
        });
        if (!r.isConfirmed) return;

        $.ajax({
            url: 'endschresultate/delete_endschresultat.php',
            method: 'POST',
            data: {
                teilnehmer: key,
                jahr: jahr,
                aktion: 'loeschen',
                csrf_token: csrf
            },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success === false) { msvToast(resp.message || 'Nicht gelöscht', 'error'); return; }
                msvToast('Resultate gelöscht' + (resp && resp.sicherung ? ' – Sicherung ' + msvEsc(resp.sicherung) : ''), 'success');
                EndEditPanel.close();
                loadData($('#yearSelect').val());
            },
            error: function(xhr) { msvToast(msvXhrMessage(xhr, 'Nicht gelöscht'), 'error'); }
        });
    });

    // Summen-Berechnung bei Input
    $(document).on('input change', '.endschuss, .schwini-schuss1, .schwini-schuss2, .kunst, .zabig', function() {
        calculateAllSums();
    });

    // Feld ausgefuellt -> gruen
    $(document).on('input change', '#editPanel .shot-input', function() {
        $(this).toggleClass('filled', this.value !== '');
    });

    // Eingaben: «Nicht gespeichert» setzen, Zahl sofort prüfen; eine offene Fehlerliste frischt sich mit auf
    $(document).on('input', '#editPanel input:not([type="hidden"])', function() {
        EndEditPanel.setGeaendert(true);
        if (this.type === 'number') msvPruefeZahl(this);
        if (!$('#panelFehler').prop('hidden')) msvEingabeFehler('#panelFehler', msvPruefeFelder('#editPanel'), false);
    });
    $(document).on('change', '#editPanel select, #Partnerrang', function(e) { if (e.originalEvent) EndEditPanel.setGeaendert(true); });

    // Seite verlassen mit offenen Eingaben: der Browser fragt nach
    $(window).on('beforeunload', function() { if (EndEditPanel.geaendert) return 'Nicht gespeicherte Eingaben'; });

    // Sie und Er Berechnung
    $(document).on('input change', '.sie-er-schuss', function() {
        updateSieErUniqueVisualization();
    });

    // =========================================
    //  Mobile Cards
    // =========================================
    function buildMobileEndCards() {
        const isMobile = window.matchMedia('(max-width: 767.98px)');
        if (!isMobile.matches) return;

        const table = document.getElementById('mitgliederTabelle');
        const container = document.querySelector('#mobileCardsEnd .mobile-cards-scroll');
        if (!table || !container) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten gefunden</div></div>';
            return;
        }

        const rows = tbody.querySelectorAll('tr.hybrid-row');
        if (rows.length === 0) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten gefunden</div></div>';
            return;
        }

        let html = '';
        rows.forEach((row, idx) => {
            const cells = Array.from(row.querySelectorAll('td'));
            if (cells.length < 8) return;

            const key = row.dataset.teilnehmer;
            const hasData = row.dataset.hasData === '1';
            const memberName = row.querySelector('.tn-name')?.textContent?.trim() || 'Unbekannt';
            const typMarke = row.dataset.typ === 'js' ? ' (JS)' : (row.dataset.typ === 'gast' ? ' (Gast)' : '');
            const endstich = cells[1]?.textContent?.trim() || '-';
            const schwini = cells[2]?.textContent?.trim() || '-';
            const kunst = cells[3]?.textContent?.trim() || '-';
            const glueck = cells[4]?.textContent?.trim() || '-';
            const zabig = cells[5]?.textContent?.trim() || '-';
            const sieUndEr = cells[6]?.textContent?.trim() || '-';
            const ansage = cells[7]?.textContent?.trim() || '-';
            const total = cells[8]?.textContent?.trim() || '-';
            const rang = row.querySelector('.es-rang')?.textContent?.trim() || '';

            const statusDot = hasData
                ? '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--ui-ok-fg);margin-right:6px;"></span>'
                : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--ui-leer);margin-right:6px;"></span>';

            html += `
            <div class="mobile-card" data-index="${idx}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                        <div class="fw-bold">${statusDot}${rang ? msvEsc(rang) + ' ' : ''}${msvEsc(memberName)}${typMarke}</div>
                        <small class="text-muted">Endstich: ${endstich} | Schwini: ${schwini} | Total: ${total}</small>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="mobile-card-body">
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Endstich</span>
                        <span class="mobile-card-detail-value"><strong>${endstich}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Schwini</span>
                        <span class="mobile-card-detail-value"><strong>${schwini}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Kunst</span>
                        <span class="mobile-card-detail-value"><strong>${kunst}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Glück</span>
                        <span class="mobile-card-detail-value"><strong>${glueck}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Zabig</span>
                        <span class="mobile-card-detail-value"><strong>${zabig}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Sie und Er</span>
                        <span class="mobile-card-detail-value"><strong>${sieUndEr}</strong></span>
                    </div>
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">Ansage</span>
                        <span class="mobile-card-detail-value"><strong>${ansage}</strong></span>
                    </div>
                    <button type="button" class="btn btn-outline-primary w-100 mt-3"
                            onclick="EndEditPanel.open('${key}')"
                            style="min-height: 48px;">
                        <i class="bi bi-pencil me-2"></i>Bearbeiten
                    </button>
                </div>
            </div>`;
        });

        container.innerHTML = html;
    }

    window.filterMobileEnd = function(searchInput) {
        const query = searchInput.value.toLowerCase();
        const cards = document.querySelectorAll('#mobileCardsEnd .mobile-card');

        let visibleCount = 0;
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const isVisible = text.includes(query);
            card.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        const container = document.querySelector('#mobileCardsEnd .mobile-cards-scroll');
        const existingEmpty = container.querySelector('.mobile-cards-empty');
        if (visibleCount === 0 && !existingEmpty) {
            container.insertAdjacentHTML('beforeend', `
                <div class="mobile-cards-empty">
                    <i class="bi bi-search"></i>
                    <div>Keine Treffer gefunden</div>
                </div>`);
        } else if (visibleCount > 0 && existingEmpty) {
            existingEmpty.remove();
        }
    };

    // Responsive Rebuild
    let wasDesktop = window.matchMedia('(min-width: 768px)').matches;
    window.addEventListener('resize', function() {
        const isNowDesktop = window.matchMedia('(min-width: 768px)').matches;
        if (wasDesktop && !isNowDesktop) {
            buildMobileEndCards();
        }
        wasDesktop = isNowDesktop;
    });

    // EndEditPanel global verfügbar machen für Mobile-Cards onclick
    window.EndEditPanel = EndEditPanel;

    // =========================================
    //  Init
    // =========================================
    initializeYearDropdown();
    zeigeAnsicht(esAnsicht);   // Titel, Browser-Tab und Adresse zur Ansicht aus ?ansicht=
    loadData($('#yearSelect').val());
});
</script>

<script>
/* ===== Ansicht «Rangliste»: Dokumente, Prüfliste und Stand des Absendenbuchs (vorher endschrang.php) =====
   Die Prüfliste läuft nach jedem Laden der Tabelle, solange die Ansicht «Rangliste» offen ist (loadData). */
$(document).ready(function () {
    // Ranglisten als PDF (Ausgabe-Baustein msvAusgabe: sperren, Spinner, Download, ein Toast).
    // Die Generatoren liefern 'dat/…' relativ zu endschrang/ → linkPrefix.
    function generatePDF(buttonClass, scriptName, documentName) {
        $(document).on('click', '.' + buttonClass, function (e) {
            e.preventDefault();
            var jahr = $('#yearSelect').val();
            msvAusgabe(this, {
                url: 'endschrang/' + scriptName,
                data: {
                    year: jahr,
                    orientation: window.MsvDruck ? MsvDruck.orientierung('endschrang', 'portrait') : 'portrait' // Format aus dem Druckprofil
                },
                linkPrefix: 'endschrang/',
                titel: documentName + ' ' + jahr,
                name: 'Endschiessen_' + documentName.replace(/\s+/g, '_') + '_' + jahr,
                fehler: '«' + documentName + '» konnte nicht erstellt werden. Bitte nochmals versuchen und sonst dem Admin melden.'
            });
        });
    }

    // Absendenbuch als Word (Vorlage befüllt); Link relativ zu absenden/
    $(document).on('click', '.abs-btn', function (e) {
        e.preventDefault();
        if (this.disabled) return;
        var jahr = $('#yearSelect').val();
        absendenbuchErstellen(this, 'Absendenbuch (Word) wird erstellt.', {
            url: 'absenden/generate_absendenbuch.php',
            data: { year: jahr },
            linkPrefix: 'absenden/',
            titel: 'Absendenbuch ' + jahr + ' (Word)',
            name: 'Absendenbuch_' + jahr,
            fehler: 'Das Absendenbuch konnte nicht erstellt werden. Bitte nochmals versuchen und sonst dem Admin melden.',
            erfolg: j => absendenbuchErfolg(j, 'Absendenbuch ' + jahr + ' (Word) heruntergeladen.')
        });
    });

    generatePDF('ges-btn', 'generate_pdf_gesamt.php', 'Gesamtrangliste');
    generatePDF('zwi-btn', 'generate_pdf_zwischenrangliste.php', 'Zwischenrangliste');
    generatePDF('einzel-btn', 'generate_pdf_einzelwettbewerbe.php', 'Einzelwettbewerbe');
    generatePDF('end-btn', 'generate_pdf_end.php', 'Rangliste Endstich');
    generatePDF('sch-btn', 'generate_pdf_schwini.php', 'Rangliste Schwini');
    generatePDF('kun-btn', 'generate_pdf_kunst.php', 'Rangliste Kunst');
    generatePDF('glu-btn', 'generate_pdf_glueck.php', 'Rangliste Glück');
    generatePDF('zab-btn', 'generate_pdf_zabig.php', 'Rangliste Zabig');
    generatePDF('dif-btn', 'generate_pdf_diff.php', 'Rangliste Differenzler');
    generatePDF('anm-btn', 'generate_pdf_anmeldung.php', 'Anmeldungen');
    generatePDF('part-btn', 'generate_pdf_partner.php', 'Rangliste Partner');
    generatePDF('sieer-btn', 'generate_pdf_sieer.php', 'Rangliste Sie und Er');

    // ===== Absenden vorbereiten: Prüfliste =====
    // Offene Stiche und Sie-und-Er-Paare zählt sie aus dem Lader der Tabelle (dieselbe Regel: «offen», solange ein
    // gelöster Stich kein Resultat hat; Sie und Er braucht eine verknüpfte Partnerin bzw. einen verknüpften Partner,
    // data-su="ohne-partner"). Wanderpreise, Siegerlisten und die übrigen Teile des Absendenbuchs (JM, Heim, Kanti,
    // Cup) kommen aus endschrang/absenden_bereit.php, jeder Teil einzeln (null = nicht geprüft).
    const absPunkte = {}; // letzter Zustand je Prüfung, für den Stand des Absendenbuchs
    function absZeile(key, zustand, text, link) {
        absPunkte[key] = { zustand: zustand, text: text, link: link || null };
        const label = { ok: 'erledigt', offen: 'offen', leer: 'nichts zu tun', fehler: 'nicht geprüft' }[zustand];
        $('#absListe li[data-pruefung="' + key + '"]').html(
            '<span class="ui-status' + (zustand === 'ok' || zustand === 'offen' ? ' ' + zustand : '') + '"><span class="ui-punkt"></span>' + label + '</span>'
            + '<span>' + msvEsc(text) + '</span>'
            + (link ? '<a href="' + link.href + '">' + msvEsc(link.text) + ' <i class="bi bi-arrow-right" aria-hidden="true"></i></a>' : ''));
        return zustand;
    }
    // «A, B und C»
    function absAufzaehlen(liste) {
        return liste.length < 2 ? liste.join('') : liste.slice(0, -1).join(', ') + ' und ' + liste[liste.length - 1];
    }
    // «Heimmeisterschaft Kat. A» + «Heimmeisterschaft Kat. B» → «Heimmeisterschaft (Kat. A und B)»
    function absKatZusammen(teile) {
        const basis = t => t.replace(/ Kat\. [AB]$/, '');
        return teile.filter((t, i) => teile.findIndex(u => basis(u) === basis(t)) === i)
            .map(t => teile.filter(u => basis(u) === basis(t)).length > 1 ? basis(t) + ' (Kat. A und B)' : t);
    }
    function zeilenZaehlen(html) {
        const doc = new DOMParser().parseFromString('<table><tbody>' + html + '</tbody></table>', 'text/html');
        return { total: doc.querySelectorAll('tr[data-stand]').length, offen: doc.querySelectorAll('tr[data-stand="offen"]').length,
                 ohnePartner: doc.querySelectorAll('tr[data-su="ohne-partner"]').length };
    }
    // «Zur Erfassung»: dieselbe Seite in der Ansicht «Erfassen»
    const zurErfassung = { href: 'endresultate.php', text: 'Zur Erfassung' };
    async function absendenPruefen() {
        const jahr = $('#yearSelect').val();
        if (!jahr) return;
        $('#absNeu').prop('disabled', true).attr('aria-busy', 'true');
        try { await absendenPruefenJahr(jahr); }
        finally { $('#absNeu').prop('disabled', false).removeAttr('aria-busy'); }
    }
    async function absendenPruefenJahr(jahr) {
        $('#absStand').html('<span class="ui-status"><span class="ui-punkt"></span>wird geprüft …</span>');
        const q = { year: jahr };
        const [end, wp] = await Promise.allSettled([
            $.get('endschresultate/load_endschresultate.php', q),
            $.getJSON('endschrang/absenden_bereit.php', q)
        ]);
        if (jahr !== $('#yearSelect').val()) return; // inzwischen ein anderes Jahr gewählt
        const z = [];
        if (end.status === 'fulfilled') {
            const n = zeilenZaehlen(end.value);
            z.push(n.total === 0 ? absZeile('stiche', 'leer', 'Für ' + jahr + ' sind keine Stiche gelöst.')
                : n.offen === 0 ? absZeile('stiche', 'ok', 'Alle gelösten Stiche sind erfasst (' + n.total + ' Teilnehmer).')
                : absZeile('stiche', 'offen', n.offen + ' von ' + n.total + ' Teilnehmern haben noch offene Stiche.', zurErfassung));
            z.push(n.ohnePartner === 0 ? absZeile('partner', 'ok', 'Alle Sie-und-Er-Schützen sind mit ihrer Partnerin bzw. ihrem Partner verknüpft.')
                : absZeile('partner', 'offen', (n.ohnePartner === 1 ? '1 Person mit Sie und Er ist' : n.ohnePartner + ' Personen mit Sie und Er sind') + ' noch mit niemandem verknüpft.', zurErfassung));
        } else {
            z.push(absZeile('stiche', 'fehler', 'Die Stiche konnten nicht geprüft werden.'));
            z.push(absZeile('partner', 'fehler', 'Sie und Er konnte nicht geprüft werden.'));
        }
        const bereit = wp.status === 'fulfilled' && wp.value && wp.value.success ? wp.value : {};
        const w = bereit.wanderpreise;
        z.push(!w ? absZeile('wanderpreise', 'fehler', 'Die Wanderpreise konnten nicht geprüft werden.')
            : w.total === 0 ? absZeile('wanderpreise', 'leer', 'Keine Wanderpreise im Umlauf.')
            : w.vergeben >= w.total ? absZeile('wanderpreise', 'ok', 'Alle ' + w.total + ' Wanderpreise sind für ' + jahr + ' vergeben.')
            : absZeile('wanderpreise', 'offen', w.vergeben + ' von ' + w.total + ' Wanderpreisen sind für ' + jahr + ' vergeben.', { href: 'wanderpreise.php', text: 'Zu den Wanderpreisen' }));
        // Siegerlisten «… Sieger seit» im Absendenbuch (Tabelle sieger): fehlt das Jahr, fehlt es auch im Buch
        const s = bereit.siegerlisten;
        z.push(!s ? absZeile('siegerlisten', 'fehler', 'Die Siegerlisten konnten nicht geprüft werden.')
            : s.total === 0 ? absZeile('siegerlisten', 'leer', 'Keine Siegerlisten eingerichtet.')
            : s.ohne.length === 0 ? absZeile('siegerlisten', 'ok', 'Alle ' + s.total + ' Siegerlisten enthalten ' + jahr + '.')
            : absZeile('siegerlisten', 'offen', (s.ohne.length === s.total ? 'Allen ' + s.total + ' Siegerlisten fehlt ' + jahr + '.'
                : s.ohne.length + ' von ' + s.total + ' Siegerlisten fehlt ' + jahr + ': ' + absAufzaehlen(s.ohne) + '.'),
                { href: 'sieger.php', text: 'Zu den Siegerlisten' }));
        // Übrige Teile des Buchs mit eigener Erfassung: ohne Resultate bliebe ihre Tabelle im Buch leer
        const b = bereit.buchteile;
        // fehlt = keine Resultate; unfertig = Resultate da, aber noch nicht fertig fürs Buch (Cup-Finale, gemeldete JM)
        const fehlt = b ? b.filter(t => !t.vorhanden) : [];
        const unfertig = b ? b.filter(t => t.vorhanden && t.offen) : [];
        const buchText = [];
        if (fehlt.length) buchText.push('Noch ohne Resultate für ' + jahr + ': ' + absAufzaehlen(absKatZusammen(fehlt.map(t => t.teil)))
            + (fehlt.length === 1 ? '. Im Buch bliebe diese Tabelle leer.' : '. Im Buch blieben diese Tabellen leer.'));
        unfertig.forEach(t => buchText.push(t.hinweis + '.'));
        z.push(!b ? absZeile('buch', 'fehler', 'Die übrigen Teile des Absendenbuchs konnten nicht geprüft werden.')
            : !buchText.length ? absZeile('buch', 'ok', 'Jahresmeisterschaft, Heimmeisterschaft, Kantonalstich und Vereinscup haben Resultate für ' + jahr + '.')
            : absZeile('buch', 'offen', buchText.join(' '), { href: (fehlt[0] || unfertig[0]).link, text: 'Zur Erfassung' }));
        const offen = z.filter(s => s === 'offen').length, fehler = z.filter(s => s === 'fehler').length;
        const zeit = new Date().toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit' });
        $('#absStand').html((offen
            ? '<span class="ui-status offen"><span class="ui-punkt"></span>' + (offen === 1 ? '1 Punkt offen' : offen + ' Punkte offen') + '</span>'
            : fehler ? '<span class="ui-status"><span class="ui-punkt"></span>nicht alles geprüft</span>'
            : '<span class="ui-status ok"><span class="ui-punkt"></span>bereit fürs Absenden</span>')
            + '<span class="es-abs-zeit">Stand ' + zeit + '</span>');
        // Die Dokumente lassen sich auch mit offenen Punkten erstellen; der Hinweis sagt, dass sie den heutigen Stand zeigen.
        // Gibt es schon ein Absendenbuch, sagt das sein Stand genauer.
        const hatBuch = absBuchstandZeigen(jahr, bereit.buchstand);
        $('#absDokHinweis').text(offen ? 'Noch ' + (offen === 1 ? '1 Punkt' : offen + ' Punkte') + ' offen – die Dokumente zeigen den heutigen Stand.' : '').prop('hidden', !offen || hatBuch);
    }

    // ===== Stand des Absendenbuchs =====
    // Ein Befund fürs Heft: die Hinweise beim Erstellen (gelten für genau diese Datei) plus die offenen Punkte der
    // Prüfliste, die der Generator nicht kennt (Stiche, Wanderpreise). Ist ein Hinweis seither in der Prüfliste
    // erledigt, steht «inzwischen erledigt, Buch neu erstellen» – die Datei hat den alten Stand.
    function absHinweisZiel(abschnitt, jahr) {
        const basis = String(abschnitt || '').replace(/ Kat\. [AB]$/, '');
        const ziele = {
            'Siegerlisten':        { href: 'sieger.php', text: 'Zu den Siegerlisten', pruefung: 'siegerlisten' },
            'Jahresmeisterschaft': { href: 'jmresultate.php', text: 'Zur Erfassung', pruefung: 'buch' },
            'Heimmeisterschaft':   { href: 'heimresultate.php', text: 'Zur Erfassung', pruefung: 'buch' },
            'Kantonalstich':       { href: 'kantiresultate.php', text: 'Zur Erfassung', pruefung: 'buch' },
            'Vereinscup':          { href: 'cup.php', text: 'Zur Erfassung', pruefung: 'buch' },
            'Vorlage':             null
        };
        return basis in ziele ? ziele[basis] : (basis ? { href: 'endresultate.php?year=' + jahr, text: 'Zur Erfassung', pruefung: null } : null);
    }
    function absZeitpunkt(iso) {
        const d = new Date(iso);
        if (isNaN(d)) return '';
        return d.toLocaleDateString('de-CH', { weekday: 'short', day: 'numeric', month: 'numeric' })
            + ', ' + d.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit' });
    }
    function absPunktHtml(text, ziel, erledigt) {
        return '<li>' + msvEsc(text)
            + (erledigt ? ' <span class="es-erledigt">Inzwischen erledigt, Buch neu erstellen.</span>'
                : ziel ? ' <a href="' + msvEsc(ziel.href) + '">' + msvEsc(ziel.text) + ' <i class="bi bi-arrow-right" aria-hidden="true"></i></a>' : '')
            + '</li>';
    }
    // true, wenn für das Jahr schon ein Absendenbuch erstellt wurde
    function absBuchstandZeigen(jahr, stand) {
        $('#absBuchstand').prop('hidden', false);
        const $dateien = $('#absBuchDateien'), $punkte = $('#absBuchPunkte');
        if (!stand) {
            $dateien.html('<p class="es-buchdatei es-buchdatei-meta">Der Stand des Absendenbuchs konnte nicht geladen werden.</p>');
            $punkte.empty();
            return false;
        }
        const arten = [['broschuere', 'Broschüre'], ['word', 'Word']].filter(a => stand[a[0]]);
        if (!arten.length) {
            $dateien.html('<p class="es-buchdatei es-buchdatei-meta">Für ' + msvEsc(jahr) + ' ist noch kein Absendenbuch erstellt.</p>');
            $punkte.empty();
            return false;
        }
        $dateien.html(arten.map(([art, name]) => {
            const s = stand[art];
            const meta = [absZeitpunkt(s.erstellt), s.von, s.seiten ? s.seiten + ' Seiten auf ' + s.blatt + ' Blatt' : ''].filter(Boolean).join(' · ');
            return '<p class="es-buchdatei"><span class="es-buchdatei-art">' + name + '</span>'
                + '<span class="es-buchdatei-meta">' + msvEsc(meta) + '</span>'
                + (!s.vorhanden ? '<span class="es-buchdatei-weg">Datei nicht mehr vorhanden, bitte neu erstellen.</span>'
                    : '<a href="' + msvEsc(s.link) + '"' + (art === 'word' ? ' download' : ' target="_blank" rel="noopener"') + '>Öffnen</a>'
                      + (art === 'broschuere' ? '<button type="button" class="btn btn-sm btn-outline-info msv-druck" data-druck-doctype="absendenbuch"'
                          + ' data-druck-label="Absendenbuch (Broschüre)" data-druck-datei="' + msvEsc(s.link) + '" aria-label="Diese Broschüre drucken">'
                          + '<i class="bi bi-printer me-1" aria-hidden="true"></i>Drucken</button>' : ''))
                + '</p>';
        }).join(''));

        // Befund der zuletzt erstellten Datei
        const neueste = arten.map(a => stand[a[0]]).sort((x, y) => String(y.erstellt).localeCompare(String(x.erstellt)))[0];
        const punkte = (neueste.hinweise || []).map(h => {
            const ziel = absHinweisZiel(h.abschnitt, jahr);
            const erledigt = !!(ziel && ziel.pruefung && absPunkte[ziel.pruefung] && absPunkte[ziel.pruefung].zustand === 'ok');
            return absPunktHtml((h.abschnitt ? h.abschnitt + ': ' : '') + h.text, ziel, erledigt);
        });
        ['stiche', 'wanderpreise'].forEach(k => {
            if (absPunkte[k] && absPunkte[k].zustand === 'offen') punkte.push(absPunktHtml(absPunkte[k].text, absPunkte[k].link, false));
        });
        $punkte.html(punkte.length
            ? '<p class="es-buchpunkte-kopf"><span class="ui-status offen"><span class="ui-punkt"></span>Vor dem Druck prüfen (' + punkte.length + ')</span></p>'
              + '<ul class="es-buchpunkte">' + punkte.join('') + '</ul>'
            : '<p class="es-buchpunkte-kopf"><span class="ui-status ok"><span class="ui-punkt"></span>Keine offenen Punkte</span></p>');
        if (window.MsvDruck && MsvDruck.refresh) MsvDruck.refresh(); // Drucker-Knopf der Datei ein- oder ausblenden
        return true;
    }
    window.absendenPruefen = absendenPruefen; // loadData und die Ausgabe-Knöpfe rufen sie auf
    $('#absNeu').on('click', absendenPruefen);
});
</script>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray): ein Profil «Endschiessen Ranglisten» für alle PDFs der Ansicht «Rangliste».
// Die Generatoren liefern JSON {pdf_link: 'dat/…'} relativ zum Modul → linkPrefix.
MsvDruck.resolve('endschrang', (btn) => {
    const jahr = document.getElementById('yearSelect').value;
    return {
        url: 'endschrang/' + btn.dataset.druckScript + '?year=' + encodeURIComponent(jahr)
            + '&orientation=' + MsvDruck.orientierung('endschrang', 'portrait'),
        jobName: btn.dataset.druckJob + ' ' + jahr,
        linkPrefix: 'endschrang/'
    };
});

// Absendenbuch als Broschüre (absenden/generate_absendenbuch_pdf.php: DOCX → PDF → pdfjam --booklet).
// Broschüren brauchen beidseitigen Druck mit Wenden an der kurzen Seite; steht im Profil kein Duplex,
// wird «short-edge» gesetzt, damit die Rückseiten nicht auf dem Kopf stehen.
// Der Drucker neben «Absendenbuch (Broschüre)» erstellt neu; «Drucken» im Stand des Absendenbuchs druckt genau
// die vorhandene Datei (data-druck-datei), ohne neue Umwandlung.
MsvDruck.resolve('absendenbuch', (btn) => {
    const jahr = document.getElementById('yearSelect').value;
    const profil = MsvDruck.profil('absendenbuch');
    return {
        url: (btn && btn.dataset.druckDatei) || ('absenden/generate_absendenbuch_pdf.php?year=' + encodeURIComponent(jahr)),
        jobName: 'Absendenbuch ' + jahr + ' (Broschüre)',
        orientation: 'landscape',
        duplex: (profil && profil.duplex) || 'short-edge'
    };
});

// Erfolg beim Absendenbuch: kurzer Toast. Die Hinweise stehen bleibend im Stand des Absendenbuchs.
function absendenbuchErfolg(j, text) {
    const n = (j && Array.isArray(j.hinweise)) ? j.hinweise.length : 0;
    return n ? { text: text + ' ' + (n === 1 ? '1 Hinweis' : n + ' Hinweise') + ' unter «Stand des Absendenbuchs».', typ: 'warning' } : text;
}

// Absendenbuch erstellen (Word oder Broschüre): Während der Arbeit steht im Stand, was läuft, mit mitlaufender
// Zeit (die Uhr ist für Screenreader ausgeblendet, damit die Live-Region nicht jede Sekunde spricht). Danach
// Stand neu laden und den Fokus zurück auf den Knopf, den der Ladezustand gesperrt hatte.
function absendenbuchErstellen(btn, lauftext, opts) {
    $('#absBuchstand').prop('hidden', false);
    $('#absBuchLauf').text(lauftext);
    const start = Date.now();
    const uhr = () => { const s = Math.floor((Date.now() - start) / 1000); $('#absBuchUhr').text(Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0')); };
    uhr();
    const takt = setInterval(uhr, 1000);
    return msvAusgabe(btn, opts).finally(() => {
        clearInterval(takt);
        $('#absBuchLauf, #absBuchUhr').text('');
        if (window.absendenPruefen) window.absendenPruefen();
        btn.focus();
    });
}

// Broschüren-PDF herunterladen (gleicher Endpunkt, zum Prüfen der Seitenfolge oder für die Druckerei)
$(document).on('click', '.absbk-btn', function () {
    if (this.disabled) return;
    const jahr = document.getElementById('yearSelect').value;
    // Die Umwandlung in PDF läuft über einen externen Dienst und dauert spürbar: vorher sagen, damit niemand
    // nach einer halben Minute Spinner nochmals klickt oder zum Drucker greift
    absendenbuchErstellen(this, 'Absendenbuch (Broschüre) wird erstellt, das kann bis zu einer Minute dauern.', {
        url: 'absenden/generate_absendenbuch_pdf.php',
        data: { year: jahr },
        titel: 'Absendenbuch ' + jahr + ' (Broschüre)',
        name: 'Absendenbuch_' + jahr + '_Broschuere',
        fehler: 'Die Broschüre konnte nicht erstellt werden. Bitte nochmals versuchen; bis dahin geht «Absendenbuch (Word)».',
        erfolg: j => absendenbuchErfolg(j, j && j.pages
            ? 'Broschüre heruntergeladen: ' + j.pages + ' Seiten auf ' + j.sheets + ' Blatt A4 (beidseitig, Wenden an der kurzen Kante).'
            : 'Broschüre heruntergeladen.')
    });
});
</script>

<script>
// «Weitere Dokumente»: auf- oder zugeklappt bleibt es in diesem Browser, wie zuletzt verlassen
(function() {
    const el = document.getElementById('esDokWeitere');
    if (!el) return;
    try { if (localStorage.getItem('esDokWeitere') === 'offen') el.open = true; } catch (e) {}
    el.addEventListener('toggle', function() {
        try { localStorage.setItem('esDokWeitere', el.open ? 'offen' : 'zu'); } catch (e) {}
    });
})();
</script>

<?php include 'footer.inc.php'; ?>
