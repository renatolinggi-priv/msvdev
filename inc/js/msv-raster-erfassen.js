/**
 * Raster-Erfassung (Heimmeisterschaft, Kantonalstich): eine Zeile pro Mitglied mit N Passen, Rang je Kategorie,
 * Sortierung «Name | Rang», Filter und Suche, Schnellerfassung im Slide-Panel und Karten auf dem Handy.
 *
 * Markup: partials/raster_erfassen.inc.php (msv_raster_tabelle + msv_raster_panel), Aussehen: Block
 * «Raster-Erfassung» in css/msv-ui.css. Der Lader der Seite liefert pro Mitglied
 *   <tr data-kat="A|B|"> mit .hk-rang, .hk-name, .hk-anzahl, N × input.small-input name="passe[ID][n]" und .sum-cell.
 *
 * Aufruf (in $(document).ready):
 *   MsvRaster.init({
 *     kennung: 'heimresultate',              // Form #<kennung>Form, Tabelle #<kennung>Tabelle
 *     passen: 8,
 *     laden: 'heimresultate/load_heimresultate_form.php',
 *     speichern: 'heimresultate/save_heimresultate.php',
 *     bestePasse: false,                     // Kanti: beste Passe gold markieren (zählt in der JM)
 *     pdf: { url: 'heimrang/generate_pdf.php', druckprofil: 'heimrang', format: 'landscape',
 *            titel: 'Heimmeisterschaft Rangliste', datei: 'Heimmeisterschaft_Rangliste' },
 *     website: { tabelle: 'heimresultate', titel: 'Heimresultate' }
 *   });
 * Setzt window.hkVorAusgabe (vor PDF und Direktdruck: Ungespeichertes zuerst speichern oder abbrechen).
 */
