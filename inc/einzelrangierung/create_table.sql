-- Tabelle für Einzelrangierungen von Mitgliedern
CREATE TABLE IF NOT EXISTS einzelrangierungen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL,
    jmdefinition_id INT NOT NULL,
    mitglied_id INT NOT NULL,
    rang INT NOT NULL,
    preis DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Key Constraints
    CONSTRAINT fk_einzelrangierung_jmdefinition 
        FOREIGN KEY (jmdefinition_id) REFERENCES JMDefinition(ID) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    CONSTRAINT fk_einzelrangierung_mitglied 
        FOREIGN KEY (mitglied_id) REFERENCES mitglieder(ID) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Unique Constraint: Ein Mitglied kann pro Jahr und Anlass nur eine Rangierung haben
    UNIQUE KEY uk_einzelrangierung_year_definition_mitglied (year, jmdefinition_id, mitglied_id),
    
    -- Indizes für bessere Performance
    INDEX idx_einzelrangierung_year (year),
    INDEX idx_einzelrangierung_jmdefinition (jmdefinition_id),
    INDEX idx_einzelrangierung_mitglied (mitglied_id),
    INDEX idx_einzelrangierung_rang (rang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kommentare für bessere Dokumentation
ALTER TABLE einzelrangierungen 
COMMENT = 'Einzelrangierungen von Mitgliedern bei JM-Anlässen';

ALTER TABLE einzelrangierungen 
MODIFY COLUMN year INT NOT NULL COMMENT 'Jahr der Rangierung',
MODIFY COLUMN jmdefinition_id INT NOT NULL COMMENT 'Referenz zum JM-Anlass',
MODIFY COLUMN mitglied_id INT NOT NULL COMMENT 'Referenz zum Mitglied',
MODIFY COLUMN rang INT NOT NULL COMMENT 'Platzierung (1, 2, 3, etc.)',
MODIFY COLUMN preis DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preis in CHF';