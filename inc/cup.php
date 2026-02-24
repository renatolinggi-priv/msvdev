<?php
// cup.php - Turnierbaum UI
include 'dbconnect.inc.php';

$body_class = 'cup4-page';
include 'header.inc.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<link rel="stylesheet" href="../css/cup.css?v=<?php echo time(); ?>">
<script src="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.2/dist/themes/base/jquery-ui.min.css">
<script>const CUP4_CSRF = '<?php echo $_SESSION['csrf_token']; ?>';</script>

<div class="container-fluid">

    <!-- Titel (nur Desktop) -->
    <div class="row mb-3 d-none d-md-flex">
        <div class="col-auto">
            <h4 class="mb-0" style="color: var(--cup4-primary);">
                <i class="bi bi-trophy me-2"></i>CUP Resultaterfassung
            </h4>
        </div>
    </div>

    <!-- Fortschrittsanzeige -->
    <div class="cup4-progress" id="progress-section">
        <div class="cup4-progress-steps" id="progress-steps">
            <span class="cup4-step active" data-round="1">
                <span class="cup4-step-dot"></span> Runde 1 <small id="step-r1-count"></small>
            </span>
            <span class="cup4-step-arrow"><i class="bi bi-chevron-right"></i></span>
            <span class="cup4-step" data-round="2">
                <span class="cup4-step-dot"></span> Runde 2 <small id="step-r2-count"></small>
            </span>
            <span class="cup4-step-arrow"><i class="bi bi-chevron-right"></i></span>
            <span class="cup4-step" data-round="final">
                <span class="cup4-step-dot"></span> Finale <small id="step-final-count"></small>
            </span>
        </div>
        <div class="cup4-progress-bar">
            <div class="cup4-progress-fill" id="progress-fill" style="width: 0%"></div>
        </div>
    </div>

    <!-- Sticky Toolbar -->
    <div class="cup4-toolbar" id="cup4-toolbar">
        <!-- Mobile: Sticky Header mit Fortschritt + Toggle -->
        <div class="cup4-toolbar-toggle" id="toolbar-toggle">
            <div class="cup4-mini-progress">
                <span class="cup4-mini-step active" id="mini-step-r1">
                    <span class="cup4-mini-dot"></span>R1 <small id="mini-r1"></small>
                </span>
                <span class="cup4-mini-arrow"><i class="bi bi-chevron-right"></i></span>
                <span class="cup4-mini-step" id="mini-step-r2">
                    <span class="cup4-mini-dot"></span>R2 <small id="mini-r2"></small>
                </span>
                <span class="cup4-mini-arrow"><i class="bi bi-chevron-right"></i></span>
                <span class="cup4-mini-step" id="mini-step-final">
                    <span class="cup4-mini-dot"></span>Finale <small id="mini-final"></small>
                </span>
            </div>
            <i class="bi bi-chevron-down" id="toolbar-chevron"></i>
        </div>
        <!-- Toolbar Body -->
        <div class="cup4-toolbar-body" id="toolbar-body">
            <div class="cup4-toolbar-group">
                <label for="yearSelect">Jahr:</label>
                <select id="yearSelect" class="form-select" style="width: 90px;"></select>
            </div>
            <div class="cup4-toolbar-divider"></div>
            <div class="cup4-toolbar-group">
                <label for="pair-count">Paarungen:</label>
                <input type="number" id="pair-count" class="form-control" min="1" max="10" value="4">
            </div>
            <div class="cup4-toolbar-group">
                <label for="pair-size">Gr&ouml;sse:</label>
                <select id="pair-size" class="form-select" style="width: 130px;">
                    <option value="2">Zweier</option>
                    <option value="3">Dreier</option>
                </select>
            </div>
            <button id="generate-pairs" class="btn btn-outline-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Generieren
            </button>
            <div class="cup4-toolbar-spacer"></div>
            <div class="cup4-toolbar-actions">
                <button id="save-all" class="btn btn-primary btn-sm">
                    <i class="bi bi-save me-1"></i>Speichern
                </button>
                <button class="btn btn-outline-secondary btn-sm pdf-btn">
                    <i class="bi bi-file-pdf me-1"></i>PDF
                </button>
                <button id="delete-btn" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-trash me-1"></i>L&ouml;schen
                </button>
            </div>
        </div>
        <!-- Tabs (sichtbar < 1200px, im Toolbar f&uuml;r sticky) -->
        <div class="cup4-tabs" id="cup4-tabs">
            <div class="cup4-tab active" data-target="round1-col">Runde 1</div>
            <div class="cup4-tab" data-target="round2-col">Runde 2</div>
            <div class="cup4-tab" data-target="final-col">Finale</div>
        </div>
    </div>

    <!-- Turnierbaum -->
    <div class="cup4-bracket" id="bracket">

        <!-- Teilnehmer-Pool -->
        <div class="cup4-pool" id="participant-pool">
            <div class="cup4-pool-header" id="pool-header">
                <span class="cup4-pool-title"><i class="bi bi-people me-1"></i>Teilnehmer</span>
                <span class="cup4-pool-header-right">
                    <span class="cup4-counter" id="pool-counter">0</span>
                    <i class="bi bi-chevron-down cup4-pool-chevron"></i>
                </span>
            </div>
            <div id="pool-list"></div>
        </div>

        <!-- Runde 1 -->
        <div class="cup4-round active" id="round1-col">
            <div class="cup4-round-header">
                <span class="cup4-round-title">Runde 1</span>
                <span class="cup4-round-badge" id="r1-badge">0 Paarungen</span>
            </div>
            <div id="r1-pairs"></div>
            <div class="cup4-empty" id="r1-empty">
                <div class="cup4-empty-icon"><i class="bi bi-trophy"></i></div>
                <div class="cup4-empty-text">Noch keine Paarungen.<br>W&auml;hle Anzahl und Gr&ouml;sse, dann klicke &laquo;Generieren&raquo;.</div>
            </div>
        </div>

        <!-- Runde 2 -->
        <div class="cup4-round" id="round2-col">
            <div class="cup4-round-header">
                <span class="cup4-round-title">Runde 2</span>
                <span class="cup4-round-badge" id="r2-badge">0 Paarungen</span>
            </div>
            <!-- Winners pool for R2 (inside round column) -->
            <div id="r2-winners-pool" style="display:none; margin-bottom:0.75rem;">
                <div class="cup4-pool-header" style="border:none; padding:0; margin-bottom:0.5rem;">
                    <span class="cup4-pool-title" style="font-size:0.75rem;"><i class="bi bi-award me-1"></i>Gewinner R1</span>
                    <span style="display:flex; align-items:center; gap:0.35rem;">
                        <button class="btn btn-outline-secondary btn-sm" id="btn-nachnominierung" style="font-size:0.7rem; padding:0.15rem 0.5rem;" title="Mitglied f&uuml;r Runde 2 nachnominieren">
                            <i class="bi bi-plus-lg me-1"></i>Nachnominieren
                        </button>
                        <span class="cup4-counter" id="r2-pool-counter">0</span>
                    </span>
                </div>
                <div id="r2-pool-list"></div>
            </div>
            <div id="r2-pairs"></div>
            <div class="cup4-empty" id="r2-empty">
                <div class="cup4-empty-icon"><i class="bi bi-hourglass-split"></i></div>
                <div class="cup4-empty-text">Runde 1 zuerst abschliessen.</div>
            </div>
        </div>

        <!-- Finale -->
        <div class="cup4-round cup4-final-section" id="final-col">
            <div class="cup4-round-header">
                <span class="cup4-round-title"><i class="bi bi-trophy-fill me-1"></i>Finale</span>
                <span class="cup4-round-badge" id="final-badge">0 Finalisten</span>
            </div>
            <div id="final-list"></div>
            <div class="cup4-empty" id="final-empty">
                <div class="cup4-empty-icon"><i class="bi bi-flag"></i></div>
                <div class="cup4-empty-text">Noch keine Finalisten.</div>
            </div>
            <div id="pdf-link" class="mt-2"></div>
        </div>
    </div>

    <!-- Standcup Final (unter dem Bracket) -->
    <div class="cup4-standcup" id="standcup-section" style="display:none;">
        <h6 class="mb-3"><i class="bi bi-award me-2"></i>Standcup Final</h6>
        <div class="cup4-standcup-row">
            <span class="cup4-standcup-label">MSV Wilen</span>
            <input type="text" id="sc-name-1" class="form-control" placeholder="Teilnehmer">
            <input type="number" id="sc-result-1" class="form-control" placeholder="Res" style="width:80px;">
        </div>
        <div class="cup4-standcup-row">
            <span class="cup4-standcup-label">SV Wollerau</span>
            <input type="text" id="sc-name-2" class="form-control" placeholder="Teilnehmer">
            <input type="number" id="sc-result-2" class="form-control" placeholder="Res" style="width:80px;">
        </div>
        <div class="cup4-standcup-row">
            <span class="cup4-standcup-label">SV Freienbach</span>
            <input type="text" id="sc-name-3" class="form-control" placeholder="Teilnehmer">
            <input type="number" id="sc-result-3" class="form-control" placeholder="Res" style="width:80px;">
        </div>
        <div class="text-end mt-2">
            <button id="save-standcup" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-save me-1"></i>Standcup speichern
            </button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    /* ── CSRF für alle POST-Requests ──────── */
    $.ajaxSetup({
        beforeSend: function(xhr, settings) {
            if (settings.type === 'POST') {
                xhr.setRequestHeader('X-CSRF-TOKEN', CUP4_CSRF);
            }
        }
    });

    /* ── Globals ──────────────────────────── */
    const currentYear = new Date().getFullYear();
    let allParticipants = []; // Cache for swap-back

    function showMessage(msg, type) {
        const map = { danger: 'error', success: 'success', warning: 'warning', info: 'info' };
        msvToast(msg, map[type] || 'info');
    }

    /* ── Year Dropdown ────────────────────── */
    function initYearDropdown() {
        const $sel = $('#yearSelect').empty();
        for (let y = currentYear; y >= currentYear - 3; y--) {
            $sel.append($('<option>').val(y).text(y).prop('selected', y === currentYear));
        }
    }

    /* ── Responsive Tabs ──────────────────── */
    $(document).on('click', '.cup4-tab', function() {
        const target = $(this).data('target');
        $('.cup4-tab').removeClass('active');
        $(this).addClass('active');
        $('.cup4-round').removeClass('active');
        $('#' + target).addClass('active');
    });

    /* ── Mobile Toolbar Toggle ────────────── */
    $(document).on('click', '#toolbar-toggle', function() {
        $('#cup4-toolbar').toggleClass('expanded');
    });

    /* ── Mobile Pool Toggle ──────────────── */
    $(document).on('click', '#pool-header', function(e) {
        if ($(e.target).closest('.cup4-pool-item').length) return;
        $('#participant-pool').toggleClass('expanded');
    });

    /* ── Progress Update ──────────────────── */
    function updateProgress() {
        const r1Total = $('#r1-pairs .cup4-pair-card').length;
        const r1Done = $('#r1-pairs .cup4-pair-card.cup4-pair-complete').length;
        const r2Total = $('#r2-pairs .cup4-pair-card').length;
        const r2Done = $('#r2-pairs .cup4-pair-card.cup4-pair-complete').length;
        const fTotal = $('#final-list .cup4-final-item').length;
        const fDone = $('#final-list .cup4-final-item').filter(function() {
            return $(this).find('.cup4-result').val();
        }).length;

        $('#step-r1-count').text(r1Total > 0 ? '(' + r1Done + '/' + r1Total + ')' : '');
        $('#step-r2-count').text(r2Total > 0 ? '(' + r2Done + '/' + r2Total + ')' : '');
        $('#step-final-count').text(fTotal > 0 ? '(' + fDone + '/' + fTotal + ')' : '');

        // Steps status
        const $steps = $('.cup4-step');
        $steps.removeClass('active completed');
        if (r1Total > 0 && r1Done === r1Total && r1Total > 0) {
            $steps.eq(0).addClass('completed');
            $steps.eq(1).addClass('active');
        } else if (r1Total > 0) {
            $steps.eq(0).addClass('active');
        }
        if (r2Total > 0 && r2Done === r2Total && r2Total > 0) {
            $steps.eq(1).addClass('completed');
            $steps.eq(2).addClass('active');
        }
        if (fDone > 0 && fDone === fTotal && fTotal > 0) {
            $steps.eq(2).addClass('completed');
        }

        // Progress bar
        const total = r1Total + r2Total + fTotal;
        const done = r1Done + r2Done + fDone;
        const pct = total > 0 ? Math.round((done / total) * 100) : 0;
        $('#progress-fill').css('width', pct + '%');

        // Mini-Progress (Mobile Toolbar) — spiegelt Hauptfortschritt
        $('#mini-r1').text($('#step-r1-count').text());
        $('#mini-r2').text($('#step-r2-count').text());
        $('#mini-final').text($('#step-final-count').text());
        const $mini = $('.cup4-mini-step');
        $mini.removeClass('active completed');
        $steps.each(function(i) {
            if ($(this).hasClass('completed')) $mini.eq(i).addClass('completed');
            if ($(this).hasClass('active')) $mini.eq(i).addClass('active');
        });

        // Badges
        $('#r1-badge').text(r1Total + ' Paarung' + (r1Total !== 1 ? 'en' : ''));
        $('#r2-badge').text(r2Total + ' Paarung' + (r2Total !== 1 ? 'en' : ''));
        $('#final-badge').text(fTotal + ' Finalist' + (fTotal !== 1 ? 'en' : ''));

        // Empty states
        $('#r1-empty').toggle(r1Total === 0);
        const r2PoolCount = $('#r2-pool-list .cup4-pool-item').length;
        $('#r2-empty').toggle(r2Total === 0 && r2PoolCount === 0);
        $('#final-empty').toggle(fTotal === 0);
    }

    /* ── Pool Counter ─────────────────────── */
    function updatePoolCounter() {
        const total = allParticipants.length;
        const inPool = $('#pool-list .cup4-pool-item').length;
        $('#pool-counter').text(inPool + ' / ' + total);
    }

    function updateR2PoolCounter() {
        const cnt = $('#r2-pool-list .cup4-pool-item').length;
        $('#r2-pool-counter').text(cnt);
        $('#r2-winners-pool').toggle(cnt > 0);
    }

    /* ── Load Participants ────────────────── */
    function loadParticipants() {
        $.ajax({
            url: 'cup2/fetch_participants.php',
            data: { year: $('#yearSelect').val() },
            success: function(response) {
                let participants;
                if (typeof response === 'string') {
                    try {
                        const parsed = JSON.parse(response);
                        participants = parsed.data || parsed;
                    } catch (e) { console.error('Parse error', e); return; }
                } else {
                    participants = response.data || response;
                }

                allParticipants = participants;
                const $list = $('#pool-list').empty();
                participants.forEach(function(p) {
                    $list.append(
                        '<div class="cup4-pool-item" data-id="' + p.ID + '">' +
                        p.Name + ' ' + p.Vorname + '</div>'
                    );
                });

                initDraggable('#pool-list .cup4-pool-item');
                removeUsedParticipants();
                updatePoolCounter();
            },
            error: function() { msvToast('Fehler beim Laden der Teilnehmer', 'error'); }
        });
    }

    /* ── Draggable Init ───────────────────── */
    function initDraggable(selector) {
        $(selector).draggable({
            helper: 'clone',
            revert: 'invalid',
            appendTo: 'body',
            containment: 'window',
            distance: 5,
            scroll: false,
            start: function(event, ui) {
                $(ui.helper).css({ 'z-index': 9999, position: 'absolute', 'pointer-events': 'none' });
            }
        });
    }

    /* ── Droppable Init ───────────────────── */
    function initDroppable(container) {
        $(container).find('.cup4-drop-zone').each(function() {
            if (!$(this).data('ui-droppable')) {
                $(this).droppable({
                    accept: '.cup4-pool-item',
                    hoverClass: 'ui-droppable-hover',
                    tolerance: 'pointer',
                    drop: function(event, ui) {
                        const $zone = $(this);
                        const newId = ui.helper.data('id');
                        const newText = ui.helper.text().trim().replace(/\s*NR$/, '');

                        // Swap: if zone already occupied, return old participant to correct pool
                        const oldId = $zone.attr('data-id');
                        if (oldId) {
                            const oldName = $zone.find('.cup4-zone-name').text().trim() || $zone.text().trim();
                            const inR2 = $zone.closest('#r2-pairs').length > 0;
                            inR2 ? returnToR2Pool(oldId, oldName) : returnToPool(oldId, oldName);
                        }

                        $zone.attr('data-id', newId)
                             .html('<span class="cup4-zone-name">' + newText + '</span>' +
                                   '<button class="cup4-zone-remove" title="Entfernen" tabindex="-1">&times;</button>');
                        $zone.addClass('drop-success');
                        setTimeout(function() { $zone.removeClass('drop-success'); }, 400);

                        // Remove from all pools
                        $('#pool-list .cup4-pool-item[data-id="' + newId + '"]').remove();
                        $('#r2-pool-list .cup4-pool-item[data-id="' + newId + '"]').remove();
                        ui.helper.remove();

                        updatePoolCounter();
                        updateR2PoolCounter();
                        // Trigger winner check on card
                        updateWinnerHighlight($zone.closest('.cup4-pair-card'));
                    }
                });
            }
        });
    }

    /* ── Remove single participant from drop zone ── */
    $(document).on('click', '.cup4-zone-remove', function(e) {
        e.stopPropagation();
        const $zone = $(this).closest('.cup4-drop-zone');
        const id = $zone.attr('data-id');
        const name = $zone.find('.cup4-zone-name').text().trim();
        if (!id) return;

        const inR2 = $zone.closest('#r2-pairs').length > 0;
        inR2 ? returnToR2Pool(id, name) : returnToPool(id, name);

        // Reset drop zone to empty
        $zone.removeAttr('data-id').empty().removeClass('cup4-nachnominiert');
        // Reset winner highlighting on the card
        const $card = $zone.closest('.cup4-pair-card');
        $card.find('.cup4-participant-row').removeClass('winner loser');
        $card.removeClass('cup4-pair-complete');
        updateProgress();
        if (!inR2) extractR1WinnersToR2Pool();
    });

    // Hilfsfunktion: Namen aus Drop-Zone lesen (ggf. mit .cup4-zone-name Wrapper)
    function zoneName($zone) {
        return $zone.find('.cup4-zone-name').text().trim() || $zone.text().trim();
    }

    /* ── Return to Pool ───────────────────── */
    function returnToPool(id, name) {
        if (!id) return;
        // Check if already in pool
        if ($('#pool-list .cup4-pool-item[data-id="' + id + '"]').length > 0) return;
        const $item = $('<div class="cup4-pool-item" data-id="' + id + '">' + name + '</div>');
        $('#pool-list').append($item);
        initDraggable($item);
        updatePoolCounter();
    }

    function returnToR2Pool(id, name) {
        if (!id) return;
        if ($('#r2-pool-list .cup4-pool-item[data-id="' + id + '"]').length > 0) return;
        // Nachnominiert = nicht in R1-Gewinnern
        const isNominated = !r1WinnerIds[String(id)];
        let $item;
        if (isNominated) {
            $item = $(
                '<div class="cup4-pool-item cup4-winner-item cup4-nominated" data-id="' + id + '" data-nominated="1">' +
                '<i class="bi bi-person-plus-fill me-1" style="color:var(--cup4-info);font-size:0.7rem;"></i>' +
                name + ' <span class="cup4-nominated-badge">NR</span></div>'
            );
        } else {
            $item = $(
                '<div class="cup4-pool-item cup4-winner-item" data-id="' + id + '">' +
                '<i class="bi bi-trophy-fill me-1" style="color:var(--cup4-success);font-size:0.7rem;"></i>' +
                name + '</div>'
            );
        }
        $('#r2-pool-list').append($item);
        initDraggable($item);
        updateR2PoolCounter();
    }

    /* ── Nachnominierung Hilfsfunktionen ── */
    let r1WinnerIds = {}; // Cache: R1-Gewinner-IDs { "id": true }

    function addNominatedToPool(id, name) {
        if ($('#r2-pool-list .cup4-pool-item[data-id="' + id + '"]').length > 0) return;
        const $item = $(
            '<div class="cup4-pool-item cup4-winner-item cup4-nominated" data-id="' + id + '" data-nominated="1">' +
            '<i class="bi bi-person-plus-fill me-1" style="color:var(--cup4-info);font-size:0.7rem;"></i>' +
            name +
            ' <span class="cup4-nominated-badge">NR</span>' +
            '</div>'
        );
        $('#r2-pool-list').append($item);
        initDraggable($item);
        updateR2PoolCounter();
    }

    // Markiert Nachnominierte in R2-Paarungen (= Teilnehmer die NICHT R1-Gewinner sind)
    function markNachnominiertInR2() {
        $('#r2-pairs .cup4-drop-zone[data-id]').each(function() {
            const id = $(this).attr('data-id');
            if (id && !r1WinnerIds[id]) {
                $(this).addClass('cup4-nachnominiert');
                if ($(this).find('.cup4-nominated-badge').length === 0) {
                    $(this).append(' <span class="cup4-nominated-badge">NR</span>');
                }
            }
        });
    }

    /* ── Nachnominierung für R2 ──────────── */
    $('#btn-nachnominierung').click(function() {
        // IDs sammeln die bereits in R2 sind (Pool + Paarungen)
        const usedInR2 = {};
        $('#r2-pool-list .cup4-pool-item').each(function() {
            usedInR2[$(this).attr('data-id')] = true;
        });
        $('#r2-pairs .cup4-drop-zone[data-id]').each(function() {
            usedInR2[$(this).attr('data-id')] = true;
        });

        // R1-Verlierer aus den Paarungen extrahieren
        const losers = [];
        $('#r1-pairs .cup4-pair-card').each(function() {
            $(this).find('.cup4-participant-row.loser').each(function() {
                const $zone = $(this).find('.cup4-drop-zone');
                const id = $zone.attr('data-id');
                const name = zoneName($zone);
                if (id && name && !usedInR2[id]) {
                    losers.push({ id: id, name: name });
                }
            });
        });

        if (losers.length === 0) {
            msvToast('Keine R1-Verlierer verf\u00fcgbar', 'info');
            return;
        }

        // Kompaktes Radio-Button HTML
        let radioHtml = '<div style="text-align:left;max-height:220px;overflow-y:auto;">';
        losers.forEach(function(l, i) {
            radioHtml += '<label style="display:block;padding:0.4rem 0.5rem;margin:0;cursor:pointer;border-radius:4px;font-size:0.85rem;" ' +
                'onmouseover="this.style.background=\'#f0f0f0\'" onmouseout="this.style.background=\'transparent\'">' +
                '<input type="radio" name="nachn" value="' + l.id + '" data-name="' + l.name.replace(/"/g, '&quot;') + '" style="margin-right:0.5rem;"' +
                (i === 0 ? ' checked' : '') + '>' + l.name + '</label>';
        });
        radioHtml += '</div>';

        Swal.fire({
            title: 'NR w\u00e4hlen',
            html: radioHtml,
            width: 340,
            showCancelButton: true,
            confirmButtonText: 'Nominieren',
            cancelButtonText: 'Abbrechen',
            customClass: { popup: 'cup4-swal-compact' },
            preConfirm: function() {
                const checked = document.querySelector('input[name="nachn"]:checked');
                if (!checked) { Swal.showValidationMessage('Bitte einen Verlierer w\u00e4hlen'); return false; }
                return { id: checked.value, name: checked.getAttribute('data-name') };
            }
        }).then(function(result) {
            if (!result.isConfirmed) return;
            addNominatedToPool(result.value.id, result.value.name);
            autoGenerateR2Pairs();
            msvToast(result.value.name + ' f\u00fcr Runde 2 nominiert', 'success');
        });
    });

    /* ── Remove Used Participants ──────────── */
    function removeUsedParticipants() {
        $('#r1-pairs .cup4-drop-zone[data-id], #r2-pairs .cup4-drop-zone[data-id]').each(function() {
            const usedId = $(this).attr('data-id');
            if (usedId) {
                $('#pool-list .cup4-pool-item[data-id="' + usedId + '"]').remove();
            }
        });
        updatePoolCounter();
    }

    /* ── Winner Highlight (Realtime) ──────── */
    function updateWinnerHighlight($card) {
        if (!$card || $card.length === 0) return;
        const $rows = $card.find('.cup4-participant-row');
        $rows.removeClass('winner loser cup4-tied');
        $card.removeClass('cup4-pair-complete cup4-pair-tied');
        $card.find('.cup4-tie-pick, .cup4-tie-eliminate').remove();

        const manualWinner = $card.data('manual-winner');

        if ($rows.length === 2) {
            const id1 = $rows.eq(0).find('.cup4-drop-zone').attr('data-id');
            const id2 = $rows.eq(1).find('.cup4-drop-zone').attr('data-id');
            const r1 = parseInt($rows.eq(0).find('.cup4-result').val()) || 0;
            const r2 = parseInt($rows.eq(1).find('.cup4-result').val()) || 0;

            if (r1 === 0 && r2 === 0) { updateProgress(); return; }

            // Manueller Gewinner hat Vorrang
            if (manualWinner) {
                if (String(manualWinner) === String(id1)) {
                    $rows.eq(0).addClass('winner');
                    $rows.eq(1).addClass('loser');
                } else {
                    $rows.eq(1).addClass('winner');
                    $rows.eq(0).addClass('loser');
                }
                $card.addClass('cup4-pair-complete');
            } else if (r1 === r2 && r1 > 0) {
                // Gleichstand — manuelle Wahl nötig
                $rows.addClass('cup4-tied');
                $card.addClass('cup4-pair-tied');
                $rows.each(function() {
                    const $zone = $(this).find('.cup4-drop-zone');
                    const pid = $zone.attr('data-id');
                    const name = zoneName($zone);
                    if (pid) {
                        $(this).append(
                            '<button class="cup4-tie-pick" tabindex="-1" data-winner-id="' + pid + '" title="' + name + ' als Gewinner w&auml;hlen">' +
                            '<i class="bi bi-trophy"></i></button>'
                        );
                    }
                });
            } else if (r1 > r2) {
                $rows.eq(0).addClass('winner');
                $rows.eq(1).addClass('loser');
                $card.addClass('cup4-pair-complete');
            } else {
                $rows.eq(1).addClass('winner');
                $rows.eq(0).addClass('loser');
                $card.addClass('cup4-pair-complete');
            }
        } else if ($rows.length === 3) {
            const scores = [];
            $rows.each(function(i) {
                scores.push({
                    idx: i,
                    id: $(this).find('.cup4-drop-zone').attr('data-id'),
                    r: parseInt($(this).find('.cup4-result').val()) || 0
                });
            });

            if (scores.every(s => s.r === 0)) { updateProgress(); return; }

            const mwVal = manualWinner ? parseInt(manualWinner) : 0;

            if (mwVal < 0) {
                // Negative ManualWinner = allThreeTied-Auflösung: abs(ID) ist der Verlierer
                const loserId = String(Math.abs(mwVal));
                scores.forEach(function(s) {
                    if (String(s.id) === loserId) {
                        $rows.eq(s.idx).addClass('loser');
                    } else {
                        $rows.eq(s.idx).addClass('winner');
                    }
                });
                $card.addClass('cup4-pair-complete');
            } else if (mwVal > 0) {
                // Positive ManualWinner = bottomTwoTied-Auflösung: ID ist der Gewinner
                const mwId = String(mwVal);
                scores.forEach(function(s) {
                    $rows.eq(s.idx).addClass(String(s.id) === mwId ? 'winner' : '');
                });
                // Aus den verbleibenden den besseren als Gewinner, schlechteren als Verlierer
                const remaining = scores.filter(s => String(s.id) !== mwId).sort((a, b) => b.r - a.r);
                $rows.eq(remaining[0].idx).addClass('winner');
                $rows.eq(remaining[1].idx).addClass('loser');
                $card.addClass('cup4-pair-complete');
            } else {
                scores.sort((a, b) => b.r - a.r);

                const allThreeTied = scores[0].r === scores[2].r && scores[0].r > 0;
                const topTwoTied = !allThreeTied && scores[0].r === scores[1].r && scores[0].r > 0;
                const bottomTwoTied = !allThreeTied && !topTwoTied && scores[1].r === scores[2].r && scores[1].r > 0;

                if (allThreeTied) {
                    // Alle drei gleich — Gewinner wählen (2 Klicks nötig)
                    const firstWinner = $card.data('first-winner');
                    if (firstWinner) {
                        // 1. Gewinner bereits gewählt — 2. Gewinner wählen
                        const fwId = String(firstWinner);
                        scores.forEach(function(s) {
                            const $row = $rows.eq(s.idx);
                            if (String(s.id) === fwId) {
                                $row.addClass('winner');
                            } else {
                                $row.addClass('cup4-tied');
                                const name = zoneName($row.find('.cup4-drop-zone'));
                                $row.append(
                                    '<button class="cup4-tie-pick" tabindex="-1" data-winner-id="' + s.id + '" title="' + name + ' als 2. Gewinner w&auml;hlen">' +
                                    '<i class="bi bi-trophy"></i></button>'
                                );
                            }
                        });
                    } else {
                        // Noch kein Gewinner gewählt — 1. Gewinner wählen
                        scores.forEach(function(s) {
                            const $row = $rows.eq(s.idx);
                            $row.addClass('cup4-tied');
                            const name = zoneName($row.find('.cup4-drop-zone'));
                            $row.append(
                                '<button class="cup4-tie-pick" tabindex="-1" data-winner-id="' + s.id + '" title="' + name + ' als 1. Gewinner w&auml;hlen">' +
                                '<i class="bi bi-trophy"></i></button>'
                            );
                        });
                    }
                    $card.addClass('cup4-pair-tied');
                } else if (topTwoTied) {
                    // Pos 1+2 gleichauf — beide kommen weiter, Pos 3 verliert
                    $rows.eq(scores[0].idx).addClass('winner');
                    $rows.eq(scores[1].idx).addClass('winner');
                    $rows.eq(scores[2].idx).addClass('loser');
                    $card.addClass('cup4-pair-complete');
                } else if (bottomTwoTied) {
                    // Pos 1 gewinnt klar, Pos 2+3 gleichauf — manuelle Wahl für Platz 2
                    $rows.eq(scores[0].idx).addClass('winner');
                    [scores[1], scores[2]].forEach(function(s) {
                        const $row = $rows.eq(s.idx);
                        $row.addClass('cup4-tied');
                        const name = zoneName($row.find('.cup4-drop-zone'));
                        $row.append(
                            '<button class="cup4-tie-pick" tabindex="-1" data-winner-id="' + s.id + '" title="' + name + ' als Gewinner w&auml;hlen">' +
                            '<i class="bi bi-trophy"></i></button>'
                        );
                    });
                    $card.addClass('cup4-pair-tied');
                } else {
                    // Alle drei unterschiedlich — Top 2 gewinnen, Letzter verliert
                    $rows.eq(scores[0].idx).addClass('winner');
                    $rows.eq(scores[1].idx).addClass('winner');
                    $rows.eq(scores[2].idx).addClass('loser');
                    if (scores.every(s => s.r > 0)) $card.addClass('cup4-pair-complete');
                }
            }
        }

        updateProgress();

        // Live-Vorschau: R1-Gewinner sofort im R2-Pool anzeigen
        if ($card.closest('#r1-pairs').length) {
            extractR1WinnersToR2Pool();
        }
        // Live-Vorschau: R2-Gewinner sofort im Finale anzeigen
        if ($card.closest('#r2-pairs').length) {
            extractR2WinnersToFinale();
        }
    }

    /* ── Live R1 Winners → R2 Pool ─────────── */
    function extractR1WinnersToR2Pool() {
        // Alle IDs sammeln die bereits in R2-Paarungen platziert sind
        const usedInR2 = {};
        $('#r2-pairs .cup4-drop-zone[data-id]').each(function() {
            const id = $(this).attr('data-id');
            if (id) usedInR2[id] = true;
        });

        // Gewinner aus allen R1-Cards extrahieren
        const liveWinners = [];
        const r1Cards = $('#r1-pairs .cup4-pair-card');
        r1Cards.each(function() {
            const $card = $(this);
            // Nur abgeschlossene Paarungen (mit eindeutigem Gewinner)
            if (!$card.hasClass('cup4-pair-complete')) return;

            $card.find('.cup4-participant-row.winner').each(function() {
                const $zone = $(this).find('.cup4-drop-zone');
                const id = $zone.attr('data-id');
                const name = zoneName($zone);
                if (id && name && !usedInR2[id]) {
                    liveWinners.push({ id: id, name: name });
                }
            });
        });

        // Bestehende server-seitige Items beibehalten (haben evtl. andere Daten)
        // Nur live-preview Items aktualisieren
        const $pool = $('#r2-pool-list');

        // Entferne alte live-preview Items
        $pool.find('.cup4-pool-item[data-live-preview]').remove();

        // Füge aktuelle live-preview Items hinzu (nur wenn nicht schon als server-Item vorhanden)
        liveWinners.forEach(function(w) {
            if ($pool.find('.cup4-pool-item[data-id="' + w.id + '"]').length === 0) {
                const $item = $(
                    '<div class="cup4-pool-item cup4-winner-item" data-id="' + w.id + '" data-live-preview="1">' +
                    '<i class="bi bi-trophy-fill me-1" style="color:var(--cup4-success);font-size:0.7rem;"></i>' +
                    w.name + '</div>'
                );
                $pool.append($item);
                initDraggable($item);
            }
        });

        // Entferne live-preview Items deren Gewinner-Status sich geändert hat
        $pool.find('.cup4-pool-item[data-live-preview]').each(function() {
            const id = $(this).attr('data-id');
            const stillWinner = liveWinners.some(function(w) { return w.id === id; });
            if (!stillWinner) $(this).remove();
        });

        // R2-Pool als Droppable initialisieren (falls noch nicht geschehen)
        if ($pool.children().length > 0 && !$pool.data('ui-droppable')) {
            $pool.droppable({
                accept: '.cup4-drop-zone[data-id]',
                drop: function(event, ui) {
                    const id = ui.helper.data('id');
                    const text = ui.helper.text().trim();
                    const $item = $('<div class="cup4-pool-item cup4-winner-item" data-id="' + id + '" data-live-preview="1">' +
                                   '<i class="bi bi-trophy-fill me-1" style="color:var(--cup4-success);font-size:0.7rem;"></i>' +
                                   text + '</div>');
                    $(this).append($item);
                    initDraggable($item);
                    updateR2PoolCounter();
                }
            });
        }

        updateR2PoolCounter();
        autoGenerateR2Pairs();
    }

    /* ── Live R2 Winners → Finale ────────────── */
    function extractR2WinnersToFinale() {
        const liveFinalists = [];
        $('#r2-pairs .cup4-pair-card.cup4-pair-complete').each(function() {
            $(this).find('.cup4-participant-row.winner').each(function() {
                const $zone = $(this).find('.cup4-drop-zone');
                const id = $zone.attr('data-id');
                const name = zoneName($zone);
                if (id && name) liveFinalists.push({ id: id, name: name });
            });
        });

        const $list = $('#final-list');

        // Alte Live-Preview-Items entfernen
        $list.find('.cup4-final-item[data-live-preview]').remove();

        // Neue hinzufügen (nur wenn nicht schon als gespeichertes Item vorhanden)
        liveFinalists.forEach(function(f) {
            if ($list.find('.cup4-final-name[data-id="' + f.id + '"]').length === 0) {
                $list.append(
                    '<div class="cup4-final-item" data-live-preview="1">' +
                    '<span class="cup4-final-name" data-id="' + f.id + '">' + f.name + '</span>' +
                    '<div class="cup4-input-group"><span class="cup4-result-label">Res</span>' +
                    '<input type="number" class="cup4-result form-control" min="0" max="100"></div>' +
                    '<button class="cup4-btn-sm cup4-btn-remove" tabindex="-1" title="Entfernen"><i class="bi bi-x-lg"></i></button>' +
                    '</div>'
                );
            }
        });

        // Entferne live-preview Items deren Gewinner-Status sich geändert hat
        $list.find('.cup4-final-item[data-live-preview]').each(function() {
            const id = $(this).find('.cup4-final-name').data('id');
            if (!liveFinalists.some(function(f) { return f.id == id; })) {
                $(this).remove();
            }
        });

        updateProgress();
    }

    /* ── Auto-Generate R2 Pair Slots ───────── */
    function autoGenerateR2Pairs() {
        // Zähle verfügbare Gewinner im R2-Pool (ohne Kat. B Qualifier)
        const availableInPool = $('#r2-pool-list .cup4-pool-item:not(.cup4-katb-qualifier)').length;
        // Zähle bereits in R2-Paarungen platzierte Teilnehmer
        const placedInR2 = $('#r2-pairs .cup4-drop-zone[data-id]').length;
        const totalR2 = availableInPool + placedInR2;

        if (totalR2 < 2) return;

        // Soll-Konfiguration: bei ungerader Anzahl eine 3er-Paarung, Rest 2er
        // 5 → 1×3er + 1×2er = 2 Cards | 6 → 3×2er = 3 Cards | 7 → 1×3er + 2×2er = 3 Cards
        const need3 = (totalR2 % 2 !== 0) ? 1 : 0;
        const remaining = totalR2 - (need3 * 3);
        const need2 = Math.max(0, remaining / 2);

        // Ist-Konfiguration ermitteln
        const $filledCards = [];
        const $emptyCards = [];
        $('#r2-pairs .cup4-pair-card').each(function() {
            const $card = $(this);
            const filledZones = $card.find('.cup4-drop-zone[data-id]').length;
            const is3 = $card.find('.cup4-participant-row').length === 3;
            if (filledZones > 0) {
                $filledCards.push({ $el: $card, is3: is3 });
            } else {
                $emptyCards.push({ $el: $card, is3: is3 });
            }
        });

        let filled3 = 0, filled2 = 0, empty3 = 0, empty2 = 0;
        $filledCards.forEach(function(c) { c.is3 ? filled3++ : filled2++; });
        $emptyCards.forEach(function(c) { c.is3 ? empty3++ : empty2++; });

        // Prüfe ob Rekonfiguration nötig
        if ((filled3 + empty3) === need3 && (filled2 + empty2) === need2) return;

        // Leere Cards entfernen
        $emptyCards.forEach(function(c) { c.$el.remove(); });

        // Restbedarf berechnen (abzüglich gefüllter Cards)
        const gen3 = Math.max(0, need3 - filled3);
        const gen2 = Math.max(0, need2 - filled2);

        if (gen3 > 0) generatePairSlots(gen3, '#r2-pairs', 3);
        if (gen2 > 0) generatePairSlots(gen2, '#r2-pairs', 2);
    }

    // Bind realtime input event (delegated)
    $(document).on('input', '.cup4-result', function() {
        const $card = $(this).closest('.cup4-pair-card');
        // Bei Ergebnis-Änderung: Zwischenstatus zurücksetzen
        $card.removeData('first-winner').removeAttr('data-first-winner');
        updateWinnerHighlight($card);
    });

    // Tie-Breaker: Klick auf Gewinner-Button
    $(document).on('click', '.cup4-tie-pick', function(e) {
        e.preventDefault();
        const $card = $(this).closest('.cup4-pair-card');
        const pairId = $card.data('pair-id');
        const winnerId = $(this).data('winner-id');
        const $rows = $card.find('.cup4-participant-row');
        const isThreeWay = $rows.length === 3;
        const firstWinner = $card.data('first-winner');

        // 3-Way allThreeTied: 2-Klick-Flow
        if (isThreeWay && !firstWinner && !$card.data('manual-winner')) {
            // Prüfe ob allThreeTied (alle Resultate gleich)
            const vals = [];
            $rows.each(function() { vals.push(parseInt($(this).find('.cup4-result').val()) || 0); });
            const allEqual = vals[0] > 0 && vals.every(v => v === vals[0]);

            if (allEqual) {
                // 1. Klick: ersten Gewinner merken
                $card.data('first-winner', winnerId);
                updateWinnerHighlight($card);
                const $zone = $card.find('.cup4-drop-zone[data-id="' + winnerId + '"]');
                const name = zoneName($zone) || 'Gewinner';
                msvToast(name + ' als 1. Gewinner \u2014 bitte 2. w\u00e4hlen', 'info');
                return;
            }
        }

        if (isThreeWay && firstWinner) {
            // 2. Klick: zweiten Gewinner → Verlierer ermitteln → negative ManualWinner
            const allIds = [];
            $rows.each(function() { allIds.push($(this).find('.cup4-drop-zone').attr('data-id')); });
            const loserId = allIds.find(id => String(id) !== String(firstWinner) && String(id) !== String(winnerId));
            const negativeId = -Math.abs(parseInt(loserId));

            $card.removeData('first-winner').removeAttr('data-first-winner');
            $card.attr('data-manual-winner', negativeId).data('manual-winner', negativeId);
            updateWinnerHighlight($card);

            const $loserZone = $card.find('.cup4-drop-zone[data-id="' + loserId + '"]');
            const loserName = zoneName($loserZone);

            if (!pairId) {
                msvToast('Gewinner gew\u00e4hlt (' + loserName + ' ausgeschieden)', 'success');
                return;
            }

            // Serverseitig aktualisieren
            $.ajax({
                url: 'cup2/set_manual_winner.php',
                method: 'POST',
                data: { pair_id: pairId, winner_id: negativeId, reason: 'Dreier-Gleichstand - manuell' },
                dataType: 'json',
                success: function(resp) {
                    if (resp.success) {
                        msvToast('Gewinner gew\u00e4hlt (' + loserName + ' ausgeschieden)', 'success');
                    } else {
                        msvToast('Fehler: ' + resp.message, 'error');
                    }
                },
                error: function() { msvToast('Verbindungsfehler', 'error'); }
            });
            return;
        }

        // Standard 2-Way Tie oder bottomTwoTied: positiver ManualWinner
        $card.attr('data-manual-winner', winnerId).data('manual-winner', winnerId);
        updateWinnerHighlight($card);

        if (!pairId) {
            const $zone = $card.find('.cup4-drop-zone[data-id="' + winnerId + '"]');
            const name = zoneName($zone) || 'Gewinner';
            msvToast(name + ' als Gewinner vorgemerkt', 'success');
            return;
        }

        $.ajax({
            url: 'cup2/set_manual_winner.php',
            method: 'POST',
            data: { pair_id: pairId, winner_id: winnerId, reason: 'Gleichstand - manuell' },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    msvToast((resp.data.winner_name || 'Gewinner') + ' manuell gew\u00e4hlt', 'success');
                } else {
                    msvToast('Fehler: ' + resp.message, 'error');
                }
            },
            error: function() { msvToast('Verbindungsfehler', 'error'); }
        });
    });

    /* ── Generate Pair Slots ──────────────── */
    function generatePairSlots(count, target, size) {
        if (count <= 0) return;
        const $target = $(target);

        for (let i = 0; i < count; i++) {
            let html = '<div class="cup4-pair-card">';

            if (size == 3) {
                // 3-way
                html += buildParticipantRow() + '<div class="cup4-vs">vs</div>' +
                        buildParticipantRow() + '<div class="cup4-vs">vs</div>' +
                        buildParticipantRow();
            } else {
                // 2-way
                html += buildParticipantRow() + '<div class="cup4-vs">vs</div>' +
                        buildParticipantRow();
            }

            html += '<button class="cup4-card-remove" tabindex="-1" title="Entfernen"><i class="bi bi-x-lg"></i></button>';
            html += '</div>';

            $target.append(html);
        }

        initDroppable($target);
        updateProgress();
    }

    function buildParticipantRow() {
        return '<div class="cup4-participant-row">' +
               '<div class="cup4-drop-zone"></div>' +
               '<div class="cup4-input-group"><span class="cup4-result-label">Res</span>' +
               '<input type="number" class="cup4-result form-control" min="0" max="100"></div>' +
               '</div>';
    }

    $('#generate-pairs').click(function() {
        const count = parseInt($('#pair-count').val()) || 0;
        const size = $('#pair-size').val();
        generatePairSlots(count, '#r1-pairs', size);
    });

    /* ── Load Saved Pairs ─────────────────── */
    function loadSavedPairs(round, targetList, callback) {
        $.ajax({
            url: 'cup2/fetch_pairs.php',
            data: { round: round, year: $('#yearSelect').val() },
            success: function(data) {
                const pairs = typeof data === 'string' ? JSON.parse(data) : data;
                const $target = $(targetList).empty();

                pairs.forEach(function(pair) {
                    let manualAttr = (pair.ManualWinner != null && pair.ManualWinner !== 0) ? ' data-manual-winner="' + pair.ManualWinner + '"' : '';
                    let html = '<div class="cup4-pair-card" data-pair-id="' + pair.ID + '"' + manualAttr + '>';

                    function dropZoneHtml(id, name) {
                        return '<div class="cup4-drop-zone" data-id="' + id + '">' +
                               '<span class="cup4-zone-name">' + name + '</span>' +
                               '<button class="cup4-zone-remove" title="Entfernen" tabindex="-1">&times;</button></div>';
                    }

                    // Participant 1
                    html += '<div class="cup4-participant-row">' +
                            dropZoneHtml(pair.Participant1, pair.Name1 + ' ' + pair.Vorname1) +
                            '<div class="cup4-input-group"><span class="cup4-result-label">Res</span>' +
                            '<input type="number" class="cup4-result form-control" min="0" max="100" value="' + (pair.Result1 || '') + '"></div>' +
                            '</div>';

                    html += '<div class="cup4-vs">vs</div>';

                    // Participant 2
                    html += '<div class="cup4-participant-row">' +
                            dropZoneHtml(pair.Participant2, pair.Name2 + ' ' + pair.Vorname2) +
                            '<div class="cup4-input-group"><span class="cup4-result-label">Res</span>' +
                            '<input type="number" class="cup4-result form-control" min="0" max="100" value="' + (pair.Result2 || '') + '"></div>' +
                            '</div>';

                    // Participant 3 (3-way)
                    if (pair.Participant3 && pair.Participant3 !== 'NULL' && pair.Participant3 !== '0') {
                        html += '<div class="cup4-vs">vs</div>';
                        html += '<div class="cup4-participant-row">' +
                                dropZoneHtml(pair.Participant3, pair.Name3 + ' ' + pair.Vorname3) +
                                '<div class="cup4-input-group"><span class="cup4-result-label">Res</span>' +
                                '<input type="number" class="cup4-result form-control" min="0" max="100" value="' + (pair.Result3 || '') + '"></div>' +
                                '</div>';
                    }

                    html += '<button class="cup4-card-remove" tabindex="-1" data-pair-id="' + pair.ID + '" title="Entfernen"><i class="bi bi-x-lg"></i></button>';
                    html += '</div>';

                    $target.append(html);
                });

                // Init droppable & highlight winners
                initDroppable($target);
                $target.find('.cup4-pair-card').each(function() {
                    updateWinnerHighlight($(this));
                });

                removeUsedParticipants();
                updateProgress();

                if (typeof callback === 'function') callback();
            }
        });
    }

    /* ── Load Winners for Round 2 ─────────── */
    function loadWinnersForRound2() {
        // R1-Gewinner-IDs aus DOM cachen (ALLE R1-Gewinner, auch bereits in R2 platzierte)
        r1WinnerIds = {};
        $('#r1-pairs .cup4-participant-row.winner .cup4-drop-zone[data-id]').each(function() {
            r1WinnerIds[$(this).attr('data-id')] = true;
        });

        $.ajax({
            url: 'cup2/fetch_winners.php',
            data: { year: $('#yearSelect').val() },
            success: function(data) {
                const winners = typeof data === 'string' ? JSON.parse(data) : data;
                const $pool = $('#r2-pool-list').empty();

                // Auch die fetch_winners IDs hinzufügen (falls R1-Cards noch keine Winner-Klasse haben)
                winners.forEach(function(w) {
                    r1WinnerIds[String(w.ID)] = true;
                });

                if (winners.length > 0) {
                    // Prüfe ob es genau einen Kat. B Gewinner gibt
                    const katBWinners = winners.filter(w => w.Kategorie === 'Kat. B');
                    const hasSingleKatB = katBWinners.length === 1;

                    winners.forEach(function(w) {
                        const isKatBAutoQualifier = hasSingleKatB && w.Kategorie === 'Kat. B';
                        const extraClass = isKatBAutoQualifier ? ' cup4-katb-qualifier' : '';
                        const icon = isKatBAutoQualifier
                            ? '<i class="bi bi-star-fill me-1" style="color:#6f42c1;font-size:0.7rem;" title="Kat. B &rarr; direkt ins Finale"></i>'
                            : '<i class="bi bi-trophy-fill me-1" style="color:var(--cup4-success);font-size:0.7rem;"></i>';
                        const badge = isKatBAutoQualifier
                            ? ' <span class="cup4-katb-badge">B &rarr; Finale</span>'
                            : '';
                        $pool.append(
                            '<div class="cup4-pool-item cup4-winner-item' + extraClass + '" data-id="' + w.ID + '">' +
                            icon + w.Name + ' ' + w.Vorname + badge + '</div>'
                        );
                    });

                    // Nur nicht-KatB-Items draggable machen
                    initDraggable('#r2-pool-list .cup4-pool-item:not(.cup4-katb-qualifier)');

                    // Remove already used in R2 pairings
                    $('#r2-pairs .cup4-drop-zone[data-id]').each(function() {
                        const usedId = $(this).attr('data-id');
                        if (usedId) {
                            $('#r2-pool-list .cup4-pool-item[data-id="' + usedId + '"]').remove();
                        }
                    });

                    // Nachnominierte aus gespeicherten R2-Paarungen zurück in Pool
                    // (Teilnehmer in R2, die NICHT R1-Gewinner sind)
                    const r2UsedNominated = [];
                    $('#r2-pairs .cup4-drop-zone[data-id]').each(function() {
                        const id = $(this).attr('data-id');
                        if (id && !r1WinnerIds[id]) {
                            r2UsedNominated.push(id);
                        }
                    });

                    // Auto-generate R2 pair slots if needed
                    autoGenerateR2Pairs();

                    // Make R2 pool droppable (return items to pool)
                    if (!$('#r2-pool-list').data('ui-droppable')) {
                        $('#r2-pool-list').droppable({
                            accept: '.cup4-drop-zone[data-id]',
                            drop: function(event, ui) {
                                const id = ui.helper.data('id');
                                const text = ui.helper.text().trim();
                                const isNominated = !r1WinnerIds[String(id)];
                                let $item;
                                if (isNominated) {
                                    $item = $(
                                        '<div class="cup4-pool-item cup4-winner-item cup4-nominated" data-id="' + id + '" data-nominated="1">' +
                                        '<i class="bi bi-person-plus-fill me-1" style="color:var(--cup4-info);font-size:0.7rem;"></i>' +
                                        text + ' <span class="cup4-nominated-badge">NR</span></div>'
                                    );
                                } else {
                                    $item = $('<div class="cup4-pool-item cup4-winner-item" data-id="' + id + '">' +
                                        '<i class="bi bi-trophy-fill me-1" style="color:var(--cup4-success);font-size:0.7rem;"></i>' +
                                        text + '</div>');
                                }
                                $(this).append($item);
                                initDraggable($item);
                                updateR2PoolCounter();
                            }
                        });
                    }
                }

                // Nachnominierte in R2-Paarungen markieren
                markNachnominiertInR2();

                updateR2PoolCounter();
                loadFinalists();
            },
            error: function(xhr) {
                console.error('fetch_winners FEHLER:', xhr.status, xhr.responseText);
            }
        });
    }

    /* ── Load Finalists ───────────────────── */
    function loadFinalists() {
        $.ajax({
            url: 'cup2/fetch_final_results.php',
            data: { year: $('#yearSelect').val() },
            dataType: 'json',
            success: function(finalists) {
                const $list = $('#final-list').empty();
                if (!finalists.error && finalists.length > 0) {
                    finalists.forEach(function(f) {
                        $list.append(
                            '<div class="cup4-final-item">' +
                            '<span class="cup4-final-name" data-id="' + f.ID + '">' + f.Name + ' ' + f.Vorname + '</span>' +
                            '<div class="cup4-input-group"><span class="cup4-result-label">Res</span>' +
                            '<input type="number" class="cup4-result form-control" min="0" max="100" value="' + (f.Result || '') + '"></div>' +
                            '<button class="cup4-btn-sm cup4-btn-remove" tabindex="-1" title="Entfernen"><i class="bi bi-x-lg"></i></button>' +
                            '</div>'
                        );
                    });

                    const hasResults = finalists.some(f => f.Result !== null && f.Result !== undefined && String(f.Result).trim() !== '');
                    if (hasResults) loadStandcupData();
                }

                updateProgress();
                checkKatBFinalist();
            },
            error: function() { console.error('Fehler beim Laden der Finalergebnisse'); }
        });
    }

    /* ── Kat B Check ──────────────────────── */
    function checkKatBFinalist() {
        $.ajax({
            url: 'cup2/check_katb_finalist.php',
            data: { year: $('#yearSelect').val() },
            dataType: 'json',
            success: function(resp) {
                if (resp.has_single_katb_winner && resp.katb_finalist) {
                    const f = resp.katb_finalist;
                    // Show info
                    if ($('#katb-info').length === 0) {
                        const info = '<div id="katb-info" class="alert alert-info alert-sm py-2 px-3 mb-2" style="font-size:0.8125rem;">' +
                                     '<strong>Kat. B:</strong> ' + f.Name + ' ' + f.Vorname + ' qualifiziert sich automatisch f&uuml;r das Finale.</div>';
                        $('#final-col .cup4-round-header').after(info);
                    }
                    // Add to final list if not present
                    if ($('#final-list .cup4-final-name[data-id="' + f.ID + '"]').length === 0) {
                        $('#final-list').append(
                            '<div class="cup4-final-item">' +
                            '<span class="cup4-final-name" data-id="' + f.ID + '">' + f.Name + ' ' + f.Vorname + '</span>' +
                            '<div class="cup4-input-group"><span class="cup4-result-label">Res</span>' +
                            '<input type="number" class="cup4-result form-control" min="0" max="100" value="' + (f.Result || '') + '"></div>' +
                            '<button class="cup4-btn-sm cup4-btn-remove" tabindex="-1" title="Entfernen"><i class="bi bi-x-lg"></i></button>' +
                            '</div>'
                        );
                        updateProgress();
                    }
                }
            }
        });
    }

    /* ── Save All ─────────────────────────── */
    $('#save-all').click(function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Speichere...');

        // ALLE ManualWinner sammeln (vor dem Speichern, da Cards danach neu geladen werden)
        // Sowohl gespeicherte (pair-id bekannt) als auch neue (pair-id unbekannt)
        const allManualWinners = [];
        $('#r1-pairs .cup4-pair-card, #r2-pairs .cup4-pair-card').each(function() {
            const mw = $(this).data('manual-winner');
            if (!mw) return;
            const pairId = $(this).data('pair-id');
            const reason = parseInt(mw) < 0 ? 'Dreier-Gleichstand - manuell' : 'Gleichstand - manuell';
            if (pairId) {
                // Bereits gespeichert — Pair-ID bekannt → direkt setzen
                allManualWinners.push({ pairId: pairId, winnerId: mw, reason: reason });
            } else {
                // Noch nicht gespeichert — Teilnehmer-IDs merken um Paar nach Save zu identifizieren
                const ids = [];
                $(this).find('.cup4-drop-zone[data-id]').each(function() {
                    ids.push($(this).attr('data-id'));
                });
                const round = $(this).closest('#r1-pairs').length ? 1 : 2;
                allManualWinners.push({ winnerId: mw, p1: ids[0], p2: ids[1], round: round, reason: reason });
            }
        });

        const year = $('#yearSelect').val();
        const promises = [];

        // Round 1
        const r1 = collectPairs('#r1-pairs');
        if (r1.length > 0) {
            promises.push($.ajax({
                url: 'cup2/save_pairs.php', method: 'POST',
                data: { pairs: JSON.stringify(r1), year: year, round: 1 }
            }));
        }

        // Round 2
        const r2 = collectPairs('#r2-pairs');
        if (r2.length > 0) {
            promises.push($.ajax({
                url: 'cup2/save_pairs.php', method: 'POST',
                data: { pairs: JSON.stringify(r2), year: year, round: 2 }
            }));
        }

        // Final
        const fin = collectFinalResults();
        if (fin.length > 0) {
            promises.push($.ajax({
                url: 'cup2/save_finalresults.php', method: 'POST',
                data: { pairs: JSON.stringify(fin), year: year }
            }));
        }

        if (promises.length === 0) {
            msvToast('Keine Daten zum Speichern', 'warning');
            $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Speichern');
            return;
        }

        $.when.apply($, promises).done(function() {
            msvToast('Erfolgreich gespeichert!', 'success');
            $btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Gespeichert');
            setTimeout(function() {
                $btn.html('<i class="bi bi-save me-1"></i>Speichern');
            }, 2000);

            // ManualWinner synchronisieren (sowohl neue als auch bestehende Paare)
            if (allManualWinners.length > 0) {
                syncAllManualWinners(allManualWinners, year, function() {
                    refreshAfterSave();
                });
            } else {
                refreshAfterSave();
            }
        }).fail(function() {
            msvToast('Fehler beim Speichern!', 'error');
            $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Speichern');
        });
    });

    function syncAllManualWinners(winners, year, callback) {
        if (winners.length === 0) { if (callback) callback(); return; }
        let completed = 0;
        const total = winners.length;

        function checkDone() {
            completed++;
            if (completed === total && callback) callback();
        }

        winners.forEach(function(w) {
            if (w.pairId) {
                // Pair-ID bekannt → direkt setzen
                $.ajax({
                    url: 'cup2/set_manual_winner.php',
                    method: 'POST',
                    data: { pair_id: w.pairId, winner_id: w.winnerId, reason: w.reason },
                    complete: checkDone
                });
            } else {
                // Pair-ID unbekannt → über fetch_pairs ermitteln
                $.ajax({
                    url: 'cup2/fetch_pairs.php',
                    data: { round: w.round, year: year },
                    success: function(data) {
                        const pairs = typeof data === 'string' ? JSON.parse(data) : data;
                        const match = pairs.find(function(p) {
                            return String(p.Participant1) === String(w.p1) && String(p.Participant2) === String(w.p2);
                        });
                        if (match) {
                            $.ajax({
                                url: 'cup2/set_manual_winner.php',
                                method: 'POST',
                                data: { pair_id: match.ID, winner_id: w.winnerId, reason: w.reason },
                                complete: checkDone
                            });
                        } else {
                            checkDone();
                        }
                    },
                    error: checkDone
                });
            }
        });
    }

    function collectPairs(container) {
        const pairs = [];
        $(container + ' .cup4-pair-card').each(function() {
            const $rows = $(this).find('.cup4-participant-row');
            const ids = [];
            const results = [];

            $rows.each(function() {
                const id = $(this).find('.cup4-drop-zone').attr('data-id');
                if (id) ids.push(id);
                results.push($(this).find('.cup4-result').val() || null);
            });

            if (ids.length >= 2) {
                // Format: [id1, id2, (id3), r1, r2, (r3), ls1, ls2, (ls3)]
                // LowShot = null, aber Array-Positionen beibehalten für Backend
                const nullLowshots = ids.map(() => null);
                const pair = ids.concat(results).concat(nullLowshots);
                pairs.push(pair);
            }
        });
        return pairs;
    }

    function collectFinalResults() {
        const results = [];
        $('#final-list .cup4-final-item').each(function() {
            const id = $(this).find('.cup4-final-name').data('id');
            const r = $(this).find('.cup4-result').val();
            if (id && (r || r === 0)) {
                results.push([id, r, 0]);
            }
        });
        return results;
    }

    function refreshAfterSave() {
        // Reload pairs, then participants & winners (Reihenfolge wichtig!)
        loadSavedPairs(1, '#r1-pairs', function() {
            loadSavedPairs(2, '#r2-pairs', function() {
                loadParticipants(); // Teilnehmer-Pool neu laden (entfernt verwendete)
                loadWinnersForRound2();
            });
        });
    }

    /* ── Delete All ───────────────────────── */
    $('#delete-btn').click(async function() {
        const result = await msvConfirm(
            'Alle Resultate l&ouml;schen?',
            'S&auml;mtliche Cup-Daten f&uuml;r dieses Jahr werden gel&ouml;scht.',
            'L&ouml;schen',
            'Abbrechen'
        );
        if (!result.isConfirmed) return;

        $.ajax({
            url: 'cup2/delete_cup.php',
            method: 'POST',
            success: function() {
                msvToast('Alle Resultate gel&ouml;scht', 'success');
                initializePage();
            },
            error: function() { msvToast('Fehler beim L&ouml;schen', 'error'); }
        });
    });

    /* ── Delete Single Pair ───────────────── */
    $(document).on('click', '.cup4-card-remove, .cup4-final-item .cup4-btn-sm.cup4-btn-remove', async function() {
        const pairId = $(this).data('pair-id');
        const $card = $(this).closest('.cup4-pair-card, .cup4-final-item');

        if (!pairId) {
            // Unsaved pair — just remove from DOM and return participants to correct pool
            const wasInR1 = $card.closest('#r1-pairs').length > 0;
            const wasInR2 = $card.closest('#r2-pairs').length > 0;
            $card.find('.cup4-drop-zone[data-id]').each(function() {
                const id = $(this).attr('data-id');
                const name = zoneName($(this));
                if (wasInR2) {
                    returnToR2Pool(id, name);
                } else {
                    returnToPool(id, name);
                }
            });
            $card.remove();
            updateProgress();
            if (wasInR1) extractR1WinnersToR2Pool();
            return;
        }

        const result = await msvConfirm('Paarung l&ouml;schen?', '', 'L&ouml;schen', 'Abbrechen');
        if (!result.isConfirmed) return;

        $.ajax({
            url: 'cup2/delete_pair.php',
            method: 'POST',
            data: { pair_id: pairId },
            success: function() {
                msvToast('Paarung gel&ouml;scht', 'success');
                refreshAfterSave();
            },
            error: function() { msvToast('Fehler beim L&ouml;schen', 'error'); }
        });
    });

    /* ── PDF Export ────────────────────────── */
    $(document).on('click', '.pdf-btn', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'cup2/rangcup.php',
            data: { year: $('#yearSelect').val() },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    $('#pdf-link').html(
                        '<a href="cup2/' + resp.pdf_link + '" target="_blank" class="btn btn-sm btn-success">' +
                        '<i class="bi bi-download me-1"></i>PDF herunterladen</a>'
                    );
                } else {
                    msvToast('Fehler: ' + resp.error, 'error');
                }
            },
            error: function() { msvToast('PDF-Fehler', 'error'); }
        });
    });

    /* ── Standcup ─────────────────────────── */
    function loadStandcupData() {
        $.ajax({
            url: 'cup2/fetch_standcup_final.php',
            data: { year: $('#yearSelect').val() },
            dataType: 'json',
            success: function(data) {
                if (data.length > 0) {
                    data.forEach(function(e) {
                        if (e.club === 'MSV Wilen') {
                            $('#sc-name-1').val(e.ParticipantName);
                            $('#sc-result-1').val(e.Result);
                        } else if (e.club === 'SV Wollerau') {
                            $('#sc-name-2').val(e.ParticipantName);
                            $('#sc-result-2').val(e.Result);
                        } else if (e.club === 'SV Freienbach') {
                            $('#sc-name-3').val(e.ParticipantName);
                            $('#sc-result-3').val(e.Result);
                        }
                    });
                    $('#standcup-section').show();
                }
            }
        });
    }

    $('#save-standcup').click(function() {
        const names = [$('#sc-name-1').val(), $('#sc-name-2').val(), $('#sc-name-3').val()];
        const results = [$('#sc-result-1').val(), $('#sc-result-2').val(), $('#sc-result-3').val()];

        if (names.some(n => !n) || results.some(r => !r)) {
            msvToast('Bitte alle Felder ausf&uuml;llen', 'warning');
            return;
        }

        $.ajax({
            url: 'cup2/save_standcupfinal.php',
            method: 'POST',
            data: {
                participant1_name: names[0], participant1_result: results[0],
                participant2_name: names[1], participant2_result: results[1],
                participant3_name: names[2], participant3_result: results[2],
                year: $('#yearSelect').val()
            },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    msvToast('Standcup gespeichert!', 'success');
                } else {
                    msvToast('Fehler: ' + (resp.errors || []).join(', '), 'error');
                }
            },
            error: function() { msvToast('Verbindungsfehler', 'error'); }
        });
    });

    /* ── Check for final participants ─────── */
    function checkForFinalParticipants() {
        $.ajax({
            url: 'cup2/check_final_participants.php',
            success: function(resp) {
                if (String(resp).trim().toLowerCase() === 'true' || String(resp).trim() === '1') {
                    $('#standcup-section').show();
                }
            }
        });
    }

    /* ── Initialize ───────────────────────── */
    function initializePage() {
        $('#r1-pairs, #r2-pairs, #final-list').empty();
        $('#r2-pool-list').empty();
        $('#katb-info').remove();
        $('#standcup-section').hide();

        loadParticipants();
        loadSavedPairs(1, '#r1-pairs', function() {
            loadSavedPairs(2, '#r2-pairs', function() {
                loadWinnersForRound2();
            });
        });
        updateProgress();
    }

    $('#yearSelect').on('change', function() {
        initializePage();
        checkKatBFinalist();
    });

    initYearDropdown();
    initializePage();
    checkKatBFinalist();
    checkForFinalParticipants();
});
</script>

<?php include 'footer.inc.php'; ?>
