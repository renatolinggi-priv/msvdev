<?php
// wanderpreise_regeln.php – seit 1.68.29 die Ansicht «Regeln» von wanderpreise.php (Wanderpreise und ihre
// Zuordnungsregeln auf einer Seite). Bleibt als Weiterleitung für Lesezeichen, alte Links und Hilfetexte.
// Die Endpunkte unter wanderpreise/ (get_regeln_json, save_regel, test_regel_sql …) bleiben in Gebrauch.
header('Location: wanderpreise.php?ansicht=regeln', true, 302);
exit;
