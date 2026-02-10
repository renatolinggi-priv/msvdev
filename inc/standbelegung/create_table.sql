-- Standbelegung Tabelle
-- Führe dieses Script in phpMyAdmin aus

CREATE TABLE IF NOT EXISTS `Standbelegung` (
    `ID` INT(11) NOT NULL AUTO_INCREMENT,
    `Datum` DATE NOT NULL,
    `Wochentag` VARCHAR(2) DEFAULT NULL,
    `Bezeichnung` VARCHAR(255) NOT NULL,
    `StartZeit` TIME DEFAULT NULL,
    `EndZeit` TIME DEFAULT NULL,
    `Kategorie` VARCHAR(50) DEFAULT 'Sonstiges',
    `Jahr` YEAR NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ID`),
    INDEX `idx_kategorie` (`Kategorie`),
    INDEX `idx_jahr` (`Jahr`),
    INDEX `idx_datum` (`Datum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beispiel-Abfragen:

-- Alle 300m Termine für ein Jahr:
-- SELECT * FROM Standbelegung WHERE Jahr = 2025 AND Kategorie = '300m' ORDER BY Datum;

-- Alle Termine eines Monats:
-- SELECT * FROM Standbelegung WHERE Jahr = 2025 AND MONTH(Datum) = 4 ORDER BY Datum;

-- Termine für Kalender-Export:
-- SELECT Datum, Bezeichnung, StartZeit, EndZeit, Kategorie 
-- FROM Standbelegung 
-- WHERE Jahr IN (2025, 2026) 
-- ORDER BY Datum;
