<?php
/**
 * Dokumente-Verwaltung (Admin) – zentrale Verwaltung ALLER Vereins-Dokumente:
 *   • Einsatzpläne   (typ = einsatzplan)
 *   • Protokolle     (typ = protokoll)
 *   • JSK-Dokumente  (typ = jsk)
 *
 * Upload / Bearbeiten / Löschen laufen hier im Desktop-Admin – NICHT mehr über die PWA.
 * Die Portal-Seiten (einsatzplaene.php / protokolle.php / jsk_dokumente.php) sind reine Ansichten.
 * Backend wird wiederverwendet:
 *   api/dokument_upload.php · dokument_update.php · dokument_delete.php · dokument_download.php
 */
try { include 'dbconnect.inc.php'; } catch (Exception $e) { die("System error."); }
require_once __DIR__ . '/../auth.php';

// Zugriffsschutz: nur Vorstand/Admin (vor header.inc.php, da dieser Output erzeugt)
if (!isset($_SESSION['user_id']) || !(isAdmin() || isVorstand())) {
    if (($_SESSION['user_id'] ?? 0) != 1) {
        header('Location: home.php');
        exit();
    }
}

$page_specific_css = <<<'CSS'
.main-content-wrapper { max-width: 1040px; }
.dv-upload { background:#f8fafc; border:2px dashed #cbd5e1; border-radius:0.75rem; padding:1rem 1.1rem; margin-bottom:1.1rem; }
.dv-upload h6 { color:#475569; }
.dv-table { width:100%; }
.dv-table td { vertical-align:middle; border-bottom:1px solid #eef1f6; font-size:0.85rem; }
.dv-table tr:last-child td { border-bottom:0; }
.dv-chip { width:38px; height:38px; border-radius:0.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:1.15rem; }
.dv-chip.red   { background:#fde2e2; color:#c0392b; }
.dv-chip.green { background:#d1f4dd; color:#1e7e44; }
.dv-chip.blue  { background:#dbe4f7; color:#2d4373; }
.dv-title { font-weight:600; }
.dv-meta  { font-size:0.8rem; color:#94a3b8; }
.dv-empty { text-align:center; color:#94a3b8; padding:2rem 1rem; }
.vis-badge { font-size:0.66rem; vertical-align:middle; }
CSS;

include 'header.inc.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf          = $_SESSION['csrf_token'];
$db            = getDB();
$isAdminUser   = isAdmin();
$selected_year = intval($_GET['year'] ?? date('Y'));

// Dokumente eines Typs für das gewählte Jahr laden (Manager sehen alle Sichtbarkeiten)
$ladeDok = function (PDO $db, string $typ, int $jahr): array {
    $stmt = $db->prepare(
        "SELECT d.*, u.full_name AS uploader_name
           FROM vorstand_dokumente d
           LEFT JOIN users u ON d.hochgeladen_von = u.id
          WHERE d.typ = ? AND (d.jahr = ? OR d.jahr IS NULL)
          ORDER BY d.datum DESC, d.hochgeladen_am DESC"
    );
    $stmt->execute([$typ, $jahr]);
    return $stmt->fetchAll();
};
$docsEins = $ladeDok($db, 'einsatzplan', $selected_year);
$docsProt = $ladeDok($db, 'protokoll',   $selected_year);
$docsJsk  = $ladeDok($db, 'jsk',         $selected_year);

$years = $db->query("SELECT DISTINCT jahr FROM vorstand_dokumente WHERE jahr IS NOT NULL ORDER BY jahr DESC")->fetchAll(PDO::FETCH_COLUMN);
$years = array_map('intval', $years);
if (!in_array((int) date('Y'), $years, true)) array_unshift($years, (int) date('Y'));
if (empty($years)) $years = [(int) date('Y')];

// --- Einsatzplan-spezifisch: importierte Einsätze + Tausch-Log (nur Einsatzpläne-Tab) ---
$wochentage = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
$einsaetze = [];
$einsaetze_grouped = [];
try {
    $ez = $db->prepare(
        "SELECT z.*, m.Name AS m_name, m.Vorname AS m_vorname, d.titel AS dok_titel
           FROM einsatz_zuweisungen z
           LEFT JOIN mitglieder m ON z.mitglied_id = m.ID
           LEFT JOIN vorstand_dokumente d ON z.dokument_id = d.id
          WHERE z.jahr = ?
          ORDER BY z.event_datum, z.bezeichnung, z.funktion, z.mitglied_name"
    );
    $ez->execute([$selected_year]);
    $einsaetze = $ez->fetchAll();
    foreach ($einsaetze as $e) { $einsaetze_grouped[$e['event_datum'] . '|' . $e['bezeichnung']][] = $e; }
} catch (Throwable $e) { $einsaetze = []; $einsaetze_grouped = []; }

$tausch_log = [];
try {
    $tl = $db->prepare(
        "SELECT t.id, t.typ, t.status, t.erstellt_am, t.entschieden_am,
                vm.Vorname AS von_vorname, vm.Name AS von_name,
                am.Vorname AS an_vorname, am.Name AS an_name,
                ea.bezeichnung AS a_bez, ea.event_datum AS a_datum,
                eb.bezeichnung AS b_bez, eb.event_datum AS b_datum
           FROM einsatz_tausch t
           JOIN mitglieder vm ON vm.ID = t.von_mitglied_id
           JOIN mitglieder am ON am.ID = t.an_mitglied_id
           LEFT JOIN einsatz_zuweisungen ea ON ea.id = t.einsatz_a_id
           LEFT JOIN einsatz_zuweisungen eb ON eb.id = t.einsatz_b_id
          WHERE YEAR(t.erstellt_am) = ?
          ORDER BY t.erstellt_am DESC LIMIT 100"
    );
    $tl->execute([$selected_year]);
    $tausch_log = $tl->fetchAll();
} catch (Throwable $e) { $tausch_log = []; }

/** Rendert die Dokumentliste eines Typs als Tabelle. $typ='einsatzplan' ergänzt den Import-Button. */
function dv_render_list(array $docs, string $typ = ''): void {
    if (empty($docs)) {
        echo '<div class="dv-empty"><i class="bi bi-folder2-open d-block mb-2" style="font-size:2rem;"></i>Keine Dokumente für dieses Jahr.</div>';
        return;
    }
    echo '<div class="table-responsive"><table class="table table-hover align-middle dv-table mb-0"><tbody>';
    foreach ($docs as $doc) {
        $ext     = strtolower(pathinfo($doc['dateiname'], PATHINFO_EXTENSION));
        $variant = $ext === 'pdf' ? 'red' : (in_array($ext, ['xlsx','xls','jpg','jpeg','png']) ? 'green' : 'blue');
        $icon    = $ext === 'pdf' ? 'pdf' : (in_array($ext, ['xlsx','xls']) ? 'excel' : (in_array($ext, ['jpg','jpeg','png']) ? 'image' : 'word'));
        $sizeKb  = round(($doc['dateigroesse'] ?? 0) / 1024);
        $vis     = $doc['sichtbar_fuer'];
        $visBadge = $vis === 'admin'
            ? '<span class="badge bg-danger vis-badge">Nur Admin</span>'
            : ($vis === 'vorstand'
                ? '<span class="badge bg-warning text-dark vis-badge">Nur Vorstand</span>'
                : '<span class="badge bg-success vis-badge">Alle</span>');
        $id = (int) $doc['id'];
        ?>
        <tr id="dvdoc-<?= $id ?>">
          <td style="width:46px;"><span class="dv-chip <?= $variant ?>"><i class="bi bi-file-earmark-<?= $icon ?>"></i></span></td>
          <td>
            <div class="dv-title"><?= htmlspecialchars($doc['titel']) ?> <?= $visBadge ?></div>
            <div class="dv-meta">
              <?php if ($doc['datum']): ?><i class="bi bi-calendar me-1"></i><?= date('d.m.Y', strtotime($doc['datum'])) ?> &middot; <?php endif; ?>
              <?= htmlspecialchars($doc['dateiname']) ?> (<?= $sizeKb ?> KB)
              <?php if (!empty($doc['uploader_name'])): ?> &middot; von <?= htmlspecialchars($doc['uploader_name']) ?><?php endif; ?>
            </div>
            <?php if (!empty($doc['beschreibung'])): ?><div class="dv-meta"><?= htmlspecialchars($doc['beschreibung']) ?></div><?php endif; ?>
          </td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="../api/dokument_download.php?id=<?= $id ?>" target="_blank" rel="noopener" title="Öffnen" aria-label="Öffnen"><i class="bi bi-eye"></i></a>
            <?php if ($typ === 'einsatzplan' && in_array($ext, ['docx','pdf','xlsx','xls'], true)): ?>
            <button class="btn btn-sm btn-outline-success btn-import-einsatz" data-id="<?= $id ?>" data-titel="<?= htmlspecialchars($doc['titel'], ENT_QUOTES) ?>" title="Einsätze importieren" aria-label="Einsätze importieren"><i class="bi bi-table"></i></button>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-primary btn-edit-doc"
              data-id="<?= $id ?>"
              data-titel="<?= htmlspecialchars($doc['titel'], ENT_QUOTES) ?>"
              data-beschreibung="<?= htmlspecialchars($doc['beschreibung'] ?? '', ENT_QUOTES) ?>"
              data-datum="<?= htmlspecialchars($doc['datum'] ?? '', ENT_QUOTES) ?>"
              data-sichtbar="<?= htmlspecialchars($vis, ENT_QUOTES) ?>"
              data-dateiname="<?= htmlspecialchars($doc['dateiname'], ENT_QUOTES) ?>"
              title="Bearbeiten" aria-label="Bearbeiten"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger" onclick="dvDelete(<?= $id ?>, '<?= htmlspecialchars($doc['titel'], ENT_QUOTES) ?>')" title="Löschen" aria-label="Löschen"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
        <?php
    }
    echo '</tbody></table></div>';
}

/** Sichtbarkeits-Optionen für Einsatzpläne/Protokolle (JSK ist immer „alle Mitglieder"). */
function dv_vis_options(bool $isAdminUser, string $current = 'alle_mitglieder'): void {
    $opts = [];
    if ($isAdminUser) $opts['admin'] = 'Nur Admin';
    $opts['vorstand']       = 'Nur Vorstand';
    $opts['alle_mitglieder'] = 'Alle Mitglieder';
    foreach ($opts as $val => $label) {
        $sel = $val === $current ? ' selected' : '';
        echo '<option value="' . $val . '"' . $sel . '>' . $label . '</option>';
    }
}
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <h2 class="h4 mb-0 page-title">Dokumente-Verwaltung</h2>
          <form method="get" class="d-flex align-items-center gap-2">
            <label class="text-muted small mb-0">Jahr</label>
            <select name="year" class="form-select form-select-sm" style="max-width:120px;" onchange="this.form.submit()">
              <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $y === $selected_year ? 'selected' : '' ?>><?= $y ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>

        <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabEins" type="button"><i class="bi bi-calendar-check me-1"></i>Einsatzpläne <span class="badge bg-secondary ms-1"><?= count($docsEins) ?></span></button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabProt" type="button"><i class="bi bi-file-text me-1"></i>Protokolle <span class="badge bg-secondary ms-1"><?= count($docsProt) ?></span></button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabJsk" type="button"><i class="bi bi-mortarboard me-1"></i>JSK-Dokumente <span class="badge bg-secondary ms-1"><?= count($docsJsk) ?></span></button>
          </li>
        </ul>

        <div class="tab-content">

          <!-- TAB: Einsatzpläne -->
          <div class="tab-pane fade show active" id="tabEins" role="tabpanel">
            <div class="dv-upload">
              <h6 class="mb-3"><i class="bi bi-cloud-upload me-2"></i>Neuen Einsatzplan hochladen</h6>
              <form class="js-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="typ" value="einsatzplan">
                <div class="row g-2">
                  <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="titel" placeholder="Titel (z.B. Obligatorisch 2026) *" required></div>
                  <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="datum" value="<?= date('Y-m-d') ?>" required></div>
                  <div class="col-md-3"><select class="form-select form-select-sm" name="sichtbar_fuer"><?php dv_vis_options($isAdminUser); ?></select></div>
                  <div class="col-md-3"><input type="file" class="form-control form-control-sm" name="datei" accept=".pdf,.docx,.xlsx,.xls,.jpg,.jpeg,.png" required></div>
                </div>
                <div class="row g-2 mt-1">
                  <div class="col-md-10"><input type="text" class="form-control form-control-sm" name="beschreibung" placeholder="Beschreibung (optional)"></div>
                  <div class="col-md-2"><button type="submit" class="btn btn-outline-success btn-sm w-100"><i class="bi bi-upload me-1"></i>Hochladen</button></div>
                </div>
                <div class="form-text">Word/Excel-Einsatzpläne werden automatisch „Nur Admin".</div>
              </form>
            </div>
            <?php dv_render_list($docsEins, 'einsatzplan'); ?>

            <!-- Importierte Einsätze -->
            <?php if (!empty($einsaetze_grouped)): ?>
            <hr class="my-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Importierte Einsätze <?= $selected_year ?></h5>
              <span class="badge bg-secondary"><?= count($einsaetze) ?> Einträge</span>
            </div>
            <?php $groupIdx = 0; foreach ($einsaetze_grouped as $key => $entries):
              $first = $entries[0]; $ts = strtotime($first['event_datum']); $groupIdx++; ?>
            <div class="card mb-2">
              <div class="card-header py-2 d-flex justify-content-between align-items-center" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#ezGroup<?= $groupIdx ?>">
                <div>
                  <strong><?= htmlspecialchars($first['bezeichnung']) ?></strong>
                  <span class="text-muted ms-2"><?= $wochentage[(int) date('w', $ts)] ?> <?= date('d.m.Y', $ts) ?></span>
                  <?php if ($first['event_zeit']): ?><span class="text-muted"><?= htmlspecialchars($first['event_zeit']) ?></span><?php endif; ?>
                  <span class="badge bg-primary ms-2"><?= count($entries) ?></span>
                </div>
                <div>
                  <?php if ($first['dokument_id']): ?>
                  <button class="btn btn-sm btn-outline-danger btn-delete-all-ez" data-dokid="<?= $first['dokument_id'] ?>" data-titel="<?= htmlspecialchars($first['bezeichnung'], ENT_QUOTES) ?>" onclick="event.stopPropagation();" title="Alle Einträge dieses Imports löschen"><i class="bi bi-trash me-1"></i>Alle</button>
                  <?php endif; ?>
                  <i class="bi bi-chevron-down"></i>
                </div>
              </div>
              <div class="collapse show" id="ezGroup<?= $groupIdx ?>">
                <div class="card-body p-0">
                  <table class="table table-sm table-hover mb-0" style="font-size:0.85rem;">
                    <thead class="table-light"><tr><th>Funktion</th><th>Name (Dokument)</th><th>Mitglied (DB)</th><th style="width:80px;"></th></tr></thead>
                    <tbody>
                    <?php foreach ($entries as $e): ?>
                      <tr id="ez-row-<?= $e['id'] ?>">
                        <td><?= htmlspecialchars($e['funktion']) ?></td>
                        <td><?= htmlspecialchars($e['mitglied_name']) ?></td>
                        <td>
                          <?php if ($e['mitglied_id']): ?>
                            <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($e['m_name'] . ' ' . $e['m_vorname']) ?></span>
                          <?php else: ?>
                            <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Nicht zugeordnet</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-end">
                          <button class="btn btn-sm btn-outline-primary py-0 px-1 btn-edit-ez"
                            data-id="<?= $e['id'] ?>"
                            data-funktion="<?= htmlspecialchars($e['funktion'], ENT_QUOTES) ?>"
                            data-name="<?= htmlspecialchars($e['mitglied_name'], ENT_QUOTES) ?>"
                            data-mid="<?= $e['mitglied_id'] ?? '' ?>"
                            data-datum="<?= $e['event_datum'] ?>"
                            data-zeit="<?= htmlspecialchars($e['event_zeit'] ?? '', ENT_QUOTES) ?>"
                            title="Bearbeiten"><i class="bi bi-pencil"></i></button>
                          <button class="btn btn-sm btn-outline-danger py-0 px-1 btn-delete-ez" data-id="<?= $e['id'] ?>" data-name="<?= htmlspecialchars($e['mitglied_name'], ENT_QUOTES) ?>" title="Löschen"><i class="bi bi-trash"></i></button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Einsatz-Tausche & Übernahmen (read-only) -->
            <?php if (!empty($tausch_log)): ?>
            <hr class="my-4">
            <h5 class="mb-1"><i class="bi bi-arrow-left-right me-2"></i>Einsatz-Tausche &amp; Übernahmen <?= $selected_year ?></h5>
            <p class="text-muted small mb-2">Von den Mitgliedern selbst abgewickelt. Bei Bedarf über die Einsatz-Bearbeitung korrigierbar.</p>
            <div class="table-responsive">
              <table class="table table-sm table-hover" style="font-size:0.85rem;">
                <thead class="table-light"><tr><th>Datum</th><th>Art</th><th>Von → An</th><th>Einsatz</th><th>Status</th></tr></thead>
                <tbody>
                <?php
                $tauschStatusMap = ['offen'=>['Offen','bg-warning text-dark'],'bestaetigt'=>['Bestätigt','bg-success'],'abgelehnt'=>['Abgelehnt','bg-secondary'],'zurueckgezogen'=>['Zurückgezogen','bg-light text-dark border']];
                foreach ($tausch_log as $r):
                  $stx = $tauschStatusMap[$r['status']] ?? [$r['status'], 'bg-secondary'];
                  $datumRef = $r['entschieden_am'] ?: $r['erstellt_am']; ?>
                  <tr>
                    <td><?= $datumRef ? date('d.m.Y', strtotime($datumRef)) : '' ?></td>
                    <td><?= $r['typ'] === 'tausch' ? 'Tausch' : 'Übernahme' ?></td>
                    <td><?= htmlspecialchars(trim($r['von_vorname'] . ' ' . $r['von_name'])) ?> &rarr; <?= htmlspecialchars(trim($r['an_vorname'] . ' ' . $r['an_name'])) ?></td>
                    <td>
                      <?= htmlspecialchars($r['a_bez'] ?? '—') ?><?php if (!empty($r['a_datum'])): ?> <span class="text-muted">(<?= date('d.m.Y', strtotime($r['a_datum'])) ?>)</span><?php endif; ?>
                      <?php if ($r['typ'] === 'tausch' && !empty($r['b_bez'])): ?><br><span class="text-muted">&harr; <?= htmlspecialchars($r['b_bez']) ?><?php if (!empty($r['b_datum'])): ?> (<?= date('d.m.Y', strtotime($r['b_datum'])) ?>)<?php endif; ?></span><?php endif; ?>
                    </td>
                    <td><span class="badge <?= $stx[1] ?>"><?= $stx[0] ?></span></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>

          <!-- TAB: Protokolle -->
          <div class="tab-pane fade" id="tabProt" role="tabpanel">
            <div class="dv-upload">
              <h6 class="mb-3"><i class="bi bi-cloud-upload me-2"></i>Neues Protokoll hochladen</h6>
              <form class="js-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="typ" value="protokoll">
                <div class="row g-2">
                  <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="titel" placeholder="Titel (z.B. GV-Protokoll 2025) *" required></div>
                  <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="datum" value="<?= date('Y-m-d') ?>" required></div>
                  <div class="col-md-3"><select class="form-select form-select-sm" name="sichtbar_fuer"><?php dv_vis_options($isAdminUser); ?></select></div>
                  <div class="col-md-3"><input type="file" class="form-control form-control-sm" name="datei" accept=".pdf,.docx,.jpg,.jpeg,.png" required></div>
                </div>
                <div class="row g-2 mt-1">
                  <div class="col-md-10"><input type="text" class="form-control form-control-sm" name="beschreibung" placeholder="Beschreibung (optional)"></div>
                  <div class="col-md-2"><button type="submit" class="btn btn-outline-success btn-sm w-100"><i class="bi bi-upload me-1"></i>Hochladen</button></div>
                </div>
              </form>
            </div>
            <?php dv_render_list($docsProt); ?>
          </div>

          <!-- TAB: JSK-Dokumente -->
          <div class="tab-pane fade" id="tabJsk" role="tabpanel">
            <div class="dv-upload">
              <h6 class="mb-3"><i class="bi bi-cloud-upload me-2"></i>Neues JSK-Dokument hochladen</h6>
              <form class="js-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="typ" value="jsk">
                <input type="hidden" name="sichtbar_fuer" value="alle_mitglieder">
                <div class="row g-2">
                  <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="titel" placeholder="Titel (z.B. Standblatt Kurs 1) *" required></div>
                  <div class="col-md-3"><input type="date" class="form-control form-control-sm" name="datum" value="<?= date('Y-m-d') ?>" required></div>
                  <div class="col-md-4"><input type="file" class="form-control form-control-sm" name="datei" accept=".pdf,.docx,.jpg,.jpeg,.png" required></div>
                </div>
                <div class="row g-2 mt-1">
                  <div class="col-md-10"><input type="text" class="form-control form-control-sm" name="beschreibung" placeholder="Beschreibung (optional)"></div>
                  <div class="col-md-2"><button type="submit" class="btn btn-outline-success btn-sm w-100"><i class="bi bi-upload me-1"></i>Hochladen</button></div>
                </div>
              </form>
            </div>
            <?php dv_render_list($docsJsk); ?>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Edit-Modal (für alle Typen) -->
<div class="modal fade" id="dvEditModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Dokument bearbeiten</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="dvEditForm" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="id" id="dvEditId">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">Titel *</label>
            <input type="text" class="form-control" name="titel" id="dvEditTitel" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Datum *</label>
            <input type="date" class="form-control" name="datum" id="dvEditDatum" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Sichtbarkeit</label>
            <select class="form-select" name="sichtbar_fuer" id="dvEditSichtbar"><?php dv_vis_options($isAdminUser); ?></select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Beschreibung</label>
            <input type="text" class="form-control" name="beschreibung" id="dvEditBeschreibung" placeholder="Optional">
          </div>
          <div class="mb-0">
            <label class="form-label fw-semibold">Datei ersetzen</label>
            <div class="text-muted small mb-1">Aktuelle Datei: <span id="dvEditCurrentFile" class="fw-medium text-dark"></span></div>
            <input type="file" class="form-control" name="datei" id="dvEditDatei" accept=".pdf,.docx,.xlsx,.xls,.jpg,.jpeg,.png">
            <div class="form-text">Leer lassen, um die bestehende Datei zu behalten.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-outline-primary btn-sm" id="dvEditSaveBtn"><i class="bi bi-save me-1"></i>Speichern</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Import-Modal (Einsätze aus Einsatzplan) -->
<div class="modal fade" id="importModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-table me-2"></i>Einsätze importieren</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3" id="importDocTitle"></p>
        <div id="importLoading" class="text-center py-4">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="text-muted mt-2">Dokument wird analysiert...</p>
        </div>
        <div id="importError" class="alert alert-danger d-none"></div>
        <div id="importDebugArea" class="d-none mt-2">
          <button class="btn btn-sm btn-outline-secondary" id="importDebugBtn"><i class="bi bi-bug me-1"></i>Debug-Infos laden</button>
          <pre id="importDebugOutput" class="d-none mt-2 p-2 bg-light border rounded" style="max-height:300px;overflow:auto;font-size:0.75rem;"></pre>
        </div>
        <div id="importResult" class="d-none">
          <div class="import-stats d-flex flex-wrap gap-3 p-2 bg-light rounded mb-3" id="importStats" style="font-size:0.9rem;"></div>
          <div id="importPreview" style="max-height:400px;overflow-y:auto;"></div>
        </div>
      </div>
      <div class="modal-footer" id="importFooter" style="display:none;">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-success btn-sm" id="importSaveBtn"><i class="bi bi-check-lg me-1"></i>Importieren</button>
      </div>
    </div>
  </div>
</div>

<!-- Einsatz bearbeiten -->
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
          <div class="mb-3"><label class="form-label fw-semibold">Funktion</label><input type="text" class="form-control" name="funktion" id="ezEditFunktion"></div>
          <div class="mb-3"><label class="form-label fw-semibold">Name (Dokument)</label><input type="text" class="form-control" name="mitglied_name" id="ezEditName" required></div>
          <div class="mb-3"><label class="form-label fw-semibold">Mitglied zuordnen</label><select class="form-select" name="mitglied_id" id="ezEditMitglied"><option value="">– Nicht zugeordnet –</option></select></div>
          <div class="row g-2">
            <div class="col-7"><label class="form-label fw-semibold">Datum</label><input type="date" class="form-control" name="event_datum" id="ezEditDatum"></div>
            <div class="col-5"><label class="form-label fw-semibold">Zeit</label><input type="text" class="form-control" name="event_zeit" id="ezEditZeit" placeholder="18:00 – 20:00"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
          <button type="submit" class="btn btn-outline-primary btn-sm" id="ezEditSaveBtn"><i class="bi bi-save me-1"></i>Speichern</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var CSRF = document.getElementById('csrfToken').value;
  var editModal = new bootstrap.Modal(document.getElementById('dvEditModal'));

  // Direkt zu einem Tab springen (z.B. aus der JSK-Verwaltung: dokumente_verwaltung.php#tabJsk)
  if (window.location.hash) {
    var trigger = document.querySelector('[data-bs-target="' + window.location.hash + '"]');
    if (trigger) { new bootstrap.Tab(trigger).show(); }
  }

  // Direkt zu einem Tab springen (z.B. aus der JSK-Verwaltung: dokumente_verwaltung.php#tabJsk)
  if (window.location.hash) {
    var trigger = document.querySelector('[data-bs-target="' + window.location.hash + '"]');
    if (trigger) { new bootstrap.Tab(trigger).show(); }
  }

  // Upload (alle Tabs)
  $('.js-upload-form').on('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(this);
    var $btn = $(this).find('button[type="submit"]');
    var orig = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({
      url: '../api/dokument_upload.php', type: 'POST', data: fd,
      processData: false, contentType: false, dataType: 'json',
      success: function (r) {
        if (r.success) { msvToast(r.message, 'success'); setTimeout(function () { location.reload(); }, 700); }
        else { msvToast(r.message, 'error'); }
      },
      error: function () { msvToast('Upload fehlgeschlagen', 'error'); },
      complete: function () { $btn.prop('disabled', false).html(orig); }
    });
  });

  // Bearbeiten öffnen
  $(document).on('click', '.btn-edit-doc', function () {
    var $b = $(this);
    $('#dvEditId').val($b.data('id'));
    $('#dvEditTitel').val($b.data('titel'));
    $('#dvEditBeschreibung').val($b.data('beschreibung'));
    $('#dvEditDatum').val($b.data('datum'));
    $('#dvEditSichtbar').val($b.data('sichtbar'));
    $('#dvEditCurrentFile').text($b.data('dateiname'));
    $('#dvEditDatei').val('');
    editModal.show();
  });

  // Bearbeiten speichern
  $('#dvEditForm').on('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(this);
    var $btn = $('#dvEditSaveBtn');
    var orig = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({
      url: '../api/dokument_update.php', type: 'POST', data: fd,
      processData: false, contentType: false, dataType: 'json',
      success: function (r) {
        if (r.success) { msvToast(r.message, 'success'); editModal.hide(); setTimeout(function () { location.reload(); }, 600); }
        else { msvToast(r.message, 'error'); }
      },
      error: function () { msvToast('Fehler beim Speichern', 'error'); },
      complete: function () { $btn.prop('disabled', false).html(orig); }
    });
  });

  // Löschen
  window.dvDelete = function (id, title) {
    msvConfirmDelete('Dokument "' + title + '"').then(function (res) {
      if (res.isConfirmed) {
        $.post('../api/dokument_delete.php', { id: id, csrf_token: CSRF }, function (r) {
          if (r.success) { msvToast(r.message, 'success'); $('#dvdoc-' + id).fadeOut(); }
          else { msvToast(r.message, 'error'); }
        }, 'json');
      }
    });
  };

  function escHtml(str) { if (!str) return ''; return $('<span>').text(str).html(); }

  // ===== Einsatz-Import (aus Einsatzplan-Dokument) =====
  var importData = null, importDocId = null;
  var importModal = new bootstrap.Modal(document.getElementById('importModal'));
  var ezEditModal = new bootstrap.Modal(document.getElementById('ezEditModal'));

  $('#importModal').on('show.bs.modal', function () { $(this).removeAttr('aria-hidden'); });

  $(document).on('click', '.btn-import-einsatz', function () {
    importDocId = $(this).data('id');
    $('#importDocTitle').text($(this).data('titel'));
    $('#importLoading').show();
    $('#importError').addClass('d-none');
    $('#importResult').addClass('d-none');
    $('#importFooter').hide();
    importData = null;
    importModal.show();
    $.post('../api/einsatzplan_import.php', { action: 'parse', dokument_id: importDocId, csrf_token: CSRF }, function (resp) {
      $('#importLoading').hide();
      if (resp.success) {
        importData = resp.data;
        renderImportPreview(resp);
        $('#importResult').removeClass('d-none');
        $('#importFooter').show();
        $('#importDebugArea').addClass('d-none');
      } else if (resp.csrf_expired) {
        importModal.hide(); msvToast('Sitzung abgelaufen – Seite wird neu geladen…', 'warning');
        setTimeout(function () { location.reload(); }, 1800);
      } else {
        $('#importError').removeClass('d-none').text(resp.message);
        $('#importDebugArea').removeClass('d-none');
        $('#importDebugOutput').addClass('d-none');
      }
    }, 'json').fail(function (xhr) {
      $('#importLoading').hide();
      var msg = 'Fehler beim Parsen des Dokuments';
      if (xhr.responseText) msg += ': ' + xhr.responseText.substring(0, 200);
      $('#importError').removeClass('d-none').text(msg);
      $('#importDebugArea').removeClass('d-none');
    });
  });

  function renderImportPreview(resp) {
    var s = resp.stats;
    $('#importStats').html(
      '<div><i class="bi bi-list-check"></i> <strong>' + s.total + '</strong> Einsätze</div>' +
      '<div class="text-success"><i class="bi bi-check-circle-fill"></i> <strong>' + s.matched + '</strong> zugeordnet</div>' +
      '<div class="text-danger"><i class="bi bi-question-circle-fill"></i> <strong>' + s.unmatched + '</strong> nicht gefunden</div>'
    );
    var html = '<table class="table table-sm table-hover mb-0"><thead class="table-light"><tr>' +
      '<th>Datum</th><th>Zeit</th><th>Funktion</th><th>Name (Dokument)</th><th>Mitglied (DB)</th><th>Status</th></tr></thead><tbody>';
    resp.data.forEach(function (z) {
      var icon, cls;
      if (z.match_status === 'exact') { icon = '<i class="bi bi-check-circle-fill text-success"></i>'; cls = ''; }
      else if (z.match_status === 'fuzzy') { icon = '<i class="bi bi-exclamation-circle-fill" style="color:#e67e00;"></i>'; cls = 'table-warning'; }
      else { icon = '<i class="bi bi-x-circle-fill text-danger"></i>'; cls = 'table-danger'; }
      var df = z.event_datum;
      try { df = new Date(z.event_datum).toLocaleDateString('de-CH'); } catch (e) {}
      html += '<tr class="' + cls + '"><td>' + df + '</td><td>' + (z.event_zeit || '') + '</td><td>' + escHtml(z.funktion) +
        '</td><td>' + escHtml(z.mitglied_name) + '</td><td>' + escHtml(z.matched_name || '–') + '</td><td class="text-center">' + icon + '</td></tr>';
    });
    $('#importPreview').html(html + '</tbody></table>');
  }

  $('#importSaveBtn').on('click', function () {
    if (!importData || !importDocId) return;
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
    $.post('../api/einsatzplan_import.php', { action: 'save', dokument_id: importDocId, zuweisungen: JSON.stringify(importData), csrf_token: CSRF }, function (resp) {
      if (resp.success) { msvToast(resp.message, 'success'); importModal.hide(); setTimeout(function () { location.reload(); }, 800); }
      else if (resp.csrf_expired) { msvToast('Sitzung abgelaufen – Seite wird neu geladen…', 'warning'); setTimeout(function () { location.reload(); }, 1800); }
      else { msvToast(resp.message, 'error'); }
    }, 'json').fail(function () { msvToast('Fehler beim Speichern', 'error'); }).always(function () {
      $btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Importieren');
    });
  });

  $('#importDebugBtn').on('click', function () {
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Lade...');
    $.post('../api/einsatzplan_import.php', { action: 'parse', dokument_id: importDocId, debug: 1, csrf_token: CSRF }, function (resp) {
      $('#importDebugOutput').text(JSON.stringify(resp.debug || resp, null, 2)).removeClass('d-none');
    }, 'json').fail(function (xhr) {
      $('#importDebugOutput').text('Fehler: ' + xhr.responseText).removeClass('d-none');
    }).always(function () { $btn.prop('disabled', false).html('<i class="bi bi-bug me-1"></i>Debug-Infos laden'); });
  });

  // ===== Importierte Einsätze: bearbeiten / löschen =====
  var ezMembersLoaded = false;
  $(document).on('click', '.btn-edit-ez', function () {
    var $b = $(this);
    $('#ezEditId').val($b.data('id'));
    $('#ezEditFunktion').val($b.data('funktion'));
    $('#ezEditName').val($b.data('name'));
    $('#ezEditDatum').val($b.data('datum'));
    $('#ezEditZeit').val($b.data('zeit'));
    if (!ezMembersLoaded) {
      $.post('../api/einsatzplan_import.php', { action: 'members', csrf_token: CSRF }, function (resp) {
        if (resp.success) {
          var $sel = $('#ezEditMitglied');
          $sel.find('option:not(:first)').remove();
          resp.data.forEach(function (m) { $sel.append('<option value="' + m.id + '">' + escHtml(m.name + ' ' + m.vorname) + '</option>'); });
          ezMembersLoaded = true;
          $('#ezEditMitglied').val($b.data('mid') || '');
        }
      }, 'json');
    } else {
      $('#ezEditMitglied').val($b.data('mid') || '');
    }
    ezEditModal.show();
  });

  $('#ezEditForm').on('submit', function (e) {
    e.preventDefault();
    var $btn = $('#ezEditSaveBtn');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
    $.post('../api/einsatzplan_import.php', {
      action: 'update', id: $('#ezEditId').val(), funktion: $('#ezEditFunktion').val(),
      mitglied_name: $('#ezEditName').val(), mitglied_id: $('#ezEditMitglied').val(),
      event_datum: $('#ezEditDatum').val(), event_zeit: $('#ezEditZeit').val(), csrf_token: CSRF
    }, function (resp) {
      if (resp.success) { msvToast(resp.message, 'success'); ezEditModal.hide(); setTimeout(function () { location.reload(); }, 600); }
      else { msvToast(resp.message, 'error'); }
    }, 'json').fail(function () { msvToast('Fehler beim Speichern', 'error'); }).always(function () {
      $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Speichern');
    });
  });

  $(document).on('click', '.btn-delete-ez', function () {
    var id = $(this).data('id'), name = $(this).data('name');
    msvConfirmDelete('Einsatz "' + name + '"').then(function (res) {
      if (res.isConfirmed) {
        $.post('../api/einsatzplan_import.php', { action: 'delete', id: id, csrf_token: CSRF }, function (resp) {
          if (resp.success) { msvToast(resp.message, 'success'); $('#ez-row-' + id).fadeOut(); }
          else { msvToast(resp.message, 'error'); }
        }, 'json');
      }
    });
  });

  $(document).on('click', '.btn-delete-all-ez', function () {
    var dokId = $(this).data('dokid'), titel = $(this).data('titel');
    msvConfirm('Alle importierten Einträge für "' + titel + '" löschen?', 'Alle löschen').then(function (res) {
      if (res.isConfirmed) {
        $.post('../api/einsatzplan_import.php', { action: 'delete_all', dokument_id: dokId, csrf_token: CSRF }, function (resp) {
          if (resp.success) { msvToast(resp.message, 'success'); setTimeout(function () { location.reload(); }, 600); }
          else { msvToast(resp.message, 'error'); }
        }, 'json');
      }
    });
  });
})();
</script>

<?php include 'footer.inc.php'; ?>
