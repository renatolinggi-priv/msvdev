-- Migration 077: Hilfetexte nach dem Umbau der Ranglisten-Seiten (Okt 2026):
-- Kanti-Abrechnung neu aufgebaut (Neu laden pro Kategorie, SKSG-Abrechnung eigene Karte),
-- Fragebogen-Auswertung ohne Werkzeugleiste (Jahr, PDF und Speichern in der Kopf-Karte).
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('kantiabr.uebersicht',
 'Kantonalstich: Ranglisten',
 '<p>Zeigt die Ranglisten des Kantonalstichs für das gewählte Jahr, getrennt nach <strong>Kategorie A</strong> und <strong>Kategorie B</strong>: Hauptdoppel, bis vier Nachdoppel und Total.</p><ul><li>Das Jahr oben neben dem Titel wählen; der Pfeil-Knopf im Kopf jeder Kategorie lädt deren Daten neu. «Zur Erfassung» wechselt auf die Resultaterfassung.</li><li><strong>Rangliste PDF</strong> erstellt die Rangliste als Datei, der Drucker-Knopf schickt sie direkt an den hinterlegten Drucker.</li><li>Darunter füllt <strong>SKSG-Abrechnung (Excel)</strong> das Abrechnungsformular des Verbands; Verantwortlicher und Adresse werden beim Erstellen gespeichert.</li></ul>',
 'kantiabr'),

('mitgliederfragebogen.pdf',
 'Fragebogen als PDF und Direktdruck',
 '<p><strong>Fragebogen PDF</strong> erstellt die Auswertung des gewählten Jahres als PDF im Querformat: alle Mitglieder mit ihren Antworten als Text und eine <strong>Total-Zeile</strong> am Ende.</p><ul><li>Mannschaft: Anzahl der Antworten «Ja».</li><li>Gruppen: Anzahl der Teilnahmen, getrennt nach Kat. A und Kat. B gemäss Waffenkategorie des Mitglieds.</li><li>Je erweiterte Frage: Anzahl der Antworten «Ja».</li></ul><p>Die Knöpfe stehen oben in der Kopf-Karte; ein Download-Link erscheint bei Bedarf oberhalb der Tabelle. Das Drucker-Symbol schickt das gleiche PDF direkt an den Drucker, der in der <a href="drucksteuerung.php">Drucksteuerung</a> für das Profil «Fragebogen» hinterlegt ist. Das PDF zeigt den gespeicherten Stand; ungespeicherte Änderungen vorher speichern (auch dieser Knopf steht oben).</p>',
 'mitgliederfragebogen')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
