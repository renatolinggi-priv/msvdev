-- Erstelle Tabelle für Gäste beim Endschiessen
CREATE TABLE IF NOT EXISTS `endstich_gaeste` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL COMMENT 'Name des Gastes',
  `jahr` int(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_gast_jahr` (`name`, `jahr`),
  KEY `idx_jahr` (`jahr`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Gäste/Partner für Endschiessen';

-- Erweitere die Selection-Tabelle um Gast-ID
ALTER TABLE `endstich_selection` 
  ADD COLUMN `gast_id` int(11) DEFAULT NULL AFTER `mitglied_id`,
  ADD KEY `idx_gast` (`gast_id`),
  ADD CONSTRAINT `fk_selection_gast` FOREIGN KEY (`gast_id`) REFERENCES `endstich_gaeste` (`id`) ON DELETE CASCADE;

-- Ändere den Foreign Key für mitglied_id auf optional
ALTER TABLE `endstich_selection` 
  MODIFY COLUMN `mitglied_id` int(11) DEFAULT NULL;

-- Erweitere die Zusatz-Schuss-Tabelle für Gäste
ALTER TABLE `endstich_zusatz_schuss`
  ADD COLUMN `gast_id` int(11) DEFAULT NULL AFTER `mitglied_id`,
  ADD KEY `idx_zusatz_gast` (`gast_id`),
  ADD CONSTRAINT `fk_zusatz_gast` FOREIGN KEY (`gast_id`) REFERENCES `endstich_gaeste` (`id`) ON DELETE CASCADE;

-- Ändere den Foreign Key für mitglied_id auf optional
ALTER TABLE `endstich_zusatz_schuss`
  MODIFY COLUMN `mitglied_id` int(11) DEFAULT NULL;
