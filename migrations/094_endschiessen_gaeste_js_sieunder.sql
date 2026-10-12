-- Migration 094: Endschiessen – Gäste und Jungschützen in den Resultat-Tabellen, Sie und Er als Paar
--
-- Bisher hatten nur Mitglieder Platz in endstich, schwini, kunst, glueck und zabig. Partnerinnen lagen
-- mit Freitext-Namen in endresultate_partner (nur Endstich, Schwini, Sie und Er) und hingen immer an einem
-- Mitglied. Jungschützen lagen in endstich_jung, schwini_jung und zabig_jung, für die es keine
-- Eingabeseite mehr gab.
--
-- Neu:
--   * Die Resultat-Tabellen tragen neben MitgliedID eine GastID (endstich_gaeste: Gäste und Jungschützen
--     aus «Stiche lösen», Jungschütze = Gast mit Geburtsdatum). Genau eine der beiden ist gesetzt.
--   * Sie und Er liegt pro Person in endstich_sieunder: fünf Schüsse und der Verweis auf die Partnerin
--     bzw. den Partner (Mitglied oder Gast). So lassen sich auch zwei Mitglieder als Paar führen.
--
-- Die bisherigen Daten werden übernommen:
--   * *_jung: JungschuetzeID ist schon die Gast-ID, also 1:1 in die Resultat-Tabellen.
--   * endresultate_partner: Endstich und Schwini der Partnerin als Gast-Resultate, Sie und Er als zwei
--     Zeilen (Schüsse 6 bis 10 beim Mitglied, 1 bis 5 bei der Partnerin) mit gegenseitigem Verweis.
--     Die Partnerin wird über den Namen gefunden: zuerst als Gast desselben Jahres, sonst als Mitglied
--     («Name Vorname»). Platzhalter «Partner» bleiben ohne Verweis und werden in der Erfassung verknüpft.
--     Unbekannte Namen werden als Gast angelegt, damit nichts verloren geht.
-- Die alten Tabellen bleiben unverändert als Sicherung stehen und werden nicht mehr gelesen.
-- Idempotent: kopiert wird nur, wo noch nichts steht.

ALTER TABLE endstich MODIFY MitgliedID INT(11) NULL DEFAULT NULL;
ALTER TABLE endstich ADD COLUMN IF NOT EXISTS GastID INT(11) NULL DEFAULT NULL AFTER MitgliedID;
ALTER TABLE endstich ADD INDEX IF NOT EXISTS idx_endstich_gast (GastID, Jahr);
ALTER TABLE endstich ADD CONSTRAINT fk_endstich_gast FOREIGN KEY IF NOT EXISTS (GastID) REFERENCES endstich_gaeste (id);

ALTER TABLE schwini MODIFY MitgliedID INT(11) NULL DEFAULT NULL;
ALTER TABLE schwini ADD COLUMN IF NOT EXISTS GastID INT(11) NULL DEFAULT NULL AFTER MitgliedID;
ALTER TABLE schwini ADD INDEX IF NOT EXISTS idx_schwini_gast (GastID, Jahr);
ALTER TABLE schwini ADD CONSTRAINT fk_schwini_gast FOREIGN KEY IF NOT EXISTS (GastID) REFERENCES endstich_gaeste (id);

ALTER TABLE kunst MODIFY MitgliedID INT(11) NULL DEFAULT NULL;
ALTER TABLE kunst ADD COLUMN IF NOT EXISTS GastID INT(11) NULL DEFAULT NULL AFTER MitgliedID;
ALTER TABLE kunst ADD INDEX IF NOT EXISTS idx_kunst_gast (GastID, Jahr);
ALTER TABLE kunst ADD CONSTRAINT fk_kunst_gast FOREIGN KEY IF NOT EXISTS (GastID) REFERENCES endstich_gaeste (id);

ALTER TABLE glueck MODIFY MitgliedID INT(11) NULL DEFAULT NULL;
ALTER TABLE glueck ADD COLUMN IF NOT EXISTS GastID INT(11) NULL DEFAULT NULL AFTER MitgliedID;
ALTER TABLE glueck ADD INDEX IF NOT EXISTS idx_glueck_gast (GastID, Jahr);
ALTER TABLE glueck ADD CONSTRAINT fk_glueck_gast FOREIGN KEY IF NOT EXISTS (GastID) REFERENCES endstich_gaeste (id);

ALTER TABLE zabig MODIFY MitgliedID INT(11) NULL DEFAULT NULL;
ALTER TABLE zabig ADD COLUMN IF NOT EXISTS GastID INT(11) NULL DEFAULT NULL AFTER MitgliedID;
ALTER TABLE zabig ADD INDEX IF NOT EXISTS idx_zabig_gast (GastID, Jahr);
ALTER TABLE zabig ADD CONSTRAINT fk_zabig_gast FOREIGN KEY IF NOT EXISTS (GastID) REFERENCES endstich_gaeste (id);

