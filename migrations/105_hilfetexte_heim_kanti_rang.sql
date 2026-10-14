-- Migration 105: Heimmeisterschaft und Kantonalstich mit Rangliste im Erfassungsraster (Okt 2026, 1.68.29).
-- heimrang.php und kantirang.php leiten auf die Erfassung mit Sortierung «Rang» weiter. Im Raster steht vor dem
-- Namen der Rang je Kategorie, der Umschalter «Name | Rang» sortiert, im Kopf stehen PDF und Direktdruck.
-- Ergänzt die bestehenden Texte um einen Absatz (nur wenn er noch fehlt), damit Anpassungen im Editor bleiben.
--
-- Idempotent: Bedingung NOT LIKE auf den neuen Absatz.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte SET inhalt_html = CONCAT(inhalt_html,
  '<p><strong>Rangliste</strong>: Vor jedem Namen steht der Rang in seiner Kategorie (z.B. «A 3.»), er rechnet beim Tippen mit. Gleiches Total heisst gleicher Rang. Der Umschalter <strong>Name | Rang</strong> sortiert das Raster nach Name oder nach Kat. A, Kat. B und Rang; die Zeilen ordnen sich nach dem Speichern neu. Der Menüeintrag «Heimmeisterschaft Rangliste» öffnet diese Seite mit Sortierung «Rang».</p>')
WHERE schluessel = 'heimresultate.tabelle' AND inhalt_html NOT LIKE '%Name | Rang%';

UPDATE hilfetexte SET inhalt_html = CONCAT(inhalt_html,
  '<p><strong>Rangliste</strong> (oben) erstellt die Rangliste als PDF, der Drucker daneben druckt sie direkt.</p>')
WHERE schluessel = 'heimresultate.uebersicht' AND inhalt_html NOT LIKE '%druckt sie direkt%';

UPDATE hilfetexte SET inhalt_html = CONCAT(inhalt_html,
  '<p><strong>Rangliste</strong>: Vor jedem Namen steht der Rang in seiner Kategorie (z.B. «A 3.»), er rechnet beim Tippen mit. Massgebend ist die Summe der Passen; gleiche Summe heisst gleicher Rang. Der Umschalter <strong>Name | Rang</strong> sortiert das Raster nach Name oder nach Kat. A, Kat. B und Rang; die Zeilen ordnen sich nach dem Speichern neu. Der Menüeintrag «Kantonalstich Rangliste» öffnet diese Seite mit Sortierung «Rang».</p>')
WHERE schluessel = 'kantiresultate.tabelle' AND inhalt_html NOT LIKE '%Name | Rang%';

UPDATE hilfetexte SET inhalt_html = CONCAT(inhalt_html,
  '<p><strong>Rangliste</strong> (oben) erstellt die Rangliste als PDF, der Drucker daneben druckt sie direkt.</p>')
WHERE schluessel = 'kantiresultate.uebersicht' AND inhalt_html NOT LIKE '%druckt sie direkt%';
