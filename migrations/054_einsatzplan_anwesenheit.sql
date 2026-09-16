-- Migration 054: Einsatzplanung – Anwesenheit je Position erfassen (Vorstand/Admin, mobil im Portal).
-- anwesend: NULL = nicht erfasst, 1 = da, 0 = nicht erschienen. Auswertung je Plan und je Jahr/Mitglied/Verein.
-- Idempotent.
ALTER TABLE `einsatz_plan_slots`
  ADD COLUMN IF NOT EXISTS `anwesend` TINYINT(1) NULL AFTER `vorschlag`,
  ADD COLUMN IF NOT EXISTS `anwesend_am` DATETIME NULL AFTER `anwesend`,
  ADD COLUMN IF NOT EXISTS `anwesend_von` INT NULL AFTER `anwesend_am`;
