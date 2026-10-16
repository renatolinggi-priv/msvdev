-- Migration 110: Hilfetexte und Menü nach dem Zusammenlegen von Erfassen und Rangliste (Okt 2026, 1.68.30).
-- 1) Spalte navigation.Link fasste nur 30 Zeichen: Mig. 107 schrieb «endresultate.php?ansicht=rangliste» gekürzt als
--    «…?ansicht=rangl», der Menüeintrag «Endschiessen Rangliste» öffnete darum die Ansicht «Erfassen».
--    Der Editor (nav_admin.php) erlaubt bereits 255 Zeichen.
-- 2) Mig. 104/105 nannten Menüeinträge «Jahresmeisterschaft/Heimmeisterschaft/Kantonalstich Rangliste», die Mig. 107
--    danach gelöscht hat (Entscheid: keine doppelten Einträge). Die Texte sagen jetzt, wo die Rangliste auf der Seite steht.
-- 3) Weitere Verweise auf aufgehobene Seiten (heimrang.php, kantirang.php, «Vereinscup Rangliste», «Wanderpreis-Regeln»).
-- 4) JM-Rangliste: vor dem Endstich heisst sie «Zwischenstand».
--
-- Idempotent: MODIFY ist wiederholbar, alle Texte per REPLACE (ohne Treffer keine Änderung).
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

ALTER TABLE navigation MODIFY Link VARCHAR(255) NOT NULL;
UPDATE navigation SET Link = 'endresultate.php?ansicht=rangliste' WHERE Link = 'endresultate.php?ansicht=rangl';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  ' Der Menüeintrag «Jahresmeisterschaft Rangliste» führt ebenfalls hierher, direkt zur Rangliste.',
  ' Einen eigenen Menüeintrag für die Rangliste gibt es nicht; ältere Lesezeichen auf «Jahresmeisterschaft Rangliste» springen direkt zur Rangliste.')
WHERE schluessel = 'jmresultate.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Total</strong> und <strong>Rang</strong> werden erst nach dem Endstich berechnet, vorher steht «offen».</li>',
  '<li><strong>Zwischenstand</strong>: Bis zum Endstich heisst die Liste «Zwischenstand Kat. A/B». Sie ist nach Name sortiert, es gibt noch keine Streicher, und Total und Rang stehen auf «offen». Der Kopf nennt das Datum des Endstichs; danach wird sie zur Rangliste.</li>')
WHERE schluessel = 'jmresultate.rangliste';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  ' Der Menüeintrag «Heimmeisterschaft Rangliste» öffnet diese Seite mit Sortierung «Rang».',
  ' Einen eigenen Menüeintrag für die Rangliste gibt es nicht; ältere Lesezeichen auf «Heimmeisterschaft Rangliste» öffnen diese Seite mit Sortierung «Rang».')
WHERE schluessel = 'heimresultate.tabelle';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  ' Der Menüeintrag «Kantonalstich Rangliste» öffnet diese Seite mit Sortierung «Rang».',
  ' Einen eigenen Menüeintrag für die Rangliste gibt es nicht; ältere Lesezeichen auf «Kantonalstich Rangliste» öffnen diese Seite mit Sortierung «Rang».')
WHERE schluessel = 'kantiresultate.tabelle';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'wirken in der Rangliste (<a href="heimrang.php">Heimmeisterschaft Rangliste</a>).',
  'bestimmen den Rang vor jedem Namen (z.B. «A 3.»).')
WHERE schluessel = 'heimresultate.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'wirken in der Rangliste (<a href="kantirang.php">Kantonalstich Rangliste</a>) sowie in der',
  'bestimmen den Rang vor jedem Namen (z.B. «A 3.») und die Zuordnung in der')
WHERE schluessel = 'kantiresultate.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  ', mit denselben Daten wie die Seite «Vereinscup Rangliste»; ungespeicherte Eingaben fehlen darin.',
  '; ungespeicherte Eingaben fehlen darin, darum vorher speichern.')
WHERE schluessel = 'cup.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '(Seite «Wanderpreis-Regeln»)',
  '(Seite «Wanderpreise», Ansicht «Regeln»)')
WHERE schluessel = 'sieger.uebernehmen';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<a href="endschrang.php">Endschiessen Rangliste</a>',
  '<a href="endresultate.php?ansicht=rangliste">Endschiessen Rangliste</a>')
WHERE schluessel = 'wanderpreise.uebersicht';
