-- Migration 038: Foto-Galerie pro JM-Anlass
--
-- Mitglieder laden Fotos zu einem Vereinsanlass hoch. Der "Anlass" ist ein
-- bestehender JM-Anlass (JMDefinition). Der Vorstand schaltet pro Anlass eine
-- Galerie frei und entscheidet pro Galerie, ob Fotos vor der Anzeige bewilligt
-- werden muessen (moderation_aktiv). Die Slideshow gruppiert die Fotos anhand
-- des EXIF-Aufnahmedatums nach den Schiesstagen des Anlasses.
--
-- Idempotent (IF NOT EXISTS / INSERT IGNORE / INSERT...SELECT), erneutes
-- Ausfuehren ist unschaedlich.

CREATE TABLE IF NOT EXISTS `anlass_galerie` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `jmdefinition_id`    INT NOT NULL,
  `freigeschaltet`     TINYINT(1) NOT NULL DEFAULT 1,   -- Galerie fuer Mitglieder sichtbar
  `upload_offen`       TINYINT(1) NOT NULL DEFAULT 1,   -- Mitglieder duerfen Fotos hochladen
  `moderation_aktiv`   TINYINT(1) NOT NULL DEFAULT 1,   -- Fotos erst nach Freigabe sichtbar
  `beschreibung`       TEXT DEFAULT NULL,
  `programm_dateiname` VARCHAR(255) DEFAULT NULL,        -- optionale Programm-PDF
  `programm_dateipfad` VARCHAR(255) DEFAULT NULL,
  `erstellt_von`       INT DEFAULT NULL,
  `erstellt_am`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_galerie_jmdef` (`jmdefinition_id`),
  CONSTRAINT `fk_galerie_jmdef` FOREIGN KEY (`jmdefinition_id`)
      REFERENCES `JMDefinition` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `anlass_fotos` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `galerie_id`      INT NOT NULL,
  `dateiname`       VARCHAR(255) NOT NULL,               -- gespeicherter Dateiname (Original/Full)
  `dateipfad`       VARCHAR(255) NOT NULL,               -- absoluter Pfad zur Full-Version
  `thumb_pfad`      VARCHAR(255) DEFAULT NULL,           -- absoluter Pfad zum Thumbnail
  `original_name`   VARCHAR(255) DEFAULT NULL,
  `dateigroesse`    INT DEFAULT NULL,
  `breite`          INT DEFAULT NULL,
  `hoehe`           INT DEFAULT NULL,
  `aufnahme_zeit`   DATETIME DEFAULT NULL,               -- aus EXIF DateTimeOriginal
  `zeit_quelle`     ENUM('exif','mtime','upload') DEFAULT NULL,
  `tag_datum`       DATE DEFAULT NULL,                   -- zugeordneter Anlass-Tag
  `tag_index`       INT DEFAULT NULL,                    -- Tag-Reihenfolge (NULL = ausserhalb der Schiesstage)
  `titel`           VARCHAR(255) DEFAULT NULL,
  `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `hochgeladen_von` INT DEFAULT NULL,
  `hochgeladen_am`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `moderiert_von`   INT DEFAULT NULL,
  `moderiert_am`    DATETIME DEFAULT NULL,
  `sortierung`      INT NOT NULL DEFAULT 0,
  KEY `ix_foto_galerie_status` (`galerie_id`, `status`),
  KEY `ix_foto_galerie_tag` (`galerie_id`, `tag_index`, `sortierung`),
  CONSTRAINT `fk_foto_galerie` FOREIGN KEY (`galerie_id`)
      REFERENCES `anlass_galerie` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Benachrichtigungs-Thema "Fotos" (Web Push)
ALTER TABLE `benachrichtigung_prefs`
    ADD COLUMN IF NOT EXISTS `fotos` TINYINT(1) NOT NULL DEFAULT 1 AFTER `einsatz_tausch`;

-- Globaler Schalter (analog jsk_betreuung_aktiv)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('fotogalerie_aktiv', '1');

-- Admin-Navigation: Eintrag "Foto-Galerien" unter "Definitionen / Ausdrucke"
-- (robust ohne hartkodierte Parent-ID, analog Migration 037).
INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie)
SELECT 'Foto-Galerien', 'anlass_galerie_verwaltung.php', x.parent_id, 90, 'bi-images', 0
FROM (
    SELECT
        (SELECT ID FROM navigation WHERE Text = 'Definitionen / Ausdrucke' AND ParentID = 0 ORDER BY ID LIMIT 1) AS parent_id,
        (SELECT COUNT(*) FROM navigation WHERE Link = 'anlass_galerie_verwaltung.php')                            AS vorhanden
) AS x
WHERE x.parent_id IS NOT NULL AND x.vorhanden = 0;
