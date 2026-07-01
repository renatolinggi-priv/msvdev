-- Migration 033: Native Push (FCM/APNs) fuer die native iOS-/Android-App
--
-- Laeuft PARALLEL zu Web-Push (push_abos, Migration 021): Browser/PWA nutzen
-- weiterhin push_abos (VAPID), die native App registriert hier ihren FCM-Token.
-- Der Versand in inc/push_helper.php bedient beide Kanaele.
--
-- FK-Ziel ist users.id (INT, PRIMARY KEY).

CREATE TABLE IF NOT EXISTS `push_geraete_native` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `benutzer_id`  INT NOT NULL,
    `fcm_token`    VARCHAR(255) NOT NULL,       -- FCM-Registration-Token (Android nativ, iOS via APNs)
    `platform`     ENUM('ios','android') DEFAULT NULL,
    `app_version`  VARCHAR(20) DEFAULT NULL,
    `erstellt_am`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_am`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_native_token` (`fcm_token`),
    KEY `ix_native_benutzer` (`benutzer_id`),
    CONSTRAINT `fk_native_benutzer` FOREIGN KEY (`benutzer_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FCM-Konfiguration (Werte werden manuell/aus Firebase eingetragen; leer = native Push inaktiv).
--   fcm_project_id            : Firebase Project-ID (aus der Firebase-Console)
--   fcm_service_account_path  : absoluter Pfad zur Service-Account-JSON AUSSERHALB des Web-Roots
-- (fcm_access_token / _exp werden vom Helper als OAuth-Cache selbst gepflegt.)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('fcm_project_id',           ''),
    ('fcm_service_account_path', '');
