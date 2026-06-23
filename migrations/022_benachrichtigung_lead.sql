-- Migration 022: Pro-Benutzer-Vorlaufzeit fuer Push-Benachrichtigungen
--
-- lead_tage = wie viele Tage VOR dem Termin die (einmalige) Erinnerung kommt.
-- Gilt fuer alle Themen. NULL = globale Standardwerte verwenden
-- (settings: push_lead_einsaetze/jm/umfragen/termine) -> bestehendes Verhalten
-- bleibt unveraendert, bis der Benutzer einen eigenen Wert speichert.

ALTER TABLE `benachrichtigung_prefs`
    ADD COLUMN IF NOT EXISTS `lead_tage` TINYINT NULL DEFAULT NULL AFTER `termine`;
