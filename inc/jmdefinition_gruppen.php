<?php
// jmdefinition_gruppen.php - Gruppenerfassung Jahresmeisterschaft
include 'dbconnect.inc.php';

// Seitenspezifische Styles definieren
$page_specific_css = "
/* Drag & Drop Styling */
.draggable-member {
    cursor: grab;
    margin: 2px;
    padding: 4px 10px;
    border: 1.5px solid #e9ecef;
    background: linear-gradient(135deg, var(--light-color) 0%, #ffffff 100%);
    border-radius: var(--border-radius);
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--dark-color);
    transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    box-shadow: var(--box-shadow);
    user-select: none;
    -webkit-user-select: none;
    touch-action: none;
}

.draggable-member:hover {
    border-color: var(--secondary-color);
    background: linear-gradient(135deg, #e9ecef 0%, #ffffff 100%);
    box-shadow: var(--box-shadow-hover);
}

.draggable-member:active {
    cursor: grabbing;
}

.member-flex-item {
    width: 31%;
    box-sizing: border-box;
    margin: 1%;
}

.droppable-group {
    border: 2px dashed var(--secondary-color);
    border-radius: var(--border-radius);
    min-height: 150px;
    padding: 15px;
    background: linear-gradient(135deg, var(--light-color) 0%, #ffffff 100%);
    transition: all var(--transition-speed) ease;
}

.droppable-group.hovered {
    background: linear-gradient(135deg, #d4edda 0%, #ffffff 100%);
    border-color: var(--success-color);
    box-shadow: 0 0 20px rgba(40, 167, 69, 0.1);
}

.droppable-group p {
    color: var(--secondary-color);
    font-style: italic;
    margin: 0;
    text-align: center;
    padding: 2rem 0;
}

/* Verfügbare Mitglieder */
.available-members-container {
    background: var(--light-color);
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius);
    padding: 1rem;
    min-height: 150px;
    display: flex;
    flex-wrap: wrap;
    align-content: flex-start;
}

/* UI Draggable States */
.ui-draggable-dragging {
    width: 180px !important;
    box-sizing: border-box;
    z-index: 9999 !important;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25) !important;
    opacity: 0.92 !important;
    border: 2px solid var(--secondary-color) !important;
    background: linear-gradient(135deg, #e9ecef 0%, #ffffff 100%) !important;
    cursor: grabbing !important;
    transition: none !important;
    pointer-events: none;
}

/* Hover-States für Drop-Zonen */
.droppable-group.ui-droppable-hover {
    background: linear-gradient(135deg, #d4edda 0%, #ffffff 100%);
    border-color: var(--success-color);
    box-shadow: 0 0 20px rgba(40, 167, 69, 0.2);
}

.available-members-container.ui-droppable-hover {
    background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%);
    border-color: var(--warning-color);
    box-shadow: 0 0 20px rgba(255, 193, 7, 0.2);
}

/* Drag-Placeholder */
.ui-sortable-placeholder {
    background: rgba(108, 117, 125, 0.1) !important;
    border: 2px dashed var(--secondary-color) !important;
    border-radius: var(--border-radius) !important;
    height: 40px !important;
    margin: 3px !important;
    visibility: visible !important;
}

/* Responsive für Gruppenerfassung */
@media (max-width: 767.98px) {
    /* Desktop Drag & Drop verstecken */
    .desktop-group-container {
        display: none !important;
    }

    /* Mobile Touch-Interface anzeigen */
    .mobile-group-container {
        display: block !important;
    }

    .main-card, .sidebar-card, .group-creation-card {
        padding: 1rem;
        margin: 0 0 2rem 0;
        border-radius: 8px;
    }

    .member-flex-item {
        width: 100%;
        margin: 0.25rem 0;
    }

    .group-actions {
        flex-direction: row;
        gap: 8px;
    }

    /* Mobile Member Selection */
    .mobile-member-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px;
        margin-bottom: 8px;
        background: #f8f9fa;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        transition: all 0.2s;
    }

    .mobile-member-item.selected {
        background: #d4edda;
        border-color: #28a745;
    }

    .mobile-member-name {
        font-size: 16px;
        font-weight: 500;
    }

    .mobile-add-btn, .mobile-remove-btn {
        min-width: 44px;
        min-height: 44px;
        padding: 8px 12px;
        border-radius: 8px;
        border: 2px solid;
        background: white;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .mobile-add-btn {
        border-color: #28a745;
        color: #28a745;
    }

    .mobile-add-btn:active {
        background: #28a745;
        color: white;
        transform: scale(0.95);
    }

    .mobile-remove-btn {
        border-color: #dc3545;
        color: #dc3545;
    }

    .mobile-remove-btn:active {
        background: #dc3545;
        color: white;
        transform: scale(0.95);
    }

    .mobile-group-section {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .mobile-section-header {
        font-size: 14px;
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e9ecef;
    }

    .mobile-group-list {
        min-height: 100px;
        padding: 0.5rem;
        background: #f8f9fa;
        border-radius: 8px;
        border: 2px dashed #dee2e6;
    }

    .mobile-group-list.empty {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
        font-style: italic;
    }

    /* Formular-Buttons */
    .btn-compact, .btn-compact-standard {
        min-height: 48px;
        font-size: 16px;
        width: 100%;
        margin-bottom: 8px;
    }
}

/* Desktop: Mobile Container verstecken */
@media (min-width: 768px) {
    .mobile-group-container {
        display: none !important;
    }

    .desktop-group-container {
        display: block !important;
    }
}

/* === Überarbeitung: kompakter, klickbar, Suche/Zähler === */
.main-content-wrapper { max-width: 1200px; }
.draggable-member { cursor: pointer; display: inline-block; }
.draggable-member:hover { border-color: #3b5998; background: #eef2f7; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
.member-flex-item { width: auto; }
.droppable-group { display: flex; flex-wrap: wrap; align-content: flex-start; }
.droppable-group p.text-muted { width: 100%; text-align: center; font-style: italic; padding: 1.25rem 0; margin: 0; }
.droppable-group { border-color: #cbd5e0; }
.droppable-group.hovered, .droppable-group.ui-droppable-hover { background: #f1f7f2; border-color: #2f855a; box-shadow: none; }
.ui-draggable-dragging { box-shadow: 0 6px 18px rgba(0,0,0,.18) !important; border: 1px solid #3b5998 !important; background: #eef2f7 !important; }
.gr-col-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .4rem; }
.gr-col-head .lbl { font-weight: 600; font-size: .75rem; color: #64748b; text-transform: uppercase; letter-spacing: .3px; }
.gr-count { background: #eef2f7; color: #3b5998; font-weight: 700; font-size: .72rem; border-radius: 999px; padding: 1px 9px; }
.member-search { position: relative; margin-bottom: .5rem; }
.member-search input { padding-left: 1.9rem; }
.member-search .bi-search { position: absolute; left: .6rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: .85rem; }

/* === SortableJS Drag-Feedback (ersetzt jQuery-UI) === */
.draggable-member.sortable-ghost { opacity: .35; background: #eef2f7; border-color: #3b5998; }
.draggable-member.sortable-chosen { box-shadow: 0 6px 18px rgba(0,0,0,.18); border-color: #3b5998; }
.draggable-member.sortable-drag { opacity: .9; }

/* === Slide-Panel 'Neuer Anlass' ===
   Container/.panel-overlay/.panel-header/.panel-body sowie Mobile-Touch-Targets
   jetzt zentral in css/msv-styles.css. Breite = 440px via --panel-width am Panel.
   .panel-footer bleibt seitenspezifisch (Flex-Layout der Buttons). */
.panel-footer { display: flex; gap: .5rem; justify-content: flex-end; }
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-12 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <?php
                $page_title = 'Gruppenerfassung Jahresmeisterschaft';
                include 'partials/page_header.inc.php';
                ?>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <!-- Jahr-Auswahl -->
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                            <i class="bi bi-calendar3 me-1"></i>Jahr:
                        </label>
                        <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;">
                            <!-- Dynamisch per JS -->
                        </select>
                    </div>

                    <div class="row g-3">
                        <!-- Sidebar: Anlass und bestehende Gruppen -->
                        <div class="col-xl-3 col-lg-4">
                            <!-- Anlass auswählen -->
                            <div class="card mb-3">
                                <div class="card-header py-2">
                                    <span class="fw-semibold"><i class="bi bi-calendar-event me-2"></i>Anlass auswählen</span>
                                </div>
                                <div class="card-body py-2">
                                    <select id="eventSelect" class="form-select form-select-sm mb-2">
                                        <option value="">Bitte wählen...</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-success btn-sm w-100" id="openAnlassPanel">
                                        <i class="bi bi-plus-circle me-1"></i>Neuer Anlass
                                    </button>
                                </div>
                            </div>

                            <!-- Bestehende Gruppen -->
                            <div class="card">
                                <div class="card-header py-2">
                                    <span class="fw-semibold"><i class="bi bi-collection me-2"></i>Bestehende Gruppen</span>
                                </div>
                                <div class="card-body p-2">
                                    <div id="existingGroups">
                                        <div class="text-center text-muted py-3">
                                            <i class="bi bi-info-circle me-2"></i>
                                            Bitte zuerst einen Anlass wählen
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hauptbereich: Gruppe erstellen -->
                        <div class="col-xl-9 col-lg-8">
                            <div class="card">
                                <div class="card-header py-2">
                                    <span class="fw-semibold" id="formCardTitle"><i class="bi bi-plus-square me-2"></i>Neue Gruppe erstellen</span>
                                </div>
                                <div class="card-body">
                                    <form id="newGroupForm">
                                        <input type="hidden" id="editGroupId" value="">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                                        <div class="mb-3">
                                            <label for="gruppenname" class="form-label fw-semibold small">
                                                <i class="bi bi-tag me-1"></i>Gruppenname:
                                            </label>
                                            <input type="text" id="gruppenname" class="form-control form-control-sm" style="max-width: 320px;" placeholder="Name der Gruppe" required>
                                        </div>

                                        <!-- Desktop: Mitglieder zuteilen (Klick oder Drag & Drop) -->
                                        <div class="desktop-group-container">
                                            <p class="text-muted small mb-2"><i class="bi bi-hand-index me-1"></i>Klicken oder ziehen, um Mitglieder zuzuteilen.</p>
                                            <div class="row g-3">
                                                <!-- Verfügbare Mitglieder -->
                                                <div class="col-md-6">
                                                    <div class="gr-col-head">
                                                        <span class="lbl"><i class="bi bi-person-lines-fill me-1"></i>Verfügbar</span>
                                                        <span class="gr-count" id="availCount">0</span>
                                                    </div>
                                                    <div class="member-search">
                                                        <i class="bi bi-search"></i>
                                                        <input type="text" id="memberSearch" class="form-control form-control-sm" placeholder="Mitglied suchen...">
                                                    </div>
                                                    <div id="availableMembers" class="available-members-container">
                                                        <div class="text-center text-muted w-100 py-3">
                                                            <i class="bi bi-person-plus me-2"></i>
                                                            Wähle zuerst einen Anlass
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Gruppe zusammenstellen -->
                                                <div class="col-md-6">
                                                    <div class="gr-col-head">
                                                        <span class="lbl"><i class="bi bi-people-fill me-1"></i>Gruppe</span>
                                                        <span class="gr-count" id="groupCount">0</span>
                                                    </div>
                                                    <div id="groupMembers" class="droppable-group">
                                                        <p class="text-muted">
                                                            <i class="bi bi-cursor me-2"></i>
                                                            Mitglieder hierher ziehen oder links anklicken...
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Mobile Touch Container -->
                                        <div class="mobile-group-container" style="display:none;">
                                            <!-- Verfügbare Mitglieder -->
                                            <div class="mobile-group-section">
                                                <div class="mobile-section-header">
                                                    <i class="bi bi-person-lines-fill me-2"></i>
                                                    Verfügbare Mitglieder
                                                </div>
                                                <div id="mobileAvailableMembers">
                                                    <div class="text-center text-muted py-3">
                                                        <i class="bi bi-person-plus me-2"></i>
                                                        Wähle zuerst einen Anlass
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Gruppe -->
                                            <div class="mobile-group-section">
                                                <div class="mobile-section-header">
                                                    <i class="bi bi-people-fill me-2"></i>
                                                    Gruppe (<span id="mobileGroupCount">0</span>)
                                                </div>
                                                <div id="mobileGroupMembers" class="mobile-group-list empty">
                                                    <span>Noch keine Mitglieder hinzugefügt</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2 mt-3">
                                            <button type="submit" class="btn btn-outline-primary btn-sm" id="saveGruppe">
                                                <i class="bi bi-save me-1"></i>Gruppe speichern
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="resetForm">
                                                <i class="bi bi-arrow-clockwise me-1"></i>Zurücksetzen
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

<!-- Slide-Panel für neuen Anlass – zentrale Struktur via inc/partials/side_panel.inc.php -->
<?php
$panel_id       = 'anlassPanel';
$panel_overlay_id = 'anlassOverlay';
$panel_close_id = 'closeAnlassPanel';
$panel_width    = '440px';
$panel_title    = '<i class="bi bi-plus-circle me-2"></i>Neuen Anlass hinzufügen';
ob_start();
?>
        <div class="mb-3">
            <label for="neueJMDefinitionBezeichnung" class="form-label fw-semibold small">Anlassname *</label>
            <input type="text" class="form-control" id="neueJMDefinitionBezeichnung" placeholder="z.B. Gruppenwettkampf">
        </div>
        <div class="mb-3">
            <label for="neueJMDefinitionMaxpunkte" class="form-label fw-semibold small">Maximalpunkte</label>
            <input type="number" class="form-control" id="neueJMDefinitionMaxpunkte" placeholder="100" min="0">
        </div>
        <div class="mb-3">
            <label for="neueJMDefinitionSchiesstage" class="form-label fw-semibold small">Schiesstage</label>
            <textarea class="form-control" id="neueJMDefinitionSchiesstage" placeholder="z.B. Samstag 09:00-12:00..." rows="3"></textarea>
        </div>
        <div class="mb-2">
            <label class="form-label fw-semibold small">Optionen:</label>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="neueJMDefinitionStreicher">
                <label class="form-check-label" for="neueJMDefinitionStreicher">Streicher</label>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="neueJMDefinitionErweitert">
                <label class="form-check-label" for="neueJMDefinitionErweitert">Erweitert</label>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="neueJMDefinitionInfo">
                <label class="form-check-label" for="neueJMDefinitionInfo">Info</label>
            </div>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="neueJMDefinitionGruppe" checked>
                <label class="form-check-label" for="neueJMDefinitionGruppe">Gruppenwettkampf</label>
            </div>
        </div>
<?php
$panel_body = ob_get_clean();
ob_start();
?>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelAnlassPanel">
            <i class="bi bi-x-circle me-1"></i>Abbrechen
        </button>
        <button type="button" class="btn btn-outline-success btn-sm" id="jmdefinitionHinzufuegen">
            <i class="bi bi-plus-circle me-1"></i>Anlass hinzufügen
        </button>
<?php
$panel_footer = ob_get_clean();
include 'partials/side_panel.inc.php';
?>

<!-- SortableJS für Drag & Drop (ersetzt jQuery UI) -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
$(document).ready(function() {


    ////////////////////////
    // 1) Initialisierung //
    ////////////////////////

    // a) Year-Dropdown
    function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        const currentYear = new Date().getFullYear();
        for (let year = currentYear; year >= currentYear - 3; year--) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
    }
    initializeYearDropdown();

    /**
     * b) Drag & Drop Setup mit SortableJS (ersetzt jQuery UI)
     * SortableJS macht alle passenden Kind-Elemente automatisch ziehbar -
     * kein per-Element-Init noetig. Init nur einmal pro Container (idempotent).
     */
    function setupDragDrop() {
        var avail = document.getElementById('availableMembers');
        var grp   = document.getElementById('groupMembers');

        if (avail && !avail._sortable) {
            avail._sortable = Sortable.create(avail, {
                group: 'gruppenDnD',
                animation: 150,
                draggable: '.draggable-member',
                onAdd: function(evt) {
                    // Mitglied wurde nach "Verfuegbar" gezogen
                    $(evt.item).addClass('member-flex-item');
                    if ($("#groupMembers .draggable-member").length === 0) {
                        $("#groupMembers").html('<p class="text-muted"><i class="bi bi-cursor me-2"></i>Mitglieder hierher ziehen oder links anklicken...</p>');
                    }
                    refreshCounts();
                    applyMemberFilter();
                }
            });
        }

        if (grp && !grp._sortable) {
            grp._sortable = Sortable.create(grp, {
                group: 'gruppenDnD',
                animation: 150,
                draggable: '.draggable-member',
                onAdd: function(evt) {
                    // Mitglied wurde nach "Gruppe" gezogen
                    $("#groupMembers p.text-muted").remove();
                    $(evt.item).removeClass('member-flex-item');
                    refreshCounts();
                }
            });
        }
    }

    // Einmal aufrufen
    setupDragDrop();

    // --- Zuteilen per Klick + Suche + Zähler (Desktop) ---
    function refreshCounts() {
        $("#availCount").text($("#availableMembers .draggable-member").length);
        $("#groupCount").text($("#groupMembers .draggable-member").length);
    }
    function applyMemberFilter() {
        var q = ($("#memberSearch").val() || "").toLowerCase().trim();
        $("#availableMembers .draggable-member").each(function() {
            $(this).toggle(this.textContent.toLowerCase().indexOf(q) !== -1);
        });
    }
    function moveToGroup($member) {
        var id = $member.data("id");
        if ($("#groupMembers [data-id='" + id + "']").length > 0) { $member.remove(); refreshCounts(); return; }
        var $new = $("<div></div>").addClass("draggable-member").attr("data-id", id).text($member.text());
        $("#groupMembers").find("p.text-muted").remove();
        $("#groupMembers").append($new);
        $member.remove();
        refreshCounts();
    }
    function moveToAvailable($member) {
        var id = $member.data("id");
        if ($("#availableMembers [data-id='" + id + "']").length > 0) {
            $member.remove();
        } else {
            var $new = $("<div></div>").addClass("member-flex-item draggable-member").attr("data-id", id).text($member.text());
            $("#availableMembers").append($new);
            $member.remove();
        }
        if ($("#groupMembers .draggable-member").length === 0) {
            $("#groupMembers").html('<p class="text-muted"><i class="bi bi-cursor me-2"></i>Mitglieder hierher ziehen oder links anklicken...</p>');
        }
        refreshCounts();
        applyMemberFilter();
    }
    $("#availableMembers").on("click", ".draggable-member", function() { moveToGroup($(this)); });
    $("#groupMembers").on("click", ".draggable-member", function() { moveToAvailable($(this)); });
    $("#memberSearch").on("input", applyMemberFilter);

    // Mobile Detection
    var isMobile = window.matchMedia('(max-width: 767.98px)').matches;
    var mobileGroupMembers = []; // Array für mobile Gruppenmitglieder

    // Mobile Member Liste erstellen
    function buildMobileMemberList(members) {
        if (!isMobile) return;

        var container = $('#mobileAvailableMembers');
        if (!members || members.length === 0) {
            container.html('<div class="text-center text-muted py-3"><i class="bi bi-person-x me-2"></i>Keine verfügbaren Mitglieder</div>');
            return;
        }

        var html = '';
        members.forEach(function(member) {
            var memberId = member.dataset.id;
            var memberName = member.textContent.trim();

            // Prüfen ob Mitglied bereits in Gruppe
            var isInGroup = mobileGroupMembers.some(function(m) { return m.id == memberId; });

            if (!isInGroup) {
                html += '<div class="mobile-member-item" data-id="' + memberId + '">';
                html += '<span class="mobile-member-name">' + memberName + '</span>';
                html += '<button type="button" class="mobile-add-btn" onclick="addToMobileGroup(' + memberId + ', \'' + memberName.replace(/'/g, "\\'") + '\')">';
                html += '<i class="bi bi-plus-lg"></i>';
                html += '</button>';
                html += '</div>';
            }
        });

        container.html(html || '<div class="text-center text-muted py-3">Alle Mitglieder bereits in Gruppe</div>');
    }

    // Member zur mobilen Gruppe hinzufügen
    window.addToMobileGroup = function(memberId, memberName) {
        // Zu mobile Array hinzufügen
        mobileGroupMembers.push({ id: memberId, name: memberName });

        // Auch zum Desktop #groupMembers hinzufügen
        var $newMember = $("<div></div>")
            .addClass("draggable-member")
            .attr("data-id", memberId)
            .text(memberName);
        $("#groupMembers").find("p.text-muted").remove();
        $("#groupMembers").append($newMember);

        // Mobile UI aktualisieren
        updateMobileGroupDisplay();
        rebuildMobileAvailableList();
    };

    // Member aus mobiler Gruppe entfernen
    window.removeFromMobileGroup = function(memberId) {
        // Aus mobile Array entfernen
        mobileGroupMembers = mobileGroupMembers.filter(function(m) { return m.id != memberId; });

        // Auch aus Desktop #groupMembers entfernen
        $("#groupMembers .draggable-member[data-id='" + memberId + "']").remove();

        if ($("#groupMembers .draggable-member").length === 0) {
            $("#groupMembers").html('<p class="text-muted"><i class="bi bi-cursor me-2"></i>Ziehe die Mitglieder hierher...</p>');
        }

        // Mobile UI aktualisieren
        updateMobileGroupDisplay();
        rebuildMobileAvailableList();
    };

    // Mobile Gruppenanzeige aktualisieren
    function updateMobileGroupDisplay() {
        var container = $('#mobileGroupMembers');
        $('#mobileGroupCount').text(mobileGroupMembers.length);

        if (mobileGroupMembers.length === 0) {
            container.addClass('empty');
            container.html('<span>Noch keine Mitglieder hinzugefügt</span>');
            return;
        }

        container.removeClass('empty');
        var html = '';
        mobileGroupMembers.forEach(function(member) {
            html += '<div class="mobile-member-item selected">';
            html += '<span class="mobile-member-name">' + member.name + '</span>';
            html += '<button type="button" class="mobile-remove-btn" onclick="removeFromMobileGroup(' + member.id + ')">';
            html += '<i class="bi bi-dash-lg"></i>';
            html += '</button>';
            html += '</div>';
        });
        container.html(html);
    }

    // Mobile verfügbare Liste neu aufbauen
    function rebuildMobileAvailableList() {
        if (!isMobile) return;
        var members = document.querySelectorAll('#availableMembers .draggable-member');
        buildMobileMemberList(members);
    }

    // Beim Start: hole das Jahr aus dem Dropdown
    let selectedYear = $('#yearSelect').val() || new Date().getFullYear();
    loadEventDropdown(selectedYear);

    // Wenn das Jahr geändert wird: loadEventDropdown
    $('#yearSelect').on('change', function() {
        let year = $(this).val();
        loadEventDropdown(year);
    });

    ///////////////////////////
    // 2) Events & Funktionen//
    ///////////////////////////

    /**
     * loadEventDropdown => füllt #eventSelect - MIT DEBUG
     */
    function loadEventDropdown(year) {
        $.ajax({
            url: 'jmdefinition/load_jmdefinition_gruppen.php',
            method: 'GET',
            data: { year: year },
            dataType: 'json',
            success: function(data) {
                var eventSelect = $('#eventSelect').empty();
                if (data && data.length > 0) {
                    eventSelect.append($('<option></option>').val('').text('Bitte auswählen'));
                    data.forEach(function(ev) {
                        eventSelect.append($('<option></option>').val(ev.ID).text(ev.Bezeichnung));
                    });
                } else {
                    eventSelect.append($('<option></option>').val('').text('Keine Anlässe gefunden'));
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", xhr.responseText); // DEBUG
                msvToast("Fehler beim Laden der Anlässe: " + error, 'error');
            }
        });
    }

    /**
     * loadExistingGroups => füllt #existingGroups
     */
    function loadExistingGroups(eventID, jahr) {
        $('#existingGroups').html(`
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                Lade Gruppen...
            </div>
        `);
        
        $.ajax({
            url: 'jmdefinition/load_gruppen.php',
            method: 'GET',
            data: { eventID: eventID, jahr: jahr },
            dataType: 'json',
            success: function(data) {
                let container = $("#existingGroups").empty();
                if (data && data.length > 0) {
                    data.forEach(function(group) {
                        let card = $(`
                            <div class="card mb-2" data-groupid="${group.ID}">
                                <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-semibold small">${group.Gruppenname}</div>
                                        <div class="text-muted" style="font-size:0.8rem;">Mitglieder: ${group.Mitglieder}</div>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-outline-primary btn-sm edit-group" data-tooltip="Gruppe bearbeiten">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm delete-group" data-tooltip="Gruppe löschen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `);

                        // Event Handler
                        card.find('.edit-group').on('click', function() {
                            editGroup(group.ID);
                        });

                        card.find('.delete-group').on('click', function() {
                            deleteGroup(group.ID);
                        });

                        container.append(card);
                    });
                } else {
                    container.html(`
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-info-circle me-2"></i>
                            Für diesen Anlass gibt es noch keine Gruppen.
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                $("#existingGroups").html(`
                    <div class="text-center text-danger py-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Fehler beim Laden der Gruppen
                    </div>
                `);
                msvToast("Fehler beim Laden der Gruppen: " + error, 'error');
            }
        });
    }

    /**
     * loadAvailableMembers => füllt #availableMembers - KORRIGIERT
     */
    function loadAvailableMembers(eventID, jahr) {
        $('#availableMembers').html(`
            <div class="text-center text-muted w-100 py-3">
                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                Lade Mitglieder...
            </div>
        `);

        if (isMobile) {
            $('#mobileAvailableMembers').html(`
                <div class="text-center text-muted py-3">
                    <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                    Lade Mitglieder...
                </div>
            `);
        }

        $.ajax({
            url: 'jmdefinition/load_gruppen_members.php',  // KORRIGIERT: richtige Datei
            method: 'GET',
            data: { eventID: eventID, jahr: jahr },
            dataType: 'json',
            success: function(data) {
                let availableContainer = $("#availableMembers").empty();
                if (data.length > 0) {
                    data.forEach(function(member) {
                        let $member = $("<div></div>")
                            .addClass("member-flex-item draggable-member")
                            .attr("data-id", member.ID)
                            .text(member.Name + " " + member.Vorname);
                        availableContainer.append($member);
                    });
                    setupDragDrop();

                    // Mobile Liste generieren
                    if (isMobile) {
                        var members = document.querySelectorAll('#availableMembers .draggable-member');
                        buildMobileMemberList(members);
                    }
                } else {
                    availableContainer.html(`
                        <div class="text-center text-muted w-100 py-3">
                            <i class="bi bi-person-x me-2"></i>
                            Keine verfügbaren Mitglieder gefunden.
                        </div>
                    `);

                    if (isMobile) {
                        $('#mobileAvailableMembers').html(`
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-person-x me-2"></i>
                                Keine verfügbaren Mitglieder
                            </div>
                        `);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error Details:", xhr.responseText);
                $("#availableMembers").html(`
                    <div class="text-center text-danger w-100 py-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Fehler beim Laden der Mitglieder
                    </div>
                `);

                if (isMobile) {
                    $('#mobileAvailableMembers').html(`
                        <div class="text-center text-danger py-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Fehler beim Laden
                        </div>
                    `);
                }

                msvToast("Fehler beim Laden der Mitglieder: " + error, 'error');
            },
            complete: function() { refreshCounts(); applyMemberFilter(); }
        });
    }

    // #eventSelect => bei Änderung => Gruppen + Mitglieder laden
    $('#eventSelect').on('change', function() {
        let eventID = $(this).val();
        let jahr = $('#yearSelect').val();
        
        if (eventID) {
            loadExistingGroups(eventID, jahr);
            loadAvailableMembers(eventID, jahr);
        } else {
            $('#existingGroups').html(`
                <div class="text-center text-muted py-3">
                    <i class="bi bi-info-circle me-2"></i>
                    Bitte zuerst einen Anlass wählen
                </div>
            `);
            $('#availableMembers').html(`
                <div class="text-center text-muted w-100 py-3">
                    <i class="bi bi-person-plus me-2"></i>
                    Wähle zuerst einen Anlass
                </div>
            `);
        }
    });

    //////////////////////////
    // 3) Bearbeiten-Funktion
    //////////////////////////

    function editGroup(groupID) {
        $.ajax({
            url: 'jmdefinition/get_group_details.php',
            method: 'GET',
            data: { groupID: groupID },
            dataType: 'json',
            success: function(groupData) {
                if(!groupData || !groupData.ID) {
                    msvToast("Keine Daten gefunden für Gruppe " + groupID, 'error');
                    return;
                }
                fillGroupEditForm(groupData);
            },
            error: function(xhr, status, error) {
                msvToast("Fehler beim Laden der Gruppe: " + error, 'error');
            }
        });
    }

    function fillGroupEditForm(groupData) {
        $("#editGroupId").val(groupData.ID);
        $("#gruppenname").val(groupData.Gruppenname);
        $("#groupMembers").empty();

        // Mobile Array zurücksetzen
        mobileGroupMembers = [];

        if (!groupData.MemberIDs) {
            if (isMobile) {
                updateMobileGroupDisplay();
                rebuildMobileAvailableList();
            }
            return;
        }

        let arrIDs = groupData.MemberIDs.split(",");
        let arrNames = groupData.MemberNames ? groupData.MemberNames.split("|") : [];

        arrIDs.forEach(function(mid, index) {
            mid = mid.trim();

            let $cand = $("#availableMembers .draggable-member[data-id='" + mid + "']");
            if ($cand.length) {
                let $clone = $("<div></div>")
                    .addClass("draggable-member")
                    .attr("data-id", mid)
                    .text($cand.text());
                $("#groupMembers").append($clone);

                // Zu mobile Array hinzufügen
                mobileGroupMembers.push({
                    id: mid,
                    name: $cand.text().trim()
                });

                $cand.remove();
            } else {
                let nameFallback = "Mitglied " + mid;

                if (arrNames[index]) {
                    nameFallback = arrNames[index].trim();
                }

                let $newElem = $("<div></div>")
                    .addClass("draggable-member")
                    .attr("data-id", mid)
                    .text(nameFallback);
                $("#groupMembers").append($newElem);

                // Zu mobile Array hinzufügen
                mobileGroupMembers.push({
                    id: mid,
                    name: nameFallback
                });
            }
        });

        setupDragDrop();
        refreshCounts();

        // Mobile UI aktualisieren
        if (isMobile) {
            updateMobileGroupDisplay();
            rebuildMobileAvailableList();
        }
    }

    //////////////////////////
    // 4) Gruppe speichern  //
    //////////////////////////
    $("#newGroupForm").on("submit", function(e) {
        e.preventDefault();

        let $submitBtn = $('#saveGruppe');
        let originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        let editId = $("#editGroupId").val().trim(); 
        let eventID = $("#eventSelect").val();
        let jahr = $("#yearSelect").val();
        let gruppenname = $("#gruppenname").val().trim();

        let mitgliederIDs = [];
        $("#groupMembers .draggable-member").each(function() {
            mitgliederIDs.push($(this).data("id"));
        });

        if(!gruppenname || !eventID) {
            msvToast("Bitte Gruppenname und Anlass wählen.", 'warning');
            $submitBtn.prop('disabled', false).html(originalText);
            return;
        }

        if(mitgliederIDs.length === 0) {
            msvToast("Bitte mindestens ein Mitglied zur Gruppe hinzufügen.", 'warning');
            $submitBtn.prop('disabled', false).html(originalText);
            return;
        }

        let requestData = {
            eventID: eventID,
            jahr: jahr,
            gruppenname: gruppenname,
            mitglieder: mitgliederIDs,
            csrf_token: $('input[name="csrf_token"]').val()
        };

        if(editId) {
            requestData.editGroupId = editId;
        }

        $.ajax({
            url: 'jmdefinition/save_gruppen.php',
            method: 'POST',
            dataType: 'json',
            data: requestData,
            success: function(response) {
                if(response.success) {
                    msvToast(editId ? "Gruppe erfolgreich aktualisiert!" : "Gruppe erfolgreich erstellt!", 'success');
                    resetForm();
                    setTimeout(() => {
                        loadExistingGroups(eventID, jahr);
                        loadAvailableMembers(eventID, jahr);
                    }, 500);
                } else {
                    msvToast("Fehler: " + (response.error || response.message || 'Unbekannt'), 'error');
                }
            },
            error: function(xhr, status, error) {
                msvToast("Fehler beim Speichern der Gruppe: " + error, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    //////////////////////////
    // 5) Löschen-Funktion  //
    //////////////////////////
    async function deleteGroup(groupId) {
        const result = await msvConfirmDelete('diese Gruppe');
        if (!result.isConfirmed) return;

        $.ajax({
            url: 'jmdefinition/delete_gruppe.php',
            method: 'POST',
            data: {
                groupID: groupId,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    msvToast("Gruppe wurde gelöscht.", 'success');
                    // Gruppenliste + Mitglieder neu laden
                    let eventID = $('#eventSelect').val();
                    let jahr = $('#yearSelect').val();
                    if (eventID) {
                        loadExistingGroups(eventID, jahr);
                        loadAvailableMembers(eventID, jahr);
                    }
                } else {
                    msvToast("Fehler: " + (response.error || response.message || 'Unbekannt'), 'error');
                }
            },
            error: function(xhr, status, error) {
                msvToast("Fehler beim Löschen der Gruppe: " + error, 'error');
            }
        });
    }

    // Formular zurücksetzen
    function resetForm() {
        $("#editGroupId").val("");
        $("#gruppenname").val("");
        $("#groupMembers").html(`
            <p class="text-muted">
                <i class="bi bi-cursor me-2"></i>
                Mitglieder hierher ziehen oder links anklicken...
            </p>
        `);
        refreshCounts();

        // Mobile zurücksetzen
        mobileGroupMembers = [];
        if (isMobile) {
            updateMobileGroupDisplay();
        }
    }

    $('#resetForm').on('click', function() {
        resetForm();
        let eventID = $('#eventSelect').val();
        let jahr = $('#yearSelect').val();
        if (eventID) {
            loadAvailableMembers(eventID, jahr);
        }
    });

    // --- Slide-Panel 'Neuer Anlass' Steuerung ---
    function openAnlassPanel() {
        $('#anlassOverlay').addClass('show');
        $('#anlassPanel').addClass('open').attr('aria-hidden', 'false');
        setTimeout(function() { $('#neueJMDefinitionBezeichnung').trigger('focus'); }, 350);
    }
    function closeAnlassPanel() {
        $('#anlassPanel').removeClass('open').attr('aria-hidden', 'true');
        $('#anlassOverlay').removeClass('show');
    }
    $('#openAnlassPanel').on('click', openAnlassPanel);
    $('#closeAnlassPanel, #cancelAnlassPanel, #anlassOverlay').on('click', closeAnlassPanel);
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#anlassPanel').hasClass('open')) closeAnlassPanel();
    });
    // Enter in einem Textfeld des Panels = Anlass hinzufügen
    $('#anlassPanel').on('keydown', 'input', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#jmdefinitionHinzufuegen').click(); }
    });

    // Neuen Anlass hinzufügen
    $('#jmdefinitionHinzufuegen').click(function() {
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Hinzufügen...');

        var bezeichnung = $('#neueJMDefinitionBezeichnung').val().trim();
        var schiesstage = $('#neueJMDefinitionSchiesstage').val();
        var maxpunkte = $('#neueJMDefinitionMaxpunkte').val();
        var streicher = $('#neueJMDefinitionStreicher').is(':checked') ? 1 : 0;
        var erweitert = $('#neueJMDefinitionErweitert').is(':checked') ? 1 : 0;
        var info = $('#neueJMDefinitionInfo').is(':checked') ? 1 : 0;
        var gruppe = $('#neueJMDefinitionGruppe').is(':checked') ? 1 : 0;
        var year = $('#yearSelect').val() || new Date().getFullYear();

        if (!bezeichnung) {
            msvToast('Bitte Anlassname eingeben', 'warning');
            $btn.prop('disabled', false).html(originalText);
            return;
        }

        $.ajax({
            url: 'jmdefinition/add_jmdefinition.php',
            type: 'POST',
            data: {
                bezeichnung: bezeichnung,
                schiesstage: schiesstage,
                maxpunkte: maxpunkte,
                streicher: streicher,
                erweitert: erweitert,
                info: info,
                gruppe: gruppe,
                adresse: '',
                year: year,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                msvToast('Anlass erfolgreich hinzugefügt!', 'success');

                closeAnlassPanel();

                // Panel-Felder zurücksetzen
                $('#neueJMDefinitionBezeichnung, #neueJMDefinitionSchiesstage, #neueJMDefinitionMaxpunkte').val('');
                $('#neueJMDefinitionStreicher, #neueJMDefinitionErweitert, #neueJMDefinitionInfo').prop('checked', false);
                $('#neueJMDefinitionGruppe').prop('checked', true);
                
                // Event-Dropdown neu laden
                setTimeout(() => loadEventDropdown(year), 500);
            },
            error: function() {
                msvToast('Fehler beim Hinzufügen des Anlasses', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<?php
include 'footer.inc.php';
?>