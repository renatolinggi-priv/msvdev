-- Migration 083: Hilfetexte «Absenden vorbereiten» auf endschrang.php (Okt 2026): Prüfliste
-- (Stiche, Partnerinnen, Wanderpreise) und die drei Dokumente fürs Absenden.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('endschrang.absenden',
 'Absenden vorbereiten',
 '<p>Die Karte zeigt am Abend vor dem Absenden auf einen Blick, ob alles bereit ist, und hält die drei Dokumente fürs Absenden bereit.</p><ul><li><strong>Stiche</strong>: wie viele Mitglieder noch gelöste Stiche ohne Resultat haben, nach derselben Regel wie in der Erfassung; der Link führt direkt dorthin.</li><li><strong>Partnerinnen</strong>: Partnerinnen, für die noch kein Resultat erfasst ist.</li><li><strong>Wanderpreise</strong>: wie viele der Wanderpreise im Umlauf (angeschafft und nicht schon in einem früheren Jahr definitiv gewonnen) für das gewählte Jahr einen Gewinner haben.</li><li><strong>Absendenbuch</strong> (Word), <strong>Broschüre</strong> (PDF zum Falten) und <strong>Gesamtrangliste</strong> (PDF); der Drucker daneben druckt direkt über die Drucksteuerung.</li></ul><p>Rechts oben steht der Stand: «bereit fürs Absenden» oder wie viele Punkte noch offen sind. Die übrigen Ranglisten und Listen stehen darunter.</p>',
 'endschrang'),

('endschrang.dokumente',
 'Dokumente erstellen',
 '<p>Gesamtrangliste, Absendenbuch und Broschüre stehen oben in der Karte «Absenden vorbereiten», alle übrigen hier.</p><ul><li><strong>Gesamt</strong>: Gesamtrangliste Kat. A und B mit allen Stichen. <strong>Zwischen</strong>: dieselbe Liste ohne Zabig, für den Stand vor dem letzten Stich.</li><li><strong>Anmeldung</strong>: Liste der Absenden-Anmeldungen (Mitglieder, Jungschützen, Gäste) sowie die Abrechnung von Schwini-Passen, Differenzler, Sie und Er und Partner-Paketen aus den gelösten Stichen und den Spezialpreisen.</li><li><strong>Absendenbuch</strong>: das Buch aus der Word-Vorlage; <strong>Broschüre</strong> legt dessen A5-Seiten paarweise auf A4 quer, so dass gefaltet ein Heft entsteht.</li><li><strong>Einzelwettbewerbe</strong>: Endstich (Mitglieder, Jungschützen und Partnerinnen, mit Königskranz), Schwini, Kunst, Glück, Zabig, Differenzler.</li><li><strong>Partner</strong>: Endstich plus beste Schwini-Passe der Partnerinnen. <strong>Sie &amp; Er</strong>: Summe der eindeutigen Werte aus den Schüssen von Partnerin und Mitglied.</li></ul>',
 'endschrang')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
