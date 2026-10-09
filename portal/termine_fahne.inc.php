<?php
// termine_fahne.inc.php – Terminliste «Vereinsfahne» (Vorschau, Okt 2026): JM-Anlässe, Vereinstermine
// und die eigenen Einsätze nach Tag gruppiert, nach Monat gegliedert. Ein Tag = ein Kästchen, mehrere
// Anlässe darin untereinander; Antippen öffnet den Tagesplan (tag.php). Vergangenes eingeklappt.
// Eingebunden von termine.php nach portal_header.php ($db, $jahr, $jahre).
if (!isset($db) || !($db instanceof PDO)) { http_response_code(404); exit; }

require_once __DIR__ . '/inc_termine.php';

$tf_mitglied = !empty($_SESSION['mitglied_id']) ? (int) $_SESSION['mitglied_id'] : null;
$tf_heute    = date('Y-m-d');
$tf_tage     = portalTermineNachTag(portalTermineLaden($db, $jahr, $jahr, $tf_mitglied));
$tf_kommend  = array_filter($tf_tage, fn($d) => $d >= $tf_heute, ARRAY_FILTER_USE_KEY);
$tf_vorbei   = array_reverse(array_filter($tf_tage, fn($d) => $d < $tf_heute, ARRAY_FILTER_USE_KEY), true);
$tf_naechster = null;
foreach ($tf_kommend as $d => $l) {
    foreach ($l as $x) { if ($x['art'] !== 'einsatz') { $tf_naechster = $d; break 2; } }
}

$tf_tag = function (string $datum, array $liste, bool $naechster): void {
    $ts = strtotime($datum);
    ?>
    <a class="tf-tag<?php echo $naechster ? ' is-naechster' : ''; ?>" href="tag.php?von=<?php echo $datum; ?>&amp;bis=<?php echo $datum; ?>">
        <span class="tf-datum" aria-hidden="true"><small><?php echo PT_TAGE_KURZ[(int) date('w', $ts)]; ?></small><b><?php echo (int) date('j', $ts); ?></b></span>
        <span class="tf-inhalt">
            <span class="visually-hidden"><?php echo htmlspecialchars(ptTagLang($datum)); ?>:</span>
            <?php foreach ($liste as $x):
                $zeit = $x['fenster'] ? ptFensterText($x['fenster']) : '';
                $ort  = ($x['ort'] !== '' && !ptIstKoordinate($x['ort'])) ? $x['ort'] : '';
            ?>
            <span class="tf-eintrag art-<?php echo $x['art']; ?>">
                <?php if ($x['art'] === 'einsatz'): ?>
                <span class="tf-eigen">Dein Einsatz<?php echo $zeit !== '' ? ' ' . htmlspecialchars($zeit) : ''; ?><?php echo $x['funktion'] !== '' ? ' · ' . htmlspecialchars($x['funktion']) : ''; ?></span>
                <?php else: ?>
                <span class="tf-name"><?php echo htmlspecialchars($x['name']); ?><?php if ($x['jsk']): ?> <span class="tf-jsk">Jungschützen</span><?php endif; ?></span>
                <?php if ($zeit !== '' || $ort !== ''): ?><span class="tf-meta"><?php echo htmlspecialchars(implode(' · ', array_filter([$zeit, $ort]))); ?></span><?php endif; ?>
                <?php endif; ?>
            </span>
            <?php endforeach; ?>
        </span>
        <?php if ($naechster): ?><span class="tf-hinweis"><?php echo ptNaehe($datum) !== '' ? ptNaehe($datum) : 'als Nächstes'; ?></span><?php endif; ?>
    </a>
    <?php
};
?>

<div class="tf-kopf">
    <h1>Termine</h1>
    <div class="tf-aktionen">
        <label class="tf-jahr">Jahr
            <select onchange="location.href='termine.php?year=' + this.value">
                <?php foreach ($jahre as $j): ?>
                <option value="<?php echo $j; ?>"<?php echo $j === $jahr ? ' selected' : ''; ?>><?php echo $j; ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <a class="btn btn-sm btn-outline-primary" href="kalender_abo.php"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Abonnieren</a>
    </div>
</div>

<?php if (!$tf_tage): ?>
<p class="tf-leer">Für <?php echo $jahr; ?> sind noch keine Termine eingetragen.</p>
<?php else: ?>

<?php if ($tf_kommend):
    $monat = '';
    foreach ($tf_kommend as $datum => $liste):
        $m = PT_MONAT_LANG[(int) date('n', strtotime($datum))] . ' ' . date('Y', strtotime($datum));
        if ($m !== $monat): $monat = $m; ?>
<h2 class="tf-monat"><?php echo htmlspecialchars($m); ?></h2>
        <?php endif;
        $tf_tag($datum, $liste, $datum === $tf_naechster);
    endforeach;
elseif ($jahr >= (int) date('Y')): ?>
<p class="tf-leer">Für <?php echo $jahr; ?> stehen keine weiteren Termine an.</p>
<?php endif; ?>

<?php if ($tf_vorbei): ?>
<details class="tf-vorbei">
    <summary>Vergangene Termine <?php echo $jahr; ?> (<?php echo count($tf_vorbei); ?>)</summary>
    <?php foreach ($tf_vorbei as $datum => $liste) $tf_tag($datum, $liste, false); ?>
</details>
<?php endif; ?>

<?php endif; ?>
