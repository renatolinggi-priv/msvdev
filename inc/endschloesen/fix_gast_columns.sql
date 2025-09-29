-- Korrigiere die Tabellenstruktur für Gäste-Support

-- Erstelle Gäste-Tabelle falls sie nicht existiert
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

-- Prüfe und füge gast_id zu endstich_selection hinzu, falls nicht vorhanden
ALTER TABLE `endstich_selection` 
  MODIFY COLUMN `mitglied_id` int(11) DEFAULT NULL;

-- Füge gast_id nur hinzu wenn sie noch nicht existiert
SET @dbname = DATABASE();
SET @tablename = 'endstich_selection';
SET @columnname = 'gast_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `gast_id` int(11) DEFAULT NULL AFTER `mitglied_id`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Prüfe und füge gast_id zu endstich_zusatz_schuss hinzu
SET @tablename = 'endstich_zusatz_schuss';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN `gast_id` int(11) DEFAULT NULL AFTER `mitglied_id`")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Ändere mitglied_id auf optional in endstich_zusatz_schuss
ALTER TABLE `endstich_zusatz_schuss`
  MODIFY COLUMN `mitglied_id` int(11) DEFAULT NULL;
