-- Migration 032: Chat (Jungschütze ↔ Leiter / Match), WhatsApp-Stil
--
-- 1. mitglieder.ist_jsk_leiter: markiert designierte Jungschützenleiter (gesetzt in der
--    Mitgliederverwaltung). Chat-Teilnehmer ist der verknüpfte Login (users.mitglied_id).
-- 2. chat_conversations: ein 1:1-Verlauf. typ='leiter' (js ↔ Leitung, partner_user_id NULL)
--    oder typ='match' (js ↔ betreuendes Mitglied). UNIQUE dedupt Match-Chats pro Paar;
--    Leiter-Chats (partner NULL) werden im Code find-or-create gehandhabt.
-- 3. chat_nachrichten: einzelne Nachrichten.
-- 4. chat_gelesen: Lese-/Ungelesen-Stand PRO Teilnehmer (mehrere Leiter möglich).
-- 5. benachrichtigung_prefs.chat: Push-Opt-In für Chat-Nachrichten (Default an).

ALTER TABLE `mitglieder`
    ADD COLUMN IF NOT EXISTS `ist_jsk_leiter` TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `chat_conversations` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `typ`             ENUM('leiter','match') NOT NULL,
    `js_user_id`      INT NOT NULL,
    `partner_user_id` INT DEFAULT NULL,
    `erstellt_am`     DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_message_at` DATETIME  DEFAULT NULL,
    UNIQUE KEY `uq_conv` (`typ`, `js_user_id`, `partner_user_id`),
    KEY `ix_conv_js` (`js_user_id`),
    KEY `ix_conv_partner` (`partner_user_id`),
    KEY `ix_conv_last` (`last_message_at`),
    CONSTRAINT `fk_conv_js` FOREIGN KEY (`js_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_conv_partner` FOREIGN KEY (`partner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_nachrichten` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `sender_user_id`  INT NOT NULL,
    `text`            VARCHAR(2000) NOT NULL,
    `erstellt_am`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `ix_msg_conv` (`conversation_id`, `id`),
    CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_gelesen` (
    `conversation_id`        INT NOT NULL,
    `user_id`                INT NOT NULL,
    `last_read_nachricht_id` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`conversation_id`, `user_id`),
    CONSTRAINT `fk_gelesen_conv` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gelesen_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `benachrichtigung_prefs`
    ADD COLUMN IF NOT EXISTS `chat` TINYINT(1) NOT NULL DEFAULT 1 AFTER `jsk_betreuung`;
