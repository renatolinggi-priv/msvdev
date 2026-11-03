-- Migration 126: Hilfetext Druckprofile, neue Gruppe «Verein» mit Monatsblatt und Fragebogen (Nov 2026, 1.68.51).
-- Das Monatsblatt ist eine A5-Broschüre wie das Absendenbuch und wird ohne Duplex im Profil über die kurze Seite gewendet.
--
-- Idempotent: REPLACE ändert nur, solange der alte Text noch drinsteht.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte
SET inhalt_html = REPLACE(inhalt_html,
    'Jahresmeisterschaft, Endschiessen, Ranglisten und Einsätze',
    'Jahresmeisterschaft, Endschiessen, Ranglisten, Einsätze und Verein (Monatsblatt, Fragebogen)')
WHERE schluessel = 'drucksteuerung.profile';

UPDATE hilfetexte
SET inhalt_html = REPLACE(inhalt_html,
    'Das Absendenbuch wird als Broschüre über die kurze Seite gewendet.',
    'Absendenbuch und Monatsblatt sind Broschüren und werden über die kurze Seite gewendet, wenn im Profil kein Duplex steht.')
WHERE schluessel = 'drucksteuerung.profile';
