<?php
// portal/jsk_dokumente.php - Dokumente fuer Jungschuetzen (Ansicht JSK, Upload Vorstand/Admin)
$portal_page_title = 'JSK-Dokumente';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
$db = getDB();

$can_manage    = isVorstand();           // Vorstand/Admin duerfen hochladen/loeschen
$user_role     = $_SESSION['user_role'] ?? 'mitglied';
$selected_year = intval($_GET['year'] ?? date('Y'));

// Dokumente laden (Manager sehen alle; alle anderen nur 'alle_mitglieder')
if ($can_manage) {
    $stmt = $db->prepare("
        SELECT d.*, u.full_name AS uploader_name
        FROM vorstand_dokumente d
        LEFT JOIN users u ON d.hochgeladen_von = u.id
        WHERE d.typ = 'jsk' AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
} else {
    $stmt = $db->prepare("
        SELECT d.*, u.full_name AS uploader_name
        FROM vorstand_dokumente d
        LEFT JOIN users u ON d.hochgeladen_von = u.id
        WHERE d.typ = 'jsk' AND d.sichtbar_fuer = 'alle_mitglieder' AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
}
$stmt->execute([$selected_year]);
$dokumente = $stmt->fetchAll();

$years_stmt = $db->query("SELECT DISTINCT jahr FROM vorstand_dokumente WHERE typ='jsk' AND jahr IS NOT NULL ORDER BY jahr DESC");
$available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array(date('Y'), $available_years)) array_unshift($available_years, date('Y'));
if (empty($available_years)) $available_years = [date('Y')];

include 'portal_header.php';
?>

<style>
.upload-area { background:#f8f9fa; border:2px dashed var(--p-border); border-radius:var(--p-radius); padding:var(--p-4); margin-bottom:var(--p-4); }
</style>

<div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-mortarboard me-2"></i>JSK-Dokumente</h1>
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
    <h6 class="mb-3"><i class="bi bi-cloud-upload me-2"></i>Neues Dokument für Jungschützen hochladen</h6>
    <form id="uploadForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="typ" value="jsk">
        <input type="hidden" name="sichtbar_fuer" value="alle_mitglieder">
        <div class="row g-2">
            <div class="col-md-5">
                <input type="text" class="form-control" name="titel" placeholder="Titel (z.B. Standblatt Kurs 1) *" required>
            </div>
            <div class="col-md-3">
                <input type="date" class="form-control" name="datum" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-4">
                <input type="file" class="form-control" name="datei" accept=".pdf,.docx,.jpg,.jpeg,.png" required>
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-10">
                <input type="text" class="form-control" name="beschreibung" placeholder="Beschreibung (optional)">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100" id="uploadBtn"><i class="bi bi-upload me-1"></i>Hochladen</button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (empty($dokumente)): ?>
<div class="text-center py-5">
    <i class="bi bi-folder2-open" style="font-size: 3rem; color: #dee2e6;"></i>
    <p class="text-muted mt-3">Keine JSK-Dokumente für <?php echo $selected_year; ?> vorhanden</p>
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
        $chip_variant = match($icon_class) { 'pdf' => 'red', 'img' => 'green', 'xls' => 'green', default => 'blue' };
        $size_kb = round(($doc['dateigroesse'] ?? 0) / 1024);
    ?>
    <div class="p-list-row" id="doc-<?php echo $doc['id']; ?>">
        <div class="p-chip lg <?php echo $chip_variant; ?>"><i class="bi bi-file-earmark-<?php echo $icon_name; ?>"></i></div>
        <div class="p-list-body">
            <div class="p-list-title"><?php echo htmlspecialchars($doc['titel']); ?></div>
            <div class="p-list-meta">
                <?php if ($doc['datum']): ?><i class="bi bi-calendar me-1"></i><?php echo date('d.m.Y', strtotime($doc['datum'])); ?> &middot; <?php endif; ?>
                <?php echo htmlspecialchars($doc['dateiname']); ?> (<?php echo $size_kb; ?> KB)
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
            <button class="btn btn-sm btn-outline-danger" title="Löschen" aria-label="Dokument löschen" onclick="deleteDoc(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['titel'], ENT_QUOTES); ?>')">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
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
        url: '../api/dokument_upload.php', type: 'POST', data: formData,
        processData: false, contentType: false, dataType: 'json',
        success: function(resp) {
            if (resp.success) { msvToast(resp.message, 'success'); setTimeout(() => location.reload(), 800); }
            else { msvToast(resp.message, 'error'); }
        },
        error: function() { msvToast('Upload fehlgeschlagen', 'error'); },
        complete: function() { $btn.prop('disabled', false).html('<i class="bi bi-upload me-1"></i>Hochladen'); }
    });
});

function deleteDoc(id, title) {
    msvConfirmDelete('Dokument "' + title + '"').then(result => {
        if (result.isConfirmed) {
            $.post('../api/dokument_delete.php', { id: id, csrf_token: '<?php echo $_SESSION['csrf_token']; ?>' }, function(resp) {
                if (resp.success) { msvToast(resp.message, 'success'); $('#doc-' + id).fadeOut(); }
                else { msvToast(resp.message, 'error'); }
            }, 'json');
        }
    });
}
<?php endif; ?>
</script>

<?php include 'portal_footer.php'; ?>
