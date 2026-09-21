-- Migration 061: Funktionen, die immer vom OK besetzt sind (Schlossturmschiessen)
--
-- Definition über alle Jahre in settings.einsatzplan_ok_funktionen (JSON-Liste normalisierter Funktionsnamen),
-- gepflegt im Editor-Dialog «OK-Mitglieder». Funktionen in der Liste erhalten die Rolle «OK»; alle ihre Positionen
-- zählen in Word und Helferabrechnung als OK (ep_slot_ist_ok). Start: EDV / Anlage, Schiessleitung.
--
-- Korrektur der Vorbelegung aus Migration 053: dort wurden auch «Kurier» und «Znüni / Zvieri» mit Rolle «OK» belegt.
-- Beide sind gemäss Abrechnung 2025/2026 normale Helferpositionen (nur einzelne Personen OK) – Rolle zurück auf NULL,
-- sonst würden ihre Positionen in der Abrechnung fälschlich als OK gelten. Idempotent.

INSERT INTO settings (setting_key, setting_value)
SELECT 'einsatzplan_ok_funktionen', '["edv / anlage","schiessleitung"]'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'einsatzplan_ok_funktionen');

UPDATE einsatz_plan_funktionen f JOIN einsatz_plaene p ON p.id = f.plan_id
   SET f.rolle = NULL
 WHERE p.typ = 'schlossturm' AND f.rolle = 'OK'
   AND LOWER(f.bezeichnung) NOT IN ('edv / anlage', 'edv/anlage', 'schiessleitung');
