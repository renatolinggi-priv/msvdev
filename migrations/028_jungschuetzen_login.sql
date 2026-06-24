-- Migration 028: Jungschuetzen-Login + Stammdaten-Erweiterung
--
-- Voraussetzung fuer die Jungschuetzen-Betreuung ("Tinder fuer JSK"):
--   1. jungschuetzen bekommt Kontaktfelder (Email/Mobile) fuer die
--      Selbstregistrierung per Mailadresse, plus Aktiv-Flag und Kursjahr.
--   2. Bisherige Pflichtfelder werden gelockert, damit Import (SSV-Verzeichnis)
--      und manuelle Erfassung ohne AHV/komplette Adresse moeglich sind.
--      AHVNummer bleibt UNIQUE (MariaDB erlaubt mehrere NULL-Werte).
--   3. users bekommt die Rolle 'jungschuetze' + Verknuepfung jungschuetze_id.

ALTER TABLE `jungschuetzen`
    MODIFY `AHVNummer`    VARCHAR(16)  NULL DEFAULT NULL,
    MODIFY `Geburtsdatum` DATE         NULL DEFAULT NULL,
    MODIFY `Strasse`      VARCHAR(255) NULL DEFAULT NULL,
    MODIFY `PLZ`          VARCHAR(10)  NULL DEFAULT NULL,
    MODIFY `Ort`          VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `Email`    VARCHAR(255) NULL DEFAULT NULL AFTER `Ort`,
    ADD COLUMN IF NOT EXISTS `Mobile`   VARCHAR(50)  NULL DEFAULT NULL AFTER `Email`,
    ADD COLUMN IF NOT EXISTS `KursJahr` SMALLINT     NULL DEFAULT NULL AFTER `KursNummer`,
    ADD COLUMN IF NOT EXISTS `Aktiv`    TINYINT(1)   NOT NULL DEFAULT 1 AFTER `KursJahr`;

ALTER TABLE `jungschuetzen`
    ADD INDEX IF NOT EXISTS `ix_js_email` (`Email`);

ALTER TABLE `users`
    MODIFY `role` ENUM('admin','vorstand','mitglied','jungschuetze') NOT NULL DEFAULT 'mitglied',
    ADD COLUMN IF NOT EXISTS `jungschuetze_id` INT(11) DEFAULT NULL COMMENT 'Verknuepfung zu jungschuetzen.id' AFTER `mitglied_id`;

ALTER TABLE `users`
    ADD CONSTRAINT `fk_users_jungschuetze` FOREIGN KEY (`jungschuetze_id`)
        REFERENCES `jungschuetzen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
