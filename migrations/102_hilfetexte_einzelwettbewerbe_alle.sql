-- Migration 102: Hilfetext «Dokumente» der Endschiessen-Rangliste – neuer Knopf «Alle Einzelwettbewerbe»
-- (1.68.26, Okt 2026): Endstich, Schwini, Kunst, Glück, Zabig und Differenzler in einem PDF.
--
-- Idempotent: REPLACE greift nur, solange der neue Satz noch nicht im Text steht.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Einzelwettbewerbe</strong>: Endstich und Schwini',
  '<li><strong>Einzelwettbewerbe</strong>: <strong>Alle Einzelwettbewerbe</strong> fasst die sechs Ranglisten in einem PDF zusammen, jede auf einer eigenen Seite (Ranglisten ohne Resultate fallen weg). Einzeln: Endstich und Schwini')
WHERE schluessel = 'endschrang.dokumente'
  AND inhalt_html NOT LIKE '%Alle Einzelwettbewerbe%';
