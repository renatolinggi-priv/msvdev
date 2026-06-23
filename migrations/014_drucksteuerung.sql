-- Migration 014: Drucksteuerung-Tabellen (QZ Tray)
-- Drucker, Druckprofile, Druckauftraege

CREATE TABLE IF NOT EXISTS `printers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `benutzer_id` INT NOT NULL,
    `machine_id` VARCHAR(64) DEFAULT NULL COMMENT 'Mehrplatz-Trennung (UUID aus localStorage)',
    `name` VARCHAR(255) NOT NULL COMMENT 'Systemname des Druckers',
    `anzeigename` VARCHAR(255) DEFAULT NULL,
    `typ` ENUM('laser','tintenstrahl','etiketten','bon','sonstige') NOT NULL DEFAULT 'laser',
    `beschreibung` TEXT DEFAULT NULL,
    `ist_standard` TINYINT(1) NOT NULL DEFAULT 0,
    `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
    `erstellt_am` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_benutzer` (`benutzer_id`),
    INDEX `idx_machine` (`benutzer_id`, `machine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `print_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `benutzer_id` INT NOT NULL,
    `machine_id` VARCHAR(64) DEFAULT NULL,
    `doc_type` VARCHAR(100) NOT NULL COMMENT 'Dokumenttyp-Schluessel (z.B. kranzkarte, rangliste)',
    `anzeigename` VARCHAR(255) DEFAULT NULL,
    `printer_id` INT DEFAULT NULL,
    `print_mode` ENUM('pixel','raw') NOT NULL DEFAULT 'pixel',
    `copies` TINYINT NOT NULL DEFAULT 1,
    `paper_size` VARCHAR(50) DEFAULT 'A4',
    `orientation` ENUM('portrait','landscape') DEFAULT 'portrait',
    `color_mode` ENUM('color','grayscale','blackwhite') DEFAULT 'blackwhite',
    `duplex` TINYINT(1) NOT NULL DEFAULT 0,
    `optionen` JSON DEFAULT NULL COMMENT 'Seitenspezifische Extras als JSON',
    `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
    `erstellt_am` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_benutzer` (`benutzer_id`),
    INDEX `idx_doc_type` (`benutzer_id`, `doc_type`),
    INDEX `idx_machine` (`benutzer_id`, `machine_id`),
    FOREIGN KEY (`printer_id`) REFERENCES `printers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `print_jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `benutzer_id` INT DEFAULT NULL,
    `machine_id` VARCHAR(64) DEFAULT NULL,
    `doc_type` VARCHAR(100) DEFAULT NULL,
    `printer_name` VARCHAR(255) DEFAULT NULL,
    `dateiname` VARCHAR(500) DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'gesendet',
    `fehler_text` TEXT DEFAULT NULL,
    `copies` TINYINT NOT NULL DEFAULT 1,
    `erstellt_am` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_benutzer` (`benutzer_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_erstellt` (`erstellt_am`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
