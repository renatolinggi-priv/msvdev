/**
 * app-drucksteuerung.js — Drucksteuerung-Modul (MSV)
 *
 * Eigenstaendiges Modul (Druck-Objekt), nicht App-abhaengig.
 * Nutzt PrintManager, msvToast/msvError fuer Feedback.
 */

// Format-Auswahl fuer Ranglisten: die Wahl landet in print_profiles.orientation (+ optionen.format) und wird
// vom Direktdruck und von den PDF-Buttons der Seiten als ?orientation= an den Generator weitergegeben
// (inc/pdf/pdf_orientation.inc.php). Erster Eintrag = Vorgabe = bisheriges Format des Generators.
const FORMAT_A4_HOCH = [
    { value: 'A4P', label: 'A4 Portrait',  paper: 'A4', orient: 'portrait' },
    { value: 'A4L', label: 'A4 Landscape', paper: 'A4', orient: 'landscape' }
];
const FORMAT_A4_QUER = [FORMAT_A4_HOCH[1], FORMAT_A4_HOCH[0]];

const Druck = {

    pm: null,
    printers: [],
    profiles: [],
    systemPrinters: [],

    // ============================================================
    //  PROFIL-DEFINITIONEN (wird spaeter pro Seite erweitert)
    // ============================================================
    _profileDefinitions: [
        // doc_type = Schluessel in print_profiles; die Seiten nutzen ihn ueber js/msv-direktdruck.js
        // (data-druck-doctype). Ausrichtung = Seitenformat des jeweiligen PDF-Generators (Dompdf setPaper).
        {
            section: 'Jahresmeisterschaft',
            profiles: [
                { doc_type: 'jm_standblatt',  label: 'JM Standblatt',    desc: 'Standblatt fuer Jahresmeisterschaft (Word-Vorlage A4 quer, PDF)', format_fixed: 'A4 Landscape', default_copies: 1 },
                { doc_type: 'jmrang',         label: 'JM Rangliste',     desc: 'Rangliste nach Rang / nach Name (JM Rangliste)', format_select: FORMAT_A4_QUER, format_field: 'format', default_copies: 1 },
                { doc_type: 'jmdurchschnitt', label: 'JM Durchschnitte', desc: 'PDF-Export der Durchschnitte pro Anlass', format_select: FORMAT_A4_HOCH, format_field: 'format', default_copies: 1 },
                { doc_type: 'jmdefinition',   label: 'Jahresprogramm',   desc: 'Jahresprogramm-PDF (JM Definition)', format_fixed: 'A4 Portrait', default_copies: 1 }
            ]
        },
        {
            section: 'Endschiessen',
            profiles: [
                // Excel-Vorlage → PDF (Konvertierungsdienst) → QZ Tray; Duplex «Lange Seite» = beidseitig, Bindung an der langen Kante
                { doc_type: 'endschiessen_standblatt', label: 'Endschiessen Standblatt',   desc: 'Standblatt aus «Endschiessen loesen» (A4 quer, PDF)', format_fixed: 'A4 Landscape', default_copies: 1 },
                { doc_type: 'endschiessen_abrechnung', label: 'Endschiessen Abrechnung',   desc: 'Abrechnung aus «Endschiessen loesen»', format_fixed: 'A4 Landscape', default_copies: 1 },
                { doc_type: 'endschrang',              label: 'Endschiessen Ranglisten',   desc: 'Alle Ranglisten der Seite «Endschiessen Rangliste» (Gesamt, Zwischen, Anmeldung, Stiche, Partner)', format_select: FORMAT_A4_HOCH, format_field: 'format', default_copies: 1 },
                { doc_type: 'absendenbuch',            label: 'Absendenbuch (Broschuere)', desc: 'A5-Broschuere auf A4 quer, 2 Seiten pro Blatt, gefaltet. Duplex «Kurze Seite» (ohne Angabe wird sie automatisch gesetzt)', format_fixed: 'A4 Landscape', default_copies: 1 },
                { doc_type: 'endsch_targetprint',      label: 'Endschiessen Zielscheiben', desc: 'Zielscheiben-PDF aus der CSV-Datei', format_fixed: 'A4 Portrait', default_copies: 1 },
                { doc_type: 'munitionskauf',           label: 'Munitionskauf Liste',       desc: 'PDF der Munitionskaeufe (aktiver Zeitraum-Filter)', format_fixed: 'A4 Portrait', default_copies: 1 }
            ]
        },
        {
            section: 'Ranglisten',
            profiles: [
                { doc_type: 'kantirang',            label: 'Kantonalstich Rangliste',        desc: 'Rangliste Kantonalstich (auch von «Kantonal Abrechnung» genutzt)', format_select: FORMAT_A4_HOCH, format_field: 'format', default_copies: 1 },
                { doc_type: 'heimrang',             label: 'Heimmeisterschaft Rangliste',    desc: 'Rangliste Heimmeisterschaft', format_select: FORMAT_A4_QUER, format_field: 'format', default_copies: 1 },
                { doc_type: 'sektionrang',          label: 'Sektionsmeisterschaft Rangliste', desc: 'Runde 1, Runde 2 und Schnitt', format_select: FORMAT_A4_HOCH, format_field: 'format', default_copies: 1 },
                { doc_type: 'sektionsrangierungen', label: 'Sektionsrangierungen',           desc: 'PDF-Export der Sektionsrangierungen', format_select: FORMAT_A4_HOCH, format_field: 'format', default_copies: 1 },
                { doc_type: 'einzelrangierung',     label: 'Einzelrangierungen',             desc: 'PDF-Export der Einzelrangierungen', format_select: FORMAT_A4_HOCH, format_field: 'format', default_copies: 1 },
                { doc_type: 'cuprang',              label: 'Vereinscup Rangliste',           desc: 'Rangliste Vereinscup', format_select: FORMAT_A4_HOCH, format_field: 'format', default_copies: 1 }
            ]
        },
        {
            section: 'Einsätze',
            profiles: [
                // Word-Einsatzliste (PhpWord) → PDF über den Konvertierungsdienst → QZ Tray; Ausrichtung kommt je Plan von der Seite
                { doc_type: 'einsatzplan', label: 'Einsatzplan', desc: 'Einsatzliste aus der Einsatzplanung (Obligatorisch, Feldschiessen, Chilbi quer; Schlossturm hoch), PDF', format_fixed: 'A4 (Ausrichtung je Plan)', default_copies: 1 }
            ]
        }
    ],

    // ============================================================
    //  INIT
    // ============================================================
    init() {
        this.pm = new PrintManager();
        this.pm.onStatusChange = (connected) => this.updateStatusUI(connected);

        // Machine-ID Badge
        const badge = document.getElementById('machineIdBadge');
        if (badge && typeof getMachineId === 'function') {
            const mid = getMachineId();
            badge.textContent = 'Arbeitsplatz: ' + mid.substring(0, 8);
            badge.title = 'Machine-ID: ' + mid;
        } else if (badge) {
            badge.style.display = 'none';
        }

        this.loadPrinters();
        this.loadPrintLog();
        this.connect();
    },

    // ============================================================
    //  HILFSFUNKTIONEN
    // ============================================================
    esc(str) {
        if (str == null) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    },

    // ============================================================
    //  QZ TRAY VERBINDUNG
    // ============================================================
    async connect() {
        if (!this.pm) return;
        try {
            $('#btnConnect').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Verbinde...');
            await this.pm.connect();
            this.updateStatusUI(true);
            await this.refreshSystemPrinters();
        } catch (err) {
            this.updateStatusUI(false);
            const msg = err?.message || String(err);
            console.error('[QZ Connect] Fehler:', err);
            let hint = '';
            if (msg.includes('Unable to connect') || msg.includes('WebSocket')) {
                hint = '\n\nPruefe: Laeuft QZ Tray im System-Tray?';
            } else if (msg.includes('sign') || msg.includes('Signierung') || msg.includes('certificate')) {
                hint = '\n\nPruefe: Ist der Private Key (private-key.pem) vorhanden und das QZ-Zertifikat installiert?';
            }
            msvError('QZ Tray Verbindung fehlgeschlagen: ' + msg + hint);
        } finally {
            this.updateConnectButtons();
        }
    },

    async disconnect() {
        if (!this.pm) return;
        await this.pm.disconnect();
        this.systemPrinters = [];
        this.updateConnectButtons();
    },

    updateStatusUI(connected) {
        const $dot = $('#qzDot');
        const $text = $('#qzStatusText');
        if (connected) {
            $dot.removeClass('disconnected').addClass('connected');
            $text.text('Verbunden mit QZ Tray');
        } else {
            $dot.removeClass('connected').addClass('disconnected');
            $text.text('Nicht verbunden mit QZ Tray');
        }
        this.updateConnectButtons();
    },

    updateConnectButtons() {
        if (!this.pm) return;
        const connected = this.pm.connected;
        $('#btnConnect').prop('disabled', connected).html('<i class="bi bi-plug me-1"></i>Verbinden');
        $('#btnDisconnect').prop('disabled', !connected);
    },

    // ============================================================
    //  SYSTEMDRUCKER (live von QZ Tray)
    // ============================================================
    async refreshSystemPrinters() {
        if (!this.pm || !this.pm.connected) return;
        try {
            this.systemPrinters = await this.pm.listPrinters();
            console.log('[Drucker] Systemdrucker geladen:', this.systemPrinters);
            this.populatePrinterSelects();
        } catch (err) {
            console.error('[Drucker] Systemdrucker-Fehler:', err);
        }
    },

    populatePrinterSelects() {
        const $panel = $('#printerName');
        const panelVal = $panel.val();
        $panel.find('option:not(:first)').remove();
        (this.systemPrinters || []).forEach(p => {
            $panel.append(`<option value="${this.esc(p)}">${this.esc(p)}</option>`);
        });
        if (panelVal) $panel.val(panelVal);
    },

    // ============================================================
    //  PROFIL-MATRIX: Render
    // ============================================================
    _renderProfileMatrix() {
        const $matrix = $('#profileMatrix');
        let html = '';

        if (this._profileDefinitions.length === 0) {
            html = '<div class="text-center text-muted p-4">Keine Dokumenttypen definiert. Dokumenttypen werden hinzugefuegt, wenn Seiten Direktdruck bekommen.</div>';
            $matrix.html(html);
            $('#profileCount').text(0);
            $('#profileFooter').text('0 Profile konfiguriert');
            return;
        }

        // Header-Zeile
        html += '<div class="profile-matrix-header">';
        html += '<span>Dokumenttyp</span><span>Drucker</span>';
        html += '<span>Format / Modus</span><span>Kopien</span><span>Duplex</span><span>Farbe</span><span>Test</span>';
        html += '</div>';

        let configuredCount = 0;
        const expandedSections = this._getExpandedSections();

        this._profileDefinitions.forEach((section, sIdx) => {
            let sectionConfigured = 0;
            section.profiles.forEach(def => {
                const p = this._findProfile(def.doc_type);
                if (p && p.printer_id) sectionConfigured++;
            });

            const sectionKey = 'section_' + sIdx;
            const isCollapsed = !expandedSections.includes(sectionKey);

            html += '<div class="profile-section-label' + (isCollapsed ? ' collapsed' : '') + '" data-section="' + sectionKey + '" onclick="Druck.toggleSection(this)">';
            html += '<i class="bi bi-chevron-down section-chevron"></i>';
            html += '<span>' + this.esc(section.section) + '</span>';
            html += '<span class="section-count">' + sectionConfigured + '/' + section.profiles.length + '</span>';
            html += '</div>';

            section.profiles.forEach(def => {
                const profile = this._findProfile(def.doc_type);
                const printerId = profile ? profile.printer_id : '';
                if (printerId) configuredCount++;

                const printerOptions = this._buildPrinterOptions(printerId);

                let formatHtml;
                if (def.format_select) {
                    const currentVal = this._getProfileOption(profile, def.format_field) || def.format_select[0].value;
                    const opts = def.format_select.map(o =>
                        '<option value="' + o.value + '" ' + (o.value === currentVal ? 'selected' : '') + '>' + this.esc(o.label) + '</option>'
                    ).join('');
                    formatHtml = '<select class="profile-select" data-field="' + def.format_field + '">' + opts + '</select>';
                } else {
                    formatHtml = '<span class="profile-format-badge">' + this.esc(def.format_fixed) + '</span>';
                }

                const copies = profile ? (profile.copies || def.default_copies) : def.default_copies;
                const duplexVal = profile ? (profile.duplex || '') : '';

                html += '<div class="profile-row" data-doc-type="' + def.doc_type + '" data-section="' + sectionKey + '"' + (isCollapsed ? ' style="display:none"' : '') + '>';
                html += '<div class="profile-name" data-label="Typ"><span class="profile-name-label">' + this.esc(def.label) + '</span><span class="profile-name-desc">' + this.esc(def.desc) + '</span></div>';
                html += '<div class="profile-printer" data-label="Drucker"><select class="profile-select" data-field="printer_id">' + printerOptions + '</select></div>';
                html += '<div class="profile-format" data-label="Format">' + formatHtml + '</div>';
                html += '<div class="profile-copies" data-label="Kopien"><input type="number" class="profile-copies-input" data-field="copies" value="' + copies + '" min="1" max="9"></div>';
                html += '<div class="profile-duplex" data-label="Duplex"><select class="profile-select" data-field="duplex">';
                html += '<option value=""' + (!duplexVal ? ' selected' : '') + '>Aus</option>';
                html += '<option value="long-edge"' + (duplexVal === 'long-edge' || duplexVal === '1' || duplexVal === 1 ? ' selected' : '') + '>Lange Seite</option>';
                html += '<option value="short-edge"' + (duplexVal === 'short-edge' ? ' selected' : '') + '>Kurze Seite</option>';
                html += '</select></div>';
                // Farbe: QZ colorType color | grayscale | blackwhite (print_profiles.color_mode, Default Schwarzweiss)
                const colorVal = (profile && profile.color_mode) ? profile.color_mode : 'blackwhite';
                html += '<div class="profile-color" data-label="Farbe"><select class="profile-select" data-field="color_mode">';
                html += '<option value="blackwhite"' + (colorVal === 'blackwhite' ? ' selected' : '') + '>Schwarzweiss</option>';
                html += '<option value="grayscale"' + (colorVal === 'grayscale' ? ' selected' : '') + '>Graustufen</option>';
                html += '<option value="color"' + (colorVal === 'color' ? ' selected' : '') + '>Farbe</option>';
                html += '</select></div>';
                html += '<div class="profile-test" data-label="">'
                    + '<button class="profile-test-btn" onclick="Druck.testProfile(\'' + def.doc_type + '\')" title="Testdruck"><i class="bi bi-printer"></i></button>'
                    + '<button class="profile-test-btn profile-copy-btn" onclick="Druck.copyToSection(\'' + def.doc_type + '\', event)" title="Drucker, Duplex, Farbe und Kopien dieser Zeile auf alle Profile der Sektion übertragen (Shift+Klick: auf alle Sektionen). Danach speichern."><i class="bi bi-arrow-bar-down"></i></button>'
                    + '</div>';
                html += '</div>';
            });
        });

        $matrix.html(html);
        $('#profileCount').text(configuredCount);
        $('#profileFooter').text(configuredCount + ' Profile konfiguriert');
    },

    // ============================================================
    //  PROFIL-MATRIX: Save All
    // ============================================================
    saveAllProfiles() {
        const profiles = [];
        const toDelete = [];

        $('#profileMatrix .profile-row').each((_, row) => {
            const $row = $(row);
            const docType = $row.data('doc-type');
            const printerId = $row.find('[data-field="printer_id"]').val();
            const existing = this._findProfile(docType);
            const def = this._findDefinition(docType);

            if (!printerId) {
                if (existing) toDelete.push(existing.id);
                return;
            }

            const data = {
                doc_type: docType,
                anzeigename: def.label,
                printer_id: parseInt(printerId),
                print_mode: 'pixel',
                copies: parseInt($row.find('[data-field="copies"]').val()) || def.default_copies,
                paper_size: 'A4',
                orientation: 'portrait',
                color_mode: $row.find('[data-field="color_mode"]').val() || 'blackwhite',
                duplex: $row.find('[data-field="duplex"]').val() || '',
                optionen: '{}',
            };
            if (existing) data.id = existing.id;

            if (def.format_select) {
                const formatVal = $row.find('[data-field="' + def.format_field + '"]').val();
                const formatDef = def.format_select.find(o => o.value === formatVal);
                if (formatDef) {
                    data.paper_size = formatDef.paper || data.paper_size;
                    if (formatDef.orient) data.orientation = formatDef.orient;
                }
                const optObj = {};
                optObj[def.format_field] = formatVal;
                data.optionen = JSON.stringify(optObj);
            } else if (def.format_fixed) {
                const parts = def.format_fixed.split(' ');
                data.paper_size = parts[0] || 'A4';
                data.orientation = (parts[1] || 'portrait').toLowerCase();
            }

            profiles.push(data);
        });

        if (profiles.length === 0 && toDelete.length === 0) {
            msvToast('Keine Profile mit zugewiesenem Drucker', 'warning');
            return;
        }

        $.ajax({
            url: 'drucksteuerung/profiles_api.php',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': window._csrfToken || '' },
            data: JSON.stringify({
                action: 'save_all',
                profiles: profiles,
                delete_ids: toDelete
            }),
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    const msg = [];
                    if (res.saved > 0) msg.push(res.saved + ' gespeichert');
                    if (res.deleted > 0) msg.push(res.deleted + ' entfernt');
                    msvToast('Profile: ' + msg.join(', '), 'success');
                } else {
                    msvError(res.message || 'Fehler beim Speichern');
                }
                this._reloadProfiles();
            },
            error: () => msvError('Serverfehler beim Speichern')
        });
    },

    // ============================================================
    //  TESTDRUCK
    // ============================================================
    async testProfile(docType) {
        if (!this.pm || !this.pm.connected) {
            msvError('Nicht verbunden mit QZ Tray');
            return;
        }
        const $row = $('.profile-row[data-doc-type="' + docType + '"]');
        const printerId = $row.find('[data-field="printer_id"]').val();
        if (!printerId) {
            msvError('Bitte zuerst einen Drucker waehlen');
            return;
        }
        const printer = this.printers.find(p => p.id == printerId);
        if (!printer) return;
        const copies = parseInt($row.find('[data-field="copies"]').val()) || 1;
        const def = this._findDefinition(docType);
        const label = def?.label || docType;

        // Druckoptionen aus der ZEILE (auch ungespeicherte Werte), damit der Test zeigt, was gespeichert wuerde
        let paper = 'A4', orientation = 'portrait';
        if (def?.format_select) {
            const fv = $row.find('[data-field="' + def.format_field + '"]').val();
            const fd = def.format_select.find(o => o.value === fv) || def.format_select[0];
            paper = fd.paper || paper; orientation = fd.orient || orientation;
        } else if (def?.format_fixed) {
            const parts = def.format_fixed.split(' ');
            paper = parts[0] || paper; orientation = (parts[1] || 'portrait').toLowerCase();
        }
        const duplex = $row.find('[data-field="duplex"]').val() || '';
        const color  = $row.find('[data-field="color_mode"]').val() || 'blackwhite';
        const PAPIER = { A3: [297, 420], A4: [210, 297], A5: [148, 210], LETTER: [216, 279] };
        const size = PAPIER[paper.toUpperCase()] || PAPIER.A4;

        // Testseite als PDF vom Server (gleicher Druckpfad wie echte Dokumente, kein HTML-Rendering in QZ)
        const params = new URLSearchParams({ doc_type: docType, label, printer: printer.name, orientation, paper, copies, duplex, color });
        try {
            const r = await fetch('drucksteuerung/test_pdf.php?' + params, { credentials: 'same-origin' });
            if (!r.ok) throw new Error('Testseite konnte nicht erzeugt werden (HTTP ' + r.status + ')');
            const blob = await r.blob();
            const base64 = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(String(reader.result).split(',')[1]);
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
            await this.pm.printPixel(printer.name,
                [{ type: 'pdf', format: 'base64', data: base64 }],
                {
                    // orientation:null wie im Direktdruck (js/msv-direktdruck.js): QZ erkennt die Ausrichtung aus dem PDF;
                    // explizites 'landscape' fuehrte zu doppelt gedrehtem, verkleinertem Druck
                    size: { width: size[0], height: size[1] }, units: 'mm', orientation: null,
                    copies, duplex: duplex || false, colorType: color,
                    margins: { top: 0, right: 0, bottom: 0, left: 0 }, rasterize: false,
                    jobName: 'Testdruck ' + label
                }
            );
            await this.pm.logJob(docType, printer.name, 'Testdruck ' + paper + ' ' + orientation, 'gesendet', copies);
            msvToast('Testdruck "' + label + '" gesendet (' + paper + ' ' + (orientation === 'landscape' ? 'quer' : 'hoch') + ')', 'success');
            this.loadPrintLog();
        } catch (err) {
            await this.pm.logJob(docType, printer.name, 'Testdruck', 'fehler', copies, err && err.message ? err.message : String(err));
            msvError('Testdruck fehlgeschlagen: ' + (err.message || err));
        }
    },

    // ============================================================
    //  EINSTELLUNGEN AUF SEKTION UEBERTRAGEN
    //  Drucker, Duplex, Farbe und Kopien der Quellzeile in alle Zeilen der Sektion (Shift: alle Sektionen)
    //  kopieren. Das Format bleibt pro Zeile (Ausrichtung ist dokumentspezifisch). Speichern macht der Benutzer.
    // ============================================================
    copyToSection(docType, ev) {
        if (ev && ev.stopPropagation) ev.stopPropagation();
        const $src = $('.profile-row[data-doc-type="' + docType + '"]');
        if (!$src.length) return;
        const alle = !!(ev && ev.shiftKey);
        const sectionKey = $src.data('section');
        const werte = {
            printer_id: $src.find('[data-field="printer_id"]').val(),
            duplex:     $src.find('[data-field="duplex"]').val(),
            color_mode: $src.find('[data-field="color_mode"]').val(),
            copies:     $src.find('[data-field="copies"]').val(),
        };
        if (!werte.printer_id) {
            msvToast('Bitte zuerst in dieser Zeile einen Drucker wählen', 'warning');
            return;
        }
        const $ziel = (alle ? $('.profile-row') : $('.profile-row[data-section="' + sectionKey + '"]')).not($src);
        let n = 0;
        $ziel.each((_, row) => {
            const $row = $(row);
            Object.keys(werte).forEach(feld => {
                const $f = $row.find('[data-field="' + feld + '"]');
                if ($f.length && werte[feld] !== undefined && werte[feld] !== null) $f.val(werte[feld]);
            });
            $row.addClass('profile-row-copied');
            n++;
        });
        setTimeout(() => $('.profile-row-copied').removeClass('profile-row-copied'), 1500);
        const def = this._findDefinition(docType);
        msvToast('Einstellungen von «' + (def?.label || docType) + '» auf ' + n + (alle ? ' Profile aller Sektionen' : ' Profile der Sektion')
            + ' übertragen – jetzt «Speichern» klicken', 'success');
    },

    // ============================================================
    //  SEKTIONEN
    // ============================================================
    toggleSection(el) {
        const $label = $(el);
        const sectionKey = $label.data('section');
        const isCollapsed = $label.hasClass('collapsed');

        if (isCollapsed) {
            $label.removeClass('collapsed');
            $('.profile-row[data-section="' + sectionKey + '"]').slideDown(150);
        } else {
            $label.addClass('collapsed');
            $('.profile-row[data-section="' + sectionKey + '"]').slideUp(150);
        }
        this._saveExpandedSections();
    },

    _getExpandedSections() {
        try { return JSON.parse(localStorage.getItem('msv_druckprofile_expanded') || '[]'); }
        catch (e) { return []; }
    },

    _saveExpandedSections() {
        const expanded = [];
        $('.profile-section-label:not(.collapsed)').each(function() {
            expanded.push($(this).data('section'));
        });
        localStorage.setItem('msv_druckprofile_expanded', JSON.stringify(expanded));
    },

    // ============================================================
    //  HILFSMETHODEN
    // ============================================================
    _findProfile(docType) {
        return (this.profiles || []).find(p => p.doc_type === docType) || null;
    },

    _findDefinition(docType) {
        for (const section of this._profileDefinitions) {
            const def = section.profiles.find(p => p.doc_type === docType);
            if (def) return def;
        }
        return null;
    },

    _getProfileOption(profile, field) {
        if (!profile || !profile.optionen) return null;
        try {
            const opts = typeof profile.optionen === 'string' ? JSON.parse(profile.optionen) : profile.optionen;
            return opts[field] || null;
        } catch (e) { return null; }
    },

    _buildPrinterOptions(selectedId) {
        let html = '<option value="">-- Drucker --</option>';
        (this.printers || []).forEach(p => {
            if (p.aktiv != 1) return;
            const label = p.anzeigename || p.name;
            html += '<option value="' + p.id + '" ' + (p.id == selectedId ? 'selected' : '') + '>' + this.esc(label) + '</option>';
        });
        return html;
    },

    // ============================================================
    //  DRUCKER (aus DB)
    // ============================================================
    loadPrinters() {
        $('#printerTableBody').html('<tr><td colspan="4" class="text-center text-muted">Lade...</td></tr>');
        $.getJSON('drucksteuerung/printers_api.php', (res) => {
            if (!res.success) return;
            this.printers = res.data;
            this.renderPrinterTable();
            this._reloadProfiles();
        });
    },

    _reloadProfiles() {
        $.getJSON('drucksteuerung/profiles_api.php', (res) => {
            if (!res.success) return;
            this.profiles = res.data;
            this._renderProfileMatrix();
        });
    },

    renderPrinterTable() {
        const $body = $('#printerTableBody');
        if (this.printers.length === 0) {
            $body.html('<tr><td colspan="4" class="text-center text-muted">Keine Drucker konfiguriert</td></tr>');
            return;
        }
        let html = '';
        this.printers.forEach(p => {
            const label = p.anzeigename || p.name;
            html += '<tr style="cursor:pointer" onclick="Druck.editPrinter(' + p.id + ')">'
                + '<td>' + this.esc(label) + '</td>'
                + '<td><span class="badge bg-secondary">' + this.esc(p.typ) + '</span></td>'
                + '<td>' + (p.aktiv == 1 ? '<span class="badge bg-success">Aktiv</span>' : '<span class="badge bg-secondary">Inaktiv</span>') + '</td>'
                + '<td style="text-align:right"><button class="btn btn-outline-danger btn-sm" onclick="event.stopPropagation(); Druck.deletePrinter(' + p.id + ')" title="Loeschen"><i class="bi bi-trash"></i></button></td>'
                + '</tr>';
        });
        $body.html(html);
    },

    showAddPrinter() {
        $('#printerModalTitle').html('<i class="bi bi-printer me-2"></i>Drucker hinzufuegen');
        $('#printerEditId').val('');
        $('#printerName').val('');
        $('#printerDisplayName').val('');
        $('#printerTyp').val('laser');
        $('#printerAktiv').prop('checked', true);
        new bootstrap.Modal('#printerModal').show();
    },

    editPrinter(id) {
        const p = this.printers.find(x => x.id == id);
        if (!p) return;
        $('#printerModalTitle').html('<i class="bi bi-printer me-2"></i>Drucker bearbeiten');
        $('#printerEditId').val(p.id);
        $('#printerName').val(p.name);
        $('#printerDisplayName').val(p.anzeigename || '');
        $('#printerTyp').val(p.typ);
        $('#printerAktiv').prop('checked', p.aktiv == 1);
        new bootstrap.Modal('#printerModal').show();
    },

    savePrinter() {
        const id = $('#printerEditId').val();
        const name = $('#printerName').val().trim();
        if (!name) { msvError('Systemname ist erforderlich.'); return; }

        const data = {
            action: id ? 'update' : 'add',
            name: name,
            anzeigename: $('#printerDisplayName').val().trim(),
            typ: $('#printerTyp').val(),
            beschreibung: '',
            ist_standard: 0,
            aktiv: $('#printerAktiv').is(':checked') ? 1 : 0,
        };
        if (id) data.id = id;

        $.ajax({
            url: 'drucksteuerung/printers_api.php',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': window._csrfToken || '' },
            data: JSON.stringify(data),
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('printerModal'))?.hide();
                    msvToast(id ? 'Drucker aktualisiert' : 'Drucker erstellt', 'success');
                    this.loadPrinters();
                } else {
                    msvError(res.message || 'Fehler beim Speichern');
                }
            },
            error: () => msvError('Serverfehler')
        });
    },

    async deletePrinter(id) {
        const result = await msvConfirmDelete('Drucker');
        if (!result.isConfirmed) return;
        $.ajax({
            url: 'drucksteuerung/printers_api.php',
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': window._csrfToken || '' },
            data: JSON.stringify({ action: 'delete', id: id }),
            dataType: 'json',
            success: (res) => {
                if (res.success) {
                    msvToast('Drucker geloescht', 'success');
                    this.loadPrinters();
                } else {
                    msvError(res.message || 'Fehler beim Loeschen');
                }
            }
        });
    },

    // ============================================================
    //  DRUCKPROTOKOLL
    // ============================================================
    loadPrintLog() {
        $.getJSON('drucksteuerung/print_log_api.php?limit=20', (res) => {
            const $body = $('#printLogBody');
            if (!res.success || !res.data.length) {
                $body.html('<div class="print-log-empty">Keine Eintraege</div>');
                return;
            }
            // Status: gesendet = an den Spooler uebergeben (Normalfall, blau), erfolgreich = bestaetigt (gruen),
            // fehler = rot mit Ursache im Tooltip. Dokumenttyp als Profil-Bezeichnung statt Schluessel.
            const STATUS = {
                gesendet:    { dot: 'sent', text: 'an Drucker übergeben' },
                erfolgreich: { dot: 'ok',   text: 'gedruckt' },
                fehler:      { dot: 'err',  text: 'Fehler' },
            };
            let html = '';
            res.data.forEach(j => {
                const zeit = new Date(j.erstellt_am).toLocaleString('de-CH', {
                    day: '2-digit', month: '2-digit',
                    hour: '2-digit', minute: '2-digit'
                });
                const st = STATUS[j.status] || { dot: 'warn', text: j.status || '?' };
                const def = this._findDefinition(j.doc_type);
                const kopien = Number(j.copies) > 1 ? ' · ' + j.copies + '×' : '';
                const tip = (j.status === 'fehler' && j.fehler_text) ? ' title="' + this.esc(j.fehler_text) + '"' : '';
                html += '<div class="print-log-row' + (j.status === 'fehler' ? ' is-error' : '') + '"' + tip + '>'
                    + '<span class="print-log-dot ' + st.dot + '"></span>'
                    + '<span class="print-log-type">' + this.esc(def?.label || j.doc_type || '-') + '</span>'
                    + '<span class="print-log-file">' + this.esc(j.dateiname || '') + '</span>'
                    + '<span class="print-log-printer">' + this.esc(j.printer_name || '-') + kopien + '</span>'
                    + '<span class="print-log-status ' + st.dot + '">' + this.esc(st.text) + '</span>'
                    + '<span class="print-log-time">' + zeit + '</span>'
                    + '</div>';
            });
            $body.html(html);
        });
    }
};

// Auto-Init wenn DOM bereit
$(function() {
    if (document.getElementById('profileMatrix')) {
        Druck.init();
    }
});
