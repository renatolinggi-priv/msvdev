-- Migration 040: manuelle Tageszuordnung pro Foto
--
-- Der Vorstand kann ein Foto in der Detailansicht per Drag&Drop in einen anderen
-- Tag verschieben (auch wenn der EXIF-Zeitstempel etwas anderes sagt). Damit
-- „Tage neu zuordnen" (rematch) diese Hand-Zuordnung NICHT wieder ueberschreibt,
-- markiert ein Verschieben das Foto als manuell (tag_manuell = 1); rematch
-- ueberspringt solche Fotos.

ALTER TABLE `anlass_fotos`
    ADD COLUMN IF NOT EXISTS `tag_manuell` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tag_index`;
