-- Migration 015: CSV-Schnittstelle (Schiessanlage shooters.csv Export)
-- Neue Spalte mitgliedernr fuer Gaeste/JS, Settings-Keys

-- Neue Spalte fuer synthetische Mitgliedernummer (Gaeste + Jungschuetzen)
ALTER TABLE endstich_gaeste ADD COLUMN mitgliedernr INT DEFAULT NULL AFTER name;
ALTER TABLE endstich_gaeste ADD UNIQUE INDEX idx_mitgliedernr (mitgliedernr);

-- Bestehende Eintraege befuellen
UPDATE endstich_gaeste SET mitgliedernr = 999000 + id WHERE mitgliedernr IS NULL;

-- Trigger: automatisch setzen bei neuen Gaesten/JS
DELIMITER //
CREATE TRIGGER trg_gaeste_mitgliedernr AFTER INSERT ON endstich_gaeste
FOR EACH ROW
BEGIN
    UPDATE endstich_gaeste SET mitgliedernr = 999000 + NEW.id WHERE id = NEW.id AND mitgliedernr IS NULL;
END//
DELIMITER ;

-- Settings-Tabelle (Key-Value, falls noch nicht vorhanden)
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `setting_value` TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings fuer CSV-Export
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('csv_export_aktiv', '0'),
    ('csv_pfad_shooters', '');
