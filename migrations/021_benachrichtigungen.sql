-- Migration 021: Push-Benachrichtigungen (Web Push) fuer das Mitgliederportal
-- Vorlage: benachrichtigungs-konzept.md
-- Fuehre dieses Script direkt auf der Datenbank aus.
--
-- Themen (einzeln pro Benutzer schaltbar): Einsaetze, Jahresmeisterschaft,
-- Umfrage-Fristen, Vereinstermine & Training.
--
-- FK-Ziel ist users.id (int(11), PRIMARY KEY) -> benutzer_id/user_id als INT.

-- ---------------------------------------------------------------------------
-- 1. Push-Abos: ein Geraet = ein Abo (eindeutiger endpoint)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `push_abos` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `benutzer_id`  INT NOT NULL,
    `endpoint`     VARCHAR(500) NOT NULL,
    `p256dh`       VARCHAR(255) NOT NULL,    -- Public Key des Browsers
    `auth_key`     VARCHAR(255) NOT NULL,    -- Auth-Secret des Browsers
    `geraet`       VARCHAR(100) DEFAULT NULL,-- User-Agent-Ausschnitt, nur fuers UI
    `erstellt_am`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_push_endpoint` (`endpoint`),
    KEY `ix_push_benutzer` (`benutzer_id`),
    CONSTRAINT `fk_push_benutzer` FOREIGN KEY (`benutzer_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. Schalter pro Benutzer (Spalten). Fehlende Zeile = alles an (via COALESCE).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `benachrichtigung_prefs` (
    `user_id`     INT NOT NULL PRIMARY KEY,
    `push_aktiv`  TINYINT(1) NOT NULL DEFAULT 1,   -- Haupt-Schalter
    `einsaetze`   TINYINT(1) NOT NULL DEFAULT 1,
    `jm`          TINYINT(1) NOT NULL DEFAULT 1,
    `umfragen`    TINYINT(1) NOT NULL DEFAULT 1,
    `termine`     TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_prefs_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Idempotenz-Marker: eine Zeile = "diesem User wurde dieses Vorkommnis
--    bereits gemeldet". Vermeidet ALTERs an den Quell-Tabellen.
--    termin_datum im UNIQUE-Key -> aendert sich das Event-Datum, wird neu erinnert.
--    ref_typ je Quelle eindeutig: einsatz | jm | umfrage | standbelegung | wichtig
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `benachrichtigung_log` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `benutzer_id`   INT NOT NULL,
    `kategorie`     VARCHAR(30) NOT NULL,   -- einsaetze | jm | umfragen | termine
    `ref_typ`       VARCHAR(30) NOT NULL,   -- Quell-Tabelle (s.o.)
    `ref_id`        INT NOT NULL,
    `termin_datum`  DATE NOT NULL,
    `gesendet_am`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_log` (`benutzer_id`, `ref_typ`, `ref_id`, `termin_datum`),
    KEY `ix_log_termin` (`termin_datum`),
    CONSTRAINT `fk_log_benutzer` FOREIGN KEY (`benutzer_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. Settings-Defaults (Lead-Time in Tagen pro Thema). settings-Tabelle aus 015.
--    VAPID-Keys + cron_trigger_key werden NICHT hier erzeugt, sondern einmalig
--    von tools/vapid_setup.php.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
    ('push_lead_einsaetze', '3'),
    ('push_lead_jm',        '2'),
    ('push_lead_umfragen',  '3'),
    ('push_lead_termine',   '2');
