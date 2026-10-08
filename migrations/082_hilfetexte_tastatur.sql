-- Migration 082: Hilfetexte zur Bedienung per Tastatur (Okt 2026): Cup-Zuordnen ohne Ziehen,
-- Mitgliederliste mit Pfeiltasten.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('cup.uebersicht',
 'Vereinscup erfassen',
 '<p>Der Cup läuft in drei Stufen: <strong>Runde 1</strong>, <strong>Runde 2</strong>, <strong>Finale</strong>. Oben stehen das Jahr und der Fortschritt: wie viele Paarungen je Runde erfasst sind.</p><ul><li><strong>Generieren</strong> zieht aus den Teilnehmern die gewünschte Anzahl Paarungen in der gewählten Grösse (Zweier- oder Dreiergruppen). Teilnehmer lassen sich danach per Ziehen umsortieren, oder ohne Maus: Teilnehmer anklicken bzw. mit Tab wählen und Enter drücken, danach den Platz in der Paarung ebenso. Entf nimmt einen Teilnehmer wieder heraus, Escape hebt die Wahl auf.</li><li>Resultate direkt in die Karten eintragen; <strong>Speichern</strong> (auch Ctrl+S) sichert alle Runden auf einmal. Bei Gleichstand wird die Paarung gelb markiert und der Gewinner per Klick auf den Namen bestimmt. Abgeschlossene Paarungen haben einen grünen Rahmen.</li><li>Eine einzelne Paarung entfernt das X oben rechts auf ihrer Karte; einen Knopf, der den ganzen Cup eines Jahres löscht, gibt es nicht mehr. Frühere Jahre bleiben über die Jahresauswahl abrufbar.</li><li><strong>PDF</strong> erzeugt die Cup-Rangliste des gewählten Jahres.</li></ul><p>Runde 2 wird aus den Gewinnern von Runde 1 gebildet (nachnominierte Mitglieder sind blau markiert), das Finale aus den Gewinnern von Runde 2. Der Standcup-Final ist ein eigener Block für die drei Vereine.</p>',
 'cup'),

('mitgliederverwaltung.liste',
 'Mitgliederliste',
 '<p>Das Suchfeld filtert live nach Lizenznummer, Name, Vorname, E-Mail und Ort; der Zähler zeigt «sichtbar von gesamt».</p><p>Mit der Tastatur: Tab auf die Tabelle, die Pfeiltasten wählen eine Zeile, Enter öffnet sie im Panel.</p><ul><li>Die Spalte <strong>Status</strong> zeigt vier Punkte: Aktiv (Haken), Ehrenmitglied (Auszeichnung, orange), Verstorben (grau) und Jungschützenleiter (türkis). Ein ausgefüllter Punkt bedeutet «gesetzt».</li><li>Verstorbene Mitglieder werden abgeblendet dargestellt, bleiben aber in der Liste.</li><li>Die Spalte <strong>Waffe</strong> zeigt das Sportgerät aus der Waffenliste.</li></ul><p>Auf dem Smartphone erscheinen statt der Tabelle Karten mit eigener Suche und einem Bearbeiten-Knopf, der dasselbe Panel öffnet.</p>',
 'mitgliederverwaltung')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
