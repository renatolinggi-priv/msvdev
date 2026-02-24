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
.wp-alert {
    background: linear-gradient(135deg, #fff3e0, #ffe0b2);
    border: 1px solid #ffcc80;
    border-radius: 0.75rem;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}
.wp-alert-icon { font-size: 1.5rem; color: #e65100; }
.wp-alert h3 { font-size: 1rem; font-weight: 700; color: #e65100; margin-bottom: 0.25rem; }
.wp-alert p { font-size: 0.85rem; color: #6d4c00; margin-bottom: 0; }
.wp-list { list-style: none; padding: 0; margin: 0; }
.wp-item {
    background: white;
    border-radius: 0.75rem;
    padding: 1rem 1.25rem;
    margin-bottom: 0.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border-left: 4px solid #ff9800;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.wp-item-name { font-weight: 600; color: #2d3748; }
.wp-item-detail { font-size: 0.8rem; color: #718096; }
.wp-item-year {
    font-size: 0.8rem;
    font-weight: 600;
    color: #ff9800;
    white-space: nowrap;
}
.wp-empty {
    text-align: center;
    padding: 2.5rem 1rem;
    color: #718096;
}
.wp-empty i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; color: #28a745; }
.wp-empty p { margin-bottom: 0; }
</style>

<div class="portal-page-header">
    <h1><i class="bi bi-award me-2"></i>Wanderpreise</h1>
    <p class="subtitle">Rückgabe am Endschiessen</p>
</div>

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

<ul class="wp-list">
    <?php foreach ($zurueckbringen as $wp): ?>
    <li class="wp-item">
        <div>
            <div class="wp-item-name"><?php echo htmlspecialchars($wp['bezeichnung']); ?></div>
            <?php if (!empty($wp['hersteller'])): ?>
            <div class="wp-item-detail"><?php echo htmlspecialchars($wp['hersteller']); ?></div>
            <?php endif; ?>
        </div>
        <div class="wp-item-year">Gewonnen <?php echo $wp['gewonnen_jahr']; ?></div>
    </li>
    <?php endforeach; ?>
</ul>

<?php endif; ?>

<?php include 'portal_footer.php'; ?>
