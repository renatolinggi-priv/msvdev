<?php
// cuprang.php – Vereinscup Ranglisten (angepasst an heimrang.php Layout)

require_once 'dbconnect.inc.php';
require_once 'cuprang/cup_repository.php';
require_once 'cuprang/cup_table_renderer.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// WICHTIG: header.inc.php verpackt $page_specific_css bereits in <style>…</style>,
// daher hier NUR rohes CSS (kein eigenes <style>-Tag → sonst verschachtelt & wirkungslos).
$page_specific_css = '
/* Inhaltsbreite begrenzen, damit Paarungen/Ranglisten nicht unnötig in die Breite gezogen werden */
.main-content-wrapper { max-width: 980px; }
/* Karten an ihren Inhalt anpassen (kein erzwungener Leerraum durch flex:1 1 auto / min-height) */
.content-background .table-wrapper { flex: 0 0 auto !important; }

/* Mobile Optimierung für Cuprang */
@media (max-width: 767.98px) {
    .main-content-wrapper { max-width: none; }
    .form-select { min-height: 48px !important; font-size: 16px !important; }
    .btn { min-height: 48px !important; font-size: 16px !important; }
    .table-title { font-size: 1.1rem !important; }
    .container-fluid { padding: 0.5rem !important; }
}
';
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

