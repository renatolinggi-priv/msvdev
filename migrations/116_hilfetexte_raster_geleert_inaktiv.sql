-- Migration 116: Heim und Kanti erfassen, Audit-Korrekturen (Okt 2026, 1.68.38).
-- Ein geleertes Feld löscht die gespeicherte Passe jetzt (vorher blieb der alte Wert stehen, und die Hilfe riet, 0
-- einzutragen). Die Liste zeigt neben den aktiven Mitgliedern alle, die im gewählten Jahr Resultate haben (wie die
-- PDF-Rangliste), mit dem Hinweis «inaktiv» oder «verstorben». Die beste Kanti-Passe ist golden mit Stern (nicht
-- mehr blau). Der Name öffnet die Schnellerfassung auch per Enter. Auf dem Handy steht unten eine Speicherleiste.
--
-- Idempotent: alle Texte per REPLACE (ohne Treffer keine Änderung).
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'Beim Speichern werden bei bestehenden Datensätzen nur ausgefüllte Felder übernommen. Ein geleertes Feld löscht den gespeicherten Wert nicht; wer ein Resultat entfernen will, trägt 0 ein.',
  'Ein geleertes Feld löscht die gespeicherte Passe beim Speichern (sie zählt danach als nicht geschossen); die Meldung nach dem Speichern nennt, wie viele Passen geleert wurden.')
WHERE schluessel IN ('heimresultate.tabelle', 'kantiresultate.tabelle');

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'Ein Klick auf den Namen öffnet den Schützen in der Schnellerfassung.',
  'Ein Klick auf den Namen (oder Enter auf dem Namen) öffnet den Schützen in der Schnellerfassung.')
WHERE schluessel IN ('heimresultate.tabelle', 'kantiresultate.tabelle');

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'die <strong>beste Passe</strong> eines Schützen ist blau hervorgehoben (in der Schnellerfassung mit «beste» beschriftet).',
  'die <strong>beste Passe</strong> eines Schützen ist golden und mit einem Stern markiert (in der Schnellerfassung und auf dem Handy mit «beste» beschriftet).')
WHERE schluessel = 'kantiresultate.tabelle';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<strong>Liste</strong>: alle aktiven Mitglieder, zuerst die mit Resultaten, dann die ohne.',
  '<strong>Liste</strong>: alle aktiven Mitglieder und dazu alle, die im gewählten Jahr Resultate haben (nicht mehr aktive mit dem Hinweis «inaktiv» oder «verstorben», so wie sie auch in der Rangliste stehen); zuerst die mit Resultaten, dann die ohne.')
WHERE schluessel IN ('heimresultate.uebersicht', 'kantiresultate.uebersicht');

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'Auf dem Handy erscheint pro Mitglied eine aufklappbare Karte.</li>',
  'Auf dem Handy erscheint pro Mitglied eine aufklappbare Karte; ist etwas nicht gespeichert, steht unten eine Leiste mit «Speichern».</li>')
WHERE schluessel IN ('heimresultate.uebersicht', 'kantiresultate.uebersicht');

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li>Leere Felder vor einer späteren Passe werden als 0 gespeichert.</li>',
  '<li>Leere Felder vor einer späteren Passe werden als 0 gespeichert; ein geleertes Feld löscht die gespeicherte Passe.</li>')
WHERE schluessel IN ('heimresultate.schnellerfassung', 'kantiresultate.schnellerfassung');
