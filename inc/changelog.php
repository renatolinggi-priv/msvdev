<?php
// inc/changelog.php - Changelog Desktop-Ansicht (Admin/Vorstand)
include 'dbconnect.inc.php';
include 'header.inc.php';

// JSON laden
$changelog = [];
$json_path = __DIR__ . '/../changelog.json';
if (file_exists($json_path)) {
    $raw = file_get_contents($json_path);
    $changelog = json_decode($raw, true) ?: [];
}

// Typ-Badge Mapping
$typ_badges = [
    'feature'      => ['class' => 'bg-primary',   'label' => 'Feature'],
    'fix'          => ['class' => 'bg-danger',     'label' => 'Fix'],
    'verbesserung' => ['class' => 'bg-success',    'label' => 'Verbesserung'],
    'info'         => ['class' => 'bg-secondary',  'label' => 'Info'],
];

// Deutsche Monatsnamen
$monate = ['','Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
?>

<style>
.changelog-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 1rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.changelog-header h2 {
    font-size: 1.4rem;
    font-weight: 700;
    color: #2d3748;
    margin: 0;
}
.changelog-header .subtitle {
    color: #718096;
    font-size: 0.9rem;
    margin: 0.25rem 0 0;
}
.version-block {
    margin-bottom: 1.5rem;
}
.version-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e2e8f0;
}
.version-header .version-tag {
    background: #2d3748;
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 0.375rem;
    font-size: 0.85rem;
    font-weight: 600;
    font-family: monospace;
}
.version-header .version-date {
    color: #718096;
    font-size: 0.85rem;
}
.changelog-entry {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.6rem 0;
}
.changelog-entry + .changelog-entry {
    border-top: 1px solid #f0f0f0;
}
.changelog-entry .badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    flex-shrink: 0;
    margin-top: 0.15rem;
}
.changelog-entry .badge-intern {
    background: #fef3cd;
    color: #856404;
    font-size: 0.65rem;
    padding: 0.1rem 0.4rem;
}
.changelog-entry .entry-content {
    flex: 1;
}
.changelog-entry .entry-title {
    font-weight: 600;
    color: #2d3748;
    font-size: 0.95rem;
}
.changelog-entry .entry-desc {
    color: #718096;
    font-size: 0.85rem;
    margin-top: 0.15rem;
}
.changelog-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: #a0aec0;
}
.changelog-empty i {
    font-size: 3rem;
    display: block;
    margin-bottom: 1rem;
}
@media (max-width: 768px) {
    .changelog-header { padding: 1rem; }
    .changelog-header h2 { font-size: 1.2rem; }
    .version-header { flex-wrap: wrap; gap: 0.5rem; }
}
</style>

<div class="content-wrapper">
    <div class="content-background">
        <div class="changelog-header">
            <h2><i class="bi bi-megaphone me-2"></i>Changelog</h2>
            <p class="subtitle">Alle Änderungen und Neuerungen am System</p>
        </div>

        <?php if (empty($changelog)): ?>
        <div class="changelog-empty">
            <i class="bi bi-journal-text"></i>
            <p>Noch keine Changelog-Einträge vorhanden.</p>
        </div>
        <?php else: ?>
            <?php foreach ($changelog as $release):
                $d = $release['datum'] ?? '';
                $datum_fmt = '';
                if ($d && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
                    $datum_fmt = intval($m[3]) . '. ' . ($monate[intval($m[2])] ?? $m[2]) . ' ' . $m[1];
                }
            ?>
            <div class="version-block">
                <div class="version-header">
                    <span class="version-tag"><?php echo htmlspecialchars($release['version'] ?? ''); ?></span>
                    <?php if ($datum_fmt): ?>
                    <span class="version-date"><i class="bi bi-calendar3 me-1"></i><?php echo $datum_fmt; ?></span>
                    <?php endif; ?>
                </div>
                <?php foreach (($release['aenderungen'] ?? []) as $entry):
                    $typ = $entry['typ'] ?? 'info';
                    $badge = $typ_badges[$typ] ?? $typ_badges['info'];
                    $is_intern = empty($entry['portal']);
                ?>
                <div class="changelog-entry">
                    <span class="badge <?php echo $badge['class']; ?>"><?php echo $badge['label']; ?></span>
                    <?php if ($is_intern): ?>
                    <span class="badge badge-intern">Intern</span>
                    <?php endif; ?>
                    <div class="entry-content">
                        <div class="entry-title"><?php echo htmlspecialchars($entry['titel'] ?? ''); ?></div>
                        <?php if (!empty($entry['beschreibung'])): ?>
                        <div class="entry-desc"><?php echo nl2br(htmlspecialchars($entry['beschreibung'])); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.inc.php'; ?>
