-- Migration 079: Hilfetext Fragebogen-Auswertung ohne «Alle Antworten des Jahres löschen»
-- (Knopf entfernt am 08.10.2026: ganze Jahre werden nicht gelöscht).
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('mitgliederfragebogen.uebersicht',
 'Auswertung Fragebogen',
 '<p>Hier werden die Antworten des <strong>Mitglieder-Fragebogens</strong> eines Jahres erfasst und ausgewertet, eine Zeile pro Mitglied.</p><ul><li><strong>Waffe</strong>: die gemeldete Waffe oder «Nehme nicht teil». Beim Speichern wird die gewählte Waffe auch in die <strong>Mitglieder-Stammdaten</strong> übernommen (nicht bei «Nehme nicht teil»).</li><li><strong>ZSMM</strong> (Vereinsmannschaft) und <strong>GM</strong> (Gruppenmeisterschaft): Ja, Nein oder Auffüllen. Die Farben zeigen den Zustand (grün Ja, rot Nein, gelb Auffüllen).</li><li>Rechts folgt <strong>eine Spalte pro Anlass</strong>, der im Jahresprogramm die Option «Erweitert» trägt (Ja/Nein). Lange Bezeichnungen sind gekürzt, der volle Text erscheint beim Zeigen mit der Maus.</li><li>Mitglieder mit «Nehme nicht teil» sind ausgeblendet; der Knopf <strong>Nicht-Teilnehmer anzeigen</strong> blendet sie ein und zeigt ihre Anzahl.</li><li>Ohne gespeicherte Antwort gilt: Waffe «Nehme nicht teil», Mannschaft und Gruppen «Nein».</li></ul><p><strong>Speichern</strong> schreibt alle Zeilen des gewählten Jahres. Der leere Fragebogen zum Verteilen wird auf der Seite <a href="jmdefinition.php">Jahresmeisterschaft Definition</a> erzeugt.</p>',
 'mitgliederfragebogen')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
