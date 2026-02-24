<?php
// internestichdef.php - Frontend für Stichnummer Definition
include 'dbconnect.inc.php';

// Session-Kontrolle
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Lade Mitglieder für Dropdown
// Versuche Lizenznummer zu laden falls vorhanden
$sql = "SELECT * FROM mitglieder WHERE Verstorben = 0 ORDER BY Name, Vorname";
$mitglieder_result = connect_db($sql);

$page_specific_css = '
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
                <div class="row mb-4 d-none d-md-flex">
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
                    <div class="mb-3">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button id="btnSave" class="btn btn-outline-success btn-compact-standard" disabled>
                                <i class="bi bi-save"></i><span>Speichern</span>
                            </button>
                        </div>
                    </div>

                    <!-- Tabelle -->
                    <div class="table-wrapper">
                        <h3 class="table-title">Interne Stiche â€“ Stichnummern</h3>
                        <div class="desktop-table-container">
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
                                                    placeholder="â€”">
                                            </td>
                                            <td class="text-center">
                                                <input type="text" class="form-control form-control-sm nr2-input"
                                                    placeholder="â€”">
                                            </td>
                                            <td class="text-center">
                                                <input type="text" class="form-control form-control-sm nr3-input"
                                                    placeholder="â€”">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mobile-cards-container" id="mobileInternestichedefCards">
                            <div class="mobile-search">
                                <div class="position-relative">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" class="form-control" placeholder="Suchen..." oninput="filterMobileInternestichedef(this)">
                                </div>
                            </div>
                            <div class="mobile-cards-scroll"></div>
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

<!-- CSRF Token für JavaScript -->
<script>
const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
</script>

<!-- JavaScript für Interne Stiche -->
<script src="internestichedef/stiche.js?v=<?= time(); ?>"></script>
<script>
$(function(){
    window.InterneStiche.init();
    buildMobileInternestichedefCards();
});

function buildMobileInternestichedefCards() {
    const isMobile = window.matchMedia('(max-width: 767.98px)');
    if (!isMobile.matches) return;
    const table = document.querySelector('#stichdefTabelle');
    if (!table) return;
    const container = document.querySelector('#mobileInternestichedefCards .mobile-cards-scroll');
    if (!container) return;
    container.innerHTML = '';
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length < 4) return;
        const stich = cells[0].textContent.trim();
        const nr1Input = cells[1].querySelector('input');
        const nr2Input = cells[2].querySelector('input');
        const nr3Input = cells[3].querySelector('input');
        const card = document.createElement('div');
        card.className = 'mobile-card';
        card.innerHTML = `
            <div class="mobile-card-header"><div class="mobile-card-title">${stich}</div></div>
            <div class="mobile-card-body">
                <div class="mb-3"><label class="form-label fw-bold small">Stichnr. 1:</label>
                <input type="text" class="form-control" data-row="${row.dataset.stich}" data-col="nr1" value="${nr1Input.value}" placeholder="—"></div>
                <div class="mb-3"><label class="form-label fw-bold small">Stichnr. 2:</label>
                <input type="text" class="form-control" data-row="${row.dataset.stich}" data-col="nr2" value="${nr2Input.value}" placeholder="—"></div>
                <div class="mb-3"><label class="form-label fw-bold small">Stichnr. 3:</label>
                <input type="text" class="form-control" data-row="${row.dataset.stich}" data-col="nr3" value="${nr3Input.value}" placeholder="—"></div>
            </div>`;
        container.appendChild(card);
    });
    container.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', function() {
            const stichRow = table.querySelector(`tr[data-stich="${this.dataset.row}"]`);
            if (stichRow) {
                const desktopInput = stichRow.querySelector(`.${this.dataset.col}-input`);
                if (desktopInput) desktopInput.value = this.value;
            }
        });
    });
}
window.filterMobileInternestichedef = function(searchInput) {
    const searchTerm = searchInput.value.toLowerCase();
    document.querySelectorAll('#mobileInternestichedefCards .mobile-card').forEach(card => {
        card.style.display = card.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
    });
};
</script>

<style>
@media (max-width: 767.98px) {
    /* WCAG AAA Touch Targets: Alle Form-Elemente */
    .form-control,
    .form-select,
    input[type="text"],
    input[type="number"] {
        min-height: 48px !important;
        font-size: 16px !important; /* Verhindert iOS Auto-Zoom */
    }

    /* Alle Buttons */
    .btn {
        min-height: 48px !important;
        font-size: 16px !important;
        padding: 0.5rem 1rem !important;
    }

    /* Desktop Table/Mobile Cards Toggle */
    .desktop-table-container { display: none !important; }
    .mobile-cards-container { display: block !important; }
}
@media (min-width: 768px) {
    .mobile-cards-container { display: none !important; }
}
</style>

<?php
include 'footer.inc.php';
?>