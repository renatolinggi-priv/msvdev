<?php
// endschrang.php – Endschiessen Ranglisten (Kat. A/B) und Dokumente
include 'dbconnect.inc.php';

// Seitenspezifische Styles: nur Aufbau dieser Seite; die Optik kommt aus css/msv-ui.css
$page_specific_css = '
/* Aufbau: links lesen (Prüfliste, Rangliste), rechts ausgeben (alle Dokumente in einer Leiste).
   Erst ab 1040px Inhaltsbreite zweispaltig, damit die neun Spalten der Rangliste Platz behalten. */
.es-seite { container-type: inline-size; margin-bottom: 14px; }
.es-raster { display: grid; grid-template-columns: minmax(0, 1fr); grid-template-areas: "pruef" "doks" "rang"; gap: 14px 16px; align-items: start; }
.es-absenden { grid-area: pruef; }
.es-doks { grid-area: doks; }
.main-content-wrapper .es-raster > .es-rangliste { grid-area: rang; margin-bottom: 0 !important; }
@container (min-width: 1040px) {
    /* Zeile 2 nimmt den Überhang der Dokumenten-Leiste auf, sonst entstünde eine Lücke unter der Prüfliste */
    .es-raster { grid-template-columns: minmax(0, 1fr) 17rem; grid-template-rows: auto 1fr; grid-template-areas: "pruef doks" "rang doks"; }
}

/* Absenden vorbereiten: die Prüfliste */
.es-abs-stand { margin-left: auto; }
.es-abs-liste { list-style: none; margin: 0; padding: 14px var(--ui-pad) 16px; display: grid; gap: 10px; }
.es-abs-liste li { display: flex; align-items: baseline; gap: 6px 12px; flex-wrap: wrap; }
.es-abs-liste .ui-status { min-width: 6.5rem; }
.es-abs-liste .ui-status:not(.ok):not(.offen) { color: var(--ui-text-2); }
.es-abs-liste .ui-status:not(.ok):not(.offen) .ui-punkt { background: var(--ui-text-3); }
.es-abs-liste a { font-weight: 600; white-space: nowrap; }
.es-abs-zeit { margin-left: 8px; font-size: .75rem; color: var(--ui-text-2); white-space: nowrap; }

/* Dokumente: ein Dokument pro Zeile, Drucker immer gleich breit am rechten Rand.
   Einspaltig (schmal) fliessen die Knöpfe nebeneinander wie in der Export-Toolbar. */
.es-doks-inhalt { display: grid; gap: 16px; padding: 14px 16px 16px; }
.es-dok-label { margin: 0 0 6px; font-size: .72rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--ui-text-2); }
.es-abs-hinweis { margin: -2px 0 8px; font-size: .8rem; line-height: 1.35; color: var(--ui-warn-fg); }
/* Stand des Absendenbuchs: Abschnitt unter den Knöpfen «Fürs Absenden», abgesetzt durch eine Haarlinie */
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
.es-dok-liste { display: flex; flex-wrap: wrap; gap: 6px 8px; }
.es-dok-liste > .btn-group > .btn:first-child { text-align: left; }
.es-dok-liste > .btn-group > .msv-druck { flex: 0 0 2.25rem; }
@container (min-width: 1040px) {
    .es-dok-liste { flex-direction: column; flex-wrap: nowrap; }
    .es-dok-liste > .btn-group { width: 100%; }
    .es-dok-liste > .btn-group > .btn:first-child { flex: 1 1 auto; }
}

/* Noch nicht erfasster Stich in der Rangliste */
.es-fehlt { color: var(--ui-leer); }

