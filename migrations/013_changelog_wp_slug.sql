-- Migration 013: wp_slug Spalte für Changelog-Einträge
-- Speichert den WordPress-Seitenslug für Verlinkung im Widget
ALTER TABLE `changelog` ADD COLUMN `wp_slug` VARCHAR(100) DEFAULT NULL AFTER `sichtbar`;
ALTER TABLE `changelog` ADD INDEX `idx_wp_slug` (`wp_slug`);
