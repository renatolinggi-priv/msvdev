-- Migration 052: Einsatzplanung – Plantyp «schlossturm» und Info-Zeile je Termin.
-- Das Schlossturmschiessen (OK-Einsatzliste, drei Vereine) ist strukturell Funktionen × Termine
-- mit Vereins-Zuordnung je Position; zusätzlich hat jede Schicht Treffpunkt- und Büro-Zeiten,
-- die als Info-Zeile im Kopf stehen (kein Einfluss auf Portal-Bezeichnung oder Projektion).
-- Idempotent: MODIFY ist wiederholbar, ADD COLUMN IF NOT EXISTS.

ALTER TABLE `einsatz_plaene`
  MODIFY `typ` ENUM('obligatorisch','feldschiessen','chilbi','schlossturm','sonstiges') NOT NULL DEFAULT 'sonstiges';

ALTER TABLE `einsatz_plan_termine`
  ADD COLUMN IF NOT EXISTS `info` VARCHAR(100) NULL AFTER `zeit_text`;
