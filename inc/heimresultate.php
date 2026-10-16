<?php
// heimresultate.php – Heimmeisterschaft: Raster pro Mitglied (8 Passen), Schnellerfassung im Slide-Panel, Rangliste.
// Raster, Panel und Handy-Karten kommen aus dem Baustein Raster-Erfassung (gemeinsam mit kantiresultate.php):
// Markup partials/raster_erfassen.inc.php, Verhalten js/msv-raster-erfassen.js, Aussehen css/msv-ui.css.
// heimrang.php leitet hierher weiter (?sortierung=rang).
include 'dbconnect.inc.php';
require_once __DIR__ . '/partials/raster_erfassen.inc.php';

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
$csrf = csrf_token();
$raster = [
    'kennung'    => 'heimresultate',
    'passen'     => 8,
    'min_breite' => 880,
    'sort_rang'  => ($_GET['sortierung'] ?? '') === 'rang',
    'csrf'       => $csrf,
];
?>
<?php msv_raster_select2(); ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-default ui-vollhoehe">
                <?php
                $page_title = 'Heimmeisterschaft erfassen';
                $page_title_after = '<button type="button" class="btn-help" data-help="heimresultate.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_actions = '<button type="button" class="btn btn-outline-primary btn-sm d-none d-md-inline-block" id="startEntryBtn"><i class="bi bi-lightning me-1" aria-hidden="true"></i>Schnellerfassung</button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm" id="publishChangelogBtn" data-tooltip="Hinweis «Resultate aktualisiert» auf www.msvwilen.ch"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>Auf Website melden</button>'
                    . '<div class="btn-group btn-group-sm" role="group" aria-label="Rangliste">'
                    . '<button type="button" class="btn btn-outline-info" id="hkPdfBtn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Rangliste</span></button>'
                    . '<button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="heimrang" data-druck-label="Heimmeisterschaft Rangliste" aria-label="Rangliste direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>'
                    . '</div>'
                    . '<button type="submit" form="heimresultateForm" class="btn btn-primary btn-sm" id="rasterSpeichernBtn"><i class="bi bi-save me-1" aria-hidden="true"></i>Speichern</button>';
                $page_extra = '<div class="ui-fortschritt" aria-live="polite"><span class="ui-zahl" id="progressText">–</span></div>'
                    . '<div class="ui-chips" id="progressChips"></div>';
                $page_show_mobile = true;
                include 'partials/page_header.inc.php';

                msv_raster_tabelle($raster);
                ?>
            </div>
        </div>
    </div>
</div>
<?php msv_raster_panel($raster); ?>
<script src="js/msv-raster-erfassen.js?v=<?php echo @filemtime(__DIR__ . '/js/msv-raster-erfassen.js') ?: '1'; ?>"></script>
<script>
$(document).ready(function () {
    MsvRaster.init({
        kennung: 'heimresultate',
        passen: 8,
        laden: 'heimresultate/load_heimresultate_form.php',
        speichern: 'heimresultate/save_heimresultate.php',
        bestePasse: false,
        pdf: { url: 'heimrang/generate_pdf.php', druckprofil: 'heimrang', format: 'landscape',
               titel: 'Heimmeisterschaft Rangliste', datei: 'Heimmeisterschaft_Rangliste' },
        website: { tabelle: 'heimresultate', titel: 'Heimresultate' }
    });
});
</script>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray), Profil «Heimmeisterschaft Rangliste» (A4 quer); fragt wie der PDF-Knopf nach Ungespeichertem
MsvDruck.resolve('heimrang', async () => {
    if (!(await window.hkVorAusgabe())) return null;
    const jahr = document.getElementById('yearSelect').value;
    return { url: 'heimrang/generate_pdf.php?year=' + encodeURIComponent(jahr) + '&orientation=' + MsvDruck.orientierung('heimrang', 'landscape'), jobName: 'Heimmeisterschaft Rangliste ' + jahr };
});
</script>

<?php include 'footer.inc.php'; ?>
