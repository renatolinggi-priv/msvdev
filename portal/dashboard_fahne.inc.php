<?php
// dashboard_fahne.inc.php – Startseite «Vereinsfahne», Variante B (Vorschau, nur Admins mit Schalter, Okt 2026).
// Eingebunden von dashboard.php nach portal_header.php. Nutzt dessen Daten ($vorname, $mitglied_id,
// $next_einsatz, $offene_tausch, $offene_umfragen, $push_setup_noetig).
// Aufbau: Begrüssung → «Jetzt»-Liste (nächster Tag oder Wochenende, nächster Einsatz, Offenes)
// → «Alles im Portal» als ruhige Kacheln. Gleichzeitige Anlässe: ein Tag/Wochenende = ein Eintrag,
// Details auf tag.php.
if (!isset($db) || !($db instanceof PDO)) { http_response_code(404); exit; }

require_once __DIR__ . '/inc_termine.php';

$fs_heute = date('Y-m-d');
$fs_alle  = portalTermineLaden($db, (int) date('Y'), (int) date('Y') + 1);
$fs_block = portalNaechsterBlock($fs_alle, $fs_heute);
$fs_text  = $fs_block ? portalBlockText($fs_block) : null;

$fs_chip = function (string $ymd): string {
    $ts = strtotime($ymd);
    return '<b>' . (int) date('j', $ts) . '</b><small>' . htmlspecialchars(PT_MONAT_KURZ[(int) date('n', $ts)]) . '</small>';
};
$fs_pfeil = '<i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i>';
?>

<div class="fs-gruss">
    <div>
        <h1>Hallo <?php echo htmlspecialchars($vorname); ?></h1>
        <p><?php echo htmlspecialchars(ptTagLang($fs_heute, false)); ?></p>
    </div>
    <?php if ($mitglied_id): ?>
    <button type="button" class="fs-lizenz" id="barcodeBtn" aria-label="SSV-Lizenz als Barcode zeigen"><i class="bi bi-upc-scan" aria-hidden="true"></i></button>
    <?php endif; ?>
</div>

<ul class="fs-jetzt" aria-label="Was jetzt ansteht">
    <?php if ($fs_block): $naehe = ptNaehe($fs_block['von']); ?>
    <li><a href="tag.php?von=<?php echo $fs_block['von']; ?>&amp;bis=<?php echo $fs_block['bis']; ?>">
        <span class="fs-chip is-gold" aria-hidden="true"><?php echo $fs_chip($fs_block['von']); ?></span>
        <span class="fs-txt">
            <strong><?php echo htmlspecialchars($fs_text['titel']); ?><?php if ($naehe !== ''): ?> <span class="fs-naehe"><?php echo $naehe; ?></span><?php endif; ?></strong>
            <?php if (!$fs_text['mehrtaegig']): ?><span class="visually-hidden"><?php echo htmlspecialchars(ptTagLang($fs_block['von'])); ?>,</span><?php endif; ?>
            <?php if ($fs_text['wann'] !== ''): ?><span class="fs-wann"><?php echo htmlspecialchars($fs_text['wann']); ?></span><?php endif; ?>
            <?php if ($fs_text['unter'] !== ''): ?><span class="fs-unter"><?php echo htmlspecialchars($fs_text['unter']); ?></span><?php endif; ?>
        </span><?php echo $fs_pfeil; ?></a></li>
    <?php endif; ?>

    <?php if ($next_einsatz): ?>
    <li><a href="meine_einsaetze.php">
        <span class="fs-chip" aria-hidden="true"><?php echo $fs_chip($next_einsatz['event_datum']); ?></span>
        <span class="fs-txt">
            <strong>Einsatz: <?php echo htmlspecialchars($next_einsatz['bezeichnung']); ?></strong>
            <span class="fs-wann"><?php echo htmlspecialchars(ptTagKurz($next_einsatz['event_datum'], true) . (!empty($next_einsatz['event_zeit']) ? ', ' . $next_einsatz['event_zeit'] : '') . (!empty($next_einsatz['funktion']) ? ' · ' . $next_einsatz['funktion'] : '')); ?></span>
        </span><?php echo $fs_pfeil; ?></a></li>
    <?php endif; ?>

    <?php if ($offene_tausch > 0): ?>
    <li><a href="meine_einsaetze.php">
        <span class="fs-chip" aria-hidden="true"><i class="bi bi-arrow-left-right"></i></span>
        <span class="fs-txt"><strong><?php echo $offene_tausch === 1 ? '1 Tausch-Anfrage' : $offene_tausch . ' Tausch-Anfragen'; ?></strong><span class="fs-wann"><?php echo $offene_tausch === 1 ? 'wartet auf deine Antwort' : 'warten auf deine Antwort'; ?></span></span><?php echo $fs_pfeil; ?></a></li>
    <?php endif; ?>

    <?php if ($offene_umfragen > 0): ?>
    <li><a href="mein_fragebogen.php">
        <span class="fs-chip" aria-hidden="true"><i class="bi bi-clipboard-check"></i></span>
        <span class="fs-txt"><strong><?php echo $offene_umfragen === 1 ? '1 Umfrage offen' : $offene_umfragen . ' Umfragen offen'; ?></strong><span class="fs-wann"><?php echo $offene_umfragen === 1 ? 'wartet auf deine Antwort' : 'warten auf deine Antworten'; ?></span></span><?php echo $fs_pfeil; ?></a></li>
    <?php endif; ?>

    <?php if (!empty($push_setup_noetig)): ?>
    <li><a href="benachrichtigungen.php">
        <span class="fs-chip" aria-hidden="true"><i class="bi bi-bell"></i></span>
        <span class="fs-txt"><strong>Benachrichtigungen einschalten</strong><span class="fs-wann">auf diesem Gerät, damit sie ankommen</span></span><?php echo $fs_pfeil; ?></a></li>
    <?php endif; ?>
</ul>

<?php if (empty($portal_hide_pwa_install)) include __DIR__ . '/inc_pwa_install.php'; /* «Als App installieren», nur wenn noch nicht installiert */ ?>

<h2 class="fs-bereiche-titel">Alles im Portal</h2>
<nav class="fs-kacheln" aria-label="Alle Bereiche">
    <a href="meine_jm.php"><i class="bi bi-bullseye" aria-hidden="true"></i><span>Jahresmeisterschaft</span></a>
    <a href="meine_heim.php"><i class="bi bi-house-heart" aria-hidden="true"></i><span>Heimmeisterschaft</span></a>
    <a href="meine_kanti.php"><i class="bi bi-shield" aria-hidden="true"></i><span>Kantonalstich</span></a>
    <a href="meine_wanderpreise.php"><i class="bi bi-trophy" aria-hidden="true"></i><span>Wanderpreise</span></a>
    <a href="termine.php"><i class="bi bi-calendar3" aria-hidden="true"></i><span>Termine</span></a>
    <a href="anlaesse.php"><i class="bi bi-images" aria-hidden="true"></i><span>Fotos</span></a>
    <a href="meine_einsaetze.php"><i class="bi bi-person-badge" aria-hidden="true"></i><span>Meine Einsätze</span></a>
    <a href="mein_fragebogen.php"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Umfragen</span></a>
    <a href="einsatzplaene.php"><i class="bi bi-calendar-check" aria-hidden="true"></i><span>Einsatzpläne</span></a>
    <a href="protokolle.php"><i class="bi bi-file-text" aria-hidden="true"></i><span>Protokolle</span></a>
</nav>
