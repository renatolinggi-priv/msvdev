<?php
// cuprang.php – Vereinscup Ranglisten (angepasst an heimrang.php Layout)

require_once 'dbconnect.inc.php';
require_once 'cuprang/cup_repository.php';
require_once 'cuprang/cup_table_renderer.php';


// WICHTIG: header.inc.php verpackt $page_specific_css bereits in <style>…</style>,
// daher hier NUR rohes CSS (kein eigenes <style>-Tag → sonst verschachtelt & wirkungslos).
$page_specific_css = '
/* Karten an ihren Inhalt anpassen (kein erzwungener Leerraum durch flex:1 1 auto / min-height) */
.content-background .table-wrapper { flex: 0 0 auto !important; }

/* Mobile Optimierung für Cuprang */
@media (max-width: 767.98px) {
    .main-content-wrapper { max-width: none; }
    .table-title { font-size: 0.95rem !important; }
    .container-fluid { padding: 0.5rem !important; }
}
';
require_once __DIR__ . '/jahr.inc.php';
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : msvJahrStandard();

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <!-- Äusserer weisser Container -->
            <div class="main-content-wrapper content-width-default">
                <!-- Header ausserhalb des inneren Containers -->
                <?php
                $page_title = "Vereinscup Rangliste";
                $page_title_after = '<button type="button" class="btn-help" data-help="cuprang.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_show_mobile = true;
                ob_start(); ?>
<div class="btn-group btn-group-sm" role="group" aria-label="Rangliste">
<button id="btnCupPdf" type="button" class="btn btn-outline-info pdf-btn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Rangliste</span></button>
<button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="cuprang" data-druck-label="Vereinscup Rangliste" aria-label="Rangliste direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
</div>
<button id="redirect-btn" type="button" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Resultate bearbeiten</button>
                <?php $page_actions = ob_get_clean();
                include 'partials/page_header.inc.php'; ?>

                <!-- Weisser Hintergrund-Container -->
                <div class="content-background">

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
                                Paarungen <?= (int)$selectedYear ?> <button type="button" class="btn-help" data-help="cuprang.paarungen" aria-label="Hilfe"></button>
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
                                Finale Rangliste <?= (int)$selectedYear ?> <button type="button" class="btn-help" data-help="cuprang.finale" aria-label="Hilfe"></button>
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
                                Standcup Final <?= (int)$selectedYear ?> <button type="button" class="btn-help" data-help="cuprang.standcup" aria-label="Hilfe"></button>
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

    // Rangliste als PDF (Ausgabe-Baustein msvAusgabe: sperren, Spinner, Download, Toast)
    document.getElementById('btnCupPdf').addEventListener('click', function () {
        const jahr = document.getElementById('yearSelect').value;
        msvAusgabe(this, {
            url: 'cuprang/generate_cup_pdf.php',
            data: { year: jahr, orientation: window.MsvDruck ? MsvDruck.orientierung('cuprang', 'portrait') : 'portrait' }, // Format aus dem Druckprofil
            titel: 'Vereinscup Rangliste ' + jahr,
            name: 'Vereinscup_Rangliste_' + jahr,
            fehler: 'Die Rangliste konnte nicht erstellt werden. Bitte nochmals versuchen.'
        });
    });

    // Year-Dropdown füllen und Event-Handler
    const yearSelect = document.getElementById('yearSelect');
    const current = new Date().getFullYear();
    const selected = <?= (int)$selectedYear ?>;

    msvJahrAuswahl(yearSelect, { jahr: selected });
    
    // Bei Jahr-Änderung Seite neu laden
    yearSelect.addEventListener('change', function() {
        window.location.href = '?year=' + this.value;
    });
});
</script>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray), Profil «Vereinscup Rangliste»; Generator liefert JSON {pdf_link} mit absolutem Pfad
MsvDruck.resolve('cuprang', () => {
    const jahr = document.getElementById('yearSelect').value;
    return { url: 'cuprang/generate_cup_pdf.php?year=' + encodeURIComponent(jahr) + '&orientation=' + MsvDruck.orientierung('cuprang', 'portrait'), jobName: 'Vereinscup Rangliste ' + jahr };
});
</script>
<?php include 'footer.inc.php'; ?>