/**
 * msv-direktdruck.js — Direktdruck-Baustein (QZ Tray) für Admin-Seiten
 *
 * Einbinden (vor footer.inc.php):  <?php include 'partials/direktdruck_scripts.inc.php'; ?>
 * Das Partial lädt rsvp, sha-256, qz-tray, print-manager und dieses Modul; jQuery kommt aus dem Header.
 *
 * Deklarativ — ein Button pro PDF-Ausgabe, direkt neben dem bestehenden PDF-Button:
 *
 *   <button type="button" class="btn btn-outline-info btn-sm msv-druck"
 *           data-druck-doctype="kantirang"                   // Profil-Schlüssel (Drucksteuerung, print_profiles.doc_type)
 *           data-druck-label="Kantonalstich Rangliste"       // Anzeigename fürs Tooltip «Kein Druckprofil …»
 *           data-druck-url="kantirang/generate_pdf.php?year=2026"   // GET → PDF ODER JSON mit pdf_link/pdf_url/url
 *           data-druck-job="Kantonalstich Rangliste 2026"    // Name im Druckspooler / Druckprotokoll
 *           data-druck-orientation="landscape"               // optional: übersteuert die Ausrichtung des Profils
 *           data-druck-linkprefix="kantirang/">              // optional: Präfix für relative Links aus der JSON-Antwort
 *     <i class="bi bi-printer"></i>
 *   </button>
 *
 * Dynamische URLs (Jahr aus einem Select, POST mit CSRF …): statt data-druck-url einen Resolver registrieren:
 *   MsvDruck.resolve('kantirang', btn => ({ url: 'kantirang/generate_pdf.php?year=' + jahr(), jobName: 'Rangliste ' + jahr() }));
 * Rückgabe: { url, jobName, orientation?, paper?, duplex?, linkPrefix?, method?, body?, headers? } — oder null zum Abbrechen
 * (Meldung dann selbst per msvToast). Der Resolver darf ein Promise liefern.
 *
 * Verhalten: Beim Laden verbindet das Modul QZ Tray im Hintergrund und lädt alle Druckprofile des
 * Benutzers/Arbeitsplatzes (eine Anfrage). Ohne QZ-Verbindung sind die Drucker AUSGEBLENDET (CSS: .msv-druck
 * ohne .msv-druck-an) und die Kopf-Card zeigt einen Hinweis «Direktdruck nicht verbunden». Mit Verbindung,
 * aber ohne Profil für den doc_type fragt der erste Klick einmal nach dem Drucker (einrichten(), seit 1.68.52):
 * Drucker und Profil werden für diesen Browser angelegt, Format nach js/msv-druck-dokumente.js, dann wird gedruckt.
 * War QZ beim letzten Seitenaufruf verbunden, stehen die Drucker während des Verbindens schon da (gesperrt),
 * damit nichts springt. Nach dynamischem Rendern: MsvDruck.refresh().
 * Seiten mit eigener Druck-UI können MsvDruck.onChange = fn setzen (wird nach jedem refresh() aufgerufen).
 *
 * Druckoptionen aus dem Profil: Drucker, Kopien, Farbe, Duplex ('' | 'long-edge' | 'short-edge'),
 * Papier (paper_size) und Ausrichtung (orientation). QZ liest die Seitengrösse NICHT aus dem PDF,
 * darum wird size/units immer explizit gesetzt; rasterize:false (Vektordruck). Protokoll: print_jobs, Status
 * «gesendet» (= an den Spooler übergeben) bzw. «fehler».
 */
