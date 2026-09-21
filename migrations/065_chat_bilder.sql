-- Migration 065: Bilder im Jungschuetzenchat
--
-- chat_nachrichten.bild        Dateiname (JPG) unter portal/uploads/chat/<conversation_id>/,
--                              Thumbnail = gleicher Name mit Suffix _t. Auslieferung nur ueber
--                              api/chat_bild.php (Berechtigungspruefung), Direktzugriff auf
--                              portal/uploads ist per .htaccess gesperrt.
-- chat_nachrichten.bild_breite / bild_hoehe  Masse der Vollversion (Platzhalter ohne Layout-Sprung).
-- text darf bei Bildnachrichten leer sein (Bildunterschrift optional).

ALTER TABLE `chat_nachrichten`
    ADD COLUMN IF NOT EXISTS `bild`        VARCHAR(120) NULL DEFAULT NULL AFTER `text`,
    ADD COLUMN IF NOT EXISTS `bild_breite` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `bild`,
    ADD COLUMN IF NOT EXISTS `bild_hoehe`  SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `bild_breite`;
