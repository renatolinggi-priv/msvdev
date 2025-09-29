-- Erweiterung der endstich_gaeste Tabelle für JS-Endschiessen
-- Fügt Geburtsdatum für Jungschützen hinzu

-- Prüfe ob Spalte bereits existiert und füge sie nur hinzu wenn nicht vorhanden
ALTER TABLE `endstich_gaeste` 
ADD COLUMN IF NOT EXISTS `geburtsdatum` DATE NULL AFTER `name`,
ADD COLUMN IF NOT EXISTS `vorname` VARCHAR(100) NULL AFTER `geburtsdatum`,
ADD COLUMN IF NOT EXISTS `nachname` VARCHAR(100) NULL AFTER `vorname`;

-- Index für bessere Performance
ALTER TABLE `endstich_gaeste` 
ADD INDEX IF NOT EXISTS `idx_geburtsdatum` (`geburtsdatum`);

-- Stelle sicher dass die JS-Stiche in der endstich_definition existieren
-- Falls noch nicht vorhanden, füge sie ein
INSERT INTO `endstich_definition` (`code`, `name`, `shots`, `price_cents`, `sort_order`, `active`) VALUES
('END', 'Endstich', 10, 2000, 10, 1),
('SCHWINI_P1', 'Schwini Passe 1', 10, 2000, 20, 1),
('SCHWINI_P2', 'Schwini Passe 2', 10, 2000, 30, 1),
('ZABIG', 'Zabigstich', 10, 2000, 60, 1)
ON DUPLICATE KEY UPDATE 
    shots = VALUES(shots),
    active = 1;

-- Info für Admin
SELECT 'JS-Endschiessen Tabellen-Erweiterung erfolgreich durchgeführt' as Status;
