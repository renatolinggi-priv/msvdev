-- Migration 047: Endschiessen – Waffe pro Lösung (Standblatt-Platzhalter ${waffe})
--
-- Beim Lösen der Stiche wird neu die Waffe gewählt (Dropdown auf «Endschiessen
-- lösen», vorbelegt aus mitglieder.WaffenID bzw. endstich_gaeste.waffen_id). Die
-- Auswahl wird wie zahlungsmethode auf allen Zeilen des Teilnehmers im Jahr
-- gespeichert. Ohne Wert greifen Standblatt und Übersicht auf die Stammdaten zurück.
-- Solange die Spalte fehlt, arbeiten API und Generator ohne sie weiter
-- (Prüfung per SHOW COLUMNS). Idempotent.

ALTER TABLE endstich_selection ADD COLUMN IF NOT EXISTS waffen_id INT(11) DEFAULT NULL AFTER zahlungsmethode;
ALTER TABLE endstich_selection ADD INDEX IF NOT EXISTS idx_sel_waffe (waffen_id);
