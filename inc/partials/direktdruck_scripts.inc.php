<?php
/**
 * partials/direktdruck_scripts.inc.php — Skripte für den QZ-Tray-Direktdruck
 *
 * Einbinden in Admin-Seiten (inc/*.php) VOR footer.inc.php:
 *   <?php include 'partials/direktdruck_scripts.inc.php'; ?>
 * Danach stehen PrintManager, qz und MsvDruck (js/msv-direktdruck.js) zur Verfügung.
 * Cache-Busting per filemtime (siehe header.inc.php), NIE time().
 */
$ddBase = __DIR__ . '/../js/';
$ddV = static fn(string $f): string => (string)(@filemtime($ddBase . $f) ?: '1');
?>
<!-- QZ Tray Direktdruck (Profile in der Drucksteuerung) -->
<script src="js/lib/rsvp.min.js"></script>
<script src="js/lib/sha-256.min.js"></script>
<script src="js/lib/qz-tray.js"></script>
<script src="js/print-manager.js?v=<?= $ddV('print-manager.js') ?>"></script>
<script src="js/msv-direktdruck.js?v=<?= $ddV('msv-direktdruck.js') ?>"></script>
