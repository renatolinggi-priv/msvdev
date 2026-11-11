-- Migration 136: Hilfetexte Drucksteuerung nach dem Einstieg für Erstnutzer (Nov 2026, 1.68.58).
-- Neu: Schrittleiste oben (Verbindung, Drucker, Dokumente, Testseite) mit dem nächsten Schritt als Knopf,
-- «Alle einrichten» bzw. «Übrige einrichten» über der Profilliste. Der Satz zu den Faltblättern aus Migration 128 bleibt.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('drucksteuerung.uebersicht',
 'Drucksteuerung (Direktdruck)',
 '<p>Auf vielen Seiten steht neben einem PDF das Drucker-Symbol. Es druckt direkt, ohne das PDF zu öffnen. Dafür muss auf dem Computer das Programm <strong>QZ Tray</strong> laufen. Hier wird eingerichtet, welches Dokument auf welchem Drucker wie herauskommt.</p><p><strong>Die Schritte oben</strong> zeigen den Stand dieses Browsers: Verbindung, Drucker, Dokumente, Testseite. Der nächste Schritt ist blau, rechts daneben steht der passende Knopf; ein Klick auf einen Schritt springt zum Abschnitt. Sind alle vier erledigt, steht dort «Direktdruck bereit».</p><p><strong>Am schnellsten:</strong> einen Drucker übernehmen, unter Druckprofile <strong>Alle einrichten</strong> wählen, <strong>Speichern</strong> und eine Testseite drucken. Oder auf einer Seite das Drucker-Symbol klicken: Beim ersten Druck eines Dokuments fragt die App einmal nach dem Drucker und merkt sich die Wahl.</p><ul><li><strong>Verbindung</strong>: ob QZ Tray auf diesem Computer läuft, und was zu tun ist, wenn nicht.</li><li><strong>Drucker</strong>: die Drucker dieses Computers, die zur Wahl stehen.</li><li><strong>Druckprofile</strong>: pro Dokument Drucker, Format, Kopien, beidseitig und Farbe.</li><li><strong>Druckprotokoll</strong>: die letzten Aufträge aus diesem Browser; bei einem Fehler steht die Ursache direkt darunter.</li></ul><p>Ohne QZ Tray geht nichts verloren: Dokumente herunterladen und wie gewohnt drucken.</p>',
 'drucksteuerung'),

('drucksteuerung.profile',
 'Druckprofile',
 '<p>Eine Zeile pro Dokument, gruppiert nach Jahresmeisterschaft, Endschiessen, Ranglisten, Einsätze und Verein. Die Gruppen lassen sich zuklappen; der Browser merkt sich das.</p><p><strong>Alle einrichten</strong>: Solange Dokumente keinen Drucker haben, steht über der Liste ein blauer Streifen. Er legt alle Dokumente ohne Drucker auf den gewählten Drucker, mit den üblichen Werten (1 Kopie, Schwarzweiss, Faltblätter automatisch beidseitig). Eingerichtete Zeilen bleiben, wie sie sind. Danach <strong>Speichern</strong>. Sollen die übrigen Dokumente bewusst ohne Drucker bleiben, blendet das «×» den Streifen für diesen Browser aus.</p><ul><li><strong>Drucker</strong>: einer der Drucker oben. Ein inaktiver bleibt im Profil stehen, bis ein anderer gewählt wird.</li><li><strong>Format</strong>: bei Ranglisten wählbar (A4 hoch oder quer), das PDF wird dann so erzeugt. Sonst fest.</li><li><strong>Beidseitig</strong>: einseitig, lange Kante (wie ein Buch) oder kurze Kante (Blätter im Querformat zum Falten). Blätter zum Falten (Absendenbuch, Monatsblatt und die beiden Standblätter) werden automatisch über die kurze Kante gewendet, solange in der Zeile nichts anderes gewählt ist.</li><li><strong>Kopien</strong> 1 bis 9 und <strong>Farbe</strong> (Schwarzweiss, Graustufen, Farbe).</li><li><strong>Testdruck</strong> (Drucker-Symbol der Zeile): eine Testseite mit Rahmen und 100-mm-Massband mit den Werten der Zeile, auch ungespeichert.</li><li><strong>Übertragen</strong> (Pfeil): Drucker, Kopien, beidseitig und Farbe auf die ganze Gruppe oder auf alle Dokumente übernehmen. Mit gedrückter Umschalttaste direkt auf alle.</li></ul><p>Geänderte Zeilen sind blau markiert, bis sie mit <strong>Speichern</strong> übernommen sind; <strong>Änderungen verwerfen</strong> stellt den gespeicherten Stand her. Wer eine Zeile auf «Kein Drucker» setzt und speichert, entfernt dieses Profil nach einer Rückfrage.</p>',
 'drucksteuerung')
ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
