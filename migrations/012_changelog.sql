-- Migration 012: Changelog / Activity Log
-- Zentrale Tabelle für alle loggbaren Änderungen im System
-- Wird von changelog_helper.php beschrieben und von api/changelog.php gelesen

CREATE TABLE IF NOT EXISTS `changelog` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `kategorie` VARCHAR(30) NOT NULL COMMENT 'resultate, termine, definition, standbelegung',
  `aktion` VARCHAR(20) NOT NULL COMMENT 'erstellt, aktualisiert, geloescht',
  `beschreibung` VARCHAR(500) NOT NULL COMMENT 'Menschenlesbare Beschreibung',
  `tabelle` VARCHAR(50) DEFAULT NULL COMMENT 'Betroffene DB-Tabelle',
  `jahr` INT DEFAULT NULL COMMENT 'Betroffenes Jahr',
  `details` TEXT DEFAULT NULL COMMENT 'Optionales JSON fuer Zusatzinfos',
  `sichtbar` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=oeffentlich, 0=nur intern (JM-Resultate)',
  `user_id` INT DEFAULT NULL COMMENT 'Session user_id',
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sichtbar_erstellt` (`sichtbar`, `erstellt_am`),
  KEY `idx_kategorie` (`kategorie`),
  KEY `idx_jahr` (`jahr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
