<?php
// portal/changelog.php - Neuigkeiten & Updates (Portal-Ansicht)
$portal_page_title = 'Neuigkeiten';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();

$show_intern = isVorstand(); // Admin + Vorstand sehen auch interne Eintraege

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

// Eintraege filtern: Mitglieder sehen nur portal:true
if (!$show_intern) {
    foreach ($changelog as &$release) {
        $release['aenderungen'] = array_filter(
            $release['aenderungen'] ?? [],
            function ($e) { return !empty($e['portal']); }
        );
    }
    unset($release);
    // Versionen ohne sichtbare Eintraege entfernen
    $changelog = array_filter($changelog, function ($r) {
        return !empty($r['aenderungen']);
    });
}

include 'portal_header.php';
?>

<style>
.changelog-page-header {
    margin-bottom: 1.5rem;
}
.changelog-page-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2d3748;
    margin: 0;
}
.changelog-page-header .subtitle {
    color: #718096;
    font-size: 0.9rem;
    margin: 0.25rem 0 0;
}
.version-card {
    background: white;
    border-radius: 0.75rem;
    padding: 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid #f0f0f0;
}
.version-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    padding-bottom: 0.6rem;
    border-bottom: 1px solid #edf2f7;
}
.version-tag {
    background: #2d3748;
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 0.375rem;
    font-size: 0.8rem;
    font-weight: 600;
    font-family: monospace;
}
.version-date {
    color: #718096;
    font-size: 0.8rem;
}
.cl-entry {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.5rem 0;
}
.cl-entry + .cl-entry {
    border-top: 1px solid #f7fafc;
}
.cl-entry .badge {
    font-size: 0.65rem;
    padding: 0.15rem 0.45rem;
    flex-shrink: 0;
    margin-top: 0.15rem;
}
.cl-entry .badge-intern {
    background: #fef3cd;
    color: #856404;
    font-size: 0.6rem;
    padding: 0.1rem 0.35rem;
}
.cl-entry-content {
    flex: 1;
}
.cl-entry-title {
    font-weight: 600;
    color: #2d3748;
    font-size: 0.9rem;
}
.cl-entry-desc {
    color: #718096;
    font-size: 0.82rem;
    margin-top: 0.1rem;
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
@media (max-width: 767.98px) {
    .changelog-page-header h1 { font-size: 1.2rem; }
    .version-card { padding: 1rem; }
    .version-card-header { flex-wrap: wrap; gap: 0.5rem; }
    .cl-entry-title { font-size: 0.85rem; }
    .cl-entry-desc { font-size: 0.78rem; }
}
</style>

<div class="changelog-page-header">
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
    <div class="version-card">
        <div class="version-card-header">
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
        <div class="cl-entry">
            <span class="badge <?php echo $badge['class']; ?>"><?php echo $badge['label']; ?></span>
            <?php if ($is_intern && $show_intern): ?>
            <span class="badge badge-intern">Intern</span>
            <?php endif; ?>
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

<?php include 'portal_footer.php'; ?>
