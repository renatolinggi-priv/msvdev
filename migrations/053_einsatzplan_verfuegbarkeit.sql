-- Migration 053: Einsatzplanung – Verfügbarkeiten (Personalanfrage), Anfrage-Rollen, Umfrage-Verknüpfung,
-- Einteilungs-Vorschläge.
--
-- Schlossturmschiessen: Mitglieder aller drei Vereine melden, wann sie in welcher Rolle arbeiten können
-- (Portal-Umfrage «Schlossturmschiessen 2026», Excel «Personalanfrage», manuell). Daraus rechnet die App
-- einen Einteilungs-Vorschlag (Slots mit vorschlag = 1), den der Vorstand kontrolliert und übernimmt.
-- Idempotent.

-- Anfrage-Rolle je Funktion: 'Büro','Schützenmeister','Warner','Türkontrolle','Parkdienst','OK' (OK = fest
-- durch das OK gesetzt, keine automatische Einteilung); NULL = keine Rolle (Obli/Feld/Chilbi).
ALTER TABLE `einsatz_plan_funktionen` ADD COLUMN IF NOT EXISTS `rolle` VARCHAR(50) NULL AFTER `bezeichnung`;

-- Portal-Umfrage als Quelle der Verfügbarkeiten (umfragen.id, kategorie arbeitseinsatz/helfer)
ALTER TABLE `einsatz_plaene` ADD COLUMN IF NOT EXISTS `umfrage_id` INT NULL AFTER `quelle_dokument_id`;

-- Automatisch eingeteilt, noch nicht bestätigt (nicht im Portal/Word, nur im Editor sichtbar)
ALTER TABLE `einsatz_plan_slots` ADD COLUMN IF NOT EXISTS `vorschlag` TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `einsatz_plan_verfuegbarkeit` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `plan_id`         INT NOT NULL,
  `verein`          ENUM('msv','freienbach','wollerau') NOT NULL DEFAULT 'msv',
  `mitglied_id`     INT NULL,
  `name_text`       VARCHAR(100) NULL,
  `rollen`          TEXT NOT NULL,             -- JSON-Array, z.B. ["Warner","Büro"]
  `termin_ids`      TEXT NOT NULL,             -- JSON-Array, z.B. [12,14]
  `bemerkung`       VARCHAR(255) NULL,
  `quelle`          ENUM('umfrage','excel','manuell') NOT NULL DEFAULT 'manuell',
  `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_plan` (`plan_id`),
  KEY `idx_mitglied` (`mitglied_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Rollen-Vorbelegung für bestehende Schlossturm-Pläne (gleichnamig bzw. Gruppen Büro / OK)
UPDATE einsatz_plan_funktionen f JOIN einsatz_plaene p ON p.id = f.plan_id
   SET f.rolle = CASE
        WHEN f.bezeichnung IN ('Standblätter','Kasse','Munition','Auszahlung','Auszeichnungen') THEN 'Büro'
        WHEN f.bezeichnung IN ('EDV / Anlage','Schiessleitung','Kurier','Znüni / Zvieri') THEN 'OK'
        WHEN f.bezeichnung IN ('Schützenmeister','Warner','Türkontrolle','Parkdienst') THEN f.bezeichnung
        ELSE f.rolle END
 WHERE p.typ = 'schlossturm' AND f.rolle IS NULL;

-- Umfrage «Schlossturmschiessen {Jahr}» (kategorie arbeitseinsatz) mit dem gleichjährigen Schlossturm-Plan verknüpfen
UPDATE einsatz_plaene p
  JOIN umfragen u ON u.kategorie = 'arbeitseinsatz' AND u.titel LIKE CONCAT('%Schlossturm%', p.jahr, '%')
   SET p.umfrage_id = u.id
 WHERE p.typ = 'schlossturm' AND p.umfrage_id IS NULL;
