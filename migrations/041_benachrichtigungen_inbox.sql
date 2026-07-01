-- Migration 041: In-App-Benachrichtigungen (Glocke) + Vorstand-Broadcast
--
-- Persistenter Verlauf JEDER zugestellten Benachrichtigung pro Benutzer. Bisher
-- wurde Push nur "fire-and-forget" verschickt (push_abos / push_geraete_native) und
-- benachrichtigung_log diente ausschliesslich der Cron-Deduplizierung. Diese Tabelle
-- speist die neue Glocke (Ungelesen-Badge + Dropdown + portal/mitteilungen.php) und
-- nimmt auch die manuell vom Vorstand gesendeten Mitteilungen (kategorie='mitteilung')
-- auf.
--
-- Geschrieben wird ueber benachrichtigungZustellen() in inc/push_helper.php: der
-- In-App-Eintrag entsteht IMMER (unabhaengig von Geraeten/Prefs), der Push nur bei
-- aktivem push_aktiv.

CREATE TABLE IF NOT EXISTS `benachrichtigungen_inbox` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT NOT NULL,
    `titel`       VARCHAR(150) NOT NULL,
    `text`        VARCHAR(500) NOT NULL,
    `url`         VARCHAR(255) DEFAULT NULL,
    `kategorie`   VARCHAR(30)  NOT NULL DEFAULT 'allgemein',  -- chat|einsaetze|jm|umfragen|termine|fotos|jsk_betreuung|einsatz_tausch|mitteilung|allgemein
    `gelesen_am`  DATETIME DEFAULT NULL,
    `erstellt_am` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `ix_inbox_user`     (`user_id`, `gelesen_am`),
    KEY `ix_inbox_erstellt` (`erstellt_am`),
    CONSTRAINT `fk_inbox_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
