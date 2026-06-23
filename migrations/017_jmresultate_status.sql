-- Migration 017: jmresultate Status-Felder fuer Mitglied-Selbsteingabe
-- Mitglieder koennen ihre JM-Resultate selbst im Portal eingeben (status='entwurf').
-- Freigabe (status='freigegeben') erfolgt implizit, sobald der Vorstand die
-- Resultaterfassung speichert (save_jmresultate.php) -> Mitglied kann nicht mehr aendern.

ALTER TABLE jmresultate
    ADD COLUMN status ENUM('entwurf','freigegeben') NOT NULL DEFAULT 'freigegeben' AFTER Info,
    ADD COLUMN eingegeben_von INT NULL AFTER status,
    ADD COLUMN eingegeben_am DATETIME NULL AFTER eingegeben_von,
    ADD COLUMN freigegeben_von INT NULL AFTER eingegeben_am,
    ADD COLUMN freigegeben_am DATETIME NULL AFTER freigegeben_von,
    ADD INDEX idx_jmresultate_status (status);

-- Bestehende Daten gelten als bereits freigegeben (Default deckt das implizit ab,
-- expliziter UPDATE fuer Klarheit)
UPDATE jmresultate SET status = 'freigegeben' WHERE status IS NULL OR status = '';