(function (global) {
  'use strict';

  const PAPIER = { A3: [297, 420], A4: [210, 297], A5: [148, 210], LETTER: [216, 279] };

  const MsvDruck = {
    pm: null,
    verbunden: false,
    profile: {},            // doc_type -> Profil (aus drucksteuerung/profiles_api.php)
    resolvers: {},          // doc_type -> fn(btn) => Ziel-Objekt (siehe Kopf)
    onChange: null,         // optionaler Callback nach jedem refresh()
    _init: false,
    _laufend: new Set(),
    _verbinde: false,       // Verbindungsversuch läuft noch
    _zuletzt: false,        // war QZ beim letzten Seitenaufruf verbunden? (localStorage)

    // -------------------------------------------------------------- Init / Status
    async init() {
      if (this._init) return;
      this._init = true;
      try { this._zuletzt = localStorage.getItem('msvDruckQz') === '1'; } catch (e) { this._zuletzt = false; }
      document.addEventListener('click', ev => this._onClick(ev));

      if (typeof PrintManager === 'undefined' || typeof qz === 'undefined') {
        console.warn('[MsvDruck] QZ-Skripte fehlen – Direktdruck deaktiviert');
        this.refresh();
        return;
      }
      this._verbinde = true;
      this.refresh();
      this.pm = new PrintManager();
      this.pm.onStatusChange = c => { this.verbunden = !!c; this._merke(); this.refresh(); };

      try {
        await this.pm.connect();
        this.verbunden = true;
      } catch (err) {
        this.verbunden = false;
        console.warn('[MsvDruck] QZ Tray nicht verfügbar:', err && err.message ? err.message : err);
      }
      this._merke();
      await this._ladeProfile();
      this._verbinde = false;
      this.refresh();
    },

    _merke() {
      try { localStorage.setItem('msvDruckQz', this.verbunden ? '1' : '0'); } catch (e) { /* privat/gesperrt: egal */ }
    },

    async _ladeProfile() {
      try {
        const res = await $.getJSON('drucksteuerung/profiles_api.php');
        this.profile = {};
        (res && res.success ? res.data || [] : []).forEach(p => {
          if (p.printer_name && Number(p.aktiv ?? 1) === 1) this.profile[p.doc_type] = p;
        });
      } catch (err) {
        console.error('[MsvDruck] Druckprofile konnten nicht geladen werden:', err);
        if (typeof msvToast === 'function') msvToast('Druckprofile konnten nicht geladen werden – Direktdruck nicht verfügbar', 'error');
      }
    },

    /** Profil für einen doc_type (oder null). */
    profil(docType) { return this.profile[docType] || null; },

    /** Druckbereit für diesen doc_type? Mit Verbindung immer: ohne Profil fragt print() einmal nach dem Drucker. */
    bereit(docType) { return !!(this.pm && this.verbunden); },

    /**
     * Sollen Drucker-Knöpfe überhaupt sichtbar sein? Ja mit QZ-Verbindung – und während des Verbindens,
     * wenn QZ beim letzten Aufruf lief. Seiten mit eigener Druck-UI (onChange) blenden ihre Knöpfe danach aus.
     */
    sichtbar() { return !!(this.pm && this.verbunden) || (this._verbinde && this._zuletzt); },

    /** Resolver für dynamische URLs registrieren. */
    resolve(docType, fn) { this.resolvers[docType] = fn; this.refresh(); },

    /** Grund, warum (noch) nicht gedruckt werden kann – oder '' wenn bereit. */
    grund(docType, label) {
      if (!this.pm) return 'QZ Tray nicht verfügbar';
      if (!this.verbunden) return 'QZ Tray nicht verbunden';
      return '';
    },

    /**
     * Ausrichtung aus dem Profil ('portrait' | 'landscape'), sonst fallback.
     * Von den Seiten genutzt, um dem Generator dieselbe Ausrichtung mitzugeben (Download UND Direktdruck).
     */
    orientierung(docType, fallback = 'portrait') {
      const p = this.profil(docType);
      const o = p && String(p.orientation || '').toLowerCase();
      return o === 'landscape' || o === 'portrait' ? o : fallback;
    },

    /** Kurzbeschreibung des Profils fürs Tooltip. */
    profilText(docType) {
      const p = this.profil(docType);
      if (!p) return 'Drucker beim ersten Druck wählen';
      const farbe = { color: ', Farbe', grayscale: ', Graustufen' }[String(p.color_mode || '').toLowerCase()] || '';
      return p.printer_name + (String(p.orientation).toLowerCase() === 'landscape' ? ', quer' : '')
        + (p.duplex ? ', beidseitig' : '') + farbe + (Number(p.copies) > 1 ? ', ' + p.copies + '×' : '');
    },

    /**
     * Alle .msv-druck-Buttons anhand Verbindung/Profil zeigen, sperren oder freigeben (Tooltip mit Grund).
     * Ohne QZ-Verbindung: Drucker ausblenden und einen Hinweis in der Kopf-Card zeigen.
     */
    refresh() {
      const mitQz = !!(this.pm && this.verbunden);
      const zeigen = this.sichtbar();
      const knoepfe = document.querySelectorAll('.msv-druck[data-druck-doctype]');
      knoepfe.forEach(btn => {
        const dt = btn.dataset.druckDoctype;
        const grund = this._verbinde && !mitQz ? 'Verbinde mit QZ Tray …' : this.grund(dt, btn.dataset.druckLabel);
        btn.classList.toggle('msv-druck-an', zeigen);
        btn.disabled = !!grund || btn.dataset.druckBlocked === '1' || this._laufend.has(btn);
        if (!btn.dataset.druckKeepTooltip) {
          btn.dataset.tooltip = grund || ('Direktdruck: ' + this.profilText(dt));
        }
      });
      this._hinweis((knoepfe.length > 0 || document.querySelector('[data-druck-eigen]')) && !zeigen && !this._verbinde);
      if (typeof this.onChange === 'function') {
        try { this.onChange(); } catch (e) { console.error('[MsvDruck] onChange:', e); }
      }
    },

    /**
     * Hinweis «QZ Tray starten» in der Kopf-Card (einmal pro Seite) ein-/ausblenden. Seit 1.68.54 ein Knopf:
     * Klick öffnet die Anleitung mit «Neu verbinden» und dem Weg zum Setup (Drucksteuerung).
     */
    _hinweis(an) {
      let chip = document.querySelector('.msv-druck-chip');
      if (!an) { if (chip) chip.hidden = true; return; }
      if (!chip) {
        const ziel = document.querySelector('.msv-kopf .page-actions') || document.querySelector('.msv-kopf .msv-kopf-titel');
        if (!ziel) return;
        chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'ui-chip msv-druck-chip';
        chip.dataset.tooltip = 'Direktdruck ist aus: QZ Tray läuft auf diesem Computer nicht. Klicken für die Anleitung. '
          + 'Ohne QZ Tray: Dokument herunterladen und wie gewohnt drucken.';
        chip.innerHTML = '<i class="bi bi-exclamation-circle" aria-hidden="true"></i>Direktdruck: QZ Tray starten';
        chip.addEventListener('click', () => this.anleitung());
        ziel.appendChild(chip);
      }
      chip.hidden = false;
    },

    /** Anleitung, wenn QZ Tray nicht erreichbar ist: starten, neu verbinden, sonst Setup in der Drucksteuerung. */
    async anleitung() {
      if (typeof msvSwal === 'undefined') { this._toast('QZ Tray auf diesem Computer starten, dann die Seite neu laden.', 'warning'); return; }
      const r = await msvSwal.fire({
        title: 'QZ Tray starten',
        html: '<div class="text-start"><p class="mb-2">Für den Direktdruck muss auf diesem Computer das Programm <strong>QZ Tray</strong> laufen. Im Moment antwortet es nicht.</p>'
          + '<ol class="ps-3 mb-2"><li class="mb-1">QZ Tray starten: im Windows-Startmenü «QZ Tray» suchen und öffnen. Es läuft danach unten rechts in der Taskleiste.</li>'
          + '<li>Hier <strong>Neu verbinden</strong> klicken.</li></ol>'
          + '<p class="small mb-1"><strong>Noch nicht installiert?</strong> Das Setup mit Zertifikat gibt es unter Einstellungen → Drucksteuerung.</p>'
          + '<p class="small text-muted mb-0">Ohne QZ Tray: das Dokument herunterladen und wie gewohnt drucken.</p></div>',
        showCancelButton: true, showDenyButton: true,
        confirmButtonText: 'Neu verbinden', denyButtonText: 'Zur Drucksteuerung', cancelButtonText: 'Schliessen',
        customClass: Object.assign(typeof msvSwalButtons === 'function' ? msvSwalButtons('btn-primary') : {},
          { denyButton: 'btn btn-sm btn-outline-secondary', popup: 'msv-swal msv-swal-drei' }),
      });
      if (r.isConfirmed) this.neuVerbinden();
      else if (r.isDenied) window.location.href = 'drucksteuerung.php';
    },

    /** Verbindung zu QZ Tray nochmals versuchen (nach dem Starten), ohne die Seite neu zu laden. */
    async neuVerbinden() {
      if (!this.pm) { this._toast('Direktdruck ist auf dieser Seite nicht verfügbar. Bitte die Seite neu laden.', 'warning'); return; }
      this._verbinde = true;
      this.refresh();
      try {
        await this.pm.connect();
        this.verbunden = true;
      } catch (err) {
        this.verbunden = false;
        console.warn('[MsvDruck] QZ Tray nicht verfügbar:', err && err.message ? err.message : err);
      }
      this._merke();
      await this._ladeProfile();
      this._verbinde = false;
      this.refresh();
      this._toast(this.verbunden ? 'Direktdruck verbunden' : 'QZ Tray antwortet noch nicht. Ist es gestartet? Beim ersten Start kann es einige Sekunden dauern.',
        this.verbunden ? 'success' : 'warning');
    },

    // -------------------------------------------------------------- Klick
    async _onClick(ev) {
      const btn = ev.target.closest('.msv-druck[data-druck-doctype]');
      if (!btn || btn.disabled) return;
      ev.preventDefault();
      const dt = btn.dataset.druckDoctype;
      if (!this.bereit(dt)) { this._toast(this.grund(dt, btn.dataset.druckLabel), 'warning'); return; }
      if (this._laufend.has(btn)) return;

      let ziel;
      try {
        const r = this.resolvers[dt];
        ziel = r ? await r(btn) : {
          url: btn.dataset.druckUrl,
          jobName: btn.dataset.druckJob,
          linkPrefix: btn.dataset.druckLinkprefix,
        };
      } catch (err) {
        this._toast('Druck abgebrochen: ' + (err && err.message ? err.message : err), 'error');
        return;
      }
      if (!ziel || !ziel.url) return; // Resolver hat abgebrochen (z.B. Validierung), Meldung kommt von dort

      this._laufend.add(btn);
      const orig = btn.innerHTML;
      // Spinner mit Text für Screenreader; aria-busy, solange gedruckt wird
      btn.disabled = true; btn.setAttribute('aria-busy', 'true');
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span class="visually-hidden">Wird gedruckt …</span>';
      try {
        await this.print({
          docType: dt,
          url: ziel.url,
          jobName: ziel.jobName || btn.dataset.druckJob || dt,
          orientation: ziel.orientation || btn.dataset.druckOrientation || null,
          paper: ziel.paper || btn.dataset.druckPaper || null,
          duplex: ziel.duplex || btn.dataset.druckDuplex || null,
          linkPrefix: ziel.linkPrefix || btn.dataset.druckLinkprefix || '',
          request: { method: ziel.method, body: ziel.body, headers: ziel.headers },
        });
      } finally {
        this._laufend.delete(btn);
        btn.removeAttribute('aria-busy');
        btn.innerHTML = orig;
        this.refresh();
      }
    },

    // -------------------------------------------------------------- Drucker beim ersten Druck wählen
    /**
     * Für einen doc_type ohne Profil einmal nach dem Drucker fragen und Drucker + Profil für diesen Browser
     * anlegen (printers_api / profiles_api, gleiche Arbeitsplatz-Kennung wie die Drucksteuerung).
     * Vorschlag: der Drucker, den die meisten Profile schon nutzen, sonst der Standarddrucker des Systems.
     * Format, Kopien und Farbe kommen aus js/msv-druck-dokumente.js. Rückgabe: Profil oder null (abgebrochen).
     */
    async einrichten(docType, label) {
      if (!this.pm || !this.verbunden) return null;
      if (this._einrichtung) return this._einrichtung; // Doppelklick: dieselbe Frage nicht zweimal
      this._einrichtung = this._einrichtenAblauf(docType, label);
      try { return await this._einrichtung; } finally { this._einrichtung = null; }
    },

    async _einrichtenAblauf(docType, label) {
      const katalog = global.MsvDruckDokumente || null;
      const def = katalog ? katalog.finde(docType) : null;
      const name = (def && def.label) || label || docType;
      if (typeof msvSwal === 'undefined') {
        this._toast('Für «' + name + '» ist noch kein Drucker festgelegt (Einstellungen → Drucksteuerung).', 'warning');
        return null;
      }
      let system = [];
      try { system = await this.pm.listPrinters(); } catch (e) { system = []; }
      if (!system || !system.length) {
        this._toast('QZ Tray findet auf diesem Computer keinen Drucker. Bitte den Drucker in Windows einrichten und nochmals versuchen.', 'warning');
        return null;
      }
      const zaehler = {};
      Object.values(this.profile).forEach(p => { zaehler[p.printer_name] = (zaehler[p.printer_name] || 0) + 1; });
      let vorschlag = Object.keys(zaehler).sort((a, b) => zaehler[b] - zaehler[a]).find(n => system.includes(n)) || '';
      if (!vorschlag) { try { vorschlag = await qz.printers.getDefault(); } catch (e) { vorschlag = ''; } }
      const esc = typeof msvEsc === 'function' ? msvEsc : (s => String(s));
      const optionen = system.map(s => '<option value="' + esc(s) + '"' + (s === vorschlag ? ' selected' : '') + '>' + esc(s) + '</option>').join('');

      const r = await msvSwal.fire({
        title: 'Drucker wählen',
        html: '<div class="text-start"><p class="mb-2">Für «' + esc(name) + '» ist auf diesem Computer noch kein Drucker festgelegt.</p>'
          + '<label class="form-label small fw-semibold mb-1" for="msvDruckWahl">Drucker</label>'
          + '<select id="msvDruckWahl" class="form-select form-select-sm">' + optionen + '</select>'
          + '<p class="small text-muted mt-2 mb-0">Wird für diesen Browser gemerkt. Kopien, Farbe und beidseitig lassen sich unter Einstellungen → Drucksteuerung ändern.</p></div>',
        showCancelButton: true,
        confirmButtonText: 'Drucken',
        customClass: Object.assign(typeof msvSwalButtons === 'function' ? msvSwalButtons('btn-primary') : {}, { popup: 'msv-swal' }),
        preConfirm: () => (document.getElementById('msvDruckWahl') || {}).value || '',
      });
      if (!r.isConfirmed || !r.value) return null;
      const wahl = r.value;

      const csrf = (document.querySelector('[name="csrf_token"]') || {}).value || '';
      const sende = async (url, body) => {
        const res = await fetch(url, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify(body),
        });
        let j = {};
        try { j = await res.json(); } catch (e) { j = {}; }
        if (!res.ok || !j.success) throw new Error(j.message || 'Speichern fehlgeschlagen');
        return j;
      };
      const lies = async (url) => {
        const res = await fetch(url, { credentials: 'same-origin' });
        const j = await res.json();
        if (!j || !j.success) throw new Error((j && j.message) || 'Laden fehlgeschlagen');
        return j.data || [];
      };

      try {
        // Drucker dieses Browsers: vorhandenen nehmen (ggf. wieder aktivieren), sonst anlegen
        const drucker = await lies('drucksteuerung/printers_api.php');
        let d = drucker.find(x => x.name === wahl);
        let druckerId;
        if (d) {
          druckerId = Number(d.id);
          if (Number(d.aktiv) !== 1) {
            await sende('drucksteuerung/printers_api.php', { action: 'update', id: druckerId, anzeigename: d.anzeigename || d.name,
              typ: d.typ || 'laser', beschreibung: d.beschreibung || '', ist_standard: d.ist_standard || 0, aktiv: 1 });
          }
        } else {
          druckerId = Number((await sende('drucksteuerung/printers_api.php', { action: 'add', name: wahl, anzeigename: wahl })).id);
        }
        // Profil: ein vorhandenes ohne Drucker (Drucker gelöscht) weiterverwenden, sonst neu
        const profile = await lies('drucksteuerung/profiles_api.php');
        const alt = profile.find(x => x.doc_type === docType);
        const neu = Object.assign(katalog ? katalog.profilVorgabe(docType) : { doc_type: docType, anzeigename: name }, { printer_id: druckerId });
        if (alt) neu.id = alt.id;
        await sende('drucksteuerung/profiles_api.php', { action: 'save_all', profiles: [neu], delete_ids: [] });
      } catch (err) {
        this._toast('Der Drucker konnte nicht gespeichert werden: ' + (err && err.message ? err.message : err), 'error');
        return null;
      }
      await this._ladeProfile();
      this.refresh();
      const p = this.profil(docType);
      if (!p) this._toast('Das Profil für «' + name + '» ist gespeichert, liess sich aber nicht laden. Bitte die Seite neu laden.', 'warning');
      return p; // die Meldung «… an Drucker gesendet» kommt gleich danach aus print()
    },

    // -------------------------------------------------------------- Drucken
    /**
     * PDF von url holen und über das Profil des docType drucken.
     * url darf direkt ein PDF liefern oder JSON mit einem Link (pdf_link | pdf_url | url | file | link).
     * request: { method, body, headers } für POST-Endpunkte (z.B. CSRF-geschützte Exporte).
     */
    async print({ docType, url = null, blob = null, jobName, orientation = null, paper = null, duplex = null, linkPrefix = '', request = {} }) {
      let p = this.profil(docType);
      if (!p) {
        p = await this.einrichten(docType);
        if (!p) return false; // abgebrochen oder nicht möglich (Meldung kam schon)
      }
      const copies = parseInt(p.copies, 10) || 1;
      const name = jobName || docType;
      try {
        // blob: bereits geladenes PDF (z.B. Sammel-PDF mit Zusatz-Headern), sonst von url holen
        if (!blob) blob = await this._holePdf(url, request || {}, linkPrefix || '');
        const base64 = await this._blobZuBase64(blob);

        const papier = PAPIER[String(paper || p.paper_size || 'A4').toUpperCase()] || PAPIER.A4;
        const katalogDef = global.MsvDruckDokumente ? global.MsvDruckDokumente.finde(docType) : null;
        const zumFalten = !!(katalogDef && katalogDef.broschuere);

        // Ausrichtung NICHT explizit setzen: QZ Tray erkennt sie für PDFs aus der Seitengrösse selbst
        // (orientation:null). Mit explizitem 'landscape' wurde die Querseite zusätzlich gedreht und auf
        // ~70 % verkleinert gedruckt (Endschiessen-Standblatt, 16.09.2026). Papier immer in Hochformat-
        // Massen angeben (A4 = 210×297), QZ dreht die Fläche passend zur erkannten Ausrichtung.
        // Der Parameter orientation bleibt für die Generatoren (Seitenformat) und den Job-Namen relevant.
        await this.pm.printPixel(
          p.printer_name,
          [{ type: 'pdf', format: 'base64', data: base64 }],
          {
            copies,
            orientation: null,
            size: { width: papier[0], height: papier[1] },
            units: 'mm',
            margins: { top: 0, right: 0, bottom: 0, left: 0 },
            colorType: p.color_mode || 'blackwhite',
            // Übersteuerung der Seite vor Profil; Blätter zum Falten (Katalog: broschuere) ohne Angabe über die kurze Kante
            duplex: duplex || p.duplex || (zumFalten ? 'short-edge' : false),
            rasterize: false,
            jobName: name,
          }
        );
        await this.pm.logJob(docType, p.printer_name, name, 'gesendet', copies);
        this._toast('«' + name + '» an ' + p.printer_name + ' gesendet', 'success');
        return true;
      } catch (err) {
        // Generator ohne Daten (msvAusgabeLeer): nichts drucken, nur Hinweis – kein Eintrag «fehler» im Protokoll
        if (err && err.leer) { this._toast(err.message, 'warning'); return false; }
        // Nach Hinweisen des Generators bewusst nicht gedruckt: kein Fehler
        if (err && err.abgebrochen) return false;
        console.error('[MsvDruck] Druckfehler:', err);
        const msg = err && err.message ? err.message : String(err);
        if (this.pm) await this.pm.logJob(docType, p.printer_name, name, 'fehler', copies, msg);
        this._toast(err && err.server ? msg : 'Druckfehler: ' + msg, 'error'); // Servermeldungen ohne Vorspann
        return false;
      }
    },

    /**
     * Link aus einer JSON-Antwort für die aktuelle Seite auflösen.
     * Generatoren liefern uneinheitlich: absolut (/inc/…/dat/x.pdf), relativ zu inc/ (modul/dat/x.pdf),
     * relativ zum Modul (dat/x.pdf → linkPrefix) oder mit «inc/»-Präfix (inc/modul/dat/x.pdf).
     */
    _normalisiereLink(link, linkPrefix) {
      let l = String(link);
      if (/^(https?:)?\/\//i.test(l) || l.startsWith('/')) return l;      // absolut
      const unterInc = /\/inc(\/|$)/.test(window.location.pathname);
      if (l.startsWith('inc/') && unterInc) l = l.slice(4);                 // «inc/…» aus /inc/ heraus
      else if (linkPrefix && !l.startsWith(linkPrefix)) l = linkPrefix + l; // modul-relativ («dat/…»)
      return l;
    },

    /** PDF laden; JSON-Antworten mit Link werden aufgelöst, alles andere als Fehler gemeldet. */
    async _holePdf(url, request, linkPrefix, tiefe = 0) {
      const opts = { credentials: 'same-origin' };
      if (tiefe === 0 && request && request.method) {
        opts.method = request.method;
        if (request.body !== undefined) opts.body = request.body;
        if (request.headers) opts.headers = request.headers;
      }
      const r = await fetch(url, opts);
      const ct = (r.headers.get('Content-Type') || '').toLowerCase();
      if (!r.ok) {
        let text = '';
        try {
          if (ct.includes('json')) { const jf = JSON.parse(await r.text()); text = jf.message || jf.error || ''; }
          else text = (await r.text()).replace(/<[^>]+>/g, ' ').trim().slice(0, 200);
        } catch (e) { /* egal */ }
        const e = new Error(text || ('HTTP ' + r.status));
        e.server = !!text && ct.includes('json'); // Klartext des Generators
        throw e;
      }
      if (ct.includes('json')) {
        if (tiefe > 0) throw new Error('Endpunkt liefert kein PDF');
        const j = await r.json();
        if (j && j.leer) { const e = new Error(j.message || 'Keine Daten für diese Auswahl'); e.leer = true; throw e; }
        if (j && j.success === false) {
          const e = new Error(j.message || j.error || 'PDF-Erzeugung fehlgeschlagen');
          e.server = !!(j.message || j.error);
          throw e;
        }
        // Hinweise des Generators (z.B. Absendenbuch mit leeren Abschnitten): vor dem Druck nachfragen
        if (j && Array.isArray(j.hinweise) && j.hinweise.length && typeof msvConfirm === 'function') {
          const liste = j.hinweise.map(h => '<li>' + msvEsc(h) + '</li>').join('');
          const antwort = await msvConfirm('<div class="text-start"><p class="mb-2">Das Dokument ist erstellt, hat aber '
            + (j.hinweise.length === 1 ? 'einen Hinweis' : j.hinweise.length + ' Hinweise') + ':</p><ul class="mb-0">' + liste + '</ul></div>',
            'Trotzdem drucken?', 'Ja, drucken', 'Nicht drucken');
          if (!antwort.isConfirmed) { const e = new Error('Druck abgebrochen'); e.abgebrochen = true; throw e; }
        }
        const link = j && (j.pdf_link || j.pdf_url || j.pdf || j.url || j.file || j.download_url || j.link);
        if (!link) throw new Error(j && j.error ? String(j.error) : 'Antwort enthält keinen PDF-Link');
        return this._holePdf(this._normalisiereLink(link, linkPrefix), {}, linkPrefix, tiefe + 1);
      }
      const blob = await r.blob();
      const kopf = await blob.slice(0, 5).text();
      if (!kopf.startsWith('%PDF')) {
        throw new Error(ct.includes('html') ? 'Server lieferte HTML statt PDF (Login/Fehlerseite?)' : 'Antwort ist kein PDF');
      }
      return blob;
    },

    _blobZuBase64(blob) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result).split(',')[1]);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
    },

    _toast(msg, typ) {
      if (typeof msvToast === 'function') msvToast(msg, typ);
      else if (typ === 'error') alert(msg);
      else console.log('[MsvDruck]', msg);
    },
  };

  global.MsvDruck = MsvDruck;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => MsvDruck.init());
  else MsvDruck.init();
})(window);
