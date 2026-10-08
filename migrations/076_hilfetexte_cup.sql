-- Migration 076: Hilfetext «CUP Resultaterfassung» nach dem Umbau (Okt 2026):
-- Jahr und Fortschritt in der Kopf-Card, Speichern auch mit Ctrl+S, kein «Löschen» mehr
-- (der Knopf löschte immer das laufende Kalenderjahr samt Finalresultaten, ohne Sicherung).
-- Ersetzt den Text aus 067 für diesen Schlüssel.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('cup.uebersicht',
 'Vereinscup erfassen',
 '<p>Der Cup läuft in drei Stufen: <strong>Runde 1</strong>, <strong>Runde 2</strong>, <strong>Finale</strong>. Oben stehen das Jahr und der Fortschritt: wie viele Paarungen je Runde erfasst sind.</p><ul><li><strong>Generieren</strong> zieht aus den Teilnehmern die gewünschte Anzahl Paarungen in der gewählten Grösse (Zweier- oder Dreiergruppen). Teilnehmer lassen sich danach per Ziehen umsortieren.</li><li>Resultate direkt in die Karten eintragen; <strong>Speichern</strong> (auch Ctrl+S) sichert alle Runden auf einmal. Bei Gleichstand wird die Paarung gelb markiert und der Gewinner per Klick auf den Namen bestimmt. Abgeschlossene Paarungen haben einen grünen Rahmen.</li><li>Eine einzelne Paarung entfernt das X oben rechts auf ihrer Karte; einen Knopf, der den ganzen Cup eines Jahres löscht, gibt es nicht mehr. Frühere Jahre bleiben über die Jahresauswahl abrufbar.</li><li><strong>PDF</strong> erzeugt die Cup-Rangliste des gewählten Jahres.</li></ul><p>Runde 2 wird aus den Gewinnern von Runde 1 gebildet (nachnominierte Mitglieder sind blau markiert), das Finale aus den Gewinnern von Runde 2. Der Standcup-Final ist ein eigener Block für die drei Vereine.</p>',
 'cup')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
