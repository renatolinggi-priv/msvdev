-- Migration 046: Navigation – «Sektionsmeisterschaft Rangliste» unter Ranglisten
--
-- Neue Seite inc/sektionrang.php (Runde 1, Runde 2 und Gesamt der
-- Sektionsmeisterschaft). Einsortiert zwischen «Vereinscup Rangliste» (40)
-- und «Sektionsabrechnungen» (50). Der Name hat 31 Zeichen; navigation.Text
-- war VARCHAR(30) und wird auf 50 erweitert (admin/nav_api.php kürzt bereits
-- auf 50). Idempotent: mehrfaches Ausführen legt keinen zweiten Eintrag an.

ALTER TABLE navigation MODIFY Text VARCHAR(50) NOT NULL;

INSERT INTO navigation (Text, Link, ParentID, SortOrder)
SELECT 'Sektionsmeisterschaft Rangliste', 'sektionrang.php', 2, 45
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM navigation WHERE Link = 'sektionrang.php');
