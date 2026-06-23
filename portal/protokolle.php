<?php
// portal/protokolle.php - Protokolle ansehen und verwalten
$portal_page_title = 'Protokolle';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
$db = getDB();

$can_manage = isVorstand();
$user_role = $_SESSION['user_role'] ?? 'mitglied';
$selected_year = intval($_GET['year'] ?? date('Y'));

// Dokumente laden
if ($user_role === 'admin') {
    // Admin: alle Protokolle sehen (inkl. admin-only)
    $stmt = $db->prepare("
        SELECT d.*, u.full_name AS uploader_name
        FROM vorstand_dokumente d
        LEFT JOIN users u ON d.hochgeladen_von = u.id
        WHERE d.typ = 'protokoll' AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
} elseif ($user_role === 'vorstand') {
    // Vorstand: nur vorstand + alle_mitglieder (nicht admin-only)
    $stmt = $db->prepare("
        SELECT d.*, u.full_name AS uploader_name
        FROM vorstand_dokumente d
        LEFT JOIN users u ON d.hochgeladen_von = u.id
        WHERE d.typ = 'protokoll' AND d.sichtbar_fuer IN ('vorstand', 'alle_mitglieder')
          AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
} else {
    $stmt = $db->prepare("
        SELECT d.*, u.full_name AS uploader_name
        FROM vorstand_dokumente d
        LEFT JOIN users u ON d.hochgeladen_von = u.id
        WHERE d.typ = 'protokoll' AND d.sichtbar_fuer = 'alle_mitglieder'
          AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
}
$stmt->execute([$selected_year]);
$dokumente = $stmt->fetchAll();

$years_stmt = $db->query("SELECT DISTINCT jahr FROM vorstand_dokumente WHERE typ='protokoll' AND jahr IS NOT NULL ORDER BY jahr DESC");
$available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array(date('Y'), $available_years)) array_unshift($available_years, date('Y'));
if (empty($available_years)) $available_years = [date('Y')];

include 'portal_header.php';
?>

<style>
/* Seitenspezifisch: gestrichelte Upload-Box + Sichtbarkeits-Badge.
   Karten/Listen/Chips/Felder kommen aus css/portal.css (p-list, p-chip, p-field). */
.upload-area {
    background: #f8f9fa;
    border: 2px dashed var(--p-border);
    border-radius: var(--p-radius);
    padding: var(--p-4);
    margin-bottom: var(--p-4);
}
.visibility-badge { font-size: .7rem; padding: var(--p-1) var(--p-2); border-radius: var(--p-radius-sm); }
</style>

<div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-file-text me-2"></i>Protokolle</h1>
        <p class="subtitle mb-0"><?php echo $selected_year; ?></p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2">
        <select name="year" class="form-select form-select-sm" style="max-width:140px;" onchange="this.form.submit()">
            <?php foreach ($available_years as $y): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($can_manage): ?>
<div class="upload-area">
    <h6 class="mb-3"><i class="bi bi-cloud-upload me-2"></i>Neues Protokoll hochladen</h6>
    <form id="uploadForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="typ" value="protokoll">
        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" class="form-control" name="titel" placeholder="Titel (z.B. GV-Protokoll 2025) *" required>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" name="datum" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="sichtbar_fuer">
                    <?php if (isAdmin()): ?>
                    <option value="admin">Nur Admin</option>
                    <?php endif; ?>
                    <option value="vorstand">Nur Vorstand (Sitzungsprotokoll)</option>
                    <option value="alle_mitglieder" selected>Alle Mitglieder (GV-Protokoll)</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="file" class="form-control" name="datei" accept=".pdf,.docx,.jpg,.jpeg,.png" required>
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-10">
                <input type="text" class="form-control" name="beschreibung" placeholder="Beschreibung (optional)">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100" id="uploadBtn">
                    <i class="bi bi-upload me-1"></i>Hochladen
                </button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (empty($dokumente)): ?>
<div class="text-center py-5">
    <i class="bi bi-folder2-open" style="font-size: 3rem; color: #dee2e6;"></i>
    <p class="text-muted mt-3">Keine Protokolle für <?php echo $selected_year; ?> vorhanden</p>
</div>
<?php else: ?>
<div class="p-list">
    <?php foreach ($dokumente as $doc):
        $ext = strtolower(pathinfo($doc['dateiname'], PATHINFO_EXTENSION));
        $icon_class = 'doc';
        if ($ext == 'pdf') $icon_class = 'pdf';
        elseif (in_array($ext, ['xlsx','xls'])) $icon_class = 'xls';
        elseif (in_array($ext, ['jpg','jpeg','png'])) $icon_class = 'img';
        $icon_name = match($icon_class) { 'pdf' => 'pdf', 'xls' => 'excel', 'img' => 'image', default => 'word' };
        // Datei-Typ -> geteilte Chip-Farbvariante (pdf=red, img/xls=green, doc=blue)
        $chip_variant = match($icon_class) { 'pdf' => 'red', 'img' => 'green', 'xls' => 'green', default => 'blue' };
        $size_kb = round(($doc['dateigroesse'] ?? 0) / 1024);
    ?>
    <div class="p-list-row" id="doc-<?php echo $doc['id']; ?>">
        <div class="p-chip lg <?php echo $chip_variant; ?>">
            <i class="bi bi-file-earmark-<?php echo $icon_name; ?>"></i>
        </div>
        <div class="p-list-body">
            <div class="p-list-title">
                <?php echo htmlspecialchars($doc['titel']); ?>
                <?php if ($doc['sichtbar_fuer'] == 'admin'): ?>
                    <span class="badge bg-danger visibility-badge">Nur Admin</span>
                <?php elseif ($doc['sichtbar_fuer'] == 'vorstand'): ?>
                    <span class="badge bg-warning text-dark visibility-badge">Nur Vorstand</span>
                <?php else: ?>
                    <span class="badge bg-success visibility-badge">Alle</span>
                <?php endif; ?>
            </div>
            <div class="p-list-meta">
                <?php if ($doc['datum']): ?>
                    <i class="bi bi-calendar me-1"></i><?php echo date('d.m.Y', strtotime($doc['datum'])); ?> &middot;
                <?php endif; ?>
                <?php echo htmlspecialchars($doc['dateiname']); ?> (<?php echo $size_kb; ?> KB)
                <?php if ($doc['uploader_name']): ?>
                    &middot; von <?php echo htmlspecialchars($doc['uploader_name']); ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($doc['beschreibung'])): ?>
            <div class="p-list-meta mt-1"><?php echo htmlspecialchars($doc['beschreibung']); ?></div>
            <?php endif; ?>
        </div>
        <div class="p-list-actions">
            <button class="btn btn-sm btn-outline-primary" title="Öffnen" aria-label="Dokument öffnen" onclick="openPortalDoc(<?php echo $doc['id']; ?>, <?php echo htmlspecialchars(json_encode($doc['dateiname'])); ?>)">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
            <?php if ($can_manage && ($doc['hochgeladen_von'] == $_SESSION['user_id'] || isAdmin())): ?>
            <button class="btn btn-sm btn-outline-secondary btn-edit-doc"
                title="Bearbeiten" aria-label="Dokument bearbeiten"
                data-id="<?php echo $doc['id']; ?>"
                data-titel="<?php echo htmlspecialchars($doc['titel'], ENT_QUOTES); ?>"
                data-beschreibung="<?php echo htmlspecialchars($doc['beschreibung'] ?? '', ENT_QUOTES); ?>"
                data-datum="<?php echo htmlspecialchars($doc['datum'] ?? '', ENT_QUOTES); ?>"
                data-sichtbar="<?php echo htmlspecialchars($doc['sichtbar_fuer'], ENT_QUOTES); ?>"
                data-dateiname="<?php echo htmlspecialchars($doc['dateiname'], ENT_QUOTES); ?>">
                <i class="bi bi-pencil" aria-hidden="true"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" title="Löschen" aria-label="Dokument löschen" onclick="deleteDoc(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['titel']); ?>')">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Edit Modal -->
<?php if ($can_manage): ?>
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Protokoll bearbeiten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id" id="editDocId">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Titel *</label>
                        <input type="text" class="form-control" name="titel" id="editTitel" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Datum *</label>
                        <input type="date" class="form-control" name="datum" id="editDatum" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sichtbarkeit</label>
                        <select class="form-select" name="sichtbar_fuer" id="editSichtbar">
                            <?php if (isAdmin()): ?>
                            <option value="admin">Nur Admin</option>
                            <?php endif; ?>
                            <option value="vorstand">Nur Vorstand (Sitzungsprotokoll)</option>
                            <option value="alle_mitglieder">Alle Mitglieder (GV-Protokoll)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Beschreibung</label>
                        <input type="text" class="form-control" name="beschreibung" id="editBeschreibung" placeholder="Optional">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Datei ersetzen</label>
                        <div class="text-muted small mb-1">Aktuelle Datei: <span id="editCurrentFile" class="fw-medium text-dark"></span></div>
                        <input type="file" class="form-control" name="datei" id="editDatei" accept=".pdf,.docx,.jpg,.jpeg,.png">
                        <div class="form-text">Leer lassen, um die bestehende Datei zu behalten.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-outline-primary btn-sm" id="editSaveBtn">
                        <i class="bi bi-save me-1"></i>Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
<?php if ($can_manage): ?>
$('#uploadForm').on('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    var $btn = $('#uploadBtn');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

    $.ajax({
        url: '../api/dokument_upload.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                msvToast(resp.message, 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                msvToast(resp.message, 'error');
            }
        },
        error: function() { msvToast('Upload fehlgeschlagen', 'error'); },
        complete: function() { $btn.prop('disabled', false).html('<i class="bi bi-upload me-1"></i>Hochladen'); }
    });
});

