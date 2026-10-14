-- Migration 109: Wanderpreise und Wanderpreis-Regeln auf einer Seite (Okt 2026, 1.68.29).
-- wanderpreise.php hat die Ansichten «Wanderpreise» und «Regeln»; wanderpreise_regeln.php leitet auf die Ansicht
-- «Regeln» weiter. Die Regelliste zeigt, für welchen Wanderpreis eine Regel gilt; «SQL testen» nimmt das oben
-- gewählte Jahr.
-- 1) Menüeintrag «Wanderpreis-Regeln» entfällt, «Sieger der letzten Jahre» rückt nach.
-- 2) Hilfetexte: Absatz zur Ansicht «Regeln», Hinweis «Weitere» ohne den alten Weg zu den Regeln.
--
-- Idempotent: DELETE/UPDATE über Link, Hilfetexte mit NOT LIKE bzw. REPLACE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

DELETE FROM navigation WHERE Link = 'wanderpreise_regeln.php';
UPDATE navigation SET SortOrder = 50 WHERE Link = 'sieger.php';

UPDATE hilfetexte SET inhalt_html = CONCAT(inhalt_html,
  '<p><strong>Wanderpreise | Regeln</strong>: Der Schalter neben dem Jahr wechselt zwischen dem Stand der Wanderpreise und den <strong>Zuordnungsregeln</strong> der Auto-Zuordnung. Bei jeder Regel steht, für welchen Wanderpreis sie gilt. «SQL testen» im Regel-Panel rechnet mit dem oben gewählten Jahr.</p>')
WHERE schluessel = 'wanderpreise.uebersicht' AND inhalt_html NOT LIKE '%Wanderpreise | Regeln%';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  ' und der Weg zu den <a href="wanderpreise_regeln.php">Wanderpreis-Regeln</a>',
  '; die Regeln stehen in der Ansicht «Regeln» (Schalter neben dem Jahr)')
WHERE schluessel = 'wanderpreise.aktionen';
