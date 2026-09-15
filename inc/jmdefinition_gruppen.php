<?php
// jmdefinition_gruppen.php – Gruppenschiessen: Gruppen pro Anlass (JMDefinition.Gruppe = 1) zusammenstellen
include 'dbconnect.inc.php';

// Nur die auf dieser Seite genutzten Klassen (Drag & Drop via SortableJS, Mobile-Touch-Liste).
$page_specific_css = <<<'CSS'
/* Mitglieder-Chips (Klick oder Ziehen) */
.draggable-member {
    display: inline-block; cursor: pointer; user-select: none; -webkit-user-select: none; touch-action: none;
    margin: 2px; padding: 4px 10px;
    border: 1.5px solid #e9ecef; border-radius: var(--border-radius); background: #fff;
    font-size: 0.78rem; font-weight: 500; color: var(--dark-color);
    transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}
.draggable-member:hover { border-color: #3b5998; background: #eef2f7; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
.draggable-member.sortable-ghost { opacity: .35; background: #eef2f7; border-color: #3b5998; }
.draggable-member.sortable-chosen { box-shadow: 0 6px 18px rgba(0,0,0,.18); border-color: #3b5998; }
.draggable-member.sortable-drag { opacity: .9; }

/* Spalten "Verfügbar" und "Gruppe" */
.available-members-container, .droppable-group {
    display: flex; flex-wrap: wrap; align-content: flex-start;
    min-height: 150px; padding: 12px; border-radius: var(--border-radius);
}
.available-members-container { background: var(--light-color); border: 1px solid #dee2e6; }
.droppable-group { border: 2px dashed #cbd5e0; background: #fff; }
.droppable-group p.text-muted { width: 100%; text-align: center; font-style: italic; padding: 1.25rem 0; margin: 0; }
.gr-col-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .4rem; }
.gr-col-head .lbl { font-weight: 600; font-size: .75rem; color: #64748b; text-transform: uppercase; letter-spacing: .3px; }
.gr-count { background: #eef2f7; color: #3b5998; font-weight: 700; font-size: .72rem; border-radius: 999px; padding: 1px 9px; }
.member-search { position: relative; margin-bottom: .5rem; }
.member-search input { padding-left: 1.9rem; }
.member-search .bi-search { position: absolute; left: .6rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: .85rem; }
#gruppenname { max-width: 320px; }
.gr-edit-hint { display: none; }
.gr-edit-mode .gr-edit-hint { display: inline; }

/* Mobile: Touch-Listen statt Drag & Drop */
.mobile-group-container { display: none; }
@media (max-width: 767.98px) {
    .desktop-group-container { display: none !important; }
    .mobile-group-container { display: block; }
    .mobile-member-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 12px; margin-bottom: 8px; background: #f8f9fa; border: 2px solid #dee2e6; border-radius: 8px;
    }
    .mobile-member-item.selected { background: #d4edda; border-color: #28a745; }
    .mobile-member-name { font-size: 16px; font-weight: 500; }
    .mobile-add-btn, .mobile-remove-btn {
        min-width: 44px; min-height: 44px; padding: 8px 12px; border-radius: 8px; border: 2px solid; background: #fff;
        font-size: 18px; display: flex; align-items: center; justify-content: center; cursor: pointer;
    }
    .mobile-add-btn { border-color: #28a745; color: #28a745; }
    .mobile-add-btn:active { background: #28a745; color: #fff; }
    .mobile-remove-btn { border-color: #dc3545; color: #dc3545; }
    .mobile-remove-btn:active { background: #dc3545; color: #fff; }
    .mobile-group-section { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 1rem; margin-bottom: 1rem; }
    .mobile-section-header {
        font-size: 14px; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;
        margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e9ecef;
    }
    .mobile-group-list { min-height: 100px; padding: 0.5rem; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6; }
    .mobile-group-list.empty { display: flex; align-items: center; justify-content: center; color: #6c757d; font-style: italic; }
}
CSS;

include 'header.inc.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-default">
                <?php $page_title = 'Gruppenschiessen'; include 'partials/page_header.inc.php'; ?>

                <div class="content-background">
                    <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Jahr + Anlass -->
                    <div class="export-toolbar mb-3">
                        <div class="export-toolbar-head flex-wrap">
                            <label for="yearSelect" class="export-year-label mb-0"><i class="bi bi-calendar3 me-1"></i>Jahr:</label>
                            <select id="yearSelect" class="form-select form-select-sm export-year-select"></select>
                            <span class="export-toolbar-divider" aria-hidden="true"></span>
                            <label for="eventSelect" class="export-year-label mb-0"><i class="bi bi-calendar-event me-1"></i>Anlass:</label>
                            <select id="eventSelect" class="form-select form-select-sm" style="min-width:220px;">
                                <option value="">Bitte wählen...</option>
                            </select>
                            <a href="jmdefinition.php" class="btn btn-outline-secondary btn-sm ms-auto" data-tooltip="Anlässe mit Gruppenwettkampf werden in der JM-Definition angelegt">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Anlässe verwalten
                            </a>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Bestehende Gruppen -->
                        <div class="col-xl-3 col-lg-4">
                            <div class="table-wrapper">
                                <h5 class="table-title"><span><i class="bi bi-collection me-2"></i>Bestehende Gruppen</span><span class="badge bg-secondary" id="groupsCount"></span></h5>
                                <div id="existingGroups" class="p-2">
                                    <div class="text-center text-muted py-3"><i class="bi bi-info-circle me-2"></i>Bitte zuerst einen Anlass wählen</div>
                                </div>
                            </div>
                        </div>

                        <!-- Gruppe erstellen / bearbeiten -->
                        <div class="col-xl-9 col-lg-8">
                            <div class="table-wrapper" id="groupFormWrap">
                                <h5 class="table-title">
                                    <span id="formCardTitle"><i class="bi bi-plus-square me-2"></i>Neue Gruppe erstellen</span>
                                    <span class="badge bg-warning text-dark gr-edit-hint"><i class="bi bi-pencil me-1"></i>Bearbeiten</span>
                                </h5>
                                <div class="p-3">
                                    <form id="newGroupForm">
                                        <input type="hidden" id="editGroupId" value="">

                                        <div class="mb-3">
                                            <label for="gruppenname" class="form-label fw-semibold small"><i class="bi bi-tag me-1"></i>Gruppenname</label>
                                            <input type="text" id="gruppenname" class="form-control form-control-sm" placeholder="Name der Gruppe" required>
                                        </div>

                                        <!-- Desktop: Klick oder Drag & Drop -->
                                        <div class="desktop-group-container">
                                            <p class="text-muted small mb-2"><i class="bi bi-hand-index me-1"></i>Klicken oder ziehen, um Mitglieder zuzuteilen.</p>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div class="gr-col-head">
                                                        <span class="lbl"><i class="bi bi-person-lines-fill me-1"></i>Verfügbar</span>
                                                        <span class="gr-count" id="availCount">0</span>
                                                    </div>
                                                    <div class="member-search">
                                                        <i class="bi bi-search"></i>
                                                        <input type="text" id="memberSearch" class="form-control form-control-sm" placeholder="Mitglied suchen..." aria-label="Mitglied suchen">
                                                    </div>
                                                    <div id="availableMembers" class="available-members-container">
                                                        <div class="text-center text-muted w-100 py-3"><i class="bi bi-person-plus me-2"></i>Wähle zuerst einen Anlass</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="gr-col-head">
                                                        <span class="lbl"><i class="bi bi-people-fill me-1"></i>Gruppe</span>
                                                        <span class="gr-count" id="groupCount">0</span>
                                                    </div>
                                                    <div id="groupMembers" class="droppable-group"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Mobile: Touch-Listen (werden aus den Desktop-Containern gerendert) -->
                                        <div class="mobile-group-container">
                                            <div class="mobile-group-section">
                                                <div class="mobile-section-header"><i class="bi bi-person-lines-fill me-2"></i>Verfügbare Mitglieder</div>
                                                <div id="mobileAvailableMembers"></div>
                                            </div>
                                            <div class="mobile-group-section">
                                                <div class="mobile-section-header"><i class="bi bi-people-fill me-2"></i>Gruppe (<span id="mobileGroupCount">0</span>)</div>
                                                <div id="mobileGroupMembers" class="mobile-group-list empty"></div>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2 mt-3">
                                            <button type="submit" class="btn btn-outline-primary btn-sm" id="saveGruppe">
                                                <i class="bi bi-save me-1"></i><span id="saveGruppeText">Gruppe speichern</span>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="resetForm">
                                                <i class="bi bi-arrow-clockwise me-1"></i><span id="resetFormText">Zurücksetzen</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SortableJS für Drag & Drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
$(function() {
    const CSRF = document.getElementById('csrfToken').value;
    const EMPTY_GROUP_HTML = '<p class="text-muted"><i class="bi bi-cursor me-2"></i>Mitglieder hierher ziehen oder links anklicken...</p>';
    const esc = s => $('<span>').text(s == null ? '' : String(s)).html();
    const ajaxMsg = (xhr, fallback) => (xhr && xhr.responseJSON && xhr.responseJSON.message) || fallback;
    const isMobile = () => window.matchMedia('(max-width: 767.98px)').matches;

    // ---- Jahr (wie JM-Definition: Folgejahr bis -3) ----
    (function() {
        const $y = $('#yearSelect').empty(), cur = new Date().getFullYear();
        for (let y = cur + 1; y >= cur - 3; y--) $y.append($('<option></option>').val(y).text(y).prop('selected', y === cur));
    })();

    // ---- Chip-Helfer (Desktop-Container sind die einzige Datenquelle) ----
    function chip(id, name, available) {
        return $('<div></div>').addClass('draggable-member' + (available ? ' member-flex-item' : '')).attr('data-id', id).text(name);
    }
    function refreshCounts() {
        $('#availCount').text($('#availableMembers .draggable-member').length);
        $('#groupCount').text($('#groupMembers .draggable-member').length);
        if ($('#groupMembers .draggable-member').length === 0 && !$('#groupMembers p.text-muted').length) $('#groupMembers').html(EMPTY_GROUP_HTML);
        renderMobile();
    }
    function applyMemberFilter() {
        const q = ($('#memberSearch').val() || '').toLowerCase().trim();
        $('#availableMembers .draggable-member').each(function() { $(this).toggle(this.textContent.toLowerCase().includes(q)); });
    }
    function moveToGroup(id, name) {
        $('#availableMembers .draggable-member[data-id="' + id + '"]').remove();
        if (!$('#groupMembers .draggable-member[data-id="' + id + '"]').length) {
            $('#groupMembers p.text-muted').remove();
            $('#groupMembers').append(chip(id, name, false));
        }
        refreshCounts(); applyMemberFilter();
    }
    function moveToAvailable(id, name) {
        $('#groupMembers .draggable-member[data-id="' + id + '"]').remove();
        if (!$('#availableMembers .draggable-member[data-id="' + id + '"]').length) $('#availableMembers').append(chip(id, name, true));
        refreshCounts(); applyMemberFilter();
    }
    $('#availableMembers').on('click', '.draggable-member', function() { moveToGroup($(this).data('id'), $(this).text()); });
    $('#groupMembers').on('click', '.draggable-member', function() { moveToAvailable($(this).data('id'), $(this).text()); });
    $('#memberSearch').on('input', applyMemberFilter);

    // ---- Drag & Drop (SortableJS, einmal pro Container) ----
    (function setupDragDrop() {
        const avail = document.getElementById('availableMembers'), grp = document.getElementById('groupMembers');
        Sortable.create(avail, { group: 'gruppenDnD', animation: 150, draggable: '.draggable-member',
            onAdd: evt => { $(evt.item).addClass('member-flex-item'); refreshCounts(); applyMemberFilter(); } });
        Sortable.create(grp, { group: 'gruppenDnD', animation: 150, draggable: '.draggable-member',
            onAdd: evt => { $('#groupMembers p.text-muted').remove(); $(evt.item).removeClass('member-flex-item'); refreshCounts(); } });
    })();

    // ---- Mobile-Listen aus dem DOM rendern (delegierte Handler, keine Inline-onclick mit Namen) ----
    function renderMobile() {
        if (!isMobile()) return;
        const avail = $('#availableMembers .draggable-member').map(function() { return { id: this.dataset.id, name: this.textContent.trim() }; }).get();
        const grp   = $('#groupMembers .draggable-member').map(function() { return { id: this.dataset.id, name: this.textContent.trim() }; }).get();
        $('#mobileAvailableMembers').html(avail.length
            ? avail.map(m => '<div class="mobile-member-item" data-id="' + esc(m.id) + '"><span class="mobile-member-name">' + esc(m.name) + '</span><button type="button" class="mobile-add-btn" aria-label="Hinzufügen"><i class="bi bi-plus-lg"></i></button></div>').join('')
            : '<div class="text-center text-muted py-3"><i class="bi bi-person-x me-2"></i>' + ($('#eventSelect').val() ? 'Keine verfügbaren Mitglieder' : 'Wähle zuerst einen Anlass') + '</div>');
        $('#mobileGroupCount').text(grp.length);
        $('#mobileGroupMembers').toggleClass('empty', grp.length === 0).html(grp.length
            ? grp.map(m => '<div class="mobile-member-item selected" data-id="' + esc(m.id) + '"><span class="mobile-member-name">' + esc(m.name) + '</span><button type="button" class="mobile-remove-btn" aria-label="Entfernen"><i class="bi bi-dash-lg"></i></button></div>').join('')
            : '<span>Noch keine Mitglieder hinzugefügt</span>');
    }
    $('#mobileAvailableMembers').on('click', '.mobile-add-btn', function() { const $i = $(this).closest('.mobile-member-item'); moveToGroup($i.data('id'), $i.find('.mobile-member-name').text()); });
    $('#mobileGroupMembers').on('click', '.mobile-remove-btn', function() { const $i = $(this).closest('.mobile-member-item'); moveToAvailable($i.data('id'), $i.find('.mobile-member-name').text()); });
    window.matchMedia('(max-width: 767.98px)').addEventListener('change', renderMobile);

    // ---- Laden ----
    function loadEventDropdown(year) {
        $.getJSON('jmdefinition/load_jmdefinition_gruppen.php', { year })
            .done(function(data) {
                const $s = $('#eventSelect').empty();
                if (Array.isArray(data) && data.length) {
                    $s.append($('<option></option>').val('').text('Bitte wählen...'));
                    data.forEach(ev => $s.append($('<option></option>').val(ev.ID).text(ev.Bezeichnung)));
                } else {
                    $s.append($('<option></option>').val('').text('Keine Anlässe mit Gruppenwettkampf in ' + year));
                }
                $s.trigger('change');
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Laden der Anlässe'), 'error'));
    }

    function loadExistingGroups(eventID, jahr) {
        $('#existingGroups').html('<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-secondary me-2"></div>Lade Gruppen...</div>');
        $.getJSON('jmdefinition/load_gruppen.php', { eventID, jahr })
            .done(function(data) {
                const $c = $('#existingGroups').empty();
                $('#groupsCount').text(Array.isArray(data) ? data.length : '');
                if (!Array.isArray(data) || !data.length) {
                    $c.html('<div class="text-center text-muted py-3"><i class="bi bi-inbox me-2"></i>Keine Gruppen für diesen Anlass gefunden</div>');
                    return;
                }
                data.forEach(function(g) {
                    $c.append(
                        '<div class="card mb-2 gr-card" data-groupid="' + esc(g.ID) + '" data-name="' + esc(g.Gruppenname) + '">' +
                        '<div class="card-body py-2 px-3 d-flex align-items-center justify-content-between gap-2">' +
                        '<div class="min-w-0"><div class="fw-semibold small">' + esc(g.Gruppenname) + '</div>' +
                        '<div class="text-muted small">' + esc(g.Mitglieder) + '</div></div>' +
                        '<div class="d-flex gap-1 flex-shrink-0">' +
                        '<button type="button" class="btn btn-outline-primary btn-sm edit-group" data-tooltip="Gruppe bearbeiten"><i class="bi bi-pencil-square"></i></button>' +
                        '<button type="button" class="btn btn-outline-danger btn-sm delete-group" data-tooltip="Gruppe löschen"><i class="bi bi-trash"></i></button>' +
                        '</div></div></div>');
                });
            })
            .fail(function(xhr) {
                $('#existingGroups').html('<div class="text-center text-danger py-3"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden der Gruppen</div>');
                msvToast(ajaxMsg(xhr, 'Fehler beim Laden der Gruppen'), 'error');
            });
    }

    function loadAvailableMembers(eventID, jahr) {
        $('#availableMembers').html('<div class="text-center text-muted w-100 py-3"><div class="spinner-border spinner-border-sm text-secondary me-2"></div>Lade Mitglieder...</div>');
        return $.getJSON('jmdefinition/load_gruppen_members.php', { eventID, jahr })
            .done(function(data) {
                const $a = $('#availableMembers').empty();
                if (Array.isArray(data) && data.length) data.forEach(m => $a.append(chip(m.ID, m.Name + ' ' + m.Vorname, true)));
                else $a.html('<div class="text-center text-muted w-100 py-3"><i class="bi bi-person-x me-2"></i>Keine verfügbaren Mitglieder gefunden</div>');
            })
            .fail(function(xhr) {
                $('#availableMembers').html('<div class="text-center text-danger w-100 py-3"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden der Mitglieder</div>');
                msvToast(ajaxMsg(xhr, 'Fehler beim Laden der Mitglieder'), 'error');
            })
            .always(() => { refreshCounts(); applyMemberFilter(); });
    }

    $('#yearSelect').on('change', function() { loadEventDropdown(this.value); });
    $('#eventSelect').on('change', function() {
        const eventID = this.value, jahr = $('#yearSelect').val();
        resetForm(false);
        if (eventID) { loadExistingGroups(eventID, jahr); loadAvailableMembers(eventID, jahr); }
        else {
            $('#groupsCount').text('');
            $('#existingGroups').html('<div class="text-center text-muted py-3"><i class="bi bi-info-circle me-2"></i>Bitte zuerst einen Anlass wählen</div>');
            $('#availableMembers').html('<div class="text-center text-muted w-100 py-3"><i class="bi bi-person-plus me-2"></i>Wähle zuerst einen Anlass</div>');
            refreshCounts();
        }
    });

    // ---- Bearbeiten (sichtbarer Edit-Modus: Titel, Badge, Button-Texte) ----
    function setEditMode(on, name) {
        $('#groupFormWrap').toggleClass('gr-edit-mode', on);
        $('#formCardTitle').html(on ? '<i class="bi bi-pencil-square me-2"></i>Gruppe „' + esc(name) + '" bearbeiten' : '<i class="bi bi-plus-square me-2"></i>Neue Gruppe erstellen');
        $('#saveGruppeText').text(on ? 'Änderungen speichern' : 'Gruppe speichern');
        $('#resetFormText').text(on ? 'Abbrechen' : 'Zurücksetzen');
    }

    $('#existingGroups').on('click', '.edit-group', function() {
        const $card = $(this).closest('.gr-card');
        $.getJSON('jmdefinition/get_group_details.php', { groupID: $card.data('groupid') })
            .done(function(g) {
                if (!g || !g.ID) { msvToast((g && g.message) || 'Keine Daten für diese Gruppe gefunden', 'error'); return; }
                // Aktuelle Gruppe zurueck in "Verfuegbar", dann Mitglieder der zu bearbeitenden Gruppe uebernehmen
                $('#groupMembers .draggable-member').each(function() { moveToAvailable($(this).data('id'), $(this).text()); });
                $('#editGroupId').val(g.ID);
                $('#gruppenname').val(g.Gruppenname);
                const ids = g.MemberIDs ? String(g.MemberIDs).split(',') : [];
                const names = g.MemberNames ? String(g.MemberNames).split('|') : [];
                ids.forEach(function(mid, i) {
                    mid = mid.trim();
                    const $cand = $('#availableMembers .draggable-member[data-id="' + mid + '"]');
                    moveToGroup(mid, $cand.length ? $cand.text() : (names[i] ? names[i].trim() : 'Mitglied ' + mid));
                });
                setEditMode(true, g.Gruppenname);
                $('#gruppenname').trigger('focus');
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Laden der Gruppe'), 'error'));
    });

    // ---- Löschen ----
    $('#existingGroups').on('click', '.delete-group', async function() {
        const $card = $(this).closest('.gr-card');
        const res = await msvConfirmDelete('Gruppe „' + $card.data('name') + '"');
        if (!res.isConfirmed) return;
        $.post('jmdefinition/delete_gruppe.php', { groupID: $card.data('groupid'), csrf_token: CSRF }, null, 'json')
            .done(function(r) {
                if (!r || !r.success) { msvToast((r && r.message) || 'Fehler beim Löschen', 'error'); return; }
                msvToast('Gruppe gelöscht', 'success');
                if (String($('#editGroupId').val()) === String($card.data('groupid'))) resetForm(false);
                const eventID = $('#eventSelect').val(), jahr = $('#yearSelect').val();
                if (eventID) { loadExistingGroups(eventID, jahr); loadAvailableMembers(eventID, jahr); }
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Löschen der Gruppe'), 'error'));
    });

    // ---- Speichern ----
    $('#newGroupForm').on('submit', function(e) {
        e.preventDefault();
        const editId = $('#editGroupId').val().trim(), eventID = $('#eventSelect').val(), jahr = $('#yearSelect').val();
        const gruppenname = $('#gruppenname').val().trim();
        const mitglieder = $('#groupMembers .draggable-member').map(function() { return $(this).data('id'); }).get();

        if (!gruppenname || !eventID) { msvToast('Bitte Gruppenname und Anlass wählen', 'warning'); return; }
        if (!mitglieder.length) { msvToast('Bitte mindestens ein Mitglied zur Gruppe hinzufügen', 'warning'); return; }

        const $btn = $('#saveGruppe'), orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
        const data = { eventID, jahr, gruppenname, mitglieder, csrf_token: CSRF };
        if (editId) data.editGroupId = editId;

        $.post('jmdefinition/save_gruppen.php', data, null, 'json')
            .done(function(r) {
                if (!r || !r.success) { msvToast((r && r.message) || 'Fehler beim Speichern', 'error'); return; }
                msvToast(editId ? 'Gruppe aktualisiert' : 'Gruppe gespeichert', 'success');
                resetForm(false);
                loadExistingGroups(eventID, jahr);
                loadAvailableMembers(eventID, jahr);
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Speichern der Gruppe'), 'error'))
            .always(() => $btn.prop('disabled', false).html(orig));
    });

    // ---- Zurücksetzen / Abbrechen ----
    function resetForm(reload) {
        const wasEdit = !!$('#editGroupId').val();
        $('#editGroupId').val('');
        $('#gruppenname').val('');
        $('#groupMembers').html(EMPTY_GROUP_HTML);
        setEditMode(false);
        refreshCounts();
        const eventID = $('#eventSelect').val(), jahr = $('#yearSelect').val();
        if (reload && eventID) loadAvailableMembers(eventID, jahr); // Chips aus einer abgebrochenen Bearbeitung zurueck
        return wasEdit;
    }
    $('#resetForm').on('click', () => resetForm(true));

    // Start
    $('#groupMembers').html(EMPTY_GROUP_HTML);
    loadEventDropdown($('#yearSelect').val());
});
</script>

<?php include 'footer.inc.php'; ?>
