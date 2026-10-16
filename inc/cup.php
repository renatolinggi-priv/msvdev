<?php
// cup.php - Turnierbaum (Vereinscup: Runde 1, Runde 2, Finale, Standcup)
// Seit 1.68.31 im Erscheinungsbild der übrigen Admin-Seiten: Spalten als Tabellen-Karten, Paarungen als
// kompakte Zeilen (Sieger mit Haken, keine Chips, kein «vs»), «Speichern» im Kopf.
include 'dbconnect.inc.php';

$body_class = 'cup4-page';
include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
$csrf = csrf_token();
?>

<link rel="stylesheet" href="../css/cup.css?v=<?php echo @filemtime(__DIR__ . '/../css/cup.css') ?: '1'; ?>">
<script>const CUP4_CSRF = <?= json_encode($csrf) ?>;</script>

<div class="container-fluid">
<div class="row">
<div class="col-12 ps-0">
    <div class="main-content-wrapper content-width-wide">
    <?php
    $page_title = 'Vereinscup erfassen';
    $page_title_after = '<button type="button" class="btn-help" data-help="cup.uebersicht" aria-label="Hilfe"></button>'
        . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
        . '<select id="yearSelect" class="form-select form-select-sm"></select>';
    // Rangliste (PDF + Direktdruck) wie bei JM, Heim und Kanti; rechts davon die eine Hauptaktion «Speichern»
    $page_actions = '<span class="cup4-kopf-aktionen">'
        . '<span class="ui-chip msv-ungespeichert" hidden><span class="ui-punkt" aria-hidden="true"></span>Nicht gespeichert</span>'
        . '<span class="btn-group btn-group-sm" role="group" aria-label="Rangliste">'
        . '<button type="button" class="btn btn-outline-info pdf-btn" data-tooltip="Cup-Rangliste als PDF (nur gespeicherte Resultate)">'
        . '<i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Rangliste</span></button>'
        . '<button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="cuprang" data-druck-label="Vereinscup Rangliste" aria-label="Rangliste direkt drucken">'
        . '<i class="bi bi-printer" aria-hidden="true"></i></button>'
        . '</span>'
        . '<button type="button" id="save-all" class="btn btn-primary btn-sm" data-tooltip="Alle Runden, Finale und Standcup speichern (Ctrl+S)">'
        . '<i class="bi bi-save me-1" aria-hidden="true"></i>Speichern</button>'
        . '</span>';
    // Fortschritt: je Runde ein Statuspunkt (grau leer, bernstein offen, grün fertig) mit Zähler
    $page_extra = '<div class="cup4-schritte" id="progress-steps">'
        . '<span class="cup4-schritt" data-round="1"><span class="ui-punkt" aria-hidden="true"></span>Runde 1 <span class="cup4-schritt-zahl" id="step-r1-count"></span></span>'
        . '<i class="bi bi-chevron-right cup4-schritt-pfeil" aria-hidden="true"></i>'
        . '<span class="cup4-schritt" data-round="2"><span class="ui-punkt" aria-hidden="true"></span>Runde 2 <span class="cup4-schritt-zahl" id="step-r2-count"></span></span>'
        . '<i class="bi bi-chevron-right cup4-schritt-pfeil" aria-hidden="true"></i>'
        . '<span class="cup4-schritt" data-round="final"><span class="ui-punkt" aria-hidden="true"></span>Finale <span class="cup4-schritt-zahl" id="step-final-count"></span></span>'
        . '<span class="cup4-sieger" id="cup-sieger" hidden></span>'
        . '</div>';
    $page_show_mobile = true;
    include 'partials/page_header.inc.php';
    ?>

    <!-- Schmale Fenster: eine Spalte nach der anderen -->
    <div class="ui-filter cup4-tabs" id="cup4-tabs" role="group" aria-label="Spalte anzeigen">
        <button type="button" data-target="round1-col" aria-pressed="true">Runde 1</button>
        <button type="button" data-target="round2-col" aria-pressed="false">Runde 2</button>
        <button type="button" data-target="final-wrap" aria-pressed="false">Finale</button>
    </div>

    <!-- Turnierbaum -->
    <div class="cup4-bracket" id="bracket">

        <!-- Teilnehmer, die noch keinen Platz haben -->
        <section class="ui-karte cup4-pool" id="participant-pool" aria-labelledby="pool-titel">
            <div class="ui-tab-kopf">
                <h2 class="ui-tab-titel" id="pool-titel">Teilnehmer</h2>
                <span class="cup4-zahl" id="pool-counter"></span>
            </div>
            <p class="cup4-hinweis">Ziehen oder anklicken, dann einen freien Platz wählen.</p>
            <div id="pool-list" class="cup4-liste"></div>
            <p class="cup4-hinweis cup4-pool-fertig" id="pool-fertig" hidden><i class="bi bi-check-lg" aria-hidden="true"></i> Alle eingeteilt.</p>
        </section>

        <!-- Runde 1 -->
        <section class="ui-karte cup4-round active" id="round1-col" aria-labelledby="r1-titel">
            <div class="ui-tab-kopf">
                <h2 class="ui-tab-titel" id="r1-titel">Runde 1</h2>
                <button type="button" class="btn-help" data-help="cup.runden" aria-label="Hilfe"></button>
                <span class="cup4-zahl" id="r1-badge"></span>
            </div>
            <div id="r1-pairs" class="cup4-paarungen"></div>
            <div class="ui-leerzustand" id="r1-empty"><i class="bi bi-diagram-2" aria-hidden="true"></i><p>Noch keine Paarungen. Unten anlegen.</p></div>
            <div class="cup4-anlegen">
                <label for="pair-count">Neue Paarungen</label>
                <input type="number" id="pair-count" class="form-control form-control-sm" min="1" max="10" value="4">
                <label for="pair-size" class="visually-hidden">Grösse</label>
                <select id="pair-size" class="form-select form-select-sm">
                    <option value="2">Zweier</option>
                    <option value="3">Dreier</option>
                </select>
                <button type="button" id="generate-pairs" class="btn btn-outline-success btn-sm" data-tooltip="Leere Paarungen unten an Runde 1 anfügen">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Hinzufügen
                </button>
            </div>
        </section>

        <!-- Runde 2 -->
        <section class="ui-karte cup4-round" id="round2-col" aria-labelledby="r2-titel">
            <div class="ui-tab-kopf">
                <h2 class="ui-tab-titel" id="r2-titel">Runde 2</h2>
                <span class="cup4-zahl" id="r2-badge"></span>
            </div>
            <!-- Wer aus Runde 1 weiterkommt und noch keinen Platz hat -->
            <div id="r2-winners-pool" class="cup4-bereit" hidden>
                <div class="cup4-abschnitt">
                    <span class="cup4-abschnitt-titel">Weiter aus Runde 1 <span class="cup4-zahl" id="r2-pool-counter"></span></span>
                    <button type="button" class="btn-help" data-help="cup.nachruecker" aria-label="Hilfe"></button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="btn-nachnominierung" data-tooltip="Einen Verlierer aus Runde 1 für Runde 2 nachnehmen">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nachrücker
                    </button>
                </div>
                <div id="r2-pool-list" class="cup4-liste"></div>
            </div>
            <div id="r2-pairs" class="cup4-paarungen"></div>
            <div class="ui-leerzustand" id="r2-empty"><i class="bi bi-hourglass-split" aria-hidden="true"></i><p>Runde 1 zuerst abschliessen.</p></div>
        </section>

        <!-- Finale + Standcup (4. Spalte) -->
        <div class="cup4-final-wrap cup4-round" id="final-wrap">
            <section class="ui-karte cup4-final-section" id="final-col" aria-labelledby="final-titel">
                <div class="ui-tab-kopf">
                    <h2 class="ui-tab-titel" id="final-titel">Finale</h2>
                    <button type="button" class="btn-help" data-help="cup.finale" aria-label="Hilfe"></button>
                    <span class="cup4-zahl" id="final-badge"></span>
                </div>
                <div class="cup4-katb-switch form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="katb-final-switch" checked>
                    <label class="form-check-label" for="katb-final-switch" data-tooltip="Der beste Kat.-B-Schütze aus Runde 2 rückt ins Finale nach, auch wenn er verloren hat. Wirkt sofort.">Bester Kat. B rückt ins Finale nach</label>
                </div>
                <div id="final-list" class="cup4-liste"></div>
                <div class="ui-leerzustand" id="final-empty"><i class="bi bi-flag" aria-hidden="true"></i><p>Noch keine Finalisten.</p></div>
            </section>

            <!-- Standcup Final: je ein Teilnehmer der drei Vereine (Entscheid 08.10.2026: bleibt so) -->
            <section class="ui-karte cup4-standcup" id="standcup-section" aria-labelledby="standcup-titel" hidden>
                <div class="ui-tab-kopf">
                    <h2 class="ui-tab-titel" id="standcup-titel">Standcup Final</h2>
                </div>
                <div class="cup4-standcup-row">
                    <label class="cup4-standcup-label" for="sc-name-1">MSV Wilen</label>
                    <input type="text" id="sc-name-1" class="form-control form-control-sm cup4-standcup-name" placeholder="Name Vorname">
                    <input type="number" id="sc-result-1" class="form-control form-control-sm cup4-result" min="0" max="100" placeholder="Pkt" aria-label="MSV Wilen: Punkte">
                </div>
                <div class="cup4-standcup-row">
                    <label class="cup4-standcup-label" for="sc-name-2">SV Wollerau</label>
                    <input type="text" id="sc-name-2" class="form-control form-control-sm cup4-standcup-name" placeholder="Name Vorname">
                    <input type="number" id="sc-result-2" class="form-control form-control-sm cup4-result" min="0" max="100" placeholder="Pkt" aria-label="SV Wollerau: Punkte">
                </div>
                <div class="cup4-standcup-row">
                    <label class="cup4-standcup-label" for="sc-name-3">SV Freienbach</label>
                    <input type="text" id="sc-name-3" class="form-control form-control-sm cup4-standcup-name" placeholder="Name Vorname">
                    <input type="number" id="sc-result-3" class="form-control form-control-sm cup4-result" min="0" max="100" placeholder="Pkt" aria-label="SV Freienbach: Punkte">
                </div>
            </section>
        </div>
    </div>

    </div><!-- main-content-wrapper -->
