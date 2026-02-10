-- Bestehenden UNIQUE INDEX entfernen und neu erstellen mit StartZeit
-- Damit können gleiche Bezeichnungen am gleichen Tag mit unterschiedlichen Zeiten existieren

ALTER TABLE Standbelegung DROP INDEX idx_unique_entry;

ALTER TABLE Standbelegung 
ADD UNIQUE INDEX idx_unique_entry (Datum, Bezeichnung, StartZeit, Jahr);
