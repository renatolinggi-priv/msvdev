-- Migration 043: Admin-Navigation – einheitliche Benennung (Optik-Audit 2.1)
--
-- Pro Seite gilt EIN Name für Menü, Tab und H1. Menü/Tab kommen aus
-- navigation.Text; die H1/$page_title-Änderungen liegen im Code.
-- UPDATEs sind idempotent (mehrfaches Ausführen unschädlich).

UPDATE navigation SET Text = 'JM Standblatt'        WHERE ID = 10123;
UPDATE navigation SET Text = 'Imetron CSV prüfen'   WHERE ID = 10103;
UPDATE navigation SET Text = 'Endschiessen Partner' WHERE ID = 10104;

-- Trailing-Space im Link bereinigen (Nebenfund 2.1):
UPDATE navigation SET Link = 'jmdurchschnitt.php'   WHERE ID = 10095;
