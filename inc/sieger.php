<?php
include 'dbconnect.inc.php';

// Seitenspezifische Styles definieren
$page_specific_css = "
/* === YEAR SELECTOR === */
.year-selector {
    display: flex; align-items: center; gap: 0.5rem;
}
.year-selector label {
    font-weight: 600; font-size: 0.9rem; color: #64748b; white-space: nowrap; margin-bottom: 0;
}
.year-selector select {
    width: auto; min-width: 90px;
}

/* === CATEGORY GRID === */
.desktop-cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.25rem;
}

/* === CATEGORY CARD === */
.cat-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
    overflow: hidden;
    transition: box-shadow 0.2s;
}
.cat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.cat-card-head {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}
.cat-card-head h6 {
    margin: 0; font-weight: 600; font-size: 0.9rem; color: #1e293b;
}
.cat-card-body {
    padding: 0.25rem 0 0.5rem;
}

/* === CATEGORY ICON === */
.cat-icon {
    width: 36px; height: 36px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.cat-icon.gold   { background: #fff8e1; color: #ffc107; border: 1px solid #ffe082; }
.cat-icon.blue   { background: #dbeafe; color: #2563eb; border: 1px solid #bfdbfe; }
.cat-icon.green  { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
.cat-icon.purple { background: #f3e8ff; color: #7c3aed; border: 1px solid #ddd6fe; }

/* === WINNER ROW === */
.winner-row {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.4rem 0.75rem;
    border-bottom: 1px solid #f8fafc;
    cursor: pointer; transition: background 0.15s;
}
.winner-row:hover { background: rgba(99,102,241,0.07); }
.winner-row:last-child { border-bottom: none; }
.winner-name {
    flex: 1; font-weight: 500; color: #334155; font-size: 0.85rem;
}
.winner-score {
    font-weight: 700; color: #1e293b; font-size: 0.85rem;
    min-width: 40px; text-align: right;
}
.winner-action {
    display: flex; gap: 0.2rem;
}
.winner-action .btn {
    padding: 0.15rem 0.35rem; font-size: 0.75rem;
    opacity: 0; transition: opacity 0.15s;
}
.winner-row:hover .winner-action .btn { opacity: 1; }

/* === EMPTY STATE === */
.empty-state {
    text-align: center; padding: 3rem 1rem; color: #94a3b8;
}
.empty-state i { font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 0.75rem; }

/* === SLIDE PANEL === */
.add-sieger-panel {
    position: fixed; top: 0; right: -480px; width: 460px; height: 100vh;
    background: #fff; box-shadow: -8px 0 30px rgba(0,0,0,0.12);
    z-index: 1060; transition: right 0.3s cubic-bezier(0.4,0,0.2,1);
    overflow-y: auto; display: flex; flex-direction: column;
}
.add-sieger-panel.open { right: 0; }
.panel-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.3);
    z-index: 1055; opacity: 0; visibility: hidden; transition: all 0.3s;
}
.panel-overlay.show { opacity: 1; visibility: visible; }
.panel-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0;
    background: #f8fafc; flex-shrink: 0;
}
.panel-header h6 { font-weight: 600; color: #1e293b; }
.panel-body { padding: 1.25rem; flex: 1; }
.panel-label {
    font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 0.35rem; display: block;
}

/* === CUSTOM CLOSE BUTTON === */
.custom-close {
    background: none; border: none; color: #64748b; font-size: 1.5rem;
    opacity: 0.7; transition: all 0.15s; padding: 0;
    width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
}
.custom-close:hover {
    opacity: 1; background-color: rgba(220, 53, 69, 0.1); color: #dc3545;
}

/* === MOBILE === */
@media (max-width: 767.98px) {
    .desktop-cards-container { grid-template-columns: 1fr; gap: 0.75rem; }
    .add-sieger-panel { width: 100%; right: -100%; }
    .year-selector { width: 100%; }
    .year-selector select { flex: 1; }
    .cat-card-head { padding: 0.4rem 0.625rem; }
    .cat-card-head h6 { font-size: 0.85rem; }
    .cat-icon { width: 28px; height: 28px; font-size: 0.8rem; border-radius: 6px; }
    .cat-card-body { padding: 0.15rem 0 0.35rem; }
    .winner-row { padding: 0.3rem 0.625rem; }
    .winner-action .btn { opacity: 0.6; }
    .winner-name { font-size: 0.85rem; }
    .winner-score { font-size: 0.85rem; }
    .winner-action .btn { opacity: 0.6; }
}
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-10 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- CSRF Token -->
                <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <!-- Header außerhalb des inneren Containers (nur Desktop) -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-trophy me-2"></i>
                            Sieger der letzten Jahre
                        </h2>
                    </div>
                </div>

                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <!-- Jahr-Auswahl -->
                    <div class="year-selector mb-3">
                        <label><i class="bi bi-calendar3 me-1"></i>Jahr:</label>
                        <select id="filterYear" class="form-select form-select-sm">
                            <?php
                            $sql = "SELECT DISTINCT year FROM sieger ORDER BY year DESC";
                            $result = $conn->query($sql);
                            $currentYear = date("Y");
                            $lastYear = $currentYear - 1;
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $selected = ($row['year'] == $lastYear) ? "selected" : "";
                                    echo "<option value='{$row['year']}' {$selected}>{$row['year']}</option>";
                                }
                            } else {
                                echo "<option value='{$currentYear}'>{$currentYear}</option>";
                            }
                            ?>
                        </select>
                        <button class="btn btn-sm btn-success" id="btnAddSieger">
                            <i class="bi bi-plus-lg me-1"></i>Hinzufügen
                        </button>
                    </div>

                    <!-- Kategorie-Karten Container -->
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

<!-- Slide-Panel: Sieger hinzufügen -->
<div class="panel-overlay" id="addPanelOverlay"></div>
<div class="add-sieger-panel" id="addSiegerPanel">
    <div class="panel-header">
        <h6 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Neuen Sieger hinzufügen</h6>
        <button class="btn btn-sm btn-outline-secondary" id="addPanelClose"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="panel-body">
        <form id="addSiegerForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
                <label class="panel-label"><i class="bi bi-person me-1"></i>Mitglied</label>
                <select name="member_id" class="form-select" id="memberSelect">
                    <option value="">– Mitglied wählen –</option>
                    <?php
                    $sql = "SELECT ID, Vorname, Name FROM mitglieder WHERE Verstorben = 0 ORDER BY Name, Vorname";
                    $result = $conn->query($sql);
                    if ($result) while ($row = $result->fetch_assoc()) {
                        echo "<option value='" . $row['ID'] . "'>" .
                             htmlspecialchars($row['Name'] . ' ' . $row['Vorname']) .
                             "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="panel-label"><i class="bi bi-trophy me-1"></i>Auszeichnung</label>
                <select name="siegerdef" class="form-select" required>
                    <option value="">– Kategorie wählen –</option>
                    <?php
                    $sql = "SELECT ID, Bezeichnung FROM siegerdef ORDER BY Bezeichnung";
                    $result = $conn->query($sql);
                    if ($result) while ($row = $result->fetch_assoc()) {
                        echo "<option value='" . $row['ID'] . "'>" .
                             htmlspecialchars($row['Bezeichnung']) .
                             "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="panel-label"><i class="bi bi-123 me-1"></i>Resultat / Punkte</label>
                <input type="number" name="wert" class="form-control" min="0" required placeholder="z.B. 98">
            </div>

            <div class="mb-3">
                <label class="panel-label"><i class="bi bi-calendar3 me-1"></i>Jahr</label>
                <input type="number" name="year" class="form-control" value="<?= date('Y') ?>" required>
            </div>

            <hr>
            <button type="submit" class="btn btn-success w-100">
                <i class="bi bi-plus-circle me-1"></i>Sieger hinzufügen
            </button>
        </form>
    </div>
</div>

<!-- Modal zur Bestätigung für das Löschen -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="bi bi-exclamation-triangle text-warning"></i> Bestätigung erforderlich
                </h5>
                <button type="button" class="custom-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>
                    <div>
                        <strong>Möchtest du diesen Sieger-Eintrag wirklich löschen?</strong>
                        <br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="confirmAction">
                    <i class="bi bi-trash me-1"></i>Löschen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Kategorie-Konfiguration
const CATEGORY_CONFIG = {
    'Kunst':                { icon: 'bi-palette',    color: 'gold',   label: 'Kunst' },
    'Glück':                { icon: 'bi-clover',     color: 'green',  label: 'Glück' },
    'Zabigstich':           { icon: 'bi-bullseye',   color: 'purple', label: 'Zabigstich' },
    'Endstich':             { icon: 'bi-crosshair',  color: 'blue',   label: 'Endstich' },
    'Schwini':              { icon: 'bi-star',        color: 'purple', label: 'Schwini' },
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

function enhanceCategoryCards() {
    document.querySelectorAll('.cat-card').forEach(card => {
        const catName = card.dataset.category;
        const config = CATEGORY_CONFIG[catName] || DEFAULT_CONFIG;

        // Icon setzen
        const iconEl = card.querySelector('[data-cat-icon]');
        if (iconEl) {
            iconEl.className = `cat-icon ${config.color}`;
            iconEl.innerHTML = `<i class="bi ${config.icon}"></i>`;
        }

        // Label aufhübschen
        const labelEl = card.querySelector('[data-cat-label]');
        if (labelEl && config.label) {
            labelEl.textContent = config.label;
        }
    });
}

function loadSieger(year) {
    const container = $('#siegerContainer');
    container.html(`
        <div class="text-center py-5">
            <div class="spinner-border spinner-border-sm me-2"></div>
            Lade Sieger für ${year}...
        </div>
    `);

    $.ajax({
        url: 'sieger/load_sieger.php',
        method: 'GET',
        data: { year: year },
        success: function(response) {
            container.html(response);
            enhanceCategoryCards();
        },
        error: function() {
            container.html(`
                <div class="text-center py-4 text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Fehler beim Laden der Sieger
                </div>
            `);
            msvToast('Fehler beim Laden der Sieger', 'error');
        }
    });
}

// Panel öffnen/schliessen
function openAddPanel() {
    $('#addSiegerPanel').addClass('open');
    $('#addPanelOverlay').addClass('show');
}
function closeAddPanel() {
    $('#addSiegerPanel').removeClass('open').removeAttr('data-edit-id').removeAttr('data-edit-name');
    $('#addPanelOverlay').removeClass('show');
    $('#addSiegerForm')[0].reset();
    // Zurück auf Add-Modus
    $('#addSiegerPanel .panel-header h6').html('<i class="bi bi-plus-circle me-2"></i>Neuen Sieger hinzufügen');
    $('#addSiegerForm button[type="submit"]').html('<i class="bi bi-plus-circle me-1"></i>Sieger hinzufügen');
}

$(document).ready(function() {
    var siegerId = null;

    // Jahr-Wechsel lädt sofort neu
    $('#filterYear').on('change', function() {
        loadSieger(this.value);
    });

    // Sieger hinzufügen — Panel öffnen (Add-Modus)
    $('#btnAddSieger').on('click', function() {
        $('#addSiegerPanel').removeAttr('data-edit-id').removeAttr('data-edit-name');
        $('#addSiegerPanel .panel-header h6').html('<i class="bi bi-plus-circle me-2"></i>Neuen Sieger hinzufügen');
        $('#addSiegerForm button[type="submit"]').html('<i class="bi bi-plus-circle me-1"></i>Sieger hinzufügen');
        openAddPanel();
    });

    // Panel schliessen
    $('#addPanelClose, #addPanelOverlay').on('click', closeAddPanel);
    $(document).on('keydown', function(e) { if (e.key === 'Escape') closeAddPanel(); });

    // Bearbeiten — Klick auf Winner-Row öffnet Panel
    $(document).on('click', '.winner-row', function(e) {
        if ($(e.target).closest('.delete-sieger').length) return; // Lösch-Button ignorieren
        var $row = $(this);
        var id = $row.data('id');
        var name = $row.data('name');
        var wert = $row.data('wert');
        var siegerdefId = $row.data('siegerdef');
        var year = $('#filterYear').val();

        // Panel in Edit-Modus setzen
        $('#addSiegerPanel').attr('data-edit-id', id).attr('data-edit-name', name);
        $('#addSiegerPanel .panel-header h6').html('<i class="bi bi-pencil-square me-2"></i>Sieger bearbeiten');
        var $form = $('#addSiegerForm');
        $form.find('[name="wert"]').val(wert);
        $form.find('[name="siegerdef"]').val(siegerdefId);
        $form.find('[name="year"]').val(year);

        // Member-Select: versuche nach Name zu matchen
        var $memberSelect = $form.find('[name="member_id"]');
        var matched = false;
        $memberSelect.find('option').each(function() {
            if (this.textContent.trim() === name) {
                $(this).prop('selected', true);
                matched = true;
                return false;
            }
        });
        if (!matched) $memberSelect.val('');

        // Submit-Button anpassen
        $form.find('button[type="submit"]').html('<i class="bi bi-save me-1"></i>Änderungen speichern');

        openAddPanel();
    });

    // Löschen — Modal öffnen
    $(document).on('click', '.delete-sieger', function(e) {
        e.stopPropagation();
        siegerId = $(this).data('id');
        $('#confirmModal').modal('show');
    });

    // Löschen bestätigen
    $('#confirmAction').on('click', function() {
        if (!siegerId) return;
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');

        $.ajax({
            url: 'sieger/delete_sieger.php',
            method: 'POST',
            data: { sieger_id: siegerId, csrf_token: $('#csrf_token').val() },
            success: function(response) {
                try {
                    const r = JSON.parse(response);
                    if (r.success) {
                        $('#confirmModal').modal('hide');
                        msvToast('Sieger erfolgreich gelöscht', 'success');
                        setTimeout(() => loadSieger($('#filterYear').val()), 500);
                    } else {
                        msvToast('Fehler: ' + (r.message || 'Unbekannter Fehler'), 'error');
                    }
                } catch (e) {
                    $('#confirmModal').modal('hide');
                    msvToast('Sieger erfolgreich gelöscht', 'success');
                    setTimeout(() => loadSieger($('#filterYear').val()), 500);
                }
            },
            error: function() { msvToast('Fehler beim Löschen', 'error'); },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="bi bi-trash me-1"></i>Löschen');
                siegerId = null;
            }
        });
    });

    // Sieger hinzufügen/bearbeiten — Formular absenden
    $('#addSiegerForm').on('submit', function(e) {
        e.preventDefault();
        var editId = $('#addSiegerPanel').attr('data-edit-id');
        var isEdit = editId && editId !== '';

        // Beim Hinzufügen muss ein Mitglied gewählt sein
        if (!isEdit && !$('#memberSelect').val()) {
            msvToast('Bitte ein Mitglied wählen', 'warning');
            $('#memberSelect').focus();
            return;
        }
        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichern...');

        var url = isEdit ? 'sieger/update_sieger.php' : 'sieger/save_sieger.php';
        var formData = $(this).serialize();
        if (isEdit) {
            formData += '&id=' + encodeURIComponent(editId);
            // Name für Update mitsenden falls kein Member gewählt
            var $memberSel = $(this).find('[name="member_id"]');
            if (!$memberSel.val()) {
                var origName = $('#addSiegerPanel').attr('data-edit-name') || '';
                formData += '&name=' + encodeURIComponent(origName);
            }
        }

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(r) {
                if (r.success) {
                    msvToast(isEdit ? 'Sieger erfolgreich aktualisiert' : 'Sieger erfolgreich hinzugefügt', 'success');
                    closeAddPanel();
                    loadSieger($('#filterYear').val());
                } else {
                    msvToast('Fehler: ' + (r.message || 'Unbekannter Fehler'), 'error');
                }
            },
            error: function() { msvToast('Fehler beim Speichern', 'error'); },
            complete: function() {
                $btn.prop('disabled', false).html(isEdit
                    ? '<i class="bi bi-save me-1"></i>Änderungen speichern'
                    : '<i class="bi bi-plus-circle me-1"></i>Sieger hinzufügen');
            }
        });
    });

    // Initial laden
    loadSieger($('#filterYear').val());
});
</script>

<?php
include 'footer.inc.php';
?>
