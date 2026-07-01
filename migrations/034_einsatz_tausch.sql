-- Migration 034: Einsatz-Tausch / Übernahme zwischen Mitgliedern
--
-- Ein Mitglied (A) meldet, dass ein anderes Mitglied (B) seinen Arbeitseinsatz
-- übernimmt (typ='uebernahme') oder dass beide Einsätze tauschen (typ='tausch',
-- auch anlassübergreifend). Der Übernehmer (B) akzeptiert in der App -> erst
-- dann wird die Zuordnung in einsatz_zuweisungen umgeschrieben und der Vorstand
-- informiert. Kein Gating-Freigabeschritt durch den Vorstand.
--
-- Referenzen auf einsatz_zuweisungen.id / mitglieder.ID / users.id werden in der
-- API geprüft (KEINE FK-Constraints: Einsatz-Importe ersetzen Zeilen, und die
-- Tausch-Historie soll auch nach einem Re-Import als Audit erhalten bleiben).

CREATE TABLE IF NOT EXISTS `einsatz_tausch` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `typ`             ENUM('uebernahme','tausch') NOT NULL DEFAULT 'uebernahme',
    `einsatz_a_id`    INT NOT NULL,                    -- A's Einsatz (wird abgegeben) -> einsatz_zuweisungen.id
    `einsatz_b_id`    INT NULL,                        -- nur bei 'tausch': B's Einsatz (geht an A)
    `von_mitglied_id` INT NOT NULL,                    -- A (Initiator) -> mitglieder.ID
    `an_mitglied_id`  INT NOT NULL,                    -- B (Übernehmer / Tauschpartner) -> mitglieder.ID
    `nachricht`       VARCHAR(500) NULL,
    `status`          ENUM('offen','bestaetigt','abgelehnt','zurueckgezogen') NOT NULL DEFAULT 'offen',
    `erstellt_von`    INT NOT NULL,                    -- users.id von A
    `erstellt_am`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `entschieden_von` INT NULL,                        -- users.id von B (bei accept/decline)
    `entschieden_am`  DATETIME NULL,
    KEY `idx_an_status` (`an_mitglied_id`, `status`),
    KEY `idx_von_status` (`von_mitglied_id`, `status`),
    KEY `idx_einsatz_a` (`einsatz_a_id`),
    KEY `idx_einsatz_b` (`einsatz_b_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Themen-Schalter: Push rund um Einsatz-Tausch (Anfragen, Bestätigungen). Default an.
ALTER TABLE `benachrichtigung_prefs`
    ADD COLUMN IF NOT EXISTS `einsatz_tausch` TINYINT(1) NOT NULL DEFAULT 1;
