-- Migration 118: Tippfehler in der Siegerliste Heimmeisterschaft Kat. B (Okt 2026, 1.68.39).
-- 2020 stand «Nicht druchgeführt»; alle übrigen Einträge dieser Art heissen «Nicht durchgeführt».
-- Sichtbar im Absendenbuch (Heimmeisterschaft Sieger seit 1997) und auf «Sieger der letzten Jahre».
--
-- Idempotent: ändert nur den falsch geschriebenen Wert.
-- Hinweis: keine Zeile innerhalb der Anweisung darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE sieger SET Name = 'Nicht durchgeführt'
 WHERE Name = 'Nicht druchgeführt';
