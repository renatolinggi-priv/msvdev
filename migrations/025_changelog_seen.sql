-- Migration 025: Changelog "Was ist neu" pro Benutzer merken
-- Speichert die zuletzt im Portal als gesehen bestaetigte changelog.json-Version.
-- NULL = noch nie gesehen (wird beim ersten Portal-Besuch still auf die neuste
-- Version gesetzt, ohne Popup). Abwaertskompatibel.

ALTER TABLE `users`
    ADD COLUMN `changelog_seen_version` VARCHAR(20) DEFAULT NULL
    COMMENT 'Zuletzt im Portal als gesehen bestaetigte changelog.json-Version';
