-- Migration 107: Menü nach dem Zusammenlegen von Erfassen und Rangliste (Okt 2026, 1.68.29).
--
-- Jahresmeisterschaft, Heimmeisterschaft, Kantonalstich, Vereinscup und Endschiessen haben Erfassen und Rangliste
-- auf einer Seite; jmrang.php, heimrang.php, kantirang.php, cuprang.php und endschrang.php leiten nur noch weiter.
-- 1) Die vier doppelten Ranglisten-Einträge fallen weg (die Weiterleitungen bleiben für Lesezeichen und Links).
-- 2) «Endschiessen erfassen» heisst «Endschiessen» und bündelt Stiche lösen, Resultate erfassen, Rangliste
--    (direkt die Ansicht «Rangliste») und Partner Scheiben Ausdruck (hing an der alten Ranglisten-Seite).
-- 3) Root «Ranglisten» heisst «Auswertungen» (Sektionsmeisterschaft Rangliste, Sektionsabrechnungen, Wanderpreise).
--
-- Idempotent: alle Schritte über Link bzw. Text, wiederholbar.

DELETE FROM navigation WHERE Link IN ('jmrang.php', 'heimrang.php', 'kantirang.php', 'cuprang.php');

UPDATE navigation SET Text = 'Endschiessen'
WHERE Link = '#' AND Text = 'Endschiessen erfassen';

UPDATE navigation n
JOIN (SELECT ID FROM navigation WHERE Link = '#' AND Text = 'Endschiessen' ORDER BY ID LIMIT 1) e
SET n.ParentID = e.ID, n.SortOrder = 30, n.Text = 'Endschiessen Rangliste', n.Link = 'endresultate.php?ansicht=rangliste'
WHERE n.Link IN ('endschrang.php', 'endresultate.php?ansicht=rangliste');

UPDATE navigation n
JOIN (SELECT ID FROM navigation WHERE Link = '#' AND Text = 'Endschiessen' ORDER BY ID LIMIT 1) e
SET n.ParentID = e.ID, n.SortOrder = 40
WHERE n.Link = 'endsch_targetprint.php';

UPDATE navigation SET Text = 'Auswertungen'
WHERE ParentID = 0 AND Text = 'Ranglisten';
