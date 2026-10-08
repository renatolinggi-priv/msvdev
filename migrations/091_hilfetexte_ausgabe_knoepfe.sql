-- Migration 091: Hilfetexte zum einheitlichen Verhalten der Ausgabe-Knöpfe (Okt 2026).
-- Alle PDF-, Word- und Excel-Knöpfe laden die Datei direkt herunter (kein Link unter den Knöpfen, kein
-- neues Fenster, kein Erfolgsdialog); ohne verbundenes QZ Tray sind die Drucker-Knöpfe ausgeblendet und
-- die Kopf-Karte zeigt «Direktdruck nicht verbunden». Betroffen: JM-, Heim- und Kanti-Rangliste,
-- JM Standblatt (Badge «Direktdruck» entfernt) und Zielscheiben-Ausdruck (ohne Erfolgsdialog).
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('jmrang.dokumente',
 'PDF-Ranglisten und Direktdruck',
 '<ul><li><strong>Rangliste (nach Rang)</strong>: die offizielle Rangliste, je eine Seite pro Kategorie, sortiert nach Total. Streicher sind rot durchgestrichen, die Legende nennt die Anzahl Streicher; hochgerechnete Werte und Totale haben zwei Nachkommastellen, rohe Resultate keine.</li><li><strong>Rangliste (nach Name)</strong>: alphabetische Liste, in der <em>alle</em> Resultate summiert werden, ohne Streicher-Logik. Dient als Übersicht und zur Kontrolle.</li><li>Ein Klick erstellt die Datei und lädt sie direkt herunter; solange sie entsteht, dreht sich das Symbol im Knopf.</li><li>Der <strong>Drucker-Knopf</strong> neben jedem PDF schickt es direkt an den Drucker aus dem Druckprofil «JM Rangliste» (Seite Drucksteuerung, dort auch Hoch- oder Querformat). Er erscheint nur, wenn QZ Tray auf diesem Computer läuft; sonst steht oben «Direktdruck nicht verbunden».</li></ul><p>Hinweis: Das PDF nimmt als Streicher-Basis alle Anlässe mit mindestens einem erfassten Resultat, die Bildschirm-Rangliste nur nach Datum durchgeführte. Während der Saison können die Totale deshalb abweichen; nach dem Endstich stimmen beide überein.</p>',
 'jmrang'),

('heimrang.dokumente',
 'Rangliste als PDF und Direktdruck',
 '<ul><li><strong>Rangliste</strong> erzeugt ein PDF mit beiden Kategorien des gewählten Jahres und lädt es herunter. Standard ist Querformat; die Ausrichtung folgt dem Druckprofil «Heimmeisterschaft Rangliste» aus der Drucksteuerung.</li><li>Der <strong>Drucker-Knopf</strong> schickt dasselbe PDF direkt an den im Druckprofil hinterlegten Drucker. Er erscheint nur, wenn QZ Tray auf diesem Computer läuft (sonst steht oben «Direktdruck nicht verbunden»); fehlt das Druckprofil, bleibt er gesperrt und der Tooltip nennt den Grund.</li></ul><p>Jede Erstellung schreibt eine neue, zeitgestempelte Datei; ältere Stände werden automatisch aufgeräumt.</p>',
 'heimrang'),

('kantirang.dokumente',
 'Rangliste als PDF und Direktdruck',
 '<ul><li><strong>Rangliste</strong> erzeugt ein PDF mit beiden Kategorien des gewählten Jahres und lädt es herunter. Standard ist Hochformat; die Ausrichtung folgt dem Druckprofil «Kantonalstich Rangliste» aus der Drucksteuerung.</li><li>Der <strong>Drucker-Knopf</strong> schickt dasselbe PDF direkt an den im Druckprofil hinterlegten Drucker. Er erscheint nur, wenn QZ Tray auf diesem Computer läuft (sonst steht oben «Direktdruck nicht verbunden»); fehlt das Druckprofil, bleibt er gesperrt und der Tooltip nennt den Grund.</li></ul><p>Jede Erstellung schreibt eine neue, zeitgestempelte Datei; ältere Stände werden automatisch aufgeräumt.</p>',
 'kantirang'),

