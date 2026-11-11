-- Migration 135: Hilfetexte Mitgliederverwaltung nach dem Umbau (Nov 2026, 1.68.57).
-- Mitgliedschaft (Stand, Mitgliedsart, Eintritt, Austritt), Filter und Sortierung, Prüfung am Feld,
-- Import mit Prüflauf. Neu: mitgliederverwaltung.neu und mitgliederverwaltung.import.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('mitgliederverwaltung.uebersicht',
 'Mitglieder verwalten',
 '<p>Die Stammliste aller Vereinsmitglieder. Ein Klick auf eine Zeile öffnet das Mitglied rechts zum Bearbeiten. <strong>Gespeichert wird mit «Speichern»</strong> im Panel oder Ctrl+S; schliesst du das Panel mit offenen Änderungen, fragt die Seite: Speichern, Verwerfen oder Zurück. Mit den Pfeilen unten im Panel (oder Alt+↑ und Alt+↓) gehst du zum vorigen oder nächsten Mitglied der Liste.</p><ul><li><strong>Hinzufügen</strong>: Name, Vorname, Geburtsdatum, Lizenznummer und Waffe braucht es, Adresse und Kontakt lassen sich später ergänzen.</li><li><strong>Import</strong>: CSV-Datei hochladen. Zuerst zeigt die Seite, was sich ändert, erst «Änderungen übernehmen» schreibt, mit Sicherung vorher.</li><li><strong>Mitglieder (CSV)</strong> ist der vollständige Export und zugleich die Vorlage für den Import. <strong>Adressliste (Excel)</strong> enthält alle nicht verstorbenen Mitglieder, auch ausgetretene, mit Notizen.</li></ul><p>Die Mitgliederdaten werden überall weiterverwendet: in Ranglisten und Resultaterfassung, in der Einsatzplanung, für die Zuordnung von Portal-Logins (<a href="benutzerverwaltung.php">Benutzerverwaltung</a>) und in Exporten. <strong>Löschen</strong> geht nur, solange am Mitglied nichts hängt (Resultate, Einsätze, Benutzerkonto und Ähnliches), vorher wird die Datenbank gesichert. Sonst zeigt der Dialog, was dranhängt, und bietet «Als ausgetreten markieren» an: Das Mitglied fällt aus den aktuellen Listen, seine Resultate bleiben in früheren Ranglisten.</p>',
 'mitgliederverwaltung'),

('mitgliederverwaltung.liste',
 'Mitgliederliste',
 '<p>Oben wählst du, wen die Liste zeigt: <strong>Mitglieder</strong> (Standard), <strong>Ausgetreten</strong>, <strong>Verstorben</strong> oder <strong>Alle</strong>. Die Zahl daneben sagt, wie viele es sind. Das Suchfeld findet Lizenznummer, Name, Vorname, Adresse, E-Mail, Telefon und Waffe.</p><ul><li>Ein Klick auf einen Spaltenkopf sortiert, ein zweiter dreht die Reihenfolge um.</li><li>Die Spalte <strong>Mitgliedschaft</strong> zeigt den Stand in Worten: die Mitgliedsart (zum Beispiel Aktivmitglied), «Ausgetreten» mit Jahr oder «Verstorben», dazu «JSK-Leitung», wenn gesetzt. Ausgetretene und Verstorbene stehen grau.</li><li>«Waffe fehlt» heisst: Die Waffe des Mitglieds steht nicht in der Waffenliste. Im Panel eine Waffe wählen und speichern.</li></ul><p>Mit der Tastatur: Tab auf die Tabelle, die Pfeiltasten wählen eine Zeile, Enter öffnet sie im Panel.</p><p>Auf dem Smartphone erscheinen statt der Tabelle Karten mit eigener Suche und einem Bearbeiten-Knopf, der dasselbe Panel öffnet.</p>',
 'mitgliederverwaltung'),

('mitgliederverwaltung.stammdaten',
 'Stammdaten eines Mitglieds',
 '<ul><li><strong>Lizenznr.</strong> ist die eindeutige Kennung des Mitglieds (Lizenznummer des Verbands) und kann nach dem Anlegen nicht mehr geändert werden. Sie ist der Schlüssel für Resultate, Import und die Portal-Registrierung. Bei einem Tippfehler: Hängt noch nichts am Mitglied, löschen und neu anlegen, sonst den Administrator fragen.</li><li><strong>Waffe</strong> ist das Sportgerät aus der Waffenliste. Es steuert die Einteilung in Ranglisten und Abrechnungen.</li><li><strong>E-Mail</strong> wird bei der Portal-Registrierung abgeglichen: Stimmt die eingegebene Adresse mit dieser überein, ist das Konto sofort aktiv, sonst wartet es auf Freischaltung in der <a href="benutzerverwaltung.php">Benutzerverwaltung</a>.</li><li><strong>Telefon und Mobile</strong>: Schweizer Nummern werden beim Verlassen des Feldes ins Format «+41 79 123 45 67» gebracht. Ausländische Nummern mit Vorwahl (zum Beispiel +49 …) bleiben, wie sie sind.</li><li><strong>Kommunikation</strong> (Briefpost, WhatsApp, Beides) erscheint in der Adressliste. <strong>Notizen</strong> sind intern: Sie stehen hier und in der Adressliste (Excel), nicht im Portal.</li></ul><p>Fehlt etwas oder passt ein Format nicht, steht oben im Panel, welches Feld zu prüfen ist. Ein Klick darauf springt hin. Gespeichert wird erst, wenn alles stimmt.</p>',
 'mitgliederverwaltung'),

