-- Migration 090: Hilfetexte der Wanderpreise-Seite nach dem Umbau (Okt 2026): Jahresauswahl,
-- Stand je Preis (offen, vergeben, nicht im Umlauf), «Sieg x von y», Filter und Suche,
-- Knöpfe Auto-Zuordnung / Zuordnen / Dokumente / Weitere.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('wanderpreise.uebersicht',
 'Wanderpreise',
 '<p>Die Seite zeigt den <strong>Stand der Wanderpreise für das gewählte Jahr</strong> (Auswahl neben dem Titel; das Jahr gilt auch auf den anderen Seiten). Oben steht, wie viele der Preise im Umlauf schon einen Gewinner haben. Offene Preise stehen zuoberst und sind hell hinterlegt.</p><ul><li><strong>Offen</strong>: im Umlauf, aber für das Jahr noch ohne Gewinner.</li><li><strong>Vergeben</strong>: Gewinner des Jahres, gegebenenfalls mit Rang, und der Stand «Sieg 2 von 10»: wie oft dieses Mitglied den Preis gewonnen hat und ab wie vielen Siegen er definitiv in seinen Besitz übergeht.</li><li><strong>Nicht im Umlauf</strong>: erst später angeschafft oder in einem früheren Jahr definitiv gewonnen.</li><li><strong>Zuletzt</strong>: der Gewinner des letzten Jahres vor dem gewählten.</li></ul><p>Filter und Suche im Tabellenkopf grenzen die Liste ein. Ein Klick auf den Namen oder die Zeile öffnet die <strong>Historie</strong> des Preises mit allen Gewinnern nach Jahr; von dort lässt sich die Historie als PDF ausgeben. Der Bleistift bearbeitet den Preis, der Papierkorb löscht ihn, solange keine Gewinner erfasst sind.</p><p>Pro Preis und Jahr gibt es höchstens einen Gewinner. «Im Umlauf» zählt gleich wie die Karte «Absenden vorbereiten» auf der <a href="endschrang.php">Endschiessen Rangliste</a>.</p>',
 'wanderpreise'),

('wanderpreise.aktionen',
 'Aktionen: Zuordnen, Dokumente, Weitere',
 '<p><strong>Auto-Zuordnung</strong> und <strong>Zuordnen</strong> arbeiten mit dem oben gewählten Jahr.</p><ul><li><strong>Auto-Zuordnung</strong> ermittelt für das gewählte Jahr die Gewinner aller Preise, bei denen die Auto-Zuordnung mit einer Regel aktiviert ist. Preise, die in diesem Jahr schon einen Gewinner haben, werden übersprungen; das Ergebnis erscheint als Liste je Preis (zugeordnet, übersprungen, keine Daten, Fehler).</li><li><strong>Zuordnen</strong> trägt den Gewinner eines Jahres von Hand ein, mit Rang/Resultat und Bemerkung; das Jahr ist mit dem gewählten vorbelegt. Wählbar sind aktive Mitglieder; das Jahr darf für diesen Preis noch keinen Gewinner haben. Unten im Fenster stehen die bisherigen Gewinner des Preises.</li><li><strong>Dokumente</strong> enthält <strong>Listen &amp; Berichte</strong> und die <strong>Gravur-Aufträge</strong>. Jeder Bericht fragt ein Jahr ab, vorbelegt mit dem gewählten: CSV (Tabellenexport), PDF Alle (Jahresbericht über alle Preise), JM Preise (Bericht der drei besten Schützen), Mitglieder (kompakte Mitglieder-Information). Die Gravur-Aufträge Schnitzerei und Akura erzeugen den Jahresbericht gefiltert nach Hersteller; der Hersteller steht pro Preis im Feld «Hersteller».</li><li><strong>Weitere</strong>: <strong>Wanderpreis anlegen</strong> (Bezeichnung, Beschreibung, Anschaffungsjahr, Min. Gewinne, Hersteller, optional Auto-Zuordnung), <strong>Frühere Gewinner nachtragen</strong> (z.B. aus der Zeit vor der Erfassung im System) und der Weg zu den <a href="wanderpreise_regeln.php">Wanderpreis-Regeln</a>.</li></ul>',
 'wanderpreise')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
