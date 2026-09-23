-- Migration 066: Hilfesystem – Tabelle hilfetexte + Menüpunkt «Hilfetexte»
--
-- Backing-Store für die «?»-Buttons im Admin-Bereich: pro stabilem Schlüssel
-- (Konvention seite.thema, z.B. jmdefinition.skalierung) ein Eintrag mit Titel und
-- sanitisiertem HTML. Anzeige über inc/js/msv-help.js (Klick = Modal, Hover = Kurzansicht),
-- Lookup über inc/hilfetexte/api.php (admin/vorstand), Pflege über inc/hilfetexte.php (nur admin).
-- Vorbild: Hilfesystem jungschuetzen.sksg.ch (jsk_hilfetexte).
--
-- Idempotent: CREATE IF NOT EXISTS, Menüpunkt nur einmal.

CREATE TABLE IF NOT EXISTS hilfetexte (
    id            INT NOT NULL AUTO_INCREMENT,
    schluessel    VARCHAR(100) NOT NULL COMMENT 'Stabiler Key, z.B. jmdefinition.skalierung',
    titel         VARCHAR(150) NOT NULL,
    inhalt_html   TEXT NOT NULL COMMENT 'Sanitisiertes HTML (Whitelist beim Speichern)',
    kategorie     VARCHAR(50) DEFAULT NULL COMMENT 'Gruppierung in der Admin-Liste, i.d.R. Seitenname',
    erstellt_am   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    geaendert_am  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    erstellt_von  INT DEFAULT NULL,
    geaendert_von INT DEFAULT NULL,
    ist_geloescht TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_schluessel (schluessel),
    KEY idx_kategorie (kategorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menüpunkt unter «Definitionen / Ausdrucke», Block Stammdaten (nach PDF-Vorlage 40, vor Trennlinie 50).
INSERT INTO navigation (Text, Link, ParentID, SortOrder, Icon, IstTrennlinie)
SELECT 'Hilfetexte', 'hilfetexte.php', x.parent_id, 45, 'bi-question-circle', 0
FROM (
    SELECT
        (SELECT ID FROM navigation WHERE Text = 'Definitionen / Ausdrucke' AND ParentID = 0 ORDER BY ID LIMIT 1) AS parent_id,
        (SELECT COUNT(*) FROM navigation WHERE Link = 'hilfetexte.php') AS vorhanden
) AS x
WHERE x.parent_id IS NOT NULL AND x.vorhanden = 0;
