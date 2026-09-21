-- Migration 062: JSK-Review (Matching + Chat)
--
-- 1. chat_conversations.partner_key: generierte Spalte COALESCE(partner_user_id, 0), damit
--    der UNIQUE-Key auch Leiter-Chats (partner NULL) dedupt. Bisher konnten zwei gleichzeitige
--    Aufrufe (Seitenaufruf + Push-Deep-Link) zwei Leitungs-Chats fuer denselben Jungschuetzen
--    anlegen. Vorher werden allfaellige Duplikate zusammengefuehrt (Nachrichten in den aeltesten
--    Chat verschoben, Rest geloescht).
-- 2. chat_nachrichten.geloescht_am: eigene Nachricht innerhalb von 10 Minuten zuruecknehmen
--    (Soft-Delete, der Text bleibt fuer die Leitung/Admin in der DB).
--    ACHTUNG Runner: kein Semikolon in Kommentaren, der Splitter kennt keine --Kommentare.
-- 3. jsk_betreuung_anfragen.termin_id: Bezug zum ausgeschriebenen JSK-Termin (Schnellauswahl),
--    damit die Verwaltung Anfragen pro Trainingstermin sieht.
-- 4. settings.jsk_chat_leiter_einsicht: Leitung darf Match-Chats mitlesen (Default AUS, Admin
--    entscheidet in der JSK-Verwaltung).
-- 5. jsk_register_versuche: einfacher Schutz gegen Durchprobieren der JSK-Registrierung
--    (Limit pro IP und Stunde).

-- 1a. Duplikate der Leiter-Chats zusammenfuehren (aeltester Chat pro Jungschuetze bleibt)
UPDATE chat_nachrichten n
  JOIN chat_conversations c ON c.id = n.conversation_id
  JOIN (SELECT js_user_id, MIN(id) AS keep_id FROM chat_conversations WHERE typ = 'leiter' GROUP BY js_user_id) k
    ON k.js_user_id = c.js_user_id
   SET n.conversation_id = k.keep_id
 WHERE c.typ = 'leiter' AND c.id <> k.keep_id;

DELETE c FROM chat_conversations c
  JOIN (SELECT js_user_id, MIN(id) AS keep_id FROM chat_conversations WHERE typ = 'leiter' GROUP BY js_user_id) k
    ON k.js_user_id = c.js_user_id
 WHERE c.typ = 'leiter' AND c.id <> k.keep_id;

UPDATE chat_conversations c
   SET c.last_message_at = (SELECT MAX(n.erstellt_am) FROM chat_nachrichten n WHERE n.conversation_id = c.id)
 WHERE c.typ = 'leiter';

-- 1b. Generierte Spalte + neuer UNIQUE-Key
ALTER TABLE `chat_conversations`
    ADD COLUMN IF NOT EXISTS `partner_key` INT AS (COALESCE(`partner_user_id`, 0)) STORED;

ALTER TABLE `chat_conversations`
    DROP INDEX IF EXISTS `uq_conv`;

ALTER TABLE `chat_conversations`
    ADD UNIQUE KEY IF NOT EXISTS `uq_conv_key` (`typ`, `js_user_id`, `partner_key`);

-- 2. Soft-Delete fuer Nachrichten
ALTER TABLE `chat_nachrichten`
    ADD COLUMN IF NOT EXISTS `geloescht_am` DATETIME NULL DEFAULT NULL AFTER `erstellt_am`;

-- 3. Termin-Bezug der Anfrage
ALTER TABLE `jsk_betreuung_anfragen`
    ADD COLUMN IF NOT EXISTS `termin_id` INT NULL DEFAULT NULL AFTER `datum`;

ALTER TABLE `jsk_betreuung_anfragen`
    ADD INDEX IF NOT EXISTS `ix_jsk_termin` (`termin_id`);

-- 4. Schalter Leitungs-Einsicht (Default aus)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('jsk_chat_leiter_einsicht', '0');

-- 5. Registrierungs-Versuche (Rate-Limit)
CREATE TABLE IF NOT EXISTS `jsk_register_versuche` (
    `id`        INT AUTO_INCREMENT PRIMARY KEY,
    `ip`        VARCHAR(45) NOT NULL,
    `zeitpunkt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `ix_reg_ip_zeit` (`ip`, `zeitpunkt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