include 'header.inc.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xxl-8 col-xl-9 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-trophy me-2"></i>
                            MSV Wilen Vereinscup – Übersicht
                        </h2>
                    </div>
                </div>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <!-- Jahr-Auswahl -->
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                            <i class="bi bi-calendar3 me-1"></i>Jahr:
                        </label>
                        <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
                    </div>
                    <!-- Export-Toolbar (einheitlich mit endschrang.php) -->
                    <div class="export-toolbar mb-4">
                        <div class="export-toolbar-head">
                            <i class="bi bi-file-earmark-arrow-down"></i>
                            <span>Dokumente erstellen</span>
                            <button id="redirect-btn" type="button" class="btn btn-outline-primary btn-sm ms-auto">
                                <i class="bi bi-pencil-square me-1"></i>Resultate bearbeiten
                            </button>
                        </div>
                        <div class="export-group-btns">
                            <button id="btnCupPdf" type="button" class="btn btn-outline-info btn-sm pdf-btn">
                                <i class="bi bi-file-pdf me-1"></i><span>Rangliste</span>
                            </button>
                        </div>
                        <div id="pdf-link" class="mt-2"></div>
                    </div>

                    <?php
                    $conn = get_db_connection();
                    if (!$conn) {
                        echo '<div class="alert alert-danger mt-4">Datenbankverbindung fehlgeschlagen.</div>';
                    } else {
                        $pairs = cup_fetch_pairs($conn, $selectedYear);
                        $final = cup_fetch_final_results($conn, $selectedYear);
                        $stand = cup_fetch_standcup_final($conn, $selectedYear);

                        // Runden bestimmen
                        $rounds = array_values(array_unique(array_map(fn($r) => (int)$r['Round'], $pairs)));
                        sort($rounds, SORT_ASC);
                        ?>

                        <!-- Paarungen -->
                        <div class="table-wrapper">
                            <h5 class="table-title">
                                <i class="bi bi-diagram-2 me-2"></i>
                                Paarungen <?= (int)$selectedYear ?>
                            </h5>
                            <?php
                            if (empty($pairs)) {
                                echo '<div class="text-muted p-3">Keine Paarungen vorhanden.</div>';
                            } else {
                                echo '<div class="cup-rounds">';
                                foreach ($rounds as $rnd) {
                                    echo '<div class="cup-round">';
                                    echo '<h6 class="fw-bold text-secondary mb-2"><i class="bi bi-diagram-3 me-1"></i>Runde ' . (int)$rnd . '</h6>';
                                    echo cup_render_round_table($conn, $pairs, $rnd);
                                    echo '</div>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>

                        <!-- Finale Rangliste + Standcup Final nebeneinander -->
                        <div class="cup-final-row">
                        <div class="table-wrapper">
                            <h5 class="table-title">
                                <i class="bi bi-trophy-fill me-2"></i>
                                Finale Rangliste <?= (int)$selectedYear ?>
                            </h5>
                            <?php
                            if (empty($final)) {
                                echo '<div class="text-muted p-3">Noch keine Finalresultate vorhanden.</div>';
                            } else {
                                echo cup_render_final_ranking_table($conn, $final);
                            }
                            ?>
                        </div>

                        <!-- Standcup Final -->
                        <div class="table-wrapper">
                            <h5 class="table-title">
                                <i class="bi bi-award me-2"></i>
                                Standcup Final <?= (int)$selectedYear ?>
                            </h5>
                            <?php
                            if (empty($stand)) {
                                echo '<div class="text-muted p-3">Noch keine Standcup-Finaldaten vorhanden.</div>';
                            } else {
                                echo cup_render_standcup_table($conn, $stand);
                            }
                            ?>
                        </div>
                        </div><!-- /cup-final-row -->

                        <?php
                        $conn->close();
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bearbeiten-Button → cup.php mit Jahresauswahl
    document.getElementById('redirect-btn').addEventListener('click', function() {
        const year = document.getElementById('yearSelect').value;
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Lade...';
        setTimeout(() => {
            window.location.href = 'cup.php?year=' + encodeURIComponent(year);
        }, 300);
    });

    // PDF Export Handler
    document.getElementById('btnCupPdf').addEventListener('click', async function(){
        const year = document.getElementById('yearSelect').value;
        const btn = this;
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Erzeuge PDF...';
        
        try {
            // 1) JSON vom Generator holen
            const res = await fetch('cuprang/generate_cup_pdf.php?year=' + encodeURIComponent(year), {
                headers: { 'Accept': 'application/json' }
            });
            const raw = await res.text();
            let data;
            try { 
                data = JSON.parse(raw); 
            } catch (e) { 
                console.error('Server response (not JSON):', raw); 
                throw new Error('Ungültige Antwort vom Server (kein JSON).'); 
            }

            if (!(data && data.success && data.pdf_link)) {
                throw new Error(data && data.error ? data.error : 'PDF konnte nicht erstellt werden.');
            }

            // 2) PDF als Blob laden
            const pdfRes = await fetch(data.pdf_link, { credentials: 'same-origin' });
            if (!pdfRes.ok) throw new Error('PDF konnte nicht geladen werden.');
            const blob = await pdfRes.blob();

            // 3) Dateiname mit Jahr + Zeitstempel bauen
            function pad(n){ return n.toString().padStart(2,'0'); }
            const now = new Date();
            const ts = now.getFullYear() + '-' +
                       pad(now.getMonth()+1) + '-' +
                       pad(now.getDate()) + '_' +
                       pad(now.getHours()) + '-' +
                       pad(now.getMinutes()) + '-' +
                       pad(now.getSeconds());
            const filename = `cup_${year}_${ts}.pdf`;

            // 4) Download erzwingen
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (e) {
            msvError('Fehler beim PDF-Export: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    });

    // Year-Dropdown füllen und Event-Handler
    const yearSelect = document.getElementById('yearSelect');
    const current = new Date().getFullYear();
    const selected = <?= (int)$selectedYear ?>;

    for (let y = current; y >= current - 3; y--) {
        const opt = document.createElement('option');
        opt.value = y; 
        opt.textContent = y;
        if (y === selected) opt.selected = true;
        yearSelect.appendChild(opt);
    }
    
    // Bei Jahr-Änderung Seite neu laden
    yearSelect.addEventListener('change', function() {
        window.location.href = '?year=' + this.value;
    });
});
</script>

<?php include 'footer.inc.php'; ?>