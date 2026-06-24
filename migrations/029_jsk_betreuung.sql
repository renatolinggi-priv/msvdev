-- Migration 029: Jungschuetzen-Betreuung ("Tinder fuer JSK")
--
-- 1. jsk_betreuung_anfragen: ein Jungschuetze meldet sich fuer ein konkretes
--    Datum an (status 'offen'). Ein Mitglied uebernimmt (first-come) -> status
--    'vergeben' + betreut_von_user_id. Absage -> 'abgesagt', Vergangenheit ->
--    'erledigt' (per Filter/Cron).
-- 2. benachrichtigung_prefs.jsk_betreuung: Opt-In des Mitglieds, Jungschuetzen
--    betreuen zu wollen (steuert Board-Sichtbarkeit + Push). Default 0 = aus.
-- 3. settings.jsk_betreuung_aktiv: globaler Admin-Schalter fuer die ganze
--    Funktion. Default '0' = aus, Admin schaltet bewusst frei.

CREATE TABLE IF NOT EXISTS `jsk_betreuung_anfragen` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `jungschuetze_id`     INT NOT NULL,
    `datum`               DATE NOT NULL,
    `zeit`                VARCHAR(20)  DEFAULT NULL,
    `bemerkung`           VARCHAR(500) DEFAULT NULL,
    `status`              ENUM('offen','vergeben','abgesagt','erledigt') NOT NULL DEFAULT 'offen',
    `betreut_von_user_id` INT DEFAULT NULL,
    `betreut_am`          DATETIME DEFAULT NULL,
    `erstellt_am`         DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `ix_jsk_status_datum` (`status`, `datum`),
    KEY `ix_jsk_js` (`jungschuetze_id`),
    KEY `ix_jsk_betreuer` (`betreut_von_user_id`),
    CONSTRAINT `fk_jskanfrage_js` FOREIGN KEY (`jungschuetze_id`)
        REFERENCES `jungschuetzen` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_jskanfrage_betreuer` FOREIGN KEY (`betreut_von_user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `benachrichtigung_prefs`
    ADD COLUMN IF NOT EXISTS `jsk_betreuung` TINYINT(1) NOT NULL DEFAULT 0 AFTER `termine`;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('jsk_betreuung_aktiv', '0');
