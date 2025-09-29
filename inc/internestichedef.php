<?php
// internestichdef.php - Frontend für Stichnummer Definition
include 'dbconnect.inc.php';

// Session-Kontrolle
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Lade Mitglieder für Dropdown
// Versuche Lizenznummer zu laden falls vorhanden
$sql = "SELECT * FROM mitglieder ORDER BY Name, Vorname";
$mitglieder_result = connect_db($sql);

$page_specific_css = '
#toast-container{
  position: fixed;
  top: calc(var(--toast-top, 76px) + 8px) !important;
  right: 16px !important;
  left: auto !important;
  z-index: 9999 !important;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  pointer-events: none;
}
#toast-container .toast-message{ pointer-events: auto; }

/* Erste Spalte linksbündig */
#stichdefTabelle td:first-child,
#stichdefTabelle th:first-child {
  text-align: left !important;
}
';

include 'header.inc.php';
?>


<!-- Result Import CSV Hauptbereich -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-6 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-file-earmark-arrow-up me-2"></i>
                            Interne Stiche - Imetron Stichnummerverwaltung
                        </h2>
                    </div>
                </div>

                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <?php
                    $stiche = ['Heimmeisterschaft', 'Kantonalstich', 'Endstich', 'Schwini', 'Kunst', 'Glück', 'Sie und Er', 'Zabig'];
                    ?>

                    <!-- Info -->
                    <div class="info-card">
                        <i class="bi bi-info-circle me-2"></i>
                        Verwalte hier die internen Imetron-Stichnummern (bis zu 3 je Stich).
                        Diese sind für den CSV Import der Resultate wichtig!
                    </div>

                    <!-- Toolbar -->
                    <div class="year-selection-card">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button id="btnSave" class="btn btn-outline-success btn-compact-standard" disabled>
                                <i class="bi bi-save"></i><span>Speichern</span>
                            </button>
                        </div>
                    </div>

                    <!-- Tabelle -->
                    <div class="table-wrapper">
                        <h3 class="table-title">Interne Stiche – Stichnummern</h3>
                        <div class="table-responsive">
                            <table id="stichdefTabelle" class="table table-striped table-bordered align-middle">
                                <thead>
                                    <tr>
                                        <th style="min-width:120px">Stich</th>
                                        <th class="text-center" style="min-width:160px">Stichnr. 1</th>
                                        <th class="text-center" style="min-width:160px">Stichnr. 2</th>
                                        <th class="text-center" style="min-width:160px">Stichnr. 3</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stiche as $s): ?>
                                        <tr data-stich="<?= htmlspecialchars($s) ?>">
                                            <td><strong><?= htmlspecialchars($s) ?></strong></td>
                                            <td class="text-center">
                                                <input type="text" class="form-control form-control-sm nr1-input"
                                                    placeholder="—">
                                            </td>
                                            <td class="text-center">
                                                <input type="text" class="form-control form-control-sm nr2-input"
                                                    placeholder="—">
                                            </td>
                                            <td class="text-center">
                                                <input type="text" class="form-control form-control-sm nr3-input"
                                                    placeholder="—">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>


                </div>
            </div>
        </div>

    </div>
</div>

<!-- Confirm Import Modal -->
<div class="modal fade" id="confirmImportModal" tabindex="-1" aria-labelledby="confirmImportModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmImportModalLabel">
                    <i class="bi bi-question-circle me-2"></i>
                    Import bestätigen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Möchtest du die ausgewählten Programme wirklich importieren?</p>
                <div id="confirmImportDetails" class="mt-3">
                    <!-- Wird dynamisch gefüllt mit Import-Details -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>
                    Abbrechen
                </button>
                <button type="button" class="btn btn-success" id="confirmImportBtn">
                    <i class="bi bi-check-circle me-2"></i>
                    Ja, importieren
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>
<!-- CSRF Token für JavaScript -->
<script>
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
</script>


<!-- JavaScript für Interne Stiche -->
<script src="internestichedef/stiche.js?v=<?= time(); ?>"></script>
<script>
$(function(){
    window.InterneStiche.init();
});
</script>

<?php
include 'footer.inc.php';
?>