<?php
// portal/tag.php – Tagesplan: alles an einem Tag oder Wochenende auf einen Blick (Okt 2026).
// Aufruf von der Startseite und aus der Terminliste: tag.php?von=Y-m-d&bis=Y-m-d (höchstens 7 Tage).
// Pro Tag nach Uhrzeit: JM-Anlässe (JM-Kennzeichen beim ersten Auftreten), weitere Schiessen,
// Info-Anlässe wie das Mittagessen (ruhiger), Vereinstermine und die eigenen Einsätze.
$portal_page_title = 'Tagesplan';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
require_once __DIR__ . '/inc_termine.php';

$db = getDB();
$mitglied_id = !empty($_SESSION['mitglied_id']) ? (int) $_SESSION['mitglied_id'] : null;

$tagOk = function (string $d): bool {
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)
        && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
};
$von = (string) ($_GET['von'] ?? '');
$bis = (string) ($_GET['bis'] ?? $von);
if (!$tagOk($von)) $von = date('Y-m-d');
if (!$tagOk($bis) || $bis < $von || (strtotime($bis) - strtotime($von)) > 6 * 86400) $bis = $von;

$alle    = portalTermineLaden($db, (int) substr($von, 0, 4), (int) substr($bis, 0, 4) + 1, $mitglied_id);
$imBlock = array_values(array_filter($alle, fn($x) => $x['datum'] >= $von && $x['datum'] <= $bis));
$ohneEinsatz = array_values(array_filter($imBlock, fn($x) => $x['art'] !== 'einsatz'));
$text = $ohneEinsatz ? portalBlockText(['von' => $von, 'bis' => $bis, 'eintraege' => $ohneEinsatz]) : null;

// Weitere Schiessdaten desselben Anlasses ausserhalb dieses Tages (ab heute)
$heute = date('Y-m-d');
$weitere = [];
foreach ($alle as $x) {
    if (strpos($x['quelle'], 'jm:') === 0 && ($x['datum'] < $von || $x['datum'] > $bis) && $x['datum'] >= $heute) {
        $weitere[$x['quelle']][] = $x['datum'];
    }
}
$nachTag    = portalTermineNachTag($imBlock);
$mehrtaegig = ($von !== $bis);
$titel      = $text ? $text['titel'] : (count($imBlock) ? 'Dein Einsatz' : 'Tagesplan');
$portal_page_title = $titel;

