-- Migration 096: Endschiessen – Mitglieder in der Partner-Rangliste
--
-- Die Partner-Rangliste (Endstich plus beste Schwini-Passe) umfasst die Gäste ohne Geburtsdatum. Ist eine
-- Partnerin bzw. ein Partner selbst Mitglied (z.B. ein Ehepaar, beide im Verein), lässt sich das Mitglied
-- unter «Endschiessen Resultate erfassen» pro Jahr zusätzlich in die Partner-Rangliste aufnehmen. In den
-- übrigen Ranglisten bleibt es wie bisher.
-- Idempotent.

CREATE TABLE IF NOT EXISTS endstich_partnerrang (
    MitgliedID INT(11) NOT NULL,
    Jahr INT(4) NOT NULL,
    ErstelltAm TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (MitgliedID, Jahr),
    KEY idx_partnerrang_jahr (Jahr),
    CONSTRAINT fk_partnerrang_mitglied FOREIGN KEY (MitgliedID) REFERENCES mitglieder (ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Endschiessen: Mitglieder, die zusätzlich in der Partner-Rangliste stehen';

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('endresultate.partnerrang',
 'Partner-Rangliste',
 '<p>Die <strong>Partner-Rangliste</strong> (Endstich plus beste Schwini-Passe) umfasst die Gäste. Ist eine Partnerin bzw. ein Partner selbst Mitglied, zum Beispiel bei einem Ehepaar, das beide im Verein sind, hier das Häkchen setzen: das Mitglied erscheint dann im gewählten Jahr zusätzlich in der Partner-Rangliste (PDF und Absendenbuch).</p><ul><li>In allen übrigen Ranglisten bleibt das Mitglied wie bisher.</li><li>In der Tabelle steht bei markierten Mitgliedern die Marke «Partner».</li><li>Das Häkchen gilt nur für das gewählte Jahr und wird zusammen mit den Resultaten gespeichert.</li></ul>',
 'endresultate')
ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<strong>Partner</strong>: Endstich plus beste Schwini-Passe der Gäste.',
  '<strong>Partner</strong>: Endstich plus beste Schwini-Passe der Gäste und der als Partnerin bzw. Partner markierten Mitglieder.')
WHERE schluessel = 'endschrang.dokumente';
