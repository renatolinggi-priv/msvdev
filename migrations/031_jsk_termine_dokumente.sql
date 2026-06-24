-- Migration 031: JSK-Inhalte fuer das Portal
--
-- 1. wichtige_termine.fuer_jsk: markiert einen Termin als "fuer Jungschuetzen".
--    Der Vorstand pflegt Termine wie gewohnt und setzt nur das Flag; das JSK-Portal
--    zeigt diese Termine. Default 0 = normaler Vereinstermin.
-- 2. vorstand_dokumente.typ: neue Kategorie 'jsk' fuer Dokumente, die im
--    Jungschuetzen-Portal sichtbar sein sollen.

ALTER TABLE `wichtige_termine`
    ADD COLUMN IF NOT EXISTS `fuer_jsk` TINYINT(1) NOT NULL DEFAULT 0 AFTER `year`;

ALTER TABLE `vorstand_dokumente`
    MODIFY `typ` ENUM('einsatzplan','protokoll','jsk') NOT NULL;
