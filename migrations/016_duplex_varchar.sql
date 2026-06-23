-- Migration 016: Duplex-Spalte von TINYINT auf VARCHAR (fuer long-edge/short-edge)
ALTER TABLE print_profiles MODIFY COLUMN `duplex` VARCHAR(20) NOT NULL DEFAULT '';
