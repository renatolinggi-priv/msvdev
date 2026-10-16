-- Migration 112: Hilfetext «Spalten und Gesamttotal» beim Endschiessen an die Tabelle seit 1.68.29 angepasst (Okt 2026, 1.68.30).
-- 1) endschrang.wertung beschrieb umgerechnete Werte (Kunst und Glück geteilt durch 10) und «Strich = noch nicht erfasst».
--    Die Tabelle zeigt seit 1.68.29 die erfassten Rohwerte, «–» heisst dort nicht gelöst, «gelöst» heisst bezahlt ohne
--    Resultat. Das Total rechnet um; die Total-Zelle zeigt die Rechnung jetzt als Tooltip.
-- 2) Links auf die aufgehobene Seite endschrang.php zeigen direkt auf die Ansicht «Rangliste».
--
-- Idempotent: ON DUPLICATE KEY UPDATE (Muster 067), Links per REPLACE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('endschrang.wertung',
 'Spalten und Gesamttotal',
 '<ul><li>Die Spalten zeigen die <strong>erfassten Werte</strong>: Endstich als Summe der zehn Schüsse, Schwini «beste Passe, andere Passe», Kunst als Summe der fünf Hunderterwertungen, Glück «bester Schuss (alle drei)», Zabig in Punkten (Hunderterwertung auf die Zehnerskala: 91–100 = 10, 81–90 = 9, … 1–10 = 1), dazu Sie und Er und die Ansage.</li><li><strong>Total</strong> = Endstich + beste Schwini-Passe + Kunst ÷ 10 + bester Glück-Schuss ÷ 10 + Zabig-Punkte. Mit der Maus über dem Total steht die Rechnung der Person, z.B. «Endstich 92 + Schwini 88 + Kunst 45.0 + Glück 9.6 + Zabig 52». Die Zwischenrangliste lässt Zabig weg.</li><li><strong>Ansage</strong> (Differenzler): nur Anzeige, zählt nicht ins Total.</li><li><strong>–</strong> heisst: Stich nicht gelöst. <strong>gelöst</strong> (gelb) heisst: bezahlt, aber noch ohne Resultat. Bis das Resultat erfasst ist, zählt der Stich im Total als 0, gleich wie in der Gesamtrangliste und im Absendenbuch.</li></ul><p>Sortiert wird nach Total, dann Endstich, dann Alter. Die ersten drei jeder Kategorie erhalten in der Schützenabrechnung den Endschiessen-Preis.</p>',
 'endschrang')
ON DUPLICATE KEY UPDATE titel = VALUES(titel), inhalt_html = VALUES(inhalt_html), kategorie = VALUES(kategorie);

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, '<a href="endschrang.php">', '<a href="endresultate.php?ansicht=rangliste">')
WHERE inhalt_html LIKE '%<a href="endschrang.php">%';