('mitgliederverwaltung.status',
 'Mitgliedschaft',
 '<ul><li><strong>Stand</strong>: Mitglied, Ausgetreten oder Verstorben. Nur Mitglieder erscheinen in den Auswahllisten der Resultaterfassung und der Jahresmeisterschaft, beim Munitionskauf und bei der Zuordnung von Portal-Logins. Wer austritt, wird hier auf <em>Ausgetreten</em> gesetzt statt gelöscht. Resultate und frühere Ranglisten bleiben.</li><li><strong>Mitgliedsart</strong>: Aktiv-, Passiv-, Frei- oder Ehrenmitglied. Ehrenmitglieder werden in der Adressliste und in der Schützenabrechnung ausgewiesen. Passiv und Frei sind eine Angabe für den Vorstand und ändern an Listen und Ranglisten nichts.</li><li><strong>Eintritt</strong> und <strong>Austritt</strong>: Jahr der Vereinsaufnahme und des Austritts. Beim Wechsel auf <em>Ausgetreten</em> schlägt die Seite das laufende Jahr vor.</li><li><strong>Verstorben</strong>: Das Mitglied verschwindet aus Adressliste, Fragebogen und den Auswahllisten, bleibt aber mit seinen Resultaten erhalten.</li><li><strong>Jungschützenleitung</strong>: Das Mitglied gehört zur Jungschützenleitung. Es sieht das Betreuer-Board auch ohne eigene Anmeldung als Betreuer, empfängt den Leitungs-Chat der Jungschützen und die Eskalationen bei unbetreuten Anfragen. Dafür braucht das Mitglied ein freigegebenes Portal-Login, ohne Login laufen diese Meldungen ins Leere (Hinweis in der <a href="jsk_verwaltung.php">JSK-Verwaltung</a>).</li></ul>',
 'mitgliederverwaltung'),

('mitgliederverwaltung.neu',
 'Neues Mitglied',
 '<p>Name, Vorname, Geburtsdatum, Lizenznummer und Waffe braucht es zum Anlegen. Alles andere lässt sich später im Panel ergänzen. «Adresse und Kontakt» klappt auf, wenn du es gleich miterfassen willst. Ctrl+S legt an wie der Knopf.</p><ul><li>Die <strong>Lizenznummer</strong> muss frei sein. Ist sie vergeben, nennt die Seite das Mitglied, dem sie gehört.</li><li>Gibt es schon jemanden mit gleichem Namen, Vornamen und Geburtsdatum, fragt die Seite nach, bevor sie ein zweites Mitglied anlegt.</li><li>Neue Mitglieder haben den Stand <em>Mitglied</em> und die gewählte Mitgliedsart. Als Eintritt steht das laufende Jahr, wenn du nichts anderes einträgst.</li></ul><p>Nach dem Anlegen springt die Liste zum neuen Mitglied.</p>',
 'mitgliederverwaltung'),

('mitgliederverwaltung.import',
 'Mitglieder importieren',
 '<p>Der Import liest eine CSV-Datei mit Kopfzeile (Trennzeichen Semikolon, Komma oder Tab, aus Excel als «CSV UTF-8» oder «CSV» gespeichert). Am einfachsten lädst du mit <strong>Mitglieder (CSV)</strong> die aktuelle Liste herunter, änderst sie in Excel und lädst sie wieder hoch.</p><ul><li><strong>ID</strong> (Lizenznummer) ist Pflicht: Gibt es die Nummer, wird das Mitglied aktualisiert, sonst neu angelegt.</li><li>Geändert werden <strong>nur Spalten, die in der Datei stehen</strong>. Eine Liste nur mit ID und Email ändert also nur E-Mail-Adressen.</li><li>Bekannte Spalten: ID, Anrede, Vorname, Name, Geburtsdatum, WaffenID oder Waffe (Bezeichnung), Status (1 = Mitglied, 0 = ausgetreten), Mitgliedsart, Ehrenmitglied, Vereinsaufnahme, Austrittsjahr, Verstorben, Strasse, PLZ, Ort, Email, Telefon, Mobile, Kommunikation, Notizen. Andere Spalten werden angezeigt und übergangen.</li><li>Neue Mitglieder brauchen Name, Vorname, Geburtsdatum und Waffe.</li></ul><p><strong>Zuerst prüfen, dann übernehmen</strong>: Nach dem Hochladen zeigt die Seite, wer neu ist, was sich bei wem ändert (alt → neu) und welche Zeilen Fehler haben. Erst «Änderungen übernehmen» schreibt, vorher wird die Datenbank gesichert. Zeilen mit Fehlern werden übersprungen, der Rest wird übernommen.</p>',
 'mitgliederverwaltung')
ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
