-- Migration 106: Endschiessen auf einer Seite (Okt 2026, 1.68.29).
-- «Endschiessen Resultate erfassen» und «Endschiessen Rangliste» sind eine Seite (endresultate.php) mit zwei
-- Ansichten derselben Tabelle; endschrang.php leitet auf die Ansicht «Rangliste» weiter. Rang und Total stehen
-- in beiden Ansichten, die Werte bleiben Rohwerte (Entscheid 14.10.2026).
-- Ergänzt den bestehenden Text um einen Absatz (nur wenn er noch fehlt), damit Anpassungen im Editor bleiben.
--
-- Idempotent: Bedingung NOT LIKE auf den neuen Absatz.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte SET inhalt_html = CONCAT(inhalt_html,
  '<p><strong>Erfassen | Rangliste</strong>: Der Schalter neben dem Jahr wechselt die Ansicht derselben Tabelle; beide Menüeinträge führen hierher.</p><ul><li><strong>Erfassen</strong> sortiert nach Name, zeigt den Stand und filtert nach offen oder vollständig.</li><li><strong>Rangliste</strong> sortiert nach Kat. A, Kat. B und Rang, darunter «Ohne Wertung» mit dem Grund (z.B. Endstich noch nicht erfasst, Gast ohne alle fünf Stiche, Jungschütze). Daneben stehen die Prüfliste «Absenden vorbereiten» und alle Dokumente.</li><li>In beiden Ansichten steht vor dem Namen der Rang in der Kategorie (z.B. «A 3.») und rechts das Total, gleich gerechnet wie die Gesamtrangliste im PDF. Die Spalten zeigen die erfassten Werte.</li><li>Ein Klick auf eine Zeile öffnet die Erfassung, auch in der Rangliste; nach dem Speichern ordnet sich die Liste neu.</li></ul>')
WHERE schluessel = 'endresultate.uebersicht' AND inhalt_html NOT LIKE '%Erfassen | Rangliste%';
