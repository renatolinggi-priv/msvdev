-- Migration 024: Beteiligungszuschlaege 2026 vom Vorjahr (2025) uebernehmen
-- Bei wiederkehrenden Schiessen wurde der Beteiligungszuschlag fuer 2026 noch
-- nicht erfasst (Zuschlag = 0), obwohl er im Vorjahr gesetzt war. Diese Migration
-- uebertraegt die Werte aus 2025 auf die entsprechenden 2026-Anlaesse.
--
-- Zuordnung manuell aus dem JMDefinition-Datenbestand (Stand 17.06.2026) abgeleitet,
-- da sich die Namen jaehrlich aendern (fortlaufende Nummer, Zusaetze wie "(Tuggen)").
-- Jede Anweisung trifft genau einen Anlass (year = 2026 + eindeutiger Namensteil).

-- 35. Schlossturmschiessen (2025, Zuschlag 2) -> 36. Schlossturmschiessen
UPDATE JMDefinition SET Zuschlag = 2
 WHERE year = 2026 AND Bezeichnung LIKE '%Schlossturmschiessen%';

-- 13. Rossbergschiessen (2025, Zuschlag 1) -> 14. Rossbergschiessen
UPDATE JMDefinition SET Zuschlag = 1
 WHERE year = 2026 AND Bezeichnung LIKE '%Rossbergschiessen%';

-- 27. Hirschflueschiessen (2025, Zuschlag 2) -> 28. Hirschflueschiessen
UPDATE JMDefinition SET Zuschlag = 2
 WHERE year = 2026 AND Bezeichnung LIKE '%Hirschflueschiessen%';

-- 40. Roggenstockschiessen (2025, Zuschlag 1) -> 41. Roggenstockschiessen
UPDATE JMDefinition SET Zuschlag = 1
 WHERE year = 2026 AND Bezeichnung LIKE '%Roggenstockschiessen%';

-- 14. Zuercher Oberlaender Maischiessen (2025, Zuschlag 2) -> 15. ... Maischiessen 2026
UPDATE JMDefinition SET Zuschlag = 2
 WHERE year = 2026 AND Bezeichnung LIKE '%Oberländer Maischiessen%';

-- RSV Schiessen March-Hoefe (2025, Zuschlag 2) -> RSV Schiessen March-Hoefe (Tuggen)
-- Bewusst spezifisch ("RSV Schiessen"), damit der separate
-- "Jubilaeumsstich 25 Jahre RSV March-Hoefe" NICHT mitgetroffen wird.
UPDATE JMDefinition SET Zuschlag = 2
 WHERE year = 2026 AND Bezeichnung LIKE '%RSV Schiessen March-Höfe%';
