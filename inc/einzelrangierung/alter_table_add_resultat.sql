-- Alter Table: Resultat-Feld zur einzelrangierungen Tabelle hinzufügen
ALTER TABLE einzelrangierungen 
ADD COLUMN resultat VARCHAR(20) NULL COMMENT 'Erreichtes Resultat (z.B. Punkte, Ringe)' 
AFTER preis;

-- Index für bessere Performance bei Abfragen
ALTER TABLE einzelrangierungen 
ADD INDEX idx_einzelrangierung_resultat (resultat);

-- Update der Kommentare
ALTER TABLE einzelrangierungen 
COMMENT = 'Einzelrangierungen von Mitgliedern bei JM-Anlässen mit Resultat';