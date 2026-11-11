-- Migration 132: Hilfetexte Einsatzplanung nach der Critique (Nov 2026, 1.68.56).
-- Neu: Ablauf (Schrittleiste), Verfügbarkeiten, Einteilung, Rückmeldung, OK-Mitglieder.
-- Aktualisiert: Liste (Löschen nur Entwürfe ohne erfasste Einsätze) und Editor (Plan einrichten, Vorschläge, Speichern).
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('einsatzplanung.ablauf',
 'Ablauf eines Einsatzplans',
 '<p>Die Leiste oben zeigt die Schritte in der Reihenfolge der Saison und wie weit jeder ist. Ein Haken heisst erledigt, die blaue Nummer ist der nächste Schritt. Der blaue Knopf rechts führt direkt dorthin; ein Klick auf einen Schritt öffnet ihn ebenfalls.</p><ol><li><strong>Termine und Funktionen</strong>: wann, und welche Posten es braucht (unter «Plan einrichten»).</li><li><strong>Verfügbarkeiten</strong> (nur mit Anfrage-Rollen, z.B. Schlossturm): wer wann in welcher Rolle kann, aus der Umfrage im Portal, der Personalanfrage der Vereine oder von Hand.</li><li><strong>Positionen besetzen</strong>: automatisch einteilen und die Vorschläge übernehmen, Personen aus der Liste rechts ziehen oder das ausgefüllte Word der Vereine als Rückmeldung einlesen.</li><li><strong>Freigeben</strong>: Die Mitglieder sehen ihre Einsätze im Portal und werden auf Wunsch benachrichtigt.</li><li><strong>Anwesenheit</strong>: nach dem Anlass erfassen, wer da war (auch mobil im Portal).</li><li><strong>Abrechnung</strong> (Schlossturm): Helferstunden je Verein.</li></ol><p>Die Knöpfe darunter bleiben für Korrekturen jederzeit verfügbar.</p>',
 'einsatzplanung'),
('einsatzplanung.verfuegbarkeiten',
 'Verfügbarkeiten',
 '<p>Hier steht, wer an welchen Schichten in welcher Rolle helfen kann. Die automatische Einteilung braucht diese Angaben von allen drei Vereinen.</p><ul><li><strong>Umfrage im Portal</strong>: Umfrage wählen (Kategorie «Arbeitseinsatz» oder «Helfer») und <strong>Aus Umfrage übernehmen</strong>. Rollen und Schichten werden aus den Antworten erkannt.</li><li><strong>Personalanfrage-Excel</strong>: Verein wählen, Datei prüfen, in der Vorschau auswählen und übernehmen. Die leere Vorlage für die anderen Vereine gibt es als «Personalanfrage leer (Excel)».</li><li><strong>Von Hand</strong>: unterste Zeile ausfüllen und mit «+» hinzufügen.</li></ul><p>Geänderte Zeilen sind hinterlegt, bis sie gespeichert sind: einzeln mit dem Haken oder alle mit <strong>Änderungen speichern</strong>. Beim Schliessen mit offenen Änderungen fragt die Seite nach. Umfrage und Excel überschreiben keine von Hand erfassten Zeilen.</p>',
 'einsatzplanung'),
('einsatzplanung.einteilung',
 'Automatische Einteilung',
 '<p>Die Einteilung besetzt offene Positionen mit gemeldeten Personen. Sie schlägt nur vor: Vorschläge stehen im Raster <em>kursiv, grau gestrichelt und mit Zauberstab</em> und erscheinen weder im Word noch im Portal, bis sie übernommen sind.</p><ul><li>Fest besetzte Positionen bleiben unverändert.</li><li>Nur Funktionen mit einer <strong>Anfrage-Rolle</strong> werden besetzt (Plan einrichten → Funktionen).</li><li>Reihenfolge: knappe Schichten zuerst, dann der Verein im Rückstand, wenig belastete Personen, gleiche Funktion über mehrere Schichten, wenig flexible Personen vor flexiblen. Eine Person hat höchstens eine Position pro Schicht.</li></ul><p>Danach im Raster prüfen, einzelne Vorschläge im Panel übernehmen oder alle mit <strong>Vorschläge übernehmen</strong>. «Verwerfen» macht die Positionen wieder frei.</p>',
 'einsatzplanung'),
