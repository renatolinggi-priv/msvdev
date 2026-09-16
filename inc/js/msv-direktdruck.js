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
 * Benutzers/Arbeitsplatzes (eine Anfrage). Buttons bleiben deaktiviert, solange QZ nicht läuft oder für den
 * doc_type kein Profil existiert; der Tooltip nennt den Grund. Nach dynamischem Rendern: MsvDruck.refresh().
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

    // -------------------------------------------------------------- Init / Status
    async init() {
      if (this._init) return;
      this._init = true;
      document.addEventListener('click', ev => this._onClick(ev));
      this.refresh();

      if (typeof PrintManager === 'undefined' || typeof qz === 'undefined') {
        console.warn('[MsvDruck] QZ-Skripte fehlen – Direktdruck deaktiviert');
        return;
      }
      this.pm = new PrintManager();
      this.pm.onStatusChange = c => { this.verbunden = !!c; this.refresh(); };

      try {
        await this.pm.connect();
        this.verbunden = true;
      } catch (err) {
        this.verbunden = false;
        console.warn('[MsvDruck] QZ Tray nicht verfügbar:', err && err.message ? err.message : err);
      }
      await this._ladeProfile();
      this.refresh();
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
      }
    },

    /** Profil für einen doc_type (oder null). */
    profil(docType) { return this.profile[docType] || null; },

    /** Druckbereit für diesen doc_type? */
    bereit(docType) { return !!(this.pm && this.verbunden && this.profil(docType)); },

    /** Resolver für dynamische URLs registrieren. */
    resolve(docType, fn) { this.resolvers[docType] = fn; this.refresh(); },

    /** Grund, warum (noch) nicht gedruckt werden kann – oder '' wenn bereit. */
    grund(docType, label) {
      if (!this.pm) return 'QZ Tray nicht verfügbar';
      if (!this.verbunden) return 'QZ Tray nicht verbunden';
      if (!this.profil(docType)) return 'Kein Druckprofil «' + (label || docType) + '» (Drucksteuerung)';
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
      if (!p) return '';
      const farbe = { color: ', Farbe', grayscale: ', Graustufen' }[String(p.color_mode || '').toLowerCase()] || '';
      return p.printer_name + (String(p.orientation).toLowerCase() === 'landscape' ? ', quer' : '')
        + (p.duplex ? ', beidseitig' : '') + farbe + (Number(p.copies) > 1 ? ', ' + p.copies + '×' : '');
    },

    /** Alle .msv-druck-Buttons anhand Verbindung/Profil aktivieren bzw. deaktivieren (Tooltip mit Grund). */
    refresh() {
      document.querySelectorAll('.msv-druck[data-druck-doctype]').forEach(btn => {
        const dt = btn.dataset.druckDoctype;
        const grund = this.grund(dt, btn.dataset.druckLabel);
        btn.disabled = !!grund || btn.dataset.druckBlocked === '1' || this._laufend.has(btn);
        if (!btn.dataset.druckKeepTooltip) {
          btn.dataset.tooltip = grund || ('Direktdruck: ' + this.profilText(dt));
        }
      });
      if (typeof this.onChange === 'function') {
        try { this.onChange(); } catch (e) { console.error('[MsvDruck] onChange:', e); }
      }
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
      btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
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
        btn.innerHTML = orig;
        this.refresh();
      }
    },

    // -------------------------------------------------------------- Drucken
    /**
     * PDF von url holen und über das Profil des docType drucken.
     * url darf direkt ein PDF liefern oder JSON mit einem Link (pdf_link | pdf_url | url | file | link).
     * request: { method, body, headers } für POST-Endpunkte (z.B. CSRF-geschützte Exporte).
     */
    async print({ docType, url = null, blob = null, jobName, orientation = null, paper = null, duplex = null, linkPrefix = '', request = {} }) {
      const p = this.profil(docType);
      if (!p) throw new Error('Kein Druckprofil für ' + docType);
      const copies = parseInt(p.copies, 10) || 1;
      const name = jobName || docType;
      try {
        // blob: bereits geladenes PDF (z.B. Sammel-PDF mit Zusatz-Headern), sonst von url holen
        if (!blob) blob = await this._holePdf(url, request || {}, linkPrefix || '');
        const base64 = await this._blobZuBase64(blob);

        const papier = PAPIER[String(paper || p.paper_size || 'A4').toUpperCase()] || PAPIER.A4;

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
            duplex: duplex || p.duplex || false, // Übersteuerung (z.B. Broschüre) vor Profil
            rasterize: false,
            jobName: name,
          }
        );
        await this.pm.logJob(docType, p.printer_name, name, 'gesendet', copies);
        this._toast('«' + name + '» an ' + p.printer_name + ' gesendet', 'success');
        return true;
      } catch (err) {
        console.error('[MsvDruck] Druckfehler:', err);
        const msg = err && err.message ? err.message : String(err);
        if (this.pm) await this.pm.logJob(docType, p.printer_name, name, 'fehler', copies, msg);
        this._toast('Druckfehler: ' + msg, 'error');
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
        try { text = ct.includes('json') ? (JSON.parse(await r.text()).message || '') : (await r.text()).replace(/<[^>]+>/g, ' ').trim().slice(0, 200); } catch (e) { /* egal */ }
        throw new Error(text || ('HTTP ' + r.status));
      }
      if (ct.includes('json')) {
        if (tiefe > 0) throw new Error('Endpunkt liefert kein PDF');
        const j = await r.json();
        if (j && j.success === false) throw new Error(j.message || j.error || 'PDF-Erzeugung fehlgeschlagen');
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