(function ($) {
    'use strict';

    function init(cfg) {
        const TAB = '#' + cfg.kennung + 'Tabelle';
        const FORM = '#' + cfg.kennung + 'Form';
        const N = cfg.passen;
        const SPALTEN = N + 2;
        const MAX = 100;
        const istHandy = () => window.matchMedia('(max-width: 767.98px)').matches;

        // ===== Ungespeicherte Änderungen im Raster (pro Mitglied-ID) =====
        const geaendert = new Set();
        function rowId($tr) {
            const m = ($tr.find('input.small-input').first().attr('name') || '').match(/passe\[(\d+)\]/);
            return m ? m[1] : null;
        }
        function markGeaendert($tr) {
            const id = rowId($tr);
            if (!id) return;
            geaendert.add(id);
            $tr.addClass('hk-geaendert');
            zaehlen();
        }
        window.addEventListener('beforeunload', function (e) {
            if (geaendert.size) { e.preventDefault(); e.returnValue = ''; }
        });

        // Ein Feld, das beim Laden einen Wert hatte und jetzt leer ist, wurde bewusst geleert. Der Endpunkt lässt
        // leere Felder unverändert; darum geht es als 0 hinaus (zählt wie «nicht geschossen»), sonst käme der alte Wert zurück.
        function geleert(input) {
            const alt = String(input.defaultValue || '').trim();
            return input.value.trim() === '' && alt !== '' && alt !== '0';
        }

        // ===== Handy: Speicherleiste unten, solange etwas nicht gespeichert ist und «Speichern» im Kopf nicht zu sehen ist =====
        let kopfKnopfSichtbar = true;
        function speicherleiste() {
            const n = geaendert.size;
            $('#rasterSpeicherleisteText').text(n === 1 ? '1 Zeile nicht gespeichert' : n + ' Zeilen nicht gespeichert');
            $('#rasterSpeicherleiste').prop('hidden', !(n && istHandy() && !kopfKnopfSichtbar));
        }
        const kopfKnopf = document.getElementById('rasterSpeichernBtn');
        if (kopfKnopf && 'IntersectionObserver' in window) {
            new IntersectionObserver(function (eintraege) {
                kopfKnopfSichtbar = eintraege[eintraege.length - 1].isIntersecting;
                speicherleiste();
            }).observe(kopfKnopf);
        } else {
            kopfKnopfSichtbar = false;   // ohne Beobachter: Leiste immer zeigen, sobald etwas offen ist
        }

        // ===== Jahr-Dropdown: ?year= kommt von der Weiterleitung der alten Ranglisten-Seite, danach aus der Adresse =====
        function initializeYearDropdown() {
            const params = new URLSearchParams(location.search);
            msvJahrAuswahl('#yearSelect', { jahr: params.get('year') });
            if (params.has('year')) {
                const url = new URL(location.href);
                url.searchParams.delete('year');
                history.replaceState(null, '', url);
            }
        }

        // ===== Resultate laden =====
        let fokusNachLaden = null;   // Mitglied-ID, deren Namensknopf nach dem Laden den Fokus bekommt
        let ortNachLaden = null;     // nach dem Speichern im Raster: { feld: name des zuletzt benutzten Felds, scroll }
        let letztesFeld = null;      // name des zuletzt fokussierten Rasterfelds
        $(document).on('focusin', TAB + ' input.small-input', function () { letztesFeld = this.name; });
        function fokusAufName(id) {
            const knopf = $(TAB + ' input[name^="passe[' + id + ']"]').first().closest('tr').find('button.hk-name')[0];
            if (knopf && knopf.offsetParent !== null) knopf.focus();
        }
        function loadResultate(year) {
            const $tbody = $(TAB + ' tbody');
            $tbody.html('<tr class="ui-leer"><td colspan="' + SPALTEN + '"><div class="spinner-border spinner-border-sm me-2" aria-hidden="true"></div>Lade Resultate …</td></tr>');
            if (istHandy()) {
                $('#rasterKarten').html('<div class="mobile-cards-empty"><div class="spinner-border spinner-border-sm me-2" aria-hidden="true"></div>Lade Resultate …</div>');
            }
            $.ajax({
                url: cfg.laden,
                method: 'GET',
                cache: false,
                data: { year: year },
                success: function (response) {
                    const gewaehlt = $('#yearSelect').val();
                    if (year && gewaehlt && String(year) !== String(gewaehlt)) return; // Antwort für ein inzwischen abgewähltes Jahr
                    $tbody.html(response);
                    geaendert.clear();
                    bindInputs();
                    updateRowStats();   // rechnet auch den Rang
                    ursprung = $tbody.children().toArray();
                    sortieren();        // filtert und baut die Reihenfolge der Schnellerfassung
                    buildMobileCards();
                    // Fokus war auf einem Namen, den das Neuladen ersetzt hat (Panel nach dem Speichern geschlossen)
                    const id = fokusNachLaden;
                    fokusNachLaden = null;
                    if (id && (!document.activeElement || document.activeElement === document.body)) fokusAufName(id);
                    // Nach dem Speichern im Raster: dort weiter, wo man war (Scrollposition und Feld)
                    const ort = ortNachLaden;
                    ortNachLaden = null;
                    if (ort) {
                        $('#hkScroll').scrollTop(ort.scroll);
                        const a = document.activeElement;
                        const frei = !a || a === document.body || a.id === 'rasterSpeichernBtn' || a.id === 'rasterSpeicherleisteBtn';
                        const feld = ort.feld ? $(TAB + ' input[name="' + ort.feld + '"]')[0] : null;
                        if (frei && feld && feld.offsetParent !== null) feld.focus({ preventScroll: true });
                    }
                },
                error: function (xhr) {
                    $tbody.html('<tr class="ui-leer"><td colspan="' + SPALTEN + '" class="text-danger"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>'
                        + 'Die Resultate konnten nicht geladen werden: ' + msvEsc(msvXhrMessage(xhr, 'Serverfehler')) + '</td></tr>');
                    $('#rasterKarten').html('<div class="mobile-cards-empty text-danger"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>'
                        + '<div>Die Resultate liessen sich nicht laden. Lade die Seite neu (F5).</div></div>');
                    msvToast('Die Resultate liessen sich nicht laden. Lade die Seite neu (F5).', 'error');
                }
            });
        }

        // ===== Summe, beste Passe, Stand, Fortschritt =====
        function updateRowStats() {
            $(TAB + ' tbody tr').each(function () {
                const $inputs = $(this).find('input.small-input');
                if (!$inputs.length) return;
                let sum = 0, best = -1, bestIdx = -1, filled = 0;
                const total = $inputs.length;
                $inputs.each(function (i) {
                    $(this).removeClass('best-passe filled').removeAttr('aria-describedby').parent().removeClass('hk-beste-zelle');
                    const val = parseInt(this.value) || 0;
                    if (this.value.trim() !== '' && this.value !== '0') {
                        filled++;
                        $(this).addClass('filled');
                    }
                    sum += val;
                    if (val > best && val <= MAX) { best = val; bestIdx = i; }   // ungültige Werte (> 100) nie «beste Passe»
                });
                // Beste Passe nicht nur über Farbe: Stern in der Zelle (CSS) und «beste Passe» für den Screenreader
                if (cfg.bestePasse && bestIdx >= 0 && best > 0) {
                    $inputs.eq(bestIdx).addClass('best-passe').attr('aria-describedby', 'hkBesteText').parent().addClass('hk-beste-zelle');
                }

                const $sumCell = $(this).find('.sum-cell');
                if ($sumCell.length) $sumCell.text(sum > 0 ? sum : '–').toggleClass('empty', sum === 0);

                // Stand: Anzahl erfasster Passen neben dem Namen, data-stand für den Filter
                $(this).find('.hk-anzahl').text(filled ? filled + '/' + total : '').toggleClass('voll', filled === total);
                this.setAttribute('data-stand', filled ? 'mit' : 'ohne');
                this.setAttribute('data-voll', filled === total ? '1' : '0');
            });
            rangBerechnen();
            zaehlen();
        }

        // Kopf-Card: Mitglieder mit Resultaten, vollständige, ungespeicherte Zeilen; Zähler im Filter
        let zaehlenStand = '';
        function zaehlen() {
            const $rows = $(TAB + ' tbody tr[data-stand]');
            const total = $rows.length;
            const mit = $rows.filter('[data-stand="mit"]').length;
            const voll = $rows.filter('[data-voll="1"]').length;
            // Live-Region nur bei echter Änderung schreiben, sonst sagt der Screenreader bei jedem Tastendruck den Stand an
            const stand = mit + ' von ' + total + ' <span>Mitgliedern mit Resultaten</span>';
            if (stand !== zaehlenStand) { zaehlenStand = stand; $('#progressText').html(stand); }
            $('#progressChips').html(
                (voll ? '<span class="ui-chip"><b>' + voll + '</b> mit allen ' + N + ' Passen</span>' : '') +
                (geaendert.size ? '<span class="ui-chip hk-chip-ungespeichert"><span class="ui-punkt"></span><b>' + geaendert.size + '</b> ' +
                    (geaendert.size === 1 ? 'Zeile' : 'Zeilen') + ' nicht gespeichert</span>' : '')
            );
            $('#nAlle').text(total);
            $('#nMit').text(mit);
            $('#nOhne').text(total - mit);
            rangHinweis();
            speicherleiste();
        }

        // Solange etwas nicht gespeichert ist, rechnet der Rang mit Eingaben, die das PDF (liest die DB) noch nicht kennt:
        // dann alle Ränge kursiv und mit Tooltip «vorläufig»
        function rangHinweis() {
            const vorlaeufig = geaendert.size > 0;
            const $t = $(TAB).toggleClass('hk-vorlaeufig', vorlaeufig);
            $t.find('tbody tr[data-rang]').each(function () {
                const kat = this.getAttribute('data-kat');
                const $rang = $(this).find('.hk-rang');
                $rang.find('.visually-hidden').remove();
                if (vorlaeufig) $rang.append('<span class="visually-hidden"> (vorläufig)</span>');
                $rang.attr('data-tooltip', vorlaeufig
                    ? 'Vorläufiger Rang in Kat. ' + kat + ': rechnet mit Eingaben, die noch nicht gespeichert sind'
                    : 'Rang in Kat. ' + kat);
            });
        }

        // ===== Filter (Alle / Mit Resultaten / Ohne) und Suche =====
        let filter = 'alle', suchtext = '';
        function filterAnwenden() {
            const $tbody = $(TAB + ' tbody');
            const $zeilen = $tbody.children('tr[data-stand]');
            let sichtbar = 0;
            $zeilen.each(function () {
                const zeigen = (filter === 'alle' || this.getAttribute('data-stand') === filter)
                    && $(this).find('.hk-name').text().toLowerCase().indexOf(suchtext) !== -1;
                this.style.display = zeigen ? '' : 'none';
                if (zeigen) sichtbar++;
            });
            // Gruppen-Überschriften nur in der ungefilterten Liste
            $tbody.children('tr.group-header').toggle(filter === 'alle' && suchtext === '');
            $('#hkKeineTreffer').remove();
            if (!sichtbar && $zeilen.length) {
                $tbody.append('<tr class="ui-leer" id="hkKeineTreffer"><td colspan="' + SPALTEN + '">Keine Mitglieder für diese Auswahl. ' +
                    '<button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="hkFilterZurueck">Filter zurücksetzen</button></td></tr>');
            }
        }
        $(document).on('click', '#hkFilterGruppe button', function () {
            filter = $(this).data('filter');
            $('#hkFilterGruppe button').attr('aria-pressed', 'false');
            $(this).attr('aria-pressed', 'true');
            filterAnwenden();
        });
        $('#hkSuche').on('input', function () { suchtext = this.value.trim().toLowerCase(); filterAnwenden(); });
        $(document).on('click', '#hkFilterZurueck', function () {
            filter = 'alle'; suchtext = ''; $('#hkSuche').val('');
            $('#hkFilterGruppe button').attr('aria-pressed', 'false');
            $('#hkFilterGruppe button[data-filter="alle"]').attr('aria-pressed', 'true');
            filterAnwenden();
        });

        // ===== Rang je Kategorie (Regel wie die PDF-Rangliste: nur Total > 0, gleiches Total = gleicher Rang) =====
        function rangBerechnen() {
            const proKat = { A: [], B: [] };
            $(TAB + ' tbody tr[data-stand]').each(function () {
                let summe = 0;
                $(this).find('input.small-input').each(function () { summe += parseInt(this.value, 10) || 0; });
                this.removeAttribute('data-rang');
                const kat = this.getAttribute('data-kat');
                if (summe > 0 && proKat[kat]) proKat[kat].push({ tr: this, summe: summe });
            });
            Object.keys(proKat).forEach(function (kat) {
                const liste = proKat[kat].sort(function (a, b) { return b.summe - a.summe; });
                liste.forEach(function (x, i) {
                    x.rang = (i > 0 && liste[i - 1].summe === x.summe) ? liste[i - 1].rang : i + 1;
                    x.tr.setAttribute('data-rang', x.rang);
                });
            });
            $(TAB + ' tbody tr[data-stand]').each(function () {
                const r = this.getAttribute('data-rang'), kat = this.getAttribute('data-kat');
                this.classList.remove('rank-1', 'rank-2', 'rank-3'); // Podest wie in den anderen Ranglisten
                if (r && +r <= 3) this.classList.add('rank-' + r);
                $(this).find('.hk-rang').text(r ? kat + ' ' + r + '.' : '').attr('data-tooltip', r ? 'Rang in Kat. ' + kat
                    : (!kat && this.getAttribute('data-stand') === 'mit' ? 'Ohne Rang: keine Kategorie, weil in den Stammdaten keine Waffe hinterlegt ist' : null));
            });
        }

        // ===== Sortierung «Name» (wie geladen) / «Rang» (Kat. A, Kat. B, dann ohne Rang) =====
        // Beim Tippen rechnet nur der Rang mit; die Zeilen ordnen sich erst nach dem Speichern bzw. beim Umschalten neu.
        let sortierung = new URLSearchParams(location.search).get('sortierung') === 'rang' ? 'rang' : 'name';
        let ursprung = [];   // Zeilen in der geladenen Reihenfolge, mit Gruppen-Überschriften
        function sortieren() {
            const $tbody = $(TAB + ' tbody');
            $tbody.children('tr.hk-kat-kopf').remove();
            if (ursprung.length) {
                if (sortierung === 'name') {
                    $tbody.append(ursprung);
                } else {
                    const zeilen = ursprung.filter(function (tr) { return tr.hasAttribute('data-stand'); });
                    $(ursprung).filter('tr.group-header').detach();
                    const kopf = function (text) {
                        return $('<tr class="group-header hk-kat-kopf"><td colspan="' + SPALTEN + '" class="group-header-cell"></td></tr>').find('td').text(text).end();
                    };
                    ['A', 'B'].forEach(function (kat) {
                        const g = zeilen.filter(function (tr) { return tr.getAttribute('data-kat') === kat && tr.hasAttribute('data-rang'); })
                            .sort(function (a, b) { return a.getAttribute('data-rang') - b.getAttribute('data-rang'); }); // stabil: bei gleichem Rang alphabetisch
                        if (g.length) $tbody.append(kopf('Kat. ' + kat + ' (' + g.length + ')'), g);
                    });
                    const ohne = zeilen.filter(function (tr) { return !tr.hasAttribute('data-rang'); });
                    if (ohne.length) $tbody.append(kopf('Ohne Rang (' + ohne.length + ')'), ohne);
                }
            }
            filterAnwenden();
            EntryPanel.buildIndex();
        }
        $('#hkSortierung').on('click', 'button', function () {
            sortierung = this.getAttribute('data-sort');
            $('#hkSortierung button').each(function () { this.setAttribute('aria-pressed', String(this.getAttribute('data-sort') === sortierung)); });
            const url = new URL(location.href);
            if (sortierung === 'rang') url.searchParams.set('sortierung', 'rang'); else url.searchParams.delete('sortierung');
            history.replaceState(null, '', url);
            sortieren();
            buildMobileCards();
        });

        // Eingaben im Raster: Summe, Stand und Rang rechnen sofort mit
        $(document).on('input', TAB + ' input.small-input', function () {
            updateRowStats();
        });

        // ==========================================================
        //  Raster: Tastatur, Eingabe prüfen
        // ==========================================================
        function bindInputs() {
            const $inputs = $(TAB + ' input');

            $inputs.off('keydown.raster').on('keydown.raster', function (e) {
                if (e.key === 'Enter') { // Tab bleibt nativ, damit man das Raster verlassen kann
                    e.preventDefault();
                    const inputs = $(TAB + ' tbody tr:visible input.small-input');
                    const currentIndex = inputs.index(this);
                    const nextIndex = e.shiftKey ? currentIndex - 1 : currentIndex + 1;
                    if (nextIndex >= 0 && nextIndex < inputs.length) inputs.eq(nextIndex).focus().select();
                } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    // gleiche Passe in der nächsten bzw. vorherigen sichtbaren Zeile
                    e.preventDefault();
                    const spalte = $(this).closest('td').index();
                    const $zeilen = $(TAB + ' tbody tr:visible').filter(function () { return $(this).find('input.small-input').length > 0; });
                    const z = $zeilen.index($(this).closest('tr')) + (e.key === 'ArrowDown' ? 1 : -1);
                    if (z >= 0 && z < $zeilen.length) $zeilen.eq(z).children('td').eq(spalte).find('input').focus().select();
                }
            });

            // Eine 0 verschwindet beim Betreten; leer bleibt leer (keine Passe, kein Datensatz)
            $inputs.off('focus.raster').on('focus.raster', function () {
                const $this = $(this);
                if ($this.val() === '0') $this.val('').select();
                else if ($this.val() !== '') $this.select();
            });

            $inputs.off('input.raster').on('input.raster', function (e) {
                let value = $(this).val().replace(/[^0-9]/g, '');
                if (value.length > 3) value = value.substring(0, 3);
                $(this).val(value);
                msvPruefeZahl(this);
                if (!$('#hkFehler').prop('hidden')) msvEingabeFehler('#hkFehler', msvPruefeFelder(TAB), false);
                // nur echte Eingaben im Raster; Übernahmen aus Panel und Handy-Karte markieren selbst
                if (e.originalEvent) markGeaendert($(this).closest('tr'));
            });
        }

        // Leere Felder vor einer späteren Passe mit 0 füllen (wie die Schnellerfassung), geleerte ebenso.
        // Gibt die Anzahl geleerter Passen zurück (für die Meldung).
        function fillEmptyWithZero() {
            let geleerte = 0;
            $(TAB + ' tbody tr').each(function () {
                if (!geaendert.has(rowId($(this)))) return;   // nur Zeilen, die gespeichert werden
                const inputs = $(this).find('input.small-input');
                let hasLaterValue = false;
                for (let i = inputs.length - 1; i >= 0; i--) {
                    const $input = $(inputs[i]);
                    const val = $input.val().trim();
                    if (val !== '' && val !== '0') hasLaterValue = true;
                    else if (geleert(inputs[i])) { $input.val('0'); geleerte++; }
                    else if (hasLaterValue && val === '') $input.val('0');
                }
            });
            return geleerte;
        }

        // ===== Speichern =====
        // Gibt ein Promise zurück: true = gespeichert, false = nicht gespeichert (Prüffehler oder Serverfehler, Meldung steht)
        function rasterSpeichern() {
            // Werte über 100 oder keine Zahl: nicht speichern, sondern zeigen, wo
            if (msvEingabeFehler('#hkFehler', msvPruefeFelder(TAB))) return Promise.resolve(false);
            if (!geaendert.size) { msvToast('Es gibt nichts zu speichern.', 'info'); return Promise.resolve(true); }
            // Kopf-Knopf und Speicherleiste (Handy) sperren und zeigen dasselbe
            const $submitBtn = $('#rasterSpeichernBtn, #rasterSpeicherleisteBtn');
            $submitBtn.each(function () { $(this).data('html', $(this).html()); }).prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Speichere …');

            const geleerte = fillEmptyWithZero();
            const selectedYear = $('#yearSelect').val();
            // Nur die geänderten Zeilen: unveränderte alte Werte überschreiben so keine Eingaben einer zweiten Person
            const $zeilen = $(TAB + ' tbody tr').filter(function () { return geaendert.has(rowId($(this))); });
            const anzahl = $zeilen.length;
            const formData = $zeilen.find('input.small-input').serialize()
                + '&csrf_token=' + encodeURIComponent($(FORM + ' input[name="csrf_token"]').val())
                + '&year=' + selectedYear + '&jahr=' + selectedYear;

            return new Promise(function (fertig) {
                $.ajax({
                    url: cfg.speichern,
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (resp) {
                        if (!resp || !resp.success) { msvToast((resp && resp.message) || 'Fehler beim Speichern der Ergebnisse', 'error'); fertig(false); return; }
                        geaendert.clear();
                        const was = anzahl === 1 ? '1 Zeile' : anzahl + ' Zeilen';
                        msvToast(was + ' gespeichert' + (geleerte ? ', ' + (geleerte === 1 ? '1 Passe' : geleerte + ' Passen') + ' geleert' : ''), 'success');
                        ortNachLaden = { feld: letztesFeld, scroll: $('#hkScroll').scrollTop() };
                        loadResultate(selectedYear);
                        fertig(true);
                    },
                    error: function (xhr) {
                        msvToast(msvXhrMessage(xhr, 'Fehler beim Speichern der Ergebnisse'), 'error');
                        fertig(false);
                    },
                    complete: function () {
                        $submitBtn.each(function () { $(this).prop('disabled', false).html($(this).data('html')); });
                    }
                });
            });
        }
        $(FORM).on('submit', function (e) {
            e.preventDefault();
            rasterSpeichern();
        });

        // Vor PDF und Direktdruck: Die Rangliste liest die Datenbank. Ist im Raster etwas nicht gespeichert,
        // zuerst speichern (oder abbrechen), damit PDF und Bildschirm denselben Rang zeigen.
        window.hkVorAusgabe = function () { return msvVorAusgabe(geaendert.size, rasterSpeichern); };

        // ===== Rangliste als PDF (Ausgabe-Baustein msvAusgabe: sperren, Spinner, Download, Toast) =====
        $('#hkPdfBtn').on('click', async function (e) {
            e.preventDefault();
            if (!(await window.hkVorAusgabe())) return;
            const jahr = $('#yearSelect').val();
            msvAusgabe(this, {
                url: cfg.pdf.url,
                data: {
                    year: jahr,
                    orientation: window.MsvDruck ? MsvDruck.orientierung(cfg.pdf.druckprofil, cfg.pdf.format) : cfg.pdf.format // Format aus dem Druckprofil
                },
                titel: cfg.pdf.titel + ' ' + jahr,
                name: cfg.pdf.datei + '_' + jahr,
                fehler: 'Die Rangliste konnte nicht erstellt werden. Bitte nochmals versuchen.'
            });
        });

        // ===== Jahreswechsel (fragt nach, wenn im Raster noch nicht gespeichert wurde) =====
        let aktuellesJahr = null;
        $('#yearSelect').on('change', async function () {
            const neu = this.value;
            if (neu === aktuellesJahr) return;
            if (geaendert.size) {
                // Drei Wege wie im Vereinscup: Speichern (dann wechseln), Verwerfen, Zurück
                const wahl = await msvUngespeichert({ wer: geaendert.size === 1 ? '1 Zeile' : geaendert.size + ' Zeilen' });
                const zurueck = () => { this.value = aktuellesJahr; this.dispatchEvent(new Event('change', { bubbles: true })); };
                if (wahl === 'zurueck') { zurueck(); return; }
                if (wahl === 'speichern') {
                    this.value = aktuellesJahr;          // rasterSpeichern() liest das Jahr sofort aus der Auswahl
                    const gespeichert = rasterSpeichern();
                    this.value = neu;
                    if (!(await gespeichert)) { zurueck(); return; }
                }
            }
            aktuellesJahr = neu;
            loadResultate(neu);
        });

        // ===== Auf der Website melden =====
        $('#publishChangelogBtn').on('click', async function () {
            if (!(await window.hkVorAusgabe())) return;   // die Website zeigt, was gespeichert ist
            const r = await msvConfirm('Auf www.msvwilen.ch erscheint der Hinweis «' + msvEsc(cfg.website.titel + ' ' + $('#yearSelect').val()) + ' aktualisiert». Die Resultate selbst ändert das nicht.', 'Auf der Website melden?', 'Melden', 'Abbrechen');
            if (!r.isConfirmed) return;
            const selectedYear = $('#yearSelect').val();
            $.post('changelog_publish.php', {
                kategorie: 'resultate',
                tabelle: cfg.website.tabelle,
                jahr: selectedYear,
                beschreibung: cfg.website.titel + ' ' + selectedYear + ' aktualisiert',
                csrf_token: $('input[name="csrf_token"]').val()
            }).done(function (res) {
                if (res.success) msvToast('Auf der Website gemeldet', 'success');
                else msvToast(res.message || 'Der Hinweis konnte nicht gemeldet werden.', 'error');
            }).fail(function (xhr) {
                msvToast(msvXhrMessage(xhr, 'Der Hinweis konnte nicht gemeldet werden. Bitte nochmals versuchen.'), 'error');
            });
        });

        // ==========================================================
        //  Handy: eine Karte pro Mitglied (zentrales Kartendesign), Eingaben gehen ins Raster
        // ==========================================================
        function kartenStand($karte) {
            let sum = 0, filled = 0, best = 0, bestIdx = -1;
            const $felder = $karte.find('.raster-karte-feld input');
            $felder.each(function (i) {
                const v = parseInt(this.value, 10) || 0;
                if (this.value !== '' && this.value !== '0') filled++;
                sum += v;
                if (v > best && v <= MAX) { best = v; bestIdx = i; }
                $(this).toggleClass('filled', this.value !== '' && this.value !== '0');
            });
            if (cfg.bestePasse) {
                $felder.each(function (i) {
                    $(this).toggleClass('best', i === bestIdx);
                    $(this).closest('.raster-karte-feld').find('.hk-beste').prop('hidden', i !== bestIdx);
                });
            }
            $karte.toggleClass('hat-werte', filled > 0);
            $karte.find('.raster-karte-stand').text(filled ? filled + '/' + N + ' Passen · Total ' + sum : 'Keine Resultate');
        }
        function kartenZaehler() {
            const $k = $('#rasterKarten .mobile-card');
            $('#rasterMobilZaehler').text($k.filter('.hat-werte').length + '/' + $k.length + ' erfasst');
        }

        function buildMobileCards() {
            const $container = $('#rasterKarten').empty();
            if (!istHandy()) return;   // am Laptop keine versteckten Karten; der Wechsel aufs Handy baut sie bei Bedarf
            let html = '';
            $(TAB + ' tbody tr[data-stand]').each(function () {
                const $tr = $(this);
                const name = $tr.find('.hk-name').text().trim();
                const rang = $tr.find('.hk-rang').clone().children().remove().end().text().trim();
                const status = $tr.find('.hk-status').text().trim();   // «inaktiv» / «verstorben»
                const podest = (this.className.match(/\brank-[123]\b/) || [''])[0];
                let felder = '';
                $tr.find('input.small-input').each(function (i) {
                    felder += '<label class="raster-karte-feld"><span>Passe ' + (i + 1)
                        + (cfg.bestePasse ? ' <span class="hk-beste" hidden>· beste</span>' : '') + '</span>'
                        + '<input type="text" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="3" autocomplete="off"'
                        + ' data-sync="' + msvEsc(this.name) + '" value="' + msvEsc(this.value) + '"></label>';
                });
                html += '<div class="mobile-card ' + podest + '" data-name="' + msvEsc(name.toLowerCase()) + '">'
                    + '<div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)"><div>'
                    + '<div class="fw-bold">' + (rang ? '<span class="hk-rang">' + msvEsc(rang) + '</span> ' : '') + msvEsc(name)
                    + (status ? ' <span class="hk-status">' + msvEsc(status) + '</span>' : '') + '</div>'
                    + '<small class="raster-karte-stand text-muted"></small></div>'
                    + '<i class="bi bi-chevron-down" aria-hidden="true"></i></div>'
                    + '<div class="mobile-card-body"><div class="raster-karte-passen">' + felder + '</div></div></div>';
            });
            $container.html(html || '<div class="mobile-cards-empty"><i class="bi bi-inbox" aria-hidden="true"></i><div>Keine Mitglieder gefunden</div></div>');
            $container.find('.mobile-card').each(function () { kartenStand($(this)); });
            kartenZaehler();
            mobilFiltern();
        }

        // Eingabe in der Karte → Raster (dort rechnen Summe, Rang und «nicht gespeichert» mit)
        $(document).on('input', '#rasterKarten .raster-karte-feld input', function () {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value.length > 3) value = value.substring(0, 3);
            this.value = value;
            this.setAttribute('aria-invalid', value !== '' && parseInt(value, 10) > MAX ? 'true' : 'false');
            const $ziel = $(TAB + ' input[name="' + this.getAttribute('data-sync') + '"]').val(value).trigger('input');
            markGeaendert($ziel.closest('tr'));
            const $karte = $(this).closest('.mobile-card');
            kartenStand($karte);
            kartenZaehler();
            // Rang im Kartenkopf nachführen
            const rang = $ziel.closest('tr').find('.hk-rang').clone().children().remove().end().text().trim();
            const $rang = $karte.find('.mobile-card-header .hk-rang');
            if ($rang.length) $rang.text(rang);
        });
        $(document).on('focus', '#rasterKarten .raster-karte-feld input', function () {
            if (this.value === '0') this.value = '';
            this.select();
        });
        // Enter: nächstes Feld; nach der letzten Passe die nächste sichtbare Karte öffnen
        $(document).on('keydown', '#rasterKarten .raster-karte-feld input', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const $karte = $(this).closest('.mobile-card');
            const $felder = $karte.find('.raster-karte-feld input');
            const i = $felder.index(this);
            if (i < $felder.length - 1) { $felder.eq(i + 1).focus(); return; }
            const $naechste = $karte.nextAll('.mobile-card:visible').first();
            if (!$naechste.length) return;
            if ($karte.hasClass('open')) MSVMobileCards.toggle($karte.find('.mobile-card-header')[0]);
            if (!$naechste.hasClass('open')) MSVMobileCards.toggle($naechste.find('.mobile-card-header')[0]);
            $naechste[0].scrollIntoView({ block: 'nearest' });
            $naechste.find('.raster-karte-feld input').first().focus();
        });

        function mobilFiltern() {
            const $container = $('#rasterKarten');
            const $karten = $container.find('.mobile-card');
            if (!$karten.length) return;
            const query = ($('#rasterSucheMobil').val() || '').trim().toLowerCase();
            let sichtbar = 0;
            $karten.each(function () {
                const zeigen = (this.getAttribute('data-name') || '').indexOf(query) !== -1;
                this.style.display = zeigen ? '' : 'none';
                if (zeigen) sichtbar++;
            });
            $container.find('.mobile-cards-empty').remove();
            if (!sichtbar) $container.append('<div class="mobile-cards-empty"><i class="bi bi-search" aria-hidden="true"></i><div>Keine Treffer gefunden</div></div>');
        }
        $('#rasterSucheMobil').on('input', mobilFiltern);

        // Wechsel Laptop ↔ Handy: Karten aus dem Raster bauen bzw. am Laptop entfernen
        // (sonst stünden beim Zurückwechseln alte Werte in den Karten)
        let resizeTimeout;
        $(window).on('resize', function () {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function () {
                if (istHandy() && !$('#rasterKarten .mobile-card').length) buildMobileCards();
                else if (!istHandy()) $('#rasterKarten').empty();
                speicherleiste();
            }, 150);
        });

        // =========================================
        //  Schnellerfassung im Slide-Panel – Schütze um Schütze
        // =========================================
        const EntryPanel = {
            rows: [],
            idx: -1,
            _silent: false,
            _saved: false,   // im Panel gespeichert → Liste beim Schliessen neu laden
            _opener: null,   // Element, das das Panel geöffnet hat (bekommt den Fokus beim Schliessen zurück)

            buildIndex() {
                this.rows = [];
                const self = this;
                $(TAB + ' tbody tr').each(function () {
                    const $tr = $(this);
                    const $inputs = $tr.find('input.small-input');
                    if (!$inputs.length) return;
                    const m = ($inputs.first().attr('name') || '').match(/passe\[(\d+)\]/);
                    if (!m) return;
                    self.rows.push({ id: m[1], name: $tr.find('.hk-name').text().trim(), $tr: $tr, $inputs: $inputs });
                });
            },

            isComplete(row) {
                let all = true;
                row.$inputs.each(function () {
                    const v = $(this).val();
                    if (v === '' || v === '0') all = false;
                });
                return all;
            },

            firstIncomplete() {
                for (let i = 0; i < this.rows.length; i++) {
                    if (!this.isComplete(this.rows[i])) return i;
                }
                return 0;
            },

            nextIncomplete(from) {
                for (let i = from + 1; i < this.rows.length; i++) {
                    if (!this.isComplete(this.rows[i])) return i;
                }
                return -1;
            },

            open(idx) {
                if (idx < 0 || idx >= this.rows.length) return;
                // Auslöser merken, wenn das Panel neu aufgeht (nicht beim Blättern darin)
                if (!$('#entryPanel').hasClass('open')) {
                    const a = document.activeElement;
                    this._opener = a && a !== document.body && !document.getElementById('entryPanel').contains(a) ? a : null;
                }
                this.idx = idx;
                const row = this.rows[idx];

                const $grid = $('#entryPassenGrid').empty();
                row.$inputs.each(function (i) {
                    const $field = $(
                        '<div class="entry-passe-field" data-pi="' + i + '">' +
                        '<label>Passe ' + (i + 1) + '</label>' +
                        '<input type="text" inputmode="numeric" autocomplete="off" aria-label="Passe ' + (i + 1) + '" maxlength="3" data-max="' + MAX + '">' +
                        '</div>'
                    );
                    $field.find('input').val($(this).val() || '');
                    $grid.append($field);
                });

                $('#entryName').text(row.name);
                $('#entrySubtitle').text((idx + 1) + ' von ' + this.rows.length);
                this.rows.forEach(r => r.$tr.removeClass('selected'));
                row.$tr.addClass('selected');
                if (row.$tr.is(':visible')) row.$tr[0].scrollIntoView({ block: 'nearest' });

                this.refreshFields();
                msvEingabeFehler('#entryFehler', []);
                zaehlen();
                this.stand();
                this._silent = true;
                this.populateSelect();
                this._silent = false;

                $('#entryOverlay').addClass('show');
                $('#entryPanel').addClass('open');
                setTimeout(function () {
                    const $fields = $('#entryPassenGrid input');
                    let $target = $fields.filter(function () {
                        const v = $(this).val().trim();
                        return v === '' || v === '0';
                    }).first();
                    if (!$target.length) $target = $fields.first();
                    $target.focus().select();
                }, 150);
            },

            // Schützen-Suche (Select2) befüllen + aktuellen markieren
            populateSelect() {
                const $sel = $('#entryMemberSelect');
                if ($.fn.select2 && $sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
                $sel.empty();
                const self = this;
                this.rows.forEach(function (r, i) {
                    $sel.append(new Option((self.isComplete(r) ? '✓ ' : '') + r.name, i, false, i === self.idx));
                });
                if (!$.fn.select2) return;   // Select2 nicht geladen: die normale Auswahlliste tut es auch
                $sel.select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#entryPanel'),
                    width: '100%',
                    placeholder: 'Schütze suchen…'
                }).off('select2:open.entry').on('select2:open.entry', function () {
                    setTimeout(function () {
                        const f = document.querySelector('.select2-container--open .select2-search__field');
                        if (f) f.focus();
                    }, 0);
                });
            },

            // Hat der aktuelle Schütze ungespeicherte Passen? Dann Chip «Nicht gespeichert» im Panel-Kopf
            istGeaendert() {
                return this.idx >= 0 && geaendert.has(this.rows[this.idx].id);
            },
            stand() {
                msvPanelUngespeichert($('#entryUngespeichert'), this.istGeaendert());
            },

            // Vor Schliessen, Blättern oder Wechseln: Ungespeichertes speichern, verwerfen oder zurück.
            // Gibt true zurück, wenn das Panel den Schützen verlassen darf.
            _fragt: false,
            async verlassen() {
                if (!this.istGeaendert()) return true;
                if (this._fragt) return false;
                const row = this.rows[this.idx];
                this._fragt = true;
                let wahl;
                try { wahl = await msvUngespeichert({ wer: row.name }); } finally { this._fragt = false; }
                if (wahl === 'speichern') return await this.saveP();
                if (wahl === 'verwerfen') {
                    // Raster-Zeile auf den geladenen Stand zurück (dort steht, was gespeichert ist)
                    row.$inputs.each(function () { this.value = this.defaultValue; this.removeAttribute('aria-invalid'); $(this).trigger('input'); });
                    geaendert.delete(row.id);
                    row.$tr.removeClass('hk-geaendert');
                    zaehlen();
                    return true;
                }
                $('#entryPassenGrid input').first().trigger('focus');
                return false;   // zurück
            },

            async close() {
                if (!(await this.verlassen())) return;
                const letzte = this.idx >= 0 ? this.rows[this.idx].id : null;
                const fokusImPanel = document.getElementById('entryPanel').contains(document.activeElement);
                $('#entryPanel').removeClass('open');
                $('#entryOverlay').removeClass('show');
                this.rows.forEach(r => r.$tr.removeClass('selected'));
                this.idx = -1;
                // Fokus aus dem Panel zurück (sonst bleibt er im ausgeblendeten Panel und fällt an den Seitenanfang):
                // auf den Auslöser (z.B. «Schnellerfassung»), bei einem Namen auf den zuletzt erfassten Schützen
                let aufName = false;
                const opener = this._opener;
                this._opener = null;
                if (fokusImPanel) {
                    if (opener && opener.isConnected && opener.offsetParent !== null && !opener.classList.contains('hk-name')) opener.focus();
                    else if (letzte) { fokusAufName(letzte); aufName = true; }
                }
                // Nach Speichern im Panel frisch laden (Gruppen), aber nie ungespeicherte Raster-Eingaben verwerfen.
                // Das Neuladen ersetzt den Namensknopf: danach wieder auf diesen Schützen.
                if (this._saved && !geaendert.size) {
                    this._saved = false;
                    if (aufName) fokusNachLaden = letzte;
                    loadResultate($('#yearSelect').val());
                }
            },

            async navigate(dir) {
                const n = this.idx + dir;
                if (n < 0 || n >= this.rows.length) return;
                if (await this.verlassen()) this.open(n);
            },

            // Panel-Feld → Raster-Input
            syncField(pi, value) {
                if (this.idx < 0) return;
                const row = this.rows[this.idx];
                row.$inputs.eq(pi).val(value).trigger('input');
                markGeaendert(row.$tr);   // ungespeichert, bis das Panel speichert
                this.stand();
            },

            // Gefüllt, beste Passe und Total im Panel
            refreshFields() {
                let sum = 0, best = -1, bestIdx = -1;
                const $fields = $('#entryPassenGrid .entry-passe-field');
                $fields.each(function (i) {
                    const raw = $(this).find('input').val().trim();
                    const v = parseInt(raw, 10) || 0;
                    $(this).removeClass('is-best');
                    $(this).find('input').toggleClass('filled', raw !== '' && raw !== '0');
                    sum += v;
                    if (v > best && v <= MAX) { best = v; bestIdx = i; }
                });
                if (cfg.bestePasse && bestIdx >= 0 && best > 0) $fields.eq(bestIdx).addClass('is-best');
                $('#entryTotalBadge').html('<span>Total</span>' + sum);
            },

            // Leere Felder vor einem späteren Wert und geleerte Felder als 0 schicken (wie Raster-Save)
            collectPayload(row) {
                const vals = [];
                row.$inputs.each(function () { vals.push($(this).val().trim()); });
                let hasLater = false;
                for (let i = vals.length - 1; i >= 0; i--) {
                    if (vals[i] !== '' && vals[i] !== '0') hasLater = true;
                    else if (vals[i] === '' && (hasLater || geleert(row.$inputs[i]))) vals[i] = '0';
                }
                row.$inputs.each(function (i) {
                    if ($(this).val().trim() === '' && vals[i] === '0') $(this).val('0').trigger('input');
                });
                const passe = {};
                for (let i = 0; i < vals.length; i++) passe[i + 1] = vals[i];
                const obj = {};
                obj[row.id] = passe;
                return obj;
            },

            // Speichert den aktuellen Schützen; Promise: true = gespeichert, false = nicht (Prüffehler oder Serverfehler)
            saveP() {
                if (this.idx < 0 || $('#entrySaveBtn').prop('disabled')) return Promise.resolve(false);   // läuft bereits
                // Wie im Raster: Werte über 100 nicht speichern, sondern im Panel zeigen, wo
                if (msvEingabeFehler('#entryFehler', msvPruefeFelder('#entryPassenGrid'))) return Promise.resolve(false);
                const row = this.rows[this.idx];
                const $btns = $('#entrySaveBtn, #entrySaveNextBtn').prop('disabled', true);
                const self = this;
                return new Promise(function (fertig) {
                    $.ajax({
                        url: cfg.speichern,
                        type: 'POST',
                        data: {
                            csrf_token: $('input[name="csrf_token"]').first().val(),
                            jahr: $('#yearSelect').val(),
                            year: $('#yearSelect').val(),
                            passe: self.collectPayload(row)
                        },
                        dataType: 'json',
                        success: function (resp) {
                            if (!resp || !resp.success) { msvToast((resp && resp.message) || 'Die Passen wurden nicht gespeichert. Bitte nochmals versuchen.', 'error'); fertig(false); return; }
                            self._saved = true;
                            geaendert.delete(row.id);
                            row.$tr.removeClass('hk-geaendert');
                            row.$inputs.each(function () { this.defaultValue = this.value; });   // gilt jetzt als gespeichert
                            zaehlen();
                            self.stand();
                            fertig(true);
                        },
                        error: function (xhr) { msvToast(msvXhrMessage(xhr, 'Die Passen wurden nicht gespeichert. Bitte nochmals versuchen.'), 'error'); fertig(false); },
                        complete: function () {
                            $btns.prop('disabled', false);
                            // Der gesperrte Knopf hat den Fokus verloren: zurück ins Panel
                            if (!document.activeElement || document.activeElement === document.body) $('#entryPassenGrid input').first().trigger('focus');
                        }
                    });
                });
            },
            save(onDone) {
                const name = this.idx >= 0 ? this.rows[this.idx].name : '';
                return this.saveP().then(function (ok) {
                    if (!ok) return;
                    if (typeof onDone === 'function') onDone();
                    else msvToast('Passen von ' + name + ' gespeichert', 'success');
                });
            },

            saveAndNext() {
                const fromIdx = this.idx;
                this.save(function () {
                    const n = EntryPanel.nextIncomplete(fromIdx);
                    if (n >= 0) {
                        EntryPanel.open(n);
                    } else {
                        msvToast('Gespeichert – alle Schützen sind erfasst', 'success');
                        EntryPanel.close();
                    }
                });
            }
        };

        // Panel-Feld-Eingabe: prüfen und ins Raster übernehmen
        $(document).on('input', '#entryPassenGrid input', function (e) {
            let value = $(this).val().replace(/[^0-9]/g, '');
            if (value.length > 3) value = value.substring(0, 3);
            // Über dem Maximum nicht still kappen, sondern markieren; beim Speichern meldet die Fehlerliste den Wert
            this.setAttribute('aria-invalid', value !== '' && parseInt(value, 10) > MAX ? 'true' : 'false');
            $(this).val(value);
            const pi = parseInt($(this).closest('.entry-passe-field').data('pi'), 10);
            EntryPanel.syncField(pi, value);
            EntryPanel.refreshFields();
            $(this).removeData('autoSprung');
            if (!$('#entryFehler').prop('hidden')) msvEingabeFehler('#entryFehler', msvPruefeFelder('#entryPassenGrid'), false);

            // Auto-Weiter beim Tippen: 2-9 → zweistellig, 1 → dreistellig (100); 0 bleibt stehen
            const typed = e.originalEvent && /^insert/.test(e.originalEvent.inputType || '');
            const need = (value === '' || value[0] === '0') ? 0 : (value[0] === '1' ? 3 : 2);
            if (typed && need && value.length >= need) {
                const $inputs = $('#entryPassenGrid input');
                const i = $inputs.index(this);
                // Das Ziel merkt sich den Sprung: ein Enter aus Gewohnheit gleich danach bleibt im (leeren) Feld
                if (i < $inputs.length - 1) $inputs.eq(i + 1).data('autoSprung', true).focus().select();
            }
        });
        $(document).on('blur', '#entryPassenGrid input', function () { $(this).removeData('autoSprung'); });

        $(document).on('focus', '#entryPassenGrid input', function () {
            if ($(this).val() === '0') $(this).val('');
            $(this).select();
        });

        // Enter: nächstes Feld, letztes Feld → Speichern & Weiter
        $(document).on('keydown', '#entryPassenGrid input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                // Gerade selbst hierher gesprungen und noch leer: Enter überspringt nicht diese Passe
                if ($(this).data('autoSprung') && this.value === '') { $(this).removeData('autoSprung'); return; }
                const $inputs = $('#entryPassenGrid input');
                const i = $inputs.index(this);
                if (i < $inputs.length - 1) $inputs.eq(i + 1).focus().select();
                else EntryPanel.saveAndNext();
            }
        });

        $('#startEntryBtn').on('click', function () {
            EntryPanel.buildIndex();
            if (!EntryPanel.rows.length) { msvToast('Keine Mitglieder geladen', 'error'); return; }
            EntryPanel.open(EntryPanel.firstIncomplete());
        });

        // Klick auf die Namen-Zelle öffnet die Schnellerfassung bei diesem Schützen
        $(document).on('click', TAB + ' tbody td:first-child', async function () {
            const $tr = $(this).closest('tr');
            const i = EntryPanel.rows.findIndex(r => r.$tr.is($tr));
            if (i < 0 || i === EntryPanel.idx) return;
            if ($('#entryPanel').hasClass('open') && !(await EntryPanel.verlassen())) return;
            EntryPanel.open(i);
        });

        // Auswahl «Schütze wechseln» springt zum Schützen
        $(document).on('change', '#entryMemberSelect', async function () {
            if (EntryPanel._silent) return;
            const i = parseInt($(this).val(), 10);
            if (isNaN(i) || i === EntryPanel.idx) return;
            if (await EntryPanel.verlassen()) { EntryPanel.open(i); return; }
            EntryPanel._silent = true;   // zurück: Auswahl wieder auf den aktuellen Schützen
            $(this).val(String(EntryPanel.idx)).trigger('change');
            EntryPanel._silent = false;
        });

        $('#entryPrev').on('click', function () { EntryPanel.navigate(-1); });
        $('#entryNext').on('click', function () { EntryPanel.navigate(1); });
        $('#entryClose, #entryOverlay').on('click', function () { EntryPanel.close(); });
        $('#entrySaveBtn').on('click', function () {
            EntryPanel.save(function () {
                msvToast('Gespeichert', 'success');
                EntryPanel.close();
            });
        });
        $('#entrySaveNextBtn').on('click', function () { EntryPanel.saveAndNext(); });
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#entryPanel').hasClass('open') && !$('.select2-container--open').length && !$('.swal2-container').length) EntryPanel.close();
        });

        // Ctrl+S speichert (Panel offen: diesen Schützen, sonst das ganze Raster), Ctrl+Enter im Panel: speichern & weiter
        $(document).on('keydown', function (e) {
            if (!(e.ctrlKey || e.metaKey)) return;
            const panelOffen = $('#entryPanel').hasClass('open');
            if (e.key === 's' || e.key === 'S') {
                e.preventDefault();
                if (panelOffen) $('#entrySaveBtn').trigger('click');
                else if (!$('#rasterSpeichernBtn').prop('disabled')) $(FORM).trigger('submit');
            } else if (e.key === 'Enter' && panelOffen) {
                e.preventDefault();
                EntryPanel.saveAndNext();
            }
        });

        // Start
        initializeYearDropdown();
        aktuellesJahr = $('#yearSelect').val();
        loadResultate(aktuellesJahr);
    }

    window.MsvRaster = { init: init };
})(jQuery);
