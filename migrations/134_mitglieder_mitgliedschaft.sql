-- Migration 134: Mitgliedschaft mit Mitgliedsart und Austrittsjahr (Nov 2026, 1.68.57).
--
-- Bisher war die Mitgliedschaft ein Ja/Nein (Status) plus Merkmal Ehrenmitglied. Neu:
--   Mitgliedsart  Aktiv / Passiv / Frei / Ehren (Angabe für den Vorstand; Ehrenmitglied wird daraus nachgeführt,
--                 weil Schützenabrechnung, Adressliste und Einsatzplan-Import die Spalte Ehrenmitglied lesen)
--   Austrittsjahr Jahr des Austritts (Eintritt = Vereinsaufnahme)
-- Status (1 = Mitglied, 0 = ausgetreten) und Verstorben bleiben unverändert die Spalten, nach denen alle Listen filtern.
--
-- Bestand: Ehrenmitglieder erhalten die Mitgliedsart Ehren, alle anderen Aktiv. Austrittsjahr bleibt leer
-- (das Jahr früherer Austritte ist nicht bekannt).
--
-- Idempotent: ADD COLUMN IF NOT EXISTS; das UPDATE setzt nur, was ohnehin aus Ehrenmitglied folgt.
-- Hinweis: keine Zeile innerhalb der Anweisungen darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS Mitgliedsart ENUM('Aktiv', 'Passiv', 'Frei', 'Ehren') NOT NULL DEFAULT 'Aktiv' AFTER Ehrenmitglied;

ALTER TABLE mitglieder ADD COLUMN IF NOT EXISTS Austrittsjahr YEAR NULL DEFAULT NULL AFTER Vereinsaufnahme;

UPDATE mitglieder SET Mitgliedsart = 'Ehren' WHERE Ehrenmitglied = 1 AND Mitgliedsart = 'Aktiv';
