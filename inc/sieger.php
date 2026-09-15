<?php
// sieger.php – Sieger der letzten Jahre (Kategorie-Karten pro Jahr, Slide-Panel zum Erfassen/Bearbeiten)
include 'dbconnect.inc.php';

$page_specific_css = <<<'CSS'
/* === CATEGORY GRID === */
.desktop-cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0.6rem;
}

/* === ÜBERGEORDNETE GRUPPEN === */
.sieger-group { margin-bottom: 1.5rem; }
.sieger-group:last-child { margin-bottom: 0; }
.sieger-group-title {
    display: flex; align-items: center; gap: 0.4rem;
    margin: 0 0 0.6rem; padding-bottom: 0.35rem;
    font-size: 0.95rem; font-weight: 700; color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
}
.sieger-group-title i { color: #64748b; }

/* === CATEGORY CARD (kompakt) === */
.cat-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.cat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.cat-card-head {
    display: flex; align-items: center; gap: 0.45rem;
    padding: 0.3rem 0.6rem;
    border-bottom: 1px solid #f1f5f9;
}
.cat-card-head h6 { margin: 0; font-weight: 600; font-size: 0.82rem; color: #1e293b; }
.cat-card-body { padding: 0.1rem 0 0.2rem; }

/* === CATEGORY ICON === */
.cat-icon {
    width: 28px; height: 28px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; flex-shrink: 0;
}
.cat-icon.gold   { background: #fff8e1; color: #ffc107; border: 1px solid #ffe082; }
.cat-icon.blue   { background: #dbeafe; color: #2563eb; border: 1px solid #bfdbfe; }
.cat-icon.green  { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
.cat-icon.purple { background: #f3e8ff; color: #7c3aed; border: 1px solid #ddd6fe; }

/* === WINNER ROW (Klick/Enter = Bearbeiten, Aktionen immer sichtbar) === */
.winner-row {
    display: flex; align-items: center; gap: 0.45rem;
    padding: 0.25rem 0.6rem;
    border-bottom: 1px solid #f8fafc;
    cursor: pointer; transition: background 0.15s;
}
.winner-row:hover, .winner-row:focus-visible { background: rgba(99,102,241,0.07); outline: none; }
.winner-row:focus-visible { box-shadow: inset 0 0 0 2px rgba(99,102,241,0.35); }
.winner-row:last-child { border-bottom: none; }
.winner-name  { flex: 1; font-weight: 500; color: #334155; font-size: 0.8rem; }
.winner-score { font-weight: 700; color: #1e293b; font-size: 0.8rem; min-width: 38px; text-align: right; }
.winner-action { display: flex; gap: 0.2rem; }
.winner-action .btn {
    padding: 0.1rem 0.35rem; font-size: 0.72rem; line-height: 1.3;
    opacity: 0.55; transition: opacity 0.15s;
}
.winner-row:hover .winner-action .btn, .winner-row:focus-within .winner-action .btn { opacity: 1; }

/* === EMPTY STATE === */
.empty-state { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
.empty-state i { font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 0.75rem; }

/* === MOBILE === */
@media (max-width: 767.98px) {
    .desktop-cards-container { grid-template-columns: 1fr; gap: 0.75rem; }
    .cat-card-head { padding: 0.4rem 0.625rem; }
    .cat-card-head h6 { font-size: 0.85rem; }
    .cat-card-body { padding: 0.15rem 0 0.35rem; }
    .winner-row { padding: 0.3rem 0.625rem; }
    .winner-action .btn { opacity: 1; }
    .winner-name, .winner-score { font-size: 0.85rem; }
}
CSS;

include 'header.inc.php';

$currentYear = (int)date('Y');
$lastYear    = $currentYear - 1;
// Vorhandene Jahre aus der DB mit dem Standardbereich zusammenführen, damit auch Jahre
// ohne bisherige Sieger wählbar sind.
$years = [];
$result = $conn->query("SELECT DISTINCT year FROM sieger");
if ($result) {
    while ($row = $result->fetch_assoc()) $years[] = (int)$row['year'];
}
for ($y = $currentYear + 1; $y >= $currentYear - 2; $y--) $years[] = $y;
$years = array_unique($years);
rsort($years);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-default">
                <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <?php $page_title = 'Sieger der letzten Jahre'; include 'partials/page_header.inc.php'; ?>

                <div class="content-background">
                    <!-- Jahr + Erfassen (Toolbar wie auf den Ranglisten-Seiten) -->
                    <div class="export-toolbar mb-3">
                        <div class="export-toolbar-head">
                            <label for="filterYear" class="export-year-label mb-0"><i class="bi bi-calendar3 me-1"></i>Jahr:</label>
                            <select id="filterYear" class="form-select form-select-sm export-year-select">
                                <?php foreach ($years as $y): ?>
                                    <option value="<?= $y ?>" <?= $y === $lastYear ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-success ms-auto" id="btnAddSieger">
                                <i class="bi bi-plus-lg me-1"></i>Hinzufügen
                            </button>
                        </div>
                    </div>

                    <!-- Kategorie-Karten -->
                    <div id="siegerContainer">
                        <div class="text-center py-5">
                            <div class="spinner-border spinner-border-sm me-2"></div>
                            Lade Sieger...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Slide-Panel: Sieger hinzufügen/bearbeiten – zentrale Struktur via inc/partials/side_panel.inc.php -->
<?php
$panel_id         = 'addSiegerPanel';
$panel_class      = 'add-sieger-panel';
$panel_overlay_id = 'addPanelOverlay';
$panel_close_id   = 'addPanelClose';
$panel_width      = '460px';
$panel_title      = '<i class="bi bi-plus-circle me-2"></i>Neuen Sieger hinzufügen';
ob_start();
?>
        <form id="addSiegerForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
                <label class="panel-label" for="memberSelect"><i class="bi bi-person me-1"></i>Mitglied</label>
                <select name="member_id" class="form-select form-select-sm" id="memberSelect">
                    <option value="">– Mitglied wählen –</option>
                    <?php
                    $result = $conn->query("SELECT ID, Vorname, Name FROM mitglieder WHERE Verstorben = 0 ORDER BY Name, Vorname");
                    if ($result) while ($row = $result->fetch_assoc()) {
                        echo "<option value='" . (int)$row['ID'] . "'>" . htmlspecialchars($row['Name'] . ' ' . $row['Vorname']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="panel-label" for="siegerdefSelect"><i class="bi bi-trophy me-1"></i>Auszeichnung</label>
                <select name="siegerdef" class="form-select form-select-sm" id="siegerdefSelect" required>
                    <option value="">– Kategorie wählen –</option>
                    <?php
                    $result = $conn->query("SELECT ID, Bezeichnung FROM siegerdef ORDER BY Bezeichnung");
                    if ($result) while ($row = $result->fetch_assoc()) {
                        echo "<option value='" . (int)$row['ID'] . "'>" . htmlspecialchars($row['Bezeichnung']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="panel-label" for="wertInput"><i class="bi bi-123 me-1"></i>Resultat / Punkte</label>
                <input type="number" name="wert" id="wertInput" class="form-control form-control-sm" min="1" required placeholder="z.B. 98">
            </div>

            <div class="mb-3">
                <label class="panel-label" for="yearInput"><i class="bi bi-calendar3 me-1"></i>Jahr</label>
                <input type="number" name="year" id="yearInput" class="form-control form-control-sm" min="2000" max="2100" value="<?= $lastYear ?>" required>
            </div>

            <hr>
            <button type="submit" class="btn btn-outline-success btn-sm w-100" id="siegerSubmitBtn">
                <i class="bi bi-plus-circle me-1"></i>Sieger hinzufügen
            </button>
        </form>
<?php
$panel_body = ob_get_clean();
include 'partials/side_panel.inc.php';
?>

<script>
// Kategorie-Konfiguration (Icon/Farbe/Anzeigename pro siegerdef.Bezeichnung)
const CATEGORY_CONFIG = {
    'Kunst':                { icon: 'bi-palette',    color: 'gold',   label: 'Kunst' },
    'Glück':                { icon: 'bi-clover',     color: 'green',  label: 'Glück' },
    'Zabigstich':           { icon: 'bi-bullseye',   color: 'purple', label: 'Zabigstich' },
    'Endstich':             { icon: 'bi-crosshair',  color: 'blue',   label: 'Endstich' },
    'Schwini':              { icon: 'bi-star',       color: 'purple', label: 'Schwini' },
    'EndschiessenA':        { icon: 'bi-trophy',     color: 'gold',   label: 'Endschiessen Kat. A' },
    'EndschiessenB':        { icon: 'bi-trophy',     color: 'gold',   label: 'Endschiessen Kat. B' },
    'HeimmeisterschaftA':   { icon: 'bi-house',      color: 'blue',   label: 'Heimmeisterschaft Kat. A' },
    'HeimmeisterschaftB':   { icon: 'bi-house',      color: 'blue',   label: 'Heimmeisterschaft Kat. B' },
    'KantonalstichA':       { icon: 'bi-flag',       color: 'green',  label: 'Kantonalstich Kat. A' },
    'KantonalstichB':       { icon: 'bi-flag',       color: 'green',  label: 'Kantonalstich Kat. B' },
    'JahresmeisterschaftA': { icon: 'bi-award',      color: 'gold',   label: 'Jahresmeisterschaft Kat. A' },
    'JahresmeisterschaftB': { icon: 'bi-award',      color: 'gold',   label: 'Jahresmeisterschaft Kat. B' },
};
const DEFAULT_CONFIG = { icon: 'bi-trophy', color: 'blue' };

const ADD_TITLE  = '<i class="bi bi-plus-circle me-2"></i>Neuen Sieger hinzufügen';
const EDIT_TITLE = '<i class="bi bi-pencil-square me-2"></i>Sieger bearbeiten';
const ADD_BTN    = '<i class="bi bi-plus-circle me-1"></i>Sieger hinzufügen';
const EDIT_BTN   = '<i class="bi bi-save me-1"></i>Änderungen speichern';

function enhanceCategoryCards() {
    document.querySelectorAll('.cat-card').forEach(card => {
        const config = CATEGORY_CONFIG[card.dataset.category] || DEFAULT_CONFIG;
        const iconEl = card.querySelector('[data-cat-icon]');
        if (iconEl) {
            iconEl.className = `cat-icon ${config.color}`;
            iconEl.innerHTML = `<i class="bi ${config.icon}"></i>`;
        }
        const labelEl = card.querySelector('[data-cat-label]');
        if (labelEl && config.label) labelEl.textContent = config.label;
    });
}

function loadSieger(year) {
    const container = $('#siegerContainer');
    container.html(`<div class="text-center py-5"><div class="spinner-border spinner-border-sm me-2"></div>Lade Sieger für ${$('<i>').text(year).html()}...</div>`);
    $.get('sieger/load_sieger.php', { year })
        .done(html => { container.html(html); enhanceCategoryCards(); })
        .fail(() => {
            container.html('<div class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden der Sieger</div>');
            msvToast('Fehler beim Laden der Sieger', 'error');
        });
}

// Panel öffnen/schliessen
function isPanelOpen() { return $('#addSiegerPanel').hasClass('open'); }
function openAddPanel() {
    $('#addSiegerPanel').addClass('open');
    $('#addPanelOverlay').addClass('show');
}
function closeAddPanel() {
    if (!isPanelOpen()) return; // Escape bei geschlossenem Panel darf das Formular nicht zurücksetzen
    $('#addSiegerPanel').removeClass('open').removeAttr('data-edit-id').removeAttr('data-edit-name');
    $('#addPanelOverlay').removeClass('show');
    $('#addSiegerForm')[0].reset();
    $('#addSiegerPanel .panel-header h6').html(ADD_TITLE);
    $('#siegerSubmitBtn').html(ADD_BTN);
}

// Bearbeiten: Panel mit den Werten einer Zeile fuellen
function openEditFor($row) {
    const id = $row.data('id'), name = $row.data('name'), wert = $row.data('wert'), siegerdefId = $row.data('siegerdef');
    $('#addSiegerPanel').attr('data-edit-id', id).attr('data-edit-name', name);
    $('#addSiegerPanel .panel-header h6').html(EDIT_TITLE);
    const $form = $('#addSiegerForm');
    $form.find('[name="wert"]').val(wert);
    $form.find('[name="siegerdef"]').val(siegerdefId);
    $form.find('[name="year"]').val($row.data('year') || $('#filterYear').val());

    // Mitglied nach Name matchen (Name ist als Text gespeichert)
    const $memberSelect = $form.find('[name="member_id"]');
    let matched = false;
    $memberSelect.find('option').each(function() {
        if (this.textContent.trim() === String(name).trim()) { $(this).prop('selected', true); matched = true; return false; }
    });
    if (!matched) $memberSelect.val('');

    $('#siegerSubmitBtn').html(EDIT_BTN);
    openAddPanel();
}

$(function() {
    $('#filterYear').on('change', function() { loadSieger(this.value); });

    // Hinzufügen: Jahr aus dem Filter vorbelegen (vorher aktuelles Jahr -> Eintrag landete
    // im falschen Jahr und war nach dem Speichern unsichtbar)
    $('#btnAddSieger').on('click', function() {
        $('#addSiegerPanel').removeAttr('data-edit-id').removeAttr('data-edit-name');
        $('#addSiegerPanel .panel-header h6').html(ADD_TITLE);
        $('#siegerSubmitBtn').html(ADD_BTN);
        $('#addSiegerForm')[0].reset();
        $('#addSiegerForm [name="year"]').val($('#filterYear').val());
        openAddPanel();
        setTimeout(() => $('#memberSelect').trigger('focus'), 250);
    });

    $('#addPanelClose, #addPanelOverlay').on('click', closeAddPanel);
    $(document).on('keydown', function(e) { if (e.key === 'Escape') closeAddPanel(); });

    // Bearbeiten: Klick auf Zeile oder Stift, Enter/Leertaste auf fokussierter Zeile
    $(document).on('click', '.winner-row', function(e) {
        if ($(e.target).closest('.delete-sieger').length) return;
        openEditFor($(this));
    });
    $(document).on('keydown', '.winner-row', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openEditFor($(this)); }
    });

    // Löschen
    $(document).on('click', '.delete-sieger', function(e) {
        e.stopPropagation();
        const siegerId = $(this).data('id');
        if (!siegerId) return;
        const name = $(this).closest('.winner-row').data('name') || 'diesen Sieger-Eintrag';
        msvConfirmDelete(name).then(res => {
            if (!res.isConfirmed) return;
            $.post('sieger/delete_sieger.php', { sieger_id: siegerId, csrf_token: $('#csrf_token').val() }, null, 'json')
                .done(r => {
                    if (r && r.success) { msvToast('Sieger gelöscht', 'success'); loadSieger($('#filterYear').val()); }
                    else msvToast('Fehler: ' + ((r && r.message) || 'Unbekannter Fehler'), 'error');
                })
                .fail(xhr => msvToast((xhr.responseJSON && xhr.responseJSON.message) || 'Fehler beim Löschen', 'error'));
        });
    });

    // Hinzufügen/Bearbeiten absenden
    $('#addSiegerForm').on('submit', function(e) {
        e.preventDefault();
        const editId = $('#addSiegerPanel').attr('data-edit-id');
        const isEdit = !!editId;

        if (!isEdit && !$('#memberSelect').val()) { msvToast('Bitte ein Mitglied wählen', 'warning'); $('#memberSelect').trigger('focus'); return; }
        const wert = parseInt($('#wertInput').val(), 10);
        if (isNaN(wert) || wert <= 0) { msvToast('Wert muss grösser als 0 sein', 'warning'); $('#wertInput').trigger('focus'); return; }

        const $btn = $('#siegerSubmitBtn');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichern...');

        let formData = $(this).serialize();
        if (isEdit) {
            formData += '&id=' + encodeURIComponent(editId);
            if (!$('#memberSelect').val()) formData += '&name=' + encodeURIComponent($('#addSiegerPanel').attr('data-edit-name') || '');
        }

        $.post(isEdit ? 'sieger/update_sieger.php' : 'sieger/save_sieger.php', formData, null, 'json')
            .done(r => {
                if (r && r.success) {
                    msvToast(isEdit ? 'Sieger aktualisiert' : 'Sieger hinzugefügt', 'success');
                    const savedYear = $('#yearInput').val();
                    closeAddPanel();
                    // Falls in ein anderes Jahr gespeichert wurde, dorthin wechseln, damit der Eintrag sichtbar ist
                    if (savedYear && $('#filterYear option[value="' + savedYear + '"]').length && savedYear !== $('#filterYear').val()) {
                        $('#filterYear').val(savedYear);
                    }
                    loadSieger($('#filterYear').val());
                } else {
                    msvToast('Fehler: ' + ((r && r.message) || 'Unbekannter Fehler'), 'error');
                }
            })
            .fail(xhr => msvToast((xhr.responseJSON && xhr.responseJSON.message) || 'Fehler beim Speichern', 'error'))
            .always(() => $btn.prop('disabled', false).html(isEdit ? EDIT_BTN : ADD_BTN));
    });

    loadSieger($('#filterYear').val());
});
</script>

<?php include 'footer.inc.php'; ?>
