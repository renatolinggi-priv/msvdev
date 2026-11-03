-- Migration 124: Hilfetexte JM Standblatt, seit das Blatt direkt als PDF entsteht (Nov 2026, 1.68.49).
-- Keine Word-Vorlage und kein PDF-Dienst mehr, kein Word-Download; Heimmeisterschaft mit den
-- Mannschaftsstichen (1. und 2. Passe Mannschaft abwechselnd), Deckblatt wie beim Endschiessen.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('jmstandblatt.uebersicht',
 'JM Standblatt',
 '<p>Erzeugt für die aktiven Mitglieder das Standblatt für Heimmeisterschaft und Kantonalstich als PDF: ein A4-Bogen quer, beidseitig gedruckt und in der Mitte gefaltet.</p><ul><li><strong>Aussen</strong>: rechts das Deckblatt mit Name, Waffe und Lizenz-Barcode, links die Barcodes für Mannschaft, SSM, SKSG-Wettkampf, Proben und Rütlitraining.</li><li><strong>Innen</strong>: die Klebefelder für die Standblätter der Anlage, 8 Passen Heimmeisterschaft und 5 Passen Kantonalstich. Die Heimmeisterschaft ist die Mannschaft: 4 Runden zu je 2 Passen, darum wechseln die Felder zwischen 1. und 2. Passe Mannschaft.</li><li><strong>Jahr</strong> wählen (nächstes Jahr bis drei Jahre zurück); das Suchfeld filtert die Liste.</li><li><strong>Alle Standblätter</strong> erstellt ein Sammel-PDF mit allen aktiven Mitgliedern, zwei Seiten pro Mitglied.</li><li><strong>Alle drucken</strong> schickt dasselbe Sammel-PDF als einen Druckauftrag an den Drucker aus dem Druckprofil «JM Standblatt». Die Druck-Knöpfe erscheinen nur, wenn QZ Tray auf diesem Computer läuft; sonst steht oben «Direktdruck nicht verbunden».</li></ul><p>Die Stichnummern für Heimmeisterschaft (Nummer 1 und 2) und Kantonalstich (Nummer 1) kommen aus der Seite «Imetron-Stichnummern».</p>',
 'jmstandblatt'),

('jmstandblatt.ausgabe',
 'Standblatt pro Mitglied',
 '<p>Pro Zeile lässt sich das Standblatt als <strong>PDF</strong> herunterladen oder per <strong>Direktdruck</strong> drucken. Der Druck-Knopf erscheint nur, wenn QZ Tray läuft; fehlt in der Drucksteuerung das Profil «JM Standblatt», bleibt er gesperrt und der Tooltip nennt den Grund.</p><ul><li><strong>Lizenz</strong> ist die Mitgliedernummer. Der Barcode folgt dem SSV-Format: sechsstellige Nummern erhalten den Präfix 10, dazu zwei Prüfziffern. Passt die Nummer nicht in dieses Schema, bleibt das Barcode-Feld leer.</li><li>Name, Vorname und Waffe kommen aus den Stammdaten; Änderungen dort wirken beim nächsten Erzeugen.</li></ul>',
 'jmstandblatt')
ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
