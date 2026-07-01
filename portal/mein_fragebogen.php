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
/* === JM-Fragebogen ===
   Karten-Chrome kommt aus portal.css (.p-card); die blaue Verlaufs-Kopfzeile
   lebt jetzt nur noch in der Accordion-Helfer-Regel weiter unten. */
.question-group {
    background: #f8f9fa;
    border-radius: var(--p-radius-sm);
    padding: var(--p-3) var(--p-4);
    margin-bottom: var(--p-3);
    border-left: 3px solid var(--primary-color);
}
.question-group label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--p-text);
    margin-bottom: var(--p-1);
    display: block;
}
.question-group .form-select, .question-group .form-control {
    font-size: 0.9rem;
    border: 2px solid #e0e4e8;
    border-radius: var(--p-radius-sm);
}
.question-group .form-select:focus, .question-group .form-control:focus {
    border-color: var(--primary-color);
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

/* === Accordion (Karten-Chrome aus dem Design-System) === */
.umfrage-accordion .accordion-item {
    background: #fff;
    border-radius: var(--p-radius) !important;
    box-shadow: var(--p-shadow);
    border: 1px solid var(--p-border) !important;
    margin-bottom: var(--p-3);
    overflow: hidden;
}
.umfrage-accordion .accordion-button {
    font-weight: 700;
    font-size: 0.92rem;
    color: #1a5c2a;
    background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
    padding: 0.85rem 1.1rem;
    border-bottom: 1px solid #a5d6a7;
    box-shadow: none !important;
    gap: 0;
}
.umfrage-accordion .accordion-button:not(.collapsed) {
    background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
    color: #1a5c2a;
}
.umfrage-accordion .accordion-button::after {
    filter: none;
    flex-shrink: 0;
    margin-left: 0.5rem;
}
/* Arbeitseinsatz-Cards: orange/rot */
.umfrage-accordion .accordion-item[data-kategorie='arbeitseinsatz'] .accordion-button,
.umfrage-accordion .accordion-item[data-kategorie='arbeitseinsatz'] .accordion-button:not(.collapsed) {
    background: linear-gradient(135deg, #fef0e4, #fde8d0);
    color: #8b4000;
    border-bottom-color: #f5c89a;
}
/* Helfer-Cards: blau */
.umfrage-accordion .accordion-item[data-kategorie='helfer'] .accordion-button,
.umfrage-accordion .accordion-item[data-kategorie='helfer'] .accordion-button:not(.collapsed) {
    background: linear-gradient(135deg, #e8f4fd, #d1ecf9);
    color: #0c5460;
    border-bottom-color: #bee5eb;
}
.umfrage-accordion .accordion-body {
    padding: 1.5rem;
}
.umfrage-accordion .accordion-button .btn-title {
    flex: 1;
    min-width: 0;
    /* Voller Titel sichtbar: umbrechen statt einzeilig kürzen */
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
    line-height: 1.3;
    text-align: left;
}
.umfrage-accordion .accordion-button { align-items: flex-start; }
.status-badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.15rem 0.45rem;
    border-radius: 12px;
    margin-left: auto;
    white-space: nowrap;
    flex-shrink: 0;
}
.status-badge.offen { background: #fff3cd; color: #856404; }
.status-badge.beantwortet { background: #d4edda; color: #155724; }
.status-badge.geschlossen { background: #e9ecef; color: #6c757d; }
.status-badge.entwurf { background: #e0cffc; color: #6f42c1; }

/* === Fragen-Builder Modal === */
.frage-card {
    background: #f8f9fa;
    border: 1px solid #e0e4e8;
    border-radius: var(--p-radius-sm);
    padding: var(--p-4);
    margin-bottom: var(--p-3);
    position: relative;
}
.frage-card .drag-handle {
    cursor: grab;
    color: #adb5bd;
    margin-right: var(--p-2);
}
.frage-card .btn-remove-frage {
    position: absolute;
    top: var(--p-2);
    right: var(--p-2);
}
.option-row {
    display: flex;
    gap: var(--p-2);
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
    border-left: 3px solid var(--primary-color);
    padding: var(--p-2) var(--p-3);
    margin-bottom: var(--p-2);
    border-radius: 0 4px 4px 0;
    font-size: 0.85rem;
}
.text-antwort .mitglied-name {
    font-weight: 600;
    color: var(--p-text);
}

/* === Checkbox-Gruppe in Umfrage-Antworten === */
.umfrage-check-group .form-check {
    margin-bottom: 0.35rem;
}
.umfrage-radio-group .form-check {
    margin-bottom: 0.35rem;
}

/* === Auto-Save Feedback === */
.question-group {
    position: relative;
}
.autosave-indicator {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.autosave-indicator.visible {
    opacity: 1;
}
.autosave-indicator.saving {
    color: #6c757d;
}
.autosave-indicator.saved {
    color: #28a745;
}
.autosave-indicator.error {
    color: #dc3545;
}
.autosave-indicator .spinner-border {
    width: 0.75rem;
    height: 0.75rem;
    border-width: 0.12em;
}

/* Beantwortet-Tags (Auswertung) */
.beantwortet-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.beantwortet-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    background: #e8f4fd;
    color: #0c5460;
    font-size: 0.8rem;
    font-weight: 500;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    white-space: nowrap;
}
.btn-tag-delete {
    background: none;
    border: none;
    color: #6c757d;
    padding: 0;
    line-height: 1;
    cursor: pointer;
    font-size: 0.85rem;
    opacity: 0.5;
    transition: opacity 0.15s, color 0.15s;
}
.btn-tag-delete:hover {
    opacity: 1;
    color: #dc3545;
}

/* Min-Auswahl Warnung */
.min-auswahl-warning {
    background: #fff3cd;
    border: 1px solid var(--warning-color);
    border-radius: var(--p-radius-sm);
    padding: var(--p-2) var(--p-3);
    margin-top: var(--p-2);
    font-size: 0.8rem;
    color: #856404;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

@media (max-width: 767.98px) {
    .umfrage-accordion .accordion-button {
        font-size: 0.82rem;
        padding: 0.75rem 0.9rem;
    }
    .umfrage-accordion .accordion-button i.bi:first-child {
        font-size: 0.9rem;
    }
    .status-badge {
        font-size: 0.65rem;
        padding: 0.12rem 0.35rem;
    }
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
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="submit" class="btn btn-success" id="btnSaveJm">
                            <i class="bi bi-save me-2"></i>Speichern
                        </button>
                        <?php if ($ist_vorstand): ?>
                        <button type="button" class="btn btn-outline-primary" onclick="openJmResults()">
                            <i class="bi bi-bar-chart me-1"></i>Auswertung
                        </button>
                        <?php endif; ?>
                    </div>
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
            <div class="col-6 col-md-4">
                <label class="form-label fw-bold">Kategorie</label>
                <select class="form-select" id="builderKategorie">
                    <option value="umfrage">Umfrage</option>
                    <option value="arbeitseinsatz">Arbeitseinsatz</option>
                    <option value="helfer">Helfer-Anfrage</option>
                </select>
            </div>
        </div>

        <hr>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-list-ol me-1"></i>Fragen</h6>
            <div>
                <label class="btn btn-outline-secondary btn-sm mb-0" for="excelImportFile" style="cursor:pointer;">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel importieren
                </label>
                <input type="file" id="excelImportFile" accept=".xlsx,.xls" style="display:none;" onchange="importExcel(this)">
            </div>
        </div>
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

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
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
        html += '<option value="0"' + (existing.waffenID === 0 ? ' selected' : '') + '>Nehme nicht teil</option>';
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
            $badge.removeClass('offen').addClass('beantwortet').html('<i class="bi bi-check-lg me-1"></i>Erledigt');
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

    const KATEGORIE_CONFIG = {
        umfrage:        { icon: 'bi-clipboard2',    label: 'Umfrage' },
        arbeitseinsatz: { icon: 'bi-tools',         label: 'Arbeitseinsatz' },
        helfer:         { icon: 'bi-people-fill',   label: 'Helfer-Anfrage' }
    };

    function renderUmfragenAccordion(umfragen) {
        const $container = $('#umfragenAccordionItems');
        $container.empty();

        if (umfragen.length === 0) {
            $container.html('<div class="text-muted small text-center py-3"><i class="bi bi-clipboard-x me-1"></i>Aktuell keine weiteren Umfragen.</div>');
            return;
        }

        umfragen.forEach(function(u) {
            let badgeClass, badgeText;
            if (u.status === 'entwurf') {
                badgeClass = 'entwurf';
                badgeText = '<i class="bi bi-pencil me-1"></i>Entwurf';
            } else if (u.beantwortet) {
                badgeClass = 'beantwortet';
                badgeText = '<i class="bi bi-check-lg me-1"></i>Erledigt';
            } else if (u.status === 'geschlossen') {
                badgeClass = 'geschlossen';
                badgeText = 'Geschlossen';
            } else {
                badgeClass = 'offen';
                badgeText = 'Offen';
            }

            const kat = KATEGORIE_CONFIG[u.kategorie] || KATEGORIE_CONFIG.umfrage;

            let html = '<div class="accordion-item" data-kategorie="' + (u.kategorie || 'umfrage') + '">';
            html += '<h2 class="accordion-header">';
            html += '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#umfrageCollapse' + u.id + '">';
            html += '<i class="bi ' + kat.icon + ' me-2"></i><span class="btn-title">' + escapeHtml(u.titel) + '</span>';
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

        if (!readonly) {
            html += '<p class="text-muted mb-3" style="font-size:0.82rem;"><i class="bi bi-cloud-check me-1"></i>Deine Antworten werden automatisch gespeichert.</p>';
        }

        html += '<div class="umfrage-autosave-form" data-umfrage-id="' + umfrage.id + '">';

        fragen.forEach(function(f) {
            const existing = antworten[f.id] || '';
            const minAttr = (f.frage_typ === 'checkbox' && f.min_auswahl) ? '" data-min-auswahl="' + f.min_auswahl : '';
            html += '<div class="question-group" data-frage-id="' + f.id + '" data-frage-typ="' + f.frage_typ + minAttr + '">';
            html += '<div class="autosave-indicator" id="autosave-' + f.id + '"></div>';
            html += '<label>' + escapeHtml(f.frage_text);
            if (f.pflichtfeld) html += ' <span class="text-danger">*</span>';
            html += '</label>';

            if (f.frage_typ === 'radio') {
                html += '<div class="umfrage-radio-group">';
                (f.optionen || []).forEach(function(opt, idx) {
                    const checked = existing === opt ? ' checked' : '';
                    const disabled = readonly ? ' disabled' : '';
                    html += '<div class="form-check">';
                    html += '<input class="form-check-input autosave-input" type="radio" name="antworten_' + f.id + '" data-frage-id="' + f.id + '" value="' + escapeHtml(opt) + '" id="r' + f.id + '_' + idx + '"' + checked + disabled + '>';
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
                    html += '<input class="form-check-input autosave-input" type="checkbox" name="antworten_' + f.id + '[]" data-frage-id="' + f.id + '" value="' + escapeHtml(opt) + '" id="c' + f.id + '_' + idx + '"' + checked + disabled + '>';
                    html += '<label class="form-check-label" for="c' + f.id + '_' + idx + '">' + escapeHtml(opt) + '</label>';
                    html += '</div>';
                });
                html += '</div>';
                // Min-Auswahl Warnung
                if (f.min_auswahl && !readonly) {
                    const selectedCount = existingArr.length;
                    const showWarn = selectedCount < f.min_auswahl;
                    html += '<div class="min-auswahl-warning" id="minwarn-' + f.id + '" style="' + (showWarn ? '' : 'display:none;') + '"><i class="bi bi-exclamation-triangle"></i> Bitte mindestens ' + f.min_auswahl + ' Optionen auswählen (aktuell: <span class="minwarn-count">' + selectedCount + '</span>)</div>';
                }
            } else if (f.frage_typ === 'dropdown') {
                html += '<select class="form-select autosave-input" data-frage-id="' + f.id + '"' + (readonly ? ' disabled' : '') + '>';
                html += '<option value="">-- Bitte wählen --</option>';
                (f.optionen || []).forEach(function(opt) {
                    const sel = existing === opt ? ' selected' : '';
                    html += '<option value="' + escapeHtml(opt) + '"' + sel + '>' + escapeHtml(opt) + '</option>';
                });
                html += '</select>';
            } else if (f.frage_typ === 'text') {
                html += '<textarea class="form-control autosave-input autosave-text" data-frage-id="' + f.id + '" rows="2"' + (readonly ? ' disabled' : '') + '>' + escapeHtml(existing) + '</textarea>';
            }

            html += '</div>';
        });

        if (umfrage.status === 'entwurf' && istVorstand) {
            html += '<div class="d-flex flex-wrap gap-2 mt-3 align-items-center">';
            html += '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="openBuilder(' + umfrage.id + ')"><i class="bi bi-pencil me-1"></i>Bearbeiten</button>';
            html += '<button type="button" class="btn btn-outline-success btn-sm" onclick="changeStatus(' + umfrage.id + ', \'activate\')"><i class="bi bi-send me-1"></i>Aktivieren</button>';
            html += '<button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteUmfrage(' + umfrage.id + ')"><i class="bi bi-trash me-1"></i>Löschen</button>';
            html += '<small class="text-muted"><i class="bi bi-eye me-1"></i>Vorschau — diese Umfrage ist noch nicht veröffentlicht.</small>';
            html += '</div>';
        } else if (!readonly && istVorstand) {
            html += '<div class="d-flex flex-wrap gap-2 mt-3">';
            html += '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="openBuilder(' + umfrage.id + ')"><i class="bi bi-pencil me-1"></i>Bearbeiten</button>';
            html += '<button type="button" class="btn btn-outline-primary btn-sm" onclick="openResults(' + umfrage.id + ')"><i class="bi bi-bar-chart me-1"></i>Auswertung</button>';
            html += '</div>';
        } else if (readonly) {
            html += '<div class="d-flex flex-wrap gap-2 mt-3">';
            if (istVorstand) {
                html += '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="openBuilder(' + umfrage.id + ')"><i class="bi bi-pencil me-1"></i>Ansehen</button>';
                html += '<button type="button" class="btn btn-outline-primary btn-sm" onclick="openResults(' + umfrage.id + ')"><i class="bi bi-bar-chart me-1"></i>Auswertung</button>';
            }
            html += '<small class="text-muted"><i class="bi bi-lock me-1"></i>Diese Umfrage ist geschlossen.</small>';
            html += '</div>';
        }

        html += '</div>';
        $body.html(html);
    }

    // ========== AUTO-SAVE LOGIK ==========
    const autosaveTimers = {};

    function showAutosaveIndicator(frageId, state) {
        const $ind = $('#autosave-' + frageId);
        $ind.removeClass('saving saved error visible');

        if (state === 'saving') {
            $ind.addClass('saving visible').html('<span class="spinner-border"></span> Speichern...');
        } else if (state === 'saved') {
            $ind.addClass('saved visible').html('<i class="bi bi-check-circle"></i> Gespeichert');
            setTimeout(function() { $ind.removeClass('visible'); }, 2000);
        } else if (state === 'error') {
            $ind.addClass('error visible').html('<i class="bi bi-exclamation-circle"></i> Fehler');
            setTimeout(function() { $ind.removeClass('visible'); }, 4000);
        }
    }

    function autosaveAntwort(umfrageId, frageId, frageTyp) {
        // Wert ermitteln
        let antwort;
        const $container = $('.question-group[data-frage-id="' + frageId + '"]');

        if (frageTyp === 'radio') {
            antwort = $container.find('input[type=radio]:checked').val() || '';
        } else if (frageTyp === 'checkbox') {
            antwort = [];
            $container.find('input[type=checkbox]:checked').each(function() {
                antwort.push($(this).val());
            });
        } else if (frageTyp === 'dropdown') {
            antwort = $container.find('select').val() || '';
        } else if (frageTyp === 'text') {
            antwort = $container.find('textarea').val() || '';
        }

        showAutosaveIndicator(frageId, 'saving');

        const postData = {
            csrf_token: csrfToken,
            umfrage_id: umfrageId,
            frage_id: frageId
        };

        // Checkbox als Array senden
        if (frageTyp === 'checkbox') {
            postData['antwort'] = antwort; // Array
        } else {
            postData['antwort'] = antwort;
        }

        $.ajax({ url: '../api/portal_umfrage_autosave.php', type: 'POST', data: postData, dataType: 'json' })
        .done(function(resp) {
            if (resp.success) {
                showAutosaveIndicator(frageId, 'saved');
                // Badge aktualisieren
                const $item = $('#umfrageCollapse' + umfrageId).closest('.accordion-item');
                $item.find('.status-badge').removeClass('offen').addClass('beantwortet').html('<i class="bi bi-check-lg me-1"></i>Erledigt');
            } else {
                showAutosaveIndicator(frageId, 'error');
            }
        })
        .fail(function() {
            showAutosaveIndicator(frageId, 'error');
        });
    }

    // Min-Auswahl Warnung aktualisieren
    function updateMinAuswahlWarning(frageId) {
        const $container = $('.question-group[data-frage-id="' + frageId + '"]');
        const minAuswahl = parseInt($container.data('min-auswahl')) || 0;
        if (minAuswahl < 1) return;

        const selectedCount = $container.find('input[type=checkbox]:checked').length;
        const $warn = $('#minwarn-' + frageId);
        if (selectedCount < minAuswahl) {
            $warn.find('.minwarn-count').text(selectedCount);
            $warn.show();
        } else {
            $warn.hide();
        }
    }

    // Auto-Save: Radio, Checkbox, Dropdown — sofort bei change
    $(document).on('change', '.autosave-input:not(.autosave-text)', function() {
        const $this = $(this);
        const frageId = $this.data('frage-id');
        const $container = $this.closest('.question-group');
        const frageTyp = $container.data('frage-typ');
        const umfrageId = $this.closest('.umfrage-autosave-form').data('umfrage-id');
        autosaveAntwort(umfrageId, frageId, frageTyp);
        // Checkbox: Min-Auswahl Warnung aktualisieren
        if (frageTyp === 'checkbox') updateMinAuswahlWarning(frageId);
    });

    // Auto-Save: Textfelder — debounced (800ms nach letztem Tastendruck)
    $(document).on('input', '.autosave-text', function() {
        const $this = $(this);
        const frageId = $this.data('frage-id');
        const $container = $this.closest('.question-group');
        const frageTyp = $container.data('frage-typ');
        const umfrageId = $this.closest('.umfrage-autosave-form').data('umfrage-id');

        // Clear vorheriger Timer
        if (autosaveTimers[frageId]) clearTimeout(autosaveTimers[frageId]);

        // Neuer Timer
        autosaveTimers[frageId] = setTimeout(function() {
            autosaveAntwort(umfrageId, frageId, frageTyp);
        }, 800);
    });

    // Auto-Save: Textfelder — auch bei blur (falls User Feld verlässt)
    $(document).on('blur', '.autosave-text', function() {
        const $this = $(this);
        const frageId = $this.data('frage-id');
        if (autosaveTimers[frageId]) {
            clearTimeout(autosaveTimers[frageId]);
            delete autosaveTimers[frageId];
        }
        const $container = $this.closest('.question-group');
        const frageTyp = $container.data('frage-typ');
        const umfrageId = $this.closest('.umfrage-autosave-form').data('umfrage-id');
        autosaveAntwort(umfrageId, frageId, frageTyp);
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
        $('#builderZielgruppe').val('alle').prop('disabled', false);
        $('#builderKategorie').val('umfrage').prop('disabled', false);
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
                    $('#builderKategorie').val(u.kategorie || 'umfrage');
                    // Geschlossene Umfragen: Metadaten + Fragen nicht editierbar
                    const fragenReadonly = (u.status === 'geschlossen');
                    if (fragenReadonly) {
                        $('#builderZielgruppe').prop('disabled', true);
                        $('#builderKategorie').prop('disabled', true);
                    }
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
        const minAuswahl = (data && data.min_auswahl) || '';
        const optionen = (data && data.optionen) || ['', ''];
        const disabled = readonly ? ' disabled' : '';

        let html = '<div class="frage-card" data-idx="' + idx + '" draggable="' + (!readonly) + '">';
        html += '<span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>';
        html += '<strong>' + (idx + 1) + '.</strong>';
        if (!readonly) html += '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-frage" onclick="$(this).closest(\'.frage-card\').remove(); renumberFragen();"><i class="bi bi-trash"></i></button>';

        html += '<div class="row g-2 mt-1">';
        html += '<div class="col-12 col-md-5"><input type="text" class="form-control form-control-sm frage-text" placeholder="Fragetext *" value="' + escapeHtml(text) + '"' + disabled + '></div>';
        html += '<div class="col-4 col-md-2"><select class="form-select form-select-sm frage-typ"' + disabled + '>';
        ['radio', 'checkbox', 'dropdown', 'text'].forEach(function(t) {
            html += '<option value="' + t + '"' + (typ === t ? ' selected' : '') + '>' + t.charAt(0).toUpperCase() + t.slice(1) + '</option>';
        });
        html += '</select></div>';
        html += '<div class="col-4 col-md-2"><div class="form-check mt-1"><input type="checkbox" class="form-check-input frage-pflicht"' + (pflicht ? ' checked' : '') + disabled + '><label class="form-check-label" style="font-size:0.8rem;">Pflicht</label></div></div>';
        html += '<div class="col-4 col-md-3 min-auswahl-wrap" style="' + (typ === 'checkbox' ? '' : 'display:none;') + '"><div class="input-group input-group-sm"><span class="input-group-text" style="font-size:0.75rem;">Mind.</span><input type="number" class="form-control form-control-sm frage-min-auswahl" placeholder="0" min="0" value="' + escapeHtml(String(minAuswahl)) + '" title="Empfohlene Mindestauswahl (Warnung, nicht blockierend)"' + disabled + '></div></div>';
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
                    $card.find('.min-auswahl-wrap').hide();
                } else {
                    const $bereich = $card.find('.optionen-bereich');
                    $bereich.show();
                    if (newTyp === 'checkbox') { $card.find('.min-auswahl-wrap').show(); } else { $card.find('.min-auswahl-wrap').hide(); }
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

    // ========== EXCEL IMPORT ==========
    window.importExcel = function(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];

        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const wb = XLSX.read(data, { type: 'array' });
                const ws = wb.Sheets[wb.SheetNames[0]];
                const rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });

                parseExcelToBuilder(rows);
                msvToast('Excel importiert! Bitte überprüfe die Fragen.', 'success');
            } catch (err) {
                console.error('Excel-Import Fehler:', err);
                msvToast('Fehler beim Lesen der Excel-Datei.', 'error');
            }
        };
        reader.readAsArrayBuffer(file);
        // Input zurücksetzen damit gleiche Datei nochmal importiert werden kann
        input.value = '';
    };

    function parseExcelToBuilder(rows) {
        // 1. Titel: Erste nicht-leere Zelle
        let titel = '';
        let beschreibung = '';
        const rollen = [];
        const termine = [];

        // Reihen durchsuchen
        let rollenRow = -1;
        let termineRow = -1;
        let headerRow = -1;

        for (let r = 0; r < rows.length; r++) {
            const row = rows[r];
            if (!row || row.length === 0) continue;

            const firstCell = String(row[0] || '').trim();

            // Titel = erste Zeile mit sinnvollem Text
            if (!titel && firstCell && firstCell.length > 3 && !firstCell.startsWith('Anfrage') && !firstCell.startsWith('Verein')) {
                titel = firstCell;
                continue;
            }

            // Header-Zeile erkennen: enthält "als:" oder "möglich am:"
            for (let c = 0; c < row.length; c++) {
                const cell = String(row[c] || '').trim().toLowerCase();
                if (cell.indexOf('als:') >= 0 || cell === 'als') { headerRow = r; break; }
                if (cell.indexOf('möglich am') >= 0) { headerRow = r; break; }
            }

            // Zeile nach Header: Rollen und Termine
            if (headerRow >= 0 && r === headerRow + 1) {
                let foundMoeglich = false;
                for (let c = 1; c < row.length; c++) {
                    const cell = String(row[c] || '').trim();
                    if (!cell) continue;

                    // Prüfe ob wir in den Header-Spalten "möglich am" erreicht haben
                    const headerCell = String((rows[headerRow] || [])[c] || '').trim().toLowerCase();
                    if (headerCell.indexOf('möglich') >= 0) foundMoeglich = true;

                    if (!foundMoeglich) {
                        // Rollen (Spalten unter "als:")
                        rollen.push(cell);
                    } else {
                        // Termine (Spalten unter "möglich am:")
                        // Mehrzeilige Zellen (Datum + Uhrzeit)
                        termine.push(cell.replace(/\n/g, ', '));
                    }
                }
            }

            // Beschreibungstext (längerer Text weiter unten)
            if (firstCell.length > 80 || firstCell.indexOf('Personalbedarf') >= 0 || firstCell.indexOf('Geschätzte') >= 0) {
                // Beschreibungstext extrahieren (Zeilen mit langem Text)
                beschreibung = firstCell.replace(/\n/g, '\n');
            }
        }

        // Builder füllen
        if (titel) $('#builderTitel').val(titel);
        if (beschreibung) $('#builderBeschreibung').val(beschreibung);
        $('#builderKategorie').val('arbeitseinsatz');

        // Bestehende Fragen löschen
        builderFrageIndex = 0;
        $('#builderFragen').empty();

        // Frage 1: Rollen (Checkbox)
        if (rollen.length >= 2) {
            addBuilderFrage({
                frage_text: 'Welche Funktionen kannst du ausüben?',
                frage_typ: 'checkbox',
                pflichtfeld: false,
                min_auswahl: null,
                optionen: rollen
            });
        }

        // Frage 2: Termine (Checkbox mit min_auswahl = 2)
        if (termine.length >= 2) {
            addBuilderFrage({
                frage_text: 'An welchen Terminen bist du verfügbar?',
                frage_typ: 'checkbox',
                pflichtfeld: false,
                min_auswahl: 2,
                optionen: termine
            });
        }

        // Frage 3: Freitext für Bemerkungen
        addBuilderFrage({
            frage_text: 'Bemerkungen',
            frage_typ: 'text',
            pflichtfeld: false,
            min_auswahl: null,
            optionen: []
        });
    }

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
                min_auswahl: $card.find('.frage-min-auswahl').val() ? parseInt($card.find('.frage-min-auswahl').val()) : null,
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
            kategorie: $('#builderKategorie').val(),
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

    // ========== JM-FRAGEBOGEN AUSWERTUNG ==========
    window.openJmResults = function() {
        $('#resultsBody').html('<div class="loading-placeholder"><div class="spinner-custom"></div><div>Lade Auswertung...</div></div>');
        const y = currentYear || new Date().getFullYear();
        $('#resultsCsvLink').attr('href', '../api/portal_jm_fragebogen_results.php?year=' + y + '&format=csv');
        $('#resultsTitle').text('Auswertung: Jahresmeisterschaft ' + y);
        new bootstrap.Modal('#resultsModal').show();

        $.getJSON('../api/portal_jm_fragebogen_results.php', { year: y })
            .done(function(resp) {
                if (!resp.success) {
                    $('#resultsBody').html('<div class="alert alert-danger">' + escapeHtml(resp.message) + '</div>');
                    return;
                }
                renderJmResults(resp);
            })
            .fail(function() {
                $('#resultsBody').html('<div class="alert alert-danger">Verbindungsfehler</div>');
            });
    };

    function renderJmResults(data) {
        const pct = data.total_mitglieder > 0 ? Math.round(data.total_beantwortet / data.total_mitglieder * 100) : 0;
        let html = '<div class="mb-3">';
        html += '<p class="text-muted">' + data.total_beantwortet + ' von ' + data.total_mitglieder + ' Mitgliedern haben geantwortet (' + pct + '%)</p>';
        html += '</div>';

        // Waffen-Verteilung
        html += '<div class="mb-4">';
        html += '<h6 class="mb-2"><i class="bi bi-crosshair me-1"></i>Waffe</h6>';
        const totalW = data.total_beantwortet || 1;
        for (const [opt, count] of Object.entries(data.waffen)) {
            const p = Math.round(count / totalW * 100) || 0;
            html += '<div class="result-bar-container">';
            html += '<div class="result-bar-label"><span>' + escapeHtml(opt) + '</span><span>' + count + ' (' + p + '%)</span></div>';
            html += '<div class="result-bar"><div class="result-bar-fill" style="width:' + p + '%"></div></div>';
            html += '</div>';
        }
        html += '</div>';

        // Mannschaft (ZSMM)
        html += '<div class="mb-4">';
        html += '<h6 class="mb-2"><i class="bi bi-people me-1"></i>ZSMM (Mannschaft)</h6>';
        for (const [key, count] of Object.entries(data.mannschaft)) {
            const label = data.teilnahme_labels[key] || key;
            const p = Math.round(count / totalW * 100) || 0;
            html += '<div class="result-bar-container">';
            html += '<div class="result-bar-label"><span>' + escapeHtml(label) + '</span><span>' + count + ' (' + p + '%)</span></div>';
            html += '<div class="result-bar"><div class="result-bar-fill" style="width:' + p + '%"></div></div>';
            html += '</div>';
        }
        html += '</div>';

        // Gruppen (GM)
        html += '<div class="mb-4">';
        html += '<h6 class="mb-2"><i class="bi bi-people-fill me-1"></i>GM (Gruppenmeisterschaft)</h6>';
        for (const [key, count] of Object.entries(data.gruppen)) {
            const label = data.teilnahme_labels[key] || key;
            const p = Math.round(count / totalW * 100) || 0;
            html += '<div class="result-bar-container">';
            html += '<div class="result-bar-label"><span>' + escapeHtml(label) + '</span><span>' + count + ' (' + p + '%)</span></div>';
            html += '<div class="result-bar"><div class="result-bar-fill" style="width:' + p + '%"></div></div>';
            html += '</div>';
        }
        html += '</div>';

        // Erweiterte Anlässe
        if (data.erweitert && data.erweitert.length > 0) {
            html += '<div class="mb-4">';
            html += '<h6 class="mb-2"><i class="bi bi-list-check me-1"></i>Anlässe</h6>';
            data.erweitert.forEach(function(e) {
                html += '<div class="mb-3"><strong style="font-size:0.9rem;">' + escapeHtml(e.bezeichnung) + '</strong>';
                for (const [key, count] of Object.entries(e.optionen)) {
                    const label = key === 'ja' ? 'Ja' : 'Nein';
                    const p = Math.round(count / totalW * 100) || 0;
                    html += '<div class="result-bar-container">';
                    html += '<div class="result-bar-label"><span>' + label + '</span><span>' + count + ' (' + p + '%)</span></div>';
                    html += '<div class="result-bar"><div class="result-bar-fill" style="width:' + p + '%"></div></div>';
                    html += '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        // Einzelantworten als kompakte Liste
        if (data.details && data.details.length > 0) {
            html += '<hr><h6 class="mb-2"><i class="bi bi-people me-1"></i>Einzelne Antworten (' + data.details.length + ')</h6>';
            data.details.forEach(function(d) {
                html += '<div style="padding:0.5rem 0; border-bottom:1px solid #f0f0f0;">';
                html += '<div class="fw-medium" style="font-size:0.88rem;">' + escapeHtml(d.name) + '</div>';
                html += '<div style="font-size:0.8rem; color:#6c757d; display:flex; flex-wrap:wrap; gap:0.35rem 0.75rem; margin-top:0.2rem;">';
                html += '<span><i class="bi bi-crosshair me-1"></i>' + escapeHtml(d.waffe) + '</span>';
                html += '<span>ZSMM: ' + jmBadge(d.mannschaft) + '</span>';
                html += '<span>GM: ' + jmBadge(d.gruppen) + '</span>';
                if (d.erweitert) {
                    d.erweitert.forEach(function(e) {
                        html += '<span>' + escapeHtml(e.bezeichnung) + ': ' + jmBadge(e.antwort === 'ja' ? 'teil' : 'nicht', e.antwort) + '</span>';
                    });
                }
                html += '</div></div>';
            });
        }

        // Nicht beantwortet
        if (data.nicht_beantwortet.length > 0) {
            html += '<hr><h6 class="text-muted mb-2">Nicht beantwortet (' + data.nicht_beantwortet.length + ')</h6>';
            html += '<p class="text-muted" style="font-size:0.85rem;">' + data.nicht_beantwortet.map(n => escapeHtml(n)).join(', ') + '</p>';
        }

        $('#resultsBody').html(html);
    }

    function jmBadge(key, label) {
        label = label || key;
        const map = {
            teil: { css: 'background:#d4edda;color:#155724;', text: label === 'teil' ? 'Teilnahme' : label },
            nicht: { css: 'background:#f8d7da;color:#721c24;', text: label === 'nicht' ? 'Nein' : label },
            evtl: { css: 'background:#fff3cd;color:#856404;', text: label === 'evtl' ? 'Evtl.' : label }
        };
        const m = map[key] || { css: 'background:#e9ecef;color:#495057;', text: label };
        return '<span style="' + m.css + 'padding:0.15rem 0.4rem;border-radius:4px;font-size:0.75rem;white-space:nowrap;">' + escapeHtml(m.text) + '</span>';
    }

    // ========== UMFRAGE AUSWERTUNG ==========
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

    let currentResultsUmfrageId = 0;

    function renderResults(data) {
        currentResultsUmfrageId = data.umfrage.id;
        const pct = data.total_mitglieder > 0 ? Math.round(data.total_beantwortet / data.total_mitglieder * 100) : 0;
        let html = '<div class="mb-3">';
        html += '<h6>' + escapeHtml(data.umfrage.titel) + '</h6>';
        html += '<p class="text-muted">' + data.total_beantwortet + ' von ' + data.total_mitglieder + ' Mitgliedern haben geantwortet (' + pct + '%)</p>';
        html += '</div>';

        data.ergebnisse.forEach(function(r, idx) {
            html += '<div class="mb-4">';
            html += '<h6 class="mb-2">' + (idx + 1) + '. ' + escapeHtml(r.frage_text) + '</h6>';

            if (r.optionen) {
                const total = r.total || 1;
                for (const [opt, count] of Object.entries(r.optionen)) {
                    const p = Math.round(count / total * 100) || 0;
                    html += '<div class="result-bar-container">';
                    html += '<div class="result-bar-label"><span>' + escapeHtml(opt) + '</span><span>' + count + ' (' + p + '%)</span></div>';
                    html += '<div class="result-bar"><div class="result-bar-fill" style="width:' + p + '%"></div></div>';
                    html += '</div>';
                }
            } else if (r.texte) {
                if (r.texte.length === 0) {
                    html += '<p class="text-muted">Keine Antworten</p>';
                } else {
                    r.texte.forEach(function(t) {
                        html += '<div class="text-antwort">';
                        html += '<span class="mitglied-name">' + escapeHtml(t.mitglied) + ':</span> ' + escapeHtml(t.antwort);
                        html += '</div>';
                    });
                }
            }

            html += '</div>';
        });

        // Beantwortet — einzeln löschbar
        if (data.beantwortet && data.beantwortet.length > 0) {
            html += '<hr><h6 class="text-muted mb-2">Beantwortet (' + data.beantwortet.length + ')</h6>';
            html += '<div class="beantwortet-list">';
            data.beantwortet.forEach(function(b) {
                html += '<span class="beantwortet-tag">' + escapeHtml(b.name);
                html += '<button type="button" class="btn-tag-delete" title="Rückmeldung löschen" onclick="deleteAntwort(' + data.umfrage.id + ', ' + b.mitglied_id + ', \'' + escapeHtml(b.name).replace(/'/g, "\\'") + '\')"><i class="bi bi-x"></i></button>';
                html += '</span>';
            });
            html += '</div>';
        }

        // Nicht beantwortet
        if (data.nicht_beantwortet.length > 0) {
            html += '<hr><h6 class="text-muted mb-2">Nicht beantwortet (' + data.nicht_beantwortet.length + ')</h6>';
            html += '<p class="text-muted" style="font-size:0.85rem;">' + data.nicht_beantwortet.map(n => escapeHtml(n)).join(', ') + '</p>';
        }

        // "Alle löschen" Button
        if (data.total_beantwortet > 0) {
            html += '<hr><button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteAlleAntworten(' + data.umfrage.id + ')">';
            html += '<i class="bi bi-trash me-1"></i>Alle ' + data.total_beantwortet + ' Rückmeldungen löschen</button>';
        }

        $('#resultsTitle').text('Auswertung: ' + data.umfrage.titel);
        $('#resultsBody').html(html);
    }

    window.deleteAntwort = async function(umfrageId, mitgliedId, name) {
        const r = await msvConfirm('Rückmeldung von <b>' + name + '</b> wirklich löschen?');
        if (!r.isConfirmed) return;

        $.post('../api/umfrage_admin.php', {
            action: 'delete_antworten',
            umfrage_id: umfrageId,
            mitglied_id: mitgliedId,
            csrf_token: csrfToken
        }, null, 'json')
        .done(function(resp) {
            msvToast(resp.message, resp.success ? 'success' : 'error');
            if (resp.success) {
                openResults(umfrageId);
                loadUmfragen();
            }
        })
        .fail(function() { msvToast('Verbindungsfehler', 'error'); });
    };

    window.deleteAlleAntworten = async function(umfrageId) {
        const r = await msvConfirmDelete('alle Rückmeldungen dieser Umfrage');
        if (!r.isConfirmed) return;

        $.post('../api/umfrage_admin.php', {
            action: 'delete_antworten',
            umfrage_id: umfrageId,
            csrf_token: csrfToken
        }, null, 'json')
        .done(function(resp) {
            msvToast(resp.message, resp.success ? 'success' : 'error');
            if (resp.success) {
                openResults(umfrageId);
                loadUmfragen();
            }
        })
        .fail(function() { msvToast('Verbindungsfehler', 'error'); });
    };

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
