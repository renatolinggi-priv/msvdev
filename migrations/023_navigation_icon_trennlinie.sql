-- Migration 023: Navigation - Icon + Trennlinie
-- Ermoeglicht Bootstrap-Icons pro Menueeintrag und horizontale Trennlinien
-- (Divider) in Dropdown-Menues. Beide Spalten sind abwaertskompatibel:
-- Icon NULL = kein Icon, IstTrennlinie 0 = normaler Eintrag.
--
-- Hinweis: nav_api.php legt diese Spalten zur Laufzeit ebenfalls an
-- (self-healing via SHOW COLUMNS), diese Migration dokumentiert das Schema.

ALTER TABLE navigation
    ADD COLUMN Icon VARCHAR(50) NULL DEFAULT NULL AFTER Link;

ALTER TABLE navigation
    ADD COLUMN IstTrennlinie TINYINT NOT NULL DEFAULT 0 AFTER SortOrder;
