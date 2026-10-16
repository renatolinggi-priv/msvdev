<?php
// kantirang.php – seit 1.68.29 Teil von kantiresultate.php: Rang je Kategorie im Raster, Sortierung «Rang»,
// PDF und Direktdruck im Kopf. Bleibt als Weiterleitung für (Menüeintrag seit Mig. 107 entfernt)
// Lesezeichen und alte Links. kantirang/load_kantonal.php und kantirang/generate_pdf.php bleiben in Gebrauch
// (Kantonalstich Abrechnung, Direktdruck).
$ziel = 'kantiresultate.php?sortierung=rang';
if (isset($_GET['year']) && preg_match('/^\d{4}$/', (string)$_GET['year'])) {
    $ziel .= '&year=' . (int)$_GET['year'];
}
header('Location: ' . $ziel, true, 302);
exit;
