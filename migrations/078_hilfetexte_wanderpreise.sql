-- Migration 078: Hilfetexte Wanderpreise nach dem Umbau (Okt 2026): Verwaltungsknöpfe in der Kopf-Karte,
-- Listen, Berichte und Gravur-Aufträge im Menü «Dokumente» statt im Bereich «Aktionen».
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('wanderpreise.aktionen',
 'Aktionen: Verwaltung, Berichte, Gravur',
 '<p><strong>Verwaltung</strong></p><ul><li><strong>Hinzufügen</strong> legt einen neuen Wanderpreis an (Bezeichnung, Beschreibung, Beschaffungsjahr, Min. Gewinne, Hersteller, optional Auto-Zuordnung).</li><li><strong>Zuordnen</strong> trägt den Gewinner eines Jahres von Hand ein, mit Rang/Resultat und Bemerkung. Wählbar sind aktive Mitglieder; das Jahr darf für diesen Preis noch keinen Gewinner haben. Unten im Fenster stehen die bisherigen Gewinner des gewählten Preises.</li><li><strong>Auto-Zuordnung</strong> ermittelt für ein Jahr die Gewinner aller Preise, bei denen die Auto-Zuordnung mit einer Regel aktiviert ist. Preise, die in diesem Jahr schon einen Gewinner haben, werden übersprungen; das Ergebnis wird als Liste je Preis angezeigt (zugeordnet, übersprungen, keine Daten, Fehler).</li><li><strong>Historie</strong> trägt vergangene Gewinner nach, z.B. aus der Zeit vor der Erfassung im System.</li></ul><p>Hinzufügen, Zuordnen, Auto-Zuordnung und Historie stehen oben in der Kopf-Karte. Das Menü <strong>Dokumente</strong> daneben enthält <strong>Listen &amp; Berichte</strong> und die Gravur-Aufträge; jeder Bericht fragt ein Jahr ab: <strong>CSV</strong> (Tabellenexport), <strong>PDF Alle</strong> (Jahresbericht über alle Preise), <strong>JM Preise</strong> (Bericht der drei besten Schützen), <strong>Mitglieder</strong> (kompakte Mitglieder-Information).</p><p><strong>Gravur-Aufträge</strong> erzeugen den Jahresbericht gefiltert nach Hersteller: <strong>Schnitzerei</strong> und <strong>Akura</strong> (eigenes Format als Gravur-Auftrag). Der Hersteller wird pro Preis im Feld «Hersteller» festgelegt.</p>',
 'wanderpreise'),

('wanderpreise.uebersicht',
 'Wanderpreise verwalten',
 '<p>Hier werden die <strong>Wanderpreise</strong> des Vereins geführt: Bezeichnung, Hersteller, aktueller Gewinner, Anzahl bisheriger Gewinner und die Zahl der Gewinne, ab der ein Preis definitiv in den Besitz übergeht («Min. Gewinne»). Ein Klick auf eine Zeile öffnet die <strong>Historie</strong> des Preises mit allen Gewinnern nach Jahr; von dort lässt sich die Historie als PDF ausgeben.</p><ul><li>Pro Preis und Jahr gibt es <strong>höchstens einen Gewinner</strong>. Ein zweiter Eintrag im gleichen Jahr wird abgelehnt.</li><li>Gewinner werden entweder von Hand zugeordnet («Zuordnen») oder automatisch über eine Regel ermittelt («Auto-Zuordnung»). Die Regeln werden unter <a href="wanderpreise_regeln.php">Wanderpreis-Regeln</a> gepflegt.</li><li>Erreicht ein Mitglied die eingestellte Anzahl Gewinne, wird der Gewinn als <strong>definitiver Besitz</strong> markiert (siehe Hilfe beim Feld «Min. Gewinne»).</li><li>Ein Preis kann nur gelöscht werden, solange keine Gewinner zu ihm erfasst sind.</li></ul><p>Die Verwaltungsknöpfe stehen oben in der Kopf-Karte; alle Listen, Berichte und Gravur-Aufträge sind im Menü «Dokumente» daneben zusammengefasst.</p>',
 'wanderpreise')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
