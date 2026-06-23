<?php
// portal/meine_wanderpreise.php - Wanderpreise-Rückgabe
$portal_page_title = 'Wanderpreise';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
$db = getDB();

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
$zurueckbringen = [];

if ($mitglied_id) {
    // Wanderpreise wo dieses Mitglied aktueller Inhaber ist und noch nicht definitiv
    $stmt = $db->prepare("
        SELECT
            w.bezeichnung,
            w.hersteller,
            wg.jahr as gewonnen_jahr
        FROM wanderpreise w
        INNER JOIN wanderpreise_gewinner wg ON w.id = wg.wanderpreis_id
        INNER JOIN (
            SELECT wanderpreis_id, MAX(jahr) as max_jahr
            FROM wanderpreise_gewinner
            GROUP BY wanderpreis_id
        ) latest ON wg.wanderpreis_id = latest.wanderpreis_id AND wg.jahr = latest.max_jahr
        WHERE wg.gewinner_id = ? AND wg.ist_definitiv = 0
        ORDER BY w.bezeichnung ASC
    ");
    $stmt->execute([$mitglied_id]);
    $zurueckbringen = $stmt->fetchAll();
}

include 'portal_header.php';
?>

<style>
/* Amber Info-Box — seitenspezifisch */
.wp-alert {
    background: linear-gradient(135deg, #fff3e0, #ffe0b2);
    border: 1px solid #ffcc80;
    border-radius: var(--p-radius);
    padding: var(--p-3) var(--p-4);
    margin-bottom: var(--p-4);
}
.wp-alert-icon { font-size: 1.4rem; color: #e65100; }
.wp-alert h3 { font-size: .95rem; font-weight: 700; color: #e65100; margin-bottom: .15rem; }
.wp-alert p { font-size: .82rem; color: #6d4c00; margin-bottom: 0; }
/* Modifier auf .p-list-row: oranger Links-Akzent */
.wp-item { border-left: 4px solid #ff9800; }
.wp-item-year {
    font-size: .8rem;
    font-weight: 600;
    color: #ff9800;
    white-space: nowrap;
}
.wp-empty {
    text-align: center;
    padding: var(--p-5) var(--p-4);
    color: var(--p-text-muted);
}
.wp-empty i { font-size: 2.5rem; margin-bottom: var(--p-3); display: block; color: var(--success-color); }
.wp-empty p { margin-bottom: 0; }
</style>

<div class="portal-page-header">
    <h1><i class="bi bi-award me-2"></i>Wanderpreise</h1>
    <p class="subtitle">Rückgabe am Endschiessen</p>
</div>

<div class="p-narrow">
<?php if (!$mitglied_id): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Dein Account ist noch nicht mit einem Mitglied verknüpft.</div>
<?php elseif (empty($zurueckbringen)): ?>
<div class="wp-empty">
    <i class="bi bi-check-circle-fill"></i>
    <p>Du hast keine Wanderpreise zurückzugeben.</p>
</div>
<?php else: ?>

<div class="wp-alert">
    <div class="d-flex align-items-start gap-3">
        <div class="wp-alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div>
            <h3>Bitte am Endschiessen mitbringen</h3>
            <p>Du hast <?php echo count($zurueckbringen); ?> Wanderpreis<?php echo count($zurueckbringen) > 1 ? 'e' : ''; ?>, <?php echo count($zurueckbringen) > 1 ? 'die' : 'der'; ?> zurückgebracht werden <?php echo count($zurueckbringen) > 1 ? 'müssen' : 'muss'; ?>.</p>
        </div>
    </div>
</div>

<div class="p-list">
    <?php foreach ($zurueckbringen as $wp): ?>
    <div class="p-list-row wp-item">
        <div class="p-chip orange"><i class="bi bi-award"></i></div>
        <div class="p-list-body">
            <div class="p-list-title"><?php echo htmlspecialchars($wp['bezeichnung']); ?></div>
            <?php if (!empty($wp['hersteller'])): ?>
            <div class="p-list-meta"><?php echo htmlspecialchars($wp['hersteller']); ?></div>
            <?php endif; ?>
        </div>
        <div class="p-list-actions">
            <span class="wp-item-year">Gewonnen <?php echo $wp['gewonnen_jahr']; ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
</div>

<?php include 'portal_footer.php'; ?>
