-- Migration 027: Gefuehrter Wanderpreise-Regel-Builder
-- Fuegt zwei Spalten zu wanderpreise_regeln hinzu:
--   regel_typ    = 'custom' (rohes SQL, heutiges Verhalten) | 'einzelwettbewerb' (gefuehrt)
--   regel_params = JSON der Formular-Parameter des Builders (NULL bei custom)
-- Bestehende Regeln behalten den Default 'custom' und laufen unveraendert weiter.
-- auto_zuordnung.php liest weiterhin nur sql_query -> kein Eingriff in die Ausfuehrung.

ALTER TABLE `wanderpreise_regeln`
    ADD COLUMN IF NOT EXISTS `regel_typ`    VARCHAR(40) NOT NULL DEFAULT 'custom' AFTER `regel_beschreibung`,
    ADD COLUMN IF NOT EXISTS `regel_params` TEXT NULL AFTER `sql_query`;
