-- Erstelle Tabelle für zusätzliche Schüsse (Munition)
-- Falls die Tabelle noch nicht existiert

CREATE TABLE IF NOT EXISTS `endstich_zusatz_schuss` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mitglied_id` int(11) NOT NULL,
  `jahr` int(4) NOT NULL,
  `typ` varchar(50) NOT NULL COMMENT 'GP11_60, GP90_50, GP11_CUSTOM, GP90_CUSTOM',
  `anzahl` int(11) NOT NULL DEFAULT 0,
  `preis_cents` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mitglied_jahr` (`mitglied_id`, `jahr`),
  KEY `idx_jahr` (`jahr`),
  CONSTRAINT `fk_zusatz_mitglied` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Zusätzliche Schüsse (Munition) für Endschiessen';

-- Optional: Beispieldaten zum Testen (kannst du anpassen oder löschen)
-- INSERT INTO `endstich_zusatz_schuss` (`mitglied_id`, `jahr`, `typ`, `anzahl`, `preis_cents`, `created_by`) VALUES
-- (1, 2025, 'GP11_60', 60, 3000, 'admin'),
-- (1, 2025, 'GP90_CUSTOM', 30, 1500, 'admin'),
-- (2, 2025, 'GP90_50', 50, 2500, 'admin');
