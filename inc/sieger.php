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
.cat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.cat-card-head {
    display: flex; align-items: center; gap: 0.45rem;
    padding: 0.3rem 0.6rem;
    border-bottom: 1px solid #f1f5f9;
}
.cat-card-head h6 {
    margin: 0; font-weight: 600; font-size: 0.82rem; color: #1e293b;
}
.cat-card-body {
    padding: 0.1rem 0 0.2rem;
}

/* === CATEGORY ICON (kompakt) === */
.cat-icon {
    width: 28px; height: 28px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; flex-shrink: 0;
}
.cat-icon.gold   { background: #fff8e1; color: #ffc107; border: 1px solid #ffe082; }
.cat-icon.blue   { background: #dbeafe; color: #2563eb; border: 1px solid #bfdbfe; }
.cat-icon.green  { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
.cat-icon.purple { background: #f3e8ff; color: #7c3aed; border: 1px solid #ddd6fe; }

/* === WINNER ROW === */
.winner-row {
    display: flex; align-items: center; gap: 0.45rem;
    padding: 0.25rem 0.6rem;
    border-bottom: 1px solid #f8fafc;
    cursor: pointer; transition: background 0.15s;
}
.winner-row:hover { background: rgba(99,102,241,0.07); }
.winner-row:last-child { border-bottom: none; }
.winner-name {
    flex: 1; font-weight: 500; color: #334155; font-size: 0.8rem;
}
.winner-score {
    font-weight: 700; color: #1e293b; font-size: 0.8rem;
    min-width: 38px; text-align: right;
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

/* === SLIDE PANEL ===
   .add-sieger-panel/.panel-overlay/.panel-header/.panel-body/.panel-label
   jetzt zentral in css/msv-styles.css. Breite 460px via --panel-width am Panel. */

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
    /* .add-sieger-panel Mobile-Vollbreite jetzt zentral in css/msv-styles.css */
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
        <div class="col-xl-12 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- CSRF Token -->
                <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <?php $page_title = 'Sieger der letzten Jahre'; include 'partials/page_header.inc.php'; ?>

                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <!-- Jahr-Auswahl -->
                    <div class="year-selector mb-3">
                        <label><i class="bi bi-calendar3 me-1"></i>Jahr:</label>
                        <select id="filterYear" class="form-select form-select-sm">
                            <?php
                            $currentYear = (int)date("Y");
                            $lastYear = $currentYear - 1;
                            // Vorhandene Jahre aus DB mit Standardbereich (currentYear-2 .. currentYear+1)
                            // zusammenführen, damit auch Jahre ohne bisherige Sieger wählbar sind.
                            $years = [];
                            $result = $conn->query("SELECT DISTINCT year FROM sieger");
                            if ($result) {
                                while ($row = $result->fetch_assoc()) {
                                    $years[] = (int)$row['year'];
                                }
                            }
                            for ($y = $currentYear + 1; $y >= $currentYear - 2; $y--) {
                                $years[] = $y;
                            }
                            $years = array_unique($years);
                            rsort($years);
                            foreach ($years as $y) {
                                $selected = ($y === $lastYear) ? "selected" : "";
                                echo "<option value='{$y}' {$selected}>{$y}</option>";
                            }
                            ?>
                        </select>
                        <button class="btn btn-sm btn-outline-success" id="btnAddSieger">
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

<!-- Slide-Panel: Sieger hinzufügen – zentrale Struktur via inc/partials/side_panel.inc.php -->
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
            <button type="submit" class="btn btn-outline-success btn-sm w-100">
                <i class="bi bi-plus-circle me-1"></i>Sieger hinzufügen
            </button>
        </form>
<?php
$panel_body = ob_get_clean();
include 'partials/side_panel.inc.php';
?>

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

    // Löschen — Bestätigung via SweetAlert2, dann AJAX
    $(document).on('click', '.delete-sieger', function(e) {
        e.stopPropagation();
        var siegerId = $(this).data('id');
        if (!siegerId) return;
        var name = $(this).closest('.winner-row').data('name') || 'diesen Sieger-Eintrag';
        msvConfirmDelete(name).then(function(res) {
            if (!res.isConfirmed) return;
            $.ajax({
                url: 'sieger/delete_sieger.php',
                method: 'POST',
                data: { sieger_id: siegerId, csrf_token: $('#csrf_token').val() },
                success: function(response) {
                    try {
                        const r = JSON.parse(response);
                        if (r.success) {
                            msvToast('Sieger erfolgreich gelöscht', 'success');
                            setTimeout(() => loadSieger($('#filterYear').val()), 500);
                        } else {
                            msvToast('Fehler: ' + (r.message || 'Unbekannter Fehler'), 'error');
                        }
                    } catch (e) {
                        msvToast('Sieger erfolgreich gelöscht', 'success');
                        setTimeout(() => loadSieger($('#filterYear').val()), 500);
                    }
                },
                error: function() { msvToast('Fehler beim Löschen', 'error'); }
            });
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
        // Wert muss positiv sein
        var $wertInput = $(this).find('[name="wert"]');
        if (parseInt($wertInput.val(), 10) <= 0 || isNaN(parseInt($wertInput.val(), 10))) {
            msvToast('Wert muss grösser als 0 sein', 'warning');
            $wertInput.focus();
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
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Fehler beim Speichern';
                msvToast(msg, 'error');
            },
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
