<?php
// endschrang.php – seit 1.68.29 die Ansicht «Rangliste» von endresultate.php (eine Seite für Erfassen und Rangliste):
// gleiche Tabelle nach Kat. A, Kat. B und «Ohne Wertung», Prüfliste «Absenden vorbereiten» und alle Dokumente.
// Bleibt als Weiterleitung für den Menüeintrag «Endschiessen Rangliste», die Startseite, Lesezeichen und alte Links.
// Die Generatoren und Klassen unter endschrang/ bleiben in Gebrauch.
$ziel = 'endresultate.php?ansicht=rangliste';
if (isset($_GET['year']) && preg_match('/^\d{4}$/', (string)$_GET['year'])) {
    $ziel .= '&year=' . (int)$_GET['year'];
}
header('Location: ' . $ziel, true, 302);
exit;
