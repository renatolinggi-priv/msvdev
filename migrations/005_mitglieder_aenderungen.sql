-- Migration 005: Audit-Trail für Mitglieder-Datenänderungen
-- Tabelle: mitglieder_aenderungen

CREATE TABLE IF NOT EXISTS `mitglieder_aenderungen` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `mitglied_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `feld` VARCHAR(50) NOT NULL,
  `alter_wert` VARCHAR(255) DEFAULT NULL,
  `neuer_wert` VARCHAR(255) DEFAULT NULL,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mitglied` (`mitglied_id`),
  KEY `idx_datum` (`geaendert_am`),
  CONSTRAINT `fk_aenderungen_mitglied` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_aenderungen_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
