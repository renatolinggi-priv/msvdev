<?php
// portal/changelog.php - Neuigkeiten & Updates (Portal-Ansicht)
$portal_page_title = 'Neuigkeiten';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/changelog_portal.inc.php';
requireLogin();

$show_intern = isVorstand(); // Admin + Vorstand sehen auch interne Eintraege

// Gefilterte, fuer das Portal sichtbare Changelog-Struktur (admin_only entfernt,
// fuer Nicht-Vorstand nur portal:true, leere Releases verworfen).
$changelog = getPortalChangelog($show_intern);

// Typ-Badge Mapping
$typ_badges = [
    'feature'      => ['class' => 'bg-primary',   'label' => 'Feature'],
    'fix'          => ['class' => 'bg-danger',     'label' => 'Fix'],
    'verbesserung' => ['class' => 'bg-success',    'label' => 'Verbesserung'],
    'info'         => ['class' => 'bg-secondary',  'label' => 'Info'],
];

// Deutsche Monatsnamen
$monate = ['','Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

include 'portal_header.php';
?>

<style>
/* version-card uses .p-card + .p-card-body; only page-specifics below */
.version-card { margin-bottom: var(--p-3); }
.version-card-header {
    display: flex;
    align-items: center;
    gap: var(--p-2);
    margin-bottom: var(--p-2);
    padding-bottom: var(--p-2);
    border-bottom: 1px solid var(--p-border);
}
.version-tag {
    background: var(--p-text);
    color: #fff;
    padding: .2rem .6rem;
    border-radius: var(--p-radius-sm);
    font-size: .8rem;
    font-weight: 600;
    font-family: monospace;
}
.version-date {
    color: var(--p-text-muted);
    font-size: .8rem;
}
.version-badges {
    margin-left: auto;
    display: flex;
    gap: var(--p-1);
    flex-shrink: 0;
}
.version-card-header .badge {
    font-size: .65rem;
    padding: .15rem .45rem;
    flex-shrink: 0;
}
.cl-entry {
    display: flex;
    align-items: flex-start;
    gap: var(--p-2);
    padding: .4rem 0;
}
.cl-entry + .cl-entry {
    border-top: 1px solid #f7fafc;
}
.cl-entry-content { flex: 1; }
.cl-entry-title {
    font-weight: 600;
    color: var(--p-text);
    font-size: .9rem;
}
.cl-entry-desc {
    color: var(--p-text-muted);
    font-size: .82rem;
    margin-top: .1rem;
}
.changelog-empty {
    text-align: center;
    padding: var(--p-5) var(--p-4);
    color: #a0aec0;
}
.changelog-empty i {
    font-size: 3rem;
    display: block;
    margin-bottom: var(--p-4);
}
@media (max-width: 767.98px) {
    .version-card-header { flex-wrap: wrap; gap: var(--p-2); }
    .cl-entry-title { font-size: .85rem; }
    .cl-entry-desc { font-size: .78rem; }
}
</style>

<div class="p-narrow">

<div class="portal-page-header">
    <h1><i class="bi bi-megaphone me-2"></i>Neuigkeiten & Updates</h1>
    <p class="subtitle">Alle Änderungen und Neuerungen am System</p>
</div>

<?php if (empty($changelog)): ?>
<div class="changelog-empty">
    <i class="bi bi-journal-text"></i>
    <p>Noch keine Neuigkeiten vorhanden.</p>
</div>
<?php else: ?>
    <?php foreach ($changelog as $release):
        $d = $release['datum'] ?? '';
        $datum_fmt = '';
        if ($d && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
            $datum_fmt = intval($m[3]) . '. ' . ($monate[intval($m[2])] ?? $m[2]) . ' ' . $m[1];
        }
    ?>
    <div class="version-card p-card p-card-body">
        <div class="version-card-header">
            <span class="version-tag"><?php echo htmlspecialchars($release['version'] ?? ''); ?></span>
            <?php if ($datum_fmt): ?>
            <span class="version-date"><i class="bi bi-calendar3 me-1"></i><?php echo $datum_fmt; ?></span>
            <?php endif; ?>
            <?php
            // Distinkte Typ-Badges dieses Releases neben Version/Datum anzeigen (rechtsbündig)
            $seen_typen = [];
            $badges_html = '';
            foreach (($release['aenderungen'] ?? []) as $e) {
                $t = $e['typ'] ?? 'info';
                if (!isset($seen_typen[$t])) {
                    $b = $typ_badges[$t] ?? $typ_badges['info'];
                    $seen_typen[$t] = true;
                    $badges_html .= '<span class="badge ' . $b['class'] . '">' . $b['label'] . '</span>';
                }
            }
            if ($badges_html !== '') {
                echo '<span class="version-badges">' . $badges_html . '</span>';
            }
            ?>
        </div>
        <?php foreach (($release['aenderungen'] ?? []) as $entry): ?>
        <div class="cl-entry">
            <div class="cl-entry-content">
                <div class="cl-entry-title"><?php echo htmlspecialchars($entry['titel'] ?? ''); ?></div>
                <?php if (!empty($entry['beschreibung'])): ?>
                <div class="cl-entry-desc"><?php echo nl2br(htmlspecialchars($entry['beschreibung'])); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

</div><!-- /.p-narrow -->

<?php include 'portal_footer.php'; ?>
