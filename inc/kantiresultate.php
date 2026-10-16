<?php
// kantiresultate.php – Kantonalstich: Raster pro Mitglied (5 Passen), Schnellerfassung im Slide-Panel, Rangliste.
// Raster, Panel und Handy-Karten kommen aus dem Baustein Raster-Erfassung (gemeinsam mit heimresultate.php):
// Markup partials/raster_erfassen.inc.php, Verhalten js/msv-raster-erfassen.js, Aussehen css/msv-ui.css.
// Die beste Passe ist gold markiert (zählt als «Bester Kantonalstich» in der JM).
// kantirang.php leitet hierher weiter (?sortierung=rang).
include 'dbconnect.inc.php';
require_once __DIR__ . '/partials/raster_erfassen.inc.php';

include 'header.inc.php';
require_once __DIR__ . '/csrf.inc.php';
$csrf = csrf_token();
$raster = [
    'kennung'    => 'kantiresultate',
    'passen'     => 5,
    'min_breite' => 680,
    'sort_rang'  => ($_GET['sortierung'] ?? '') === 'rang',
    'csrf'       => $csrf,
];
?>
<!-- Select2 (Schützenwahl in der Schnellerfassung). defer: hält den Seitenaufbau nicht an; fehlt Select2
     (CDN nicht erreichbar), bleibt die normale Auswahlliste -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" defer></script>
<style>
.select2-container { z-index: 1065; }
#entryMemberSelect + .select2-container { width: 100% !important; }
.select2-container--bootstrap-5 .select2-selection { min-height: calc(1.5em + 0.75rem + 2px); }
</style>
<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-default ui-vollhoehe">
                <?php
                $page_title = 'Kantonalstich erfassen';
                $page_title_after = '<button type="button" class="btn-help" data-help="kantiresultate.uebersicht" aria-label="Hilfe"></button>'
                    . '<label for="yearSelect" class="visually-hidden">Jahr</label>'
                    . '<select id="yearSelect" class="form-select form-select-sm"></select>';
                $page_actions = '<button type="button" class="btn btn-outline-primary btn-sm d-none d-md-inline-block" id="startEntryBtn"><i class="bi bi-lightning me-1" aria-hidden="true"></i>Schnellerfassung</button>'
                    . '<button type="button" class="btn btn-outline-secondary btn-sm" id="publishChangelogBtn" data-tooltip="Hinweis «Resultate aktualisiert» auf www.msvwilen.ch"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>Auf Website melden</button>'
                    . '<div class="btn-group btn-group-sm" role="group" aria-label="Rangliste">'
                    . '<button type="button" class="btn btn-outline-info" id="hkPdfBtn"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i><span>Rangliste</span></button>'
                    . '<button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="kantirang" data-druck-label="Kantonalstich Rangliste" aria-label="Rangliste direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>'
                    . '</div>'
                    . '<button type="submit" form="kantiresultateForm" class="btn btn-primary btn-sm" id="rasterSpeichernBtn"><i class="bi bi-save me-1" aria-hidden="true"></i>Speichern</button>';
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
        kennung: 'kantiresultate',
        passen: 5,
        laden: 'kantiresultate/load_kantiresultate_form.php',
        speichern: 'kantiresultate/save_kantiresultate.php',
        bestePasse: true,
        pdf: { url: 'kantirang/generate_pdf.php', druckprofil: 'kantirang', format: 'portrait',
               titel: 'Kantonalstich Rangliste', datei: 'Kantonalstich_Rangliste' },
        website: { tabelle: 'kantiresultate', titel: 'Kantiresultate' }
    });
});
</script>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray), Profil «Kantonalstich Rangliste» (A4 hoch); fragt wie der PDF-Knopf nach Ungespeichertem
MsvDruck.resolve('kantirang', async () => {
    if (!(await window.hkVorAusgabe())) return null;
    const jahr = document.getElementById('yearSelect').value;
    return { url: 'kantirang/generate_pdf.php?year=' + encodeURIComponent(jahr) + '&orientation=' + MsvDruck.orientierung('kantirang', 'portrait'), jobName: 'Kantonalstich Rangliste ' + jahr };
});
</script>

<?php include 'footer.inc.php'; ?>
