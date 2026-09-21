-- Migration 055: Einsatzplanung – Titelfarbe je Plan (Hintergrund der Titelzeile im Word/PDF, Hex «#RRGGBB»).
-- NULL = Standardgrau. Idempotent.
ALTER TABLE `einsatz_plaene` ADD COLUMN IF NOT EXISTS `farbe` VARCHAR(7) NULL AFTER `fusstext`;
