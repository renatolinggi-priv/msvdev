-- Feld InKalender zur Standbelegung-Tabelle hinzufügen
ALTER TABLE Standbelegung 
ADD COLUMN InKalender TINYINT(1) NOT NULL DEFAULT 0 AFTER Kategorie;

-- Index für Kalender-Abfragen
CREATE INDEX idx_inkalender ON Standbelegung (InKalender, Jahr, Datum);

-- Unique Index für Upsert (verhindert Duplikate beim Import)
-- Basierend auf Datum + Bezeichnung + StartZeit + Jahr
-- (gleiche Bezeichnung am gleichen Tag mit unterschiedlicher Zeit ist erlaubt)
ALTER TABLE Standbelegung 
ADD UNIQUE INDEX idx_unique_entry (Datum, Bezeichnung, StartZeit, Jahr);
