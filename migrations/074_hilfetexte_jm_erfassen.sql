-- Migration 074: Hilfetexte «Erfassung Jahresmeisterschaft» nach dem Umbau (Okt 2026):
-- Kopf-Card mit Kennzahlen, Anlass-Karten mit «gemeldet», kein allgemeiner Speichern-Knopf mehr
-- (er bestätigte ungeprüft alle Mitglied-Meldungen), Panel mit Rückfrage und Pfeiltasten.
-- Ersetzt die Texte aus 068/070/071 für diese Schlüssel.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('jmresultate.uebersicht',
 'Jahresmeisterschaft erfassen',
 '<p>Hier werden die Resultate der Jahresmeisterschaft erfasst, Anlass für Anlass. Oben stehen das Jahr, wie viele Resultate erfasst sind und wie viele Meldungen von Mitgliedern noch auf die Bestätigung warten.</p><ul><li><strong>Anlässe</strong>: Jede Karte steht für einen Anlass des gewählten Jahres und zeigt die Anzahl erfasster Resultate. «gemeldet» zählt Resultate, die Mitglieder im Portal selbst eingetragen haben und die noch nicht bestätigt sind. Ein Klick auf die Karte öffnet rechts das Erfassungs-Panel.</li><li><strong>Jahr</strong>: Die Auswahl enthält alle Jahre mit Daten; frühere Jahre bleiben vollständig erhalten. Das gewählte Jahr gilt auch auf den anderen Seiten (für einige Stunden). Ist nicht das laufende Jahr gewählt, steht daneben «Archiv JAHR» mit einem Knopf zurück zum laufenden Jahr.</li><li><strong>Endstich</strong> und <strong>Bester Kantonalstich</strong> haben keine Karte: ihre Werte kommen automatisch aus dem Endschiessen bzw. dem Kantonalstich. Info-Einträge und Anlässe des erweiterten Programms erscheinen ebenfalls nicht.</li><li><strong>PDF importieren</strong> liest eine fremde Einzelrangliste (z.B. Vereinsstich) oder die FSA-Teilnehmerliste für Obligatorisch und Feldschiessen ein. Vereinsmitglieder werden automatisch erkannt, die Vorschau lässt sich korrigieren; bereits erfasste Zeilen sind gelb markiert und abgewählt. Die Ränge 1 bis 10 werden zusätzlich als Einzelrangierung gespeichert, eine erkannte Vereinszeile als Sektionsrangierung.</li><li><strong>Veröffentlichen</strong> erscheint, sobald gespeicherte Änderungen des gewählten Jahres noch nicht für das Portal freigegeben sind (Zähler = offene Einträge), und macht sie dort sichtbar.</li><li><strong>Rangliste</strong> wechselt zur Ranglisten-Seite; unten auf dieser Seite steht die Rangliste Kat. A und Kat. B als Kontrolle.</li></ul><p>Gespeichert wird immer im Erfassungs-Panel eines Anlasses; einen allgemeinen Speichern-Knopf gibt es nicht mehr. Einzelne Resultate korrigiert oder entfernt man dort (Feld leeren und speichern).</p>',
 'jmresultate'),

('jmresultate.erfassung',
 'Erfassungs-Panel eines Anlasses',
 '<p>Im Kopf stehen Anlass, Maximalpunkte und, falls zutreffend, «2 Runden» (Sektionsmeisterschaft) und «Streicher». Darunter alle aktiven Mitglieder, alphabetisch in zwei Gruppen: <strong>Mit JM-Resultat</strong> (hat in diesem Jahr irgendwo ein gewertetes Resultat; Obligatorisch, Feldschiessen, Sektionsmeisterschaft und Cup zählen dafür nicht) und <strong>Noch ohne JM-Resultat</strong>. Ausgefüllte Felder sind weiss, leere grau; oben rechts steht, wie viele Mitglieder in diesem Anlass ein Resultat haben.</p><ul><li>Punkte als ganze Zahl eingeben. <strong>Enter</strong> springt zum nächsten Feld, im letzten Feld speichert Enter. Die Pfeiltasten <strong>auf</strong> und <strong>ab</strong> wechseln die Zeile. <strong>Ctrl+S</strong> speichert jederzeit. Das Suchfeld filtert die Liste.</li><li>Ein geleertes Feld löscht das Resultat beim Speichern.</li><li>Werte über den Maximalpunkten oder keine gültige Zahl werden rot umrandet; der Hinweis erscheint beim Zeigen oder Fokussieren des Feldes. Gespeichert wird erst, wenn alle Felder stimmen.</li><li><strong>Sektionsmeisterschaft</strong> hat zwei Felder R1 und R2; in der Rangliste zählt nur der höhere Wert.</li><li>Gelb hinterlegte Felder mit «gemeldet» stammen aus der Selbsteingabe im Portal. <strong>Speichern</strong> bestätigt alle Resultate dieses Anlasses, also auch diese Meldungen; sie deshalb vor dem Speichern prüfen.</li></ul><p>Escape, Abbrechen oder ein Klick neben das Panel schliessen es; bei ungespeicherten Eingaben fragt die Seite nach. Schlägt das Speichern fehl, bleiben die Eingaben im Panel stehen.</p>',
 'jmresultate'),

('jmresultate.rangliste',
 'Kontroll-Rangliste Kat. A und Kat. B',
 '<p>Dieselbe Darstellung wie auf der Seite <a href="jmrang.php">Jahresmeisterschaft Ranglisten</a>, unter der Erfassung als Kontrolle. Sie wird nach jedem Speichern eines Anlasses neu geladen.</p><ul><li>Die Kategorie richtet sich nach der Waffe des Mitglieds. Als Spalten stehen die bis zu sechs zuletzt durchgeführten Anlässe (neueste rechts); Obligatorisch und Feldschiessen erscheinen nur in der Aufschlüsselung.</li><li>Ein Klick auf eine Zeile klappt die Aufschlüsselung auf (Pflicht- und Streichresultate mit Zwischensummen).</li><li>«–» heisst: Anlass durchgeführt, Resultat noch nicht erfasst. Rot durchgestrichene Werte sind Streicher und zählen nicht.</li><li><strong>Total</strong> und <strong>Rang</strong> werden erst nach dem Endstich berechnet, vorher steht «offen».</li></ul><p>Details zu Streichern, Hochrechnung und Aufschlüsselung stehen in der Hilfe der Ranglisten-Seite.</p>',
 'jmresultate')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
