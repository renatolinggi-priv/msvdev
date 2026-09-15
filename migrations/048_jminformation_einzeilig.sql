-- Migration 048: JMInformation auf eine Zeile bereinigen
--
-- save_jminformation.php nutzte INSERT … ON DUPLICATE KEY UPDATE auf einer Tabelle, die
-- nur einen Primärschlüssel auf id hat. Jedes Speichern des Zusatztexts legte darum eine
-- neue Zeile an (Stand 15.09.2026 auf Prod: 237 Zeilen), gelesen wurde nur die neueste.
-- Der Code pflegt seit dem Review 09.2026 genau eine Zeile (jm_information_save()).
-- Hier: alle bis auf die jüngste Zeile entfernen. Idempotent.

DELETE FROM JMInformation
WHERE id < (SELECT m FROM (SELECT MAX(id) AS m FROM JMInformation) AS x);
