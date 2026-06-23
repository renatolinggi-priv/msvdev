<?php
// portal/einsatzplaene.php - Einsatzplaene ansehen und verwalten
$portal_page_title = 'Einsatzpläne';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
$db = getDB();

$can_manage = isVorstand(); // Vorstand + Admin koennen verwalten
$user_role = $_SESSION['user_role'] ?? 'mitglied';
$selected_year = intval($_GET['year'] ?? date('Y'));

// Dokumente laden (Berechtigungspruefung)
if ($user_role === 'admin') {
    // Admin: alle Einsatzplaene sehen (inkl. admin-only)
    $stmt = $db->prepare("
        SELECT d.*, u.full_name AS uploader_name
        FROM vorstand_dokumente d
        LEFT JOIN users u ON d.hochgeladen_von = u.id
        WHERE d.typ = 'einsatzplan' AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
} elseif ($user_role === 'vorstand') {
    // Vorstand: nur vorstand + alle_mitglieder (nicht admin-only)
    $stmt = $db->prepare("
        SELECT d.*, u.full_name AS uploader_name
        FROM vorstand_dokumente d
        LEFT JOIN users u ON d.hochgeladen_von = u.id
        WHERE d.typ = 'einsatzplan' AND d.sichtbar_fuer IN ('vorstand', 'alle_mitglieder')
          AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
} else {
    // Mitglied: nur fuer alle sichtbare
    $stmt = $db->prepare("
        SELECT d.*, u.full_name AS uploader_name
        FROM vorstand_dokumente d
        LEFT JOIN users u ON d.hochgeladen_von = u.id
        WHERE d.typ = 'einsatzplan' AND d.sichtbar_fuer = 'alle_mitglieder'
          AND (d.jahr = ? OR d.jahr IS NULL)
        ORDER BY d.datum DESC, d.hochgeladen_am DESC
    ");
}
$stmt->execute([$selected_year]);
$dokumente = $stmt->fetchAll();

// Importierte Einsätze laden (für Admin/Vorstand)
$einsaetze_grouped = [];
if ($can_manage) {
    $ez_stmt = $db->prepare("
        SELECT z.*, m.Name AS m_name, m.Vorname AS m_vorname, d.titel AS dok_titel
        FROM einsatz_zuweisungen z
        LEFT JOIN mitglieder m ON z.mitglied_id = m.ID
        LEFT JOIN vorstand_dokumente d ON z.dokument_id = d.id
        WHERE z.jahr = ?
        ORDER BY z.event_datum, z.bezeichnung, z.funktion, z.mitglied_name
    ");
    $ez_stmt->execute([$selected_year]);
    $einsaetze = $ez_stmt->fetchAll();

    foreach ($einsaetze as $e) {
        $key = $e['event_datum'] . '|' . $e['bezeichnung'];
        $einsaetze_grouped[$key][] = $e;
    }
}

// Verfuegbare Jahre
$years_stmt = $db->query("SELECT DISTINCT jahr FROM vorstand_dokumente WHERE typ='einsatzplan' AND jahr IS NOT NULL ORDER BY jahr DESC");
$available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array(date('Y'), $available_years)) array_unshift($available_years, date('Y'));
if (empty($available_years)) $available_years = [date('Y')];

include 'portal_header.php';
?>

<style>
/* Generische Doku-Liste nutzt jetzt .p-list / .p-list-row / .p-chip aus portal.css.
   Hier bleibt nur Seitenspezifisches: Upload-Bereich, Badges, Import-Ansicht. */
.upload-area {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: var(--p-radius);
    padding: var(--p-4);
    margin-bottom: var(--p-4);
}
.visibility-badge {
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
    border-radius: 3px;
}
/* Import-Modal (seitenspezifisch, JS-getrieben) */
#importModal .modal-dialog { max-width: 900px; }
#importPreview { max-height: 400px; overflow-y: auto; }
#importPreview table { font-size: 0.85rem; }
.match-exact { color: var(--success-color); }
.match-fuzzy { color: #e67e00; }
.match-none { color: var(--danger-color); }
.import-stats {
    display: flex; gap: var(--p-4); flex-wrap: wrap;
    padding: var(--p-3) var(--p-4); background: #f8f9fa;
    border-radius: var(--p-radius-sm); margin-bottom: var(--p-4); font-size: 0.9rem;
}
.import-stats .stat-item { display: flex; align-items: center; gap: 0.3rem; }
</style>

<div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-calendar-check me-2"></i>Einsatzpläne</h1>
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

<?php if (isAdmin() && !empty($einsaetze_grouped)): ?>
<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-dokumente" data-bs-toggle="tab" data-bs-target="#pane-dokumente" type="button" role="tab">
            <i class="bi bi-file-earmark me-1"></i>Dokumente
            <span class="badge bg-secondary ms-1"><?php echo count($dokumente); ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-einsaetze" data-bs-toggle="tab" data-bs-target="#pane-einsaetze" type="button" role="tab">
            <i class="bi bi-people-fill me-1"></i>Importierte Einsätze
            <span class="badge bg-secondary ms-1"><?php echo count($einsaetze); ?></span>
        </button>
    </li>
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="pane-dokumente" role="tabpanel">
<?php endif; ?>

<?php if ($can_manage): ?>
<!-- Upload-Bereich -->
<div class="upload-area">
    <h6 class="mb-3"><i class="bi bi-cloud-upload me-2"></i>Neuen Einsatzplan hochladen</h6>
    <form id="uploadForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="typ" value="einsatzplan">
        <div class="row g-2">
            <div class="col-md-4">
                <div class="p-field mb-0">
                    <label>Typ</label>
                    <div class="input-group">
                        <select class="form-select" name="titel_typ" id="uploadTitelTyp" required>
                            <option value="">– Typ wählen –</option>
                            <option value="Obligatorisch">Obligatorisch</option>
                            <option value="Feldschiessen">Feldschiessen</option>
                            <option value="Schlossturm">Schlossturm</option>
                            <option value="Wyler Chilbi">Wyler Chilbi</option>
                            <option value="Diverse">Diverse...</option>
                        </select>
                        <input type="text" class="form-control d-none" id="uploadTitelDiverse" placeholder="Name eingeben">
                    </div>
                </div>
                <input type="hidden" name="titel" id="uploadTitel">
            </div>
            <div class="col-md-2">
                <div class="p-field mb-0">
                    <label>Datum</label>
                    <input type="date" class="form-control" name="datum" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-field mb-0">
                    <label>Sichtbar für</label>
                    <select class="form-select" name="sichtbar_fuer">
                        <?php if (isAdmin()): ?>
                        <option value="admin">Nur Admin</option>
                        <?php endif; ?>
                        <option value="vorstand" selected>Vorstand</option>
                        <option value="alle_mitglieder">Alle Mitglieder</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-field mb-0">
                    <label>Datei</label>
                    <input type="file" class="form-control" name="datei" accept=".pdf,.docx,.xlsx,.xls,.jpg,.jpeg,.png" required>
                </div>
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-10">
                <div class="p-field mb-0">
                    <label>Beschreibung</label>
                    <input type="text" class="form-control" name="beschreibung" placeholder="Optional">
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100" id="uploadBtn">
                    <i class="bi bi-upload me-1"></i>Hochladen
                </button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Dokumentenliste -->
<?php if (empty($dokumente)): ?>
<div class="text-center py-5">
    <i class="bi bi-folder2-open" style="font-size: 3rem; color: #dee2e6;"></i>
    <p class="text-muted mt-3">Keine Einsatzpläne für <?php echo $selected_year; ?> vorhanden</p>
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
        // Chip-Farbvariante (portal.css): PDF=rot, IMG/XLS=grün, DOC=blau
        $chip_color = match($icon_class) { 'pdf' => 'red', 'img' => 'green', 'xls' => 'green', default => 'blue' };
        $size_kb = round(($doc['dateigroesse'] ?? 0) / 1024);
    ?>
    <div class="p-list-row" id="doc-<?php echo $doc['id']; ?>">
        <div class="p-chip lg <?php echo $chip_color; ?>">
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
            <?php if ($can_manage && in_array($ext, ['docx', 'pdf', 'xlsx', 'xls'])): ?>
            <button class="btn btn-sm btn-outline-info btn-import-einsatz" data-id="<?php echo $doc['id']; ?>" data-titel="<?php echo htmlspecialchars($doc['titel'], ENT_QUOTES); ?>" title="Einsätze importieren" aria-label="Einsätze importieren">
                <i class="bi bi-table" aria-hidden="true"></i>
            </button>
            <?php endif; ?>
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

<?php if (isAdmin() && !empty($einsaetze_grouped)): ?>
</div><!-- /pane-dokumente -->

<div class="tab-pane fade" id="pane-einsaetze" role="tabpanel">
<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Importierte Einsätze (<?php echo $selected_year; ?>)</h5>
        <span class="badge bg-secondary"><?php echo count($einsaetze); ?> Einträge</span>
    </div>

    <?php
    $wochentage = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
    $groupIdx = 0;
    foreach ($einsaetze_grouped as $key => $entries):
        $first = $entries[0];
        $datum_fmt = date('d.m.Y', strtotime($first['event_datum']));
        $wochentag = $wochentage[date('w', strtotime($first['event_datum']))];
        $groupIdx++;
    ?>
    <div class="card mb-2">
        <div class="card-header py-2 d-flex justify-content-between align-items-center" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#ezGroup<?php echo $groupIdx; ?>">
            <div>
                <strong><?php echo htmlspecialchars($first['bezeichnung']); ?></strong>
                <span class="text-muted ms-2"><?php echo $wochentag; ?> <?php echo $datum_fmt; ?></span>
                <?php if ($first['event_zeit']): ?>
                    <span class="text-muted"><?php echo htmlspecialchars($first['event_zeit']); ?></span>
                <?php endif; ?>
                <span class="badge bg-primary ms-2"><?php echo count($entries); ?></span>
            </div>
            <div>
                <?php if ($first['dokument_id']): ?>
                <button class="btn btn-sm btn-outline-danger btn-delete-all-ez" data-dokid="<?php echo $first['dokument_id']; ?>" data-titel="<?php echo htmlspecialchars($first['bezeichnung'], ENT_QUOTES); ?>" onclick="event.stopPropagation();" title="Alle Einträge dieses Imports löschen">
                    <i class="bi bi-trash me-1"></i>Alle
                </button>
                <?php endif; ?>
                <i class="bi bi-chevron-down"></i>
            </div>
        </div>
        <div class="collapse show" id="ezGroup<?php echo $groupIdx; ?>">
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0" style="font-size:0.85rem;">
                    <thead class="table-light">
                        <tr><th>Funktion</th><th>Name (Dokument)</th><th>Mitglied (DB)</th><th style="width:80px;"></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entries as $e): ?>
                        <tr id="ez-row-<?php echo $e['id']; ?>">
                            <td><?php echo htmlspecialchars($e['funktion']); ?></td>
                            <td><?php echo htmlspecialchars($e['mitglied_name']); ?></td>
                            <td>
                                <?php if ($e['mitglied_id']): ?>
                                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i><?php echo htmlspecialchars($e['m_name'] . ' ' . $e['m_vorname']); ?></span>
                                <?php else: ?>
                                    <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Nicht zugeordnet</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary py-0 px-1 btn-edit-ez"
                                    data-id="<?php echo $e['id']; ?>"
                                    data-funktion="<?php echo htmlspecialchars($e['funktion'], ENT_QUOTES); ?>"
                                    data-name="<?php echo htmlspecialchars($e['mitglied_name'], ENT_QUOTES); ?>"
                                    data-mid="<?php echo $e['mitglied_id'] ?? ''; ?>"
                                    data-datum="<?php echo $e['event_datum']; ?>"
                                    data-zeit="<?php echo htmlspecialchars($e['event_zeit'] ?? '', ENT_QUOTES); ?>"
                                    title="Bearbeiten"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-danger py-0 px-1 btn-delete-ez"
                                    data-id="<?php echo $e['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($e['mitglied_name'], ENT_QUOTES); ?>"
                                    title="Löschen"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

</div><!-- /pane-einsaetze -->
</div><!-- /tab-content -->

<!-- Einsatz Edit Modal -->
<div class="modal fade" id="ezEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Einsatz bearbeiten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="ezEditForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="ezEditId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Funktion</label>
                        <input type="text" class="form-control" name="funktion" id="ezEditFunktion">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name (Dokument)</label>
                        <input type="text" class="form-control" name="mitglied_name" id="ezEditName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mitglied zuordnen</label>
                        <select class="form-select" name="mitglied_id" id="ezEditMitglied">
                            <option value="">– Nicht zugeordnet –</option>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-7">
                            <label class="form-label fw-semibold">Datum</label>
                            <input type="date" class="form-control" name="event_datum" id="ezEditDatum">
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-semibold">Zeit</label>
                            <input type="text" class="form-control" name="event_zeit" id="ezEditZeit" placeholder="18:00 – 20:00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-outline-primary btn-sm" id="ezEditSaveBtn">
                        <i class="bi bi-save me-1"></i>Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Edit Modal -->
<?php if ($can_manage): ?>
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Einsatzplan bearbeiten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id" id="editDocId">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Typ *</label>
                        <div class="input-group">
                            <select class="form-select" id="editTitelTyp" required>
                                <option value="">– Typ wählen –</option>
                                <option value="Obligatorisch">Obligatorisch</option>
                                <option value="Feldschiessen">Feldschiessen</option>
                                <option value="Schlossturm">Schlossturm</option>
                                <option value="Wyler Chilbi">Wyler Chilbi</option>
                                <option value="Diverse">Diverse...</option>
                            </select>
                            <input type="text" class="form-control d-none" id="editTitelDiverse" placeholder="Name eingeben">
                        </div>
                        <input type="hidden" name="titel" id="editTitel">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Datum *</label>
                        <input type="date" class="form-control" name="datum" id="editDatum" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sichtbar für</label>
                        <select class="form-select" name="sichtbar_fuer" id="editSichtbar">
                            <?php if (isAdmin()): ?>
                            <option value="admin">Nur Admin</option>
                            <?php endif; ?>
                            <option value="vorstand">Vorstand</option>
                            <option value="alle_mitglieder">Alle Mitglieder</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Beschreibung</label>
                        <input type="text" class="form-control" name="beschreibung" id="editBeschreibung" placeholder="Optional">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Datei ersetzen</label>
                        <div class="text-muted small mb-1">Aktuelle Datei: <span id="editCurrentFile" class="fw-medium text-dark"></span></div>
                        <input type="file" class="form-control" name="datei" id="editDatei" accept=".pdf,.docx,.xlsx,.xls,.jpg,.jpeg,.png">
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
<!-- Import-Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-table me-2"></i>Einsätze importieren</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="importDocTitle"></p>

                <!-- Loading -->
                <div id="importLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Dokument wird analysiert...</p>
                </div>

                <!-- Fehler -->
                <div id="importError" class="alert alert-danger d-none"></div>
                <div id="importDebugArea" class="d-none mt-2">
                    <button class="btn btn-sm btn-outline-secondary" id="importDebugBtn">
                        <i class="bi bi-bug me-1"></i>Debug-Infos laden
                    </button>
                    <pre id="importDebugOutput" class="d-none mt-2 p-2 bg-light border rounded" style="max-height:300px;overflow:auto;font-size:0.75rem;"></pre>
                </div>

                <!-- Vorschau -->
                <div id="importResult" class="d-none">
                    <div class="import-stats" id="importStats"></div>
                    <div id="importPreview"></div>
                </div>
            </div>
            <div class="modal-footer" id="importFooter" style="display:none;">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-success btn-sm" id="importSaveBtn">
                    <i class="bi bi-check-lg me-1"></i>Importieren
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
<?php if ($can_manage): ?>
// Einsatzplan-Typ Dropdown: "Diverse" zeigt Textfeld
$('#uploadTitelTyp').on('change', function() {
    var $diverse = $('#uploadTitelDiverse');
    if ($(this).val() === 'Diverse') {
        $diverse.removeClass('d-none').prop('required', true).focus();
    } else {
        $diverse.addClass('d-none').prop('required', false).val('');
    }
});

// Titel aus Dropdown + Jahr zusammenbauen
function buildUploadTitel() {
    var typ = $('#uploadTitelTyp').val();
    if (!typ) return '';
    var datum = $('[name="datum"]', '#uploadForm').val();
    var jahr = datum ? new Date(datum).getFullYear() : new Date().getFullYear();
    if (typ === 'Diverse') {
        var custom = $('#uploadTitelDiverse').val().trim();
        return custom ? custom + ' ' + jahr : '';
    }
    return typ + ' ' + jahr;
}

$('#uploadForm').on('submit', function(e) {
    e.preventDefault();
    var titel = buildUploadTitel();
    if (!titel) {
        msvToast('Bitte Einsatzplan-Typ wählen', 'error');
        return;
    }
    $('#uploadTitel').val(titel);
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
                $('#uploadForm')[0].reset();
                $('#uploadTitelDiverse').addClass('d-none').prop('required', false);
                setTimeout(() => location.reload(), 800);
            } else {
                msvToast(resp.message, 'error');
            }
        },
        error: function() { msvToast('Upload fehlgeschlagen', 'error'); },
        complete: function() { $btn.prop('disabled', false).html('<i class="bi bi-upload me-1"></i>Hochladen'); }
    });
});

