-- Migration 050: Einsatzplanung – Arbeitseinsätze (Obligatorisch / Feldschiessen / Wiler Chilbi)
-- werden in der App erfasst statt aus Word/Excel importiert.
--
-- Modell: ein Plan pro Anlass und Jahr; Termine (Spalten), Funktionen (Zeilen) und Slots
-- (eine Position = Funktion × Termin × Pos). Ein Slot gehört einem Verein (MSV Wilen,
-- SV Freienbach, SV Wollerau) und trägt entweder ein Mitglied oder einen Klartextnamen.
-- Für die Wiler Chilbi (Layout person_x_schicht) ist Termin = Schicht, Funktion = Einsatztyp.
--
-- einsatz_zuweisungen bleibt die Lesetabelle für Portal, Tausch, Cron und ICS-Feed und wird
-- aus den Slots projiziert (Schlüssel slot_id). Die Tabelle wird hier erstmals versioniert
-- (sie existierte bisher nur im DB-Dump).
-- Idempotent: CREATE/ADD IF NOT EXISTS, Nav-Eintrag mit Vorhanden-Prüfung.

CREATE TABLE IF NOT EXISTS `einsatz_zuweisungen` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `typ`           VARCHAR(50) NOT NULL,
  `bezeichnung`   VARCHAR(100) NOT NULL,
  `event_datum`   DATE NOT NULL,
  `event_zeit`    VARCHAR(30) DEFAULT NULL,
  `funktion`      VARCHAR(100) NOT NULL,
  `mitglied_name` VARCHAR(100) NOT NULL,
  `mitglied_id`   INT(11) DEFAULT NULL,
  `jahr`          INT(11) NOT NULL,
  `dokument_id`   INT(11) DEFAULT NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mitglied` (`mitglied_id`, `event_datum`),
  KEY `idx_datum` (`event_datum`),
  KEY `idx_dokument` (`dokument_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `einsatz_zuweisungen`
  ADD COLUMN IF NOT EXISTS `slot_id` INT NULL AFTER `dokument_id`,
  ADD UNIQUE KEY IF NOT EXISTS `uq_slot` (`slot_id`);

CREATE TABLE IF NOT EXISTS `einsatz_plaene` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `jahr`               YEAR(4) NOT NULL,
  `typ`                ENUM('obligatorisch','feldschiessen','chilbi','sonstiges') NOT NULL DEFAULT 'sonstiges',
  `titel`              VARCHAR(100) NOT NULL,
  `layout`             ENUM('funktion_x_termin','person_x_schicht') NOT NULL DEFAULT 'funktion_x_termin',
  `status`             ENUM('entwurf','freigegeben','final') NOT NULL DEFAULT 'entwurf',
  `fusstext`           TEXT NULL,
  `vorlage_plan_id`    INT NULL,
  `quelle_dokument_id` INT NULL,
  `freigegeben_am`     DATETIME NULL,
  `erstellt_von`       INT NULL,
  `erstellt_am`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_jahr_typ` (`jahr`, `typ`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `einsatz_plan_termine` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id`     INT NOT NULL,
  `bezeichnung` VARCHAR(100) NULL,
  `datum`       DATE NOT NULL,
  `zeit_von`    TIME NULL,
  `zeit_bis`    TIME NULL,
  `zeit_text`   VARCHAR(30) NULL,
  `sort`        INT NOT NULL DEFAULT 0,
  KEY `idx_plan` (`plan_id`, `datum`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `einsatz_plan_funktionen` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id`     INT NOT NULL,
  `gruppe`      VARCHAR(50) NULL,
  `bezeichnung` VARCHAR(100) NOT NULL,
  `anzahl`      TINYINT NOT NULL DEFAULT 1,
  `sort`        INT NOT NULL DEFAULT 0,
  KEY `idx_plan` (`plan_id`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `einsatz_plan_slots` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id`     INT NOT NULL,
  `termin_id`   INT NOT NULL,
  `funktion_id` INT NOT NULL,
  `pos`         TINYINT NOT NULL DEFAULT 1,
  `verein`      ENUM('msv','freienbach','wollerau') NOT NULL DEFAULT 'msv',
  `mitglied_id` INT NULL,
  `name_text`   VARCHAR(100) NULL,
  `bemerkung`   VARCHAR(100) NULL,
  UNIQUE KEY `uq_slot_pos` (`termin_id`, `funktion_id`, `pos`),
  KEY `idx_plan` (`plan_id`),
  KEY `idx_mitglied` (`mitglied_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Admin-Navigation: «Einsatzplanung» im Block Stammdaten direkt nach «Dokumente verwalten»
INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie)
SELECT 'Einsatzplanung', 'einsatzplanung.php', x.parent_id, 25, 'bi-person-lines-fill', 0
FROM (
    SELECT
        (SELECT ID FROM navigation WHERE Text = 'Definitionen / Ausdrucke' AND ParentID = 0 ORDER BY ID LIMIT 1) AS parent_id,
        (SELECT COUNT(*) FROM navigation WHERE Link = 'einsatzplanung.php')                                       AS vorhanden
) AS x
WHERE x.parent_id IS NOT NULL AND x.vorhanden = 0;
