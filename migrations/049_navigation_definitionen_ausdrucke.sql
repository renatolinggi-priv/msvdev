-- Migration 049: Admin-Navigation „Definitionen / Ausdrucke" aufräumen (Review 15.09.2026)
--
-- 1) Ein Name pro Seite für Menü, Tab und H1 (Regel aus Migration 043). Die H1-Änderungen
--    liegen im Code: Gruppenschiessen, Monatsblatt, Dokumente verwalten, Wanderpreis-Regeln,
--    PDF-Vorlage.
-- 2) Doppelte SortOrder (80/80, 90/90) aufgelöst, Reihenfolge nach Themenblöcken:
--    Stammdaten · JM-Definition · Termine · Auswertung, mit Trennlinien (IstTrennlinie,
--    seit Migration 023 möglich, bisher nirgends genutzt).
-- Idempotent: UPDATEs sind wiederholbar, Trennlinien werden nur einmal angelegt.

UPDATE navigation SET Text = 'Wanderpreis-Regeln' WHERE Link = 'wanderpreise_regeln.php';
UPDATE navigation SET Text = 'PDF-Vorlage'        WHERE Link = 'pdf_design.php';

-- Block 1: Stammdaten
UPDATE navigation SET SortOrder = 10 WHERE Link = 'mitgliederverwaltung.php';
UPDATE navigation SET SortOrder = 20 WHERE Link = 'dokumente_verwaltung.php';
UPDATE navigation SET SortOrder = 30 WHERE Link = 'anlass_galerie_verwaltung.php';
UPDATE navigation SET SortOrder = 40 WHERE Link = 'pdf_design.php';
-- Block 2: Jahresmeisterschaft
UPDATE navigation SET SortOrder = 60 WHERE Link = 'jmdefinition.php';
UPDATE navigation SET SortOrder = 70 WHERE Link = 'jmdefinition_gruppen.php';
UPDATE navigation SET SortOrder = 80 WHERE Link = 'jmstandblatt.php';
-- Block 3: Termine
UPDATE navigation SET SortOrder = 100 WHERE Link = 'wichtigetermine.php';
UPDATE navigation SET SortOrder = 110 WHERE Link = 'standbelegung.php';
UPDATE navigation SET SortOrder = 120 WHERE Link = 'monatsblatt.php';
-- Block 4: Auswertung
UPDATE navigation SET SortOrder = 140 WHERE Link = 'mitgliederfragebogen.php';
UPDATE navigation SET SortOrder = 150 WHERE Link = 'sieger.php';
UPDATE navigation SET SortOrder = 160 WHERE Link = 'wanderpreise_regeln.php';

-- Trennlinien zwischen den Blöcken (Text/Link leer, IstTrennlinie = 1)
INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie)
SELECT '', '', p.ID, s.so, NULL, 1
FROM (SELECT ParentID AS ID FROM navigation WHERE Link = 'jmdefinition.php' LIMIT 1) AS p
JOIN (SELECT 50 AS so UNION ALL SELECT 90 UNION ALL SELECT 130) AS s
WHERE NOT EXISTS (
    SELECT 1 FROM navigation t WHERE t.ParentID = p.ID AND t.IstTrennlinie = 1 AND t.SortOrder = s.so
);
