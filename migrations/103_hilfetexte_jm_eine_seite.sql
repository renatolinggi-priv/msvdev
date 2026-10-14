-- Migration 103: Jahresmeisterschaft auf einer Seite (Okt 2026, 1.68.29).
-- «Jahresmeisterschaft erfassen» und «Jahresmeisterschaft Rangliste» sind eine Seite mit zwei Ansichten
-- (jmresultate.php, jmrang.php leitet weiter). Spaltenköpfe der Rangliste öffnen die Erfassung.
-- Neue Regel bei Gleichstand: vorne, wer im Endstich mehr hat (Entscheid 14.10.2026).
-- Ersetzt die Texte aus 074 für diese Schlüssel; jmrang.uebersicht nur den Satz zum Gleichstand.
--
-- Idempotent: ON DUPLICATE KEY UPDATE bzw. REPLACE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('jmresultate.uebersicht',
 'Jahresmeisterschaft',
 '<p>Erfassen und Rangliste der Jahresmeisterschaft sind eine Seite. Der Schalter neben dem Jahr wechselt die Ansicht; beide Menüeinträge führen hierher.</p><ul><li><strong>Erfassen</strong> zeigt oben die Anlässe des gewählten Jahres mit der Anzahl erfasster Resultate. «gemeldet» zählt Resultate, die Mitglieder im Portal selbst eingetragen haben und die noch nicht bestätigt sind. Ein Klick auf die Karte öffnet rechts das Erfassungs-Panel. Darunter steht die Rangliste.</li><li><strong>Rangliste</strong> zeigt nur die Rangliste Kat. A und Kat. B und oben die Ausgaben: <strong>Rangliste nach Rang</strong> und <strong>Rangliste nach Name</strong> als PDF, je mit Drucker für den Direktdruck.</li><li>In beiden Ansichten öffnet ein Klick auf einen Anlass im Spaltenkopf der Rangliste dasselbe Erfassungs-Panel.</li><li><strong>Jahr</strong>: Die Auswahl enthält alle Jahre mit Daten; frühere Jahre bleiben vollständig erhalten. Das gewählte Jahr gilt auch auf den anderen Seiten (für einige Stunden). Ist nicht das laufende Jahr gewählt, steht daneben «Archiv JAHR» mit einem Knopf zurück zum laufenden Jahr.</li><li><strong>Endstich</strong> und <strong>Bester Kantonalstich</strong> haben keine Karte: ihre Werte kommen automatisch aus dem Endschiessen bzw. dem Kantonalstich. Im Panel stehen sie nur zur Ansicht.</li><li><strong>PDF importieren</strong> liest eine fremde Einzelrangliste (z.B. Vereinsstich) oder die FSA-Teilnehmerliste für Obligatorisch und Feldschiessen ein. Vereinsmitglieder werden automatisch erkannt, die Vorschau lässt sich korrigieren; bereits erfasste Zeilen sind gelb markiert und abgewählt.</li><li><strong>Veröffentlichen</strong> erscheint, sobald gespeicherte Änderungen des gewählten Jahres noch nicht für das Portal freigegeben sind (Zähler = offene Einträge), und macht sie dort sichtbar.</li></ul><p>Gespeichert wird immer im Erfassungs-Panel eines Anlasses. Einzelne Resultate korrigiert oder entfernt man dort (Feld leeren und speichern).</p>',
 'jmresultate'),
('jmresultate.rangliste',
 'Rangliste Kat. A und Kat. B',
 '<p>Die Rangliste rechnet nach jedem Speichern neu. Die Kategorie richtet sich nach der Waffe des Mitglieds.</p><ul><li>Als Spalten stehen die bis zu sechs zuletzt durchgeführten Anlässe (neueste rechts); Obligatorisch und Feldschiessen erscheinen nur in der Aufschlüsselung. Ein Klick auf den Anlass im Spaltenkopf öffnet seine Erfassung.</li><li>Ein Klick auf eine Zeile klappt die Aufschlüsselung auf (Pflicht- und Streichresultate mit Zwischensummen).</li><li>«–» heisst: Anlass durchgeführt, Resultat noch nicht erfasst. Durchgestrichene Werte sind Streicher und zählen nicht.</li><li>Resultate von Anlässen unter 100 Punkten werden auf 100 hochgerechnet (ausser Einzelwettschiessen, Obligatorisch, Feldschiessen).</li><li><strong>Total</strong> und <strong>Rang</strong> werden erst nach dem Endstich berechnet, vorher steht «offen».</li><li><strong>Gleichstand</strong>: Bei gleichem Total steht vorne, wer im Endstich mehr hat. Erst bei gleichem Total und gleichem Endstich teilen sich zwei denselben Rang. Dieselbe Regel gilt im PDF und im Absendenbuch.</li></ul><p>Unten steht die Gruppe <strong>Ohne gewertetes JM-Resultat</strong>: Mitglieder ohne Resultat ausser Obligatorisch, Feldschiessen, Sektionsmeisterschaft oder Cup, alphabetisch und ohne Rang.</p>',
 'jmresultate')
ON DUPLICATE KEY UPDATE titel = VALUES(titel), inhalt_html = VALUES(inhalt_html), kategorie = VALUES(kategorie);

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'Punktgleiche erhalten denselben Rang.',
  'Bei gleichem Total steht vorne, wer im Endstich mehr hat; erst bei gleichem Endstich teilen sich zwei denselben Rang.')
WHERE schluessel = 'jmrang.uebersicht';
