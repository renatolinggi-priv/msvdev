-- Migration 081: Hilfetexte zum Löschen nur ohne Geschichte (Okt 2026): Mitglieder mit Resultaten,
-- Einsätzen oder Konto werden nicht gelöscht, sondern auf inaktiv gesetzt; JM-Anlässe mit Daten bleiben,
-- ohne Daten wird vor dem Löschen gesichert.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('mitgliederverwaltung.uebersicht',
 'Mitglieder verwalten',
 '<p>Die Stammliste aller Vereinsmitglieder mit Adresse, Kontakt, Sportgerät und Status. Ein Klick auf eine Zeile öffnet das Mitglied rechts zum Bearbeiten; <strong>gespeichert wird automatisch beim Schliessen</strong> des Panels (Escape) und beim Wechsel zu einem anderen Mitglied, mit Ctrl+S sofort; <strong>Enter</strong> springt ins nächste Feld. Die Zeile leuchtet kurz grün, wenn das Speichern geklappt hat.</p><ul><li><strong>Hinzufügen</strong>: Lizenznummer, Name, Vorname, Geburtsdatum und Waffe sind Pflicht. Die Lizenznummer muss frei sein.</li><li><strong>Import</strong>: CSV mit Semikolon im Format des CSV-Exports (Kopfzeile nötig). Bestehende Lizenznummern werden aktualisiert, neue angelegt; fehlerhafte Zeilen werden übersprungen und nach dem Import aufgelistet.</li><li><strong>CSV</strong> ist der vollständige Export (auch als Vorlage für den Import), <strong>Adressliste</strong> eine Excel-Datei ohne verstorbene Mitglieder.</li></ul><p>Die Mitgliederdaten werden überall weiterverwendet: in Ranglisten und Resultaterfassung, in der Einsatzplanung, für die Zuordnung von Portal-Logins (<a href="benutzerverwaltung.php">Benutzerverwaltung</a>) und in Exporten. <strong>Löschen</strong> geht nur, solange am Mitglied nichts hängt (Resultate, Einsätze, Benutzerkonto und Ähnliches); vorher wird die Datenbank gesichert. Sonst zeigt der Dialog, was dranhängt, und bietet «Auf inaktiv setzen» an: Das Mitglied fällt aus den aktuellen Listen, seine Resultate bleiben in früheren Ranglisten.</p>',
 'mitgliederverwaltung'),

('jmdefinition.uebersicht',
 'Jahresmeisterschaft definieren',
 '<p>Hier wird das <strong>Jahresprogramm</strong> eines Jahres gepflegt: alle Anlässe mit Adresse, Schiesstagen, Maximalpunkten und den Optionen, die bestimmen, wie ein Anlass in der Jahresmeisterschaft zählt. Die Reihenfolge lässt sich per Griff links ziehen oder mit «Nach Datum sortieren» setzen.</p><ul><li><strong>Neuer Anlass</strong> legt einen Eintrag an, <strong>Vom Vorjahr übernehmen</strong> kopiert das ganze Programm eines früheren Jahres als Ausgangslage.</li><li><strong>Infotext zur JM</strong> erscheint im Jahresprogramm-PDF; der Platzhalter <code>{anzahl_streicher}</code> wird durch die eingestellte Zahl ersetzt.</li><li><strong>Anzahl Streicher</strong> legt fest, wie viele der schlechtesten Streicher-Anlässe pro Schütze nicht zählen.</li><li><strong>Jahresprogramm</strong> als PDF (fertig oder als Entwurf), Fragebogen als Word und alle Schiesstage als Kalenderdatei (ICS).</li><li><strong>Löschen</strong> geht nur bei einem Anlass ohne Daten (keine Resultate, importierten Ranglisten, Fragebogen-Antworten oder Fotos); Schiesstage, Gruppen und Kranzlimiten gehen mit, vorher wird die Datenbank gesichert. Hängen Daten dran, zeigt der Dialog, welche.</li></ul><p>Änderungen an Bezeichnung, Punkten und Optionen wirken sofort auf die Ranglisten des Jahres.</p>',
 'jmdefinition')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
