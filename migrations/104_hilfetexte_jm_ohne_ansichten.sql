-- Migration 104: Jahresmeisterschaft ohne Ansichtsschalter (Okt 2026, 1.68.29).
-- Nach 103 entschieden: Bei der JM bringt eine zweite Ansicht «Rangliste» nichts (dieselbe Tabelle).
-- Eine Seite mit Anlässen, Rangliste und allen Aktionen im Kopf; jmrang.php springt zur Rangliste.
-- Ersetzt den Text aus 103 für jmresultate.uebersicht.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('jmresultate.uebersicht',
 'Jahresmeisterschaft erfassen',
 '<p>Erfassen und Rangliste der Jahresmeisterschaft sind eine Seite. Oben stehen die Anlässe, darunter die Rangliste Kat. A und Kat. B. Der Menüeintrag «Jahresmeisterschaft Rangliste» führt ebenfalls hierher, direkt zur Rangliste.</p><ul><li><strong>Anlässe</strong>: Jede Karte zeigt die Anzahl erfasster Resultate. «gemeldet» zählt Resultate, die Mitglieder im Portal selbst eingetragen haben und die noch nicht bestätigt sind. Ein Klick auf die Karte öffnet rechts das Erfassungs-Panel. Dasselbe Panel öffnet ein Klick auf einen Anlass im Spaltenkopf der Rangliste.</li><li><strong>Jahr</strong>: Die Auswahl enthält alle Jahre mit Daten; frühere Jahre bleiben vollständig erhalten. Das gewählte Jahr gilt auch auf den anderen Seiten (für einige Stunden). Ist nicht das laufende Jahr gewählt, steht daneben «Archiv JAHR» mit einem Knopf zurück zum laufenden Jahr.</li><li><strong>Endstich</strong> und <strong>Bester Kantonalstich</strong> haben keine Karte: ihre Werte kommen automatisch aus dem Endschiessen bzw. dem Kantonalstich. Im Panel stehen sie nur zur Ansicht.</li><li><strong>PDF importieren</strong> liest eine fremde Einzelrangliste (z.B. Vereinsstich) oder die FSA-Teilnehmerliste für Obligatorisch und Feldschiessen ein. Vereinsmitglieder werden automatisch erkannt, die Vorschau lässt sich korrigieren; bereits erfasste Zeilen sind gelb markiert und abgewählt.</li><li><strong>Veröffentlichen</strong> erscheint, sobald gespeicherte Änderungen des gewählten Jahres noch nicht für das Portal freigegeben sind (Zähler = offene Einträge), und macht sie dort sichtbar.</li><li><strong>Rangliste nach Rang</strong> und <strong>Rangliste nach Name</strong> erstellen die Rangliste als PDF; der Drucker daneben druckt sie direkt.</li></ul><p>Gespeichert wird immer im Erfassungs-Panel eines Anlasses. Einzelne Resultate korrigiert oder entfernt man dort (Feld leeren und speichern).</p>',
 'jmresultate')
ON DUPLICATE KEY UPDATE titel = VALUES(titel), inhalt_html = VALUES(inhalt_html), kategorie = VALUES(kategorie);
