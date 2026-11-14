-- Migration 138: Hilfetext «Dokumente» der Endschiessen-Rangliste – Sammelrangliste mit Partner und Siegerseite
-- (Nov 2026, 1.68.64/1.68.65): Partner steht vor «Endschiessen gesamt», am Schluss folgen alle Sieger wie im Absendenbuch.
-- Ergänzt den Satz aus Migration 137.
--
-- Idempotent: REPLACE greift nur, solange «Zabig, Partner,» noch nicht im Text steht.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'Zabig, Endschiessen gesamt, Heimmeisterschaft, Kantonalstich, Cup, Jahresmeisterschaft.',
  'Zabig, Partner, Endschiessen gesamt, Heimmeisterschaft, Kantonalstich, Cup, Jahresmeisterschaft und am Schluss alle Sieger wie im Absendenbuch (Jahresmeister zuerst).')
WHERE schluessel = 'endschrang.dokumente'
  AND inhalt_html NOT LIKE '%Zabig, Partner,%';
