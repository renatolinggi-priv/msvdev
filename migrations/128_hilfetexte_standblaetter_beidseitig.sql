-- Migration 128: Hilfetext Druckprofile – die beiden Standblätter werden wie die Broschüren automatisch
-- beidseitig über die kurze Kante gedruckt (Nov 2026, 1.68.53).
--
-- Idempotent: REPLACE ändert nur, solange der alte Satz noch drinsteht (Text aus Migration 127).
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte
SET inhalt_html = REPLACE(inhalt_html,
    'Absendenbuch und Monatsblatt werden automatisch über die kurze Kante gewendet.',
    'Blätter zum Falten (Absendenbuch, Monatsblatt und die beiden Standblätter) werden automatisch über die kurze Kante gewendet, solange in der Zeile nichts anderes gewählt ist.')
WHERE schluessel = 'drucksteuerung.profile';
