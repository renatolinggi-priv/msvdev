-- Migration 011: Kategorie-Feld für Umfragen
-- Ermöglicht Kategorisierung: umfrage, arbeitseinsatz, helfer, etc.

ALTER TABLE umfragen ADD COLUMN kategorie VARCHAR(50) NOT NULL DEFAULT 'umfrage' AFTER zielgruppe;
