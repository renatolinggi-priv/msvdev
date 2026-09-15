<?php
/**
 * Dokumente verwalten (Admin) – zentrale Verwaltung ALLER Vereins-Dokumente:
 *   • Einsatzpläne   (typ = einsatzplan)
 *   • Protokolle     (typ = protokoll)
 *   • JSK-Dokumente  (typ = jsk)
 *
 * Upload / Bearbeiten / Löschen laufen hier im Desktop-Admin – NICHT mehr über die PWA.
 * Die Portal-Seiten (einsatzplaene.php / protokolle.php / jsk_dokumente.php) sind reine Ansichten.
 * Backend wird wiederverwendet:
 *   api/dokument_upload.php · dokument_update.php · dokument_delete.php · dokument_download.php
 */
require_once 'dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';

// Zugriffsschutz: nur Vorstand/Admin (vor header.inc.php, da dieser Output erzeugt)
if (!(isAdmin() || isVorstand()) && (int)($_SESSION['user_id'] ?? 0) !== 1) {
    header('Location: home.php');
    exit();
}

$page_specific_css = <<<'CSS'
.dv-upload { background:#f8fafc; border:2px dashed #cbd5e1; border-radius:0.75rem; padding:1rem 1.1rem; margin-bottom:1.1rem; }
.dv-upload h6 { color:#475569; }
.dv-table { width:100%; }
.dv-table td { vertical-align:middle; border-bottom:1px solid #eef1f6; }
.dv-table tr:last-child td { border-bottom:0; }
.dv-chip { width:38px; height:38px; border-radius:0.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:1.15rem; }
.dv-chip.red   { background:#fde2e2; color:#c0392b; }
.dv-chip.green { background:#d1f4dd; color:#1e7e44; }
.dv-chip.blue  { background:#dbe4f7; color:#2d4373; }
.dv-title { font-weight:600; }
.dv-meta  { font-size:0.8rem; color:#94a3b8; }
.dv-empty { text-align:center; color:#94a3b8; padding:2rem 1rem; }
.dv-empty i { font-size:1.6rem; opacity:.5; display:block; margin-bottom:.5rem; }
.vis-badge { font-size:0.66rem; vertical-align:middle; }
.ez-group-header { cursor:pointer; }
.ez-note { font-size:.8rem; }
.import-preview { max-height:400px; overflow-y:auto; }
.import-debug { max-height:300px; overflow:auto; font-size:0.75rem; }
CSS;

include 'header.inc.php';

$csrf          = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
$db            = getDB();
$isAdminUser   = isAdmin();
$selected_year = (int)($_GET['year'] ?? date('Y'));
$debugMode     = isset($_GET['debug']); // Import-Debugausgabe nur auf Wunsch

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
if (!in_array($selected_year, $years, true)) { $years[] = $selected_year; rsort($years); }

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
    foreach ($einsaetze as $z) { $einsaetze_grouped[$z['event_datum'] . '|' . $z['bezeichnung']][] = $z; }
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
function dv_render_list(array $docs, string $typ): void {
    if (empty($docs)) {
        echo '<div class="dv-empty"><i class="bi bi-inbox"></i>Keine Dokumente für dieses Jahr gefunden</div>';
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
        $id    = (int) $doc['id'];
        $titel = htmlspecialchars($doc['titel'], ENT_QUOTES, 'UTF-8');
        ?>
        <tr id="dvdoc-<?= $id ?>">
          <td style="width:46px;"><span class="dv-chip <?= $variant ?>"><i class="bi bi-file-earmark-<?= $icon ?>"></i></span></td>
          <td>
            <div class="dv-title"><?= $titel ?> <?= $visBadge ?></div>
            <div class="dv-meta">
              <?php if ($doc['datum']): ?><i class="bi bi-calendar me-1"></i><?= date('d.m.Y', strtotime($doc['datum'])) ?> &middot; <?php endif; ?>
              <?= htmlspecialchars($doc['dateiname']) ?> (<?= $sizeKb ?> KB)
              <?php if (!empty($doc['uploader_name'])): ?> &middot; von <?= htmlspecialchars($doc['uploader_name']) ?><?php endif; ?>
            </div>
            <?php if (!empty($doc['beschreibung'])): ?><div class="dv-meta"><?= htmlspecialchars($doc['beschreibung']) ?></div><?php endif; ?>
          </td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-info" href="../api/dokument_download.php?id=<?= $id ?>" target="_blank" rel="noopener" data-tooltip="Öffnen" aria-label="Öffnen"><i class="bi bi-eye"></i></a>
            <?php if ($typ === 'einsatzplan' && in_array($ext, ['docx','pdf','xlsx','xls'], true)): ?>
            <button type="button" class="btn btn-sm btn-outline-success btn-import-einsatz" data-id="<?= $id ?>" data-titel="<?= $titel ?>" data-tooltip="Einsätze importieren" aria-label="Einsätze importieren"><i class="bi bi-table"></i></button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-doc"
              data-id="<?= $id ?>"
              data-typ="<?= htmlspecialchars($typ, ENT_QUOTES) ?>"
              data-titel="<?= $titel ?>"
              data-beschreibung="<?= htmlspecialchars($doc['beschreibung'] ?? '', ENT_QUOTES) ?>"
              data-datum="<?= htmlspecialchars($doc['datum'] ?? '', ENT_QUOTES) ?>"
              data-sichtbar="<?= htmlspecialchars($vis, ENT_QUOTES) ?>"
              data-dateiname="<?= htmlspecialchars($doc['dateiname'], ENT_QUOTES) ?>"
              data-tooltip="Bearbeiten" aria-label="Bearbeiten"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-doc" data-id="<?= $id ?>" data-titel="<?= $titel ?>" data-tooltip="Löschen" aria-label="Löschen"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
        <?php
    }
    echo '</tbody></table></div>';
}

/** Sichtbarkeits-Optionen für Einsatzpläne/Protokolle (JSK ist immer „alle Mitglieder", Server erzwingt das). */
function dv_vis_options(bool $isAdminUser, string $current = 'alle_mitglieder'): void {
    $opts = [];
    if ($isAdminUser) $opts['admin'] = 'Nur Admin';
    $opts['vorstand']        = 'Nur Vorstand';
    $opts['alle_mitglieder'] = 'Alle Mitglieder';
    foreach ($opts as $val => $label) {
        echo '<option value="' . $val . '"' . ($val === $current ? ' selected' : '') . '>' . $label . '</option>';
    }
}

/** Upload-Formular eines Typs (identischer Aufbau für alle drei Tabs). */
function dv_upload_form(string $typ, string $titelHint, string $accept, bool $isAdminUser, string $csrf, bool $mitSichtbarkeit): void {
    $uid = 'dvUp' . ucfirst($typ);
    ?>
    <div class="dv-upload">
      <h6 class="mb-3"><i class="bi bi-cloud-upload me-2"></i>Neues Dokument hochladen</h6>
      <form class="js-upload-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="typ" value="<?= $typ ?>">
        <div class="row g-2">
          <div class="<?= $mitSichtbarkeit ? 'col-md-4' : 'col-md-5' ?>">
            <label class="visually-hidden" for="<?= $uid ?>Titel">Titel</label>
            <input type="text" class="form-control form-control-sm" id="<?= $uid ?>Titel" name="titel" placeholder="Titel (z.B. <?= $titelHint ?>) *" required>
          </div>
          <div class="<?= $mitSichtbarkeit ? 'col-md-2' : 'col-md-3' ?>">
            <label class="visually-hidden" for="<?= $uid ?>Datum">Datum</label>
            <input type="date" class="form-control form-control-sm" id="<?= $uid ?>Datum" name="datum" value="<?= date('Y-m-d') ?>" required>
          </div>
          <?php if ($mitSichtbarkeit): ?>
          <div class="col-md-3">
            <label class="visually-hidden" for="<?= $uid ?>Sichtbar">Sichtbarkeit</label>
            <select class="form-select form-select-sm" id="<?= $uid ?>Sichtbar" name="sichtbar_fuer"><?php dv_vis_options($isAdminUser); ?></select>
          </div>
          <?php else: ?>
          <input type="hidden" name="sichtbar_fuer" value="alle_mitglieder">
          <?php endif; ?>
          <div class="<?= $mitSichtbarkeit ? 'col-md-3' : 'col-md-4' ?>">
            <label class="visually-hidden" for="<?= $uid ?>Datei">Datei</label>
            <input type="file" class="form-control form-control-sm" id="<?= $uid ?>Datei" name="datei" accept="<?= $accept ?>" required>
          </div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-md-10">
            <label class="visually-hidden" for="<?= $uid ?>Beschreibung">Beschreibung</label>
            <input type="text" class="form-control form-control-sm" id="<?= $uid ?>Beschreibung" name="beschreibung" placeholder="Beschreibung (optional)">
          </div>
          <div class="col-md-2"><button type="submit" class="btn btn-outline-success btn-sm w-100"><i class="bi bi-upload me-1"></i>Hochladen</button></div>
        </div>
        <?php if ($typ === 'einsatzplan'): ?><div class="form-text">Word/Excel-Einsatzpläne werden automatisch „Nur Admin".</div><?php endif; ?>
      </form>
    </div>
    <?php
}

// Jahr-Filter rechts im Seitenkopf (auch mobil sichtbar)
ob_start(); ?>
<form method="get" class="d-flex align-items-center gap-2">
  <label class="text-muted small mb-0" for="dvYear">Jahr</label>
  <select name="year" id="dvYear" class="form-select form-select-sm export-year-select" onchange="this.form.submit()">
    <?php foreach ($years as $y): ?>
      <option value="<?= $y ?>" <?= $y === $selected_year ? 'selected' : '' ?>><?= $y ?></option>
    <?php endforeach; ?>
  </select>
</form>
<?php
$page_actions     = ob_get_clean();
$page_show_mobile = true;
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-default">

        <?php $page_title = 'Dokumente verwalten'; include 'partials/page_header.inc.php'; ?>

        <input type="hidden" id="csrfToken" value="<?= $csrf ?>">

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
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabEinsaetze" type="button"><i class="bi bi-people-fill me-1"></i>Einsätze <span class="badge bg-secondary ms-1"><?= count($einsaetze) ?></span></button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTausch" type="button"><i class="bi bi-arrow-left-right me-1"></i>Tausche <span class="badge bg-secondary ms-1"><?= count($tausch_log) ?></span></button>
          </li>
        </ul>

        <div class="tab-content">

          <!-- TAB: Einsatzpläne -->
          <div class="tab-pane fade show active" id="tabEins" role="tabpanel">
            <?php dv_upload_form('einsatzplan', 'Obligatorisch 2026', '.pdf,.docx,.xlsx,.xls,.jpg,.jpeg,.png', $isAdminUser, $csrf, true); ?>
            <?php dv_render_list($docsEins, 'einsatzplan'); ?>
          </div>

          <!-- TAB: Protokolle -->
          <div class="tab-pane fade" id="tabProt" role="tabpanel">
            <?php dv_upload_form('protokoll', 'GV-Protokoll 2025', '.pdf,.docx,.jpg,.jpeg,.png', $isAdminUser, $csrf, true); ?>
            <?php dv_render_list($docsProt, 'protokoll'); ?>
          </div>

          <!-- TAB: JSK-Dokumente -->
          <div class="tab-pane fade" id="tabJsk" role="tabpanel">
            <?php dv_upload_form('jsk', 'Standblatt Kurs 1', '.pdf,.docx,.jpg,.jpeg,.png', $isAdminUser, $csrf, false); ?>
            <?php dv_render_list($docsJsk, 'jsk'); ?>
          </div>

          <!-- TAB: Einsätze -->
          <div class="tab-pane fade" id="tabEinsaetze" role="tabpanel">
            <?php if (!empty($einsaetze_grouped)): ?>
            <div class="table-wrapper">
              <h5 class="table-title d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people-fill me-2"></i>Importierte Einsätze <?= $selected_year ?>
                  <span class="text-muted fw-normal ez-note">· <?= count($einsaetze) ?> Einträge, <?= count($einsaetze_grouped) ?> Anlässe</span>
                </span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="ezExpandAll" data-state="mixed">
                  <i class="bi bi-arrows-expand me-1"></i>Alle ausklappen
                </button>
              </h5>
              <div class="p-2 d-flex flex-column gap-2">
                <?php $groupIdx = 0; $today = strtotime('today');
                foreach ($einsaetze_grouped as $key => $entries):
                  $first = $entries[0]; $ts = strtotime($first['event_datum']); $groupIdx++;
                  $istKommend = $ts >= $today;
                ?>
                <div class="card">
                  <div class="card-header py-2 d-flex justify-content-between align-items-center ez-group-header<?= $istKommend ? '' : ' text-muted' ?>" data-bs-toggle="collapse" data-bs-target="#ezGroup<?= $groupIdx ?>">
                    <div>
                      <strong><?= htmlspecialchars($first['bezeichnung']) ?></strong>
                      <span class="ms-2"><?= $wochentage[(int) date('w', $ts)] ?> <?= date('d.m.Y', $ts) ?></span>
                      <?php if ($first['event_zeit']): ?><span class="ms-1"><?= htmlspecialchars($first['event_zeit']) ?></span><?php endif; ?>
                      <span class="badge bg-primary ms-2"><?= count($entries) ?></span>
                      <?php if (!$istKommend): ?><span class="ms-2 ez-note">· vergangen</span><?php endif; ?>
                    </div>
                    <div>
                      <?php if ($first['dokument_id']): ?>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-delete-all-ez" data-dokid="<?= (int)$first['dokument_id'] ?>" data-titel="<?= htmlspecialchars($first['bezeichnung'], ENT_QUOTES) ?>" data-tooltip="Alle Einträge dieses Imports löschen"><i class="bi bi-trash me-1"></i>Alle</button>
                      <?php endif; ?>
                      <i class="bi <?= $istKommend ? 'bi-chevron-down' : 'bi-chevron-right' ?> ez-chevron"></i>
                    </div>
                  </div>
                  <div class="collapse<?= $istKommend ? ' show' : '' ?>" id="ezGroup<?= $groupIdx ?>">
                    <div class="card-body p-0">
                      <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Funktion</th><th>Name (Dokument)</th><th>Mitglied (DB)</th><th style="width:80px;"></th></tr></thead>
                        <tbody>
                        <?php foreach ($entries as $z): ?>
                          <tr id="ez-row-<?= (int)$z['id'] ?>">
                            <td><?= htmlspecialchars($z['funktion']) ?></td>
                            <td><?= htmlspecialchars($z['mitglied_name']) ?></td>
                            <td>
                              <?php if ($z['mitglied_id']): ?>
                                <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($z['m_name'] . ' ' . $z['m_vorname']) ?></span>
                              <?php else: ?>
                                <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Nicht zugeordnet</span>
                              <?php endif; ?>
                            </td>
                            <td class="text-end">
                              <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 btn-edit-ez"
                                data-id="<?= (int)$z['id'] ?>" data-funktion="<?= htmlspecialchars($z['funktion'], ENT_QUOTES) ?>"
                                data-name="<?= htmlspecialchars($z['mitglied_name'], ENT_QUOTES) ?>" data-mid="<?= (int)($z['mitglied_id'] ?? 0) ?: '' ?>"
                                data-datum="<?= htmlspecialchars($z['event_datum'], ENT_QUOTES) ?>" data-zeit="<?= htmlspecialchars($z['event_zeit'] ?? '', ENT_QUOTES) ?>"
                                data-tooltip="Bearbeiten"><i class="bi bi-pencil"></i></button>
                              <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 btn-delete-ez" data-id="<?= (int)$z['id'] ?>" data-name="<?= htmlspecialchars($z['mitglied_name'], ENT_QUOTES) ?>" data-tooltip="Löschen"><i class="bi bi-trash"></i></button>
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
            </div>
            <?php else: ?>
            <div class="dv-empty"><i class="bi bi-inbox"></i>Keine importierten Einsätze für dieses Jahr gefunden</div>
            <?php endif; ?>
          </div>

          <!-- TAB: Tausche -->
          <div class="tab-pane fade" id="tabTausch" role="tabpanel">
            <?php if (!empty($tausch_log)): ?>
            <div class="table-wrapper">
              <h5 class="table-title"><i class="bi bi-arrow-left-right me-2"></i>Einsatz-Tausche &amp; Übernahmen <?= $selected_year ?></h5>
              <p class="text-muted small px-3 pt-2 mb-2">Von den Mitgliedern selbst abgewickelt. Bei Bedarf über die Einsatz-Bearbeitung korrigierbar.</p>
              <div class="table-responsive">
                <table class="table table-sm table-hover">
                  <thead><tr><th>Datum</th><th>Art</th><th>Von → An</th><th>Einsatz</th><th>Status</th></tr></thead>
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
            </div>
            <?php else: ?>
            <div class="dv-empty"><i class="bi bi-inbox"></i>Keine Tausche oder Übernahmen für dieses Jahr gefunden</div>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Edit-Modal (für alle Typen) -->
<div class="modal fade" id="dvEditModal" tabindex="-1" aria-labelledby="dvEditModalTitle">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="dvEditModalTitle"><i class="bi bi-pencil-square me-2"></i>Dokument bearbeiten</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <form id="dvEditForm" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="id" id="dvEditId">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold" for="dvEditTitel">Titel *</label>
            <input type="text" class="form-control form-control-sm" name="titel" id="dvEditTitel" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="dvEditDatum">Datum *</label>
            <input type="date" class="form-control form-control-sm" name="datum" id="dvEditDatum" required>
          </div>
          <div class="mb-3" id="dvEditSichtbarWrap">
            <label class="form-label fw-semibold" for="dvEditSichtbar">Sichtbarkeit</label>
            <select class="form-select form-select-sm" name="sichtbar_fuer" id="dvEditSichtbar"><?php dv_vis_options($isAdminUser); ?></select>
            <div class="form-text d-none" id="dvEditSichtbarJskHint">JSK-Dokumente sind immer für alle Mitglieder sichtbar.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="dvEditBeschreibung">Beschreibung</label>
            <input type="text" class="form-control form-control-sm" name="beschreibung" id="dvEditBeschreibung" placeholder="Optional">
          </div>
          <div class="mb-0">
            <label class="form-label fw-semibold" for="dvEditDatei">Datei ersetzen</label>
            <div class="text-muted small mb-1">Aktuelle Datei: <span id="dvEditCurrentFile" class="fw-medium text-dark"></span></div>
            <input type="file" class="form-control form-control-sm" name="datei" id="dvEditDatei" accept=".pdf,.docx,.xlsx,.xls,.jpg,.jpeg,.png">
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
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalTitle">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="importModalTitle"><i class="bi bi-table me-2"></i>Einsätze importieren</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3" id="importDocTitle"></p>
        <div id="importLoading" class="text-center py-4">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="text-muted mt-2">Dokument wird analysiert...</p>
        </div>
        <div id="importError" class="alert alert-danger d-none"></div>
        <?php if ($debugMode): ?>
        <div id="importDebugArea" class="d-none mt-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="importDebugBtn"><i class="bi bi-bug me-1"></i>Debug-Infos laden</button>
          <pre id="importDebugOutput" class="d-none mt-2 p-2 bg-light border rounded import-debug"></pre>
        </div>
        <?php endif; ?>
        <div id="importResult" class="d-none">
          <div class="import-stats d-flex flex-wrap gap-3 p-2 bg-light rounded mb-3 small" id="importStats"></div>
          <div id="importPreview" class="import-preview"></div>
        </div>
      </div>
      <div class="modal-footer d-none" id="importFooter">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-outline-success btn-sm" id="importSaveBtn"><i class="bi bi-check-lg me-1"></i>Importieren</button>
      </div>
    </div>
  </div>
</div>

<!-- Einsatz bearbeiten -->
<div class="modal fade" id="ezEditModal" tabindex="-1" aria-labelledby="ezEditModalTitle">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ezEditModalTitle"><i class="bi bi-pencil-square me-2"></i>Einsatz bearbeiten</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
      </div>
      <form id="ezEditForm">
        <div class="modal-body">
          <input type="hidden" name="id" id="ezEditId">
          <div class="mb-3"><label class="form-label fw-semibold" for="ezEditFunktion">Funktion</label><input type="text" class="form-control form-control-sm" name="funktion" id="ezEditFunktion"></div>
          <div class="mb-3"><label class="form-label fw-semibold" for="ezEditName">Name (Dokument)</label><input type="text" class="form-control form-control-sm" name="mitglied_name" id="ezEditName" required></div>
          <div class="mb-3"><label class="form-label fw-semibold" for="ezEditMitglied">Mitglied zuordnen</label><select class="form-select form-select-sm" name="mitglied_id" id="ezEditMitglied"><option value="">– Nicht zugeordnet –</option></select></div>
          <div class="row g-2">
            <div class="col-7"><label class="form-label fw-semibold" for="ezEditDatum">Datum</label><input type="date" class="form-control form-control-sm" name="event_datum" id="ezEditDatum"></div>
            <div class="col-5"><label class="form-label fw-semibold" for="ezEditZeit">Zeit</label><input type="text" class="form-control form-control-sm" name="event_zeit" id="ezEditZeit" placeholder="18:00 – 20:00"></div>
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

  function escHtml(str) { if (str == null || str === '') return ''; return $('<span>').text(String(str)).html(); }
  function ajaxMsg(xhr, fallback) { return msvXhrMessage(xhr, fallback); } // zentral in msv-toast.js

  // Aktiven Tab merken, damit ein Reload (nach Upload/Import) nicht auf "Einsatzpläne" zurückfällt
  function activeTabHash() {
    var active = document.querySelector('.nav-tabs .nav-link.active');
    return active ? active.getAttribute('data-bs-target') : '';
  }
  function reloadKeepTab(delay) {
    var hash = activeTabHash();
    setTimeout(function () {
      if (hash) { location.hash = hash; }
      location.reload();
    }, delay || 600);
  }

  // Direkt zu einem Tab springen (z.B. aus der JSK-Verwaltung: dokumente_verwaltung.php#tabJsk oder nach Reload)
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
      processData: false, contentType: false, dataType: 'json'
    }).done(function (r) {
      if (r && r.success) { msvToast(r.message, 'success'); reloadKeepTab(700); }
      else { msvToast((r && r.message) || 'Upload fehlgeschlagen', 'error'); }
    }).fail(function (xhr) {
      msvToast(ajaxMsg(xhr, xhr.status === 413 ? 'Datei zu gross für den Server' : 'Upload fehlgeschlagen'), 'error');
    }).always(function () { $btn.prop('disabled', false).html(orig); });
  });

  // Bearbeiten öffnen
  $(document).on('click', '.btn-edit-doc', function () {
    var $b = $(this);
    var istJsk = $b.data('typ') === 'jsk';
    $('#dvEditId').val($b.data('id'));
    $('#dvEditTitel').val($b.data('titel'));
    $('#dvEditBeschreibung').val($b.data('beschreibung'));
    $('#dvEditDatum').val($b.data('datum'));
    $('#dvEditSichtbar').val(istJsk ? 'alle_mitglieder' : $b.data('sichtbar')).prop('disabled', istJsk);
    $('#dvEditSichtbarJskHint').toggleClass('d-none', !istJsk);
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
      processData: false, contentType: false, dataType: 'json'
    }).done(function (r) {
      if (r && r.success) { msvToast(r.message, 'success'); editModal.hide(); reloadKeepTab(600); }
      else { msvToast((r && r.message) || 'Fehler beim Speichern', 'error'); }
    }).fail(function (xhr) { msvToast(ajaxMsg(xhr, 'Fehler beim Speichern'), 'error'); })
      .always(function () { $btn.prop('disabled', false).html(orig); });
  });

  // Löschen (delegiert, Titel aus data-Attribut statt Inline-onclick)
  $(document).on('click', '.btn-delete-doc', function () {
    var id = $(this).data('id'), title = $(this).data('titel');
    msvConfirmDelete('Dokument „' + title + '"').then(function (res) {
      if (!res.isConfirmed) return;
      $.post('../api/dokument_delete.php', { id: id, csrf_token: CSRF }, null, 'json')
        .done(function (r) {
          if (r && r.success) { msvToast(r.message, 'success'); $('#dvdoc-' + id).fadeOut(); }
          else { msvToast((r && r.message) || 'Fehler beim Löschen', 'error'); }
        })
        .fail(function (xhr) { msvToast(ajaxMsg(xhr, 'Fehler beim Löschen'), 'error'); });
    });
  });

  // ===== Einsatz-Import (aus Einsatzplan-Dokument) =====
  var importData = null, importDocId = null;
  var importModal = new bootstrap.Modal(document.getElementById('importModal'));
  var ezEditModal = new bootstrap.Modal(document.getElementById('ezEditModal'));

  $(document).on('click', '.btn-import-einsatz', function () {
    importDocId = $(this).data('id');
    $('#importDocTitle').text($(this).data('titel'));
    $('#importLoading').show();
    $('#importError').addClass('d-none');
    $('#importResult').addClass('d-none');
    $('#importFooter').addClass('d-none');
    $('#importDebugArea').addClass('d-none');
    importData = null;
    importModal.show();
    $.post('../api/einsatzplan_import.php', { action: 'parse', dokument_id: importDocId, csrf_token: CSRF }, null, 'json')
      .done(function (resp) {
        $('#importLoading').hide();
        if (resp && resp.success) {
          importData = resp.data;
          renderImportPreview(resp);
          $('#importResult').removeClass('d-none');
          $('#importFooter').removeClass('d-none');
        } else if (resp && resp.csrf_expired) {
          importModal.hide(); msvToast('Sitzung abgelaufen – Seite wird neu geladen…', 'warning');
          reloadKeepTab(1800);
        } else {
          $('#importError').removeClass('d-none').text((resp && resp.message) || 'Dokument konnte nicht gelesen werden');
          $('#importDebugArea').removeClass('d-none');
        }
      })
      .fail(function (xhr) {
        $('#importLoading').hide();
        $('#importError').removeClass('d-none').text(ajaxMsg(xhr, 'Fehler beim Parsen des Dokuments'));
        $('#importDebugArea').removeClass('d-none');
      });
  });

  function renderImportPreview(resp) {
    var s = resp.stats || {};
    $('#importStats').html(
      '<div><i class="bi bi-list-check"></i> <strong>' + (s.total || 0) + '</strong> Einsätze</div>' +
      '<div class="text-success"><i class="bi bi-check-circle-fill"></i> <strong>' + (s.matched || 0) + '</strong> zugeordnet</div>' +
      '<div class="text-danger"><i class="bi bi-question-circle-fill"></i> <strong>' + (s.unmatched || 0) + '</strong> nicht gefunden</div>'
    );
    var html = '<table class="table table-sm table-hover mb-0"><thead><tr>' +
      '<th>Datum</th><th>Zeit</th><th>Funktion</th><th>Name (Dokument)</th><th>Mitglied (DB)</th><th>Status</th></tr></thead><tbody>';
    (resp.data || []).forEach(function (z) {
      var icon, cls;
      if (z.match_status === 'exact') { icon = '<i class="bi bi-check-circle-fill text-success"></i>'; cls = ''; }
      else if (z.match_status === 'fuzzy') { icon = '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; cls = 'table-warning'; }
      else { icon = '<i class="bi bi-x-circle-fill text-danger"></i>'; cls = 'table-danger'; }
      var df = z.event_datum;
      try { var d = new Date(z.event_datum); if (!isNaN(d)) df = d.toLocaleDateString('de-CH'); } catch (e) {}
      // Werte stammen aus dem geparsten Dokument -> escapen
      html += '<tr class="' + cls + '"><td>' + escHtml(df) + '</td><td>' + escHtml(z.event_zeit) + '</td><td>' + escHtml(z.funktion) +
        '</td><td>' + escHtml(z.mitglied_name) + '</td><td>' + escHtml(z.matched_name || '–') + '</td><td class="text-center">' + icon + '</td></tr>';
    });
    $('#importPreview').html(html + '</tbody></table>');
  }

  $('#importSaveBtn').on('click', function () {
    if (!importData || !importDocId) return;
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>...');
    $.post('../api/einsatzplan_import.php', { action: 'save', dokument_id: importDocId, zuweisungen: JSON.stringify(importData), csrf_token: CSRF }, null, 'json')
      .done(function (resp) {
        if (resp && resp.success) { msvToast(resp.message, 'success'); importModal.hide(); location.hash = '#tabEinsaetze'; setTimeout(function () { location.reload(); }, 800); }
        else if (resp && resp.csrf_expired) { msvToast('Sitzung abgelaufen – Seite wird neu geladen…', 'warning'); reloadKeepTab(1800); }
        else { msvToast((resp && resp.message) || 'Fehler beim Speichern', 'error'); }
      })
      .fail(function (xhr) { msvToast(ajaxMsg(xhr, 'Fehler beim Speichern'), 'error'); })
      .always(function () { $btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Importieren'); });
  });

  $('#importDebugBtn').on('click', function () {
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Lade...');
    $.post('../api/einsatzplan_import.php', { action: 'parse', dokument_id: importDocId, debug: 1, csrf_token: CSRF }, null, 'json')
      .done(function (resp) { $('#importDebugOutput').text(JSON.stringify(resp.debug || resp, null, 2)).removeClass('d-none'); })
      .fail(function (xhr) { $('#importDebugOutput').text('Fehler: ' + (xhr.responseText || xhr.status)).removeClass('d-none'); })
      .always(function () { $btn.prop('disabled', false).html('<i class="bi bi-bug me-1"></i>Debug-Infos laden'); });
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
      $.post('../api/einsatzplan_import.php', { action: 'members', csrf_token: CSRF }, null, 'json')
        .done(function (resp) {
          if (resp && resp.success) {
            var $sel = $('#ezEditMitglied');
            $sel.find('option:not(:first)').remove();
            resp.data.forEach(function (m) { $sel.append('<option value="' + parseInt(m.id, 10) + '">' + escHtml(m.name + ' ' + m.vorname) + '</option>'); });
            ezMembersLoaded = true;
            $('#ezEditMitglied').val($b.data('mid') || '');
          }
        })
        .fail(function (xhr) { msvToast(ajaxMsg(xhr, 'Mitgliederliste konnte nicht geladen werden'), 'error'); });
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
    }, null, 'json')
      .done(function (resp) {
        if (resp && resp.success) { msvToast(resp.message, 'success'); ezEditModal.hide(); reloadKeepTab(600); }
        else { msvToast((resp && resp.message) || 'Fehler beim Speichern', 'error'); }
      })
      .fail(function (xhr) { msvToast(ajaxMsg(xhr, 'Fehler beim Speichern'), 'error'); })
      .always(function () { $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Speichern'); });
  });

  $(document).on('click', '.btn-delete-ez', function () {
    var id = $(this).data('id'), name = $(this).data('name');
    msvConfirmDelete('Einsatz „' + name + '"').then(function (res) {
      if (!res.isConfirmed) return;
      $.post('../api/einsatzplan_import.php', { action: 'delete', id: id, csrf_token: CSRF }, null, 'json')
        .done(function (resp) {
          if (resp && resp.success) { msvToast(resp.message, 'success'); $('#ez-row-' + id).fadeOut(); }
          else { msvToast((resp && resp.message) || 'Fehler beim Löschen', 'error'); }
        })
        .fail(function (xhr) { msvToast(ajaxMsg(xhr, 'Fehler beim Löschen'), 'error'); });
    });
  });

  $(document).on('click', '.btn-delete-all-ez', function (e) {
    e.stopPropagation(); // Header-Klick würde sonst die Gruppe auf-/zuklappen
    var dokId = $(this).data('dokid'), titel = $(this).data('titel');
    msvConfirm('Alle importierten Einträge für „' + titel + '" löschen?', 'Alle löschen').then(function (res) {
      if (!res.isConfirmed) return;
      $.post('../api/einsatzplan_import.php', { action: 'delete_all', dokument_id: dokId, csrf_token: CSRF }, null, 'json')
        .done(function (resp) {
          if (resp && resp.success) { msvToast(resp.message, 'success'); reloadKeepTab(600); }
          else { msvToast((resp && resp.message) || 'Fehler beim Löschen', 'error'); }
        })
        .fail(function (xhr) { msvToast(ajaxMsg(xhr, 'Fehler beim Löschen'), 'error'); });
    });
  });

  // ===== Einsätze-Tab: Alle ausklappen / einklappen + Chevron-Rotation =====
  $('#ezExpandAll').on('click', function () {
    var open = $(this).attr('data-state') !== 'open';
    $('#tabEinsaetze .collapse').collapse(open ? 'show' : 'hide');
    $('#tabEinsaetze .ez-chevron').toggleClass('bi-chevron-down', open).toggleClass('bi-chevron-right', !open);
    $(this).attr('data-state', open ? 'open' : 'closed')
           .html('<i class="bi ' + (open ? 'bi-arrows-collapse' : 'bi-arrows-expand') + ' me-1"></i>' + (open ? 'Alle einklappen' : 'Alle ausklappen'));
  });
  $('#tabEinsaetze').on('shown.bs.collapse hidden.bs.collapse', '.collapse', function (e) {
    var down = e.type === 'shown';
    $(this).closest('.card').find('.ez-chevron').toggleClass('bi-chevron-down', down).toggleClass('bi-chevron-right', !down);
  });
})();
</script>

<?php include 'footer.inc.php'; ?>
