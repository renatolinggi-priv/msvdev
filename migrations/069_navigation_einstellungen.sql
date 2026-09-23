-- Migration 069: Neuer Hauptmenü-Eintrag «Einstellungen» + Spalte navigation.NurAdmin
--
-- 1) Spalte NurAdmin: Einträge mit 1 sieht nur die Rolle admin (Filter in inc/navigation.inc.php,
--    Häkchen im Navigations-Editor). Bisher waren Admin-Links hart im Benutzermenü verdrahtet.
-- 2) Root «Einstellungen» (SortOrder 80, hinter «Schiessdaten Import») bündelt die Konfiguration:
--    Block Anwendung: Hilfetexte (aus Definitionen/Ausdrucke), PDF-Vorlage (dito, nur Admin),
--                     Drucksteuerung, CSV-Schnittstelle (Schiessanlage)   – bisher nur im Benutzermenü
--    Block System:    Benutzerverwaltung, Navigation verwalten, Datenbank aktualisieren (nur Admin,
--                     bisher nur im Benutzermenü), Backup & Restore (bisher Root-Eintrag, nur Admin)
--    «Passwort ändern» bleibt im Benutzermenü. Menütext = Seitentitel (Regel aus Migration 043/049).
--    Links auf Seiten unter /admin/ mit führendem Schrägstrich; relative Links macht der Renderer
--    absolut (/inc/…), damit sie auch von /admin/-Seiten aus funktionieren.
-- 3) Hilfetext für das neue Häkchen im Navigations-Editor.
--
-- Idempotent: ADD COLUMN IF NOT EXISTS, Inserts nur wenn Link fehlt, UPDATEs wiederholbar.

ALTER TABLE navigation ADD COLUMN IF NOT EXISTS NurAdmin TINYINT NOT NULL DEFAULT 0 AFTER IstTrennlinie;

-- Root «Einstellungen»
INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie, NurAdmin)
SELECT 'Einstellungen', '#', 0, 80, 'bi-gear', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM navigation WHERE Text = 'Einstellungen' AND ParentID = 0);

-- Bestehende Einträge umhängen
UPDATE navigation n
JOIN (SELECT ID FROM navigation WHERE Text = 'Einstellungen' AND ParentID = 0 ORDER BY ID LIMIT 1) e
SET n.ParentID = e.ID, n.SortOrder = 10, n.Icon = 'bi-question-circle', n.NurAdmin = 0
WHERE n.Link = 'hilfetexte.php';

UPDATE navigation n
JOIN (SELECT ID FROM navigation WHERE Text = 'Einstellungen' AND ParentID = 0 ORDER BY ID LIMIT 1) e
SET n.ParentID = e.ID, n.SortOrder = 20, n.Icon = 'bi-palette', n.NurAdmin = 1
WHERE n.Link = 'pdf_design.php';

UPDATE navigation n
JOIN (SELECT ID FROM navigation WHERE Text = 'Einstellungen' AND ParentID = 0 ORDER BY ID LIMIT 1) e
SET n.ParentID = e.ID, n.SortOrder = 90, n.Icon = 'bi-hdd', n.NurAdmin = 1, n.Text = 'Backup & Restore'
WHERE n.Link = 'backup_restore.php';

-- Neue Einträge (nur wenn der Link noch nirgends steht)
INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie, NurAdmin)
SELECT v.Text, v.Link, e.ID, v.SortOrder, v.Icon, v.IstTrennlinie, v.NurAdmin
FROM (
    SELECT 'Drucksteuerung'                    AS Text, 'drucksteuerung.php'         AS Link, 30 AS SortOrder, 'bi-printer'          AS Icon, 0 AS IstTrennlinie, 0 AS NurAdmin
    UNION ALL SELECT 'CSV-Schnittstelle (Schiessanlage)', 'csv_schnittstelle.php',       40, 'bi-arrow-left-right', 0, 0
    UNION ALL SELECT 'Benutzerverwaltung',              'benutzerverwaltung.php',      60, 'bi-people-fill',      0, 1
    UNION ALL SELECT 'Navigation verwalten',            '/admin/nav_admin.php',        70, 'bi-menu-button-wide', 0, 1
    UNION ALL SELECT 'Datenbank aktualisieren',         '/admin/aktualisierung.php',   80, 'bi-database-gear',    0, 1
) v
JOIN (SELECT ID FROM navigation WHERE Text = 'Einstellungen' AND ParentID = 0 ORDER BY ID LIMIT 1) e
WHERE NOT EXISTS (SELECT 1 FROM navigation t WHERE t.Link = v.Link);

-- Trennlinie zwischen Block Anwendung und Block System
INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie, NurAdmin)
SELECT '', '', e.ID, 50, NULL, 1, 0
FROM (SELECT ID FROM navigation WHERE Text = 'Einstellungen' AND ParentID = 0 ORDER BY ID LIMIT 1) e
WHERE NOT EXISTS (SELECT 1 FROM navigation t WHERE t.ParentID = e.ID AND t.IstTrennlinie = 1 AND t.SortOrder = 50);

-- Hilfetext zum neuen Häkchen
INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('nav_admin.nuradmin',
 'Nur für Administratoren',
 '<p>Ist das Häkchen gesetzt, sehen nur Benutzer mit der Rolle <strong>Admin</strong> diesen Menüeintrag; für den Vorstand ist er unsichtbar. Unterpunkte eines ausgeblendeten Eintrags verschwinden mit.</p><ul><li>Das Häkchen steuert nur die <em>Anzeige im Menü</em>. Die Seite selbst muss den Zugriff zusätzlich prüfen, sonst bleibt sie über die Adresse erreichbar. Benutzerverwaltung, Navigation, Datenbank-Aktualisierung, PDF-Vorlage und Backup tun das.</li><li>Typische Verwendung: alles unter «Einstellungen», was nur der Administrator bedienen soll.</li></ul><p>Im Editor ist ein solcher Eintrag am gelben Schloss hinter dem Titel erkennbar.</p>',
 'nav_admin')
ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
