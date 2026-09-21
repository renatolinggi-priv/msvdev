-- Migration 060: Stammliste der OK-Mitglieder (Schlossturmschiessen, Helferabrechnung)
--
-- Wer dauerhaft zum Organisationskomitee gehört, wird hier hinterlegt (Mitglied per ID, Personen der
-- Partnervereine per Klartextname wie in einsatz_plan_slots.name_text). Beim Import, Kopieren und
-- Setzen einer Position erhält die Person automatisch das OK-Kennzeichen (slots.ok, Migration 058);
-- Funktionen mit Rolle «OK» zählen unabhängig davon immer als OK. Pflege im Editor-Dialog «OK-Mitglieder»
-- (Schalter «dauerhaft»). Logik: plan_helpers.inc.php ep_ok_*. Idempotent.

CREATE TABLE IF NOT EXISTS `einsatz_ok_personen` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `mitglied_id`  INT NULL,
  `name_text`    VARCHAR(100) NULL,
  `bemerkung`    VARCHAR(100) NULL,
  `erstellt_von` INT NULL,
  `erstellt_am`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_mitglied` (`mitglied_id`),
  UNIQUE KEY `uq_name` (`name_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
