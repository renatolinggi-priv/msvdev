<?php
// portal/meine_einsaetze.php — Alle Arbeitseinsaetze des Mitglieds
$portal_page_title = 'Meine Einsätze';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
$db = getDB();

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
$einsaetze_kommend = [];
$einsaetze_vergangen = [];
$table_exists = true;

if ($mitglied_id) {
    try {
        $stmt = $db->prepare("
            SELECT bezeichnung, event_datum, event_zeit, funktion, typ, jahr
            FROM einsatz_zuweisungen
            WHERE mitglied_id = ?
            ORDER BY event_datum ASC
        ");
        $stmt->execute([$mitglied_id]);
        $alle = $stmt->fetchAll();

        $today = date('Y-m-d');
        foreach ($alle as $e) {
            if ($e['event_datum'] >= $today) {
                $einsaetze_kommend[] = $e;
            } else {
                $einsaetze_vergangen[] = $e;
            }
        }
        // Vergangene: neueste zuerst
        $einsaetze_vergangen = array_reverse($einsaetze_vergangen);
    } catch (Exception $e) {
        $table_exists = false;
    }
}

$weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
$months   = ['', 'Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.'];

function einsatzDatum(string $datum, array $weekdays, array $months): string {
    $dt = new DateTime($datum);
    return $weekdays[$dt->format('w')] . ', ' . $dt->format('j') . '. ' . $months[(int)$dt->format('n')] . ' ' . $dt->format('Y');
}

include 'portal_header.php';
?>

<style>
/* Vergangen-Variante: gedaempfter Look auf .p-list-row */
.p-list-row.vergangen {
    background: #fafafa;
    border-color: #ebebeb;
    opacity: 0.7;
}
.einsatz-datum {
    font-size: 0.78rem;
    color: #718096;
    margin-bottom: 0.1rem;
}
.p-list-row .einsatz-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.p-list-row.vergangen .einsatz-name {
    color: #718096;
}
.einsatz-funktion {
    font-size: 0.78rem;
    color: #718096;
    margin-top: 0.1rem;
}
.einsatz-time-badge {
    font-size: 0.72rem;
    background: #f0f0f0;
    color: #555;
    border-radius: 4px;
    padding: 0.1rem 0.4rem;
    flex-shrink: 0;
    white-space: nowrap;
}
.p-list-row:not(.vergangen) .einsatz-time-badge {
    background: #fff3e0;
    color: #e65100;
}
.naechster-badge {
    font-size: 0.65rem;
    font-weight: 700;
    background: #e65100;
    color: white;
    border-radius: 3px;
    padding: 0.1rem 0.35rem;
    margin-left: 0.4rem;
    vertical-align: middle;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.empty-state {
    text-align: center;
    padding: 2.5rem 1rem;
    color: #a0aec0;
}
.empty-state i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    display: block;
}
.summary-bar {
    background: linear-gradient(135deg, #fff8e1, #ffecb3);
    border: 1px solid #ffe082;
    border-radius: 0.65rem;
    padding: 0.6rem 1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.summary-bar .stat {
    font-size: 0.82rem;
    color: #5d4037;
}
.summary-bar .stat strong {
    font-weight: 700;
    color: #e65100;
}
.vergangene-toggle {
    background: none;
    border: none;
    color: #718096;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.vergangene-toggle:hover { color: #4a5568; }
</style>

<div class="portal-page-header">
    <h1><i class="bi bi-person-badge me-2"></i>Meine Einsätze</h1>
</div>

<?php
$total = count($einsaetze_kommend) + count($einsaetze_vergangen);
if (!$mitglied_id): ?>
    <div class="empty-state">
        <i class="bi bi-person-x"></i>
        <div>Dein Konto ist keinem Vereinsmitglied zugeordnet.<br>Bitte kontaktiere den Administrator.</div>
    </div>
<?php elseif (!$table_exists): ?>
    <div class="empty-state">
        <i class="bi bi-calendar-x"></i>
        <div>Einsätze sind noch nicht konfiguriert.</div>
    </div>
<?php elseif ($total === 0): ?>
    <div class="empty-state">
        <i class="bi bi-calendar-check"></i>
        <div>Es sind noch keine Einsätze für dich erfasst.</div>
    </div>
<?php else: ?>

    <!-- Summary -->
    <div class="summary-bar">
        <?php if (count($einsaetze_kommend) > 0): ?>
        <span class="stat"><strong><?php echo count($einsaetze_kommend); ?></strong> kommende<?php echo count($einsaetze_kommend) === 1 ? 'r' : ''; ?> Einsatz<?php echo count($einsaetze_kommend) !== 1 ? 'ätze' : ''; ?></span>
        <?php endif; ?>
        <?php if (count($einsaetze_vergangen) > 0): ?>
        <span class="stat" style="color:#a0907a"><strong><?php echo count($einsaetze_vergangen); ?></strong> vergangene<?php echo count($einsaetze_vergangen) !== 1 ? '' : 'r'; ?></span>
        <?php endif; ?>
    </div>

    <!-- Kommende Einsaetze -->
    <?php if (!empty($einsaetze_kommend)): ?>
    <div class="p-eyebrow">Kommend</div>
    <?php if (array_filter($einsaetze_kommend, fn($e) => !empty($e['event_zeit']))): ?>
    <div class="alert alert-info py-2 px-3 small mb-2" role="alert">
        <i class="bi bi-info-circle me-1"></i>
        Bitte <strong>30 Minuten vor Arbeitsbeginn</strong> vor Ort erscheinen.
    </div>
    <?php endif; ?>
    <div class="p-list">
    <?php foreach ($einsaetze_kommend as $i => $e): ?>
    <div class="p-list-row">
        <div class="p-chip lg orange"><i class="bi bi-person-badge"></i></div>
        <div class="p-list-body">
            <div class="einsatz-datum">
                <?php echo einsatzDatum($e['event_datum'], $weekdays, $months); ?>
                <?php if ($i === 0): ?><span class="naechster-badge">Nächster</span><?php endif; ?>
            </div>
            <div class="p-list-title einsatz-name"><?php echo htmlspecialchars($e['bezeichnung']); ?></div>
            <?php if (!empty($e['funktion'])): ?>
            <div class="einsatz-funktion"><i class="bi bi-wrench me-1"></i><?php echo htmlspecialchars($e['funktion']); ?></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($e['event_zeit'])): ?>
        <div class="einsatz-time-badge"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($e['event_zeit']); ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="p-eyebrow">Kommend</div>
    <div class="empty-state" style="padding: 1.5rem; color: #a0aec0; font-size: 0.85rem; text-align: center;">
        <i class="bi bi-check-circle" style="font-size: 1.5rem; display: block; margin-bottom: 0.4rem;"></i>
        Keine kommenden Einsätze geplant.
    </div>
    <?php endif; ?>

    <!-- Vergangene Einsaetze -->
    <?php if (!empty($einsaetze_vergangen)): ?>
    <div class="p-eyebrow d-flex align-items-center justify-content-between">
        <span>Vergangen</span>
        <button class="vergangene-toggle" onclick="toggleVergangene(this)" data-open="0">
            <i class="bi bi-chevron-down"></i> Anzeigen
        </button>
    </div>
    <div id="vergangene-list" class="p-list" style="display:none;">
    <?php foreach ($einsaetze_vergangen as $e): ?>
    <div class="p-list-row vergangen">
        <div class="p-chip lg gray"><i class="bi bi-person-badge"></i></div>
        <div class="p-list-body">
            <div class="einsatz-datum"><?php echo einsatzDatum($e['event_datum'], $weekdays, $months); ?></div>
            <div class="p-list-title einsatz-name"><?php echo htmlspecialchars($e['bezeichnung']); ?></div>
            <?php if (!empty($e['funktion'])): ?>
            <div class="einsatz-funktion"><i class="bi bi-wrench me-1"></i><?php echo htmlspecialchars($e['funktion']); ?></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($e['event_zeit'])): ?>
        <div class="einsatz-time-badge"><?php echo htmlspecialchars($e['event_zeit']); ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>

<script>
function toggleVergangene(btn) {
    const list = document.getElementById('vergangene-list');
    const open = btn.dataset.open === '1';
    if (open) {
        list.style.display = 'none';
        btn.innerHTML = '<i class="bi bi-chevron-down"></i> Anzeigen';
        btn.dataset.open = '0';
    } else {
        list.style.display = 'block';
        btn.innerHTML = '<i class="bi bi-chevron-up"></i> Ausblenden';
        btn.dataset.open = '1';
    }
}
</script>

<?php include 'portal_footer.php'; ?>
