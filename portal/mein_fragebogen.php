<?php
// portal/mein_fragebogen.php - Fragebogen & Umfragen (Mitglied + Vorstand-Verwaltung)
$portal_page_title = 'Fragebogen & Umfragen';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
$ist_vorstand = isVorstand();
$csrf_token = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');

$portal_page_css = "
/* === JM-Fragebogen (bestehend) === */
.fragebogen-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid #f0f0f0;
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.fragebogen-card-header {
    background: linear-gradient(135deg, #e8f4fd, #d1ecf9);
    border-bottom: 1px solid #bee5eb;
    padding: 1.25rem 1.5rem;
}
.fragebogen-card-header h2 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0c5460;
    margin: 0;
}
.fragebogen-card-body {
    padding: 1.5rem;
}
.question-group {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 1rem 1.25rem;
    margin-bottom: 1rem;
    border-left: 3px solid #3b5998;
}
.question-group label {
    font-weight: 600;
    font-size: 0.9rem;
    color: #2d3748;
    margin-bottom: 0.4rem;
    display: block;
}
.question-group .form-select, .question-group .form-control {
    font-size: 0.9rem;
    border: 2px solid #e0e4e8;
    border-radius: 6px;
}
.question-group .form-select:focus, .question-group .form-control:focus {
    border-color: #3b5998;
    box-shadow: 0 0 0 3px rgba(59,89,152,0.12);
}
.select-teil { background-color: #d4edda !important; border-color: #28a745 !important; color: #155724 !important; }
.select-nicht { background-color: #f8d7da !important; border-color: #dc3545 !important; color: #721c24 !important; }
.select-evtl { background-color: #fff3cd !important; border-color: #ffc107 !important; color: #856404 !important; }
.select-ja { background-color: #d4edda !important; border-color: #28a745 !important; color: #155724 !important; }
.select-nein { background-color: #f8d7da !important; border-color: #dc3545 !important; color: #721c24 !important; }
.year-badge {
    background: #e9ecef;
    color: #495057;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    margin-left: 0.5rem;
}
.loading-placeholder {
    text-align: center;
    padding: 3rem 1rem;
    color: #6c757d;
}
.spinner-custom {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    border: 0.25em solid #dee2e6;
    border-top-color: #3b5998;
    border-radius: 50%;
    animation: spin 0.75s linear infinite;
    margin-bottom: 1rem;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* === Accordion === */
.umfrage-accordion .accordion-item {
    background: white;
    border-radius: var(--border-radius, 0.75rem) !important;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid #f0f0f0 !important;
    margin-bottom: 1rem;
    overflow: hidden;
}
.umfrage-accordion .accordion-button {
    font-weight: 700;
    font-size: 1rem;
    color: #0c5460;
    background: linear-gradient(135deg, #e8f4fd, #d1ecf9);
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #bee5eb;
    box-shadow: none !important;
}
.umfrage-accordion .accordion-button:not(.collapsed) {
    background: linear-gradient(135deg, #e8f4fd, #d1ecf9);
    color: #0c5460;
}
.umfrage-accordion .accordion-button::after {
    filter: none;
}
.umfrage-accordion .accordion-body {
    padding: 1.5rem;
}
.status-badge {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    margin-left: 0.5rem;
}
.status-badge.offen { background: #fff3cd; color: #856404; }
.status-badge.beantwortet { background: #d4edda; color: #155724; }
.status-badge.geschlossen { background: #e9ecef; color: #6c757d; }

/* === Verwaltungsbereich === */
/* Status-Pills (für Umfrage-Cards) */
.status-pill {
    font-size: 0.7rem; font-weight: 600; padding: 0.15rem 0.5rem;
    border-radius: 10px; display: inline-block; margin-left: 0.4rem;
}
.status-pill.aktiv { background: #d4edda; color: #155724; }
.status-pill.entwurf { background: #fff3cd; color: #856404; }
.status-pill.geschlossen { background: #e9ecef; color: #6c757d; }

/* === Fragen-Builder Modal === */
.frage-card {
    background: #f8f9fa;
    border: 1px solid #e0e4e8;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    position: relative;
}
.frage-card .drag-handle {
    cursor: grab;
    color: #adb5bd;
    margin-right: 0.5rem;
}
.frage-card .btn-remove-frage {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
}
.option-row {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
    align-items: center;
}
.option-row input { flex: 1; }

/* === Auswertung === */
.result-bar-container { margin-bottom: 0.5rem; }
.result-bar-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    margin-bottom: 0.2rem;
}
.result-bar {
    height: 24px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}
.result-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b5998, #5b7bd5);
    border-radius: 4px;
    transition: width 0.5s ease;
    min-width: 2px;
}
.text-antwort {
    background: #f8f9fa;
    border-left: 3px solid #3b5998;
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 0 4px 4px 0;
    font-size: 0.85rem;
}
.text-antwort .mitglied-name {
    font-weight: 600;
    color: #2d3748;
}

/* === Checkbox-Gruppe in Umfrage-Antworten === */
.umfrage-check-group .form-check {
    margin-bottom: 0.35rem;
}
.umfrage-radio-group .form-check {
    margin-bottom: 0.35rem;
}

@media (max-width: 767.98px) {
}
";

include 'portal_header.php';
?>

<div class="portal-page-header">
    <h1><i class="bi bi-clipboard-check me-2"></i>Fragebogen & Umfragen</h1>
    <p class="subtitle">Teilnahme erfassen und Umfragen beantworten</p>
</div>

<?php if (!$mitglied_id): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Dein Konto ist keinem Mitglied zugeordnet. Bitte wende dich an den Administrator.
</div>
<?php else: ?>

<!-- ============ VERWALTUNGSBEREICH (nur Vorstand/Admin) ============ -->
<?php if ($ist_vorstand): ?>
<div class="mb-3 text-end">
    <button class="btn btn-primary btn-sm" onclick="openBuilder()">
        <i class="bi bi-plus-lg me-1"></i>Neue Umfrage
    </button>
</div>
<?php endif; ?>

<!-- ============ ACCORDION: JM-Fragebogen + Umfragen ============ -->
<div class="accordion umfrage-accordion" id="fragebogenAccordion">

    <!-- JM-Fragebogen (bestehend) -->
    <div class="accordion-item" id="jmFragebogenItem">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#jmCollapse">
                <i class="bi bi-pencil-square me-2"></i>Jahresmeisterschaft
                <span class="year-badge" id="yearBadge">...</span>
                <span class="status-badge" id="jmStatusBadge"></span>
            </button>
        </h2>
        <div id="jmCollapse" class="accordion-collapse collapse" data-bs-parent="#fragebogenAccordion">
            <div class="accordion-body">
                <div id="jmLoadingState" class="loading-placeholder">
                    <div class="spinner-custom"></div>
                    <div>Lade Fragebogen...</div>
                </div>
                <form id="fragebogenForm" style="display:none;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="year" id="formYear" value="">
                    <div id="fragebogenFields"></div>
                    <button type="submit" class="btn btn-success mt-3" id="btnSaveJm">
                        <i class="bi bi-save me-2"></i>Speichern
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Umfragen werden hier dynamisch eingefügt -->
    <div id="umfragenAccordionItems"></div>

</div>

<?php endif; ?>

<!-- ============ FRAGEN-BUILDER MODAL ============ -->
<div class="modal fade" id="builderModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-scrollable">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="builderTitle">Neue Umfrage</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="builderUmfrageId" value="0">

        <div class="row g-3 mb-3">
            <div class="col-12">
                <label class="form-label fw-bold">Titel *</label>
                <input type="text" class="form-control" id="builderTitel" maxlength="255" required>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">Beschreibung <span class="text-muted fw-normal">(optional)</span></label>
                <textarea class="form-control" id="builderBeschreibung" rows="2"></textarea>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label fw-bold">Gültig bis</label>
                <input type="date" class="form-control" id="builderGueltigBis" style="text-align:left;">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label fw-bold">Zielgruppe</label>
                <select class="form-select" id="builderZielgruppe">
                    <option value="alle">Alle Mitglieder</option>
                    <option value="vorstand">Nur Vorstand</option>
                </select>
            </div>
        </div>

        <hr>
        <h6 class="fw-bold mb-3"><i class="bi bi-list-ol me-1"></i>Fragen</h6>
        <div id="builderFragen"></div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addBuilderFrage()">
            <i class="bi bi-plus-lg me-1"></i>Frage hinzufügen
        </button>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-success" onclick="saveBuilder(false)">
            <i class="bi bi-save me-1"></i>Als Entwurf
        </button>
        <button type="button" class="btn btn-success" onclick="saveBuilder(true)">
            <i class="bi bi-send me-1"></i>Speichern & Aktivieren
        </button>
    </div>
</div>
</div>
</div>

<!-- ============ AUSWERTUNGS-MODAL ============ -->
<div class="modal fade" id="resultsModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-scrollable">
<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title" id="resultsTitle">Auswertung</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body" id="resultsBody">
        <div class="loading-placeholder">
            <div class="spinner-custom"></div>
            <div>Lade Auswertung...</div>
        </div>
    </div>
    <div class="modal-footer">
        <a href="#" id="resultsCsvLink" class="btn btn-outline-success" target="_blank">
            <i class="bi bi-download me-1"></i>CSV Export
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schliessen</button>
    </div>
</div>
</div>
</div>

<script>
$(function() {
    <?php if (!$mitglied_id): ?>return;<?php endif; ?>

    const csrfToken = '<?= $csrf_token ?>';
    const istVorstand = <?= $ist_vorstand ? 'true' : 'false' ?>;

    // ========== JM-FRAGEBOGEN (bestehend, unverändert) ==========
    let currentYear = 0;

    function updateSelectColor(el) {
        const $el = $(el);
        const val = $el.val();
        $el.removeClass('select-teil select-nicht select-evtl select-ja select-nein');
        if (val === 'teil') $el.addClass('select-teil');
        else if (val === 'nicht') $el.addClass('select-nicht');
        else if (val === 'evtl') $el.addClass('select-evtl');
        else if (val === 'ja') $el.addClass('select-ja');
        else if (val === 'nein') $el.addClass('select-nein');
    }

    function buildJmForm(waffen, defs, existing) {
        let html = '';
        html += '<div class="question-group">';
        html += '<label><i class="bi bi-crosshair me-1"></i>Mit welcher Waffe nimmst du an der Jahresmeisterschaft teil?</label>';
        html += '<select name="waffenID" class="form-select">';
        waffen.forEach(function(w) {
            const sel = (w.id === existing.waffenID) ? 'selected' : '';
            html += '<option value="' + w.id + '" ' + sel + '>' + escapeHtml(w.bezeichnung) + '</option>';
        });
        html += '</select></div>';

        html += '<div class="question-group">';
        html += '<label><i class="bi bi-people me-1"></i>Zentralschweizer Mannschaftsmeisterschaft (ZSMM)</label>';
        html += buildParticipationSelect('mannschaft', existing.mannschaft || 'nicht');
        html += '</div>';

        html += '<div class="question-group">';
        html += '<label><i class="bi bi-people-fill me-1"></i>Gruppenmeisterschaft (GM)</label>';
        html += buildParticipationSelect('gruppen', existing.gruppen || 'nicht');
        html += '</div>';

        if (defs.length > 0) {
            html += '<hr class="my-3">';
            html += '<p class="text-muted mb-2" style="font-size:0.85rem;"><i class="bi bi-list-check me-1"></i>Nimmst du an folgenden Anlässen teil?</p>';
            defs.forEach(function(d) {
                const currentVal = existing.erweitert[d.id] || 'nein';
                html += '<div class="question-group">';
                html += '<label>' + escapeHtml(d.bezeichnung) + '</label>';
                html += '<select name="erweitert[' + d.id + ']" class="form-select erweitert-select">';
                html += '<option value="nein"' + (currentVal === 'nein' ? ' selected' : '') + '>Nein</option>';
                html += '<option value="ja"' + (currentVal === 'ja' ? ' selected' : '') + '>Ja</option>';
                html += '</select></div>';
            });
        }

        $('#fragebogenFields').html(html);
        $('#fragebogenFields select').each(function() { updateSelectColor(this); });
    }

    function buildParticipationSelect(name, currentVal) {
        let html = '<select name="' + name + '" class="form-select participation-select">';
        html += '<option value="teil"' + (currentVal === 'teil' ? ' selected' : '') + '>Ich nehme teil</option>';
        html += '<option value="nicht"' + (currentVal === 'nicht' ? ' selected' : '') + '>Ich nehme nicht teil</option>';
        html += '<option value="evtl"' + (currentVal === 'evtl' ? ' selected' : '') + '>Nur wenn Gruppe füllt</option>';
        html += '</select>';
        return html;
    }

    // JM laden
    let jmBeantwortet = false;
    $.getJSON('../api/portal_fragebogen_data.php')
        .done(function(resp) {
            if (!resp.success) {
                msvToast(resp.message || 'Fehler beim Laden.', 'error');
                return;
            }
            currentYear = resp.year;
            $('#yearBadge').text(currentYear);
            $('#formYear').val(currentYear);
            buildJmForm(resp.waffen, resp.defs, resp.existing);
            $('#jmLoadingState').hide();
            $('#fragebogenForm').show();

            // Status: prüfen ob bereits beantwortet (mannschaft !== default)
            jmBeantwortet = resp.existing.mannschaft && resp.existing.mannschaft !== '';
            updateJmBadge();
        })
        .fail(function() {
            msvToast('Verbindungsfehler beim Laden des Fragebogens.', 'error');
            $('#jmLoadingState').html('<i class="bi bi-exclamation-circle text-danger me-2"></i>Fehler beim Laden.');
        });

    function updateJmBadge() {
        const $badge = $('#jmStatusBadge');
        if (jmBeantwortet) {
            $badge.removeClass('offen').addClass('beantwortet').html('<i class="bi bi-check-lg me-1"></i>Beantwortet');
        } else {
            $badge.removeClass('beantwortet').addClass('offen').text('Offen');
        }
    }

    $(document).on('change', '.participation-select, .erweitert-select', function() {
        updateSelectColor(this);
    });

    $('#fragebogenForm').on('submit', function(e) {
        e.preventDefault();
        const $btn = $('#btnSaveJm');
        const origHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        $.ajax({ url: '../api/portal_fragebogen_save.php', type: 'POST', data: $(this).serialize(), dataType: 'json' })
        .done(function(resp) {
            if (resp.success) {
                msvToast('Fragebogen gespeichert!', 'success');
                jmBeantwortet = true;
                updateJmBadge();
            } else {
                msvToast(resp.message || 'Fehler beim Speichern.', 'error');
            }
        })
        .fail(function() { msvToast('Verbindungsfehler. Bitte erneut versuchen.', 'error'); })
        .always(function() { $btn.prop('disabled', false).html(origHtml); });
    });

    // ========== UMFRAGEN (dynamisch) ==========
    function loadUmfragen() {
        $.getJSON('../api/portal_umfragen_list.php')
            .done(function(resp) {
                if (!resp.success) return;
                renderUmfragenAccordion(resp.umfragen);
            });
    }

    function renderUmfragenAccordion(umfragen) {
        const $container = $('#umfragenAccordionItems');
        $container.empty();

        if (umfragen.length === 0) return;

        umfragen.forEach(function(u) {
            let badgeClass = u.beantwortet ? 'beantwortet' : (u.status === 'geschlossen' ? 'geschlossen' : 'offen');
            let badgeText = u.beantwortet ? '<i class="bi bi-check-lg me-1"></i>Beantwortet' : (u.status === 'geschlossen' ? 'Geschlossen' : 'Offen');

            let html = '<div class="accordion-item">';
            html += '<h2 class="accordion-header">';
            html += '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#umfrageCollapse' + u.id + '">';
            html += '<i class="bi bi-clipboard2 me-2"></i>' + escapeHtml(u.titel);
            html += '<span class="status-badge ' + badgeClass + '">' + badgeText + '</span>';
            html += '</button></h2>';
            html += '<div id="umfrageCollapse' + u.id + '" class="accordion-collapse collapse" data-bs-parent="#fragebogenAccordion">';
            html += '<div class="accordion-body" data-umfrage-id="' + u.id + '" data-loaded="false">';
            html += '<div class="loading-placeholder"><div class="spinner-custom"></div><div>Lade Umfrage...</div></div>';
            html += '</div></div></div>';

            $container.append(html);
        });

        // Lazy Load: Inhalt laden wenn Accordion aufklappt
        $container.find('.accordion-collapse').on('show.bs.collapse', function() {
            const $body = $(this).find('.accordion-body');
            if ($body.data('loaded') === false || $body.data('loaded') === 'false') {
                loadUmfrageContent($body, $body.data('umfrage-id'));
            }
        });

    }

    function loadUmfrageContent($body, umfrageId) {
        $.getJSON('../api/portal_umfrage_data.php', { id: umfrageId })
            .done(function(resp) {
                if (!resp.success) {
                    $body.html('<div class="alert alert-danger">' + escapeHtml(resp.message) + '</div>');
                    return;
                }
                $body.data('loaded', true);
                renderUmfrageForm($body, resp);
            })
            .fail(function() {
                $body.html('<div class="alert alert-danger">Verbindungsfehler</div>');
            });
    }

    function renderUmfrageForm($body, data) {
        const { umfrage, fragen, antworten, readonly } = data;
        let html = '';

        if (umfrage.beschreibung) {
            html += '<p class="text-muted mb-3">' + escapeHtml(umfrage.beschreibung) + '</p>';
        }

        html += '<form class="umfrage-form" data-umfrage-id="' + umfrage.id + '">';
        html += '<input type="hidden" name="csrf_token" value="' + csrfToken + '">';
        html += '<input type="hidden" name="umfrage_id" value="' + umfrage.id + '">';

        fragen.forEach(function(f) {
            const existing = antworten[f.id] || '';
            html += '<div class="question-group">';
            html += '<label>' + escapeHtml(f.frage_text);
            if (f.pflichtfeld) html += ' <span class="text-danger">*</span>';
            html += '</label>';

            if (f.frage_typ === 'radio') {
                html += '<div class="umfrage-radio-group">';
                (f.optionen || []).forEach(function(opt, idx) {
                    const checked = existing === opt ? ' checked' : '';
                    const disabled = readonly ? ' disabled' : '';
                    html += '<div class="form-check">';
                    html += '<input class="form-check-input" type="radio" name="antworten[' + f.id + ']" value="' + escapeHtml(opt) + '" id="r' + f.id + '_' + idx + '"' + checked + disabled + '>';
                    html += '<label class="form-check-label" for="r' + f.id + '_' + idx + '">' + escapeHtml(opt) + '</label>';
                    html += '</div>';
                });
                html += '</div>';
            } else if (f.frage_typ === 'checkbox') {
                let existingArr = [];
                try { existingArr = existing ? JSON.parse(existing) : []; } catch(e) {}
                html += '<div class="umfrage-check-group">';
                (f.optionen || []).forEach(function(opt, idx) {
                    const checked = existingArr.includes(opt) ? ' checked' : '';
                    const disabled = readonly ? ' disabled' : '';
                    html += '<div class="form-check">';
                    html += '<input class="form-check-input" type="checkbox" name="antworten[' + f.id + '][]" value="' + escapeHtml(opt) + '" id="c' + f.id + '_' + idx + '"' + checked + disabled + '>';
                    html += '<label class="form-check-label" for="c' + f.id + '_' + idx + '">' + escapeHtml(opt) + '</label>';
                    html += '</div>';
                });
                html += '</div>';
            } else if (f.frage_typ === 'dropdown') {
                html += '<select name="antworten[' + f.id + ']" class="form-select"' + (readonly ? ' disabled' : '') + '>';
                html += '<option value="">-- Bitte wählen --</option>';
                (f.optionen || []).forEach(function(opt) {
                    const sel = existing === opt ? ' selected' : '';
                    html += '<option value="' + escapeHtml(opt) + '"' + sel + '>' + escapeHtml(opt) + '</option>';
                });
                html += '</select>';
            } else if (f.frage_typ === 'text') {
                html += '<textarea name="antworten[' + f.id + ']" class="form-control" rows="2"' + (readonly ? ' disabled' : '') + '>' + escapeHtml(existing) + '</textarea>';
            }

            html += '</div>';
        });

        if (!readonly) {
            html += '<div class="d-flex flex-wrap gap-2 mt-3">';
            html += '<button type="submit" class="btn btn-success"><i class="bi bi-save me-2"></i>Antworten speichern</button>';
            if (istVorstand) {
                html += '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="openBuilder(' + umfrage.id + ')"><i class="bi bi-pencil me-1"></i>Bearbeiten</button>';
                html += '<button type="button" class="btn btn-outline-primary btn-sm" onclick="openResults(' + umfrage.id + ')"><i class="bi bi-bar-chart me-1"></i>Auswertung</button>';
            }
            html += '</div>';
        } else {
            html += '<div class="d-flex flex-wrap gap-2 mt-3">';
            if (istVorstand) {
                html += '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="openBuilder(' + umfrage.id + ')"><i class="bi bi-pencil me-1"></i>Ansehen</button>';
                html += '<button type="button" class="btn btn-outline-primary btn-sm" onclick="openResults(' + umfrage.id + ')"><i class="bi bi-bar-chart me-1"></i>Auswertung</button>';
            }
            html += '<small class="text-muted"><i class="bi bi-lock me-1"></i>Diese Umfrage ist geschlossen.</small>';
            html += '</div>';
        }

        html += '</form>';
        $body.html(html);
    }

    // Umfrage-Antworten speichern
    $(document).on('submit', '.umfrage-form', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type=submit]');
        const origHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        $.ajax({ url: '../api/portal_umfrage_save.php', type: 'POST', data: $form.serialize(), dataType: 'json' })
        .done(function(resp) {
            if (resp.success) {
                msvToast('Antworten gespeichert!', 'success');
                // Badge aktualisieren
                const umfrageId = $form.data('umfrage-id');
                const $item = $('#umfrageCollapse' + umfrageId).closest('.accordion-item');
                $item.find('.status-badge').removeClass('offen').addClass('beantwortet').html('<i class="bi bi-check-lg me-1"></i>Beantwortet');
            } else {
                msvToast(resp.message || 'Fehler beim Speichern.', 'error');
            }
        })
        .fail(function() { msvToast('Verbindungsfehler. Bitte erneut versuchen.', 'error'); })
        .always(function() { $btn.prop('disabled', false).html(origHtml); });
    });

    // Umfragen laden
    loadUmfragen();

    // ========== VERWALTUNGSFUNKTIONEN (Vorstand/Admin) ==========
    <?php if ($ist_vorstand): ?>

    // Status ändern
    window.changeStatus = async function(id, action) {
        const label = action === 'activate' ? 'aktivieren' : 'schliessen';
        const r = await msvConfirm('Umfrage wirklich ' + label + '?');
        if (!r.isConfirmed) return;

        $.post('../api/umfrage_admin.php', { action: action, id: id, csrf_token: csrfToken }, null, 'json')
            .done(function(resp) {
                msvToast(resp.message, resp.success ? 'success' : 'error');
                if (resp.success) loadUmfragen();
            })
            .fail(function() { msvToast('Verbindungsfehler', 'error'); });
    };

    window.deleteUmfrage = async function(id) {
        const r = await msvConfirmDelete('diese Umfrage');
        if (!r.isConfirmed) return;

        $.post('../api/umfrage_admin.php', { action: 'delete', id: id, csrf_token: csrfToken }, null, 'json')
            .done(function(resp) {
                msvToast(resp.message, resp.success ? 'success' : 'error');
                if (resp.success) loadUmfragen();
            })
            .fail(function() { msvToast('Verbindungsfehler', 'error'); });
    };

    window.copyUmfrage = function(id) {
        $.post('../api/umfrage_admin.php', { action: 'copy', id: id, csrf_token: csrfToken }, null, 'json')
            .done(function(resp) {
                msvToast(resp.message, resp.success ? 'success' : 'error');
                if (resp.success) loadUmfragen();
            })
            .fail(function() { msvToast('Verbindungsfehler', 'error'); });
    };

    // ========== FRAGEN-BUILDER ==========
    let builderFrageIndex = 0;

    window.openBuilder = function(id) {
        builderFrageIndex = 0;
        $('#builderUmfrageId').val(id || 0);
        $('#builderTitel').val('');
        $('#builderBeschreibung').val('');
        $('#builderGueltigBis').val('');
        $('#builderZielgruppe').val('alle');
        $('#builderFragen').empty();
        $('#builderTitle').text(id ? 'Umfrage bearbeiten' : 'Neue Umfrage');

        if (id) {
            // Bestehende Umfrage laden
            $.getJSON('../api/umfrage_admin.php', { action: 'get', id: id })
                .done(function(resp) {
                    if (!resp.success) { msvToast(resp.message, 'error'); return; }
                    const u = resp.umfrage;
                    $('#builderTitel').val(u.titel);
                    $('#builderBeschreibung').val(u.beschreibung || '');
                    $('#builderGueltigBis').val(u.gueltig_bis || '');
                    $('#builderZielgruppe').val(u.zielgruppe);
                    // Zielgruppe bei aktiver Umfrage nicht mehr änderbar
                    if (u.status === 'aktiv') {
                        $('#builderZielgruppe').prop('disabled', true);
                    }

                    // Geschlossene Umfragen: Fragen nicht editierbar
                    const fragenReadonly = (u.status === 'geschlossen');
                    resp.fragen.forEach(function(f) {
                        addBuilderFrage(f, fragenReadonly);
                    });
                    if (fragenReadonly) {
                        $('.btn-add-frage, .btn-remove-frage').hide();
                    }

                    new bootstrap.Modal('#builderModal').show();
                });
        } else {
            addBuilderFrage();
            new bootstrap.Modal('#builderModal').show();
        }
    };

    window.addBuilderFrage = function(data, readonly) {
        const idx = builderFrageIndex++;
        const typ = (data && data.frage_typ) || 'radio';
        const text = (data && data.frage_text) || '';
        const pflicht = data ? data.pflichtfeld : true;
        const optionen = (data && data.optionen) || ['', ''];
        const disabled = readonly ? ' disabled' : '';

        let html = '<div class="frage-card" data-idx="' + idx + '" draggable="' + (!readonly) + '">';
        html += '<span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>';
        html += '<strong>' + (idx + 1) + '.</strong>';
        if (!readonly) html += '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-frage" onclick="$(this).closest(\'.frage-card\').remove(); renumberFragen();"><i class="bi bi-trash"></i></button>';

        html += '<div class="row g-2 mt-1">';
        html += '<div class="col-12 col-md-8"><input type="text" class="form-control form-control-sm frage-text" placeholder="Fragetext *" value="' + escapeHtml(text) + '"' + disabled + '></div>';
        html += '<div class="col-6 col-md-2"><select class="form-select form-select-sm frage-typ"' + disabled + '>';
        ['radio', 'checkbox', 'dropdown', 'text'].forEach(function(t) {
            html += '<option value="' + t + '"' + (typ === t ? ' selected' : '') + '>' + t.charAt(0).toUpperCase() + t.slice(1) + '</option>';
        });
        html += '</select></div>';
        html += '<div class="col-6 col-md-2"><div class="form-check mt-1"><input type="checkbox" class="form-check-input frage-pflicht"' + (pflicht ? ' checked' : '') + disabled + '><label class="form-check-label" style="font-size:0.8rem;">Pflicht</label></div></div>';
        html += '</div>';

        // Optionen-Bereich (nur für radio/checkbox/dropdown)
        const showOpts = (typ !== 'text');
        html += '<div class="optionen-bereich mt-2" style="' + (showOpts ? '' : 'display:none;') + '">';
        optionen.forEach(function(opt) {
            html += buildOptionRow(opt, readonly);
        });
        if (!readonly) html += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 btn-add-option" onclick="addOption(this)"><i class="bi bi-plus me-1"></i>Option</button>';
        html += '</div>';

        html += '</div>';
        $('#builderFragen').append(html);

        // Typ-Wechsel Handler
        if (!readonly) {
            $('#builderFragen .frage-card[data-idx=' + idx + '] .frage-typ').on('change', function() {
                const $card = $(this).closest('.frage-card');
                const newTyp = $(this).val();
                if (newTyp === 'text') {
                    $card.find('.optionen-bereich').hide();
                } else {
                    const $bereich = $card.find('.optionen-bereich');
                    $bereich.show();
                    if ($bereich.find('.option-row').length === 0) {
                        $bereich.prepend(buildOptionRow(''));
                        $bereich.prepend(buildOptionRow(''));
                    }
                }
            });
        }
    };

    function buildOptionRow(val, readonly) {
        const disabled = readonly ? ' disabled' : '';
        let html = '<div class="option-row">';
        html += '<input type="text" class="form-control form-control-sm option-text" placeholder="Option..." value="' + escapeHtml(val || '') + '"' + disabled + '>';
        if (!readonly) html += '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeOption(this)"><i class="bi bi-x"></i></button>';
        html += '</div>';
        return html;
    }

    window.addOption = function(btn) {
        $(btn).before(buildOptionRow(''));
    };

    window.removeOption = function(btn) {
        const $row = $(btn).closest('.option-row');
        if ($row.siblings('.option-row').length >= 2) {
            $row.remove();
        } else {
            msvToast('Mindestens 2 Optionen nötig', 'warning');
        }
    };

    window.renumberFragen = function() {
        $('#builderFragen .frage-card').each(function(i) {
            $(this).find('strong').first().text((i + 1) + '.');
        });
    };

    window.saveBuilder = function(activate) {
        const umfrageId = parseInt($('#builderUmfrageId').val()) || 0;
        const titel = $('#builderTitel').val().trim();
        if (!titel) { msvToast('Bitte Titel eingeben', 'warning'); return; }

        const fragen = [];
        let valid = true;
        $('#builderFragen .frage-card').each(function() {
            const $card = $(this);
            const f = {
                frage_text: $card.find('.frage-text').val().trim(),
                frage_typ: $card.find('.frage-typ').val(),
                pflichtfeld: $card.find('.frage-pflicht').is(':checked'),
                optionen: []
            };
            if (!f.frage_text) { valid = false; return false; }
            if (f.frage_typ !== 'text') {
                $card.find('.option-text').each(function() {
                    const v = $(this).val().trim();
                    if (v) f.optionen.push(v);
                });
                if (f.optionen.length < 2) { msvToast('Frage "' + f.frage_text + '": Mindestens 2 Optionen', 'warning'); valid = false; return false; }
            }
            fragen.push(f);
        });
        if (!valid && fragen.length === 0) { msvToast('Bitte Fragetext eingeben', 'warning'); return; }
        if (!valid) return;

        const data = {
            id: umfrageId,
            titel: titel,
            beschreibung: $('#builderBeschreibung').val().trim(),
            gueltig_bis: $('#builderGueltigBis').val() || null,
            zielgruppe: $('#builderZielgruppe').val(),
            fragen: fragen,
            activate: activate
        };

        $.post('../api/umfrage_admin.php', {
            action: 'save',
            data: JSON.stringify(data),
            csrf_token: csrfToken
        }, null, 'json')
        .done(function(resp) {
            msvToast(resp.message, resp.success ? 'success' : 'error');
            if (resp.success) {
                bootstrap.Modal.getInstance(document.getElementById('builderModal')).hide();
                loadUmfragen();
            }
        })
        .fail(function() { msvToast('Verbindungsfehler', 'error'); });
    };

    // ========== AUSWERTUNG ==========
    window.openResults = function(id) {
        $('#resultsBody').html('<div class="loading-placeholder"><div class="spinner-custom"></div><div>Lade Auswertung...</div></div>');
        $('#resultsCsvLink').attr('href', '../api/umfrage_results.php?id=' + id + '&format=csv');
        new bootstrap.Modal('#resultsModal').show();

        $.getJSON('../api/umfrage_results.php', { id: id })
            .done(function(resp) {
                if (!resp.success) {
                    $('#resultsBody').html('<div class="alert alert-danger">' + escapeHtml(resp.message) + '</div>');
                    return;
                }
                renderResults(resp);
            })
            .fail(function() {
                $('#resultsBody').html('<div class="alert alert-danger">Verbindungsfehler</div>');
            });
    };

    function renderResults(data) {
        const pct = data.total_mitglieder > 0 ? Math.round(data.total_beantwortet / data.total_mitglieder * 100) : 0;
        let html = '<div class="mb-3">';
        html += '<h6>' + escapeHtml(data.umfrage.titel) + '</h6>';
        html += '<p class="text-muted">' + data.total_beantwortet + ' von ' + data.total_mitglieder + ' Mitgliedern haben geantwortet (' + pct + '%)</p>';
        html += '</div>';

        data.ergebnisse.forEach(function(r, idx) {
            html += '<div class="mb-4">';
            html += '<h6 class="mb-2">' + (idx + 1) + '. ' + escapeHtml(r.frage_text) + '</h6>';

            if (r.optionen) {
                // Radio/Checkbox/Dropdown
                const total = r.total || 1;
                for (const [opt, count] of Object.entries(r.optionen)) {
                    const p = Math.round(count / total * 100) || 0;
                    html += '<div class="result-bar-container">';
                    html += '<div class="result-bar-label"><span>' + escapeHtml(opt) + '</span><span>' + count + ' (' + p + '%)</span></div>';
                    html += '<div class="result-bar"><div class="result-bar-fill" style="width:' + p + '%"></div></div>';
                    html += '</div>';
                }
            } else if (r.texte) {
                // Freitext
                if (r.texte.length === 0) {
                    html += '<p class="text-muted">Keine Antworten</p>';
                } else {
                    r.texte.forEach(function(t) {
                        html += '<div class="text-antwort"><span class="mitglied-name">' + escapeHtml(t.mitglied) + ':</span> ' + escapeHtml(t.antwort) + '</div>';
                    });
                }
            }

            html += '</div>';
        });

        // Nicht beantwortet
        if (data.nicht_beantwortet.length > 0) {
            html += '<hr><h6 class="text-muted mb-2">Nicht beantwortet (' + data.nicht_beantwortet.length + ')</h6>';
            html += '<p class="text-muted" style="font-size:0.85rem;">' + data.nicht_beantwortet.map(n => escapeHtml(n)).join(', ') + '</p>';
        }

        $('#resultsTitle').text('Auswertung: ' + data.umfrage.titel);
        $('#resultsBody').html(html);
    }

    <?php endif; ?>

    // ========== HILFSFUNKTIONEN ==========
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        if (isNaN(d)) return dateStr;
        return d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear();
    }
});
</script>

<?php include 'portal_footer.php'; ?>
