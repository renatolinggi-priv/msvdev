-- Mitglied-IDs ändern:
--   Schritt 1: 112102 → 112104
--   Schritt 2: 112098 → 112102
-- WICHTIG: Vor Ausführung Backup erstellen!
-- Prüfe zuerst ob die Ziel-ID noch frei ist:
SELECT ID FROM mitglieder WHERE ID = 112104;
-- Falls diese Query ein Ergebnis liefert, ist die ID bereits vergeben → ABBRECHEN!

-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;

-- =============================================
-- SCHRITT 1: 112102 → 112104
-- =============================================
UPDATE mitglieder SET ID = 112104 WHERE ID = 112102;

UPDATE endresultate_partner SET MitgliedID = 112104 WHERE MitgliedID = 112102;
UPDATE heimresultate SET MitgliedID = 112104 WHERE MitgliedID = 112102;
UPDATE JMDefinition_Gruppen SET mitgliederID = 112104 WHERE mitgliederID = 112102;
UPDATE wanderpreise SET gewinner_id = 112104 WHERE gewinner_id = 112102;
UPDATE wanderpreise_gewinner SET gewinner_id = 112104 WHERE gewinner_id = 112102;
UPDATE mitglieder_aenderungen SET mitglied_id = 112104 WHERE mitglied_id = 112102;
UPDATE einzelrangierungen SET mitglied_id = 112104 WHERE mitglied_id = 112102;
UPDATE endstich SET MitgliedID = 112104 WHERE MitgliedID = 112102;
UPDATE endstich_zusatz_schuss SET mitglied_id = 112104 WHERE mitglied_id = 112102;
UPDATE glueck SET MitgliedID = 112104 WHERE MitgliedID = 112102;
UPDATE jmresultate SET mitgliederID = 112104 WHERE mitgliederID = 112102;
UPDATE kantiresultate SET MitgliedID = 112104 WHERE MitgliedID = 112102;
UPDATE kunst SET MitgliedID = 112104 WHERE MitgliedID = 112102;
UPDATE munitionskauf SET mitglied_id = 112104 WHERE mitglied_id = 112102;
UPDATE schwini SET MitgliedID = 112104 WHERE MitgliedID = 112102;
UPDATE zabig SET MitgliedID = 112104 WHERE MitgliedID = 112102;
UPDATE users SET mitglied_id = 112104 WHERE mitglied_id = 112102;
UPDATE mitglieder_fragebogen SET mitgliedID = 112104 WHERE mitgliedID = 112102;

-- =============================================
-- SCHRITT 2: 112098 → 112102
-- =============================================
UPDATE mitglieder SET ID = 112102 WHERE ID = 112098;

-- 2. Alle abhängigen Tabellen manuell updaten
--    (nötig für FKs ohne ON UPDATE CASCADE)
UPDATE endresultate_partner SET MitgliedID = 112102 WHERE MitgliedID = 112098;
UPDATE heimresultate SET MitgliedID = 112102 WHERE MitgliedID = 112098;
UPDATE JMDefinition_Gruppen SET mitgliederID = 112102 WHERE mitgliederID = 112098;
UPDATE wanderpreise SET gewinner_id = 112102 WHERE gewinner_id = 112098;
UPDATE wanderpreise_gewinner SET gewinner_id = 112102 WHERE gewinner_id = 112098;
UPDATE mitglieder_aenderungen SET mitglied_id = 112102 WHERE mitglied_id = 112098;

-- 3. Tabellen mit ON UPDATE CASCADE (werden automatisch aktualisiert,
--    aber sicherheitshalber auch hier explizit updaten)
UPDATE einzelrangierungen SET mitglied_id = 112102 WHERE mitglied_id = 112098;
UPDATE endstich SET MitgliedID = 112102 WHERE MitgliedID = 112098;
UPDATE endstich_zusatz_schuss SET mitglied_id = 112102 WHERE mitglied_id = 112098;
UPDATE glueck SET MitgliedID = 112102 WHERE MitgliedID = 112098;
UPDATE jmresultate SET mitgliederID = 112102 WHERE mitgliederID = 112098;
UPDATE kantiresultate SET MitgliedID = 112102 WHERE MitgliedID = 112098;
UPDATE kunst SET MitgliedID = 112102 WHERE MitgliedID = 112098;
UPDATE munitionskauf SET mitglied_id = 112102 WHERE mitglied_id = 112098;
UPDATE schwini SET MitgliedID = 112102 WHERE MitgliedID = 112098;
UPDATE zabig SET MitgliedID = 112102 WHERE MitgliedID = 112098;
UPDATE users SET mitglied_id = 112102 WHERE mitglied_id = 112098;
UPDATE mitglieder_fragebogen SET mitgliedID = 112102 WHERE mitgliedID = 112098;

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;

-- Kontrolle: Neue IDs vorhanden, alte weg?
SELECT ID, Name, Vorname FROM mitglieder WHERE ID IN (112098, 112102, 112104);