$(document).on('click', '.btn-edit-doc', function() {
    var $b = $(this);
    $('#editDocId').val($b.data('id'));
    $('#editTitel').val($b.data('titel'));
    $('#editBeschreibung').val($b.data('beschreibung'));
    $('#editDatum').val($b.data('datum'));
    $('#editSichtbar').val($b.data('sichtbar'));
    $('#editCurrentFile').text($b.data('dateiname'));
    $('#editDatei').val('');
    $('#editModal').modal('show');
});

$('#editForm').on('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    var $btn = $('#editSaveBtn');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

    $.ajax({
        url: '../api/dokument_update.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                msvToast(resp.message, 'success');
                $('#editModal').modal('hide');
                setTimeout(() => location.reload(), 600);
            } else {
                msvToast(resp.message, 'error');
            }
        },
        error: function() { msvToast('Fehler beim Speichern', 'error'); },
        complete: function() { $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Speichern'); }
    });
});
<?php endif; ?>

function deleteDoc(id, title) {
    msvConfirmDelete('Dokument "' + title + '"').then(result => {
        if (result.isConfirmed) {
            $.post('../api/dokument_delete.php', {
                id: id, csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            }, function(resp) {
                if (resp.success) {
                    msvToast(resp.message, 'success');
                    $('#doc-' + id).fadeOut();
                } else {
                    msvToast(resp.message, 'error');
                }
            }, 'json');
        }
    });
}
</script>

<?php include 'portal_footer.php'; ?>
