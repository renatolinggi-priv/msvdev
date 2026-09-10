-- Migration 045: Endschiessen lösen – Schema fest verankern
--
-- Bisher legte inc/endschloesen/endschloesen_api.php Tabellen und Spalten ZUR LAUFZEIT an
-- (SHOW TABLES / SHOW COLUMNS + CREATE/ALTER TABLE bei jedem Speichern, dazu
-- SET FOREIGN_KEY_CHECKS = 0). Diese Migration bildet den produktiven Stand vom
-- 09.09.2026 ab, damit der Laufzeit-Code entfallen kann. Auf Produktion ist sie ein
-- No-op (alles vorhanden); auf einer frischen Datenbank erzeugt sie die Struktur.
-- Alle Statements sind idempotent (IF NOT EXISTS / INSERT IGNORE).

CREATE TABLE IF NOT EXISTS `endstich_gaeste` (
  `id`           int(11) NOT NULL AUTO_INCREMENT,
  `name`         varchar(200) NOT NULL COMMENT 'Name des Gastes',
  `mitgliedernr` int(11) DEFAULT NULL,
  `geburtsdatum` date DEFAULT NULL,
  `waffen_id`    int(11) DEFAULT NULL,
  `vorname`      varchar(100) DEFAULT NULL,
  `nachname`     varchar(100) DEFAULT NULL,
  `jahr`         int(4) NOT NULL,
  `created_at`   timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by`   varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_gast_jahr` (`name`, `jahr`),
  UNIQUE KEY `idx_mitgliedernr` (`mitgliedernr`),
  KEY `idx_jahr` (`jahr`),
  KEY `idx_name` (`name`),
  KEY `idx_geburtsdatum` (`geburtsdatum`),
  KEY `fk_gast_waffe` (`waffen_id`),
  CONSTRAINT `fk_gast_waffe` FOREIGN KEY (`waffen_id`) REFERENCES `Waffen` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Gäste/Partner für Endschiessen';

CREATE TABLE IF NOT EXISTS `endstich_spezialpreise` (
  `id`           int(11) NOT NULL AUTO_INCREMENT,
  `typ`          varchar(50) NOT NULL,
  `price_cents`  int(11) NOT NULL,
  `beschreibung` varchar(200) DEFAULT NULL,
  `sort_order`   int(11) DEFAULT 100,
  `active`       tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_typ` (`typ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `endstich_zusatz_schuss` (
  `id`          bigint(20) NOT NULL AUTO_INCREMENT,
  `mitglied_id` int(11) DEFAULT NULL,
  `gast_id`     int(11) DEFAULT NULL,
  `jahr`        int(11) NOT NULL,
  `typ`         enum('GP11_60','GP90_50','GP11_CUSTOM','GP90_CUSTOM') NOT NULL,
  `anzahl`      int(11) NOT NULL DEFAULT 0,
  `preis_cents` int(11) NOT NULL DEFAULT 0,
  `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by`  varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mitglied_jahr` (`mitglied_id`, `jahr`),
  KEY `idx_zusatz_jahr` (`jahr`),
  KEY `idx_zusatz_gast` (`gast_id`),
  CONSTRAINT `fk_zusatz_gast` FOREIGN KEY (`gast_id`) REFERENCES `endstich_gaeste` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_zusatz_mitglied` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- endstich_selection: Gast-Fähigkeit und Zusatzspalten (früher per Laufzeit-ALTER)
ALTER TABLE `endstich_selection` MODIFY COLUMN `mitglied_id` int(11) DEFAULT NULL;
ALTER TABLE `endstich_selection` ADD COLUMN IF NOT EXISTS `gast_id` int(11) DEFAULT NULL AFTER `mitglied_id`;
ALTER TABLE `endstich_selection` ADD COLUMN IF NOT EXISTS `zahlungsmethode` varchar(20) DEFAULT 'bar' AFTER `stich_id`;
ALTER TABLE `endstich_selection` ADD COLUMN IF NOT EXISTS `gast_spezialpreis` int(11) DEFAULT NULL AFTER `zahlungsmethode`;
ALTER TABLE `endstich_selection` ADD COLUMN IF NOT EXISTS `sie_und_er` tinyint(1) DEFAULT 0 AFTER `gast_spezialpreis`;
ALTER TABLE `endstich_selection` ADD INDEX IF NOT EXISTS `idx_gast` (`gast_id`);
-- Fremdschluessel fk_selection_gast (gast_id -> endstich_gaeste.id) besteht auf Produktion bereits.
-- MariaDB kennt fuer ADD CONSTRAINT ... FOREIGN KEY kein IF NOT EXISTS, darum hier nicht erneut angelegt;
-- auf einer frischen Datenbank von Hand: ALTER TABLE endstich_selection ADD CONSTRAINT fk_selection_gast
--   FOREIGN KEY (gast_id) REFERENCES endstich_gaeste (id) ON DELETE CASCADE;

-- Spezialpreise: Stand Produktion 09.09.2026 (bestehende Werte bleiben unangetastet)
INSERT IGNORE INTO `endstich_spezialpreise` (`typ`, `price_cents`, `beschreibung`, `sort_order`, `active`) VALUES
  ('munition_pro_schuss',   50, 'Preis pro Schuss Munition',                                          10, 1),
  ('gast_kombi_2',        3500, 'Gäste: 2 Stiche aus End/Schwini',                                    20, 1),
  ('gast_kombi_3',        4900, 'Gäste: 3 Stiche aus End/Schwini',                                    30, 1),
  ('gast_sie_und_er',     1000, 'Gäste: Sie und Er Stich',                                            40, 1),
  ('partner_zabig',       1000, 'Partner-Preis für Zabigstich',                                       50, 1),
  ('munition_gp11_60',    3000, 'Standard-Paket: 60 Schuss GP11',                                     60, 1),
  ('munition_gp90_50',    2500, 'Standard-Paket: 50 Schuss GP90',                                     70, 1),
  ('js_paket_preis',         0, 'JS-Paket: Endstich 10 + Probe 3 + Schwini 8 + Zabig 6 = 27 Schuss', 100, 1);