('jmstandblatt.uebersicht',
 'JM Standblatt',
 '<p>Erzeugt für die aktiven Mitglieder das Standblatt der Jahresmeisterschaft aus der Word-Vorlage (A4 quer) mit Jahr, Name und der Lizenznummer als Barcode.</p><ul><li><strong>Jahr</strong> wählen (nächstes Jahr bis drei Jahre zurück); das Suchfeld filtert die Liste.</li><li><strong>Alle (DOCX)</strong> lädt nacheinander je eine Word-Datei pro Mitglied herunter.</li><li><strong>Alle (PDF)</strong> erstellt ein Sammel-PDF; jede Datei wird dafür über den PDF-Dienst umgewandelt, was einige Zeit dauert. Mitglieder, für die kein Standblatt erzeugt werden konnte, werden gemeldet.</li><li><strong>Alle drucken</strong> schickt dasselbe Sammel-PDF als einen Druckauftrag an den Drucker aus dem Druckprofil «JM Standblatt». Die Druck-Knöpfe erscheinen nur, wenn QZ Tray auf diesem Computer läuft; sonst steht oben «Direktdruck nicht verbunden».</li></ul>',
 'jmstandblatt'),

('jmstandblatt.ausgabe',
 'Standblatt pro Mitglied',
 '<p>Pro Zeile lässt sich das Standblatt als <strong>Word</strong> herunterladen oder per <strong>Direktdruck</strong> drucken. Der Druck-Knopf erscheint nur, wenn QZ Tray läuft; fehlt in der Drucksteuerung das Profil «JM Standblatt», bleibt er gesperrt und der Tooltip nennt den Grund.</p><ul><li><strong>Lizenz</strong> ist die Mitgliedernummer. Der Barcode folgt dem SSV-Format: sechsstellige Nummern erhalten den Präfix 10, dazu zwei Prüfziffern. Passt die Nummer nicht in dieses Schema, bleibt das Barcode-Feld leer.</li><li>Name und Vorname kommen aus den Stammdaten; Änderungen dort wirken beim nächsten Erzeugen.</li></ul>',
 'jmstandblatt'),

('endsch_targetprint.uebersicht',
 'Zielscheiben aus Imetron-CSV drucken',
 '<p>Erzeugt aus einer Imetron-Resultatdatei ein PDF mit den <strong>Trefferbildern</strong> pro Stich, zum Beispiel als Andenken für Partnerinnen und Gäste. Es wird nichts in der Datenbank gespeichert.</p><ul><li><strong>Ablauf</strong>: CSV ablegen, optional den Namen des Schützen eingeben (erscheint im PDF-Titel) und das Jahr wählen, gefundene Stiche prüfen. <strong>PDF Generieren</strong> erstellt die Datei und lädt sie direkt herunter; der Drucker-Knopf daneben druckt sie direkt (Druckprofil «Endschiessen Zielscheiben» in der Drucksteuerung, nur sichtbar mit QZ Tray). «Zurück» lädt eine neue CSV.</li><li>Welche Programmnummern erkannt werden, steht im Hinweis oben und stammt aus <a href="internestichedef.php">Imetron-Stichnummern</a>. Nicht definierte Nummern werden trotzdem ausgegeben, mit dem Namen aus der Datei.</li><li>Pro Stich eine Seite mit Trefferbild aus den Koordinaten, Statistik (Schuss, Wertung, Hunderter) und Total. Der Schwini-Stich wird auf der Keiler-Scheibe dargestellt.</li><li>Hunderterwertungen werden für die Anzeige auf die Zehnerskala umgerechnet (91–100 = 10, 81–90 = 9 und so weiter); Kunst und Glück bleiben unverändert.</li></ul>',
 'endsch_targetprint')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
