/**
 * inc/hilfetexte/hilfetexte.js – Logik der Seite «Hilfetexte» (Editor).
 *
 * Erwartet aus der Seite: window.HILFE_API, window.HILFE_NAV_PAGES, window.HILFE_KANN_BEARBEITEN,
 * ein Slide-Panel #hilfePanel (Partial side_panel) mit Overlay #panelOverlay, sowie msv-toast.js
 * (msvGet/msvPost/msvEsc/msvConfirmDelete/msvToast) und msv-help.js (MsvHelp.forget).
 */
(function ($) {
    'use strict';

    var API   = window.HILFE_API || 'hilfetexte/api.php';
    var EDIT  = !!window.HILFE_KANN_BEARBEITEN;
    var items = [];
    var scan  = null;          // {used:{key:[files]}} oder null = kein Scan
    var currentId = null;

    var ta = document.getElementById('sgInhalt');

    // ----------------------------------------------------------------- Panel
    var Panel = {
        open: function () {
            $('#hilfePanel').addClass('open');
            $('#panelOverlay').addClass('show');
        },
        close: function () {
            $('#hilfePanel').removeClass('open');
            $('#panelOverlay').removeClass('show');
            $('.hybrid-row').removeClass('selected');
            currentId = null;
        },
        isOpen: function () { return $('#hilfePanel').hasClass('open'); }
    };

    // ----------------------------------------------------------------- Daten
    function load() {
        msvGet(API, function (r) {
            items = r.items || [];
            render();
            fillKategorien();
            if (scan) renderBanner();
        }, { failMsg: 'Hilfetexte konnten nicht geladen werden' });
    }

    function formatDate(s) {
        if (!s || s.length < 16) return s || '';
        return s.substring(8, 10) + '.' + s.substring(5, 7) + '.' + s.substring(0, 4) + ' ' + s.substring(11, 16);
    }

    function fillKategorien() {
        var cats = items.map(function (it) { return it.kategorie; }).filter(Boolean);
        cats = cats.filter(function (c, i) { return cats.indexOf(c) === i; }).sort();
        var $dl = $('#hilfeKategorien').empty();
        cats.forEach(function (c) { $dl.append('<option value="' + msvEsc(c) + '">'); });
    }

    function render() {
        var q = ($('#hilfeSearch').val() || '').toLowerCase().trim();
        var list = items.filter(function (it) {
            if (!q) return true;
            return (it.schluessel || '').toLowerCase().indexOf(q) >= 0
                || (it.titel || '').toLowerCase().indexOf(q) >= 0
                || (it.kategorie || '').toLowerCase().indexOf(q) >= 0;
        });
        var showUsage = !!scan;
        $('#thUsage').toggleClass('d-none', !showUsage);
        $('#hilfeCount').text(items.length);

        var $tb = $('#hilfeTable tbody').empty();
        if (!list.length) {
            $tb.html('<tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>'
                + (q ? 'Keine Hilfetexte gefunden' : 'Noch keine Hilfetexte angelegt') + '</td></tr>');
            return;
        }
        list.forEach(function (it) {
            var cat = it.kategorie ? '<span class="hilfe-cat">' + msvEsc(it.kategorie) + '</span>' : '<span class="hilfe-empty">–</span>';
            var usage = '';
            if (showUsage) {
                var files = scan.used[it.schluessel];
                usage = files && files.length
                    ? '<td><span class="usage-yes"><i class="bi bi-check-circle-fill me-1"></i>' + files.length + '×</span>'
                        + '<span class="usage-files">' + msvEsc(files.join(', ')) + '</span></td>'
                    : '<td><span class="usage-no"><i class="bi bi-exclamation-circle me-1"></i>verwaist</span></td>';
            }
            $tb.append('<tr class="hybrid-row' + (Number(it.id) === Number(currentId) ? ' selected' : '') + '" data-id="' + it.id + '">'
                + '<td><span class="hilfe-key">' + msvEsc(it.schluessel) + '</span></td>'
                + '<td>' + msvEsc(it.titel) + '</td>'
                + '<td>' + cat + '</td>'
                + usage
                + '<td><span class="hilfe-date">' + msvEsc(formatDate(it.geaendert_am)) + '</span></td>'
                + '</tr>');
        });
    }

    // ----------------------------------------------------------------- Scan
    function runScan() {
        var $btn = $('#btnScanHilfe');
        var orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Scan …');
        msvGet(API, { action: 'scan' }, function (r) {
            scan = { used: r.used || {} };
            renderBanner();
            render();
            msvToast('Code-Scan abgeschlossen', 'success');
        }, {
            failMsg: 'Code-Scan fehlgeschlagen',
            fail: function () { $btn.prop('disabled', false).html(orig); }
        }).always(function () { $btn.prop('disabled', false).html(orig); });
    }

    function renderBanner() {
        var $b = $('#scanBanner').empty();
        if (!scan) return;
        var used = Object.keys(scan.used);
        var dbKeys = items.map(function (it) { return it.schluessel; });
        var missing  = used.filter(function (k) { return dbKeys.indexOf(k) < 0; });
        var orphaned = dbKeys.filter(function (k) { return used.indexOf(k) < 0; });
        var cls = missing.length ? 'scan-bad' : (orphaned.length ? 'scan-warn' : 'scan-ok');

        var html = '<div class="scan-banner ' + cls + '"><div style="flex:1;min-width:200px;">'
            + '<div class="scan-banner-title"><i class="bi bi-search me-1"></i>Code-Scan</div>'
            + '<div class="scan-stats mt-1">'
            + '<span class="scan-stat-pill">' + used.length + ' im Code verwendet</span>'
            + '<span class="scan-stat-pill">' + missing.length + ' ohne Text</span>'
            + '<span class="scan-stat-pill">' + orphaned.length + ' verwaist</span></div>';
        if (missing.length) {
            html += '<div class="mt-2"><strong>Im Code verwendet, aber ohne Hilfetext (klicken zum Anlegen):</strong><div class="scan-keylist">'
                + missing.map(function (k) { return '<span class="scan-key" data-create-key="' + msvEsc(k) + '"><i class="bi bi-plus-lg me-1"></i>' + msvEsc(k) + '</span>'; }).join('')
                + '</div></div>';
        }
        if (orphaned.length) {
            html += '<div class="mt-2"><strong>Hilfetexte, die im Code nirgends verwendet werden:</strong><div class="scan-keylist">'
                + orphaned.map(function (k) { return '<span class="scan-key" data-edit-key="' + msvEsc(k) + '">' + msvEsc(k) + '</span>'; }).join('')
                + '</div></div>';
        }
        html += '</div><button type="button" class="btn-close" id="scanBannerClose" aria-label="Schliessen"></button></div>';
        $b.html(html);
    }

    // ----------------------------------------------------------------- Editor
    function updatePreview() {
        var html = $('#sgInhalt').val();
        $('#sgPreview').html(html.trim() ? html : '<span class="text-muted">Vorschau erscheint nach Eingabe …</span>');
    }

    function openNew(key) {
        currentId = null;
        $('#hilfePanelTitle').text('Neuer Hilfetext');
        $('#sgId').val('');
        $('#sgSchluessel').val(key || '');
        $('#sgTitel').val('');
        $('#sgKategorie').val(key && key.indexOf('.') > 0 ? key.substring(0, key.indexOf('.')) : '');
        $('#sgInhalt').val('');
        updatePreview();
        $('#sgDelete').hide();
        $('.hybrid-row').removeClass('selected');
        Panel.open();
        setTimeout(function () { $(key ? '#sgTitel' : '#sgSchluessel').trigger('focus'); }, 300);
    }

    function openEdit(id) {
        var it = items.find(function (x) { return Number(x.id) === Number(id); });
        if (!it) return;
        currentId = it.id;
        $('#hilfePanelTitle').text(EDIT ? 'Hilfetext bearbeiten' : 'Hilfetext');
        $('#sgId').val(it.id);
        $('#sgSchluessel').val(it.schluessel);
        $('#sgTitel').val(it.titel);
        $('#sgKategorie').val(it.kategorie || '');
        $('#sgInhalt').val(it.inhalt_html || '');
        updatePreview();
        $('#sgDelete').toggle(EDIT);
        $('.hybrid-row').removeClass('selected');
        $('.hybrid-row[data-id="' + it.id + '"]').addClass('selected');
        Panel.open();
    }

    function save() {
        var schluessel = $('#sgSchluessel').val().trim();
        var titel      = $('#sgTitel').val().trim();
        var inhalt     = $('#sgInhalt').val();
        if (!schluessel || !titel || !inhalt.trim()) { msvToast('Schlüssel, Titel und Inhalt sind Pflicht', 'warning'); return; }
        if (!/^[a-z0-9._\-]+$/i.test(schluessel)) { msvToast('Schlüssel: nur Buchstaben, Zahlen, Punkt, _ und -', 'warning'); return; }

        var data = {
            action: currentId ? 'update' : 'add',
            schluessel: schluessel, titel: titel,
            kategorie: $('#sgKategorie').val().trim(),
            inhalt_html: inhalt
        };
        if (currentId) data.id = currentId;

        msvPost(API, data, function (r) {
            items = r.items || [];
            if (window.MsvHelp) MsvHelp.forget(schluessel);
            Panel.close();
            render();
            fillKategorien();
            if (scan) renderBanner();
            msvToast(r.message || 'Gespeichert', 'success');
        }, { failMsg: 'Speichern fehlgeschlagen' });
    }

    function remove() {
        if (!currentId) return;
        var it = items.find(function (x) { return Number(x.id) === Number(currentId); });
        if (!it) return;
        msvConfirmDelete('den Hilfetext «' + msvEsc(it.titel) + '»').then(function (res) {
            if (!res.isConfirmed) return;
            msvPost(API, { action: 'delete', id: currentId }, function (r) {
                items = r.items || [];
                if (window.MsvHelp) MsvHelp.forget(it.schluessel);
                Panel.close();
                render();
                fillKategorien();
                if (scan) renderBanner();
                msvToast(r.message || 'Gelöscht', 'success');
            }, { failMsg: 'Löschen fehlgeschlagen' });
        });
    }

    // ----------------------------------------------------------------- Format-Toolbar
    function replaceRange(start, end, text, cursorOffset) {
        ta.focus();
        ta.setSelectionRange(start, end);
        // execCommand('insertText') hält den nativen Undo-Stack der Textarea intakt.
        var ok = document.execCommand('insertText', false, text);
        if (!ok) {
            ta.value = ta.value.substring(0, start) + text + ta.value.substring(end);
            $(ta).trigger('input');
        }
        var pos = start + (cursorOffset !== undefined ? cursorOffset : text.length);
        ta.setSelectionRange(pos, pos);
    }

    function applyWrap(open, close, placeholder) {
        var start = ta.selectionStart, end = ta.selectionEnd;
        var sel = ta.value.substring(start, end);
        var inner = sel || (placeholder || '');
        var text = open + inner + close;
        replaceRange(start, end, text, sel ? text.length : open.length + inner.length);
    }

    function applyBlock(tag) {
        var start = ta.selectionStart, end = ta.selectionEnd;
        var sel = ta.value.substring(start, end).trim();
        var snippet;
        if (tag === 'ul' || tag === 'ol') {
            var lines = sel ? sel.split(/\r?\n/).filter(function (l) { return l.trim(); }) : ['', ''];
            snippet = '<' + tag + '>\n' + lines.map(function (l) { return '  <li>' + l + '</li>'; }).join('\n') + '\n</' + tag + '>';
        } else {
            snippet = '<' + tag + '>' + sel + '</' + tag + '>';
        }
        var before = ta.value.substring(0, start), after = ta.value.substring(end);
        var sep = (before && !/\n$/.test(before)) ? '\n' : '';
        var sepEnd = (after && !/^\n/.test(after)) ? '\n' : '';
        replaceRange(start, end, sep + snippet + sepEnd, sep.length + snippet.length);
    }

    // ----------------------------------------------------------------- Link-Dialog
    var LinkDialog = {
        _sel: null,
        open: function () {
            this._sel = { start: ta.selectionStart, end: ta.selectionEnd };
            var sel = ta.value.substring(this._sel.start, this._sel.end);
            $('#linkDialogText').val(sel);
            $('#linkDialogUrl').val('');
            this.suggest('');
            $('#linkDialogOverlay').addClass('active');
            $('#linkDialog').removeAttr('hidden').addClass('open');
            setTimeout(function () { $(sel ? '#linkDialogUrl' : '#linkDialogText').trigger('focus'); }, 80);
        },
        close: function () {
            $('#linkDialog').removeClass('open');
            $('#linkDialogOverlay').removeClass('active');
            setTimeout(function () { $('#linkDialog').attr('hidden', ''); }, 200);
            this._sel = null;
        },
        suggest: function (filter) {
            var q = (filter || '').toLowerCase().trim();
            var pages = (window.HILFE_NAV_PAGES || []).filter(function (p) {
                return !q || (p.text || '').toLowerCase().indexOf(q) >= 0 || (p.link || '').toLowerCase().indexOf(q) >= 0;
            });
            var $box = $('#linkDialogSuggestions').empty();
            if (!pages.length) { $box.append('<span class="hilfe-empty">Keine passenden Seiten</span>'); return; }
            pages.forEach(function (p) {
                $box.append('<span class="link-sug" data-link="' + msvEsc(p.link) + '" data-text="' + msvEsc(p.text) + '">'
                    + '<i class="bi bi-file-earmark"></i>' + msvEsc(p.text)
                    + ' <span class="link-sug-link">' + msvEsc(p.link) + '</span></span>');
            });
        },
        insert: function () {
            var url = $('#linkDialogUrl').val().trim();
            var text = $('#linkDialogText').val().trim();
            if (!url) { msvToast('Ziel ist Pflicht', 'warning'); return; }
            if (this._sel) { ta.focus(); ta.setSelectionRange(this._sel.start, this._sel.end); }
            applyWrap('<a href="' + url + '">', '</a>', text || url);
            this.close();
            updatePreview();
        }
    };

    // ----------------------------------------------------------------- Events
    $(function () {
        $('#hilfeSearch').on('input', render);
        $(document).on('click', '#hilfeTable .hybrid-row', function () { openEdit($(this).data('id')); });
        $('#hilfePanelClose, #panelOverlay, #sgCancel').on('click', Panel.close);
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && Panel.isOpen() && !$('#linkDialog').hasClass('open')) Panel.close();
        });

        if (EDIT) {
            $('#btnAddHilfe').on('click', function () { openNew(''); });
            $('#btnScanHilfe').on('click', runScan);
            $('#sgSave').on('click', save);
            $('#sgDelete').on('click', remove);
            $('#sgInhalt').on('input', updatePreview);

            $(document).on('click', '#scanBanner [data-create-key]', function () { openNew($(this).data('create-key')); });
            $(document).on('click', '#scanBanner [data-edit-key]', function () {
                var key = $(this).data('edit-key');
                var it = items.find(function (x) { return x.schluessel === key; });
                if (it) openEdit(it.id);
            });
            $(document).on('click', '#scanBannerClose', function () { scan = null; $('#scanBanner').empty(); render(); });

            $(document).on('click', '.editor-toolbar .ed-btn', function () {
                var $b = $(this);
                if ($b.is('[data-undo]'))   { ta.focus(); document.execCommand('undo'); $(ta).trigger('input'); return; }
                if ($b.is('[data-redo]'))   { ta.focus(); document.execCommand('redo'); $(ta).trigger('input'); return; }
                if ($b.is('[data-link]'))   { LinkDialog.open(); return; }
                if ($b.is('[data-insert]')) { replaceRange(ta.selectionStart, ta.selectionEnd, $b.attr('data-insert')); updatePreview(); return; }
                if ($b.is('[data-block]'))  { applyBlock($b.attr('data-block')); updatePreview(); return; }
                if ($b.is('[data-wrap]'))   { var t = $b.attr('data-wrap'); applyWrap('<' + t + '>', '</' + t + '>', ''); updatePreview(); }
            });
            $('#sgInhalt').on('keydown', function (e) {
                if (!(e.ctrlKey || e.metaKey)) return;
                var k = e.key.toLowerCase();
                if (k === 'b') { e.preventDefault(); applyWrap('<strong>', '</strong>', ''); }
                else if (k === 'i') { e.preventDefault(); applyWrap('<em>', '</em>', ''); }
                else if (k === 'u') { e.preventDefault(); applyWrap('<u>', '</u>', ''); }
                else return;
                updatePreview();
            });

            $('#linkDialogUrl, #linkDialogText').on('input', function () {
                LinkDialog.suggest($('#linkDialogUrl').val() || $('#linkDialogText').val());
            }).on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); LinkDialog.insert(); } });
            $(document).on('click', '#linkDialogSuggestions .link-sug', function () {
                $('#linkDialogUrl').val($(this).data('link')).trigger('focus');
                if (!$('#linkDialogText').val().trim()) $('#linkDialogText').val($(this).data('text'));
            });
            $('#linkDialogInsert').on('click', function () { LinkDialog.insert(); });
            $('#linkDialogCancel, #linkDialogCloseX, #linkDialogOverlay').on('click', function () { LinkDialog.close(); });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $('#linkDialog').hasClass('open')) { LinkDialog.close(); e.stopImmediatePropagation(); }
            });
        }

        load();
    });
})(jQuery);
