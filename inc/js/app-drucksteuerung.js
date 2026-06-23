/**
 * app-drucksteuerung.js — Drucksteuerung-Modul (MSV)
 *
 * Eigenstaendiges Modul (Druck-Objekt), nicht App-abhaengig.
 * Nutzt PrintManager, msvToast/msvError fuer Feedback.
 */

const Druck = {

    pm: null,
    printers: [],
    profiles: [],
    systemPrinters: [],

    // ============================================================
    //  PROFIL-DEFINITIONEN (wird spaeter pro Seite erweitert)
    // ============================================================
    _profileDefinitions: [
        {
            section: 'JM Standblatt',
            profiles: [
                { doc_type: 'jm_standblatt', label: 'JM Standblatt', desc: 'Standblatt fuer Jahresmeisterschaft (PDF)', format_fixed: 'A4 Portrait', default_copies: 1 }
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
        html += '<span>Format / Modus</span><span>Kopien</span><span>Duplex</span><span>Test</span>';
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
                html += '<div class="profile-test" data-label=""><button class="profile-test-btn" onclick="Druck.testProfile(\'' + def.doc_type + '\')" title="Testdruck"><i class="bi bi-printer"></i></button></div>';
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
                color_mode: 'blackwhite',
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
        const now = new Date().toLocaleString('de-CH');

        // Generischer A4-Testdruck
        const testHtml = '<div style="width:210mm;height:297mm;padding:20mm;font-family:Arial,sans-serif;box-sizing:border-box">'
            + '<h2>Testdruck ' + this.esc(def?.label || docType) + '</h2>'
            + '<p>Drucker: ' + this.esc(printer.name) + '</p>'
            + '<p>Kopien: ' + copies + '</p>'
            + '<p>Datum: ' + now + '</p></div>';

        try {
            await this.pm.printPixel(printer.name,
                [{ type: 'html', format: 'plain', data: testHtml }],
                { size: { width: 210, height: 297 }, units: 'mm', orientation: 'portrait', copies: copies, margins: { top: 0, right: 0, bottom: 0, left: 0 }, jobName: 'Testdruck ' + (def?.label || docType) }
            );
            await this.pm.logJob(docType, printer.name, 'Testdruck', 'erfolgreich');
            msvToast('Testdruck "' + (def?.label || docType) + '" gesendet', 'success');
            this.loadPrintLog();
        } catch (err) {
            msvError('Testdruck fehlgeschlagen: ' + (err.message || err));
        }
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
            let html = '';
            res.data.forEach(j => {
                const zeit = new Date(j.erstellt_am).toLocaleString('de-CH', {
                    day: '2-digit', month: '2-digit',
                    hour: '2-digit', minute: '2-digit'
                });
                const dotClass = j.status === 'erfolgreich' ? 'ok' :
                                 j.status === 'fehler' ? 'err' : 'warn';
                html += '<div class="print-log-row">'
                    + '<span class="print-log-dot ' + dotClass + '"></span>'
                    + '<span class="print-log-type">' + this.esc(j.doc_type || '-') + '</span>'
                    + '<span class="print-log-printer">' + this.esc(j.printer_name || '-') + '</span>'
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
