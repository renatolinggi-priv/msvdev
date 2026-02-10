-- Tabellen für Munitionskauf Modul

-- Haupttabelle für Munitionsbestellungen
CREATE TABLE IF NOT EXISTS `munitionskauf` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jahr` year NOT NULL,
  `kauf_datum` date NOT NULL,
  `anlass` varchar(100) DEFAULT NULL,
  `mitglied_id` int(11) DEFAULT NULL,
  `gast_name` varchar(100) DEFAULT NULL,
  `gp11_total` int(11) NOT NULL DEFAULT 0,
  `gp90_total` int(11) NOT NULL DEFAULT 0,
  `total_preis` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jahr` (`jahr`),
  KEY `idx_kauf_datum` (`kauf_datum`),
  KEY `idx_mitglied` (`mitglied_id`),
  KEY `idx_jahr_datum` (`jahr`, `kauf_datum`),
  CONSTRAINT `fk_munitionskauf_mitglied` FOREIGN KEY (`mitglied_id`) 
    REFERENCES `mitglieder` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detailtabelle für einzelne Munitionspositionen
CREATE TABLE IF NOT EXISTS `munitionskauf_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bestellung_id` int(11) NOT NULL,
  `typ` varchar(50) NOT NULL COMMENT 'GP11_60, GP11_CUSTOM, GP90_50, GP90_CUSTOM',
  `anzahl` int(11) NOT NULL,
  `preis_pro_schuss` int(11) NOT NULL DEFAULT 50 COMMENT 'in Rappen',
  PRIMARY KEY (`id`),
  KEY `idx_bestellung` (`bestellung_id`),
  CONSTRAINT `fk_munitionskauf_details` FOREIGN KEY (`bestellung_id`) 
    REFERENCES `munitionskauf` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index für Performance
ALTER TABLE `munitionskauf` ADD INDEX `idx_stats` (`jahr`, `kauf_datum`, `total_preis`);
ALTER TABLE `munitionskauf` ADD INDEX `idx_gast` (`gast_name`);
