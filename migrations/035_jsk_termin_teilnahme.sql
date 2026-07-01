-- Migration 035: JSK-Termin-Teilnahme (RSVP)
--
-- Jungschuetze gibt pro (geflaggtem) Termin an, ob er teilnimmt. Standard = teilnehmen:
-- fehlt eine Zeile, gilt der Jungschuetze als teilnehmend. teilnahme=0 = explizite Absage.
-- Der Vorstand sieht daraus die Teilnehmerliste.

CREATE TABLE IF NOT EXISTS `jsk_termin_teilnahme` (
    `termin_id`       INT NOT NULL,
    `jungschuetze_id` INT NOT NULL,
    `teilnahme`       TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`termin_id`, `jungschuetze_id`),
    KEY `ix_tt_termin` (`termin_id`),
    CONSTRAINT `fk_tt_termin` FOREIGN KEY (`termin_id`) REFERENCES `wichtige_termine` (`ID`) ON DELETE CASCADE,
    CONSTRAINT `fk_tt_js` FOREIGN KEY (`jungschuetze_id`) REFERENCES `jungschuetzen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nav-Eintrag „JSK-Teilnehmerlisten" unter der JSK-Gruppe (idempotent, kein hartkodiertes ID)
INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie)
SELECT 'JSK-Teilnehmerlisten', 'jsk_teilnahme.php', x.jsk_id, 6, 'bi-check2-square', 0
FROM (
    SELECT
        (SELECT ID FROM navigation WHERE Text = 'JSK' AND ParentID = 0 ORDER BY ID LIMIT 1) AS jsk_id,
        (SELECT COUNT(*) FROM navigation WHERE Link = 'jsk_teilnahme.php')                   AS vorhanden
) AS x
WHERE x.jsk_id IS NOT NULL AND x.vorhanden = 0;
