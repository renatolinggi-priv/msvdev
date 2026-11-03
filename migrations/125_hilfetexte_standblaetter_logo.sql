-- Migration 125: Hilfetext PDF-Vorlage, seit beide Standblätter direkt als PDF entstehen (Nov 2026, 1.68.50).
-- Die Standblätter übernehmen aus der PDF-Vorlage nur noch das Logo; ihr Layout ist fest.
--
-- Idempotent: REPLACE ändert nur, solange der alte Satz noch drinsteht.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte
SET inhalt_html = REPLACE(inhalt_html,
    'Das Standblatt hat ein eigenes, festes Layout und hängt nicht an dieser Seite.',
    'Die Standblätter (Jahresmeisterschaft und Endschiessen) übernehmen nur das Logo; ihr Layout ist fest, weil die Klebefelder auf die Standblätter der Anlage abgestimmt sind.')
WHERE schluessel = 'pdf_design.uebersicht';
