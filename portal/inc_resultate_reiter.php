<?php
// inc_resultate_reiter.php – Reiter zwischen den Resultatseiten (Vorschau «Vereinsfahne», Okt 2026).
// Eingebunden von meine_jm/meine_heim/meine_kanti/meine_wanderpreise nach portal_header.php.
// Ohne Vorschau gibt die Datei nichts aus.
if (empty($portal_fahne)) return;

$__reiter = [
    ['meine_jm.php', 'JM'],
    ['meine_heim.php', 'Heim'],
    ['meine_kanti.php', 'Kanti'],
    ['meine_wanderpreise.php', 'Wanderpreise'],
];
$__hier = basename($_SERVER['PHP_SELF'] ?? '');
?>
<nav class="fr-reiter" aria-label="Resultate">
    <?php foreach ($__reiter as [$__href, $__text]): ?>
    <a href="<?php echo $__href; ?>"<?php echo $__href === $__hier ? ' aria-current="page"' : ''; ?>><?php echo $__text; ?></a>
    <?php endforeach; ?>
</nav>
