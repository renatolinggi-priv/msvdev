-- Migration 062: Anfrage-Rolle «Büro» für Kurier und Znüni / Zvieri (Schlossturm-Pläne)
--
-- Migration 061 hatte beiden Funktionen die fälschliche Rolle «OK» genommen; ohne Rolle werden sie von der
-- automatischen Einteilung übersprungen. Neu: Rolle «Büro» – die Positionen werden aus den Personen besetzt,
-- die sich «als Büro» verfügbar gemeldet haben (ep_rolle_vorbelegung liefert dasselbe für künftige Importe).
-- Idempotent: nur Funktionen ohne Rolle werden gesetzt, gesetzte Rollen bleiben.

UPDATE einsatz_plan_funktionen f JOIN einsatz_plaene p ON p.id = f.plan_id
   SET f.rolle = 'Büro'
 WHERE p.typ = 'schlossturm' AND f.rolle IS NULL
   AND LOWER(f.bezeichnung) IN ('kurier', 'znüni / zvieri', 'znüni/zvieri');