('einsatzplanung.rueckmeldung',
 'Rückmeldung eines Vereins',
 '<p>Die anderen Vereine tragen ihre Leute im Word ein, das aus diesem Plan exportiert wurde (Ausgabe → Dokumente). Das ausgefüllte Word hier hochladen und angeben, von welchem Verein es kommt.</p><p><strong>Prüfen</strong> zeigt jede Änderung mit «bisher» und «neu». Nur die angehakten Zeilen werden übernommen; Positionen anderer Vereine bleiben unberührt.</p>',
 'einsatzplanung'),
('einsatzplanung.ok',
 'OK-Mitglieder',
 '<p>Wer zum Organisationskomitee gehört, steht im Original-Einsatzplan mit «OK» statt «X» und zählt in der Helferabrechnung separat (nur mit «OK-Einsätze mitzählen»).</p><ul><li><strong>OK im Plan</strong> gilt für alle Positionen der Person in diesem Plan.</li><li><strong>Dauerhaft</strong> merkt die Person in der Stammliste; in jedem künftigen Plan ist sie automatisch OK.</li><li><strong>Funktionen, die immer vom OK besetzt sind</strong> (z.B. EDV / Anlage) gelten für alle Jahre; Personen darin brauchen kein eigenes Häkchen.</li></ul>',
 'einsatzplanung'),
('einsatzplanung.liste',
 'Einsatzpläne',
 '<p>Ein Einsatzplan ist die Helferliste eines Anlasses (Obligatorisch, Feldschiessen, Wiler Chilbi, Schlossturmschiessen) mit Terminen bzw. Schichten, Funktionen und Positionen. Die Liste zeigt alle Pläne des gewählten Jahres; ein Klick auf die Zeile öffnet den Plan.</p><ul><li><strong>Status</strong>: <em>Entwurf</em> ist nur hier sichtbar. <em>Freigegeben</em> überträgt die Einsätze der MSV-Mitglieder ins Portal («Meine Einsätze», Tausch, Erinnerungen). <em>Final</em> erklärt den Plan als definitiv (alle Namen komplett) und aktualisiert die Einsätze im Portal ein letztes Mal.</li><li><strong>Neuer Plan</strong> startet neu mit dem Aufbau eines früheren Plans gleichen Typs, als Kopie mit den Personen (Stand nach allen Tauschen) oder aus einem vorhandenen Dokument.</li><li><strong>Löschen</strong> geht nur bei Entwürfen ohne erfasste Anwesenheit und ohne Abrechnungszeilen; vorher wird die Datenbank gesichert. Ein freigegebener Plan wird zuerst zurückgezogen.</li><li><strong>Auswertung Anwesenheit</strong> zeigt die Anwesenheit des Jahres je Verein und Mitglied über alle freigegebenen Pläne.</li></ul>',
 'einsatzplanung'),
('einsatzplanung.editor',
 'Einsatzplan bearbeiten',
 '<p>Das Raster zeigt Funktionen (Zeilen) mal Termine bzw. Schichten (Spalten). Jede Position ist ein Chip: Person aus der Liste rechts hineinziehen oder Chip anklicken und im Panel wählen. Die Fläche zeigt den Verein (MSV Wilen weiss mit rotem Punkt, SV Freienbach blau, SV Wollerau grün). <strong>Offen</strong> ist bernsteinfarben gestrichelt, ein <strong>Vorschlag</strong> kursiv grau gestrichelt mit Zauberstab. Leere Positionen der anderen Vereine erscheinen im Word als Platzhalter mit dem Vereinsnamen.</p><ul><li><strong>Plan einrichten</strong>: Termine, Funktionen, Positionen je Termin (Chilbi: Soll je Schicht), Titel, Farbe und Fusstext, OK-Mitglieder.</li><li><strong>Planung</strong>: Verfügbarkeiten, automatisch Einteilen, Rückmeldung der Vereine einlesen.</li><li><strong>Ausgabe</strong>: Einsatzplan als Word, PDF oder Excel; das PDF kann direkt als Dokument fürs Portal abgelegt werden.</li><li><strong>Anwesenheit</strong> wird pro Schicht erfasst (auch mobil im Portal) und fliesst in die Auswertung und die Helferabrechnung.</li></ul><p>Das Panel speichert jede Änderung sofort und zeigt «Gespeichert um …». In den Dialogen sind geänderte Zeilen hinterlegt, bis sie gespeichert sind. Bei freigegebenen Plänen wandern Änderungen direkt ins Portal. Funktionszeilen lassen sich auch mit der Tastatur auf- und zuklappen (Tab, Enter).</p>',
 'einsatzplanung')
ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
