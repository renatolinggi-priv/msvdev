-- Migration 093: Hilfetexte der Seite «Endschiessen Rangliste» nach dem Layout-Umbau (Okt 2026):
-- Prüfliste und Rangliste links, alle 13 Dokumente in der Karte «Dokumente» rechts (Fürs Absenden,
-- Übersicht, Einzelwettbewerbe, Partner). Dazu der Strich für noch nicht erfasste Stiche und
-- «lädt herunter» statt «zeigt den Link darunter».
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('endschrang.uebersicht',
 'Endschiessen Rangliste',
 '<p>Zeigt die Gesamtrangliste des Endschiessens für das gewählte Jahr, getrennt nach <strong>Kategorie A</strong> und <strong>Kategorie B</strong>, und erzeugt alle Endschiessen-Dokumente.</p><ul><li>Links stehen die Prüfliste «Absenden vorbereiten» und die Rangliste, rechts die Karte «Dokumente» mit allen Ranglisten und Listen. Auf schmalen Bildschirmen steht die Karte «Dokumente» zwischen Prüfliste und Rangliste.</li><li>Das Jahr oben wählen; Prüfliste und Tabellen laden neu. <strong>Resultate bearbeiten</strong> wechselt zur Erfassung (<a href="endresultate.php">Endschiessen Resultate erfassen</a>) im gleichen Jahr.</li><li>Jeder Dokument-Knopf lädt das Dokument herunter; der Drucker-Knopf daneben schickt dasselbe Dokument direkt an den Drucker aus dem Druckprofil «Endschiessen Ranglisten» (Hoch- oder Querformat gemäss Profil). Die Broschüre hat ein eigenes Profil.</li><li>Am Bildschirm erscheinen nur Mitglieder mit einem Endstich-Resultat des Jahres. Sortiert wird nach Total, dann Endstich, dann Alter (ältere vor jüngeren); die ersten drei sind hervorgehoben.</li></ul>',
 'endschrang'),

('endschrang.absenden',
 'Absenden vorbereiten',
 '<p>Die Karte zeigt am Abend vor dem Absenden auf einen Blick, ob alles bereit ist. Die drei Dokumente fürs Absenden stehen zuoberst in der Karte «Dokumente».</p><ul><li><strong>Stiche</strong>: wie viele Mitglieder noch gelöste Stiche ohne Resultat haben, nach derselben Regel wie in der Erfassung; der Link führt direkt dorthin.</li><li><strong>Partnerinnen</strong>: Partnerinnen, für die noch kein Resultat erfasst ist.</li><li><strong>Wanderpreise</strong>: wie viele der Wanderpreise im Umlauf (angeschafft und nicht schon in einem früheren Jahr definitiv gewonnen) für das gewählte Jahr einen Gewinner haben.</li></ul><p>Rechts oben steht der Stand mit der Uhrzeit der Prüfung: «bereit fürs Absenden» oder wie viele Punkte noch offen sind; der Pfeil daneben prüft neu. Sind noch Punkte offen, steht das auch bei den Dokumenten fürs Absenden; sie lassen sich trotzdem erstellen und zeigen dann den heutigen Stand.</p>',
 'endschrang'),

('endschrang.dokumente',
 'Dokumente',
 '<p>Alle Dokumente der Seite stehen in dieser Karte, jedes in einer eigenen Zeile. Der Knopf mit dem Namen lädt das Dokument herunter (PDF, das Absendenbuch als Word); der Drucker daneben druckt direkt und erscheint nur, wenn die Drucksteuerung verbunden ist.</p><ul><li><strong>Fürs Absenden</strong>: das <strong>Absendenbuch</strong> aus der Word-Vorlage; die <strong>Broschüre</strong> legt dessen A5-Seiten paarweise auf A4 quer, so dass gefaltet ein Heft entsteht; die <strong>Gesamtrangliste</strong> beider Kategorien.</li><li><strong>Zwischenrangliste</strong>: die Gesamtrangliste ohne Zabig, für den Stand vor dem letzten Stich.</li><li><strong>Anmeldungen</strong>: Liste der Absenden-Anmeldungen (Mitglieder, Jungschützen, Gäste) sowie die Abrechnung von Schwini-Passen, Differenzler, Sie und Er und Partner-Paketen aus den gelösten Stichen und den Spezialpreisen.</li><li><strong>Einzelwettbewerbe</strong>: Endstich (Mitglieder, Jungschützen und Partnerinnen, mit Königskranz), Schwini, Kunst, Glück, Zabig, Differenzler.</li><li><strong>Partner</strong>: Endstich plus beste Schwini-Passe der Partnerinnen. <strong>Sie &amp; Er</strong>: Summe der eindeutigen Werte aus den Schüssen von Partnerin und Mitglied.</li></ul>',
 'endschrang'),

('endschrang.wertung',
 'Spalten und Gesamttotal',
 '<ul><li><strong>Endstich</strong>: Summe der zehn Schüsse.</li><li><strong>Schwini</strong>: «beste Passe (schlechtere Passe)»; ins Total zählt die beste.</li><li><strong>Kunst</strong>: Summe der fünf Hunderterwertungen geteilt durch 10.</li><li><strong>Glück</strong>: bester der drei Schüsse geteilt durch 10.</li><li><strong>Zabig</strong>: die sechs Hunderterwertungen auf die Zehnerskala umgerechnet und summiert (91–100 = 10, 81–90 = 9, … 1–10 = 1).</li><li><strong>Differenzler</strong>: Ansage minus Zabig-Summe in Rohwerten; nur Anzeige, zählt nicht ins Total.</li><li><strong>Total</strong> = Endstich + beste Schwini-Passe + Kunst + Glück + Zabig. Die Zwischenrangliste lässt Zabig weg.</li><li>Ein <strong>Strich</strong> heisst: Dieser Stich ist für das Mitglied noch nicht erfasst. Er zählt im Total als 0, gleich wie in der Gesamtrangliste und im Absendenbuch.</li></ul><p>Sortiert wird nach Total, dann Endstich, dann Alter. Die ersten drei jeder Kategorie erhalten in der Schützenabrechnung den Endschiessen-Preis.</p>',
 'endschrang')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
