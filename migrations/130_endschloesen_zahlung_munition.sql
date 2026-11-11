-- Migration 130: Zahlungsweg auch für reine Munitionskäufe (Endschiessen Stiche lösen, Nov 2026, 1.68.55).
-- Bisher stand der Zahlungsweg nur an den gelösten Stichen (endstich_selection.zahlungsmethode). Wer nur
-- Zusatzmunition kaufte, hatte keinen: die Seite zeigte «Karte», das Abrechnungs-PDF rechnete mit «Bar».
-- Ab jetzt trägt jede Zeile der Zusatzmunition den Zahlungsweg der Lösung. NULL = nicht erfasst
-- (Altdaten, Jungschützen gratis); die Seite zeigt das als «offen» statt einen Wert zu erfinden.
--
-- Idempotent (ADD COLUMN IF NOT EXISTS, MariaDB). API und PDF arbeiten auch ohne die Spalte weiter.

ALTER TABLE `endstich_zusatz_schuss`
  ADD COLUMN IF NOT EXISTS `zahlungsmethode` varchar(20) DEFAULT NULL AFTER `preis_cents`;

-- Wer Stiche UND Munition hat: Zahlungsweg der Stiche auf die Munition übernehmen
UPDATE `endstich_zusatz_schuss` z
  JOIN (SELECT mitglied_id, gast_id, jahr, MAX(zahlungsmethode) AS zahlungsmethode
          FROM `endstich_selection`
         WHERE zahlungsmethode IN ('bar', 'karte')
         GROUP BY mitglied_id, gast_id, jahr) s
    ON s.jahr = z.jahr
   AND ((z.mitglied_id IS NOT NULL AND s.mitglied_id = z.mitglied_id)
     OR (z.gast_id IS NOT NULL AND s.gast_id = z.gast_id))
   SET z.zahlungsmethode = s.zahlungsmethode
 WHERE z.zahlungsmethode IS NULL;
