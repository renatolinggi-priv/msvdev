-- Migration 100: Hilfetext «Absenden vorbereiten» – Siegerlisten lassen sich seit Migration 099 auf der Seite
-- «Sieger der letzten Jahre» aus den Ranglisten übernehmen; der Text sagte noch «von Hand geführt … nachtragen».
-- Sonst unverändert gegenüber 097 (1.68.10, Okt 2026).
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('endschrang.absenden',
 'Absenden vorbereiten',
 '<p>Die Karte zeigt am Abend vor dem Absenden auf einen Blick, ob alles bereit ist: die Erfassung des Endschiessens und alles, was ins Absendenbuch kommt. Die Dokumente fürs Absenden stehen zuoberst in der Karte «Dokumente».</p><ul><li><strong>Stiche</strong>: wie viele Teilnehmer (Mitglieder, Gäste, Jungschützen) noch gelöste Stiche ohne Resultat haben, nach derselben Regel wie in der Erfassung; der Link führt direkt dorthin.</li><li><strong>Sie und Er</strong>: wer Sie und Er gelöst hat, aber noch mit niemandem verknüpft ist. Sie und Er hat eine eigene Rangliste und steht nicht im Absendenbuch.</li><li><strong>Wanderpreise</strong>: wie viele der Wanderpreise im Umlauf (angeschafft und nicht schon in einem früheren Jahr definitiv gewonnen) für das gewählte Jahr einen Gewinner haben.</li><li><strong>Siegerlisten</strong>: ob die Listen «… Sieger seit» im Absendenbuch das gewählte Jahr enthalten. Fehlt es, auf der Seite <a href="sieger.php">Sieger der letzten Jahre</a> mit «Aus Ranglisten übernehmen» ergänzen.</li><li><strong>Weitere Teile des Absendenbuchs</strong>: ob Jahresmeisterschaft, Heimmeisterschaft und Kantonalstich (je Kat. A und B) sowie der Vereinscup Resultate für das Jahr haben. Sonst bliebe ihre Tabelle im Buch leer; der Link führt zur Erfassung des ersten fehlenden Teils.</li></ul><p>Rechts oben steht der Stand mit der Uhrzeit der Prüfung: «bereit fürs Absenden» oder wie viele Punkte noch offen sind; der Pfeil daneben prüft neu. Sind noch Punkte offen, steht das auch bei den Dokumenten fürs Absenden; sie lassen sich trotzdem erstellen und zeigen dann den heutigen Stand. Was beim Erstellen des Absendenbuchs leer bleibt oder fehlt, zeigt danach ein Dialog; der Direktdruck fragt in diesem Fall nach, bevor er druckt.</p>',
 'endschrang')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
