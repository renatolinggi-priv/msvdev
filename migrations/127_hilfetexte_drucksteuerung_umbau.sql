-- Migration 127: Hilfetexte Drucksteuerung nach dem Umbau (Nov 2026, 1.68.52).
-- Neu: Reihenfolge Verbindung → Drucker → Druckprofile, Drucker beim ersten Druck auf einer Seite wählen,
-- Einrichtung eines anderen Arbeitsplatzes übernehmen, Hilfe zur Karte «Drucker».
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('drucksteuerung.uebersicht',
 'Drucksteuerung (Direktdruck)',
 '<p>Auf vielen Seiten steht neben einem PDF das Drucker-Symbol. Es druckt direkt, ohne das PDF zu öffnen. Dafür muss auf dem Computer das Programm <strong>QZ Tray</strong> laufen. Hier wird eingerichtet, welches Dokument auf welchem Drucker wie herauskommt.</p><p><strong>Am einfachsten:</strong> auf einer Seite das Drucker-Symbol klicken. Beim ersten Druck eines Dokuments fragt die App einmal nach dem Drucker und merkt sich die Wahl. Hier lässt sich das danach anpassen.</p><ul><li><strong>Verbindung</strong>: ob QZ Tray auf diesem Computer läuft, und was zu tun ist, wenn nicht.</li><li><strong>Drucker</strong>: die Drucker dieses Computers, die zur Wahl stehen.</li><li><strong>Druckprofile</strong>: pro Dokument Drucker, Format, Kopien, beidseitig und Farbe.</li><li><strong>Druckprotokoll</strong>: die letzten Aufträge; bei einem Fehler steht die Ursache direkt darunter.</li></ul><p>Ohne QZ Tray geht nichts verloren: Dokumente herunterladen und wie gewohnt drucken.</p>',
 'drucksteuerung'),

('drucksteuerung.qz',
 'Verbindung zu QZ Tray',
 '<p>Grün heisst: Direktdruck möglich. Rot heisst: Der Browser erreicht QZ Tray nicht.</p><ul><li><strong>QZ Tray läuft nicht</strong>: Das Programm starten (Symbol unten rechts in der Taskleiste) und <strong>Neu verbinden</strong> wählen. Ist es nicht installiert, gibt es es kostenlos unter qz.io.</li><li><strong>QZ Tray lehnt ab</strong>: Auf diesem Computer fehlt das Zertifikat der Vereinsverwaltung. Das richtet der Administrator ein.</li><li><strong>Gilt für diesen Browser</strong>: Drucker und Profile gehören zu diesem Browser. In einem anderen Browser, auf einem anderen Computer oder nach dem Löschen der Browserdaten ist zuerst nichts eingerichtet. Gibt es eine Einrichtung an einem anderen Arbeitsplatz, bietet die Seite an, sie zu <strong>übernehmen</strong>; Vorhandenes bleibt dabei, wie es ist.</li></ul>',
 'drucksteuerung'),

('drucksteuerung.drucker',
 'Drucker',
 '<p>Die Drucker dieses Computers, die in den Profilen zur Wahl stehen.</p><ul><li><strong>Drucker übernehmen</strong>: einen Drucker aus der Liste wählen, die QZ Tray vom Computer liefert. Der Anzeigename ist freiwillig, z.&nbsp;B. «Laser Büro».</li><li><strong>Inaktiv</strong>: Der Drucker steht nicht mehr zur Wahl. Profile, die ihn schon nutzen, behalten ihn und zeigen einen Hinweis.</li><li><strong>Löschen</strong>: Die Rückfrage nennt die Profile, die damit ihren Drucker verlieren. Sie bleiben in der Liste und brauchen danach einen anderen Drucker.</li></ul><p>Wer auf einer Seite zum ersten Mal ein Dokument direkt druckt und dort einen Drucker wählt, findet ihn danach auch hier.</p>',
 'drucksteuerung'),

('drucksteuerung.profile',
 'Druckprofile',
 '<p>Eine Zeile pro Dokument, gruppiert nach Jahresmeisterschaft, Endschiessen, Ranglisten, Einsätze und Verein. Die Gruppen lassen sich zuklappen; der Browser merkt sich das.</p><ul><li><strong>Drucker</strong>: einer der Drucker oben. Ein inaktiver bleibt im Profil stehen, bis ein anderer gewählt wird.</li><li><strong>Format</strong>: bei Ranglisten wählbar (A4 hoch oder quer), das PDF wird dann so erzeugt. Sonst fest.</li><li><strong>Beidseitig</strong>: einseitig, lange Kante (wie ein Buch) oder kurze Kante (Blätter im Querformat zum Falten). Absendenbuch und Monatsblatt werden automatisch über die kurze Kante gewendet.</li><li><strong>Kopien</strong> 1 bis 9 und <strong>Farbe</strong> (Schwarzweiss, Graustufen, Farbe).</li><li><strong>Testdruck</strong> (Drucker-Symbol der Zeile): eine Testseite mit Rahmen und 100-mm-Massband mit den Werten der Zeile, auch ungespeichert.</li><li><strong>Übertragen</strong> (Pfeil): Drucker, Kopien, beidseitig und Farbe auf die ganze Gruppe oder auf alle Dokumente übernehmen. Mit gedrückter Umschalttaste direkt auf alle.</li></ul><p>Geänderte Zeilen sind blau markiert, bis sie mit <strong>Speichern</strong> übernommen sind; <strong>Änderungen verwerfen</strong> stellt den gespeicherten Stand her. Wer eine Zeile auf «Kein Drucker» setzt und speichert, entfernt dieses Profil nach einer Rückfrage.</p>',
 'drucksteuerung')
ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
