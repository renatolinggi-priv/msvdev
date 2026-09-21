-- Migration 058: Helferabrechnung (Schlossturmschiessen) auf Basis der Einsatzplanung
--
-- Die Abrechnung der Helferstunden pro Verein (SV Freienbach / MSV Wilen / SV Wollerau) rechnet
-- direkt auf den Slots der Einsatzplanung (einsatz_plan_slots), nicht auf einsatz_zuweisungen –
-- dort stehen nur MSV-Mitglieder. Neu:
--   einsatz_plan_termine.pauschale_std  abgerechnete Stunden je Schicht (Sa 5.00, So 3.00);
--                                       NULL = Dauer aus zeit_von/zeit_bis (bisheriges Verhalten)
--   einsatz_plan_slots.ok               Position vom OK besetzt (im Original «OK» statt «x»);
--                                       zählt nur mit Schalter «OK-Einsätze mitzählen»
--   einsatz_plan_slots.stunden_korrektur überschreibt die Pauschale für genau diese Position
--   einsatz_abr_zeilen                  manuelle Zeilen: Vor-/Nacharbeiten, OK-Funktionen, Nachträge
-- Rechenlogik: inc/helferabrechnung/abrechnung.inc.php (ep_termin_stunden() in plan_helpers.inc.php).
-- Idempotent. Pauschalen und OK-Kennzeichen werden über die Seite «Helferabrechnung» gesetzt.

ALTER TABLE `einsatz_plan_termine`
  ADD COLUMN IF NOT EXISTS `pauschale_std` DECIMAL(4,2) NULL AFTER `zeit_text`;

ALTER TABLE `einsatz_plan_slots`
  ADD COLUMN IF NOT EXISTS `ok`                TINYINT(1)   NOT NULL DEFAULT 0 AFTER `bemerkung`,
  ADD COLUMN IF NOT EXISTS `stunden_korrektur` DECIMAL(4,2) NULL AFTER `ok`;

CREATE TABLE IF NOT EXISTS `einsatz_abr_zeilen` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id`      INT NOT NULL,
  `kategorie`    ENUM('einsatz','vorarbeit','ok_funktion') NOT NULL DEFAULT 'vorarbeit',
  `taetigkeit`   VARCHAR(150) NOT NULL,
  `verein`       ENUM('msv','freienbach','wollerau') NOT NULL DEFAULT 'msv',
  `mitglied_id`  INT NULL,
  `name_text`    VARCHAR(100) NULL,
  `stunden`      DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `bemerkung`    VARCHAR(255) NULL,
  `sort`         INT NOT NULL DEFAULT 0,
  `erstellt_von` INT NULL,
  `erstellt_am`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `geaendert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_plan` (`plan_id`, `kategorie`, `verein`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Admin-Navigation: «Helferabrechnung» im Block Auswertung (nach «Wanderpreis-Regeln», SortOrder 160)
INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie)
SELECT 'Helferabrechnung', 'helferabrechnung.php', x.parent_id, 170, 'bi-calculator', 0
FROM (
    SELECT
        (SELECT ID FROM navigation WHERE Text = 'Definitionen / Ausdrucke' AND ParentID = 0 ORDER BY ID LIMIT 1) AS parent_id,
        (SELECT COUNT(*) FROM navigation WHERE Link = 'helferabrechnung.php')                                     AS vorhanden
) AS x
WHERE x.parent_id IS NOT NULL AND x.vorhanden = 0;
