-- Tabellen für Jungschützen-Endschloesen
-- Vereinfachte Version nur mit Gästen und festem Paket

-- Tabelle für die Stich-Definitionen (nur die benötigten)
CREATE TABLE IF NOT EXISTS jsendschloesen_stiche (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    preis DECIMAL(10, 2) NOT NULL,
    anzahl_schuss INT NOT NULL,
    aktiv BOOLEAN DEFAULT TRUE,
    reihenfolge INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standard-Stiche einfügen
INSERT INTO jsendschloesen_stiche (name, preis, anzahl_schuss, reihenfolge) VALUES
('Endstich', 20.00, 10, 1),
('Schwini Passe 1', 20.00, 5, 2),
('Schwini Passe 2', 16.00, 5, 3),
('Zabigstich', 19.00, 5, 4)
ON DUPLICATE KEY UPDATE 
    preis = VALUES(preis),
    anzahl_schuss = VALUES(anzahl_schuss),
    reihenfolge = VALUES(reihenfolge);

-- Tabelle für Gast-Anmeldungen
CREATE TABLE IF NOT EXISTS jsendschloesen_gaeste (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vorname VARCHAR(100) NOT NULL,
    nachname VARCHAR(100) NOT NULL,
    jahrgang INT NOT NULL,
    verein VARCHAR(200),
    lizenz_nr VARCHAR(50),
    jahr INT NOT NULL,
    paket_geloest BOOLEAN DEFAULT FALSE,
    munition_gp11 INT DEFAULT 0,
    munition_gp90 INT DEFAULT 0,
    total_preis DECIMAL(10, 2) DEFAULT 0.00,
    bemerkung TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_jahr (jahr),
    INDEX idx_name (nachname, vorname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
