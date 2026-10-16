<?php
// jmrang.php – seit 1.68.29 Teil von jmresultate.php (eine Seite für Erfassen und Rangliste).
// Bleibt als Weiterleitung für Lesezeichen und alte Links (Menüeintrag seit Mig. 107 entfernt);
// springt direkt zur Rangliste. Die Ausgaben (PDF, Direktdruck) stehen dort im Kopf.
$ziel = 'jmresultate.php';
if (isset($_GET['year']) && preg_match('/^\d{4}$/', (string)$_GET['year'])) {
    $ziel .= '?year=' . (int)$_GET['year'];
}
header('Location: ' . $ziel . '#rangliste', true, 302);
exit;
