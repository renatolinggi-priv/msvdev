-- Migration 044: JM-Umrechnung — Stiche mit Maxpunkte ueber 100 nicht mehr auf 100 umrechnen
-- Regel: Hochrechnung auf 100 nur bei Maxpunkte < 100. Ein Stich mit z.B. 120 Maxpunkten
-- zaehlt in allen JM-Ranglisten mit dem effektiven Resultat (bisher wurde er in einzelnen
-- Auswertungen auf 100 herunterskaliert).
-- Diese Migration passt die in der DB gespeicherten Wanderpreis-Regeln an
-- (auto_zuordnung.php fuehrt wanderpreise_regeln.sql_query direkt aus).
-- Der PHP-Code (jmrang/generate_pdf_jm.php, absenden/functions.inc.php,
-- wanderpreise/PDFReports.php) ist separat auf dieselbe Regel angepasst.
-- Idempotent: der NOT-LIKE-Guard verhindert doppeltes Ersetzen.

UPDATE `wanderpreise_regeln`
SET `sql_query` = REPLACE(`sql_query`,
    'WHEN jd.Maxpunkte > 0 THEN',
    'WHEN jd.Maxpunkte > 0 AND jd.Maxpunkte < 100 THEN')
WHERE `regel_code` IN ('jahresmeisterschaftA', 'jahresmeisterschaftB')
  AND `sql_query` NOT LIKE '%jd.Maxpunkte < 100%';
