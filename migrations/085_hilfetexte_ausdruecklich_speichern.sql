-- Migration 085: Hilfetexte zum ausdrücklichen Speichern (Mitglieder, Navigation) und zur Startseite
-- (Aufgaben offen). Entscheid 08.10.2026: Panels speichern nicht mehr still beim Schliessen.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('mitgliederverwaltung.uebersicht',
 'Mitglieder verwalten',
 '<p>Die Stammliste aller Vereinsmitglieder mit Adresse, Kontakt, Sportgerät und Status. Ein Klick auf eine Zeile öffnet das Mitglied rechts zum Bearbeiten; <strong>gespeichert wird mit «Speichern»</strong> im Panel oder Ctrl+S; schliesst du das Panel mit offenen Änderungen, fragt die Seite: Speichern, Verwerfen oder Zurück. <strong>Enter</strong> springt ins nächste Feld. Die Zeile leuchtet kurz grün, wenn das Speichern geklappt hat.</p><ul><li><strong>Hinzufügen</strong>: Lizenznummer, Name, Vorname, Geburtsdatum und Waffe sind Pflicht. Die Lizenznummer muss frei sein.</li><li><strong>Import</strong>: CSV mit Semikolon im Format des CSV-Exports (Kopfzeile nötig). Bestehende Lizenznummern werden aktualisiert, neue angelegt; fehlerhafte Zeilen werden übersprungen und nach dem Import aufgelistet.</li><li><strong>CSV</strong> ist der vollständige Export (auch als Vorlage für den Import), <strong>Adressliste</strong> eine Excel-Datei ohne verstorbene Mitglieder.</li></ul><p>Die Mitgliederdaten werden überall weiterverwendet: in Ranglisten und Resultaterfassung, in der Einsatzplanung, für die Zuordnung von Portal-Logins (<a href="benutzerverwaltung.php">Benutzerverwaltung</a>) und in Exporten. <strong>Löschen</strong> geht nur, solange am Mitglied nichts hängt (Resultate, Einsätze, Benutzerkonto und Ähnliches); vorher wird die Datenbank gesichert. Sonst zeigt der Dialog, was dranhängt, und bietet «Auf inaktiv setzen» an: Das Mitglied fällt aus den aktuellen Listen, seine Resultate bleiben in früheren Ranglisten.</p>',
 'mitgliederverwaltung'),

('nav_admin.uebersicht',
 'Navigation verwalten',
 '<p>Hier wird das Hauptmenü des Admin-Bereichs gepflegt, das oben als Leiste oder links als Seitenleiste erscheint. Einträge der Hauptebene sind die Menütitel, ihre Unterpunkte die aufklappbaren Einträge; bis zu drei Ebenen sind möglich.</p><ul><li><strong>Neuer Eintrag</strong> legt Titel, Link, Icon und übergeordneten Punkt an. Ein Klick auf eine Zeile öffnet sie rechts zum Bearbeiten; gespeichert wird mit «Speichern» im Panel oder Ctrl+S; schliesst du es mit offenen Eingaben, fragt die Seite nach.</li><li>Reihenfolge und Zuordnung ändern sich per Ziehen am Griff oder mit den Pfeilen «Ebene höher / tiefer». Solche Verschiebungen werden gesammelt (gelbe Marke «Ungespeicherte Änderungen») und erst mit <strong>Alles speichern</strong> übernommen; <strong>Aktualisieren</strong> lädt nach Rückfrage den gespeicherten Stand neu und verwirft sie.</li><li><strong>Duplizieren</strong> kopiert einen Eintrag, <strong>Löschen</strong> geht nur bei Einträgen ohne Unterpunkte.</li></ul><p>Änderungen wirken sofort im Menü aller Benutzer. Der Seitentitel jeder Seite soll dem Menütext entsprechen.</p>',
 'nav_admin'),

('nav_admin.ebene',
 'Ebene verschieben',
 '<p><strong>Höher</strong> macht den Eintrag zum Nachbarn seines bisherigen Elternpunkts, <strong>Tiefer</strong> ordnet ihn dem darüberstehenden Eintrag als Unterpunkt zu. Unterpunkte wandern mit. Möglich sind Ebene 0 (Hauptmenü) bis Ebene 3.</p><p>Wie das Ziehen ist dies eine Strukturänderung: sie wird erst mit <strong>Alles speichern</strong> in der Werkzeugleiste übernommen, die Felder im Panel dagegen mit «Speichern» im Panel.</p>',
 'nav_admin'),

('home.uebersicht',
 'Startseite',
 '<p>Die Startseite fasst zusammen, was gerade anliegt, und führt zu den Bereichen, die in der laufenden Saisonphase gebraucht werden.</p><ul><li><strong>Das wartet auf dich</strong> (offen, sobald es etwas zu tun gibt): offene Punkte quer durch die Anwendung, etwa noch nicht erfasste Anlässe, abgelaufene Umfragen, Einsatzpläne kurz vor dem Termin mit offenen Positionen oder noch nicht freigegeben, offene Einsatztausche, wartende Portal-Anmeldungen und Fotos zur Freigabe; für Administratoren zusätzlich ausstehende Datenbank-Aktualisierungen. Ist nichts offen, steht eine grüne Zeile.</li><li><strong>Nächste Termine</strong> aus Jahresprogramm und wichtigen Terminen, <strong>Vereinsjubiläen</strong> des Jahres (Vielfache von 5 Jahren) und <strong>Geburtstage</strong> der nächsten 90 Tage, runde hervorgehoben.</li><li>Darunter die Kacheln zu den Bereichen in zwei Zonen, siehe Hilfe bei «Jetzt aktuell».</li></ul>',
 'home')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
