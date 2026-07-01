-- Migration 036: JSK-Verwaltung + Teilnehmerlisten auf EINER Seite (jsk_verwaltung.php)
--
-- Die bisherigen getrennten Admin-Seiten jungschuetzen_verwaltung.php und
-- jsk_teilnahme.php wurden zu inc/jsk_verwaltung.php (Tabs) zusammengeführt.
-- Navigation entsprechend anpassen (idempotent):
--   1. „Jungschützenverwaltung" → jsk_verwaltung.php (+ Text „JSK-Verwaltung")
--   2. den separaten „JSK-Teilnehmerlisten"-Eintrag entfernen (jetzt ein Tab)

UPDATE navigation
   SET Link = 'jsk_verwaltung.php', Text = 'JSK-Verwaltung'
 WHERE Link = 'jungschuetzen_verwaltung.php';

DELETE FROM navigation WHERE Link = 'jsk_teilnahme.php';
