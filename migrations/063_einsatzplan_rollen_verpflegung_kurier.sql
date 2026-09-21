-- Migration 063: eigene Anfrage-Rollen «Verpflegung» (Znüni / Zvieri) und «Kurier»
--
-- Beide erscheinen neu als Ankreuzfelder «als:» in der Personalanfrage (Excel-Export/-Import), in der
-- Umfrage-Erkennung und im Funktionen-Dialog (EP_ROLLEN in plan_helpers.inc.php). Die Funktionen der
-- bestehenden Schlossturm-Pläne erhalten die passende Rolle (ersetzt die Zwischenlösung «Büro» aus 062).
-- Idempotent: nur Funktionen ohne Rolle oder mit «Büro» werden umgestellt.

UPDATE einsatz_plan_funktionen f JOIN einsatz_plaene p ON p.id = f.plan_id
   SET f.rolle = 'Verpflegung'
 WHERE p.typ = 'schlossturm' AND (f.rolle IS NULL OR f.rolle = 'Büro')
   AND LOWER(f.bezeichnung) IN ('znüni / zvieri', 'znüni/zvieri', 'verpflegung');

UPDATE einsatz_plan_funktionen f JOIN einsatz_plaene p ON p.id = f.plan_id
   SET f.rolle = 'Kurier'
 WHERE p.typ = 'schlossturm' AND (f.rolle IS NULL OR f.rolle = 'Büro')
   AND LOWER(f.bezeichnung) = 'kurier';
