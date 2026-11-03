-- Migration 129: Hilfetext Verbindung zu QZ Tray mit dem eigenen Setup «MSV Wilen Direktdruck» (Nov 2026, 1.68.54).
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('drucksteuerung.qz',
 'Verbindung zu QZ Tray',
 '<p>Grün heisst: Direktdruck möglich. Rot heisst: Der Browser erreicht QZ Tray nicht. Auf den anderen Seiten steht dann oben der Knopf <strong>Direktdruck: QZ Tray starten</strong> mit derselben Anleitung.</p><ul><li><strong>QZ Tray ist installiert, läuft aber nicht</strong>: im Windows-Startmenü «QZ Tray» öffnen (danach unten rechts in der Taskleiste) und <strong>Neu verbinden</strong> wählen.</li><li><strong>QZ Tray fehlt oder lehnt ab</strong>: das Setup <strong>MSV Wilen Direktdruck</strong> herunterladen und ausführen. Es installiert QZ Tray (nur wenn es fehlt oder älter ist) und das Zertifikat der Vereinsverwaltung, dem QZ Tray für den Druck ohne Rückfrage vertraut. Dafür braucht es Administratorrechte am Computer. Wer schon das QZ-Setup von SFARL installiert hat, braucht es nicht: Das Zertifikat ist dasselbe.</li><li><strong>Mac</strong>: QZ Tray von qz.io; das Zertifikat richtet der Administrator ein.</li><li><strong>Gilt für diesen Browser</strong>: Drucker und Profile gehören zu diesem Browser. In einem anderen Browser, auf einem anderen Computer oder nach dem Löschen der Browserdaten ist zuerst nichts eingerichtet. Gibt es eine Einrichtung an einem anderen Arbeitsplatz, bietet die Seite an, sie zu <strong>übernehmen</strong>.</li></ul><p>Ohne QZ Tray geht nichts verloren: Dokumente herunterladen und wie gewohnt drucken.</p>',
 'drucksteuerung')
ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
