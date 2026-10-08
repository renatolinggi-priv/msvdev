-- Migration 071: Hilfetexte nach dem Entfernen von «Alle Resultate löschen» (Okt 2026).
-- Die Erfassungsseiten (JM, Endschiessen, Heim, Kanti) löschen kein ganzes Jahr mehr; jedes Jahr
-- bleibt über die Jahresauswahl abrufbar. Die Auswahl zeigt alle Jahre mit Daten, merkt sich das
-- gewählte Jahr seitenübergreifend und markiert ein anderes als das laufende Jahr mit «Archiv»
-- bzw. «Planung». Ersetzt für diese Schlüssel die Texte aus 068/070.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('jmresultate.uebersicht',
 'Jahresmeisterschaft erfassen',
 '<p>Hier werden die Resultate der Jahresmeisterschaft erfasst. Jede Karte steht für einen Anlass des gewählten Jahres und zeigt, wie viele der aktiven Mitglieder bereits ein Resultat haben (grüner Rahmen = vollständig). Ein Klick auf die Karte öffnet rechts das Erfassungs-Panel.</p><ul><li><strong>Jahr</strong>: Die Auswahl enthält alle Jahre mit Daten; frühere Jahre bleiben vollständig erhalten und abrufbar. Das gewählte Jahr gilt auch auf den anderen Seiten (für einige Stunden). Ist nicht das laufende Jahr gewählt, steht daneben «Archiv JAHR» mit einem Knopf zurück zum laufenden Jahr.</li><li><strong>Endstich</strong> und <strong>Bester Kantonalstich</strong> haben keine Karte: ihre Werte kommen automatisch aus dem Endschiessen bzw. dem Kantonalstich. Info-Einträge und Anlässe des erweiterten Programms erscheinen ebenfalls nicht.</li><li><strong>PDF importieren</strong> liest eine fremde Einzelrangliste (z.B. Vereinsstich) oder die FSA-Teilnehmerliste für Obligatorisch und Feldschiessen ein. Vereinsmitglieder werden automatisch erkannt, die Vorschau lässt sich korrigieren; bereits erfasste Zeilen sind gelb markiert und abgewählt. Die Ränge 1 bis 10 werden zusätzlich als Einzelrangierung gespeichert, eine erkannte Vereinszeile als Sektionsrangierung.</li><li><strong>Veröffentlichen</strong> erscheint, sobald gespeicherte Änderungen noch nicht für das Portal freigegeben sind (Zähler = offene Einträge), und macht sie dort sichtbar.</li><li><strong>Rangliste</strong> wechselt zur Ranglisten-Seite; unten auf dieser Seite steht dieselbe Rangliste Kat. A und Kat. B als Kontrolle.</li></ul><p>Einzelne Resultate korrigiert oder entfernt man im Erfassungs-Panel (Feld leeren und speichern). Mitglieder können ihre Resultate des laufenden Jahres im Portal selbst melden; solche Einträge sind im Panel gelb als «gemeldet» markiert, bis der Vorstand sie speichert.</p>',
 'jmresultate'),

('heimresultate.aktionen',
 'Aktionen der Resultaterfassung',
 '<ul><li><strong>Schnellerfassung</strong> öffnet das Panel, in dem ein Schütze nach dem anderen erfasst wird (nur am Desktop sichtbar).</li><li><strong>Speichern</strong> schreibt alle Zeilen des Rasters. Leere Felder, auf die später noch ein Wert folgt, werden dabei automatisch mit 0 gefüllt; Mitglieder ohne einen einzigen Wert erhalten keinen Datensatz.</li><li><strong>Rangliste</strong> wechselt zur Heimmeisterschaft-Rangliste.</li><li><strong>Veröffentlichen</strong> legt nach Rückfrage einen Eintrag «Heimresultate JAHR aktualisiert» im Änderungsprotokoll an, das den Mitgliedern angezeigt wird. Die Resultate selbst sind unabhängig davon sofort in der Rangliste sichtbar.</li></ul><p>Ein ganzes Jahr wird nicht gelöscht: frühere Jahre bleiben über die Jahresauswahl abrufbar («Archiv JAHR» neben der Auswahl).</p>',
 'heimresultate'),

('kantiresultate.aktionen',
 'Aktionen der Resultaterfassung',
 '<ul><li><strong>Schnellerfassung</strong> öffnet das Panel, in dem ein Schütze nach dem anderen erfasst wird (nur am Desktop sichtbar).</li><li><strong>Speichern</strong> schreibt alle Zeilen des Rasters. Leere Felder, auf die später noch ein Wert folgt, werden dabei automatisch mit 0 gefüllt; Mitglieder ohne einen einzigen Wert erhalten keinen Datensatz.</li><li><strong>Rangliste</strong> wechselt zur Kantonalstich-Rangliste.</li><li><strong>Veröffentlichen</strong> legt nach Rückfrage einen Eintrag «Kantiresultate JAHR aktualisiert» im Änderungsprotokoll an, das den Mitgliedern angezeigt wird. Die Resultate selbst sind unabhängig davon sofort in der Rangliste sichtbar.</li></ul><p>Ein ganzes Jahr wird nicht gelöscht: frühere Jahre bleiben über die Jahresauswahl abrufbar («Archiv JAHR» neben der Auswahl).</p>',
 'kantiresultate'),

('endresultate.uebersicht',
 'Endschiessen: Resultate erfassen',
 '<p>Hier werden die Resultate der <strong>Mitglieder</strong> am Endschiessen erfasst. Die Tabelle zeigt für das gewählte Jahr alle Mitglieder, die Stiche gelöst oder bereits Resultate haben, mit dem Total je Stich.</p><ul><li><strong>Zeile anklicken</strong> öffnet rechts das Erfassungs-Panel. Aktiv sind nur die Stiche, die das Mitglied unter «Endschiessen lösen» gelöst hat; nicht gelöste Stiche sind zusammengeklappt und mit «Nicht gelöst» markiert. Das Feld Absenden ist immer offen.</li><li>In der Tabelle steht <strong>«gelöst»</strong>, wenn ein Stich gelöst, aber noch kein Resultat erfasst ist; «–» heisst nicht gelöst.</li><li><strong>Werte</strong>: Endstich und Schwini pro Schuss 0–10, Kunst, Glück und Zabig als Hunderterwertung 0–100. Die Totale rechnen beim Tippen mit.</li><li><strong>Speichern &amp; Nächster</strong> springt zum nächsten Mitglied ohne Resultate; der Fortschrittsbalken zählt Mitglieder mit Resultaten.</li><li>Gespeichert wird ein Stich nur, wenn mindestens ein Wert grösser 0 ist; leere Stiche erzeugen keinen Eintrag.</li></ul><p>Der Papierkorb im Panel entfernt die Resultate eines einzelnen Mitglieds; ein ganzes Jahr wird nicht gelöscht, frühere Jahre bleiben über die Jahresauswahl abrufbar («Archiv JAHR»). Alternativ lassen sich Resultate aus der Imetron-CSV importieren (<a href="endsch_import.php">CSV-Import Endschiessen</a>); die Schüsse der Partnerinnen werden unter <a href="endresultate_partner.php">Partnerinnen erfassen</a> erfasst.</p>',
 'endresultate')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
