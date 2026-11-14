/**
 * app-drucksteuerung.js — Seite «Drucksteuerung» (inc/drucksteuerung.php)
 *
 * Reihenfolge der Einrichtung: Verbindung zu QZ Tray → Drucker dieses Browsers → Druckprofile pro Dokument.
 * Dokumente und Formate kommen aus js/msv-druck-dokumente.js (dieselbe Quelle wie der Direktdruck auf den Seiten).
 * Umbau 1.68.52 nach der Critique vom 03.11.2026:
 *  - ohne QZ Tray kein Fehlerdialog, sondern ein Hinweis mit dem nächsten Schritt (auch: herunterladen und normal drucken)
 *  - Profile gehen nie still verloren: inaktive Drucker bleiben in ihren Profilen, Entfernen nur nach Rückfrage
 *  - ungespeicherte Zeilen sind markiert, «Speichern» ist der eine Hauptknopf, Verlassen warnt
 *  - Gruppen und Knöpfe per Tastatur erreichbar, jedes Feld beschriftet
 *  - Einrichtung eines anderen Arbeitsplatzes (anderer Browser, gelöschte Browserdaten) übernehmen
 * Einstieg 1.68.58 (impeccable onboard):
 *  - Schrittleiste in der Kopf-Card: Verbindung → Drucker → Dokumente → Testseite, der nächste Schritt als Knopf
 *  - «Alle Dokumente einrichten»: ein Drucker für alle Dokumente ohne Drucker, danach ausdrücklich «Speichern»
 *  - ohne Drucker keine leere Profilliste, sondern ein Leerzustand; Leerzustände sagen, was als Nächstes kommt
 */
