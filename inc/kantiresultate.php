<?php
// kantiresultate.php - Vereinheitlichte Version
include 'dbconnect.inc.php';
include 'header.inc.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<link rel="stylesheet" href="../css/fixes/no-page-scroll-override.css">
<div class="container-fluid">
  <div class="row">
    <div class="col-xl-8 col-lg-11 col-12 ps-0">
      <div class="main-content-wrapper">
        <!-- Seitenüberschrift -->
        <div class="row mb-4">
          <div class="col-md-12">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
              <i class="bi bi-target me-2"></i>Kantonalstich Resultaterfassung
            </h2>
          </div>
        </div>

        <div class="content-background">
          <form id="kantiresultateForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- Jahr-Auswahl (einheitlich) -->
            <div class="year-selection-card mb-3">
              <div class="row align-items-center">
                <div class="col-md-5">
                  <label for="yearSelect" class="form-label fw-bold">
                    <i class="bi bi-calendar3 me-1"></i> Jahr auswählen:
                  </label>
                  <select id="yearSelect" class="form-select"></select>
                </div>
              </div>
            </div>

            <!-- Toolbar (einheitlich) -->
            <div class="button-toolbar mb-3">
              <div class="button-group d-flex gap-2 flex-wrap">
                <button id="redirect-btn" type="button" class="btn btn-compact-standard btn-outline-success">
                  <i class="bi bi-trophy me-2"></i>Rangliste
                </button>
                <button type="submit" class="btn btn-compact-standard btn-outline-primary">
                  <i class="bi bi-save me-2"></i>Ergebnisse speichern
                </button>
                <button id="delete-btn" type="button" class="btn btn-compact-standard btn-outline-danger">
                  <i class="bi bi-trash me-2"></i>Aktuelle Resultate löschen
                </button>
              </div>
            </div>

            <div id="message" class="mb-2"></div>

            <!-- Tabelle (einheitlich) -->
            <div class="table-wrapper">
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="kantiresultateTabelle" style="width:auto;">
                  <thead>
                    <tr>
                      <th scope="col" style="min-width:200px;">
                        <i class="bi bi-person me-1"></i>Mitglied
                      </th>
                      <th class="text-center">Passe 1</th>
                      <th class="text-center">Passe 2</th>
                      <th class="text-center">Passe 3</th>
                      <th class="text-center">Passe 4</th>
                      <th class="text-center">Passe 5</th>
                    </tr>
                  </thead>
                  <tbody><!-- dynamisch --></tbody>
                </table>
              </div>
            </div>
          </form>
        </div><!-- /content-background -->
      </div><!-- /main-content-wrapper -->
    </div>
  </div>
</div>

<!-- Einheitliches Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmModalLabel">
          <i class="bi bi-exclamation-triangle text-warning me-2"></i>Bestätigung erforderlich
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        Sind Sie sicher, dass Sie diese Aktion durchführen möchten?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle me-1"></i>Abbrechen
        </button>
        <button type="button" class="btn btn-outline-danger" id="confirmAction">
          <i class="bi bi-check-circle me-1"></i>Bestätigen
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Gemeinsame Bibliothek einbinden -->
<script src="js/msv-resultate-common.js"></script>

<script>
// Initialisierung mit der gemeinsamen Bibliothek
$(document).ready(function() {
    // Initialisiere den Manager für Kanti-Resultate
    const kantiManager = MSV.init('kanti');
    
    // Global Scroll aktivieren
    MSV.enableGlobalScroll();
});
</script>

<?php include 'footer.inc.php'; ?>
