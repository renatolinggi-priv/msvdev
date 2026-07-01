-- Migration 037: Admin-Navigation – Menüeintrag "Dokumente verwalten"
--
-- Zentrale Admin-Seite inc/dokumente_verwaltung.php verwaltet ALLE Dokumente
-- (Einsatzpläne, Protokolle, JSK-Dokumente). Upload/Verwaltung läuft ab sofort
-- nur noch im Admin-Bereich, nicht mehr über die PWA/Portal-Seiten.
--
-- Idempotent & robust (kein hartkodiertes Parent-ID): Eintrag unterhalb der
-- bestehenden Gruppe "Definitionen / Ausdrucke" anlegen, falls noch nicht vorhanden.
-- Erneutes Ausführen ist unschädlich.

INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie)
SELECT 'Dokumente verwalten', 'dokumente_verwaltung.php', x.parent_id, 80, 'bi-folder', 0
FROM (
    SELECT
        (SELECT ID FROM navigation WHERE Text = 'Definitionen / Ausdrucke' AND ParentID = 0 ORDER BY ID LIMIT 1) AS parent_id,
        (SELECT COUNT(*) FROM navigation WHERE Link = 'dokumente_verwaltung.php')                                 AS vorhanden
) AS x
WHERE x.parent_id IS NOT NULL AND x.vorhanden = 0;