(function ($) {
  'use strict';

  const KATALOG = window.MsvDruckDokumente;
  const ZU_SCHLUESSEL = 'msv_druckprofile_zu'; // zugeklappte Gruppen (Standard: alle offen)
  const SCHNELL_AUS = 'msv_druckprofile_schnell_aus'; // «übrige Dokumente einrichten» ausgeblendet (dieser Browser)
  const ABSCHNITT = { verbindung: 'drVerbindungTitel', drucker: 'drDruckerTitel', dokumente: 'drProfileTitel', test: 'drProfileTitel' };
  const PAPIER = { A3: [297, 420], A4: [210, 297], A5: [148, 210], LETTER: [216, 279] };
  const esc = s => msvEsc(s == null ? '' : String(s));
  const csrf = () => (document.querySelector('[name="csrf_token"]') || {}).value || '';

  const Druck = {
    pm: null,
    verbunden: false,
    pruefend: true,    // erster Verbindungsversuch läuft noch
    log: null,         // letzte Druckaufträge dieses Browsers (null = noch nicht geladen)
    drucker: [],       // Drucker dieses Browsers (printers)
    profile: [],       // Profile dieses Browsers (print_profiles)
    system: [],        // Drucker des Computers (live von QZ Tray)
    geaendert: new Set(),

    // =================================================================== Start
    init() {
      this.pm = new PrintManager();
      this.pm.onStatusChange = (c) => { this.verbunden = !!c; this.renderDrucker(); this.zeigeStatus(); this.renderMatrix(true); };

      const chip = document.getElementById('drArbeitsplatz');
      if (chip && typeof getMachineId === 'function') {
        chip.dataset.tooltip = 'Drucker und Profile gelten für diesen Browser (Kennung ' + getMachineId().slice(0, 8)
          + '). In einem anderen Browser oder nach dem Löschen der Browserdaten lässt sich die Einrichtung übernehmen.';
      }

      $('#btnConnect').on('click', () => this.verbinde(true));
      $('#btnDruckerNeu').on('click', () => this.oeffnePanel(null));
      $('#btnSpeichern').on('click', () => this.speichern());
      $('#btnVerwerfen').on('click', () => this.verwerfen());
      $('#btnLogNeu').on('click', () => this.ladeLog());
      $('#druckerSpeichern').on('click', () => this.speichereDrucker());
      $('#druckerAbbrechen, #druckerClose, #druckerOverlay').on('click', () => this.schliessePanel());
      $(document).on('keydown', (e) => { if (e.key === 'Escape' && $('#druckerPanel').hasClass('open')) this.schliessePanel(); });

      const $m = $('#profileMatrix');
      $m.on('click', '.dr-gruppe-knopf', (e) => this.klappe(e.currentTarget));
      $m.on('click', '.dr-test', (e) => this.testdruck(e.currentTarget.dataset.doc));
      $m.on('click', '.dr-uebertragen', (e) => this.uebertrage(e.currentTarget.dataset.doc, e.shiftKey));
      $m.on('change input', '[data-feld]', (e) => this.markiere($(e.target).closest('.dr-zeile')[0]));
      $('#drAblauf').on('click', '.dr-schritt', (e) => this.geheZu(e.currentTarget.dataset.schritt));
      $('#drWeiter').on('click', '[data-weiter]', (e) => this.weiter(e.currentTarget));
      $('#drSchnell').on('click', '#drSchnellKnopf', () => this.schnellEinrichten());
      $('#drSchnell').on('click', '#drSchnellAus', () => {
        try { localStorage.setItem(SCHNELL_AUS, '1'); } catch (e) { /* privat: egal */ }
        this.zeigeSchnell();
        document.getElementById('drProfileTitel').focus();
      });
      $('#druckerListe').on('click', '.dr-neu', () => this.oeffnePanel(null));
      $('#druckerListe').on('click', '.dr-bearbeiten', (e) => this.oeffnePanel(Number(e.currentTarget.dataset.id)));
      $('#druckerListe').on('click', '.dr-loeschen', (e) => this.loescheDrucker(Number(e.currentTarget.dataset.id)));
      // Wegklicken über einen Link (Menü, Kopf): eigener Dialog statt der Browser-Meldung.
      // Neu laden / Tab schliessen bleibt beim Browser-Dialog (beforeunload lässt sich nicht ersetzen).
      document.addEventListener('click', (e) => this.vorVerlassen(e), true);
      document.addEventListener('keydown', (e) => this.vorNeuLaden(e), true);
      $(window).on('beforeunload', (e) => {
        if (!this.geaendert.size || this.verlassenOk) return undefined;
        e.preventDefault();
        return 'Die Druckprofile sind noch nicht gespeichert.';
      });

      this.zeigeStatus();
      this.ladeAlles();
      this.ladeLog();
      this.ladeSetup();
      this.verbinde(false);
    },

    // =================================================================== Verbindung
    async verbinde(manuell) {
      $('#btnConnect').prop('disabled', true);
      $('#qzStatusText').text('Verbinde mit QZ Tray …');
      $('#qzStatus').attr('class', 'ui-status aus');
      this.pruefend = true;
      this.zeigeAblauf();
      try {
        await this.pm.connect();
        this.verbunden = true;
        $('#qzHinweis').prop('hidden', true).empty();
        try { this.system = await this.pm.listPrinters(); } catch (e) { this.system = []; }
      } catch (err) {
        this.verbunden = false;
        console.warn('[Drucksteuerung] QZ Tray:', err);
        this.zeigeHinweis(err && err.message ? err.message : String(err));
        if (manuell) msvToast('Keine Verbindung zu QZ Tray', 'warning');
      } finally {
        this.pruefend = false;
        $('#btnConnect').prop('disabled', false);
        this.renderDrucker();
        this.zeigeStatus();
        this.renderMatrix(true);
      }
    },

    zeigeStatus() {
      const v = this.verbunden;
      $('#qzStatus').attr('class', 'ui-status ' + (v ? 'ok' : 'fehler'));
      $('#qzStatusText').text(v ? 'Mit QZ Tray verbunden' : 'Nicht mit QZ Tray verbunden');
      $('#btnConnect').prop('hidden', v);
      $('#drSetupZeile').prop('hidden', !v || !this.setup); // ohne Verbindung steht das Setup schon im Hinweis
      this.zeigeAblauf();
    },

    // =================================================================== Einrichtung (Schrittleiste)
    /** Stand der vier Schritte und der nächste Schritt; erledigt ist, was für den Direktdruck schon reicht. */
    schritte() {
      const aktiv = this.drucker.filter(d => Number(d.aktiv) === 1).length;
      const mit = this.profile.filter(p => p.printer_id).length;
      const gedruckt = (this.log || []).find(j => j.status !== 'fehler');
      const am = gedruckt ? new Date(String(gedruckt.erstellt_am).replace(' ', 'T')).toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';
      return [
        { key: 'verbindung', fertig: this.verbunden, stand: this.verbunden ? 'QZ Tray verbunden' : (this.pruefend ? 'wird geprüft …' : 'QZ Tray fehlt') },
        { key: 'drucker', fertig: aktiv > 0, stand: aktiv ? aktiv + ' Drucker' : 'noch keiner' },
        { key: 'dokumente', fertig: mit > 0, stand: mit + ' von ' + this.alleDokumente().length + ' eingerichtet' },
        { key: 'test', fertig: !!gedruckt, stand: gedruckt ? 'gedruckt am ' + am : (this.log === null ? '…' : 'noch keine') },
      ];
    },

    zeigeAblauf() {
      const schritte = this.schritte();
      // solange die erste Verbindung geprüft wird, ist noch nichts «jetzt» (sonst springt die Leiste beim Laden)
      const jetzt = this.pruefend && !this.verbunden ? null : (schritte.find(s => !s.fertig) || null);
      schritte.forEach((s, i) => {
        const b = document.querySelector('#drAblauf .dr-schritt[data-schritt="' + s.key + '"]');
        if (!b) return;
        const istJetzt = !!jetzt && jetzt.key === s.key;
        b.classList.toggle('ist-fertig', s.fertig);
        b.classList.toggle('ist-jetzt', istJetzt);
        if (istJetzt) b.setAttribute('aria-current', 'step'); else b.removeAttribute('aria-current');
        b.querySelector('.dr-schritt-nr').innerHTML = s.fertig ? '<i class="bi bi-check-lg"></i>' : String(i + 1);
        const name = b.querySelector('.dr-schritt-name');
        name.innerHTML = esc(name.textContent.replace(' (erledigt)', '')) + (s.fertig ? '<span class="visually-hidden"> (erledigt)</span>' : '');
        b.querySelector('.dr-schritt-stand').textContent = s.stand;
      });

      let weiter = '';
      if (!jetzt) {
        if (!this.pruefend) weiter = '<span class="ui-status ok"><span class="ui-punkt" aria-hidden="true"></span>Direktdruck bereit</span>';
      } else if (jetzt.key === 'drucker' && this.verbunden) {
        weiter = '<button type="button" class="btn btn-primary btn-sm" data-weiter="drucker"><i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i>Drucker übernehmen</button>';
      } else if (jetzt.key === 'dokumente') {
        weiter = '<button type="button" class="btn btn-primary btn-sm" data-weiter="dokumente"><i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i>Dokumente einrichten</button>';
      } else if (jetzt.key === 'test' && this.verbunden) {
        const ziel = this.testZiel();
        if (ziel) {
          const p = this.profilVon(ziel.doc_type);
          const d = this.drucker.find(x => Number(x.id) === Number(p.printer_id));
          weiter = '<button type="button" class="btn btn-primary btn-sm" data-weiter="test" data-doc="' + esc(ziel.doc_type) + '" data-tooltip="'
            + esc('Eine Seite mit Rahmen und Massband: «' + ziel.label + '» auf ' + this.druckerName(d)) + '">'
            + '<i class="bi bi-printer me-1" aria-hidden="true"></i>Testseite drucken</button>';
        }
      }
      // Verbindung: die Anleitung mit «Neu verbinden» steht direkt darunter in der Karte, kein dritter Knopf
      $('#drWeiter').html(weiter);
    },

    /** Für die Testseite: ein eingerichtetes Dokument auf einem aktiven Drucker, lieber ein einfaches Blatt als ein Faltblatt. */
    testZiel() {
      const mit = this.alleDokumente().filter(def => {
        const p = this.profilVon(def.doc_type);
        return p && p.printer_id && this.drucker.some(d => Number(d.id) === Number(p.printer_id) && Number(d.aktiv) === 1);
      });
      return mit.find(def => !def.broschuere) || mit[0] || null;
    },

    /** Schritt anklicken: zum Abschnitt scrollen und den Titel fokussieren (Tastatur und Screenreader landen dort). */
    geheZu(key) {
      const h = document.getElementById(ABSCHNITT[key]);
      if (!h) return;
      const ruhig = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      h.closest('section').scrollIntoView({ behavior: ruhig ? 'auto' : 'smooth', block: 'start' });
      h.focus({ preventScroll: true });
    },

    weiter(knopf) {
      const was = knopf.dataset.weiter;
      if (was === 'drucker') this.oeffnePanel(null);
      else if (was === 'dokumente') {
        this.geheZu('dokumente');
        const k = document.getElementById('drSchnellKnopf');
        if (k) k.focus({ preventScroll: true });
      } else if (was === 'test') {
        knopf.disabled = true;
        this.testdruck(knopf.dataset.doc).finally(() => { knopf.disabled = false; });
      }
    },

    /** Hinweis mit dem nächsten Schritt statt der technischen Meldung (die steht in der Konsole). */
    zeigeHinweis(meldung) {
      this._letzteMeldung = meldung;
      const m = String(meldung || '').toLowerCase();
      const s = this.setup;
      const setupText = s
        ? 'das Setup «MSV Wilen Direktdruck» herunterladen und ausführen (braucht Administratorrechte am Computer)'
        : 'das Setup «MSV Wilen Direktdruck» ausführen; es ist noch nicht auf dem Server, bitte beim Administrator melden';
      let html;
      if (/sign|certificate|zertifikat|untrusted/.test(m)) {
        html = '<p><strong>QZ Tray lehnt die Verbindung ab.</strong> Auf diesem Computer fehlt das Zertifikat der Vereinsverwaltung.</p>'
          + '<p>Abhilfe: ' + setupText + '. Es richtet das Zertifikat ein, QZ Tray bleibt. Danach «Neu verbinden».</p>';
      } else {
        html = '<p><strong>QZ Tray läuft nicht auf diesem Computer.</strong> Ohne QZ Tray gibt es keinen Direktdruck.</p>'
          + '<ol class="ps-3 mb-2"><li class="mb-1"><strong>Schon installiert?</strong> Im Windows-Startmenü «QZ Tray» öffnen. Es läuft danach unten rechts in der Taskleiste. Dann «Neu verbinden».</li>'
          + '<li><strong>Noch nicht installiert?</strong> Dafür ' + setupText + '. Es installiert QZ Tray mit dem Zertifikat. Danach «Neu verbinden».</li></ol>';
      }
      html += '<p class="small mb-2">Ohne QZ Tray: Dokumente herunterladen und wie gewohnt drucken. Auf einem Mac: QZ Tray von qz.io; das Zertifikat richtet der Administrator ein.</p>'
        + '<div class="d-flex flex-wrap gap-2">'
        + '<button type="button" class="btn btn-outline-primary btn-sm" id="btnHinweisVerbinden"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Neu verbinden</button>'
        + (s ? '<a class="btn btn-outline-info btn-sm" href="drucksteuerung/qz_setup.php" download><i class="bi bi-download me-1" aria-hidden="true"></i>Setup herunterladen (Windows, ' + esc(s.mb) + ' MB)</a>' : '')
        + '</div>';
      $('#qzHinweis').html(html).prop('hidden', false);
      $('#btnHinweisVerbinden').on('click', () => this.verbinde(true));
    },

    /** Setup «MSV Wilen Direktdruck» auf dem Server? Dann im Hinweis und für weitere Computer anbieten. */
    ladeSetup() {
      $.getJSON('drucksteuerung/qz_setup.php', { info: 1 }).done((r) => {
        this.setup = r && r.success ? r : null;
        if (this.setup) {
          $('#drSetupZeile').html('<i class="bi bi-download me-1" aria-hidden="true"></i>Weiterer Computer? '
            + '<a href="drucksteuerung/qz_setup.php" download>Setup «MSV Wilen Direktdruck» herunterladen</a> (Windows, ' + esc(this.setup.mb) + ' MB, Version ' + esc(this.setup.version) + '): installiert QZ Tray mit dem Zertifikat.')
            .prop('hidden', !this.verbunden);
        }
        if (!this.verbunden && !$('#qzHinweis').prop('hidden')) this.zeigeHinweis(this._letzteMeldung);
      });
    },

    // =================================================================== Daten
    alleDokumente() {
      return KATALOG.gruppen.reduce((a, g) => a.concat(g.dokumente), []);
    },

    ladeAlles() {
      return $.when(
        $.getJSON('drucksteuerung/printers_api.php'),
        $.getJSON('drucksteuerung/profiles_api.php')
      ).done((d, p) => {
        this.drucker = (d[0] && d[0].data) || [];
        this.profile = (p[0] && p[0].data) || [];
        this.geaendert.clear();
        this.renderDrucker();
        this.renderMatrix();
        this.zeigeStatus();
        this.pruefeAndere();
      }).fail((xhr) => {
        msvToast(msvXhrMessage(xhr, 'Drucker und Profile konnten nicht geladen werden. Bitte die Seite neu laden.'), 'error');
        $('#druckerListe').html('<div class="dr-leer text-danger">Drucker konnten nicht geladen werden.</div>');
      });
    },

    /** Hat dieser Browser nichts, aber ein anderer Arbeitsplatz desselben Benutzers? Dann Übernahme anbieten. */
    pruefeAndere() {
      const $box = $('#drAndere');
      if (this.profile.some(p => p.printer_id)) { $box.prop('hidden', true).empty(); return; }
      $.getJSON('drucksteuerung/printers_api.php', { andere: 1 }).done((r) => {
        const liste = (r && r.data) || [];
        if (!liste.length) { $box.prop('hidden', true).empty(); return; }
        let html = '<p><strong>Auf diesem Browser ist noch nichts eingerichtet.</strong> Du hast den Direktdruck schon an einem anderen Arbeitsplatz eingerichtet; das lässt sich hierher übernehmen.</p><ul class="mb-2 ps-3">';
        liste.forEach((a, i) => {
          const zuletzt = a.zuletzt ? new Date(String(a.zuletzt).replace(' ', 'T')).toLocaleDateString('de-CH') : '';
          html += '<li class="mb-1">' + esc(a.profile) + ' Profile, Drucker: ' + esc(a.drucker || '–') + (zuletzt ? ', eingerichtet am ' + esc(zuletzt) : '')
            + ' <button type="button" class="btn btn-outline-success btn-sm ms-2 dr-uebernehmen" data-i="' + i + '"><i class="bi bi-download me-1" aria-hidden="true"></i>Übernehmen</button></li>';
        });
        html += '</ul><p class="small mb-0">Vorhandenes bleibt, wie es ist. Hat der andere Computer Drucker mit anderen Namen, danach unter «Drucker» prüfen.</p>';
        $box.html(html).prop('hidden', false);
        $box.find('.dr-uebernehmen').on('click', (e) => this.uebernehme(liste[Number(e.currentTarget.dataset.i)].machine_id, e.currentTarget));
      });
    },

    uebernehme(von, knopf) {
      $(knopf).prop('disabled', true);
      this.sende('drucksteuerung/printers_api.php', { action: 'uebernehmen', von: von === null ? '' : von })
        .then((r) => { msvToast(r.message || 'Einrichtung übernommen', 'success'); return this.ladeAlles(); })
        .catch((err) => { msvToast(err.message, 'error'); $(knopf).prop('disabled', false); });
    },

    /** POST mit JSON; liefert die Antwort oder wirft Error mit der Servermeldung. */
    sende(url, body) {
      return new Promise((resolve, reject) => {
        $.ajax({ url, method: 'POST', contentType: 'application/json', dataType: 'json',
          headers: { 'X-CSRF-Token': csrf() }, data: JSON.stringify(body) })
          .done((r) => (r && r.success ? resolve(r) : reject(new Error((r && r.message) || 'Speichern fehlgeschlagen'))))
          .fail((xhr) => reject(new Error(msvXhrMessage(xhr, 'Keine Verbindung zum Server. Bitte nochmals versuchen.'))));
      });
    },

    // =================================================================== Drucker
    druckerName(d) { return d ? (d.anzeigename || d.name) : ''; },

    renderDrucker() {
      const $b = $('#druckerListe');
      // ohne Drucker steht der Knopf im Leerzustand, nicht doppelt im Kartenkopf
      $('#btnDruckerNeu, #drDruckerKopf').prop('hidden', !this.drucker.length);
      if (!this.drucker.length) {
        let inhalt;
        if (this.pruefend && !this.verbunden) {
          inhalt = '<p>Drucker werden gesucht …</p>';
        } else if (!this.verbunden) {
          inhalt = '<i class="bi bi-plug" aria-hidden="true"></i><p><strong>Noch kein Drucker.</strong></p>'
            + '<p>Die Drucker kommen über QZ Tray vom Computer. Sobald oben die Verbindung steht, wählst du hier einen aus.</p>';
        } else if (!(this.system || []).length) {
          inhalt = '<i class="bi bi-printer" aria-hidden="true"></i><p><strong>QZ Tray findet auf diesem Computer keinen Drucker.</strong></p>'
            + '<p>Den Drucker zuerst in Windows einrichten (Einstellungen › Drucker und Scanner), dann oben «Neu verbinden».</p>';
        } else {
          const n = this.system.length;
          inhalt = '<i class="bi bi-printer" aria-hidden="true"></i><p><strong>Noch kein Drucker übernommen.</strong> '
            + 'QZ Tray sieht ' + (n === 1 ? 'einen Drucker' : n + ' Drucker') + ' auf diesem Computer.</p>'
            + '<button type="button" class="btn btn-outline-success btn-sm dr-neu"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Drucker übernehmen</button>'
            + '<p class="small">Geht auch ohne diese Seite: Beim ersten Klick auf ein Drucker-Symbol neben einem PDF fragt die App nach dem Drucker.</p>';
        }
        $b.html('<div class="dr-leer" role="row"><div role="cell">' + inhalt + '</div></div>');
        return;
      }
      $b.html(this.drucker.map(d => {
        const n = this.profile.filter(p => Number(p.printer_id) === Number(d.id)).length;
        const name = this.druckerName(d);
        return '<div class="dr-drucker-zeile" role="row">'
          + '<div role="cell" class="dr-drucker-name">' + esc(name) + (d.anzeigename && d.anzeigename !== d.name ? '<span class="dr-drucker-system">' + esc(d.name) + '</span>' : '') + '</div>'
          + '<div role="cell">' + (Number(d.aktiv) === 1 ? '<span class="ui-status ok"><span class="ui-punkt" aria-hidden="true"></span>Aktiv</span>' : '<span class="ui-status aus"><span class="ui-punkt" aria-hidden="true"></span>Inaktiv</span>') + '</div>'
          + '<div role="cell">' + (n ? 'genutzt von ' + n + (n === 1 ? ' Profil' : ' Profilen') : '<span class="cell-empty">nicht genutzt</span>') + '</div>'
          + '<div role="cell" class="dr-aktionen">'
          + '<button type="button" class="btn btn-outline-primary btn-sm dr-bearbeiten" data-id="' + d.id + '" aria-label="' + esc(name) + ' bearbeiten" data-tooltip="Bearbeiten"><i class="bi bi-pencil" aria-hidden="true"></i></button>'
          + '<button type="button" class="btn btn-outline-danger btn-sm dr-loeschen" data-id="' + d.id + '" aria-label="' + esc(name) + ' löschen" data-tooltip="Löschen"><i class="bi bi-trash" aria-hidden="true"></i></button>'
          + '</div></div>';
      }).join(''));
    },

    oeffnePanel(id) {
      const d = id ? this.drucker.find(x => Number(x.id) === id) : null;
      $('#druckerId').val(d ? d.id : '');
      $('#druckerPanelTitel').text(d ? 'Drucker bearbeiten' : 'Drucker übernehmen');
      $('#druckerWahlFeld').prop('hidden', !!d);
      $('#druckerNameFeld').prop('hidden', !d);
      $('#druckerSystemText').text(d ? d.name : '');
      $('#druckerAnzeige').val(d ? (d.anzeigename && d.anzeigename !== d.name ? d.anzeigename : '') : '');
      $('#druckerAktiv').prop('checked', d ? Number(d.aktiv) === 1 : true);
      let speichernMoeglich = true;
      if (!d) {
        const $s = $('#druckerSystem').empty();
        const frei = (this.system || []).filter(n => !this.drucker.some(x => x.name === n));
        if (!this.verbunden) {
          $s.append('<option value="">QZ Tray nicht verbunden</option>').prop('disabled', true);
          $('#druckerSystemHinweis').text('Ohne Verbindung zu QZ Tray lassen sich keine Drucker übernehmen. Zuerst oben «Neu verbinden».');
          speichernMoeglich = false;
        } else if (!frei.length) {
          $s.append('<option value="">Keine weiteren Drucker</option>').prop('disabled', true);
          $('#druckerSystemHinweis').text('Alle Drucker dieses Computers sind schon übernommen.');
          speichernMoeglich = false;
        } else {
          frei.forEach(n => $s.append('<option value="' + esc(n) + '">' + esc(n) + '</option>'));
          $s.prop('disabled', false);
          $('#druckerSystemHinweis').text('Die Liste kommt live von QZ Tray.');
        }
      }
      $('#druckerSpeichern').prop('disabled', !speichernMoeglich);
      $('#druckerPanel').addClass('open');
      $('#druckerOverlay').addClass('show');
      setTimeout(() => $(d ? '#druckerAnzeige' : '#druckerSystem').trigger('focus'), 320);
    },

    schliessePanel() {
      $('#druckerPanel').removeClass('open');
      $('#druckerOverlay').removeClass('show');
    },

    speichereDrucker() {
      const id = Number($('#druckerId').val()) || 0;
      const d = id ? this.drucker.find(x => Number(x.id) === id) : null;
      const anzeige = $('#druckerAnzeige').val().trim();
      const aktiv = $('#druckerAktiv').is(':checked') ? 1 : 0;
      let body;
      if (d) {
        body = { action: 'update', id, anzeigename: anzeige || d.name, typ: d.typ || 'laser', beschreibung: d.beschreibung || '', ist_standard: d.ist_standard || 0, aktiv };
      } else {
        const name = $('#druckerSystem').val();
        if (!name) { msvToast('Bitte einen Drucker wählen.', 'warning'); return; }
        body = { action: 'add', name, anzeigename: anzeige || name };
      }
      const $k = $('#druckerSpeichern').prop('disabled', true);
      this.sende('drucksteuerung/printers_api.php', body)
        .then(() => {
          msvToast(d ? 'Drucker gespeichert' : 'Drucker übernommen', 'success');
          this.schliessePanel();
          // neue Druckerliste übernehmen, ungespeicherte Profiländerungen behalten
          return $.getJSON('drucksteuerung/printers_api.php').done((r) => {
            this.drucker = (r && r.data) || [];
            this.renderDrucker();
            this.renderMatrix(true);
            this.zeigeStatus();
          });
        })
        .catch((err) => msvToast(err.message, 'error'))
        .finally(() => $k.prop('disabled', false));
    },

    async loescheDrucker(id) {
      const d = this.drucker.find(x => Number(x.id) === id);
      if (!d) return;
      const nutzer = this.profile.filter(p => Number(p.printer_id) === id).map(p => (KATALOG.finde(p.doc_type) || {}).label || p.doc_type);
      const html = 'Möchtest du <strong>' + esc(this.druckerName(d)) + '</strong> wirklich löschen?'
        + (nutzer.length
          ? '<p class="mt-2 mb-1 text-start">Diese Profile verlieren damit ihren Drucker:</p><ul class="text-start ps-3 mb-1">' + nutzer.map(n => '<li>' + esc(n) + '</li>').join('') + '</ul>'
            + '<p class="text-start small mb-0">Sie bleiben in der Liste und brauchen danach einen anderen Drucker. Soll er nur nicht mehr zur Wahl stehen, stattdessen auf «Inaktiv» setzen.</p>'
          : '');
      const r = await msvConfirmDelete(this.druckerName(d), { html });
      if (!r.isConfirmed) return;
      this.sende('drucksteuerung/printers_api.php', { action: 'delete', id })
        .then(() => { msvToast('Drucker gelöscht', 'success'); return this.ladeAlles(); })
        .catch((err) => msvToast(err.message, 'error'));
    },

    // =================================================================== Profile
    profilVon(docType) { return this.profile.find(p => p.doc_type === docType) || null; },

    optionAus(profil, feld) {
      if (!profil || !profil.optionen) return null;
      try {
        const o = typeof profil.optionen === 'string' ? JSON.parse(profil.optionen) : profil.optionen;
        return (o && o[feld]) || null;
      } catch (e) { return null; }
    },

    zugeklappt() {
      try { return JSON.parse(localStorage.getItem(ZU_SCHLUESSEL) || '[]'); } catch (e) { return []; }
    },

    /** behalte=true: Eingaben der Zeilen übernehmen (z.B. nach neuem Drucker oder Verbindungswechsel). */
    renderMatrix(behalte) {
      const $m = $('#profileMatrix');
      const vorher = {};
      if (behalte) $m.find('.dr-zeile').each((_, z) => { vorher[z.dataset.doc] = this.werteAus(z); });
      const zu = this.zugeklappt();
      const aktiveDrucker = this.drucker.filter(d => Number(d.aktiv) === 1);
      let html = '<div role="rowgroup"><div class="dr-matrix-kopf" role="row">'
        + '<span role="columnheader">Dokument</span><span role="columnheader">Drucker</span><span role="columnheader">Format</span>'
        + '<span role="columnheader">Kopien</span><span role="columnheader">Beidseitig</span><span role="columnheader">Farbe</span>'
        + '<span role="columnheader"><span class="visually-hidden">Aktionen</span></span></div></div>';

      // ohne Drucker keine Liste mit 18 leeren Zeilen: nur sagen, was hier entsteht
      const ohneDrucker = !this.drucker.length;
      $('#drProfilFuss').prop('hidden', ohneDrucker);
      $m.attr('aria-describedby', ohneDrucker ? null : 'drProfilFussText');
      if (ohneDrucker) {
        $m.html('<div class="dr-leer" role="row"><div role="cell"><i class="bi bi-list-check" aria-hidden="true"></i>'
          + '<p><strong>Hier legst du fest, welches Dokument auf welchem Drucker herauskommt.</strong></p>'
          + '<p>Sobald oben ein Drucker übernommen ist, richtest du alle ' + this.alleDokumente().length + ' Dokumente mit einem Klick ein.</p></div></div>');
        this.geaendert.clear();
        this.zeigeAenderungen();
        return;
      }

      KATALOG.gruppen.forEach((g, gi) => {
        const offen = !zu.includes(g.gruppe);
        const fertig = g.dokumente.filter(def => { const p = this.profilVon(def.doc_type); return p && p.printer_id; }).length;
        const zid = 'drGruppe' + gi;
        html += '<div role="rowgroup">'
          + '<div role="row"><div role="cell"><button type="button" class="dr-gruppe-knopf" aria-expanded="' + offen + '" aria-controls="' + zid + '" data-gruppe="' + esc(g.gruppe) + '">'
          + '<i class="bi bi-chevron-down dr-chevron" aria-hidden="true"></i><span>' + esc(g.gruppe) + '</span>'
          + '<span class="dr-gruppe-zahl">' + fertig + ' von ' + g.dokumente.length + ' eingerichtet</span></button></div></div>'
          + '<div id="' + zid + '"' + (offen ? '' : ' hidden') + '>';
        g.dokumente.forEach(def => { html += this.zeile(def, aktiveDrucker, behalte ? vorher[def.doc_type] : null); });
        html += '</div></div>';
      });
      $m.html(html);
      if (behalte) $m.find('.dr-zeile').each((_, z) => this.markiere(z, true));
      this.zeigeAenderungen();
    },

    zeile(def, aktiveDrucker, werte) {
      const p = this.profilVon(def.doc_type);
      const w = werte || {
        printer_id: p && p.printer_id ? String(p.printer_id) : '',
        format: def.formate ? (this.optionAus(p, 'format') || def.formate[0].value) : '',
        copies: String(p ? (p.copies || 1) : 1),
        duplex: p ? (String(p.duplex) === '1' ? 'long-edge' : String(p.duplex || '')) : '',
        color_mode: (p && p.color_mode) || 'blackwhite',
      };
      const label = def.label;
      // Drucker: aktive zur Wahl; ein inaktiver bleibt im eigenen Profil stehen (nie still verlieren)
      let opts = '<option value="">Kein Drucker</option>';
      const liste = aktiveDrucker.slice();
      const eigener = p && p.printer_id ? this.drucker.find(d => Number(d.id) === Number(p.printer_id)) : null;
      if (eigener && Number(eigener.aktiv) !== 1) liste.push(eigener);
      liste.forEach(d => {
        opts += '<option value="' + d.id + '"' + (String(d.id) === String(w.printer_id) ? ' selected' : '') + '>'
          + esc(this.druckerName(d)) + (Number(d.aktiv) !== 1 ? ' (inaktiv)' : '') + '</option>';
      });
      let warn = '';
      if (p && !p.printer_id) warn = '<span class="dr-warn">Der Drucker wurde gelöscht. Bitte einen anderen wählen.</span>';
      else if (eigener && Number(eigener.aktiv) !== 1) warn = '<span class="dr-warn">Der Drucker ist inaktiv.</span>';

      const format = def.formate
        ? '<select class="form-select form-select-sm" data-feld="format" aria-label="Format für ' + esc(label) + '">'
          + def.formate.map(f => '<option value="' + f.value + '"' + (f.value === w.format ? ' selected' : '') + '>' + esc(f.label) + '</option>').join('') + '</select>'
        : '<span class="dr-format">' + esc(def.format.text) + '</span>';

      const duplex = [['', def.broschuere ? 'Automatisch (kurze Kante)' : 'Einseitig'], ['long-edge', 'Beidseitig, lange Kante'], ['short-edge', 'Beidseitig, kurze Kante']]
        .map(([v, t]) => '<option value="' + v + '"' + (v === w.duplex ? ' selected' : '') + '>' + t + '</option>').join('');
      const farbe = [['blackwhite', 'Schwarzweiss'], ['grayscale', 'Graustufen'], ['color', 'Farbe']]
        .map(([v, t]) => '<option value="' + v + '"' + (v === w.color_mode ? ' selected' : '') + '>' + t + '</option>').join('');

      const testGrund = !this.verbunden ? 'Testdruck braucht die Verbindung zu QZ Tray' : (!w.printer_id ? 'Zuerst einen Drucker wählen' : '');
      return '<div class="dr-zeile" role="row" data-doc="' + def.doc_type + '">'
        + '<div role="cell" data-label="Dokument"><div><span class="dr-name">' + esc(label) + '</span><span class="dr-punkt" hidden aria-hidden="true"></span>'
        + '<span class="visually-hidden dr-punkt-text" hidden>, nicht gespeichert</span><span class="dr-desc">' + esc(def.desc) + '</span>' + warn + '</div></div>'
        + '<div role="cell" data-label="Drucker"><select class="form-select form-select-sm" data-feld="printer_id" aria-label="Drucker für ' + esc(label) + '">' + opts + '</select></div>'
        + '<div role="cell" data-label="Format">' + format + '</div>'
        + '<div role="cell" data-label="Kopien"><input type="number" class="form-control form-control-sm" data-feld="copies" min="1" max="9" value="' + esc(w.copies) + '" aria-label="Kopien für ' + esc(label) + '"></div>'
        + '<div role="cell" data-label="Beidseitig"><select class="form-select form-select-sm" data-feld="duplex" aria-label="Beidseitig für ' + esc(label) + '">' + duplex + '</select></div>'
        + '<div role="cell" data-label="Farbe"><select class="form-select form-select-sm" data-feld="color_mode" aria-label="Farbe für ' + esc(label) + '">' + farbe + '</select></div>'
        + '<div role="cell" class="dr-aktionen">'
        + '<button type="button" class="btn btn-outline-info btn-sm dr-test" data-doc="' + def.doc_type + '" aria-label="Testseite für ' + esc(label) + ' drucken" data-tooltip="' + esc(testGrund || 'Testdruck mit den Werten dieser Zeile') + '"' + (testGrund ? ' disabled' : '') + '><i class="bi bi-printer" aria-hidden="true"></i></button>'
        + '<button type="button" class="btn btn-outline-secondary btn-sm dr-uebertragen" data-doc="' + def.doc_type + '" aria-label="Einstellungen von ' + esc(label) + ' übertragen" data-tooltip="Drucker, Kopien, Beidseitig und Farbe auf andere Dokumente übertragen"><i class="bi bi-arrow-bar-down" aria-hidden="true"></i></button>'
        + '</div></div>';
    },

    werteAus(z) {
      const $z = $(z);
      const v = f => { const $f = $z.find('[data-feld="' + f + '"]'); return $f.length ? String($f.val() ?? '') : ''; };
      return { printer_id: v('printer_id'), format: v('format'), copies: v('copies'), duplex: v('duplex'), color_mode: v('color_mode') };
    },

    gespeichert(docType) {
      const def = KATALOG.finde(docType);
      const p = this.profilVon(docType);
      return {
        printer_id: p && p.printer_id ? String(p.printer_id) : '',
        format: def && def.formate ? (this.optionAus(p, 'format') || def.formate[0].value) : '',
        copies: String(p ? (p.copies || 1) : 1),
        duplex: p ? (String(p.duplex) === '1' ? 'long-edge' : String(p.duplex || '')) : '',
        color_mode: (p && p.color_mode) || 'blackwhite',
      };
    },

    /** Zeile mit dem gespeicherten Stand vergleichen und markieren; auch den Testknopf nachführen. */
    markiere(z, still) {
      if (!z) return;
      const doc = z.dataset.doc;
      const jetzt = this.werteAus(z);
      const alt = this.gespeichert(doc);
      const p = this.profilVon(doc);
      // ohne Profil und ohne Drucker zählt nur die Druckerwahl als Änderung
      const anders = p ? Object.keys(jetzt).some(k => jetzt[k] !== alt[k]) : !!jetzt.printer_id;
      if (anders) this.geaendert.add(doc); else this.geaendert.delete(doc);
      z.classList.toggle('dr-geaendert', anders);
      $(z).find('.dr-punkt, .dr-punkt-text').prop('hidden', !anders);
      const testGrund = !this.verbunden ? 'Testdruck braucht die Verbindung zu QZ Tray' : (!jetzt.printer_id ? 'Zuerst einen Drucker wählen' : '');
      const t = z.querySelector('.dr-test');
      if (t) { t.disabled = !!testGrund; t.dataset.tooltip = testGrund || 'Testdruck mit den Werten dieser Zeile'; }
      if (!still) this.zeigeAenderungen();
    },

    zeigeAenderungen() {
      const n = this.geaendert.size;
      $('#drUngespeichert').prop('hidden', !n);
      $('#drUngespeichertText').text(n === 1 ? '1 Zeile nicht gespeichert' : n + ' Zeilen nicht gespeichert');
      $('#btnVerwerfen').prop('hidden', !n);
      const fertig = this.profile.filter(p => p.printer_id).length;
      $('#drProfilFussText').text(n
        ? n + (n === 1 ? ' Änderung' : ' Änderungen') + ' noch nicht gespeichert.'
        : fertig + ' von ' + this.alleDokumente().length + ' Dokumenten haben einen Drucker.');
      this.zeigeSchnell();
    },

    /**
     * «Alle Dokumente einrichten»: steht über der Liste, solange Dokumente ohne Drucker sind und nichts ungespeichert ist.
     * Beim ersten Einrichten immer, danach («übrige») lässt es sich für diesen Browser ausblenden.
     */
    zeigeSchnell() {
      const $s = $('#drSchnell');
      const aktive = this.drucker.filter(d => Number(d.aktiv) === 1);
      const alle = this.alleDokumente();
      const offen = alle.filter(def => { const p = this.profilVon(def.doc_type); return !(p && p.printer_id); }).length;
      const erstes = offen === alle.length;
      let aus = false;
      try { aus = localStorage.getItem(SCHNELL_AUS) === '1'; } catch (e) { /* privat: egal */ }
      if (!aktive.length || !offen || this.geaendert.size || (!erstes && aus)) { $s.prop('hidden', true).empty(); return; }

      const wahl = aktive.length === 1
        ? '<span class="dr-schnell-drucker"><i class="bi bi-printer me-1" aria-hidden="true"></i>' + esc(this.druckerName(aktive[0])) + '</span>'
          + '<input type="hidden" id="drSchnellDrucker" value="' + esc(aktive[0].id) + '">'
        : '<select id="drSchnellDrucker" class="form-select form-select-sm" aria-label="Drucker für alle Dokumente ohne Drucker">'
          + aktive.map(d => '<option value="' + esc(d.id) + '">' + esc(this.druckerName(d)) + '</option>').join('') + '</select>';
      const text = erstes
        ? '<strong>Alle ' + alle.length + ' Dokumente auf einen Drucker legen</strong>'
          + 'Mit den üblichen Werten: 1 Kopie, Schwarzweiss, Faltblätter automatisch beidseitig. Einzelne Zeilen passt du danach an.'
        : '<strong>' + offen + (offen === 1 ? ' Dokument hat' : ' Dokumente haben') + ' noch keinen Drucker</strong>'
          + 'Auf einen Drucker legen, mit den üblichen Werten. Eingerichtete Zeilen bleiben, wie sie sind.';
      $s.html('<div class="dr-schnell-text">' + text + '</div><div class="dr-schnell-aktion">' + wahl
        + '<button type="button" class="btn btn-outline-primary btn-sm" id="drSchnellKnopf"><i class="bi bi-check2-all me-1" aria-hidden="true"></i>'
        + (erstes ? 'Alle einrichten' : 'Übrige einrichten') + '</button>'
        + (erstes ? '' : '<button type="button" class="btn btn-outline-secondary btn-sm" id="drSchnellAus" aria-label="Hinweis ausblenden" data-tooltip="Ausblenden: die übrigen Dokumente bleiben ohne Drucker"><i class="bi bi-x-lg" aria-hidden="true"></i></button>')
        + '</div>').prop('hidden', false);
    },

    /** Drucker in alle Zeilen ohne Drucker setzen; gespeichert wird wie immer erst mit «Speichern». */
    schnellEinrichten() {
      const id = String($('#drSchnellDrucker').val() || '');
      const d = this.drucker.find(x => String(x.id) === id);
      if (!d) return;
      const $leer = $('#profileMatrix .dr-zeile').filter((_, z) => !$(z).find('[data-feld="printer_id"]').val());
      if (!$leer.length) return;
      $leer.each((_, z) => {
        $(z).find('[data-feld="printer_id"]').val(id);
        z.classList.add('dr-kopiert');
        this.markiere(z, true);
      });
      // zugeklappte Gruppen mit neuen Zeilen öffnen: sichtbar, was sich ändert
      $leer.closest('[id^="drGruppe"]').each((_, g) => {
        const k = g.hidden ? document.querySelector('#profileMatrix .dr-gruppe-knopf[aria-controls="' + g.id + '"]') : null;
        if (k) this.klappe(k);
      });
      this.zeigeAenderungen();
      setTimeout(() => $('#profileMatrix .dr-kopiert').removeClass('dr-kopiert'), 1200);
      msvToast($leer.length + (($leer.length === 1) ? ' Dokument' : ' Dokumente') + ' auf «' + this.druckerName(d) + '» vorbereitet. Prüfen und «Speichern».', 'success');
      document.getElementById('btnSpeichern').focus();
    },

    klappe(knopf) {
      const offen = knopf.getAttribute('aria-expanded') === 'true';
      knopf.setAttribute('aria-expanded', String(!offen));
      document.getElementById(knopf.getAttribute('aria-controls')).hidden = offen;
      const zu = $('#profileMatrix .dr-gruppe-knopf[aria-expanded="false"]').map((_, b) => b.dataset.gruppe).get();
      try { localStorage.setItem(ZU_SCHLUESSEL, JSON.stringify(zu)); } catch (e) { /* privat: egal */ }
    },

    /** Klick auf einen Link, der die Seite verlässt, während Profile ungespeichert sind: Speichern / Verwerfen / Zurück. */
    async vorVerlassen(e) {
      if (!this.geaendert.size || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      const abmelden = e.target.closest && e.target.closest('[data-msv-logout]'); // Abmelden schickt ein Formular (header.inc.php)
      let ziel = null;
      if (!abmelden) {
        const a = e.target.closest && e.target.closest('a[href]');
        if (!a || a.hasAttribute('download') || (a.target && a.target !== '_self')) return;
        const href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) return;
        ziel = new URL(a.href, location.href);
        // nur ein Sprung zu einem Anker bleibt auf der Seite; derselbe Link ohne Anker (Menü «Drucksteuerung») lädt neu
        if (ziel.hash && ziel.pathname === location.pathname && ziel.search === location.search) return;
      }
      e.preventDefault();
      e.stopPropagation();
      const form = abmelden && document.getElementById('msvLogoutForm');
      await this.fragVerlassen(() => { if (form) form.submit(); else if (ziel) location.href = ziel.href; });
    },

    /** Neu laden per Taste (F5, Ctrl+R, Ctrl+Shift+R, Cmd+R): eigener Dialog. Den Knopf im Browser erreicht keine Seite. */
    vorNeuLaden(e) {
      if (!this.geaendert.size) return;
      const taste = e.key === 'F5' || ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'r' || e.key === 'R'));
      if (!taste) return;
      e.preventDefault();
      if (this.fragtVerlassen) return; // Taste gedrückt gehalten oder Dialog schon offen
      this.fragVerlassen(() => location.reload());
    },

    /** Speichern / Verwerfen / Zurück; danach weiter() nur, wenn nichts mehr ungespeichert ist oder verworfen wurde. */
    async fragVerlassen(weiter) {
      if (this.fragtVerlassen) return;
      this.fragtVerlassen = true;
      const n = this.geaendert.size;
      try {
        const wahl = await msvUngespeichert({ wer: n === 1 ? '1 Druckprofil' : n + ' Druckprofile' });
        if (wahl === 'zurueck') return;
        if (wahl === 'speichern') {
          await this.speichern();
          if (this.geaendert.size) return; // nicht gespeichert (Fehler oder Rückfrage abgebrochen): auf der Seite bleiben
        }
      } finally {
        this.fragtVerlassen = false;
      }
      this.verlassenOk = true;
      weiter();
    },

    verwerfen() {
      this.geaendert.clear();
      this.renderMatrix();
      msvToast('Änderungen verworfen', 'info');
    },

    async speichern() {
      const speichern = [];
      const entfernen = [];
      $('#profileMatrix .dr-zeile').each((_, z) => {
        const doc = z.dataset.doc;
        const w = this.werteAus(z);
        const p = this.profilVon(doc);
        if (!w.printer_id) {
          if (p) entfernen.push({ id: p.id, label: (KATALOG.finde(doc) || {}).label || doc });
          return;
        }
        const vorgabe = KATALOG.profilVorgabe(doc);
        const def = KATALOG.finde(doc);
        const f = KATALOG.format(def, w.format);
        const d = Object.assign(vorgabe, {
          printer_id: Number(w.printer_id),
          copies: Math.min(9, Math.max(1, parseInt(w.copies, 10) || 1)),
          paper_size: f.paper || 'A4',
          orientation: f.orient || 'portrait',
          color_mode: w.color_mode || 'blackwhite',
          duplex: w.duplex || '',
          optionen: def && def.formate ? JSON.stringify({ format: f.value }) : '{}',
        });
        if (p) d.id = p.id;
        speichern.push(d);
      });
      if (!speichern.length && !entfernen.length) { msvToast('Noch kein Dokument hat einen Drucker.', 'warning'); return; }
      if (entfernen.length) {
        const r = await msvConfirmDelete('Profile', {
          title: 'Profile entfernen?',
          confirmText: 'Entfernen und speichern',
          html: '<div class="text-start"><p class="mb-2">Diese Profile werden entfernt, weil kein Drucker gewählt ist:</p><ul class="ps-3 mb-0">'
            + entfernen.map(e => '<li>' + esc(e.label) + '</li>').join('') + '</ul></div>',
        });
        if (!r.isConfirmed) return;
      }
      const $k = $('#btnSpeichern').prop('disabled', true).attr('aria-busy', 'true');
      const erstesMal = !this.profile.some(p => p.printer_id) && !(this.log || []).some(j => j.status !== 'fehler');
      try {
        const r = await this.sende('drucksteuerung/profiles_api.php', { action: 'save_all', profiles: speichern, delete_ids: entfernen.map(e => e.id) });
        const teile = [];
        if (r.saved) teile.push(r.saved + ' gespeichert');
        if (r.deleted) teile.push(r.deleted + ' entfernt');
        // beim ersten Einrichten gleich zeigen, wie man es ausprobiert (der Knopf steht danach in der Schrittleiste)
        const probe = erstesMal && this.verbunden && speichern.length ? '. Ausprobieren: «Testseite drucken» oben oder das Drucker-Symbol einer Zeile.' : '';
        msvToast('Druckprofile: ' + (teile.join(', ') || 'gespeichert') + probe, 'success');
        this.geaendert.clear();
        await this.ladeAlles();
      } catch (err) {
        msvToast(err.message, 'error');
      } finally {
        $k.prop('disabled', false).removeAttr('aria-busy');
      }
    },

    /** Drucker, Kopien, Beidseitig und Farbe einer Zeile auf die Gruppe oder alle Dokumente übertragen. */
    async uebertrage(docType, alleDirekt) {
      const $q = $('#profileMatrix .dr-zeile[data-doc="' + docType + '"]');
      const w = this.werteAus($q[0]);
      if (!w.printer_id) { msvToast('Bitte zuerst in dieser Zeile einen Drucker wählen.', 'warning'); return; }
      const label = (KATALOG.finde(docType) || {}).label || docType;
      const gruppe = (KATALOG.gruppen.find(g => g.dokumente.some(d => d.doc_type === docType)) || {}).gruppe || '';
      let alle = !!alleDirekt;
      if (!alleDirekt) {
        const r = await msvSwal.fire({
          title: 'Einstellungen übertragen',
          html: 'Drucker, Kopien, Beidseitig und Farbe von «' + esc(label) + '» übernehmen für …<p class="small text-muted mt-2 mb-0">Das Format bleibt je Dokument. Danach «Speichern».</p>',
          showCancelButton: true, showDenyButton: true,
          confirmButtonText: 'Gruppe «' + esc(gruppe) + '»', denyButtonText: 'Alle Dokumente',
          customClass: Object.assign(msvSwalButtons('btn-primary'), { denyButton: 'btn btn-sm btn-outline-primary', popup: 'msv-swal msv-swal-drei' }),
        });
        if (r.isDismissed) return;
        alle = r.isDenied;
      }
      const $ziel = (alle ? $('#profileMatrix .dr-zeile') : $q.closest('[id^="drGruppe"]').find('.dr-zeile')).not($q);
      $ziel.each((_, z) => {
        ['printer_id', 'copies', 'duplex', 'color_mode'].forEach(f => {
          const $f = $(z).find('[data-feld="' + f + '"]');
          if ($f.length && $f.find('option[value="' + w[f] + '"]').length + ($f.is('input') ? 1 : 0)) $f.val(w[f]);
        });
        z.classList.add('dr-kopiert');
        this.markiere(z, true);
      });
      this.zeigeAenderungen();
      setTimeout(() => $('#profileMatrix .dr-kopiert').removeClass('dr-kopiert'), 1200);
      msvToast('Auf ' + $ziel.length + ' Dokumente übertragen. Jetzt «Speichern».', 'success');
    },

    async testdruck(docType) {
      const $z = $('#profileMatrix .dr-zeile[data-doc="' + docType + '"]');
      const w = this.werteAus($z[0]);
      const d = this.drucker.find(x => String(x.id) === w.printer_id);
      if (!this.verbunden || !d) return;
      const def = KATALOG.finde(docType);
      const label = (def && def.label) || docType;
      const f = KATALOG.format(def, w.format);
      const size = PAPIER[String(f.paper || 'A4').toUpperCase()] || PAPIER.A4;
      const copies = Math.min(9, Math.max(1, parseInt(w.copies, 10) || 1));
      const $k = $z.find('.dr-test').prop('disabled', true).attr('aria-busy', 'true');
      const params = new URLSearchParams({ doc_type: docType, label, printer: d.name, orientation: f.orient, paper: f.paper, copies, duplex: w.duplex, color: w.color_mode });
      try {
        // Testseite als PDF vom Server: gleicher Druckpfad wie die echten Dokumente
        const r = await fetch('drucksteuerung/test_pdf.php?' + params, { credentials: 'same-origin' });
        if (!r.ok) throw new Error('Die Testseite konnte nicht erstellt werden.');
        const blob = await r.blob();
        const base64 = await new Promise((ok, fehler) => {
          const rd = new FileReader();
          rd.onload = () => ok(String(rd.result).split(',')[1]);
          rd.onerror = fehler;
          rd.readAsDataURL(blob);
        });
        // orientation:null wie im Direktdruck: QZ erkennt die Ausrichtung aus dem PDF (explizit = doppelt gedreht)
        await this.pm.printPixel(d.name, [{ type: 'pdf', format: 'base64', data: base64 }], {
          size: { width: size[0], height: size[1] }, units: 'mm', orientation: null,
          copies, duplex: w.duplex || (def && def.broschuere ? 'short-edge' : false), colorType: w.color_mode,
          margins: { top: 0, right: 0, bottom: 0, left: 0 }, rasterize: false, jobName: 'Testdruck ' + label,
        });
        await this.pm.logJob(docType, d.name, 'Testdruck ' + f.text, 'gesendet', copies);
        msvToast('Testseite «' + label + '» (' + f.text + ') an ' + this.druckerName(d) + ' gesendet', 'success');
        this.ladeLog();
      } catch (err) {
        await this.pm.logJob(docType, d.name, 'Testdruck', 'fehler', copies, err && err.message ? err.message : String(err));
        msvToast('Testdruck nicht gesendet. Ist der Drucker eingeschaltet und QZ Tray verbunden?', 'error');
        this.ladeLog();
      } finally {
        $k.removeAttr('aria-busy');
        this.markiere($z[0]);
      }
    },

    // =================================================================== Protokoll
    ladeLog() {
      msvGet('drucksteuerung/print_log_api.php?limit=20', (res) => {
        const $b = $('#printLogBody');
        const liste = (res && res.data) || [];
        this.log = liste;
        this.zeigeAblauf();
        if (!liste.length) {
          $b.html('<div class="dr-leer"><i class="bi bi-clock-history" aria-hidden="true"></i><p><strong>Noch keine Druckaufträge aus diesem Browser.</strong></p>'
            + '<p>Hier stehen danach die letzten Direktdrucke und Testseiten, bei einem Fehler mit der Ursache.</p></div>');
          return;
        }
        $b.html(liste.map(j => {
          const zeit = new Date(String(j.erstellt_am).replace(' ', 'T')).toLocaleString('de-CH', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
          const fehler = j.status === 'fehler';
          const def = KATALOG.finde(j.doc_type);
          const status = fehler
            ? '<span class="ui-status fehler"><span class="ui-punkt" aria-hidden="true"></span>Fehler</span>'
            : '<span class="ui-status ok"><span class="ui-punkt" aria-hidden="true"></span>' + (j.status === 'erfolgreich' ? 'gedruckt' : 'an Drucker übergeben') + '</span>';
          return '<div class="dr-log-zeile">'
            + status
            + '<span class="dr-log-typ">' + esc((def && def.label) || j.doc_type || '–') + '</span>'
            + '<span class="dr-log-datei">' + esc(j.dateiname || '') + '</span>'
            + '<span class="dr-log-drucker">' + esc(j.printer_name || '–') + (Number(j.copies) > 1 ? ' · ' + esc(j.copies) + '×' : '') + '</span>'
            + '<span class="dr-log-zeit">' + esc(zeit) + '</span>'
            + (fehler && j.fehler_text ? '<span class="dr-log-fehler">' + esc(j.fehler_text) + '</span>' : '')
            + '</div>';
        }).join(''));
      }, {
        failMsg: 'Das Druckprotokoll konnte nicht geladen werden.',
        fail: () => {
          if (this.log === null) { this.log = []; this.zeigeAblauf(); }
          if ($('#printLogBody .dr-log-zeile').length === 0) $('#printLogBody').html('<div class="dr-leer text-danger">Das Druckprotokoll konnte nicht geladen werden.</div>');
        },
      });
    },
  };

  window.Druck = Druck;
  $(function () { if (document.getElementById('profileMatrix') && KATALOG) Druck.init(); });
})(jQuery);
