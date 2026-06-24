-- Migration 030: Navigation - Menueintrag "Jungschuetzenverwaltung" unter "JSK"
--
-- Idempotent und robust (kein hartkodiertes ID):
--   1. Einen bestehenden (verwaisten) Eintrag jsverwaltung.php auf die neue,
--      funktionsfaehige Seite jungschuetzen_verwaltung.php umbiegen.
--   2. Falls (noch) KEIN Eintrag auf jungschuetzen_verwaltung.php zeigt, einen neuen
--      Eintrag unterhalb der JSK-Gruppe (Top-Level-Eintrag mit Text = 'JSK') anlegen.
--      Die JSK-Gruppen-ID wird zur Laufzeit ermittelt. Existiert keine JSK-Gruppe oder
--      ist der Eintrag bereits vorhanden, wird nichts eingefuegt.
-- Erneutes Ausfuehren ist unschaedlich.

UPDATE navigation SET Link = 'jungschuetzen_verwaltung.php' WHERE Link = 'jsverwaltung.php';

INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie)
SELECT 'Jungschützenverwaltung', 'jungschuetzen_verwaltung.php', x.jsk_id, 5, 'bi-person-bounding-box', 0
FROM (
    SELECT
        (SELECT ID FROM navigation WHERE Text = 'JSK' AND ParentID = 0 ORDER BY ID LIMIT 1) AS jsk_id,
        (SELECT COUNT(*) FROM navigation WHERE Link = 'jungschuetzen_verwaltung.php')        AS vorhanden
) AS x
WHERE x.jsk_id IS NOT NULL AND x.vorhanden = 0;