// Edit-Modal: Diverse Textfeld toggle
$('#editTitelTyp').on('change', function() {
    var $diverse = $('#editTitelDiverse');
    if ($(this).val() === 'Diverse') {
        $diverse.removeClass('d-none').prop('required', true).focus();
    } else {
        $diverse.addClass('d-none').prop('required', false).val('');
    }
});

// Titel auf Dropdown mappen
var einsatzplanTypen = ['Obligatorisch', 'Feldschiessen', 'Schlossturm', 'Wyler Chilbi'];

function parseTitelToTyp(titel) {
    if (!titel) return {typ: '', diverse: ''};
    for (var i = 0; i < einsatzplanTypen.length; i++) {
        if (titel.indexOf(einsatzplanTypen[i]) === 0) {
            return {typ: einsatzplanTypen[i], diverse: ''};
        }
    }
    // Kein bekannter Typ → Diverse
    return {typ: 'Diverse', diverse: titel.replace(/\s*\d{4}$/, '')};
}

function buildEditTitel() {
    var typ = $('#editTitelTyp').val();
    if (!typ) return '';
    var datum = $('#editDatum').val();
    var jahr = datum ? new Date(datum).getFullYear() : new Date().getFullYear();
    if (typ === 'Diverse') {
        var custom = $('#editTitelDiverse').val().trim();
        return custom ? custom + ' ' + jahr : '';
    }
    return typ + ' ' + jahr;
}

