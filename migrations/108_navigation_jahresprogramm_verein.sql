-- Migration 108: «Definitionen / Ausdrucke» aufgeteilt (Okt 2026, 1.68.29, Entscheid 14.10.2026).
--
-- Die Gruppe mischte vier Dinge. Neu:
--   Jahresprogramm (neu):  Jahresmeisterschaft Definition, Gruppenschiessen, JM Standblatt
--                          | Wichtige Termine, Standbelegung, Monatsblatt
--   Verein (bisher «Definitionen / Ausdrucke», gleiche ID):
--                          Mitgliederverwaltung, Einsatzplanung, Auswertung Fragebogen
--                          | Dokumente verwalten, Foto-Galerien
--   Auswertungen:          Sektionsmeisterschaft Rangliste, Sektionsabrechnungen
--                          | Wanderpreise, Wanderpreis-Regeln, Sieger der letzten Jahre (alles fürs Absenden)
-- Reihenfolge der Hauptpunkte: Resultate, Auswertungen, Jahresprogramm, Verein, Abrechnung, JSK, Import, Einstellungen.
--
-- Idempotent: Gruppen über Text, Einträge über Link; Trennlinien je Gruppe gelöscht und genau einmal neu gesetzt.

-- Hauptpunkte
UPDATE navigation SET Text = 'Verein', SortOrder = 25
WHERE ParentID = 0 AND Text IN ('Definitionen / Ausdrucke', 'Verein');

INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie, NurAdmin)
SELECT 'Jahresprogramm', '', 0, 20, NULL, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM navigation WHERE ParentID = 0 AND Text = 'Jahresprogramm');

-- Jahresprogramm
UPDATE navigation n
JOIN (SELECT ID FROM navigation WHERE ParentID = 0 AND Text = 'Jahresprogramm' ORDER BY ID LIMIT 1) g
JOIN (
    SELECT 'jmdefinition.php' AS Link, 10 AS SortOrder
    UNION ALL SELECT 'jmdefinition_gruppen.php', 20
    UNION ALL SELECT 'jmstandblatt.php',         30
    UNION ALL SELECT 'wichtigetermine.php',      50
    UNION ALL SELECT 'standbelegung.php',        60
    UNION ALL SELECT 'monatsblatt.php',          70
) v ON v.Link = n.Link
SET n.ParentID = g.ID, n.SortOrder = v.SortOrder;

-- Verein
UPDATE navigation n
JOIN (SELECT ID FROM navigation WHERE ParentID = 0 AND Text = 'Verein' ORDER BY ID LIMIT 1) g
JOIN (
    SELECT 'mitgliederverwaltung.php' AS Link, 10 AS SortOrder
    UNION ALL SELECT 'einsatzplanung.php',            20
    UNION ALL SELECT 'mitgliederfragebogen.php',      30
    UNION ALL SELECT 'dokumente_verwaltung.php',      50
    UNION ALL SELECT 'anlass_galerie_verwaltung.php', 60
) v ON v.Link = n.Link
SET n.ParentID = g.ID, n.SortOrder = v.SortOrder;

-- Auswertungen
UPDATE navigation n
JOIN (SELECT ID FROM navigation WHERE ParentID = 0 AND Text IN ('Auswertungen', 'Ranglisten') ORDER BY ID LIMIT 1) g
JOIN (
    SELECT 'sektionrang.php' AS Link, 10 AS SortOrder
    UNION ALL SELECT 'jmdurchschnitt.php',      20
    UNION ALL SELECT 'wanderpreise.php',        40
    UNION ALL SELECT 'wanderpreise_regeln.php', 50
    UNION ALL SELECT 'sieger.php',              60
) v ON v.Link = n.Link
SET n.ParentID = g.ID, n.SortOrder = v.SortOrder;

-- Trennlinien: in den drei Gruppen alle entfernen, dann je eine an die Blockgrenze
DELETE FROM navigation
WHERE IstTrennlinie = 1
  AND ParentID IN (SELECT ID FROM (SELECT ID FROM navigation WHERE ParentID = 0 AND Text IN ('Jahresprogramm', 'Verein', 'Auswertungen')) g);

INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie, NurAdmin)
SELECT '', '', g.ID, s.SortOrder, NULL, 1, 0
FROM navigation g
JOIN (
    SELECT 'Jahresprogramm' AS Text, 40 AS SortOrder
    UNION ALL SELECT 'Verein',        40
    UNION ALL SELECT 'Auswertungen',  30
) s ON s.Text = g.Text
WHERE g.ParentID = 0;