/* Ranglisten Kat. A / B: Zahlen zentriert, Name links, Total betont */
#EndA thead th, #EndB thead th,
#EndA tbody td, #EndB tbody td { text-align: center; }
#EndA thead th:nth-child(2), #EndB thead th:nth-child(2),
#EndA tbody td:nth-child(2), #EndB tbody td:nth-child(2) { text-align: left; font-weight: 500; }
#EndA tbody td:last-child:where(:not([colspan])), #EndB tbody td:last-child:where(:not([colspan])) { font-weight: 700; color: var(--ui-text); background-color: var(--ui-flaeche-2); font-variant-numeric: tabular-nums; }
/* Podium: Top 3 leicht in Gold, Silber und Bronze getönt, Rang fett */
#EndA tbody tr.rank-1 td, #EndB tbody tr.rank-1 td { background-color: color-mix(in srgb, var(--ui-gold-bg) 40%, var(--ui-flaeche)); }
#EndA tbody tr.rank-2 td, #EndB tbody tr.rank-2 td { background-color: color-mix(in srgb, var(--ui-silber-bg) 40%, var(--ui-flaeche)); }
#EndA tbody tr.rank-3 td, #EndB tbody tr.rank-3 td { background-color: color-mix(in srgb, var(--ui-bronze-bg) 40%, var(--ui-flaeche)); }
#EndA tbody tr:is(.rank-1, .rank-2, .rank-3) td:first-child,
#EndB tbody tr:is(.rank-1, .rank-2, .rank-3) td:first-child { font-weight: 700; }
';
include 'header.inc.php';
?>

<!-- Header -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <!-- Äusserer weisser Container -->
            <div class="main-content-wrapper content-width-wide">
                <!-- Header ausserhalb des inneren Containers -->
                <?php
                $page_title = "Endschiessen Rangliste";
                $page_title_after = '<button type="button" class="btn-help" data-help="endschrang.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_actions = '<button id="redirect-btn" type="button" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Resultate bearbeiten</button>';
                $page_show_mobile = true;
                include 'partials/page_header.inc.php'; ?>
                <div class="es-seite"><div class="es-raster">
                <!-- Absenden vorbereiten: Prüfliste (links oben) -->
                <section class="ui-karte es-absenden" aria-labelledby="absTitel">
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
                <!-- Dokumente: alle Ausgaben der Seite in einer Leiste (rechts, über die ganze Höhe) -->
                <aside class="ui-karte es-doks" aria-labelledby="doksTitel">
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
                                    <button type="button" class="btn btn-outline-info absbk-btn" data-tooltip="Das Absendenbuch als PDF zum Falten: je zwei A5-Seiten auf A4 quer. Beidseitig drucken, an der kurzen Kante wenden."><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Absendenbuch (Broschüre)</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="absendenbuch" data-druck-label="Absendenbuch (Broschüre)" aria-label="Absendenbuch (Broschüre) direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group" aria-label="Gesamtrangliste">
                                    <button type="button" class="btn btn-outline-info ges-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Gesamtrangliste</span></button>
                                    <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="endschrang" data-druck-label="Endschiessen Ranglisten" data-druck-script="generate_pdf_gesamt.php" data-druck-job="Endschiessen Gesamtrangliste" aria-label="Gesamtrangliste direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                </div>
                            </div>
                            <!-- Stand des zuletzt erstellten Absendenbuchs (aus absenden_bereit.php: buchstand) -->
                            <section class="es-buchstand" id="absBuchstand" aria-labelledby="absBuchstandTitel" hidden>
                                <p class="es-buchstand-titel" id="absBuchstandTitel">Stand des Absendenbuchs <button type="button" class="btn-help" data-help="endschrang.buchstand" aria-label="Hilfe"></button></p>
                                <p class="es-buchstand-lauf"><span id="absBuchLauf" role="status" aria-live="polite"></span> <span id="absBuchUhr" aria-hidden="true"></span></p>
                                <div id="absBuchDateien"></div>
                                <div id="absBuchPunkte"></div>
                            </section>
                        </div>
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
                </aside>
                <!-- Rangliste Kat. A und B (links unter der Prüfliste) -->
                <div class="content-background es-rangliste">
                <!-- Tabellenbereich Kat. A -->
                <div class="table-wrapper mb-4">
                    <h5 class="table-title">Endschiessen Kat. A <button type="button" class="btn-help" data-help="endschrang.wertung" aria-label="Hilfe"></button></h5>
                    <div class="desktop-table-container">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="EndA">
                                <thead>
                                    <tr>
                                        <th scope="col">Rang</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Endstich</th>
                                        <th scope="col">Schwini</th>
                                        <th scope="col">Kunst</th>
                                        <th scope="col">Glück</th>
                                        <th scope="col">Zabig</th>
                                        <th scope="col">Differenzler</th>
                                        <th scope="col">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamische Inhalte hier -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Mobile Cards Container -->
                    <div class="mobile-cards-container" id="mobileCardsEndA">
                        <div class="mobile-search">
                            <div class="position-relative">
                                <i class="bi bi-search search-icon" aria-hidden="true"></i>
                                <input type="text" class="form-control" placeholder="Suchen..." aria-label="Rangliste Kat. A durchsuchen"
                                       oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsEndA')">
                            </div>
                        </div>
                        <div class="mobile-cards-scroll">
                            <!-- Cards werden hier eingefügt -->
                        </div>
                    </div>
                </div>
                <!-- Tabellenbereich Kat. B -->
                <div class="table-wrapper mb-4">
                    <h5 class="table-title">Endschiessen Kat. B <button type="button" class="btn-help" data-help="endschrang.kategorien" aria-label="Hilfe"></button></h5>
                    <div class="desktop-table-container">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="EndB">
                                <thead>
                                    <tr>
                                        <th scope="col">Rang</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Endstich</th>
                                        <th scope="col">Schwini</th>
                                        <th scope="col">Kunst</th>
                                        <th scope="col">Glück</th>
                                        <th scope="col">Zabig</th>
                                        <th scope="col">Differenzler</th>
                                        <th scope="col">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamische Inhalte hier -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Mobile Cards Container -->
                    <div class="mobile-cards-container" id="mobileCardsEndB">
                        <div class="mobile-search">
                            <div class="position-relative">
                                <i class="bi bi-search search-icon" aria-hidden="true"></i>
                                <input type="text" class="form-control" placeholder="Suchen..." aria-label="Rangliste Kat. B durchsuchen"
                                       oninput="MSVMobileCards.filterCardsDebounced(this, '#mobileCardsEndB')">
                            </div>
                        </div>
                        <div class="mobile-cards-scroll">
                            <!-- Cards werden hier eingefügt -->
                        </div>
                    </div>
                </div>
                </div><!-- /es-rangliste -->
                </div></div><!-- /es-raster, /es-seite -->
            </div><!-- /main-content-wrapper -->
        </div>
    </div>
