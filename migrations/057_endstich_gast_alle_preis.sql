-- Migration 057: Spezialpreis «Gäste: alle Stiche»
--
-- Löst ein Gast jeden für ihn lösbaren Stich (alle aktiven ausser Probeschüsse), gilt neu ein
-- frei definierbarer Pauschalpreis statt Kombi-Preis plus Einzelpreise. Gepflegt wird er im
-- Admin-Panel der Seite «Endschiessen lösen» unter «Spezialpreise».
-- Startwert 0 = Pauschale aus, es wird wie bisher gerechnet. Idempotent.

INSERT IGNORE INTO endstich_spezialpreise (typ, price_cents, beschreibung, sort_order, active) VALUES
  ('gast_alle', 0, 'Gäste: Pauschale, wenn alle Stiche gelöst werden (0 = aus)', 35, 1);
