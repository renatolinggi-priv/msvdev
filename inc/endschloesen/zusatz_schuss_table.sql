-- SQL für zusätzliche Schüsse Tabelle
-- Führe dieses SQL in phpMyAdmin aus

-- Tabelle für zusätzliche Schuss-Pakete
CREATE TABLE IF NOT EXISTS `endstich_zusatz_schuss` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `mitglied_id` int(11) NOT NULL,
  `jahr` int(11) NOT NULL,
  `typ` ENUM('GP11_60', 'GP90_50', 'GP11_CUSTOM', 'GP90_CUSTOM') NOT NULL,
  `anzahl` int(11) NOT NULL DEFAULT 0,
  `preis_cents` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mitglied_jahr` (`mitglied_id`, `jahr`),
  CONSTRAINT `fk_zusatz_mitglied` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Index für bessere Performance
CREATE INDEX idx_zusatz_jahr ON endstich_zusatz_schuss(jahr);