$portal_page_css = '
.tp-titel { font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--p-text); }
.tp-meta { margin: .15rem 0 .85rem; color: var(--p-text-muted); font-size: .9375rem; }
.tp-naehe { display: inline-block; font-size: .8rem; font-weight: 700; color: var(--ehre-text, #5c4a00); background: var(--ehre-hell, #fff6bf); border-radius: 999px; padding: 0 .45rem; margin-left: .3rem; }
.tp-tag { font-size: .9375rem; font-weight: 700; color: var(--p-text); margin: 1rem .1rem .4rem; }
.tp-tag:first-of-type { margin-top: .25rem; }
.tp-plan { list-style: none; margin: 0 0 .6rem; padding: 0; background: #fff; border: 1px solid var(--p-border); border-radius: var(--p-radius); }
.tp-plan li { display: flex; gap: .75rem; align-items: flex-start; padding: .55rem .9rem; border-top: 1px solid var(--p-border); }
.tp-plan li:first-child { border-top: 0; }
.tp-zeit { flex-shrink: 0; width: 3.4rem; line-height: 1.25; font-variant-numeric: tabular-nums; }
.tp-zeit b { display: block; font-size: .9375rem; color: var(--p-text); }
.tp-zeit span { font-size: .8125rem; color: var(--p-text-muted); }
.tp-was { flex: 1; min-width: 0; }
.tp-was strong { display: block; font-size: .9375rem; line-height: 1.3; color: var(--p-text); }
.tp-zeile { display: block; font-size: .8125rem; color: var(--p-text-muted); line-height: 1.4; }
.tp-zeile a { color: var(--primary-color); font-weight: 600; }
.tp-weitere { display: block; font-size: .8125rem; color: var(--p-text-muted); font-style: italic; }
.tp-jm { display: inline-block; font-size: .75rem; font-weight: 700; color: var(--akzent-tief, #2d4373); background: var(--akzent-hell, #e8f0fe); border-radius: 999px; padding: 0 .45rem; margin-left: .25rem; vertical-align: 1px; }
.tp-plan li.art-info strong, .tp-plan li.art-info .tp-zeit b { font-weight: 600; color: var(--p-text-muted); }
.tp-plan li.art-einsatz strong { color: var(--akzent-tief, #2d4373); }
.tp-leer { background: #fff; border: 1px solid var(--p-border); border-radius: var(--p-radius); padding: 1.5rem 1rem; text-align: center; color: var(--p-text-muted); }
.tp-leer a { font-weight: 600; }
';
include 'portal_header.php';
?>

<h1 class="tp-titel"><?php echo htmlspecialchars($titel); ?></h1>
<p class="tp-meta">
    <?php echo htmlspecialchars($mehrtaegig ? ptTagLang($von, false) . ' bis ' . ptTagLang($bis) : ptTagLang($von)); ?>
    <?php $naehe = ptNaehe($von); if ($naehe !== ''): ?><span class="tp-naehe"><?php echo $naehe; ?></span><?php endif; ?>
</p>

<?php if (!$nachTag): ?>
<div class="tp-leer">An diesem Tag ist nichts eingetragen. <a href="termine.php">Alle Termine</a></div>
<?php else:
    $gezeigt = [];   // JM-Kennzeichen und weitere Daten nur beim ersten Auftreten
    foreach ($nachTag as $datum => $liste): ?>
    <?php if ($mehrtaegig): ?><h2 class="tp-tag"><?php echo htmlspecialchars(ptTagLang($datum, false)); ?></h2><?php endif; ?>
    <ul class="tp-plan">
        <?php foreach ($liste as $x):
            $erstes = !isset($gezeigt[$x['quelle']]);
            $gezeigt[$x['quelle']] = true;
            $letztes = $x['fenster'] ? end($x['fenster']) : null;
            $ende = ($letztes && $letztes[1] !== '' && $letztes[1] !== '23:59') ? $letztes[1] : '';
        ?>
        <li class="art-<?php echo $x['art']; ?>">
            <span class="tp-zeit"><b><?php echo $x['start'] !== '' ? $x['start'] : '–'; ?></b><?php if ($ende !== ''): ?><span><?php echo $ende; ?></span><?php endif; ?></span>
            <span class="tp-was">
                <strong><?php echo htmlspecialchars($x['art'] === 'einsatz' ? 'Dein Einsatz: ' . $x['name'] : $x['name']); ?><?php if ($x['art'] === 'jm' && $erstes): ?> <span class="tp-jm">JM</span><?php endif; ?></strong>
                <?php if (count($x['fenster']) > 1): ?><span class="tp-zeile"><?php echo htmlspecialchars(ptFensterText($x['fenster'])); ?></span><?php endif; ?>
                <?php if ($x['art'] === 'einsatz' && $x['funktion'] !== ''): ?><span class="tp-zeile"><?php echo htmlspecialchars($x['funktion']); ?></span><?php endif; ?>
                <?php if ($x['ort'] !== '' && $erstes): ?>
                <span class="tp-zeile"><?php if (!ptIstKoordinate($x['ort'])) echo htmlspecialchars($x['ort']) . ' · '; ?><a href="<?php echo htmlspecialchars(ptKartenLink($x['ort'])); ?>" target="_blank" rel="noopener">Karte öffnen</a></span>
                <?php endif; ?>
                <?php if ($erstes && !empty($weitere[$x['quelle']])):
                    $w = array_slice(array_unique($weitere[$x['quelle']]), 0, 3); ?>
                <span class="tp-weitere">auch <?php echo htmlspecialchars(implode(', ', array_map(fn($d) => ptTagKurz($d, true), $w))); ?></span>
                <?php endif; ?>
            </span>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endforeach; endif; ?>

<?php include 'portal_footer.php'; ?>
