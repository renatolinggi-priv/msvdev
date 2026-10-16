-- Migration 111: Hilfetexte zu «Auf Website melden» und «Weitere Dokumente» (Okt 2026, 1.68.30).
-- 1) Heimmeisterschaft und Kantonalstich: Der Knopf «Veröffentlichen» heisst «Auf Website melden». Er legt einen
--    Hinweis auf www.msvwilen.ch an (changelog, abgerufen über api/changelog.php), nicht im Portal der Mitglieder.
-- 2) Endschiessen Rangliste: Übersicht, Einzelwettbewerbe und Partner stehen unter «Weitere Dokumente» (eingeklappt).
--
-- Idempotent: REPLACE (ohne Treffer keine Änderung) bzw. NOT LIKE auf den neuen Absatz.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Veröffentlichen</strong> legt nach Rückfrage einen Eintrag «Heimresultate JAHR aktualisiert» im Änderungsprotokoll der Mitglieder an. Die Resultate selbst sind unabhängig davon sofort in der Rangliste sichtbar.</li>',
  '<li><strong>Auf Website melden</strong> setzt nach Rückfrage den Hinweis «Heimresultate JAHR aktualisiert» auf www.msvwilen.ch. Die Resultate selbst ändert das nicht; gespeichert sind sie sofort sichtbar.</li>')
WHERE schluessel = 'heimresultate.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Veröffentlichen</strong> legt nach Rückfrage einen Eintrag «Kantiresultate JAHR aktualisiert» im Änderungsprotokoll der Mitglieder an. Die Resultate selbst sind unabhängig davon sofort in der Rangliste sichtbar.</li>',
  '<li><strong>Auf Website melden</strong> setzt nach Rückfrage den Hinweis «Kantiresultate JAHR aktualisiert» auf www.msvwilen.ch. Die Resultate selbst ändert das nicht; gespeichert sind sie sofort sichtbar.</li>')
WHERE schluessel = 'kantiresultate.uebersicht';

UPDATE hilfetexte SET inhalt_html = CONCAT(inhalt_html,
  '<p><strong>Weitere Dokumente</strong>: Offen steht nur, was es fürs Absenden braucht. Zwischenrangliste, Anmeldungen, Einzelwettbewerbe und Partner liegen unter «Weitere Dokumente»; ein Klick klappt sie auf, und der Browser merkt sich, ob sie offen bleiben.</p>')
WHERE schluessel = 'endschrang.dokumente' AND inhalt_html NOT LIKE '%Weitere Dokumente%';
