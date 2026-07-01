-- Migration 039: Vorschaubild (Cover) pro Foto-Galerie
--
-- Der Vorstand kann eines der Galerie-Fotos als Vorschaubild festlegen. Ist keines
-- gesetzt (oder das gewählte Foto gelöscht/nicht freigegeben), faellt die Uebersicht
-- automatisch auf das erste freigegebene Foto zurueck (COALESCE in portal/anlaesse.php).
-- Bewusst ohne FK: stale Werte sind unkritisch (Fallback greift), kein Zyklus-Risiko.

ALTER TABLE `anlass_galerie`
    ADD COLUMN IF NOT EXISTS `cover_foto_id` INT DEFAULT NULL AFTER `beschreibung`;
