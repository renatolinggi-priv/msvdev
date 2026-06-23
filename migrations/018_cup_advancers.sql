-- Migration 018: Cup Dreier-Gruppen - Anzahl Weiterkommende waehlbar
-- Bei Dreier-Paarungen kann pro Gruppe gewaehlt werden, ob 1 oder 2 Teilnehmer
-- weiterkommen. NULL = Default-Verhalten (2 kommen weiter), abwaertskompatibel.
-- Nur fuer 3er-Paarungen relevant; 2er-Paarungen bleiben NULL.

ALTER TABLE cupPairs
    ADD COLUMN Advancers TINYINT NULL DEFAULT NULL AFTER Participant3;

-- Bestehende Dreier-Paarungen behalten implizit "2 weiter" (COALESCE in den Queries).
