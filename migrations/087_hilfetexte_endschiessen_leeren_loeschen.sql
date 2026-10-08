-- Migration 087: Hilfetext Endschiessen erfassen – geleerte Stiche werden wirklich geleert, und der Papierkorb
-- zeigt vor dem Löschen, was betroffen ist, und sichert vorher die Datenbank.
--
-- Gezielte Ersetzungen statt ganzer Texte, damit Anpassungen anderer Migrationen (z.B. 084) erhalten bleiben.
-- Findet REPLACE den Satz nicht (im Editor geändert), bleibt der Text unverändert.

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li>Gespeichert wird ein Stich nur, wenn mindestens ein Wert grösser 0 ist.</li>',
  '<li>Gespeichert wird ein Stich nur, wenn mindestens ein Wert grösser 0 ist. Leerst du alle Felder eines gelösten Stichs (bei Schwini einer Passe) und speicherst, wird er auch in der Datenbank geleert; die Meldung nennt dann «geleert: …».</li>')
WHERE schluessel = 'endresultate.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'Der Papierkorb im Panel löscht die Resultate eines einzelnen Mitglieds.',
  'Der Papierkorb im Panel löscht die Resultate eines einzelnen Mitglieds im gewählten Jahr. Vorher zeigt er, was betroffen ist, auch den Eintrag der Partnerin samt Sie und Er und das Endstich-Resultat der Jahresmeisterschaft. Die Datenbank wird vor dem Löschen gesichert.')
WHERE schluessel = 'endresultate.uebersicht';
