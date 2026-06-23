-- Migration 010: Neue Felder für Mitgliederverwaltung (Anrede, Vereinsaufnahme, Kommunikation)
-- Führe dieses Script direkt auf der Datenbank aus.

ALTER TABLE `mitglieder`
  ADD COLUMN `Anrede` ENUM('Herr','Frau') NULL DEFAULT NULL AFTER `ID`,
  ADD COLUMN `Vereinsaufnahme` YEAR NULL DEFAULT NULL AFTER `Verstorben`,
  ADD COLUMN `Kommunikation` ENUM('Briefpost','Whatsapp','Beides') NULL DEFAULT NULL AFTER `Vereinsaufnahme`;