</div>
</div>
</div>

<script>
$(document).ready(function() {

    /* ── CSRF für alle POST-Requests ──────── */
    $.ajaxSetup({
        beforeSend: function(xhr, settings) {
            if (settings.type === 'POST') {
                xhr.setRequestHeader('X-CSRF-TOKEN', CUP4_CSRF);
            }
        }
    });

    /* ── Ctrl+S speichert alles ───────────── */
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            if (!$('#save-all').prop('disabled')) $('#save-all').trigger('click');
        }
    });

    /* ── Year Dropdown ────────────────────── */
    function initYearDropdown() {
        msvJahrAuswahl('#yearSelect', { jahr: <?php echo isset($_GET['year']) ? (int)$_GET['year'] : 'null'; ?> });
    }
    function jahr() { return $('#yearSelect').val(); }

    /* ── Schmale Fenster: Spalten-Umschalter ─ */
    $('#cup4-tabs').on('click', 'button', function() {
        $('#cup4-tabs button').attr('aria-pressed', 'false');
        $(this).attr('aria-pressed', 'true');
        $('.cup4-round').removeClass('active');
        $('#' + $(this).data('target')).addClass('active');
    });

    /* ── Bausteine: Zeilen und Paarungen ───── */
    // Ein Platz in einer Paarung: leer oder mit Teilnehmer (Name, ggf. Nachrücker-Marke, Herausnehmen)
    function platzInhalt(id, name) {
        return '<span class="cup4-zone-name">' + msvEsc(name) + '</span>' +
               '<button type="button" class="cup4-zone-remove" aria-label="' + msvEsc(name) + ' herausnehmen" data-tooltip="Herausnehmen (Entf)">' +
               '<i class="bi bi-x" aria-hidden="true"></i></button>';
    }
    function zeileHtml(id, name, resultat) {
        const belegt = id ? ' data-id="' + msvEsc(id) + '"' : '';
        const wert = (resultat !== null && resultat !== undefined && resultat !== '') ? ' value="' + msvEsc(resultat) + '"' : '';
        return '<div class="cup4-participant-row">' +
               '<i class="bi bi-check-lg cup4-sieg" aria-hidden="true"></i>' +
               '<div class="cup4-drop-zone"' + belegt + '>' + (id ? platzInhalt(id, name) : '') + '</div>' +
               '<span class="visually-hidden cup4-sr-status"></span>' +
               '<input type="number" class="cup4-result form-control form-control-sm" min="0" max="100" placeholder="Pkt" aria-label="Punkte"' + wert + '>' +
               '</div>';
    }
    // Dreier: wie viele kommen weiter (Segment-Schalter wie überall)
    function advancersToggle(val) {
        const v = (parseInt(val, 10) === 1) ? 1 : 2;
        return '<span class="ui-filter cup4-adv-toggle" role="group" aria-label="Wie viele kommen weiter?">' +
               '<button type="button" class="cup4-adv-btn" data-adv="1" aria-pressed="' + (v === 1) + '">1 weiter</button>' +
               '<button type="button" class="cup4-adv-btn" data-adv="2" aria-pressed="' + (v === 2) + '">2 weiter</button>' +
               '</span>';
    }
    // Paarung: Kopf (Nummer, Hinweis bei Gleichstand, Löschen), zwei oder drei Zeilen, bei Dreiern darunter «weiter»
    function paarungHtml(o) {
        const drei = o.zeilen.length === 3;
        let attr = '';
        if (o.id) attr += ' data-pair-id="' + msvEsc(o.id) + '"';
        if (drei) attr += ' data-advancers="' + ((parseInt(o.adv, 10) === 1) ? 1 : 2) + '"';
        if (o.mw) attr += ' data-manual-winner="' + msvEsc(o.mw) + '"';
        let html = '<div class="cup4-pair-card' + (drei ? ' cup4-dreier' : '') + '"' + attr + '>' +
                   '<div class="cup4-pair-kopf">' +
                   '<span class="cup4-pair-titel"></span>' +
                   '<span class="cup4-pair-hinweis" aria-live="polite"></span>' +
                   '<button type="button" class="cup4-card-remove" aria-label="Paarung löschen" data-tooltip="Paarung löschen"><i class="bi bi-trash3" aria-hidden="true"></i></button>' +
                   '</div>';
        o.zeilen.forEach(function(z) { html += zeileHtml(z.id, z.name, z.r); });
        if (drei) html += '<div class="cup4-pair-fuss"><span aria-hidden="true">Weiter kommen</span>' + advancersToggle(o.adv) + '</div>';
        return html + '</div>';
    }
    function poolItemHtml(id, name, opts) {
        opts = opts || {};
        const nr = opts.nachruecker
            ? '<span class="cup4-nr" data-tooltip="Nachrücker: in Runde 2, ohne Runde 1 gewonnen zu haben"><i class="bi bi-person-plus" aria-hidden="true"></i><span class="visually-hidden">(Nachrücker)</span></span>'
            : '';
        return '<div class="cup4-pool-item' + (opts.nachruecker ? ' cup4-nominated' : '') + '" data-id="' + msvEsc(id) + '" data-name="' + msvEsc(name) + '"' +
               (opts.live ? ' data-live-preview="1"' : '') + (opts.nachruecker ? ' data-nominated="1"' : '') + '>' +
               '<i class="bi bi-grip-vertical cup4-griff" aria-hidden="true"></i><span class="cup4-pool-name">' + msvEsc(name) + '</span>' + nr + '</div>';
    }
    function finalItemHtml(id, name, resultat, opts) {
        opts = opts || {};
        const wert = (resultat !== null && resultat !== undefined && String(resultat).trim() !== '') ? ' value="' + msvEsc(resultat) + '"' : '';
        return '<div class="cup4-final-item"' + (opts.live ? ' data-live-preview="1"' : '') + (opts.katb ? ' data-katb="1"' : '') + '>' +
               '<span class="rang-badge cup4-rang" aria-hidden="true">–</span>' +
               '<span class="cup4-final-name" data-id="' + msvEsc(id) + '">' + msvEsc(name) + '</span>' +
               (opts.katb ? '<span class="cup4-marke" data-tooltip="Bester Kat. B, rückt ins Finale nach">Kat. B</span>' : '') +
               '<span class="visually-hidden cup4-sr-status"></span>' +
               '<input type="number" class="cup4-result form-control form-control-sm" min="0" max="100" placeholder="Pkt" aria-label="Punkte"' + wert + '>' +
               '</div>';
    }

    /* ── Fortschritt ──────────────────────── */
    // Punktefelder für Bildschirmleser benennen («Punkte Muster Hans» statt nur «Punkte»); läuft mit jedem
    // updateProgress(), also nach Laden, Ablegen, Entfernen und Eingabe
    function cupFeldNamen() {
        $('#bracket .cup4-participant-row .cup4-result, #bracket .cup4-final-item .cup4-result').each(function() {
            const $zeile = $(this).closest('.cup4-participant-row, .cup4-final-item');
            const $zone = $zeile.find('.cup4-drop-zone[data-id]');
            const name = $zone.length ? zoneName($zone) : $zeile.find('.cup4-final-name').text().trim();
            this.setAttribute('aria-label', name ? 'Punkte ' + name : 'Punkte (noch niemand eingeteilt)');
        });
    }

    function schritt($s, fertig, gesamt) {
        $s.removeClass('ok offen');
        if (gesamt > 0) $s.addClass(fertig === gesamt ? 'ok' : 'offen');
        $s.find('.cup4-schritt-zahl').text(gesamt > 0 ? fertig + '/' + gesamt : '');
    }

    function updateProgress() {
        cupFeldNamen();
        const r1Total = $('#r1-pairs .cup4-pair-card').length;
        const r1Done = $('#r1-pairs .cup4-pair-card.cup4-pair-complete').length;
        const r2Total = $('#r2-pairs .cup4-pair-card').length;
        const r2Done = $('#r2-pairs .cup4-pair-card.cup4-pair-complete').length;
        const fTotal = $('#final-list .cup4-final-item').length;
        const fDone = $('#final-list .cup4-final-item').filter(function() {
            return $(this).find('.cup4-result').val() !== '';
        }).length;

        schritt($('.cup4-schritt[data-round="1"]'), r1Done, r1Total);
        schritt($('.cup4-schritt[data-round="2"]'), r2Done, r2Total);
        schritt($('.cup4-schritt[data-round="final"]'), fDone, fTotal);

        $('#r1-badge').text(r1Total ? r1Total + ' Paarung' + (r1Total !== 1 ? 'en' : '') : '');
        $('#r2-badge').text(r2Total ? r2Total + ' Paarung' + (r2Total !== 1 ? 'en' : '') : '');
        $('#final-badge').text(fTotal ? fTotal + ' Finalist' + (fTotal !== 1 ? 'en' : '') : '');

        $('#r1-empty').toggle(r1Total === 0);
        const r2PoolCount = $('#r2-pool-list .cup4-pool-item').length;
        $('#r2-empty').toggle(r2Total === 0 && r2PoolCount === 0);
        $('#final-empty').toggle(fTotal === 0);

        finalRang();
    }

    // Rang im Finale: erst wenn alle Finalisten Punkte haben; gleiche Punkte teilen den Rang
    function finalRang() {
        const $items = $('#final-list .cup4-final-item');
        const werte = $items.map(function() { return $(this).find('.cup4-result').val(); }).get();
        const komplett = $items.length > 1 && werte.every(function(v) { return v !== ''; });
        $items.removeClass('rank-1 rank-2 rank-3');
        $items.find('.cup4-rang').removeClass('r1 r2 r3').text('–');
        $items.find('.cup4-sr-status').text('');
        const $sieger = $('#cup-sieger');
        if (!komplett) { $sieger.prop('hidden', true).empty(); return; }
        const zahlen = werte.map(function(v) { return parseInt(v, 10) || 0; });
        $items.each(function(i) {
            const rang = 1 + zahlen.filter(function(z) { return z > zahlen[i]; }).length;
            $(this).find('.cup4-rang').text(rang + '.').addClass(rang <= 3 ? 'r' + rang : '');
            if (rang <= 3) $(this).addClass('rank-' + rang);
            $(this).find('.cup4-sr-status').text('Rang ' + rang);
        });
        const sieger = $items.filter('.rank-1').map(function() { return $(this).find('.cup4-final-name').text(); }).get();
        $sieger.prop('hidden', false).html('<i class="bi bi-trophy" aria-hidden="true"></i> Cupsieger ' + msvEsc(jahr()) + ': <strong>' + msvEsc(sieger.join(' und ')) + '</strong>');
    }

    /* ── Zähler der Teilnehmer-Liste (der Server liefert nur, wer noch keinen Platz hat) ── */
    function updatePoolCounter() {
        const frei = $('#pool-list .cup4-pool-item').length;
        $('#pool-counter').text(frei ? frei + ' frei' : '');
        $('#pool-fertig').prop('hidden', !(frei === 0 && $('#r1-pairs .cup4-drop-zone[data-id]').length > 0));
    }

    function updateR2PoolCounter() {
        const cnt = $('#r2-pool-list .cup4-pool-item').length;
        $('#r2-pool-counter').text(cnt);
        $('#r2-winners-pool').prop('hidden', cnt === 0);
    }

    /* ── Teilnehmer laden ─────────────────── */
    function loadParticipants() {
        $.ajax({
            url: 'cup2/fetch_participants.php',
            data: { year: jahr() },
            dataType: 'json',
            success: function(response) {
                const participants = (response && response.data) || response || [];
                const $list = $('#pool-list').empty();
                participants.forEach(function(p) {
                    $list.append(poolItemHtml(p.ID, p.Name + ' ' + p.Vorname));
                });
                initDraggable('#pool-list .cup4-pool-item');
                removeUsedParticipants();
                updatePoolCounter();
            },
            error: function(xhr) { msvToast(msvXhrMessage(xhr, 'Die Teilnehmer konnten nicht geladen werden. Bitte die Seite neu laden.'), 'error'); }
        });
    }

    /* ── Ziehen und Ablegen ───────────────── */
    function initDraggable(selector) {
        $(selector).draggable({
            helper: 'clone',
            revert: 'invalid',
            appendTo: 'body',
            containment: 'window',
            distance: 5,
            scroll: false,
            start: function(event, ui) {
                $(ui.helper).css({ 'z-index': 9999, position: 'absolute', 'pointer-events': 'none' });
            }
        });
    }

    function initDroppable(container) {
        $(container).find('.cup4-drop-zone').each(function() {
            if (!$(this).data('ui-droppable')) {
                $(this).droppable({
                    accept: '.cup4-pool-item',
                    hoverClass: 'ui-droppable-hover',
                    tolerance: 'pointer',
                    drop: function(event, ui) {
                        zuordnen($(this), ui.helper.attr('data-id'), ui.helper.attr('data-name'));
                        ui.helper.remove();
                    }
                });
            }
        });
    }

    /* ── Ungespeicherte Änderungen: Resultate, Zuordnungen, Gewinnerwahl, «weiter», Standcup ──
       Hinweis neben «Speichern», Warnung beim Verlassen, Rückfrage beim Jahreswechsel und vor dem Löschen. */
    let cupGeaendert = false, cupJahr = null, cupJahrNachher = null, cupStandcupGeaendert = false;
    function cupMarkieren(an) {
        cupGeaendert = !!an;
        if (!an) cupStandcupGeaendert = false;
        msvPanelUngespeichert($('.cup4-kopf-aktionen'), cupGeaendert);
    }
    $(document).on('input', '#bracket input:not(#katb-final-switch)', function() {
        if ($(this).closest('#standcup-section').length) cupStandcupGeaendert = true;
        cupMarkieren(true);
    });
    $(window).on('beforeunload', function() { if (cupGeaendert) return 'Nicht gespeicherte Eingaben'; });

    // Vor PDF und Direktdruck (lesen die DB): bei Ungespeichertem zuerst speichern oder abbrechen.
    // «Speichern» löst #save-all aus; cupGespeichert() meldet das Ende.
    let cupSpeichernFertig = null;
    function cupGespeichert(ok) {
        if (!cupSpeichernFertig) return;
        const fertig = cupSpeichernFertig;
        cupSpeichernFertig = null;
        fertig(ok);
    }
    window.cupVorAusgabe = function() {
        return msvVorAusgabe(cupGeaendert, function() {
            return new Promise(function(fertig) {
                if ($('#save-all').prop('disabled')) { fertig(false); return; }
                cupSpeichernFertig = fertig;
                $('#save-all').trigger('click');
            });
        });
    };

    // Ändern sich die Teilnehmer einer Paarung, gilt eine frühere Gewinnerwahl nicht mehr
    function gewinnerwahlZuruecksetzen($card) {
        $card.removeData('first-winner manual-winner').removeAttr('data-first-winner data-manual-winner');
    }

    /* ── Teilnehmer auf einen Platz setzen (Ziehen, Tastatur, Klick) ── */
    function zuordnen($zone, newId, name) {
        cupMarkieren(true);
        name = String(name || '').trim();
        const $card = $zone.closest('.cup4-pair-card');

        // Platz schon belegt: bisherigen Teilnehmer in die richtige Liste zurück
        const oldId = $zone.attr('data-id');
        if (oldId) {
            const inR2 = $zone.closest('#r2-pairs').length > 0;
            inR2 ? returnToR2Pool(oldId, zoneName($zone)) : returnToPool(oldId, zoneName($zone));
        }

        $zone.attr('data-id', newId).html(platzInhalt(newId, name));
        if ($zone.closest('#r2-pairs').length && !istR1Gewinner(newId)) nachrueckerMarke($zone);
        gewinnerwahlZuruecksetzen($card);

        $('#pool-list .cup4-pool-item[data-id="' + newId + '"]').remove();
        $('#r2-pool-list .cup4-pool-item[data-id="' + newId + '"]').remove();

        updatePoolCounter();
        updateR2PoolCounter();
        updateWinnerHighlight($card);
    }

    /* ── Zuordnen ohne Ziehen: Teilnehmer wählen (Enter, Leertaste oder Klick), dann den Platz ──
       Entf auf einem belegten Platz nimmt den Teilnehmer heraus, Escape hebt die Wahl auf. */
    let cupGewaehlt = null;
    const cupAnsage = $('<div class="visually-hidden" aria-live="polite"></div>').appendTo('body');
    function cupWahl($item) {
        $('.cup4-pool-item.cup4-gewaehlt').removeClass('cup4-gewaehlt').attr('aria-pressed', 'false');
        $('#bracket').removeClass('cup4-wahl-aktiv');
        cupGewaehlt = $item && $item.length ? $item : null;
        if (cupGewaehlt) {
            cupGewaehlt.addClass('cup4-gewaehlt').attr('aria-pressed', 'true');
            $('#bracket').addClass('cup4-wahl-aktiv');
            cupAnsage.text(cupGewaehlt.attr('data-name') + ' gewählt. Jetzt einen Platz in einer Paarung wählen.');
        }
    }
    // Ansage-Name eines Platzes: «Lienert Roman, Runde 1, Paarung 2»
    function platzOrt(z) {
        const $z = $(z);
        const runde = $z.closest('#r2-pairs').length ? 'Runde 2' : 'Runde 1';
        const nr = $z.closest('.cup4-paarungen').children('.cup4-pair-card').index($z.closest('.cup4-pair-card')) + 1;
        return runde + ', Paarung ' + nr;
    }
    function cupTastaturBereit() {
        $('.cup4-pool-item:not([tabindex])').attr({ tabindex: 0, role: 'button', 'aria-pressed': 'false' });
        $('.cup4-drop-zone:not([tabindex])').attr({ tabindex: 0, role: 'button' });
        document.querySelectorAll('.cup4-drop-zone').forEach(function(z) {
            const n = ((z.querySelector('.cup4-zone-name') || {}).textContent || '').trim();
            const label = n ? n + ', ' + platzOrt(z) + ' (Entf nimmt heraus)' : 'Freier Platz, ' + platzOrt(z);
            if (z.getAttribute('aria-label') !== label) z.setAttribute('aria-label', label);
        });
    }
    new MutationObserver(cupTastaturBereit).observe(document.getElementById('bracket'), { childList: true, subtree: true });
    cupTastaturBereit();
    $(document).on('click', '.cup4-pool-item', function() {
        if ($(this).hasClass('ui-draggable-dragging')) return;
        cupWahl($(this).hasClass('cup4-gewaehlt') ? null : $(this));
    });
    $(document).on('click', '.cup4-drop-zone', function(e) {
        if (!cupGewaehlt || $(e.target).closest('button').length) return;
        const $zone = $(this);
        zuordnen($zone, cupGewaehlt.attr('data-id'), cupGewaehlt.attr('data-name'));
        cupAnsage.text(zoneName($zone) + ' zugeordnet.');
        cupWahl(null);
        $zone.trigger('focus');
    });
    $(document).on('keydown', '.cup4-pool-item, .cup4-drop-zone', function(e) {
        if (e.target !== this) return;
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $(this).trigger('click'); }
        else if (e.key === 'Escape' && cupGewaehlt) { e.preventDefault(); cupWahl(null); cupAnsage.text('Wahl aufgehoben.'); }
        else if ((e.key === 'Delete' || e.key === 'Backspace') && $(this).is('.cup4-drop-zone[data-id]')) {
            e.preventDefault();
            $(this).find('.cup4-zone-remove').trigger('click');
        }
    });

    /* ── Einzelnen Teilnehmer aus einem Platz nehmen ── */
    $(document).on('click', '.cup4-zone-remove', function(e) {
        e.stopPropagation();
        const $zone = $(this).closest('.cup4-drop-zone');
        const id = $zone.attr('data-id');
        if (!id) return;
        cupMarkieren(true);
        const name = zoneName($zone);

        const inR2 = $zone.closest('#r2-pairs').length > 0;
        inR2 ? returnToR2Pool(id, name) : returnToPool(id, name);

        $zone.removeAttr('data-id').empty();
        const $card = $zone.closest('.cup4-pair-card');
        gewinnerwahlZuruecksetzen($card);
        updateWinnerHighlight($card);
        $zone.trigger('focus');
    });

    function zoneName($zone) {
        return $zone.find('.cup4-zone-name').text().trim();
    }

    /* ── Zurück in die Listen ─────────────── */
    function returnToPool(id, name) {
        if (!id || $('#pool-list .cup4-pool-item[data-id="' + id + '"]').length > 0) return;
        const $item = $(poolItemHtml(id, name));
        $('#pool-list').append($item);
        initDraggable($item);
        updatePoolCounter();
    }

    function returnToR2Pool(id, name) {
        if (!id || $('#r2-pool-list .cup4-pool-item[data-id="' + id + '"]').length > 0) return;
        const $item = $(poolItemHtml(id, name, { nachruecker: !istR1Gewinner(id) }));
        $('#r2-pool-list').append($item);
        initDraggable($item);
        updateR2PoolCounter();
    }

    /* ── Nachrücker ───────────────────────── */
    let r1WinnerIds = {}; // R1-Gewinner { "id": true }
    let cupKatBToFinal = true; // Bester Kat. B rückt ins Finale nach

    function loadCupSettings(callback) {
        $.ajax({
            url: 'cup2/cup_settings.php',
            data: { year: jahr() },
            dataType: 'json',
            success: function(resp) {
                cupKatBToFinal = !resp || resp.katb_to_final !== 0;
                $('#katb-final-switch').prop('checked', cupKatBToFinal);
                if (typeof callback === 'function') callback();
            },
            error: function(xhr) {
                msvToast(msvXhrMessage(xhr, 'Die Cup-Einstellungen konnten nicht geladen werden; es gilt der Standard (Kat. B rückt nach).'), 'warning');
                cupKatBToFinal = true;
                $('#katb-final-switch').prop('checked', true);
                if (typeof callback === 'function') callback();
            }
        });
    }

    // Gewinner aus Runde 1: gespeichert (Server) oder gerade erfasst (Anzeige)
    function istR1Gewinner(id) {
        return !!r1WinnerIds[String(id)] || $('#r1-pairs .cup4-participant-row.winner .cup4-drop-zone[data-id="' + id + '"]').length > 0;
    }

    function nachrueckerMarke($zone) {
        if ($zone.find('.cup4-nr').length) return;
        $zone.find('.cup4-zone-name').after('<span class="cup4-nr" data-tooltip="Nachrücker: in Runde 2, ohne Runde 1 gewonnen zu haben"><i class="bi bi-person-plus" aria-hidden="true"></i><span class="visually-hidden">(Nachrücker)</span></span>');
    }

    // Nachrücker in Runde-2-Paarungen markieren (= wer dort steht, ohne Runde 1 gewonnen zu haben)
    function markNachnominiertInR2() {
        $('#r2-pairs .cup4-drop-zone[data-id]').each(function() {
            if (!istR1Gewinner($(this).attr('data-id'))) nachrueckerMarke($(this));
        });
    }

    $('#btn-nachnominierung').on('click', function() {
        const usedInR2 = {};
        $('#r2-pool-list .cup4-pool-item, #r2-pairs .cup4-drop-zone[data-id]').each(function() {
            usedInR2[$(this).attr('data-id')] = true;
        });

        // Verlierer aus Runde 1, die noch nicht in Runde 2 stehen
        const losers = [];
        $('#r1-pairs .cup4-participant-row.loser .cup4-drop-zone[data-id]').each(function() {
            const id = $(this).attr('data-id');
            if (!usedInR2[id]) losers.push({ id: id, name: zoneName($(this)) });
        });

        if (losers.length === 0) {
            msvToast('In Runde 1 gibt es noch keinen Verlierer, der nachrücken könnte.', 'info');
            return;
        }

        let radioHtml = '<div class="cup4-nr-wahl">';
        losers.forEach(function(l, i) {
            radioHtml += '<label><input type="radio" name="nachn" value="' + msvEsc(l.id) + '" data-name="' + msvEsc(l.name) + '"' +
                (i === 0 ? ' checked' : '') + '> ' + msvEsc(l.name) + '</label>';
        });
        radioHtml += '</div>';

        msvSwal.fire({
            title: 'Nachrücker für Runde 2',
            html: '<p class="text-start mb-2">Wer aus Runde 1 rückt nach?</p>' + radioHtml,
            showCancelButton: true,
            confirmButtonText: 'Nachrücken lassen',
            cancelButtonText: 'Abbrechen',
            preConfirm: function() {
                const checked = document.querySelector('input[name="nachn"]:checked');
                if (!checked) { Swal.showValidationMessage('Bitte jemanden wählen.'); return false; }
                return { id: checked.value, name: checked.getAttribute('data-name') };
            }
        }).then(function(result) {
            if (!result.isConfirmed) return;
            returnToR2Pool(result.value.id, result.value.name);
            autoGenerateR2Pairs();
            updateProgress();
            msvToast(result.value.name + ' rückt in Runde 2 nach', 'success');
        });
    });

    /* ── Bereits eingeteilte aus der Teilnehmer-Liste nehmen ── */
    function removeUsedParticipants() {
        $('#r1-pairs .cup4-drop-zone[data-id], #r2-pairs .cup4-drop-zone[data-id]').each(function() {
            $('#pool-list .cup4-pool-item[data-id="' + $(this).attr('data-id') + '"]').remove();
        });
        updatePoolCounter();
    }

    /* ── Gewinner (live) ──────────────────── */
    function wahlKnopf($row, id, text) {
        const nm = zoneName($row.find('.cup4-drop-zone'));
        $row.addClass('cup4-tied').append(
            '<button type="button" class="btn btn-outline-primary btn-sm cup4-tie-pick" data-winner-id="' + msvEsc(id) + '" aria-label="' + msvEsc(nm) + ' kommt weiter">' + text + '</button>'
        );
    }

    function updateWinnerHighlight($card) {
        if (!$card || $card.length === 0) return;
        const $rows = $card.find('.cup4-participant-row');
        $rows.removeClass('winner loser cup4-tied');
        $card.removeClass('cup4-pair-complete cup4-pair-tied');
        $card.find('.cup4-tie-pick').remove();
        const $hinweis = $card.find('.cup4-pair-hinweis').text('');

        const manualWinner = $card.data('manual-winner');

        if ($rows.length === 2) {
            const id1 = $rows.eq(0).find('.cup4-drop-zone').attr('data-id');
            const id2 = $rows.eq(1).find('.cup4-drop-zone').attr('data-id');
            const r1 = parseInt($rows.eq(0).find('.cup4-result').val()) || 0;
            const r2 = parseInt($rows.eq(1).find('.cup4-result').val()) || 0;

            if (r1 === 0 && r2 === 0) { srStatus($card); updateProgress(); folgerunden($card); return; }

            if (manualWinner) {
                const erster = String(manualWinner) === String(id1);
                $rows.eq(erster ? 0 : 1).addClass('winner');
                $rows.eq(erster ? 1 : 0).addClass('loser');
                $card.addClass('cup4-pair-complete');
            } else if (r1 === r2 && r1 > 0) {
                // Gleichstand: wer weiterkommt, wählt der Mensch
                $rows.each(function() {
                    const pid = $(this).find('.cup4-drop-zone').attr('data-id');
                    if (pid) wahlKnopf($(this), pid, 'Weiter');
                });
                $hinweis.text('Gleichstand: wer kommt weiter?');
                $card.addClass('cup4-pair-tied');
            } else if (r1 > r2) {
                $rows.eq(0).addClass('winner');
                $rows.eq(1).addClass('loser');
                $card.addClass('cup4-pair-complete');
            } else {
                $rows.eq(1).addClass('winner');
                $rows.eq(0).addClass('loser');
                $card.addClass('cup4-pair-complete');
            }
        } else if ($rows.length === 3) {
            const scores = [];
            $rows.each(function(i) {
                scores.push({
                    idx: i,
                    id: $(this).find('.cup4-drop-zone').attr('data-id'),
                    r: parseInt($(this).find('.cup4-result').val()) || 0
                });
            });

            if (scores.every(s => s.r === 0)) { srStatus($card); updateProgress(); folgerunden($card); return; }

            const advancers = parseInt($card.data('advancers'), 10) || 2;
            const mwVal = manualWinner ? parseInt(manualWinner) : 0;

            if (advancers === 1) {
                // Nur einer kommt weiter
                if (mwVal > 0) {
                    scores.forEach(function(s) {
                        $rows.eq(s.idx).addClass(String(s.id) === String(mwVal) ? 'winner' : 'loser');
                    });
                    $card.addClass('cup4-pair-complete');
                } else {
                    scores.sort((a, b) => b.r - a.r);
                    const maxR = scores[0].r;
                    const topTied = scores.filter(function(s) { return s.r === maxR; });
                    if (topTied.length === 1) {
                        $rows.eq(scores[0].idx).addClass('winner');
                        $rows.eq(scores[1].idx).addClass('loser');
                        $rows.eq(scores[2].idx).addClass('loser');
                        $card.addClass('cup4-pair-complete');
                    } else {
                        scores.forEach(function(s) {
                            if (s.r === maxR) wahlKnopf($rows.eq(s.idx), s.id, 'Weiter');
                            else $rows.eq(s.idx).addClass('loser');
                        });
                        $hinweis.text('Gleichstand: wer kommt weiter?');
                        $card.addClass('cup4-pair-tied');
                    }
                }
            } else if (mwVal < 0) {
                // Negativer manueller Gewinner = Dreier-Gleichstand aufgelöst: abs(ID) ist ausgeschieden
                const loserId = String(Math.abs(mwVal));
                scores.forEach(function(s) {
                    $rows.eq(s.idx).addClass(String(s.id) === loserId ? 'loser' : 'winner');
                });
                $card.addClass('cup4-pair-complete');
            } else if (mwVal > 0) {
                // Positiver manueller Gewinner = Gleichstand um Platz 2 aufgelöst
                const mwId = String(mwVal);
                $rows.eq(scores.find(s => String(s.id) === mwId).idx).addClass('winner');
                const remaining = scores.filter(s => String(s.id) !== mwId).sort((a, b) => b.r - a.r);
                $rows.eq(remaining[0].idx).addClass('winner');
                $rows.eq(remaining[1].idx).addClass('loser');
                $card.addClass('cup4-pair-complete');
            } else {
                scores.sort((a, b) => b.r - a.r);

                const allThreeTied = scores[0].r === scores[2].r && scores[0].r > 0;
                const topTwoTied = !allThreeTied && scores[0].r === scores[1].r && scores[0].r > 0;
                const bottomTwoTied = !allThreeTied && !topTwoTied && scores[1].r === scores[2].r && scores[1].r > 0;

                if (allThreeTied) {
                    // Alle drei gleich: zwei nacheinander wählen; der Hinweis sagt, wo man steht
                    const firstWinner = $card.data('first-winner');
                    scores.forEach(function(s) {
                        if (firstWinner && String(s.id) === String(firstWinner)) $rows.eq(s.idx).addClass('winner');
                        else wahlKnopf($rows.eq(s.idx), s.id, 'Weiter');
                    });
                    $hinweis.text(firstWinner ? 'Gleichstand: wer kommt als Zweiter weiter?' : 'Gleichstand: wähle die zwei, die weiterkommen.');
                    $card.addClass('cup4-pair-tied');
                } else if (topTwoTied) {
                    $rows.eq(scores[0].idx).addClass('winner');
                    $rows.eq(scores[1].idx).addClass('winner');
                    $rows.eq(scores[2].idx).addClass('loser');
                    $card.addClass('cup4-pair-complete');
                } else if (bottomTwoTied) {
                    $rows.eq(scores[0].idx).addClass('winner');
                    [scores[1], scores[2]].forEach(function(s) { wahlKnopf($rows.eq(s.idx), s.id, 'Weiter'); });
                    $hinweis.text('Gleichstand um Platz 2: wer kommt weiter?');
                    $card.addClass('cup4-pair-tied');
                } else {
                    $rows.eq(scores[0].idx).addClass('winner');
                    $rows.eq(scores[1].idx).addClass('winner');
                    $rows.eq(scores[2].idx).addClass('loser');
                    if (scores.every(s => s.r > 0)) $card.addClass('cup4-pair-complete');
                }
            }
        }

        srStatus($card);
        updateProgress();
        folgerunden($card);
    }

    // Sieg und Niederlage nicht nur über Farbe: Text für Bildschirmleser
    function srStatus($card) {
        $card.find('.cup4-participant-row').each(function() {
            const $r = $(this);
            $r.find('.cup4-sr-status').text($r.hasClass('winner') ? 'kommt weiter' : $r.hasClass('loser') ? 'ausgeschieden' : $r.hasClass('cup4-tied') ? 'Gleichstand' : '');
        });
    }

    // Live-Vorschau: Gewinner von Runde 1 sofort in Runde 2, von Runde 2 sofort im Finale
    let cupLaedt = false;
    function folgerunden($card) {
        if (cupLaedt) return;
        if ($card.closest('#r1-pairs').length) extractR1WinnersToR2Pool();
        if ($card.closest('#r2-pairs').length) extractR2WinnersToFinale();
    }

    /* ── Gewinner Runde 1 → Runde 2 (live) ── */
    function extractR1WinnersToR2Pool() {
        const usedInR2 = {};
        $('#r2-pairs .cup4-drop-zone[data-id]').each(function() { usedInR2[$(this).attr('data-id')] = true; });

        const liveWinners = [];
        $('#r1-pairs .cup4-pair-card.cup4-pair-complete .cup4-participant-row.winner .cup4-drop-zone[data-id]').each(function() {
            const id = $(this).attr('data-id');
            if (!usedInR2[id]) liveWinners.push({ id: id, name: zoneName($(this)) });
        });

        const $pool = $('#r2-pool-list');
        // Gespeicherte Einträge bleiben; Vorschau-Einträge werden neu aufgebaut
        $pool.find('.cup4-pool-item[data-live-preview]').remove();
        liveWinners.forEach(function(w) {
            if ($pool.find('.cup4-pool-item[data-id="' + w.id + '"]').length === 0) {
                const $item = $(poolItemHtml(w.id, w.name, { live: true }));
                $pool.append($item);
                initDraggable($item);
            }
        });

        updateR2PoolCounter();
        autoGenerateR2Pairs();
    }

    /* ── Gewinner Runde 2 → Finale (live) ──── */
    function extractR2WinnersToFinale() {
        const liveFinalists = [];
        $('#r2-pairs .cup4-pair-card.cup4-pair-complete .cup4-participant-row.winner .cup4-drop-zone[data-id]').each(function() {
            liveFinalists.push({ id: $(this).attr('data-id'), name: zoneName($(this)) });
        });

        const $list = $('#final-list');
        $list.find('.cup4-final-item[data-live-preview]').remove();
        liveFinalists.forEach(function(f) {
            if ($list.find('.cup4-final-name[data-id="' + f.id + '"]').length === 0) {
                $list.append(finalItemHtml(f.id, f.name, '', { live: true }));
            }
        });

        updateProgress();
    }

    /* ── Plätze in Runde 2 automatisch ─────── */
    function autoGenerateR2Pairs() {
        const availableInPool = $('#r2-pool-list .cup4-pool-item').length;
        const placedInR2 = $('#r2-pairs .cup4-drop-zone[data-id]').length;
        const totalR2 = availableInPool + placedInR2;

        if (totalR2 < 2) return;

        // Bei ungerader Anzahl eine Dreier-Paarung, sonst Zweier
        // 5 → 1 Dreier + 1 Zweier | 6 → 3 Zweier | 7 → 1 Dreier + 2 Zweier
        const need3 = (totalR2 % 2 !== 0) ? 1 : 0;
        const need2 = Math.max(0, (totalR2 - need3 * 3) / 2);

        const filled = [], empty = [];
        $('#r2-pairs .cup4-pair-card').each(function() {
            const c = { $el: $(this), is3: $(this).find('.cup4-participant-row').length === 3 };
            ($(this).find('.cup4-drop-zone[data-id]').length > 0 ? filled : empty).push(c);
        });

        let filled3 = 0, filled2 = 0, empty3 = 0, empty2 = 0;
        filled.forEach(function(c) { c.is3 ? filled3++ : filled2++; });
        empty.forEach(function(c) { c.is3 ? empty3++ : empty2++; });

        if ((filled3 + empty3) === need3 && (filled2 + empty2) === need2) return;

        empty.forEach(function(c) { c.$el.remove(); });
        const gen3 = Math.max(0, need3 - filled3);
        const gen2 = Math.max(0, need2 - filled2);
        if (gen3 > 0) generatePairSlots(gen3, '#r2-pairs', 3);
        if (gen2 > 0) generatePairSlots(gen2, '#r2-pairs', 2);
    }

    // Resultat geändert: Gewinner neu bestimmen; eine angefangene Dreier-Wahl gilt nicht mehr
    $(document).on('input', '.cup4-pair-card .cup4-result', function() {
        const $card = $(this).closest('.cup4-pair-card');
        $card.removeData('first-winner').removeAttr('data-first-winner');
        updateWinnerHighlight($card);
    });
    $(document).on('input', '#final-list .cup4-result', updateProgress);

    // Gleichstand: Gewinner wählen. Gespeichert wird mit «Speichern» (zusammen mit den Resultaten).
    $(document).on('click', '.cup4-tie-pick', function(e) {
        e.preventDefault();
        cupMarkieren(true);
        const $card = $(this).closest('.cup4-pair-card');
        const winnerId = $(this).data('winner-id');
        const $rows = $card.find('.cup4-participant-row');
        const isThreeWay = $rows.length === 3;
        const firstWinner = $card.data('first-winner');
        const advancers = parseInt($card.data('advancers'), 10) || 2;

        // Dreier, alle gleich, zwei kommen weiter: erster Klick merkt den ersten
        if (isThreeWay && advancers === 2 && !firstWinner && !$card.data('manual-winner')) {
            const vals = [];
            $rows.each(function() { vals.push(parseInt($(this).find('.cup4-result').val()) || 0); });
            if (vals[0] > 0 && vals.every(v => v === vals[0])) {
                $card.data('first-winner', winnerId);
                updateWinnerHighlight($card);
                $card.find('.cup4-tie-pick').first().trigger('focus');
                return;
            }
        }

        if (isThreeWay && firstWinner) {
            // Zweiter Klick: der Dritte scheidet aus → negativer manueller Gewinner
            const allIds = $rows.map(function() { return $(this).find('.cup4-drop-zone').attr('data-id'); }).get();
            const loserId = allIds.find(id => String(id) !== String(firstWinner) && String(id) !== String(winnerId));
            $card.removeData('first-winner');
            $card.attr('data-manual-winner', -Math.abs(parseInt(loserId))).data('manual-winner', -Math.abs(parseInt(loserId)));
        } else {
            $card.attr('data-manual-winner', winnerId).data('manual-winner', winnerId);
        }
        updateWinnerHighlight($card);
    });

    // Dreier: wie viele kommen weiter (1 oder 2)
    $(document).on('click', '.cup4-adv-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $card = $btn.closest('.cup4-pair-card');
        const adv = parseInt($btn.data('adv'), 10);
        if ((parseInt($card.data('advancers'), 10) || 2) === adv) return;
        cupMarkieren(true);

        const isR1 = $card.closest('#r1-pairs').length > 0;

        // Gewinner vor dem Wechsel merken (um Ausscheidende in Runde 2 zu finden)
        const winnersBefore = {};
        $card.find('.cup4-participant-row.winner .cup4-drop-zone[data-id]').each(function() {
            winnersBefore[$(this).attr('data-id')] = true;
        });

        $card.attr('data-advancers', adv).data('advancers', adv);
        $card.find('.cup4-adv-btn').attr('aria-pressed', 'false');
        $btn.attr('aria-pressed', 'true');

        // Eine frühere Gleichstand-Wahl gilt nicht mehr
        gewinnerwahlZuruecksetzen($card);
        updateWinnerHighlight($card);

        // Folgerunde anpassen: wer nicht mehr weiterkommt, verlässt Runde 2
        if (isR1) {
            const winnersAfter = {};
            $card.find('.cup4-participant-row.winner .cup4-drop-zone[data-id]').each(function() {
                winnersAfter[$(this).attr('data-id')] = true;
            });
            let removedFromR2 = false;
            Object.keys(winnersBefore).forEach(function(id) {
                if (winnersAfter[id]) return;
                $('#r2-pairs .cup4-drop-zone[data-id="' + id + '"]').each(function() {
                    $(this).removeAttr('data-id').empty();
                    const $c = $(this).closest('.cup4-pair-card');
                    gewinnerwahlZuruecksetzen($c);
                    updateWinnerHighlight($c);
                    removedFromR2 = true;
                });
                $('#r2-pool-list .cup4-pool-item[data-id="' + id + '"]').remove();
                delete r1WinnerIds[String(id)];
            });
            if (removedFromR2) {
                updateR2PoolCounter();
                autoGenerateR2Pairs();
            }
        }
    });

    /* ── Leere Paarungen anlegen ──────────── */
    function generatePairSlots(count, target, size) {
        if (count <= 0) return;
        const $target = $(target);
        const leer = { id: null, name: '', r: null };
        for (let i = 0; i < count; i++) {
            $target.append(paarungHtml({ zeilen: size == 3 ? [leer, leer, leer] : [leer, leer], adv: 2 }));
        }
        initDroppable($target);
        updateProgress();
    }

    $('#generate-pairs').on('click', function() {
        const count = parseInt($('#pair-count').val()) || 0;
        generatePairSlots(Math.min(count, 10), '#r1-pairs', $('#pair-size').val());
        cupMarkieren(true);
    });

    /* ── Gespeicherte Paarungen laden ─────── */
    function loadSavedPairs(round, targetList, callback) {
        $.ajax({
            url: 'cup2/fetch_pairs.php',
            data: { round: round, year: jahr() },
            dataType: 'json',
            error: function(xhr) { msvToast(msvXhrMessage(xhr, 'Die Paarungen konnten nicht geladen werden. Bitte die Seite neu laden.'), 'error'); },
            success: function(pairs) {
                const $target = $(targetList).empty();
                (pairs || []).forEach(function(pair) {
                    const zeilen = [
                        { id: pair.Participant1, name: pair.Name1 + ' ' + pair.Vorname1, r: pair.Result1 },
                        { id: pair.Participant2, name: pair.Name2 + ' ' + pair.Vorname2, r: pair.Result2 }
                    ];
                    const isThree = pair.Participant3 && pair.Participant3 !== 'NULL' && pair.Participant3 !== '0';
                    if (isThree) zeilen.push({ id: pair.Participant3, name: pair.Name3 + ' ' + pair.Vorname3, r: pair.Result3 });
                    const mw = (pair.ManualWinner != null && parseInt(pair.ManualWinner, 10) !== 0) ? pair.ManualWinner : null;
                    $target.append(paarungHtml({ id: pair.ID, zeilen: zeilen, adv: pair.Advancers, mw: mw }));
                });

                initDroppable($target);
                $target.find('.cup4-pair-card').each(function() {
                    const $c = $(this);
                    // Ohne Folgerunden-Vorschau: die Listen für Runde 2 und Finale kommen danach vom Server
                    cupLaedt = true;
                    updateWinnerHighlight($c);
                    cupLaedt = false;
                });

                removeUsedParticipants();
                updateProgress();

                if (typeof callback === 'function') callback();
            }
        });
    }

    /* ── Gewinner für Runde 2 laden ───────── */
    function loadWinnersForRound2() {
        // R1-Gewinner aus der Anzeige (auch die, die in Runde 2 schon einen Platz haben)
        r1WinnerIds = {};
        $('#r1-pairs .cup4-participant-row.winner .cup4-drop-zone[data-id]').each(function() {
            r1WinnerIds[$(this).attr('data-id')] = true;
        });

        $.ajax({
            url: 'cup2/fetch_winners.php',
            data: { year: jahr() },
            dataType: 'json',
            success: function(winners) {
                const $pool = $('#r2-pool-list').empty();
                (winners || []).forEach(function(w) { r1WinnerIds[String(w.ID)] = true; });

                // Kat. B spielt normal in Runde 2 mit; der beste Kat. B rückt erst danach ins Finale nach
                (winners || []).forEach(function(w) {
                    if ($('#r2-pairs .cup4-drop-zone[data-id="' + w.ID + '"]').length === 0) {
                        $pool.append(poolItemHtml(w.ID, w.Name + ' ' + w.Vorname));
                    }
                });
                initDraggable('#r2-pool-list .cup4-pool-item');
                autoGenerateR2Pairs();

                markNachnominiertInR2();
                updateR2PoolCounter();
                updateProgress();
                loadFinalists();
            },
            error: function(xhr) {
                msvToast(msvXhrMessage(xhr, 'Die Gewinner aus Runde 1 konnten nicht geladen werden. Bitte die Seite neu laden.'), 'error');
            }
        });
    }

    /* ── Finalisten laden ─────────────────── */
    function loadFinalists() {
        $.ajax({
            url: 'cup2/fetch_final_results.php',
            data: { year: jahr() },
            dataType: 'json',
            success: function(finalists) {
                const $list = $('#final-list').empty();
                if (!finalists.error && finalists.length > 0) {
                    finalists.forEach(function(f) {
                        $list.append(finalItemHtml(f.ID, f.Name + ' ' + f.Vorname, f.Result));
                    });
                    const hasResults = finalists.some(f => f.Result !== null && f.Result !== undefined && String(f.Result).trim() !== '');
                    if (hasResults) loadStandcupData();
                }
                updateProgress();
                checkKatBFinalist();
            },
            error: function(xhr) { msvToast(msvXhrMessage(xhr, 'Die Finalresultate konnten nicht geladen werden. Bitte die Seite neu laden.'), 'error'); }
        });
    }

    /* ── Bester Kat. B rückt nach ─────────── */
    function checkKatBFinalist() {
        $.ajax({
            url: 'cup2/check_katb_finalist.php',
            data: { year: jahr() },
            dataType: 'json',
            error: function(xhr) { msvToast(msvXhrMessage(xhr, 'Der Kat.-B-Nachrücker konnte nicht ermittelt werden. Bitte die Seite neu laden.'), 'error'); },
            success: function(resp) {
                if (!(resp.has_single_katb_winner && resp.katb_finalist)) return;
                const f = resp.katb_finalist;
                if ($('#final-list .cup4-final-name[data-id="' + f.ID + '"]').length === 0) {
                    $('#final-list').append(finalItemHtml(f.ID, f.Name + ' ' + f.Vorname, f.Result, { katb: true }));
                    updateProgress();
                }
            }
        });
    }

    /* ── Speichern ────────────────────────── */
    // Ein POST als Promise: erfüllt nur bei Erfolg. Antworten mit success:false (auch HTTP 207, Teilerfolg)
    // gelten als Fehler, damit nie «Gespeichert» erscheint, wenn etwas fehlt.
    function cupPost(url, data, wasFehlt) {
        return new Promise(function(ok, fehler) {
            $.ajax({ url: url, method: 'POST', data: data, dataType: 'json' })
                .done(function(r) {
                    if (r && r.success === false) fehler(r.message || (r.errors || []).join(' ') || wasFehlt + ' wurde nicht gespeichert.');
                    else ok(r);
                })
                .fail(function(xhr) {
                    const r = xhr.responseJSON;
                    fehler((r && (r.message || r.error)) || msvXhrMessage(xhr, wasFehlt + ' wurde nicht gespeichert.'));
                });
        });
    }

    $('#save-all').on('click', function() {
        const $btn = $(this);
        const year = jahr();
        const auftraege = [];

        const r1 = collectPairs('#r1-pairs');
        if (r1.length) auftraege.push(cupPost('cup2/save_pairs.php', { pairs: JSON.stringify(r1), year: year, round: 1 }, 'Runde 1'));
        const r2 = collectPairs('#r2-pairs');
        if (r2.length) auftraege.push(cupPost('cup2/save_pairs.php', { pairs: JSON.stringify(r2), year: year, round: 2 }, 'Runde 2'));
        const fin = collectFinalResults();
        if (fin.length) auftraege.push(cupPost('cup2/save_finalresults.php', { pairs: JSON.stringify(fin), year: year }, 'Das Finale'));
        if (cupStandcupGeaendert) {
            const names = [1, 2, 3].map(i => $('#sc-name-' + i).val().trim());
            const results = [1, 2, 3].map(i => $('#sc-result-' + i).val());
            if (names.some(n => !n) || results.some(r => r === '')) {
                auftraege.push(Promise.reject('Standcup: bitte für alle drei Vereine Name und Punkte eintragen.'));
            } else {
                auftraege.push(cupPost('cup2/save_standcupfinal.php', {
                    participant1_name: names[0], participant1_result: results[0],
                    participant2_name: names[1], participant2_result: results[1],
                    participant3_name: names[2], participant3_result: results[2],
                    year: year
                }, 'Der Standcup'));
            }
        }

        if (auftraege.length === 0) {
            msvToast('Es gibt noch nichts zu speichern.', 'info');
            cupMarkieren(false);
            cupGespeichert(true);
            return;
        }

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Speichere…');
        const fertig = function() { $btn.prop('disabled', false).html('<i class="bi bi-save me-1" aria-hidden="true"></i>Speichern'); };

        Promise.allSettled(auftraege).then(function(ergebnisse) {
            const fehler = ergebnisse.filter(e => e.status === 'rejected').map(e => String(e.reason));
            fertig();
            if (fehler.length) {
                cupJahrNachher = null;
                cupGespeichert(false);
                msvError('Nicht alles gespeichert. ' + fehler.join(' ') + ' Die Eingaben sind noch da; bitte korrigieren und nochmals speichern.');
                return;
            }
            cupMarkieren(false);
            msvToast('Gespeichert', 'success');
            const jahrNachher = cupJahrNachher;
            cupJahrNachher = null;
            if (jahrNachher) $('#yearSelect').val(jahrNachher).trigger('change');
            else refreshAfterSave();
            cupGespeichert(true);
        });
    });

    // Je Paarung: ID (falls gespeichert), Teilnehmer mit ihren Resultaten, «weiter» und manueller Gewinner
    function collectPairs(container) {
        const pairs = [];
        $(container + ' .cup4-pair-card').each(function() {
            const $card = $(this);
            const p = [], r = [];
            $card.find('.cup4-participant-row').each(function() {
                const id = $(this).find('.cup4-drop-zone').attr('data-id');
                if (!id) return;
                const v = $(this).find('.cup4-result').val();
                p.push(parseInt(id, 10));
                r.push(v === '' ? null : parseInt(v, 10));
            });
            if (p.length < 2) return;
            pairs.push({
                id: $card.data('pair-id') || null,
                p: p,
                r: r,
                adv: parseInt($card.data('advancers'), 10) || 2,
                mw: $card.data('manual-winner') ? parseInt($card.data('manual-winner'), 10) : null
            });
        });
        return pairs;
    }

    function collectFinalResults() {
        const results = [];
        $('#final-list .cup4-final-item').each(function() {
            const id = $(this).find('.cup4-final-name').data('id');
            const r = $(this).find('.cup4-result').val();
            if (id && r !== '') results.push([id, r, 0]);
        });
        return results;
    }

    function refreshAfterSave() {
        // Paarungen neu laden, dann Teilnehmer und Gewinner (Reihenfolge wichtig)
        loadSavedPairs(1, '#r1-pairs', function() {
            loadSavedPairs(2, '#r2-pairs', function() {
                loadParticipants();
                loadWinnersForRound2();
            });
        });
        checkForFinalParticipants(); // erstes Finalresultat gespeichert → Standcup erscheint
    }

    /* ── Paarung löschen ──────────────────── */
    $(document).on('click', '.cup4-card-remove', async function() {
        const $card = $(this).closest('.cup4-pair-card');
        const pairId = $card.data('pair-id');
        const wasInR1 = $card.closest('#r1-pairs').length > 0;

        if (!pairId) {
            // Noch nicht gespeichert: nur aus der Anzeige nehmen, Teilnehmer zurück in die Liste
            $card.find('.cup4-drop-zone[data-id]').each(function() {
                wasInR1 ? returnToPool($(this).attr('data-id'), zoneName($(this))) : returnToR2Pool($(this).attr('data-id'), zoneName($(this)));
            });
            $card.remove();
            updateProgress();
            if (wasInR1) extractR1WinnersToR2Pool();
            return;
        }

        // Gespeichert: der Server löscht und lädt danach alles neu – Ungespeichertes ginge dabei verloren
        if (cupGeaendert) {
            msvToast('Bitte zuerst speichern; danach die Paarung löschen.', 'warning');
            return;
        }

        let p;
        try { p = await $.post('cup2/delete_pair.php', { pair_id: pairId, aktion: 'pruefen' }, null, 'json'); }
        catch (xhr) { msvToast(msvXhrMessage(xhr, 'Prüfen hat nicht geklappt. Bitte die Seite neu laden.'), 'error'); return; }
        const folgen = (p && p.folgen) || [];
        const liste = folgen.length
            ? '<p class="text-start mb-1 mt-2">Mitgelöscht wird:</p><ul class="text-start mb-2 ps-3">' + folgen.map(f => '<li>' + msvEsc(f) + '</li>').join('') + '</ul>'
            : '';
        const r = await msvConfirmDelete('', {
            title: 'Paarung löschen?',
            html: 'Runde ' + msvEsc(p.runde) + ' ' + msvEsc(p.jahr) + ': <strong>' + msvEsc(p.paarung) + '</strong> mit ihren Resultaten.' + liste + 'Vorher wird die Datenbank gesichert.',
            confirmText: 'Ja, löschen'
        });
        if (!r.isConfirmed) return;

        try {
            const resp = await $.post('cup2/delete_pair.php', { pair_id: pairId, aktion: 'loeschen' }, null, 'json');
            if (resp && resp.success === false) { msvError(resp.message || 'Die Paarung wurde nicht gelöscht. Bitte die Seite neu laden.'); return; }
            msvToast('Paarung gelöscht' + (resp && resp.sicherung ? ' – Sicherung ' + resp.sicherung : ''), 'success');
            refreshAfterSave();
        } catch (xhr) {
            msvError(msvXhrMessage(xhr, 'Die Paarung wurde nicht gelöscht. Bitte die Seite neu laden und nochmals versuchen.'));
        }
    });

    /* ── PDF ──────────────────────────────── */
    // Derselbe Generator wie auf der Seite «Vereinscup Rangliste»; das PDF enthält nur Gespeichertes.
    $(document).on('click', '.pdf-btn', async function(e) {
        e.preventDefault();
        if (!(await window.cupVorAusgabe())) return;
        const j = jahr();
        msvAusgabe(this, {
            url: 'cuprang/generate_cup_pdf.php',
            data: { year: j, orientation: window.MsvDruck ? MsvDruck.orientierung('cuprang', 'portrait') : 'portrait' },
            titel: 'Vereinscup Rangliste ' + j,
            name: 'Vereinscup_Rangliste_' + j,
            fehler: 'Die Rangliste konnte nicht erstellt werden. Bitte nochmals versuchen.'
        });
    });

    /* ── Standcup ─────────────────────────── */
    function standcupLeeren() {
        $('#standcup-section input').val('');
        $('#standcup-section').prop('hidden', true);
    }

    function loadStandcupData() {
        $.ajax({
            url: 'cup2/fetch_standcup_final.php',
            data: { year: jahr() },
            dataType: 'json',
            error: function(xhr) { msvToast(msvXhrMessage(xhr, 'Der Standcup konnte nicht geladen werden. Bitte die Seite neu laden.'), 'error'); },
            success: function(data) {
                if (!data.length) return;
                const nr = { 'MSV Wilen': 1, 'SV Wollerau': 2, 'SV Freienbach': 3 };
                data.forEach(function(e) {
                    if (!nr[e.club]) return;
                    $('#sc-name-' + nr[e.club]).val(e.ParticipantName);
                    $('#sc-result-' + nr[e.club]).val(e.Result);
                });
                $('#standcup-section').prop('hidden', false);
            }
        });
    }

    function checkForFinalParticipants() {
        $.ajax({
            url: 'cup2/check_final_participants.php',
            data: { year: jahr() },
            error: function(xhr) { msvToast(msvXhrMessage(xhr, 'Die Finalisten konnten nicht geprüft werden. Bitte die Seite neu laden.'), 'error'); },
            success: function(resp) {
                if (String(resp).trim().toLowerCase() === 'true' || String(resp).trim() === '1') {
                    $('#standcup-section').prop('hidden', false);
                }
            }
        });
    }

    /* ── Start ────────────────────────────── */
    function initializePage() {
        cupMarkieren(false);
        cupWahl(null);
        $('#r1-pairs, #r2-pairs, #final-list, #r2-pool-list').empty();
        standcupLeeren();
        r1WinnerIds = {};

        loadParticipants();
        loadSavedPairs(1, '#r1-pairs', function() {
            loadSavedPairs(2, '#r2-pairs', function() {
                loadWinnersForRound2();
            });
        });
        checkForFinalParticipants();
        updateProgress();
    }

    // Bester Kat. B ins Finale: wirkt sofort (Einstellung pro Jahr). Ungespeicherte Finalpunkte bleiben stehen,
    // nur der Kat.-B-Eintrag wird neu bestimmt.
    $('#katb-final-switch').on('change', function() {
        const on = $(this).is(':checked');
        $.ajax({
            url: 'cup2/cup_settings.php',
            method: 'POST',
            data: { year: jahr(), katb_to_final: on ? 1 : 0 },
            dataType: 'json',
            success: function() {
                cupKatBToFinal = on;
                $('#final-list .cup4-final-item[data-katb]').remove();
                if (on) checkKatBFinalist();
                updateProgress();
                msvToast(on ? 'Der beste Kat. B rückt ins Finale nach' : 'Kein Kat.-B-Nachrücker im Finale', 'info');
            },
            error: function(xhr) {
                $('#katb-final-switch').prop('checked', !on);
                msvToast(msvXhrMessage(xhr, 'Die Einstellung wurde nicht gespeichert. Bitte nochmals versuchen.'), 'error');
            }
        });
    });

    $('#yearSelect').on('change', async function() {
        const neu = $(this).val();
        if (cupGeaendert && cupJahr && neu !== cupJahr) {
            const wahl = await msvUngespeichert({ wer: 'den Vereinscup ' + cupJahr });
            if (wahl === 'zurueck') { $(this).val(cupJahr); return; }
            if (wahl === 'speichern') { $(this).val(cupJahr); cupJahrNachher = neu; $('#save-all').trigger('click'); return; }
        }
        cupJahr = neu;
        loadCupSettings(initializePage);
    });

    initYearDropdown();
    cupJahr = jahr();
    loadCupSettings(initializePage);
});
</script>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray), Profil «Vereinscup Rangliste»; druckt nur Gespeichertes, darum vorher cupVorAusgabe()
MsvDruck.resolve('cuprang', async () => {
    if (!(await window.cupVorAusgabe())) return null; // wie der PDF-Knopf: zuerst speichern oder abbrechen
    const jahr = document.getElementById('yearSelect').value;
    return { url: 'cuprang/generate_cup_pdf.php?year=' + encodeURIComponent(jahr) + '&orientation=' + MsvDruck.orientierung('cuprang', 'portrait'), jobName: 'Vereinscup Rangliste ' + jahr };
});
</script>
<?php include 'footer.inc.php'; ?>
