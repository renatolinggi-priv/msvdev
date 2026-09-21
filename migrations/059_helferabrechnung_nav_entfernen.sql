-- Migration 059: Menüpunkt «Helferabrechnung» wieder entfernen (aus 058).
-- Die Helferabrechnung ist keine eigene Seite mehr, sondern die Ansicht «Abrechnung» im Einsatzplan-Editor
-- (inc/einsatzplanung.php?id=…&ansicht=abrechnung, nur Pläne vom Typ schlossturm). Idempotent.
DELETE FROM navigation WHERE Link = 'helferabrechnung.php';
