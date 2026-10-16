-- Migration 120: Erinnerungen in der Glocke verfallen nach ihrem Termin (Okt 2026, 1.68.41).
-- Neue Spalte gilt_bis: Erinnerungen (Einsätze, JM-Tage, Termine, Umfragen) gelten bis zum Termin bzw. zur
-- Antwortfrist; danach zählen sie nicht mehr als «neu» (Glocke, Mitteilungen). Mitteilungen ohne Datum bleiben
-- neu, bis sie gelesen sind. Der Cron schreibt gilt_bis ab jetzt mit (cron/check_benachrichtigungen.php).
--
-- Bestehende Einträge: gilt_bis aus dem Protokoll der Erinnerungen (benachrichtigung_log), gleicher Benutzer und
-- gleiche Kategorie, zugestellt innerhalb von 5 Minuten um den Eintrag (der Cron schreibt beides direkt hintereinander).
--
-- Idempotent: ADD COLUMN IF NOT EXISTS; das UPDATE füllt nur leere Werte.
-- Hinweis: keine Zeile innerhalb der Anweisungen darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

ALTER TABLE benachrichtigungen_inbox ADD COLUMN IF NOT EXISTS gilt_bis DATE NULL DEFAULT NULL AFTER kategorie;

UPDATE benachrichtigungen_inbox i
   SET i.gilt_bis = (SELECT MAX(l.termin_datum)
                       FROM benachrichtigung_log l
                      WHERE l.benutzer_id = i.user_id
                        AND l.kategorie = i.kategorie
                        AND l.gesendet_am BETWEEN i.erstellt_am - INTERVAL 5 MINUTE AND i.erstellt_am + INTERVAL 5 MINUTE)
 WHERE i.gilt_bis IS NULL
   AND i.kategorie IN ('einsaetze', 'jm', 'termine', 'umfragen');
