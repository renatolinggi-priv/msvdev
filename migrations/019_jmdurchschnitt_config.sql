-- Migration 019: JM-Durchschnitt - Anzahl zaehlende Resultate pro Jahr
-- Bisher war die Anzahl der zaehlenden Resultate (6) hardcodiert.
-- Wird jetzt pro Jahr in einer eigenen Tabelle gespeichert. Fehlt der
-- Eintrag fuer ein Jahr, wird der Wert des Vorjahres uebernommen (Fallback 6).

CREATE TABLE IF NOT EXISTS jmdurchschnitt_config (
    year INT NOT NULL,
    anzahl_zaehlende INT NOT NULL DEFAULT 6,
    PRIMARY KEY (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
