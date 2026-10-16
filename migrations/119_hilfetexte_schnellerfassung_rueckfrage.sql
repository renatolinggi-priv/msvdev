-- Migration 119: Hilfetexte Schnellerfassung Heim/Kanti nach dem Absichern (Okt 2026, 1.68.40).
-- 1) Escape, Blättern und Schütze wechseln fragen bei ungespeicherten Passen nach (speichern, verwerfen, zurück).
-- 2) Das Panel prüft vor dem Speichern (Wert über 100) und zeigt «Nicht gespeichert» im Kopf.
-- 3) Ein Enter direkt nach dem selbsttätigen Weiterspringen bleibt im Feld (keine Passe übersprungen).
--
-- Idempotent: REPLACE (ohne Treffer keine Änderung) bzw. NOT LIKE auf den neuen Satz.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<strong>Escape</strong> schliesst ohne Speichern.',
  '<strong>Escape</strong> schliesst das Panel. Sind Passen dieses Schützen noch nicht gespeichert, fragt es zuerst: speichern, verwerfen oder zurück. Dasselbe gilt beim Blättern und beim Wechsel des Schützen.')
WHERE schluessel IN ('heimresultate.schnellerfassung', 'kantiresultate.schnellerfassung');

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<p>Was im Panel nicht gespeichert wurde, bleibt im Raster als ungespeichert markiert und wird mit «Speichern» oben mitgesichert.</p>',
  '<p>Solange Passen dieses Schützen nicht gespeichert sind, steht im Kopf des Panels «Nicht gespeichert». Ein Wert über 100 wird rot markiert und nicht gespeichert; die Liste über den Feldern führt direkt zum Feld.</p>')
WHERE schluessel IN ('heimresultate.schnellerfassung', 'kantiresultate.schnellerfassung');

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'springt die Eingabe von selbst weiter.</li>',
  'springt die Eingabe von selbst weiter. Ein Enter gleich nach diesem Sprung bleibt im neuen Feld, damit keine Passe übersprungen wird.</li>')
WHERE schluessel = 'heimresultate.schnellerfassung';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<strong>Enter</strong> springt zum nächsten Feld, im letzten Feld wird gespeichert und weitergeblättert.</li>',
  '<strong>Enter</strong> springt zum nächsten Feld, im letzten Feld wird gespeichert und weitergeblättert. Nach zwei Ziffern (beginnt die Zahl mit 1, nach drei) springt die Eingabe von selbst weiter; ein Enter gleich danach bleibt im neuen Feld, damit keine Passe übersprungen wird.</li>')
WHERE schluessel = 'kantiresultate.schnellerfassung'
  AND inhalt_html NOT LIKE '%gleich danach bleibt im neuen Feld%';
