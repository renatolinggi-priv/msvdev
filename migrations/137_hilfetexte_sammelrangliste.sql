-- Migration 137: Hilfetext «Dokumente» der Endschiessen-Rangliste – neuer Knopf «Sammelrangliste» (Nov 2026, 1.68.62).
-- Alle Ranglisten zum Vorlesen am Absenden in einem PDF (A4, kein Heft), in der Reihenfolge des Absendens.
--
-- Idempotent: NOT LIKE auf den neuen Absatz.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte SET inhalt_html = CONCAT(inhalt_html,
  '<p><strong>Sammelrangliste</strong>: alle Ranglisten zum Vorlesen am Absenden in einem PDF, A4 und nicht als Heft. Reihenfolge: Sektions- und Einzelrangierungen, Endstich, Schwini, Kunst, Glück, Zwischenrangliste, Differenzler, Zabig, Endschiessen gesamt, Heimmeisterschaft, Kantonalstich, Cup, Jahresmeisterschaft. Jede Rangliste ist dieselbe wie beim einzelnen Knopf; Ranglisten ohne Resultate fallen weg, die Meldung nennt sie. Das Erstellen dauert etwa eine halbe Minute, der Knopf zählt mit.</p>')
WHERE schluessel = 'endschrang.dokumente' AND inhalt_html NOT LIKE '%Sammelrangliste%';
