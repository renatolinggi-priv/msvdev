-- Migration 020: Cup-Einstellungen pro Jahr
-- KatBToFinal: Steuert, ob sich ein einzelner Kat.-B-Gewinner aus Runde 1
-- automatisch fuers Finale qualifiziert (1 = ein/wie bisher, 0 = aus).
-- Fehlt der Eintrag fuer ein Jahr, gilt der Default 1 (ein).

CREATE TABLE IF NOT EXISTS cupSettings (
    Year INT NOT NULL,
    KatBToFinal TINYINT NOT NULL DEFAULT 1,
    PRIMARY KEY (Year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