CREATE TABLE IF NOT EXISTS endstich_sieunder (
    ID INT(11) NOT NULL AUTO_INCREMENT,
    MitgliedID INT(11) NULL DEFAULT NULL,
    GastID INT(11) NULL DEFAULT NULL,
    Jahr INT(4) NOT NULL,
    Schuss1 TINYINT NULL DEFAULT NULL,
    Schuss2 TINYINT NULL DEFAULT NULL,
    Schuss3 TINYINT NULL DEFAULT NULL,
    Schuss4 TINYINT NULL DEFAULT NULL,
    Schuss5 TINYINT NULL DEFAULT NULL,
    PartnerMitgliedID INT(11) NULL DEFAULT NULL,
    PartnerGastID INT(11) NULL DEFAULT NULL,
    ErstelltAm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    GeaendertAm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    UNIQUE KEY uq_sieunder_mitglied (MitgliedID, Jahr),
    UNIQUE KEY uq_sieunder_gast (GastID, Jahr),
    KEY idx_sieunder_jahr (Jahr),
    KEY idx_sieunder_partner_m (PartnerMitgliedID),
    KEY idx_sieunder_partner_g (PartnerGastID),
    CONSTRAINT fk_sieunder_mitglied FOREIGN KEY (MitgliedID) REFERENCES mitglieder (ID),
    CONSTRAINT fk_sieunder_gast FOREIGN KEY (GastID) REFERENCES endstich_gaeste (id),
    CONSTRAINT fk_sieunder_partner_m FOREIGN KEY (PartnerMitgliedID) REFERENCES mitglieder (ID) ON DELETE SET NULL,
    CONSTRAINT fk_sieunder_partner_g FOREIGN KEY (PartnerGastID) REFERENCES endstich_gaeste (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Endschiessen Sie und Er: 5 Schüsse pro Person, Paar über den Partner-Verweis';

-- Jungschützen: *_jung → Resultat-Tabellen (JungschuetzeID = endstich_gaeste.id)
INSERT INTO endstich (MitgliedID, GastID, Jahr, Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, Schuss6, Schuss7, Schuss8, Schuss9, Schuss10, Tiefschuss, AbsendenAnmeldung)
SELECT NULL, j.JungschuetzeID, j.Jahr, j.Schuss1, j.Schuss2, j.Schuss3, j.Schuss4, j.Schuss5, j.Schuss6, j.Schuss7, j.Schuss8, j.Schuss9, j.Schuss10,
       COALESCE(j.Tiefschuss, 0),
       CASE WHEN TRIM(COALESCE(j.AbsendenAnmeldung, '')) REGEXP '^[0-9]+$' THEN TRIM(j.AbsendenAnmeldung) + 0 ELSE 0 END
FROM endstich_jung j
INNER JOIN endstich_gaeste g ON g.id = j.JungschuetzeID
WHERE NOT EXISTS (SELECT 1 FROM endstich e WHERE e.GastID = j.JungschuetzeID AND e.Jahr = j.Jahr);

INSERT INTO schwini (MitgliedID, GastID, Jahr, P1Schuss1, P1Schuss2, P1Schuss3, P1Schuss4, P1Schuss5, P1Schuss6, P2Schuss1, P2Schuss2, P2Schuss3, P2Schuss4, P2Schuss5, P2Schuss6)
SELECT NULL, j.JungschuetzeID, j.Jahr, j.P1Schuss1, j.P1Schuss2, j.P1Schuss3, j.P1Schuss4, j.P1Schuss5, j.P1Schuss6, j.P2Schuss1, j.P2Schuss2, j.P2Schuss3, j.P2Schuss4, j.P2Schuss5, j.P2Schuss6
FROM schwini_jung j
INNER JOIN endstich_gaeste g ON g.id = j.JungschuetzeID
WHERE NOT EXISTS (SELECT 1 FROM schwini s WHERE s.GastID = j.JungschuetzeID AND s.Jahr = j.Jahr);

INSERT INTO zabig (MitgliedID, GastID, Jahr, ZSchuss1, ZSchuss2, ZSchuss3, ZSchuss4, ZSchuss5, ZSchuss6, Ansage)
SELECT NULL, j.JungschuetzeID, j.Jahr, j.ZSchuss1, j.ZSchuss2, j.ZSchuss3, j.ZSchuss4, j.ZSchuss5, j.ZSchuss6, COALESCE(j.Ansage, 0)
FROM zabig_jung j
INNER JOIN endstich_gaeste g ON g.id = j.JungschuetzeID
WHERE NOT EXISTS (SELECT 1 FROM zabig z WHERE z.GastID = j.JungschuetzeID AND z.Jahr = j.Jahr);

-- Partnerinnen: unbekannte Namen als Gast anlegen (weder Gast desselben Jahres noch Mitglied)
INSERT INTO endstich_gaeste (name, jahr, created_by)
SELECT DISTINCT TRIM(ep.PartnerName), ep.Jahr, 'Migration 094'
FROM endresultate_partner ep
WHERE TRIM(ep.PartnerName) NOT IN ('', 'Partner')
  AND NOT EXISTS (SELECT 1 FROM endstich_gaeste g
                  WHERE g.jahr = ep.Jahr AND g.name COLLATE utf8mb4_unicode_ci = TRIM(ep.PartnerName) COLLATE utf8mb4_unicode_ci)
  AND NOT EXISTS (SELECT 1 FROM mitglieder m
                  WHERE CONCAT(m.Name, ' ', m.Vorname) COLLATE utf8mb4_unicode_ci = TRIM(ep.PartnerName) COLLATE utf8mb4_unicode_ci);

UPDATE endstich_gaeste SET mitgliedernr = 999000 + id WHERE mitgliedernr IS NULL;

-- Zuordnung Partnerzeile → Gast bzw. Mitglied (nur für diese Migration)
CREATE TEMPORARY TABLE IF NOT EXISTS mig094_partner AS
SELECT ep.ID AS ep_id,
       (SELECT g.id FROM endstich_gaeste g
         WHERE g.jahr = ep.Jahr AND g.name COLLATE utf8mb4_unicode_ci = TRIM(ep.PartnerName) COLLATE utf8mb4_unicode_ci
         ORDER BY g.id LIMIT 1) AS gast_id,
       (SELECT m.ID FROM mitglieder m
         WHERE CONCAT(m.Name, ' ', m.Vorname) COLLATE utf8mb4_unicode_ci = TRIM(ep.PartnerName) COLLATE utf8mb4_unicode_ci
           AND m.ID <> ep.MitgliedID
         ORDER BY m.ID LIMIT 1) AS mitglied_id
FROM endresultate_partner ep
WHERE TRIM(ep.PartnerName) NOT IN ('', 'Partner');

UPDATE mig094_partner SET mitglied_id = NULL WHERE gast_id IS NOT NULL;

-- Endstich und Schwini der Partnerinnen (nur Gäste: ein Mitglied hat seine eigenen Resultate)
INSERT INTO endstich (MitgliedID, GastID, Jahr, Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, Schuss6, Schuss7, Schuss8, Schuss9, Schuss10, Tiefschuss, AbsendenAnmeldung)
SELECT NULL, p.gast_id, ep.Jahr,
       ROUND(ep.EndstichSchuss1), ROUND(ep.EndstichSchuss2), ROUND(ep.EndstichSchuss3), ROUND(ep.EndstichSchuss4), ROUND(ep.EndstichSchuss5),
       ROUND(ep.EndstichSchuss6), ROUND(ep.EndstichSchuss7), ROUND(ep.EndstichSchuss8), ROUND(ep.EndstichSchuss9), ROUND(ep.EndstichSchuss10),
       0, 0
FROM endresultate_partner ep
INNER JOIN mig094_partner p ON p.ep_id = ep.ID
WHERE p.gast_id IS NOT NULL
  AND COALESCE(ep.EndstichSchuss1, 0) + COALESCE(ep.EndstichSchuss2, 0) + COALESCE(ep.EndstichSchuss3, 0) + COALESCE(ep.EndstichSchuss4, 0) + COALESCE(ep.EndstichSchuss5, 0)
    + COALESCE(ep.EndstichSchuss6, 0) + COALESCE(ep.EndstichSchuss7, 0) + COALESCE(ep.EndstichSchuss8, 0) + COALESCE(ep.EndstichSchuss9, 0) + COALESCE(ep.EndstichSchuss10, 0) > 0
  AND NOT EXISTS (SELECT 1 FROM endstich e WHERE e.GastID = p.gast_id AND e.Jahr = ep.Jahr);

INSERT INTO schwini (MitgliedID, GastID, Jahr, P1Schuss1, P1Schuss2, P1Schuss3, P1Schuss4, P1Schuss5, P1Schuss6, P2Schuss1, P2Schuss2, P2Schuss3, P2Schuss4, P2Schuss5, P2Schuss6)
SELECT NULL, p.gast_id, ep.Jahr,
       ROUND(ep.PartnerSchwiniSchuss1), ROUND(ep.PartnerSchwiniSchuss2), ROUND(ep.PartnerSchwiniSchuss3),
       ROUND(ep.PartnerSchwiniSchuss4), ROUND(ep.PartnerSchwiniSchuss5), ROUND(ep.PartnerSchwiniSchuss6),
       ROUND(ep.PartnerSchwiniSchuss7), ROUND(ep.PartnerSchwiniSchuss8), ROUND(ep.PartnerSchwiniSchuss9),
       ROUND(ep.PartnerSchwiniSchuss10), ROUND(ep.PartnerSchwiniSchuss11), ROUND(ep.PartnerSchwiniSchuss12)
FROM endresultate_partner ep
INNER JOIN mig094_partner p ON p.ep_id = ep.ID
WHERE p.gast_id IS NOT NULL
  AND COALESCE(ep.PartnerSchwiniSchuss1, 0) + COALESCE(ep.PartnerSchwiniSchuss2, 0) + COALESCE(ep.PartnerSchwiniSchuss3, 0)
    + COALESCE(ep.PartnerSchwiniSchuss4, 0) + COALESCE(ep.PartnerSchwiniSchuss5, 0) + COALESCE(ep.PartnerSchwiniSchuss6, 0)
    + COALESCE(ep.PartnerSchwiniSchuss7, 0) + COALESCE(ep.PartnerSchwiniSchuss8, 0) + COALESCE(ep.PartnerSchwiniSchuss9, 0)
    + COALESCE(ep.PartnerSchwiniSchuss10, 0) + COALESCE(ep.PartnerSchwiniSchuss11, 0) + COALESCE(ep.PartnerSchwiniSchuss12, 0) > 0
  AND NOT EXISTS (SELECT 1 FROM schwini s WHERE s.GastID = p.gast_id AND s.Jahr = ep.Jahr);

-- Sie und Er, Mitglied: Schüsse 6 bis 10 und Verweis auf die Partnerin
INSERT INTO endstich_sieunder (MitgliedID, GastID, Jahr, Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, PartnerMitgliedID, PartnerGastID)
SELECT ep.MitgliedID, NULL, ep.Jahr,
       NULLIF(ep.SieErSchuss6, 0), NULLIF(ep.SieErSchuss7, 0), NULLIF(ep.SieErSchuss8, 0), NULLIF(ep.SieErSchuss9, 0), NULLIF(ep.SieErSchuss10, 0),
       p.mitglied_id, p.gast_id
FROM endresultate_partner ep
LEFT JOIN mig094_partner p ON p.ep_id = ep.ID
WHERE (COALESCE(ep.SieErSchuss6, 0) + COALESCE(ep.SieErSchuss7, 0) + COALESCE(ep.SieErSchuss8, 0) + COALESCE(ep.SieErSchuss9, 0) + COALESCE(ep.SieErSchuss10, 0) > 0
       OR p.gast_id IS NOT NULL OR p.mitglied_id IS NOT NULL)
  AND NOT EXISTS (SELECT 1 FROM endstich_sieunder s WHERE s.MitgliedID = ep.MitgliedID AND s.Jahr = ep.Jahr);

-- Sie und Er, Partnerin als Gast: Schüsse 1 bis 5 und Verweis auf das Mitglied
INSERT INTO endstich_sieunder (MitgliedID, GastID, Jahr, Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, PartnerMitgliedID, PartnerGastID)
SELECT NULL, p.gast_id, ep.Jahr,
       NULLIF(ep.SieErSchuss1, 0), NULLIF(ep.SieErSchuss2, 0), NULLIF(ep.SieErSchuss3, 0), NULLIF(ep.SieErSchuss4, 0), NULLIF(ep.SieErSchuss5, 0),
       ep.MitgliedID, NULL
FROM endresultate_partner ep
INNER JOIN mig094_partner p ON p.ep_id = ep.ID
WHERE p.gast_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM endstich_sieunder s WHERE s.GastID = p.gast_id AND s.Jahr = ep.Jahr);

-- Sie und Er, Partnerin als Mitglied (z.B. Ehepaar, beide im Verein)
INSERT INTO endstich_sieunder (MitgliedID, GastID, Jahr, Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, PartnerMitgliedID, PartnerGastID)
SELECT p.mitglied_id, NULL, ep.Jahr,
       NULLIF(ep.SieErSchuss1, 0), NULLIF(ep.SieErSchuss2, 0), NULLIF(ep.SieErSchuss3, 0), NULLIF(ep.SieErSchuss4, 0), NULLIF(ep.SieErSchuss5, 0),
       ep.MitgliedID, NULL
FROM endresultate_partner ep
INNER JOIN mig094_partner p ON p.ep_id = ep.ID
WHERE p.mitglied_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM endstich_sieunder s WHERE s.MitgliedID = p.mitglied_id AND s.Jahr = ep.Jahr);

DROP TEMPORARY TABLE IF EXISTS mig094_partner;

-- Die Seite «Partnerinnen erfassen» entfällt: Partnerinnen stehen jetzt unter «Resultate erfassen»
DELETE FROM navigation WHERE Link = 'endresultate_partner.php';
