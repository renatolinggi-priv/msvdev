-- Migration 051: Einsatzplanung – Soll-Besetzung pro Termin und Funktion.
-- Im Layout Personen × Schichten (Wiler Chilbi) braucht der Donnerstag viele Helfer zum Aufstellen,
-- der Sonntag drei an der Bar: die feste Anzahl pro Funktion (einsatz_plan_funktionen.anzahl) reicht
-- dort nicht. Diese Tabelle hält je Termin × Funktion das Soll; Ist = Anzahl Slots. Fehlt eine Zeile,
-- gilt kein Soll (keine Anzeige «offen»). Idempotent.

CREATE TABLE IF NOT EXISTS `einsatz_plan_soll` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id`     INT NOT NULL,
  `termin_id`   INT NOT NULL,
  `funktion_id` INT NOT NULL,
  `soll`        TINYINT NOT NULL DEFAULT 0,
  UNIQUE KEY `uq_soll` (`termin_id`, `funktion_id`),
  KEY `idx_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
