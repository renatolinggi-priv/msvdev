-- Migration 086: Hilfetexte zum Schutz ungespeicherter Eingaben im Vereinscup und bei «Stiche lösen»
-- sowie zum Stand von «Absenden vorbereiten» (Uhrzeit, neu prüfen, Hinweis bei den Dokumenten).
--
-- Gezielte Ersetzungen statt ganzer Texte, damit Anpassungen anderer Migrationen (z.B. 084) erhalten bleiben.
-- Findet REPLACE den Satz nicht (im Editor geändert), bleibt der Text unverändert.

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<strong>Speichern</strong> (auch Ctrl+S) sichert alle Runden auf einmal.',
  '<strong>Speichern</strong> (auch Ctrl+S) sichert alle Runden auf einmal. Solange etwas nicht gespeichert ist, steht «Nicht gespeichert» daneben, und beim Jahreswechsel oder beim Verlassen der Seite fragt sie nach.')
WHERE schluessel = 'cup.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'die bearbeitete Zeile ist blau umrandet.</li>',
  'die bearbeitete Zeile ist blau umrandet. Ist im Formular noch eine Auswahl nicht gespeichert, fragt die Seite vor dem Wechsel zu einem anderen Teilnehmer oder Jahr: Speichern, Verwerfen oder Zurück.</li>')
WHERE schluessel = 'endschloesen.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'Rechts oben steht der Stand: «bereit fürs Absenden» oder wie viele Punkte noch offen sind.',
  'Rechts oben steht der Stand mit der Uhrzeit der Prüfung: «bereit fürs Absenden» oder wie viele Punkte noch offen sind; der Pfeil daneben prüft neu. Sind noch Punkte offen, steht das auch bei den Dokumenten; sie lassen sich trotzdem erstellen.')
WHERE schluessel = 'endschrang.absenden';
