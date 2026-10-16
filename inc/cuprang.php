<?php
// cuprang.php – seit 1.68.29 Weiterleitung auf cup.php («Vereinscup erfassen»): Turnierbaum mit Paarungen,
// Finale und Standcup, Rangliste (PDF + Direktdruck) im Kopf. Bleibt (Menüeintrag seit Mig. 107 entfernt) für
// Lesezeichen und alte Links. Die Ordner cuprang/ (Generator, Repository) bleiben in Gebrauch.
$ziel = 'cup.php';
if (isset($_GET['year']) && preg_match('/^\d{4}$/', (string)$_GET['year'])) {
    $ziel .= '?year=' . (int)$_GET['year'];
}
header('Location: ' . $ziel, true, 302);
exit;
