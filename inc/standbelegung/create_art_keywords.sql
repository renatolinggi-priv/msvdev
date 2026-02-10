-- Tabelle für Schützenfest-Keywords (SF-Erkennung)
CREATE TABLE IF NOT EXISTS Standbelegung_ArtKeywords (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    Keyword VARCHAR(100) NOT NULL,
    Art VARCHAR(10) NOT NULL DEFAULT 'SF',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_keyword (Keyword)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Standard Keywords einfügen
INSERT IGNORE INTO Standbelegung_ArtKeywords (Keyword, Art) VALUES
-- Schützenfeste (SF)
('Schlossturmschiessen', 'SF'),
('Winterschiessen', 'SF'),
('Veteranenmeisterschaft', 'SF'),
('Schützenfest', 'SF'),
('Endschiessen', 'SF'),
('Kantonalschiessen', 'SF'),
('Bezirksschiessen', 'SF'),
('Gruppenschiessen', 'SF'),
('Vereinsmeisterschaft', 'SF'),
('Herbstschiessen', 'SF'),
('Frühlingsschiessen', 'SF'),
('Jahresmeisterschaft', 'SF'),
-- Feldschiessen (FS)
('Feldschiessen', 'FS'),
('Eidgenössisches Feldschiessen', 'FS');

-- Art-Codes Referenz:
-- SF = Schützenfest
-- FS = Feldschiessen
-- OP = Obligatorisches Programm (Bundesprogramm)
-- WK = Wettkampf/Match
-- JSK = Jungschützenkurs
-- TR = Training
-- VS = Versammlung