$(document).on('click', '.btn-edit-doc', function() {
    var $b = $(this);
    $('#editDocId').val($b.data('id'));
    $('#editBeschreibung').val($b.data('beschreibung'));
    $('#editDatum').val($b.data('datum'));
    $('#editSichtbar').val($b.data('sichtbar'));
    $('#editCurrentFile').text($b.data('dateiname'));
    $('#editDatei').val('');

    // Titel → Dropdown mappen
    var parsed = parseTitelToTyp($b.data('titel'));
    $('#editTitelTyp').val(parsed.typ).trigger('change');
    if (parsed.typ === 'Diverse') {
        $('#editTitelDiverse').val(parsed.diverse);
    }

    $('#editModal').modal('show');
});

$('#editForm').on('submit', function(e) {
    e.preventDefault();
    var titel = buildEditTitel();
    if (!titel) {
        msvToast('Bitte Einsatzplan-Typ wählen', 'error');
        return;
    }
    $('#editTitel').val(titel);
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

// === Einsatz-Import ===
var importData = null;
var importDocId = null;

// aria-hidden Race-Condition (Bootstrap 5): beim Öffnen sofort entfernen
$('#importModal').on('show.bs.modal', function() {
    $(this).removeAttr('aria-hidden');
});

$(document).on('click', '.btn-import-einsatz', function() {
    importDocId = $(this).data('id');
    var titel = $(this).data('titel');

    $('#importDocTitle').text(titel);
    $('#importLoading').show();
    $('#importError').addClass('d-none');
    $('#importResult').addClass('d-none');
    $('#importFooter').hide();
    importData = null;

    $('#importModal').modal('show');

    // Parsen starten
    $.post('../api/einsatzplan_import.php', {
        action: 'parse',
        dokument_id: importDocId,
        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
    }, function(resp) {
        $('#importLoading').hide();
        if (resp.success) {
            importData = resp.data;
            renderImportPreview(resp);
            $('#importResult').removeClass('d-none');
            $('#importFooter').show();
            $('#importDebugArea').addClass('d-none');
        } else if (resp.csrf_expired) {
            $('#importModal').modal('hide');
            msvToast('Sitzung abgelaufen – Seite wird neu geladen…', 'warning');
            setTimeout(function() { location.reload(); }, 1800);
        } else {
            $('#importError').removeClass('d-none').text(resp.message);
            $('#importDebugArea').removeClass('d-none');
            $('#importDebugOutput').addClass('d-none');
        }
    }, 'json').fail(function(xhr) {
        $('#importLoading').hide();
        var msg = 'Fehler beim Parsen des Dokuments';
        if (xhr.responseText) msg += ': ' + xhr.responseText.substring(0, 200);
        $('#importError').removeClass('d-none').text(msg);
        $('#importDebugArea').removeClass('d-none');
    });
});

function renderImportPreview(resp) {
    var s = resp.stats;
    var statsHtml = '<div class="stat-item"><i class="bi bi-list-check"></i> <strong>' + s.total + '</strong> Einsätze</div>' +
        '<div class="stat-item match-exact"><i class="bi bi-check-circle-fill"></i> <strong>' + s.matched + '</strong> zugeordnet</div>' +
        '<div class="stat-item match-none"><i class="bi bi-question-circle-fill"></i> <strong>' + s.unmatched + '</strong> nicht gefunden</div>';
    $('#importStats').html(statsHtml);

    var html = '<table class="table table-sm table-hover mb-0"><thead class="table-light"><tr>' +
        '<th>Datum</th><th>Zeit</th><th>Funktion</th><th>Name (Dokument)</th><th>Mitglied (DB)</th><th>Status</th></tr></thead><tbody>';

    resp.data.forEach(function(z) {
        var statusIcon, statusClass;
        if (z.match_status === 'exact') {
            statusIcon = '<i class="bi bi-check-circle-fill match-exact"></i>';
            statusClass = '';
        } else if (z.match_status === 'fuzzy') {
            statusIcon = '<i class="bi bi-exclamation-circle-fill match-fuzzy"></i>';
            statusClass = 'table-warning';
        } else {
            statusIcon = '<i class="bi bi-x-circle-fill match-none"></i>';
            statusClass = 'table-danger';
        }

        var datumFormatted = z.event_datum;
        try {
            var d = new Date(z.event_datum);
            datumFormatted = d.toLocaleDateString('de-CH');
        } catch(e) {}

        html += '<tr class="' + statusClass + '">' +
            '<td>' + datumFormatted + '</td>' +
            '<td>' + (z.event_zeit || '') + '</td>' +
            '<td>' + escHtml(z.funktion) + '</td>' +
            '<td>' + escHtml(z.mitglied_name) + '</td>' +
            '<td>' + escHtml(z.matched_name || '–') + '</td>' +
            '<td class="text-center">' + statusIcon + '</td></tr>';
    });

    html += '</tbody></table>';
    $('#importPreview').html(html);
}

$('#importSaveBtn').on('click', function() {
    if (!importData || !importDocId) return;

    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

    $.post('../api/einsatzplan_import.php', {
        action: 'save',
        dokument_id: importDocId,
        zuweisungen: JSON.stringify(importData),
        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
    }, function(resp) {
        if (resp.success) {
            msvToast(resp.message, 'success');
            $('#importModal').modal('hide');
            setTimeout(() => location.reload(), 800);
        } else if (resp.csrf_expired) {
            msvToast('Sitzung abgelaufen – Seite wird neu geladen…', 'warning');
            setTimeout(function() { location.reload(); }, 1800);
        } else {
            msvToast(resp.message, 'error');
        }
    }, 'json').fail(function() {
        msvToast('Fehler beim Speichern', 'error');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Importieren');
    });
});

// Debug-Infos laden
$('#importDebugBtn').on('click', function() {
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Lade...');

    $.post('../api/einsatzplan_import.php', {
        action: 'parse',
        dokument_id: importDocId,
        debug: 1,
        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
    }, function(resp) {
        var debugText = JSON.stringify(resp.debug || resp, null, 2);
        $('#importDebugOutput').text(debugText).removeClass('d-none');
    }, 'json').fail(function(xhr) {
        $('#importDebugOutput').text('Fehler: ' + xhr.responseText).removeClass('d-none');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="bi bi-bug me-1"></i>Debug-Infos laden');
    });
});

function escHtml(str) {
    if (!str) return '';
    return $('<span>').text(str).html();
}

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

// === Einsatz-Verwaltung ===
var ezMembersLoaded = false;

// Einsatz bearbeiten
$(document).on('click', '.btn-edit-ez', function() {
    var $b = $(this);
    $('#ezEditId').val($b.data('id'));
    $('#ezEditFunktion').val($b.data('funktion'));
    $('#ezEditName').val($b.data('name'));
    $('#ezEditDatum').val($b.data('datum'));
    $('#ezEditZeit').val($b.data('zeit'));

    // Mitglieder-Dropdown laden (einmalig)
    if (!ezMembersLoaded) {
        $.post('../api/einsatzplan_import.php', {
            action: 'members',
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        }, function(resp) {
            if (resp.success) {
                var $sel = $('#ezEditMitglied');
                $sel.find('option:not(:first)').remove();
                resp.data.forEach(function(m) {
                    $sel.append('<option value="' + m.id + '">' + escHtml(m.name + ' ' + m.vorname) + '</option>');
                });
                ezMembersLoaded = true;
                $('#ezEditMitglied').val($b.data('mid') || '');
            }
        }, 'json');
    } else {
        $('#ezEditMitglied').val($b.data('mid') || '');
    }

    $('#ezEditModal').modal('show');
});

$('#ezEditForm').on('submit', function(e) {
    e.preventDefault();
    var $btn = $('#ezEditSaveBtn');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');

    $.post('../api/einsatzplan_import.php', {
        action: 'update',
        id: $('#ezEditId').val(),
        funktion: $('#ezEditFunktion').val(),
        mitglied_name: $('#ezEditName').val(),
        mitglied_id: $('#ezEditMitglied').val(),
        event_datum: $('#ezEditDatum').val(),
        event_zeit: $('#ezEditZeit').val(),
        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
    }, function(resp) {
        if (resp.success) {
            msvToast(resp.message, 'success');
            $('#ezEditModal').modal('hide');
            setTimeout(() => location.reload(), 600);
        } else {
            msvToast(resp.message, 'error');
        }
    }, 'json').fail(function() {
        msvToast('Fehler beim Speichern', 'error');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Speichern');
    });
});

// Einzelnen Einsatz löschen
$(document).on('click', '.btn-delete-ez', function() {
    var id = $(this).data('id');
    var name = $(this).data('name');
    msvConfirmDelete('Einsatz "' + name + '"').then(result => {
        if (result.isConfirmed) {
            $.post('../api/einsatzplan_import.php', {
                action: 'delete',
                id: id,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            }, function(resp) {
                if (resp.success) {
                    msvToast(resp.message, 'success');
                    $('#ez-row-' + id).fadeOut();
                } else {
                    msvToast(resp.message, 'error');
                }
            }, 'json');
        }
    });
});

// Alle Einträge eines Dokuments löschen
$(document).on('click', '.btn-delete-all-ez', function() {
    var dokId = $(this).data('dokid');
    var titel = $(this).data('titel');
    msvConfirm('Alle importierten Einträge für "' + titel + '" löschen?', 'Alle löschen').then(result => {
        if (result.isConfirmed) {
            $.post('../api/einsatzplan_import.php', {
                action: 'delete_all',
                dokument_id: dokId,
                csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
            }, function(resp) {
                if (resp.success) {
                    msvToast(resp.message, 'success');
                    setTimeout(() => location.reload(), 600);
                } else {
                    msvToast(resp.message, 'error');
                }
            }, 'json');
        }
    });
});
</script>

<?php include 'portal_footer.php'; ?>