</div>
<script>
$(document).ready(function () {
    var basePath = '';

    // Bearbeiten-Button → endresultate.php mit Jahresauswahl
    $('#redirect-btn').on('click', function(e) {
        e.preventDefault();
        window.location.href = 'endresultate.php?year=' + encodeURIComponent($('#yearSelect').val());
    });

    // Initialisierung des Jahres-Dropdowns
    function initializeYearDropdown() {
        msvJahrAuswahl('#yearSelect');
    }

    // Rangliste einer Kategorie laden. Während des Ladens und bei einem Fehler steht eine Hinweiszeile in der
    // Tabelle (Klasse msv-empty-row: zählt nicht als Daten, auch nicht in den Handy-Karten), damit nie die
    // Zahlen des vorher gewählten Jahres stehen bleiben.
    function hinweisZeile(text, fehler) {
        return '<tr class="msv-empty-row"><td colspan="9" class="text-center py-4 ' + (fehler ? 'text-danger' : 'text-muted') + '">'
            + (fehler ? '<i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>'
                      : '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>')
            + msvEsc(text) + '</td></tr>';
    }
    function ladeRangliste(kat) {
        const jahr = $('#yearSelect').val();
        const $tbody = $('#End' + kat + ' tbody');
        $tbody.html(hinweisZeile('Rangliste Kat. ' + kat + ' wird geladen …'));
        baueKarten(kat);
        $.ajax({
            url: basePath + 'endschrang/load_endsch.php',
            type: 'GET',
            data: { kat: kat, year: jahr }
        }).done(function (response) {
            if (jahr !== $('#yearSelect').val()) return; // inzwischen ein anderes Jahr gewählt
            $tbody.html(response);
            baueKarten(kat);
        }).fail(function (xhr) {
            if (jahr !== $('#yearSelect').val()) return;
            $tbody.html(hinweisZeile(msvXhrMessage(xhr, 'Die Rangliste Kat. ' + kat + ' konnte nicht geladen werden. Bitte die Seite neu laden.'), true));
            baueKarten(kat);
        });
    }
    function loadenda() { ladeRangliste('A'); }
    function loadendb() { ladeRangliste('B'); }

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

    // Alle PDF-Buttons registrieren mit beschreibenden Namen
    generatePDF('ges-btn', 'generate_pdf_gesamt.php', 'Gesamtrangliste');
    generatePDF('zwi-btn', 'generate_pdf_zwischenrangliste.php', 'Zwischenrangliste');
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
    // Offene Stiche und Sie-und-Er-Paare zählt sie aus dem Lader der Erfassungsseite (dieselbe Regel wie dort:
    // «offen», solange ein gelöster Stich kein Resultat hat; Sie und Er braucht eine verknüpfte Partnerin bzw. einen
    // verknüpften Partner, data-su="ohne-partner"). Wanderpreise, Siegerlisten und die übrigen Teile des Absendenbuchs
    // (JM, Heim, Kanti, Cup) kommen aus endschrang/absenden_bereit.php, jeder Teil einzeln (null = nicht geprüft).
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
                : absZeile('stiche', 'offen', n.offen + ' von ' + n.total + ' Teilnehmern haben noch offene Stiche.', { href: 'endresultate.php?year=' + jahr, text: 'Zur Erfassung' }));
            z.push(n.ohnePartner === 0 ? absZeile('partner', 'ok', 'Alle Sie-und-Er-Schützen sind mit ihrer Partnerin bzw. ihrem Partner verknüpft.')
                : absZeile('partner', 'offen', (n.ohnePartner === 1 ? '1 Person mit Sie und Er ist' : n.ohnePartner + ' Personen mit Sie und Er sind') + ' noch mit niemandem verknüpft.', { href: 'endresultate.php?year=' + jahr, text: 'Zur Erfassung' }));
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
        // Siegerlisten «… Sieger seit» im Absendenbuch: von Hand geführt, das Jahr fehlt sonst im Buch
        const s = bereit.siegerlisten;
        z.push(!s ? absZeile('siegerlisten', 'fehler', 'Die Siegerlisten konnten nicht geprüft werden.')
            : s.total === 0 ? absZeile('siegerlisten', 'leer', 'Keine Siegerlisten eingerichtet.')
            : s.ohne.length === 0 ? absZeile('siegerlisten', 'ok', 'Alle ' + s.total + ' Siegerlisten enthalten ' + jahr + '.')
            : absZeile('siegerlisten', 'offen', (s.ohne.length === s.total ? 'Allen ' + s.total + ' Siegerlisten fehlt ' + jahr + '.'
                : s.ohne.length + ' von ' + s.total + ' Siegerlisten fehlt ' + jahr + ': ' + absAufzaehlen(s.ohne) + '.'),
                { href: 'sieger.php', text: 'Zu den Siegerlisten' }));
        // Übrige Teile des Buchs mit eigener Erfassung: ohne Resultate bliebe ihre Tabelle im Buch leer
        const b = bereit.buchteile;
        const fehlt = b ? b.filter(t => !t.vorhanden) : [];
        z.push(!b ? absZeile('buch', 'fehler', 'Die übrigen Teile des Absendenbuchs konnten nicht geprüft werden.')
            : !fehlt.length ? absZeile('buch', 'ok', 'Jahresmeisterschaft, Heimmeisterschaft, Kantonalstich und Vereinscup haben Resultate für ' + jahr + '.')
            : absZeile('buch', 'offen', 'Noch ohne Resultate für ' + jahr + ': ' + absAufzaehlen(absKatZusammen(fehlt.map(t => t.teil)))
                + (fehlt.length === 1 ? '. Im Buch bliebe diese Tabelle leer.' : '. Im Buch blieben diese Tabellen leer.'),
                { href: fehlt[0].link, text: 'Zur Erfassung' }));
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
    window.absendenPruefen = absendenPruefen; // für die Ausgabe-Knöpfe weiter unten

    $('#absNeu').on('click', absendenPruefen);
    // Beim Ändern des Jahres im Dropdown beide Tabellen und die Prüfliste neu laden
    $('#yearSelect').on('change', function () {
        loadenda();
        loadendb();
        absendenPruefen();
    });

    // Initialisierung beim Laden der Seite
    initializeYearDropdown();
    loadenda();
    loadendb();
    absendenPruefen();
});


    // Handy-Karten einer Kategorie aus der Tabelle bauen (Rang und Name als Titel, Total in der Zusammenfassung)
    function baueKarten(kat) {
        MSVMobileCards.initResponsive(function() {
            MSVMobileCards.buildCards('#End' + kat, '#mobileCardsEnd' + kat, {
                titleColumns: [0, 1],
                summaryColumns: [8],
                rankColumn: 0
            });
        });
    }

</script>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray): ein Profil «Endschiessen Ranglisten» fuer alle PDFs dieser Seite.
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
<?php
include 'footer.inc.php';
?>
