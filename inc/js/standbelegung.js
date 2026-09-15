/**
 * standbelegung.js – Import, Übersicht/Export und Art-Erkennung der Standbelegung (inc/standbelegung.php)
 *
 * Seitendaten kommen aus window.SB_INIT (json_encode mit JSON_HEX_TAG):
 *   { csrf, artCodes, kategorien, rules, entries, keywords }
 * Alle JSON-Endpoints unter standbelegung/ lesen das CSRF-Token aus dem Header X-CSRF-TOKEN
 * ($.ajaxSetup), weil $_POST bei contentType 'application/json' leer ist.
 */
(function ($) {
    'use strict';

    const INIT = window.SB_INIT || {};
    const CSRF = INIT.csrf || '';
    const ART_CODES = INIT.artCodes || {};
    const KATEGORIEN = INIT.kategorien || ['300m', '50m', '25m', '10m', 'Sonstiges'];
    const RULES = INIT.rules || {};
    const KAT_COLOR = { '300m': 'text-danger', '50m': 'text-success', '25m': 'text-primary', '10m': 'text-10m', 'Sonstiges': 'text-secondary' };

    let importData = [];
    let overviewData = Array.isArray(INIT.entries) ? INIT.entries : [];
    let artKeywords = Array.isArray(INIT.keywords) ? INIT.keywords : [];
    let exportData = [];
    let exportSource = 'overview';

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': CSRF } });

    // ---------- Helfer ----------
    const esc = s => $('<span>').text(s == null ? '' : String(s)).html();
    const ajaxMsg = (xhr, fallback) => (xhr && xhr.responseJSON && xhr.responseJSON.message)
        || (xhr && xhr.status === 401 ? 'Sitzung abgelaufen – bitte neu anmelden' : fallback);
    const postJson = (url, payload) => $.ajax({ url, method: 'POST', data: JSON.stringify(payload), contentType: 'application/json', dataType: 'json' });
    const badgeClass = k => ({ '300m': 'badge-300m', '50m': 'badge-50m', '25m': 'badge-25m', '10m': 'badge-10m' })[k] || 'badge-sonstiges';
    const formatDate = d => { if (!d) return '-'; const x = new Date(d); return isNaN(x) ? esc(d) : x.toLocaleDateString('de-CH'); };
    const formatTime = t => t ? String(t).substring(0, 5) : '-';
    const yearOf = d => { const x = new Date(d); return isNaN(x) ? '' : String(x.getFullYear()); };
    function withSpinner($btn, text) {
        const orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>' + (text || '…'));
        return () => $btn.prop('disabled', false).html(orig);
    }
    function statsHtml(stats) {
        return KATEGORIEN.filter(k => stats[k] > 0).map(k =>
            `<div class="col-auto"><div class="stat-card"><div class="stat-number ${KAT_COLOR[k] || ''}">${stats[k]}</div><div class="stat-label">${esc(k)}</div></div></div>`
        ).join('');
    }

    // Art aus Bezeichnung: DB-Keywords zuerst, dann Standard-Regeln aus SB_INIT.rules (eine Quelle mit PHP)
    function detectArt(bezeichnung) {
        if (!bezeichnung) return 'AND';
        const bez = String(bezeichnung).toLowerCase();
        for (const kw of artKeywords) {
            const k = String(kw.Keyword || '').toLowerCase();
            if (k && bez.includes(k)) return kw.Art;
        }
        for (const art of Object.keys(RULES)) {
            for (const t of RULES[art]) {
                const hit = t.length <= 3 ? new RegExp('\\b' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b').test(bez) : bez.includes(t);
                if (hit) return art;
            }
        }
        return 'AND';
    }
    const mapDisziplin = k => ({ '300m': 'G300', '50m': 'KK50', '25m': 'P25', '10m': 'LG10' })[k] || k;

    // ---------- Upload ----------
    function bindDropzone($area, $input, handler) {
        $area.on('click', e => { e.preventDefault(); $input.trigger('click'); });
        $area.on('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $input.trigger('click'); } });
        $input.on('change', e => { if (e.target.files[0]) handler(e.target.files[0]); e.target.value = ''; });
        $area.on('dragover', e => { e.preventDefault(); $area.addClass('dragging'); })
             .on('dragleave', e => { e.preventDefault(); $area.removeClass('dragging'); })
             .on('drop', e => { e.preventDefault(); $area.removeClass('dragging'); const f = e.originalEvent.dataTransfer.files; if (f.length) handler(f[0]); });
    }

    function handleFileUpload(file) {
        if (!/\.xlsx?$/i.test(file.name)) { msvToast('Bitte nur Excel-Dateien hochladen', 'error'); return; }
        const fd = new FormData();
        fd.append('file', file);
        fd.append('year', $('#importYear').val());
        fd.append('csrf_token', CSRF);
        showLoading('Excel wird analysiert...');
        $.ajax({ url: 'standbelegung/parse_excel.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
            .done(r => {
                if (r && r.success) { importData = r.data || []; showImportPreview(r.stats || {}); msvToast(importData.length + ' Termine gefunden', 'success'); }
                else msvToast((r && (r.message || r.error)) || 'Excel konnte nicht gelesen werden', 'error');
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Excel konnte nicht gelesen werden'), 'error'))
            .always(hideLoading);
    }

    function handlePdfUpload(file) {
        if (!/\.pdf$/i.test(file.name)) { msvToast('Bitte nur PDF-Dateien hochladen', 'error'); return; }
        const fd = new FormData();
        fd.append('file', file);
        fd.append('year', $('#importYear').val());
        fd.append('csrf_token', CSRF);
        showLoading('PDF wird hochgeladen...');
        $.ajax({ url: 'standbelegung/upload_pdf.php', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
            .done(r => {
                if (r && r.success) {
                    msvToast('PDF für ' + r.year + ' gespeichert', 'success');
                    $('#pdfFileName').text(r.filename || file.name).prop('hidden', false);
                    $('#pdfUploadArea .sb-upload-icon').attr('class', 'bi bi-check-circle-fill sb-upload-icon text-success');
                } else msvToast((r && (r.message || r.error)) || 'Upload fehlgeschlagen', 'error');
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, xhr.status === 413 ? 'Datei zu gross für den Server' : 'Upload fehlgeschlagen'), 'error'))
            .always(hideLoading);
    }

    // ---------- Import-Vorschau ----------
    function showImportPreview(stats) {
        $('#importStatsRow').html(statsHtml(stats));
        KATEGORIEN.forEach(k => $(`.filter-chip[data-scope="import"][data-kategorie="${k}"] .existing-count`).text('(' + (stats[k] || 0) + ')'));
        $('.filter-chip[data-scope="import"]').addClass('active').attr('aria-pressed', 'true');
        renderImportTable();
        $('#uploadArea').closest('.row').prop('hidden', true);
        $('#importPreview').prop('hidden', false);
    }

    function renderImportTable() {
        const html = importData.map((item, i) => {
            const inKal = item.kategorie === '300m'; // Standard: 300m im Kalender
            return `<tr data-index="${i}" data-kategorie="${esc(item.kategorie)}">
                <td><input type="checkbox" class="form-check-input row-checkbox" data-index="${i}" checked aria-label="Zeile auswählen"></td>
                <td class="text-center"><button type="button" class="kalender-toggle ${inKal ? 'active' : ''}" data-tooltip="Im Kalender anzeigen" aria-pressed="${inKal}"><i class="bi bi-calendar-check"></i></button></td>
                <td>${esc(item.datum || '-')}</td><td>${esc(item.wochentag || '-')}</td><td>${esc(item.bezeichnung || '-')}</td>
                <td>${esc(item.start_zeit || '-')}</td><td>${esc(item.end_zeit || '-')}</td>
                <td><span class="badge ${badgeClass(item.kategorie)}">${esc(item.kategorie)}</span></td></tr>`;
        }).join('');
        $('#importTableBody').html(html || '<tr><td colspan="8" class="text-center text-muted py-4">Keine Termine gefunden</td></tr>');
        updateImportCount();
    }
    const updateImportCount = () => $('#saveImportCount').text($('#importTableBody .row-checkbox:checked').length);

    function applyImportFilters() {
        const active = $('.filter-chip[data-scope="import"].active').map(function () { return $(this).data('kategorie'); }).get();
        $('#importTableBody tr[data-kategorie]').each(function () {
            const show = !active.length || active.includes($(this).data('kategorie'));
            $(this).toggle(show);
            if (!show) $(this).find('.row-checkbox').prop('checked', false);
        });
        updateImportCount();
    }

    function resetImport() {
        importData = [];
        $('#importPreview').prop('hidden', true);
        $('#uploadArea').closest('.row').prop('hidden', false);
    }

    function saveImport() {
        const selected = [];
        $('#importTableBody .row-checkbox:checked').each(function () {
            const i = $(this).data('index');
            selected.push({ ...importData[i], in_kalender: $(this).closest('tr').find('.kalender-toggle').hasClass('active') ? 1 : 0 });
        });
        if (!selected.length) { msvToast('Bitte mindestens einen Eintrag auswählen', 'warning'); return; }
        const done = withSpinner($('#saveImportBtn'), 'Speichere…');
        postJson('standbelegung/save_standbelegung.php', { year: $('#importYear').val(), termine: selected })
            .done(r => {
                if (!r || !r.success) { msvToast((r && r.message) || 'Import fehlgeschlagen', 'error'); return; }
                const errs = Array.isArray(r.errors) ? r.errors : [];
                msvToast(r.message || (r.inserted + ' neu, ' + r.updated + ' aktualisiert'), errs.length ? 'warning' : 'success');
                if (errs.length) msvConfirm('<div class="text-start small">' + errs.slice(0, 15).map(esc).join('<br>') + (errs.length > 15 ? '<br>…' : '') + '</div>', 'Nicht importierte Zeilen', 'OK');
                resetImport();
                reloadOverview(true);
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Speichern'), 'error'))
            .always(done);
    }

    // Übersicht frisch vom Server (statt location.reload nach dem Import)
    function reloadOverview(switchTab) {
        return $.getJSON('standbelegung/list_entries.php')
            .done(r => {
                if (r && r.success) { overviewData = r.entries || []; renderOverviewTable(); }
                if (switchTab) new bootstrap.Tab(document.getElementById('overview-tab')).show();
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Übersicht konnte nicht geladen werden'), 'error'));
    }

    // ---------- Übersicht ----------
    function renderOverviewTable() {
        const html = overviewData.map(item => {
            const inKal = parseInt(item.InKalender, 10) === 1;
            return `<tr data-id="${item.ID}" data-kategorie="${esc(item.Kategorie)}" data-jahr="${item.Jahr}" data-bezeichnung="${esc(String(item.Bezeichnung || '').toLowerCase())}">
                <td><input type="checkbox" class="form-check-input row-checkbox" data-id="${item.ID}" aria-label="Eintrag auswählen"></td>
                <td class="text-center"><button type="button" class="kalender-toggle ${inKal ? 'active' : ''}" data-action="kalender-toggle" data-id="${item.ID}" data-tooltip="Im Kalender anzeigen" aria-pressed="${inKal}"><i class="bi bi-calendar-check"></i></button></td>
                <td>${formatDate(item.Datum)}</td><td>${esc(item.Wochentag || '-')}</td><td>${esc(item.Bezeichnung || '-')}</td>
                <td>${formatTime(item.StartZeit)}</td><td>${formatTime(item.EndZeit)}</td>
                <td><span class="badge ${badgeClass(item.Kategorie)}">${esc(item.Kategorie)}</span></td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-outline-primary btn-sm me-1" data-action="entry-edit" data-id="${item.ID}" data-tooltip="Bearbeiten"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-action="entry-delete" data-id="${item.ID}" data-tooltip="Löschen"><i class="bi bi-trash"></i></button>
                </td></tr>`;
        }).join('');
        $('#overviewTableBody').html(html);
        applyOverviewFilters();
    }

    function visibleEntries() {
        const year = $('#overviewYear').val();
        const search = ($('#overviewSearch').val() || '').toLowerCase();
        const active = $('.filter-chip[data-scope="overview"].active').map(function () { return $(this).data('kategorie'); }).get();
        return overviewData.filter(e => (!year || String(e.Jahr) === year)
            && (!active.length || active.includes(e.Kategorie))
            && (!search || String(e.Bezeichnung || '').toLowerCase().includes(search)));
    }

    function applyOverviewFilters() {
        const vis = new Set(visibleEntries().map(e => String(e.ID)));
        const stats = Object.fromEntries(KATEGORIEN.map(k => [k, 0]));
        $('#overviewTableBody tr[data-id]').each(function () {
            const show = vis.has(String($(this).data('id')));
            $(this).toggle(show);
            if (show) { const k = $(this).data('kategorie'); if (stats[k] !== undefined) stats[k]++; }
            else $(this).find('.row-checkbox').prop('checked', false);
        });
        $('#overviewStatsRow').html(statsHtml(stats));
        $('#overviewVisibleCount').text(vis.size);
        $('#overviewTableBody .msv-empty-row').remove();
        if (!vis.size) $('#overviewTableBody').append('<tr class="msv-empty-row"><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-inbox d-block mb-2" style="font-size:1.6rem;opacity:.5;"></i>Keine Einträge gefunden</td></tr>');
        updateOverviewCount();
        buildMobileCards();
    }
    const updateOverviewCount = () => $('#overviewCount').text($('#overviewTableBody .row-checkbox:checked').length);
    const selectedIds = () => $('#overviewTableBody .row-checkbox:checked').map(function () { return $(this).data('id'); }).get();

    // Mobile-Cards nur aus den GEFILTERTEN Daten (MSVMobileCards.buildCards nahm alle tbody-Zeilen)
    function buildMobileCards() {
        if (!window.matchMedia('(max-width: 767.98px)').matches) return;
        const sc = document.querySelector('#mobileCardsStandbelegung .mobile-cards-scroll');
        if (!sc) return;
        const list = visibleEntries();
        sc.innerHTML = list.length ? list.map(e => {
            const inKal = parseInt(e.InKalender, 10) === 1;
            const checked = $('#overviewTableBody .row-checkbox[data-id="' + e.ID + '"]').prop('checked');
            return `<div class="mobile-card" data-id="${e.ID}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div class="d-flex align-items-center gap-2 flex-grow-1">
                        <input type="checkbox" class="form-check-input row-checkbox-mobile" data-id="${e.ID}" ${checked ? 'checked' : ''} aria-label="Eintrag auswählen">
                        <button type="button" class="kalender-toggle ${inKal ? 'active' : ''}" data-action="kalender-toggle" data-id="${e.ID}" aria-pressed="${inKal}"><i class="bi bi-calendar-check"></i></button>
                        <div><div class="fw-bold">${formatDate(e.Datum)} – ${esc(e.Bezeichnung)}</div>
                        <small class="text-muted">${esc(e.Wochentag || '')} | ${formatTime(e.StartZeit)} – ${formatTime(e.EndZeit)}</small></div>
                    </div>
                    <div class="d-flex align-items-center gap-2"><span class="badge ${badgeClass(e.Kategorie)}">${esc(e.Kategorie)}</span><i class="bi bi-chevron-down"></i></div>
                </div>
                <div class="mobile-card-body">
                    <div class="mobile-card-detail-row"><span class="mobile-card-detail-label">Datum</span><span class="mobile-card-detail-value">${formatDate(e.Datum)} (${esc(e.Wochentag || '')})</span></div>
                    <div class="mobile-card-detail-row"><span class="mobile-card-detail-label">Von – Bis</span><span class="mobile-card-detail-value">${formatTime(e.StartZeit)} – ${formatTime(e.EndZeit)}</span></div>
                    <div class="mobile-card-detail-row"><span class="mobile-card-detail-label">Aktionen</span><span class="mobile-card-detail-value">
                        <button type="button" class="btn btn-outline-primary btn-sm me-1" data-action="entry-edit" data-id="${e.ID}"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-action="entry-delete" data-id="${e.ID}"><i class="bi bi-trash"></i></button></span></div>
                </div></div>`;
        }).join('') : '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Einträge gefunden</div></div>';
    }

    function toggleKalender(btn) {
        const id = $(btn).data('id');
        const newValue = !$(btn).hasClass('active');
        postJson('standbelegung/update_kalender.php', { id, in_kalender: newValue ? 1 : 0 })
            .done(r => {
                if (!r || !r.success) { msvToast((r && r.message) || 'Fehler beim Speichern', 'error'); return; }
                const entry = overviewData.find(e => String(e.ID) === String(id));
                if (entry) entry.InKalender = newValue ? 1 : 0;
                $('.kalender-toggle[data-id="' + id + '"]').toggleClass('active', newValue).attr('aria-pressed', String(newValue));
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Speichern'), 'error'));
    }

    // ---------- Löschen ----------
    async function deleteEntries(ids) {
        if (!ids.length) { msvToast('Bitte Einträge auswählen', 'warning'); return; }
        const res = await msvConfirmDelete(ids.length === 1 ? 'diesen Eintrag' : ids.length + ' Einträge');
        if (!res.isConfirmed) return;
        postJson('standbelegung/delete_entries.php', { ids })
            .done(r => {
                if (!r || !r.success) { msvToast((r && r.message) || 'Fehler beim Löschen', 'error'); return; }
                msvToast((r.deleted != null ? r.deleted : ids.length) + ' Einträge gelöscht', 'success');
                const set = new Set(ids.map(String));
                overviewData = overviewData.filter(e => !set.has(String(e.ID)));
                renderOverviewTable();
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Löschen'), 'error'));
    }

    // ---------- Export (Schiesstagemeldung) ----------
    function showExportPreview(fromImport) {
        exportSource = fromImport ? 'import' : 'overview';
        if (fromImport) {
            exportData = $('#importTableBody .row-checkbox:checked').map(function () {
                const it = importData[$(this).data('index')];
                return { Kategorie: it.kategorie, Datum: it.datum, StartZeit: it.start_zeit, EndZeit: it.end_zeit, Bezeichnung: it.bezeichnung };
            }).get();
        } else {
            const set = new Set(selectedIds().map(String));
            exportData = overviewData.filter(e => set.has(String(e.ID)));
        }
        if (!exportData.length) { msvToast('Bitte Einträge für den Export auswählen', 'warning'); return; }
        const opts = Object.keys(ART_CODES);
        $('#exportPreviewBody').html(exportData.map((item, i) => {
            const art = detectArt(item.Bezeichnung);
            const datum = item.Datum && String(item.Datum).includes('.') ? esc(item.Datum) : formatDate(item.Datum);
            return `<tr data-index="${i}"><td>${esc(mapDisziplin(item.Kategorie))}</td><td>${datum}</td>
                <td>${item.StartZeit ? esc(String(item.StartZeit).substring(0, 5)) : '-'}</td><td>${item.EndZeit ? esc(String(item.EndZeit).substring(0, 5)) : '-'}</td>
                <td><select class="form-select form-select-sm art-select" data-index="${i}" aria-label="Art">${opts.map(c => `<option value="${c}" ${c === art ? 'selected' : ''}>${c}</option>`).join('')}</select></td>
                <td>${esc(item.Bezeichnung || '-')}</td></tr>`;
        }).join(''));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('exportModal')).show();
    }

    function executeExport() {
        const entries = $('#exportPreviewBody tr').map(function () {
            const i = $(this).find('.art-select').data('index');
            return { ...exportData[i], art: $(this).find('.art-select').val() };
        }).get();
        const done = withSpinner($('#executeExportBtn'), 'Erstelle…');
        postJson('standbelegung/export_schiesstagemeldung.php', { entries, source: exportSource })
            .done(r => {
                if (r && r.success && r.file) { msvToast('Export erstellt', 'success'); bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide(); window.location.href = r.file; }
                else msvToast((r && (r.message || r.error)) || 'Export fehlgeschlagen', 'error');
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Export'), 'error'))
            .always(done);
    }

    // ---------- Keywords ----------
    function addKeyword() {
        const keyword = $('#newKeyword').val().trim(), art = $('#newKeywordArt').val();
        if (!keyword) { msvToast('Bitte ein Keyword eingeben', 'warning'); return; }
        postJson('standbelegung/manage_keywords.php', { action: 'add', keyword, art })
            .done(r => {
                if (!r || !r.success) { msvToast((r && (r.message || r.error)) || 'Fehler beim Hinzufügen', 'error'); return; }
                msvToast('Keyword hinzugefügt', 'success');
                artKeywords.push({ ID: Number(r.id), Keyword: keyword, Art: art });
                const $c = $(`.keywords-container[data-art="${art}"]`);
                $c.find('.kw-empty').remove();
                $c.append(`<span class="keyword-tag" data-id="${Number(r.id)}">${esc(keyword)} <button type="button" class="btn-remove" data-action="keyword-delete" data-id="${Number(r.id)}" aria-label="Keyword entfernen">&times;</button></span>`);
                $(`.kw-count[data-art="${art}"]`).text('(' + $c.find('.keyword-tag').length + ')');
                $('#newKeyword').val('').trigger('focus');
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Hinzufügen'), 'error'));
    }

    async function deleteKeyword(id) {
        const res = await msvConfirmDelete('dieses Keyword');
        if (!res.isConfirmed) return;
        postJson('standbelegung/manage_keywords.php', { action: 'delete', id })
            .done(r => {
                if (!r || !r.success) { msvToast((r && (r.message || r.error)) || 'Fehler beim Löschen', 'error'); return; }
                msvToast('Keyword gelöscht', 'success');
                artKeywords = artKeywords.filter(kw => String(kw.ID) !== String(id)); // ID aus PHP ist ein String
                const $tag = $(`.keyword-tag[data-id="${id}"]`), $c = $tag.closest('.keywords-container');
                $tag.remove();
                if (!$c.find('.keyword-tag').length) $c.html('<span class="text-muted fst-italic small kw-empty">Keine Keywords definiert</span>');
                $(`.kw-count[data-art="${$c.data('art')}"]`).text('(' + $c.find('.keyword-tag').length + ')');
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Löschen'), 'error'));
    }

    // ---------- Eintrag bearbeiten / hinzufügen (Slide-Panel) ----------
    function openEditPanel() { $('#editPanelOverlay').addClass('show'); $('#editPanel').addClass('open'); setTimeout(() => $('#editDatum').trigger('focus'), 350); }
    function closeEditPanel() { $('#editPanel').removeClass('open'); $('#editPanelOverlay').removeClass('show'); }

    function openAdd() {
        $('#editModalTitle').html('<i class="bi bi-plus-lg me-2"></i>Neuer Eintrag');
        $('#editId').val(''); $('#editDatum').val(''); $('#editBezeichnung').val(''); $('#editStartZeit').val(''); $('#editEndZeit').val('');
        $('#editKategorie').val('300m'); $('#editInKalender').prop('checked', true);
        openEditPanel();
    }
    function openEdit(id) {
        const e = overviewData.find(x => String(x.ID) === String(id));
        if (!e) { msvToast('Eintrag nicht gefunden', 'error'); return; }
        $('#editModalTitle').html('<i class="bi bi-pencil me-2"></i>Eintrag bearbeiten');
        $('#editId').val(e.ID); $('#editDatum').val(e.Datum); $('#editBezeichnung').val(e.Bezeichnung);
        $('#editStartZeit').val(e.StartZeit ? String(e.StartZeit).substring(0, 5) : ''); $('#editEndZeit').val(e.EndZeit ? String(e.EndZeit).substring(0, 5) : '');
        $('#editKategorie').val(e.Kategorie); $('#editInKalender').prop('checked', parseInt(e.InKalender, 10) === 1);
        openEditPanel();
    }

    function saveEntry() {
        const id = $('#editId').val(), datum = $('#editDatum').val(), bezeichnung = $('#editBezeichnung').val().trim();
        const startZeit = $('#editStartZeit').val(), endZeit = $('#editEndZeit').val(), kategorie = $('#editKategorie').val();
        const inKalender = $('#editInKalender').prop('checked') ? 1 : 0;
        if (!datum) { msvToast('Bitte Datum eingeben', 'warning'); return; }
        if (!bezeichnung) { msvToast('Bitte Bezeichnung eingeben', 'warning'); return; }
        if (startZeit && endZeit && endZeit < startZeit) { msvToast('Die Endzeit liegt vor der Startzeit', 'warning'); return; }
        const done = withSpinner($('#saveEntryBtn'), 'Speichere…');
        postJson('standbelegung/save_entry.php', { id: id || null, datum, bezeichnung, start_zeit: startZeit || null, end_zeit: endZeit || null, kategorie, in_kalender: inKalender })
            .done(r => {
                if (!r || !r.success) { msvToast((r && r.message) || 'Fehler beim Speichern', 'error'); return; }
                msvToast(id ? 'Eintrag aktualisiert' : 'Eintrag hinzugefügt', 'success');
                closeEditPanel();
                const rec = { Datum: datum, Bezeichnung: bezeichnung, StartZeit: startZeit || null, EndZeit: endZeit || null, Kategorie: kategorie, InKalender: inKalender, Jahr: r.jahr || yearOf(datum), Wochentag: r.wochentag || '' };
                if (id) { const i = overviewData.findIndex(x => String(x.ID) === String(id)); if (i !== -1) overviewData[i] = { ...overviewData[i], ...rec }; }
                else overviewData.push({ ID: r.id, ...rec });
                renderOverviewTable();
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Speichern'), 'error'))
            .always(done);
    }

    // ---------- Loading-Overlay (nur für den Excel-Parser / PDF-Upload) ----------
    function showLoading(text) { $('#loadingText').text(text || 'Wird verarbeitet...'); $('#loadingOverlay').prop('hidden', false); }
    function hideLoading() { $('#loadingOverlay').prop('hidden', true); }

    // ---------- Veröffentlichen ----------
    function publishChangelog() {
        msvConfirm('Ein Eintrag wird auf der Website angezeigt.', 'Änderung veröffentlichen?', 'Veröffentlichen').then(r => {
            if (!r.isConfirmed) return;
            const year = $('#overviewYear').val() || $('#importYear').val();
            $.post('changelog_publish.php', { kategorie: 'standbelegung', tabelle: 'Standbelegung', jahr: year, beschreibung: 'Standbelegung ' + year + ' aktualisiert', csrf_token: CSRF }, null, 'json')
                .done(res => msvToast((res && res.message) || (res && res.success ? 'Veröffentlicht' : 'Fehler'), res && res.success ? 'success' : 'error'))
                .fail(xhr => msvToast(ajaxMsg(xhr, 'Veröffentlichung fehlgeschlagen'), 'error'));
        });
    }

    // ---------- Init ----------
    $(function () {
        bindDropzone($('#uploadArea'), $('#fileInput'), handleFileUpload);
        bindDropzone($('#pdfUploadArea'), $('#pdfFileInput'), handlePdfUpload);

        $('#importSelectAll').on('change', function () { $('#importTableBody tr:visible .row-checkbox').prop('checked', this.checked); updateImportCount(); });
        $('#importTableBody').on('change', '.row-checkbox', updateImportCount)
                             .on('click', '.kalender-toggle', function () { $(this).toggleClass('active').attr('aria-pressed', String($(this).hasClass('active'))); });
        $('#overviewSelectAll').on('change', function () { $('#overviewTableBody tr:visible .row-checkbox').prop('checked', this.checked); updateOverviewCount(); buildMobileCards(); });
        $('#overviewTableBody').on('change', '.row-checkbox', updateOverviewCount);
        $('#mobileCardsStandbelegung').on('click', '.row-checkbox-mobile', function (e) { e.stopPropagation(); $('#overviewTableBody .row-checkbox[data-id="' + $(this).data('id') + '"]').prop('checked', this.checked); updateOverviewCount(); });
        $('#mobileCardsStandbelegung').on('click', '[data-action]', e => e.stopPropagation());

        // Filter-Chips (Buttons mit aria-pressed) – Import und Übersicht
        $(document).on('click', '.filter-chip[data-scope]', function () {
            $(this).toggleClass('active').attr('aria-pressed', String($(this).hasClass('active')));
            if ($(this).data('scope') === 'import') applyImportFilters(); else applyOverviewFilters();
        });
        $('#overviewYear').on('change', applyOverviewFilters);
        $('#overviewSearch').on('input', applyOverviewFilters);
        window.matchMedia('(max-width: 767.98px)').addEventListener('change', buildMobileCards);
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', e => { if (e.target.id === 'overview-tab') applyOverviewFilters(); });

        // Aktionen (delegiert, keine Inline-onclick)
        $(document).on('click', '[data-action]', function (e) {
            const a = $(this).data('action'), id = $(this).data('id');
            switch (a) {
                case 'import-select-all':  $('#importTableBody tr:visible .row-checkbox').prop('checked', true); updateImportCount(); break;
                case 'import-select-none': $('#importTableBody tr:visible .row-checkbox').prop('checked', false); updateImportCount(); break;
                case 'import-kalender-on': $('#importTableBody tr:visible .kalender-toggle').addClass('active').attr('aria-pressed', 'true'); break;
                case 'import-kalender-off': $('#importTableBody tr:visible .kalender-toggle').removeClass('active').attr('aria-pressed', 'false'); break;
                case 'import-reset': resetImport(); break;
                case 'import-save': saveImport(); break;
                case 'export-from-import': showExportPreview(true); break;
                case 'export-preview': showExportPreview(false); break;
                case 'export-execute': executeExport(); break;
                case 'export-jsk-pdf': window.open('standbelegung/export_jsk_pdf.php?year=' + encodeURIComponent($('#overviewYear').val() || new Date().getFullYear()), '_blank'); break;
                case 'overview-select-all':  $('#overviewTableBody tr:visible .row-checkbox').prop('checked', true); updateOverviewCount(); buildMobileCards(); break;
                case 'overview-select-none': $('#overviewTableBody tr:visible .row-checkbox').prop('checked', false); updateOverviewCount(); buildMobileCards(); break;
                case 'delete-selected': deleteEntries(selectedIds()); break;
                case 'entry-add': openAdd(); break;
                case 'entry-edit': openEdit(id); break;
                case 'entry-delete': deleteEntries([id]); break;
                case 'entry-save': saveEntry(); break;
                case 'kalender-toggle': toggleKalender(this); break;
                case 'keyword-add': addKeyword(); break;
                case 'keyword-delete': deleteKeyword(id); break;
                case 'publish': publishChangelog(); break;
                default: return;
            }
            e.preventDefault();
        });

        $('#closeEditPanel, #cancelEditPanel, #editPanelOverlay').on('click', closeEditPanel);
        $(document).on('keydown', e => { if (e.key === 'Escape' && $('#editPanel').hasClass('open')) closeEditPanel(); });
        $('#editPanel').on('keydown', 'input:not([type=checkbox])', e => { if (e.key === 'Enter') { e.preventDefault(); saveEntry(); } });
        $('#newKeyword').on('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addKeyword(); } });

        renderOverviewTable();
    });
})(jQuery);
