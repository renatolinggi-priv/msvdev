-- Migration 010: min_auswahl für Umfragen-Fragen (Soft-Warnung bei Checkbox-Fragen)
-- Ermöglicht eine Mindestanzahl Auswahlen pro Frage mit Warnhinweis (nicht blockierend)

ALTER TABLE umfragen_fragen ADD COLUMN min_auswahl INT DEFAULT NULL AFTER pflichtfeld;
